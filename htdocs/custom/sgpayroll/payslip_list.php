<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        payslip_list.php
 * \ingroup     sgpayroll
 * \brief       Monthly payroll run list with Dolibarr-standard list conventions.
 *              HR Manager sees all payslips; employees see their own (mode=own).
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load Dolibarr main.inc.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('sgpayroll/class/payrollrecord.class.php');
dol_include_once('sgpayroll/class/employee.class.php');
dol_include_once('sgpayroll/class/payrollcalc.class.php');
dol_include_once('sgpayroll/lib/sgpayroll.lib.php');

// ── Security ──────────────────────────────────────────────────────────────────
if (!isModEnabled('sgpayroll')) accessforbidden();

$ownOnly = (GETPOST('mode', 'aZ') === 'own');
$canSeeOthers = $user->admin || $user->hasRight('sgpayroll', 'payroll', 'read') || $user->hasRight('sgpayroll', 'payroll', 'approve');
if ($ownOnly) {
	if (!$user->admin && !$user->hasRight('sgpayroll', 'employee', 'self_write') && !$user->hasRight('sgpayroll', 'payroll', 'read')) {
		accessforbidden();
	}
} else {
	if (!$user->admin && !$user->hasRight('sgpayroll', 'payroll', 'read') && !$user->hasRight('sgpayroll', 'payroll', 'approve')) {
		accessforbidden();
	}
}

// ── Hooks ─────────────────────────────────────────────────────────────────────
$hookmanager->initHooks(array('sgpayrollpaysliplist'));

// ── Translations ──────────────────────────────────────────────────────────────
$langs->loadLangs(array('sgpayroll@sgpayroll', 'compta', 'hrm'));

// ── Parameters ────────────────────────────────────────────────────────────────
$action      = GETPOST('action', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ?: 'sgpayrollpaysliplist';
$optioncss   = GETPOST('optioncss', 'aZ');

// Pagination
$limit     = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 'pl.rowid';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'DESC';
$page      = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if ($page < 0) $page = 0;
$offset = $limit * $page;

// Filters
$search_employee = GETPOST('search_employee', 'alphanohtml');
$search_year     = GETPOST('search_year', 'int') ?: (int) dol_print_date(dol_now(), '%Y');
$search_month    = GETPOST('search_month', 'int');
$search_status   = GETPOST('search_status', 'alpha');

if (GETPOST('button_prev_month', 'alpha')) {
	$search_month = $search_month ?: (int) dol_print_date(dol_now(), '%m');
	$search_month--;
	if ($search_month < 1) { $search_month = 12; $search_year--; }
} elseif (GETPOST('button_next_month', 'alpha')) {
	$search_month = $search_month ?: (int) dol_print_date(dol_now(), '%m');
	$search_month++;
	if ($search_month > 12) { $search_month = 1; $search_year++; }
}

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$search_employee = '';
	$search_year     = (int) dol_print_date(dol_now(), '%Y');
	$search_month    = '';
	$search_status   = '';
}

