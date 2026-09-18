<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: payment card — invoice sub-list Source order column (Paiement context).
 */
trait ActionsSlycustomListPaymentTrait
{
	/**
	 * @param object|null $object Hook object
	 * @return bool
	 */
	protected function slyListPayment_isPaymentCardListTitleContext($object)
	{
		return is_object($object) && (get_class($object) === 'Paiement' || (isset($object->table_element) && $object->table_element === 'paiement'));
	}

	/**
	 * @param object|null $object Hook object
	 * @param array       $parameters
	 * @return bool
	 */
	protected function slyListPayment_shouldPrintListValue($parameters, $object)
	{
		return isset($parameters['fk_paiement']) && is_object($object) && isset($object->facid) && (int) $object->facid > 0;
	}

	/**
	 * @param object $langs
	 * @return string
	 */
	protected function slyListPayment_printFieldListTitle($langs)
	{
		return '<td>'.$langs->trans("SourceOrder").'</td>';
	}

	/**
	 * @param object $object row object with facid
	 * @return string
	 */
	protected function slyListPayment_printFieldListValue($object)
	{
		$order = $this->getInvoiceSourceOrder((int) $object->facid);
		$html = '<td class="tdoverflowmax150">';
		if ($order !== null && $order['id'] > 0) {
			$html .= '<a href="'.DOL_URL_ROOT.'/commande/card.php?id='.((int) $order['id']).'">'.dol_escape_htmltag($order['ref']).'</a>';
		} else {
			$html .= '&nbsp;';
		}
		$html .= '</td>';
		return $html;
	}
}
