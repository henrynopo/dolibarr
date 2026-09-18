<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: supplier invoice (invoice_supplier) list — Source PO column.
 */
trait ActionsSlycustomListInvoiceSupplierTrait
{
	/**
	 * @param array $arrayfields
	 */
	protected function slyListInvoiceSupplier_addArrayFields(array &$arrayfields)
	{
		$arrayfields['sly_source_supplier_order_ref'] = array(
			'label' => 'SourceOrder',
			'langfile' => 'slycustom@slycustom',
			'checked' => '1',
			'position' => 7,
			'enabled' => '1',
		);
	}

	/**
	 * @return string
	 */
	protected function slyListInvoiceSupplier_printFieldListSelect()
	{
		return ', cf_supp_src.ref as sly_source_supplier_order_ref, cf_supp_src.rowid as sly_source_supplier_order_id';
	}

	/**
	 * @return string
	 */
	protected function slyListInvoiceSupplier_printFieldListFrom()
	{
		$p = MAIN_DB_PREFIX;
		$out = $this->buildBidirectionalElementJoin('ee_cfsupp', 'inv_id', 'po_id', array('invoice_supplier', 'facture_fourn'), 'order_supplier', 'f');
		$out .= " LEFT JOIN ".$p."commande_fournisseur as cf_supp_src ON cf_supp_src.rowid = ee_cfsupp.po_id";
		return $out;
	}

	/**
	 * @param array  $arrayfields
	 * @param string $insert_after
	 * @return string
	 */
	protected function slyListInvoiceSupplier_printFieldListOption(array $arrayfields, $insert_after)
	{
		if ($insert_after !== 'f.ref' || empty($arrayfields['sly_source_supplier_order_ref']['checked'])) {
			return '';
		}
		$search_sly_supplier_order_ref = GETPOST('search_sly_supplier_order_ref', 'alphanohtml');
		return '<td class="liste_titre"><input class="flat maxwidth50imp" type="text" name="search_sly_supplier_order_ref" value="'.dol_escape_htmltag($search_sly_supplier_order_ref).'"></td>';
	}

	/**
	 * @return string
	 */
	protected function slyListInvoiceSupplier_printFieldListWhere()
	{
		$search_sly_supplier_order_ref = GETPOST('search_sly_supplier_order_ref', 'alphanohtml');
		if ($search_sly_supplier_order_ref !== '') {
			return natural_search('cf_supp_src.ref', $search_sly_supplier_order_ref, 0, 0);
		}
		return '';
	}

	/**
	 * @return string
	 */
	protected function slyListInvoiceSupplier_printFieldListSearchParam()
	{
		$search_sly_supplier_order_ref = GETPOST('search_sly_supplier_order_ref', 'alphanohtml');
		if ($search_sly_supplier_order_ref !== '') {
			return '&search_sly_supplier_order_ref='.urlencode($search_sly_supplier_order_ref);
		}
		return '';
	}

	/**
	 * @param array  $arrayfields
	 * @param string $param
	 * @param string $sortfield
	 * @param string $sortorder
	 * @param array  $parameters
	 * @return string
	 */
	protected function slyListInvoiceSupplier_printFieldListTitle(array $arrayfields, $param, $sortfield, $sortorder, array &$parameters)
	{
		if (empty($arrayfields['sly_source_supplier_order_ref']['checked'])) {
			return '';
		}
		if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield'])) {
			$parameters['totalarray']['nbfield']++;
		}
		global $langs;
		ob_start();
		print_liste_field_titre($langs->trans("SourceOrder"), $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder);
		return ob_get_clean();
	}

	/**
	 * @param array  $arrayfields
	 * @param object $obj
	 * @param int    $i
	 * @param array  $parameters
	 * @return string
	 */
	protected function slyListInvoiceSupplier_printFieldListValue(array $arrayfields, $obj, $i, array &$parameters)
	{
		if (empty($arrayfields['sly_source_supplier_order_ref']['checked'])) {
			return '';
		}
		if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield']) && !$i) {
			$parameters['totalarray']['nbfield']++;
		}
		return $this->getListRefCellHtml($obj, 'sly_source_supplier_order_ref', 'sly_source_supplier_order_id', DOL_URL_ROOT.'/fourn/commande/card.php');
	}
}
