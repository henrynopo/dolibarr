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
 * \brief   Wise webhook delivery dumps — manual replay / cleanup.
 *
 * Every delivery Wise sent leaves a diagnostic dump under
 * DOL_DATA_ROOT/wise_webhook/{accepted,rejected}/. When a delivery could not
 * be ingested at the time (ingest bug, DB error, bootstrap early-ack), the
 * operator can replay it here — the replay goes through the exact webhook
 * entry point (WiseIncomingPayment::createFromWebhook) and is idempotent via
 * the payload_md5 dedupe, so Wise retries are no longer the only recovery
 * path and no cron statement sync is needed for short outages.
 *
 * Access: admin or "wise/read" to view; "wise/write" (or admin) to replay or
 * delete. All writes are POST + CSRF token.
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

// Writes need the wise/write right (same bar as saving drafts in reconcile.php).
if (in_array($action, array('replay', 'delete'), true)
	&& !$user->admin && !$user->hasRight('slycustom', 'wise', 'write')) {
	accessforbidden();
	exit;
}

$wp = new WiseIncomingPayment($db);

if (in_array($action, array('replay', 'delete'), true)) {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		if ($action === 'replay') {
			$r = $wp->replayDump($bucket, $dumpFile);
			setEventMessages(($r['ok'] ? $langs->trans("WiseDumpReplayedOk") : $langs->trans("WiseDumpReplayFailed")).' '.dol_escape_htmltag($r['message']), null, $r['ok'] ? 'mesgs' : 'errors');
		} else {
			$r = $wp->deleteDump($bucket, $dumpFile);
			setEventMessages(($r['ok'] ? $langs->trans("WiseDumpDeleted") : $langs->trans("WiseDumpDeleteFailed")).' '.dol_escape_htmltag($r['message']), null, $r['ok'] ? 'mesgs' : 'errors');
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}

/*
 * View
 */
llxHeader('', $langs->trans("WiseDumpsTitle"), '', '', 0, 0, '', '', '', 'mod-slycustom page-wise_dumps');
print load_fiche_titre($langs->trans("WiseDumpsTitle"), '<a class="butAction" href="'.dol_buildpath('/custom/slycustom/wise/reconcile.php', 1).'?mainmenu=tools">'.$langs->trans("WiseReconcileTitle").'</a>', 'payment');
print '<span class="opacitymedium">'.$langs->trans("WiseDumpsHelp").'</span><br><br>';

$dumps = $wp->listDumps((int) $conf->entity);

$bucketLabels = array(
	'accepted' => $langs->trans("WiseDumpBucketAccepted"),
	'rejected' => $langs->trans("WiseDumpBucketRejected"),
);
$anyShown = false;
foreach ($dumps as $bucketName => $rows) {
	if (empty($rows)) {
		continue;
	}
	$anyShown = true;
	print load_fiche_titre($bucketLabels[$bucketName].' ('.count($rows).')', '', 'generic');
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("Date").'</th>';
	print '<th>'.$langs->trans("Type").'</th>';
	print '<th class="right">'.$langs->trans("Amount").'</th>';
	print '<th>'.$langs->trans("WiseDumpDelivery").'</th>';
	print '<th>'.$langs->trans("WiseDumpSourceIp").'</th>';
	print '<th>'.$langs->trans("Status").'</th>';
	print '<th class="right">'.$langs->trans("Actions").'</th>';
	print '</tr>';
	foreach ($rows as $d) {
		print '<tr>';
		print '<td>'.dol_print_date($d['received_at'], 'dayhour', 'tzuserrel').'</td>';
		print '<td>'.dol_escape_htmltag($d['event_type'] !== '' ? $d['event_type'] : '?').'</td>';
		print '<td class="right">'.($d['amount'] !== null ? price($d['amount'], 2, '', 1, -1, -1, strtoupper($d['currency'])) : '—').'</td>';
		print '<td>'.dol_escape_htmltag($d['delivery_id'] !== '' ? $d['delivery_id'] : '—').'</td>';
		print '<td>'.dol_escape_htmltag($d['remote_ip']).($d['bootstrap'] ? ' <span class="badge badge-warning">bootstrap</span>' : '').'</td>';
		print '<td>';
		if ($d['ingested']) {
			print '<span class="badge badge-status4 badge-status">'.$langs->trans("WiseDumpIngested").'</span>';
		} elseif ($d['truncated']) {
			print '<span class="badge badge-warning" title="'.dol_escape_htmltag($langs->trans("WiseDumpTruncatedHelp")).'">'.$langs->trans("WiseDumpTruncated").'</span>';
		} else {
			print '<span class="badge badge-status8 badge-status">'.$langs->trans("WiseDumpPending").'</span>';
		}
		print '</td>';
		print '<td class="right">';
		$hidden = '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="bucket" value="'.dol_escape_htmltag($bucketName).'"><input type="hidden" name="file" value="'.dol_escape_htmltag($d['file']).'">';
		if ($d['replayable'] && !$d['ingested']) {
			print '<form method="POST" style="display:inline" action="'.$_SERVER["PHP_SELF"].'">'.$hidden.'<input type="hidden" name="action" value="replay"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans("WiseDumpReplay")).'"></form> ';
		}
		print '<form method="POST" style="display:inline" action="'.$_SERVER["PHP_SELF"].'" onsubmit="return confirm(\''.dol_escape_js($langs->trans("WiseDumpConfirmDelete")).'\');">'.$hidden.'<input type="hidden" name="action" value="delete"><input type="submit" class="button button-cancel" value="'.dol_escape_htmltag($langs->trans("WiseDumpDelete")).'"></form>';
		print '</td>';
		print '</tr>';
	}
	print '</table></div><br>';
}

if (!$anyShown) {
	print '<div class="opacitymedium">'.$langs->trans("WiseDumpNoDumps").'</div>';
}

llxFooter();
$db->close();
