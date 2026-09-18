<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */
/**
 * \file        admin/upgrade_sql.php
 * \ingroup     sgpayroll
 * \brief       Generic SQL upgrade runner for SGPayroll.
 *
 * Scans custom/sgpayroll/sql/ for llx_sgpayroll_upgrade_*.sql files and lets
 * the admin inspect / execute them one at a time or all at once.
 *
 * Unlike the hard-coded upgrade_database.php, this page is driven entirely
 * by the SQL files on disk — drop a new upgrade_*.sql into sql/ and it
 * appears here automatically.
 */

$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php'))      { $res = @include __DIR__.'/../../main.inc.php'; }
if (!$res && file_exists(__DIR__.'/../../../main.inc.php'))    { $res = @include __DIR__.'/../../../main.inc.php'; }
if (!$res && file_exists(__DIR__.'/../../../../main.inc.php')) { $res = @include __DIR__.'/../../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
$libPath = dol_buildpath('/sgpayroll/lib/sgpayroll.lib.php', 0);
if (file_exists($libPath)) require_once $libPath;

if (!$user->admin) accessforbidden();

$langs->loadLangs(array('sgpayroll@sgpayroll', 'admin'));
if ($langs->trans('Module100Name') === 'Module100Name') {
	$langs->loadLangs(array('sgpayroll@custom/sgpayroll', 'admin'));
}

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'aZ09');

/* ──────────────────────────────────────────────────────────────────────────
 * Locate sql/ directory
 * ──────────────────────────────────────────────────────────────────────── */
$sqlDir = realpath(dirname(__DIR__).'/sql');
if (!$sqlDir) {
	$sqlDir = realpath(DOL_DOCUMENT_ROOT.'/custom/sgpayroll/sql');
}

/* ──────────────────────────────────────────────────────────────────────────
 * Discover upgrade SQL files
 *
 *   Upgrade files follow the pattern  llx_sgpayroll_upgrade_*.sql
 *   (any case-insensitive variant). Other sql files (base tables, cpf rates
 *   seeding, etc.) are listed separately so the admin can still inspect
 *   them, but the default upgrade action only touches upgrade_*.sql.
 * ──────────────────────────────────────────────────────────────────────── */
$upgradeFiles = array();
$otherFiles   = array();

if (is_dir($sqlDir)) {
	$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sqlDir, RecursiveDirectoryIterator::SKIP_DOTS));
	foreach ($rii as $f) {
		if (!$f->isFile()) continue;
		if (strtolower($f->getExtension()) !== 'sql') continue;
		$name = $f->getFilename();
		if (!preg_match('/^llx_sgpayroll_(.+)\.sql$/i', $name, $m)) continue;
		$rest = strtolower($m[1]);
		$entry = array(
			'path'  => $f->getRealPath() ?: $f->getPathname(),
			'name'  => $name,
			'size'  => $f->getSize(),
			'mtime' => $f->getMTime(),
		);
		if (strpos($rest, 'upgrade_') === 0) {
			$upgradeFiles[] = $entry;
		} else {
			$otherFiles[] = $entry;
		}
	}
	usort($upgradeFiles, function ($a, $b) { return strcmp($a['name'], $b['name']); });
	usort($otherFiles,   function ($a, $b) { return strcmp($a['name'], $b['name']); });
}

/* ──────────────────────────────────────────────────────────────────────────
 * Track which upgrades have already been applied
 *
 * Stored in llx_const as a comma-separated list of filenames. This lets
 * the admin see at a glance which files are new vs. already applied.
 * ──────────────────────────────────────────────────────────────────────── */
$constName = 'SGPAYROLL_SQL_UPGRADES_DONE';
$applied = array_values(array_filter(array_map('trim', explode(',', (string) getDolGlobalString($constName)))));

/* ──────────────────────────────────────────────────────────────────────────
 * Helpers
 * ──────────────────────────────────────────────────────────────────────── */

/**
 * Count the number of statements in a SQL file. Splits on ';' at end-of-line
 * (good enough for the sql files this module ships with; nothing fancy).
 *
 * @param  string  $path
 * @return int
 */
