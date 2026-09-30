<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/EBKBookkeepingWriter.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Persists balanced journal entries into llx_accounting_bookkeeping.
 *
 *             Two write paths, shared guards and helpers:
 *               - writePair():  ONE debit + ONE credit row (legacy total-amount
 *                 path; kept for the REST API and backward compatibility).
 *               - writeEntry(): N-row Odoo-style entry (counter row + per-line
 *                 rows + grouped VAT rows) sharing one piece_num, written inside
 *                 one outer transaction. piece_num allocation is left to core
 *                 BookKeeping::create() (it reuses the value matched on
 *                 doc_type/fk_doc/doc_ref/entity, else allocates MAX+1); we only
 *                 ASSERT all rows ended up on the same piece_num.
 *
 *             Design contract:
 *               - Never writes to the bookkeeping table directly; always goes
 *                 through BookKeeping::create() so core hooks
 *                 (BOOKKEEPING_CREATE / bookkeepingCreateBefore) still fire.
 *               - Returns a structured EBKWriteResult so callers (UI / AJAX)
 *                 can surface translated errors without exceptions.
 */

require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKEntryProposal.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKEntryDraft.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKBookkeepingAlreadyDone.class.php';

if (!class_exists('EBKWriteResult', false)) {

/**
 * Outcome of EBKBookkeepingWriter::writePair() / writeEntry(). Plain object — never
 * serialized to JSON without ->toArray() which trims sensitive fields.
 */
class EBKWriteResult
{
	/** @var bool True on success (all rows written, balanced, one piece_num) */
	public $ok = false;

	/** @var int Allocated piece_num on success, 0 otherwise */
	public $piece_num = 0;

	/** @var int Rowid of the debit line (or 0 on failure) */
	public $debit_rowid = 0;

	/** @var int Rowid of the credit line (or 0 on failure) */
	public $credit_rowid = 0;

	/** @var int[] Rowids of every written row (writeEntry path; writePair fills 2 too) */
	public $rowids = array();

	/** @var int Number of written rows */
	public $nb_lines = 0;

	/** @var string Empty on success, otherwise a lang key describing the failure */
	public $error_key = '';

	/** @var string Free-text error detail (from BookKeeping::$errors), safe to show only to admins */
	public $error_detail = '';
}
}

