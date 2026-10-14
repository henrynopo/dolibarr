<?php
/* Copyright (C) 2026 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/slycustom/wise/dumps.php
 * \ingroup slycustom
 * \brief   Wise rejected-bucket diagnostic page.
 *
 * 2.5.0 slim-down: the accepted bucket moved entirely to the reconcile
 * inbox (file-based view, sidecar state). What this page shows now is
 * only the rejected bucket — deliveries turned away by the IP whitelist,
 * the signature bootstrap, or (in 2.5.0+) the email-collector template
 * matcher. The role of this page is to surface the diagnostic archive so
 * an operator can see "we lost N deliveries last week" without flipping
 * through the inbox.
 *
 * Access: admin or "wise/read" to view; "wise/write" (or admin) to delete.
 * All writes are POST + CSRF token.
 */

$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php failed for SLY Custom wise/dumps.php');
}

require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_Incoming.class.php';

$langs->loadLangs(array('slycustom@slycustom'));

if (!$user->admin && !$user->hasRight('slycustom', 'wise', 'read')) {
	accessforbidden();
	exit;
}

$action = GETPOST('action', 'aZ09');
$bucket = GETPOST('bucket', 'aZ09');
$dumpFile = GETPOST('file', 'none');

// Delete needs the wise/write right. Replay is gone in 2.4.0.
if (in_array($action, array('delete', 'bulk_delete'), true)
	&& !$user->admin && !$user->hasRight('slycustom', 'wise', 'write')) {
	accessforbidden();
	exit;
}

$wp = new WiseIncomingPayment($db);

if ($action === 'delete') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$r = $wp->deleteDump($bucket, $dumpFile);
		setEventMessages(($r['ok'] ? $langs->trans("WiseDumpDeleted") : $langs->trans("WiseDumpDeleteFailed")).' '.dol_escape_htmltag($r['message']), null, $r['ok'] ? 'mesgs' : 'errors');
	}
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}

// 2.4.1+: bulk-delete across a single bucket. Each row's checkbox posts its
// `<bucket>/<file>` reference into `files[]`; the form-level hidden `bucket`
// guards against any cross-bucket confusion. Per-row state (entity, valid
// name) is re-validated inside deleteDump via dumpPath() regex, so a
// tampered POST can't escape the wise_webhook directory.
if ($action === 'bulk_delete') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
		header('Location: '.$_SERVER["PHP_SELF"]);
		exit;
	}
	if (!in_array($bucket, array('accepted', 'rejected'), true)) {
		setEventMessages($langs->trans("WiseDumpDeleteFailed").' invalid bucket', null, 'errors');
		header('Location: '.$_SERVER["PHP_SELF"]);
		exit;
	}
	$files = GETPOST('files', 'array');
	$selected = array();
	if (is_array($files)) {
		foreach ($files as $f) {
			$f = (string) $f;
			if ($f === '') continue;
			$selected[] = $f;
		}
	}
	if (empty($selected)) {
		setEventMessages($langs->trans("WiseDumpBulkNoSelection"), null, 'warnings');
		header('Location: '.$_SERVER["PHP_SELF"]);
		exit;
	}
	$ok = 0; $ko = 0;
	foreach ($selected as $rel) {
		// rel is "<bucket>/<file>" — strip the bucket prefix to feed
		// deleteDump the bare file name. deleteDump re-applies the form
		// bucket via dumpPath() so the two strings must agree.
		$prefix = $bucket.'/';
		if (strncmp($rel, $prefix, strlen($prefix)) !== 0) {
			$ko++;
			continue;
		}
		$name = substr($rel, strlen($prefix));
		$r = $wp->deleteDump($bucket, $name);
		if ($r['ok']) $ok++; else $ko++;
	}
	if ($ko === 0) {
		setEventMessages($langs->trans("WiseDumpBulkDeletedOk", $ok), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("WiseDumpBulkPartial", $ok, $ko), null, 'warnings');
	}
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}

/*
 * View
 */
llxHeader('', $langs->trans("WiseDumpsTitle"), '', '', 0, 0, '', '', '', 'mod-slycustom page-bankemail_dumps');
print load_fiche_titre($langs->trans("WiseDumpsTitle"), '<a class="butAction" href="'.dol_buildpath('/custom/slycustom/wise/reconcile.php', 1).'?mainmenu=tools">'.$langs->trans("WiseReconcileTitle").'</a>', 'payment');

$dumps = $wp->listDumps((int) $conf->entity);

