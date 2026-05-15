<?php

/* Copyright (C) 2018		ATM Consulting		<support@atm-consulting.fr>
 * Copyright (C) 2021-2024  Frédéric France     <frederic.france@free.fr>
 * Copyright (C) 2025		MDW					<mdeweerd@users.noreply.github.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Needs the following variables defined:
 * $object					Proposal, order, invoice (including supplier versions)
 * $thirdparty				Third party of object
 * $discount_type			0 => Customer discounts, 1 => Supplier discounts
 * $backtopage				URL to come back to from discount modification pages
 * $discount_form_action		Optional: form action URL for discount dropdown on create form (when no object id yet)
 */

 /**
 * @var Object		$object
 * @var Form 		$form
 * @var Translate 	$langs
 * @var Societe		$thirdparty
 * @var	float		$absolute_discount		Amount of fixed discounts available
 * @var	float		$absolute_creditnote	Amount of credit notes available
 * @var int			$cannotApplyDiscount
 */

print '<!-- BEGIN object_discounts.tpl.php -->'."\n";

'
@phan-var-force Propal|Commande|CommandeFournisseur|Facture|FactureFournisseur $object
@phan-var-force Societe 	$thirdparty
@phan-var-force string 		$backtopage
@phan-var-force string 		$filtercreditnote
@phan-var-force string 		$filterabsolutediscount
@phan-var-force int<0,1> 	$discount_type
@phan-var-force int 		$resteapayer
';

$objclassname = get_class($object);
$isInvoice = in_array($object->element, array('facture', 'invoice', 'facture_fourn', 'invoice_supplier'));
$isNewObject = empty($object->id) && empty($object->rowid);

// Clean variables not defined
if (empty($absolute_discount)) {
	$absolute_discount = 0;
}
if (empty($absolute_creditnote)) {
	$absolute_creditnote = 0;
}

// Multicurrency display: use document or third party currency for discount amounts
$display_currency = $conf->currency;
$display_absolute_discount = $absolute_discount;
$display_absolute_creditnote = $absolute_creditnote;
if (isModEnabled('multicurrency')) {
	require_once DOL_DOCUMENT_ROOT.'/multicurrency/class/multicurrency.class.php';
	$dbtmp = isset($object->db) ? $object->db : $db;
	$multicurrency_tx = 0;
	if (!empty($object->multicurrency_code) && $object->multicurrency_code != $conf->currency) {
		if (!empty($object->multicurrency_tx) && (float) $object->multicurrency_tx > 0) {
			$multicurrency_tx = (float) $object->multicurrency_tx;
		} else {
			$tmparray = MultiCurrency::getIdAndTxFromCode($dbtmp, $object->multicurrency_code, !empty($object->date) ? $object->date : 0);
			$multicurrency_tx = !empty($tmparray[1]) ? (float) $tmparray[1] : 0;
		}
		if ($multicurrency_tx > 0) {
			$display_currency = $object->multicurrency_code;
			$display_absolute_discount = price2num($absolute_discount * $multicurrency_tx, 'MT');
			$display_absolute_creditnote = price2num($absolute_creditnote * $multicurrency_tx, 'MT');
		}
	}
	if ($display_currency == $conf->currency && !empty($thirdparty->multicurrency_code) && $thirdparty->multicurrency_code != $conf->currency) {
		$dbtmp = isset($thirdparty->db) ? $thirdparty->db : $db;
		$tmparray = MultiCurrency::getIdAndTxFromCode($dbtmp, $thirdparty->multicurrency_code);
		if (!empty($tmparray[1]) && (float) $tmparray[1] > 0) {
			$display_currency = $thirdparty->multicurrency_code;
			$multicurrency_tx = (float) $tmparray[1];
			$display_absolute_discount = price2num($absolute_discount * $multicurrency_tx, 'MT');
			$display_absolute_creditnote = price2num($absolute_creditnote * $multicurrency_tx, 'MT');
		}
	}
}

// Relative and absolute discounts
$addrelativediscount = '<a class="editfielda" href="'.DOL_URL_ROOT.'/comm/remise.php?id='.((int) $thirdparty->id).'&backtopage='.urlencode($backtopage).'&action=create&token='.newToken().(!empty($discount_type) ? '&discount_type=1' : '').'">'.img_edit($langs->trans("EditRelativeDiscount")).'</a>';
$addabsolutediscount = '<a class="editfielda" href="'.DOL_URL_ROOT.'/comm/remx.php?id='.((int) $thirdparty->id).'&backtopage='.urlencode($backtopage).'&action=create&token='.newToken().'">'.img_edit($langs->trans("EditGlobalDiscounts")).'</a>';
$viewabsolutediscount = '<a class="editfielda" href="'.DOL_URL_ROOT.'/comm/remx.php?id='.((int) $thirdparty->id).'&backtopage='.urlencode($backtopage).'">'.$langs->trans("ViewAvailableGlobalDiscounts").'</a>';

$fixedDiscount = $thirdparty->remise_percent;
if (!empty($discount_type)) {
	$fixedDiscount = $thirdparty->remise_supplier_percent;
}

if ($fixedDiscount > 0) {
	$translationKey = (empty($discount_type)) ? 'CompanyHasRelativeDiscount' : 'HasRelativeDiscountFromSupplier';
	print $langs->trans($translationKey, $fixedDiscount);
} else {
	if ($conf->dol_optimize_smallscreen) {
		$translationKey = 'RelativeDiscount';
	} else {
		$translationKey = (empty($discount_type)) ? 'CompanyHasNoRelativeDiscount' : 'HasNoRelativeDiscountFromSupplier';
	}
	print '<span class="opacitymedium">'.$langs->trans($translationKey).'</span>';
}
// Add link to edit the relative discount
print ' '.$addrelativediscount;


