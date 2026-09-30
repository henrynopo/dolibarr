<?php
/* Copyright (C) 2026 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Software Foundation; either version 3 of the
 * License, or (at your option) any later version.
 */

/**
 * \file    custom/slycustom/wise/prepare.php
 * \ingroup slycustom
 * \brief   Prepare a Wise transfer for a supplier invoice (flow A).
 *
 * Shows exactly what will be sent (recipient, amount, currency, the VENDOR's
 * own order reference), creates an UNFUNDED transfer on confirmation, and
 * tracks its state. Funding the transfer manually in the Wise dashboard is
 * the approval gate; once Wise reports outgoing_payment_sent, the Dolibarr
 * supplier payment is recorded (automatically via webhook, or via the
 * manual button here when the event was lost).
 */

$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php failed for SLY Custom wise/prepare.php');
}

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_Incoming.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_Payment.class.php';

$langs->loadLangs(array('bills', 'banks', 'compta', 'slycustom@slycustom'));

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
$form = new Form($db);

if (!$user->admin && !$user->hasRight('slycustom', 'wise', 'read')) {
	accessforbidden();
	exit;
}

$id = (int) GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

// Financial writes (create transfer / record payment / refresh state) need the
// dedicated wise write right; wise read only allows the preview page.
if (in_array($action, array('prepare', 'refresh', 'record'), true)
	&& !$user->admin && !$user->hasRight('slycustom', 'wise', 'write')) {
	accessforbidden();
	exit;
}

$object = new FactureFournisseur($db);
if ($id > 0 && $object->fetch($id) <= 0) {
	$id = 0;
}
if ($id <= 0 || (int) $object->entity !== (int) $conf->entity) {
	accessforbidden('Invoice not found in this entity');
	exit;
}
$object->fetch_thirdparty();

$op = new WiseOutgoingPayment($db);

/**
 * Escaped label for the reference source.
 *
 * @param  string $source reference_source code
 * @return string
 */
function wiseRefSourceLabel($source)
{
	global $langs;
	switch ($source) {
		case 'order_ref_supplier': return $langs->trans("WiseRefFromVendorOrder");
		case 'invoice_ref_supplier': return $langs->trans("WiseRefFromVendorInvoice");
		case 'our_ref': return $langs->trans("WiseRefFromOurRef");
		default: return $source;
	}
}

/*
 * Actions
 */
$backUrl = $_SERVER["PHP_SELF"].'?id='.$id;

if ($action == 'prepare') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$result = $op->prepareTransfer($id, $user);
		if ($result['ok']) {
			setEventMessages($langs->trans("WiseTransferCreated", $result['wise_transfer_id']), null, 'mesgs');
			dol_syslog('wise_prepare invoice='.$id.' transfer='.$result['wise_transfer_id'].' by user '.$user->id, LOG_INFO);
		} else {
			$msg = $langs->trans("WiseTransferFailed");
			setEventMessages($msg.' '.dol_escape_htmltag($result['error']), null, 'errors');
			if (!empty($result['detail'])) {
				dol_syslog('wise_prepare detail: '.$result['detail'], LOG_WARNING);
			}
		}
	}
	header('Location: '.$backUrl);
	exit;
}

if ($action == 'refresh') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$row = $op->fetchActiveForInvoice($id, (int) $conf->entity);
		if ($row && $row->wise_transfer_id) {
			$api = Wise_API::fromEntity($db, (int) $conf->entity);
			if ($api) {
				$profileId = WiseIncomingPayment::getProfileIdForEntity($db, (int) $conf->entity);
				$t = $api->getTransfer((int) $row->wise_transfer_id);
				if (!isset($t['error']) && $t['httpCode'] < 400 && !empty($t['status'])) {
					$op->applyTransferState((int) $row->wise_transfer_id, (string) $t['status'], '');
					setEventMessages($langs->trans("WiseTransferRefreshed").' '.dol_escape_htmltag((string) $t['status']), null, 'mesgs');
				} else {
					setEventMessages($langs->trans("WiseEnrichedKo"), null, 'errors');
				}
			}
		}
	}
	header('Location: '.$backUrl);
	exit;
}

if ($action == 'record') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$row = $op->fetchActiveForInvoice($id, (int) $conf->entity);
		// Fallback path: also allow recording when already SENT but webhook was lost
		if (!$row) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'slycustom_wise_transfer';
			$sql .= ' WHERE fk_facture_fourn = '.$id.' AND entity = '.(int) $conf->entity;
			$sql .= " AND status = '".WiseOutgoingPayment::STATUS_SENT."'";
			$sql .= ' ORDER BY rowid DESC LIMIT 1';
			$resql = $db->query($sql);
			$row = $resql ? $db->fetch_object($resql) : null;
		}
		if ($row) {
			$pid = $op->recordSupplierPayment((int) $row->rowid, $user);
			if ($pid > 0) {
				setEventMessages($langs->trans("WiseRecordedAs").' #'.$pid, null, 'mesgs');
			} else {
				setEventMessages($langs->trans("Error").' ('.$pid.')', null, 'errors');
			}
		}
	}
	header('Location: '.$backUrl);
	exit;
}

/*
 * View
 */