// ── Actions ───────────────────────────────────────────────────────────────────
if ($action === 'createrun') {
	if (!$user->admin && !$user->hasRight('sgpayroll', 'payroll', 'approve') && !$user->hasRight('sgpayroll', 'payroll', 'create')) {
		setEventMessages($langs->trans("NoPermissionToCreatePayrollRun"), null, 'errors');
	} else {
		// Verify CSRF token
		$token_provided = GETPOST('token', 'alpha');
		$token_session = $_SESSION['token'] ?? '';
		if ($token_provided !== $token_session || $token_session === '') {
			setEventMessages($langs->trans("Errors").': CSRF Token invalid. Please reload the page.', null, 'errors');
			header('Location: '.$_SERVER["PHP_SELF"]); exit;
		}
		$payYear  = GETPOST('new_pay_year', 'int');
		$payMonth = GETPOST('new_pay_month', 'int');
		$user_ids = GETPOST('fk_user', 'array:int');
		if (!is_array($user_ids)) {
			$user_ids = array();
		}
		$user_ids = array_filter(array_map('intval', $user_ids));
		
		if (empty($user_ids)) {
			setEventMessages($langs->trans('ErrorNoEmployeeSelected'), null, 'errors');
		} else {
			$ref = 'PAY-'.$payYear.'-'.str_pad($payMonth, 2, '0', STR_PAD_LEFT);
			
			$run = new SGPayrollRecord($db);
			$run->pay_year  = $payYear;
			$run->pay_month = $payMonth;
			$run->ref       = $ref;
			$run->status    = 'draft';
			
			$db->begin();
			
			// Ensure run header exists
			$sqlChk = "SELECT rowid FROM ".MAIN_DB_PREFIX."sgpayroll_payroll";
			$sqlChk .= " WHERE ref = '".$db->escape($ref)."' AND entity = ".(int)$conf->entity;
			$resChk = $db->query($sqlChk);
			if ($resChk && $db->num_rows($resChk) > 0) {
				$objChk = $db->fetch_object($resChk);
				$run->id = $objChk->rowid;
				$run->fk_payroll = $run->id;
			} else {
				if ($run->create($user) <= 0) {
					$db->rollback();
					setEventMessages($run->error ?: $langs->trans('ErrorCreatingPayrollRun'), null, 'errors');
					header('Location: '.$_SERVER["PHP_SELF"]);
					exit;
				}
			}

			$count = 0;
			// Use Default Payment Day from setup (e.g. 25), capped by last day of month
			$defaultDay = (int) getDolGlobalString('SGPAYROLL_DEFAULT_PAYMENT_DAY');
			$lastDayOfMonth = (int) date('t', mktime(0, 0, 0, $payMonth, 1, $payYear));
			if ($defaultDay <= 0 || $defaultDay >= 32) {
				$defaultDay = $lastDayOfMonth;
			} else {
				$defaultDay = min($defaultDay, $lastDayOfMonth);
			}
			$payDate = sprintf('%04d-%02d-%02d', $payYear, $payMonth, $defaultDay);
			$skipFetch = 0;
			$skipExists = 0;
			$skipCompute = 0;
			$saveErrorCount = 0;
			$lastSaveError = '';
			foreach ($user_ids as $fk_user) {
				$emp = new SGPayrollEmployee($db);
				if (!$emp->fetchByUser($fk_user)) {
					$skipFetch++;
					dol_syslog('SGPayroll payslip_list createrun: fetchByUser failed for fk_user='.$fk_user.' (employee not in sgpayroll_employee or wrong entity)', LOG_WARNING);
					continue;
				}
				// Check if line already exists for this user in this run
				$sqlLine = "SELECT rowid FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll = ".(int)$run->id." AND fk_user = ".(int)$fk_user;
				$resLine = $db->query($sqlLine);
				if ($resLine && $db->num_rows($resLine) > 0) {
					$skipExists++;
					continue;
				}

				$workDaysEmp = sgpayroll_worked_days_in_month($payYear, $payMonth, $emp);
				$inputs = array(
					'pay_date'  => $payDate,
					'work_days' => $workDaysEmp,
					'allowances'=> array()
				);
				$result = SGPayrollCalc::computePayrollLine($db, (array)$emp, $inputs);
				if (empty($result)) {
					$skipCompute++;
					dol_syslog("SGPayroll: computePayrollLine returned empty for user $fk_user", LOG_ERR);
					continue;
				}

				$payLine = new SGPayrollRecord($db);
				$payLine->fk_payroll = $run->id;
				$payLine->fk_user    = $fk_user;
				$payLine->pay_year     = $payYear;
				$payLine->pay_month    = $payMonth;
				$payLine->status       = 'draft';
				$payLine->work_days    = $workDaysEmp;
				$payLine->payment_date = $payDate; // Required for DB DATE column

				foreach ($result as $k => $v) {
					if (!is_array($v)) $payLine->$k = $v;
				}

				if ($payLine->savePayslipLine($user) > 0) {
					$count++;
				} else {
					$saveErrorCount++;
					$lastSaveError = $payLine->error ?: $db->lasterror();
					dol_syslog('SGPayroll payslip_list createrun: savePayslipLine failed for fk_user='.$fk_user.' : '.$lastSaveError, LOG_ERR);
				}
			}

			if ($count > 0) {
				$db->commit();
				setEventMessages($langs->trans('GeneratedDraftPayslips', $count), null, 'mesgs');
			} else {
				$db->rollback();
				setEventMessages($langs->trans('NoDraftPayslipsGenerated'), null, 'warnings');
				$reasons = array();
				if ($skipFetch > 0) {
					$reasons[] = $skipFetch.' '.$langs->trans('EmployeeNotInSGPayroll');
				}
				if ($skipExists > 0) {
					$reasons[] = $skipExists.' '.$langs->trans('AlreadyHasPayslipForPeriod');
				}
				if ($skipCompute > 0) {
					$reasons[] = $skipCompute.' '.$langs->trans('ComputeFailed');
				}
				if ($saveErrorCount > 0) {
					$reasons[] = $saveErrorCount.' '.$langs->trans('SaveFailed');
					if ($lastSaveError !== '') {
						setEventMessages($lastSaveError, null, 'errors');
					}
				}
				if (!empty($reasons)) {
					setEventMessages(implode('; ', $reasons), null, 'warnings');
				}
			}
			
			header('Location: payslip_list.php?search_year='.$payYear.'&search_month='.$payMonth);
			exit;
		}
	}
}