function sgpayroll_sql_count_statements($path)
{
	$src = @file_get_contents($path);
	if ($src === false) return 0;
	// Strip line comments starting with --
	$clean = preg_replace('/^\s*--.*$/m', '', $src);
	// Count semicolons that end a statement (not inside strings — fine for our SQL)
	$count = preg_match_all('/;\s*(\r?\n|$)/', $clean, $m);
	return (int) $count;
}

/**
 * Execute a single SQL file via Dolibarr's run_sql() helper.
 *
 * @param  string  $path  Absolute path to .sql
 * @return array          {ok:int, log:array<string>}
 */
function sgpayroll_sql_execute($path)
{
	global $db, $conf, $langs;

	$log = array();
	$ok = 0;
	$err = 0;

	ob_start();
	$res = run_sql($path, 0, $conf->entity, 1, 0, 'default', 32768, 0);
	$captured = ob_get_clean();
	if (!empty($captured)) {
		dol_syslog('sgpayroll_sql_execute: captured output from run_sql: '.substr($captured, 0, 500), LOG_DEBUG);
	}

	if ($res > 0) {
		$ok++;
		$log[] = array('success', $langs->trans('SqlRunFileSuccess', basename($path)));
	} else {
		$lasterror = $db->lasterror();
		// "already exists" / "duplicate" errors are expected when re-running
		// ALTER/INDEX statements — treat as success.
		if (strpos($lasterror, 'Duplicate column name') !== false
			|| strpos($lasterror, 'Duplicate key name') !== false
			|| strpos($lasterror, 'Duplicate entry') !== false
			|| strpos($lasterror, 'already exists') !== false) {
			$ok++;
			$log[] = array('success', $langs->trans('SqlRunFileIgnored', basename($path)));
		} else {
			$err++;
			$log[] = array('error', $langs->trans('SqlRunFileFailed', basename($path), $lasterror ?: $langs->trans('SqlRunFileUnknownError')));
		}
	}
	return array('ok' => $ok, 'err' => $err, 'log' => $log, 'res' => $res);
}

/* ──────────────────────────────────────────────────────────────────────────
 * Actions
 * ──────────────────────────────────────────────────────────────────────── */

if ($action === 'run_one' && $confirm === 'yes') {
	$token = GETPOST('token', 'none');
	if ($token !== newToken()) {
		setEventMessages($langs->trans('InvalidToken'), null, 'errors');
	} else {
		$file = GETPOST('file', 'alpha');
		$matched = null;
		foreach ($upgradeFiles as $u) {
			if ($u['name'] === $file) { $matched = $u; break; }
		}
		if (!$matched || !is_readable($matched['path'])) {
			dol_syslog('sgpayroll run_one: file='.var_export($file, true).' matched='.($matched ? 'yes' : 'no').(isset($matched['path']) ? ' path='.$matched['path'].' is_readable='.(is_readable($matched['path']) ? '1' : '0') : ''), LOG_WARNING);
			setEventMessages($langs->trans('SqlFileNotFound', dol_escape_htmltag($file)), null, 'errors');
		} else {
			$r = sgpayroll_sql_execute($matched['path']);
			if ($r['err'] === 0 && !in_array($matched['name'], $applied, true)) {
				$applied[] = $matched['name'];
				dolibarr_set_const($db, $constName, implode(',', $applied), 'chaine', 0, '', $conf->entity);
			}
			foreach ($r['log'] as $entry) {
				setEventMessages($entry[1], null, $entry[0] === 'error' ? 'errors' : 'mesgs');
			}
		}
	}
	header('Location: upgrade_sql.php'); exit;
}

if ($action === 'run_all' && $confirm === 'yes') {
	$token = GETPOST('token', 'none');
	if ($token !== newToken()) {
		setEventMessages($langs->trans('InvalidToken'), null, 'errors');
	} else {
		$totalOk = 0; $totalErr = 0;
		$pendingToRun = array();
		foreach ($upgradeFiles as $u) {
			if (in_array($u['name'], $applied, true)) continue;
			$pendingToRun[] = $u;
		}
		if (empty($pendingToRun)) {
			setEventMessages($langs->trans('SqlAllApplied'), null, 'mesgs');
		} else {
			foreach ($pendingToRun as $u) {
				$r = sgpayroll_sql_execute($u['path']);
				$totalOk += $r['ok'];
				$totalErr += $r['err'];
				if ($r['err'] === 0 && !in_array($u['name'], $applied, true)) {
					$applied[] = $u['name'];
				}
			}
			dolibarr_set_const($db, $constName, implode(',', $applied), 'chaine', 0, '', $conf->entity);
			if ($totalErr === 0) {
				setEventMessages($langs->trans('SqlRunAllDone', $totalOk), null, 'mesgs');
			} else {
				setEventMessages($langs->trans('SqlRunAllWithErrors', $totalErr, $totalOk), null, 'warnings');
			}
		}
	}
	header('Location: upgrade_sql.php'); exit;
}

