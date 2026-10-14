<?php
/* Copyright (C) 2026 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/slycustom/wise/email_collector.php
 * \ingroup slycustom
 * \brief   Manual "pull once" page for the email collector. Used by
 *          operators when testing a new mailbox setup, or after the cron
 *          was off for a while and they want to drain a backlog
 *          immediately.
 *
 * Two modes:
 *   1. ?action=test_pull  : runs pullOnce() for the active entity, prints
 *      the resulting counts in a fiche box. Honors CSRF token; the
 *      button on this page is the only trigger.
 *   2. no action          : renders a "Pull now" button + the last pull
 *      stats (read from the dolibarr const SLYEMAIL_LAST_PULL_STATS so
 *      the cron can update it).
 *
 * Access: admin or "email/write".
 */

$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php failed for slycustom/wise/email_collector.php');
}

require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/slyemailcollector/EmailCollector.class.php';

$langs->loadLangs(array('bills', 'banks', 'compta', 'slycustom@slycustom'));

$accessOk = $user->admin || $user->hasRight('slycustom', 'email', 'read');
if (!$accessOk) {
	accessforbidden();
	exit;
}
$writeOk = $user->admin || $user->hasRight('slycustom', 'email', 'write');

$action = GETPOST('action', 'aZ09');
$entity = (int) $conf->entity;
$ec = new EmailCollector($db);

if ($action === 'test_pull') {
	if (!$writeOk) {
		accessforbidden();
		exit;
	}
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
		header('Location: '.$_SERVER["PHP_SELF"]);
		exit;
	}
	$res = $ec->pullOnce($entity);
	$msg = $langs->trans("BankEmailCollectorTestPullDone", (int) $res['ok'], (int) $res['rejected'], (int) $res['error'], (int) $res['skipped']);
	setEventMessages($msg, null, 'mesgs');
	dol_syslog('slycustom.wise.email_collector manual pull entity='.$entity.' '.json_encode($res), LOG_INFO);
	// Persist last-pull stats for the read-only block below.
	if (function_exists('dolibarr_set_const')) {
		dolibarr_set_const($db, 'SLYEMAIL_LAST_PULL_STATS', json_encode($res + array('entity' => $entity, 'tms' => date('Y-m-d H:i:s'))), 'chaine', 0, '', $entity);
	}
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}

llxHeader('', $langs->trans("BankEmailCollectorTitle"), '', '', 0, 0, '', '', '', 'mod-slycustom page-bankemail_collector');
print load_fiche_titre($langs->trans("BankEmailCollectorTitle"), '<a class="butAction" href="'.dol_buildpath('/custom/slycustom/wise/reconcile.php', 1).'?mainmenu=tools">'.$langs->trans("WiseInboxTitle").'</a>', 'email');

print '<div class="fiche"><div class="titre">'.$langs->trans("BankEmailCollectorOverview").'</div>';
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans("BankEmailCollectorEnabled").'</td><td>'.($ec->isEnabledForEntity($entity) ? $langs->trans("Yes") : $langs->trans("No")).'</td></tr>';
$lastStats = '';
if (function_exists('dolibarr_get_const')) {
	$lastStats = dolibarr_get_const($db, 'SLYEMAIL_LAST_PULL_STATS', $entity);
}
if ($lastStats !== '' && $lastStats !== null) {
	$j = json_decode((string) $lastStats, true);
	if (is_array($j)) {
		print '<tr class="oddeven"><td>'.$langs->trans("BankEmailCollectorLastPull").'</td><td>'.dol_print_date(strtotime((string) ($j['tms'] ?? 'now')), 'dayhour', 'tzuserrel').'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans("BankEmailCollectorLastPullOk").'</td><td>'.(int) ($j['ok'] ?? 0).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans("BankEmailCollectorLastPullRejected").'</td><td>'.(int) ($j['rejected'] ?? 0).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans("BankEmailCollectorLastPullError").'</td><td>'.(int) ($j['error'] ?? 0).'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans("BankEmailCollectorLastPullSkipped").'</td><td>'.(int) ($j['skipped'] ?? 0).'</td></tr>';
	}
} else {
	print '<tr class="oddeven"><td>'.$langs->trans("BankEmailCollectorLastPull").'</td><td><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
}
print '</table></div>';

if ($writeOk) {
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="test_pull">';
	print '<div class="tabsAction" style="margin-top:10px;">';
	print '<button class="butAction" type="submit">'.$langs->trans("BankEmailCollectorPullNow").'</button>';
	print '</div>';
	print '</form>';
}

llxFooter();
$db->close();