// ── SQL ───────────────────────────────────────────────────────────────────────
$sqlfields  = "SELECT pl.rowid, pl.fk_user, p.pay_year, p.pay_month, pl.status,";
$sqlfields .= " pl.gross_salary, pl.employee_cpf, pl.net_pay, pl.net_pay_fc, pl.contract_currency,";
$sqlfields .= " pl.exchange_rate, pl.sdl_amount, pl.fwl_amount, pl.work_days, pl.upl_days,";
$sqlfields .= " u.lastname, u.firstname, u.login";
$sql        = $sqlfields;
$sql       .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sql       .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sql       .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
$sql       .= " WHERE pl.fk_user > 0 AND pl.entity = ".(int)$conf->entity;

if ($ownOnly) {
	// Self-service users without HR read rights may only see their own payslips (IDOR guard)
	$targetUser = ($canSeeOthers ? GETPOST('fk_user', 'int') : 0) ?: $user->id;
	$sql .= " AND pl.fk_user = ".(int)$targetUser;
}
if ($search_employee) {
	$sql .= natural_search(array('u.lastname', 'u.firstname', 'u.login'), $search_employee);
}
if ($search_year) {
	$sql .= " AND p.pay_year = ".(int)$search_year;
}
if ($search_month) {
	$sql .= " AND p.pay_month = ".(int)$search_month;
}
if ($search_status && $search_status != '-1') {
	$sql .= " AND pl.status = '".$db->escape($search_status)."'";
}

// Hook extra where
$parameters = array();
$hookmanager->executeHooks('printFieldListWhere', $parameters);
$sql .= $hookmanager->resPrint;

// Count total records for pagination
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) AS nb', $sql);
	$rescount = $db->query($sqlforcount);
	if ($rescount) {
		$obj = $db->fetch_object($rescount);
		$nbtotalofrecords = $obj->nb;
	}
	if (($page * $limit) > $nbtotalofrecords) { $page = 0; $offset = 0; }
}

$sql .= $db->order($sortfield, $sortorder);
if ($limit) $sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) { dol_print_error($db); exit; }
$num = $db->num_rows($resql);

// ── Page output ───────────────────────────────────────────────────────────────
$form = new Form($db);

$title    = $ownOnly ? $langs->trans('MyPayslips') : $langs->trans('SgpayrollPayrollRun');
$help_url = 'https://www.mom.gov.sg/employment-practices/salary/itemised-payslips';
$bodyclass = 'bodyforlist mod-sgpayroll page-payslip-list';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', $bodyclass);

if ($ownOnly) {
	$fk_user_for_tabs = ($canSeeOthers ? GETPOST('fk_user', 'int') : 0) ?: $user->id;
	$user_for_title = new User($db);
	$user_for_title->fetch($fk_user_for_tabs);
	print load_fiche_titre($langs->trans('EmployeeProfile').' — '.$user_for_title->getFullName($langs), '', 'title_hrm');

	$head = sgpayroll_employee_prepare_head($fk_user_for_tabs);
	dol_fiche_head($head, 'payslips', '', 0, '');
}

// Build link params
$param  = '&mode='.($ownOnly ? 'own' : 'all');
if ($search_employee) $param .= '&search_employee='.urlencode($search_employee);
if ($search_year)     $param .= '&search_year='.$search_year;
if ($search_month)    $param .= '&search_month='.$search_month;
if ($search_status)   $param .= '&search_status='.urlencode($search_status);
if ($limit != $conf->liste_limit && $limit > 0) $param .= '&limit='.(int)$limit;

