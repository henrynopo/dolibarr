<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: sales order (commande) list — SalesPerson + Source supplier PO.
 */
trait ActionsSlycustomListCommandeTrait
{
	/**
	 * @param array $arrayfields
	 */
	protected function slyListCommande_addArrayFields(array &$arrayfields)
	{
		$coreSalesChecked = isset($arrayfields['sale_representative']['checked']) ? (string) $arrayfields['sale_representative']['checked'] : '0';
		if (isset($arrayfields['sale_representative'])) {
			$arrayfields['sale_representative']['enabled'] = '0';
			$arrayfields['sale_representative']['checked'] = '0';
		}
		$arrayfields['sly_sales_person'] = array(
			'label' => 'SalesPerson',
			'langfile' => 'slycustom@slycustom',
			'checked' => $coreSalesChecked,
			'position' => 116,
		);
		$arrayfields['sly_source_supplier_order_ref'] = array(
			'label' => 'SourceOrder',
			'langfile' => 'slycustom@slycustom',
			'checked' => '0',
			'position' => 117,
		);
	}

	/**
	 * @return string
	 */
	protected function slyListCommande_printFieldListSelect()
	{
		$out = ', cf_src.ref as sly_source_supplier_order_ref, cf_src.rowid as sly_source_supplier_order_id';
		$out .= ", (SELECT MIN(ur.login)
				FROM ".MAIN_DB_PREFIX."element_contact as ec_sales
				INNER JOIN ".MAIN_DB_PREFIX."c_type_contact as tc_sales ON tc_sales.rowid = ec_sales.fk_c_type_contact
				INNER JOIN ".MAIN_DB_PREFIX."user as ur ON ur.rowid = ec_sales.fk_socpeople
				WHERE ec_sales.element_id = c.rowid
					AND tc_sales.element = 'commande'
					AND tc_sales.source = 'internal'
					AND tc_sales.code = 'SALESREPFOLL'
					AND tc_sales.active = 1
			) as sly_sales_person_login";
		return $out;
	}

	/**
	 * @return string
	 */
	protected function slyListCommande_printFieldListFrom()
	{
		$p = MAIN_DB_PREFIX;
		$out = $this->buildBidirectionalElementJoin('ee_cf', 'cmd_id', 'po_id', 'commande', 'order_supplier', 'c');
		$out .= " LEFT JOIN ".$p."commande_fournisseur cf_src ON cf_src.rowid = ee_cf.po_id";
		return $out;
	}

	/**
	 * @param array  $arrayfields
	 * @param string $insert_after
	 * @return string
	 */
	protected function slyListCommande_printFieldListOption(array $arrayfields, $insert_after)
	{
		if ($insert_after !== 'c.ref' || empty($arrayfields['sly_source_supplier_order_ref']['checked'])) {
			return '';
		}
		$v = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_sly_source_supplier_order_ref', 'alphanohtml');
		return '<td class="liste_titre"><input class="flat maxwidth50imp" type="text" name="search_sly_source_supplier_order_ref" value="'.dol_escape_htmltag($v).'"></td>';
	}

	/**
	 * @return string
	 */
	protected function slyListCommande_printFieldListWhere()
	{
		$v = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_sly_source_supplier_order_ref', 'alphanohtml');
		if ($v !== '') {
			return natural_search('cf_src.ref', $v, 0, 0);
		}
		return '';
	}

	/**
	 * @return string
	 */
	protected function slyListCommande_printFieldListSearchParam()
	{
		$v = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_sly_source_supplier_order_ref', 'alphanohtml');
		if ($v !== '') {
			return '&search_sly_source_supplier_order_ref='.urlencode($v);
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
	protected function slyListCommande_printFieldListTitle(array $arrayfields, $param, $sortfield, $sortorder, array &$parameters)
	{
		global $langs;
		$html = '';
		if (!empty($arrayfields['sly_sales_person']['checked'])) {
			ob_start();
			print_liste_field_titre($langs->trans("SalesPerson"), $_SERVER["PHP_SELF"], 'sly_sales_person_login', '', $param, '', $sortfield, $sortorder);
			$html .= ob_get_clean();
			if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield'])) {
				$parameters['totalarray']['nbfield']++;
			}
		}
		if (!empty($arrayfields['sly_source_supplier_order_ref']['checked'])) {
			ob_start();
			print_liste_field_titre($langs->trans("SourceOrder"), $_SERVER["PHP_SELF"], 'sly_source_supplier_order_ref', '', $param, '', $sortfield, $sortorder);
			$html .= ob_get_clean();
			if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield'])) {
				$parameters['totalarray']['nbfield']++;
			}
		}
		return $html;
	}

	/**
	 * @param CommonObject $object list context (Commande)
	 * @param array        $arrayfields
	 * @param object       $obj row
	 * @param int          $i
	 * @param array        $parameters
	 * @return string
	 */
	protected function slyListCommande_printFieldListValue($object, array $arrayfields, $obj, $i, array &$parameters)
	{
		$html = '';
		if (!empty($arrayfields['sly_sales_person']['checked'])) {
			if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield']) && !$i) {
				$parameters['totalarray']['nbfield']++;
			}
			$salesPersonCell = '<td class="tdoverflowmax150">';
			if (!empty($obj->rowid)) {
				$object->id = (int) $obj->rowid;
				$arrayidcontact = $object->getIdContact('internal', 'SALESREPFOLL');
				if (!empty($arrayidcontact) && is_array($arrayidcontact)) {
					$userstatic = new User($this->db);
					$j = 0;
					$nbofsalesperson = count($arrayidcontact);
					foreach ($arrayidcontact as $userid) {
						$userid = (int) $userid;
						if ($userid <= 0) {
							continue;
						}
						if ($userstatic->fetch($userid) > 0) {
							$tmpshow = ($nbofsalesperson < 2) ? $userstatic->getNomUrl(-1, '', 0, 0, 12) : $userstatic->getNomUrl(-2);
							if (!empty($tmpshow)) {
								$salesPersonCell .= $tmpshow;
								$j++;
								if ($j < $nbofsalesperson) {
									$salesPersonCell .= ' ';
								}
							}
						}
					}
					if ($j === 0) {
						$salesPersonCell .= '&nbsp;';
					}
				} else {
					$salesPersonCell .= '&nbsp;';
				}
			} else {
				$salesPersonCell .= '&nbsp;';
			}
			$salesPersonCell .= '</td>';
			$html .= $salesPersonCell;
		}
		if (!empty($arrayfields['sly_source_supplier_order_ref']['checked'])) {
			if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield']) && !$i) {
				$parameters['totalarray']['nbfield']++;
			}
			$html .= $this->getListRefCellHtml($obj, 'sly_source_supplier_order_ref', 'sly_source_supplier_order_id', DOL_URL_ROOT.'/fourn/commande/card.php');
		}
		return $html;
	}
}
