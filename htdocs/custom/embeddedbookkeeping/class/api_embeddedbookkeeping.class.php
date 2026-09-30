<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * REST API for EmbeddedBookkeeping — exposed at:
 *   /api/index.php/embeddedbookkeeping/...
 *
 * Required Dolibarr rights (see modEmbeddedBookkeeping::$rights):
 *   bookkeeping->read    — check, listAccounts
 *   bookkeeping->write   — create
 *   ai->suggest          — suggest
 *   admin->setup         — only on /setup endpoints (not implemented yet)
 *
 * Standard error codes thrown via RestException:
 *   400 — invalid input
 *   403 — permission denied
 *   404 — resource not found
 *   409 — bookkeeping already posted (idempotency)
 *   500 — internal writer error
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/api_embeddedbookkeeping.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      REST API class for EmbeddedBookkeeping — provides machine-readable
 *	             endpoints so AI agents and external systems can:
 *	               - probe whether an invoice already has bookkeeping (check)
 *	               - list eligible accounts for a pcg_type bucket (listAccounts)
 *	               - ask the configured AI provider for a suggestion (suggest)
 *	               - create a balanced pair manually (create)
 */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKAccountLookup.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKBookkeepingAlreadyDone.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKBookkeepingWriter.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKEntryProposal.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiLine.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiProviderFactory.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiSuggester.interface.php';