// "New Run" section (HR only)
$newbutton = '';
if (!$ownOnly && ($user->hasRight('sgpayroll', 'payroll', 'create') || $user->hasRight('sgpayroll', 'payroll', 'approve'))) {
	$new_year = ($search_year ?: (int) dol_print_date(dol_now(), '%Y'));
	if ($search_month) {
		$new_month = (int) $search_month;
	} else {
		// Auto-advance to the next available month for the specified year
		$sqlMax = "SELECT MAX(p.pay_month) as max_m FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll WHERE p.pay_year = ".(int)$new_year." AND pl.entity = ".(int)$conf->entity;
		$resMax = $db->query($sqlMax);
		$max_month = 0;
		if ($resMax && ($objMax = $db->fetch_object($resMax))) {
			$max_month = (int)$objMax->max_m;
		}
		if ($max_month > 0 && $max_month < 12) {
			$new_month = $max_month + 1;
		} else if ($max_month == 12) {
			$new_month = 12;
		} else {
			$new_month = ($new_year == (int)dol_print_date(dol_now(), '%Y')) ? (int)dol_print_date(dol_now(), '%m') : 1;
		}
	}

	// Fetch candidates: Onboarded, Active, NOT already in this period, proper multi-company filter
	$sqlCand = "SELECT u.rowid, u.lastname, u.firstname, e.basic_salary";
	$sqlCand .= " FROM ".MAIN_DB_PREFIX."user u";
	$sqlCand .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = u.rowid AND e.entity = ".(int)$conf->entity;
	$sqlCand .= " WHERE e.status = 1 AND u.statut = 1";
	// Filter by employment dates comparing against payroll month bounds
	$first_day = $db->idate(dol_get_first_day($new_year, $new_month));
	$last_day  = $db->idate(dol_get_last_day($new_year, $new_month));
	// Use COALESCE to prefer core user dates over the custom HR fields if they exist
	$sqlCand .= " AND (COALESCE(u.dateemployment, e.work_contract_date) IS NULL OR COALESCE(u.dateemployment, e.work_contract_date) <= '1900-01-01' OR COALESCE(u.dateemployment, e.work_contract_date) <= '".$last_day."')";
	$sqlCand .= " AND (COALESCE(u.dateemploymentend, e.cessation_date) IS NULL OR COALESCE(u.dateemploymentend, e.cessation_date) <= '1900-01-01' OR COALESCE(u.dateemploymentend, e.cessation_date) >= '".$first_day."')";
	$sqlCand .= " AND u.rowid NOT IN (";
	$sqlCand .= "   SELECT pl.fk_user FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
	$sqlCand .= "   INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
	$sqlCand .= "   WHERE p.pay_year = ".(int)$new_year." AND p.pay_month = ".(int)$new_month;
	$sqlCand .= "   AND p.entity = ".(int)$conf->entity;
	$sqlCand .= " )";
	$sqlCand .= " ORDER BY u.lastname, u.firstname";
	
	$resCand = $db->query($sqlCand);
	$candidates = array();
	while ($resCand && $objC = $db->fetch_object($resCand)) {
		$candidates[] = $objC;
	}

	if (!empty($candidates)) {
		print '<div class="div-table-responsive" style="margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; background: #f9f9f9; border-radius: 4px;">';
		print '<h3>'.img_picto('', 'add', 'class="paddingright"').$langs->trans('CreatePayrollRunFor').' '.dol_print_date(dol_mktime(0, 0, 0, $new_month, 1, $new_year), '%B %Y').'</h3>';
		print '<form method="POST" action="payslip_list.php">';
		print '<input type="hidden" name="action" value="createrun">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="new_pay_year" value="'.$new_year.'">';
		print '<input type="hidden" name="new_pay_month" value="'.$new_month.'">';
		
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td width="30"><input type="checkbox" id="checkall_users" onclick="toggleAllUsers(this)"></td>';
		print '<td>'.$langs->trans('Employee').'</td>';
		print '<td class="right">'.$langs->trans('BasicSalary').'</td>';
		print '</tr>';
		
		foreach ($candidates as $cand) {
			print '<tr class="oddeven">';
			print '<td><input type="checkbox" name="fk_user[]" value="'.$cand->rowid.'" class="user_checkbox"></td>';
			print '<td>'.dol_escape_htmltag(sgpayroll_format_employee_name($cand->firstname, $cand->lastname)).'</td>';
			print '<td class="right">'.price($cand->basic_salary).'</td>';
			print '</tr>';
		}
		print '</table>';
		
		print '<div class="margin-top-10">';
		print '<button type="submit" class="butAction">'.$langs->trans('GenerateDrafts').'</button>';
		print '</div>';
		print '</form>';
		print '</div>';
		
		// JS for checkall
		print '<script>
		function toggleAllUsers(master) {
			var cb = document.querySelectorAll(".user_checkbox");
			cb.forEach(function(c) { c.checked = master.checked; });
		}
		</script>';
	} else {
		print '<div class="info">'.img_picto('', 'info', 'class="paddingright"').$langs->trans('AllEmployeesHavePayslipsForPeriod').'</div><br>';
	}

	$summary_url = './export/pdf_summary.php?year='.$new_year.'&month='.$new_month;
	$newbutton = '<a href="'.$summary_url.'" class="butAction" style="margin-left:5px" target="_blank">'.img_picto('', 'pdf', 'class="paddingright"').$langs->trans('DownloadSummary').'</a>';
}

