<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: SLY Custom shipping card hooks (ShipsGo buttons + unvalidate).
 *
 * Extracted from actions_slycustom.class.php to reduce file size.
 * Hook method names and signatures are kept identical to preserve behavior.
 */
trait ActionsSlycustomShippingCardHooksTrait
{
	/**
	 * Overloading the formConfirm function.
	 *
	 * @param array        $parameters Hook parameters (formConfirm)
	 * @param CommonObject $object     Object
	 * @param string       $action     Current action
	 * @return int
	 */
	public function formConfirm($parameters, &$object, &$action)
	{
		global $conf, $user, $langs, $form, $hookmanager;

		if (empty($object->element) || $object->element != 'shipping') {
			return 0;
		}

		// SLY: Unvalidate shipment form (action=modif)
		if ($action == 'modif' && $object->status > 0 && getDolGlobalString('SLYCUSTOM_SHIPPING_ENABLE_UNVALIDATE', 1)
			&& $user->hasRight('expedition', 'creer')
			&& (!getDolGlobalString('MAIN_USE_ADVANCED_PERMS') || $user->hasRight('expedition', 'shipping_advance', 'validate'))) {
			$langs->load("slycustom@slycustom");
			$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('UnvalidateShipment'), $langs->trans("ConfirmUnvalidateShipment", $object->ref), 'confirm_modif', '', 0, 1);
			$hookmanager->resPrint .= $formconfirm;
		}

		return 0;
	}

	/**
	 * Overloading the addMoreActionsButtons function.
	 *
	 * @param array        $parameters Hook parameters
	 * @param CommonObject $object     Object
	 * @param string       $action     Current action
	 * @return int <0 on error, 0=nothing done, >0=replace default
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;

		if (empty($object->element) || $object->element != 'shipping') {
			return 0;
		}

		// SLY ShipsGo: Update Ships button (API key per entity)
		if (isModEnabled('slycustom') && $object->status > 0 && !empty($object->tracking_number) && $user->hasRight('expedition', 'creer')) {
			require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_Update.class.php';
			if (ShipmentStatus::getApiKeyForExpedition($this->db, $object, $conf) !== '') {
				$langs->load("slycustom@slycustom");
				print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=updateships&token='.newToken().'">'.$langs->trans("UpdateShips").'</a></div>';
			}
		}

		// SLY: Unvalidate button (set back to draft)
		if ($object->status > 0 && getDolGlobalString('SLYCUSTOM_SHIPPING_ENABLE_UNVALIDATE', 1)
			&& $user->hasRight('expedition', 'creer')
			&& (!getDolGlobalString('MAIN_USE_ADVANCED_PERMS') || $user->hasRight('expedition', 'shipping_advance', 'validate'))) {
			$langs->load("slycustom@slycustom");
			print '<div class="inline-block divButAction"><a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=modif&token='.newToken().'">'.$langs->trans('UnvalidateShipment').'</a></div>';
		}

		return 0;
	}
}