llxHeader('', $langs->trans("WisePrepareTitle"), '', '', 0, 0, '', '', '', 'mod-slycustom page-wise_prepare');
$object->fetch_thirdparty();

$enabled = WiseOutgoingPayment::isEnabled($db, (int) $conf->entity);
$active = $op->fetchActiveForInvoice($id, (int) $conf->entity);
$refInfo = WiseOutgoingPayment::resolveVendorReference($db, $id);
$rib = WiseOutgoingPayment::fetchSupplierIban($db, (int) $object->socid);

$sourceCurrency = strtoupper((string) dolibarr_get_const($db, 'WISE_SOURCE_CURRENCY', (int) $conf->entity));
if ($sourceCurrency === '') {
	$sourceCurrency = strtoupper((string) $conf->currency);
}
$targetCurrency = !empty($object->multicurrency_code) ? strtoupper((string) $object->multicurrency_code) : strtoupper((string) $conf->currency);
$remaining = $object->total_ttc - $object->getSommePaiement();

$headTitle = $langs->trans("WisePrepareTitle").' - '.$object->ref.' - '.$object->thirdparty->name;
print load_fiche_titre(dol_escape_htmltag($headTitle), '<a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?facid='.$id.'">'.$langs->trans("Back").'</a>', 'payment');

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("WiseTransferSummary").'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans("SupplierInvoice").'</td><td>'.dol_escape_htmltag($object->ref).($object->ref_supplier ? ' — '.$langs->trans("RefSupplier").': '.dol_escape_htmltag($object->ref_supplier) : '').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("ThirdParty").'</td><td>'.dol_escape_htmltag($object->thirdparty->name).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("Amount").'</td><td><strong>'.dol_escape_htmltag($targetCurrency).' '.price($remaining).'</strong> ('.$langs->trans("RemainToPay").')</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("WiseSourceCurrency").'</td><td>'.dol_escape_htmltag($sourceCurrency).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("IBAN").'</td><td>'.($rib ? dol_escape_htmltag($rib['iban']).($rib['bic'] ? ' / BIC '.dol_escape_htmltag($rib['bic']) : '') : '<span class="error">'.$langs->trans("WiseNoIban").'</span>').'</td></tr>';
print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("WiseReferenceSent"), $langs->transnoentities("WiseReferenceSentTooltip")).'</td><td><strong>'.dol_escape_htmltag($refInfo['reference'] !== '' ? $refInfo['reference'] : '-').'</strong><br><span class="opacitymedium">'.dol_escape_htmltag(wiseRefSourceLabel($refInfo['source'])).'</span></td></tr>';
print '</table></div>';

// Transfer history for this invoice
$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'slycustom_wise_transfer';
$sql .= ' WHERE fk_facture_fourn = '.$id.' AND entity = '.(int) $conf->entity.' ORDER BY rowid DESC';
$resql = $db->query($sql);
print '<br><div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("Date").'</td><td>Wise #</td><td class="right">'.$langs->trans("Amount").'</td><td>'.$langs->trans("Ref").'</td><td>'.$langs->trans("Status").'</td><td>Wise state</td></tr>';
$hasSentUnrecorded = false;
$anyRow = false;
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$anyRow = true;
		if ($obj->status === WiseOutgoingPayment::STATUS_SENT) {
			$hasSentUnrecorded = true;
		}
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($obj->date_creation).'</td>';
		print '<td>'.(int) $obj->wise_transfer_id.'</td>';
		print '<td class="right">'.dol_escape_htmltag($obj->target_currency).' '.price((float) $obj->target_amount).'</td>';
		print '<td>'.dol_escape_htmltag($obj->reference_sent).'</td>';
		print '<td>'.dol_escape_htmltag($obj->status).'</td>';
		print '<td>'.dol_escape_htmltag($obj->last_state).'</td>';
		print '</tr>';
	}
	$db->free($resql);
}
if (!$anyRow) {
	print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
}
print '</table></div>';

print '<br><div class="tabsAction">';
if (!$enabled) {
	print '<span class="opacitymedium">'.$langs->trans("WiseOutgoingDisabled").'</span>';
} else {
	if ($active && $active->status === WiseOutgoingPayment::STATUS_DRAFT) {
		print '<span class="opacitymedium">'.$langs->trans("WiseAwaitingFunding").'</span> ';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" style="display:inline;">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="refresh">';
		print '<input class="butAction" type="submit" value="'.$langs->trans("WiseRefreshState").'">';
		print '</form>';
	} elseif (!$active && (int) $object->statut === 1 && empty($object->paye) && $remaining > 0.01 && !empty($rib['iban'])) {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" onsubmit="return confirm(\''.dol_escape_js($langs->transnoentities("WisePrepareConfirm")).'\');">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="prepare">';
		print '<input class="butAction" type="submit" value="'.$langs->trans("WiseCreateTransfer").'">';
		print '</form>';
	}
	if ($hasSentUnrecorded) {
		print ' ';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" style="display:inline;">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="record">';
		print '<input class="butAction" type="submit" value="'.$langs->trans("WiseRecordSupplierPayment").'">';
		print '</form>';
	}
}
print '</div>';

llxFooter();
$db->close();
