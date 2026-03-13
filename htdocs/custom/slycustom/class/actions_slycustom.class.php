<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/slycustom/class/actions_slycustom.class.php
 *	\ingroup    slycustom
 *	\brief      Hook actions for SLY Custom module
 */

/**
 *	Class ActionsSlycustom
 */
class ActionsSlycustom
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/** @var string|null Output for list hooks (used by HookManager) */
	public $resprints;

	/** @var array Hook return data (e.g. menu array for menuLeftMenuItems), set into HookManager->resArray */
	public $results = array();

	/**
	 * @var int Hook priority (higher = earlier)
	 */
	public $priority = 50;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Overloading the doActions function
	 *
	 * @param array         $parameters Hook parameters
	 * @param CommonObject  $object     Object
	 * @param string        $action     Current action
	 * @return int                     <0 on error, 0=nothing done, >0=replace default
	 */
	public function doActions($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;

		// Customer invoice: migrate old PDF model to system default (sponge_SLY_consignee was removed)
		if (is_object($object) && get_class($object) === 'Facture' && !empty($object->model_pdf)) {
			$oldModels = array('sponge_SLY_consignee', 'sponge SLY consignee');
			if (in_array($object->model_pdf, $oldModels, true)) {
				$object->model_pdf = getDolGlobalString('FACTURE_ADDON_PDF') ?: 'sly_invoice';
			}
		}

		// Supplier invoice: migrate old PDF model to system default (same pattern as customer invoice)
		if (is_object($object) && get_class($object) === 'FactureFournisseur' && !empty($object->model_pdf)) {
			$oldSupplierModels = array(); // Add old SLY supplier template names here if any were removed
			if (in_array($object->model_pdf, $oldSupplierModels, true)) {
				$object->model_pdf = getDolGlobalString('INVOICE_SUPPLIER_ADDON_PDF') ?: 'sly_debitnote';
			}
		}

		// Add SLY list columns to arrayfields (zero core patch)
		if (isset($parameters['arrayfields']) && is_array($parameters['arrayfields'])) {
			$this->addListArrayFields($parameters['arrayfields'], $object);
		}

		$massaction = GETPOST('massaction', 'alpha');
		$toselect = GETPOST('toselect', 'array');

		// Shipment list: massaction updateships
		if ($massaction === 'updateships' && is_array($toselect) && count($toselect) > 0) {
			return $this->doActionsShipmentList($parameters, $object, $action);
		}

		// Expedition card: updateships or confirm_modif
		if (($action == 'updateships' || $action == 'confirm_modif') && GETPOSTINT('id') > 0) {
			return $this->doActionsExpeditionCard($parameters, $object, $action);
		}

		return 0;
	}

	/**
	 * Add mass actions into selectMassAction().
	 *
	 * @param array        $parameters  Hook parameters (currentcontext, ...)
	 * @param CommonObject $object      Context object
	 * @param string       $action      Current action
	 * @param HookManager  $hookmanager Hook manager
	 * @return int
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;

		if (empty($parameters['currentcontext']) || !in_array($parameters['currentcontext'], array('shipmentlist'), true)) {
			return 0;
		}
		if (!isModEnabled('slycustom') || empty($conf->global->API_KEY_SHIPSGO) || !$user->hasRight('expedition', 'creer')) {
			return 0;
		}

		$langs->load("slycustom@slycustom");
		$this->resprints = '<option value="updateships" data-html="'.dol_escape_htmltag(img_picto('', 'calendar', 'class="pictofixedwidth"').$langs->trans("UpdateShips")).'">'.img_picto('', 'calendar', 'class="pictofixedwidth"').$langs->trans("UpdateShips").'</option>';
		return 0;
	}

	/**
	 * Orders index: add SELECT columns for multicurrency (core calls this and appends to SQL).
	 *
	 * @param array         $parameters Hook parameters
	 * @param CommonObject  $object     Object (unused)
	 * @param string        $action     Action (unused)
	 * @param HookManager   $hookmanager Hook manager (unused)
	 * @return int 0
	 */
	public function ordersIndexSelectSuffix($parameters, $object, $action, $hookmanager)
	{
		if (isModEnabled('multicurrency')) {
			$this->resprints = ", c.multicurrency_total_ht, c.multicurrency_code";
		}
		return 0;
	}

	/**
	 * Orders index: format amount for one row (multicurrency or local).
	 *
	 * @param array         $parameters Hook parameters ('obj' => fetched row)
	 * @param CommonObject  $object     Object (unused)
	 * @param string        $action     Action (unused)
	 * @param HookManager   $hookmanager Hook manager (unused)
	 * @return int 0
	 */
	public function ordersIndexRowAmount($parameters, $object, $action, $hookmanager)
	{
		global $conf, $langs;
		$obj = isset($parameters['obj']) ? $parameters['obj'] : null;
		if (!$obj) {
			return 0;
		}
		if (isModEnabled('multicurrency') && !empty($obj->multicurrency_code)) {
			$currency = $obj->multicurrency_code;
			$amount = $obj->multicurrency_total_ht;
		} else {
			$currency = !empty($conf->currency) ? $conf->currency : '';
			$amount = isset($obj->total_ht) ? $obj->total_ht : 0;
		}
		$this->resprints = '<span class="amount">'.price($amount, 1, $langs, 0, -1, -1, $currency).'</span>';
		return 0;
	}

	/**
	 * Invoice index (compta): add SELECT columns for multicurrency.
	 *
	 * @param array         $parameters Hook parameters ('type' => 'customer'|'supplier'|'orderToBill')
	 * @param CommonObject  $object     Object (unused)
	 * @param string        $action     Action (unused)
	 * @param HookManager   $hookmanager Hook manager (unused)
	 * @return int 0
	 */
	public function invoiceIndexSelectSuffix($parameters, $object, $action, $hookmanager)
	{
		$type = isset($parameters['type']) ? $parameters['type'] : '';
		if (!isModEnabled('multicurrency')) {
			return 0;
		}
		if ($type === 'customer') {
			$this->resprints = ", f.multicurrency_code, f.multicurrency_total_ht, f.multicurrency_total_ttc";
		} elseif ($type === 'supplier') {
			$this->resprints = ", ff.multicurrency_code, ff.multicurrency_total_ht, ff.multicurrency_total_ttc";
		} elseif ($type === 'orderToBill' && isModEnabled('order')) {
			$this->resprints = ", c.multicurrency_code, c.multicurrency_total_ht, c.multicurrency_total_ttc";
		}
		return 0;
	}

	/**
	 * Invoice index (compta): return amount_ht, amount_ttc, currency for one row (set in $this->results for resArray).
	 *
	 * @param array         $parameters Hook parameters ('obj' => row, 'type' => 'customer'|'supplier'|'orderToBill')
	 * @param CommonObject  $object     Object (unused)
	 * @param string        $action     Action (unused)
	 * @param HookManager   $hookmanager Hook manager (unused)
	 * @return int 0
	 */
	public function invoiceIndexAmountDisplay($parameters, $object, $action, $hookmanager)
	{
		global $conf;
		$obj = isset($parameters['obj']) ? $parameters['obj'] : null;
		$type = isset($parameters['type']) ? $parameters['type'] : '';
		if (!$obj || !$type) {
			return 0;
		}
		if (!isModEnabled('multicurrency') || empty($obj->multicurrency_code)) {
			return 0;
		}
		$this->results = array(
			'amount_ht' => $obj->multicurrency_total_ht,
			'amount_ttc' => $obj->multicurrency_total_ttc,
			'currency' => $obj->multicurrency_code,
		);
		return 0;
	}

	/**
	 * Add SLY custom columns to list arrayfields (no core patch)
	 *
	 * @param array         $arrayfields By reference
	 * @param CommonObject  $object      List context object (Facture, Commande, ...)
	 */
	protected function addListArrayFields(array &$arrayfields, $object)
	{
		if (empty($object->element)) {
			return;
		}
		// Invoice list: source order (from element_element link facture <- commande), right after Invoice No (f.ref=5)
		if ($object->element == 'facture') {
			$arrayfields['sly_order_ref'] = array(
				'label' => 'SourceOrder',
				'langfile' => 'slycustom@slycustom',
				'checked' => '1',
				'position' => 7,
			);
		}
		// Customer order list: source purchase order (commande -> commande_fournisseur)
		if ($object->element == 'commande') {
			$arrayfields['sly_source_supplier_order_ref'] = array(
				'label' => 'SourcePurchaseOrder',
				'langfile' => 'slycustom@slycustom',
				'checked' => '0',
				'position' => 117,
			);
		}
		// Supplier order list: source sales order (commande_fournisseur <- commande)
		if ($object->element == 'order_supplier') {
			$arrayfields['sly_source_order_ref'] = array(
				'label' => 'SourceOrder',
				'langfile' => 'slycustom@slycustom',
				'checked' => '0',
				'position' => 117,
			);
		}
		// Supplier invoice list: source purchase order (facture_fourn <- commande_fournisseur), after Ref like customer list
		if ($object->element == 'invoice_supplier') {
			$arrayfields['sly_source_supplier_order_ref'] = array(
				'label' => 'SourceOrder',
				'langfile' => 'slycustom@slycustom',
				'checked' => '1',
				'position' => 7,
				'enabled' => '1',
			);
		}
	}

	/**
	 * Expedition card actions: updateships, confirm_modif
	 *
	 * @param array        $parameters Hook parameters
	 * @param Expedition   $object     Object
	 * @param string       $action     Current action
	 * @return int
	 */
	protected function doActionsExpeditionCard($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;

		$id = GETPOSTINT('id');
		if ($id <= 0) {
			return 0;
		}

		// SLY ShipsGo: action=updateships - update shipment status from API
		if ($action == 'updateships' && isModEnabled('slycustom') && !empty($conf->global->API_KEY_SHIPSGO) && $user->hasRight('expedition', 'creer')) {
			// Enforce CSRF token check even if MAIN_SECURITY_CSRF_WITH_TOKEN is not strict enough.
			$token = GETPOST('token', 'alpha');
			if (empty($token) || $token !== currentToken()) {
				accessforbidden('Invalid CSRF token');
			}
			require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_API.class.php';
			$object->fetch($id);
			if ($object->status > 0 && !empty($object->tracking_number)) {
				$shipsGo = new ShipsGo_API($conf->global->API_KEY_SHIPSGO);
				$sqlef = "SELECT sailingstatusid FROM ".MAIN_DB_PREFIX."expedition_extrafields WHERE fk_object = ".(int) $object->id;
				$resef = $this->db->query($sqlef);
				$current_status = 0;
				if ($resef && $rowef = $this->db->fetch_object($resef)) {
					$current_status = (int) $rowef->sailingstatusid;
				}
				if ($current_status != 3 && $current_status != 4) {
					$ship_status_list = $shipsGo->GetContainerInfo($object->tracking_number);
					$ship_status = is_array($ship_status_list) && isset($ship_status_list[0]) ? $ship_status_list[0] : (is_array($ship_status_list) ? $ship_status_list : array());
					if (!empty($ship_status['Message']) && $ship_status['Message'] == 'Success') {
						$updatesql = "UPDATE ".MAIN_DB_PREFIX."expedition_extrafields SET";
						$updatesql .= " sailingstatusid = ".(int) ($ship_status['SailingStatusId'] ?? 0);
						$updatesql .= ", pol = '".$this->db->escape($ship_status['Pol'] ?? '')."'";
						if (!empty($ship_status['DepartureDate'])) {
							$updatesql .= ", atd = '".$this->db->escape(date('Y-m-d', strtotime(str_replace('/', '-', $ship_status['DepartureDate']))))."'";
						}
						$updatesql .= ", pod = '".$this->db->escape($ship_status['Pod'] ?? '')."'";
						if (!empty($ship_status['ArrivalDate'])) {
							$updatesql .= ", ata = '".$this->db->escape(date('Y-m-d', strtotime(str_replace('/', '-', $ship_status['ArrivalDate']))))."'";
						}
						$updatesql .= ", livemapurl = '".$this->db->escape($ship_status['LiveMapUrl'] ?? '')."'";
						$updatesql .= ", updatedtime = '".$this->db->idate(dol_now())."'";
						$updatesql .= " WHERE fk_object = ".(int) $object->id;
						$this->db->query($updatesql);
						$langs->load("slycustom@slycustom");
						setEventMessages($langs->trans("ShipmentUpdated"), null);
					}
				}
			}
			header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
			exit;
		}

		// SLY: action=confirm_modif - unvalidate shipment (set back to draft)
		if ($action == 'confirm_modif' && GETPOST('confirm') == 'yes' && getDolGlobalString('SLYCUSTOM_SHIPPING_ENABLE_UNVALIDATE', 1)
			&& $user->hasRight('expedition', 'creer')
			&& (!getDolGlobalString('MAIN_USE_ADVANCED_PERMS') || $user->hasRight('expedition', 'shipping_advance', 'validate'))) {
			$object->fetch($id);
			$result = $object->setDraft($user);
			if ($result < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
			} else {
				header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
				exit;
			}
		}

		return 0;
	}

	/**
	 * Shipment list actions: massaction updateships
	 *
	 * @param array      $parameters Hook parameters
	 * @param Expedition $object     Object
	 * @param string     $action     Current action
	 * @return int
	 */
	protected function doActionsShipmentList($parameters, &$object, &$action)
	{
		global $conf, $user;

		$massaction = GETPOST('massaction', 'alpha');
		$toselect = GETPOST('toselect', 'array');

		if ($massaction === 'updateships' && isModEnabled('slycustom') && !empty($conf->global->API_KEY_SHIPSGO) && !empty($toselect) && is_array($toselect) && $user->hasRight('expedition', 'creer')) {
			// Enforce CSRF token check even if MAIN_SECURITY_CSRF_WITH_TOKEN is not strict enough.
			$token = GETPOST('token', 'alpha');
			if (empty($token) || $token !== currentToken()) {
				accessforbidden('Invalid CSRF token');
			}
			require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
			require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_API.class.php';
			$objecttmp = new Expedition($this->db);
			$shipsGotmp = new ShipsGo_API($conf->global->API_KEY_SHIPSGO);
			foreach ($toselect as $expid) {
				$result = $objecttmp->fetch((int) $expid);
				if ($result > 0 && !empty($objecttmp->tracking_number)) {
					$sqlcheck = "SELECT sailingstatusid FROM ".MAIN_DB_PREFIX."expedition_extrafields WHERE fk_object = ".(int) $expid;
					$resqlcheck = $this->db->query($sqlcheck);
					$objcheck = $resqlcheck ? $this->db->fetch_object($resqlcheck) : null;
					$current_status = $objcheck ? (int) $objcheck->sailingstatusid : 0;
					if ($current_status != 3 && $current_status != 4) {
						$ship_status_list = $shipsGotmp->GetContainerInfo($objecttmp->tracking_number);
						$ship_status = is_array($ship_status_list) && isset($ship_status_list[0]) ? $ship_status_list[0] : (is_array($ship_status_list) ? $ship_status_list : array());
						if (!empty($ship_status['Message']) && $ship_status['Message'] == 'Success') {
							$updatesql = "UPDATE ".MAIN_DB_PREFIX."expedition_extrafields SET";
							$updatesql .= " sailingstatusid = ".(int) ($ship_status['SailingStatusId'] ?? 0);
							$updatesql .= ", pol = '".$this->db->escape($ship_status['Pol'] ?? '')."'";
							if (!empty($ship_status['DepartureDate'])) {
								$updatesql .= ", atd = '".$this->db->escape(date('Y-m-d', strtotime(str_replace('/', '-', $ship_status['DepartureDate']))))."'";
							}
							$updatesql .= ", pod = '".$this->db->escape($ship_status['Pod'] ?? '')."'";
							if (!empty($ship_status['ArrivalDate'])) {
								$updatesql .= ", ata = '".$this->db->escape(date('Y-m-d', strtotime(str_replace('/', '-', $ship_status['ArrivalDate']))))."'";
							}
							$updatesql .= ", livemapurl = '".$this->db->escape($ship_status['LiveMapUrl'] ?? '')."'";
							$updatesql .= ", updatedtime = '".$this->db->idate(dol_now())."'";
							$updatesql .= " WHERE fk_object = ".(int) $expid;
							$this->db->query($updatesql);
						}
					}
				}
			}
		}

		return 0;
	}

	/**
	 * Overloading the formConfirm function
	 *
	 * @param array        $parameters Hook parameters (formConfirm)
	 * @param CommonObject $object     Object
	 * @param string       $action     Current action
	 * @return int                    <0 on error, 0=nothing done, >0=replace default
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
	 * Overloading the addMoreActionsButtons function
	 *
	 * @param array        $parameters Hook parameters
	 * @param CommonObject $object     Object
	 * @param string       $action    Current action
	 * @return int                    <0 on error, 0=nothing done, >0=replace default
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;

		if (empty($object->element) || $object->element != 'shipping') {
			return 0;
		}

		// SLY ShipsGo: Update Ships button
		if (isModEnabled('slycustom') && !empty($conf->global->API_KEY_SHIPSGO) && $object->status > 0 && !empty($object->tracking_number) && $user->hasRight('expedition', 'creer')) {
			$langs->load("slycustom@slycustom");
			print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=updateships&token='.newToken().'">'.$langs->trans("UpdateShips").'</a></div>';
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
	 * Add column for "Include sales terms" in builddoc form (FormFile::showdocuments).
	 * Only for sales order (commande) so the core adds one extra <th>.
	 *
	 * @return void
	 */
	public function formBuilddocLineOptions()
	{
		// Method exists so that the core adds one extra column for our formBuilddocOptions content
	}

	/**
	 * Add "Include sales terms" row on order document form: checkbox + flat template list.
	 * All UI and template list logic lives in SLY module (no core SLY-specific code).
	 *
	 * @param array        $parameters Hook parameters (modulepart, id, socid, colspan, ...)
	 * @param CommonObject $object     Object (e.g. Commande)
	 * @return int                     0
	 */
	public function formBuilddocOptions($parameters, $object)
	{
		global $hookmanager, $langs, $conf;

		if (empty($parameters['modulepart']) || $parameters['modulepart'] !== 'commande') {
			return 0;
		}
		if (!isModEnabled('slycustom')) {
			return 0;
		}

		$langs->load('slycustom@slycustom');
		$langs->load('languages');
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$terms_base = DOL_DATA_ROOT.'/mycompany/terms';
		$sly_terms_flat = array();
		if (is_dir(dol_osencode($terms_base))) {
			$default_label = $langs->trans('Default');
			if ($default_label === 'Default' && $langs->defaultlang != 'en_US') {
				$default_label = 'Default';
			}
			$all = dol_dir_list($terms_base, 'files', 0, '', array(), 'name', SORT_ASC, 0);
			foreach ($all as $e) {
				if (preg_match('/\.pdf$/i', $e['name'])) {
					$name_no_ext = preg_replace('/\.pdf$/i', '', $e['name']);
					$sly_terms_flat[] = array('rel' => $e['name'], 'lang_label' => $default_label, 'langcode' => '', 'filename_display' => $name_no_ext);
				}
			}
			$list_dirs = dol_dir_list($terms_base, 'directories', 0, '', array(), 'name', SORT_ASC, 0);
			foreach ($list_dirs as $ent) {
				$lc = isset($ent['name']) ? trim($ent['name']) : '';
				if ($lc === '' || $lc === '.' || $lc === '..' || $lc === '-1' || is_numeric($lc)) {
					continue;
				}
				$lc = basename($lc);
				$lang_dir = $terms_base.'/'.$lc;
				if (!is_dir(dol_osencode($lang_dir))) {
					continue;
				}
				// Use Language_xx_YY so label follows current UI language (e.g. 中文 when UI is Chinese)
				$lang_key = 'Language_'.str_replace('-', '_', $lc);
				$lang_label = $langs->trans($lang_key);
				if ($lang_label === $lang_key) {
					$lang_label = $lc;
				}
				$files = dol_dir_list($lang_dir, 'files', 0, '', array(), 'name', SORT_ASC, 0);
				foreach ($files as $f) {
					if (isset($f['name']) && preg_match('/\.pdf$/i', $f['name'])) {
						$name_no_ext = preg_replace('/\.pdf$/i', '', $f['name']);
						$sly_terms_flat[] = array('rel' => $lc.'/'.$f['name'], 'lang_label' => $lang_label, 'langcode' => $lc, 'filename_display' => $name_no_ext);
					}
				}
			}
		}

		$add_terms_label = $langs->trans('SLYCUSTOM_ADD_TERMS_LABEL');
		if ($add_terms_label === 'SLYCUSTOM_ADD_TERMS_LABEL') {
			$add_terms_label = 'Include sales terms';
		}
		$no_tpl_msg = $langs->trans('SLYCUSTOM_TERMS_NO_TEMPLATE');
		if ($no_tpl_msg === 'SLYCUSTOM_TERMS_NO_TEMPLATE') {
			$no_tpl_msg = 'No sales terms template for this language';
		}
		$add_terms_checked = GETPOSTINT('add_terms') ? ' checked' : '';
		$selected_tpl = GETPOST('add_terms_template', 'alphanohtml');
		$colspan = isset($parameters['colspan']) ? (int) $parameters['colspan'] : 10;

		$this->resprints .= '<tr><td colspan="'.$colspan.'" class="oddeven">';
		$this->resprints .= '<label class="valignmiddle"><input type="checkbox" name="add_terms" id="sly_add_terms_cb" value="1"'.$add_terms_checked.'> '.dol_escape_htmltag($add_terms_label).'</label>';
		$this->resprints .= ' <span id="sly_terms_template_block" style="margin-left:8px;">';
		if (count($sly_terms_flat) === 0) {
			$this->resprints .= '<span class="opacitymedium">'.dol_escape_htmltag($no_tpl_msg).'</span>';
		} elseif (count($sly_terms_flat) === 1) {
			$one = $sly_terms_flat[0];
			$one_display = ($one['langcode'] !== '' ? picto_from_langcode($one['langcode'], 'class="saturatemedium paddingrightonly"').' ' : '')
				.dol_escape_htmltag($one['lang_label']).': '.dol_escape_htmltag($one['filename_display']);
			$this->resprints .= $one_display;
			$this->resprints .= '<input type="hidden" name="add_terms_template" value="'.dol_escape_htmltag($one['rel']).'">';
		} else {
			$this->resprints .= '<select name="add_terms_template" id="sly_add_terms_template" class="flat maxwidth200">';
			foreach ($sly_terms_flat as $idx => $item) {
				$sel = ($selected_tpl !== '' && $selected_tpl === $item['rel']) || ($selected_tpl === '' && $idx === 0) ? ' selected' : '';
				// Same format as FormAdmin::select_language: flag icon + "语言：文件名" for combobox HTML display
				$opt_html = ($item['langcode'] !== '' ? picto_from_langcode($item['langcode'], 'class="saturatemedium"').' ' : '')
					.dol_escape_htmltag($item['lang_label']).': '.dol_escape_htmltag($item['filename_display']);
				$this->resprints .= '<option value="'.dol_escape_htmltag($item['rel']).'"'.$sel.' data-html="'.dol_escape_htmltag($opt_html).'">'.$opt_html.'</option>';
			}
			$this->resprints .= '</select>';
			if (!empty($conf->use_javascript_ajax)) {
				include_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
				$this->resprints .= ajax_combobox('sly_add_terms_template');
			}
		}
		$this->resprints .= '</span>';
		$this->resprints .= '</td></tr>';
		$this->resprints .= '<script nonce="'.getNonce().'">document.addEventListener("DOMContentLoaded",function(){var cb=document.getElementById("sly_add_terms_cb");var block=document.getElementById("sly_terms_template_block");function sync(){if(block)block.style.display=cb&&cb.checked?"inline":"none";}if(cb){cb.addEventListener("change",sync);sync();}var form=document.getElementById("builddoc_form");if(form){form.addEventListener("submit",function(){if(cb&&cb.checked){var m=window.location.search.match(/[?&]id=(\d+)/);var id=m?m[1]:"";if(id){form.action="'.dol_escape_js(DOL_URL_ROOT.'/custom/slycustom/builddoc_order.php').'?id="+id;}}},false);}});</script>';

		return 0;
	}

	/**
	 * Hook builddocMoreParams (no-op when core is unmodified).
	 * When using zero-core-modification flow, sales terms are handled by custom/slycustom/builddoc_order.php
	 * instead. Kept for compatibility if core later adds this hook.
	 *
	 * @param array        $parameters moreparams (by ref), object
	 * @param CommonObject $object     Order etc.
	 * @return int                     0
	 */
	public function builddocMoreParams($parameters, $object)
	{
		return 0;
	}

	// -------- List hooks (zero core patch: extra columns) --------

	/**
	 * Add SELECT fields for SLY list columns
	 *
	 * @param array        $parameters Hook parameters
	 * @param CommonObject $object     Object
	 * @param string       $action     Action
	 * @return int
	 */
	public function printFieldListSelect($parameters, &$object, &$action)
	{
		$this->resprints = '';
		if (empty($object->element)) {
			return 0;
		}
		if ($object->element == 'facture') {
			$this->resprints = ', cord.ref as sly_order_ref, cord.rowid as sly_order_id';
		}
		if ($object->element == 'commande') {
			$this->resprints = ', cf_src.ref as sly_source_supplier_order_ref, cf_src.rowid as sly_source_supplier_order_id';
		}
		if ($object->element == 'order_supplier') {
			$this->resprints = ', cord_src.ref as sly_source_order_ref, cord_src.rowid as sly_source_order_id';
		}
		if ($object->element == 'invoice_supplier') {
			$this->resprints = ', cf_supp_src.ref as sly_source_supplier_order_ref, cf_supp_src.rowid as sly_source_supplier_order_id';
		}
		return 0;
	}

	/**
	 * Add JOIN for SLY list columns
	 *
	 * @param array        $parameters Hook parameters
	 * @param CommonObject $object     Object
	 * @param string       $action     Action
	 * @return int
	 */
	public function printFieldListFrom($parameters, &$object, &$action)
	{
		$this->resprints = '';
		if (empty($object->element)) {
			return 0;
		}
		if ($object->element == 'facture') {
			// Both link dirs via UNION (inv_id=facture, cmd_id=commande). One invoice may link N orders.
			$p = MAIN_DB_PREFIX;
			$this->resprints = " LEFT JOIN (SELECT ee.fk_target AS inv_id, ee.fk_source AS cmd_id FROM ".$p."element_element ee WHERE ee.targettype = 'facture' AND ee.sourcetype = 'commande'";
			$this->resprints .= " UNION ALL SELECT ee.fk_source AS inv_id, ee.fk_target AS cmd_id FROM ".$p."element_element ee WHERE ee.sourcetype = 'facture' AND ee.targettype = 'commande') eeord ON eeord.inv_id = f.rowid";
			$this->resprints .= " LEFT JOIN ".$p."commande as cord ON cord.rowid = eeord.cmd_id";
		}
		if ($object->element == 'commande') {
			$p = MAIN_DB_PREFIX;
			// Both link dirs via UNION (cmd_id=commande, po_id=PO). One order with N POs may show N rows.
			$this->resprints = " LEFT JOIN (SELECT ee.fk_source AS cmd_id, ee.fk_target AS po_id FROM ".$p."element_element ee WHERE ee.sourcetype = 'commande' AND ee.targettype = 'order_supplier' UNION ALL SELECT ee.fk_target AS cmd_id, ee.fk_source AS po_id FROM ".$p."element_element ee WHERE ee.sourcetype = 'order_supplier' AND ee.targettype = 'commande') ee_cf ON ee_cf.cmd_id = c.rowid";
			$this->resprints .= " LEFT JOIN ".$p."commande_fournisseur cf_src ON cf_src.rowid = ee_cf.po_id";
		}
		if ($object->element == 'order_supplier') {
			// Same pattern as commande: UNION derived table (po_id, cmd_id) from both link dirs, no GROUP BY. One PO with N sales orders may show N rows.
			$p = MAIN_DB_PREFIX;
			$this->resprints = " LEFT JOIN (SELECT ee.fk_target AS po_id, ee.fk_source AS cmd_id FROM ".$p."element_element ee WHERE ee.sourcetype = 'commande' AND ee.targettype = 'order_supplier' UNION ALL SELECT ee.fk_source AS po_id, ee.fk_target AS cmd_id FROM ".$p."element_element ee WHERE ee.sourcetype = 'order_supplier' AND ee.targettype = 'commande') ee_po ON ee_po.po_id = cf.rowid";
			$this->resprints .= " LEFT JOIN ".$p."commande cord_src ON cord_src.rowid = ee_po.cmd_id";
		}
		if ($object->element == 'invoice_supplier') {
			// Both link dirs via UNION (inv_id=supplier invoice, po_id=commande_fournisseur). One invoice may link N POs.
			$p = MAIN_DB_PREFIX;
			$this->resprints = " LEFT JOIN (SELECT ee.fk_target AS inv_id, ee.fk_source AS po_id FROM ".$p."element_element ee WHERE (ee.targettype = 'invoice_supplier' OR ee.targettype = 'facture_fourn') AND ee.sourcetype = 'order_supplier'";
			$this->resprints .= " UNION ALL SELECT ee.fk_source AS inv_id, ee.fk_target AS po_id FROM ".$p."element_element ee WHERE (ee.sourcetype = 'invoice_supplier' OR ee.sourcetype = 'facture_fourn') AND ee.targettype = 'order_supplier') ee_cfsupp ON ee_cfsupp.inv_id = f.rowid";
			$this->resprints .= " LEFT JOIN ".$p."commande_fournisseur as cf_supp_src ON cf_supp_src.rowid = ee_cfsupp.po_id";
		}
		return 0;
	}

	/**
	 * Shipment list: replace order join with bidirectional element_element (shipping <-> commande)
	 *
	 * @param array     $parameters Hook parameters ('order_join' => &$orderJoin)
	 * @param Expedition $object    Object (shipment list context)
	 * @param string    $action     Action
	 * @return int
	 */
	public function getShipmentListOrderJoin($parameters, &$object, &$action)
	{
		if (!isset($parameters['order_join']) || !is_string($parameters['order_join'])) {
			return 0;
		}
		$p = MAIN_DB_PREFIX;
		$parameters['order_join'] = " LEFT JOIN (SELECT ee.fk_target AS ship_id, ee.fk_source AS cmd_id FROM ".$p."element_element ee WHERE ee.targettype = 'shipping' AND ee.sourcetype = 'commande'";
		$parameters['order_join'] .= " UNION ALL SELECT ee.fk_source AS ship_id, ee.fk_target AS cmd_id FROM ".$p."element_element ee WHERE ee.sourcetype = 'shipping' AND ee.targettype = 'commande') eecommande ON eecommande.ship_id = e.rowid";
		$parameters['order_join'] .= " LEFT JOIN ".$p."commande as c ON (c.rowid = eecommande.cmd_id)";
		return 0;
	}

	/**
	 * Add search row cell(s) for SLY list columns (search filter inputs)
	 *
	 * @param array        $parameters Hook parameters (arrayfields)
	 * @param CommonObject $object     Object
	 * @param string       $action     Action
	 * @return int
	 */
	public function printFieldListOption($parameters, &$object, &$action)
	{
		global $langs;
		$this->resprints = '';
		$arrayfields = $parameters['arrayfields'] ?? array();
		$insert_after = $parameters['insert_after'] ?? '';
		if (empty($object->element)) {
			return 0;
		}
		$langs->load("slycustom@slycustom");
		// Called after f.ref: output only Source order for facture or invoice_supplier
		if ($insert_after === 'f.ref') {
			if ($object->element == 'facture' && !empty($arrayfields['sly_order_ref']['checked'])) {
				$search_sly_order_ref = GETPOST('search_sly_order_ref', 'alphanohtml');
				$this->resprints = '<td class="liste_titre"><input class="flat maxwidth50imp" type="text" name="search_sly_order_ref" value="'.dol_escape_htmltag($search_sly_order_ref).'"></td>';
			}
			if ($object->element == 'invoice_supplier' && !empty($arrayfields['sly_source_supplier_order_ref']['checked'])) {
				$search_sly_supplier_order_ref = GETPOST('search_sly_supplier_order_ref', 'alphanohtml');
				$this->resprints = '<td class="liste_titre"><input class="flat maxwidth50imp" type="text" name="search_sly_supplier_order_ref" value="'.dol_escape_htmltag($search_sly_supplier_order_ref).'"></td>';
			}
			return 0;
		}
		// Called after c.ref: Source purchase order for sales order list
		if ($insert_after === 'c.ref' && $object->element == 'commande' && !empty($arrayfields['sly_source_supplier_order_ref']['checked'])) {
			$v = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_sly_source_supplier_order_ref', 'alphanohtml');
			$this->resprints = '<td class="liste_titre"><input class="flat maxwidth50imp" type="text" name="search_sly_source_supplier_order_ref" value="'.dol_escape_htmltag($v).'"></td>';
			return 0;
		}
		// Called after cf.ref: Source order for purchase order list
		if ($insert_after === 'cf.ref' && $object->element == 'order_supplier' && !empty($arrayfields['sly_source_order_ref']['checked'])) {
			$v = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_sly_source_order_ref', 'alphanohtml');
			$this->resprints = '<td class="liste_titre"><input class="flat maxwidth50imp" type="text" name="search_sly_source_order_ref" value="'.dol_escape_htmltag($v).'"></td>';
			return 0;
		}
		// Normal call at end of row: facture/invoice_supplier/commande/order_supplier already have Source after Ref, so skip
		if ($object->element == 'facture' || $object->element == 'invoice_supplier' || $object->element == 'commande' || $object->element == 'order_supplier') {
			return 0;
		}
		return 0;
	}

	/**
	 * Add WHERE conditions for SLY list search (e.g. filter by source order ref)
	 *
	 * @param array        $parameters Hook parameters
	 * @param CommonObject $object     Object
	 * @param string       $action     Action
	 * @return int
	 */
	public function printFieldListWhere($parameters, &$object, &$action)
	{
		$this->resprints = '';
		if (empty($object->element)) {
			return 0;
		}
		if ($object->element == 'facture') {
			$search_sly_order_ref = GETPOST('search_sly_order_ref', 'alphanohtml');
			if ($search_sly_order_ref !== '') {
				$this->resprints = natural_search('cord.ref', $search_sly_order_ref, 0, 0);
			}
		}
		if ($object->element == 'invoice_supplier') {
			$search_sly_supplier_order_ref = GETPOST('search_sly_supplier_order_ref', 'alphanohtml');
			if ($search_sly_supplier_order_ref !== '') {
				$this->resprints = natural_search('cf_supp_src.ref', $search_sly_supplier_order_ref, 0, 0);
			}
		}
		if ($object->element == 'commande') {
			$v = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_sly_source_supplier_order_ref', 'alphanohtml');
			if ($v !== '') {
				$this->resprints = natural_search('cf_src.ref', $v, 0, 0);
			}
		}
		if ($object->element == 'order_supplier') {
			$v = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_sly_source_order_ref', 'alphanohtml');
			if ($v !== '') {
				$this->resprints = natural_search('cord_src.ref', $v, 0, 0);
			}
		}
		return 0;
	}

	/**
	 * Add search params to $param for pagination (preserve SLY search values)
	 *
	 * @param array        $parameters Hook parameters (param by ref)
	 * @param CommonObject $object     Object
	 * @param string       $action     Action
	 * @return int
	 */
	public function printFieldListSearchParam($parameters, &$object, &$action)
	{
		$this->resprints = '';
		if (empty($object->element)) {
			return 0;
		}
		if ($object->element == 'facture') {
			$search_sly_order_ref = GETPOST('search_sly_order_ref', 'alphanohtml');
			if ($search_sly_order_ref !== '') {
				$this->resprints = '&search_sly_order_ref='.urlencode($search_sly_order_ref);
			}
		}
		if ($object->element == 'invoice_supplier') {
			$search_sly_supplier_order_ref = GETPOST('search_sly_supplier_order_ref', 'alphanohtml');
			if ($search_sly_supplier_order_ref !== '') {
				$this->resprints = '&search_sly_supplier_order_ref='.urlencode($search_sly_supplier_order_ref);
			}
		}
		if ($object->element == 'commande') {
			$v = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_sly_source_supplier_order_ref', 'alphanohtml');
			if ($v !== '') {
				$this->resprints = '&search_sly_source_supplier_order_ref='.urlencode($v);
			}
		}
		if ($object->element == 'order_supplier') {
			$v = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_sly_source_order_ref', 'alphanohtml');
			if ($v !== '') {
				$this->resprints = '&search_sly_source_order_ref='.urlencode($v);
			}
		}
		return 0;
	}

	/**
	 * Add column title(s) for SLY list columns
	 *
	 * @param array        $parameters Hook parameters (arrayfields, param, sortfield, sortorder, totalarray)
	 * @param CommonObject $object     Object
	 * @param string       $action     Action
	 * @return int
	 */
	public function printFieldListTitle($parameters, &$object, &$action)
	{
		global $langs;
		$arrayfields = $parameters['arrayfields'] ?? array();
		$param = $parameters['param'] ?? '';
		$sortfield = $parameters['sortfield'] ?? '';
		$sortorder = $parameters['sortorder'] ?? '';
		$insert_after = $parameters['insert_after'] ?? '';
		$langs->load("slycustom@slycustom");
		// Payment card: invoice list – add Source order column header
		if (is_object($object) && (get_class($object) === 'Paiement' || (isset($object->table_element) && $object->table_element === 'paiement'))) {
			$this->resprints = '<td>'.$langs->trans("SourceOrder").'</td>';
			return 0;
		}
		// Payment create page: unpaid invoices list – skip (column added directly in core paiement.php)
		if (!empty($parameters['context']) && $parameters['context'] === 'payment_unpaid_invoices') {
			return 0;
		}
		if (empty($object->element)) {
			return 0;
		}
		// Called after f.ref: output only Source order title for facture or invoice_supplier
		if ($insert_after === 'f.ref') {
			if ($object->element == 'facture' && !empty($arrayfields['sly_order_ref']['checked'])) {
				if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield'])) {
					$parameters['totalarray']['nbfield']++;
				}
				$this->resprints = '';
				ob_start();
				print_liste_field_titre($langs->trans("SourceOrder"), $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder);
				$this->resprints = ob_get_clean();
			}
			if ($object->element == 'invoice_supplier' && !empty($arrayfields['sly_source_supplier_order_ref']['checked'])) {
				if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield'])) {
					$parameters['totalarray']['nbfield']++;
				}
				$this->resprints = '';
				ob_start();
				print_liste_field_titre($langs->trans("SourceOrder"), $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder);
				$this->resprints = ob_get_clean();
			}
			return 0;
		}
		// Called after c.ref: Source purchase order title for sales order list
		if ($insert_after === 'c.ref' && $object->element == 'commande' && !empty($arrayfields['sly_source_supplier_order_ref']['checked'])) {
			if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield'])) {
				$parameters['totalarray']['nbfield']++;
			}
			ob_start();
			print_liste_field_titre($langs->trans("SourcePurchaseOrder"), $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder);
			$this->resprints = ob_get_clean();
			return 0;
		}
		// Called after cf.ref: Source order title for purchase order list
		if ($insert_after === 'cf.ref' && $object->element == 'order_supplier' && !empty($arrayfields['sly_source_order_ref']['checked'])) {
			if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield'])) {
				$parameters['totalarray']['nbfield']++;
			}
			ob_start();
			print_liste_field_titre($langs->trans("SourceOrder"), $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder);
			$this->resprints = ob_get_clean();
			return 0;
		}
		// Normal call at end: skip facture/invoice_supplier/commande/order_supplier (Source already after Ref)
		if ($object->element == 'facture' || $object->element == 'invoice_supplier' || $object->element == 'commande' || $object->element == 'order_supplier') {
			return 0;
		}
		return 0;
	}

	/**
	 * Add column value(s) for SLY list columns
	 *
	 * @param array        $parameters Hook parameters (arrayfields, obj, i, totalarray)
	 * @param CommonObject $object     Object
	 * @param string       $action     Action
	 * @return int
	 */
	public function printFieldListValue($parameters, &$object, &$action)
	{
		global $langs;
		$this->resprints = '';
		$arrayfields = $parameters['arrayfields'] ?? array();
		$obj = $parameters['obj'] ?? null;
		$i = isset($parameters['i']) ? (int) $parameters['i'] : 0;
		$insert_after = $parameters['insert_after'] ?? '';
		$langs->load("slycustom@slycustom");
		// Payment card: invoice list – add Source order cell (object is the row with facid)
		if (isset($parameters['fk_paiement']) && is_object($object) && isset($object->facid) && (int) $object->facid > 0) {
			$order = $this->getInvoiceSourceOrder((int) $object->facid);
			$this->resprints = '<td class="tdoverflowmax150">';
			if ($order !== null && $order['id'] > 0) {
				$this->resprints .= '<a href="'.DOL_URL_ROOT.'/commande/card.php?id='.((int) $order['id']).'">'.dol_escape_htmltag($order['ref']).'</a>';
			} else {
				$this->resprints .= '&nbsp;';
			}
			$this->resprints .= '</td>';
			return 0;
		}
		// Payment create page: unpaid invoices list – skip (cell added directly in core paiement.php)
		if (!empty($parameters['context']) && $parameters['context'] === 'payment_unpaid_invoices') {
			return 0;
		}
		if (empty($object->element) || !is_object($obj)) {
			return 0;
		}
		// Called after f.ref: output only Source order cell for facture or invoice_supplier
		if ($insert_after === 'f.ref') {
			if ($object->element == 'facture' && !empty($arrayfields['sly_order_ref']['checked'])) {
				if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield']) && !$i) {
					$parameters['totalarray']['nbfield']++;
				}
				$this->resprints = $this->getListRefCellHtml($obj, 'sly_order_ref', 'sly_order_id', DOL_URL_ROOT.'/commande/card.php');
			}
			if ($object->element == 'invoice_supplier' && !empty($arrayfields['sly_source_supplier_order_ref']['checked'])) {
				if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield']) && !$i) {
					$parameters['totalarray']['nbfield']++;
				}
				$this->resprints = $this->getListRefCellHtml($obj, 'sly_source_supplier_order_ref', 'sly_source_supplier_order_id', DOL_URL_ROOT.'/fourn/commande/card.php');
			}
			return 0;
		}
		// Called after c.ref: Source purchase order cell for sales order list
		if ($insert_after === 'c.ref' && $object->element == 'commande' && !empty($arrayfields['sly_source_supplier_order_ref']['checked'])) {
			if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield']) && !$i) {
				$parameters['totalarray']['nbfield']++;
			}
			$this->resprints = $this->getListRefCellHtml($obj, 'sly_source_supplier_order_ref', 'sly_source_supplier_order_id', DOL_URL_ROOT.'/fourn/commande/card.php');
			return 0;
		}
		// Called after cf.ref: Source order cell for purchase order list
		if ($insert_after === 'cf.ref' && $object->element == 'order_supplier' && !empty($arrayfields['sly_source_order_ref']['checked'])) {
			if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield']) && !$i) {
				$parameters['totalarray']['nbfield']++;
			}
			$this->resprints = $this->getListRefCellHtml($obj, 'sly_source_order_ref', 'sly_source_order_id', DOL_URL_ROOT.'/commande/card.php');
			return 0;
		}
		// Normal call at end: skip facture/invoice_supplier/commande/order_supplier (Source already after Ref)
		if ($object->element == 'facture' || $object->element == 'invoice_supplier' || $object->element == 'commande' || $object->element == 'order_supplier') {
			return 0;
		}
		return 0;
	}

	/**
	 * Return HTML for a table cell with optional link for a ref column (for list hooks: use resPrint)
	 *
	 * @param object $obj     Row object
	 * @param string $refKey  Property name for ref (e.g. sly_order_ref)
	 * @param string $idKey   Property name for id (e.g. sly_order_id)
	 * @param string $baseUrl Base URL for card (e.g. DOL_URL_ROOT.'/commande/card.php')
	 * @return string <td>...</td>
	 */
	protected function getListRefCellHtml($obj, $refKey, $idKey, $baseUrl)
	{
		$ref = isset($obj->$refKey) ? $obj->$refKey : '';
		if ($ref !== '' && $ref !== null) {
			$id = isset($obj->$idKey) ? (int) $obj->$idKey : 0;
			if ($id > 0) {
				return '<td class="nowraponall">'.'<a href="'.dol_escape_htmltag($baseUrl.'?id='.$id).'">'.dol_escape_htmltag($ref).'</a>'.'</td>';
			}
			return '<td class="nowraponall">'.dol_escape_htmltag($ref).'</td>';
		}
		return '<td class="nowraponall">&nbsp;</td>';
	}

	/**
	 * Get source order (commande) linked to an invoice for payment card invoice list.
	 *
	 * @param int $facid Invoice id (llx_facture.rowid)
	 * @return array|null {'ref' => string, 'id' => int} or null
	 */
	protected function getInvoiceSourceOrder($facid)
	{
		$p = MAIN_DB_PREFIX;
		$sql = "SELECT c.ref, c.rowid AS id FROM ".$p."element_element ee";
		$sql .= " INNER JOIN ".$p."commande c ON (c.rowid = ee.fk_source AND ee.sourcetype = 'commande' AND ee.targettype = 'facture' AND ee.fk_target = ".(int) $facid.")";
		$sql .= " UNION ALL SELECT c2.ref, c2.rowid AS id FROM ".$p."element_element ee2";
		$sql .= " INNER JOIN ".$p."commande c2 ON (c2.rowid = ee2.fk_target AND ee2.targettype = 'commande' AND ee2.sourcetype = 'facture' AND ee2.fk_source = ".(int) $facid.")";
		$sql .= " LIMIT 1";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			$this->db->free($resql);
			return array('ref' => $obj->ref, 'id' => (int) $obj->id);
		}
		if ($resql) {
			$this->db->free($resql);
		}
		return null;
	}

	// -------- Language Picker (from custom/langpicker) --------

	/**
	 * Hook printTopRightMenu: add language dropdown in top right menu
	 *
	 * @param array        $parameters Hook parameters (context)
	 * @param CommonObject $object     Object
	 * @param string       $action     Action
	 * @return int
	 */
	public function printTopRightMenu($parameters, &$object, &$action)
	{
		global $user, $conf, $langs;

		if (!in_array('toprightmenu', explode(':', $parameters['context']))) {
			return 0;
		}
		// Show for all users when module enabled and not hidden (no separate permission required)
		if (getDolGlobalString('LANG_PICKER_HIDDEN')) {
			return 0;
		}

		$langs->load("admin");
		$langs->load("languages");
		$langs->load("slycustom@slycustom");
		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/langpicker.class.php';
		$langpicker = new SlyLangPicker($this->db);
		$langpicker->fetchAll(0, 0, 't.position', 'ASC');

		$default_lang = isset($user->conf->MAIN_LANG_DEFAULT) ? $user->conf->MAIN_LANG_DEFAULT : getDolGlobalString('MAIN_LANG_DEFAULT', 'en_US');
		$default_lang_picto = picto_from_langcode($default_lang);
		$default_lang_code = explode('_', $default_lang);
		$default_lang_abbr = strtoupper($default_lang_code[0]);
		$default_lang_trans = ($default_lang == 'auto' ? $langs->trans("AutoDetectLang") : $langs->trans("Language_".$default_lang));

		echo '<div class="login_block_lang valignmiddle">';
		echo '<div id="topmenu-lang-dropdown" class="language-dropdown atoplogin dropdown inline-block">';
		echo '<a id="lang-toggle" class="dropdown-toggle login-dropdown-a nofocusvisible" href="#" role="button" tabindex="0" title="'.dol_escape_htmltag($default_lang_trans).'"><span class="lang-btn-inner" style="display:flex;flex-direction:row;align-items:center;justify-content:center;gap:4px;width:100%;height:100%;box-sizing:border-box;"><span class="lang-picto">'.$default_lang_picto.'</span><span class="lang-abbr">'.$default_lang_abbr.'</span></span></a>';
		echo '<div class="dropdown-menu"><ul class="lang-list">';
		foreach ($langpicker->lines as $lang) {
			$lang_code = explode('_', $lang->lang_code);
			$abbr = strtoupper($lang_code[0]);
			$backtopage = urlencode($_SERVER['PHP_SELF'].(empty($_SERVER['QUERY_STRING']) ? '' : '?'.$_SERVER['QUERY_STRING']));
			$url = DOL_URL_ROOT.'/custom/slycustom/set_lang.php?lang_code='.urlencode($lang->lang_code).'&backtopage='.$backtopage;
			$title = $langs->trans("Language_".$lang->lang_code);
			echo '<li class="lang'.($default_lang == $lang->lang_code ? ' selected' : '').'"><a href="'.dol_escape_htmltag($url).'" title="'.dol_escape_htmltag($title).'">'.picto_from_langcode($lang->lang_code).' '.$abbr.'</a></li>';
		}
		echo '</ul></div>';
		echo '</div>';
		echo '</div>'; // end login_block_lang
		return 0;
	}

	/**
	 * Hook getLoginPageOptions: reserved for future use (login page language selector removed)
	 *
	 * @param array        $parameters Hook parameters
	 * @param CommonObject $object     Object
	 * @param string       $action     Action
	 * @return int
	 */
	public function getLoginPageOptions($parameters, &$object, &$action)
	{
		if (!in_array('mainloginpage', explode(':', $parameters['context']))) {
			return 0;
		}
		return 0;
	}

	/**
	 * Hook getLoginPageExtraOptions: reserved for future use (login page language selector removed)
	 *
	 * @param array        $parameters Hook parameters
	 * @param CommonObject $object     Object
	 * @param string       $action     Action
	 * @return int
	 */
	public function getLoginPageExtraOptions($parameters, &$object, &$action)
	{
		if (!in_array('mainloginpage', explode(':', $parameters['context']))) {
			return 0;
		}
		return 0;
	}

	/**
	 * Hook afterLogin: reserved for future use (login page language selector removed)
	 *
	 * @param array        $parameters Hook parameters
	 * @param CommonObject $object     Object (user)
	 * @param string       $action     Action
	 * @return int
	 */
	public function afterLogin($parameters, &$object, &$action)
	{
		if (!in_array('login', explode(':', $parameters['context']))) {
			return 0;
		}
		return 0;
	}

	/**
	 * Hook after discount split (remx 折扣拆分) — 仅当应用 sly22.0-remx-hooks.patch 后会被调用
	 *
	 * @param array            $parameters remid, newid1, newid2, socid
	 * @param DiscountAbsolute $object     被拆分的原折扣对象
	 * @param string           $action     confirm_split
	 * @return int
	 */
	public function afterSplitDiscount($parameters, &$object, &$action)
	{
		// 可在此做审计、同步等；默认无操作
		return 0;
	}

	/**
	 * Hook menuLeftMenuItems: 将 Shipment（发货）从「产品/服务」目录移到「商业」目录显示
	 *
	 * @param array $parameters ['mainmenu' => string]
	 * @param array $hook_items 左侧菜单项数组（与 Menu->liste 结构一致）
	 * @return int 0=不替换, 1=用 $this->results 替换整份菜单（由 HookManager 写入 resArray）
	 */
	public function menuLeftMenuItems($parameters, &$hook_items)
	{
		global $user, $langs, $conf;

		if (!isModEnabled('slycustom') || !isModEnabled('shipping')) {
			return 0;
		}

		$mainmenu = isset($parameters['mainmenu']) ? $parameters['mainmenu'] : '';

		// 商业目录：在左侧菜单中追加 Shipment 块（与 core 中 products 下结构一致，mainmenu 改为 commercial）
		if ($mainmenu === 'commercial') {
			if (!is_array($hook_items) || empty($hook_items)) {
				return 0; // 不替换，保留原有菜单，避免清空
			}
			$langs->load("sendings");
			$leftmenu = (empty($_SESSION['leftmenu']) ? '' : $_SESSION['leftmenu']);
			$usemenuhider = 1;

			$shipmentEntries = array(
				array(
					'url' => '/expedition/index.php?leftmenu=sendings',
					'titre' => $langs->trans("Shipments"),
					'level' => 0,
					'enabled' => $user->hasRight('expedition', 'lire'),
					'target' => '',
					'mainmenu' => 'commercial',
					'leftmenu' => 'sendings',
					'position' => 500,
					'id' => '',
					'idsel' => 'sendings',
					'classname' => '',
					'prefix' => img_picto('', 'shipment', 'class="paddingright pictofixedwidth"'),
				),
				array(
					'url' => '/expedition/card.php?action=create2&amp;leftmenu=sendings',
					'titre' => $langs->trans("NewSending"),
					'level' => 1,
					'enabled' => $user->hasRight('expedition', 'creer'),
					'target' => '',
					'mainmenu' => 'commercial',
					'leftmenu' => 'sendings',
					'position' => 501,
					'id' => '',
					'idsel' => '',
					'classname' => '',
					'prefix' => '',
				),
				array(
					'url' => '/expedition/list.php?leftmenu=sendings',
					'titre' => $langs->trans("List"),
					'level' => 1,
					'enabled' => $user->hasRight('expedition', 'lire'),
					'target' => '',
					'mainmenu' => 'commercial',
					'leftmenu' => 'sendings',
					'position' => 502,
					'id' => '',
					'idsel' => '',
					'classname' => '',
					'prefix' => '',
				),
			);
			if ($usemenuhider || empty($leftmenu) || $leftmenu == 'sendings') {
				$shipmentEntries[] = array(
					'url' => '/expedition/list.php?leftmenu=sendings&search_status=0',
					'titre' => $langs->trans("StatusSendingDraftShort"),
					'level' => 2,
					'enabled' => $user->hasRight('expedition', 'lire'),
					'target' => '',
					'mainmenu' => 'commercial',
					'leftmenu' => 'sendings',
					'position' => 503,
					'id' => '',
					'idsel' => '',
					'classname' => '',
					'prefix' => '',
				);
				$shipmentEntries[] = array(
					'url' => '/expedition/list.php?leftmenu=sendings&search_status=1',
					'titre' => $langs->trans("StatusSendingValidatedShort"),
					'level' => 2,
					'enabled' => $user->hasRight('expedition', 'lire'),
					'target' => '',
					'mainmenu' => 'commercial',
					'leftmenu' => 'sendings',
					'position' => 504,
					'id' => '',
					'idsel' => '',
					'classname' => '',
					'prefix' => '',
				);
				$shipmentEntries[] = array(
					'url' => '/expedition/list.php?leftmenu=sendings&search_status=2',
					'titre' => $langs->trans("StatusSendingProcessedShort"),
					'level' => 2,
					'enabled' => $user->hasRight('expedition', 'lire'),
					'target' => '',
					'mainmenu' => 'commercial',
					'leftmenu' => 'sendings',
					'position' => 505,
					'id' => '',
					'idsel' => '',
					'classname' => '',
					'prefix' => '',
				);
			}
			$shipmentEntries[] = array(
				'url' => '/expedition/stats/index.php?leftmenu=sendings',
				'titre' => $langs->trans("Statistics"),
				'level' => 1,
				'enabled' => $user->hasRight('expedition', 'lire'),
				'target' => '',
				'mainmenu' => 'commercial',
				'leftmenu' => 'sendings',
				'position' => 506,
				'id' => '',
				'idsel' => '',
				'classname' => '',
				'prefix' => '',
			);

			$new_menu = array_merge($hook_items, $shipmentEntries);
			$this->results = $new_menu; // HookManager 会据此设置 resArray，供 core 替换 menu_array
			return 1;
		}

		// 产品目录：从左侧菜单中移除 Shipment 块（expedition + sendings）
		if ($mainmenu === 'products') {
			if (!is_array($hook_items)) {
				return 0;
			}
			$new_menu = array();
			foreach ($hook_items as $item) {
				$url = isset($item['url']) ? $item['url'] : '';
				$left = isset($item['leftmenu']) ? $item['leftmenu'] : '';
				if (strpos($url, '/expedition/') !== false && $left === 'sendings') {
					continue;
				}
				$new_menu[] = $item;
			}
			if (count($new_menu) !== count($hook_items)) {
				$this->results = $new_menu;
				return 1;
			}
		}

		return 0;
	}
}
