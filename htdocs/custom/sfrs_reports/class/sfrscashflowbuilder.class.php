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
 * \file   htdocs/custom/sfrs_reports/class/sfrscashflowbuilder.class.php
 * \brief  Build the SFRS Cash Flow Statement — direct and indirect methods
 */

require_once __DIR__.'/sfrsreport.class.php';
require_once __DIR__.'/sfrsplbuilder.class.php';


/**
 * Class SfrsCashFlowBuilder
 *
 * Two methods supported (FRS 7 paragraph 18):
 *  - direct: cash receipts and cash payments aggregated by category
 *  - indirect: net profit + non-cash items + working capital changes
 *
 * Both methods must produce the same Net Change in Cash.
 *
 * Multi-currency support (IAS 21 / SFRS 21):
 * - Operating cash flows (indirect method): uses average rate from P&L builder
 * - Cash balances (1800-1899): translated at closing rate
 * - Investing/financing cash flows: translated at closing rate or actual rate
 */
class SfrsCashFlowBuilder
{
	/** @var SfrsReport */
	private $sfrs;

	/** @var int Unix timestamp — period start (inclusive) */
	private $date_start;

	/** @var int Unix timestamp — period end (inclusive) */
	private $date_end;

	/** @var string 'direct' or 'indirect' */
	private $method;

	/** @var string Currency filter — 'FUNC' (default), 'ALL', or ISO code */
	private $mc_code;

	/** @var float|null Cached exchange rate */
	private $cached_rate = null;


	/**
	 * Constructor
	 *
	 * @param SfrsReport $sfrs       SfrsReport instance
	 * @param int        $date_start Period start
	 * @param int        $date_end   Period end
	 * @param string     $method     'direct' or 'indirect'
	 * @param string     $mc_code    'FUNC' (default) | 'ALL' | 'USD' / 'SGD' / etc.
	 */
	public function __construct($sfrs, $date_start, $date_end, $method = 'direct', $mc_code = 'FUNC')
	{
		$this->sfrs = $sfrs;
		$this->date_start = $date_start;
		$this->date_end = $date_end;
		$this->method = ($method === 'indirect') ? 'indirect' : 'direct';
		$this->mc_code = $mc_code;
	}


	/**
	 * Build the cash flow lines for the active method.
	 *
	 * @return array<int,array{code:string,label:string,amount:float,kind:string,position:int}>
	 */
	public function build()
	{
		if ($this->method === 'indirect') {
			return $this->buildIndirect();
		}
		return $this->buildDirect();
	}


