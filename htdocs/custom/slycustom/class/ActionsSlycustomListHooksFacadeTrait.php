<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: aggregates list hooks; delegates per list scenario to slyList* traits.
 */
trait ActionsSlycustomListHooksFacadeTrait
{
	/**
	 * @param array        $arrayfields
	 * @param CommonObject $object
	 */
	protected function addListArrayFields(array &$arrayfields, $object)
	{
		if (empty($object->element)) {
			return;
		}
		if ($object->element == 'facture') {
			$this->slyListFacture_addArrayFields($arrayfields);
		} elseif ($object->element == 'commande') {
			$this->slyListCommande_addArrayFields($arrayfields);
		} elseif ($object->element == 'order_supplier') {
			$this->slyListOrderSupplier_addArrayFields($arrayfields);
		} elseif ($object->element == 'invoice_supplier') {
			$this->slyListInvoiceSupplier_addArrayFields($arrayfields);
		}
	}

	/**
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
			$this->resprints = $this->slyListFacture_printFieldListSelect();
		} elseif ($object->element == 'commande') {
			$this->resprints = $this->slyListCommande_printFieldListSelect();
		} elseif ($object->element == 'order_supplier') {
			$this->resprints = $this->slyListOrderSupplier_printFieldListSelect();
		} elseif ($object->element == 'invoice_supplier') {
			$this->resprints = $this->slyListInvoiceSupplier_printFieldListSelect();
		}
		return 0;
	}

	/**
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
			$this->resprints = $this->slyListFacture_printFieldListFrom();
		} elseif ($object->element == 'commande') {
			$this->resprints = $this->slyListCommande_printFieldListFrom();
		} elseif ($object->element == 'order_supplier') {
			$this->resprints = $this->slyListOrderSupplier_printFieldListFrom();
		} elseif ($object->element == 'invoice_supplier') {
			$this->resprints = $this->slyListInvoiceSupplier_printFieldListFrom();
		}
		return 0;
	}

	/**
	 * @param array        $parameters Hook parameters ('order_join' => &$orderJoin)
	 * @param Expedition   $object     Shipment list context
	 * @param string       $action     Action
	 * @return int
	 */
	public function getShipmentListOrderJoin($parameters, &$object, &$action)
	{
		return $this->slyListShipment_applyOrderJoin($parameters);
	}

	/**
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
		if ($insert_after === 'f.ref') {
			if ($object->element == 'facture') {
				$this->resprints = $this->slyListFacture_printFieldListOption($arrayfields, $insert_after);
			}
			if ($object->element == 'invoice_supplier') {
				$this->resprints = $this->slyListInvoiceSupplier_printFieldListOption($arrayfields, $insert_after);
			}
			return 0;
		}
		if ($insert_after === 'c.ref' && $object->element == 'commande') {
			$this->resprints = $this->slyListCommande_printFieldListOption($arrayfields, $insert_after);
			return 0;
		}
		if ($insert_after === 'cf.ref' && $object->element == 'order_supplier') {
			$this->resprints = $this->slyListOrderSupplier_printFieldListOption($arrayfields, $insert_after);
			return 0;
		}
		if ($object->element == 'facture' || $object->element == 'invoice_supplier' || $object->element == 'commande' || $object->element == 'order_supplier') {
			return 0;
		}
		return 0;
	}

	/**
	 * @param array        $parameters Hook parameters
	 * @param CommonObject $object     Object
	 * @param string       $action     Action
	 * @return int
	 */
	public function printFieldPreListTitle($parameters, &$object, &$action)
	{
		$this->resprints = '';
		if (empty($object->element) || $object->element !== 'order_supplier') {
			return 0;
		}
		$this->resprints = $this->slyListOrderSupplier_printFieldPreListTitle();
		return 0;
	}