print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder,
	'', $num, $nbtotalofrecords, 'object_bill', 0, $newbutton, '', $limit, 0, 0, 1);

// ── Filter form ───────────────────────────────────────────────────────────────
// ── Filter Panel ─────────────────────────────────────────────────────────────
print '<div class="div-table-responsive-filter" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #e1e5eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
print '<form method="GET" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'" style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end;">'."\n";
print '<input type="hidden" name="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
print '<input type="hidden" name="mode" value="'.($ownOnly ? 'own' : 'all').'">';

if (!$ownOnly) {
	print '<div class="filter_field"><label style="display:block; font-weight:600; margin-bottom:5px; color:#444;">'.$langs->trans('Employee').':</label>';
	print '<input class="flat" style="width:180px; padding:6px;" type="text" name="search_employee" value="'.dol_escape_htmltag($search_employee).'"></div>';
}

print '<div class="filter_field"><label style="display:block; font-weight:600; margin-bottom:5px; color:#444;">'.$langs->trans('Period').':</label>';
print '<div style="display:inline-flex; align-items:center; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; background: #fff;">';
print '<button type="submit" name="button_prev_month" value="1" title="Previous Month" style="background:#f4f4f4; border:none; border-right:1px solid #ccc; padding:6px 12px; cursor:pointer; color:#555;"><i class="fas fa-chevron-left"></i></button>';
print '<select name="search_month" class="flat" style="border:none; outline:none; color:#333; min-width:95px; padding:6px; font-weight:bold; cursor:pointer;" onchange="this.form.submit();">';
print '<option value="0"></option>';
foreach(array(1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December') as $m=>$label) {
	print '<option value="'.$m.'"'.((int)$search_month===$m?' selected':'').'>'.$langs->trans($label).'</option>';
}
print '</select>';
print '<select name="search_year" class="flat" style="border:none; outline:none; color:#333; min-width:75px; padding:6px; font-weight:bold; cursor:pointer; border-left:1px solid #eee;" onchange="this.form.submit();">';
for($y = 2020; $y <= 2050; $y++) print '<option value="'.$y.'"'.((int)$search_year==$y?' selected':'').'>'.$y.'</option>';
print '</select>';
print '<button type="submit" name="button_next_month" value="1" title="Next Month" style="background:#f4f4f4; border:none; border-left:1px solid #ccc; padding:6px 12px; cursor:pointer; color:#555;"><i class="fas fa-chevron-right"></i></button>';
print '</div></div>';

print '<div class="filter_field"><label style="display:block; font-weight:600; margin-bottom:5px; color:#444;">'.$langs->trans('Status').':</label>';
print $form->selectarray('search_status', array(
	'draft'=>$langs->trans('StatusDraft'), 'submitted'=>$langs->trans('Submitted'),
	'approved'=>$langs->trans('Approved'), 'paid'=>$langs->trans('Paid')
), $search_status, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150 flat', 0, 0, 0, 'padding:6px;');
print '</div>';

print '<div style="margin-bottom: 2px;">';
print $form->showFilterButtons();
print '</div>';

print '</form></div>';

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">'."\n";

// Header row
print '<tr class="liste_titre">';
if (!$ownOnly) {
	print_liste_field_titre($langs->trans('Employee'), $_SERVER['PHP_SELF'], 'u.lastname', '', $param, '', $sortfield, $sortorder);
}
print_liste_field_titre($langs->trans('Period'), $_SERVER['PHP_SELF'], 'p.pay_year,p.pay_month', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre($langs->trans('PaidDays'), $_SERVER['PHP_SELF'], 'pl.work_days', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre($langs->trans('GrossSalary'), $_SERVER['PHP_SELF'], 'pl.gross_salary', '', $param, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre($langs->trans('EmployeeCPF'), $_SERVER['PHP_SELF'], 'pl.employee_cpf', '', $param, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre($langs->trans('NetPay'), $_SERVER['PHP_SELF'], 'pl.net_pay', '', $param, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre($langs->trans('Currency'), $_SERVER['PHP_SELF'], 'pl.contract_currency', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre($langs->trans('Status'), $_SERVER['PHP_SELF'], 'pl.status', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', $param, '', '', '', 'center maxwidthsearch ');
print '</tr>'."\n";

// Data rows
$i = 0;
$statusLabels = array('draft'=>'Draft','submitted'=>'Submitted','approved'=>'Approved','paid'=>'Paid');
$statusBadges = array('draft'=>'badge-status0','submitted'=>'badge-status4','approved'=>'badge-status1','paid'=>'badge-status9');
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);
	if (!$obj) break;

	$empName   = sgpayroll_format_employee_name($obj->firstname, $obj->lastname);
	$currency  = $obj->contract_currency ?: 'SGD';
	$monthName = dol_print_date(dol_mktime(0, 0, 0, $obj->pay_month, 1, $obj->pay_year), '%b');
	$statusKey = $obj->status ?: 'draft';

	// Net pay display — show foreign currency if applicable
	if ($currency !== 'SGD' && $obj->net_pay_fc > 0) {
		$netDisplay = price($obj->net_pay).' <span class="opacitymedium small">('.number_format($obj->net_pay_fc, 2).' '.$currency.')</span>';
	} else {
		$netDisplay = price($obj->net_pay);
	}

	print '<tr class="oddeven">';
	if (!$ownOnly) {
		print '<td class="tdoverflowmax200 nowraponall"><a href="payslip_card.php?id='.$obj->rowid.'">'.dol_escape_htmltag($empName).'</a></td>';
	}
	$paidDays = (float)$obj->work_days - (float)$obj->upl_days;
	$paidDaysDisplay = ($obj->work_days > 0) ? (sgpayroll_format_days($paidDays).' / '.sgpayroll_format_days($obj->work_days)) : '0 / 0';
	
	print '<td class="center"><strong>'.dol_escape_htmltag($monthName).' '.$obj->pay_year.'</strong></td>';
	print '<td class="center">'.$paidDaysDisplay.'</td>';
	print '<td class="right nowraponall">'.price($obj->gross_salary).'</td>';
	print '<td class="right nowraponall">'.price($obj->employee_cpf).'</td>';
	print '<td class="right nowraponall"><strong>'.$netDisplay.'</strong></td>';
	print '<td class="center">'.dol_escape_htmltag($currency).'</td>';
	$statusClass = sgpayroll_status_class($statusKey);
	$statusLabelsDict = array('draft'=>'Draft','submitted'=>'Submitted','approved'=>'Approved','paid'=>'Paid');
	$statusLabel = $langs->trans($statusLabelsDict[$statusKey] ?? ucfirst($statusKey));
	print '<td class="center"><span class="badge '.$statusClass.'">'.dol_escape_htmltag($statusLabel).'</span></td>';
	// Download filename
	$payMonthStr = str_pad((string)$obj->pay_month, 2, '0', STR_PAD_LEFT);
	$empNameClean = preg_replace('/[^A-Za-z0-9_\-]/', '', preg_replace('/\s+/', '_', trim((string)$empName)));
	$downloadName = 'payslip_'.$empNameClean.'_'.$obj->pay_year.$payMonthStr.'.pdf';

	print '<td class="center nowraponall">';
	print '<a href="export/pdf_payslip.php?id='.$obj->rowid.'&mode=inline" target="_blank" title="'.$langs->trans('Preview').'">'.img_picto($langs->trans('Preview'), 'eye').'</a> ';
	if ($statusKey === 'approved' || $statusKey === 'paid') {
		// Using mode=inline + download attribute for maximum browser compatibility
		print '<a href="export/pdf_payslip.php?id='.$obj->rowid.'&mode=inline" download="'.$downloadName.'" title="'.$langs->trans('Download').'">'.img_picto($langs->trans('Download'), 'pdf').'</a>';
	}
	print '</td>';
	print '</tr>'."\n";
	$i++;
}
if ($num == 0) {
	$colspan = $ownOnly ? 8 : 9;
	print '<tr><td colspan="'.$colspan.'" class="opacitymedium center">'.$langs->trans('NoPayrollFound').'</td></tr>';
}

print '</table>';
print '</div>'; // div-table-responsive

// Portal links
print '<div class="tabsAction">';
print sgpayroll_portal_links('cpf');
print '</div>';

if ($ownOnly) {
	dol_fiche_end();
	print '</div>';
}

llxFooter();
$db->close();