	/**
	 * Direct method: aggregate cash receipts and payments by category
	 * using the c_accounting_category for fk_report=4.
	 * Multi-currency: cash flows translated at closing rate (IAS 21 / SFRS 21).
	 *
	 * @return array Lines
	 */
	private function buildDirect()
	{
		$categories = $this->sfrs->fetchCategories(SfrsReport::getReportRowid('CF'));

		// Determine if we need currency translation
		$needs_translation = !empty($this->mc_code) && $this->mc_code !== 'FUNC' && $this->mc_code !== 'ALL';
		$closing_rate = $needs_translation ? $this->getClosingRate() : 1.0;

		$values_by_code = array();
		foreach ($categories as $cat) {
			if ((int) $cat['category_type'] === 1) {
				continue;
			}
			if (empty($cat['range_account'])) {
				continue;
			}
			$vals = $this->computeRange($cat['range_account']);
			if ((int) $cat['sens'] === 0) {
				$amount = $vals['credit'] - $vals['debit'];
			} else {
				$amount = $vals['debit'] - $vals['credit'];
			}
			$values_by_code[$cat['code']] = (float) $amount;
		}

		// Formulas (CF_NET_OP / CF_NET_INV / CF_NET_FIN / CF_NET_CHANGE)
		for ($pass = 0; $pass < 8; $pass++) {
			$progress = false;
			foreach ($categories as $cat) {
				if ((int) $cat['category_type'] !== 1) {
					continue;
				}
				if (isset($values_by_code[$cat['code']])) {
					continue;
				}
				$val = SfrsReport::evaluateFormula($cat['formula'], $values_by_code);
				if ($val !== null) {
					$values_by_code[$cat['code']] = (float) $val;
					$progress = true;
				}
			}
			if (!$progress) {
				break;
			}
		}

		// Cash at beginning / end (1800-1899 = cash & bank, balance)
		// Cash balances are translated at closing rate
		$cash_open = $this->cashAt($this->date_start - 86400);
		$cash_close = $this->cashAt($this->date_end);

		// Apply closing rate to cash balances
		if ($needs_translation) {
			$cash_open = $cash_open * $closing_rate;
			$cash_close = $cash_close * $closing_rate;
		}

		$values_by_code['CF_OPENING'] = $cash_open;
		$values_by_code['CF_CLOSING'] = $cash_close;

		$lines = array();
		foreach ($categories as $cat) {
			$amount = isset($values_by_code[$cat['code']]) ? (float) $values_by_code[$cat['code']] : 0.0;

			// Apply closing rate to non-formula cash flow items
			if ($needs_translation && (int) $cat['category_type'] !== 1) {
				$amount = $amount * $closing_rate;
			}

			$kind = 'detail';
			if ((int) $cat['category_type'] === 2) {
				$kind = 'header';
			} elseif ((int) $cat['category_type'] === 1) {
				$kind = 'subtotal';
				if (in_array($cat['code'], array('CF_NET_OP', 'CF_NET_INV', 'CF_NET_FIN', 'CF_NET_CHANGE'))) {
					$kind = 'total';
				}
			}

			$lines[] = array(
				'code' => $cat['code'],
				'label' => $cat['label'],
				'amount' => $amount,
				'kind' => $kind,
				'position' => $cat['position'],
				'rate' => $closing_rate,
			);
		}
		return $lines;
	}