if ($action === 'reset_history' && $confirm === 'yes') {
	$token = GETPOST('token', 'none');
	if ($token !== newToken()) {
		setEventMessages($langs->trans('InvalidToken'), null, 'errors');
	} else {
		dolibarr_del_const($db, $constName, $conf->entity);
		setEventMessages($langs->trans('SqlHistoryReset'), null, 'mesgs');
	}
	header('Location: upgrade_sql.php'); exit;
}

/* ──────────────────────────────────────────────────────────────────────────
 * Render
 * ──────────────────────────────────────────────────────────────────────── */
llxHeader('', $langs->trans('SGPayrollSetup').' - '.$langs->trans('SqlUpgradeRunner'), '');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('SGPayrollSetup').' — '.$langs->trans('SqlUpgradeRunner'), $linkback, 'sgpayroll@sgpayroll');

$head = sgpayrollAdminPrepareHead();
dol_fiche_head($head, 'upgrade', $langs->trans('SqlUpgradeRunner'), -1, 'sgpayroll@sgpayroll');

/* ── Header / instructions ── */
print '<div class="info" style="margin-bottom:1em">';
print '<strong>'.$langs->trans('SqlUpgradeRunner').'</strong>';
print '<p class="opacitymedium small">';
print $langs->trans('SqlUpgradeRunnerHelp');
print '</p>';
if (!$sqlDir) {
	print '<div class="warning">'.$langs->trans('SqlDirNotFound').'</div>';
}
print '</div>';

/* ── Bulk action bar ── */
$pendingCount = 0;
foreach ($upgradeFiles as $u) {
	if (!in_array($u['name'], $applied, true)) $pendingCount++;
}
print '<div class="tabsAction">';
if ($pendingCount > 0) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline">';
	print '<input type="hidden" name="action" value="run_all">';
	print '<input type="hidden" name="confirm" value="yes">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="submit" class="butAction" value="'.dol_escape_htmltag($langs->trans('SqlRunAllPending', $pendingCount)).'" onclick="return confirm(\''.dol_escape_js($langs->trans('SqlConfirmRunAll')).'\');">';
	print '</form>';
} else {
	print '<span class="opacitymedium"><em>'.$langs->trans('SqlAllApplied').'</em></span>';
}
if (!empty($applied)) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline;margin-left:8px">';
	print '<input type="hidden" name="action" value="reset_history">';
	print '<input type="hidden" name="confirm" value="yes">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="submit" class="butActionDelete" value="'.$langs->trans('SqlResetHistoryBtn').'" onclick="return confirm(\''.dol_escape_js($langs->trans('SqlConfirmReset')).'\');">';
	print '</form>';
}
print '</div>';

/* ── Upgrade files table ── */
print '<div class="div-table-responsive-no-min" style="margin-top:1em">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('SqlColumnFile').'</td>';
print '<td style="width:5em" class="center">'.$langs->trans('SqlColumnStatements').'</td>';
print '<td style="width:7em" class="center">'.$langs->trans('SqlColumnSize').'</td>';
print '<td style="width:12em">'.$langs->trans('SqlColumnModified').'</td>';
print '<td style="width:8em" class="center">'.$langs->trans('SqlColumnStatus').'</td>';
print '<td style="width:14em" class="center">'.$langs->trans('SqlColumnAction').'</td>';
print '</tr>';

