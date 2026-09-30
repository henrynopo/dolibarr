<?php
/* Copyright (C) 2026 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Software Foundation; either version 3 of the
 * License, or (at your option) any later version.
 */

/**
 * \file    custom/slycustom/wise/reconcile.php
 * \ingroup slycustom
 * \brief   Wise incoming payments reconcile queue — the human confirmation
 *          step: review credits, their matched invoice candidates, allocate
 *          amounts and record the Dolibarr payment (Paiement + bank line +
 *          auto-close). Nothing is ever recorded without confirmation here.
 *
 * Access: admin or the "wise/read" permission of the slycustom module.
 */

$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php failed for SLY Custom wise/reconcile.php');
}

require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_Incoming.class.php';

$langs->loadLangs(array('bills', 'banks', 'compta', 'slycustom@slycustom'));

if (!$user->admin && !$user->hasRight('slycustom', 'wise', 'read')) {
	accessforbidden();
	exit;
}

$action = GETPOST('action', 'aZ09');
$rowid = (int) GETPOST('rowid', 'int');

// Recording a payment writes bookkeeping entries: require the wise write
// right, not just the queue view right (admins pass).
if ($action == 'record' && !$user->admin && !$user->hasRight('slycustom', 'wise', 'write')) {
	accessforbidden();
	exit;
}
$statusFilter = GETPOST('status', 'aZ09');
if (!in_array($statusFilter, array(WiseIncomingPayment::STATUS_NEW, WiseIncomingPayment::STATUS_ENRICHED, WiseIncomingPayment::STATUS_RECORDED, WiseIncomingPayment::STATUS_IGNORED), true)) {
	$statusFilter = '';
}

$wp = new WiseIncomingPayment($db);

/**
 * Map a recordPayment() error code to a translated message.
 *
 * @param  int    $code Error code (<0)
 * @return string
 */
function wiseRecordErrorLabel($code)
{
	global $langs;
	switch ($code) {
		case -2: return $langs->trans("WiseErrAlready");
		case -3: return $langs->trans("WiseErrAlloc");
		case -5:
		case -6: return $langs->trans("WiseErrNotOpen");
		case -7: return $langs->trans("WiseErrCurrency");
		case -8: return $langs->trans("WiseErrOverAlloc");
		case -9: return $langs->trans("WiseErrOverCredit");
		case -10: return $langs->trans("WiseErrCreate");
		default: return $langs->trans("Error").' ('.$code.')';
	}
}

/*
 * Actions (POST with CSRF token; redirect after work to avoid resubmission)
 */
