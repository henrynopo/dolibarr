<?php
/* Copyright (C) 2026 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Software Foundation; either version 3 of the
 * License, or (at your option) any later version.
 */

/**
 * Hooks for the Wise outgoing flow (flow A): "Pay via Wise" button on
 * validated, unpaid supplier invoices; per-transfer status line on the card.
 * Gated by WISE_OUTGOING_ENABLED (default off) — the button never shows
 * unless the toggle is on.
 */

if (!trait_exists('ActionsSlycustomWisePaymentTrait', false)) {
trait ActionsSlycustomWisePaymentTrait
{
	/**
	 * addMoreActionsButtons hook (context: invoicesuppliercard).
	 *
	 * @param  array        $parameters Hook metadata
	 * @param  FactureFourn $object     Supplier invoice
	 * @param  string       $action     Current action
	 * @return int
	 */
	public function wisePaymentAddMoreActionsButtons($parameters, $object, $action)
	{
		global $conf, $langs, $user;

		// 22.0.4 HookManager only injects 'currentcontext' (the declared hook
		// context); a 'context' key never exists there, so checking it made the
		// button unreachable on supplier invoice cards.
		if (empty($parameters['currentcontext']) || strpos($parameters['currentcontext'], 'invoicesuppliercard') === false) {
			return 0;
		}
		if (!isModEnabled('slycustom') || !is_object($object) || empty($object->id)) {
			return 0;
		}
		if (empty($user->rights->slycustom->wise->read) && empty($user->admin)) {
			return 0;
		}

		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_Incoming.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_Payment.class.php';
		if (!WiseOutgoingPayment::isEnabled($this->db, (int) $conf->entity)) {
			return 0;
		}

		$langs->load('slycustom@slycustom');

		$active = WiseOutgoingPayment::fetchStaticActiveForInvoice($this->db, (int) $object->id, (int) $conf->entity);
		$eligible = ((int) $object->statut === 1 && empty($object->paye));

		if ($eligible && !$active) {
			$url = DOL_URL_ROOT.'/custom/slycustom/wise/prepare.php?id='.(int) $object->id;
			print '<div class="inline-block divButAction"><a class="butAction" href="'.$url.'">'.$langs->trans("WisePayViaWise").'</a></div>';
		} elseif ($active) {
			// Status line for an in-flight transfer
			$url = DOL_URL_ROOT.'/custom/slycustom/wise/prepare.php?id='.(int) $object->id;
			$label = $langs->trans("WiseTransferStatus").': '.dol_escape_htmltag($active->status.' / '.$active->last_state);
			print '<div class="inline-block divButAction"><a class="butActionRefused" href="'.$url.'" title="'.dol_escape_htmltag($label).'">'.$label.'</a></div>';
		}

		return 0;
	}
}
}
