<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: customer invoice (facture) list — Source order column.
 */
trait ActionsSlycustomListFactureTrait
{
	/**
	 * @param array $arrayfields
	 */
	protected function slyListFacture_addArrayFields(array &$arrayfields)
	{
		$arrayfields['sly_order_ref'] = array(
			'label' => 'SourceOrder',
			'langfile' => 'slycustom@slycustom',
			'checked' => '1',
			'position' => 7,
		);
	}

	/**
	 * @return string
	 */
	protected function slyListFacture_printFieldListSelect()
	{
		return ', cord.ref as sly_order_ref, cord.rowid as sly_order_id';
	}

	/**
	 * @return string
	 */
	protected function slyListFacture_printFieldListFrom()
	{
		$p = MAIN_DB_PREFIX;
		$out = $this->buildBidirectionalElementJoin('eeord', 'inv_id', 'cmd_id', 'facture', 'commande', 'f');
		$out .= " LEFT JOIN ".$p."commande as cord ON cord.rowid = eeord.cmd_id";
		return $out;
	}

	/**
	 * @param array  $arrayfields
	 * @param string $insert_after
	 * @return string
	 */
	protected function slyListFacture_printFieldListOption(array $arrayfields, $insert_after)
	{
		if ($insert_after !== 'f.ref' || empty($arrayfields['sly_order_ref']['checked'])) {
			return '';
		}
		$search_sly_order_ref = GETPOST('search_sly_order_ref', 'alphanohtml');
		return '<td class="liste_titre"><input class="flat maxwidth50imp" type="text" name="search_sly_order_ref" value="'.dol_escape_htmltag($search_sly_order_ref).'"></td>';
	}

	/**
	 * @return string
	 */
	protected function slyListFacture_printFieldListWhere()
	{
		$search_sly_order_ref = GETPOST('search_sly_order_ref', 'alphanohtml');
		if ($search_sly_order_ref !== '') {
			return natural_search('cord.ref', $search_sly_order_ref, 0, 0);
		}
		return '';
	}

	/**
	 * @return string
	 */
	protected function slyListFacture_printFieldListSearchParam()
	{
		$search_sly_order_ref = GETPOST('search_sly_order_ref', 'alphanohtml');
		if ($search_sly_order_ref !== '') {
			return '&search_sly_order_ref='.urlencode($search_sly_order_ref);
		}
		return '';
	}

	/**
	 * @param array  $arrayfields
	 * @param string $param
	 * @param string $sortfield
	 * @param string $sortorder
	 * @param array  $parameters hook parameters (totalarray by ref)
	 * @return string
	 */
	protected function slyListFacture_printFieldListTitle(array $arrayfields, $param, $sortfield, $sortorder, array &$parameters)
	{
		if (empty($arrayfields['sly_order_ref']['checked'])) {
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
	protected function slyListFacture_printFieldListValue(array $arrayfields, $obj, $i, array &$parameters)
	{
		if (empty($arrayfields['sly_order_ref']['checked'])) {
			return '';
		}
		if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield']) && !$i) {
			$parameters['totalarray']['nbfield']++;
		}
		return $this->getListRefCellHtml($obj, 'sly_order_ref', 'sly_order_id', DOL_URL_ROOT.'/commande/card.php');
	}
}
