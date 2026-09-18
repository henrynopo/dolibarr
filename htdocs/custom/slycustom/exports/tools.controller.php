<?php
/* Copyright (C) 2025 SLY Custom
 *
 * @package SLY Custom
 */

/**
 * Controller: orchestrate request, filtering, CSV download, and call view renderer.
 *
 * Note: underlying service/formatting helpers are still provided by tools.php
 * (slyFormatExportCellValue, fetchDatasetRows, filterDatasetRows, outputDatasetCsv, etc.)
 */
function slyExportsControllerHandle($db, $langs, $conf)
{
	require_once __DIR__.'/tools.service.php';
	require_once __DIR__.'/sly_export_tabs.lib.php';

	$datasets = getSlyExportDatasets();
	$tabs = array_keys($datasets);
	$activeTab = GETPOST('tab', 'aZ09');
	$lmRaw = GETPOST('leftmenu', 'alphanohtml');
	if ($lmRaw === 'sly_export' || $lmRaw === 'sly_export_all') {
		$lmRaw = 'sly_export_invoices';
	}
	$lmOk = in_array($lmRaw, array(slyExportsLeftmenuDetails(), slyExportsLeftmenuCashflow()), true) ? $lmRaw : '';

	if (empty($activeTab) || !isset($datasets[$activeTab])) {
		if ($lmOk === slyExportsLeftmenuCashflow()) {
			$activeTab = 'so_inv_receivable';
		} else {
			$activeTab = $tabs[0];
		}
	}

	$activeDataset = $datasets[$activeTab];
	$leftmenuContext = slyExportsResolveLeftmenuFromTab($activeTab);

	// Visible columns selector.
	// We parse `selectedfields` from request (commaseparated string or array from checkboxes).
	$selectedFieldsRaw = GETPOST('selectedfields', 'restricthtml');
	$selectedFields = array();
	if (is_array($selectedFieldsRaw)) {
		foreach ($selectedFieldsRaw as $v) {
			$v = trim((string) $v);
			if ($v !== '') $selectedFields[] = $v;
		}
	} else {
		$selectedFieldsStr = trim((string) $selectedFieldsRaw);
		$selectedFieldsStr = trim($selectedFieldsStr, ',');
		if ($selectedFieldsStr !== '') {
			$selectedFields = array_filter(array_map('trim', explode(',', $selectedFieldsStr)), static function ($v) { return $v !== ''; });
		}
	}
	$selectedFields = array_values(array_unique($selectedFields));
	$visibleColumns = $activeDataset['columns'];
	if (!empty($selectedFields)) {
		$visibleColumns = array_values(array_filter($activeDataset['columns'], static function ($c) use ($selectedFields) {
			return in_array($c['field'], $selectedFields, true);
		}));
		if (empty($visibleColumns)) {
			$visibleColumns = $activeDataset['columns'];
		}
	}
	$limit = GETPOST('limit', 'int');
	if ($limit <= 0) {
		$limit = 50;
	}
	$page = GETPOSTISSET('page') ? max(0, GETPOST('page', 'int')) : 0;

	$filters = array();
	foreach ($activeDataset['columns'] as $column) {
		$field = $column['field'];
		$widgetType = getFilterWidgetType($column);
		if ($widgetType === 'date') {
			$filters[$field] = array(
				'type' => $widgetType,
				'from' => trim(GETPOST('f_'.$field.'_from', 'restricthtml')),
				'to' => trim(GETPOST('f_'.$field.'_to', 'restricthtml'))
			);
		} else {
			$filters[$field] = array(
				'type' => $widgetType,
				'value' => normalizeFilterValue(GETPOST('f_'.$field, 'restricthtml'), $widgetType)
			);
		}
	}

	$action = GETPOST('action', 'aZ09');
	if ($action === 'download_csv') {
		$targetDatasetKey = GETPOST('dataset', 'aZ09');
		if (!isset($datasets[$targetDatasetKey])) {
			$targetDatasetKey = $activeTab;
		}
		$targetDataset = $datasets[$targetDatasetKey];

		$targetFilters = array();
		foreach ($targetDataset['columns'] as $column) {
			$field = $column['field'];
			$widgetType = getFilterWidgetType($column);
			if ($widgetType === 'date') {
				$targetFilters[$field] = array(
					'type' => $widgetType,
					'from' => trim(GETPOST('f_'.$field.'_from', 'restricthtml')),
					'to' => trim(GETPOST('f_'.$field.'_to', 'restricthtml'))
				);
			} else {
				$targetFilters[$field] = array(
					'type' => $widgetType,
					'value' => normalizeFilterValue(GETPOST('f_'.$field, 'restricthtml'), $widgetType)
				);
			}
		}

		$downloadAll = (int) GETPOST('download_all', 'int');
		$sqlError = '';
		$rows = slyExportsServiceFetchRows($db, $targetDataset, $sqlError);
		if (empty($downloadAll)) {
			$rows = slyExportsServiceFilterRows($rows, $targetFilters);
		}

		slyExportsServiceOutputCsv($targetDataset['filename'], $targetDataset['columns'], $rows, $targetDatasetKey);
		$db->close();
		exit;
	}

	$sqlError = '';
	$allRows = slyExportsServiceFetchRows($db, $activeDataset, $sqlError);
	$filteredRows = slyExportsServiceFilterRows($allRows, $filters);
	$totalFiltered = count($filteredRows);
	$allRowsCount = count($allRows);

	$offset = $page * $limit;
	if ($offset >= $totalFiltered && $totalFiltered > 0) {
		$page = 0;
		$offset = 0;
	}
	$pagedRows = array_slice($filteredRows, $offset, $limit);

	$missingDepositInvoiceCount = null;
	if ($activeTab === 'so_inv_receivable') {
		$missingDepositInvoiceCount = slyExportsOrdersMissingDepositInvoiceCount($db);
	}
	$missingPoDepositInvoiceCount = null;
	if ($activeTab === 'po_inv_payable') {
		$missingPoDepositInvoiceCount = slyExportsPoOrdersMissingDepositInvoiceCount($db);
	}

	require_once __DIR__.'/tools.view.php';
	slyExportsViewRender(
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
		$missingDepositInvoiceCount,
		$missingPoDepositInvoiceCount,
		$leftmenuContext
	);
}

