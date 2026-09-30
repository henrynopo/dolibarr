<?php
/* Copyright (C) 2026 Henry Guo <hbg@hbg.sg>
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
 * \file       htdocs/custom/sfrs_reports/core/modules/modSfrsReports.class.php
 * \ingroup    sfrs_reports
 * \brief      Module descriptor for Singapore Financial Reporting Standards (SFRS) reports
 *
 * Provides Balance Sheet, Profit & Loss, Cash Flow Statement generation
 * for Singapore companies under FRS / SFRS(I) / SFRS for Small Entities frameworks.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';


/**
 * Class to describe and enable the SFRS Reports module
 */
class modSfrsReports extends DolibarrModules
{
	/**
	 * Single source of truth for the hook contexts this module listens to.
	 * Both $module_parts['hooks'] and the MAIN_MODULE_SFRSREPORTS_HOOKS const
	 * are populated from this array so the two can never drift apart.
	 *
	 * To add a new hook:
	 *   1. Append its context name here.
	 *   2. Implement the matching public method on ActionsSfrsReports.
	 *   3. Ask the user to re-enable the module (or run Setup → "Refresh module hooks").
	 */
	const HOOKS = array(
		'accountancyindex',     // Inject SFRS Reports link on Accounting home page
		'bookkeepingCreateBefore', // Auto-fill multicurrency_* on new bookkeeping lines
	);

	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// Module unique ID — pick a high number to avoid conflicts.
		// Range 500000+ is reserved for custom/3rd-party modules (see Dolibarr wiki).
		$this->numero = 501200;

		$this->family = "financial";
		$this->module_position = '75';

		// Module label (no space allowed)
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Singapore Financial Reporting Standards (SFRS) financial reports: Balance Sheet, Profit & Loss, Cash Flow Statement. Supports FRS / SFRS(I) / SFRS for Small Entities frameworks.";

		// Possible values: 'development', 'experimental', 'dolibarr' or version
		$this->version = '1.0.0';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'accountancy';

		// Data directories to create when module is enabled
		$this->dirs = array('/sfrs_reports/temp');

		// Config pages
		$this->config_page_url = array('setup.php@sfrs_reports');

		// Dependencies — require Double Entry Accounting module (modAccounting)
		$this->hidden = false;
		$this->depends = array("modAccounting"); // SFRS needs bookkeeping + chart of accounts
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(7, 4);
		// SFRS categories depend on llx_c_accounting_category.fk_report, which was added in
		// Dolibarr 20.0 (see install/mysql/migration/20.0.0-21.0.0.sql). The bookkeeping
		// hook 'bookkeepingCreateBefore' also requires Dolibarr 19+. We therefore require >= 20.
		$this->need_dolibarr_version = array(20, 0);
		$this->langfiles = array("sfrs_reports@sfrs_reports", "accountancy");

		// Hooks the module wants to receive — driven by self::HOOKS so the
		// $module_parts declaration and the runtime const stay in lockstep.
		$this->module_parts = array(
			'hooks' => self::HOOKS,
		);

