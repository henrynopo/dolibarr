<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */
/** \file admin/setup.php — Module admin configuration */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))  { $res = @include '../../../main.inc.php'; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include '../../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
$libPath = dol_buildpath('/sgpayroll/lib/sgpayroll.lib.php', 0);
if (file_exists($libPath)) require_once $libPath;

if (!$user->admin) accessforbidden();

// Accounting: use chart of accounts for GL fields when module is enabled
$useAccountingSelect = isModEnabled('accounting');
if ($useAccountingSelect) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formaccounting.class.php';
	$formaccounting = new FormAccounting($db);
}
$glAccountConsts = array('SGPAYROLL_GL_SALARY', 'SGPAYROLL_GL_CPF_PAYABLE', 'SGPAYROLL_GL_SDL_PAYABLE', 'SGPAYROLL_GL_BANK');

// Correctly load languages even if in custom folder
$langs->loadLangs(array('sgpayroll@sgpayroll', 'admin'));

$action = GETPOST('action', 'alpha');

// ── SAVE SETTINGS ─────────────────────────────────────────────────────────────
if ($action === 'save') {
	$consts = array(
		'SGPAYROLL_COMPANY_UEN',
		'SGPAYROLL_CPF_ACCOUNT',
		'SGPAYROLL_GLOBAL_WEEKLY_SCHEDULE',
		'SGPAYROLL_WORKING_HOURS_PER_DAY',
		'SGPAYROLL_FWL_SECTOR',
		'SGPAYROLL_GL_SALARY',
		'SGPAYROLL_GL_CPF_PAYABLE',
		'SGPAYROLL_GL_SDL_PAYABLE',
		'SGPAYROLL_GL_BANK',
		'SGPAYROLL_DEFAULT_PAYMENT_DAY',
		'SGPAYROLL_NAME_DISPLAY_ORDER',
		'SGPAYROLL_SEND_PAYSLIP_EMAIL',
		'SGPAYROLL_EMAIL_SUBJECT',
		'SGPAYROLL_EMAIL_BODY',
	);
	foreach ($consts as $c) {
		$val = GETPOST($c, 'alphanohtml');
		dolibarr_set_const($db, $c, $val, 'chaine', 0, '', $conf->entity);
	}
	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header('Location: setup.php'); exit;
}

