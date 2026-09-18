<?php
/* Copyright (C) 2025 SLY Custom
 *
 * @package SLY Custom
 */

/**
 * View renderer: tabs + filters + table + pagination + csv action links.
 *
 * All formatting helpers are expected to be declared in tools.php.
 */
function slyExportsViewRender(
	$db,
	$langs,
	$datasets,
	$activeTab,
	$activeDataset,
	$visibleColumns,
	$filters,
	$pagedRows,
	$totalFiltered,
	$allRowsCount,
	$page,
	$limit,
	$sqlError,
	$missingDepositInvoiceCount = null,
	$missingPoDepositInvoiceCount = null,
	$leftmenuContext = 'sly_export_invoices'
)
{
	$titleKey = ($leftmenuContext === slyExportsLeftmenuCashflow()) ? 'SLYExportMenuPaymentsPlanning' : 'SLYExportMenuLineDetail';
	$title = $langs->trans($titleKey);
	$form = new Form($db);
	llxHeader('', $title);

	print load_fiche_titre($title, '', 'title_export');

	require_once __DIR__.'/sly_export_tabs.lib.php';
	slyExportsPrintTabNavigation($langs, $datasets, $activeTab, null);
	print '<div class="fichecenter">';

	$file = $_SERVER['PHP_SELF'];
	print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" style="margin: 12px 0;">';
	print '<input type="hidden" name="mainmenu" value="tools">';
	print '<input type="hidden" name="leftmenu" value="'.dol_escape_htmltag($leftmenuContext).'">';
	print '<input type="hidden" name="tab" value="'.dol_escape_htmltag($activeTab).'">';

	if ($sqlError !== '') {
		print '<div class="warning" style="margin-bottom:10px;">SQL Error: '.dol_escape_htmltag($sqlError).'</div>';
	}

	if ($activeTab === 'so_inv_receivable' && $missingDepositInvoiceCount !== null && (int) $missingDepositInvoiceCount > 0) {
		$langs->load('slycustom@slycustom');
		$urlMissing = $_SERVER['PHP_SELF'].'?mainmenu=tools&leftmenu='.urlencode($leftmenuContext).'&tab=so_deposit_invoice_missing';
		print '<div class="warning" style="margin-bottom:12px;">';
		print dol_escape_htmltag($langs->trans('SLYDepositInvoiceMissingBanner', (int) $missingDepositInvoiceCount));
		print ' — <a href="'.dol_escape_htmltag($urlMissing).'">'.$langs->trans('SLYDepositInvoiceMissingBannerLink').'</a>';
		print '</div>';
	}

	if ($activeTab === 'po_inv_payable' && $missingPoDepositInvoiceCount !== null && (int) $missingPoDepositInvoiceCount > 0) {
		$langs->load('slycustom@slycustom');
		$urlPoMissing = $_SERVER['PHP_SELF'].'?mainmenu=tools&leftmenu='.urlencode($leftmenuContext).'&tab=po_deposit_invoice_missing';
		print '<div class="warning" style="margin-bottom:12px;">';
		print dol_escape_htmltag($langs->trans('SLYPoDepositInvoiceMissingBanner', (int) $missingPoDepositInvoiceCount));
		print ' — <a href="'.dol_escape_htmltag($urlPoMissing).'">'.$langs->trans('SLYPoDepositInvoiceMissingBannerLink').'</a>';
		print '</div>';
	}

	// Build query suffix for pagination links & export links.
	// Keep current filters and limit, otherwise arrows will not navigate as expected.
	$filterQueryParts = array();
	foreach ($filters as $field => $value) {
		$filterType = isset($value['type']) ? $value['type'] : 'text';
		if ($filterType === 'date') {
			$from = isset($value['from']) ? $value['from'] : '';
			$to = isset($value['to']) ? $value['to'] : '';
			if ($from !== '') {
				$filterQueryParts[] = 'f_'.$field.'_from='.urlencode($from);
			}
			if ($to !== '') {
				$filterQueryParts[] = 'f_'.$field.'_to='.urlencode($to);
			}
		} else {
			$filterValue = isset($value['value']) ? $value['value'] : '';
			if ($filterValue !== '') {
				$filterQueryParts[] = 'f_'.$field.'='.urlencode($filterValue);
			}
		}
	}
	$querySuffix = (!empty($filterQueryParts) ? '&'.implode('&', $filterQueryParts) : '');

	// Preserve selected columns across pagination/export links.
	$isAllColumns = count($visibleColumns) === count($activeDataset['columns']);
	$selectedFieldsStr = '';
	if (!$isAllColumns) {
		$selectedFields = array_map(static function ($c) { return (string) $c['field']; }, $visibleColumns);
		$selectedFieldsStr = implode(',', $selectedFields);
		if ($selectedFieldsStr !== '') {
			$querySuffix .= (empty($querySuffix) ? '' : '&').'selectedfields='.urlencode($selectedFieldsStr);
		}
	}

	// Mimic "limit+1" behavior from SQL so core pagination decides correctly.
	$numForNav = min((int) $totalFiltered, (int) $limit + 1);
	$optionsPagination = '&mainmenu=tools&leftmenu='.urlencode($leftmenuContext).'&tab='.urlencode($activeTab).'&limit='.(int) $limit.$querySuffix;

	$csvBase = $_SERVER['PHP_SELF'].'?mainmenu=tools&leftmenu='.urlencode($leftmenuContext).'&tab='.$activeTab.'&action=download_csv&dataset='.$activeTab;

	$buttonsHtml = '';
	// Force consistent layout in title-right cell (core theme may add margin-bottom on links).
	$buttonsHtml .= '<style type="text/css">
		.slyexports-actions {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: flex-start;
			gap: 10px;
			margin: 0;
			padding: 0;
		}
		/* Override dolibarr theme: pagination and title-right cell default to right align */
		.table-fiche-title .col-right {
			text-align: left !important;
		}
		.table-fiche-title div.pagination {
			float: none !important;
		}
		.slyexports-actions > input.button,
		.slyexports-actions > a.button,
		.slyexports-actions > a.butAction,
		.slyexports-actions > a.butActionDelete {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-height: 36px;
			padding: 0 14px;
			box-sizing: border-box;
			vertical-align: middle;
			margin: 0 !important;
		}
	</style>';

	$buttonsHtml .= '<div class="slyexports-actions">';
	$buttonsHtml .= '<input class="button button-search" type="submit" value="'.dol_escape_htmltag($langs->trans("Search")).'">';
	$resetUrl = $_SERVER['PHP_SELF'].'?mainmenu=tools&leftmenu='.urlencode($leftmenuContext).'&tab='.$activeTab;
	$buttonsHtml .= '<a class="button button-cancel" href="'.dol_escape_htmltag($resetUrl).'">'.$langs->trans("Reset").'</a>';
	$buttonsHtml .= '<a class="butAction" href="'.dol_escape_htmltag($csvBase.$querySuffix).'">'.$langs->trans("Export").' XLS ('.$langs->trans("SearchCriteria").')</a>';
	$buttonsHtml .= '<a class="butActionDelete" href="'.dol_escape_htmltag($csvBase.'&download_all=1').'">'.$langs->trans("Export").' XLS ('.$langs->trans("All").')</a>';
	$buttonsHtml .= '</div>';

	print print_barre_liste(
		dol_escape_htmltag($langs->trans($activeDataset['label'])),
		$page,
		$file,
		$optionsPagination,
		'',
		'',
		'',
		$numForNav,
		$totalFiltered,
		'generic',
		0,
		$buttonsHtml,
		'',
		$limit,
		0,
		0,
		1
	);

	// Column selector: only affects HTML rendering (filters/table columns), not CSV download.
	$selectedFields = array_map(static function ($c) { return (string) $c['field']; }, $visibleColumns);
	$varpageCols = 'sly_export_'.$activeTab.'_cols_'.substr(md5((string) implode(',', $selectedFields)), 0, 10);
	$arrayfields = array();
	$pos = 0;
	foreach ($activeDataset['columns'] as $column) {
		$field = (string) $column['field'];
		$arrayfields[$field] = array(
			'label' => (string) $column['label'],
			'checked' => in_array($field, $selectedFields, true) ? 1 : 0,
			'position' => $pos,
		);
		$pos++;
	}
	print '<div style="text-align:left; margin: 6px 0 10px 0;">';
	print $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpageCols, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN'));
	print '</div>';

	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';

	print '<tr class="liste_titre">';
	foreach ($visibleColumns as $column) {
		print '<th>'.dol_escape_htmltag($column['label']).'</th>';
	}
	print '</tr>';

	print '<tr class="liste_titre_filter">';
	foreach ($visibleColumns as $column) {
		$field = $column['field'];
		$value = isset($filters[$field]['value']) ? $filters[$field]['value'] : '';
		$valueFrom = isset($filters[$field]['from']) ? $filters[$field]['from'] : '';
		$valueTo = isset($filters[$field]['to']) ? $filters[$field]['to'] : '';
		$widgetType = getFilterWidgetType($column);

		print '<td>';
		if ($widgetType === 'status') {
			$options = slyExportsGetStatusFilterOptions($activeTab, $field, $langs);
			if (!is_array($options)) {
				$langs->load('bills');
				$options = array(
					'' => '',
					'0' => $langs->trans('BillShortStatusDraft'),
					'1' => $langs->trans('BillShortStatusValidated'),
					'2' => $langs->trans('BillShortStatusPaid'),
					'3' => $langs->trans('BillShortStatusCanceled'),
				);
			}
			print $form->selectarray('f_'.$field, $options, $value, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
		} elseif ($widgetType === 'bool') {
			$options = array(
				'' => '',
				'1' => $langs->trans("Yes"),
				'0' => $langs->trans("No"),
			);
			print $form->selectarray('f_'.$field, $options, $value, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
		} elseif ($widgetType === 'currency') {
			// ISO 4217 code only (e.g. USD); no full currency list dropdown.
			print '<input class="flat maxwidth100" type="text" name="f_'.$field.'" value="'.dol_escape_htmltag($value).'" maxlength="3" placeholder="USD" style="text-transform:uppercase;" title="ISO 4217">';
		} elseif ($widgetType === 'invoice_statut_codes') {
			$langs->load('bills');
			$options = array(
				'' => '',
				'0' => $langs->trans('BillShortStatusDraft'),
				'1' => $langs->trans('BillShortStatusValidated'),
				'2' => $langs->trans('BillShortStatusPaid'),
				'3' => $langs->trans('BillShortStatusCanceled'),
			);
			print $form->selectarray('f_'.$field, $options, $value, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
		} elseif ($widgetType === 'date') {
			// Use a flex wrapper so the two date inputs can wrap to 2 lines on narrow screens.
			// This mimics core list header filter ergonomics without forcing "no wrap".
			print '<div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">';
			print '<input class="flat" style="flex:1 1 48%; min-width:110px;" type="date" name="f_'.$field.'_from" value="'.dol_escape_htmltag($valueFrom).'" title="'.$langs->trans("DateStart").'">';
			print '<input class="flat" style="flex:1 1 48%; min-width:110px;" type="date" name="f_'.$field.'_to" value="'.dol_escape_htmltag($valueTo).'" title="'.$langs->trans("DateEnd").'">';
			print '</div>';
		} else {
			print '<input class="flat maxwidth100" type="text" name="f_'.$field.'" value="'.dol_escape_htmltag($value).'">';
		}
		print '</td>';
	}
	print '</tr>';

	if (empty($pagedRows)) {
		print '<tr class="oddeven"><td colspan="'.count($visibleColumns).'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
	} else {
		foreach ($pagedRows as $row) {
			print '<tr class="oddeven">';
			foreach ($visibleColumns as $column) {
				$field = $column['field'];
				$raw = isset($row[$field]) ? $row[$field] : '';
				$display = slyFormatExportCellValue($activeTab, $field, $raw, $langs, $row);
				print '<td>'.dol_escape_htmltag($display).'</td>';
			}
			print '</tr>';
		}
	}
	print '</table>';
	print '</div>';
	print '</form>';

	print '<div class="opacitymedium" style="margin-bottom: 6px;">'.dol_escape_htmltag($langs->trans("NbOfLines")).': '.$totalFiltered.' / '.$allRowsCount.'</div>';
	print '</div>';

	print dol_get_fiche_end();

	llxFooter();
	$db->close();
}