if ($action == 'ignore' && $rowid > 0) {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$row = $wp->fetchRow($rowid);
		if (!$row || (int) $row['entity'] !== (int) $conf->entity) {
			accessforbidden();
			exit;
		}
		if ($row['status'] !== WiseIncomingPayment::STATUS_RECORDED) {
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_incoming';
			$sql .= " SET status = '".WiseIncomingPayment::STATUS_IGNORED."', tms = tms";
			$sql .= " WHERE rowid = ".(int) $rowid;
			if ($db->query($sql)) {
				setEventMessages($langs->trans("WiseIgnoredOk"), null, 'mesgs');
			} else {
				setEventMessages($langs->trans("Error").' '.$db->lasterror, null, 'errors');
			}
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?status='.dol_escape_htmltag($statusFilter));
	exit;
}

if ($action == 'enrich' && $rowid > 0) {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$row = $wp->fetchRow($rowid);
		if (!$row || (int) $row['entity'] !== (int) $conf->entity) {
			accessforbidden();
			exit;
		}
		$r = $wp->enrichRow($rowid);
		if ($r === true) {
			setEventMessages($langs->trans("WiseEnrichedOk"), null, 'mesgs');
		} elseif ($r === null) {
			setEventMessages($langs->trans("WiseEnrichedPending"), null, 'warnings');
		} else {
			setEventMessages($langs->trans("WiseEnrichedKo"), null, 'errors');
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?rowid='.$rowid.'&status='.dol_escape_htmltag($statusFilter));
	exit;
}

if ($action == 'record' && $rowid > 0) {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$row = $wp->fetchRow($rowid);
		if (!$row || (int) $row['entity'] !== (int) $conf->entity) {
			accessforbidden();
			exit;
		}
		$facs = GETPOST('fac', 'array');
		$amounts = GETPOST('amount', 'array');
		$allocations = array();
		if (is_array($facs) && is_array($amounts)) {
			foreach ($facs as $facid => $on) {
				$facid = (int) $facid;
				$amtRaw = isset($amounts[$facid]) ? trim((string) $amounts[$facid]) : '';
				$amt = price2num($amtRaw);
				if ($facid > 0 && $amtRaw !== '' && $amt !== '' && is_numeric($amt) && (float) $amt > 0) {
					$allocations[$facid] = (float) $amt;
				}
			}
		}
		if (empty($allocations)) {
			setEventMessages($langs->trans("WiseErrAlloc"), null, 'errors');
		} else {
			$pid = $wp->recordPayment($rowid, $allocations, $user);
			if ($pid > 0) {
				setEventMessages($langs->trans("WiseRecordedAs").' #'.$pid, null, 'mesgs');
			} else {
				setEventMessages(wiseRecordErrorLabel($pid), null, 'errors');
			}
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?rowid='.$rowid.'&status='.dol_escape_htmltag($statusFilter));
	exit;
}

/*
 * View
 */
llxHeader('', $langs->trans("WiseReconcileTitle"), '', '', 0, 0, '', '', '', 'mod-slycustom page-wise_reconcile');
print load_fiche_titre($langs->trans("WiseReconcileTitle"), '', 'payment');

// Status quick filters with counts
$counts = array();
$sql = 'SELECT status, COUNT(*) AS n FROM '.MAIN_DB_PREFIX.'slycustom_wise_incoming';
$sql .= ' WHERE entity = '.(int) $conf->entity.' GROUP BY status';
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$counts[$obj->status] = (int) $obj->n;
	}
	$db->free($resql);
}
$statusList = array(
	WiseIncomingPayment::STATUS_NEW => $langs->trans("WiseStatusNew"),
	WiseIncomingPayment::STATUS_ENRICHED => $langs->trans("WiseStatusEnriched"),
	WiseIncomingPayment::STATUS_RECORDED => $langs->trans("WiseStatusRecorded"),
	WiseIncomingPayment::STATUS_IGNORED => $langs->trans("WiseStatusIgnored"),
);
print '<div style="margin-bottom:10px;">';
print '<a class="butAction'.($statusFilter === '' ? ' butActionRefused' : '').'" style="margin-right:4px;" href="'.$_SERVER["PHP_SELF"].'?status=">'.$langs->trans("All").'</a> ';
foreach ($statusList as $st => $label) {
	$n = isset($counts[$st]) ? $counts[$st] : 0;
	$css = ($statusFilter === $st) ? 'butActionRefused' : 'butAction';
	print '<a class="'.$css.'" style="margin-right:4px;" href="'.$_SERVER["PHP_SELF"].'?status='.dol_escape_htmltag($st).'">'.dol_escape_htmltag($label).' ('.$n.')</a> ';
}
print '</div>';

// Detail view: one credit + candidates + allocation form
if ($rowid > 0) {
	$row = $wp->fetchRow($rowid);
	if (!$row || (int) $row['entity'] !== (int) $conf->entity) {
		print '<div class="error">'.$langs->trans("RecordNotFound").'</div>';
	} else {
		// Candidate invoices: compute live so payments made meanwhile are reflected
		$match = $wp->findInvoiceCandidates($row);
		$candidates = isset($match['invoices']) && is_array($match['invoices']) ? $match['invoices'] : array();

		// Thirdparty names for candidates
		$socNames = array();
		$socIds = array();
		foreach ($candidates as $c) {
			if (!empty($c['socid'])) {
				$socIds[] = (int) $c['socid'];
			}
		}
		if (!empty($socIds)) {
			$sql = 'SELECT rowid, nom FROM '.MAIN_DB_PREFIX.'societe WHERE rowid IN ('.implode(',', array_unique($socIds)).')';
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$socNames[(int) $obj->rowid] = $obj->nom;
				}
				$db->free($resql);
			}
		}

		// Default allocation (FIFO on same-currency candidates only)
		$defaults = array();
		$creditLeft = (float) $row['amount'];
		foreach ($candidates as $c) {
			if ($c['currency'] === strtoupper((string) $row['currency']) && $c['remaining'] > 0 && $creditLeft > 0.009) {
				$take = min($c['remaining'], $creditLeft);
				$defaults[$c['id']] = $take;
				$creditLeft -= $take;
			}
		}

		$canRecord = ($row['status'] !== WiseIncomingPayment::STATUS_RECORDED);

		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td colspan="4">'.$langs->trans("WiseCreditDetail").' #'.$row['rowid'].'</td></tr>';
		print '<tr class="oddeven"><td class="titlefield">'.$langs->trans("Date").'</td><td>'.dol_escape_htmltag($row['occurred_at']).' UTC</td>';
		print '<td>'.$langs->trans("Amount").'</td><td><strong>'.dol_escape_htmltag($row['currency']).' '.price($row['amount']).'</strong></td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans("Ref").'</td><td>'.($row['ref_text'] !== '' ? dol_escape_htmltag($row['ref_text']) : '<span class="opacitymedium">'.$langs->trans("None").'</span>').'</td>';
		print '<td>'.$langs->trans("ThirdParty").'</td><td>'.($row['counterparty'] !== '' ? dol_escape_htmltag($row['counterparty']) : '<span class="opacitymedium">'.$langs->trans("None").'</span>').'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans("Status").'</td><td>'.dol_escape_htmltag($row['status']).'</td>';
		print '<td>'.$langs->trans("Fees").'</td><td>'.($row['fees'] !== null ? price($row['fees']) : '-').'</td></tr>';
		if ($row['status'] === WiseIncomingPayment::STATUS_RECORDED && $row['fk_paiement']) {
			print '<tr class="oddeven"><td>'.$langs->trans("Payment").'</td><td colspan="3"><a href="'.DOL_URL_ROOT.'/compta/paiement/card.php?id='.(int) $row['fk_paiement'].'">'.$langs->trans("WiseRecordedAs").' #'.(int) $row['fk_paiement'].'</a></td></tr>';
		}
		if ($row['note_private'] !== '') {
			print '<tr class="oddeven"><td>'.$langs->trans("Note").'</td><td colspan="3">'.dol_escape_htmltag($row['note_private']).'</td></tr>';
		}
		print '</table></div>';
		print '<br>';

		// Candidates + allocation form
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="record">';
		print '<input type="hidden" name="rowid" value="'.$row['rowid'].'">';
		print '<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">';
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td></td><td>'.$langs->trans("Invoice").'</td><td>'.$langs->trans("ThirdParty").'</td><td>'.$langs->trans("Date").'</td>';
		print '<td class="right">'.$langs->trans("Amount").'</td><td class="right">'.$langs->trans("RemainToPay").'</td><td class="right">'.$langs->trans("WiseAllocate").'</td></tr>';
		if (!empty($candidates)) {
			foreach ($candidates as $c) {
				$prefill = isset($defaults[$c['id']]) ? price2num($defaults[$c['id']], 'MT') : '';
				$lowConfidence = (isset($c['confidence']) && $c['confidence'] === 'amount');
				print '<tr class="oddeven">';
				print '<td><input type="checkbox" name="fac['.(int) $c['id'].']" value="1"'.($prefill !== '' ? ' checked' : '').'></td>';
				print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $c['id'].'">'.dol_escape_htmltag($c['ref']).'</a>'
					.($lowConfidence ? ' '.img_warning($langs->trans("WiseLowConfidence")) : '').'</td>';
				print '<td>'.dol_escape_htmltag(isset($socNames[$c['socid']]) ? $socNames[$c['socid']] : $c['socid']).'</td>';
				print '<td>'.dol_escape_htmltag($c['datef']).'</td>';
				print '<td class="right">'.dol_escape_htmltag($c['currency']).' '.price($c['total_ttc']).'</td>';
				print '<td class="right">'.dol_escape_htmltag($c['currency']).' '.price($c['remaining']).'</td>';
				print '<td class="right"><input type="text" class="flat maxwidth75 right" name="amount['.(int) $c['id'].']" value="'.dol_escape_htmltag($prefill).'"'.($canRecord ? '' : ' disabled').'></td>';
				print '</tr>';
			}
		} else {
			print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans("WiseNoCandidate").'</span></td></tr>';
		}
		print '</table></div>';
		if (!empty($match['sos'])) {
			$soRefs = array();
			foreach ($match['sos'] as $so) {
				$soRefs[] = dol_escape_htmltag($so['ref']);
			}
			print '<br><span class="opacitymedium">'.$langs->trans("WiseMatchedSOs").': '.implode(', ', $soRefs).'</span>';
		}
		print '<br><div class="tabsAction">';
		if ($canRecord && !empty($candidates)) {
			print '<input class="butAction" type="submit" value="'.$langs->trans("WiseConfirmRecord").'">';
		}
		print '</div>';
		print '</form>';

		// Ignore / re-enrich
		if ($canRecord) {
			print '<div class="tabsAction">';
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="ignore">';
			print '<input type="hidden" name="rowid" value="'.$row['rowid'].'">';
			print '<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">';
			print '<input class="butActionDelete" type="submit" value="'.$langs->trans("WiseIgnore").'">';
			print '</form> ';
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="enrich">';
			print '<input type="hidden" name="rowid" value="'.$row['rowid'].'">';
			print '<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">';
			print '<input class="butAction" type="submit" value="'.$langs->trans("WiseReenrich").'">';
			print '</form>';
			print '</div>';
		}
		print '<br><a class="backlink" href="'.$_SERVER["PHP_SELF"].'?status='.dol_escape_htmltag($statusFilter).'">'.$langs->trans("BackToList").'</a>';
	}
	llxFooter();
	$db->close();
	exit(0);
}