	/**
	 * Indirect method: Net Profit + non-cash items + working capital changes.
	 *
	 * All account ranges are read from c_accounting_category rows
	 * (fk_report=4, codes IND_* — see llx_sfrs_c_accounting_category.sql)
	 * so companies with non-standard charts can tweak the report without
	 * touching PHP.
	 *
	 * @return array Lines
	 */
	private function buildIndirect()
	{
		$pl = new SfrsProfitLossBuilder($this->sfrs, $this->date_start, $this->date_end, $this->mc_code);
		$pl_lines = $pl->build();

		$net_profit = 0.0;
		$finance_costs = 0.0;     // not cash operating — add back
		$finance_income = 0.0;    // not cash operating — subtract
		$tax_expense = 0.0;       // paid separately

		foreach ($pl_lines as $line) {
			if ($line['code'] === 'PL_NET') {
				$net_profit = (float) $line['amount'];
			}
			if ($line['code'] === 'FIN_COSTS') {
				$finance_costs = (float) $line['amount'];
			}
			if ($line['code'] === 'FIN_INCOME') {
				$finance_income = (float) $line['amount'];
			}
			if ($line['code'] === 'PL_TAX') {
				$tax_expense = (float) $line['amount'];
			}
		}

		// Load IND_* ranges from c_accounting_category. If any is missing, we
		// leave the corresponding line at zero rather than crashing — the
		// admin sees a row with the right label and "0" amount, which is more
		// informative than a fatal error.
		$ind = $this->loadIndirectRanges();

		// Depreciation & amortisation add-back (sens=1 → expense side).
		$depreciation_addback = isset($ind['IND_DA'])
			? $this->computeRangeSigned($ind['IND_DA']['range_account'], $ind['IND_DA']['sens'])
			: 0.0;

		// Working capital changes — each line already cash-impact signed.
		$delta_trade_recv = isset($ind['IND_WC_AR'])   ? $this->deltaRangeSigned($ind['IND_WC_AR']['range_account'])   : 0.0;
		$delta_inventory  = isset($ind['IND_WC_INV'])  ? $this->deltaRangeSigned($ind['IND_WC_INV']['range_account'])  : 0.0;
		$delta_prepay     = isset($ind['IND_WC_PREP']) ? $this->deltaRangeSigned($ind['IND_WC_PREP']['range_account']) : 0.0;
		$delta_trade_pay  = isset($ind['IND_WC_AP'])   ? $this->deltaRangeSigned($ind['IND_WC_AP']['range_account'])   : 0.0;
		$delta_accruals   = isset($ind['IND_WC_ACCR']) ? $this->deltaRangeSigned($ind['IND_WC_ACCR']['range_account']) : 0.0;
		$delta_other_cl   = isset($ind['IND_WC_OCL'])  ? $this->deltaRangeSigned($ind['IND_WC_OCL']['range_account'])  : 0.0;

		$working_capital_adj = $delta_trade_recv + $delta_inventory + $delta_prepay
			+ $delta_trade_pay + $delta_accruals + $delta_other_cl;

		// Cash from operating = NP + add-backs + WC changes (sign-corrected).
		// Subtract tax paid; add back finance costs in NP; subtract finance income.
		$cash_from_operating = $net_profit
			+ $depreciation_addback
			+ $working_capital_adj
			- $tax_expense
			+ $finance_costs
			- $finance_income;

		// Investing: delta of non-current assets (sens=1 → asset, negative on growth).
		$delta_ppe = isset($ind['IND_INV_PPE'])
			? $this->deltaRangeSigned($ind['IND_INV_PPE']['range_account'])
			: 0.0;
		$cash_investing = $delta_ppe;

		// Financing: movements in long-term loans, leases, equity, dividends, retained earnings.
		$delta_loans    = isset($ind['IND_FIN_LOAN'])  ? $this->deltaRangeSigned($ind['IND_FIN_LOAN']['range_account'])  : 0.0;
		$delta_lease_nc = isset($ind['IND_FIN_LEASE']) ? $this->deltaRangeSigned($ind['IND_FIN_LEASE']['range_account']) : 0.0;
		$delta_share_cap = isset($ind['IND_FIN_EQ'])   ? $this->deltaRangeSigned($ind['IND_FIN_EQ']['range_account'])   : 0.0;
		$delta_dividends = isset($ind['IND_FIN_DIV'])  ? $this->deltaRangeSigned($ind['IND_FIN_DIV']['range_account'])  : 0.0;

		// Retained earnings is now data-driven (IND_FIN_RET) so a company
		// with a custom retained-earnings sub-account can re-route it via SQL.
		$delta_retained = isset($ind['IND_FIN_RET'])
			? $this->deltaRangeSigned($ind['IND_FIN_RET']['range_account'])
			: 0.0;

		$cash_financing = $delta_loans + $delta_lease_nc + $delta_share_cap
			+ $delta_retained + $delta_dividends
			- $tax_expense;

		// Net change
		$net_change = $cash_from_operating + $cash_investing + $cash_financing;
		$cash_open = $this->cashAt($this->date_start - 86400);
		$cash_close = $cash_open + $net_change;

		// Build lines (custom layout for indirect method)
		$lines = array(
			['code' => 'CF_HEADER_OP', 'label' => '═══ OPERATING ACTIVITIES (indirect method) ═══', 'amount' => 0, 'kind' => 'header', 'position' => 100],
			['code' => 'IND_NP',        'label' => 'Net profit / (loss) for the period',         'amount' => $net_profit,         'kind' => 'detail', 'position' => 110],
			['code' => 'IND_DA',        'label' => 'Add: Depreciation and amortisation',        'amount' => $depreciation_addback, 'kind' => 'detail', 'position' => 120],
			['code' => 'IND_FIN_COSTS', 'label' => 'Add: Finance costs (interest expense)',     'amount' => $finance_costs,     'kind' => 'detail', 'position' => 130],
			['code' => 'IND_FIN_INC',   'label' => 'Less: Finance income (interest income)',    'amount' => -$finance_income,   'kind' => 'detail', 'position' => 140],
			['code' => 'IND_WC_AR',     'label' => 'Change in trade receivables',               'amount' => $delta_trade_recv,  'kind' => 'detail', 'position' => 150],
			['code' => 'IND_WC_INV',    'label' => 'Change in inventories',                     'amount' => $delta_inventory,   'kind' => 'detail', 'position' => 160],
			['code' => 'IND_WC_PREP',   'label' => 'Change in prepayments',                     'amount' => $delta_prepay,      'kind' => 'detail', 'position' => 170],
			['code' => 'IND_WC_AP',     'label' => 'Change in trade payables',                  'amount' => $delta_trade_pay,   'kind' => 'detail', 'position' => 180],
			['code' => 'IND_WC_ACCR',   'label' => 'Change in accruals',                        'amount' => $delta_accruals,    'kind' => 'detail', 'position' => 190],
			['code' => 'IND_WC_OCL',    'label' => 'Change in other current liabilities',        'amount' => $delta_other_cl,    'kind' => 'detail', 'position' => 200],
			['code' => 'IND_WC_TOT',    'label' => 'Working capital changes (net)',             'amount' => $working_capital_adj, 'kind' => 'subtotal', 'position' => 210],
			['code' => 'IND_TAX_PAID',  'label' => 'Less: Income tax paid (estimate)',          'amount' => -$tax_expense,      'kind' => 'detail', 'position' => 220],
			['code' => 'CF_NET_OP',     'label' => 'NET CASH FROM OPERATING',                   'amount' => $cash_from_operating, 'kind' => 'total', 'position' => 299],

			['code' => 'CF_HEADER_INV', 'label' => '═══ INVESTING ACTIVITIES ═══', 'amount' => 0, 'kind' => 'header', 'position' => 300],
			['code' => 'IND_INV_PPE',   'label' => 'Purchase / (disposal) of non-current assets', 'amount' => $delta_ppe, 'kind' => 'detail', 'position' => 310],
			['code' => 'CF_NET_INV',    'label' => 'NET CASH FROM INVESTING', 'amount' => $cash_investing, 'kind' => 'total', 'position' => 399],

			['code' => 'CF_HEADER_FIN', 'label' => '═══ FINANCING ACTIVITIES ═══', 'amount' => 0, 'kind' => 'header', 'position' => 400],
			['code' => 'IND_FIN_LOAN',  'label' => 'Change in long-term loans',       'amount' => $delta_loans,    'kind' => 'detail', 'position' => 410],
			['code' => 'IND_FIN_LEASE', 'label' => 'Change in lease liabilities (net of repayments)', 'amount' => $delta_lease_nc, 'kind' => 'detail', 'position' => 420],
			['code' => 'IND_FIN_EQ',    'label' => 'Proceeds from issue of share capital',  'amount' => $delta_share_cap, 'kind' => 'detail', 'position' => 430],
			['code' => 'IND_FIN_DIV',   'label' => 'Dividends paid (movement)',         'amount' => $delta_dividends, 'kind' => 'detail', 'position' => 440],
			['code' => 'CF_NET_FIN',    'label' => 'NET CASH FROM FINANCING', 'amount' => $cash_financing, 'kind' => 'total', 'position' => 499],

			['code' => 'CF_NET_CHANGE', 'label' => 'NET INCREASE/(DECREASE) IN CASH', 'amount' => $net_change, 'kind' => 'total', 'position' => 599],
			['code' => 'CF_OPENING',    'label' => 'Cash at beginning of period',  'amount' => $cash_open,  'kind' => 'detail', 'position' => 610],
			['code' => 'CF_CLOSING',    'label' => 'Cash at end of period',        'amount' => $cash_close, 'kind' => 'total', 'position' => 620],
		);

		return $lines;
	}


