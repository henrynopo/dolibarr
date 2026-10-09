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
 *	\brief      Read-only "Accounting entries" tab for customer invoices,
 *	             supplier invoices and expense reports.
 *
 *             ARCHITECTURE: this tab is a pure display layer that reads
 *             the core bookkeeping table (llx_accounting_bookkeeping) after
 *             the core accounting journals (sellsjournal / purchasesjournal /
 *             expensereportsjournal) have run. It does NOT implement or
 *             replicate accounting logic — all accounting rules come from
 *             the core accountancy module. Users must run the appropriate
 *             core journal before entries appear here.
 *
 *	Sections:
 *	  A. Per-line account bindings — one row per invoice line, showing the
 *	     account each line is booked to (from llx_accounting_bookkeeping).
 *	     If the document has not been processed by the core journal yet,
 *	     the table is shown from the source line table with a prominent
 *	     "run the core journal first" banner.
 *	  B. Full accounting entry — all bookkeeping rows for this document,
 *	     grouped by piece_num, linked to core bookkeeping/card.php.
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
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
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
	restrictedArea($user, 'facture', $object->id, '', '', 'fk_soc', 'rowid');
} elseif ($isSupplier) {
	$object = new FactureFournisseur($db);
	$object->fetch($id);
	$object->fetch_thirdparty();
	$object->fetch_lines();
	restrictedArea($user, 'fournisseur', $id, 'facture_fourn', 'facture', 'fk_soc', 'rowid');
} else {
	$object = new ExpenseReport($db);
	$object->fetch($id);
	$object->fetch_lines();
	restrictedArea($user, 'expensereport', $object->id, 'expensereport');
}
if ($object->id <= 0 || (int) $object->id !== $id) {
	accessforbidden();
}

// Module-level read permission check.
if (empty($user->rights->embeddedbookkeeping->bookkeeping->read) && empty($user->admin)) {
	accessforbidden();
}

// --- Read from core bookkeeping table ----------------------------------------
$postedEntries = array();
$lineBindings  = array(); // rowid of invoice line => account info
$hasEntries    = false;

$bk = new BookKeeping($db);
$filter = "(t.doc_type:=:'".$db->escape($objecttype)."') AND (t.fk_doc:=:".((int) $id).")";
$bk->fetchAll('ASC,ASC', 'piece_num,rowid', 0, 0, $filter);
$bkLines = is_array($bk->lines) ? $bk->lines : array();

if (!empty($bkLines)) {
	$hasEntries = true;

	// Group by piece_num for Section B display.
	$groups = array();
	foreach ($bkLines as $l) {
		$pn = (int) $l->piece_num;
		if (!isset($groups[$pn])) {
			$groups[$pn] = array(
				'piece_num'    => $pn,
				'doc_date'     => isset($l->doc_date) ? dol_print_date($l->doc_date, 'day') : '',
				'code_journal' => isset($l->code_journal) ? (string) $l->code_journal : '',
				'lines'        => array(),
				'debit'        => 0.0,
				'credit'       => 0.0,
			);
		}
		$groups[$pn]['lines'][] = $l;
		$groups[$pn]['debit']  += (float) $l->debit;
		$groups[$pn]['credit'] += (float) $l->credit;

		// Build per-line binding map: fk_docdet => account info.
		$docdet = (int) ($l->fk_docdet ?? 0);
		if ($docdet > 0 && !isset($lineBindings[$docdet])) {
			$lineBindings[$docdet] = array(
				'account_number' => (string) ($l->numero_compte ?? ''),
				'label'          => (string) ($l->label_compte ?? ''),
				'debit'          => (float) $l->debit,
				'credit'         => (float) $l->credit,
				'piece_num'      => $pn,
			);
		}
	}
	ksort($groups);
	$postedEntries = $groups;
}