// ── INSTALL DATABASE TABLES ──────────────────────────────────────────────────
if ($action === 'install_sql') {
	dol_syslog('sgpayroll setup: starting install_sql action', LOG_INFO);
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
	
	// Candidates for SQL directory
	$candidates = array(
		dol_buildpath('/sgpayroll/sql/', 0),
		DOL_DOCUMENT_ROOT . '/custom/sgpayroll/sql/',
		realpath(dirname(__DIR__) . '/sql') . DIRECTORY_SEPARATOR
	);
	
	$sqlFiles = array();
	$sqlDirUsed = '';
	foreach ($candidates as $dir) {
		if (empty($dir)) continue;
		$dir = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		if (is_dir($dir)) {
			$files = glob($dir . 'llx_sgpayroll_*.sql');
			if (!empty($files)) {
				$sqlFiles = $files;
				$sqlDirUsed = $dir;
				break;
			}
		}
	}
	
	if (empty($sqlFiles)) {
		setEventMessages("Could not find SQL scripts. Checked paths: " . implode(', ', array_filter($candidates)), null, 'errors');
		header('Location: setup.php'); exit;
	}

	// Sort files to ensure base tables are created before upgrades
	// llx_sgpayroll_employee.sql, llx_sgpayroll_payroll.sql should be first
	sort($sqlFiles);

	$okCount = 0;
	$errCount = 0;
	$messages = array();

	// Use output buffering to capture and suppress any direct output from run_sql
	ob_start();

	foreach ($sqlFiles as $f) {
		$basename = basename($f);
		// run_sql($file, $echo_sql=0, $entity=0, $fullcheck=0, $stop_on_error=0, $dbms='default', $max_length=32768, $nouse_foreign_key_check=0)
		$res = run_sql($f, 0, $conf->entity, 1, 0, 'default', 32768, 0);
		
		if ($res > 0) {
			$okCount++;
			$messages[] = "Processed: $basename (OK)";
		} else {
			$lasterror = $db->lasterror();
			// Suppress common errors like "column already exists" or "index already exists"
			if (strpos($lasterror, 'Duplicate column name') !== false || 
				strpos($lasterror, 'Duplicate key name') !== false ||
				strpos($lasterror, 'Duplicate entry') !== false) {
				$okCount++;
				$messages[] = "Processed: $basename (Ignored: field/index already exists)";
			} else {
				$errCount++;
				$messages[] = "Error processing: $basename - " . $lasterror;
			}
		}
	}
	
	$capturedOutput = ob_get_clean();
	if (!empty($capturedOutput)) {
		dol_syslog('sgpayroll setup: captured unexpected output during run_sql', LOG_DEBUG);
	}

	// Final verification of critical tables
	$criticalTables = array('sgpayroll_employee', 'sgpayroll_employee_jobpos', 'sgpayroll_payroll', 'sgpayroll_payroll_line');
	$missing = array();
	foreach ($criticalTables as $t) {
		$checkSql = "SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.$t)."'";
		$resCheck = $db->query($checkSql);
		if (!$resCheck || $db->num_rows($resCheck) == 0) {
			$missing[] = MAIN_DB_PREFIX.$t;
		}
	}

	if (!empty($missing)) {
		setEventMessages("Critical tables missing after installation: " . implode(', ', $missing), null, 'errors');
		$details = "<details><summary>Click for technical logs</summary>" . implode('<br>', $messages) . "</details>";
		setEventMessages($details, null, 'warnings');
		foreach ($messages as $msg) dol_syslog("sgpayroll setup: " . $msg, LOG_ERR);
	} elseif ($errCount > 0) {
		setEventMessages("Installation completed with $errCount error(s).", null, 'warnings');
		$details = "<details><summary>Click for error details</summary>" . implode('<br>', $messages) . "</details>";
		setEventMessages($details, null, 'warnings');
	} else {
		setEventMessages($langs->trans('TablesInstalled') . " ($okCount files processed).", null, 'mesgs');
		$details = "<details><summary>Click for processing logs</summary>" . implode('<br>', $messages) . "</details>";
		setEventMessages($details, null, 'mesgs');
	}
	
	header('Location: setup.php'); exit;
}

// ── PAGE ─────────────────────────────────────────────────────────────────────
llxHeader('', $langs->trans('SGPayrollSetup'), '');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('SGPayrollSetup'), $linkback, 'sgpayroll@sgpayroll');

// Tabs (use full admin head so Cost Centres + Schedule Presets are visible)
$head = sgpayrollAdminPrepareHead();
dol_fiche_head($head, 'setup', $langs->trans('SGPayrollSetup'), -1, 'sgpayroll@sgpayroll');

// ── REQUIRED STEPS AFTER ACTIVATION (do these first when installing the module) ──
$activationTitle = $langs->trans('ActivationStepsTitle');
if ($activationTitle === 'ActivationStepsTitle') {
	$activationTitle = 'Required steps after activation';
}
print '<div class="fichecenter">';
print '<div class="info" style="margin-bottom:1em">';
print '<strong>'.$activationTitle.'</strong>';
print '<p class="opacitymedium small">'.($langs->trans('ActivationStepsIntro') !== 'ActivationStepsIntro' ? $langs->trans('ActivationStepsIntro') : 'Complete these in order when you first enable SG Payroll on a new system.').'</p>';
print '</div>';