	/**
	 * Load the IND_* category rows (fk_report=4) and index them by code.
	 * Caches the result per call so we hit the DB once.
	 *
	 * @return array<string,array{range_account:string,sens:int}> code => category
	 */
	private function loadIndirectRanges()
	{
		static $cache = null;
		if ($cache !== null) {
			return $cache;
		}
		$cache = array();
		$cats = $this->sfrs->fetchCategories(SfrsReport::getReportRowid('CF'));
		foreach ($cats as $c) {
			if (strpos($c['code'], 'IND_') === 0) {
				$cache[$c['code']] = array(
					'range_account' => $c['range_account'],
					'sens' => (int) $c['sens'],
				);
			}
		}
		return $cache;
	}


	/**
	 * Aggregate a single account range in the period
	 *
	 * @param string $range "START-END" or single
	 * @return array{debit:float,credit:float}
	 */
	private function computeRange($range)
	{
		if (strpos($range, '-') !== false) {
			list($start, $end) = array_map('trim', explode('-', $range, 2));
		} else {
			$start = $end = $range;
		}
		return $this->sfrs->sumByRangeInPeriod($start, $end, $this->date_start, $this->date_end, $this->mc_code);
	}


	/**
	 * Like computeRange() but applies the SFRS 'sens' sign convention so the
	 * returned amount is already in the natural direction for the category.
	 *   sens=0 (credit-debit, e.g. liability/equity/income) → amount = credit - debit
	 *   sens=1 (debit-credit, e.g. asset/expense)          → amount = debit - credit
	 *
	 * Used by the indirect CF method so its formula stays free of arithmetic.
	 *
	 * @param string $range "START-END"
	 * @param int    $sens  0 or 1 (see above)
	 * @return float Signed amount in the period
	 */
	private function computeRangeSigned($range, $sens)
	{
		$row = $this->computeRange($range);
		$amount = ((int) $sens === 0)
			? ($row['credit'] - $row['debit'])
			: ($row['debit'] - $row['credit']);
		return (float) $amount;
	}