/**
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class EmbeddedBookkeeping extends DolibarrApi
{
	/**
	 * Return bookkeeping status for one document.
	 *
	 * @param  string $docType   'customer_invoice' | 'supplier_invoice' | 'expense_report'
	 * @param  int    $fkDoc     Rowid of the source document
	 * @return array{ok:bool, doc_type:string, fk_doc:int, entity:int, already_posted:bool, row_count:int}
	 *
	 * @url GET check
	 *
	 * @throws RestException 400 Invalid input
	 * @throws RestException 403 Permission denied
	 */
	public function check($docType, $fkDoc)
	{
		global $conf;

		if (!DolibarrApiAccess::$user->hasRight('embeddedbookkeeping', 'bookkeeping', 'read')
			&& empty(DolibarrApiAccess::$user->admin)) {
			throw new RestException(403, 'No permission to read bookkeeping');
		}

		$docType = (string) $docType;
		if (!in_array($docType, array('customer_invoice', 'supplier_invoice', 'expense_report'), true)) {
			throw new RestException(400, 'Invalid doc_type; expected customer_invoice | supplier_invoice | expense_report');
		}
		$fkDoc = (int) $fkDoc;
		if ($fkDoc <= 0) {
			throw new RestException(400, 'fk_doc must be a positive integer');
		}

		$entity = (int) ($conf->entity ?? 1);
		return array(
			'ok'             => true,
			'doc_type'       => $docType,
			'fk_doc'         => $fkDoc,
			'entity'         => $entity,
			'already_posted' => EBKBookkeepingAlreadyDone::existsFor($this->db, $docType, $fkDoc, $entity),
			'row_count'      => EBKBookkeepingAlreadyDone::countFor($this->db, $docType, $fkDoc, $entity),
		);
	}

	/**
	 * List accounting accounts filtered by pcg_type bucket.
	 *
	 * @param  string $pcgType  CSV in {INCOME,EXPENSE,ASSET,LIABILITY,CAPITAL}
	 * @param  int    $limit    1..500 (default 50)
	 * @return array{ok:bool, count:int, accounts:array<int,array{account_number:string,label:string,pcg_type:string}>}
	 *
	 * @url GET listAccounts
	 *
	 * @throws RestException 400 Invalid input
	 * @throws RestException 403 Permission denied
	 */
	public function listAccounts($pcgType = 'INCOME,EXPENSE,ASSET,LIABILITY', $limit = 50)
	{
		global $conf;

		if (!DolibarrApiAccess::$user->hasRight('embeddedbookkeeping', 'bookkeeping', 'read')
			&& empty(DolibarrApiAccess::$user->admin)) {
			throw new RestException(403, 'No permission to read bookkeeping');
		}

		$limit = max(1, min(500, (int) $limit));
		$entity = (int) ($conf->entity ?? 1);
		$rows = EBKAccountLookup::accountCandidates($this->db, (string) $pcgType, $limit, $entity);

		return array(
			'ok'       => true,
			'count'    => count($rows),
			'accounts' => $rows,
		);
	}

	/**
	 * Ask the configured AI provider for a bookkeeping suggestion.
	 *
	 * @param  string $side   'customer' | 'supplier' | 'expense'
	 * @param  int    $fkDoc  Rowid of the source document
	 * @return array{ok:bool, lines:array<int,array<string,mixed>>, warning:string, provider:string}
	 *
	 * @url POST suggest
	 *
	 * @throws RestException 400 Invalid input
	 * @throws RestException 403 Permission denied
	 * @throws RestException 404 Document not found
	 * @throws RestException 409 Already posted
	 */
	public function suggest($side, $fkDoc)
	{
		global $conf;

		if (!DolibarrApiAccess::$user->hasRight('embeddedbookkeeping', 'ai', 'suggest')
			&& empty(DolibarrApiAccess::$user->admin)) {
			throw new RestException(403, 'No permission to ask AI for suggestions');
		}

		if (!in_array($side, array('customer', 'supplier', 'expense'), true)) {
			throw new RestException(400, 'Invalid side; expected customer | supplier | expense');
		}
		$fkDoc = (int) $fkDoc;
		if ($fkDoc <= 0) {
			throw new RestException(400, 'fk_doc must be a positive integer');
		}

		$docType = ($side === 'customer') ? 'customer_invoice'
			: (($side === 'supplier') ? 'supplier_invoice' : 'expense_report');

		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
		require_once DOL_DOCUMENT_ROOT.'/expensereport/class/expensereport.class.php';

		if ($side === 'customer') {
			$invoice = new Facture($this->db);
		} elseif ($side === 'supplier') {
			$invoice = new FactureFournisseur($this->db);
		} else {
			$invoice = new ExpenseReport($this->db);
		}
		if ($invoice->fetch($fkDoc) <= 0) {
			throw new RestException(404, 'Source document not found');
		}
		if ($side !== 'expense' && method_exists($invoice, 'fetch_thirdparty')) {
			$invoice->fetch_thirdparty();
		}

		$entity = (int) ($conf->entity ?? 1);
		if (EBKBookkeepingAlreadyDone::existsFor($this->db, $docType, $fkDoc, $entity)) {
			throw new RestException(409, 'Bookkeeping already posted for this document');
		}

		$provider = EBKAiProviderFactory::resolve();
		$seed = new EBKEntryProposal();
		$seed->doc_type = $docType;
		$seed->fk_doc   = $fkDoc;
		$seed->doc_ref  = isset($invoice->ref) ? (string) $invoice->ref : '';
		$seed->amount   = isset($invoice->total_ttc) ? (float) $invoice->total_ttc : 0.0;
		$seed->currency = isset($invoice->multicurrency_code) ? (string) $invoice->multicurrency_code : '';

		$lines = $provider->suggest($seed, $invoice);

		$out = array();
		foreach ($lines as $ln) {
			$out[] = array(
				'debit_account'   => (string) $ln->debit_account,
				'debit_label'     => (string) $ln->debit_label,
				'credit_account'  => (string) $ln->credit_account,
				'credit_label'    => (string) $ln->credit_label,
				'amount'          => (float) $ln->amount,
				'currency'        => (string) $ln->currency,
				'label_operation' => (string) $ln->label_operation,
				'confidence'      => $ln->confidence,
				'provider_id'     => (string) $ln->provider_id,
				'rationale'       => $ln->rationale,
			);
		}

		$warning = '';
		if (empty($out) && method_exists($provider, 'getReason')) {
			$warning = (string) $provider->getReason();
		}

		return array(
			'ok'       => !empty($out),
			'lines'    => $out,
			'warning'  => $warning,
			'provider' => method_exists($provider, 'getReason') ? (string) $provider->getReason() : '',
		);
	}

	/**
	 * Persist a balanced bookkeeping pair manually.
	 *
	 * @param  string $docType        'customer_invoice' | 'supplier_invoice' | 'expense_report'
	 * @param  int    $fkDoc          Rowid of the source document
	 * @param  string $debitAccount   numero_compte for the debit line
	 * @param  string $creditAccount  numero_compte for the credit line
	 * @param  float  $amount         Positive amount (TTC)
	 * @param  string $labelOperation Operation label (<=255 chars)
	 * @param  string $currency       Optional ISO-4217 code (omit = entity currency, no multicurrency row)
	 * @param  string $codeJournal    Optional explicit journal code (else module default per doc_type)
	 * @return array{ok:bool, piece_num:int, debit_rowid:int, credit_rowid:int, error_key:string, error_detail:string}
	 *
	 * @url POST create
	 *
	 * @throws RestException 400 Invalid input
	 * @throws RestException 403 Permission denied
	 * @throws RestException 409 Already posted
	 * @throws RestException 500 Writer error
	 */
	public function create($docType, $fkDoc, $debitAccount, $creditAccount, $amount, $labelOperation = '', $currency = '', $codeJournal = '')
	{
		global $user, $conf;

		if (!DolibarrApiAccess::$user->hasRight('embeddedbookkeeping', 'bookkeeping', 'write')
			&& empty(DolibarrApiAccess::$user->admin)) {
			throw new RestException(403, 'No permission to write bookkeeping');
		}

		if (!in_array($docType, array('customer_invoice', 'supplier_invoice', 'expense_report'), true)) {
			throw new RestException(400, 'Invalid doc_type');
		}
		$fkDoc = (int) $fkDoc;
		if ($fkDoc <= 0) {
			throw new RestException(400, 'fk_doc must be a positive integer');
		}
		if ($debitAccount === '' || $creditAccount === '') {
			throw new RestException(400, 'Both debit_account and credit_account are required');
		}
		if ((float) $amount <= 0) {
			throw new RestException(400, 'amount must be > 0');
		}
		if ($debitAccount === $creditAccount) {
			throw new RestException(400, 'debit_account and credit_account must differ');
		}

		$entity = (int) ($conf->entity ?? 1);
		if (EBKBookkeepingAlreadyDone::existsFor($this->db, $docType, $fkDoc, $entity)) {
			throw new RestException(409, 'Bookkeeping already posted for this document');
		}

		// Resolve doc_ref best-effort (silently skip if the source cannot be loaded;
		// the writer accepts an empty doc_ref).
		$docRef = '';
		if ($docType === 'customer_invoice') {
			require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
			$src = new Facture($this->db);
			if ($src->fetch($fkDoc) > 0) {
				$docRef = (string) ($src->ref ?? '');
			}
		} elseif ($docType === 'supplier_invoice') {
			require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
			$src = new FactureFournisseur($this->db);
			if ($src->fetch($fkDoc) > 0) {
				$docRef = (string) ($src->ref ?? '');
			}
		} elseif ($docType === 'expense_report') {
			require_once DOL_DOCUMENT_ROOT.'/expensereport/class/expensereport.class.php';
			$src = new ExpenseReport($this->db);
			if ($src->fetch($fkDoc) > 0) {
				$docRef = (string) ($src->ref ?? '');
			}
		}

		$proposal = new EBKEntryProposal();
		$proposal->doc_type        = $docType;
		$proposal->fk_doc          = $fkDoc;
		$proposal->doc_ref         = $docRef;
		$proposal->debit_account   = (string) $debitAccount;
		$proposal->credit_account  = (string) $creditAccount;
		$proposal->debit_label     = EBKAccountLookup::labelForAccount($this->db, $proposal->debit_account, $entity);
		$proposal->credit_label    = EBKAccountLookup::labelForAccount($this->db, $proposal->credit_account, $entity);
		$proposal->amount          = (float) $amount;
		$proposal->currency        = (string) $currency;
		$proposal->label_operation = (string) $labelOperation;
		$proposal->code_journal    = (string) $codeJournal;
		$proposal->source          = 'api';

		$result = EBKBookkeepingWriter::writePair($this->db, DolibarrApiAccess::$user, $proposal, null);
		if (!$result->ok) {
			$errKey = (string) $result->error_key;
			if ($errKey === 'EBKAlreadyBookkeeping') {
				throw new RestException(409, 'Bookkeeping already posted for this document');
			}
			if ($errKey === 'NotEnoughPermissions') {
				throw new RestException(403, 'No permission to write bookkeeping');
			}
			throw new RestException(500, 'Writer failed: '.$errKey.(empty($result->error_detail) ? '' : ' — '.$result->error_detail));
		}

		return array(
			'ok'            => true,
			'piece_num'     => (int) $result->piece_num,
			'debit_rowid'   => (int) $result->debit_rowid,
			'credit_rowid'  => (int) $result->credit_rowid,
			'error_key'     => '',
			'error_detail'  => '',
		);
	}
}
