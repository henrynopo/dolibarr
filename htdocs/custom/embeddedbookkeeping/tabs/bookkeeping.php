<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/tabs/bookkeeping.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Odoo-style "Accounting entries" tab for customer invoices,
 *	             supplier invoices and expense reports. Serves all three object
 *	             types via ?id=N&objecttype=customer_invoice|supplier_invoice|expense_report.
 *
 *	Sections:
 *	  A. Per-line account table (bound / suggested / missing)
 *	  B. Entry form (counter row + per-line rows + grouped VAT rows) — only when
 *	     not yet posted, user has bookkeeping->write and the status is eligible.
 *	     The form NEVER depends on AI: with no provider configured it is fully
 *	     usable; the AI button merely prefills account selects when available.
 *	  C. Posted entries grouped by piece_num, linked to core bookkeeping/card.php.
 */

$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php failed for EmbeddedBookkeeping tab');
}

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/expensereport/class/expensereport.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/fourn.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/expensereport.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKTabData.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKEntryDraft.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKBookkeepingAlreadyDone.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKBookkeepingWriter.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKAccountLookup.class.php';

// --- Input -----------------------------------------------------------------
$objecttype = GETPOST('objecttype', 'aZ09');
$id = GETPOSTINT('id');
if (!in_array($objecttype, array('customer_invoice', 'supplier_invoice', 'expense_report'), true) || $id <= 0) {
	accessforbidden();
}
$action = GETPOST('action', 'aZ09');

$langs->loadLangs(array('embeddedbookkeeping@embeddedbookkeeping', 'compta', 'bills', 'accountancy'));

// --- Fetch + security --------------------------------------------------------
$isCustomer = ($objecttype === 'customer_invoice');
$isSupplier = ($objecttype === 'supplier_invoice');
$isExpense  = ($objecttype === 'expense_report');

if ($isCustomer) {
	$object = new Facture($db);
	$object->fetch($id);
	$object->fetch_thirdparty();
	$object->fetch_lines();
	$isdraft = ((int) $object->statut === Facture::STATUS_DRAFT);
	restrictedArea($user, 'facture', $object->id, '', '', 'fk_soc', 'rowid', $isdraft);
} elseif ($isSupplier) {
	$object = new FactureFournisseur($db);
	$object->fetch($id);
	$object->fetch_thirdparty();
	$object->fetch_lines();
	$isdraft = ((int) $object->statut === FactureFournisseur::STATUS_DRAFT);
	restrictedArea($user, 'fournisseur', $id, 'facture_fourn', 'facture', 'fk_soc', 'rowid', $isdraft);
} else {
	$object = new ExpenseReport($db);
	$object->fetch($id);
	$object->fetch_lines();
	restrictedArea($user, 'expensereport', $object->id, 'expensereport');
}
if ($object->id <= 0 || (int) $object->id !== $id) {
	accessforbidden();
}

// Module gate on top of the core object permission.
if (empty($user->rights->embeddedbookkeeping->bookkeeping->read) && empty($user->admin)) {
	accessforbidden();
}

$canWrite = !empty($user->rights->embeddedbookkeeping->bookkeeping->write) || !empty($user->admin);

// Status eligibility (same rule as the card button).
// ExpenseReport exposes the status as $fk_statut (no $statut property exists).
if ($isExpense) {
	$statut = isset($object->fk_statut) ? (int) $object->fk_statut : -1;
} else {
	$statut = isset($object->statut) ? (int) $object->statut : -1;
}
$eligibleStatuses = $isExpense
	? array(ExpenseReport::STATUS_VALIDATED, ExpenseReport::STATUS_APPROVED, ExpenseReport::STATUS_CLOSED)
	: array(1, 2); // invoice VALIDATED / PAID
$statusEligible = in_array($statut, $eligibleStatuses, true);

// Situation invoices not fully issued: line amounts are partial, core journal
// handles them with situation_ratio compensation — we don't, so point there.
$situationBlocked = false;
if ($isCustomer && (int) $object->type === Facture::TYPE_SITUATION && !empty($object->lines)) {
	foreach ($object->lines as $l) {
		if (isset($l->situation_percent) && (float) $l->situation_percent < 100.0) {
			$situationBlocked = true;
			break;
		}
	}
}

