<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/EBKEntryDraft.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Plain value object carrying an N-row balanced draft entry from
 *	             the bookkeeping tab (or the AI prefill) to
 *	             EBKBookkeepingWriter::writeEntry().
 *
 *	             Sign convention (identical to core journals): a row's amount
 *	             is stored SIGNED — negative = debit, positive = credit — so a
 *	             credit note (negative invoice totals) mirrors automatically.
 *	             The UI posts one POSITIVE amount per row plus its side ('D'|'C');
 *	             fromPost() converts. Nothing is persisted here.
 */

if (!class_exists('EBKEntryDraft', false)) {

class EBKEntryDraft
{
	/** @var string 'customer_invoice' | 'supplier_invoice' | 'expense_report' */
	public $doc_type = '';

	/** @var int Rowid of the source facture / facture_fourn / expensereport */
	public $fk_doc = 0;

	/** @var string Source document ref */
	public $doc_ref = '';

	/** @var string Third-party accounting code */
	public $thirdparty_code = '';

	/** @var string Entry-level label_operation */
	public $label_operation = '';

	/** @var string Journal code (writer resolves the default per doc_type when empty) */
	public $code_journal = '';

	/** @var string Journal label */
	public $journal_label = '';

	/** @var int Unix timestamp; 0 when not set */
	public $date_doc = 0;

	/** @var int Unix timestamp; 0 when not set */
	public $date_lim_reglement = 0;

	/** @var string ISO-4217 currency code (empty = entity base currency) */
	public $currency = '';

	/** @var string 'manual' | 'ai:<provider>' — written into extraparams */
	public $source = 'manual';

	/** @var float|null Confidence 0..1 (AI prefill only) */
	public $confidence = null;

	/** @var string|null Free-text rationale (AI prefill only) */
	public $rationale = null;

	/**
	 * @var array<int,array<string,mixed>> Ordered entry rows. Each row:
	 *   numero_compte   string  account number
	 *   label_compte    string  account label
	 *   amount          float   SIGNED amount (negative = debit, positive = credit)
	 *   side            string  'D' | 'C' (display hint kept in sync with the sign)
	 *   label_operation string  per-row label (must stay unique per account)
	 *   fk_docdet       int     source line rowid (0 for counter/tax rows)
	 *   subledger_account string
	 *   subledger_label   string
	 */
	public $rows = array();

	/**
	 * Append one row. The side ('D' or 'C') and a positive amount are the
	 * natural UI inputs; the signed amount is derived.
	 *
	 * @param  string $numeroCompte   Account number
	 * @param  string $labelCompte    Account label
	 * @param  float  $amountPositive Positive amount (>0)
	 * @param  string $side           'D' | 'C'
	 * @param  string $labelOperation Per-row unique label
	 * @param  int    $fkDocdet       Source line rowid or 0
	 * @param  string $subledgerAccount
	 * @param  string $subledgerLabel
	 * @return void
	 */
	public function addRow($numeroCompte, $labelCompte, $amountPositive, $side, $labelOperation, $fkDocdet = 0, $subledgerAccount = '', $subledgerLabel = '')
	{
		$mt = abs((float) $amountPositive);
		if ($side === 'D') {
			$mt = -$mt;
		}
		$this->rows[] = array(
			'numero_compte'   => (string) $numeroCompte,
			'label_compte'    => (string) $labelCompte,
			'amount'          => $mt,
			'side'            => ($side === 'D') ? 'D' : 'C',
			'label_operation' => (string) $labelOperation,
			'fk_docdet'       => (int) $fkDocdet,
			'subledger_account' => (string) $subledgerAccount,
			'subledger_label' => (string) $subledgerLabel,
		);
	}

	/**
	 * Validate the draft before persistence. All amounts are MT-precision
	 * stored values, so the balance must be exact at 2 decimals — the 0.005
	 * tolerance only guards float representation noise, mirroring core's
	 * price2num(x,'MT') equality test in the journals.
	 *
	 * @return string Empty on success, otherwise a lang key.
	 */
	public function validate()
	{
		if ($this->doc_type !== 'customer_invoice'
			&& $this->doc_type !== 'supplier_invoice'
			&& $this->doc_type !== 'expense_report') {
			return 'ErrorBadDocTypeForBookkeeping';
		}
		if ($this->fk_doc <= 0) {
			return 'ErrorBadFkDocForBookkeeping';
		}
		if (count($this->rows) < 2) {
			return 'EBKNotEnoughRowsForBookkeeping';
		}
		// Security fix (audit 2026-09-30): cap rows per entry so a caller with
		// bookkeeping write rights cannot post unbounded lines in one call.
		if (count($this->rows) > 100) {
			return 'EBKTooManyRowsForBookkeeping';
		}

		$sum = 0.0;
		$hasDebit = false;
		$hasCredit = false;
		$seen = array();
		foreach ($this->rows as $row) {
			if (empty($row['numero_compte'])) {
				return 'ErrorBadAccountForBookkeeping';
			}
			if (abs((float) $row['amount']) < 0.005) {
				return 'ErrorBadAmountForBookkeeping';
			}
			$key = $row['numero_compte'].'|'.$row['label_operation'];
			if (isset($seen[$key])) {
				return 'EBKDuplicateRowForBookkeeping';
			}
			$seen[$key] = true;
			$sum += (float) $row['amount'];
			if ($row['amount'] < 0) {
				$hasDebit = true;
			} else {
				$hasCredit = true;
			}
		}
		if (!$hasDebit || !$hasCredit) {
			return 'EBKNotEnoughRowsForBookkeeping';
		}
		if (abs(price2num($sum, 'MT')) >= 0.005) {
			return 'EBKEntryNotBalanced';
		}
		return '';
	}

	/**
	 * Rebuild a draft from the tab form POST. Arrays are read in parallel;
	 * any length mismatch or bad index is dropped (defensive — a legit form
	 * always posts aligned arrays).
	 *
	 * Expected POST shape (all arrays, same order as the rendered rows):
	 *   ebk_row_side[]     'D' | 'C'
	 *   ebk_row_account[]  account number
	 *   ebk_row_amount[]   positive amount
	 *   ebk_row_label[]    per-row label_operation
	 *   ebk_row_docdet[]   int
	 *   ebk_row_subledger[]  subledger account or ''
	 *   ebk_row_subledgerlabel[]  subledger label or ''
	 * plus scalars: ebk_doc_type, ebk_fk_doc, ebk_doc_ref, ebk_label_operation,
	 *               ebk_currency
	 *
	 * @param  string $docType
	 * @param  int    $fkDoc
	 * @param  string $docRef
	 * @return EBKEntryDraft
	 */
	public static function fromPost($docType, $fkDoc, $docRef)
	{
		$sides    = GETPOST('ebk_row_side', 'array:aZ09');
		$accounts = GETPOST('ebk_row_account', 'array:alphanohtml');
		$amounts  = GETPOST('ebk_row_amount', 'array:alphanohtml');
		$labels   = GETPOST('ebk_row_label', 'array:alphanohtml');
		$docdets  = GETPOST('ebk_row_docdet', 'array:int');
		$subledgers = GETPOST('ebk_row_subledger', 'array:alphanohtml');
		$subledgerLabels = GETPOST('ebk_row_subledgerlabel', 'array:alphanohtml');

		$draft = new self();
		$draft->doc_type        = $docType;
		$draft->fk_doc          = (int) $fkDoc;
		$draft->doc_ref         = (string) $docRef;
		$draft->label_operation = GETPOST('ebk_label_operation', 'alphanohtml');
		$draft->currency        = GETPOST('ebk_currency', 'aZ09');

		// Optional accounting-date override (HTML date input posts YYYY-MM-DD).
		// Invalid/absent input leaves date_doc = 0 → the writer falls back to the
		// source document date.
		$dateStr = GETPOST('ebk_doc_date', 'alphanohtml');
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateStr, $m)) {
			$ts = dol_mktime(12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]); // noon avoids DST edges
			if ($ts > 0) {
				$draft->date_doc = $ts;
			}
		}

		if (is_array($accounts)) {
			foreach ($accounts as $i => $account) {
				$side = (isset($sides[$i]) && $sides[$i] === 'D') ? 'D' : 'C';
				$amtRaw = isset($amounts[$i]) ? str_replace(',', '.', trim((string) $amounts[$i])) : '';
				// Accept plain decimal numbers only; anything else is a hard 0 and
				// will fail validate() with ErrorBadAmountForBookkeeping.
				$amt = is_numeric($amtRaw) ? (float) $amtRaw : 0.0;
				$draft->addRow(
					(string) $account,
					'', // label resolved server-side by the caller via EBKAccountLookup
					$amt,
					$side,
					isset($labels[$i]) ? (string) $labels[$i] : '',
					isset($docdets[$i]) ? (int) $docdets[$i] : 0,
					isset($subledgers[$i]) ? (string) $subledgers[$i] : '',
					isset($subledgerLabels[$i]) ? (string) $subledgerLabels[$i] : ''
				);
			}
		}

		return $draft;
	}
}

} // if (!class_exists('EBKEntryDraft', false))