	/**
	 * Net change in an account range over the period.
	 * (balance_end - balance_open) where balance = debit - credit.
	 * Positive = increase in a debit-balance account (asset/expense), or
	 * decrease in a credit-balance account (liability/equity/revenue).
	 *
	 * @param string $range "START-END"
	 * @return float Delta
	 */
	private function deltaRange($range)
	{
		if (strpos($range, '-') !== false) {
			list($start, $end) = array_map('trim', explode('-', $range, 2));
		} else {
			$start = $end = $range;
		}
		// Balance at end of period (inclusive of period end)
		$end_row = $this->sfrs->sumByRange($start, $end, $this->date_end, $this->mc_code);
		// Balance at start (one day before period start)
		$open_row = $this->sfrs->sumByRange($start, $end, $this->date_start - 86400, $this->mc_code);
		return (float) ($end_row['balance'] - $open_row['balance']);
	}


	/**
	 * Convert a raw balance delta into the cash impact that the underlying
	 * working-capital movement has under FRS 7 indirect method.
	 *
	 * Raw delta = balance_end - balance_open, where balance = debit - credit.
	 * Working out the cash sign for asset vs liability movements:
	 *
	 *   Asset increase    → raw delta = +ve → cash out → -ve
	 *   Liability increase→ raw delta = -ve → cash in  → +ve
	 *
	 * Both cases yield  cash_impact = -raw_delta  — sens-independent.
	 *
	 * @param string $range "START-END"
	 * @return float Signed delta in cash-impact direction
	 */
	private function deltaRangeSigned($range)
	{
		$delta = $this->deltaRange($range);
		return -$delta;
	}


	/**
	 * Cash at a given date (sum of 1800-1899 range balance).
	 *
	 * @param int $date_limit Unix timestamp
	 * @return float Net cash balance
	 */
	private function cashAt($date_limit)
	{
		$row = $this->sfrs->sumByRange('1800', '1899', $date_limit, $this->mc_code);
		return (float) $row['balance'];
	}


	/**
	 * Get the closing exchange rate for the current currency and period end date.
	 * Caches the result for reuse.
	 *
	 * @return float Exchange rate
	 */
	public function getClosingRate()
	{
		// Return 1.0 if no translation needed
		if (empty($this->mc_code) || $this->mc_code === 'FUNC' || $this->mc_code === 'ALL') {
			return 1.0;
		}

		if ($this->cached_rate !== null) {
			return $this->cached_rate;
		}

		$this->cached_rate = $this->sfrs->getRateForDate(
			$this->mc_code,
			$this->date_end,
			'closing'
		);

		return $this->cached_rate;
	}
}