// Step 1: Install/Upgrade tables — now points to the SQL Upgrade Runner.
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td style="width:2em">1</td><td><b>'.$langs->trans('DatabaseInstall').'</b></td></tr>';
print '<tr class="oddeven"><td></td><td>';
print '<p class="opacitymedium">'.($langs->trans('DatabaseInstallDesc') !== 'DatabaseInstallDesc' ? $langs->trans('DatabaseInstallDesc') : 'Creates all required SG Payroll tables in this database. Run this first.').'</p>';
print '<a href="upgrade_sql.php" class="butAction"><i class="fas fa-database"></i> '.($langs->trans('SqlUpgradeRunner') !== 'SqlUpgradeRunner' ? $langs->trans('SqlUpgradeRunner') : 'SQL Upgrade Runner').'</a>';
print '</td></tr>';
print '</table></div>';

// Step 2: CPF ExtraFields (expense report line checkbox).
// The extrafield registration is performed by llx_sgpayroll_upgrade_6a.sql,
// so we now direct admins to the SQL Upgrade Runner instead of the legacy
// upgrade_6a_extrafields.php entry point.
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td style="width:2em">2</td><td><b>'.($langs->trans('RegisterCpfExtraFields') !== 'RegisterCpfExtraFields' ? $langs->trans('RegisterCpfExtraFields') : 'Register CPF ExtraFields').'</b></td></tr>';
print '<tr class="oddeven"><td></td><td>';
print '<p class="opacitymedium">'.($langs->trans('RegisterCpfExtraFieldsDesc') !== 'RegisterCpfExtraFieldsDesc' ? $langs->trans('RegisterCpfExtraFieldsDesc') : 'Adds the <code>is_cpf_liable</code> checkbox on Expense Report lines so payroll can treat claims as CPF-liable income.').'</p>';
print '<a href="upgrade_sql.php" class="butAction"><i class="fas fa-tags"></i> '.($langs->trans('SqlUpgradeRunner') !== 'SqlUpgradeRunner' ? $langs->trans('SqlUpgradeRunner') : 'SQL Upgrade Runner').'</a>';
print '</td></tr>';
print '</table></div>';

// Step 3: Public holidays (optional — seeded by step 1, link for maintenance)
$dictUrl = DOL_URL_ROOT.'/admin/dict.php?mainmenu=home&leftmenu=setup&tabname=c_hrm_public_holiday';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td style="width:2em">3</td><td><b>'.($langs->trans('PublicHolidays') !== 'PublicHolidays' ? $langs->trans('PublicHolidays') : 'Public holidays').' (Singapore)</b> <span class="opacitymedium">— '.($langs->trans('Optional') !== 'Optional' ? $langs->trans('Optional') : 'optional').'</span></td></tr>';
print '<tr class="oddeven"><td></td><td>';
print '<p class="opacitymedium">'.($langs->trans('PublicHolidaysDesc') !== 'PublicHolidaysDesc' ? $langs->trans('PublicHolidaysDesc') : 'Step 1 already seeds Singapore public holidays. Use the link below to review or update them by year (working-day and PH OT calculations use this dictionary).').'</p>';
print '<a href="'.$dictUrl.'" target="_blank" class="butAction"><i class="fas fa-calendar-alt"></i> Setup → Dictionary → Public holidays</a>';
print '</td></tr>';
print '</table></div>';

print '</div>'; // fichecenter

// ── CONFIGURATION (company-specific, can be done after activation) ──
$configTitle = $langs->trans('ConfigurationSection');
if ($configTitle === 'ConfigurationSection') {
	$configTitle = 'Configuration';
}
print '<hr style="margin:1.5em 0">';
print '<div class="fichecenter">';
print '<p class="opacitymedium"><strong>'.$configTitle.'</strong> — '.($langs->trans('ConfigurationSectionDesc') !== 'ConfigurationSectionDesc' ? $langs->trans('ConfigurationSectionDesc') : 'Company UEN, CPF account, schedules, GL accounts, etc. Fill in when ready.').'</p>';
print '<form method="POST" action="setup.php">';
print '<input type="hidden" name="action" value="save"><input type="hidden" name="token" value="'.newToken().'">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="fieldrequired">'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';

