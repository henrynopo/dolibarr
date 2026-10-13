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
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

$langs->loadLangs(array('bills', 'banks', 'compta', 'slycustom@slycustom'));

if (!$user->admin && !$user->hasRight('slycustom', 'wise', 'read')) {
	accessforbidden();
	exit;
}

$action = GETPOST('action', 'aZ09');
// The allocation form posts a single fixed action plus a `step` token chosen by
// the button pressed, so the button labels stay translatable.
if ($action == 'allocate') {
	$action = (GETPOST('step', 'aZ09') === 'save_draft') ? 'save_draft' : 'record';
}
$rowid = (int) GETPOST('rowid', 'int');

// Recording a payment writes bookkeeping entries: require the wise write
// right, not just the queue view right (admins pass). Saving / discarding a
// draft also writes, so it takes the same right.
if (in_array($action, array('record', 'save_draft', 'discard_draft', 'auto_record', 'mark_reconciled'), true)
	&& !$user->admin && !$user->hasRight('slycustom', 'wise', 'write')) {
	accessforbidden();
	exit;
}
$statusFilter = GETPOST('status', 'aZ09');
if (!in_array($statusFilter, array(
	WiseIncomingPayment::STATUS_NEW,
	WiseIncomingPayment::STATUS_ENRICHED,
	WiseIncomingPayment::STATUS_PREPARED,
	WiseIncomingPayment::STATUS_RECORDED,
	WiseIncomingPayment::STATUS_IGNORED), true)) {
	$statusFilter = '';
}

$wp = new WiseIncomingPayment($db);

/**
 * Map the recordPayment() / saveDraft() error code to a translated message.
 *
 * @param  int    $code Error code (<0)
 * @return string
 */
function wiseRecordErrorLabel($code)
{
	global $langs;
	switch ($code) {
		case -1: return $langs->trans("WiseErrNotFound");
		case -2: return $langs->trans("WiseErrAlready");
		case -3: return $langs->trans("WiseErrAlloc");
		case -5:
		case -6: return $langs->trans("WiseErrNotOpen");
		case -7: return $langs->trans("WiseErrCurrency");
		case -8: return $langs->trans("WiseErrOverAlloc");
		case -9: return $langs->trans("WiseErrOverCredit");
		case -10: return $langs->trans("WiseErrCreate");
		case -20: return $langs->trans("WiseErrAutoState");
		case -21: return $langs->trans("WiseErrAutoNoRef");
		case -22: return $langs->trans("WiseErrAutoNoSo");
		case -23: return $langs->trans("WiseErrAutoMulti");
		case -24: return $langs->trans("WiseErrAutoCur");
		case -25: return $langs->trans("WiseErrAutoOver");
		default: return $langs->trans("Error").' ('.$code.')';
	}
}

/**
 * Read the checkbox/amount allocation form into array(invoice rowid => amount).
 * Rows with an empty or non-positive amount are dropped.
 *
 * @return array
 */
function wiseParseAllocations()
{
	$facs = GETPOST('fac', 'array');
	$amounts = GETPOST('amount', 'array');
	$allocations = array();
	if (!is_array($facs) || !is_array($amounts)) {
		return $allocations;
	}
	foreach ($facs as $facid => $on) {
		$facid = (int) $facid;
		$amtRaw = isset($amounts[$facid]) ? trim((string) $amounts[$facid]) : '';
		$amt = price2num($amtRaw);
		if ($facid > 0 && $amtRaw !== '' && $amt !== '' && is_numeric($amt) && (float) $amt > 0) {
			$allocations[$facid] = (float) $amt;
		}
	}
	return $allocations;
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

if ($action == 'save_draft' && $rowid > 0) {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$row = $wp->fetchRow($rowid);
		if (!$row || (int) $row['entity'] !== (int) $conf->entity) {
			accessforbidden();
			exit;
		}
		$allocations = wiseParseAllocations();
		if (empty($allocations)) {
			setEventMessages($langs->trans("WiseErrAlloc"), null, 'errors');
		} else {
			$r = $wp->saveDraft($rowid, $allocations, (int) $user->id);
			if ($r === 0) {
				setEventMessages($langs->trans("WiseDraftSaved"), null, 'mesgs');
			} else {
				setEventMessages(wiseRecordErrorLabel($r), null, 'errors');
			}
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?rowid='.$rowid.'&status='.dol_escape_htmltag($statusFilter));
	exit;
}

if ($action == 'discard_draft' && $rowid > 0) {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$row = $wp->fetchRow($rowid);
		if (!$row || (int) $row['entity'] !== (int) $conf->entity) {
			accessforbidden();
			exit;
		}
		$r = $wp->discardDraft($rowid);
		setEventMessages($r === 0 ? $langs->trans("WiseDraftDiscarded") : wiseRecordErrorLabel($r), null, $r === 0 ? 'mesgs' : 'errors');
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
		// No checkbox ticked on the form: fall back to the saved draft, so a
		// draft can be confirmed as-is from the list page.
		$allocations = wiseParseAllocations();
		if (empty($allocations)) {
			$draft = $wp->getDraft($row);
			if ($draft) {
				foreach ($draft['items'] as $item) {
					$allocations[(int) $item['facid']] = (float) $item['amount'];
				}
			}
		}
		if (empty($allocations)) {
			setEventMessages($langs->trans("WiseErrAlloc"), null, 'errors');
		} else {
			$pid = $wp->recordPayment($rowid, $allocations, $user);
			if ($pid > 0) {
				// Success: jump straight to the native payment card. The PRG
				// message is set before redirect; if the operator pops back here
				// the message will still be visible.
				setEventMessages($langs->trans("WiseRecordedAs").' #'.$pid, null, 'mesgs');
				header('Location: '.DOL_URL_ROOT.'/compta/paiement/card.php?id='.(int) $pid);
				exit;
			} else {
				setEventMessages(wiseRecordErrorLabel($pid), null, 'errors');
			}
		}
	}
	// Failure path (or no allocation): stay on the detail so the operator can fix it.
	header('Location: '.$_SERVER["PHP_SELF"].'?rowid='.$rowid.'&status='.dol_escape_htmltag($statusFilter));
	exit;
}

if ($action == 'auto_record' && $rowid > 0) {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$row = $wp->fetchRow($rowid);
		if (!$row || (int) $row['entity'] !== (int) $conf->entity) {
			accessforbidden();
			exit;
		}
		$pid = $wp->autoRecord($rowid, $user);
		if ($pid > 0) {
			setEventMessages($langs->trans("WiseAutoRecordOk").' #'.$pid, null, 'mesgs');
			header('Location: '.DOL_URL_ROOT.'/compta/paiement/card.php?id='.(int) $pid);
			exit;
		} else {
			setEventMessages(wiseRecordErrorLabel($pid), null, 'errors');
		}
	}
	// Failure path: stay on the queue so the operator can review and pick manually.
	header('Location: '.$_SERVER["PHP_SELF"].'?status='.dol_escape_htmltag($statusFilter));
	exit;
}

