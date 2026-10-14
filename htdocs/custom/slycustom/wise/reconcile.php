<?php
/* Copyright (C) 2026 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/slycustom/wise/reconcile.php
 * \ingroup slycustom
 * \brief   Wise incoming payments inbox — file-only view of
 *          DOL_DATA_ROOT/wise_webhook/accepted/*.json with per-dump sidecar
 *          state (<basename>.meta.json).
 *
 * 2.5.0 architectural pivot: the previous version listed rows from
 * llx_slycustom_wise_incoming and dispatched four mutating actions
 * (ignore / enrich / delete_incoming / bulk_delete_incoming) against that
 * table. That table is gone. The reconcile list now scans
 * wise_webhook/accepted/*.json and reads <basename>.meta.json for each row's
 * status / source / note / enriched fields / match. The four actions become:
 *
 *   - ignore:     write sidecar status=IGNORED (no DB write)
 *   - enrich:     read sidecar + main dump body, call Wise_API for the
 *                 statement, write sidecar status=ENRICHED + enriched fields
 *   - delete:     unlink the main dump + sidecar (no DB write)
 *   - bulk_delete: loop the above for each selected file
 *
 * Webhook deliveries and email-collector deliveries land in the same bucket
 * and render as one list. The "source" column (webhook / email) plus the
 * source filter are the only distinction.
 *
 * Access: admin or "wise/read". Mutating actions need "wise/write".
 */

$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php failed for SLY Custom wise/reconcile.php');
}

require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_Incoming.class.php';

$langs->loadLangs(array('bills', 'banks', 'compta', 'slycustom@slycustom'));

if (!$user->admin && !$user->hasRight('slycustom', 'wise', 'read')) {
	accessforbidden();
	exit;
}

$action = GETPOST('action', 'aZ09');
$bucket = GETPOST('bucket', 'aZ09');
$dumpFile = GETPOST('file', 'none');
$detailFile = $dumpFile; // ?file= is the detail-page key
$statusFilter = GETPOST('status', 'aZ09');
$sourceFilter = GETPOST('source', 'aZ09');
if (!in_array($statusFilter, array(
	WiseIncomingPayment::STATUS_NEW,
	WiseIncomingPayment::STATUS_ENRICHED,
	WiseIncomingPayment::STATUS_RECONCILED,
	WiseIncomingPayment::STATUS_IGNORED), true)) {
	$statusFilter = '';
}
if (!in_array($sourceFilter, array('webhook', 'email'), true)) {
	$sourceFilter = '';
}
if (!in_array($bucket, array('accepted', 'rejected'), true)) {
	$bucket = 'accepted';
}

if (in_array($action, array('ignore', 'enrich', 'delete_incoming', 'bulk_delete_incoming', 'mark_reconciled'), true)
	&& !$user->admin && !$user->hasRight('slycustom', 'wise', 'write')) {
	accessforbidden();
	exit;
}

$wp = new WiseIncomingPayment($db);

/*
 * Actions (POST with CSRF token; redirect after work to avoid resubmission).
 * The dump-file reference (bucket + file) is the entire "rowid" in 2.5.0 —
 * the only DB write is for transfers#state-change outgoing flow A, which
 * happens inside webhook/wise.php, not here.
 */
if ($action === 'ignore' && $dumpFile !== '') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$r = $wp->ignoreDump($bucket, $dumpFile);
		setEventMessages(($r['ok'] ? $langs->trans("WiseIgnoredOk") : $langs->trans("Error").' '.dol_escape_htmltag($r['message'])), null, $r['ok'] ? 'mesgs' : 'errors');
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?status='.urlencode($statusFilter).'&source='.urlencode($sourceFilter));
	exit;
}

if ($action === 'enrich' && $dumpFile !== '') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$r = $wp->enrichDumpFromStatement($bucket, $dumpFile);
		if ($r['ok'] && $r['enriched']) {
			setEventMessages($langs->trans("WiseEnrichedOk"), null, 'mesgs');
		} elseif ($r['ok']) {
			setEventMessages($langs->trans("WiseEnrichedPending"), null, 'warnings');
		} else {
			setEventMessages($langs->trans("WiseEnrichedKo").' '.dol_escape_htmltag($r['message']), null, 'errors');
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?file='.urlencode($dumpFile).'&bucket='.urlencode($bucket));
	exit;
}