$settings = array(
	'SGPAYROLL_COMPANY_UEN'           => array($langs->trans('CompanyUen'),          'text'),
	'SGPAYROLL_CPF_ACCOUNT'           => array($langs->trans('CpfAccount'),          'text'),
	'SGPAYROLL_GLOBAL_WEEKLY_SCHEDULE'=> array($langs->trans('GlobalWeeklySchedule').' <i class="fas fa-info-circle opacitymedium" title="'.dol_escape_htmltag($langs->trans('GlobalWeeklyScheduleHelp')).'"></i>', 'text'),
	'SGPAYROLL_WORKING_HOURS_PER_DAY' => array($langs->trans('WorkingHoursPerDay'),  'int'),
	'SGPAYROLL_FWL_SECTOR'            => array($langs->trans('FwlSector'),           array(''=>'(Select sector)','services'=>'Services','construction'=>'Construction','marine'=>'Marine / Offshore','process'=>'Process')),
	'SGPAYROLL_GL_SALARY'             => array($langs->trans('GlSalary').' <i class="fas fa-info-circle opacitymedium" title="'.$langs->trans('GlSalaryHelp').'"></i>', 'text'),
	'SGPAYROLL_GL_CPF_PAYABLE'        => array($langs->trans('GlCpfPayable'),        'text'),
	'SGPAYROLL_GL_SDL_PAYABLE'        => array($langs->trans('GlSdlPayable'),        'text'),
	'SGPAYROLL_GL_BANK'               => array($langs->trans('GlBank'),              'text'),
	'SGPAYROLL_DEFAULT_PAYMENT_DAY'   => array($langs->trans('DefaultPaymentDay'),   'int'),
	'SGPAYROLL_NAME_DISPLAY_ORDER'    => array($langs->trans('NameDisplayOrder').' <i class="fas fa-info-circle opacitymedium" title="'.dol_escape_htmltag($langs->trans('NameDisplayOrderHelp')).'"></i>', array('lastname_firstname'=>$langs->trans('NameDisplayLastFirst'), 'firstname_lastname'=>$langs->trans('NameDisplayFirstLast'))),
	// Email notification settings
	'SGPAYROLL_SEND_PAYSLIP_EMAIL'    => array($langs->trans('AutoSendPayslipEmail').': <small class="opacitymedium">'.$langs->trans('AutoSendPayslipEmailHelp').'</small>', 'boolean'),
	'SGPAYROLL_EMAIL_SUBJECT'         => array($langs->trans('EmailSubjectTemplate').': <small class="opacitymedium">'.$langs->trans('EmailSubjectTemplateHelp').'</small>', 'text'),
	'SGPAYROLL_EMAIL_BODY'            => array($langs->trans('EmailBodyTemplate').': <small class="opacitymedium">'.$langs->trans('EmailBodyTemplateHelp').'</small>', 'textarea'),
);

