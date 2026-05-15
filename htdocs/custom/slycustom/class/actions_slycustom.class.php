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
