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
 *  \defgroup sghr  Module Sgpayroll
 *  \brief      Singapore Payroll module for Dolibarr.
 *  \file       htdocs/custom/sghr/core/modules/modSgpayroll.class.php
 *  \ingroup sghr
 *  \brief      Description and activation file for module Sgpayroll (Dolibarr 22.0 compatible)
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module Sgpayroll
 */
class modSghr extends DolibarrModules
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
		$this->rights_class = 'sghr';

		$this->family = "hr";
		$this->module_position = '55';
		$this->name = "SG HR & Payroll"; // Hardcoded to avoid runtime param errors
		$this->description = "SG HR & Payroll — CPF, SDL/SHG, IRAS AIS, Payslips, employee documents";
		$this->descriptionlong = "Singapore HR & payroll for Dolibarr: CPF contributions (OW/AW), statutory levies (SDL/SHG/FWL), IRAS AIS exports (IR8A/IR21), payroll runs & payslips, leave/claims integration, employee document management with expiry reminders (absorbing docsemployes), and CV/skill management (absorbing ecv).";
		$this->version = '2.1.0';
		$this->const_name = 'MAIN_MODULE_SGHR';
		$this->picto = 'salary';

		$this->module_parts = array(
			'triggers' => 1,
			'menus' => 1,
			'hooks' => array('usercard', 'docsemployesindex', 'docsemployescard', 'completetabs', 'main'),
			'css' => array('/sghr/css/sghr.css', '/sghr/docsemployes/css/docsemployes.css', '/sghr/ecv/css/ecv.css', '/sghr/recrutement/css/recrutement.css'),
			'js' => array('/sghr/docsemployes/js/docsemployes.js', '/sghr/ecv/js/ecv.js', '/sghr/recrutement/js/recrutement.js.php'),
		);

		// Employee documents tab (absorbed from docsemployes module, phase 2)
		$this->tabs = array(
			'user:+tab_docsemploye:tab_docsemploye:docsemployes@sghr:$user->rights->sghr->docs->read:/sghr/docsemployes/index.php?id=__ID__',
		);

		$this->dirs = array("/sghr/temp");
		$this->config_page_url = array("setup.php@sghr");
		$this->hidden = false;
		$this->depends = array();
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(11, 0); 
		$this->langfiles = array("sghr@sghr");

		// Cron jobs (registered as disabled by default; enable in Scheduled Jobs)
		$this->cronjobs = array(
			0 => array(
				'entity' => 0,
				'label' => 'SGPayroll reminders',
				'jobtype' => 'method',
				'class' => 'sghr/cron/reminder_cron.php',
				'objectname' => 'SghrReminderCron',
				'method' => 'run',
				'parameters' => '',
				'comment' => 'Document expiry and AIS review reminder emails',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 0,
				'priority' => 5,
				'test' => 'isModEnabled(\'sghr\')',
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
		$r++;

		// Employee documents permissions (absorbed from docsemployes module;
		// ids kept so existing user-right bindings survive the module rename)
		$this->rights[$r][0] = 677720088;
		$this->rights[$r][1] = 'EmployeeDocsRead';
		$this->rights[$r][2] = 'r'; $this->rights[$r][3] = 1; $this->rights[$r][4] = 'docs'; $this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = 677720089;
		$this->rights[$r][1] = 'EmployeeDocsWrite';
		$this->rights[$r][2] = 'w'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'docs'; $this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = 677720090;
		$this->rights[$r][1] = 'EmployeeDocsDelete';
		$this->rights[$r][2] = 'd'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'docs'; $this->rights[$r][5] = 'delete';
		$r++;

		// CV / skills management permissions (absorbed from ecv module;
		// ids kept so existing user-right bindings survive the module rename)
		$this->rights[$r][0] = 190961110;
		$this->rights[$r][1] = 'CvRead';
		$this->rights[$r][2] = 'r'; $this->rights[$r][3] = 1; $this->rights[$r][4] = 'cv'; $this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = 190961111;
		$this->rights[$r][1] = 'CvWrite';
		$this->rights[$r][2] = 'w'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'cv'; $this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = 190961112;
		$this->rights[$r][1] = 'CvDelete';
		$this->rights[$r][2] = 'd'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'cv'; $this->rights[$r][5] = 'delete';
		$r++;

		// Recruitment permissions (absorbed from recrutement module;
		// ids kept so existing user-right bindings survive the module rename)
		$this->rights[$r][0] = 999119990;
		$this->rights[$r][1] = 'RecruitmentRead';
		$this->rights[$r][2] = 'r'; $this->rights[$r][3] = 1; $this->rights[$r][4] = 'rec'; $this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = 999119991;
		$this->rights[$r][1] = 'RecruitmentWrite';
		$this->rights[$r][2] = 'w'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'rec'; $this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = 999119992;
		$this->rights[$r][1] = 'RecruitmentDelete';
		$this->rights[$r][2] = 'd'; $this->rights[$r][3] = 0; $this->rights[$r][4] = 'rec'; $this->rights[$r][5] = 'delete';

		// ── Left menu (restructured 2026-10-01: 4 groups, unified positions/naming) ──
		$this->menu = array();
		$r = 0;

		// Top bar entry
		$this->menu[$r++] = array(
			'fk_menu' => '', 'type' => 'top', 'titre' => 'ModuleSGPayrollName', 'mainmenu' => 'sghr',
			'url' => '/sghr/employee_list.php?mainmenu=sghr',
			'langs' => 'sghr@sghr', 'position' => 1000, 'enabled' => 1,
			'perms' => '$user->admin || $user->rights->sghr->payroll->read || $user->rights->sghr->employee->read || $user->rights->sghr->claims->submit',
			'prefix' => '<span class="fas fa-briefcase fa-fw pictofixedwidth"></span>', 'user' => 0
		);

		// ── Group: EMPLOYEES (100) ─────────────────────────────────────────────
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr', 'type' => 'left', 'titre' => 'SghrMenuEmployees', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_grp_emp',
			'url' => '/sghr/employee_list.php', 'langs' => 'sghr@sghr', 'position' => 100, 'enabled' => 1,
			'prefix' => '<span class="fas fa-users fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->employee->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_emp', 'type' => 'left',
			'titre' => 'Employees', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_emp_list',
			'url' => '/sghr/employee_list.php', 'langs' => 'sghr@sghr', 'position' => 101, 'enabled' => 1,
			'prefix' => '<span class="fas fa-user fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->employee->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_emp', 'type' => 'left',
			'titre' => 'EmployeeDocuments', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_docs',
			'url' => '/sghr/docsemployes/index.php', 'langs' => 'docsemployes@sghr', 'position' => 102, 'enabled' => 1,
			'prefix' => '<span class="fas fa-passport fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->docs->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_emp', 'type' => 'left',
			'titre' => 'SghrCandidateConvertTitle', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_convert',
			'url' => '/sghr/candidate_convert.php', 'langs' => 'sghr@sghr', 'position' => 103, 'enabled' => 1,
			'prefix' => '<span class="fas fa-user-plus fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->employee->write', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_emp', 'type' => 'left',
			'titre' => 'EmployeePortal', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_portal',
			'url' => '/sghr/employee_portal.php', 'langs' => 'sghr@sghr', 'position' => 104, 'enabled' => 1,
			'prefix' => '<span class="fas fa-id-badge fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->employee->self_write || $user->admin', 'user' => 2
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_portal', 'type' => 'left',
			'titre' => 'PortalRequests', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_portal_requests',
			'url' => '/sghr/portal_requests.php', 'langs' => 'sghr@sghr', 'position' => 105, 'enabled' => 1,
			'prefix' => '<span class="fas fa-inbox fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->claims->submit || $user->admin', 'user' => 2
		);

		// ── Group: RECRUITMENT (200) ───────────────────────────────────────────
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr', 'type' => 'left', 'titre' => 'SghrMenuRecruitment', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_grp_rec',
			'url' => '/sghr/recrutement/index.php', 'langs' => 'recrutement@sghr', 'position' => 200, 'enabled' => 1,
			'prefix' => '<span class="fas fa-person-chalkboard fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->rec->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_rec', 'type' => 'left',
			'titre' => 'postes', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_rec_postes',
			'url' => '/sghr/recrutement/index.php', 'langs' => 'recrutement@sghr', 'position' => 201, 'enabled' => 1,
			'prefix' => '<span class="fas fa-briefcase fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->rec->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_rec', 'type' => 'left',
			'titre' => 'candidatures', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_rec_cands',
			'url' => '/sghr/recrutement/candidatures/kanban.php?page=0', 'langs' => 'recrutement@sghr', 'position' => 202, 'enabled' => 1,
			'prefix' => '<span class="fas fa-columns fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->rec->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_rec', 'type' => 'left',
			'titre' => 'liste_des_candidatures', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_rec_cands_list',
			'url' => '/sghr/recrutement/candidatures/index.php?page=0', 'langs' => 'recrutement@sghr', 'position' => 203, 'enabled' => 1,
			'prefix' => '<span class="fas fa-list fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->rec->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_rec', 'type' => 'left',
			'titre' => 'CVManagement', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_cv',
			'url' => '/sghr/ecv/index.php', 'langs' => 'ecv@sghr', 'position' => 204, 'enabled' => 1,
			'prefix' => '<span class="fas fa-file-alt fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->cv->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_rec', 'type' => 'left',
			'titre' => 'recru_recherche_avancee', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_rec_search',
			'url' => '/sghr/recrutement/search.php', 'langs' => 'recrutement@sghr', 'position' => 205, 'enabled' => 1,
			'prefix' => '<span class="fas fa-search fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->rec->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_rec', 'type' => 'left',
			'titre' => 'rapports', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_rec_reports',
			'url' => '/sghr/recrutement/rapports.php', 'langs' => 'recrutement@sghr', 'position' => 206, 'enabled' => 1,
			'prefix' => '<span class="fas fa-chart-bar fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->rec->read', 'user' => 0
		);

		// ── Group: PAYROLL (300) ───────────────────────────────────────────────
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr', 'type' => 'left', 'titre' => 'SghrMenuPayroll', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_grp_pay',
			'url' => '/sghr/payslip_list.php', 'langs' => 'sghr@sghr', 'position' => 300, 'enabled' => 1,
			'prefix' => '<span class="fas fa-money-check-alt fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->payroll->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_pay', 'type' => 'left',
			'titre' => 'SgpayrollPayrollRun', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_run',
			'url' => '/sghr/payslip_list.php', 'langs' => 'sghr@sghr', 'position' => 301, 'enabled' => 1,
			'prefix' => '<span class="fas fa-play fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->payroll->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_pay', 'type' => 'left',
			'titre' => 'PayrollDashboard', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_dashboard',
			'url' => '/sghr/payroll_dashboard.php', 'langs' => 'sghr@sghr', 'position' => 302, 'enabled' => 1,
			'prefix' => '<span class="fas fa-tachometer-alt fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->payroll->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_pay', 'type' => 'left',
			'titre' => 'CpfStatutory', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_cpf',
			'url' => '/sghr/cpf_review.php', 'langs' => 'sghr@sghr', 'position' => 303, 'enabled' => 1,
			'prefix' => '<span class="fas fa-piggy-bank fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->payroll->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_pay', 'type' => 'left',
			'titre' => 'IrasSubmissions', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_iras',
			'url' => '/sghr/iras_ais_review.php', 'langs' => 'sghr@sghr', 'position' => 304, 'enabled' => 1,
			'prefix' => '<span class="fas fa-file-invoice fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->ais->review || $user->rights->sghr->export->iras', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sgpayroll_iras', 'type' => 'left',
			'titre' => 'IrasWhtReview', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_wht',
			'url' => '/sghr/iras_wht_review.php', 'langs' => 'sghr@sghr', 'position' => 305, 'enabled' => 1,
			'prefix' => '<span class="fas fa-handshake fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->export->iras', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_pay', 'type' => 'left',
			'titre' => 'MomSubmissions', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_mom',
			'url' => '/sghr/mom_oed.php', 'langs' => 'sghr@sghr', 'position' => 306, 'enabled' => 1,
			'prefix' => '<span class="fas fa-building fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->payroll->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sgpayroll_mom', 'type' => 'left',
			'titre' => 'MomLevy', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_mom_levy',
			'url' => '/sghr/mom_levy.php', 'langs' => 'sghr@sghr', 'position' => 307, 'enabled' => 1,
			'prefix' => '<span class="fas fa-layer-group fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->payroll->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sgpayroll_mom', 'type' => 'left',
			'titre' => 'MomCompliance', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_mom_comp',
			'url' => '/sghr/mom_compliance.php', 'langs' => 'sghr@sghr', 'position' => 308, 'enabled' => 1,
			'prefix' => '<span class="fas fa-clipboard-check fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->payroll->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_pay', 'type' => 'left',
			'titre' => 'SgpayrollSalaryPayments', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_payments',
			'url' => '/salaries/list.php', 'langs' => 'sghr@sghr', 'position' => 309,
			'enabled' => 'isModEnabled(\'salaries\')', 'prefix' => '<span class="fas fa-credit-card fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->salaries->lire', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_pay', 'type' => 'left',
			'titre' => 'SgpayrollLeaveManagement', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_leave',
			'url' => '/holiday/list.php', 'langs' => 'sghr@sghr', 'position' => 310,
			'enabled' => 'isModEnabled(\'holiday\')', 'prefix' => '<span class="fas fa-umbrella-beach fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->holiday->read', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_pay', 'type' => 'left',
			'titre' => 'ExpenseClaims', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_claims',
			'url' => '/expensereport/list.php', 'langs' => 'sghr@sghr', 'position' => 311,
			'enabled' => 'isModEnabled(\'expensereport\')', 'prefix' => '<span class="fas fa-receipt fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->expensereport->lire || $user->rights->sghr->claims->submit', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_pay', 'type' => 'left',
			'titre' => 'SgpayrollTimeTracking', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_timespent',
			'url' => '/projet/activity/index.php', 'langs' => 'sghr@sghr', 'position' => 312,
			'enabled' => 'isModEnabled(\'projet\')', 'prefix' => '<span class="fas fa-clock fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->projet->lire', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_pay', 'type' => 'left',
			'titre' => 'Documents', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_docs',
			'url' => '/sghr/documents_list.php', 'langs' => 'sghr@sghr', 'position' => 313, 'enabled' => 1,
			'prefix' => '<span class="fas fa-file-pdf fa-fw pictofixedwidth"></span>',
			'perms' => '$user->rights->sghr->payroll->read', 'user' => 0
		);

		// ── Group: SETUP (900) ─────────────────────────────────────────────────
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr', 'type' => 'left', 'titre' => 'SghrMenuSetup', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_grp_cfg',
			'url' => '/sghr/admin/setup.php', 'langs' => 'sghr@sghr', 'position' => 900, 'enabled' => 1,
			'prefix' => '<span class="fas fa-cog fa-fw pictofixedwidth"></span>', 'perms' => '$user->admin', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_cfg', 'type' => 'left',
			'titre' => 'CpfRateTable', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_cpfrates',
			'url' => '/sghr/admin/cpfrates.php', 'langs' => 'sghr@sghr', 'position' => 901, 'enabled' => 1,
			'prefix' => '<span class="fas fa-table fa-fw pictofixedwidth"></span>', 'perms' => '$user->admin', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_cfg', 'type' => 'left',
			'titre' => 'CostCentres', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_costcentres',
			'url' => '/sghr/admin/setup_costcentres.php', 'langs' => 'sghr@sghr', 'position' => 902, 'enabled' => 1,
			'prefix' => '<span class="fas fa-sitemap fa-fw pictofixedwidth"></span>', 'perms' => '$user->admin', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_cfg', 'type' => 'left',
			'titre' => 'SchedulePresets', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_schedule_presets',
			'url' => '/sghr/admin/setup_schedule_presets.php', 'langs' => 'sghr@sghr', 'position' => 903, 'enabled' => 1,
			'prefix' => '<span class="fas fa-calendar-alt fa-fw pictofixedwidth"></span>', 'perms' => '$user->admin', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_cfg', 'type' => 'left',
			'titre' => 'StatutoryRates', 'mainmenu' => 'sghr', 'leftmenu' => 'sgpayroll_statutory_rates',
			'url' => '/sghr/admin/setup_statutory_rates.php', 'langs' => 'sghr@sghr', 'position' => 904, 'enabled' => 1,
			'prefix' => '<span class="fas fa-coins fa-fw pictofixedwidth"></span>', 'perms' => '$user->admin', 'user' => 0
		);
		// Recruitment dictionaries
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_cfg', 'type' => 'left',
			'titre' => 'departements', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_cfg_departements',
			'url' => '/sghr/recrutement/departements/index.php', 'langs' => 'recrutement@sghr', 'position' => 910, 'enabled' => 1,
			'prefix' => '<span class="fas fa-building fa-fw pictofixedwidth"></span>', 'perms' => '$user->admin', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_cfg', 'type' => 'left',
			'titre' => 'etiquettes_candidature', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_cfg_etiquettes',
			'url' => '/sghr/recrutement/etiquettes/index.php', 'langs' => 'recrutement@sghr', 'position' => 911, 'enabled' => 1,
			'prefix' => '<span class="fas fa-tags fa-fw pictofixedwidth"></span>', 'perms' => '$user->admin', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_cfg', 'type' => 'left',
			'titre' => 'origines', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_cfg_origines',
			'url' => '/sghr/recrutement/origines/index.php', 'langs' => 'recrutement@sghr', 'position' => 912, 'enabled' => 1,
			'prefix' => '<span class="fas fa-globe fa-fw pictofixedwidth"></span>', 'perms' => '$user->admin', 'user' => 0
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=sghr,fk_leftmenu=sghr_grp_cfg', 'type' => 'left',
			'titre' => 'niveauetud', 'mainmenu' => 'sghr', 'leftmenu' => 'sghr_cfg_degrees',
			'url' => '/sghr/recrutement/candidatures/degrees/index.php', 'langs' => 'recrutement@sghr', 'position' => 913, 'enabled' => 1,
			'prefix' => '<span class="fas fa-graduation-cap fa-fw pictofixedwidth"></span>', 'perms' => '$user->admin', 'user' => 0
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

		// Rename migration sgpayroll -> sghr (run before _init re-creates
		// rights/menus/cron so existing user-right bindings survive: ids are
		// preserved by the UPDATE, only the module label changes).
		// Tables llx_sgpayroll_* are NOT renamed (zero data migration by design).
		$migration = array(
			"UPDATE ".MAIN_DB_PREFIX."rights_def SET module = 'sghr' WHERE module = 'sgpayroll'",
			"UPDATE ".MAIN_DB_PREFIX."cronjobs SET module = 'sghr' WHERE module = 'sgpayroll'",
			"UPDATE ".MAIN_DB_PREFIX."const SET name = REPLACE(name, 'SGPAYROLL_', 'SGHR_') WHERE name LIKE 'SGPAYROLL\_%'",
			"UPDATE ".MAIN_DB_PREFIX."document_model SET nom = REPLACE(nom, '_sgpayroll', '_sghr') WHERE nom IN ('pdf_payslip_sgpayroll', 'pdf_ir8a_sgpayroll', 'pdf_summary_sgpayroll')",
			// phase 2: docsemployes absorption - same id-preserving label migration
			"UPDATE ".MAIN_DB_PREFIX."rights_def SET module = 'sghr' WHERE module = 'docsemployes'",
			"UPDATE ".MAIN_DB_PREFIX."cronjobs SET module = 'sghr' WHERE module = 'docsemployes'",
			"UPDATE ".MAIN_DB_PREFIX."cronjobs SET url = REPLACE(url, '/docsemployes/', '/sghr/docsemployes/') WHERE url LIKE '%/docsemployes/%'",
			"DELETE FROM ".MAIN_DB_PREFIX."menu WHERE module = 'docsemployes'",
			// phase 3: ecv absorption
			"UPDATE ".MAIN_DB_PREFIX."rights_def SET module = 'sghr' WHERE module = 'ecv'",
			"UPDATE ".MAIN_DB_PREFIX."cronjobs SET module = 'sghr' WHERE module = 'ecv'",
			"UPDATE ".MAIN_DB_PREFIX."cronjobs SET url = REPLACE(url, '/ecv/', '/sghr/ecv/') WHERE url LIKE '%/ecv/%'",
			"DELETE FROM ".MAIN_DB_PREFIX."menu WHERE module = 'ecv'",
			// phase 6: recrutement absorption
			"UPDATE ".MAIN_DB_PREFIX."rights_def SET module = 'sghr' WHERE module = 'recrutement'",
			"UPDATE ".MAIN_DB_PREFIX."cronjobs SET module = 'sghr' WHERE module = 'recrutement'",
			"UPDATE ".MAIN_DB_PREFIX."cronjobs SET url = REPLACE(url, '/recrutement/', '/sghr/recrutement/') WHERE url LIKE '%/recrutement/%'",
			"DELETE FROM ".MAIN_DB_PREFIX."menu WHERE module = 'recrutement'",
		);
		foreach ($migration as $sqlmig) {
			$this->db->query($sqlmig);
		}

		// Standard table installation on module activation. All sql/ scripts are
		// idempotent (CREATE TABLE IF NOT EXISTS / INSERT IGNORE); ALTER statements
		// that fail on an already-upgraded schema are silently ignored by run_sql.
		$this->_load_tables('/sghr/sql/');

		return $this->_init(array(), $options);
	}
}