// Mark a row as reconciled against an existing bank entry. The operator has
// already entered the credit through the bank account or invoice payment UI;
// we just mark the Wise incoming row IGNORED and append a note. We never write
// to llx_bank / llx_paiement here.
if ($action == 'mark_reconciled' && $rowid > 0) {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$row = $wp->fetchRow($rowid);
		if (!$row || (int) $row['entity'] !== (int) $conf->entity) {
			accessforbidden();
			exit;
		}
		$bankId = (int) GETPOST('bank_id', 'int');
		$paiementId = (int) GETPOST('paiement_id', 'int');
		$rc = $wp->markReconciled($rowid, $bankId, $paiementId, $user->id);
		if ($rc === 0) {
			setEventMessages($langs->trans("WiseMarkReconciledOk"), null, 'mesgs');
		} else {
			switch ($rc) {
				case -1: setEventMessages($langs->trans("WiseErrNotFound"), null, 'errors'); break;
				case -2: setEventMessages($langs->trans("WiseErrAlready"), null, 'errors'); break;
				case -11: setEventMessages($langs->trans("Error").' (db)', null, 'errors'); break;
				default: setEventMessages($langs->trans("Error").' ('.$rc.')', null, 'errors'); break;
			}
		}
	}
	// Where to land after the mark: detail page by default, list page if the
	// operator used the inline chip in the list (single best-bank case).
	$returnTo = GETPOST('return', 'aZ09');
	if ($returnTo === 'list') {
		header('Location: '.$_SERVER["PHP_SELF"].'?status='.dol_escape_htmltag($statusFilter));
	} else {
		header('Location: '.$_SERVER["PHP_SELF"].'?rowid='.(int) $rowid.'&status='.dol_escape_htmltag($statusFilter));
	}
	exit;
}

/*
 * View
 */
