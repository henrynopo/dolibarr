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
 * \file   htdocs/custom/sfrs_reports/class/sfrsbalancebuilder.class.php
 * \brief  Build the SFRS Balance Sheet from bookkeeping entries
 */

require_once __DIR__.'/sfrsreport.class.php';


/**
 * Class SfrsBalanceBuilder
 *
 * Returns an array of report lines:
 *   [
 *     ['code' => 'NCA_PPE', 'label' => '...', 'amount' => 1234.56, 'kind' => 'detail|formula|header|subtotal|total'],
 *     ...
 *   ]
 *
 * The renderer (TPL or PDF) consumes this directly.
 *
 * Multi-currency support (IAS 21 / SFRS 21):
 * - When $mc_code is a specific currency (not 'FUNC' or 'ALL'), amounts
 *   are translated using the closing rate as of the balance sheet date.
 * - Translation is applied at the computeRange() level so formulas and
 *   sub-totals automatically use translated amounts.
 */
class SfrsBalanceBuilder
{
	/** @var SfrsReport */
	private $sfrs;

	/** @var int Unix timestamp — balance sheet as-of date (inclusive) */
	private $date_limit;

	/** @var string Currency filter — 'FUNC' (default), 'ALL', or ISO code (e.g. 'USD') */
	private $mc_code;

	/** @var string Exchange rate method: 'closing' | 'average' | 'historical' | 'fixed' */
	private $rate_method;

	/** @var float|null Cached exchange rate for the current BS date */
	private $cached_rate = null;

	/** @var string|null Currency code the cached rate was fetched for */
	private $cached_rate_code = null;


	/**
	 * Constructor
	 *
	 * @param SfrsReport $sfrs       SfrsReport instance
	 * @param int        $date_limit  Unix timestamp — balance sheet date
	 * @param string     $mc_code     'FUNC' (default) | 'ALL' | 'USD' / 'SGD' / etc.
	 */
	public function __construct($sfrs, $date_limit, $mc_code = 'FUNC')
	{
		$this->sfrs = $sfrs;
		$this->date_limit = $date_limit;
		$this->mc_code = $mc_code;
		$this->rate_method = getDolGlobalString('SFRS_MC_BS_RATE_METHOD', 'closing');
	}