$hookmanager->initHooks(array('embeddedbookkeepingtab', 'globalcard'));
$parameters = array('id' => $id, 'objecttype' => $objecttype);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook > 0) {
	$action = $hookmanager->resArray[0] ?? $action;
}

$alreadyDone = EBKBookkeepingAlreadyDone::existsFor($db, $objecttype, $id, (int) $conf->entity);

$showForm = !$alreadyDone && $canWrite && $statusEligible && !$situationBlocked;

// --- POST: record the entry --------------------------------------------------
if ($action === 'record' && $showForm) {
	$error = 0;
	if (GETPOST('token', 'alpha') !== newToken()) {
		setEventMessages($langs->trans('ErrorTokenMismatch'), null, 'errors');
		$error++;
	}
	if (!$error && $alreadyDone) {
		setEventMessages($langs->trans('EBKAlreadyBookkeeping'), null, 'errors');
		$error++;
	}
	if (!$error) {
		$draft = EBKEntryDraft::fromPost($objecttype, $id, (string) $object->ref);
		// fromPost fills date_doc from the optional ebk_doc_date override; when
		// absent (0) keep the source document date.
		if ($draft->date_doc <= 0) {
			$draft->date_doc = isset($object->date) ? (int) $object->date : 0;
		}
		$draft->date_lim_reglement = isset($object->date_lim_reglement) ? (int) $object->date_lim_reglement : 0;
		if (!$isExpense && is_object($object->thirdparty)) {
			$draft->thirdparty_code = (string) $object->thirdparty->code_client;
		}
		if ($draft->label_operation === '') {
			$draft->label_operation = (string) $object->ref;
		}

		// Fill missing account labels server-side (one lookup per distinct account).
		$labelCache = array();
		foreach ($draft->rows as $k => $row) {
			if ($row['label_compte'] === '' && $row['numero_compte'] !== '') {
				if (!isset($labelCache[$row['numero_compte']])) {
					$labelCache[$row['numero_compte']] = EBKAccountLookup::labelForAccount($db, $row['numero_compte'], (int) $conf->entity);
				}
				$draft->rows[$k]['label_compte'] = $labelCache[$row['numero_compte']];
			}
		}

		$resultWrite = EBKBookkeepingWriter::writeEntry($db, $user, $draft, $object);
		if ($resultWrite->ok) {
			setEventMessages($langs->trans('EBKBookkeepingCreated', $resultWrite->piece_num), null, 'mesgs');
			header('Location: '.dol_buildpath('/custom/embeddedbookkeeping/tabs/bookkeeping.php', 1).'?id='.$id.'&objecttype='.$objecttype);
			exit;
		}
		setEventMessages($langs->trans($resultWrite->error_key), null, 'errors');
		if (!empty($resultWrite->error_detail) && !empty($user->admin)) {
			setEventMessages($resultWrite->error_detail, null, 'errors');
		}
	}
	$action = '';
}

// --- Read side ---------------------------------------------------------------
$posted = EBKTabData::postedEntries($db, $objecttype, $id);

$bundle = null;
if ($showForm) {
	$bundle = EBKTabData::defaultDraft($db, $object, $objecttype);
}

// AI prefill availability — NEVER a precondition for the form itself.
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiProviderFactory.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/NullProvider.class.php';
$canAi = (!empty($user->rights->embeddedbookkeeping->ai->suggest) || !empty($user->admin));
$aiProvider = EBKAiProviderFactory::resolve();
$aiEnabled = $canAi && !($aiProvider instanceof NullProvider);

// Account select options (one pool; preselected value always injected when missing).
$accountOptions = EBKAccountLookup::accountCandidates($db, 'INCOME,EXPENSE,ASSET,LIABILITY,CAPITAL', 500, (int) $conf->entity);

// --- View --------------------------------------------------------------------
$title = $langs->trans('EBKTabTitle');
llxHeader('', $title);

if ($isCustomer) {
	$head = facture_prepare_head($object);
	$picto = 'bill';
	$linkback = '<a href="'.DOL_URL_ROOT.'/compta/facture/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
} elseif ($isSupplier) {
	$head = facturefourn_prepare_head($object);
	$picto = 'supplier_invoice';
	$linkback = '<a href="'.DOL_URL_ROOT.'/fourn/facture/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
} else {
	$head = expensereport_prepare_head($object);
	$picto = 'trip';
	$linkback = '<a href="'.DOL_URL_ROOT.'/expensereport/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
}