foreach ($settings as $constName => $def) {
	list($label, $type) = $def;
	$curVal = getDolGlobalString($constName);
	if ($constName === 'SGPAYROLL_NAME_DISPLAY_ORDER' && $curVal === '') {
		$curVal = 'lastname_firstname';
	}
	print '<tr class="oddeven"><td>'.$label.'</td><td>';
	if ($type === 'boolean') {
		$checked = (getDolGlobalInt($constName) == 1) ? ' checked' : '';
		print '<input type="hidden" name="'.$constName.'" value="0">';
		print '<input type="checkbox" name="'.$constName.'" value="1" class="flat"'.$checked.'>';
	} elseif (is_array($type)) {
		print '<select name="'.$constName.'" class="flat">';
		foreach ($type as $v=>$l) print '<option value="'.$v.'"'.($v==$curVal?' selected':'').'>'.dol_escape_htmltag($l).'</option>';
		print '</select>';
	} elseif (in_array($constName, $glAccountConsts, true) && $useAccountingSelect && !empty($formaccounting)) {
		// GL account: dropdown from chart of accounts (select_in=1, select_out=1 => use account_number)
		$sel = $formaccounting->select_account($curVal, $constName, 1, array(), 1, 1);
		if ($sel === -1) {
			print '<input type="text" name="'.$constName.'" value="'.dol_escape_htmltag($curVal).'" class="flat" style="width:300px">';
			$langs->load("errors");
			print ' <span class="opacitymedium">'.$langs->trans("ErrorYouMustFirstSetupYourChartOfAccount").'</span>';
		} else {
			print $sel;
		}
	} else {
		$inputVal = $curVal;
		if ($constName === 'SGPAYROLL_EMAIL_SUBJECT' && $inputVal === '') {
			$inputVal = '[Payslip] {COMPANY} - {employee} - {PERIOD}';
		}
		if ($type === 'textarea') {
			print '<textarea name="'.$constName.'" rows="4" class="flat" style="width:400px">'.dol_escape_htmltag($inputVal).'</textarea>';
		} else {
			print '<input type="'.($type==='int'?'number':'text').'" name="'.$constName.'" value="'.dol_escape_htmltag($inputVal).'" class="flat" style="width:300px">';
		}
		if ($constName === 'SGPAYROLL_GLOBAL_WEEKLY_SCHEDULE') {
			print '<div class="opacitymedium small paddingtop" style="max-width:500px">'.nl2br(dol_escape_htmltag($langs->trans('GlobalWeeklyScheduleHelp'))).'</div>';
		}
		if (in_array($constName, $glAccountConsts, true) && !$useAccountingSelect) {
			print ' <span class="opacitymedium small">('.$langs->trans('EnableAccountingModuleToSelectFromChart').')</span>';
		}
	}
	print '</td></tr>';
}
print '</table>';
print '<div class="tabsAction"><input type="submit" value="'.$langs->trans('Save').'" class="butAction"></div>';
print '</form>';
print '</div>'; // fichecenter

// ── ABOUT ────────────────────────────────────────────────────────────────────
$aboutTitle = $langs->trans('SGPayrollAboutTitle');
if ($aboutTitle === 'SGPayrollAboutTitle') $aboutTitle = 'About SG Payroll';
$aboutDesc = $langs->trans('SGPayrollAboutDesc');
if ($aboutDesc === 'SGPayrollAboutDesc') {
	$aboutDesc = 'Singapore payroll localization for Dolibarr: CPF (OW/AW), SDL/SHG/FWL, IRAS AIS (IR8A/IR21), payroll runs & payslips, and employee self-service.';
}
$aboutNote = $langs->trans('SGPayrollAboutNote');
if ($aboutNote === 'SGPayrollAboutNote') {
	$aboutNote = 'Regulatory rules change over time. Always validate exports and rates against the latest MOM/IRAS/CPF Board publications.';
}
print '<hr style="margin:1.5em 0">';
print '<div class="fichecenter">';
print '<div class="titre">'.$aboutTitle.'</div>';
print '<p class="opacitymedium" style="max-width:1100px">'.$aboutDesc.'</p>';
print '<p class="opacitymedium small" style="max-width:1100px"><strong>Note:</strong> '.$aboutNote.'</p>';
print '</div>';

// Debug section for user
print '<details style="margin-top:20px; color:#666"><summary>Technical Debug Information (Click to expand if installation fails)</summary>';
print '<ul>';
print '<li>DOL_DOCUMENT_ROOT: '.DOL_DOCUMENT_ROOT.'</li>';
print '<li>Current File Path: '.__FILE__.'</li>';
print '<li>Detected SQL Dir (BuildPath): '.dol_buildpath('/sgpayroll/sql/', 0).'</li>';
print '<li>Detected SQL Dir (Local): '.realpath(dirname(dirname(__FILE__)).'/sql/').'/</li>';
print '<li>Database Type: '.$db->type.'</li>';
print '<li>Database Version: '.$db->getVersion().'</li>';
print '<li>Entity ID: '.$conf->entity.'</li>';
print '</ul></details>';

print '<hr>';
print '<div>'.sgpayroll_portal_links('setup').'</div>';

dol_fiche_end();
llxFooter(); $db->close();