// 2.5.0: mark_reconciled writes only the sidecar (status=RECONCILED +
// fk_paiement / fk_bank as banker-ref fields). The llx_paiement / llx_bank
// / llx_facture tables are NEVER written from this page (per the 2.4.0
// hard rule: "不要直接写入bank"). The payment chip in the list is therefore
// a reference, not a bookkeeping operation.
if ($action === 'mark_reconciled' && $dumpFile !== '') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$fkPaiement = (int) GETPOST('fk_paiement', 'int');
		$fkBank = (int) GETPOST('fk_bank', 'int');
		if ($fkPaiement <= 0 && $fkBank <= 0) {
			setEventMessages($langs->trans("WiseReconciledPickOne"), null, 'errors');
		} else {
			$r = $wp->markReconciledDump($bucket, $dumpFile, $fkPaiement, $fkBank);
			setEventMessages(($r['ok'] ? $langs->trans("WiseReconciledOk") : $langs->trans("Error").' '.dol_escape_htmltag($r['message'])), null, $r['ok'] ? 'mesgs' : 'errors');
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?status='.urlencode($statusFilter).'&source='.urlencode($sourceFilter));
	exit;
}

if ($action === 'delete_incoming' && $dumpFile !== '') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		// Delete is allowed for terminal states (RECONCILED, IGNORED) AND
		// for orphan NEW (no sidecar, no decision to preserve). The only
		// blockers are ENRICHED (live work in flight) and RECONCILED
		// (committed — must not be silently dropped without an operator
		// clicking through).
		$sidecar = $wp->readSidecar($bucket, $dumpFile);
		if (in_array($sidecar['status'], array(WiseIncomingPayment::STATUS_ENRICHED), true)) {
			setEventMessages($langs->trans("WiseIncomingDeleteNotAllowed"), null, 'errors');
		} else {
			$r = $wp->deleteDump($bucket, $dumpFile);
			setEventMessages(($r['ok'] ? $langs->trans("WiseDumpDeleted") : $langs->trans("WiseDumpDeleteFailed").' '.dol_escape_htmltag($r['message'])), null, $r['ok'] ? 'mesgs' : 'errors');
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?status='.urlencode($statusFilter).'&source='.urlencode($sourceFilter));
	exit;
}