llxHeader('', $langs->trans("WiseReconcileTitle"), '', '', 0, 0, '', '', '', 'mod-slycustom page-wise_reconcile');
// Auto-fill button helper: writes min(credit, remaining) into the matching
// amount[N] field. Mirrors the affordance on Dolibarr's own payment card.
print '<script type="text/javascript">function wiseFillAmount(facid, credit, remaining) { var f = document.getElementById("amount_" + facid); if (!f) return false; var take = Math.min(credit, remaining); f.value = (Math.round(take * 100) / 100).toFixed(2); f.focus(); return false; }</script>';
print load_fiche_titre($langs->trans("WiseReconcileTitle"), '<a class="butAction" href="'.dol_buildpath('/custom/slycustom/wise/dumps.php', 1).'?mainmenu=tools">'.$langs->trans("WiseDumpsTitle").'</a>', 'payment');

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
	WiseIncomingPayment::STATUS_PREPARED => $langs->trans("WiseStatusPrepared"),
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

		// Prefill the allocation: the saved draft wins, otherwise FIFO across
		// the same-currency candidates.
		$draft = $wp->getDraft($row);
		$defaults = array();
		if ($draft) {
			foreach ($draft['items'] as $item) {
				$defaults[(int) $item['facid']] = (float) $item['amount'];
			}
		} else {
			$creditLeft = (float) $row['amount'];
			foreach ($candidates as $c) {
				if ($c['currency'] === strtoupper((string) $row['currency']) && $c['remaining'] > 0 && $creditLeft > 0.009) {
					$take = min($c['remaining'], $creditLeft);
					$defaults[$c['id']] = $take;
					$creditLeft -= $take;
				}
			}
		}

		// ---- Manual invoice picker ---------------------------------------------
		// findInvoiceCandidates() only ever returns invoices it recognised. When it
		// recognises nothing, the allocation table has zero rows, the record buttons
		// never render, and the only remaining action was "ignore" — which posts
		// nothing at all and silently drops the credit. validateAllocations()
		// already checks entity / paye / statut / type / currency / remaining
		// independently of the candidate set, so widening the UI is all that is
		// needed: anything picked here goes through the same validation. Also
		// offered when candidates exist but the engine picked the wrong invoice.
		//
		// The filter takes an SO ref as readily as an invoice ref: customers quote
		// the sales order in the payment reference, not the invoice number. SO refs
		// get the same separator-insensitive matching the engine uses, so
		// SO2501-0012 / SO25010012 / SO2501_0012 all hit.
		$manualInvoices = array();
		$manualTruncated = false;
		$manualRef = trim((string) GETPOST('manref', 'alphanohtml'));
		$manualSoc = trim((string) GETPOST('mansoc', 'alphanohtml'));
		// PREPARED with a saved draft: the draft invoices are not in $candidates
		// (they were hand-picked, or the engine context has moved on), so expand
		// the manual picker by default — otherwise the operator has no way to
		// re-see the row that the draft picked and edit it.
		$hasDraft = $draft && !empty($draft['items']);
		$showManual = empty($candidates) || GETPOST('showmanual', 'int') > 0 || $hasDraft;

		// ---- Current draft rows ------------------------------------------------
		// The draft itself is the source of truth for what was allocated. Batch-
		// load the draft invoice ids via the same multicurrency-aware SQL the
		// manual picker uses, so ref/currency/remaining/date match exactly. The
		// row is always rendered editable: the operator is editing a saved
		// draft, not posting a payment, so locking the amount away when the
		// invoice has meanwhile been paye=1 forces a full draft discard.
		// validateAllocations() still refuses the allocation at save time if
		// the invoice is truly closed — that is the real safety net.
		$draftInvoices = array();
		$draftMissing = array();
		if ($hasDraft) {
			$draftFacIds = array();
			foreach ($draft['items'] as $item) {
				$facid = (int) $item['facid'];
				if ($facid > 0) {
					$draftFacIds[] = $facid;
				}
			}
			$draftFacIds = array_values(array_unique($draftFacIds));
			if (!empty($draftFacIds)) {
				$sql = 'SELECT f.rowid, f.ref, f.fk_soc, f.multicurrency_code, f.datef, f.paye, f.fk_statut,';
				$sql .= " CASE WHEN COALESCE(f.multicurrency_code, '') <> '' THEN f.multicurrency_total_ttc ELSE f.total_ttc END AS total_cur,";
				$sql .= " (SELECT COALESCE(SUM(CASE WHEN COALESCE(f.multicurrency_code, '') <> '' THEN pf.multicurrency_amount ELSE pf.amount END), 0) FROM ".MAIN_DB_PREFIX.'paiement_facture pf WHERE pf.fk_facture = f.rowid) AS paid';
				$sql .= ' FROM '.MAIN_DB_PREFIX.'facture f';
				$sql .= ' WHERE f.rowid IN ('.implode(',', $draftFacIds).')';
				$sql .= ' AND f.entity = '.(int) $conf->entity;
				$resql = $db->query($sql);
				$foundIds = array();
				if ($resql) {
					while ($obj = $db->fetch_object($resql)) {
						$draftInvoices[(int) $obj->rowid] = $obj;
						$foundIds[] = (int) $obj->rowid;
					}
					$db->free($resql);
				}
				foreach ($draftFacIds as $fid) {
					if (!in_array($fid, $foundIds, true)) {
						$draftMissing[] = $fid;
					}
				}
			}
		}
		if ($showManual) {
			// Invoice-currency amounts: for a multicurrency invoice f.total_ttc is
			// the company-currency conversion, the face value is multicurrency_total_ttc
			// and payments land in pf.multicurrency_amount. Mixing the columns once
			// showed every foreign-currency Remain-to-pay wrong by the FX rate.
			$sql = 'SELECT f.rowid, f.ref, f.fk_soc, f.multicurrency_code, f.datef,';
			$sql .= " CASE WHEN COALESCE(f.multicurrency_code, '') <> '' THEN f.multicurrency_total_ttc ELSE f.total_ttc END AS total_cur,";
			$sql .= " (SELECT COALESCE(SUM(CASE WHEN COALESCE(f.multicurrency_code, '') <> '' THEN pf.multicurrency_amount ELSE pf.amount END), 0) FROM ".MAIN_DB_PREFIX.'paiement_facture pf WHERE pf.fk_facture = f.rowid) AS paid';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'facture f';
			$sql .= ' WHERE f.entity = '.(int) $conf->entity." AND f.paye = 0 AND f.fk_statut IN (1, 2) AND f.type = 0";
			if ($manualRef !== '') {
				$esc = $db->escape($manualRef);
				$flat = $db->escape(str_replace(array('_', '-'), '', strtoupper($manualRef)));
				$sql .= ' AND (f.ref LIKE \'%'.$esc.'%\'';
				$sql .= ' OR f.rowid IN (SELECT ee.fk_target';
				$sql .= ' FROM '.MAIN_DB_PREFIX.'element_element ee';
				$sql .= ' JOIN '.MAIN_DB_PREFIX.'commande c ON c.rowid = ee.fk_source';
				$sql .= ' WHERE ee.sourcetype = \'commande\' AND ee.targettype = \'facture\'';
				$sql .= ' AND (c.ref LIKE \'%'.$esc.'%\' OR c.ref_client LIKE \'%'.$esc.'%\'';
				$sql .= ' OR REPLACE(UPPER(REPLACE(c.ref, \'_\', \'-\')), \'-\', \'\') = \''.$flat.'\'';
				$sql .= ' OR REPLACE(UPPER(REPLACE(c.ref_client, \'_\', \'-\')), \'-\', \'\') = \''.$flat.'\')))';
			}
			if ($manualSoc !== '') {
				$sql .= ' AND f.fk_soc IN (SELECT s.rowid FROM '.MAIN_DB_PREFIX.'societe s';
				$sql .= ' WHERE s.nom LIKE \'%'.$db->escape($manualSoc).'%\')';
			}
			// Ranking: same currency first, then the smallest gap between the
			// invoice remaining and this credit (an exact match sorts first —
			// that is almost always the invoice the customer is paying), then
			// newest. Wrong-currency invoices stay visible but sink below every
			// same-currency row; validateAllocations() still rejects them (-7).
			$cur = $db->escape(strtoupper((string) $row['currency']));
			$curExpr = "(UPPER(COALESCE(NULLIF(f.multicurrency_code, ''), '".$db->escape((string) $conf->currency)."')) <> '".$cur."')";
			// 201 rows so "there are more" can be reported instead of silently cut.
			$sql .= ' ORDER BY '.$curExpr.' ASC, ABS((total_cur - paid) - '.((float) $row['amount']).') ASC, f.datef DESC LIMIT 201';
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$manualInvoices[] = $obj;
				}
				$db->free($resql);
			}
			if (count($manualInvoices) > 200) {
				$manualTruncated = true;
				$manualInvoices = array_slice($manualInvoices, 0, 200);
			}
			// Third-party names and originating SO refs, both batch-loaded: no query
			// may run inside the rendering loop.
			$manualSocNames = array();
			$manualSoRefs = array();
			if (!empty($manualInvoices)) {
				$manualInvIds = array();
				$manualSocIds = array();
				foreach ($manualInvoices as $inv) {
					$manualInvIds[] = (int) $inv->rowid;
					if (!empty($inv->fk_soc)) {
						$manualSocIds[] = (int) $inv->fk_soc;
					}
				}
				if (!empty($manualSocIds)) {
					$sql = 'SELECT rowid, nom FROM '.MAIN_DB_PREFIX.'societe';
					$sql .= ' WHERE rowid IN ('.implode(',', array_unique($manualSocIds)).')';
					$resql = $db->query($sql);
					if ($resql) {
						while ($obj = $db->fetch_object($resql)) {
							$manualSocNames[(int) $obj->rowid] = $obj->nom;
						}
						$db->free($resql);
					}
				}
				$sql = 'SELECT ee.fk_target, c.ref FROM '.MAIN_DB_PREFIX.'element_element ee';
				$sql .= ' JOIN '.MAIN_DB_PREFIX.'commande c ON c.rowid = ee.fk_source';
				$sql .= ' WHERE ee.sourcetype = \'commande\' AND ee.targettype = \'facture\'';
				$sql .= ' AND ee.fk_target IN ('.implode(',', $manualInvIds).')';
				$resql = $db->query($sql);
				if ($resql) {
					while ($obj = $db->fetch_object($resql)) {
						$manualSoRefs[(int) $obj->fk_target][] = $obj->ref;
					}
					$db->free($resql);
				}
			}
			// A filter that narrows to exactly one invoice can prefill the obvious
			// amount; a wider list must stay blank so a credit is never silently
			// spread over invoices nobody looked at.
			if (count($manualInvoices) == 1) {
				$onlyId = (int) $manualInvoices[0]->rowid;
				$facOnly = new Facture($db);
				if ($facOnly->fetch($onlyId) > 0 && (int) $facOnly->entity === (int) $conf->entity) {
					$onlyRemaining = (float) $facOnly->getRemainToPay(1);
				} else {
					$onlyRemaining = (float) $manualInvoices[0]->total_ttc - (float) $manualInvoices[0]->paid;
				}
				$defaults[$onlyId] = min($onlyRemaining, (float) $row['amount']);
			}
		}

		$canRecord = ($row['status'] !== WiseIncomingPayment::STATUS_RECORDED);
		$canWrite = ($user->admin || $user->hasRight('slycustom', 'wise', 'write'));

		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td colspan="4">'.$langs->trans("WiseCreditDetail").' #'.$row['rowid'].'</td></tr>';
		print '<tr class="oddeven"><td class="titlefield">'.$langs->trans("Date").'</td><td>'.($row['occurred_at'] ? dol_print_date(WiseIncomingPayment::sqlUtcToTimestamp($row['occurred_at']), 'dayhour', 'tzuserrel') : '-').'</td>';
		print '<td>'.$langs->trans("Amount").'</td><td><strong>'.dol_escape_htmltag($row['currency']).' '.price($row['amount']).'</strong></td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans("Ref").'</td><td>'.($row['ref_text'] !== '' ? dol_escape_htmltag($row['ref_text']) : '<span class="opacitymedium">'.$langs->trans("None").'</span>').'</td>';
		print '<td>'.$langs->trans("ThirdParty").'</td><td>'.($row['counterparty'] !== '' ? dol_escape_htmltag($row['counterparty']) : '<span class="opacitymedium">'.$langs->trans("None").'</span>').'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans("Status").'</td><td>'.dol_escape_htmltag($row['status']).'</td>';
		print '<td>'.$langs->trans("Fees").'</td><td>'.($row['fees'] !== null ? price($row['fees']) : '-').'</td></tr>';
		if ($row['status'] === WiseIncomingPayment::STATUS_RECORDED && $row['fk_paiement']) {
			print '<tr class="oddeven"><td>'.$langs->trans("Payment").'</td><td colspan="3"><a href="'.DOL_URL_ROOT.'/compta/paiement/card.php?id='.(int) $row['fk_paiement'].'">'.$langs->trans("WiseRecordedAs").' #'.(int) $row['fk_paiement'].'</a></td></tr>';
		}
		if ($draft) {
			print '<tr class="oddeven"><td>'.$langs->trans("WiseDraft").'</td><td colspan="3"><span class="'.($draft['unallocated'] > 0.009 ? 'warning' : '').'">'
				.dol_escape_htmltag($draft['currency'].' '.price($draft['total']).' / '.price($draft['credit']))
				.($draft['unallocated'] > 0.009 ? ' &mdash; '.dol_escape_htmltag($langs->trans("WiseDraftUnallocated")).' '.price($draft['unallocated']) : '')
				.'</span><br><span class="opacitymedium">'.dol_escape_htmltag($langs->trans("WiseDraftSavedOn").' '.$draft['date_draft'].' ('.$langs->trans("WiseDraftSavedBy").' #'.(int) $draft['user'].')').'</span></td></tr>';
		}
		if ($row['note_private'] !== '') {
			print '<tr class="oddeven"><td>'.$langs->trans("Note").'</td><td colspan="3">'.dol_escape_htmltag($row['note_private']).'</td></tr>';
		}
		print '</table></div>';
		print '<br>';

		// Manual picker filter. Its own GET form: it only narrows the picker list and
		// must not be submitted as an allocation. Kept outside the POST form below
		// because nested forms are invalid HTML and a GET submit inside the POST
		// form would fire action=allocate with no step.
		if ($showManual) {
			print '<br><form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
			print '<input type="hidden" name="rowid" value="'.(int) $rowid.'">';
			print '<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">';
			print '<input type="hidden" name="showmanual" value="1">';
			print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
			print '<tr class="liste_titre"><td colspan="6">'.$langs->trans("WiseManualPick");
			print ' <span class="opacitymedium">(<a href="'.$_SERVER["PHP_SELF"].'?rowid='.(int) $rowid.'&status='.urlencode($statusFilter).'&manref='.urlencode($manualRef).'&mansoc='.urlencode($manualSoc).'">'.dol_escape_htmltag($langs->trans("Hide")).'</a>)</span>';
			print '</td></tr>';
			print '<tr><td class="titlefield">'.$langs->trans("WiseManualFilterRef").'</td><td>';
			print '<input type="text" class="flat maxwidth200" name="manref" value="'.dol_escape_htmltag($manualRef).'"></td>';
			print '<td>'.$langs->trans("WiseManualFilterSoc").'</td><td>';
			print '<input type="text" class="flat maxwidth200" name="mansoc" value="'.dol_escape_htmltag($manualSoc).'"></td>';
			print '<td class="right">';
			print '<input class="button button-save" type="submit" value="'.dol_escape_htmltag($langs->trans("WiseManualFilter")).'">';
			print '</td></tr>';
			print '<tr><td colspan="6"><span class="opacitymedium">'.dol_escape_htmltag($langs->trans("WiseManualNote")).'</span></td></tr>';
			print '</table></div></form>';
		}

		// Candidates + allocation form
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="allocate">';
		print '<input type="hidden" name="rowid" value="'.$row['rowid'].'">';
		print '<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">';

		// ---- Current draft rows (rendered before the candidate/manual table) ---
		// Lets the operator edit the draft inline instead of being forced through
		// the manual picker (which may not even list the same invoice again).
		$draftSocNames = array();
		if (!empty($draftInvoices)) {
			$draftSocIds = array();
			foreach ($draftInvoices as $din) {
				if (!empty($din->fk_soc)) {
					$draftSocIds[] = (int) $din->fk_soc;
				}
			}
			if (!empty($draftSocIds)) {
				$sql = 'SELECT rowid, nom FROM '.MAIN_DB_PREFIX.'societe';
				$sql .= ' WHERE rowid IN ('.implode(',', array_unique($draftSocIds)).')';
				$resql = $db->query($sql);
				if ($resql) {
					while ($obj = $db->fetch_object($resql)) {
						$draftSocNames[(int) $obj->rowid] = $obj->nom;
					}
					$db->free($resql);
				}
			}
		}
		if (!empty($draftInvoices) || !empty($draftMissing)) {
			print '<div class="div-table-responsive-no-min">';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre"><td colspan="7">'.dol_escape_htmltag($langs->trans("WiseDraftSectionTitle"));
			if ($hasDraft) {
				print ' <span class="opacitymedium">'.dol_escape_htmltag($draft['currency'].' '.price($draft['total']).' / '.price($draft['credit']).' &mdash; '.$langs->trans("WiseDraftSavedOn").' '.$draft['date_draft']).'</span>';
			}
			print '</td></tr>';
			print '<tr class="liste_titre"><td></td><td>'.$langs->trans("Invoice").'</td><td>'.$langs->trans("ThirdParty").'</td><td>'.$langs->trans("Date").'</td>';
			print '<td class="right">'.$langs->trans("Amount").'</td><td class="right">'.$langs->trans("RemainToPay").'</td><td class="right">'.$langs->trans("WiseAllocate").'</td></tr>';
			foreach ($draft['items'] as $item) {
				$facid = (int) $item['facid'];
				$prefill = price2num((float) $item['amount'], 'MT');
				$din = isset($draftInvoices[$facid]) ? $draftInvoices[$facid] : null;
				$stillOpen = $din && (int) $din->paye === 0 && in_array((int) $din->fk_statut, array(0, 1, 2), true);
				$invCur = $din && !empty($din->multicurrency_code) ? strtoupper($din->multicurrency_code) : strtoupper((string) $conf->currency);
				$invTotal = $din ? (float) $din->total_cur : 0.0;
				$remaining = $din ? ((float) $din->total_cur - (float) $din->paid) : 0.0;
				$socLabel = $din && !empty($draftSocNames[(int) $din->fk_soc]) ? $draftSocNames[(int) $din->fk_soc] : '';
				print '<tr class="oddeven">';
				if ($din) {
					// Closed rows (paye=1 or fk_statut outside 0/1/2) are shown
					// read-only with a warning — the operator must re-pick
					// before submitting, since validateAllocations() will
					// refuse the allocation at save time anyway.
					$rowChecked = $stillOpen && $canRecord ? ' checked' : '';
					$rowDisabled = ($stillOpen && $canRecord) ? '' : ' disabled';
					$warning = !$stillOpen ? ' '.img_warning($langs->trans("WiseDraftRowMissing")) : '';
					print '<td><input type="checkbox" name="fac['.$facid.']" value="1"'.$rowChecked.$rowDisabled.'></td>';
					print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$facid.'">'.dol_escape_htmltag($din->ref).'</a>'.$warning.'</td>';
					print '<td>'.dol_escape_htmltag($socLabel !== '' ? $socLabel : (string) $din->fk_soc).'</td>';
					print '<td>'.dol_escape_htmltag($din->datef).'</td>';
					print '<td class="right">'.dol_escape_htmltag($invCur).' '.price($invTotal).'</td>';
					print '<td class="right">'.dol_escape_htmltag($invCur).' '.price($remaining).'</td>';
					// AJAX fill: clicking the button writes min(credit, remaining)
					// into the amount[N] field, mirroring Dolibarr's own payment
					// card. The button is disabled when the row is not editable.
					print '<td class="right nowraponall">';
					if ($rowDisabled === '') {
						print '<input type="text" class="flat maxwidth75 right" name="amount['.$facid.']" id="amount_'.$facid.'" value="'.dol_escape_htmltag($prefill).'"> ';
						print '<button type="button" class="button" onclick="return wiseFillAmount('.$facid.', '.((float) $row['amount']).', '.((float) $remaining).');" title="'.dol_escape_htmltag($langs->trans("WiseAllocateFill")).'">&laquo;</button>';
					} else {
						print '<input type="text" class="flat maxwidth75 right" value="'.dol_escape_htmltag($prefill).'" disabled> ';
						print '<button type="button" class="button" disabled>&laquo;</button>';
					}
					print '</td>';
				} else {
					// Invoice no longer in this entity / deleted. Keep the row
					// visible so the operator sees what the draft pinned, but
					// block the form from posting an allocation against a
					// non-existent invoice.
					print '<td></td>';
					print '<td>#'.$facid.' '.img_warning($langs->trans("WiseDraftRowMissing")).'</td>';
					print '<td>'.dol_escape_htmltag($socLabel).'</td>';
					print '<td>-</td>';
					print '<td class="right">-</td>';
					print '<td class="right">-</td>';
					print '<td class="right"><input type="text" class="flat maxwidth75 right" value="'.dol_escape_htmltag($prefill).'" disabled></td>';
				}
				print '</tr>';
			}
			if (!empty($draftMissing)) {
				print '<tr class="oddeven"><td colspan="7" class="warning">'.dol_escape_htmltag($langs->trans("WiseDraftRowMissing").' '.implode(', ', array_map('intval', $draftMissing))).'</td></tr>';
			}
			print '</table></div><br>';
		}

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
				// « button writes min(credit, remaining) into the amount box
				// on demand. Mirrors Dolibarr's own payment card.
				print '<td class="right nowraponall">';
				if ($canRecord) {
					print '<input type="text" class="flat maxwidth75 right" name="amount['.(int) $c['id'].']" id="amount_'.(int) $c['id'].'" value="'.dol_escape_htmltag($prefill).'"> ';
					print '<button type="button" class="button" onclick="return wiseFillAmount('.(int) $c['id'].', '.((float) $row['amount']).', '.((float) $c['remaining']).');" title="'.dol_escape_htmltag($langs->trans("WiseAllocateFill")).'">&laquo;</button>';
				} else {
					print '<input type="text" class="flat maxwidth75 right" value="'.dol_escape_htmltag($prefill).'" disabled> ';
					print '<button type="button" class="button" disabled>&laquo;</button>';
				}
				print '</td>';
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

		// Manual picker: rendered inside the same form, so a hand-picked invoice
		// flows through wiseParseAllocations() and validateAllocations() exactly
		// like an engine candidate — same checks, same draft storage.
		if ($showManual) {
			print '<br><div class="div-table-responsive-no-min">';
			print '<table class="noborder centpercent">';
			print '<tr><td></td><td>'.$langs->trans("Invoice").'</td><td>'.$langs->trans("WiseManualSoCol").'</td><td>'.$langs->trans("ThirdParty").'</td><td>'.$langs->trans("Date").'</td>';
			print '<td class="right">'.$langs->trans("Amount").'</td><td class="right">'.$langs->trans("RemainToPay").'</td><td class="right">'.$langs->trans("WiseAllocate").'</td></tr>';
			if (empty($manualInvoices)) {
				print '<tr><td colspan="8"><span class="opacitymedium">'.$langs->trans("WiseManualNone").'</span></td></tr>';
			}
			foreach ($manualInvoices as $inv) {
				$invId = (int) $inv->rowid;
				$invCur = !empty($inv->multicurrency_code) ? strtoupper($inv->multicurrency_code) : strtoupper((string) $conf->currency);
				$invTotal = (float) $inv->total_cur;
				// Recompute the remain via the official function: the batch SQL
				// above only sums paiement_facture and understates the remain
				// for any invoice that has already consumed a deposit or credit
				// note. Up to 200 fetches on this page; the hand-rolled SQL
				// was correct on day 1 and wrong the moment IS267808 absorbed
				// a USD deposit — switch the authoritative source.
				$fac = new Facture($db);
				if ($fac->fetch($invId) > 0 && (int) $fac->entity === (int) $conf->entity) {
					$remaining = (float) $fac->getRemainToPay(1);
				} else {
					$remaining = (float) $inv->total_cur - (float) $inv->paid;
				}
				$socLabel = !empty($manualSocNames[(int) $inv->fk_soc]) ? $manualSocNames[(int) $inv->fk_soc] : (string) $inv->fk_soc;
				$soCells = '';
				if (!empty($manualSoRefs[$invId])) {
					foreach ($manualSoRefs[$invId] as $soRef) {
						$soCells .= ($soCells !== '' ? ', ' : '').dol_escape_htmltag($soRef);
					}
				}
				print '<tr class="oddeven">';
				print '<td><input type="checkbox" name="fac['.$invId.']" value="1"'.($canRecord ? '' : ' disabled').'></td>';
				print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$invId.'">'.dol_escape_htmltag($inv->ref).'</a></td>';
				print '<td>'.($soCells !== '' ? $soCells : '<span class="opacitymedium">-</span>').'</td>';
				print '<td>'.dol_escape_htmltag($socLabel).'</td>';
				print '<td>'.dol_escape_htmltag($inv->datef).'</td>';
				print '<td class="right">'.dol_escape_htmltag($invCur).' '.price($invTotal).'</td>';
				print '<td class="right">'.dol_escape_htmltag($invCur).' '.price($remaining).'</td>';
				// Amount deliberately left blank: with up to 200 rows a FIFO prefill
				// would silently spread the credit across unrelated invoices. The
				// « button next to the field writes min(credit, remaining) on
				// demand — same affordance as Dolibarr's own payment card.
				print '<td class="right nowraponall">';
				if ($canRecord) {
					print '<input type="text" class="flat maxwidth75 right" name="amount['.$invId.']" id="amount_'.$invId.'" value="'.(isset($defaults[$invId]) ? dol_escape_htmltag(price2num($defaults[$invId], 'MT')) : '').'"> ';
					print '<button type="button" class="button" onclick="return wiseFillAmount('.$invId.', '.((float) $row['amount']).', '.((float) $remaining).');" title="'.dol_escape_htmltag($langs->trans("WiseAllocateFill")).'">&laquo;</button>';
				} else {
					print '<input type="text" class="flat maxwidth75 right" value="" disabled> ';
					print '<button type="button" class="button" disabled>&laquo;</button>';
				}
				print '</td>';
				print '</tr>';
			}
			print '</table></div>';
			print '<div class="opacitymedium"><span class="'.($manualTruncated ? 'warning' : '').'">';
			print count($manualInvoices).' '.dol_escape_htmltag($langs->trans("WiseManualCount"));
			print $manualTruncated ? ' &mdash; '.dol_escape_htmltag($langs->trans("WiseManualTruncated")) : '';
			print '</span></div>';
		} elseif (!empty($candidates)) {
			print '<br><a class="opacitymedium" href="'.$_SERVER["PHP_SELF"].'?rowid='.(int) $rowid.'&status='.urlencode($statusFilter).'&showmanual=1">';
			print dol_escape_htmltag($langs->trans("WiseManualShow"));
			print '</a>';
		}

		print '<br><div class="tabsAction">';
		if ($canRecord && $canWrite) {
			// <button> not <input type=submit>: the wire value must stay a fixed
			// ASCII token (GETPOST 'action'/'step' are filtered), not a translated label.
			print '<button class="butAction" type="submit" name="step" value="save_draft">'.$langs->trans("WiseSaveDraft").'</button> ';
			print '<button class="butAction" type="submit" name="step" value="record">'.$langs->trans("WiseConfirmRecord").'</button>';
		}
		print '</div>';
		print '</form>';

		// Discard draft / ignore / re-enrich
		if ($canRecord) {
			print '<div class="tabsAction">';
			if ($draft && $canWrite) {
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="discard_draft">';
				print '<input type="hidden" name="rowid" value="'.$row['rowid'].'">';
				print '<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">';
				print '<button class="butActionDelete" type="submit" name="step" value="discard_draft">'.$langs->trans("WiseDiscardDraft").'</button>';
				print '</form> ';
			}
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="ignore">';
			print '<input type="hidden" name="rowid" value="'.$row['rowid'].'">';
			print '<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">';
			print '<button class="butActionDelete" type="submit" name="step" value="ignore">'.$langs->trans("WiseIgnore").'</button>';
			print '</form> ';
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="enrich">';
			print '<input type="hidden" name="rowid" value="'.$row['rowid'].'">';
			print '<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">';
			print '<button class="butAction" type="submit" name="step" value="enrich">'.$langs->trans("WiseReenrich").'</button>';
			print '</form>';
			print '</div>';
		}

		// Bank reconciliation panel: read-only — never writes to llx_bank /
		// llx_paiement. Just detects existing bank entries / paiements on the
		// same (date, amount, currency) so the operator can mark this Wise row
		// as already-handled. Useful for rows that show as NEW on this side
		// but were booked via the bank or invoice payment UI.
		print '<br><div class="fiche">';
		print '<div class="titre">'.$langs->trans("WiseBankMatchTitle").'</div>';
		$bankMatches = $wp->findBankMatches($row, 3, 30.0);
		$paiementMatches = $wp->findPaiementMatches($row, 3, 30.0);
		$hasAnyMatch = !empty($bankMatches) || !empty($paiementMatches);
		$rowTerminal = in_array($row['status'], array(WiseIncomingPayment::STATUS_RECORDED, WiseIncomingPayment::STATUS_IGNORED), true);

		// Show the existing paiement link if our side already created one.
		if (!empty($row['fk_paiement'])) {
			print '<p><span class="badge badge-status4 badge-status">'.$langs->trans("WiseBankLinked").'</span> ';
			print $langs->trans("WiseBankLinkedDetail");
			print ' <a href="'.DOL_URL_ROOT.'/compta/paiement/card.php?id='.(int) $row['fk_paiement'].'">';
			print 'Paiement #'.(int) $row['fk_paiement'].'</a></p>';
		}

		if (!$hasAnyMatch && empty($row['fk_paiement'])) {
			print '<p><span class="opacitymedium">'.$langs->trans("WiseBankMatchNone").'</span></p>';
		} else {
			// Bank candidates
			if (!empty($bankMatches)) {
				print '<div class="div-table-responsive-no-min" style="margin-bottom:8px;">';
				print '<table class="noborder centpercent">';
				print '<tr class="liste_titre"><td>#</td><td>'.$langs->trans("Date").'</td>';
				print '<td class="right">'.$langs->trans("Amount").'</td>';
				print '<td>'.$langs->trans("Currency").'</td>';
				print '<td>'.$langs->trans("Account").'</td><td>'.$langs->trans("Label").'</td>';
				print '<td class="right">'.$langs->trans("Diff").'</td></tr>';
				foreach ($bankMatches as $b) {
					$diff = abs(abs((float) $b['amount']) - (float) $row['amount']);
					$bankUrl = DOL_URL_ROOT.'/compta/bank/line/card.php?id='.(int) $b['rowid'];
					print '<tr class="oddeven">';
					print '<td><a href="'.$bankUrl.'">#'.(int) $b['rowid'].'</a></td>';
					print '<td>'.dol_print_date((int) $b['datev'], 'day', 'tzuserrel').'</td>';
					print '<td class="right">'.price(abs((float) $b['amount'])).'</td>';
					print '<td>'.dol_escape_htmltag($b['currency_code']).'</td>';
					print '<td>'.dol_escape_htmltag($b['account_label']).'</td>';
					print '<td>'.dol_escape_htmltag(dol_trunc((string) $b['label'], 60)).'</td>';
					print '<td class="right">'.($diff < 0.01 ? '<span class="badge badge-status4">0</span>' : price($diff)).'</td>';
					print '</tr>';
				}
				print '</table></div>';
			}

			// Paiement candidates (manual entry through invoice payment UI)
			if (!empty($paiementMatches)) {
				print '<div class="div-table-responsive-no-min" style="margin-bottom:8px;">';
				print '<table class="noborder centpercent">';
				print '<tr class="liste_titre"><td>#</td><td>'.$langs->trans("Date").'</td>';
				print '<td class="right">'.$langs->trans("Amount").'</td>';
				print '<td class="right">'.$langs->trans("Diff").'</td></tr>';
				foreach ($paiementMatches as $pm) {
					$diff = abs((float) $pm['amount'] - (float) $row['amount']);
					$pmUrl = DOL_URL_ROOT.'/compta/paiement/card.php?id='.(int) $pm['rowid'];
					print '<tr class="oddeven">';
					print '<td><a href="'.$pmUrl.'">Paiement #'.(int) $pm['rowid'].'</a></td>';
					print '<td>'.dol_print_date((int) $pm['datep'], 'day', 'tzuserrel').'</td>';
					print '<td class="right">'.price((float) $pm['amount']).'</td>';
					print '<td class="right">'.price($diff).'</td>';
					print '</tr>';
				}
				print '</table></div>';
			}

			// Mark-reconciled form: pick the bank entry (or none if the operator
			// just wants to mark as already-handled in the invoice payment UI),
			// and the paiement (optional). Refuses on terminal rows — those are
			// already in a state of record.
			if (!$rowTerminal && $canWrite) {
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="margin-top:8px;">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="mark_reconciled">';
				print '<input type="hidden" name="rowid" value="'.$row['rowid'].'">';
				print '<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">';
				// Bank select
				print '<label>'.$langs->trans("WiseBankRow").' ';
				print '<select name="bank_id">';
				print '<option value="0">— '.$langs->trans("None").' —</option>';
				foreach ($bankMatches as $b) {
					print '<option value="'.(int) $b['rowid'].'">';
					print '#'.(int) $b['rowid'].' / '.dol_print_date((int) $b['datev'], 'day', 'tzuserrel');
					print ' / '.price(abs((float) $b['amount'])).' '.dol_escape_htmltag($b['currency_code']);
					print '</option>';
				}
				print '</select></label> ';
				// Paiement select
				print '<label>'.$langs->trans("WisePaiementRow").' ';
				print '<select name="paiement_id">';
				print '<option value="0">— '.$langs->trans("None").' —</option>';
				foreach ($paiementMatches as $pm) {
					print '<option value="'.(int) $pm['rowid'].'">';
					print '#'.(int) $pm['rowid'].' / '.dol_print_date((int) $pm['datep'], 'day', 'tzuserrel');
					print ' / '.price((float) $pm['amount']);
					print '</option>';
				}
				print '</select></label> ';
				print '<button class="butAction" type="submit" onclick="return confirm(\''.dol_escape_js($langs->trans("WiseMarkReconciledConfirm")).'\');">';
				print $langs->trans("WiseMarkReconciled").'</button>';
				print '</form>';
			}
		}
		print '</div>';

		print '<br><a class="backlink" href="'.$_SERVER["PHP_SELF"].'?status='.dol_escape_htmltag($statusFilter).'">'.$langs->trans("BackToList").'</a>';
	}
	llxFooter();
	$db->close();
	exit(0);
}