// Queue list
$sql = 'SELECT t.rowid, t.currency, t.amount, t.occurred_at, t.status, t.ref_text, t.counterparty, t.fk_paiement';
$sql .= ' FROM '.MAIN_DB_PREFIX.'slycustom_wise_incoming t';
$sql .= ' WHERE t.entity = '.(int) $conf->entity;
if ($statusFilter !== '') {
	$sql .= " AND t.status = '".$db->escape($statusFilter)."'";
}
$sql .= ' ORDER BY t.rowid DESC LIMIT 200';
$resql = $db->query($sql);

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>#</td><td>'.$langs->trans("Date").'</td><td>'.$langs->trans("Amount").'</td><td>'.$langs->trans("Currency").'</td>';
print '<td>'.$langs->trans("Ref").'</td><td>'.$langs->trans("ThirdParty").'</td><td>'.$langs->trans("Status").'</td></tr>';
if ($resql) {
	$num = 0;
	while ($obj = $db->fetch_object($resql)) {
		$num++;
		print '<tr class="oddeven">';
		print '<td><a href="'.$_SERVER["PHP_SELF"].'?rowid='.(int) $obj->rowid.'&status='.dol_escape_htmltag($statusFilter).'">'.(int) $obj->rowid.'</a></td>';
		print '<td>'.dol_escape_htmltag($obj->occurred_at).' UTC</td>';
		print '<td class="right">'.price((float) $obj->amount).'</td>';
		print '<td>'.dol_escape_htmltag($obj->currency).'</td>';
		print '<td>'.($obj->ref_text !== '' ? dol_escape_htmltag($obj->ref_text) : '<span class="opacitymedium">-</span>').'</td>';
		print '<td>'.($obj->counterparty !== '' ? dol_escape_htmltag($obj->counterparty) : '<span class="opacitymedium">-</span>').'</td>';
		print '<td>'.dol_escape_htmltag($obj->status).'</td>';
		print '</tr>';
	}
	$db->free($resql);
	if ($num === 0) {
		print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="7" class="error">'.dol_escape_htmltag($db->lasterror).'</td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