if ($action === 'bulk_delete_incoming') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
		header('Location: '.$_SERVER["PHP_SELF"].'?status='.urlencode($statusFilter).'&source='.urlencode($sourceFilter));
		exit;
	}
	$files = GETPOST('files', 'array');
	$selected = array();
	if (is_array($files)) {
		foreach ($files as $rel) {
			$rel = (string) $rel;
			// `rel` is "<bucket>/<file>" — accept only same-bucket refs to
			// avoid any cross-bucket confusion.
			if (preg_match('#^accepted/[A-Za-z0-9._\-]+$#', $rel) || preg_match('#^rejected/[A-Za-z0-9._\-]+$#', $rel)) {
				$selected[] = $rel;
			}
		}
	}
	if (empty($selected)) {
		setEventMessages($langs->trans("WiseDumpBulkNoSelection"), null, 'warnings');
		header('Location: '.$_SERVER["PHP_SELF"].'?status='.urlencode($statusFilter).'&source='.urlencode($sourceFilter));
		exit;
	}
	$ok = 0; $ko = 0; $skipped = 0;
	$skippedReasons = array();
	foreach ($selected as $rel) {
		$b = substr($rel, 0, strpos($rel, '/'));
		$f = substr($rel, strlen($b) + 1);
		if (!WiseIncomingPayment::dumpPath($b, $f)) {
			$ko++;
			$skippedReasons[] = $rel.'=invalid';
			continue;
		}
		$sidecar = $wp->readSidecar($b, $f);
		if (in_array($sidecar['status'], array(WiseIncomingPayment::STATUS_ENRICHED), true)) {
			$skipped++;
			$skippedReasons[] = $rel.'=state_'.$sidecar['status'];
			continue;
		}
		$r = $wp->deleteDump($b, $f);
		if ($r['ok']) $ok++; else $ko++;
	}
	if ($ko === 0 && $skipped === 0) {
		setEventMessages($langs->trans("WiseDumpBulkDeletedOk", $ok), null, 'mesgs');
	} else {
		$failed = $ko + $skipped;
		setEventMessages($langs->trans("WiseDumpBulkPartial", $ok, $failed), null, 'warnings');
		if (!empty($skippedReasons)) {
			dol_syslog('slycustom.wise bulk_delete_incoming skipped: '.implode(',', $skippedReasons), LOG_WARNING);
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?status='.urlencode($statusFilter).'&source='.urlencode($sourceFilter));
	exit;
}

/*
 * View
 */
llxHeader('', $langs->trans("WiseInboxTitle"), '', '', 0, 0, '', '', '', 'mod-slycustom page-bankemail_inbox');

// Right of the title: a prominent "configure email collector" button so the
// IMAP host/user/password are findable in 1 click (was hidden in raw SQL
// before 2.5.0). The dumps link is kept as a secondary action.
$linkCollectorSetup = dol_buildpath('/custom/slycustom/admin/setup.php', 1).'?tab=wise&mainmenu=tools';
$linkDumps = dol_buildpath('/custom/slycustom/wise/dumps.php', 1).'?mainmenu=tools';
$titleRight = ''
	.'<a class="butAction" href="'.$linkCollectorSetup.'">'
		.img_picto('', 'mail', 'class="paddingright pictofixedwidth valignmiddle"')
		.$langs->trans("BankEmailCollectorSetup")
	.'</a> '
	.'<a class="butAction" href="'.$linkDumps.'">'
		.img_picto('', 'trash', 'class="paddingright pictofixedwidth valignmiddle"')
		.$langs->trans("WiseDumpsTitle")
	.'</a>';
print load_fiche_titre($langs->trans("WiseInboxTitle"), $titleRight, 'payment');

$dumps = $wp->listDumps((int) $conf->entity);
$acceptedRows = isset($dumps['accepted']) ? $dumps['accepted'] : array();

// Status / source counts (in-memory; only 200 files max are kept per bucket
// by the rotation, so this stays cheap).
$counts = array();
$sourceCounts = array('webhook' => 0, 'email' => 0);
foreach ($acceptedRows as $row) {
	$st = isset($row['status']) ? $row['status'] : WiseIncomingPayment::STATUS_NEW;
	$counts[$st] = isset($counts[$st]) ? $counts[$st] + 1 : 1;
	$src = isset($row['source']) ? $row['source'] : 'webhook';
	if (isset($sourceCounts[$src])) $sourceCounts[$src]++;
}
$queueTotal = array_sum($counts);
$rejTotal = isset($dumps['rejected']) ? count($dumps['rejected']) : 0;

// Single compact filter row: status chips + source chips inline. Kept
// deliberately short — the 2.4.2 layout stacked 7 chips across 2 rows
// which users called "chaotic". 2.5.0 collapses to one row.
print '<div class="filterbar">';
print '<span class="opacitymedium">'.$langs->trans("WiseInboxCount", $queueTotal).'</span> ';
print '<span class="opacitymedium">·</span> ';
print '<span class="opacitymedium">'.$langs->trans("WiseDumpsRejectedCount", $rejTotal).'</span> ';
print '<span class="opacitymedium">·</span> ';
print '<span class="opacitymedium">'.$langs->trans("WiseSourceFilter").':</span> ';
$statusList = array(
	WiseIncomingPayment::STATUS_NEW => $langs->trans("WiseStatusNew"),
	WiseIncomingPayment::STATUS_ENRICHED => $langs->trans("WiseStatusEnriched"),
	WiseIncomingPayment::STATUS_RECONCILED => $langs->trans("WiseStatusReconciled"),
	WiseIncomingPayment::STATUS_IGNORED => $langs->trans("WiseStatusIgnored"),
);
$chipBase = ' class="butAction inline-block" style="margin:0 2px;padding:2px 6px;font-size:0.9em;"';
$allCss = ($statusFilter === '' && $sourceFilter === '') ? $chipBase.' class-refused' : $chipBase;
print '<a'.$allCss.' href="'.$_SERVER["PHP_SELF"].'">'.$langs->trans("All").'</a>';
foreach ($statusList as $st => $label) {
	$n = isset($counts[$st]) ? $counts[$st] : 0;
	$css = ($statusFilter === $st && $sourceFilter === '') ? $chipBase.' class-refused' : $chipBase;
	print '<a'.$css.' href="'.$_SERVER["PHP_SELF"].'?status='.urlencode($st).'">'.dol_escape_htmltag($label).' ('.$n.')</a>';
}
foreach (array('webhook' => $langs->trans("WiseSourceWebhook"), 'email' => $langs->trans("WiseSourceEmail")) as $srcKey => $srcLabel) {
	$n = isset($sourceCounts[$srcKey]) ? (int) $sourceCounts[$srcKey] : 0;
	$css = ($sourceFilter === $srcKey && $statusFilter === '') ? $chipBase.' class-refused' : $chipBase;
	print '<a'.$css.' href="'.$_SERVER["PHP_SELF"].'?source='.urlencode($srcKey).'">'.dol_escape_htmltag($srcLabel).' ('.$n.')</a>';
}
print '</div>';

// Detail view: one dump + read-only bank/paiement candidates.
if ($detailFile !== '') {
	$detailPath = WiseIncomingPayment::dumpPath($bucket, $detailFile);
	$dumpBody = null;
	$rawDump = null;
	if ($detailPath !== null && is_file($detailPath)) {
		$rawDump = json_decode((string) @file_get_contents($detailPath), true);
		if (is_array($rawDump)) {
			$bodyStr = isset($rawDump['body']) ? (string) $rawDump['body'] : '';
			$decoded = json_decode($bodyStr, true);
			if (is_array($decoded)) {
				$dumpBody = $decoded;
			}
		}
	}
	$sidecar = $wp->readSidecar($bucket, $detailFile);
	$canWrite = ($user->admin || $user->hasRight('slycustom', 'wise', 'write'));

	// Normalise the summary view of the dump (the same shape listDumps
	// uses for the list rows, so the bank/paiement matchers can be called
	// uniformly).
	$summary = array(
		'file' => $detailFile,
		'bucket' => $bucket,
		'entity' => is_array($rawDump) && isset($rawDump['entity']) ? (int) $rawDump['entity'] : (int) $conf->entity,
		'amount' => is_array($dumpBody) && isset($dumpBody['data']['amount']) ? (float) $dumpBody['data']['amount'] : null,
		'currency' => is_array($dumpBody) && isset($dumpBody['data']['currency']) ? strtoupper((string) $dumpBody['data']['currency']) : '',
		'occurred_at' => is_array($dumpBody) && isset($dumpBody['data']['occurred_at']) ? (string) $dumpBody['data']['occurred_at'] : '',
		'event_type' => is_array($dumpBody) && isset($dumpBody['event_type']) ? (string) $dumpBody['event_type'] : '',
		'ref_text' => isset($sidecar['enriched']['ref_text']) ? (string) $sidecar['enriched']['ref_text'] : '',
		'counterparty' => isset($sidecar['enriched']['counterparty']) ? (string) $sidecar['enriched']['counterparty'] : '',
		'fees' => isset($sidecar['enriched']['fees']) ? (float) $sidecar['enriched']['fees'] : null,
		'status' => $sidecar['status'],
		'source' => $sidecar['source'],
		'note' => $sidecar['note'],
		'fk_paiement' => $sidecar['fk_paiement'],
		'fk_bank' => isset($sidecar['fk_bank']) ? (int) $sidecar['fk_bank'] : 0,
		'received_at' => is_array($rawDump) && isset($rawDump['received_at']) ? (int) $rawDump['received_at'] : 0,
	);

	if ($detailPath === null || !is_file($detailPath)) {
		print '<div class="error">'.$langs->trans("ErrorFileNotFound", $detailFile).'</div>';
	} else {
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td colspan="4">'.$langs->trans("WiseCreditDetail").' · '.dol_escape_htmltag($detailFile).'</td></tr>';
		$occTs = WiseIncomingPayment::sqlUtcToTimestamp(WiseIncomingPayment::isoToSqlUtc($summary['occurred_at']));
		print '<tr class="oddeven"><td class="titlefield">'.$langs->trans("Date").'</td><td>'.($occTs ? dol_print_date($occTs, 'dayhour', 'tzuserrel') : '-').'</td>';
		print '<td>'.$langs->trans("Amount").'</td><td><strong>'.($summary['amount'] !== null ? price($summary['amount'], 2, '', 1, -1, -1, $summary['currency']) : '-').'</strong></td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans("Ref").'</td><td>'.($summary['ref_text'] !== '' ? dol_escape_htmltag($summary['ref_text']) : '<span class="opacitymedium">'.$langs->trans("None").'</span>').'</td>';
		print '<td>'.$langs->trans("ThirdParty").'</td><td>'.($summary['counterparty'] !== '' ? dol_escape_htmltag($summary['counterparty']) : '<span class="opacitymedium">'.$langs->trans("None").'</span>').'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans("Status").'</td><td>'.dol_escape_htmltag($summary['status']).'</td>';
		print '<td>'.$langs->trans("WiseSource").'</td><td>'.dol_escape_htmltag($summary['source']).'</td></tr>';
		if ($summary['fees'] !== null) {
			print '<tr class="oddeven"><td>'.$langs->trans("Fees").'</td><td colspan="3">'.price($summary['fees']).'</td></tr>';
		}
		if (!empty($sidecar['note'])) {
			print '<tr class="oddeven"><td>'.$langs->trans("Note").'</td><td colspan="3">'.dol_escape_htmltag($sidecar['note']).'</td></tr>';
		}
		if (!empty($sidecar['match']['sos'])) {
			$soLinks = array();
			foreach ($sidecar['match']['sos'] as $so) {
				$soLinks[] = '<a href="'.DOL_URL_ROOT.'/commande/card.php?id='.(int) $so['id'].'">'.dol_escape_htmltag($so['ref']).'</a>';
			}
			print '<tr class="oddeven"><td>'.$langs->trans("WiseMatchedSOs").'</td><td colspan="3">'.implode(', ', $soLinks).'</td></tr>';
		}
		if (!empty($sidecar['match']['soc']['id'])) {
			$soc = $sidecar['match']['soc'];
			print '<tr class="oddeven"><td>'.$langs->trans("ThirdParty").'</td><td colspan="3"><a href="'.DOL_URL_ROOT.'/societe/card.php?id='.(int) $soc['id'].'">'.dol_escape_htmltag($soc['name']).'</a></td></tr>';
		}
		print '</table></div>';
		print '<br>';

		// Mutating actions.
		if ($canWrite) {
			print '<div class="tabsAction">';
			if ($summary['status'] !== WiseIncomingPayment::STATUS_RECONCILED) {
				// enrich: only available for webhook deliveries (the
				// email collector already extracts the fields via
				// template, so a Wise-API call is redundant).
				if ($summary['source'] === 'webhook' && $summary['event_type'] !== '' && strpos($summary['event_type'], 'transfers#') !== 0) {
					print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
					print '<input type="hidden" name="token" value="'.newToken().'">';
					print '<input type="hidden" name="action" value="enrich">';
					print '<input type="hidden" name="bucket" value="'.dol_escape_htmltag($bucket).'">';
					print '<input type="hidden" name="file" value="'.dol_escape_htmltag($detailFile).'">';
					print '<button class="butAction" type="submit">'.$langs->trans("WiseReenrich").'</button>';
					print '</form> ';
				}
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="ignore">';
				print '<input type="hidden" name="bucket" value="'.dol_escape_htmltag($bucket).'">';
				print '<input type="hidden" name="file" value="'.dol_escape_htmltag($detailFile).'">';
				print '<button class="butActionDelete" type="submit">'.$langs->trans("WiseIgnore").'</button>';
				print '</form>';
			}
			// mark_reconciled: live + reconciled rows. form posts fk_paiement
			// and fk_bank as numbers; the user picks from the bank/paiement
			// candidate tables below.
			if ($summary['status'] !== WiseIncomingPayment::STATUS_RECONCILED) {
				print ' <a class="butAction" href="#mark_reconcile">'.$langs->trans("WiseMarkReconciled").'</a>';
			}
			if (!in_array($summary['status'], array(WiseIncomingPayment::STATUS_ENRICHED), true)) {
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;" onsubmit="return confirm(\''.dol_escape_js($langs->trans("WiseIncomingDeleteConfirm")).'\');">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="delete_incoming">';
				print '<input type="hidden" name="bucket" value="'.dol_escape_htmltag($bucket).'">';
				print '<input type="hidden" name="file" value="'.dol_escape_htmltag($detailFile).'">';
				print '<button class="butActionDelete" type="submit">'.$langs->trans("WiseIncomingDelete").'</button>';
				print '</form>';
			}
			print '</div>';
		}

		// Bank reconciliation panel + mark_reconciled form.
		$bankMatches = $wp->findBankMatches($summary, 3, 30.0);
		$paiementMatches = $wp->findPaiementMatches($summary, 3, 30.0);
		$hasAnyMatch = !empty($bankMatches) || !empty($paiementMatches);

		if ($summary['fk_paiement'] > 0) {
			print '<br><p><span class="badge badge-status4 badge-status">'.$langs->trans("WiseBankLinked").'</span> ';
			print $langs->trans("WiseBankLinkedDetail");
			print ' <a href="'.DOL_URL_ROOT.'/compta/paiement/card.php?id='.(int) $summary['fk_paiement'].'">Paiement #'.(int) $summary['fk_paiement'].'</a></p>';
		}

		if (!$hasAnyMatch && $summary['fk_paiement'] <= 0 && $summary['status'] !== WiseIncomingPayment::STATUS_RECONCILED) {
			print '<br><div class="fiche"><div class="titre">'.$langs->trans("WiseBankMatchTitle").'</div>';
			print '<p><span class="opacitymedium">'.$langs->trans("WiseBankMatchNone").'</span></p></div>';
		} else {
			print '<br><div class="fiche" id="mark_reconcile">';
			print '<div class="titre">'.$langs->trans("WiseBankMatchTitle").'</div>';

			if ($canWrite && $summary['status'] !== WiseIncomingPayment::STATUS_RECONCILED && $hasAnyMatch) {
				// Mark-reconciled form: pick exactly one of fk_paiement /
				// fk_bank (the radio buttons live next to each candidate
				// row). Empty submit (no candidate chosen) is silently
				// rejected by the action handler with WiseReconciledPickOne.
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="mark_reconciled">';
				print '<input type="hidden" name="bucket" value="'.dol_escape_htmltag($bucket).'">';
				print '<input type="hidden" name="file" value="'.dol_escape_htmltag($detailFile).'">';
			}

			if (!empty($bankMatches)) {
				print '<div class="div-table-responsive-no-min" style="margin-bottom:8px;">';
				print '<table class="noborder centpercent">';
				print '<tr class="liste_titre"><td>'.$langs->trans("WisePick").'</td><td>#</td><td>'.$langs->trans("Date").'</td>';
				print '<td class="right">'.$langs->trans("Amount").'</td>';
				print '<td>'.$langs->trans("Currency").'</td>';
				print '<td>'.$langs->trans("Account").'</td><td>'.$langs->trans("Label").'</td>';
				print '<td class="right">'.$langs->trans("Diff").'</td></tr>';
				foreach ($bankMatches as $b) {
					$diff = abs(abs((float) $b['amount']) - (float) $summary['amount']);
					$bankUrl = DOL_URL_ROOT.'/compta/bank/line.php?rowid='.(int) $b['rowid'];
					print '<tr class="oddeven">';
					if ($canWrite && $summary['status'] !== WiseIncomingPayment::STATUS_RECONCILED) {
						print '<td><input type="radio" name="fk_bank" value="'.(int) $b['rowid'].'"></td>';
					} else {
						print '<td></td>';
					}
					print '<td><a href="'.$bankUrl.'">#'.(int) $b['rowid'].'</a></td>';
					print '<td>'.dol_print_date((int) $b['datev'], 'day', 'tzuserrel').'</td>';
					print '<td class="right">'.price(abs((float) $b['amount'])).'</td>';
					print '<td>'.dol_escape_htmltag($b['currency_code']).'</td>';
					print '<td>'.dol_escape_htmltag($b['account_label']).'</td>';
					print '<td>'.dol_escape_htmltag(dol_trunc((string) $b['label'], 60)).'</td>';
					print '<td class="right">'.($diff < 0.01 ? '<span class="badge badge-status4">0</span>' : price($diff)).'</td>';
					print '</tr>';
				}
				print '</table></div>';
			}

			if (!empty($paiementMatches)) {
				print '<div class="div-table-responsive-no-min" style="margin-bottom:8px;">';
				print '<table class="noborder centpercent">';
				print '<tr class="liste_titre"><td>'.$langs->trans("WisePick").'</td><td>#</td><td>'.$langs->trans("Date").'</td>';
				print '<td class="right">'.$langs->trans("Amount").'</td>';
				print '<td class="right">'.$langs->trans("Diff").'</td></tr>';
				foreach ($paiementMatches as $pm) {
					$diff = abs((float) $pm['amount'] - (float) $summary['amount']);
					$pmUrl = DOL_URL_ROOT.'/compta/paiement/card.php?id='.(int) $pm['rowid'];
					print '<tr class="oddeven">';
					if ($canWrite && $summary['status'] !== WiseIncomingPayment::STATUS_RECONCILED) {
						print '<td><input type="radio" name="fk_paiement" value="'.(int) $pm['rowid'].'"></td>';
					} else {
						print '<td></td>';
					}
					print '<td><a href="'.$pmUrl.'">Paiement #'.(int) $pm['rowid'].'</a></td>';
					print '<td>'.dol_print_date((int) $pm['datep'], 'day', 'tzuserrel').'</td>';
					print '<td class="right">'.price((float) $pm['amount']).'</td>';
					print '<td class="right">'.price($diff).'</td>';
					print '</tr>';
				}
				print '</table></div>';
			}

			if ($canWrite && $summary['status'] !== WiseIncomingPayment::STATUS_RECONCILED && $hasAnyMatch) {
				print '<div class="tabsAction" style="margin-top:6px;">';
				print '<button class="butAction" type="submit">'.$langs->trans("WiseMarkReconciled").'</button>';
				print '</div>';
				print '</form>';
			}
			print '</div>';
		}

		// Raw dump body (pretty). `white-space: pre-wrap` + `word-break:
		// break-all` make long single-line strings (Wise transfer
		// descriptions, payload fields) wrap inside the <pre> instead of
		// stretching the page past the viewport. JSON_PRETTY_PRINT still
		// produces real newlines + indent; only overflowing words get
		// wrapped.
		if (is_array($dumpBody)) {
			print '<br><div class="fiche"><div class="titre">'.$langs->trans("WiseDumpBody").'</div>';
			print '<pre style="max-height:400px; max-width:100%; overflow:auto; background:#fafafa; padding:8px; white-space:pre-wrap; word-break:break-all;">'.dol_escape_htmltag(json_encode($dumpBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'</pre>';
			print '</div>';
		}
	}
	print '<br><a class="backlink" href="'.$_SERVER["PHP_SELF"].'?status='.urlencode($statusFilter).'&source='.urlencode($sourceFilter).'">'.$langs->trans("BackToList").'</a>';
	llxFooter();
	$db->close();
	exit(0);
}

// Apply source filter, then status filter; the in-memory counts are
// already aggregated above so the toolbar numbers stay correct.
$rows = $acceptedRows;
if ($sourceFilter !== '') {
	$tmp = array();
	foreach ($rows as $k => $r) {
		if (isset($r['source']) && $r['source'] === $sourceFilter) {
			$tmp[$k] = $r;
		}
	}
	$rows = $tmp;
}
if ($statusFilter !== '') {
	$tmp = array();
	foreach ($rows as $k => $r) {
		if (isset($r['status']) && $r['status'] === $statusFilter) {
			$tmp[$k] = $r;
		}
	}
	$rows = $tmp;
}

// 2.5.1: pre-compute bank-entry candidates for every visible row in a single
// (entity, currency)-bucketed SELECT, so the list can show a "Match" chip
// that links to the best-matching bank line. The page is read-only on the
// bank side — this is just a "click to confirm and delete" affordance.
$bankMatchesByRow = $wp->findBankMatchesBatch($rows, 3, 30.0, 5);

// List view (file-based). Bulk delete + per-row detail link.
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="bulk_delete_incoming">';
print '<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">';
print '<input type="hidden" name="source" value="'.dol_escape_htmltag($sourceFilter).'">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="nowrap"><input type="checkbox" id="incoming-select-all"> <label for="incoming-select-all">'.$langs->trans("WiseIncomingSelectAll").'</label></td>';
print '<td>'.$langs->trans("Date").'</td>';
print '<td>'.$langs->trans("WiseSource").'</td>';
print '<td class="right">'.$langs->trans("Amount").'</td>';
print '<td>'.$langs->trans("Ref").'</td>';
print '<td>'.$langs->trans("ThirdParty").'</td>';
print '<td>'.$langs->trans("Status").'</td>';
print '<td>'.$langs->trans("WiseBankMatchTitle").'</td>';
print '<td class="right">'.$langs->trans("Actions").'</td></tr>';

$deletableCount = 0;
$canWriteAny = ($user->admin || $user->hasRight('slycustom', 'wise', 'write')) ? true : false;
if (!empty($rows)) {
	foreach ($rows as $key => $r) {
		$deletable = !in_array($r['status'], array(WiseIncomingPayment::STATUS_ENRICHED), true);
		if ($deletable) $deletableCount++;
		$detailUrl = $_SERVER["PHP_SELF"].'?file='.urlencode($r['file']).'&bucket=accepted';
		$occTs = WiseIncomingPayment::sqlUtcToTimestamp(WiseIncomingPayment::isoToSqlUtc($r['occurred_at']));
		$rowClass = 'oddeven'.($r['status'] === WiseIncomingPayment::STATUS_IGNORED ? ' opacitymedium' : '');
		print '<tr class="'.$rowClass.'">';
		print '<td class="nowrap"><input type="checkbox" class="incoming-row" name="files[]" value="accepted/'.dol_escape_htmltag($r['file']).'"'.($deletable ? '' : ' disabled').'></td>';
		print '<td>'.($r['received_at'] ? dol_print_date((int) $r['received_at'], 'dayhour', 'tzuserrel') : '-').'</td>';
		print '<td>'.dol_escape_htmltag(isset($r['source']) ? $r['source'] : 'webhook').'</td>';
		print '<td class="right">'.($r['amount'] !== null ? price($r['amount'], 2, '', 1, -1, -1, strtoupper($r['currency'])) : '—').'</td>';
		$refDisp = '';
		if (isset($r['enriched']['ref_text']) && $r['enriched']['ref_text'] !== '') {
			$refDisp = dol_escape_htmltag($r['enriched']['ref_text']);
		}
		print '<td>'.($refDisp !== '' ? $refDisp : '<span class="opacitymedium">-</span>').'</td>';
		$tpDisp = '';
		if (isset($r['enriched']['counterparty']) && $r['enriched']['counterparty'] !== '') {
			$tpDisp = dol_escape_htmltag($r['enriched']['counterparty']);
		}
		print '<td>'.($tpDisp !== '' ? $tpDisp : '<span class="opacitymedium">-</span>').'</td>';
		$statusBadge = $r['status'] === WiseIncomingPayment::STATUS_IGNORED ? 'badge badge-status8 badge-status'
			: ($r['status'] === WiseIncomingPayment::STATUS_RECONCILED ? 'badge badge-status4 badge-status'
				: ($r['status'] === WiseIncomingPayment::STATUS_ENRICHED ? 'badge badge-warning badge-status' : 'badge badge-info badge-status'));
		$statusLabel = $langs->trans("BankEmailStatus".$r['status']);
		if ($statusLabel === "BankEmailStatus".$r['status']) {
			// Fall back to the existing WiseStatus* keys if the new BankEmailStatus* alias
			// is missing in the loaded lang file (2.4.x deployments upgrading).
			$statusLabel = $langs->trans("WiseStatus".$r['status']);
		}
		print '<td><span class="'.$statusBadge.'" title="'.dol_escape_htmltag($r['status']).'">'.dol_escape_htmltag($statusLabel).'</span></td>';
		// 2.5.1: bank-match chip. Always rendered so users can see at a
		// glance whether the notification has a corresponding bank line
		// (clickable, jumps to the bank card) or is orphaned (no chip —
		// nothing to match against). The detail page still shows the
		// full candidate table; this column is the one-click affordance.
		$rowKey = $bucket.'/'.$r['file'];
		$picks = isset($bankMatchesByRow[$rowKey]) ? $bankMatchesByRow[$rowKey] : array();
		if (!empty($picks)) {
			$best = $picks[0];
			$bankUrl = DOL_URL_ROOT.'/compta/bank/line.php?rowid='.(int) $best['rowid'];
			$diffTxt = $best['diff'] < 0.01 ? '0' : price($best['diff']);
			$chip = '<a class="badge badge-status4 badge-status" href="'.$bankUrl.'" title="'.dol_escape_htmltag($best['label']).'">'
				.'#'.(int) $best['rowid'].' · '.dol_print_date((int) $best['datev'], 'day', 'tzuserrel').' · '.dol_escape_htmltag($diffTxt)
				.'</a>';
			if (count($picks) > 1) {
				$chip .= ' <span class="opacitymedium">+'.(count($picks) - 1).'</span>';
			}
			print '<td>'.$chip.'</td>';
		} elseif ($r['status'] === WiseIncomingPayment::STATUS_RECONCILED && !empty($r['fk_paiement'])) {
			print '<td><a class="badge badge-status4 badge-status" href="'.DOL_URL_ROOT.'/compta/paiement/card.php?id='.(int) $r['fk_paiement'].'">Paiement #'.(int) $r['fk_paiement'].'</a></td>';
		} else {
			print '<td><span class="opacitymedium">-</span></td>';
		}
		print '<td class="right"><a class="butAction" href="'.$detailUrl.'">'.$langs->trans("WiseView").'</a></td>';
		print '</tr>';
	}
}
if (empty($rows)) {
	print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
}
print '</table></div>';

if ($deletableCount > 0) {
	print '<div class="tabsAction" style="margin-top:6px;">';
	print '<button class="butActionDelete" type="submit">'.$langs->trans("WiseDumpBulkDelete").'</button>';
	print '</div>';
}
print '</form>';

// JS lives outside PHP string quoting so static analyzers don't choke on
// `for (var i=0;...)` inside a `print '...';` literal.
$tplBulk = json_encode($langs->trans("WiseDumpBulkConfirm", '__N__'));
$msgNone = json_encode($langs->trans("WiseDumpBulkNoSelection"));
print <<<SLYWISE_JS
<script>(function(){
var boxes = document.getElementsByClassName("incoming-row");
var all = document.getElementById("incoming-select-all");
var tpl = $tplBulk;
var msgNone = $msgNone;
var form = all && all.closest("form");
if (all) { all.addEventListener("change", function(){ for (var i=0; i<boxes.length; i++){ if (boxes[i].disabled) continue; boxes[i].checked = all.checked; } }); }
if (form) { form.addEventListener("submit", function(e){ var n = 0; for (var i=0;i<boxes.length;i++){ if (!boxes[i].disabled && boxes[i].checked) n++; } if (n <= 0) { alert(msgNone); e.preventDefault(); return false; } if (!confirm(tpl.replace("__N__", n))) { e.preventDefault(); return false; } return true; }); }
})();</script>
SLYWISE_JS;

llxFooter();
$db->close();