print dol_get_fiche_head($head, 'ebkbookkeeping', $title, -1, $picto);
print dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '');

/**
 * Render the account <select> with the current value preselected (injected
 * as an extra option when it is not part of the candidate pool).
 */
function ebk_account_select(array $options, $current, $name, $extraCss = '')
{
	$out = '<select class="flat minwidth150'.($extraCss !== '' ? ' '.$extraCss : '').'" name="'.$name.'">';
	$out .= '<option value="">&nbsp;</option>';
	$found = false;
	foreach ($options as $opt) {
		$sel = ((string) $opt['account_number'] === (string) $current) ? ' selected' : '';
		if ($sel !== '') {
			$found = true;
		}
		$out .= '<option value="'.dol_escape_htmltag($opt['account_number']).'"'.$sel.'>'
			.dol_escape_htmltag($opt['account_number'].' - '.dol_trunc($opt['label'], 40)).'</option>';
	}
	if (!$found && $current !== '' && $current !== null) {
		$out .= '<option value="'.dol_escape_htmltag((string) $current).'" selected>'.dol_escape_htmltag((string) $current).'</option>';
	}
	$out .= '</select>';
	return $out;
}

// ==== Section A — per-line accounts ==========================================
if ($bundle !== null) {
	$lineRows = $bundle['lineRows'];
} else {
	// already posted: show the line table read-only for reference
	$lineRows = EBKTabData::lineRows($db, $object, $objecttype);
}

print '<div class="div-title-under-tabs" style="margin-top:8px"><strong>'.$langs->trans('EBKTabLineAccount').'</strong></div>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th class="left">'.$langs->trans('Description').'</th>';
print '<th class="right">'.$langs->trans('Qty').'</th>';
print '<th class="right">'.$langs->trans('TotalHT').'</th>';
print '<th class="right">'.$langs->trans('VAT').'</th>';
print '<th class="left">'.$langs->trans('Account').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '</tr>'."\n";

$statusBadge = array(
	'bound' => array('key' => 'EBKTabAccountStatusBound', 'color' => '#63b563'),
	'suggested' => array('key' => 'EBKTabAccountStatusSuggested', 'color' => '#5b9bd5'),
	'missing' => array('key' => 'EBKTabAccountStatusMissing', 'color' => '#e05a5a'),
);
$hasMissing = false;
foreach ($lineRows as $line) {
	if ($line['status'] === 'missing') {
		$hasMissing = true;
	}
	$account = $line['status'] === 'bound' ? $line['bound_number'] : $line['suggested_number'];
	$accountLabel = $line['status'] === 'bound' ? $line['bound_label'] : $line['suggested_label'];
	$badge = $statusBadge[$line['status']];

	print '<tr class="oddeven">';
	print '<td class="left">'.dol_trunc(dol_escape_htmltag($line['description'] !== '' ? $line['description'] : 'L'.$line['rowid']), 80).'</td>';
	print '<td class="right">'.dol_escape_htmltag((string) $line['qty']).'</td>';
	print '<td class="right">'.price($line['total_ht']).'</td>';
	print '<td class="right">'.price($line['total_tva']).'</td>';
	print '<td class="left">'.($account !== '' ? '<strong>'.dol_escape_htmltag($account).'</strong>'.($accountLabel !== '' ? ' - '.dol_escape_htmltag(dol_trunc($accountLabel, 40)) : '') : '<span class="opacitymedium">—</span>').'</td>';
	print '<td class="center"><span class="badge" style="background:'.$badge['color'].';color:#fff">'.$langs->trans($badge['key']).'</span></td>';
	print '</tr>'."\n";
}
print '</table>'."\n";
if ($hasMissing) {
	print '<div class="warning" style="margin-top:4px">'.$langs->trans('EBKTabUnboundWarning').'</div>';
}