		// Constants — auto-created when module is enabled
		$this->const = array(
			// SFRS framework choice: 'FRS' | 'SFRS(I)' | 'SFRS_FOR_SE'
			array(
				"SFRS_FRAMEWORK",
				"chaine",
				"FRS",
				"Active SFRS framework: FRS (default for non-listed), SFRS(I) (IFRS-aligned), or SFRS_FOR_SE (small entities)",
				0, 'current', 0
			),
			// Cash Flow Statement method: 'direct' | 'indirect'
			array(
				"SFRS_CASHFLOW_METHOD",
				"chaine",
				"direct",
				"Cash Flow Statement method: direct or indirect (FRS 7)",
				0, 'current', 0
			),
			// Presentation currency
			array(
				"SFRS_PRESENTATION_CURRENCY",
				"chaine",
				"SGD",
				"Presentation currency for SFRS financial statements (ISO 4217 code)",
				0, 'current', 0
			),
			// Singapore GST rate (effective 2024-01-01)
			array(
				"SFRS_GST_STANDARD_RATE",
				"chaine",
				"9",
				"Singapore GST standard rate (percent). Effective 2024-01-01 onwards.",
				0, 'current', 0
			),
			// Company registration number (UEN) for report header
			array(
				"SFRS_COMPANY_UEN",
				"chaine",
				"",
				"Singapore Unique Entity Number (UEN) for SFRS report header",
				0, 'current', 0
			),
			// Balance Sheet exchange rate method: 'closing' | 'average' | 'historical' | 'fixed'
			array(
				"SFRS_MC_BS_RATE_METHOD",
				"chaine",
				"closing",
				"BS exchange rate method: closing (期末汇率), average, historical, or fixed",
				0, 'current', 0
			),
			// Profit & Loss exchange rate method: 'average' | 'closing' | 'historical' | 'fixed'
			array(
				"SFRS_MC_PL_RATE_METHOD",
				"chaine",
				"average",
				"P&L exchange rate method: average (平均汇率), closing, historical, or fixed",
				0, 'current', 0
			),
			// Per-currency pinned rates for the 'fixed' method (JSON map, e.g. {"USD":"1.35"})
			array(
				"SFRS_MC_FIXED_RATES",
				"chaine",
				"",
				"Per-currency fixed exchange rates, JSON map (when BS/PL rate method is 'fixed')",
				0, 'current', 0
			),
			// FX gain account for translation differences
			array(
				"SFRS_MC_FX_GAIN_ACCOUNT",
				"chaine",
				"7010",
				"SFRS account code for FX translation gains (e.g. 7010 Exchange gain)",
				0, 'current', 0
			),
			// FX loss account for translation differences
			array(
				"SFRS_MC_FX_LOSS_ACCOUNT",
				"chaine",
				"7110",
				"SFRS account code for FX translation losses (e.g. 7110 Exchange loss)",
				0, 'current', 0
			),
			// COA and categories are loaded per-entity in init() (steps 2/3) via a
		// direct COUNT() on llx_accounting_account / llx_c_accounting_category
		// — no global "loaded" flag needed. Removing SFRS_SG_COA_LOADED and
		// SFRS_CATEGORIES_LOADED lets init() run on each multicompany entity
		// independently.
	);

		// Boxes (dashboard widgets)
		$this->boxes = array();

		// Permissions
		// rights_class MUST equal the lowercase module name ('sfrsreports'):
		// Dolibarr 22's User::hasRight() first checks isModEnabled($module), and
		// a diverging rights_class ('sfrs') makes every hasRight() call return 0 —
		// even for admins with the permission granted.
		$this->rights = array();
		$this->rights_class = 'sfrsreports';
		$r = 0;

		$r++;
		$this->rights[$r][0] = 501201;
		$this->rights[$r][1] = 'Read SFRS financial reports';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'reports';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = 501202;
		$this->rights[$r][1] = 'Export SFRS reports as PDF';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'reports';
		$this->rights[$r][5] = 'export';

		$r++;
		$this->rights[$r][0] = 501203;
		$this->rights[$r][1] = 'Configure SFRS module (framework, cash flow method, GST)';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'reports';
		$this->rights[$r][5] = 'setup';

		// Main menu entries (added under Accounting menu)
		$r = 0;

		// Top-level menu (visible in main menu bar)
		$this->menu = array();

		$r++;
		$this->menu[$r] = array(
			'fk_menu'    => 0,  // Top-level menu
			'type'       => 'left',
			'titre'      => 'SFRSReports',
			'mainmenu'   => 'accountancy',
			'leftmenu'   => 'sfrs_reports',
			'url'        => '/custom/sfrs_reports/pages/index.php',
			'langs'      => 'sfrs_reports@sfrs_reports',
			'position'   => 100,
			'enabled'    => '$conf->sfrs_reports->enabled',
			'perms'      => '$user->hasRight("sfrsreports", "reports", "read")',
			'target'     => '',
			'user'       => 0,
		);

		// Sub-menu: Balance Sheet
		$r++;
		$this->menu[$r] = array(
			'fk_menu'    => 'fk_mainmenu=accountancy,fk_leftmenu=sfrs_reports',
			'type'       => 'left',
			'titre'      => 'SFRSBalanceSheet',
			'mainmenu'   => 'accountancy',
			'leftmenu'   => 'sfrs_balance_sheet',
			'url'        => '/custom/sfrs_reports/pages/balance_sheet.php',
			'langs'      => 'sfrs_reports@sfrs_reports',
			'position'   => 101,
			'enabled'    => '$conf->sfrs_reports->enabled',
			'perms'      => '$user->hasRight("sfrsreports", "reports", "read")',
			'target'     => '',
			'user'       => 0,
		);

