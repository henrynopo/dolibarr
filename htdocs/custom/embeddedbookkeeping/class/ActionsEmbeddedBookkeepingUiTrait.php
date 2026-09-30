<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/ActionsEmbeddedBookkeepingUiTrait.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      addMoreActionsButtons hook: render ONE deep-link button to the
 *	             "Accounting entries" tab on customer / supplier invoice and
 *	             expense report cards. The button honours:
 *	               - module enabled
 *	               - per-user rights (embeddedbookkeeping->bookkeeping->read)
 *	               - invoice status (must be >= VALIDATED, not DRAFT) — except that
 *                 an already-posted document keeps the button as "View bookkeeping"
 *	             Everything else (form, AI prefill, posting) lives in the tab page.
 */

if (!trait_exists('ActionsEmbeddedBookkeepingUiTrait', false)) {

trait ActionsEmbeddedBookkeepingUiTrait
{
	/**
	 * Hook for `addMoreActionsButtons` (context: invoicecard + invoicesuppliercard + expensereportcard).
	 *
	 * @param  array        $parameters Hook metadata (v22: 'currentcontext')
	 * @param  CommonObject $object     Facture | FactureFournisseur | ExpenseReport
	 * @param  string       $action     Current action
	 * @param  string       $hookname   Hook name (unused; context comes from $parameters)
	 * @return int                       0
	 */
	public function uiAddMoreActionsButtons($parameters, $object, $action, $hookname)
	{
		global $conf, $langs, $user;

		// v22.0.4 HookManager only injects parameters['currentcontext']; a 'context' key never exists.
		$ctx = isset($parameters['currentcontext']) ? (string) $parameters['currentcontext'] : '';
		if ($ctx === ''
			|| (strpos($ctx, 'invoicecard') === false
				&& strpos($ctx, 'invoicesuppliercard') === false
				&& strpos($ctx, 'expensereportcard') === false)) {
			return 0;
		}

		// Hard requirement: module enabled and an object we can act on.
		if (!isModEnabled('embeddedbookkeeping') || !is_object($object) || empty($object->id)) {
			return 0;
		}

		// Permission gate.
		if (empty($user->rights->embeddedbookkeeping->bookkeeping->read) && empty($user->admin)) {
			return 0;
		}

		$isSupplier = ($ctx === 'invoicesuppliercard') || (isset($object->element) && $object->element === 'invoice_supplier');
		$isExpense  = ($ctx === 'expensereportcard') || (isset($object->element) && $object->element === 'expensereport');
		if ($isExpense) {
			$docType = 'expense_report';
		} elseif ($isSupplier) {
			$docType = 'supplier_invoice';
		} else {
			$docType = 'customer_invoice';
		}

		// Status: only validated or paid invoices/reports offer the entry form,
		// but a document that is already posted keeps a "view" deep link in any status.
		// ExpenseReport exposes the status as $fk_statut (no $statut property exists).
		if ($isExpense) {
			$statut = isset($object->fk_statut) ? (int) $object->fk_statut : -1;
		} else {
			$statut = isset($object->statut) ? (int) $object->statut : -1;
		}
		if ($isExpense && !class_exists('ExpenseReport', false)) {
			require_once DOL_DOCUMENT_ROOT.'/expensereport/class/expensereport.class.php';
		}
		$eligibleStatuses = $isExpense ? array(ExpenseReport::STATUS_VALIDATED, ExpenseReport::STATUS_APPROVED, ExpenseReport::STATUS_CLOSED) : array(1, 2);

		require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKBookkeepingAlreadyDone.class.php';
		$alreadyDone = EBKBookkeepingAlreadyDone::existsFor($this->db, $docType, (int) $object->id, (int) $conf->entity);

		if (!$alreadyDone && !in_array($statut, $eligibleStatuses, true)) {
			return 0;
		}

		$langs->loadLangs(array('embeddedbookkeeping@embeddedbookkeeping', 'compta'));

		$tabUrl = dol_buildpath('/custom/embeddedbookkeeping/tabs/bookkeeping.php', 1)
			.'?id='.(int) $object->id.'&objecttype='.$docType;

		$label = $alreadyDone ? $langs->trans('EBKTabViewBookkeeping') : $langs->trans('EBKTabButton');
		print '<div class="inline-block divButAction"><a class="butAction" href="'.dol_escape_htmltag($tabUrl).'">'.dol_escape_htmltag($label).'</a></div>';

		return 0;
	}
}

} // if (!trait_exists('ActionsEmbeddedBookkeepingUiTrait', false))