// ==== Section B — entry form ==================================================
if ($situationBlocked) {
	print '<div class="warning" style="margin-top:12px">'.$langs->trans('EBKTabSituationBlocked').'</div>';
} elseif (!$statusEligible) {
	print '<div class="opacitymedium" style="margin-top:12px">'.$langs->trans('EBKTabStatusNotEligible').'</div>';
} elseif ($alreadyDone) {
	print '<div class="opacitymedium" style="margin-top:12px">'.$langs->trans('EBKAlreadyBookkeeping').'</div>';
} elseif (!$canWrite) {
	print '<div class="opacitymedium" style="margin-top:12px">'.$langs->trans('EBKTabNoWritePermission').'</div>';
} elseif ($bundle !== null) {
	$draft = $bundle['draft'];

	print '<div class="div-title-under-tabs" style="margin-top:16px"><strong>'.$langs->trans('EBKTabEntryPreview').'</strong></div>';
	if ($bundle['roundingAdjusted']) {
		print '<div class="warning" style="margin-top:4px">'.$langs->trans('EBKRoundingAdjusted').'</div>';
	}

	print '<form name="formebkentry" id="formebkentry" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="record">';
	print '<input type="hidden" name="id" value="'.$id.'">';
	print '<input type="hidden" name="objecttype" value="'.dol_escape_htmltag($objecttype).'">';
	print '<input type="hidden" name="ebk_doc_type" value="'.dol_escape_htmltag($objecttype).'">';
	print '<input type="hidden" name="ebk_fk_doc" value="'.$id.'">';
	print '<input type="hidden" name="ebk_doc_ref" value="'.dol_escape_htmltag($object->ref).'">';
	print '<input type="hidden" name="ebk_currency" value="'.dol_escape_htmltag($draft->currency).'">';
	print '<input type="hidden" name="ebk_label_operation" value="'.dol_escape_htmltag($draft->label_operation).'">';

	// Accounting date: quick-pick from the document's related dates plus a free
	// date input. The DEFAULT comes from the admin preset constant
	// (EMBEDDEDBOOKKEEPING_DEFAULT_DATE_*), resolved server-side; the picker can
	// override it per entry.
	$relatedDates = EBKTabData::relatedDates($db, $object, $objecttype);
	$prefDate = EBKTabData::resolveDateByPreference($db, $object, $objecttype);
	$draft->date_doc = $prefDate['ts']; // default journal date for this document
	$isoDefault = dol_print_date($prefDate['ts'], '%Y-%m-%d');
	print '<div style="margin:6px 0"><span class="fieldtitle">'.$langs->trans('EBKTabAccountingDate').'</span> ';
	print '<select id="ebk-datesel" class="flat">';
	print '<option value="">—</option>';
	foreach ($relatedDates as $d) {
		$iso = dol_print_date($d['ts'], '%Y-%m-%d');
		$label = ($d['suffix'] !== '') ? sprintf($langs->trans($d['key']), $d['suffix']) : $langs->trans($d['key']);
		$sel = ($prefDate['value'] === $d['value']) ? ' selected' : '';
		print '<option value="'.$iso.'"'.$sel.'>'.dol_escape_htmltag($label.' ('.dol_print_date($d['ts'], 'day').')').'</option>';
	}
	$isoToday = dol_print_date(dol_now(), '%Y-%m-%d');
	print '<option value="'.$isoToday.'"'.($prefDate['value'] === 'today' ? ' selected' : '').'>'.$langs->trans('EBKTabDateToday').' ('.dol_print_date(dol_now(), 'day').')</option>';
	print '</select> ';
	print '<input type="date" id="ebk-docdate" name="ebk_doc_date" value="'.$isoDefault.'" class="flat">';
	print '</div>'."\n";

	print '<table class="noborder centpercent" id="ebkentrytable">';
	print '<tr class="liste_titre">';
	print '<th class="center" style="width:40px">D/C</th>';
	print '<th class="left">'.$langs->trans('Account').'</th>';
	print '<th class="left">'.$langs->trans('Label').'</th>';
	print '<th class="right">'.$langs->trans('Debit').'</th>';
	print '<th class="right">'.$langs->trans('Credit').'</th>';
	print '</tr>'."\n";

	$rowIndex = 0;
	foreach ($draft->rows as $row) {
		$isDebit = ($row['side'] === 'D');
		print '<tr class="oddeven">';
		print '<td class="center"><strong>'.($isDebit ? 'D' : 'C').'</strong>';
		print '<input type="hidden" name="ebk_row_side[]" value="'.$row['side'].'">';
		print '<input type="hidden" name="ebk_row_docdet[]" value="'.(int) $row['fk_docdet'].'">';
		print '<input type="hidden" name="ebk_row_subledger[]" value="'.dol_escape_htmltag($row['subledger_account']).'">';
		print '<input type="hidden" name="ebk_row_subledgerlabel[]" value="'.dol_escape_htmltag($row['subledger_label']).'">';
		print '</td>';
		print '<td class="left">'.ebk_account_select($accountOptions, $row['numero_compte'], 'ebk_row_account[]', 'ebk-row-account').'</td>';
		print '<td class="left"><input type="text" class="flat minwidth200" name="ebk_row_label[]" value="'.dol_escape_htmltag(dol_trunc($row['label_operation'], 120)).'"></td>';
		print '<td class="right">'.($isDebit ? '<input type="text" class="flat right ebk-amt" size="8" name="ebk_row_amount[]" value="'.price(abs($row['amount'])).'">' : '<span class="amount ebk-amt-display"></span>').'</td>';
		print '<td class="right">'.($isDebit ? '<span class="amount ebk-amt-display"></span>' : '<input type="text" class="flat right ebk-amt" size="8" name="ebk_row_amount[]" value="'.price(abs($row['amount'])).'">').'</td>';
		print '</tr>'."\n";
		$rowIndex++;
	}

	print '<tr class="liste_total">';
	print '<td colspan="3" class="right"><strong>'.$langs->trans('EBKTabTotals').'</strong></td>';
	print '<td class="right"><strong id="ebk-total-debit">—</strong></td>';
	print '<td class="right"><strong id="ebk-total-credit">—</strong></td>';
	print '</tr>'."\n";
	print '</table>'."\n";

	print '<div class="center" style="margin-top:8px">';
	if ($aiEnabled) {
		print '<input type="button" id="ebk-ai-prefill" class="button butAction" value="'.dol_escape_htmltag($langs->trans('EBKTabAiPrefill')).'">';
	} elseif ($canAi) {
		print '<span class="butActionRefused" title="'.dol_escape_htmltag($langs->trans('EBKTabAiUnavailable')).'">'.dol_escape_htmltag($langs->trans('EBKTabAiPrefill')).'</span>';
		print ' ';
	}
	print '<input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('EBKTabRecordEntry')).'">';
	print '</div>';
	print '</form>'."\n";
}

