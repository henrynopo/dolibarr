<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        export/pdf_payslip.php
 * \ingroup sghr
 * \brief       HTTP endpoint to stream payslip PDF. Checks permissions, then delegates to class.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include '../../../main.inc.php'; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include '../../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

dol_include_once('sghr/core/modules/sghr/pdf/pdf_payslip_sgpayroll.class.php');

if (!isModEnabled("sghr")) accessforbidden();

$id   = GETPOST('id', 'int');
$mode = GETPOST('mode', 'aZ') ?: 'download'; // download | inline

// Permission: own payslip OR HR
$gen = new pdf_payslip_sgpayroll($db);

// Load line to check owner
$checkSql = "SELECT fk_user, status FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE rowid=".(int)$id." AND entity=".(int)$conf->entity;
$checkRes = $db->query($checkSql);
$owner    = ($checkRes && $db->num_rows($checkRes) > 0) ? (int)$db->fetch_object($checkRes)->fk_user : 0;

$isSelf   = ($owner === (int)$user->id);
$hasHrRight = $user->hasRight('sghr', 'payroll', 'create')
           || $user->hasRight('sghr', 'payroll', 'approve');
$canView  = $isSelf || $hasHrRight;

if (!$canView) accessforbidden();

// Owners without HR rights may only download finalised payslips (not drafts)
if ($isSelf && !$user->admin && !$hasHrRight) {
	$statusRes = $db->query($checkSql);
	$lineStatus = ($statusRes && $db->num_rows($statusRes) > 0) ? (string)$db->fetch_object($statusRes)->status : '';
	if ($lineStatus === 'draft') accessforbidden();
}

// Clear any existing output buffers to prevent corruption
while (ob_get_level()) {
    ob_end_clean();
}

$gen->generate($id, $mode);
exit;
