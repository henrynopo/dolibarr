<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/EBKEntryProposal.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Plain value object carrying one debit/credit proposal from the UI
 *	             or the AI suggester to EBKBookkeepingWriter::writePair().
 *
 *             Fields map 1:1 to llx_accounting_bookkeeping columns; nothing is
 *             computed here — the writer is the single owner of piece_num and
 *             sens/debit/credit symmetry.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Value object. Not a CommonObject — we do not persist proposals, only the
 * resulting bookkeeping rows. The object is constructed in:
 *   - class/api_embeddedbookkeeping.class.php (REST API create endpoint)
 *   - AiModuleProvider::suggest() (AI path, after JSON decode)
 * The 1.0 card-modal path was removed in 1.1.0; the bookkeeping tab uses
 * EBKEntryDraft (N rows) + EBKBookkeepingWriter::writeEntry() instead.
 */
class EBKEntryProposal
{
	/** @var string 'customer_invoice' | 'supplier_invoice' — matches llx_accounting_bookkeeping.doc_type */
	public $doc_type = '';

	/** @var int Rowid of the source facture or facture_fourn */
	public $fk_doc = 0;

	/** @var string Source document ref (facture.ref or facture_fourn.ref) */
	public $doc_ref = '';

	/** @var string Third-party accounting code (e.g. 'CU-A001') */
	public $thirdparty_code = '';

	/** @var string Subledger account (third-party code_compta) */
	public $subledger_account = '';

	/** @var string Subledger label (third-party name) */
	public $subledger_label = '';

	/** @var string Debit account number (numero_compte for the debit line) */
	public $debit_account = '';

	/** @var string Debit account label (label_compte for the debit line) */
	public $debit_label = '';

	/** @var string Credit account number */
	public $credit_account = '';

	/** @var string Credit account label */
	public $credit_label = '';

	/** @var float Positive amount (debit line: this; credit line: 0). >0 only. */
	public $amount = 0.0;

	/** @var string ISO-4217 currency code (empty = entity base currency, no multicurrency row) */
	public $currency = '';

	/** @var string Short label_operation shown in bookkeeping list */
	public $label_operation = '';

	/** @var string Journal code (default EMBEDDEDBOOKKEEPING_JOURNAL_SALES / _PURCHASES) */
	public $code_journal = '';

	/** @var string Journal label (filled by the writer from llx_accounting_journal) */
	public $journal_label = '';

	/** @var int Unix timestamp; 0 when not set (PHP $invoice->date is already a timestamp) */
	public $date_doc = 0;

	/** @var int Unix timestamp; 0 when not set (PHP $invoice->date_lim_reglement) */
	public $date_lim_reglement = 0;

	/** @var string Source marker: 'manual' | 'ai:<provider>' — written into extraparams */
	public $source = 'manual';

	/** @var float|null Confidence 0..1 (only when source starts with 'ai:') */
	public $confidence = null;

	/** @var string|null Free-text rationale (only when source starts with 'ai:') */
	public $rationale = null;

	/**
	 * Compute the debit line as a flat array (used to build a BookKeeping row).
	 *
	 * @return array<string,mixed>
	 */
	public function asDebitLine()
	{
		return array(
			'numero_compte'   => $this->debit_account,
			'label_compte'    => $this->debit_label,
			'debit'           => (float) $this->amount,
			'credit'          => 0.0,
			'sens'            => 'D',
		);
	}

	/**
	 * Compute the credit line as a flat array.
	 *
	 * @return array<string,mixed>
	 */
	public function asCreditLine()
	{
		return array(
			'numero_compte'   => $this->credit_account,
			'label_compte'    => $this->credit_label,
			'debit'           => 0.0,
			'credit'          => (float) $this->amount,
			'sens'            => 'C',
		);
	}

	/**
	 * Validate the proposal before persistence. Returns 0 on success, a
	 * translated error key on failure.
	 *
	 * @return string Empty on success, otherwise a lang key describing the failure.
	 */
	public function validate()
	{
		// Whitelisted doc_type values mirror llx_accounting_bookkeeping.doc_type
		// (customer_invoice, supplier_invoice, expense_report, bank — bank/closure
		// are not currently user-postable, see docs/README.md "Scope").
		if ($this->doc_type !== 'customer_invoice'
			&& $this->doc_type !== 'supplier_invoice'
			&& $this->doc_type !== 'expense_report') {
			return 'ErrorBadDocTypeForBookkeeping';
		}
		if ($this->fk_doc <= 0) {
			return 'ErrorBadFkDocForBookkeeping';
		}
		if ($this->debit_account === '' || $this->credit_account === '') {
			return 'ErrorBadAccountForBookkeeping';
		}
		if ((float) $this->amount <= 0) {
			return 'ErrorBadAmountForBookkeeping';
		}
		if ($this->debit_account === $this->credit_account) {
			return 'ErrorSameDebitCreditForBookkeeping';
		}
		return '';
	}
}
