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
			// Not a shipment: delegate to the Wise outgoing flow (supplier invoices)
			$this->wisePaymentAddMoreActionsButtons($parameters, $object, $action);
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

	/**
	 * Overloading the showOptionals hook.
	 *
	 * Shipment extrafield date inputs used to default to "today" in v22 but in v24 they
	 * display 1970-01-01 because the value 0 reaches selectDate() and is treated as a
	 * legitimate Unix timestamp (see core/class/html.form.class.php:8506 — the comment
	 * "set_time est un timestamps (0 possible)"). The 0 originates from:
	 *   - POST → setOptionalsFromPost:3010 returns dol_mktime(12,0,0,0,0,0) = '' (empty),
	 *     then showOptionals:9793 sees !is_numeric('') → jdate('') = 0 → value=0.
	 *   - DB row stored with options_xxx = 0 (the empty POST value written into an INT
	 *     column becomes 0 in subsequent SELECTs).
	 *
	 * We pre-seed $array_options with dol_now('tzuser') for any date-typed shipment
	 * extrafield whose current value is unset, '', 0, or '0'. The hook fires once per
	 * showOptionals call, before the foreach renders each field. Both create/edit modes
	 * benefit; view mode is skipped so historical values remain visible.
	 *
	 * Hook signature: $parameters = array('mode','params','keysuffix','display_type').
	 *
	 * @param array        $parameters Hook parameters
	 * @param CommonObject $object     Object (here: Expedition = element 'shipping')
	 * @param string       $action     Current action
	 * @param HookManager  $hookmanager Hook manager
	 * @return int 0 on success
	 */
	public function showOptionals($parameters, &$object, &$action, $hookmanager)
	{
		global $extrafields;

		if (empty($object->element) || $object->element != 'shipping') {
			return 0;
		}
		if (empty($parameters['mode']) || !in_array($parameters['mode'], array('create', 'edit'))) {
			return 0;
		}
		if (!is_object($extrafields) || empty($extrafields->attributes[$object->table_element]['type'])) {
			return 0;
		}

		$today = dol_now('tzuser');
		foreach ($extrafields->attributes[$object->table_element]['type'] as $key => $type) {
			if ($type !== 'date') {
				continue;
			}
			$opt_key = 'options_'.$key;
			$current = isset($object->array_options[$opt_key]) ? $object->array_options[$opt_key] : null;
			if ($current === null || $current === '' || $current === 0 || $current === '0') {
				$object->array_options[$opt_key] = $today;
			}
		}

		return 0;
	}
}

