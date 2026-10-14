<?php
/* Copyright (C) 2026 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/slycustom/cron/email_collector.php
 * \ingroup slycustom
 * \brief   Cron entry: pull credit-notification emails from the IMAP
 *          mailbox and write them into the Wise inbox bucket so the
 *          reconcile page sees them alongside webhook deliveries.
 *
 * Registered in modSlyCustom as job 1 with frequency 5 minutes. The
 * entity parameter (0 = active entity) is passed by Dolibarr's cron
 * engine; we don't override it here.
 */

$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php failed for slycustom/cron/email_collector.php');
}

require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/slyemailcollector/EmailCollector.class.php';

$entity = isset($entity) ? (int) $entity : 0;
$ec = new EmailCollector($db);
$totals = $ec->pullCron($entity);
dol_syslog('slycustom.cron.email_collector ok='.(int) $totals['ok'].' rejected='.(int) $totals['rejected'].' error='.(int) $totals['error'].' skipped='.(int) $totals['skipped'], LOG_INFO);