if (!class_exists('EBKBookkeepingWriter', false)) {

class EBKBookkeepingWriter
{
	/**
	 * Validate + persist a single proposal as a balanced pair.
	 *
	 * @param DoliDB            $db        Database handler
	 * @param User              $user      Connected user (used for fk_user_author + permission checks)
	 * @param EBKEntryProposal  $proposal  Validated proposal
	 * @param CommonObject|null $sourceObject Facture|FactureFournisseur (for entity & date fallback); may be null
	 * @return EBKWriteResult
	 */
	public static function writePair($db, $user, EBKEntryProposal $proposal, $sourceObject = null)
	{
		global $conf;

		$result = new EBKWriteResult();

		// --- 1. Validate proposal ------------------------------------------------
		$err = $proposal->validate();
		if ($err !== '') {
			$result->error_key = $err;
			return $result;
		}

		// --- 2. Refuse double-post (idempotency guard) ---------------------------
		$entity = self::resolveEntity($sourceObject, $conf);
		if (EBKBookkeepingAlreadyDone::existsFor($db, $proposal->doc_type, $proposal->fk_doc, $entity)) {
			$result->error_key = 'EBKAlreadyBookkeeping';
			return $result;
		}

		// --- 3. Permission check (defence in depth — UI also gates this) ---------
		if (!self::userCanWrite($user)) {
			$result->error_key = 'NotEnoughPermissions';
			return $result;
		}

		// --- 4. Resolve journal code (proposal value wins, then global, then default)
		$codeJournal = self::resolveJournalCode($proposal, $entity);
		if ($codeJournal === '') {
			$result->error_key = 'ErrorBadJournalForBookkeeping';
			return $result;
		}
		$proposal->code_journal = $codeJournal;

		// --- 5. Allocate piece_num ONCE -----------------------------------------
		$pieceNum = self::allocatePieceNum($db, $entity);
		if ($pieceNum <= 0) {
			$result->error_key = 'ErrorCannotAllocatePieceNum';
			$result->error_detail = 'Could not allocate piece_num; bookkeeping table is empty or unreadable.';
			return $result;
		}
		$result->piece_num = $pieceNum;

		// --- 6. Build debit + credit lines --------------------------------------
		$dateDoc = self::resolveDate($proposal, $sourceObject);
		$dateLim = self::resolveDateLim($proposal, $sourceObject);

		$multicurrencyAmount = null;
		$multicurrencyCode   = null;
		if (!empty($proposal->currency)) {
			$multicurrencyCode = $proposal->currency;
			// FEC/sfrs convention: entity amount / invoice rate = foreign amount.
			$mcRatePair = self::sourceMulticurrencyTx($db, $proposal->doc_type, $proposal->fk_doc);
			if ($mcRatePair <= 0) {
				$mcRatePair = 1.0;
			}
			$multicurrencyAmount = round(abs((float) $proposal->amount) / $mcRatePair, 8);
		}

		// Extraparams audit blob (string-safe — BookKeeping::create accepts JSON string)
		$extras = array(
			'ebk_source'       => $proposal->source,
			'ebk_form_version' => 1,
			'ebk_entity'       => $entity,
			'ebk_module'       => 'embeddedbookkeeping@1.0.0',
		);
		if ($proposal->confidence !== null) {
			$extras['ebk_confidence'] = (float) $proposal->confidence;
		}
		if (!empty($proposal->rationale)) {
			$extras['ebk_rationale'] = (string) $proposal->rationale;
		}
		$extraparams = json_encode($extras);
		if ($extraparams === false) {
			$extraparams = ''; // BookKeeping accepts empty string
		}

		$common = array(
			'piece_num'           => $pieceNum,
			'doc_type'            => $proposal->doc_type,
			'doc_ref'             => self::safeString($proposal->doc_ref, 300),
			'fk_doc'              => (int) $proposal->fk_doc,
			'fk_docdet'           => 0,
			'thirdparty_code'     => self::safeString($proposal->thirdparty_code, 32),
			'subledger_account'   => self::safeString($proposal->subledger_account, 32),
			'subledger_label'     => self::safeString($proposal->subledger_label, 255),
			'label_operation'     => self::safeString($proposal->label_operation, 255),
			'doc_date'            => (int) $dateDoc,
			'date_lim_reglement'  => $dateLim > 0 ? (int) $dateLim : null,
			'code_journal'        => $codeJournal,
			'journal_label'       => self::safeString($proposal->journal_label, 255),
			'multicurrency_amount'=> $multicurrencyAmount,
			'multicurrency_code'  => $multicurrencyCode,
			'extraparams'         => $extraparams,
			'fk_user_author'      => is_object($user) ? (int) $user->id : 0,
		);

		$debit = array_merge($common, $proposal->asDebitLine());
		$credit = array_merge($common, $proposal->asCreditLine());

		// --- 7. INSERT debit line ------------------------------------------------
		$bkD = self::buildBookKeeping($db, $debit);
		if (!$bkD->create($user, 0)) {
			$result->error_key = 'EBKCreateFailed';
			$result->error_detail = is_array($bkD->errors) ? implode(' | ', $bkD->errors) : '';
			return $result;
		}
		$result->debit_rowid = (int) $bkD->id;

		// --- 8. INSERT credit line (same piece_num) -----------------------------
		$bkC = self::buildBookKeeping($db, $credit);
		if (!$bkC->create($user, 0)) {
			// Best-effort rollback: delete the debit line we just inserted.
			dol_syslog(get_class()."::writePair credit insert failed; rolling back debit rowid=".$result->debit_rowid, LOG_ERR);
			$bkD->delete($user, 0);
			$result->error_key = 'EBKCreateFailed';
			$result->error_detail = is_array($bkC->errors) ? implode(' | ', $bkC->errors) : '';
			return $result;
		}
		$result->credit_rowid = (int) $bkC->id;
		$result->rowids = array((int) $bkD->id, (int) $bkC->id);
		$result->nb_lines = 2;

		$result->ok = true;
		return $result;
	}

	/**
	 * Validate + persist an N-row balanced entry (Odoo-style: counter row +
	 * per-line rows + grouped VAT rows) inside ONE outer transaction.
	 *
	 * piece_num is NOT pre-allocated: core BookKeeping::create() resets it and
	 * either reuses the piece matched on (doc_type, fk_doc, doc_ref, entity)
	 * or allocates MAX+1 itself. We record the piece_num of the first row and
	 * ASSERT every later row landed on the same one — mismatch (possible when
	 * the experimental ACCOUNTANCY_ENABLE_FKDOCDET constant fragments the
	 * reuse match) triggers a rollback.
	 *
	 * @param  DoliDB          $db           Database handler
	 * @param  User            $user         Connected user
	 * @param  EBKEntryDraft   $draft        N-row draft (validated here)
	 * @param  CommonObject|null $sourceObject Facture|FactureFournisseur|ExpenseReport for date fallback
	 * @return EBKWriteResult
	 */
	public static function writeEntry($db, $user, EBKEntryDraft $draft, $sourceObject = null)
	{
		global $conf;

		$result = new EBKWriteResult();

		// --- 1. Validate draft ---------------------------------------------------
		$err = $draft->validate();
		if ($err !== '') {
			$result->error_key = $err;
			return $result;
		}

		// --- 2. Refuse double-post (idempotency guard) ---------------------------
		$entity = self::resolveEntity($sourceObject, $conf);
		if (EBKBookkeepingAlreadyDone::existsFor($db, $draft->doc_type, $draft->fk_doc, $entity)) {
			$result->error_key = 'EBKAlreadyBookkeeping';
			return $result;
		}

		// --- 3. Permission check (defence in depth — the tab also gates this) ----
		if (!self::userCanWrite($user)) {
			$result->error_key = 'NotEnoughPermissions';
			return $result;
		}

		// --- 4. Resolve journal code ---------------------------------------------
		$codeJournal = self::journalCodeForDocType($draft->doc_type, $draft->code_journal);
		if ($codeJournal === '') {
			$result->error_key = 'ErrorBadJournalForBookkeeping';
			return $result;
		}
		$draft->code_journal = $codeJournal;

		// --- 5. Common header -----------------------------------------------------
		$dateDoc = (int) $draft->date_doc;
		if ($dateDoc <= 0 && is_object($sourceObject) && (int) $sourceObject->date > 0) {
			$dateDoc = (int) $sourceObject->date;
		}
		if ($dateDoc <= 0) {
			$dateDoc = time();
		}
		$dateLim = (int) $draft->date_lim_reglement;
		if ($dateLim <= 0 && is_object($sourceObject) && (int) $sourceObject->date_lim_reglement > 0) {
			$dateLim = (int) $sourceObject->date_lim_reglement;
		}

		$multicurrencyAmount = null;
		$multicurrencyCode   = null;
		if (!empty($draft->currency)) {
			$multicurrencyCode = $draft->currency;
			// FEC/sfrs convention: multicurrency_amount = entity amount / invoice
			// rate (NOT the entity amount mislabelled as foreign). Rows below use
			// $mcRate; fallback 1 when the source carries no rate.
			$mcRate = self::sourceMulticurrencyTx($db, $draft->doc_type, $draft->fk_doc);
			if ($mcRate <= 0) {
				$mcRate = 1.0;
			}
		}

		$extras = array(
			'ebk_source'       => $draft->source,
			'ebk_form_version' => 2,
			'ebk_entity'       => $entity,
			'ebk_module'       => 'embeddedbookkeeping@1.1.0',
		);
		if ($draft->confidence !== null) {
			$extras['ebk_confidence'] = (float) $draft->confidence;
		}
		if (!empty($draft->rationale)) {
			$extras['ebk_rationale'] = (string) $draft->rationale;
		}
		$extraparams = json_encode($extras);
		if ($extraparams === false) {
			$extraparams = '';
		}

		$common = array(
			'doc_type'            => $draft->doc_type,
			'doc_ref'             => self::safeString($draft->doc_ref, 300),
			'fk_doc'              => (int) $draft->fk_doc,
			'thirdparty_code'     => self::safeString($draft->thirdparty_code, 32),
			'label_operation'     => self::safeString($draft->label_operation, 255),
			'doc_date'            => $dateDoc,
			'date_lim_reglement'  => $dateLim > 0 ? $dateLim : null,
			'code_journal'        => $codeJournal,
			'journal_label'       => self::safeString($draft->journal_label, 255),
			'multicurrency_code'  => $multicurrencyCode,
			'extraparams'         => $extraparams,
			'fk_user_author'      => is_object($user) ? (int) $user->id : 0,
		);

		// --- 6. Insert all rows inside one outer transaction ----------------------
		$db->begin();

		$pieceNum = 0;
		$totalDebit = 0.0;
		$totalCredit = 0.0;
		$nb = 0;
		foreach ($draft->rows as $row) {
			$mt = (float) $row['amount']; // signed: negative = debit, positive = credit

			$line = $common;
			$line['numero_compte']     = self::safeString($row['numero_compte'], 32);
			$line['label_compte']      = self::safeString($row['label_compte'], 255);
			$line['subledger_account'] = self::safeString(isset($row['subledger_account']) ? $row['subledger_account'] : '', 32);
			$line['subledger_label']   = self::safeString(isset($row['subledger_label']) ? $row['subledger_label'] : '', 255);
			$line['label_operation']   = self::safeString($row['label_operation'], 255);
			$line['fk_docdet']         = (int) (isset($row['fk_docdet']) ? $row['fk_docdet'] : 0);
			$line['debit']             = ($mt < 0) ? -$mt : 0.0;
			$line['credit']            = ($mt >= 0) ? $mt : 0.0;
			$line['sens']              = ($mt < 0) ? 'D' : 'C';
			$line['multicurrency_amount'] = $multicurrencyCode !== null ? round(abs($mt) / $mcRate, 8) : null;

			$bk = self::buildBookKeeping($db, $line);
			if (!$bk->create($user, 0)) {
				$db->rollback();
				$result->error_key = 'EBKCreateFailed';
				$result->error_detail = is_array($bk->errors) ? implode(' | ', $bk->errors) : '';
				return $result;
			}

			$rowPieceNum = (int) (isset($bk->piece_num) ? $bk->piece_num : 0);
			if ($pieceNum === 0) {
				$pieceNum = $rowPieceNum;
			} elseif ($rowPieceNum !== $pieceNum) {
				$db->rollback();
				$result->error_key = 'EBKPieceNumMismatch';
				$result->error_detail = 'Row '.((int) $bk->id).' got piece_num '.$rowPieceNum.' instead of '.$pieceNum.' (check ACCOUNTANCY_ENABLE_FKDOCDET).';
				return $result;
			}

			if ($line['debit'] > 0 && $result->debit_rowid === 0) {
				$result->debit_rowid = (int) $bk->id;
			}
			if ($line['credit'] > 0 && $result->credit_rowid === 0) {
				$result->credit_rowid = (int) $bk->id;
			}
			$result->rowids[] = (int) $bk->id;
			$totalDebit += (float) $line['debit'];
			$totalCredit += (float) $line['credit'];
			$nb++;
		}

		// --- 7. Defensive balance check (same protection as core journals) --------
		if (price2num($totalDebit, 'MT') != price2num($totalCredit, 'MT')) {
			$db->rollback();
			$result->error_key = 'EBKEntryNotBalanced';
			$result->rowids = array();
			$result->debit_rowid = 0;
			$result->credit_rowid = 0;
			return $result;
		}

		// --- 8. Commit -------------------------------------------------------------
		$db->commit();

		$result->ok = true;
		$result->piece_num = $pieceNum;
		$result->nb_lines = $nb;
		return $result;
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Read the multicurrency rate (1 foreign = X entity currency) stored on the
	 * source document header. Expense reports carry none -> 0.
	 *
	 * @param  DoliDB  $db
	 * @param  string  $docType
	 * @param  int     $fkDoc
	 * @return float 0 when unknown
	 */
	private static function sourceMulticurrencyTx($db, $docType, $fkDoc)
	{
		if ($docType === 'customer_invoice') {
			$table = 'facture';
		} elseif ($docType === 'supplier_invoice') {
			$table = 'facture_fourn';
		} else {
			return 0.0;
		}
		$sql = "SELECT f.multicurrency_tx FROM ".MAIN_DB_PREFIX.$table." AS f WHERE f.rowid = ".((int) $fkDoc);
		$resql = $db->query($sql);
		if (!$resql) {
			return 0.0;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		return $obj ? (float) $obj->multicurrency_tx : 0.0;
	}

	/**
	 * Build a populated BookKeeping instance from an array of column values.
	 * Does NOT call create(); the caller does.
	 *
	 * @param DoliDB $db
	 * @param array<string,mixed> $line  Keys must match property names on BookKeeping
	 * @return BookKeeping
	 */
	private static function buildBookKeeping($db, array $line)
	{
		$bk = new BookKeeping($db);
		foreach ($line as $k => $v) {
			// BookKeeping uses snake_case attributes too; assign defensively.
			$bk->$k = $v;
		}
		$bk->status = 0; // unvalidated (status=1 reserved for validated)
		return $bk;
	}

	/**
	 * Resolve the entity id to scope every piece_num / entity lookup by.
	 *
	 * @param CommonObject|null $source
	 * @param stdClass          $conf
	 * @return int
	 */
	private static function resolveEntity($source, $conf)
	{
		if (is_object($source) && !empty($source->entity)) {
			return (int) $source->entity;
		}
		return (int) ($conf->entity ?? 1);
	}

	/**
	 * Permission gate — defence in depth. The UI already gates this in
	 * ActionsEmbeddedBookkeepingUiTrait; we re-check here so a forged POST
	 * cannot bypass the gate by skipping the UI.
	 *
	 * @param User|null $user
	 * @return bool
	 */
	private static function userCanWrite($user)
	{
		if (!is_object($user)) {
			return false;
		}
		if (!empty($user->admin)) {
			return true;
		}
		return !empty($user->rights->embeddedbookkeeping->bookkeeping->write);
	}

	/**
	 * Choose a journal code: explicit value > global constant > hard default.
	 *
	 * @param EBKEntryProposal $proposal
	 * @param int              $entity
	 * @return string
	 */
	private static function resolveJournalCode(EBKEntryProposal $proposal, $entity)
	{
		return self::journalCodeForDocType($proposal->doc_type, $proposal->code_journal);
	}

	/**
	 * Resolve the journal code for a doc_type: explicit value > module constant > hard default.
	 * Shared by writePair() (via resolveJournalCode) and writeEntry().
	 *
	 * @param  string $docType       'customer_invoice' | 'supplier_invoice' | 'expense_report'
	 * @param  string $explicitCode  Non-empty explicit journal code (wins when set)
	 * @return string
	 */
	private static function journalCodeForDocType($docType, $explicitCode)
	{
		if (!empty($explicitCode)) {
			return $explicitCode;
		}
		if ($docType === 'customer_invoice') {
			$constName = 'EMBEDDEDBOOKKEEPING_JOURNAL_SALES';
		} elseif ($docType === 'supplier_invoice') {
			$constName = 'EMBEDDEDBOOKKEEPING_JOURNAL_PURCHASES';
		} else {
			// expense_report
			$constName = 'EMBEDDEDBOOKKEEPING_JOURNAL_EXPENSE';
		}
		$val = getDolGlobalString($constName);
		if (!empty($val)) {
			return $val;
		}
		if ($docType === 'customer_invoice') return 'VT';
		if ($docType === 'supplier_invoice') return 'AC';
		return 'EX';
	}

	/**
	 * Allocate the next piece_num within the entity. MAX+1 inside a single
	 * transaction-like block: we read MAX, +1, and write — concurrent writes
	 * are extremely unlikely on an invoice-card click and the table is not
	 * expected to collide because piece_num is per-entity and grows
	 * monotonically. The Dolibarr-native sellsjournal/purchasesjournal code
	 * uses the same SELECT MAX pattern.
	 *
	 * @param DoliDB $db
	 * @param int    $entity
	 * @return int 0 on failure
	 */
	private static function allocatePieceNum($db, $entity)
	{
		$sql = "SELECT MAX(b.piece_num) AS maxnum";
		$sql .= " FROM ".MAIN_DB_PREFIX."accounting_bookkeeping AS b";
		$sql .= " WHERE b.entity = ".(int) $entity;

		$resql = $db->query($sql);
		if ($resql === false) {
			dol_syslog(get_class()."::allocatePieceNum err=".$db->lasterror, LOG_ERR);
			return 0;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		$max = $obj && $obj->maxnum !== null ? (int) $obj->maxnum : 0;
		return $max + 1;
	}

	/**
	 * Pick a doc_date: proposal value > source object $date property > now.
	 *
	 * @param EBKEntryProposal  $proposal
	 * @param CommonObject|null $source
	 * @return int Unix timestamp (>=0)
	 */
	private static function resolveDate($proposal, $source)
	{
		if ((int) $proposal->date_doc > 0) {
			return (int) $proposal->date_doc;
		}
		if (is_object($source) && (int) $source->date > 0) {
			return (int) $source->date;
		}
		return time();
	}

	/**
	 * Pick date_lim_reglement from the proposal or source.
	 *
	 * @param EBKEntryProposal  $proposal
	 * @param CommonObject|null $source
	 * @return int 0 when unknown
	 */
	private static function resolveDateLim($proposal, $source)
	{
		if ((int) $proposal->date_lim_reglement > 0) {
			return (int) $proposal->date_lim_reglement;
		}
		if (is_object($source) && (int) $source->date_lim_reglement > 0) {
			return (int) $source->date_lim_reglement;
		}
		return 0;
	}

	/**
	 * Truncate a string to a maximum length and strip control characters.
	 * Defends bookkeeping against unexpected utf-8 / null-byte payloads.
	 *
	 * @param string $val
	 * @param int    $maxLen
	 * @return string
	 */
	private static function safeString($val, $maxLen)
	{
		$val = (string) $val;
		// Strip null bytes (some MySQL configs choke on \0 in VARCHAR/TEXT)
		$val = str_replace(chr(0), '', $val);
		// Strip other C0 controls except newline/tab (cosmetic only)
		$val = preg_replace('/[\x01-\x08\x0B\x0C\x0E-\x1F]/u', '', $val);
		if (function_exists('mb_substr')) {
			$val = mb_substr($val, 0, $maxLen);
		} elseif (strlen($val) > $maxLen) {
			$val = substr($val, 0, $maxLen);
		}
		return $val;
	}
}

} // if (!class_exists('EBKBookkeepingWriter', false))