// ==== Section C — posted entries ==============================================
if (!empty($posted)) {
	print '<div class="div-title-under-tabs" style="margin-top:16px"><strong>'.$langs->trans('EBKTabPostedEntries').'</strong></div>';
	foreach ($posted as $piece) {
		$pieceUrl = DOL_URL_ROOT.'/accountancy/bookkeeping/card.php?piece_num='.((int) $piece['piece_num']);
		print '<table class="noborder centpercent" style="margin-top:6px">';
		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans('EBKTabPieceNum').' <a href="'.dol_escape_htmltag($pieceUrl).'">'.(int) $piece['piece_num'].'</a> — '.dol_escape_htmltag($piece['doc_date']).' — '.dol_escape_htmltag($piece['code_journal']).'</th>';
		print '<th class="right">'.$langs->trans('Debit').'</th>';
		print '<th class="right">'.$langs->trans('Credit').'</th>';
		print '</tr>'."\n";
		foreach ($piece['lines'] as $l) {
			print '<tr class="oddeven">';
			print '<td>'.dol_escape_htmltag((string) $l->numero_compte).($l->label_compte !== '' ? ' - '.dol_escape_htmltag(dol_trunc((string) $l->label_compte, 40)) : '')
				.(isset($l->subledger_account) && $l->subledger_account !== '' ? ' <span class="opacitymedium">('.dol_escape_htmltag((string) $l->subledger_account).')</span>' : '')
				.' &nbsp;<span class="opacitymedium">'.dol_escape_htmltag(dol_trunc((string) $l->label_operation, 60)).'</span></td>';
			print '<td class="right">'.((float) $l->debit > 0 ? price($l->debit) : '').'</td>';
			print '<td class="right">'.((float) $l->credit > 0 ? price($l->credit) : '').'</td>';
			print '</tr>'."\n";
		}
		print '<tr class="liste_total">';
		print '<td class="right"><strong>'.$langs->trans('Total').'</strong></td>';
		print '<td class="right"><strong>'.price($piece['debit']).'</strong></td>';
		print '<td class="right"><strong>'.price($piece['credit']).'</strong></td>';
		print '</tr>'."\n";
		print '</table>'."\n";
	}
}