if (empty($upgradeFiles)) {
	print '<tr class="oddeven"><td colspan="6" class="opacitymedium"><em>'.$langs->trans('SqlNoFilesMsg').'</em></td></tr>';
} else {
	foreach ($upgradeFiles as $u) {
		$isApplied = in_array($u['name'], $applied, true);
		$stCount = sgpayroll_sql_count_statements($u['path']);
		$sizeFmt = number_format($u['size'] / 1024, 1).' KB';
		$mtimeFmt = dol_print_date($u['mtime'], 'dayhour');
		print '<tr class="oddeven">';
		print '<td><code>'.dol_escape_htmltag($u['name']).'</code></td>';
		print '<td class="center">'.$stCount.'</td>';
		print '<td class="center">'.$sizeFmt.'</td>';
		print '<td>'.$mtimeFmt.'</td>';
		if ($isApplied) {
			print '<td class="center"><span style="color:green;font-weight:bold">'.$langs->trans('SqlStatusApplied').'</span></td>';
			print '<td class="center">';
			print '<a href="upgrade_sql.php?action=view&file='.urlencode($u['name']).'&token='.newToken().'" class="butAction">'.$langs->trans('SqlViewFile').'</a>';
			print '</td>';
		} else {
			print '<td class="center"><span style="color:orange;font-weight:bold">'.$langs->trans('SqlStatusPending').'</span></td>';
			print '<td class="center">';
			print '<a href="upgrade_sql.php?action=view&file='.urlencode($u['name']).'&token='.newToken().'" class="butAction">'.$langs->trans('SqlViewFile').'</a> ';
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline">';
			print '<input type="hidden" name="action" value="run_one">';
			print '<input type="hidden" name="confirm" value="yes">';
			print '<input type="hidden" name="file" value="'.dol_escape_htmltag($u['name']).'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="submit" class="butAction" value="'.$langs->trans('SqlRunFile').'" onclick="return confirm(\''.dol_escape_js($langs->trans('SqlConfirmRunOne', $u['name'])).'\');">';
			print '</form>';
			print '</td>';
		}
		print '</tr>';
	}
}
print '</table></div>';

/* ── Other SQL files (informational, not auto-run) ── */
if (!empty($otherFiles)) {
	print '<div class="div-table-responsive-no-min" style="margin-top:2em">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="4"><strong>'.$langs->trans('SqlOtherFilesHeader').'</strong></td></tr>';
	print '<tr class="liste_titre"><td>'.$langs->trans('SqlColumnFile').'</td><td class="center" style="width:5em">'.$langs->trans('SqlColumnStatements').'</td><td class="center" style="width:7em">'.$langs->trans('SqlColumnSize').'</td><td style="width:14em" class="center">'.$langs->trans('SqlColumnAction').'</td></tr>';
	foreach ($otherFiles as $u) {
		$stCount = sgpayroll_sql_count_statements($u['path']);
		$sizeFmt = number_format($u['size'] / 1024, 1).' KB';
		print '<tr class="oddeven">';
		print '<td><code>'.dol_escape_htmltag($u['name']).'</code></td>';
		print '<td class="center">'.$stCount.'</td>';
		print '<td class="center">'.$sizeFmt.'</td>';
		print '<td class="center">';
		print '<a href="upgrade_sql.php?action=view&file='.urlencode($u['name']).'&token='.newToken().'" class="butAction">'.$langs->trans('SqlViewFile').'</a>';
		print '</td>';
		print '</tr>';
	}
	print '</table></div>';
}

/* ── View single SQL file ── */
if ($action === 'view') {
	$token = GETPOST('token', 'none');
	if ($token !== newToken()) {
		setEventMessages($langs->trans('InvalidToken'), null, 'errors');
	} else {
		$file = GETPOST('file', 'alpha');
		$matched = null;
		foreach (array_merge($upgradeFiles, $otherFiles) as $u) {
			if ($u['name'] === $file) { $matched = $u; break; }
		}
		if (!$matched) {
			setEventMessages($langs->trans('SqlFileNotInList', dol_escape_htmltag($file)), null, 'errors');
		} else {
			print '<div class="div-table-responsive-no-min" style="margin-top:2em">';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre"><td><strong>'.$langs->trans('SqlViewHeader', dol_escape_htmltag($matched['name'])).'</strong></td></tr>';
			print '<tr class="oddeven"><td style="padding:0">';
			print '<pre style="max-height:500px;overflow:auto;margin:0;padding:12px;background:#f5f5f5;font-size:12px">';
			print dol_escape_htmltag(@file_get_contents($matched['path']));
			print '</pre>';
			print '</td></tr>';
			print '</table></div>';
		}
	}
}

dol_fiche_end();
llxFooter();
$db->close();