		// Sub-menu: Profit & Loss
		$r++;
		$this->menu[$r] = array(
			'fk_menu'    => 'fk_mainmenu=accountancy,fk_leftmenu=sfrs_reports',
			'type'       => 'left',
			'titre'      => 'SFRSProfitLoss',
			'mainmenu'   => 'accountancy',
			'leftmenu'   => 'sfrs_profit_loss',
			'url'        => '/custom/sfrs_reports/pages/profit_loss.php',
			'langs'      => 'sfrs_reports@sfrs_reports',
			'position'   => 102,
			'enabled'    => '$conf->sfrs_reports->enabled',
			'perms'      => '$user->hasRight("sfrsreports", "reports", "read")',
			'target'     => '',
			'user'       => 0,
		);

		// Sub-menu: Cash Flow Statement
		$r++;
		$this->menu[$r] = array(
			'fk_menu'    => 'fk_mainmenu=accountancy,fk_leftmenu=sfrs_reports',
			'type'       => 'left',
			'titre'      => 'SFRSCashFlow',
			'mainmenu'   => 'accountancy',
			'leftmenu'   => 'sfrs_cash_flow',
			'url'        => '/custom/sfrs_reports/pages/cash_flow.php',
			'langs'      => 'sfrs_reports@sfrs_reports',
			'position'   => 103,
			'enabled'    => '$conf->sfrs_reports->enabled',
			'perms'      => '$user->hasRight("sfrsreports", "reports", "read")',
			'target'     => '',
			'user'       => 0,
		);

		// Sub-menu: AR Aging (Aged Receivables)
		$r++;
		$this->menu[$r] = array(
			'fk_menu'    => 'fk_mainmenu=accountancy,fk_leftmenu=sfrs_reports',
			'type'       => 'left',
			'titre'      => 'SFRSARAging',
			'mainmenu'   => 'accountancy',
			'leftmenu'   => 'sfrs_ar_aging',
			'url'        => '/custom/sfrs_reports/pages/aging.php?type=ar',
			'langs'      => 'sfrs_reports@sfrs_reports',
			'position'   => 104,
			'enabled'    => '$conf->sfrs_reports->enabled',
			'perms'      => '$user->hasRight("sfrsreports", "reports", "read")',
			'target'     => '',
			'user'       => 0,
		);

		// Sub-menu: AP Aging (Aged Payables)
		$r++;
		$this->menu[$r] = array(
			'fk_menu'    => 'fk_mainmenu=accountancy,fk_leftmenu=sfrs_reports',
			'type'       => 'left',
			'titre'      => 'SFRSAPAging',
			'mainmenu'   => 'accountancy',
			'leftmenu'   => 'sfrs_ap_aging',
			'url'        => '/custom/sfrs_reports/pages/aging.php?type=ap',
			'langs'      => 'sfrs_reports@sfrs_reports',
			'position'   => 105,
			'enabled'    => '$conf->sfrs_reports->enabled',
			'perms'      => '$user->hasRight("sfrsreports", "reports", "read")',
			'target'     => '',
			'user'       => 0,
		);