	/**
	 * Build the BS lines in display order.
	 * Multi-currency: when $mc_code is a foreign currency, amounts are
	 * translated at the closing rate before aggregation (IAS 21 / SFRS 21).
	 *
	 * @return array<int,array{code:string,label:string,amount:float,kind:string,position:int,rate:float|null}>
	 */
	public function build()
	{
		// Load all BS categories (fk_report=2)
		$categories = $this->sfrs->fetchCategories(SfrsReport::getReportRowid('BS'));

		// Determine if we need currency translation
		$needs_translation = !empty($this->mc_code) && $this->mc_code !== 'FUNC' && $this->mc_code !== 'ALL';
		$applied_rate = null;

		// First pass: compute the value of every detail (non-formula) category
		$values_by_code = array();
		foreach ($categories as $cat) {
			if ((int) $cat['category_type'] === 1) {
				// formula — computed in second pass
				continue;
			}
			if (empty($cat['range_account'])) {
				// placeholder / header with no range
				continue;
			}
			$vals = $this->computeRange($cat['range_account']);
			// sens:
			//   0 = credit - debit (revenue, liability, equity — natural credit balance)
			//   1 = debit - credit (asset, expense — natural debit balance)
			if ((int) $cat['sens'] === 0) {
				$amount = $vals['credit'] - $vals['debit'];
			} else {
				$amount = $vals['debit'] - $vals['credit'];
			}

			// Apply exchange rate translation for multi-currency
			// IAS 21: BS items translated at closing rate
			if ($needs_translation) {
				$rate = $this->getExchangeRate();
				$amount = $amount * $rate;
			}

			$values_by_code[$cat['code']] = (float) $amount;
		}

		// Second pass: compute formula categories
		// Iterative resolution to handle nested references (e.g. NCA_TOTAL -> NCA_PPE etc.)
		$max_passes = 8;
		for ($pass = 0; $pass < $max_passes; $pass++) {
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

		// Get applied rate for display in header
		if ($needs_translation) {
			$applied_rate = $this->getExchangeRate();
		}

		// Build the final display list
		$lines = array();
		foreach ($categories as $cat) {
			$amount = isset($values_by_code[$cat['code']]) ? (float) $values_by_code[$cat['code']] : 0.0;

			$kind = 'detail';
			if ((int) $cat['category_type'] === 2) {
				$kind = 'header';
			} elseif ((int) $cat['category_type'] === 1) {
				$kind = 'subtotal';
				if (strpos($cat['code'], 'BS_TOTAL') === 0 || strpos($cat['code'], 'BS_GRAND') === 0) {
					$kind = 'total';
				}
			} elseif (strpos($cat['code'], '_TOTAL') !== false) {
				$kind = 'subtotal';
			}

			$lines[] = array(
				'code' => $cat['code'],
				'label' => $cat['label'],
				'amount' => $amount,
				'kind' => $kind,
				'position' => $cat['position'],
				'rate' => $applied_rate,
			);
		}

		return $lines;
	}


	/**
	 * Get the exchange rate for the current foreign currency and BS date.
	 * Caches the result for reuse across all computeRange() calls.
	 *
	 * @return float Exchange rate (1 foreign = X functional)
	 */
	public function getExchangeRate()
	{
		// Return 1.0 if no translation needed
		if (empty($this->mc_code) || $this->mc_code === 'FUNC' || $this->mc_code === 'ALL') {
			return 1.0;
		}

		// Check cache: return cached rate if same currency
		if ($this->cached_rate !== null && $this->cached_rate_code === $this->mc_code) {
			return $this->cached_rate;
		}

		// Fetch and cache the rate
		$this->cached_rate = $this->sfrs->getRateForDate($this->mc_code, $this->date_limit, $this->rate_method);
		$this->cached_rate_code = $this->mc_code;

		return $this->cached_rate;
	}


	/**
	 * Aggregate debit/credit over a comma-separated list of account ranges.
	 * Each range is "START-END" (inclusive on both sides) or a single account.
	 *
	 * @param string $range_spec e.g. "1000-1099" or "1600,1700-1799"
	 * @return array{debit:float,credit:float,balance:float}
	 */
	private function computeRange($range_spec)
	{
		$total_debit = 0.0;
		$total_credit = 0.0;

		$parts = array_map('trim', explode(',', $range_spec));
		foreach ($parts as $part) {
			if ($part === '') {
				continue;
			}
			if (strpos($part, '-') !== false) {
				list($start, $end) = array_map('trim', explode('-', $part, 2));
			} else {
				$start = $end = $part;
			}
			$row = $this->sfrs->sumByRange($start, $end, $this->date_limit, $this->mc_code);
			$total_debit += $row['debit'];
			$total_credit += $row['credit'];
		}
		return array(
			'debit' => $total_debit,
			'credit' => $total_credit,
			'balance' => $total_debit - $total_credit,
		);
	}


	/**
	 * Verify the BS balances: Total Assets == Total Equity + Liabilities.
	 *
	 * @param array $lines Output of build()
	 * @return array{balanced:bool, total_assets:float, total_equity_liab:float, diff:float}
	 */
	public function checkBalance($lines)
	{
		$assets = 0.0;
		$eq_liab = 0.0;
		foreach ($lines as $line) {
			if ($line['code'] === 'BS_TOTAL_ASSETS') {
				$assets = (float) $line['amount'];
			}
			if ($line['code'] === 'BS_GRAND_TOTAL') {
				$eq_liab = (float) $line['amount'];
			}
		}
		return array(
			'balanced' => abs($assets - $eq_liab) < 0.01,
			'total_assets' => $assets,
			'total_equity_liab' => $eq_liab,
			'diff' => $assets - $eq_liab,
		);
	}
}