// --- Build line-level display (Section A) ------------------------------------
// Always show the per-line breakdown from the source object.
// If the core journal has not run yet, all lines will be unbound.
$lineRows = array();
foreach ($object->lines as $line) {
	$lid = (int) ($line->rowid ?? $line->id ?? 0);
	if ($lid <= 0) continue;

	$description = $isExpense ? (string) ($line->comments ?? '') : (string) ($line->description ?? '');

	// Amounts from the source line (always available, regardless of bookkeeping status).
	$totalHt  = property_exists($line, 'total_ht')        ? (float) $line->total_ht  : 0.0;
	$totalTva = property_exists($line, 'total_tva')        ? (float) $line->total_tva : 0.0;
	$totalTtc = property_exists($line, 'total_ttc')        ? (float) $line->total_ttc : 0.0;
	$qty      = property_exists($line, 'qty')              ? (float) $line->qty      : 0.0;
	$tvaTx    = property_exists($line, 'tva_tx')          ? (float) $line->tva_tx  : 0.0;

	// Core journal bindings: if a bookkeeping row exists for this line, use it.
	$bound     = isset($lineBindings[$lid]) ? $lineBindings[$lid] : null;
	$account   = $bound ? $bound['account_number'] : '';
	$acctLabel = $bound ? $bound['label'] : '';
	$status    = $bound ? 'bound' : 'missing';

	$lineRows[] = array(
		'rowid'         => $lid,
		'description'   => $description,
		'qty'           => $qty,
		'total_ht'      => $totalHt,
		'total_tva'     => $totalTva,
		'total_ttc'     => $totalTtc,
		'tva_tx'        => $tvaTx,
		'account'       => $account,
		'account_label' => $acctLabel,
		'status'        => $status,
		'piece_num'     => $bound ? $bound['piece_num'] : 0,
	);
}

// --- AI provider resolution (used for the floating assistant only) -------------
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiProviderFactory.class.php';
EBKAiProviderFactory::resolve();

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

// ==== Section A — per-line account bindings ===================================
$journalUrl = '';
if ($isCustomer) {
	$journalUrl = DOL_URL_ROOT.'/accountancy/journal/sellsjournal.php';
} elseif ($isSupplier) {
	$journalUrl = DOL_URL_ROOT.'/accountancy/journal/purchasesjournal.php';
} else {
	$journalUrl = DOL_URL_ROOT.'/accountancy/journal/expensereportsjournal.php';
}

print '<div class="div-title-under-tabs" style="margin-top:8px"><strong>'.$langs->trans('EBKTabLineAccount').'</strong></div>';

if (!$hasEntries) {
	$journalName = $isCustomer ? $langs->trans('SellsJournal')
	               : ($isSupplier ? $langs->trans('PurchasesJournal')
	               : $langs->trans('ExpenseReportsJournal'));
	print '<div class="info" style="margin:4px 0;padding:8px;border:1px solid #e0a030;background:#fffbea;border-radius:4px">';
	print $langs->trans('EBKTabNotYetBooked', $journalName);
	print '</div>';
}

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
	'bound'      => array('key' => 'EBKTabAccountStatusBound',       'color' => '#63b563'),
	'suggested'  => array('key' => 'EBKTabAccountStatusSuggested',  'color' => '#5b9bd5'),
	'missing'    => array('key' => 'EBKTabAccountStatusMissing',    'color' => '#e05a5a'),
);
$hasMissing = false;
foreach ($lineRows as $line) {
	if ($line['status'] === 'missing') {
		$hasMissing = true;
	}
	$badge = $statusBadge[$line['status']];

	print '<tr class="oddeven">';
	print '<td class="left">'.dol_escape_htmltag(dol_trunc($line['description'] !== '' ? $line['description'] : 'L'.$line['rowid'], 80)).'</td>';
	print '<td class="right">'.dol_escape_htmltag((string) $line['qty']).'</td>';
	print '<td class="right">'.price($line['total_ht']).'</td>';
	print '<td class="right">'.price($line['total_tva']).'</td>';
	print '<td class="left">';
	if ($line['account'] !== '') {
		$pieceUrl = DOL_URL_ROOT.'/accountancy/bookkeeping/card.php?piece_num='.((int) $line['piece_num']);
		print '<strong>'.dol_escape_htmltag($line['account']).'</strong>';
		if ($line['account_label'] !== '') {
			print ' - '.dol_escape_htmltag(dol_trunc($line['account_label'], 40));
		}
		print ' <span class="opacitymedium">(<a href="'.dol_escape_htmltag($pieceUrl).'" target="_blank">'.$line['piece_num'].'</a>)</span>';
	} else {
		print '<span class="opacitymedium">—</span>';
	}
	print '</td>';
	print '<td class="center"><span class="badge" style="background:'.$badge['color'].';color:#fff">'.$langs->trans($badge['key']).'</span></td>';
	print '</tr>'."\n";
}
print '</table>'."\n";

