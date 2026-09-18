<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */
/** \file documents_list.php — Employee document vault with expiry reminders */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';
if (!isModEnabled("sgpayroll")) accessforbidden();
$langs->loadLangs(array('sgpayroll@sgpayroll'));

$fkUser  = GETPOST('fk_user', 'int') ?: $user->id;
$action  = GETPOST('action', 'aZ');
$isSelf  = ($fkUser == $user->id);
$canEdit = $user->admin || $user->hasRight('sgpayroll', 'employee', 'write');
$canView = $canEdit || $user->hasRight('sgpayroll', 'employee', 'read_all') || $user->hasRight('sgpayroll', 'employee', 'read') || $isSelf;
if (!$canView) accessforbidden();

// Hooks
$hookmanager->initHooks(array('sgpayrolldocumentslist'));

$docTypes = array('NRIC'=>'NRIC / FIN','PASSPORT'=>'Passport','WORKPASS'=>'Work Pass',
                  'CONTRACT'=>'Employment Contract','QUALIFICATION'=>'Qualification',
                  'INSURANCE'=>'Insurance','REVIEW'=>'Performance Review',
                  'PAYSLIP'=>'Payslip','OTHER'=>'Other');

// ── UPLOAD ───────────────────────────────────────────────────────────────────
if ($action === 'upload' && $canEdit) {
	$db->begin();
	$docType   = GETPOST('doc_type',  'aZ');
	$docLabel  = GETPOST('doc_label', 'alphanohtml');
	$issueDate = GETPOST('issue_date','alpha') ?: null;
	$expiryDate= GETPOST('expiry_date','alpha') ?: null;
	$notes     = GETPOST('notes',     'restricthtml');

	// Handle file upload
	$uploadPath = '';
	if (!empty($_FILES['doc_file']['name'])) {
		$allowedExt = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx');
		$fileExt    = strtolower(preg_replace('/^.*\./', '', dol_sanitizeFileName($_FILES['doc_file']['name'])));
		if (!in_array($fileExt, $allowedExt)) {
			$db->rollback();
			setEventMessages($langs->trans('SgpayrollFileExtensionNotAllowed', implode(', ', $allowedExt)), null, 'errors');
			header('Location: documents_list.php?fk_user='.$fkUser); exit;
		}
		if ((int)$_FILES['doc_file']['size'] > 10 * 1024 * 1024) {
			$db->rollback();
			setEventMessages($langs->trans('SgpayrollFileTooLarge'), null, 'errors');
			header('Location: documents_list.php?fk_user='.$fkUser); exit;
		}
		$uploadDir = DOL_DATA_ROOT.'/sgpayroll/documents/'.$fkUser.'/';
		if (!is_dir($uploadDir)) {
			require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
			dol_mkdir($uploadDir);
		}
		$safeName   = dol_sanitizeFileName($_FILES['doc_file']['name']);
		$destPath   = $uploadDir.$safeName;
		if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $destPath)) {
			$uploadPath = 'sgpayroll/documents/'.$fkUser.'/'.$safeName;
		}
	}

	$sql = "INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_documents"
	     . " (fk_user, doc_type, doc_label, file_path, issue_date, expiry_date, notes, entity, date_creation, fk_user_creat)"
	     . " VALUES (".(int)$fkUser.",'".$db->escape($docType)."','".$db->escape($docLabel)."',"
	     . "'".$db->escape($uploadPath)."',"
	     . ($issueDate  ? "'".$db->escape($issueDate)."'" : 'NULL')  .','
	     . ($expiryDate ? "'".$db->escape($expiryDate)."'" : 'NULL') .",'"
	     . $db->escape($notes)."',".(int)$conf->entity.",'".$db->idate(dol_now())."',".(int)$user->id.")";
	if ($db->query($sql)) {
		$db->commit();
		dol_syslog('sgpayroll documents_list: uploaded doc for user='.$fkUser, LOG_INFO);
		setEventMessages($langs->trans('DocumentSaved'), null, 'mesgs');
	} else {
		$db->rollback();
		dol_syslog('sgpayroll documents_list: upload error '.$db->lasterror(), LOG_ERR);
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: documents_list.php?fk_user='.$fkUser); exit;
}

if ($action === 'delete' && $canEdit) {
	$db->begin();
	$rid = GETPOST('rowid','int');
	if ($db->query("DELETE FROM ".MAIN_DB_PREFIX."sgpayroll_documents WHERE rowid=".(int)$rid." AND fk_user=".(int)$fkUser." AND entity=".(int)$conf->entity)) {
		$db->commit();
		dol_syslog('sgpayroll documents_list: deleted doc rowid='.$rid, LOG_INFO);
	} else {
		$db->rollback();
	}
	header('Location: documents_list.php?fk_user='.$fkUser); exit;
}

// ── FETCH ─────────────────────────────────────────────────────────────────────
$sql  = "SELECT * FROM ".MAIN_DB_PREFIX."sgpayroll_documents WHERE fk_user=".(int)$fkUser." AND entity=".(int)$conf->entity." ORDER BY doc_type, issue_date DESC";
$res  = $db->query($sql); $rows = array();
while ($res && $obj = $db->fetch_object($res)) $rows[] = $obj;

