<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        export/pdf_summary.php
 * \ingroup sghr
 * \brief       HTTP endpoint to stream Payroll Summary PDF.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include '../../../main.inc.php'; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include '../../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

dol_include_once('sghr/core/modules/sghr/pdf/pdf_summary_sgpayroll.class.php');

if (!isModEnabled("sghr")) accessforbidden();

$year  = GETPOST('year',  'int') ?: (int)date('Y');
$month = GETPOST('month', 'int') ?: (int)date('m');
$mode  = GETPOST('mode', 'aZ') ?: 'download';

// Permission: HR Manager or HR Approver
$canView = $user->hasRight('sghr', 'payroll', 'create')
        || $user->hasRight('sghr', 'payroll', 'approve');

if (!$canView) accessforbidden();

$gen = new pdf_summary_sgpayroll($db);
$gen->generate($year, $month, $mode);
$db->close();
exit;