if ($hasMissing) {
	print '<div class="warning" style="margin-top:4px">'.$langs->trans('EBKTabUnboundWarning').'</div>';
}

// Prompt to run the core journal if not yet done.
if (!$hasEntries) {
	print '<div class="center" style="margin-top:12px">';
	$journalName = $isCustomer ? $langs->trans('SellsJournal')
	               : ($isSupplier ? $langs->trans('PurchasesJournal')
	               : $langs->trans('ExpenseReportsJournal'));
	print '<a class="butAction" href="'.dol_escape_htmltag($journalUrl).'" target="_blank">';
	print $langs->trans('EBKTabRunJournal', $journalName);
	print '</a>';
	print '</div>';
}

// ==== Section B — full accounting entry =======================================
if (!empty($postedEntries)) {
	print '<div class="div-title-under-tabs" style="margin-top:16px"><strong>'.$langs->trans('EBKTabPostedEntries').'</strong></div>';
	foreach ($postedEntries as $piece) {
		$pieceUrl = DOL_URL_ROOT.'/accountancy/bookkeeping/card.php?piece_num='.((int) $piece['piece_num']);
		print '<table class="noborder centpercent" style="margin-top:6px">';
		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans('EBKTabPieceNum').' <a href="'.dol_escape_htmltag($pieceUrl).'" target="_blank">'.(int) $piece['piece_num'].'</a> — '.dol_escape_htmltag($piece['doc_date']).' — '.dol_escape_htmltag($piece['code_journal']).'</th>';
		print '<th class="right">'.$langs->trans('Debit').'</th>';
		print '<th class="right">'.$langs->trans('Credit').'</th>';
		print '</tr>'."\n";
		foreach ($piece['lines'] as $l) {
			$acctNum  = (string) ($l->numero_compte ?? '');
			$acctLbl  = (string) ($l->label_compte ?? '');
			$subledger = (string) ($l->subledger_account ?? '');
			$opLabel  = (string) ($l->label_operation ?? '');
			$debit    = (float) ($l->debit  ?? 0);
			$credit   = (float) ($l->credit ?? 0);

			print '<tr class="oddeven">';
			print '<td>'.dol_escape_htmltag($acctNum);
			if ($acctLbl !== '') print ' - '.dol_escape_htmltag(dol_trunc($acctLbl, 40));
			if ($subledger !== '') print ' <span class="opacitymedium">('.dol_escape_htmltag($subledger).')</span>';
			if ($opLabel !== '') print ' &nbsp;<span class="opacitymedium">'.dol_escape_htmltag(dol_trunc($opLabel, 60)).'</span>';
			print '</td>';
			print '<td class="right">'.($debit  > 0 ? price($debit)  : '').'</td>';
			print '<td class="right">'.($credit > 0 ? price($credit) : '').'</td>';
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

// --- Floating AI assistant widget (read-only context) --------------------------
$ws = $isSupplier ? '供应商发票' : ($isCustomer ? '客户发票' : '费用报销单');
?>
<script src="<?php echo dol_buildpath('/custom/embeddedbookkeeping/js/widget.js', 1); ?>"></script>
<script>
(function () {
    if (!window.EBKAiWidget) return;
    window.EBKAiWidget.init({
        endpoint: '<?php echo dol_buildpath('/custom/embeddedbookkeeping/ajax/chat.php', 1); ?>',
        token: '<?php echo newToken(); ?>',
        context: {
            section: '<?php echo dol_escape_js($ws); ?>',
            title: '<?php echo dol_escape_js($object->ref ?? ''); ?>',
            doc_id: <?php echo (int) $id; ?>,
            doc_type: '<?php echo $objecttype; ?>'
        }
    });
})();
</script>
<?php
