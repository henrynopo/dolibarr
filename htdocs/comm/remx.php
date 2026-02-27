<?php
/* Copyright (C) 2001-2004 Rodolphe Quiedeville        <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2019 Laurent Destailleur         <eldy@users.sourceforge.net>
 * Copyright (C) 2008      Raphael Bertrand (Resultic) <raphael.bertrand@resultic.fr>
 * Copyright (C) 2019-2024  Frédéric France             <frederic.france@free.fr>
 * Copyright (C) 2024-2025	MDW							<mdeweerd@users.noreply.github.com>
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
 */

/**
 *	    \file       htdocs/comm/remx.php
 *      \ingroup    societe
 *		\brief      Page to edit absolute discounts for a customer
 */

if (! defined('CSRFCHECK_WITH_TOKEN')) {
	define('CSRFCHECK_WITH_TOKEN', '1');
}		// Force use of CSRF protection with tokens even for GET

// Load Dolibarr environment
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Societe $mysoc
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array('orders', 'bills', 'companies'));

$id = GETPOSTINT('id');

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

// Security check
$socid = GETPOSTINT('id') ? GETPOSTINT('id') : GETPOSTINT('socid');
/** @var User $user */
if ($user->socid > 0) {
	$socid = $user->socid;
}

// Security check
if ($user->socid > 0) {
	$id = $user->socid;
}
$result = restrictedArea($user, 'societe', $id, '&societe', '', 'fk_soc', 'rowid', 0);

$permissiontocreate = ($user->hasRight('societe', 'creer') || $user->hasRight('facture', 'creer'));

$hookmanager->initHooks(array('remx'));

/**
 * Return foreign currency code for display (same logic as Remx list columns).
 * Uses discount multicurrency_code, then source invoice multicurrency_code, then local currency if amount set.
 *
 * @param string $multicurrency_code Discount multicurrency_code
 * @param string $fac_multicurrency_code Source invoice multicurrency_code (facture or facture_fourn)
 * @param float  $multicurrency_amount_ttc Foreign amount TTC
 * @param string $conf_currency Local currency code
 * @return string Currency code to use for price() when displaying foreign amount
 */
function remx_get_foreign_currency_code($multicurrency_code, $fac_multicurrency_code, $multicurrency_amount_ttc, $conf_currency)
{
	$cur = isset($multicurrency_code) ? trim((string) $multicurrency_code) : '';
	if (empty($cur) && !empty($fac_multicurrency_code)) {
		$cur = trim((string) $fac_multicurrency_code);
	}
	if (empty($cur) && (float) $multicurrency_amount_ttc != 0) {
		$cur = $conf_currency;
	}
	return $cur;
}

/*
 * Actions
 */

if (GETPOST('cancel', 'alpha') && !empty($backtopage)) {
	header("Location: ".$backtopage);
	exit;
}

if ($action == 'confirm_split' && GETPOST("confirm", "alpha") == 'yes' && $permissiontocreate) {
	$split_currency = GETPOST('split_currency', 'aZ09') ?: 'local';
	$split_type = GETPOST('split_type', 'aZ09') ?: 'ttc';
	$split_count = (int) GETPOST('split_count', 'int');
	if ($split_count < 2 || $split_count > 4) {
		$split_count = 2;
	}
	$amount_1 = price2num(GETPOST('split_amount_1', 'alpha'));
	$amount_2 = price2num(GETPOST('split_amount_2', 'alpha'));
	$amount_3 = price2num(GETPOST('split_amount_3', 'alpha'));

	$error = 0;
	$remid = GETPOSTINT("remid") ?: 0;
	$discount = new DiscountAbsolute($db);
	$res = $discount->fetch($remid);
	if (!($res > 0)) {
		$error++;
		setEventMessages($langs->trans("ErrorFailedToLoadDiscount"), null, 'errors');
	}
	if (!$error && $discount->fk_facture_line) {
		$error++;
		setEventMessages($langs->trans("ErrorCantSplitAUsedDiscount"), null, 'errors');
	}

	if (!$error) {
		$total_local_ttc = (float) $discount->amount_ttc;
		$total_local_ht = (float) $discount->amount_ht;
		$total_mc_ttc = (float) $discount->multicurrency_amount_ttc;
		$total_mc_ht = (float) $discount->multicurrency_amount_ht;
		$tva_tx = (float) $discount->tva_tx;

		if ($split_currency === 'foreign') {
			$total = $split_type === 'ht' ? $total_mc_ht : $total_mc_ttc;
			if ($total <= 0) {
				$error++;
				setEventMessages($langs->trans("ErrorSplitByForeignButNoMulticurrency"), null, 'errors');
			}
		} else {
			$total = $split_type === 'ht' ? $total_local_ht : $total_local_ttc;
		}

		// Build amounts array: first 1..(n-1) from form, last = total - sum
		$amounts = array();
		if ($split_count == 2) {
			$amounts[] = $amount_1;
			$amounts[] = price2num($total - $amount_1);
		} elseif ($split_count == 3) {
			$amounts[] = $amount_1;
			$amounts[] = $amount_2;
			$amounts[] = price2num($total - $amount_1 - $amount_2);
		} else {
			$amounts[] = $amount_1;
			$amounts[] = $amount_2;
			$amounts[] = $amount_3;
			$amounts[] = price2num($total - $amount_1 - $amount_2 - $amount_3);
		}
		$sum = array_sum($amounts);
		if (!$error && abs($sum - $total) > 0.01) {
			$error++;
			setEventMessages($langs->trans("TotalOfSplitDiscountMustEqualsOriginal"), null, 'errors');
		}
	}

	if (!$error) {
		$newdiscounts = array();
		$ratio_mc = ($total_local_ttc != 0 && (float) $discount->multicurrency_amount_ttc != 0)
			? ((float) $discount->multicurrency_amount_ttc / $total_local_ttc) : 0;
		$ratio_local = ($total_mc_ttc != 0 && $total_local_ttc != 0)
			? ($total_local_ttc / $total_mc_ttc) : 0;

		for ($i = 0; $i < $split_count; $i++) {
			$part = $amounts[$i];
			$newdisc = new DiscountAbsolute($db);
			$newdisc->fk_facture_source = $discount->fk_facture_source;
			$newdisc->fk_facture = $discount->fk_facture;
			$newdisc->fk_facture_line = $discount->fk_facture_line;
			$newdisc->fk_invoice_supplier_source = $discount->fk_invoice_supplier_source;
			$newdisc->fk_invoice_supplier = $discount->fk_invoice_supplier;
			$newdisc->fk_invoice_supplier_line = $discount->fk_invoice_supplier_line;
			if ($discount->description == '(CREDIT_NOTE)' || $discount->description == '(DEPOSIT)') {
				$newdisc->description = $discount->description;
			} else {
				$newdisc->description = $discount->description.' ('.($i + 1).')';
			}
			$newdisc->fk_user = $discount->fk_user;
			$newdisc->fk_soc = $discount->fk_soc;
			$newdisc->socid = $discount->socid;
			$newdisc->discount_type = $discount->discount_type;
			$newdisc->datec = $discount->datec;
			$newdisc->tva_tx = $discount->tva_tx;
			$newdisc->vat_src_code = $discount->vat_src_code;
			if (!empty($discount->multicurrency_code)) {
				$newdisc->multicurrency_code = $discount->multicurrency_code;
			}
			if (isset($discount->multicurrency_tx)) {
				$newdisc->multicurrency_tx = $discount->multicurrency_tx;
			}

			if ($split_currency === 'foreign') {
				if ($split_type === 'ht') {
					$newdisc->multicurrency_amount_ht = $part;
					$newdisc->multicurrency_amount_ttc = price2num($part * (1 + $tva_tx / 100), 'MT');
					$newdisc->multicurrency_amount_tva = price2num($newdisc->multicurrency_amount_ttc - $newdisc->multicurrency_amount_ht);
					$newdisc->amount_ttc = $ratio_local > 0 ? price2num($newdisc->multicurrency_amount_ttc * $ratio_local, 'MT') : price2num($part * (1 + $tva_tx / 100) * ($total_local_ttc / max(0.0001, $total)), 'MT');
				} else {
					$newdisc->multicurrency_amount_ttc = $part;
					$newdisc->multicurrency_amount_ht = price2num($part / (1 + $tva_tx / 100), 'MT');
					$newdisc->multicurrency_amount_tva = price2num($part - $newdisc->multicurrency_amount_ht);
					$newdisc->amount_ttc = $ratio_local > 0 ? price2num($part * $ratio_local, 'MT') : price2num($part * ($total_local_ttc / max(0.0001, $total_mc_ttc)), 'MT');
				}
				$newdisc->amount_ht = price2num($newdisc->amount_ttc / (1 + $tva_tx / 100), 'MT');
				$newdisc->amount_tva = price2num($newdisc->amount_ttc - $newdisc->amount_ht);
			} else {
				if ($split_type === 'ht') {
					$newdisc->amount_ht = $part;
					$newdisc->amount_ttc = price2num($part * (1 + $tva_tx / 100), 'MT');
					$newdisc->amount_tva = price2num($newdisc->amount_ttc - $newdisc->amount_ht);
					$newdisc->multicurrency_amount_ttc = $ratio_mc > 0 ? price2num($newdisc->amount_ttc * $ratio_mc, 'MT') : 0;
					$newdisc->multicurrency_amount_ht = price2num($newdisc->multicurrency_amount_ttc / (1 + $tva_tx / 100), 'MT');
					$newdisc->multicurrency_amount_tva = price2num($newdisc->multicurrency_amount_ttc - $newdisc->multicurrency_amount_ht);
				} else {
					$newdisc->amount_ttc = $part;
					$newdisc->amount_ht = price2num($part / (1 + $tva_tx / 100), 'MT');
					$newdisc->amount_tva = price2num($part - $newdisc->amount_ht);
					$newdisc->multicurrency_amount_ttc = $ratio_mc > 0 ? price2num($part * $ratio_mc, 'MT') : 0;
					$newdisc->multicurrency_amount_ht = price2num($newdisc->multicurrency_amount_ttc / (1 + $tva_tx / 100), 'MT');
					$newdisc->multicurrency_amount_tva = price2num($newdisc->multicurrency_amount_ttc - $newdisc->multicurrency_amount_ht);
				}
			}
			$newdiscounts[] = $newdisc;
		}

		// Last discount: set amounts to remainder so totals match exactly (avoids rounding drift)
		if (count($newdiscounts) > 1) {
			$last = end($newdiscounts);
			$sum_local_ttc = $sum_local_ht = $sum_local_tva = 0;
			$sum_mc_ttc = $sum_mc_ht = $sum_mc_tva = 0;
			$n = count($newdiscounts);
			for ($i = 0; $i < $n - 1; $i++) {
				$sum_local_ttc += (float) $newdiscounts[$i]->amount_ttc;
				$sum_local_ht += (float) $newdiscounts[$i]->amount_ht;
				$sum_local_tva += (float) $newdiscounts[$i]->amount_tva;
				$sum_mc_ttc += (float) $newdiscounts[$i]->multicurrency_amount_ttc;
				$sum_mc_ht += (float) $newdiscounts[$i]->multicurrency_amount_ht;
				$sum_mc_tva += (float) $newdiscounts[$i]->multicurrency_amount_tva;
			}
			$last->amount_ttc = price2num($total_local_ttc - $sum_local_ttc, 'MT');
			$last->amount_ht = price2num($total_local_ht - $sum_local_ht, 'MT');
			$last->amount_tva = price2num(($total_local_ttc - $total_local_ht) - ($sum_local_ttc - $sum_local_ht), 'MT');
			$last->multicurrency_amount_ttc = price2num($total_mc_ttc - $sum_mc_ttc, 'MT');
			$last->multicurrency_amount_ht = price2num($total_mc_ht - $sum_mc_ht, 'MT');
			$last->multicurrency_amount_tva = price2num(($total_mc_ttc - $total_mc_ht) - ($sum_mc_ttc - $sum_mc_ht), 'MT');
		}

		$db->begin();
		$discount->fk_facture_source = 0;
		$discount->fk_invoice_supplier_source = 0;
		$res = $discount->delete($user);
		$allok = ($res > 0);
		$newids = array();
		foreach ($newdiscounts as $newdisc) {
			$newid = $newdisc->create($user);
			if ($newid > 0) {
				$newids[] = $newid;
			} else {
				$allok = false;
				break;
			}
		}
		if ($allok && count($newids) == $split_count) {
			$db->commit();
			$parameters = array('remid' => $remid, 'newids' => $newids, 'socid' => $id);
			$hookmanager->executeHooks('afterSplitDiscount', $parameters, $discount, $action);
			header("Location: ".$_SERVER["PHP_SELF"].'?id='.$id.($backtopage ? '&backtopage='.urlencode($backtopage) : ''));
			exit;
		} else {
			$db->rollback();
			if (empty($discount->error)) {
				setEventMessages($langs->trans("ErrorFailedToCreateSplitDiscounts"), null, 'errors');
			} else {
				setEventMessages($discount->error, $discount->errors, 'errors');
			}
		}
	}
}