// ── PAGE ─────────────────────────────────────────────────────────────────────
llxHeader('', $langs->trans('Documents'), '');

if ($fkUser > 0) {
	$user_for_title = new User($db);
	$user_for_title->fetch($fkUser);
	print load_fiche_titre($langs->trans('EmployeeProfile').' — '.$user_for_title->getFullName($langs), '', 'title_hrm');

	$head = sgpayroll_employee_prepare_head($fkUser);
	dol_fiche_head($head, 'docs', '', 0, '');
}

$empUser = new User($db); $empUser->fetch($fkUser);
print load_fiche_titre($langs->trans('Documents').' — '.dol_escape_htmltag($empUser->getFullName($langs)), '', 'sgpayroll@sgpayroll');

// Upload form
if ($canEdit) {
	print '<form method="POST" action="documents_list.php" enctype="multipart/form-data">';
	print '<input type="hidden" name="action" value="upload">';
	print '<input type="hidden" name="fk_user" value="'.$fkUser.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<table class="noborder" style="width:auto"><tr class="liste_titre"><td colspan="7">'.$langs->trans('UploadDocument').'</td></tr><tr>';
	print '<td><select name="doc_type" class="flat">';
	foreach ($docTypes as $v=>$l) print '<option value="'.$v.'">'.$l.'</option>';
	print '</select></td>';
	print '<td><input type="text" name="doc_label" placeholder="'.$langs->trans('DocLabel').'" class="flat" style="width:180px"></td>';
	print '<td>'.$langs->trans('IssueDate').': <input type="date" name="issue_date" class="flat"></td>';
	print '<td>'.$langs->trans('ExpiryDate').': <input type="date" name="expiry_date" class="flat"></td>';
	print '<td><input type="file" name="doc_file" class="flat"></td>';
	print '<td><input type="text" name="notes" placeholder="'.$langs->trans('Notes').'" class="flat" style="width:150px"></td>';
	print '<td><input type="submit" value="'.$langs->trans('Upload').'" class="button"></td>';
	print '</tr></table></form><br>';
}

// Documents table
print '<div class="div-table-responsive"><table class="tagtable liste">';
print '<tr class="liste_titre">';
foreach (array('DocType','DocLabel','IssueDate','ExpiryDate','File','Notes','') as $h) print '<td>'.$langs->trans($h?:'&nbsp;').'</td>';
print '</tr>';

$today = dol_now();
foreach ($rows as $r) {
	// Expiry warning colour
	$rowStyle = '';
	if ($r->expiry_date) {
		$exp   = $db->jdate($r->expiry_date);
		$diffD = (int)(($exp - $today) / 86400);
		if ($diffD <= 30)       $rowStyle = 'style="background:#fff0f0"'; // red
		elseif ($diffD <= 60)   $rowStyle = 'style="background:#fffbe6"'; // yellow
	}
	print '<tr class="oddeven" '.$rowStyle.'>';
	print '<td><b>'.dol_escape_htmltag($docTypes[$r->doc_type] ?? $r->doc_type).'</b></td>';
	print '<td>'.dol_escape_htmltag($r->doc_label).'</td>';
	print '<td>'.dol_print_date($db->jdate($r->issue_date), 'day').'</td>';
	print '<td>';
	echo dol_print_date($db->jdate($r->expiry_date), 'day');
	if ($r->expiry_date && (int)(($today - $db->jdate($r->expiry_date)) / 86400) >= 0) echo ' '.img_picto('Expired!','warning');
	print '</td>';
	$filePath = DOL_DATA_ROOT.'/'.$r->file_path;
	if ($r->file_path && file_exists($filePath)) {
		print '<td><a href="'.DOL_URL_ROOT.'/document.php?modulepart=sgpayroll&file='.urlencode($r->file_path).'" target="_blank">'.img_picto('','pdf').' '.$langs->trans('View').'</a></td>';
	} else {
		print '<td class="opacitymedium">'.$langs->trans('NoFile').'</td>';
	}
	print '<td>'.dol_escape_htmltag($r->notes ?? '').'</td>';
	print '<td>';
	if ($canEdit) {
		print '<form method="POST" action="documents_list.php" style="display:inline">';
		print '<input type="hidden" name="action" value="delete"><input type="hidden" name="rowid" value="'.$r->rowid.'">';
		print '<input type="hidden" name="fk_user" value="'.$fkUser.'"><input type="hidden" name="token" value="'.newToken().'">';
		print '<button type="submit" class="button buttongen smallpaddingimp" onclick="return confirm(\'Delete?\')">'.img_picto('','delete').'</button>';
		print '</form>';
	}
	print '</td></tr>';
}
if (empty($rows)) print '<tr><td colspan="7" class="opacitymedium center">'.$langs->trans('NoDocumentFound').'</td></tr>';
print '</table></div>';
if ($fkUser > 0) {
	dol_fiche_end();
	print '</div>';
}

llxFooter(); $db->close();
?>