	/**
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
			$this->resprints = $this->slyListFacture_printFieldListWhere();
		} elseif ($object->element == 'invoice_supplier') {
			$this->resprints = $this->slyListInvoiceSupplier_printFieldListWhere();
		} elseif ($object->element == 'commande') {
			$this->resprints = $this->slyListCommande_printFieldListWhere();
		} elseif ($object->element == 'order_supplier') {
			$this->resprints = $this->slyListOrderSupplier_printFieldListWhere();
		}
		return 0;
	}

	/**
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
			$this->resprints = $this->slyListFacture_printFieldListSearchParam();
		} elseif ($object->element == 'invoice_supplier') {
			$this->resprints = $this->slyListInvoiceSupplier_printFieldListSearchParam();
		} elseif ($object->element == 'commande') {
			$this->resprints = $this->slyListCommande_printFieldListSearchParam();
		} elseif ($object->element == 'order_supplier') {
			$this->resprints = $this->slyListOrderSupplier_printFieldListSearchParam();
		}
		return 0;
	}

	/**
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
		if ($this->slyListPayment_isPaymentCardListTitleContext($object)) {
			$this->resprints = $this->slyListPayment_printFieldListTitle($langs);
			return 0;
		}
		if (!empty($parameters['context']) && $parameters['context'] === 'payment_unpaid_invoices') {
			return 0;
		}
		if (empty($object->element)) {
			return 0;
		}
		if ($insert_after === 'f.ref') {
			$this->resprints = '';
			if ($object->element == 'facture') {
				$this->resprints = $this->slyListFacture_printFieldListTitle($arrayfields, $param, $sortfield, $sortorder, $parameters);
			}
			if ($object->element == 'invoice_supplier') {
				$this->resprints = $this->slyListInvoiceSupplier_printFieldListTitle($arrayfields, $param, $sortfield, $sortorder, $parameters);
			}
			return 0;
		}
		if ($insert_after === 'c.ref' && $object->element == 'commande') {
			$this->resprints = $this->slyListCommande_printFieldListTitle($arrayfields, $param, $sortfield, $sortorder, $parameters);
			return 0;
		}
		if ($insert_after === 'cf.ref' && $object->element == 'order_supplier') {
			$this->resprints = $this->slyListOrderSupplier_printFieldListTitle($arrayfields, $param, $sortfield, $sortorder, $parameters);
			return 0;
		}
		if ($object->element == 'facture' || $object->element == 'invoice_supplier' || $object->element == 'commande' || $object->element == 'order_supplier') {
			return 0;
		}
		return 0;
	}

	/**
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

		// Pre-default ShipsGo date extrafields on the list page's $object:
		// dol_eval() resolves computed formulas against the page global $object
		// (raw rows carry extrafields as plain properties, not in array_options),
		// so the keys must exist there before the per-row evaluation.
		if (is_object($object) && !empty($object->element) && in_array($object->element, array('shipping', 'expedition'), true)) {
			$this->ensureShipmentDateOptionKeys($object);
		}

		if ($this->slyListPayment_shouldPrintListValue($parameters, $object)) {
			$this->resprints = $this->slyListPayment_printFieldListValue($object);
			return 0;
		}
		if (!empty($parameters['context']) && $parameters['context'] === 'payment_unpaid_invoices') {
			return 0;
		}
		if (empty($object->element) || !is_object($obj)) {
			return 0;
		}
		if ($insert_after === 'f.ref') {
			$this->resprints = '';
			if ($object->element == 'facture') {
				$this->resprints = $this->slyListFacture_printFieldListValue($arrayfields, $obj, $i, $parameters);
			}
			if ($object->element == 'invoice_supplier') {
				$this->resprints = $this->slyListInvoiceSupplier_printFieldListValue($arrayfields, $obj, $i, $parameters);
			}
			return 0;
		}
		if ($insert_after === 'c.ref' && $object->element == 'commande') {
			$this->resprints = $this->slyListCommande_printFieldListValue($object, $arrayfields, $obj, $i, $parameters);
			return 0;
		}
		if ($insert_after === 'cf.ref' && $object->element == 'order_supplier') {
			$this->resprints = $this->slyListOrderSupplier_printFieldListValue($object, $arrayfields, $obj, $i, $parameters);
			return 0;
		}
		if ($object->element == 'facture' || $object->element == 'invoice_supplier' || $object->element == 'commande' || $object->element == 'order_supplier') {
			return 0;
		}
		return 0;
	}
}
