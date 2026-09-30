<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/ai/EBKAiLine.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      One row of AI-suggested bookkeeping lines (a balanced pair).
 *	             The interface EBKAiSuggester::suggest() returns array<int,EBKAiLine>.
 */

if (!class_exists('EBKAiLine', false)) {

/**
 * One AI-suggested journal entry line (debit or credit side).
 * Always returned as a *pair* by providers — debit_account + credit_account
 * represent the same balanced entry.
 */
class EBKAiLine
{
	/** @var string Debit account number (numero_compte for the debit line) */
	public $debit_account = '';

	/** @var string Debit account label */
	public $debit_label = '';

	/** @var string Credit account number */
	public $credit_account = '';

	/** @var string Credit account label */
	public $credit_label = '';

	/** @var float Amount (>0). Currency-side amount if multicurrency is meaningful. */
	public $amount = 0.0;

	/** @var string ISO-4217 currency code */
	public $currency = '';

	/** @var string Short label_operation */
	public $label_operation = '';

	/** @var float|null Confidence in [0,1] */
	public $confidence = null;

	/** @var string|null Free-text rationale (1-2 sentences) */
	public $rationale = null;

	/** @var string Provider id (e.g. 'ai_module' | 'claude') for audit */
	public $provider_id = '';

	/**
	 * Convert to an EBKEntryProposal for the writer. doc_type / fk_doc / etc.
	 * are filled by the caller (the AJAX endpoint knows the invoice side).
	 *
	 * @return EBKEntryProposal
	 */
	public function toEntryProposal()
	{
		$p = new EBKAiEntryProposal();
		$p->debit_account   = (string) $this->debit_account;
		$p->debit_label     = (string) $this->debit_label;
		$p->credit_account  = (string) $this->credit_account;
		$p->credit_label    = (string) $this->credit_label;
		$p->amount          = (float) $this->amount;
		$p->currency        = (string) $this->currency;
		$p->label_operation = (string) $this->label_operation;
		$p->confidence      = $this->confidence;
		$p->rationale       = $this->rationale;
		$p->source          = 'ai:'.(string) $this->provider_id;
		return $p;
	}
}

/**
 * Tiny private subclass so AI-derived proposals are distinguishable from
 * manual proposals at the type level (and so source='ai:<provider>' is set
 * by the converter, not by the writer).
 */
class EBKAiEntryProposal extends EBKEntryProposal
{
}

} // if (!class_exists('EBKAiLine', false))
