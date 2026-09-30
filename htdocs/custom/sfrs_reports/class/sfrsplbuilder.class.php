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
 * \file   htdocs/custom/sfrs_reports/class/sfrsplbuilder.class.php
 * \brief  Build the SFRS Profit & Loss Statement from bookkeeping entries (period filter)
 */

require_once __DIR__.'/sfrsreport.class.php';


/**
 * Class SfrsProfitLossBuilder
 *
 * Aggregates bookkeeping entries within a period (date_start..date_end) and maps
 * the result to SFRS P&L line items.
 *
 * Multi-currency support (IAS 21 / SFRS 21):
 * - Income and expenses are translated at the average rate for the period.
 * - When $mc_code is a foreign currency, amounts are translated at the
 *   average rate before aggregation.
 */
class SfrsProfitLossBuilder
{
	/** @var SfrsReport */
	private $sfrs;

	/** @var int Unix timestamp — period start (inclusive) */
	private $date_start;

	/** @var int Unix timestamp — period end (inclusive) */
	private $date_end;

	/** @var string Currency filter — 'FUNC' (default), 'ALL', or ISO code */
	private $mc_code;

	/** @var string Exchange rate method: 'average' | 'closing' | 'historical' | 'fixed' */
	private $rate_method;

	/** @var float|null Cached exchange rate */
	private $cached_rate = null;


	/**
	 * Constructor
	 *
	 * @param SfrsReport $sfrs       SfrsReport instance
	 * @param int        $date_start Period start (Unix timestamp)
	 * @param int        $date_end   Period end (Unix timestamp)
	 * @param string     $mc_code    'FUNC' (default) | 'ALL' | 'USD' / 'SGD' / etc.
	 */
	public function __construct($sfrs, $date_start, $date_end, $mc_code = 'FUNC')
	{
		$this->sfrs = $sfrs;
		$this->date_start = $date_start;
		$this->date_end = $date_end;
		$this->mc_code = $mc_code;
		$this->rate_method = getDolGlobalString('SFRS_MC_PL_RATE_METHOD', 'average');
	}


	/**
	 * Build P&L lines in display order.
	 * Multi-currency: amounts are translated at average rate (IAS 21).
	 *
	 * @return array<int,array{code:string,label:string,amount:float,kind:string,position:int,rate:float|null}>
	 */
	public function build()
	{
		$categories = $this->sfrs->fetchCategories(SfrsReport::getReportRowid('PL'));

		// Determine if we need currency translation
		$needs_translation = !empty($this->mc_code) && $this->mc_code !== 'FUNC' && $this->mc_code !== 'ALL';
		$applied_rate = null;

		// First pass — detail rows
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

			// Apply average exchange rate translation for P&L (IAS 21)
			if ($needs_translation) {
				$amount = $amount * $this->getAverageRate();
			}

			$values_by_code[$cat['code']] = (float) $amount;
		}

		// Second pass — formula categories
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

		// Get applied rate for display
		if ($needs_translation) {
			$applied_rate = $this->getAverageRate();
		}

		$lines = array();
		foreach ($categories as $cat) {
			$amount = isset($values_by_code[$cat['code']]) ? (float) $values_by_code[$cat['code']] : 0.0;

			$kind = 'detail';
			if ((int) $cat['category_type'] === 2) {
				$kind = 'header';
			} elseif ((int) $cat['category_type'] === 1) {
				$kind = 'subtotal';
				if (in_array($cat['code'], array('PL_GROSS', 'PL_OP_PROFIT', 'PL_PRETAX', 'PL_NET'))) {
					$kind = 'total';
				}
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
	 * Get the average exchange rate for the current currency and period.
	 * Caches the result for reuse across all computeRange() calls.
	 *
	 * @return float Exchange rate
	 */
	public function getAverageRate()
	{
		// Return 1.0 if no translation needed
		if (empty($this->mc_code) || $this->mc_code === 'FUNC' || $this->mc_code === 'ALL') {
			return 1.0;
		}

		if ($this->cached_rate !== null) {
			return $this->cached_rate;
		}

		$this->cached_rate = $this->sfrs->getAverageRateInPeriod(
			$this->mc_code,
			$this->date_start,
			$this->date_end,
			$this->rate_method
		);

		return $this->cached_rate;
	}


	/**
	 * Aggregate over a comma-separated list of account ranges in the period.
	 *
	 * @param string $range_spec e.g. "4000-4099" or "6200-6299"
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
			$row = $this->sfrs->sumByRangeInPeriod($start, $end, $this->date_start, $this->date_end, $this->mc_code);
			$total_debit += $row['debit'];
			$total_credit += $row['credit'];
		}
		return array(
			'debit' => $total_debit,
			'credit' => $total_credit,
			'balance' => $total_debit - $total_credit,
		);
	}
}