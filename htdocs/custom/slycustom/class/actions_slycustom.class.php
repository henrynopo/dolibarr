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
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomListHelpersTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomListFactureTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomListCommandeTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomListOrderSupplierTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomListInvoiceSupplierTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomListPaymentTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomListShipmentListTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomListHooksFacadeTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomShipmentHooksTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomShippingCardHooksTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomProductCardHooksTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomMenuHooksTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomBuilddocHooksTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomWisePaymentTrait.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ActionsSlycustomIndexHooksTrait.php';

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

	// List hooks: per-scenario traits + facade (see ActionsSlycustomList*Trait).
	use ActionsSlycustomListHelpersTrait;
	use ActionsSlycustomListFactureTrait;
	use ActionsSlycustomListCommandeTrait;
	use ActionsSlycustomListOrderSupplierTrait;
	use ActionsSlycustomListInvoiceSupplierTrait;
	use ActionsSlycustomListPaymentTrait;
	use ActionsSlycustomListShipmentListTrait;
	use ActionsSlycustomListHooksFacadeTrait;
	// Shipment/Expedition hooks extracted into trait to reduce file size.
	use ActionsSlycustomShipmentHooksTrait;
	// Shipping card UI hooks extracted into trait to reduce file size.
	use ActionsSlycustomShippingCardHooksTrait;
	// Product card hook extracted into trait to reduce file size.
	use ActionsSlycustomProductCardHooksTrait;
	// Builddoc hooks extracted into ActionsSlycustomBuilddocHooksTrait
	use ActionsSlycustomBuilddocHooksTrait;

	use ActionsSlycustomWisePaymentTrait;
	// Menu hooks extracted into ActionsSlycustomMenuHooksTrait
	use ActionsSlycustomMenuHooksTrait;
	use ActionsSlycustomIndexHooksTrait;

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
	 * Make sure the date extrafield keys consumed by ShipsGo computed fields
	 * are present on $obj->array_options before any dol_eval() runs over the
	 * object. Missing keys raise a PHP 8.1+ "Undefined array key" warning that
	 * the computed-field formula itself cannot suppress (it cannot use
	 * isset/empty/??). Hooks do this from slycustom module layer, no core patch.
	 *
	 * @param  CommonObject $obj Object with extrafields loaded (expedition, shipment)
	 * @return void
	 */
	public function ensureShipmentDateOptionKeys($obj)
	{
		if (!is_object($obj) || !property_exists($obj, 'array_options') || !is_array($obj->array_options)) {
			return;
		}
		foreach (array('options_atd', 'options_etd', 'options_ata', 'options_eta') as $k) {
			if (!array_key_exists($k, $obj->array_options)) {
				$obj->array_options[$k] = 0;
			}
		}
	}

	/**
	 * Compute SLY shipment line totals (qty, cartons, gross weight).
	 *
	 * v24 replacement for the v22 computed extrafields (totalnetweight /
	 * TotalQtyCartons / TotalGrossWeight) that aggregated lines with
	 * array_sum(array_column(...)): Dolibarr 24's dol_eval whitelist ships no
	 * aggregation function, so the module computes the totals itself
	 * (official extension model — hooks/triggers in custom/, zero core or
	 * conf changes). Shared by the card hook (live display) and the
	 * SHIPMENT_VALIDATE trigger (persistence for lists/exports/PDF).
	 *
	 * @param  CommonObject $obj Expedition with lines loaded
	 * @return array{qty:float,cartons:float,grossweight:float}|null Null when no lines
	 */
	public static function computeShipmentLineTotals($obj)
	{
		if (!is_object($obj) || !is_array($obj->lines ?? null) || count($obj->lines) === 0) {
			return null;
		}
		$qty = 0.0;
		$cartons = 0.0;
		$gross = 0.0;
		foreach ($obj->lines as $line) {
			if (!is_object($line)) {
				continue;
			}
			$qty += (float) ($line->qty ?? 0);
			$ao = is_array($line->array_options ?? null) ? $line->array_options : array();
			$cartons += (float) ($ao['options_quantitycarton'] ?? 0);
			$gross += (float) ($ao['options_grossweight'] ?? 0);
		}
		return array('qty' => $qty, 'cartons' => $cartons, 'grossweight' => $gross);
	}

	/**
	 * Inject live line totals into the shipment's array_options for display.
	 * Keys mirror the extrafield attribute names; totalnetweight keeps the
	 * v22 formula's observable behaviour (it summed line qty, not a weight).
	 * Skipped when lines are not loaded, so stored values are never wiped
	 * with zeros in list/other contexts.
	 *
	 * @param  CommonObject $object Expedition
	 * @return void
	 */
	public function injectShipmentLineTotals(&$object)
	{
		$totals = self::computeShipmentLineTotals($object);
		if ($totals === null) {
			return;
		}
		if (!is_array($object->array_options ?? null)) {
			$object->array_options = array();
		}
		$object->array_options['options_totalnetweight'] = $totals['qty'];
		$object->array_options['options_TotalQtyCartons'] = $totals['cartons'];
		$object->array_options['options_TotalGrossWeight'] = $totals['grossweight'];
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

		// Pre-default ShipsGo date extrafields on any expedition/shipment object
		// that enters doActions (covers both view and edit paths).
		if (is_object($object) && !empty($object->element) && in_array($object->element, array('shipping', 'expedition'), true)) {
			$this->ensureShipmentDateOptionKeys($object);
			// Live line totals (v24 replacement for the aggregated computed extrafields)
			$this->injectShipmentLineTotals($object);
		}

		// Product/Service card: load SLYcustom so CustomsCode/CustomCode display as Plant No./厂号
		if (is_object($object) && !empty($object->element) && in_array($object->element, array('product', 'service'), true)) {
			$langs->load("slycustom@slycustom");
		}

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

	// 其余 hook 实现均已拆分到对应 trait（见 use 列表）。

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

	// （列表 hook 按场景见 ActionsSlycustomList*Trait；其它见对应 *HooksTrait。）
}