if ($action == 'setremise' && $permissiontocreate) {
	$amount = price2num(GETPOST('amount', 'alpha'), '', 2);
	$desc = GETPOST('desc', 'alpha');
	$tva_tx = GETPOST('tva_tx', 'alpha');
	$discount_type = GETPOSTISSET('discount_type') ? GETPOST('discount_type', 'alpha') : 0;
	$price_base_type = GETPOST('price_base_type', 'alpha');

	if ($amount > 0) {
		$error = 0;
		if (empty($desc)) {
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ReasonDiscount")), null, 'errors');
			$error++;
		}

		if (!$error) {
			$soc = new Societe($db);
			$soc->fetch($id);
			$discountid = $soc->set_remise_except((float) $amount, $user, $desc, $tva_tx, $discount_type, $price_base_type);

			if ($discountid > 0) {
				if (!empty($backtopage)) {
					header("Location: ".$backtopage.'&discountid='.((int) $discountid));
					exit;
				} else {
					header("Location: remx.php?id=".((int) $id));
					exit;
				}
			} else {
				$error++;
				setEventMessages($soc->error, $soc->errors, 'errors');
			}
		}
	} else {
		setEventMessages($langs->trans("ErrorFieldFormat", $langs->transnoentitiesnoconv("AmountHT")), null, 'errors');
	}
}

if (GETPOST('action', 'aZ09') == 'confirm_remove' && GETPOST("confirm") == 'yes' && $permissiontocreate) {
	$db->begin();

	$discount = new DiscountAbsolute($db);
	$result = $discount->fetch(GETPOSTINT("remid"));
	$result = $discount->delete($user);
	if ($result > 0) {
		$db->commit();
		header("Location: ".$_SERVER["PHP_SELF"].'?id='.$id); // To avoid pb with back
		exit;
	} else {
		setEventMessages($discount->error, $discount->errors, 'errors');
		$db->rollback();
	}
}

// Remove/merge choice: delete_one, merge_into, merge_all, delete_all
if (GETPOST('action', 'aZ09') == 'confirm_remove_choice' && GETPOST("confirm") == 'yes' && $permissiontocreate) {
	$remid = GETPOSTINT("remid");
	$remove_choice = GETPOST('remove_choice', 'aZ09');
	$target_remid = GETPOSTINT('target_remid');

	$discount = new DiscountAbsolute($db);
	$res = $discount->fetch($remid);
	if ($res <= 0) {
		setEventMessages($langs->trans("ErrorFailedToLoadDiscount"), null, 'errors');
	} else {
		$db->begin();
		$ok = false;
		if ($remove_choice === 'delete_one') {
			$result = $discount->deleteOne($user);
			$ok = ($result > 0);
			if (!$ok) {
				setEventMessages($discount->error, $discount->errors, 'errors');
			}
		} elseif ($remove_choice === 'merge_into' && $target_remid > 0 && $target_remid != $remid) {
			$target = new DiscountAbsolute($db);
			if ($target->fetch($target_remid) > 0) {
				$addOk = $target->addAmountsFrom($discount);
				if ($addOk > 0) {
					$result = $discount->deleteOne($user);
					$ok = ($result > 0);
				}
				if (!$ok) {
					setEventMessages($discount->error ?: $target->error, $discount->errors ?: $target->errors, 'errors');
				}
			} else {
				setEventMessages($langs->trans("ErrorFailedToLoadDiscount"), null, 'errors');
			}
		} elseif ($remove_choice === 'merge_all') {
			$sourceFk = $discount->fk_facture_source ?: 0;
			$sourceSup = $discount->fk_invoice_supplier_source ?: 0;
			$sql = "SELECT rowid FROM ".$db->prefix()."societe_remise_except";
			$sql .= " WHERE entity IN (".getEntity('invoice').")";
			$sql .= " AND fk_soc = ".((int) $discount->fk_soc)." AND discount_type = ".(int) $discount->discount_type;
			$sql .= " AND (fk_facture_line IS NULL AND fk_facture IS NULL) AND (fk_invoice_supplier_line IS NULL AND fk_invoice_supplier IS NULL)";
			if ($sourceFk) {
				$sql .= " AND fk_facture_source = ".((int) $sourceFk);
			} else {
				$sql .= " AND fk_invoice_supplier_source = ".((int) $sourceSup);
			}
			$resql = $db->query($sql);
			$rows = array();
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$rows[] = (int) $obj->rowid;
				}
			}
			if (count($rows) < 2) {
				setEventMessages($langs->trans("ErrorNoSameSourceDiscountsToMerge"), null, 'errors');
			} else {
				$first = new DiscountAbsolute($db);
				$first->fetch($rows[0]);
				$sum_ht = (float) $first->amount_ht;
				$sum_tva = (float) $first->amount_tva;
				$sum_ttc = (float) $first->amount_ttc;
				$sum_mc_ht = (float) $first->multicurrency_amount_ht;
				$sum_mc_tva = (float) $first->multicurrency_amount_tva;
				$sum_mc_ttc = (float) $first->multicurrency_amount_ttc;
				for ($i = 1; $i < count($rows); $i++) {
					$oth = new DiscountAbsolute($db);
					$oth->fetch($rows[$i]);
					$sum_ht += (float) $oth->amount_ht;
					$sum_tva += (float) $oth->amount_tva;
					$sum_ttc += (float) $oth->amount_ttc;
					$sum_mc_ht += (float) $oth->multicurrency_amount_ht;
					$sum_mc_tva += (float) $oth->multicurrency_amount_tva;
					$sum_mc_ttc += (float) $oth->multicurrency_amount_ttc;
				}
				$sql = "UPDATE ".$db->prefix()."societe_remise_except";
				$sql .= " SET amount_ht = ".price2num($sum_ht).", amount_tva = ".price2num($sum_tva).", amount_ttc = ".price2num($sum_ttc);
				$sql .= ", multicurrency_amount_ht = ".price2num($sum_mc_ht).", multicurrency_amount_tva = ".price2num($sum_mc_tva).", multicurrency_amount_ttc = ".price2num($sum_mc_ttc);
				$sql .= " WHERE rowid = ".((int) $rows[0]);
				if ($db->query($sql)) {
					for ($i = 1; $i < count($rows); $i++) {
						$del = new DiscountAbsolute($db);
						$del->fetch($rows[$i]);
						$del->deleteOne($user);
					}
					$ok = true;
				} else {
					setEventMessages($db->lasterror(), null, 'errors');
				}
			}
		} elseif ($remove_choice === 'delete_all') {
			$result = $discount->delete($user);
			$ok = ($result > 0);
			if (!$ok) {
				setEventMessages($discount->error, $discount->errors, 'errors');
			}
		} else {
			setEventMessages($langs->trans("ErrorInvalidRemoveChoice"), null, 'errors');
		}
		if ($ok) {
			$db->commit();
			header("Location: ".$_SERVER["PHP_SELF"].'?id='.$id);
			exit;
		}
		$db->rollback();
	}
}


/*
 * View
 */

$form = new Form($db);
$facturestatic = new Facture($db);
$facturefournstatic = new FactureFournisseur($db);
$tmpuser = new User($db);

llxHeader('', $langs->trans("GlobalDiscount"));

