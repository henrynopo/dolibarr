<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        export/pdf_ir8a.php
 * \ingroup     sgpayroll
 * \brief       HTTP endpoint to stream IR8A Employee Copy PDF.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include '../../../main.inc.php'; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include '../../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/pdf/pdf_ir8a_sgpayroll.class.php';

if (!isModEnabled("sgpayroll")) accessforbidden();

$userId = GETPOST('user_id', 'int') ?: (int)$user->id;
$ya     = GETPOST('ya',      'int') ?: (int)date('Y');
$mode   = GETPOST('mode',    'aZ')  ?: 'download';

// Permission: own record OR HR
$isSelf  = ($userId === (int)$user->id);
$canView = $isSelf
        || $user->hasRight('sgpayroll', 'payroll', 'approve')
        || $user->hasRight('sgpayroll', 'export', 'iras');

if (!$canView) accessforbidden();

$gen = new pdf_ir8a_sgpayroll($db);
$gen->generate($userId, $ya, $mode);
$db->close();
exit;
