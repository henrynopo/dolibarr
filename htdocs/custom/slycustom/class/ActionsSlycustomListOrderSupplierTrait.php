<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: purchase order (order_supplier) list — Purchase Person + Source SO.
 */
trait ActionsSlycustomListOrderSupplierTrait
{
	/**
	 * @param array $arrayfields
	 */
	protected function slyListOrderSupplier_addArrayFields(array &$arrayfields)
	{
		$arrayfields['purchase_person'] = array(
			'label' => 'PurchasePerson',
			'langfile' => 'slycustom@slycustom',
			'checked' => '1',
			'position' => 116,
		);
		$arrayfields['sly_source_order_ref'] = array(
			'label' => 'SourceOrder',
			'langfile' => 'slycustom@slycustom',
			'checked' => '0',
			'position' => 117,
		);
	}

	/**
	 * @return string
	 */
	protected function slyListOrderSupplier_printFieldListSelect()
	{
		$out = ', cord_src.ref as sly_source_order_ref, cord_src.rowid as sly_source_order_id';
		$out .= ", (SELECT MIN(ur.login) FROM ".MAIN_DB_PREFIX."element_contact as ec_p
				INNER JOIN ".MAIN_DB_PREFIX."c_type_contact as tc_p ON tc_p.rowid = ec_p.fk_c_type_contact
				INNER JOIN ".MAIN_DB_PREFIX."user as ur ON ur.rowid = ec_p.fk_socpeople
				WHERE ec_p.element_id = cf.rowid
					AND tc_p.element = 'order_supplier'
					AND tc_p.source = 'internal'
					AND tc_p.code = 'SALESREPFOLL'
					AND tc_p.active = 1
			) as purchase_person_login";
		return $out;
	}

	/**
	 * @return string
	 */
	protected function slyListOrderSupplier_printFieldListFrom()
	{
		$p = MAIN_DB_PREFIX;
		$out = $this->buildBidirectionalElementJoin('ee_po', 'po_id', 'cmd_id', 'order_supplier', 'commande', 'cf');
		$out .= " LEFT JOIN ".$p."commande cord_src ON cord_src.rowid = ee_po.cmd_id";
		return $out;
	}

	/**
	 * @param array  $arrayfields
	 * @param string $insert_after
	 * @return string
	 */
	protected function slyListOrderSupplier_printFieldListOption(array $arrayfields, $insert_after)
	{
		if ($insert_after !== 'cf.ref' || empty($arrayfields['sly_source_order_ref']['checked'])) {
			return '';
		}
		$v = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_sly_source_order_ref', 'alphanohtml');
		return '<td class="liste_titre"><input class="flat maxwidth50imp" type="text" name="search_sly_source_order_ref" value="'.dol_escape_htmltag($v).'"></td>';
	}

	/**
	 * @return string
	 */
	protected function slyListOrderSupplier_printFieldPreListTitle()
	{
		global $form, $user, $langs;
		if (empty($form) || !is_object($form)) {
			return '';
		}
		$langs->load("slycustom@slycustom");
		if (!$user->hasRight("user", "user", "lire")) {
			return '';
		}
		$search_purchase_person = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_purchase_person', 'intcomma');
		$tmptitle = $langs->trans("PurchasePerson");
		$out = '<div class="divsearchfield">';
		$out .= img_picto($tmptitle, 'user', 'class="pictofixedwidth"')
			. $form->select_dolusers($search_purchase_person, 'search_purchase_person', $tmptitle, null, 0, '', '', '0', 0, 0, '', 0, '', 'maxwidth250 widthcentpercentminusx');
		$out .= '</div>';
		return $out;
	}

	/**
	 * @return string
	 */
	protected function slyListOrderSupplier_printFieldListWhere()
	{
		$frag = '';
		$v = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_sly_source_order_ref', 'alphanohtml');
		if ($v !== '') {
			$frag .= natural_search('cord_src.ref', $v, 0, 0);
		}
		$search_purchase_person = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_purchase_person', 'intcomma');
		if (!empty($search_purchase_person) && ((int) $search_purchase_person) > 0) {
			$frag .= " AND EXISTS (";
			$frag .= " SELECT ec.rowid ";
			$frag .= " FROM " . MAIN_DB_PREFIX . "element_contact as ec";
			$frag .= " INNER JOIN " . MAIN_DB_PREFIX . "c_type_contact as tc ON tc.rowid = ec.fk_c_type_contact";
			$frag .= " WHERE ec.element_id = cf.rowid AND ec.fk_socpeople = " . ((int) $search_purchase_person);
			$frag .= " AND tc.element = 'order_supplier' AND tc.source = 'internal' AND tc.code = 'SALESREPFOLL'";
			$frag .= ")";
		}
		return $frag;
	}

	/**
	 * @return string
	 */
	protected function slyListOrderSupplier_printFieldListSearchParam()
	{
		$out = '';
		$v = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_sly_source_order_ref', 'alphanohtml');
		if ($v !== '') {
			$out .= '&search_sly_source_order_ref='.urlencode($v);
		}
		$search_purchase_person = (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) ? '' : GETPOST('search_purchase_person', 'intcomma');
		if (!empty($search_purchase_person) && ((int) $search_purchase_person) > 0) {
			$out .= '&search_purchase_person='.urlencode((string) $search_purchase_person);
		}
		return $out;
	}

	/**
	 * @param array  $arrayfields
	 * @param string $param
	 * @param string $sortfield
	 * @param string $sortorder
	 * @param array  $parameters
	 * @return string
	 */
	protected function slyListOrderSupplier_printFieldListTitle(array $arrayfields, $param, $sortfield, $sortorder, array &$parameters)
	{
		global $langs;
		$html = '';
		$printCounter = function () use (&$parameters) {
			if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield'])) {
				$parameters['totalarray']['nbfield']++;
			}
		};
		if (!empty($arrayfields['purchase_person']['checked'])) {
			ob_start();
			print_liste_field_titre($langs->trans("PurchasePerson"), $_SERVER["PHP_SELF"], 'purchase_person_login', '', $param, '', $sortfield, $sortorder);
			$html .= ob_get_clean();
			$printCounter();
		}
		if (!empty($arrayfields['sly_source_order_ref']['checked'])) {
			ob_start();
			print_liste_field_titre($langs->trans("SourceOrder"), $_SERVER["PHP_SELF"], 'sly_source_order_ref', '', $param, '', $sortfield, $sortorder);
			$html .= ob_get_clean();
			$printCounter();
		}
		return $html;
	}

	/**
	 * @param CommonObject $object
	 * @param array        $arrayfields
	 * @param object       $obj
	 * @param int          $i
	 * @param array        $parameters
	 * @return string
	 */
	protected function slyListOrderSupplier_printFieldListValue($object, array $arrayfields, $obj, $i, array &$parameters)
	{
		$html = '';
		if (!empty($arrayfields['purchase_person']['checked'])) {
			if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield']) && !$i) {
				$parameters['totalarray']['nbfield']++;
			}
			$purchasePersonCell = '<td>';
			if (!empty($obj->rowid)) {
				$object->id = (int) $obj->rowid;
				$arrayidcontact = $object->getIdContact('internal', 'SALESREPFOLL');
				if (!empty($arrayidcontact) && is_array($arrayidcontact)) {
					$userstatic = new User($this->db);
					$j = 0;
					$nbofpurchaseperson = count($arrayidcontact);
					foreach ($arrayidcontact as $userid) {
						$userid = (int) $userid;
						if ($userid <= 0) {
							continue;
						}
						if ($userstatic->fetch($userid) > 0) {
							$tmpshow = ($nbofpurchaseperson < 2) ? $userstatic->getNomUrl(-1, '', 0, 0, 12) : $userstatic->getNomUrl(-2);
							if (!empty($tmpshow)) {
								$purchasePersonCell .= $tmpshow;
								$j++;
								if ($j < $nbofpurchaseperson) {
									$purchasePersonCell .= ' ';
								}
							}
						}
					}
					if ($j === 0) {
						$purchasePersonCell .= '&nbsp;';
					}
				} else {
					$purchasePersonCell .= '&nbsp;';
				}
			} else {
				$purchasePersonCell .= '&nbsp;';
			}
			$purchasePersonCell .= '</td>';
			$html .= $purchasePersonCell;
		}
		if (!empty($arrayfields['sly_source_order_ref']['checked'])) {
			if (!empty($parameters['totalarray']) && is_array($parameters['totalarray']) && isset($parameters['totalarray']['nbfield']) && !$i) {
				$parameters['totalarray']['nbfield']++;
			}
			$html .= $this->getListRefCellHtml($obj, 'sly_source_order_ref', 'sly_source_order_id', DOL_URL_ROOT.'/commande/card.php');
		}
		return $html;
	}
}