// Top row: inbox link (live queue, accepted bucket) + rejected count
// (this page). 2.5.0 removed the SQL row count: the inbox is purely
// file-based now, and listDumps already returns the rejected row count.
$rejTotal = isset($dumps['rejected']) ? count($dumps['rejected']) : 0;
print '<div style="margin-bottom:10px;">';
print '<a href="'.dol_buildpath('/custom/slycustom/wise/reconcile.php', 1).'?mainmenu=tools">'.$langs->trans("WiseDumpsInboxLink").'</a>';
print ' · <span class="opacitymedium">'.$langs->trans("WiseDumpsRejectedCount", $rejTotal).'</span>';
print '</div>';

// 2.5.0: only the rejected bucket renders here. listDumps still scans
// the accepted bucket for the count above, but we never re-render those
// rows — reconcile.php owns the accepted view.
$bucketLabels = array(
	'rejected' => $langs->trans("WiseDumpBucketRejected"),
);
$anyShown = false;
foreach ($dumps as $bucketName => $rows) {
	if ($bucketName !== 'rejected' || empty($rows)) {
		continue;
	}
	$anyShown = true;
	print load_fiche_titre($bucketLabels[$bucketName].' ('.count($rows).')', '', 'generic');
	// One form per bucket: keeps the hidden `bucket` field co-located with
	// the bucket's rows. The select-all toggle is per-bucket (a misclick
	// that grabs both buckets would retire the wrong diagnostic evidence).
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="bulk_delete">';
	print '<input type="hidden" name="bucket" value="'.dol_escape_htmltag($bucketName).'">';
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th class="nowrap"><input type="checkbox" class="dump-select-all" data-bucket="'.dol_escape_htmltag($bucketName).'"> '.$langs->trans("WiseDumpSelectAll").'</th>';
	print '<th>'.$langs->trans("Date").'</th>';
	print '<th>'.$langs->trans("Type").'</th>';
	print '<th class="right">'.$langs->trans("Amount").'</th>';
	print '<th>'.$langs->trans("Status").'</th>';
	print '</tr>';
	foreach ($rows as $key => $d) {
		// listDumps keys each row as "<bucket>/<file>" — that's exactly
		// what deleteDump's bulk path expects after we strip the prefix.
		print '<tr>';
		print '<td class="nowrap"><input type="checkbox" class="dump-row-'.dol_escape_htmltag($bucketName).'" name="files[]" value="'.dol_escape_htmltag($key).'"></td>';
		print '<td>'.dol_print_date($d['received_at'], 'dayhour', 'tzuserrel').'</td>';
		print '<td>'.dol_escape_htmltag($d['event_type'] !== '' ? $d['event_type'] : '?').'</td>';
		print '<td class="right">'.($d['amount'] !== null ? price($d['amount'], 2, '', 1, -1, -1, strtoupper($d['currency'])) : '—').'</td>';
		print '<td>';
		if ($d['ingested']) {
			print '<span class="badge badge-status4 badge-status">'.$langs->trans("WiseDumpIngested").'</span>';
		} else {
			print '<span class="badge badge-status8 badge-status">'.$langs->trans("WiseDumpPending").'</span>';
		}
		print '</td>';
		print '</tr>';
	}
	print '</table></div>';
	// Submit row + the select-all JS — kept inline so dumps.php stays
	// single-file and needs no extra asset.
	print '<div class="tabsAction" style="margin-top:6px;">';
	print '<button class="butActionDelete" type="submit">'.$langs->trans("WiseDumpBulkDelete").'</button>';
	print '</div>';
	print '</form>';
	// Inline JS: wires the per-bucket select-all + injects a real
	// confirm() with the live checked count. The translated template has
	// a %s placeholder for the count; the closure rebuilds the message
	// at submit time so the operator sees "Delete the 7 selected dump(s)?"
	// not a literal "%s".
	print '<script>(function(){';
	print 'var boxes = document.getElementsByClassName("dump-row-'.dol_escape_htmltag($bucketName).'");';
	print 'var all = document.querySelector(".dump-select-all[data-bucket=\"'.dol_escape_htmltag($bucketName).'\"]");';
	print 'var tpl = '.json_encode($langs->trans("WiseDumpBulkConfirm", '__N__')).';';
	print 'var form = all && all.closest("form");';
	print 'if (all) { all.addEventListener("change", function(){ for (var i=0; i<boxes.length; i++){ boxes[i].checked = all.checked; } }); }';
	print 'if (form) { form.addEventListener("submit", function(e){ var n = 0; for (var i=0;i<boxes.length;i++){ if (boxes[i].checked) n++; } if (n <= 0) { alert('.json_encode($langs->trans("WiseDumpBulkNoSelection")).'); e.preventDefault(); return false; } if (!confirm(tpl.replace("__N__", n))) { e.preventDefault(); return false; } return true; }); }';
	print '})();</script>';
}

if (!$anyShown) {
	print '<div class="opacitymedium">'.$langs->trans("WiseDumpNoDumps").'</div>';
}

llxFooter();
$db->close();