		// Sub-menu: Setup
		$r++;
		$this->menu[$r] = array(
			'fk_menu'    => 'fk_mainmenu=accountancy,fk_leftmenu=sfrs_reports',
			'type'       => 'left',
			'titre'      => 'SFRSSetup',
			'mainmenu'   => 'accountancy',
			'leftmenu'   => 'sfrs_setup',
			'url'        => '/custom/sfrs_reports/admin/setup.php',
			'langs'      => 'sfrs_reports@sfrs_reports',
			'position'   => 110,
			'enabled'    => '$conf->sfrs_reports->enabled',
			'perms'      => '$user->hasRight("sfrsreports", "reports", "setup")',
			'target'     => '',
			'user'       => 0,
		);
	}


	/**
	 * Function called when module is enabled.
	 * - Creates the SFRS-BASE accounting_system record (idempotent)
	 * - Loads the full SFRS chart of accounts (idempotent)
	 * - Loads the SFRS report categories (idempotent)
	 * - Binds Dolibarr default accounts (ACCOUNTING_ACCOUNT_CUSTOMER etc.) to SFRS codes
	 *   so that sales/purchase/expense journals write postings to SFRS accounts automatically
	 * - Registers SG GST 9% tax rates so invoices carry the right tax code
	 *
	 * All steps are idempotent: re-enabling the module will not duplicate data.
	 *
	 * @param string $options Options when enabling module ('', 'norequirehooks', etc.)
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs, $user;

		$sql = array();

		$result = $this->_load_tables('/sfrs_reports/sql/');
		if ($result < 0) {
			return -1;
		}

		// 0. Migrate legacy rows written by earlier versions of this module.
		//    a) Permissions were registered with rights_class 'sfrs', which
		//       Dolibarr 22's User::hasRight() always rejects (isModEnabled('sfrs')
		//       is false — the enabled module name is 'sfrsreports').
		//       insert_permissions() skips existing (id, entity) rows WITHOUT
		//       updating the module column, so we fix the rows here: same ids,
		//       correct module. User grants in llx_user_rights reference the ids
		//       and keep working untouched.
		//    b) Purge the id=0 junk row produced by the once-missing KEY_ID.
		//    c) Menu rows created by the old descriptor carry module='sfrs', so
		//       delete_menus() (WHERE module='sfrsreports') never removes them on
		//       disable — and insert_menus() then aborts the WHOLE activation with
		//       "Menu entry already exists" (Menubase::create returns 0 = skip,
		//       but insert_menus counts it as an error). We delete every menu row
		//       pointing at this module's pages so _init() re-inserts the full,
		//       current set from the descriptor — this also makes menu changes
		//       (e.g. new entries) take effect on every enable, no manual DB
		//       cleanup needed.
		$sql_mig = "UPDATE ".MAIN_DB_PREFIX."rights_def";
		$sql_mig .= " SET module = 'sfrsreports', module_origin = 'sfrsreports'";
		$sql_mig .= " WHERE entity IN (0, ".((int) $conf->entity).") AND module = 'sfrs'";
		$this->db->query($sql_mig);
		$sql_purge = "DELETE FROM ".MAIN_DB_PREFIX."rights_def";
		$sql_purge .= " WHERE entity IN (0, ".((int) $conf->entity).") AND id = 0 AND module IN ('sfrs', 'sfrsreports')";
		$this->db->query($sql_purge);
		$sql_menudel = "DELETE FROM ".MAIN_DB_PREFIX."menu";
		$sql_menudel .= " WHERE entity = ".((int) $conf->entity);
		$sql_menudel .= " AND menu_handler = 'all'";
		$sql_menudel .= " AND url LIKE '".$this->db->escape('/custom/sfrs_reports/')."%'";
		$this->db->query($sql_menudel);

		// 1. Insert Singapore accounting_system record if not present
		$res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."accounting_system WHERE pcg_version = 'SFRS-BASE'");
		if ($res && $this->db->num_rows($res) == 0) {
			$sql_system = "INSERT INTO ".MAIN_DB_PREFIX."accounting_system ";
			$sql_system .= "(fk_country, pcg_version, label, active) VALUES ";
			$sql_system .= "(29, 'SFRS-BASE', 'Singapore SFRS Chart of Accounts (FRS / SFRS(I) / SFRS for SE)', 1)";
			$res_insert = $this->db->query($sql_system);
			if (!$res_insert) {
				dol_print_error($this->db);
				return -1;
			}
		}

		// 2. Load Singapore chart of accounts if not yet loaded for THIS entity.
		//    Per-entity check (with entity filter) instead of a global flag — this
		//    is what makes the module safe to enable on multiple multicompany
		//    entities: each one gets its own copy of the SFRS-BASE chart.
		$sql_check_coa = "SELECT COUNT(*) AS n FROM ".MAIN_DB_PREFIX."accounting_account";
		$sql_check_coa .= " WHERE fk_pcg_version = 'SFRS-BASE' AND entity = ".(int) $conf->entity;
		$res_coa = $this->db->query($sql_check_coa);
		$obj_coa = $res_coa ? $this->db->fetch_object($res_coa) : null;
		if (!$obj_coa || (int) $obj_coa->n == 0) {
			$coa_path = dol_buildpath('/sfrs_reports/sql/data/llx_sfrs_account_sg.sql');
			if ($coa_path && file_exists($coa_path)) {
				$ok = $this->runSqlFile($coa_path);
				if ($ok < 0) {
					$this->error = 'Failed importing Singapore chart of accounts: '.$this->db->lasterror();
					dol_print_error($this->db, $this->error);
					return -1;
				}
			}
		}

		// 3. Load SFRS report categories if not yet loaded for THIS entity.
		$sql_check_cat = "SELECT COUNT(*) AS n FROM ".MAIN_DB_PREFIX."c_accounting_category";
		$sql_check_cat .= " WHERE fk_country = 29 AND entity = ".(int) $conf->entity;
		$res_cat = $this->db->query($sql_check_cat);
		$obj_cat = $res_cat ? $this->db->fetch_object($res_cat) : null;
		if (!$obj_cat || (int) $obj_cat->n == 0) {
			$cat_path = dol_buildpath('/sfrs_reports/sql/data/llx_sfrs_c_accounting_category.sql');
			if ($cat_path && file_exists($cat_path)) {
				$ok = $this->runSqlFile($cat_path);
				if ($ok < 0) {
					$this->error = 'Failed importing SFRS report categories: '.$this->db->lasterror();
					dol_print_error($this->db, $this->error);
					return -1;
				}
			}
		}

		// 4. Bind Dolibarr default accounts to SFRS codes
		//    These constants are read by sellsjournal.php / purchasesjournal.php /
		//    bankjournal.php / expensereportsjournal.php to fill in the bookkeeping
		    //    account number when the customer/vendor/product has no specific one.
		$this->bindDolibarrDefaultAccounts();

		// 5. Ensure the seven standard accounting journals exist for this entity
		//    (VT/AC/BQ/OD/ER/INV/AN). Covers multicompany entities created without
		//    the accounting reference data, and binds ACCOUNTING_CLOSURE_DEFAULT_JOURNAL.
		$journals_created = $this->ensureAccountingJournals();
		if ($journals_created < 0) {
			$this->error = 'Failed ensuring accounting journals: '.$this->error;
			dol_print_error($this->db, $this->error);
			return -1;
		}

		// 6. Register SG GST 9% tax rates so invoices carry the right tax code
		$this->registerSggstRates();

		// 7. Configure Dolibarr's built-in period closure to write P&L net
		//    amounts into the SFRS Retained Earnings account (3110). Without
		//    these constants, Dolibarr's closeFiscalPeriod() in
		//    BookKeeping::closeFiscalPeriod() will fail to find a closure
		//    account and refuse to close the period.
		$this->configurePeriodClosure();

		// 8. Sync the MAIN_MODULE_SFRSREPORTS_HOOKS const so newly added
		//    hook contexts (e.g. 'bookkeepingCreateBefore') are picked up by
		//    HookManager on the next page load. Without this, a user who
		//    enabled an earlier version of this module would need to disable
		//    and re-enable to register new hooks. Sourced from self::HOOKS so
		//    the const value can never drift away from $module_parts['hooks'].
		dolibarr_set_const($this->db, 'MAIN_MODULE_SFRSREPORTS_HOOKS', json_encode(self::HOOKS), 'chaine', 0, '', $conf->entity);

		// Permissions
		$this->remove($options);

		$sql = array();
		return $this->_init($sql, $options);
	}


	/**
	 * Bind Dolibarr's default-account constants (read by the standard
	 * sales/purchase/expense journals) to SFRS chart-of-accounts codes.
	 *
	 * Effect: when a user creates a customer invoice, the bookkeeping line
	 * written by sellsjournal.php uses 1600 (Trade receivables) for the AR
	 * side and 4000 (Sales of goods) for the revenue side, instead of the
	 * default PCG 411/706 accounts.
	 *
	 * Existing user-set values are NOT overwritten — we only set a value
	 * if the const is currently empty.
	 */
	private function bindDolibarrDefaultAccounts()
	{
		global $conf, $user;

		$bindings = array(
			// Third-party defaults
			'ACCOUNTING_ACCOUNT_CUSTOMER'             => '1600',   // Trade receivables
			'ACCOUNTING_ACCOUNT_SUPPLIER'             => '2000',   // Trade payables
			'SALARIES_ACCOUNTING_ACCOUNT_PAYMENT'     => '2410',   // Wages and salaries payable
			'ACCOUNTING_ACCOUNT_EXPENSEREPORT'        => '2100',   // Accruals - operating expenses
			'ACCOUNTING_ACCOUNT_TRANSFER_CASH'        => '1899',   // Cash and cash equivalents (clearing)
			'ACCOUNTING_ACCOUNT_SUSPENSE'             => '8500',   // Suspense account (unallocated entries)

			// Product / service defaults (revenue)
			'ACCOUNTING_PRODUCT_SOLD_ACCOUNT'         => '4000',   // Sales - goods (9% GST)
			'ACCOUNTING_PRODUCT_SOLD_EXPORT_ACCOUNT'  => '4001',   // Sales - goods (zero-rated, exports)
			'ACCOUNTING_SERVICE_SOLD_ACCOUNT'         => '4100',   // Service revenue (9% GST)
			'ACCOUNTING_SERVICE_SOLD_EXPORT_ACCOUNT'  => '4101',   // Service revenue (zero-rated, international)

			// Product / service defaults (expense)
			'ACCOUNTING_PRODUCT_BUY_ACCOUNT'          => '5000',   // Cost of goods sold - purchases
			'ACCOUNTING_PRODUCT_BUY_IMPORT_ACCOUNT'   => '5000',   // (Dolibarr uses same)
			'ACCOUNTING_SERVICE_BUY_ACCOUNT'          => '6430',   // Consultancy fees
			'ACCOUNTING_SERVICE_BUY_IMPORT_ACCOUNT'   => '6430',   // (Dolibarr uses same)

			// GST / VAT
			'ACCOUNTING_VAT_SOLD_ACCOUNT'             => '2200',   // GST output tax (9% standard)
			'ACCOUNTING_VAT_BUY_ACCOUNT'              => '1730',   // GST input tax recoverable
			'ACCOUNTING_VAT_PAY_ACCOUNT'              => '2240',   // GST payable to IRAS (net)

			// Customer / supplier deposits
			'ACCOUNTING_ACCOUNT_CUSTOMER_DEPOSIT'     => '2450',   // Deposits received
			'ACCOUNTING_ACCOUNT_SUPPLIER_DEPOSIT'     => '1720',   // Deposits paid
		);

		foreach ($bindings as $const_name => $sf_account_code) {
			$existing = getDolGlobalString($const_name);
			if ($existing === '' || $existing === null) {
				dolibarr_set_const($this->db, $const_name, $sf_account_code, 'chaine', 0, '', $conf->entity);
			}
		}
	}


	/**
	 * Insert SG GST 9% tax rates (standard-rated, zero-rated, exempt) into
	 * llx_c_tva if not already present. Each row points at the right SFRS
	 * GST account in accountancy_code_sell / accountancy_code_buy.
	 */
	private function registerSggstRates()
	{
		global $conf;

		$sg_country_id = 29;
		$gst_rates = array(
			array(
				'code'                  => 'SG-S9',
				'label'                 => 'SG GST 9% (Standard-rated supplies, 2024+)',
				'taux'                  => 9.0,
				'type_vat'              => 0, // both sell and buy
				'accountancy_code_sell' => '2200',  // GST output tax
				'accountancy_code_buy'  => '1730',  // GST input tax recoverable
			),
			array(
				'code'                  => 'SG-S0',
				'label'                 => 'SG GST 0% (Zero-rated supplies, e.g. exports)',
				'taux'                  => 0.0,
				'type_vat'              => 1, // sell only
				'accountancy_code_sell' => '2210',  // GST output tax (zero-rated)
				'accountancy_code_buy'  => '1730',
			),
			array(
				'code'                  => 'SG-EX',
				'label'                 => 'SG GST Exempt (Exempt supplies, e.g. financial services)',
				'taux'                  => 0.0,
				'type_vat'              => 1, // sell only
				'accountancy_code_sell' => '2220',  // GST output tax (exempt)
				'accountancy_code_buy'  => '',
			),
			array(
				'code'                  => 'SG-S7',
				'label'                 => 'SG GST 7% (Standard-rated supplies, 2023 - historical)',
				'taux'                  => 7.0,
				'type_vat'              => 0,
				'accountancy_code_sell' => '2200',
				'accountancy_code_buy'  => '1730',
			),
			array(
				'code'                  => 'SG-S8',
				'label'                 => 'SG GST 8% (Standard-rated supplies, 2023-2024 - transitional)',
				'taux'                  => 8.0,
				'type_vat'              => 0,
				'accountancy_code_sell' => '2200',
				'accountancy_code_buy'  => '1730',
			),
		);

		foreach ($gst_rates as $r) {
			// Idempotency: skip if a row with same code + fk_pays + entity already exists
			// (c_tva is entity-scoped: in multicompany each entity gets its own rates)
			$sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_tva";
			$sql_check .= " WHERE code = '".$this->db->escape($r['code'])."'";
			$sql_check .= " AND fk_pays = ".$sg_country_id;
			$sql_check .= " AND entity = ".((int) $conf->entity);
			$res_check = $this->db->query($sql_check);
			if ($res_check && $this->db->num_rows($res_check) > 0) {
				continue;
			}

			$sql_ins = "INSERT INTO ".MAIN_DB_PREFIX."c_tva";
			$sql_ins .= " (entity, fk_pays, code, type_vat, taux, localtax1, localtax1_type, localtax2, localtax2_type,";
			$sql_ins .= "  use_default, recuperableonly, note, active, accountancy_code_sell, accountancy_code_buy)";
			$sql_ins .= " VALUES (";
			$sql_ins .= ((int) $conf->entity).",";
			$sql_ins .= $sg_country_id.",";
			$sql_ins .= " '".$this->db->escape($r['code'])."',";
			$sql_ins .= (int) $r['type_vat'].",";
			$sql_ins .= (float) $r['taux'].",";
			$sql_ins .= " '0','0','0','0',";
			$sql_ins .= " 0, 0,";
			$sql_ins .= " '".$this->db->escape($r['label'])."', 1,";
			$sql_ins .= " ".($r['accountancy_code_sell'] ? "'".$this->db->escape($r['accountancy_code_sell'])."'" : 'NULL').",";
			$sql_ins .= " ".($r['accountancy_code_buy'] ? "'".$this->db->escape($r['accountancy_code_buy'])."'" : 'NULL');
			$sql_ins .= ")";

			$res = $this->db->query($sql_ins);
			if (!$res) {
				dol_syslog("SFRS module: failed to insert GST rate ".$r['code'], LOG_WARNING);
			}
		}
	}


	/**
	 * Ensure the seven standard Dolibarr accounting journals exist for the
	 * current entity, and bind the period-closure journal constant.
	 *
	 * The journal set matches install/mysql/data/llx_accounting_journal.sql:
	 *   OD (nature 1)  miscellaneous / general journal — manual & FX revaluation entries
	 *   VT (nature 2)  sales journal        (sellsjournal)
	 *   AC (nature 3)  purchase journal     (purchasesjournal)
	 *   BQ (nature 4)  bank/finance journal (bankjournal)
	 *   ER (nature 5)  expense report journal
	 *   INV (nature 8) inventory journal
	 *   AN (nature 9)  has-new / opening journal — required by closeFiscalPeriod()
	 *
	 * Journals are normally created per-entity at install / multicompany setup;
	 * this idempotent check covers entities that were created without them —
	 * without a journal row the core journal pages cannot even be opened
	 * (they fetch the journal by rowid), and BookKeeping::closeFiscalPeriod()
	 * refuses to run without ACCOUNTING_CLOSURE_DEFAULT_JOURNAL.
	 *
	 * @return int >=0 number of journals created, -1 on error
	 */
	private function ensureAccountingJournals()
	{
		global $conf;

		$journals = array(
			array('code' => 'OD',  'label' => 'ACCOUNTING_MISCELLANEOUS_JOURNAL', 'nature' => 1),
			array('code' => 'VT',  'label' => 'ACCOUNTING_SELL_JOURNAL',          'nature' => 2),
			array('code' => 'AC',  'label' => 'ACCOUNTING_PURCHASE_JOURNAL',      'nature' => 3),
			array('code' => 'BQ',  'label' => 'FinanceJournal',                   'nature' => 4),
			array('code' => 'ER',  'label' => 'ExpenseReportsJournal',            'nature' => 5),
			array('code' => 'INV', 'label' => 'InventoryJournal',                 'nature' => 8),
			array('code' => 'AN',  'label' => 'ACCOUNTING_HAS_NEW_JOURNAL',       'nature' => 9),
		);

		$created = 0;
		foreach ($journals as $j) {
			$sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."accounting_journal";
			$sql_check .= " WHERE entity = ".((int) $conf->entity)." AND nature = ".(int) $j['nature'];
			$res_check = $this->db->query($sql_check);
			if (!$res_check) {
				$this->error = 'Failed checking journal '.$j['code'].': '.$this->db->lasterror();
				return -1;
			}
			if ($this->db->num_rows($res_check) > 0) {
				continue;
			}
			$sql_ins = "INSERT INTO ".MAIN_DB_PREFIX."accounting_journal";
			$sql_ins .= " (entity, code, label, nature, active)";
			$sql_ins .= " VALUES (".((int) $conf->entity).", '".$this->db->escape($j['code'])."',";
			$sql_ins .= " '".$this->db->escape($j['label'])."', ".(int) $j['nature'].", 1)";
			if (!$this->db->query($sql_ins)) {
				$this->error = 'Failed creating journal '.$j['code'].': '.$this->db->lasterror();
				return -1;
			}
			$created++;
		}

		// closeFiscalPeriod() requires a nature-9 (AN) journal rowid; bind it
		// only if the constant is empty so an admin's explicit choice wins.
		if (getDolGlobalString('ACCOUNTING_CLOSURE_DEFAULT_JOURNAL') === '') {
			$sql_an = "SELECT rowid FROM ".MAIN_DB_PREFIX."accounting_journal";
			$sql_an .= " WHERE entity = ".((int) $conf->entity)." AND nature = 9";
			$sql_an .= " ORDER BY rowid ASC";
			$res_an = $this->db->query($sql_an);
			if ($res_an && ($obj_an = $this->db->fetch_object($res_an)) && $obj_an->rowid > 0) {
				dolibarr_set_const($this->db, 'ACCOUNTING_CLOSURE_DEFAULT_JOURNAL', (int) $obj_an->rowid, 'chaine', 0, '', $conf->entity);
			}
		}

		return $created;
	}


	/**
	 * Configure Dolibarr's built-in period closure (BookKeeping::closeFiscalPeriod)
	 * to write the P&L net result into the SFRS Retained Earnings account (3110),
	 * and balance-sheet balances into opening balances of the new period.
	 *
	 * Without these four constants, Dolibarr's closure refuses to run because it
	 * cannot find an "income statement account" or "result account" to post to.
	 *
	 * Constants set (only if currently empty):
	 *   ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_INCOME_STATEMENT = 'INCOME,EXPENSE'
	 *     - tells closure which pcg_type groups are P&L (summed and closed)
	 *   ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_BALANCE_SHEET_ACCOUNT = 'ASSET,LIABILITY,EQUITY'
	 *     - which groups are balance sheet (carried forward as opening)
	 *   ACCOUNTING_RESULT_PROFIT = '3110'
	 *     - SFRS "Current year profit/loss" — what profit closes into
	 *   ACCOUNTING_RESULT_LOSS = '3110'
	 *     - SFRS "Current year profit/loss" — what loss closes into (same account; sign reflects)
	 *
	 * Note: ACCOUNTING_CLOSURE_DEFAULT_JOURNAL (the AN journal rowid) is bound
	 * by ensureAccountingJournals(), which must run before this method.
	 */
	private function configurePeriodClosure()
	{
		global $conf;

		$closure_defaults = array(
			'ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_INCOME_STATEMENT'
				=> 'INCOME,EXPENSE',
			'ACCOUNTING_CLOSURE_ACCOUNTING_GROUPS_USED_FOR_BALANCE_SHEET_ACCOUNT'
				=> 'ASSET,LIABILITY,EQUITY',
			'ACCOUNTING_RESULT_PROFIT' => '3110',
			'ACCOUNTING_RESULT_LOSS'   => '3110',
		);

		foreach ($closure_defaults as $const_name => $value) {
			$existing = getDolGlobalString($const_name);
			if ($existing === '' || $existing === null) {
				dolibarr_set_const($this->db, $const_name, $value, 'chaine', 0, '', $conf->entity);
			}
		}
	}


	/**
	 * Function called when module is disabled.
	 * Note: we do NOT drop the SFRS chart of accounts on disable — they may be in use.
	 * Only the SFRS_SG_COA_LOADED flag is left as-is, allowing user to re-enable and skip re-import.
	 *
	 * @param string $options Options when disabling module
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}


	/**
	 * Execute a SQL file with proper table prefix replacement.
	 *
	 * Dolibarr's standard runSqlFile() does not replace table prefixes,
	 * so we implement our own version that handles MAIN_DB_PREFIX correctly.
	 *
	 * @param string $file_path Absolute path to the .sql file
	 * @return int >= 0 on success (number of statements executed), -1 on error
	 */
	protected function runSqlFile($file_path)
	{
		global $conf;

		if (!file_exists($file_path)) {
			$this->error = 'SQL file not found: '.$file_path;
			return -1;
		}

		$sql_content = file_get_contents($file_path);
		if ($sql_content === false) {
			$this->error = 'Cannot read SQL file: '.$file_path;
			return -1;
		}

		$entity = (int) ($conf->entity ?? 1);
		$rowid_offset = $entity * 100000000;

		// Replace placeholders
		$sql_content = str_replace('__ENTITY__', (string) $entity, $sql_content);
		$sql_content = str_replace('__ROWID_OFFSET__', (string) $rowid_offset, $sql_content);
		$sql_content = preg_replace('/\bllx_/', MAIN_DB_PREFIX, $sql_content);

		// Split into individual statements
		$lines = preg_split('/\r?\n/', $sql_content);
		$executed = 0;

		foreach ($lines as $line) {
			$line = trim($line);
			// Skip empty lines and comments
			if (empty($line) || strpos($line, '--') === 0) {
				continue;
			}
			// Only process INSERT statements
			if (stripos($line, 'INSERT') !== 0) {
				continue;
			}

			$result = $this->db->query($line);
			if (!$result) {
				$this->error = 'SQL error: '.$this->db->lasterror().' in line: '.substr($line, 0, 200);
				return -1;
			}
			$executed++;
		}

		return $executed;
	}
}