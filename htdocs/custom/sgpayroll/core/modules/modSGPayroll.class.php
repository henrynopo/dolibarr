<?php
/* Copyright (C) 2024-2026  SgPayroll - Singapore Payroll for Dolibarr
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * CHANGELOG:
 * ==========
 * v1.6 (2026-07-30)
 *   - Added configurable SDL/SHG rates via llx_sgpayroll_statutory_rates table
 *   - Added new admin page: setup_statutory_rates.php (Statutory Rates config)
 *   - Updated payrollcalc.class.php: calculateSDL() and calculateSHG() now read from DB
 *   - Added 2027 CPF rate changes (SC 55-60: ER 16%->16.5%, EE 18%->19%; SC 60-65: ER 12.5%->13%, EE 12.5%->13%)
 *   - New menu: SG Payroll -> Settings -> Statutory Rates
 *
 * v1.5 (2025)
 *   - IRAS AIS improvements
 *
 * v1.4 (2025)
 *   - Multi-currency support for foreign contracts
 *
 * v1.3 (2024)
 *   - CPF rate table management
 *
 * v1.2 (2024)
 *   - Initial stable release
 */

/**
 *  \defgroup   sgpayroll  Module Sgpayroll
 *  \brief      Singapore Payroll module for Dolibarr.
 *  \file       htdocs/custom/sgpayroll/core/modules/modSgpayroll.class.php
 *  \ingroup    sgpayroll
 *  \brief      Description and activation file for module Sgpayroll (Dolibarr 22.0 compatible)
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module Sgpayroll
 */