if ($socid > 0) {
	// On recupere les donnees societes par l'objet
	$object = new Societe($db);
	$object->fetch($socid);

	$isCustomer = $object->client == 1 || $object->client == 3;
	$isSupplier = $object->fournisseur == 1;

	// Display tabs

	$head = societe_prepare_head($object);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="setremise">';
	print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';

	print dol_get_fiche_head($head, 'absolutediscount', $langs->trans("ThirdParty"), -1, 'company');

	$linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

	dol_banner_tab($object, 'socid', $linkback, ($user->socid ? 0 : 1), 'rowid', 'nom');

	print '<div class="fichecenter">';

	print '<div class="underbanner clearboth"></div>';

	if (!$isCustomer && !$isSupplier) {
		print '<p class="opacitymedium">'.$langs->trans('ThirdpartyIsNeitherCustomerNorClientSoCannotHaveDiscounts').'</p>';

		print dol_get_fiche_end();

		print '</form>';

		llxFooter();
		$db->close();
		exit;
	}


	print '<div class="div-table-responsive-no-min">';
	print '<table class="border centpercent tableforfield borderbottom">';

	if ($isCustomer) {	// Calcul avoirs client en cours
		$remise_all = $remise_user = 0;
		$sql = "SELECT SUM(rc.amount_ht) as amount, rc.fk_user";
		$sql .= " FROM ".MAIN_DB_PREFIX."societe_remise_except as rc";
		$sql .= " WHERE rc.fk_soc = ".((int) $object->id);
		$sql .= " AND rc.entity = ".((int) $conf->entity);
		$sql .= " AND discount_type = 0"; // Exclude supplier discounts
		$sql .= " AND (fk_facture_line IS NULL AND fk_facture IS NULL)";
		$sql .= " GROUP BY rc.fk_user";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$remise_all += (!empty($obj->amount) ? $obj->amount : 0);
				if (!empty($obj->fk_user) && $obj->fk_user == $user->id) {
					$remise_user += (!empty($obj->amount) ? $obj->amount : 0);
				}
			}
		} else {
			dol_print_error($db);
		}

		print '<tr><td class="titlefieldmiddle">'.$langs->trans("CustomerAbsoluteDiscountAllUsers").'</td>';
		print '<td class="amount">'.price($remise_all, 1, $langs, 1, -1, -1, $conf->currency).' '.$langs->trans("HT");
		if (empty($user->fk_soc)) {    // No need to show this for external users
			print $form->textwithpicto('', $langs->trans("CustomerAbsoluteDiscountMy").': '.price($remise_user, 1, $langs, 1, -1, -1, $conf->currency).' '.$langs->trans("HT"));
		}
		print '</td></tr>';
	}

	if ($isSupplier) {
		// Calcul avoirs fournisseur en cours
		$remise_all = $remise_user = 0;
		$sql = "SELECT SUM(rc.amount_ht) as amount, rc.fk_user";
		$sql .= " FROM ".MAIN_DB_PREFIX."societe_remise_except as rc";
		$sql .= " WHERE rc.fk_soc = ".((int) $object->id);
		$sql .= " AND rc.entity = ".((int) $conf->entity);
		$sql .= " AND discount_type = 1"; // Exclude customer discounts
		$sql .= " AND (fk_invoice_supplier_line IS NULL AND fk_invoice_supplier IS NULL)";
		$sql .= " GROUP BY rc.fk_user";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$remise_all += (!empty($obj->amount) ? $obj->amount : 0);
				if (!empty($obj->fk_user) && $obj->fk_user == $user->id) {
					$remise_user += (!empty($obj->amount) ? $obj->amount : 0);
				}
			}
		} else {
			dol_print_error($db);
		}

		print '<tr><td class="titlefieldmiddle">'.$langs->trans("SupplierAbsoluteDiscountAllUsers").'</td>';
		print '<td class="amount">'.price($remise_all, 1, $langs, 1, -1, -1, $conf->currency).' '.$langs->trans("HT");
		if (empty($user->fk_soc)) {    // No need to show this for external users
			print $form->textwithpicto('', $langs->trans("SupplierAbsoluteDiscountMy").' : '.price($remise_user, 1, $langs, 1, -1, -1, $conf->currency).' '.$langs->trans("HT"));
		}
		print '</td></tr>';
	}

	print '</table>';
	print '</div>';

	print '</div>';	// close fichecenter

	print dol_get_fiche_end();


	if ($action == 'create_remise') {
		if ($user->hasRight('societe', 'creer')) {
			print '<br>';

			$discount_type = GETPOSTISSET('discount_type') ? GETPOST('discount_type', 'alpha') : 0;
			if ($isCustomer && $isSupplier) {
				$discounttypelabel = $discount_type == 1 ? 'NewSupplierGlobalDiscount' : 'NewClientGlobalDiscount';
			} else {
				$discounttypelabel = 'NewGlobalDiscount';
			}

			print load_fiche_titre($langs->trans($discounttypelabel), '', '');

			if ($isSupplier && $discount_type == 1) {
				print '<input type="hidden" name="discount_type" value="1" />';
			} else {
				print '<input type="hidden" name="discount_type" value="0" />';
			}

			print dol_get_fiche_head();


			print '<div class="div-table-responsive-no-min">';
			print '<table class="border centpercent">';
			/*if ($isCustomer && $isSupplier) {
				print '<tr><td class="titlefield fieldrequired">'.$langs->trans('DiscountType').'</td>';
				print '<td><input type="radio" name="discount_type" id="discount_type_0" '.($discount_type != 1 ? 'checked="checked" ' : '').'value="0"/> <label for="discount_type_0">'.$langs->trans('Customer').'</label>';
				print ' &nbsp; <input type="radio" name="discount_type" id="discount_type_1" '.($discount_type == 1 ? 'checked="checked" ' : '').'value="1"/> <label for="discount_type_1">'.$langs->trans('Supplier').'</label>';
				print '</td></tr>';
			}*/

			// Amount
			print '<tr><td class="titlefield fieldrequired">'.$langs->trans("Amount").'</td>';
			print '<td><input type="text" size="5" name="amount" value="'.price2num(GETPOST("amount")).'" autofocus>';
			print '<span class="hideonsmartphone">&nbsp;'.$langs->trans("Currency".$conf->currency).'</span></td></tr>';

			// Price base (HT / TTC)
			print '<tr><td class="titlefield">'.$langs->trans("PriceBase").'</td>';
			print '<td>';
			print $form->selectPriceBaseType(GETPOST("price_base_type"), "price_base_type");
			print '</td></tr>';

			// VAT
			print '<tr><td>'.$langs->trans("VAT").'</td>';
			print '<td>';
			print $form->load_tva('tva_tx', (GETPOSTISSET('tva_tx') ? GETPOST('tva_tx', 'alpha') : getDolGlobalString('MAIN_VAT_DEFAULT_IF_AUTODETECT_FAILS', 0)), $mysoc, $object, 0, 0, '', false, 1);
			print '</td></tr>';
			print '<tr><td class="fieldrequired" >'.$langs->trans("NoteReason").'</td>';
			print '<td><input type="text" class="quatrevingtpercent" name="desc" value="'.GETPOST('desc', 'alphanohtml').'"></td></tr>';

			print "</table>";
			print '</div>';

			print dol_get_fiche_end();
		}

		if ($user->hasRight('societe', 'creer')) {
			print '<div class="center">';
			print '<input type="submit" class="button" name="submit" value="'.$langs->trans("AddGlobalDiscount").'">';
			if (!empty($backtopage)) {
				print ' &nbsp; ';
				print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans("Cancel").'">';
			}
			print '</div>';
			print '<br>';
		}
	}

	print '</form>';


	print '<br>';

	if ($action == 'remove') {
		$remid_remove = GETPOSTINT('remid');
		$discount_remove = new DiscountAbsolute($db);
		$same_source_list = array();
		$has_same_source = false;
		if ($remid_remove > 0 && $discount_remove->fetch($remid_remove) > 0) {
			$sql = "SELECT sr.rowid, sr.amount_ht, sr.amount_ttc, sr.multicurrency_amount_ht, sr.multicurrency_amount_ttc, sr.multicurrency_code";
			$sql .= ", COALESCE(fa.multicurrency_code, fsup.multicurrency_code) as fac_multicurrency_code";
			$sql .= " FROM ".$db->prefix()."societe_remise_except as sr";
			$sql .= " LEFT JOIN ".$db->prefix()."facture as fa ON sr.fk_facture_source = fa.rowid";
			$sql .= " LEFT JOIN ".$db->prefix()."facture_fourn as fsup ON sr.fk_invoice_supplier_source = fsup.rowid";
			$sql .= " WHERE sr.entity IN (".getEntity('invoice').")";
			$sql .= " AND sr.fk_soc = ".((int) $discount_remove->fk_soc)." AND sr.discount_type = ".(int) $discount_remove->discount_type;
			$sql .= " AND (sr.fk_facture_line IS NULL AND sr.fk_facture IS NULL) AND (sr.fk_invoice_supplier_line IS NULL AND sr.fk_invoice_supplier IS NULL)";
			if (!empty($discount_remove->fk_facture_source)) {
				$sql .= " AND sr.fk_facture_source = ".((int) $discount_remove->fk_facture_source);
			} elseif (!empty($discount_remove->fk_invoice_supplier_source)) {
				$sql .= " AND sr.fk_invoice_supplier_source = ".((int) $discount_remove->fk_invoice_supplier_source);
			} else {
				$sql .= " AND sr.fk_facture_source IS NULL AND sr.fk_invoice_supplier_source IS NULL";
			}
			$resql_src = $db->query($sql);
			if ($resql_src) {
				while ($o = $db->fetch_object($resql_src)) {
					$same_source_list[] = $o;
				}
				$has_same_source = (count($same_source_list) > 1);
			}
		}
		if ($has_same_source) {
			$same_source_count = count($same_source_list);
			$target_options = array();
			foreach ($same_source_list as $o) {
				if ((int) $o->rowid == $remid_remove) {
					continue;
				}
				// Same logic as Remx list: remx_get_foreign_currency_code + 外币(带符号) - 本币(带符号)
				$remx_cur = remx_get_foreign_currency_code(
					isset($o->multicurrency_code) ? $o->multicurrency_code : '',
					isset($o->fac_multicurrency_code) ? $o->fac_multicurrency_code : '',
					(float) (isset($o->multicurrency_amount_ttc) ? $o->multicurrency_amount_ttc : 0),
					$conf->currency
				);
				if ($remx_cur !== '' && (float) (isset($o->multicurrency_amount_ttc) ? $o->multicurrency_amount_ttc : 0) != 0) {
					$label = $langs->trans('AmountTTC').' '.price($o->multicurrency_amount_ttc, 0, $langs, 0, -1, -1, $remx_cur).' / '.price($o->amount_ttc, 0, $langs, 0, -1, -1, $conf->currency);
				} else {
					$label = $langs->trans('AmountTTC').' '.price($o->amount_ttc, 0, $langs, 0, -1, -1, $conf->currency);
				}
				$label .= ' (ID'.$o->rowid.')';
				$target_options[$o->rowid] = $label;
			}
			// 合并到某笔：同源多于2笔(>=3)；全部合并/删除全部：同源多于1笔(>=2)
			$radio_values = array();
			if ($same_source_count >= 3) {
				$radio_values['merge_into'] = $langs->trans('MergeIntoSameSourceDiscount');
			}
			if ($same_source_count >= 2) {
				$radio_values['merge_all'] = $langs->trans('MergeAllSameSourceIntoOne');
				$radio_values['delete_all'] = $langs->trans('DeleteAllSameSourceDiscounts');
			}
			$default_choice = ($same_source_count >= 3) ? 'merge_into' : 'merge_all';
			$formquestion = array(
				'text' => '<p class="paddingtopbottom">'.$langs->trans('RemoveDiscountChooseOption').'</p><p class="opacitymedium">'.$langs->trans('RemoveDiscountSameSourceMustMergeOrDeleteAll').'</p>',
				0 => array('type' => 'radio', 'name' => 'remove_choice', 'label' => $langs->trans('RemoveOrMergeDiscount'), 'values' => $radio_values, 'default' => $default_choice),
			);
			if ($same_source_count >= 3 && count($target_options) > 0) {
				$formquestion[1] = array('type' => 'select', 'name' => 'target_remid', 'label' => $langs->trans('MergeIntoWhichDiscount'), 'values' => $target_options, 'default' => key($target_options));
			}
			print $form->formconfirm(
				$_SERVER["PHP_SELF"].'?id='.$object->id.'&remid='.$remid_remove.($backtopage ? '&backtopage='.urlencode($backtopage) : ''),
				$langs->trans('RemoveDiscount'),
				'',
				'confirm_remove_choice',
				$formquestion,
				'',
				'remove', // useajax: string => id=dialog-confirm-remove (unique), autoOpen=false
				380,     // height (was wrongly 1 before, so dialog content was invisible)
				520      // width
			);
			// Open our dialog on load (formconfirm with useajax=string does not auto-open)
			print '<script nonce="'.getNonce().'" type="text/javascript">jQuery(function($){ setTimeout(function(){ var $d = $("#dialog-confirm-remove"); if ($d.length && typeof $d.dialog === "function") $d.dialog("open"); }, 100); });</script>';
		} else {
			print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&remid='.$remid_remove.($backtopage ? '&backtopage='.urlencode($backtopage) : ''), $langs->trans('RemoveDiscount'), $langs->trans('ConfirmRemoveDiscount'), 'confirm_remove', '', 0, 1);
		}
	}

	// List column options for "available" and "consumed" discount tables
	$contextpage = $_SERVER["PHP_SELF"].'?id='.$object->id;
	$arrayfields = array(
		'date' => array('label' => 'Date', 'checked' => 1, 'position' => 10),
		'reason' => array('label' => 'ReasonDiscount', 'checked' => 1, 'position' => 20),
		'consumed_by' => array('label' => 'ConsumedBy', 'checked' => 1, 'position' => 30),
		'amount_ht' => array('label' => 'AmountHT', 'checked' => 1, 'position' => 50),
		'vat_rate' => array('label' => 'VATRate', 'checked' => 1, 'position' => 51),
		'amount_ttc' => array('label' => 'AmountTTC', 'checked' => 1, 'position' => 52),
		'author' => array('label' => 'DiscountOfferedBy', 'checked' => 1, 'position' => 70),
		'actions' => array('label' => 'Actions', 'checked' => 1, 'position' => 80),
	);
	// 多币种：顺序为 Currency → 外币列 → 本币列
	if (isModEnabled('multicurrency') || isModEnabled('multicompany')) {
		$arrayfields['multicurrency_code'] = array('label' => 'Currency', 'checked' => 1, 'position' => 40);
		$arrayfields['multicurrency_amount_ht'] = array('label' => 'MulticurrencyAmountHT', 'checked' => 1, 'position' => 41);
		$arrayfields['multicurrency_amount_tva'] = array('label' => 'MulticurrencyAmountVAT', 'checked' => 1, 'position' => 42);
		$arrayfields['multicurrency_amount_ttc'] = array('label' => 'MulticurrencyAmountTTC', 'checked' => 1, 'position' => 43);
		$arrayfields = dol_sort_array($arrayfields, 'position');
	}
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';
	$varpage = $contextpage;
	$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage);

	/*
	 * List not consumed available credits (= linked to no invoice and no invoice line)
	 */

	if ($isCustomer && !$isSupplier) {
		$newcardbutton = dolGetButtonTitle($langs->trans("NewGlobalDiscount"), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create_remise&id='.$id.'&discount_type=0&backtopage='.urlencode($_SERVER["PHP_SELF"].'?id='.$id).'&token='.newToken());
	} elseif (!$isCustomer && $isSupplier) {
		$newcardbutton = dolGetButtonTitle($langs->trans("NewGlobalDiscount"), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create_remise&id='.$id.'&discount_type=1&backtopage='.urlencode($_SERVER["PHP_SELF"].'?id='.$id).'&token='.newToken());
	} else {
		$newcardbutton = '';
	}

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.($backtopage ? '&backtopage='.urlencode($backtopage) : '').'">';
	print '<style>.remx-reason-cell{white-space:normal;word-break:break-word;max-width:22em;}</style>';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="">';
	// 列选择需点击「应用」后提交表单才会保存；仅勾选/取消勾选不会自动保存
	$applycolbutton = ' <button type="submit" class="button small" id="remx_apply_columns" onclick="var f=document.getElementById(\'formfilteraction\'); if(f) f.value=\'listafterchangingselectedfields\'; return true;">'.$langs->trans("Apply").'</button>';
	print load_fiche_titre($langs->trans("DiscountStillRemaining"), $newcardbutton.' '.$selectedfields.$applycolbutton);

	if ($isCustomer) {
		$newcardbutton = dolGetButtonTitle($langs->trans("NewClientGlobalDiscount"), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create_remise&id='.$id.'&discount_type=0&backtopage='.urlencode($_SERVER["PHP_SELF"].'?id='.$id).'&token='.newToken());
		if ($isSupplier) {
			print '<div class="fichecenter">';
		}
		print load_fiche_titre($langs->trans("CustomerDiscounts"), $newcardbutton, '');

		$sql = "SELECT rc.rowid, rc.amount_ht, rc.amount_tva, rc.amount_ttc, rc.tva_tx, rc.vat_src_code,";
		$sql .= " rc.multicurrency_code, rc.multicurrency_amount_ht, rc.multicurrency_amount_tva, rc.multicurrency_amount_ttc,";
		$sql .= " rc.datec as dc, rc.description,";
		$sql .= " rc.fk_facture_source,";
		$sql .= " u.login, u.rowid as user_id, u.statut as status, u.firstname, u.lastname, u.photo,";
		$sql .= " fa.ref as ref, fa.type as type, fa.multicurrency_code as fac_multicurrency_code";
		$sql .= " FROM  ".MAIN_DB_PREFIX."user as u, ".MAIN_DB_PREFIX."societe_remise_except as rc";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture as fa ON rc.fk_facture_source = fa.rowid";
		$sql .= " WHERE rc.fk_soc = ".((int) $object->id);
		$sql .= " AND rc.entity = ".((int) $conf->entity);
		$sql .= " AND u.rowid = rc.fk_user";
		$sql .= " AND rc.discount_type = 0"; // Eliminate supplier discounts
		$sql .= " AND (rc.fk_facture_line IS NULL AND rc.fk_facture IS NULL)";
		$sql .= " ORDER BY rc.datec DESC";

		$resql = $db->query($sql);
		if ($resql) {
			print '<div class="div-table-responsive-no-min">';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			if (!empty($arrayfields['date']['checked'])) {
				print '<td class="widthdate">'.$langs->trans("Date").'</td>';
			}
			if (!empty($arrayfields['reason']['checked'])) {
				print '<td>'.$langs->trans("ReasonDiscount").'</td>';
			}
			if (!empty($arrayfields['consumed_by']['checked'])) {
				print '<td class="nowrap">'.$langs->trans("ConsumedBy").'</td>';
			}
			if (!empty($arrayfields['multicurrency_code']['checked'])) {
				print '<td class="center">'.$langs->trans("Currency").'</td>';
			}
			if (!empty($arrayfields['multicurrency_amount_ht']['checked'])) {
				print '<td class="right tdoverflowmax125" title="'.dol_escape_htmltag($langs->trans("MulticurrencyAmountHT")).'">'.$langs->trans("MulticurrencyAmountHT").'</td>';
			}
			if (!empty($arrayfields['multicurrency_amount_tva']['checked'])) {
				print '<td class="right tdoverflowmax125" title="'.dol_escape_htmltag($langs->trans("MulticurrencyAmountVAT")).'">'.$langs->trans("MulticurrencyAmountVAT").'</td>';
			}
			if (!empty($arrayfields['multicurrency_amount_ttc']['checked'])) {
				print '<td class="right tdoverflowmax125" title="'.dol_escape_htmltag($langs->trans("MulticurrencyAmountTTC")).'">'.$langs->trans("MulticurrencyAmountTTC").'</td>';
			}
			if (!empty($arrayfields['amount_ht']['checked'])) {
				print '<td class="right">'.$langs->trans("AmountHT").'</td>';
			}
			if (!empty($arrayfields['vat_rate']['checked'])) {
				print '<td class="right">'.$langs->trans("VATRate").'</td>';
			}
			if (!empty($arrayfields['amount_ttc']['checked'])) {
				print '<td class="right">'.$langs->trans("AmountTTC").'</td>';
			}
			if (!empty($arrayfields['author']['checked'])) {
				print '<td width="100" class="center">'.$langs->trans("DiscountOfferedBy").'</td>';
			}
			if (!empty($arrayfields['actions']['checked'])) {
				print '<td width="50">&nbsp;</td>';
			}
			print '</tr>';

			$showconfirminfo = array();

			$i = 0;
			$num = $db->num_rows($resql);
			if ($num > 0) {
				while ($i < $num) {
					$obj = $db->fetch_object($resql);

					$tmpuser->id = $obj->user_id;
					$tmpuser->login = $obj->login;
					$tmpuser->firstname = $obj->firstname;
					$tmpuser->lastname = $obj->lastname;
					$tmpuser->photo = $obj->photo;
					$tmpuser->status = $obj->status;

					print '<tr class="oddeven">';
					if (!empty($arrayfields['date']['checked'])) {
						print '<td>'.dol_print_date($db->jdate($obj->dc), 'dayhour', 'tzuserrel').'</td>';
					}
					if (!empty($arrayfields['reason']['checked'])) {
						if (preg_match('/\(CREDIT_NOTE\)/', $obj->description)) {
							print '<td class="remx-reason-cell">';
							$facturestatic->id = $obj->fk_facture_source;
							$facturestatic->ref = $obj->ref;
							$facturestatic->type = $obj->type;
							print preg_replace('/\(CREDIT_NOTE\)/', $langs->trans("CreditNote"), $obj->description).' '.$facturestatic->getNomURl(1);
							print '</td>';
						} elseif (preg_match('/\(DEPOSIT\)/', $obj->description)) {
							print '<td class="remx-reason-cell">';
							$facturestatic->id = $obj->fk_facture_source;
							$facturestatic->ref = $obj->ref;
							$facturestatic->type = $obj->type;
							print preg_replace('/\(DEPOSIT\)/', $langs->trans("InvoiceDeposit"), $obj->description).' '.$facturestatic->getNomURl(1);
							print '</td>';
						} elseif (preg_match('/\(EXCESS RECEIVED\)/', $obj->description)) {
							print '<td class="remx-reason-cell">';
							$facturestatic->id = $obj->fk_facture_source;
							$facturestatic->ref = $obj->ref;
							$facturestatic->type = $obj->type;
							print preg_replace('/\(EXCESS RECEIVED\)/', $langs->trans("ExcessReceived"), $obj->description).' '.$facturestatic->getNomURl(1);
							print '</td>';
						} else {
							print '<td class="remx-reason-cell" title="'.dol_escape_htmltag($obj->description).'">';
							print dol_escape_htmltag($obj->description);
							print '</td>';
						}
					}
					if (!empty($arrayfields['consumed_by']['checked'])) {
						print '<td class="nowrap"><span class="opacitymedium">'.$langs->trans("NotConsumed").'</span></td>';
					}
					$remx_cur = remx_get_foreign_currency_code(
						isset($obj->multicurrency_code) ? $obj->multicurrency_code : '',
						isset($obj->fac_multicurrency_code) ? $obj->fac_multicurrency_code : '',
						(float) (isset($obj->multicurrency_amount_ttc) ? $obj->multicurrency_amount_ttc : 0),
						$conf->currency
					);
					if (!empty($arrayfields['multicurrency_code']['checked'])) {
						print '<td class="center">'.dol_escape_htmltag($remx_cur).'</td>';
					}
					if (!empty($arrayfields['multicurrency_amount_ht']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->multicurrency_amount_ht, 0, $langs, 0, -1, -1, $remx_cur ? $remx_cur : $conf->currency).'</td>';
					}
					if (!empty($arrayfields['multicurrency_amount_tva']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->multicurrency_amount_tva, 0, $langs, 0, -1, -1, $remx_cur ? $remx_cur : $conf->currency).'</td>';
					}
					if (!empty($arrayfields['multicurrency_amount_ttc']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->multicurrency_amount_ttc, 0, $langs, 0, -1, -1, $remx_cur ? $remx_cur : $conf->currency).'</td>';
					}
					if (!empty($arrayfields['amount_ht']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->amount_ht, 0, $langs, 0, -1, -1, $conf->currency).'</td>';
					}
					if (!empty($arrayfields['vat_rate']['checked'])) {
						print '<td class="right nowraponall">'.vatrate($obj->tva_tx.($obj->vat_src_code ? ' ('.$obj->vat_src_code.')' : ''), true).'</td>';
					}
					if (!empty($arrayfields['amount_ttc']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->amount_ttc, 0, $langs, 0, -1, -1, $conf->currency).'</td>';
					}
					if (!empty($arrayfields['author']['checked'])) {
						print '<td class="tdoverflowmax100">';
						print $tmpuser->getNomUrl(-1);
						print '</td>';
					}
					if (!empty($arrayfields['actions']['checked'])) {
						if ($user->hasRight('societe', 'creer') || $user->hasRight('facture', 'creer')) {
							print '<td class="center nowraponall">';
							print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=split&token='.newToken().'&remid='.$obj->rowid.($backtopage ? '&backtopage='.urlencode($backtopage) : '').'">'.img_split($langs->trans("SplitDiscount")).'</a>';
							print '<a class="reposition marginleftonly" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=remove&token='.newToken().'&remid='.$obj->rowid.($backtopage ? '&backtopage='.urlencode($backtopage) : '').'">'.img_delete($langs->trans("RemoveDiscount")).'</a>';
							print '</td>';
						} else {
							print '<td>&nbsp;</td>';
						}
					}
					print '</tr>';

					if ($action == 'split' && GETPOST('remid') == $obj->rowid) {
						$showconfirminfo['rowid'] = $obj->rowid;
						$showconfirminfo['amount_ttc'] = $obj->amount_ttc;
						$showconfirminfo['amount_ht'] = $obj->amount_ht;
						$showconfirminfo['multicurrency_amount_ttc'] = isset($obj->multicurrency_amount_ttc) ? $obj->multicurrency_amount_ttc : 0;
						$showconfirminfo['multicurrency_amount_ht'] = isset($obj->multicurrency_amount_ht) ? $obj->multicurrency_amount_ht : 0;
						$showconfirminfo['tva_tx'] = isset($obj->tva_tx) ? $obj->tva_tx : 0;
						$showconfirminfo['local_currency_label'] = $langs->transnoentities("Currency".$conf->currency);
						$showconfirminfo['foreign_currency_label'] = $remx_cur ? $langs->transnoentities("Currency".$remx_cur) : $langs->transnoentities("Currency".$conf->currency);
					}
					$i++;
				}
			} else {
				$remx_colspan = 0;
				foreach ($arrayfields as $k => $v) {
					if (!empty($v['checked'])) {
						$remx_colspan++;
					}
				}
				print '<tr><td colspan="'.max(1, $remx_colspan).'"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
			}
			$db->free($resql);
			print "</table>";
			print '</div>';

			if (count($showconfirminfo)) {
				$langs->load("dict");
				$tLocalTtc = (float) $showconfirminfo['amount_ttc'];
				$tLocalHt = (float) ($showconfirminfo['amount_ht'] ?? 0);
				$tMcTtc = (float) ($showconfirminfo['multicurrency_amount_ttc'] ?? 0);
				$tMcHt = (float) ($showconfirminfo['multicurrency_amount_ht'] ?? 0);
				$defaultSplitCurrency = ((float) $tMcTtc != 0) ? 'foreign' : 'local';
				$st = (float) ($defaultSplitCurrency === 'foreign' ? $tMcTtc : $tLocalTtc);
				$def1 = price2num($st / 2, 'MT');
				$def2 = price2num($st - $def1, 'MT');
				$def3 = price2num($st / 4, 'MT');
				$localCurrencyLabel = isset($showconfirminfo['local_currency_label']) ? $showconfirminfo['local_currency_label'] : $langs->transnoentities("Currency".$conf->currency);
				$foreignCurrencyLabel = isset($showconfirminfo['foreign_currency_label']) ? $showconfirminfo['foreign_currency_label'] : $langs->transnoentities("Currency".$conf->currency);
				$formquestion = array(
					'text' => $langs->trans('SplitDiscountOptionsAndAmounts'),
					0 => array('type' => 'other', 'value' => '<style type="text/css">#dialog-confirm .confirmquestions .tagtable .tagtr>.tagtd:first-child{width:26%!important;max-width:130px!important}#dialog-confirm .confirmquestions .tagtable .tagtr>.tagtd:last-child{width:74%!important;min-width:260px!important}</style><div class="remx_split_totals" data-local-ttc="'.dol_escape_htmltag($tLocalTtc).'" data-local-ht="'.dol_escape_htmltag($tLocalHt).'" data-mc-ttc="'.dol_escape_htmltag($tMcTtc).'" data-mc-ht="'.dol_escape_htmltag($tMcHt).'" data-local-currency-label="'.dol_escape_htmltag($localCurrencyLabel).'" data-mc-currency-label="'.dol_escape_htmltag($foreignCurrencyLabel).'" style="display:none;"></div>'),
					1 => array('type' => 'radio', 'name' => 'split_currency', 'label' => $langs->trans("Currency"), 'values' => array('local' => $langs->trans("RemxSplitLocalCurrency"), 'foreign' => $langs->trans("RemxSplitOriginalCurrency")), 'default' => $defaultSplitCurrency),
					2 => array('type' => 'radio', 'name' => 'split_type', 'label' => $langs->trans("Amount").' ', 'values' => array('ttc' => $langs->trans("AmountTTC"), 'ht' => $langs->trans("AmountHT")), 'default' => 'ttc'),
					3 => array('type' => 'select', 'name' => 'split_count', 'label' => $langs->trans("SplitInto"), 'values' => array(2 => '2', 3 => '3', 4 => '4'), 'default' => 2),
					4 => array('type' => 'onecolumn', 'value' => '<span class="opacitymedium">'.$langs->trans("LastPartCalculatedAutomatically").'</span>'),
					5 => array('type' => 'text', 'name' => 'split_amount_1', 'label' => $langs->trans("Amount").' 1', 'value' => $def1, 'size' => '12'),
					6 => array('type' => 'text', 'name' => 'split_amount_2', 'label' => $langs->trans("Amount").' 2', 'value' => $def2, 'size' => '12'),
					7 => array('type' => 'text', 'name' => 'split_amount_3', 'label' => $langs->trans("Amount").' 3', 'value' => $def3, 'size' => '12'),
					8 => array('type' => 'other', 'label' => $langs->trans("Amount").' 4', 'value' => '<span class="remx_split_last_val">0</span>'),
					9 => array('type' => 'onecolumn', 'value' => '<script nonce="'.getNonce().'" type="text/javascript">
jQuery(function($){
	function runForContainer($box) {
		var totalsEl = $box.find(".remx_split_totals")[0];
		if (!totalsEl) return;
		var $dialog = $box.closest("[id^=dialog-confirm]");
		function getTotal() {
			var cur = $box.find("input[name=split_currency]:checked").val() || "local";
			var typ = $box.find("input[name=split_type]:checked").val() || "ttc";
			var t = cur === "foreign" ? (typ === "ht" ? parseFloat(totalsEl.getAttribute("data-mc-ht")) : parseFloat(totalsEl.getAttribute("data-mc-ttc"))) : (typ === "ht" ? parseFloat(totalsEl.getAttribute("data-local-ht")) : parseFloat(totalsEl.getAttribute("data-local-ttc")));
			return isNaN(t) ? 0 : t;
		}
		function round2(v) { return Math.round(v * 100) / 100; }
		function formatAmount(v) { var n = Math.round(v * 100) / 100; return n.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
		function updateConfirmMessage() {
			var total = getTotal();
			var cur = $box.find("input[name=split_currency]:checked").val() || "local";
			var label = cur === "foreign" ? (totalsEl.getAttribute("data-mc-currency-label") || "") : (totalsEl.getAttribute("data-local-currency-label") || "");
			var count = parseInt($box.find("[name=split_count]").val(), 10) || 2;
			$dialog.find("#remx_confirm_amount_display").text(formatAmount(total));
			$dialog.find("#remx_confirm_currency_display").text(label);
			$dialog.find("#remx_confirm_count_display").text(count);
		}
		function updateLastPart() {
			var total = getTotal();
			var count = parseInt($box.find("[name=split_count]").val(), 10) || 2;
			var a1 = parseFloat($box.find("[name=split_amount_1]").val()) || 0;
			var a2 = parseFloat($box.find("[name=split_amount_2]").val()) || 0;
			var a3 = parseFloat($box.find("[name=split_amount_3]").val()) || 0;
			if (count === 2) $box.find("[name=split_amount_2]").val(round2(total - a1));
			else if (count === 3) $box.find("[name=split_amount_3]").val(round2(total - a1 - a2));
			else $box.find(".remx_split_last_val").text(round2(total - a1 - a2 - a3));
		}
		function updateSplitForm() {
			var total = getTotal();
			var count = parseInt($box.find("[name=split_count]").val(), 10) || 2;
			$box.find("[name=split_amount_1]").closest(".tagtr").show();
			var $r2 = $box.find("[name=split_amount_2]").closest(".tagtr");
			var $r3 = $box.find("[name=split_amount_3]").closest(".tagtr");
			var $r4 = $box.find(".remx_split_last_val").closest(".tagtr");
			$r2.show();
			$box.find("[name=split_amount_2]").prop("readOnly", count === 2);
			$r3.toggle(count >= 3);
			$box.find("[name=split_amount_3]").prop("readOnly", count === 3);
			$r4.toggle(count >= 4);
			if (count === 2) {
				$box.find("[name=split_amount_1]").val(round2(total/2));
				$box.find("[name=split_amount_2]").val(round2(total - total/2));
			} else if (count === 3) {
				$box.find("[name=split_amount_1]").val(round2(total/3));
				$box.find("[name=split_amount_2]").val(round2(total/3));
				$box.find("[name=split_amount_3]").val(round2(total - total/3 - total/3));
			} else {
				$box.find("[name=split_amount_1]").val(round2(total/4));
				$box.find("[name=split_amount_2]").val(round2(total/4));
				$box.find("[name=split_amount_3]").val(round2(total/4));
				$box.find(".remx_split_last_val").text(round2(total - total/4*3));
			}
			updateLastPart();
			updateConfirmMessage();
		}
		$box.off("change.remx").on("change.remx", "[name=split_count]", updateSplitForm);
		$box.on("change.remx", "input[name=split_currency], input[name=split_type]", updateSplitForm);
		$box.off("input.remx").on("input.remx", "[name=split_amount_1], [name=split_amount_2], [name=split_amount_3]", updateLastPart);
		setTimeout(updateSplitForm, 120);
		$("#dialog-confirm").off("dialogopen.remx").on("dialogopen.remx", updateSplitForm);
	}
	$(".remx_split_totals").each(function(){ runForContainer($(this).closest(".confirmquestions")); });
});
</script>'),
				);
				$confirmMsg = $langs->trans('ConfirmSplitDiscount', price($showconfirminfo['amount_ttc']), $langs->transnoentities("Currency".$conf->currency), '2');
				print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&remid='.$showconfirminfo['rowid'].($backtopage ? '&backtopage='.urlencode($backtopage) : ''), $langs->trans('SplitDiscount'), $confirmMsg, 'confirm_split', $formquestion, '', 1, 420, 520);
			}
		} else {
			dol_print_error($db);
		}
	}

	if ($isSupplier) {
		if ($isCustomer) {
			print '</div>';
			print '<div class="fichecenter">';
		}
		$newcardbutton = $isCustomer ? dolGetButtonTitle($langs->trans("NewSupplierGlobalDiscount"), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create_remise&id='.$id.'&discount_type=1&backtopage='.urlencode($_SERVER["PHP_SELF"].'?id='.$id).'&token='.newToken()) : '';
		print load_fiche_titre($langs->trans("SupplierDiscounts"), $newcardbutton, '');

		/*
		 * Liste remises fixes fournisseur restant en cours (= liees a aucune facture ni ligne de facture)
		 */
		$sql = "SELECT rc.rowid, rc.amount_ht, rc.amount_tva, rc.amount_ttc, rc.tva_tx, rc.vat_src_code,";
		$sql .= " rc.multicurrency_code, rc.multicurrency_amount_ht, rc.multicurrency_amount_tva, rc.multicurrency_amount_ttc,";
		$sql .= " rc.datec as dc, rc.description,";
		$sql .= " rc.fk_invoice_supplier_source,";
		$sql .= " u.login, u.rowid as user_id, u.statut as status, u.firstname, u.lastname, u.photo,";
		$sql .= " fa.ref, fa.type as type, fa.multicurrency_code as fac_multicurrency_code";
		$sql .= " FROM  ".MAIN_DB_PREFIX."user as u, ".MAIN_DB_PREFIX."societe_remise_except as rc";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn as fa ON rc.fk_invoice_supplier_source = fa.rowid";
		$sql .= " WHERE rc.fk_soc = ".((int) $object->id);
		$sql .= " AND rc.entity = ".((int) $conf->entity);
		$sql .= " AND u.rowid = rc.fk_user";
		$sql .= " AND rc.discount_type = 1"; // Eliminate customer discounts
		$sql .= " AND (rc.fk_invoice_supplier IS NULL AND rc.fk_invoice_supplier_line IS NULL)";
		$sql .= " ORDER BY rc.datec DESC";

		$resql = $db->query($sql);
		if ($resql) {
			print '<div class="div-table-responsive-no-min">';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			if (!empty($arrayfields['date']['checked'])) {
				print '<td class="widthdate">'.$langs->trans("Date").'</td>';
			}
			if (!empty($arrayfields['reason']['checked'])) {
				print '<td>'.$langs->trans("ReasonDiscount").'</td>';
			}
			if (!empty($arrayfields['consumed_by']['checked'])) {
				print '<td class="nowrap">'.$langs->trans("ConsumedBy").'</td>';
			}
			if (!empty($arrayfields['multicurrency_code']['checked'])) {
				print '<td class="center">'.$langs->trans("Currency").'</td>';
			}
			if (!empty($arrayfields['multicurrency_amount_ht']['checked'])) {
				print '<td class="right tdoverflowmax125" title="'.dol_escape_htmltag($langs->trans("MulticurrencyAmountHT")).'">'.$langs->trans("MulticurrencyAmountHT").'</td>';
			}
			if (!empty($arrayfields['multicurrency_amount_tva']['checked'])) {
				print '<td class="right tdoverflowmax125" title="'.dol_escape_htmltag($langs->trans("MulticurrencyAmountVAT")).'">'.$langs->trans("MulticurrencyAmountVAT").'</td>';
			}
			if (!empty($arrayfields['multicurrency_amount_ttc']['checked'])) {
				print '<td class="right tdoverflowmax125" title="'.dol_escape_htmltag($langs->trans("MulticurrencyAmountTTC")).'">'.$langs->trans("MulticurrencyAmountTTC").'</td>';
			}
			if (!empty($arrayfields['amount_ht']['checked'])) {
				print '<td class="right">'.$langs->trans("AmountHT").'</td>';
			}
			if (!empty($arrayfields['vat_rate']['checked'])) {
				print '<td class="right">'.$langs->trans("VATRate").'</td>';
			}
			if (!empty($arrayfields['amount_ttc']['checked'])) {
				print '<td class="right">'.$langs->trans("AmountTTC").'</td>';
			}
			if (!empty($arrayfields['author']['checked'])) {
				print '<td width="100" class="center">'.$langs->trans("DiscountOfferedBy").'</td>';
			}
			if (!empty($arrayfields['actions']['checked'])) {
				print '<td width="50">&nbsp;</td>';
			}
			print '</tr>';

			$showconfirminfo = array();

			$i = 0;
			$num = $db->num_rows($resql);
			if ($num > 0) {
				while ($i < $num) {
					$obj = $db->fetch_object($resql);

					$tmpuser->id = $obj->user_id;
					$tmpuser->login = $obj->login;
					$tmpuser->firstname = $obj->firstname;
					$tmpuser->lastname = $obj->lastname;
					$tmpuser->photo = $obj->photo;
					$tmpuser->status = $obj->status;

					print '<tr class="oddeven">';
					if (!empty($arrayfields['date']['checked'])) {
						print '<td>'.dol_print_date($db->jdate($obj->dc), 'dayhour', 'tzuserrel').'</td>';
					}
					if (!empty($arrayfields['reason']['checked'])) {
						if (preg_match('/\(CREDIT_NOTE\)/', $obj->description)) {
							print '<td class="remx-reason-cell">';
							$facturefournstatic->id = $obj->fk_invoice_supplier_source;
							$facturefournstatic->ref = $obj->ref;
							$facturefournstatic->type = $obj->type;
							print preg_replace('/\(CREDIT_NOTE\)/', $langs->trans("CreditNote"), $obj->description).' '.$facturefournstatic->getNomURl(1);
							print '</td>';
						} elseif (preg_match('/\(DEPOSIT\)/', $obj->description)) {
							print '<td class="remx-reason-cell">';
							$facturefournstatic->id = $obj->fk_invoice_supplier_source;
							$facturefournstatic->ref = $obj->ref;
							$facturefournstatic->type = $obj->type;
							print preg_replace('/\(DEPOSIT\)/', $langs->trans("InvoiceDeposit"), $obj->description).' '.$facturefournstatic->getNomURl(1);
							print '</td>';
						} elseif (preg_match('/\(EXCESS PAID\)/', $obj->description)) {
							print '<td class="remx-reason-cell">';
							$facturefournstatic->id = $obj->fk_invoice_supplier_source;
							$facturefournstatic->ref = $obj->ref;
							$facturefournstatic->type = $obj->type;
							print preg_replace('/\(EXCESS PAID\)/', $langs->trans("ExcessPaid"), $obj->description).' '.$facturefournstatic->getNomURl(1);
							print '</td>';
						} else {
							print '<td class="remx-reason-cell" title="'.dol_escape_htmltag($obj->description).'">';
							print dol_escape_htmltag($obj->description);
							print '</td>';
						}
					}
					if (!empty($arrayfields['consumed_by']['checked'])) {
						print '<td class="nowrap"><span class="opacitymedium">'.$langs->trans("NotConsumed").'</span></td>';
					}
					$remx_cur = remx_get_foreign_currency_code(
						isset($obj->multicurrency_code) ? $obj->multicurrency_code : '',
						isset($obj->fac_multicurrency_code) ? $obj->fac_multicurrency_code : '',
						(float) (isset($obj->multicurrency_amount_ttc) ? $obj->multicurrency_amount_ttc : 0),
						$conf->currency
					);
					if (!empty($arrayfields['multicurrency_code']['checked'])) {
						print '<td class="center">'.dol_escape_htmltag($remx_cur).'</td>';
					}
					if (!empty($arrayfields['multicurrency_amount_ht']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->multicurrency_amount_ht, 0, $langs, 0, -1, -1, $remx_cur ? $remx_cur : $conf->currency).'</td>';
					}
					if (!empty($arrayfields['multicurrency_amount_tva']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->multicurrency_amount_tva, 0, $langs, 0, -1, -1, $remx_cur ? $remx_cur : $conf->currency).'</td>';
					}
					if (!empty($arrayfields['multicurrency_amount_ttc']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->multicurrency_amount_ttc, 0, $langs, 0, -1, -1, $remx_cur ? $remx_cur : $conf->currency).'</td>';
					}
					if (!empty($arrayfields['amount_ht']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->amount_ht, 0, $langs, 0, -1, -1, $conf->currency).'</td>';
					}
					if (!empty($arrayfields['vat_rate']['checked'])) {
						print '<td class="right nowraponall">'.vatrate($obj->tva_tx.($obj->vat_src_code ? ' ('.$obj->vat_src_code.')' : ''), true).'</td>';
					}
					if (!empty($arrayfields['amount_ttc']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->amount_ttc, 0, $langs, 0, -1, -1, $conf->currency).'</td>';
					}
					if (!empty($arrayfields['author']['checked'])) {
						print '<td class="tdoverflowmax100">';
						print $tmpuser->getNomUrl(-1);
						print '</td>';
					}
					if (!empty($arrayfields['actions']['checked'])) {
						if ($user->hasRight('societe', 'creer') || $user->hasRight('facture', 'creer')) {
							print '<td class="center nowraponall">';
							print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=split&token='.newToken().'&remid='.$obj->rowid.($backtopage ? '&backtopage='.urlencode($backtopage) : '').'">'.img_split($langs->trans("SplitDiscount")).'</a>';
							print '<a class="reposition marginleftonly" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=remove&token='.newToken().'&remid='.$obj->rowid.($backtopage ? '&backtopage='.urlencode($backtopage) : '').'">'.img_delete($langs->trans("RemoveDiscount")).'</a>';
							print '</td>';
						} else {
							print '<td>&nbsp;</td>';
						}
					}
					print '</tr>';

					if ($action == 'split' && GETPOST('remid') == $obj->rowid) {
						$showconfirminfo['rowid'] = $obj->rowid;
						$showconfirminfo['amount_ttc'] = $obj->amount_ttc;
						$showconfirminfo['amount_ht'] = $obj->amount_ht;
						$showconfirminfo['multicurrency_amount_ttc'] = isset($obj->multicurrency_amount_ttc) ? $obj->multicurrency_amount_ttc : 0;
						$showconfirminfo['multicurrency_amount_ht'] = isset($obj->multicurrency_amount_ht) ? $obj->multicurrency_amount_ht : 0;
						$showconfirminfo['tva_tx'] = isset($obj->tva_tx) ? $obj->tva_tx : 0;
						$showconfirminfo['local_currency_label'] = $langs->transnoentities("Currency".$conf->currency);
						$showconfirminfo['foreign_currency_label'] = $remx_cur ? $langs->transnoentities("Currency".$remx_cur) : $langs->transnoentities("Currency".$conf->currency);
					}
					$i++;
				}
			} else {
				$remx_colspan = 0;
				foreach ($arrayfields as $k => $v) {
					if (!empty($v['checked'])) {
						$remx_colspan++;
					}
				}
				print '<tr><td colspan="'.max(1, $remx_colspan).'"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
			}
			$db->free($resql);
			print "</table>";
			print '</div>';

			if (count($showconfirminfo)) {
				$langs->load("dict");
				$tLocalTtc = (float) $showconfirminfo['amount_ttc'];
				$tLocalHt = (float) ($showconfirminfo['amount_ht'] ?? 0);
				$tMcTtc = (float) ($showconfirminfo['multicurrency_amount_ttc'] ?? 0);
				$tMcHt = (float) ($showconfirminfo['multicurrency_amount_ht'] ?? 0);
				$defaultSplitCurrency = ((float) $tMcTtc != 0) ? 'foreign' : 'local';
				$st = (float) ($defaultSplitCurrency === 'foreign' ? $tMcTtc : $tLocalTtc);
				$def1 = price2num($st / 2, 'MT');
				$def2 = price2num($st - $def1, 'MT');
				$def3 = price2num($st / 4, 'MT');
				$localCurrencyLabel = isset($showconfirminfo['local_currency_label']) ? $showconfirminfo['local_currency_label'] : $langs->transnoentities("Currency".$conf->currency);
				$foreignCurrencyLabel = isset($showconfirminfo['foreign_currency_label']) ? $showconfirminfo['foreign_currency_label'] : $langs->transnoentities("Currency".$conf->currency);
				$formquestion = array(
					'text' => $langs->trans('SplitDiscountOptionsAndAmounts'),
					0 => array('type' => 'other', 'value' => '<style type="text/css">#dialog-confirm .confirmquestions .tagtable .tagtr>.tagtd:first-child{width:26%!important;max-width:130px!important}#dialog-confirm .confirmquestions .tagtable .tagtr>.tagtd:last-child{width:74%!important;min-width:260px!important}</style><div class="remx_split_totals" data-local-ttc="'.dol_escape_htmltag($tLocalTtc).'" data-local-ht="'.dol_escape_htmltag($tLocalHt).'" data-mc-ttc="'.dol_escape_htmltag($tMcTtc).'" data-mc-ht="'.dol_escape_htmltag($tMcHt).'" data-local-currency-label="'.dol_escape_htmltag($localCurrencyLabel).'" data-mc-currency-label="'.dol_escape_htmltag($foreignCurrencyLabel).'" style="display:none;"></div>'),
					1 => array('type' => 'radio', 'name' => 'split_currency', 'label' => $langs->trans("Currency"), 'values' => array('local' => $langs->trans("RemxSplitLocalCurrency"), 'foreign' => $langs->trans("RemxSplitOriginalCurrency")), 'default' => $defaultSplitCurrency),
					2 => array('type' => 'radio', 'name' => 'split_type', 'label' => $langs->trans("Amount").' ', 'values' => array('ttc' => $langs->trans("AmountTTC"), 'ht' => $langs->trans("AmountHT")), 'default' => 'ttc'),
					3 => array('type' => 'select', 'name' => 'split_count', 'label' => $langs->trans("SplitInto"), 'values' => array(2 => '2', 3 => '3', 4 => '4'), 'default' => 2),
					4 => array('type' => 'onecolumn', 'value' => '<span class="opacitymedium">'.$langs->trans("LastPartCalculatedAutomatically").'</span>'),
					5 => array('type' => 'text', 'name' => 'split_amount_1', 'label' => $langs->trans("Amount").' 1', 'value' => $def1, 'size' => '12'),
					6 => array('type' => 'text', 'name' => 'split_amount_2', 'label' => $langs->trans("Amount").' 2', 'value' => $def2, 'size' => '12'),
					7 => array('type' => 'text', 'name' => 'split_amount_3', 'label' => $langs->trans("Amount").' 3', 'value' => $def3, 'size' => '12'),
					8 => array('type' => 'other', 'label' => $langs->trans("Amount").' 4', 'value' => '<span class="remx_split_last_val">0</span>'),
					9 => array('type' => 'onecolumn', 'value' => '<script nonce="'.getNonce().'" type="text/javascript">
jQuery(function($){
	function runForContainer($box) {
		var totalsEl = $box.find(".remx_split_totals")[0];
		if (!totalsEl) return;
		var $dialog = $box.closest("[id^=dialog-confirm]");
		function getTotal() {
			var cur = $box.find("input[name=split_currency]:checked").val() || "local";
			var typ = $box.find("input[name=split_type]:checked").val() || "ttc";
			var t = cur === "foreign" ? (typ === "ht" ? parseFloat(totalsEl.getAttribute("data-mc-ht")) : parseFloat(totalsEl.getAttribute("data-mc-ttc"))) : (typ === "ht" ? parseFloat(totalsEl.getAttribute("data-local-ht")) : parseFloat(totalsEl.getAttribute("data-local-ttc")));
			return isNaN(t) ? 0 : t;
		}
		function round2(v) { return Math.round(v * 100) / 100; }
		function formatAmount(v) { var n = Math.round(v * 100) / 100; return n.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
		function updateConfirmMessage() {
			var total = getTotal();
			var cur = $box.find("input[name=split_currency]:checked").val() || "local";
			var label = cur === "foreign" ? (totalsEl.getAttribute("data-mc-currency-label") || "") : (totalsEl.getAttribute("data-local-currency-label") || "");
			var count = parseInt($box.find("[name=split_count]").val(), 10) || 2;
			$dialog.find("#remx_confirm_amount_display").text(formatAmount(total));
			$dialog.find("#remx_confirm_currency_display").text(label);
			$dialog.find("#remx_confirm_count_display").text(count);
		}
		function updateLastPart() {
			var total = getTotal();
			var count = parseInt($box.find("[name=split_count]").val(), 10) || 2;
			var a1 = parseFloat($box.find("[name=split_amount_1]").val()) || 0;
			var a2 = parseFloat($box.find("[name=split_amount_2]").val()) || 0;
			var a3 = parseFloat($box.find("[name=split_amount_3]").val()) || 0;
			if (count === 2) $box.find("[name=split_amount_2]").val(round2(total - a1));
			else if (count === 3) $box.find("[name=split_amount_3]").val(round2(total - a1 - a2));
			else $box.find(".remx_split_last_val").text(round2(total - a1 - a2 - a3));
		}
		function updateSplitForm() {
			var total = getTotal();
			var count = parseInt($box.find("[name=split_count]").val(), 10) || 2;
			$box.find("[name=split_amount_1]").closest(".tagtr").show();
			var $r2 = $box.find("[name=split_amount_2]").closest(".tagtr");
			var $r3 = $box.find("[name=split_amount_3]").closest(".tagtr");
			var $r4 = $box.find(".remx_split_last_val").closest(".tagtr");
			$r2.show();
			$box.find("[name=split_amount_2]").prop("readOnly", count === 2);
			$r3.toggle(count >= 3);
			$box.find("[name=split_amount_3]").prop("readOnly", count === 3);
			$r4.toggle(count >= 4);
			if (count === 2) {
				$box.find("[name=split_amount_1]").val(round2(total/2));
				$box.find("[name=split_amount_2]").val(round2(total - total/2));
			} else if (count === 3) {
				$box.find("[name=split_amount_1]").val(round2(total/3));
				$box.find("[name=split_amount_2]").val(round2(total/3));
				$box.find("[name=split_amount_3]").val(round2(total - total/3 - total/3));
			} else {
				$box.find("[name=split_amount_1]").val(round2(total/4));
				$box.find("[name=split_amount_2]").val(round2(total/4));
				$box.find("[name=split_amount_3]").val(round2(total/4));
				$box.find(".remx_split_last_val").text(round2(total - total/4*3));
			}
			updateLastPart();
			updateConfirmMessage();
		}
		$box.off("change.remx").on("change.remx", "[name=split_count]", updateSplitForm);
		$box.on("change.remx", "input[name=split_currency], input[name=split_type]", updateSplitForm);
		$box.off("input.remx").on("input.remx", "[name=split_amount_1], [name=split_amount_2], [name=split_amount_3]", updateLastPart);
		setTimeout(updateSplitForm, 120);
		$("#dialog-confirm").off("dialogopen.remx").on("dialogopen.remx", updateSplitForm);
	}
	$(".remx_split_totals").each(function(){ runForContainer($(this).closest(".confirmquestions")); });
});
</script>'),
				);
				$confirmMsg = $langs->trans('ConfirmSplitDiscount', price($showconfirminfo['amount_ttc']), $langs->transnoentities("Currency".$conf->currency), '2');
				print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&remid='.$showconfirminfo['rowid'].($backtopage ? '&backtopage='.urlencode($backtopage) : ''), $langs->trans('SplitDiscount'), $confirmMsg, 'confirm_split', $formquestion, 0, 1, 420, 520);
			}
		} else {
			dol_print_error($db);
		}
		if ($isCustomer) {
			print '</div>';
		}
	}

	print '<div class="clearboth"></div><br><br>';

	/*
	 * List discount consumed (=liees a une ligne de facture ou facture)
	 */

	print load_fiche_titre($langs->trans("DiscountAlreadyCounted"));

	if ($isCustomer) {
		if ($isSupplier) {
			print '<div class="fichecenter">';
		}
		print load_fiche_titre($langs->trans("CustomerDiscounts"), '', '');

		// Discount linked to invoice lines
		$sql = "SELECT rc.rowid, rc.amount_ht, rc.amount_tva, rc.amount_ttc, rc.tva_tx, rc.vat_src_code,";
		$sql .= " rc.multicurrency_code, rc.multicurrency_amount_ht, rc.multicurrency_amount_tva, rc.multicurrency_amount_ttc,";
		$sql .= " rc.datec as dc, rc.description, rc.fk_facture_line, rc.fk_facture_source,";
		$sql .= " u.login, u.rowid as user_id, u.statut as status, u.firstname, u.lastname, u.photo,";
		$sql .= " f.rowid as invoiceid, f.ref,";
		$sql .= " fa.ref as invoice_source_ref, fa.type as type, fa.multicurrency_code as fac_multicurrency_code";
		$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
		$sql .= " , ".MAIN_DB_PREFIX."user as u";
		$sql .= " , ".MAIN_DB_PREFIX."facturedet as fc";
		$sql .= " , ".MAIN_DB_PREFIX."societe_remise_except as rc";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture as fa ON rc.fk_facture_source = fa.rowid";
		$sql .= " WHERE rc.fk_soc = ".((int) $object->id);
		$sql .= " AND rc.fk_facture_line = fc.rowid";
		$sql .= " AND fc.fk_facture = f.rowid";
		$sql .= " AND rc.fk_user = u.rowid";
		$sql .= " AND rc.discount_type = 0"; // Eliminate supplier discounts
		$sql .= " ORDER BY dc DESC";
		//$sql.= " UNION ";
		// Discount linked to invoices
		$sql2 = "SELECT rc.rowid, rc.amount_ht, rc.amount_tva, rc.amount_ttc, rc.tva_tx, rc.vat_src_code,";
		$sql2 .= " rc.multicurrency_code, rc.multicurrency_amount_ht, rc.multicurrency_amount_tva, rc.multicurrency_amount_ttc,";
		$sql2 .= " rc.datec as dc, rc.description, rc.fk_facture, rc.fk_facture_source,";
		$sql2 .= " u.login, u.rowid as user_id, u.statut as status, u.firstname, u.lastname, u.photo,";
		$sql2 .= " f.rowid as invoiceid, f.ref,";
		$sql2 .= " fa.ref as invoice_source_ref, fa.type as type, fa.multicurrency_code as fac_multicurrency_code";
		$sql2 .= " FROM ".MAIN_DB_PREFIX."facture as f";
		$sql2 .= " , ".MAIN_DB_PREFIX."user as u";
		$sql2 .= " , ".MAIN_DB_PREFIX."societe_remise_except as rc";
		$sql2 .= " LEFT JOIN ".MAIN_DB_PREFIX."facture as fa ON rc.fk_facture_source = fa.rowid";
		$sql2 .= " WHERE rc.fk_soc = ".((int) $object->id);
		$sql2 .= " AND rc.fk_facture = f.rowid";
		$sql2 .= " AND rc.fk_user = u.rowid";
		$sql2 .= " AND rc.discount_type = 0"; // Eliminate supplier discounts
		$sql2 .= " ORDER BY dc DESC";

		$resql = $db->query($sql);
		$resql2 = null;
		if ($resql) {
			$resql2 = $db->query($sql2);
		}
		if ($resql2) {
			print '<div class="div-table-responsive-no-min">';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			if (!empty($arrayfields['date']['checked'])) {
				print '<td class="widthdate">'.$langs->trans("Date").'</td>';
			}
			if (!empty($arrayfields['reason']['checked'])) {
				print '<td>'.$langs->trans("ReasonDiscount").'</td>';
			}
			if (!empty($arrayfields['consumed_by']['checked'])) {
				print '<td class="nowrap">'.$langs->trans("ConsumedBy").'</td>';
			}
			if (!empty($arrayfields['multicurrency_code']['checked'])) {
				print '<td class="center">'.$langs->trans("Currency").'</td>';
			}
			if (!empty($arrayfields['multicurrency_amount_ht']['checked'])) {
				print '<td class="right tdoverflowmax125" title="'.dol_escape_htmltag($langs->trans("MulticurrencyAmountHT")).'">'.$langs->trans("MulticurrencyAmountHT").'</td>';
			}
			if (!empty($arrayfields['multicurrency_amount_tva']['checked'])) {
				print '<td class="right tdoverflowmax125" title="'.dol_escape_htmltag($langs->trans("MulticurrencyAmountVAT")).'">'.$langs->trans("MulticurrencyAmountVAT").'</td>';
			}
			if (!empty($arrayfields['multicurrency_amount_ttc']['checked'])) {
				print '<td class="right tdoverflowmax125" title="'.dol_escape_htmltag($langs->trans("MulticurrencyAmountTTC")).'">'.$langs->trans("MulticurrencyAmountTTC").'</td>';
			}
			if (!empty($arrayfields['amount_ht']['checked'])) {
				print '<td class="right">'.$langs->trans("AmountHT").'</td>';
			}
			if (!empty($arrayfields['vat_rate']['checked'])) {
				print '<td class="right">'.$langs->trans("VATRate").'</td>';
			}
			if (!empty($arrayfields['amount_ttc']['checked'])) {
				print '<td class="right">'.$langs->trans("AmountTTC").'</td>';
			}
			if (!empty($arrayfields['author']['checked'])) {
				print '<td width="100" class="center">'.$langs->trans("Author").'</td>';
			}
			if (!empty($arrayfields['actions']['checked'])) {
				print '<td width="50">&nbsp;</td>';
			}
			print '</tr>';

			$tab_sqlobj = array();
			$tab_sqlobjOrder = array();
			$num = $db->num_rows($resql);
			if ($num > 0) {
				for ($i = 0; $i < $num; $i++) {
					$sqlobj = $db->fetch_object($resql);
					$tab_sqlobj[] = $sqlobj;
					$tab_sqlobjOrder[] = $db->jdate($sqlobj->dc);
				}
			}
			$db->free($resql);

			$num = $db->num_rows($resql2);
			for ($i = 0; $i < $num; $i++) {
				$sqlobj = $db->fetch_object($resql2);
				$tab_sqlobj[] = $sqlobj;
				$tab_sqlobjOrder[] = $db->jdate($sqlobj->dc);
			}
			$db->free($resql2);
			$array1_sort_order = SORT_DESC;
			array_multisort($tab_sqlobjOrder, $array1_sort_order, $tab_sqlobj);

			$num = count($tab_sqlobj);
			if ($num > 0) {
				$i = 0;
				while ($i < $num) {
					$obj = array_shift($tab_sqlobj);

					$tmpuser->id = $obj->user_id;
					$tmpuser->login = $obj->login;
					$tmpuser->firstname = $obj->firstname;
					$tmpuser->lastname = $obj->lastname;
					$tmpuser->photo = $obj->photo;
					$tmpuser->status = $obj->status;

					print '<tr class="oddeven">';
					if (!empty($arrayfields['date']['checked'])) {
						print '<td>'.dol_print_date($db->jdate($obj->dc), 'dayhour').'</td>';
					}
					if (!empty($arrayfields['reason']['checked'])) {
						if (preg_match('/\(CREDIT_NOTE\)/', $obj->description)) {
							print '<td class="remx-reason-cell">';
							$facturestatic->id = $obj->fk_facture_source;
							$facturestatic->ref = $obj->invoice_source_ref;
							$facturestatic->type = $obj->type;
							print preg_replace('/\(CREDIT_NOTE\)/', $langs->trans("CreditNote"), $obj->description).' '.$facturestatic->getNomURl(1);
							print '</td>';
						} elseif (preg_match('/\(DEPOSIT\)/', $obj->description)) {
							print '<td class="remx-reason-cell">';
							$facturestatic->id = $obj->fk_facture_source;
							$facturestatic->ref = $obj->invoice_source_ref;
							$facturestatic->type = $obj->type;
							print preg_replace('/\(DEPOSIT\)/', $langs->trans("InvoiceDeposit"), $obj->description).' '.$facturestatic->getNomURl(1);
							print '</td>';
						} elseif (preg_match('/\(EXCESS RECEIVED\)/', $obj->description)) {
							print '<td class="remx-reason-cell">';
							$facturestatic->id = $obj->fk_facture_source;
							$facturestatic->ref = $obj->invoice_source_ref;
							$facturestatic->type = $obj->type;
							print preg_replace('/\(EXCESS RECEIVED\)/', $langs->trans("Invoice"), $obj->description).' '.$facturestatic->getNomURl(1);
							print '</td>';
						} else {
							print '<td class="remx-reason-cell" title="'.dol_escape_htmltag($obj->description).'">';
							print dol_escape_htmltag($obj->description);
							print '</td>';
						}
					}
					if (!empty($arrayfields['consumed_by']['checked'])) {
						print '<td class="left nowrap">';
						if ($obj->invoiceid) {
							print '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->invoiceid.'">'.img_object($langs->trans("ShowBill"), 'bill').' '.$obj->ref.'</a>';
						}
						print '</td>';
					}
					$remx_cur = remx_get_foreign_currency_code(
						isset($obj->multicurrency_code) ? $obj->multicurrency_code : '',
						isset($obj->fac_multicurrency_code) ? $obj->fac_multicurrency_code : '',
						(float) (isset($obj->multicurrency_amount_ttc) ? $obj->multicurrency_amount_ttc : 0),
						$conf->currency
					);
					if (!empty($arrayfields['multicurrency_code']['checked'])) {
						print '<td class="center">'.dol_escape_htmltag($remx_cur).'</td>';
					}
					if (!empty($arrayfields['multicurrency_amount_ht']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->multicurrency_amount_ht, 0, $langs, 0, -1, -1, $remx_cur ? $remx_cur : $conf->currency).'</td>';
					}
					if (!empty($arrayfields['multicurrency_amount_tva']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->multicurrency_amount_tva, 0, $langs, 0, -1, -1, $remx_cur ? $remx_cur : $conf->currency).'</td>';
					}
					if (!empty($arrayfields['multicurrency_amount_ttc']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->multicurrency_amount_ttc, 0, $langs, 0, -1, -1, $remx_cur ? $remx_cur : $conf->currency).'</td>';
					}
					if (!empty($arrayfields['amount_ht']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->amount_ht, 0, $langs, 0, -1, -1, $conf->currency).'</td>';
					}
					if (!empty($arrayfields['vat_rate']['checked'])) {
						print '<td class="right nowraponall">'.vatrate($obj->tva_tx.($obj->vat_src_code ? ' ('.$obj->vat_src_code.')' : ''), true).'</td>';
					}
					if (!empty($arrayfields['amount_ttc']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->amount_ttc, 0, $langs, 0, -1, -1, $conf->currency).'</td>';
					}
					if (!empty($arrayfields['author']['checked'])) {
						print '<td class="tdoverflowmax100">';
						print $tmpuser->getNomUrl(-1);
						print '</td>';
					}
					if (!empty($arrayfields['actions']['checked'])) {
						print '<td>&nbsp;</td>';
					}
					print '</tr>';
					$i++;
				}
			} else {
				$remx_colspan = 0;
				foreach ($arrayfields as $k => $v) {
					if (!empty($v['checked'])) {
						$remx_colspan++;
					}
				}
				print '<tr><td colspan="'.max(1, $remx_colspan).'"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
			}

			print "</table>";
			print '</div>';
		} else {
			dol_print_error($db);
		}
	}

	if ($isSupplier) {
		if ($isCustomer) {
			print '</div>';
			print '<div class="fichecenter">';
		}
		print load_fiche_titre($langs->trans("SupplierDiscounts"), '', '');

		// Discount linked to invoice lines
		$sql = "SELECT rc.rowid, rc.amount_ht, rc.amount_tva, rc.amount_ttc, rc.tva_tx, rc.vat_src_code,";
		$sql .= " rc.multicurrency_code, rc.multicurrency_amount_ht, rc.multicurrency_amount_tva, rc.multicurrency_amount_ttc,";
		$sql .= " rc.datec as dc, rc.description, rc.fk_invoice_supplier_line,";
		$sql .= " rc.fk_invoice_supplier_source,";
		$sql .= " u.login, u.rowid as user_id, u.statut as user_status, u.firstname, u.lastname, u.photo,";
		$sql .= " f.rowid as invoiceid, f.ref as ref,";
		$sql .= " fa.ref as invoice_source_ref, fa.type as type, fa.multicurrency_code as fac_multicurrency_code";
		$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn as f";
		$sql .= " , ".MAIN_DB_PREFIX."user as u";
		$sql .= " , ".MAIN_DB_PREFIX."facture_fourn_det as fc";
		$sql .= " , ".MAIN_DB_PREFIX."societe_remise_except as rc";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn as fa ON rc.fk_invoice_supplier_source = fa.rowid";
		$sql .= " WHERE rc.fk_soc = ".((int) $object->id);
		$sql .= " AND rc.fk_invoice_supplier_line = fc.rowid";
		$sql .= " AND fc.fk_facture_fourn = f.rowid";
		$sql .= " AND rc.fk_user = u.rowid";
		$sql .= " AND rc.discount_type = 1"; // Eliminate customer discounts
		$sql .= " ORDER BY dc DESC";
		//$sql.= " UNION ";
		// Discount linked to invoices
		$sql2 = "SELECT rc.rowid, rc.amount_ht, rc.amount_tva, rc.amount_ttc, rc.tva_tx, rc.vat_src_code,";
		$sql2 .= " rc.multicurrency_code, rc.multicurrency_amount_ht, rc.multicurrency_amount_tva, rc.multicurrency_amount_ttc,";
		$sql2 .= " rc.datec as dc, rc.description, rc.fk_invoice_supplier,";
		$sql2 .= " rc.fk_invoice_supplier_source,";
		$sql2 .= " u.login, u.rowid as user_id, u.statut as user_status, u.firstname, u.lastname, u.photo,";
		$sql2 .= " f.rowid as invoiceid, f.ref as ref,";
		$sql2 .= " fa.ref as invoice_source_ref, fa.type as type, fa.multicurrency_code as fac_multicurrency_code";
		$sql2 .= " FROM ".MAIN_DB_PREFIX."facture_fourn as f";
		$sql2 .= " , ".MAIN_DB_PREFIX."user as u";
		$sql2 .= " , ".MAIN_DB_PREFIX."societe_remise_except as rc";
		$sql2 .= " LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn as fa ON rc.fk_invoice_supplier_source = fa.rowid";
		$sql2 .= " WHERE rc.fk_soc = ".((int) $object->id);
		$sql2 .= " AND rc.fk_invoice_supplier = f.rowid";
		$sql2 .= " AND rc.fk_user = u.rowid";
		$sql2 .= " AND rc.discount_type = 1"; // Eliminate customer discounts
		$sql2 .= " ORDER BY dc DESC";

		$resql = $db->query($sql);
		$resql2 = null;
		if ($resql) {
			$resql2 = $db->query($sql2);
		}
		if ($resql2) {
			print '<div class="div-table-responsive-no-min">';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre">';
			if (!empty($arrayfields['date']['checked'])) {
				print '<td class="widthdate">'.$langs->trans("Date").'</td>';
			}
			if (!empty($arrayfields['reason']['checked'])) {
				print '<td>'.$langs->trans("ReasonDiscount").'</td>';
			}
			if (!empty($arrayfields['consumed_by']['checked'])) {
				print '<td class="nowrap">'.$langs->trans("ConsumedBy").'</td>';
			}
			if (!empty($arrayfields['multicurrency_code']['checked'])) {
				print '<td class="center">'.$langs->trans("Currency").'</td>';
			}
			if (!empty($arrayfields['multicurrency_amount_ht']['checked'])) {
				print '<td class="right tdoverflowmax125" title="'.dol_escape_htmltag($langs->trans("MulticurrencyAmountHT")).'">'.$langs->trans("MulticurrencyAmountHT").'</td>';
			}
			if (!empty($arrayfields['multicurrency_amount_tva']['checked'])) {
				print '<td class="right tdoverflowmax125" title="'.dol_escape_htmltag($langs->trans("MulticurrencyAmountVAT")).'">'.$langs->trans("MulticurrencyAmountVAT").'</td>';
			}
			if (!empty($arrayfields['multicurrency_amount_ttc']['checked'])) {
				print '<td class="right tdoverflowmax125" title="'.dol_escape_htmltag($langs->trans("MulticurrencyAmountTTC")).'">'.$langs->trans("MulticurrencyAmountTTC").'</td>';
			}
			if (!empty($arrayfields['amount_ht']['checked'])) {
				print '<td class="right">'.$langs->trans("AmountHT").'</td>';
			}
			if (!empty($arrayfields['vat_rate']['checked'])) {
				print '<td class="right">'.$langs->trans("VATRate").'</td>';
			}
			if (!empty($arrayfields['amount_ttc']['checked'])) {
				print '<td class="right">'.$langs->trans("AmountTTC").'</td>';
			}
			if (!empty($arrayfields['author']['checked'])) {
				print '<td width="100" class="center">'.$langs->trans("Author").'</td>';
			}
			if (!empty($arrayfields['actions']['checked'])) {
				print '<td width="50">&nbsp;</td>';
			}
			print '</tr>';

			$tab_sqlobj = array();
			$tab_sqlobjOrder = array();
			$num = $db->num_rows($resql);
			if ($num > 0) {
				for ($i = 0; $i < $num; $i++) {
					$sqlobj = $db->fetch_object($resql);
					$tab_sqlobj[] = $sqlobj;
					$tab_sqlobjOrder[] = $db->jdate($sqlobj->dc);
				}
			}
			$db->free($resql);

			$num = $db->num_rows($resql2);
			for ($i = 0; $i < $num; $i++) {
				$sqlobj = $db->fetch_object($resql2);
				$tab_sqlobj[] = $sqlobj;
				$tab_sqlobjOrder[] = $db->jdate($sqlobj->dc);
			}
			$db->free($resql2);
			$array1_sort_order = SORT_DESC;
			array_multisort($tab_sqlobjOrder, $array1_sort_order, $tab_sqlobj);

			$num = count($tab_sqlobj);
			if ($num > 0) {
				$i = 0;
				while ($i < $num) {
					$obj = array_shift($tab_sqlobj);

					$tmpuser->id = $obj->user_id;
					$tmpuser->login = $obj->login;
					$tmpuser->firstname = $obj->firstname;
					$tmpuser->lastname = $obj->lastname;
					$tmpuser->photo = $obj->photo;
					$tmpuser->status = isset($obj->status) ? $obj->status : $obj->user_status;

					print '<tr class="oddeven">';
					if (!empty($arrayfields['date']['checked'])) {
						print '<td>'.dol_print_date($db->jdate($obj->dc), 'dayhour').'</td>';
					}
					if (!empty($arrayfields['reason']['checked'])) {
						if (preg_match('/\(CREDIT_NOTE\)/', $obj->description)) {
							print '<td class="remx-reason-cell">';
							$facturefournstatic->id = $obj->fk_invoice_supplier_source;
							$facturefournstatic->ref = $obj->invoice_source_ref;
							$facturefournstatic->type = $obj->type;
							print preg_replace('/\(CREDIT_NOTE\)/', $langs->trans("CreditNote"), $obj->description).' '.$facturefournstatic->getNomURl(1);
							print '</td>';
						} elseif (preg_match('/\(DEPOSIT\)/', $obj->description)) {
							print '<td class="remx-reason-cell">';
							$facturefournstatic->id = $obj->fk_invoice_supplier_source;
							$facturefournstatic->ref = $obj->invoice_source_ref;
							$facturefournstatic->type = $obj->type;
							print preg_replace('/\(DEPOSIT\)/', $langs->trans("InvoiceDeposit"), $obj->description).' '.$facturefournstatic->getNomURl(1);
							print '</td>';
						} elseif (preg_match('/\(EXCESS PAID\)/', $obj->description)) {
							print '<td class="remx-reason-cell">';
							$facturefournstatic->id = $obj->fk_invoice_supplier_source;
							$facturefournstatic->ref = $obj->invoice_source_ref;
							$facturefournstatic->type = $obj->type;
							print preg_replace('/\(EXCESS PAID\)/', $langs->trans("Invoice"), $obj->description).' '.$facturefournstatic->getNomURl(1);
							print '</td>';
						} else {
							print '<td class="remx-reason-cell" title="'.dol_escape_htmltag($obj->description).'">';
							print dol_escape_htmltag($obj->description);
							print '</td>';
						}
					}
					if (!empty($arrayfields['consumed_by']['checked'])) {
						print '<td class="left nowrap">';
						if ($obj->invoiceid) {
							print '<a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?facid='.$obj->invoiceid.'">'.img_object($langs->trans("ShowBill"), 'bill').' '.$obj->ref.'</a>';
						}
						print '</td>';
					}
					$remx_cur = remx_get_foreign_currency_code(
						isset($obj->multicurrency_code) ? $obj->multicurrency_code : '',
						isset($obj->fac_multicurrency_code) ? $obj->fac_multicurrency_code : '',
						(float) (isset($obj->multicurrency_amount_ttc) ? $obj->multicurrency_amount_ttc : 0),
						$conf->currency
					);
					if (!empty($arrayfields['multicurrency_code']['checked'])) {
						print '<td class="center">'.dol_escape_htmltag($remx_cur).'</td>';
					}
					if (!empty($arrayfields['multicurrency_amount_ht']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->multicurrency_amount_ht, 0, $langs, 0, -1, -1, $remx_cur ? $remx_cur : $conf->currency).'</td>';
					}
					if (!empty($arrayfields['multicurrency_amount_tva']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->multicurrency_amount_tva, 0, $langs, 0, -1, -1, $remx_cur ? $remx_cur : $conf->currency).'</td>';
					}
					if (!empty($arrayfields['multicurrency_amount_ttc']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->multicurrency_amount_ttc, 0, $langs, 0, -1, -1, $remx_cur ? $remx_cur : $conf->currency).'</td>';
					}
					if (!empty($arrayfields['amount_ht']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->amount_ht, 0, $langs, 0, -1, -1, $conf->currency).'</td>';
					}
					if (!empty($arrayfields['vat_rate']['checked'])) {
						print '<td class="right nowraponall">'.vatrate($obj->tva_tx.($obj->vat_src_code ? ' ('.$obj->vat_src_code.')' : ''), true).'</td>';
					}
					if (!empty($arrayfields['amount_ttc']['checked'])) {
						print '<td class="right nowraponall amount">'.price($obj->amount_ttc, 0, $langs, 0, -1, -1, $conf->currency).'</td>';
					}
					if (!empty($arrayfields['author']['checked'])) {
						print '<td class="tdoverflowmax100">';
						print $tmpuser->getNomUrl(-1);
						print '</td>';
					}
					if (!empty($arrayfields['actions']['checked'])) {
						print '<td>&nbsp;</td>';
					}
					print '</tr>';
					$i++;
				}
			} else {
				$remx_colspan = 0;
				foreach ($arrayfields as $k => $v) {
					if (!empty($v['checked'])) {
						$remx_colspan++;
					}
				}
				print '<tr><td colspan="'.max(1, $remx_colspan).'"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
			}

			print "</table>";
			print '</div>';
		} else {
			dol_print_error($db);
		}

		if ($isCustomer) {
			print '</div>';
		}
	}
}

// End of page
llxFooter();
$db->close();