print dol_get_fiche_end();

// --- Inline JS: live totals + optional AI prefill -----------------------------
$jsSide = $isExpense ? 'expense' : ($isSupplier ? 'supplier' : 'customer');
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
	var form = document.getElementById('formebkentry');
	if (form) {
		function recompute() {
			var d = 0, c = 0;
			var rows = form.querySelectorAll('tr');
			rows.forEach(function(tr) {
				var sideEl = tr.querySelector('input[name="ebk_row_side[]"]');
				var amtEl = tr.querySelector('input[name="ebk_row_amount[]"]');
				if (!sideEl || !amtEl) { return; }
				var v = parseFloat(String(amtEl.value).replace(/,/g, '.'));
				if (isNaN(v)) { v = 0; }
				if (sideEl.value === 'D') { d += v; } else { c += v; }
			});
			var elD = document.getElementById('ebk-total-debit'), elC = document.getElementById('ebk-total-credit');
			if (elD) { elD.textContent = d.toFixed(2); }
			if (elC) { elC.textContent = c.toFixed(2) + (Math.abs(d - c) >= 0.005 ? ' ⚠' : ''); }
		}
		form.querySelectorAll('input[name="ebk_row_amount[]"]').forEach(function(inp) {
			inp.addEventListener('change', recompute);
		});
		recompute();
	}

	var dateSel = document.getElementById('ebk-datesel');
	var dateInput = document.getElementById('ebk-docdate');
	if (dateSel && dateInput) {
		dateSel.addEventListener('change', function() {
			if (dateSel.value !== '') {
				dateInput.value = dateSel.value;
			}
		});
	}

	var aiBtn = document.getElementById('ebk-ai-prefill');
	if (aiBtn) {
		aiBtn.addEventListener('click', function() {
			aiBtn.disabled = true;
			var payload = {
				side: <?php echo json_encode($jsSide); ?>,
				id: <?php echo (int) $id; ?>,
				token: <?php echo json_encode(newToken()); ?>
			};
			fetch(<?php echo json_encode(dol_buildpath('/custom/embeddedbookkeeping/ajax/suggest_entries.php', 1)); ?>, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				credentials: 'same-origin',
				body: JSON.stringify(payload)
			}).then(function(r) { return r.json(); }).then(function(data) {
				if (data && data.ok && data.lines && data.lines.length > 0) {
					var l = data.lines[0];
					var rows = document.querySelectorAll('#formebkentry tr');
					rows.forEach(function(tr) {
						var sideEl = tr.querySelector('input[name="ebk_row_side[]"]');
						var sel = tr.querySelector('select[name="ebk_row_account[]"]');
						if (!sideEl || !sel) { return; }
						var want = (sideEl.value === 'D') ? l.debit_account : l.credit_account;
						if (!want) { return; }
						// Only fill the counter row or rows whose account is still
						// unset — never overwrite an explicitly chosen account.
						if (sel.value === '' || parseInt(tr.querySelector('input[name="ebk_row_docdet[]"]').value || '0', 10) === 0) {
							for (var i = 0; i < sel.options.length; i++) {
								if (sel.options[i].value === want) { sel.selectedIndex = i; break; }
							}
							if (sel.value !== want) {
								var opt = document.createElement('option');
								opt.value = want; opt.textContent = want;
								sel.appendChild(opt); sel.value = want;
							}
						}
					});
				} else {
					alert(<?php echo json_encode($langs->trans('EBKAiNoProvider')); ?>);
				}
				aiBtn.disabled = false;
			}).catch(function() { aiBtn.disabled = false; });
		});
	}
});
</script>
<?php

llxFooter();
$db->close();