class modSGPayroll extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;

		// Id for module (must be unique)
		$this->numero = 501100;
		$this->rights_class = 'sgpayroll';

		$this->family = "hr";
		$this->module_position = '55';
		$this->name = "SGPayroll"; // Hardcoded to avoid runtime param errors
		$this->description = "Singapore Payroll — CPF, SDL/SHG, IRAS AIS, Payslips";
		$this->descriptionlong = "Singapore payroll localization for Dolibarr: CPF contributions (OW/AW), statutory levies (SDL/SHG/FWL), IRAS AIS exports (IR8A/IR21), payroll runs & payslips, plus leave/claims integration.";
		$this->version = '1.6';
		$this->const_name = 'MAIN_MODULE_SGPAYROLL';
		$this->picto = 'salary';

		$this->module_parts = array(
			'triggers' => 1,
			'menus' => 1,
			'hooks' => array('usercard'),
			'css' => array('/sgpayroll/css/sgpayroll.css'),
		);

		$this->dirs = array("/sgpayroll/temp");
		$this->config_page_url = array("setup.php@sgpayroll");
		$this->hidden = false;
		$this->depends = array();
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(11, 0); 
		$this->langfiles = array("sgpayroll@sgpayroll");

		// Cron jobs (registered as disabled by default; enable in Scheduled Jobs)
		$this->cronjobs = array(
			0 => array(
				'entity' => 0,
				'label' => 'SGPayroll reminders',
				'jobtype' => 'method',
				'class' => 'custom/sgpayroll/cron/reminder_cron.php',
				'objectname' => 'SGPayrollReminderCron',
				'method' => 'run',
				'parameters' => '',
				'comment' => 'Document expiry and AIS review reminder emails',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 0,
				'priority' => 5,
				'test' => 'isModEnabled(\'sgpayroll\')',
			),
		);

		// Permissions
		$this->rights = array();
		$r = 0;
		
		// 1. Employee
		$r++;
		$this->rights[$r][0] = 501101;
		$this->rights[$r][1] = 'Read employee payroll profiles';
		$this->rights[$r][2] = 'r'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'employee'; $this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = 501102;
		$this->rights[$r][1] = 'Create/Modify employee payroll profiles';
		$this->rights[$r][2] = 'w'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'employee'; $this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = 501103;
		$this->rights[$r][1] = 'Modify own employee profile (Self-Service)';
		$this->rights[$r][2] = 'w'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'employee'; $this->rights[$r][5] = 'self_write';
		
		// 2. Payroll
		$r++;
		$this->rights[$r][0] = 501111;
		$this->rights[$r][1] = 'Consult all payslips';
		$this->rights[$r][2] = 'r'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'payroll'; $this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = 501112;
		$this->rights[$r][1] = 'Create/approve payslips';
		$this->rights[$r][2] = 'w'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'payroll'; $this->rights[$r][5] = 'approve';

		// 3. AIS
		$r++;
		$this->rights[$r][0] = 501121;
		$this->rights[$r][1] = 'Review/Confirm AIS records';
		$this->rights[$r][2] = 'w'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'ais'; $this->rights[$r][5] = 'review';

		// 4. Exports
		$r++;
		$this->rights[$r][0] = 501131;
		$this->rights[$r][1] = 'Generate statutory exports (CPF/IRAS)';
		$this->rights[$r][2] = 'w'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'export'; $this->rights[$r][5] = 'iras';
		
		// 5. Leave
		$r++;
		$this->rights[$r][0] = 501141;
		$this->rights[$r][1] = 'Apply for own leave';
		$this->rights[$r][2] = 'w'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'leave'; $this->rights[$r][5] = 'apply';
		$r++;
		$this->rights[$r][0] = 501142;
		$this->rights[$r][1] = 'Approve employee leave';
		$this->rights[$r][2] = 'w'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'leave'; $this->rights[$r][5] = 'approve';
		
		// 6. Claims
		$r++;
		$this->rights[$r][0] = 501151;
		$this->rights[$r][1] = 'Submit own expense claims';
		$this->rights[$r][2] = 'w'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'claims'; $this->rights[$r][5] = 'submit';
		$r++;
		$this->rights[$r][0] = 501152;
		$this->rights[$r][1] = 'Approve employee claims';
		$this->rights[$r][2] = 'w'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'claims'; $this->rights[$r][5] = 'approve';

		// ── Top menu ──────────────────────────────────────────────────────────
		$this->menu[$r++] = array(
			'fk_menu' => '', 'type' => 'top', 'titre' => 'ModuleSGPayrollName', 'mainmenu' => 'sgpayroll',
			'url' => '/sgpayroll/employee_list.php?mainmenu=sgpayroll',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 1000 + $r, 'enabled' => 1,
			'perms' => '$user->admin || $user->hasRight("sgpayroll","payroll","read") || $user->hasRight("sgpayroll","employee","read") || $user->hasRight("sgpayroll","claims","submit")',
			'prefix' => '<span class="fas fa-briefcase fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-briefcase', 'user' => 2
		);

		// ═══════════════════════════════════════════════════════════════════════
		// GROUP 1: Employees
		// ═══════════════════════════════════════════════════════════════════════
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll', 'type' => 'left',
			'titre' => 'Employees', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_emp',
			'url' => '/sgpayroll/employee_list.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 100, 'enabled' => 1,
			'prefix' => '<span class="fas fa-users fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-users', 'user' => 2
		);

		// ═══════════════════════════════════════════════════════════════════════
		// GROUP 2: Payroll (parent) → HR Payslips + Finance Salary Payments
		// ═══════════════════════════════════════════════════════════════════════
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll', 'type' => 'left',
			'titre' => 'SgpayrollPayroll', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_payroll',
			'url' => '/sgpayroll/payslip_list.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 110, 'enabled' => 1,
			'prefix' => '<span class="fas fa-money-bill-wave fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-money-bill-wave', 'perms' => '$user->admin || $user->hasRight("sgpayroll","payroll","read")', 'user' => 2
		);
		// 2a. Monthly Payslips (HR)
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_payroll', 'type' => 'left',
			'titre' => 'SgpayrollPayrollRun', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_run',
			'url' => '/sgpayroll/payslip_list.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 111, 'enabled' => 1,
			'prefix' => '<span class="fas fa-play fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-play', 'perms' => '$user->admin || $user->hasRight("sgpayroll","payroll","read")', 'user' => 0
		);
		// 2b. Salary Payments (Finance)
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_payroll', 'type' => 'left',
			'titre' => 'SgpayrollSalaryPayments', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_payments',
			'url' => '/salaries/list.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 112, 'enabled' => '$conf->salaries->enabled',
			'prefix' => '<span class="fas fa-university fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-university', 'perms' => '$user->admin || $user->hasRight("salaries","read")', 'user' => 0
		);
		// 2c. Payroll Analytics Dashboard
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_payroll', 'type' => 'left',
			'titre' => 'PayrollDashboard', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_dashboard',
			'url' => '/sgpayroll/payroll_dashboard.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 113, 'enabled' => 1,
			'prefix' => '<span class="fas fa-chart-bar fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-chart-bar', 'perms' => '$user->admin || $user->hasRight("sgpayroll","payroll","read")', 'user' => 0
		);

		// ═══════════════════════════════════════════════════════════════════════
		// GROUP 3: Leave Management (standalone — core Holiday)
		// ═══════════════════════════════════════════════════════════════════════
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll', 'type' => 'left',
			'titre' => 'SgpayrollLeaveManagement', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_leave',
			'url' => '/holiday/list.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 120, 'enabled' => '$conf->holiday->enabled',
			'prefix' => '<span class="fas fa-calendar fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-calendar', 'perms' => '$user->admin || $user->hasRight("holiday","read")', 'user' => 2
		);

		// ═══════════════════════════════════════════════════════════════════════
		// GROUP 4: Expense Claims (standalone — core ExpenseReport)
		// ═══════════════════════════════════════════════════════════════════════
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll', 'type' => 'left',
			'titre' => 'ExpenseClaims', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_claims',
			'url' => '/expensereport/list.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 125, 'enabled' => '$conf->expensereport->enabled',
			'prefix' => '<span class="far fa-file-alt fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-file-alt_far', 'perms' => '$user->hasRight("expensereport","read")', 'user' => 0
		);

		// ═══════════════════════════════════════════════════════════════════════
		// GROUP 5: Time Tracking (standalone — core Project Activity)
		// ═══════════════════════════════════════════════════════════════════════
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll', 'type' => 'left',
			'titre' => 'SgpayrollTimeTracking', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_timespent',
			'url' => '/projet/activity/index.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 130, 'enabled' => '$conf->projet->enabled',
			'prefix' => '<span class="far fa-clock fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-clock_far', 'perms' => '$user->admin || $user->hasRight("projet","time","read") || $user->hasRight("projet","all","creer")', 'user' => 2
		);

		// ═══════════════════════════════════════════════════════════════════════
		// GROUP 6: IRAS (parent) → Annual AIS/IR8A + Monthly WHT
		// ═══════════════════════════════════════════════════════════════════════
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll', 'type' => 'left',
			'titre' => 'IrasSubmissions', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_iras',
			'url' => '/sgpayroll/iras_ais_review.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 140, 'enabled' => 1,
			'prefix' => '<span class="fas fa-university fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-university', 'perms' => '$user->admin || $user->hasRight("sgpayroll","ais","review")', 'user' => 2
		);
		// 6a. Annual Income Tax / AIS (IR8A)
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_iras', 'type' => 'left',
			'titre' => 'IrasAisReview', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_ais',
			'url' => '/sgpayroll/iras_ais_review.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 141, 'enabled' => 1,
			'prefix' => '<span class="far fa-file-pdf fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-file-pdf_far', 'perms' => '$user->admin || $user->hasRight("sgpayroll","ais","review")', 'user' => 0
		);
		// 6b. Monthly Withholding Tax (WHT / IR37A) — non-resident employees
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_iras', 'type' => 'left',
			'titre' => 'IrasWhtReview', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_wht',
			'url' => '/sgpayroll/iras_wht_review.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 142, 'enabled' => 1,
			'prefix' => '<span class="fas fa-percent fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-percent', 'perms' => '$user->admin || $user->hasRight("sgpayroll","ais","review")', 'user' => 0
		);

		// ═══════════════════════════════════════════════════════════════════════
		// GROUP 7: CPF & Statutory Levies (CPF + SDL + SHG) → Monthly CPFEzPay export
		// ═══════════════════════════════════════════════════════════════════════
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll', 'type' => 'left',
			'titre' => 'CpfStatutory', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_cpf',
			'url' => '/sgpayroll/cpf_review.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 150, 'enabled' => 1,
			'prefix' => '<span class="fas fa-coins fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-coins', 'perms' => '$user->admin || $user->hasRight("sgpayroll","payroll","approve")', 'user' => 0
		);

		// ═══════════════════════════════════════════════════════════════════════
		// GROUP 8: MOM (parent) → OED + Levy/FWL
		// ═══════════════════════════════════════════════════════════════════════
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll', 'type' => 'left',
			'titre' => 'MomSubmissions', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_mom',
			'url' => '/sgpayroll/mom_oed.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 160, 'enabled' => 1,
			'prefix' => '<span class="fas fa-briefcase fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-briefcase', 'perms' => '$user->admin || $user->hasRight("sgpayroll","employee","read")', 'user' => 0
		);
		// 8a. Employment Directory (OED)
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_mom', 'type' => 'left',
			'titre' => 'MomOed', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_mom_oed',
			'url' => '/sgpayroll/mom_oed.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 161, 'enabled' => 1,
			'prefix' => '<span class="fas fa-list-alt fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-list-alt', 'perms' => '$user->admin || $user->hasRight("sgpayroll","employee","read")', 'user' => 0
		);
		// 8b. Foreign Worker Levy (FWL / SDL payment tracking)
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_mom', 'type' => 'left',
			'titre' => 'MomLevy', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_mom_levy',
			'url' => '/sgpayroll/mom_levy.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 162, 'enabled' => 1,
			'prefix' => '<span class="fas fa-calculator fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-calculator', 'perms' => '$user->admin || $user->hasRight("sgpayroll","employee","read")', 'user' => 0
		);
		// 8c. MOM Compliance Dashboard
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_mom', 'type' => 'left',
			'titre' => 'MomCompliance', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_mom_comp',
			'url' => '/sgpayroll/mom_compliance.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 163, 'enabled' => 1,
			'prefix' => '<span class="far fa-check-square fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-check-square_far', 'perms' => '$user->admin || $user->hasRight("sgpayroll","employee","read")', 'user' => 0
		);

		// ═══════════════════════════════════════════════════════════════════════
		// GROUP 9: Employee Documents
		// ═══════════════════════════════════════════════════════════════════════
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll', 'type' => 'left',
			'titre' => 'Documents', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_docs',
			'url' => '/sgpayroll/documents_list.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 170, 'enabled' => 1,
			'prefix' => '<span class="fas fa-folder-open fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-folder-open', 'perms' => '$user->hasRight("sgpayroll","employee","read") || $user->id > 0', 'user' => 0
		);

		// ═══════════════════════════════════════════════════════════════════════
		// GROUP 10: Employee Self-Service Portal (all authenticated users)
		// ═══════════════════════════════════════════════════════════════════════
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll', 'type' => 'left',
			'titre' => 'EmployeePortal', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_portal',
			'url' => '/sgpayroll/employee_portal.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 180, 'enabled' => 1,
			'prefix' => '<span class="fas fa-user fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-user', 'perms' => '$user->id > 0', 'user' => 2   // All users
		);
		// HR: manage employee change requests
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_portal', 'type' => 'left',
			'titre' => 'PortalRequests', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_portal_requests',
			'url' => '/sgpayroll/portal_requests.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 181, 'enabled' => 1,
			'prefix' => '<span class="fas fa-edit fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-edit', 'perms' => '$user->admin || $user->hasRight("sgpayroll","employee","write")', 'user' => 0
		);

		// ═══════════════════════════════════════════════════════════════════════
		// Admin (always last) — Left menu matches the 4 Setup tabs 1:1
		// ═══════════════════════════════════════════════════════════════════════
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll', 'type' => 'left',
			'titre' => 'GeneralSettings', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_admin',
			'url' => '/sgpayroll/admin/setup.php',
			'langs' => 'admin', 'position' => 1000, 'enabled' => 1,
			'prefix' => '<span class="fas fa-cogs fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-cogs', 'perms' => '$user->admin', 'user' => 2
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_admin', 'type' => 'left',
			'titre' => 'CpfRateTable', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_cpfrates',
			'url' => '/sgpayroll/admin/cpfrates.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 1001, 'enabled' => 1,
			'prefix' => '<span class="fas fa-percent fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-percent', 'perms' => '$user->admin', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_admin', 'type' => 'left',
			'titre' => 'CostCentres', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_costcentres',
			'url' => '/sgpayroll/admin/setup_costcentres.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 1002, 'enabled' => 1,
			'prefix' => '<span class="fas fa-sitemap fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-sitemap', 'perms' => '$user->admin', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_admin', 'type' => 'left',
			'titre' => 'SchedulePresets', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_schedule_presets',
			'url' => '/sgpayroll/admin/setup_schedule_presets.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 1003, 'enabled' => 1,
			'prefix' => '<span class="fas fa-calendar-week fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-calendar-week', 'perms' => '$user->admin', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sgpayroll,fk_leftmenu=sgpayroll_admin', 'type' => 'left',
			'titre' => 'StatutoryRates', 'mainmenu' => 'sgpayroll', 'leftmenu' => 'sgpayroll_statutory_rates',
			'url' => '/sgpayroll/admin/setup_statutory_rates.php',
			'langs' => 'sgpayroll@sgpayroll', 'position' => 1004, 'enabled' => 1,
			'prefix' => '<span class="fas fa-coins fa-fw pictofixedwidth"></span>',
			'picto' => 'fa-coins', 'perms' => '$user->admin', 'user' => 0
		);
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$this->remove($options);
		return $this->_init(array(), $options);
	}
}
