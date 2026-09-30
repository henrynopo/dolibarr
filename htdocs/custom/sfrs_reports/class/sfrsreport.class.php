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
 * \file       htdocs/custom/sfrs_reports/class/sfrsreport.class.php
 * \ingroup    sfrs_reports
 * \brief      Main class for SFRS Reports — handles COA and categories import, builder access
 *
 * See sfrsbalancebuilder.class.php / sfrsplbuilder.class.php / sfrscashflowbuilder.class.php
 * for the per-report data aggregation.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';


/**
 * Class SfrsReport — facade for SFRS Reports module
 */
class SfrsReport extends CommonObject
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error string
	 */
	public $error;

	/**
	 * @var string[] Error messages
	 */
	public $errors = array();

	/**
	 * @var string Element identifier (kept for CommonObject compatibility)
	 */
	public $element = 'sfrs_report';

	/**
	 * @var string Table element
	 */
	public $table_element = 'sfrs_report';


	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Get the active SFRS framework constant value.
	 *
	 * @return string 'FRS' | 'SFRS(I)' | 'SFRS_FOR_SE'
	 */
	public function getFramework()
	{
		$fw = getDolGlobalString('SFRS_FRAMEWORK', 'FRS');
		if (!in_array($fw, array('FRS', 'SFRS(I)', 'SFRS_FOR_SE'))) {
			return 'FRS';
		}
		return $fw;
	}


	/**
	 * Get the active Cash Flow Statement method constant value.
	 *
	 * @return string 'direct' | 'indirect'
	 */
	public function getCashFlowMethod()
	{
		$method = getDolGlobalString('SFRS_CASHFLOW_METHOD', 'direct');
		return ($method === 'indirect') ? 'indirect' : 'direct';
	}


	/**
	 * Get the pcg_version of the chart of accounts active for the current entity.
	 *
	 * Mirrors the core convention (accountancy/admin/account.php): the
	 * CHARTOFACCOUNTS const stores the llx_accounting_system.rowid, so we join
	 * to get the version string. In multicompany setups each entity picks its
	 * own chart — SFRS reports are only meaningful on the 'SFRS-BASE' chart.
	 *
	 * @return string pcg_version (e.g. 'SFRS-BASE'), '' if not determinable
	 */
	public function getActiveChartVersion()
	{
		$chart_rowid = getDolGlobalInt('CHARTOFACCOUNTS');
		if ($chart_rowid <= 0) {
			return '';
		}

		$sql = "SELECT pcg_version FROM ".MAIN_DB_PREFIX."accounting_system WHERE rowid = ".((int) $chart_rowid);
		$res = $this->db->query($sql);
		if ($res && $obj = $this->db->fetch_object($res)) {
			return $obj->pcg_version;
		}
		return '';
	}


	/**
	 * Get the SFRS report rowid for the given report key.
	 * BS=2, PL=3, CF=4 — kept stable for compatibility with c_accounting_category.fk_report.
	 *
	 * @param string $report 'BS' | 'PL' | 'CF'
	 * @return int fk_report id
	 */
	public static function getReportRowid($report)
	{
		switch (strtoupper($report)) {
			case 'BS':
			case 'BALANCE_SHEET':
				return 2;
			case 'PL':
			case 'P&L':
			case 'PROFIT_LOSS':
				return 3;
			case 'CF':
			case 'CASH_FLOW':
				return 4;
			default:
				return 0;
		}
	}


	/**
	 * Build the multicurrency_code WHERE clause fragment.
	 *
	 * Semantics for $mc_code:
	 *   - 'FUNC' (default) -> entries with NULL/empty multicurrency_code (functional-currency postings)
	 *   - 'ALL'            -> no filter; sum all currencies together
	 *   - any other value  -> entries whose multicurrency_code equals the value (e.g. 'USD', 'SGD')
	 *
	 * @param string $mc_code One of 'FUNC', 'ALL', or an ISO currency code
	 * @return string SQL fragment starting with " AND " or "" if no filter applies
	 */
	private function buildMcCodeWhere($mc_code)
	{
		$mc_code = trim((string) $mc_code);
		if ($mc_code === '' || $mc_code === 'FUNC') {
			// Functional-currency postings: legacy entries without a multicurrency_code set,
			// OR entries explicitly tagged with the company's base currency code.
			// MAIN_MONNAIE is the llx_const key holding the base currency code (set in admin/company.php).
			$base_currency = getDolGlobalString('MAIN_MONNAIE', '');
			$base_currency_sql = '';
			if (!empty($base_currency)) {
				$base_currency_sql = " OR t.multicurrency_code = '".$this->db->escape($base_currency)."'";
			}
			return " AND (t.multicurrency_code IS NULL OR t.multicurrency_code = ''".$base_currency_sql.")";
		}
		if ($mc_code === 'ALL') {
			return '';
		}
		return " AND t.multicurrency_code = '".$this->db->escape($mc_code)."'";
	}


	/**
	 * Get total debit/credit summed over an account range (inclusive on both ends)
	 * for bookkeeping entries up to a given date, scoped to current entity.
	 *
	 * @param string $range_start Account prefix (e.g. '1000')
	 * @param string $range_end   Account prefix (e.g. '1399')
	 * @param int    $date_limit  Unix timestamp — inclusive
	 * @param string $mc_code     'FUNC' (default) | 'ALL' | 'USD' / 'SGD' / etc.
	 * @return array ['debit'=>float, 'credit'=>float, 'balance'=>float]
	 */
	public function sumByRange($range_start, $range_end, $date_limit, $mc_code = 'FUNC')
	{
		global $conf;

		$date_str = $this->db->idate($date_limit);

		$sql = "SELECT COALESCE(SUM(t.debit), 0) AS debit, COALESCE(SUM(t.credit), 0) AS credit";
		$sql .= " FROM ".MAIN_DB_PREFIX."accounting_bookkeeping AS t";
		$sql .= " WHERE t.entity = ".((int) $conf->entity); // Do not use getEntity for accounting features
		$sql .= " AND t.doc_date <= '".$date_str."'";
		$sql .= " AND t.numero_compte BETWEEN '".$this->db->escape($range_start)."' AND '".$this->db->escape($range_end)."'";
		$sql .= $this->buildMcCodeWhere($mc_code);

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = 'sumByRange query failed: '.$this->db->lasterror();
			return array('debit' => 0, 'credit' => 0, 'balance' => 0);
		}
		$obj = $this->db->fetch_object($res);
		return array(
			'debit' => (float) $obj->debit,
			'credit' => (float) $obj->credit,
			'balance' => (float) ($obj->debit - $obj->credit),
		);
	}


	/**
	 * Get total debit/credit summed over an account range within a date period (for P&L / CF).
	 *
	 * @param string $range_start  Account prefix
	 * @param string $range_end    Account prefix
	 * @param int    $date_start   Unix timestamp — inclusive
	 * @param int    $date_end     Unix timestamp — inclusive
	 * @param string $mc_code      'FUNC' (default) | 'ALL' | 'USD' / 'SGD' / etc.
	 * @return array ['debit'=>float, 'credit'=>float, 'balance'=>float]
	 */
	public function sumByRangeInPeriod($range_start, $range_end, $date_start, $date_end, $mc_code = 'FUNC')
	{
		global $conf;

		$ds = $this->db->idate($date_start);
		$de = $this->db->idate($date_end);

		$sql = "SELECT COALESCE(SUM(t.debit), 0) AS debit, COALESCE(SUM(t.credit), 0) AS credit";
		$sql .= " FROM ".MAIN_DB_PREFIX."accounting_bookkeeping AS t";
		$sql .= " WHERE t.entity = ".((int) $conf->entity); // Do not use getEntity for accounting features
		$sql .= " AND t.doc_date BETWEEN '".$ds."' AND '".$de."'";
		$sql .= " AND t.numero_compte BETWEEN '".$this->db->escape($range_start)."' AND '".$this->db->escape($range_end)."'";
		$sql .= $this->buildMcCodeWhere($mc_code);

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = 'sumByRangeInPeriod query failed: '.$this->db->lasterror();
			return array('debit' => 0, 'credit' => 0, 'balance' => 0);
		}
		$obj = $this->db->fetch_object($res);
		return array(
			'debit' => (float) $obj->debit,
			'credit' => (float) $obj->credit,
			'balance' => (float) ($obj->debit - $obj->credit),
		);
	}


	/**
	 * Load SFRS report categories for a given fk_report and current entity.
	 *
	 * Country scoping follows the core AccountancyCategory::getCats() convention:
	 * categories bound to the company's country (fk_country = mysoc->country_id)
	 * or generic ones (fk_country = 0). In multicompany setups a non-SG entity
	 * therefore gets no SFRS categories (empty report) instead of wrong SG data.
	 *
	 * @param int $fk_report fk_report id (2=BS, 3=PL, 4=CF)
	 * @return array<int,array{rowid:int,code:string,label:string,range_account:string,sens:int,category_type:int,formula:string,position:int}>
	 */
	public function fetchCategories($fk_report)
	{
		global $conf, $mysoc;

		if (empty($mysoc->country_id)) {
			$this->error = 'fetchCategories failed: company country is not defined';
			return array();
		}

		$sql = "SELECT rowid, code, label, range_account, sens, category_type, formula, position";
		$sql .= " FROM ".MAIN_DB_PREFIX."c_accounting_category";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		$sql .= " AND fk_report = ".(int) $fk_report;
		$sql .= " AND (fk_country = ".((int) $mysoc->country_id)." OR fk_country = 0)";
		$sql .= " AND active = 1";
		$sql .= " ORDER BY position ASC, rowid ASC";

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = 'fetchCategories failed: '.$this->db->lasterror();
			return array();
		}

		$out = array();
		while ($obj = $this->db->fetch_object($res)) {
			$out[] = array(
				'rowid' => (int) $obj->rowid,
				'code' => $obj->code,
				'label' => $obj->label,
				'range_account' => $obj->range_account,
				'sens' => (int) $obj->sens,
				'category_type' => (int) $obj->category_type,
				'formula' => $obj->formula,
				'position' => (int) $obj->position,
			);
		}
		return $out;
	}


	/**
	 * Evaluate a simple category formula. Supported syntax:
	 *   - 'CODE+CODE'              e.g. 'INCOMES+EXPENSES'
	 *   - 'CODE-CODE'              e.g. 'INCOMES-EXPENSES'
	 *   - '(CODE+CODE)-CODE'       grouped
	 *   - single code 'CODE'
	 *
	 * @param string $formula Raw formula text
	 * @param array<string,float> $values_by_code Map of category code -> value
	 * @return float|null Computed value or null if formula is malformed
	 */
	public static function evaluateFormula($formula, $values_by_code)
	{
		$formula = trim($formula);
		if ($formula === '') {
			return null;
		}

		// Validate characters
		if (!preg_match('/^[A-Z0-9_+\-\(\)\s]+$/i', $formula)) {
			return null;
		}

		// Replace each identifier with its value
		$expr = $formula;
		// Sort codes by length desc to avoid prefix replacement issues
		$codes = array_keys($values_by_code);
		usort($codes, function ($a, $b) {
			return strlen($b) - strlen($a);
		});
		foreach ($codes as $code) {
			$expr = preg_replace('/\b'.preg_quote($code, '/').'\b/', '('.(float) $values_by_code[$code].')', $expr);
		}

		// After replacement, only digits/operators/parens/dots should remain
		if (!preg_match('/^[\d+\-\*\/\(\)\.\s]+$/', $expr)) {
			return null;
		}

		// Safe eval: use PHP's math via a restricted closure
		try {
			$val = 0.0;
			$result = @eval('return ('.$expr.');');
			return is_numeric($result) ? (float) $result : null;
		} catch (\Throwable $e) {
			return null;
		}
	}


	/**
	 * Get the per-currency pinned rates used by the 'fixed' rate method.
	 *
	 * Stored as a JSON map in SFRS_MC_FIXED_RATES, e.g. {"USD":"1.35","CNY":"0.185"}.
	 * One rate per currency: a multi-currency ledger holds many foreign
	 * currencies, so a single global number cannot serve the 'fixed' method.
	 * Direction matches llx_multicurrency_rate.rate: 1 foreign = X functional.
	 *
	 * @return array<string,float> Currency code => rate (empty array when unset/invalid)
	 */
	public function getFixedRates()
	{
		$json = getDolGlobalString('SFRS_MC_FIXED_RATES', '');
		$map = ($json !== '') ? json_decode($json, true) : null;
		if (!is_array($map)) {
			return array();
		}
		$out = array();
		foreach ($map as $code => $rate) {
			$rate = (float) $rate;
			if (preg_match('/^[A-Za-z]{3}$/', (string) $code) && $rate > 0) {
				$out[strtoupper((string) $code)] = $rate;
			}
		}
		return $out;
	}


	/**
	 * Get exchange rate for a currency as of a specific date.
	 *
	 * Rate source: llx_multicurrency_rate joined with llx_multicurrency.
	 * The 'rate' column represents: 1 foreign currency = X functional currency units.
	 *
	 * @param string $currency_code ISO 4217 currency code, e.g. 'USD'
	 * @param int    $date          Unix timestamp (inclusive upper bound)
	 * @param string $method         'closing' (default) | 'historical' | 'fixed'
	 *                              closing/historical: latest rate on or before $date
	 *                              fixed: pinned rate for this currency (SFRS_MC_FIXED_RATES),
	 *                              falling back to the rate table when not pinned
	 * @return float Exchange rate (1 foreign = X functional), or 1.0 if not found
	 */
	public function getRateForDate($currency_code, $date, $method = 'closing')
	{
		// For FUNC currency or empty code, return 1.0
		if (empty($currency_code) || $currency_code === 'FUNC') {
			return 1.0;
		}

		// Fixed rate method: use the pinned rate for THIS currency; when it has
		// no pinned entry, fall through to the rate table below rather than
		// silently converting at a wrong global number.
		if ($method === 'fixed') {
			$fixed = $this->getFixedRates();
			if (isset($fixed[$currency_code])) {
				return $fixed[$currency_code];
			}
		}

		$date_str = $this->db->idate($date);

		// Query: latest rate on or before the given date
		$sql = "SELECT cr.rate";
		$sql .= " FROM ".MAIN_DB_PREFIX."multicurrency_rate AS cr";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."multicurrency AS m ON cr.fk_multicurrency = m.rowid";
		$sql .= " WHERE cr.entity IN (".getEntity('multicurrency').")";
		$sql .= " AND m.code = '".$this->db->escape($currency_code)."'";
		$sql .= " AND cr.rate > 0";
		$sql .= " AND cr.date_sync <= '".$date_str."'";
		$sql .= " ORDER BY cr.date_sync DESC";
		$sql .= " LIMIT 1";

		$res = $this->db->query($sql);
		if ($res && $obj = $this->db->fetch_object($res)) {
			return (float) $obj->rate;
		}

		// Fallback: try any rate for this currency (no date constraint)
		$sql2 = "SELECT cr.rate";
		$sql2 .= " FROM ".MAIN_DB_PREFIX."multicurrency_rate AS cr";
		$sql2 .= " INNER JOIN ".MAIN_DB_PREFIX."multicurrency AS m ON cr.fk_multicurrency = m.rowid";
		$sql2 .= " WHERE cr.entity IN (".getEntity('multicurrency').")";
		$sql2 .= " AND m.code = '".$this->db->escape($currency_code)."'";
		$sql2 .= " AND cr.rate > 0";
		$sql2 .= " ORDER BY cr.date_sync DESC";
		$sql2 .= " LIMIT 1";

		$res2 = $this->db->query($sql2);
		if ($res2 && $obj2 = $this->db->fetch_object($res2)) {
			return (float) $obj2->rate;
		}

		// Ultimate fallback: return 1.0 (no conversion needed)
		return 1.0;
	}


	/**
	 * Calculate the average exchange rate over a period.
	 *
	 * Used for P&L translation under IAS 21: income and expenses should
	 * be translated at average rate for the period.
	 *
	 * @param string $currency_code ISO 4217 currency code, e.g. 'USD'
	 * @param int    $date_start   Unix timestamp (inclusive)
	 * @param int    $date_end     Unix timestamp (inclusive)
	 * @param string $method       'average' (default) | 'closing' | 'fixed'
	 *                             fixed: pinned rate for this currency (SFRS_MC_FIXED_RATES),
	 *                             falling back to the period average when not pinned
	 * @return float Average exchange rate, or 1.0 if no rates found
	 */
	public function getAverageRateInPeriod($currency_code, $date_start, $date_end, $method = 'average')
	{
		// For FUNC currency or empty code, return 1.0
		if (empty($currency_code) || $currency_code === 'FUNC') {
			return 1.0;
		}

		// Fixed rate method: pinned rate for THIS currency; when it has no
		// pinned entry, fall through to the period average below.
		if ($method === 'fixed') {
			$fixed = $this->getFixedRates();
			if (isset($fixed[$currency_code])) {
				return $fixed[$currency_code];
			}
		}

		$ds = $this->db->idate($date_start);
		$de = $this->db->idate($date_end);

		if ($method === 'closing') {
			// Use the closing rate at period end
			return $this->getRateForDate($currency_code, $date_end, 'closing');
		}

		// Average rate: compute arithmetic mean of all rates in the period
		$sql = "SELECT AVG(cr.rate) AS avg_rate, COUNT(cr.rowid) AS cnt";
		$sql .= " FROM ".MAIN_DB_PREFIX."multicurrency_rate AS cr";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."multicurrency AS m ON cr.fk_multicurrency = m.rowid";
		$sql .= " WHERE cr.entity IN (".getEntity('multicurrency').")";
		$sql .= " AND m.code = '".$this->db->escape($currency_code)."'";
		$sql .= " AND cr.rate > 0";
		$sql .= " AND cr.date_sync >= '".$ds."'";
		$sql .= " AND cr.date_sync <= '".$de."'";

		$res = $this->db->query($sql);
		if ($res && $obj = $this->db->fetch_object($res)) {
			if (!empty($obj->avg_rate) && (int) $obj->cnt > 0) {
				return (float) $obj->avg_rate;
			}
		}

		// Fallback: use closing rate if no rates in period
		return $this->getRateForDate($currency_code, $date_end, 'closing');
	}


	/**
	 * Get FX translation difference account codes from settings.
	 *
	 * @return array{gain:string, loss:string}
	 */
	public function getFxAccountCodes()
	{
		return array(
			'gain' => getDolGlobalString('SFRS_MC_FX_GAIN_ACCOUNT', '7010'),
			'loss' => getDolGlobalString('SFRS_MC_FX_LOSS_ACCOUNT', '7110'),
		);
	}


	/**
	 * Generate the period-end unrealised FX revaluation journal entry (OD).
	 *
	 * SFRS 21 / IAS 21 requires open foreign-currency monetary items to be
	 * retranslated at the closing rate at each reporting date, with the
	 * difference taken to P&L as unrealised FX gain/loss.
	 *
	 * Scans OPEN multicurrency customer and supplier invoices (validated,
	 * unpaid, not abandoned), computes per invoice:
	 *   open_fx  = multicurrency_total_ttc x (open_sgd / total_ttc)  [proportional]
	 *   adj      = open_fx x closing_rate - open_sgd
	 * and posts ONE aggregated manual journal piece (doc_ref 'FXREV-YYYY-MM-DD',
	 * journal OD) with lines on the AR / AP accounts and the FX gain/loss
	 * accounts from getFxAccountCodes().
	 *
	 * Idempotent per date (piece_ref checked in bookkeeping). Aborts with an
	 * error if the closing rate for any used currency is missing — unlike the
	 * report pages, a booked journal must never fall back to rate 1.0.
	 *
	 * @param int  $date_limit Unix timestamp — revaluation date (period end)
	 * @param User $user       User creating the entry
	 * @return int >0 number of bookkeeping lines created, 0 nothing to do, -1 error ($this->error set)
	 */
	public function generateFxRevaluation($date_limit, $user)
	{
		global $conf, $langs;

		$date_str = dol_print_date($date_limit, 'standard');
		$piece_ref = 'FXREV-'.$date_str;

		// Idempotency: one FXREV piece per date and entity
		$sql_chk = "SELECT COUNT(*) AS n FROM ".MAIN_DB_PREFIX."accounting_bookkeeping";
		$sql_chk .= " WHERE entity = ".((int) $conf->entity)." AND doc_ref = '".$this->db->escape($piece_ref)."'";
		$res_chk = $this->db->query($sql_chk);
		if ($res_chk && ($obj_chk = $this->db->fetch_object($res_chk)) && (int) $obj_chk->n > 0) {
			$this->error = $langs->trans('FXRevaluationAlreadyExists', $piece_ref);
			return -1;
		}

		// Closing rates, strictly dated (no 1.0 fallback for a booked journal)
		$rates = array();
		$missing = array();

		$adj_ar = 0.0; // sum of customer-side adjustments
		$sql_ar = "SELECT f.rowid, f.multicurrency_code, f.multicurrency_total_ttc, f.total_ttc,";
		$sql_ar .= " COALESCE((SELECT SUM(pf.amount) FROM ".MAIN_DB_PREFIX."paiement_facture pf";
		$sql_ar .= "   JOIN ".MAIN_DB_PREFIX."paiement p ON p.rowid = pf.fk_paiement AND p.entity = f.entity";
		$sql_ar .= "   WHERE pf.fk_facture = f.rowid), 0) AS paid_sgd";
		$sql_ar .= " FROM ".MAIN_DB_PREFIX."facture f";
		$sql_ar .= " WHERE f.entity = ".((int) $conf->entity);
		$sql_ar .= " AND f.fk_statut = 1 AND f.paye = 0 AND f.close_code IS NULL";
		$sql_ar .= " AND f.multicurrency_code IS NOT NULL AND f.multicurrency_code <> ''";
		$sql_ar .= " AND f.total_ttc <> 0";
		$res_ar = $this->db->query($sql_ar);
		if (!$res_ar) {
			$this->error = 'generateFxRevaluation: '.$this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($res_ar)) {
			$rate = $this->getStrictClosingRate($obj->multicurrency_code, $date_limit, $rates, $missing);
			if ($rate === null) {
				continue;
			}
			$open_sgd = (float) $obj->total_ttc - (float) $obj->paid_sgd;
			$open_fx = (float) $obj->multicurrency_total_ttc * ($open_sgd / (float) $obj->total_ttc);
			$adj_ar += $open_fx * $rate - $open_sgd;
		}

		$adj_ap = 0.0; // sum of supplier-side adjustments
		$sql_ap = "SELECT ff.rowid, ff.multicurrency_code, ff.multicurrency_total_ttc, ff.total_ttc,";
		$sql_ap .= " COALESCE((SELECT SUM(pff.amount) FROM ".MAIN_DB_PREFIX."paiementfourn_facturefourn pff";
		$sql_ap .= "   JOIN ".MAIN_DB_PREFIX."paiementfourn pf2 ON pf2.rowid = pff.fk_paiementfourn AND pf2.entity = ff.entity";
		$sql_ap .= "   WHERE pff.fk_facturefourn = ff.rowid), 0) AS paid_sgd";
		$sql_ap .= " FROM ".MAIN_DB_PREFIX."facture_fourn ff";
		$sql_ap .= " WHERE ff.entity = ".((int) $conf->entity);
		$sql_ap .= " AND ff.fk_statut = 1 AND ff.paye = 0";
		$sql_ap .= " AND ff.multicurrency_code IS NOT NULL AND ff.multicurrency_code <> ''";
		$sql_ap .= " AND ff.total_ttc <> 0";
		$res_ap = $this->db->query($sql_ap);
		if (!$res_ap) {
			$this->error = 'generateFxRevaluation: '.$this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($res_ap)) {
			$rate = $this->getStrictClosingRate($obj->multicurrency_code, $date_limit, $rates, $missing);
			if ($rate === null) {
				continue;
			}
			$open_sgd = (float) $obj->total_ttc - (float) $obj->paid_sgd;
			$open_fx = (float) $obj->multicurrency_total_ttc * ($open_sgd / (float) $obj->total_ttc);
			$adj_ap += $open_fx * $rate - $open_sgd;
		}

		if (!empty($missing)) {
			$this->error = $langs->trans('ExchangeRateNotFound', implode(', ', array_unique($missing)), $date_str);
			return -1;
		}

		if (abs($adj_ar) < 0.005 && abs($adj_ap) < 0.005) {
			return 0; // nothing to revalue
		}

		// Journal: the miscellaneous (OD) journal of this entity
		$sql_journal = "SELECT code, label FROM ".MAIN_DB_PREFIX."accounting_journal";
		$sql_journal .= " WHERE entity = ".((int) $conf->entity)." AND nature = 1";
		$sql_journal .= " ORDER BY rowid ASC";
		$res_journal = $this->db->query($sql_journal);
		if (!$res_journal || !($journal = $this->db->fetch_object($res_journal))) {
			$this->error = 'generateFxRevaluation: OD journal not found for entity '.(int) $conf->entity;
			return -1;
		}

		$fx_accounts = $this->getFxAccountCodes();
		$ar_account = getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER', '1600');
		$ap_account = getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER', '2000');

		// Build the entry: AR/AP lines gross, FX line as balancing amount
		$lines = array();
		if (abs($adj_ar) >= 0.005) {
			$lines[] = array(
				'account' => $ar_account,
				'debit' => ($adj_ar > 0) ? round($adj_ar, 2) : 0.0,
				'credit' => ($adj_ar < 0) ? round(-$adj_ar, 2) : 0.0,
			);
		}
		if (abs($adj_ap) >= 0.005) {
			$lines[] = array(
				'account' => $ap_account,
				'debit' => ($adj_ap < 0) ? round(-$adj_ap, 2) : 0.0,
				'credit' => ($adj_ap > 0) ? round($adj_ap, 2) : 0.0,
			);
		}
		$sum_debit = 0.0;
		$sum_credit = 0.0;
		foreach ($lines as $l) {
			$sum_debit += $l['debit'];
			$sum_credit += $l['credit'];
		}
		$net = round($sum_debit - $sum_credit, 2);
		if (abs($net) >= 0.005) {
			$lines[] = array(
				'account' => ($net > 0) ? $fx_accounts['gain'] : $fx_accounts['loss'],
				'debit' => ($net < 0) ? -$net : 0.0,
				'credit' => ($net > 0) ? $net : 0.0,
			);
		}

		if (count($lines) < 2) {
			return 0;
		}

		// One piece_num for the whole entry (same convention as BookKeeping::create)
		$sql_num = "SELECT MAX(piece_num) + 1 AS next_num FROM ".MAIN_DB_PREFIX."accounting_bookkeeping";
		$sql_num .= " WHERE entity = ".((int) $conf->entity); // Do not use getEntity for accounting features
		$res_num = $this->db->query($sql_num);
		$obj_num = $res_num ? $this->db->fetch_object($res_num) : null;
		$piece_num = (int) ($obj_num->next_num ?? 1);

		require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';

		$label_op = $langs->trans('FXRevaluationLabel', $date_str);
		$created = 0;
		foreach ($lines as $l) {
			$bookkeeping = new BookKeeping($this->db);
			$bookkeeping->numero_compte = $l['account'];
			$bookkeeping->label_compte = $this->fetchAccountLabel($l['account']);
			$bookkeeping->label_operation = $label_op;
			$bookkeeping->doc_date = $date_limit;
			$bookkeeping->doc_type = 'fx_revaluation';
			$bookkeeping->doc_ref = $piece_ref;
			$bookkeeping->ref = $piece_ref;
			$bookkeeping->piece_num = $piece_num;
			$bookkeeping->code_journal = $journal->code;
			$bookkeeping->journal_label = $journal->label;
			$bookkeeping->debit = (float) $l['debit'];
			$bookkeeping->credit = (float) $l['credit'];
			if ($l['debit'] > 0) {
				$bookkeeping->montant = $l['debit'];
				$bookkeeping->amount = $l['debit'];
				$bookkeeping->sens = 'D';
			} else {
				$bookkeeping->montant = $l['credit'];
				$bookkeeping->amount = $l['credit'];
				$bookkeeping->sens = 'C';
			}

			$result = $bookkeeping->create($user);
			if ($result < 0) {
				$this->error = 'generateFxRevaluation: BookKeeping::create failed: '.implode(', ', $bookkeeping->errors ?: array($bookkeeping->error));
				return -1;
			}
			$created++;
		}

		return $created;
	}


	/**
	 * Strict closing-rate lookup for journal generation (no 1.0 fallback).
	 * Caches per currency; currencies without a rate on/before the date are
	 * collected in $missing and null is returned for them.
	 *
	 * @param string   $code    ISO currency code
	 * @param int      $date    Unix timestamp
	 * @param array    $rates   Cache in/out: code => rate
	 * @param string[] $missing Out: codes with no usable rate
	 * @return float|null Rate, or null when missing
	 */
	private function getStrictClosingRate($code, $date, &$rates, &$missing)
	{
		if (array_key_exists($code, $rates)) {
			return $rates[$code];
		}
		$date_str = $this->db->idate($date);
		$sql = "SELECT cr.rate";
		$sql .= " FROM ".MAIN_DB_PREFIX."multicurrency_rate AS cr";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."multicurrency AS m ON cr.fk_multicurrency = m.rowid";
		$sql .= " WHERE cr.entity IN (".getEntity('multicurrency').")";
		$sql .= " AND m.code = '".$this->db->escape($code)."'";
		$sql .= " AND cr.rate > 0 AND cr.date_sync <= '".$date_str."'";
		$sql .= " ORDER BY cr.date_sync DESC";
		$sql .= " LIMIT 1";
		$res = $this->db->query($sql);
		if ($res && ($obj = $this->db->fetch_object($res))) {
			$rates[$code] = (float) $obj->rate;
			return $rates[$code];
		}
		if (!in_array($code, $missing)) {
			$missing[] = $code;
		}
		return null;
	}


	/**
	 * Fetch the label of an accounting account in the current entity's chart.
	 *
	 * @param string $account Account number, e.g. '1600'
	 * @return string Label, '' when not found
	 */
	private function fetchAccountLabel($account)
	{
		global $conf;

		$chart = $this->getActiveChartVersion();
		if ($chart === '') {
			return '';
		}
		$sql = "SELECT label FROM ".MAIN_DB_PREFIX."accounting_account";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		$sql .= " AND fk_pcg_version = '".$this->db->escape($chart)."'";
		$sql .= " AND account = '".$this->db->escape($account)."'";
		$sql .= " ORDER BY rowid ASC";
		$res = $this->db->query($sql);
		if ($res && ($obj = $this->db->fetch_object($res))) {
			return $obj->label;
		}
		return '';
	}


	/**
	 * Build the AR or AP aging report as of a given date.
	 *
	 * Data source is the BUSINESS subledger (llx_facture / llx_facture_fourn),
	 * not the ledger: aging answers "which invoices are still open", which the
	 * bookkeeping rows cannot express. An invoice is open when validated,
	 * unpaid, not abandoned (customer side), invoiced on/before $asof, and
	 * its open part = total_ttc - (payments booked on/before $asof).
	 *
	 * Note: the "open NOW" filters (paye=0) are evaluated at query time — a
	 * historical $asof therefore shows the invoices outstanding today, aged
	 * and paid-up as they stood at $asof. For $asof = today (the default)
	 * the report is exact.
	 *
	 * Buckets by age = asof - invoice date: <=0 Current, 1-30, 31-60, 61-90,
	 * >90 days. Amounts are in the functional currency (SGD); the original-
	 * currency open part uses the same proportional method as
	 * generateFxRevaluation(): multicurrency_total_ttc x (open / total_ttc).
	 *
	 * @param string $type 'ar' (customers) | 'ap' (suppliers)
	 * @param int    $asof Unix timestamp — as-of date (inclusive)
	 * @return array|int ['type','asof','thirdparties'=>[socid=>['id','name','open','current','b30','b60','b90','b90p','currencies'=>[code=>amount]]],'totals'=>[...]],
	 *                   or -1 on error ($this->error set)
	 */
	public function getAgingReport($type, $asof)
	{
		global $conf;

		$type = ($type === 'ap') ? 'ap' : 'ar';
		// Inclusive end-of-day bound: datef is a DATE column but datep is a
		// DATETIME, so a plain idate() (midnight) would drop same-day payments.
		$asof_incl = dol_print_date($asof, '%Y-%m-%d').' 23:59:59';

		if ($type === 'ar') {
			// Customer invoices: validated, unpaid, not abandoned (close_code),
			// dated on/before asof. Credit notes (type 2) carry negative
			// total_ttc and reduce the buckets naturally.
			$sql = "SELECT f.fk_soc, s.nom AS socname, f.datef, f.total_ttc,";
			$sql .= " f.multicurrency_code, f.multicurrency_total_ttc,";
			$sql .= " COALESCE((SELECT SUM(pf.amount) FROM ".MAIN_DB_PREFIX."paiement_facture pf";
			$sql .= "   JOIN ".MAIN_DB_PREFIX."paiement p ON p.rowid = pf.fk_paiement AND p.entity = f.entity";
			$sql .= "   WHERE pf.fk_facture = f.rowid AND p.datep <= '".$asof_incl."'), 0) AS paid_sgd";
			$sql .= " FROM ".MAIN_DB_PREFIX."facture f";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc";
			$sql .= " WHERE f.entity = ".((int) $conf->entity);
			$sql .= " AND f.fk_statut = 1 AND f.paye = 0 AND f.close_code IS NULL";
			$sql .= " AND f.total_ttc <> 0";
			$sql .= " AND f.datef <= '".$asof_incl."'";
			$sql .= " ORDER BY s.nom ASC, f.datef ASC";
		} else {
			// Supplier invoices: validated and unpaid (no close_code concept here)
			$sql = "SELECT ff.fk_soc, s.nom AS socname, ff.datef, ff.total_ttc,";
			$sql .= " ff.multicurrency_code, ff.multicurrency_total_ttc,";
			$sql .= " COALESCE((SELECT SUM(pff.amount) FROM ".MAIN_DB_PREFIX."paiementfourn_facturefourn pff";
			$sql .= "   JOIN ".MAIN_DB_PREFIX."paiementfourn pf2 ON pf2.rowid = pff.fk_paiementfourn AND pf2.entity = ff.entity";
			$sql .= "   WHERE pff.fk_facturefourn = ff.rowid AND pf2.datep <= '".$asof_incl."'), 0) AS paid_sgd";
			$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn ff";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = ff.fk_soc";
			$sql .= " WHERE ff.entity = ".((int) $conf->entity);
			$sql .= " AND ff.fk_statut = 1 AND ff.paye = 0";
			$sql .= " AND ff.total_ttc <> 0";
			$sql .= " AND ff.datef <= '".$asof_incl."'";
			$sql .= " ORDER BY s.nom ASC, ff.datef ASC";
		}

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = 'getAgingReport: '.$this->db->lasterror();
			return -1;
		}

		$thirdparties = array();
		$totals = array('open' => 0.0, 'current' => 0.0, 'b30' => 0.0, 'b60' => 0.0, 'b90' => 0.0, 'b90p' => 0.0);

		while ($obj = $this->db->fetch_object($res)) {
			$open = round((float) $obj->total_ttc - (float) $obj->paid_sgd, 2);
			if (abs($open) < 0.005) {
				continue; // fully settled
			}

			$socid = (int) $obj->fk_soc;
			if (!isset($thirdparties[$socid])) {
				$thirdparties[$socid] = array(
					'id' => $socid,
					'name' => $obj->socname,
					'open' => 0.0, 'current' => 0.0, 'b30' => 0.0, 'b60' => 0.0, 'b90' => 0.0, 'b90p' => 0.0,
					'currencies' => array(),
				);
			}

			$age = (int) floor(($asof - $this->db->jdate($obj->datef)) / 86400);
			if ($age <= 0) {
				$bucket = 'current';
			} elseif ($age <= 30) {
				$bucket = 'b30';
			} elseif ($age <= 60) {
				$bucket = 'b60';
			} elseif ($age <= 90) {
				$bucket = 'b90';
			} else {
				$bucket = 'b90p';
			}

			$thirdparties[$socid][$bucket] += $open;
			$thirdparties[$socid]['open'] += $open;
			$totals[$bucket] += $open;
			$totals['open'] += $open;

			// Original-currency open part (informational, proportional method)
			if (!empty($obj->multicurrency_code) && (float) $obj->total_ttc != 0) {
				$open_fx = (float) $obj->multicurrency_total_ttc * ($open / (float) $obj->total_ttc);
				$code = $obj->multicurrency_code;
				$thirdparties[$socid]['currencies'][$code] = round(($thirdparties[$socid]['currencies'][$code] ?? 0) + $open_fx, 2);
			}
		}

		return array(
			'type' => $type,
			'asof' => $asof,
			'thirdparties' => $thirdparties,
			'totals' => $totals,
		);
	}


	/**
	 * Apply exchange rate to a foreign-currency amount.
	 *
	 * @param float  $amount          Amount in foreign currency
	 * @param float $exchange_rate   Exchange rate (1 foreign = X functional)
	 * @return float Amount in functional/presentation currency
	 */
	public static function applyExchangeRate($amount, $exchange_rate)
	{
		return (float) $amount * (float) $exchange_rate;
	}
}