// Queue list
$sql = 'SELECT t.rowid, t.currency, t.amount, t.occurred_at, t.status, t.ref_text, t.counterparty, t.fk_paiement, t.date_creation';
$sql .= ' FROM '.MAIN_DB_PREFIX.'slycustom_wise_incoming t';
$sql .= ' WHERE t.entity = '.(int) $conf->entity;
if ($statusFilter !== '') {
	$sql .= " AND t.status = '".$db->escape($statusFilter)."'";
}
$sql .= ' ORDER BY t.rowid DESC LIMIT 200';
$resql = $db->query($sql);

// Batch-load bank matches for the entire list (one SQL with a date+amount
// window covering the oldest event to the newest). Per-row lookups would
// need 200 queries; this keeps the loop tight.
$listRows = array();
$bankByOcc = array(); // key: epoch±86400 bucket (24h) -> bank rowid list
$oldestTs = PHP_INT_MAX;
$newestTs = 0;
$amountsWindow = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$listRows[] = $obj;
		$occ = WiseIncomingPayment::sqlUtcToTimestamp($obj->occurred_at);
		if ($occ <= 0 && !empty($obj->date_creation)) {
			$occ = WiseIncomingPayment::sqlUtcToTimestamp($obj->date_creation);
		}
		if ($occ > 0) {
			if ($occ < $oldestTs) $oldestTs = $occ;
			if ($occ > $newestTs) $newestTs = $occ;
		}
		$amountsWindow[$obj->rowid] = (float) $obj->amount;
	}
	$db->free($resql);
}
if (!empty($listRows) && $oldestTs < PHP_INT_MAX) {
	$from = (int) ($oldestTs - 3 * 86400);
	$to = (int) ($newestTs + 3 * 86400);
	$minAmt = min($amountsWindow) - 30;
	$maxAmt = max($amountsWindow) + 30;
	$sql = 'SELECT b.rowid, b.datev, b.amount, b.label, b.fk_account, ba.currency_code';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'bank b';
	$sql .= ' JOIN '.MAIN_DB_PREFIX.'bank_account ba ON ba.rowid = b.fk_account';
	$sql .= ' WHERE ba.entity = '.(int) $conf->entity;
	$sql .= ' AND ABS(b.amount) BETWEEN '.((float) $minAmt).' AND '.((float) $maxAmt);
	// b.datev is DATE; pass unix ints through DATE(FROM_UNIXTIME(...)) or MySQL
	// reinterprets them as 'YYYYMMDD' and misses every row.
	$sql .= ' AND b.datev BETWEEN DATE(FROM_UNIXTIME('.(int) $from.')) AND DATE(FROM_UNIXTIME('.(int) $to.'))';
	$sql .= ' ORDER BY b.datev DESC, b.rowid DESC LIMIT 1000';
	$resql2 = $db->query($sql);
	if ($resql2) {
		while ($bo = $db->fetch_object($resql2)) {
			// datev is a DATE column: "$db->fetch_object" returns it as 'YYYY-MM-DD'
			// (or 'YYYY-MM-DD HH:MM:SS' on some MySQL/MariaDB strict setups), not a
			// unix int. strtotime parses both shapes; cast to int only after.
			$dv = is_numeric($bo->datev) ? (int) $bo->datev : strtotime((string) $bo->datev);
			$bankByOcc[] = array(
				'rowid' => (int) $bo->rowid,
				'datev' => (int) $dv,
				'amount' => (float) $bo->amount,
				'label' => (string) $bo->label,
				'fk_account' => (int) $bo->fk_account,
				'currency_code' => strtoupper((string) $bo->currency_code),
			);
		}
		$db->free($resql2);
	}
}

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>#</td><td>'.$langs->trans("Date").'</td><td>'.$langs->trans("Amount").'</td><td>'.$langs->trans("Currency").'</td>';
print '<td>'.$langs->trans("Ref").'</td><td>'.$langs->trans("ThirdParty").'</td><td>'.$langs->trans("Status").'</td>';
print '<td>'.$langs->trans("WiseBankMatch").'</td>';
print '<td class="right">'.$langs->trans("Actions").'</td></tr>';
if (!empty($listRows)) {
	$canWriteAny = ($user->admin || $user->hasRight('slycustom', 'wise', 'write')) ? true : false;
	foreach ($listRows as $obj) {
		$detailUrl = $_SERVER["PHP_SELF"].'?rowid='.(int) $obj->rowid.'&status='.dol_escape_htmltag($statusFilter);
		// One visible entry point per row: NEW/ENRICHED/PREPARED open the
		// processing view (enrich -> allocate -> record live there), terminal
		// states only get a read-only view.
		if ($obj->status === WiseIncomingPayment::STATUS_PREPARED) {
			$rowAction = '<a class="butAction" href="'.$detailUrl.'">'.$langs->trans("WiseOpenDraft").'</a>';
		} elseif (in_array($obj->status, array(WiseIncomingPayment::STATUS_RECORDED, WiseIncomingPayment::STATUS_IGNORED), true)) {
			$rowAction = '<a class="butAction" href="'.$detailUrl.'">'.$langs->trans("WiseView").'</a>';
		} else {
			$rowAction = '<a class="butAction" href="'.$detailUrl.'">'.$langs->trans("WiseProcess").'</a>';
		}
		// One-click auto-record next to "Process": only offered while the row is
		// still open; autoRecord() itself refuses anything it would have to guess.
		if ($canWriteAny && in_array($obj->status, array(WiseIncomingPayment::STATUS_NEW, WiseIncomingPayment::STATUS_ENRICHED), true)) {
			$rowAction = '<form method="POST" style="display:inline" action="'.$_SERVER["PHP_SELF"].'">'
				.'<input type="hidden" name="token" value="'.newToken().'">'
				.'<input type="hidden" name="action" value="auto_record">'
				.'<input type="hidden" name="rowid" value="'.(int) $obj->rowid.'">'
				.'<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">'
				.'<button type="submit" class="butAction" onclick="return confirm(\''.dol_escape_js($langs->trans("WiseAutoRecordConfirm")).'\');">'.$langs->trans("WiseAutoRecord").'</button></form> '
				.$rowAction;
		}
		$occTs = WiseIncomingPayment::sqlUtcToTimestamp($obj->occurred_at);
		// Bank reconciliation chip. Operator value is "I can see exactly which
		// bank entry was matched and mark it without leaving the list", not
		// "the system thinks there might be a match". So:
		//   - 0 candidates / row terminal: bare "—"
		//   - 1 best candidate + row open + write perm: inline mark form, with
		//     bank row id + amount + date visible so the operator can spot a
		//     wrong pick before pressing
		//   - N>1 candidates: short list of the top 2 ids + a "view all" link
		//     to the detail page (where the existing form lets them pick)
		//   - fk_paiement already set: green "Linked" badge (unchanged)
		$chip = '<span class="opacitymedium">—</span>';
		$hasPaiement = !empty($obj->fk_paiement);
		$bankMatches = array();
		if ($occTs > 0 && !$hasPaiement) {
			$rowCur = strtoupper((string) $obj->currency);
			$rowAmt = (float) $obj->amount;
			foreach ($bankByOcc as $b) {
				if (abs($b['datev'] - $occTs) > 3 * 86400) continue;
				if ($rowCur !== '' && $b['currency_code'] !== $rowCur) continue;
				$diff = abs(abs($b['amount']) - $rowAmt);
				if ($diff > 30) continue;
				$bankMatches[] = array('b' => $b, 'diff' => $diff);
			}
			usort($bankMatches, function ($x, $y) { return $x['diff'] <=> $y['diff']; });
		}
		$rowTerminal = in_array($obj->status, array(WiseIncomingPayment::STATUS_RECORDED, WiseIncomingPayment::STATUS_IGNORED), true);
		if ($hasPaiement) {
			$chip = '<span class="badge badge-status4 badge-status" title="'.dol_escape_htmltag($langs->trans("WiseBankMatchedPayment")).'">'.$langs->trans("WiseBankLinked").'</span>';
		} elseif (!empty($bankMatches) && count($bankMatches) === 1 && !$rowTerminal && $canWriteAny) {
			// Single unambiguous bank match: render the operator-friendly view
			// (bank id + amount + date) and a one-click mark form, with a
			// fallback "view" link to the detail page if the operator wants
			// to see all the candidates or pick differently.
			$bm = $bankMatches[0]['b'];
			$chip  = '<span class="badge badge-warning badge-status" title="'.dol_escape_htmltag($langs->trans("WiseBankOnlyHint")).'">';
			$chip .= '#'.(int) $bm['rowid'].' / '.price(abs((float) $bm['amount'])).' '.dol_escape_htmltag($bm['currency_code']);
			$chip .= ' / '.dol_print_date((int) $bm['datev'], 'day', 'tzuserrel');
			$chip .= '</span> ';
			$chip .= '<form method="POST" style="display:inline" action="'.$_SERVER["PHP_SELF"].'" onsubmit="return confirm(\''.dol_escape_js($langs->trans("WiseMarkReconciledConfirm")).'\');">'
				.'<input type="hidden" name="token" value="'.newToken().'">'
				.'<input type="hidden" name="action" value="mark_reconciled">'
				.'<input type="hidden" name="rowid" value="'.(int) $obj->rowid.'">'
				.'<input type="hidden" name="bank_id" value="'.(int) $bm['rowid'].'">'
				.'<input type="hidden" name="paiement_id" value="0">'
				.'<input type="hidden" name="return" value="list">'
				.'<input type="hidden" name="status" value="'.dol_escape_htmltag($statusFilter).'">'
				.'<button type="submit" class="butAction small">'.$langs->trans("WiseMarkReconciledShort").'</button></form>';
		} elseif (!empty($bankMatches)) {
			// 2+ candidates: list the top 2 ids in a yellow chip and offer a
			// "view all" link to the detail page (the form there lets the
			// operator pick from the full set).
			$titles = array();
			foreach (array_slice($bankMatches, 0, 2) as $bm) {
				$titles[] = '#'.(int) $bm['b']['rowid'].' / '.price(abs((float) $bm['b']['amount']));
			}
			$more = count($bankMatches) > 2 ? ' (+'.(count($bankMatches) - 2).')' : '';
			$chip = '<a class="badge badge-warning badge-status" href="'.$detailUrl.'" title="'.dol_escape_htmltag($langs->trans("WiseBankOnlyHint")).'">';
			$chip .= $langs->trans("WiseBankOnly").' '.implode(', ', $titles).$more;
			$chip .= '</a>';
		}
		print '<tr class="oddeven">';
		print '<td><a href="'.$detailUrl.'">#'.(int) $obj->rowid.'</a></td>';
		print '<td>'.($occTs ? dol_print_date($occTs, 'dayhour', 'tzuserrel') : dol_escape_htmltag($obj->occurred_at)).'</td>';
		print '<td class="right">'.price((float) $obj->amount).'</td>';
		print '<td>'.dol_escape_htmltag($obj->currency).'</td>';
		print '<td>'.($obj->ref_text !== '' ? dol_escape_htmltag($obj->ref_text) : '<span class="opacitymedium">-</span>').'</td>';
		print '<td>'.($obj->counterparty !== '' ? dol_escape_htmltag($obj->counterparty) : '<span class="opacitymedium">-</span>').'</td>';
		print '<td>'.dol_escape_htmltag($obj->status).'</td>';
		print '<td>'.$chip.'</td>';
		print '<td class="right">'.$rowAction.'</td>';
		print '</tr>';
	}
	if (count($listRows) === 0) {
		print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="9" class="error">'.dol_escape_htmltag($db->lasterror).'</td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