// Is there is commercial discount or down payment available ?
if ($absolute_discount > 0) {
	print '<!-- absolute_discount -->';
	if (!empty($cannotApplyDiscount) || !$isInvoice || $isNewObject || $object->statut > $objclassname::STATUS_DRAFT || $object->type == $objclassname::TYPE_CREDIT_NOTE || $object->type == $objclassname::TYPE_DEPOSIT) {
		// On create form with form action: show dropdown so user can select discount (will be applied after invoice is created)
		if ($isNewObject && $isInvoice && !empty($discount_form_action)) {
			$more = $addabsolutediscount;
			$form->form_remise_dispo($discount_form_action, GETPOSTINT('discountid'), 'remise_id', $thirdparty->id, $absolute_discount, isset($filterabsolutediscount) ? $filterabsolutediscount : '', 0, $more, 0, $discount_type, $display_absolute_discount, $display_currency);
		} else {
			$translationKey = empty($discount_type) ? 'CompanyHasDownPaymentOrCommercialDiscount' : 'HasDownPaymentOrCommercialDiscountFromSupplier';
			$text = $langs->trans($translationKey, price($display_absolute_discount, 0, $langs, 1, -1, -1, $display_currency));

			if ($isInvoice && !$isNewObject && $object->statut > $objclassname::STATUS_DRAFT && $object->type != $objclassname::TYPE_CREDIT_NOTE && $object->type != $objclassname::TYPE_DEPOSIT) {
				$text = $form->textwithpicto($text, $langs->trans('AbsoluteDiscountUse'));
			}
			if ($isNewObject) {
				$text .= ' '.$addabsolutediscount;
			}

			if ($isNewObject) {
				print '<br>'.$text;
			} else {
				print '<div class="inline-block clearboth">'.$text.'</div>';
			}
		}
	} else {
		// Discount available of type fixed amount (not credit note)
		$more = $addabsolutediscount;
		// TODO: Check $resteapayer - is '$maxvalue' in form_remise_dispo()
		$form->form_remise_dispo($_SERVER["PHP_SELF"].'?facid='.$object->id, GETPOSTINT('discountid'), 'remise_id', $thirdparty->id, $absolute_discount, $filterabsolutediscount, $resteapayer, $more, 0, $discount_type, $display_absolute_discount, $display_currency);
	}
}


// Is there credit notes availables ?
if ($absolute_creditnote > 0) {
	print '<!-- absolute_creditnote -->';
	// Show credit note dropdown only in draft (same as deposit/commercial discount)
	if (!empty($cannotApplyDiscount) || !$isInvoice || $isNewObject || $object->statut > $objclassname::STATUS_DRAFT || $object->type == $objclassname::TYPE_CREDIT_NOTE) {
		$translationKey = empty($discount_type) ? 'CompanyHasCreditNote' : 'HasCreditNoteFromSupplier';
		$text = $langs->trans($translationKey, price($display_absolute_creditnote, 0, $langs, 1, -1, -1, $display_currency));

		if ($isInvoice && !$isNewObject && $object->statut == $objclassname::STATUS_DRAFT && $object->type != $objclassname::TYPE_DEPOSIT) {
			$text = $form->textwithpicto($text, $langs->trans('CreditNoteDepositUse'));
		}
		if ($isInvoice && !$isNewObject && $object->statut > $objclassname::STATUS_DRAFT && $object->type != $objclassname::TYPE_CREDIT_NOTE && $object->type != $objclassname::TYPE_DEPOSIT) {
			$text = $form->textwithpicto($text, $langs->trans('AbsoluteDiscountUse'));
		}

		if ($absolute_discount <= 0 || $isNewObject) {
			$text .= ' '.$addabsolutediscount;
		}

		if ($isNewObject) {
			print '<br>'.$text;
		} else {
			print '<div class="inline-block clearboth">'.$text.'</div>';
		}
	} else {  // We can add a credit note on a down payment or standard invoice or situation invoice
		// There is credit notes discounts available; preselect discount from same order as current invoice when set by caller (e.g. facture card)
		$selected_credit = isset($preselected_remise_id_for_payment) ? (int) $preselected_remise_id_for_payment : 0;
		$more = $isInvoice && !$isNewObject ? ' ('.$viewabsolutediscount.')' : '';
		$form->form_remise_dispo($_SERVER["PHP_SELF"].'?facid='.$object->id, $selected_credit, 'remise_id_for_payment', $thirdparty->id, $absolute_creditnote, $filtercreditnote, 0, $more, 0, $discount_type, $display_absolute_creditnote, $display_currency); // We allow credit note even if amount is higher
	}
}

if ($absolute_discount <= 0 && $absolute_creditnote <= 0) {
	if ($conf->dol_optimize_smallscreen) {
		$translationKey = 'AbsoluteDiscount';
	} else {
		$translationKey = !empty($discount_type) ? 'HasNoAbsoluteDiscountFromSupplier' : 'CompanyHasNoAbsoluteDiscount';
	}
	print '<br><span class="opacitymedium">'.$langs->trans($translationKey).'</span>';

	if ($isInvoice && $object->statut == $objclassname::STATUS_DRAFT && $object->type != $objclassname::TYPE_CREDIT_NOTE && $object->type != $objclassname::TYPE_DEPOSIT) {
		print ' '.$addabsolutediscount;
	}
}

print '<!-- END template -->';
