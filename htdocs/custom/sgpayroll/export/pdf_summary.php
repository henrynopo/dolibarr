<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        export/pdf_summary.php
 * \ingroup     sgpayroll
 * \brief       HTTP endpoint to stream Payroll Summary PDF.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include '../../../main.inc.php'; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include '../../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/pdf/pdf_summary_sgpayroll.class.php';

if (!isModEnabled("sgpayroll")) accessforbidden();

$year  = GETPOST('year',  'int') ?: (int)date('Y');
$month = GETPOST('month', 'int') ?: (int)date('m');
$mode  = GETPOST('mode', 'aZ') ?: 'download';

// Permission: HR Manager or HR Approver
$canView = $user->hasRight('sgpayroll', 'payroll', 'create')
        || $user->hasRight('sgpayroll', 'payroll', 'approve');

if (!$canView) accessforbidden();

$gen = new pdf_summary_sgpayroll($db);
$gen->generate($year, $month, $mode);
$db->close();
exit;
