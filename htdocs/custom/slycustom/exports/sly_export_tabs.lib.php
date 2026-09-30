<?php
/* Copyright (C) 2025 SLY Custom
 *
 * Tab groups for SLY Export: detail line reports vs AR/AP & deposit compliance.
 */

/**
 * Left menu id for Tools sidebar: SLY Invoices branch (line-detail + ALL-in-One).
 *
 * @return string
 */
function slyExportsLeftmenuDetails()
{
	return 'sly_export_invoices';
}

/**
 * Left menu id for Tools sidebar: AR/AP & deposit group.
 *
 * @return string
 */
function slyExportsLeftmenuCashflow()
{
	return 'sly_export_cashflow';
}

/**
 * Tab keys: original five detail exports (SO/PO/shipment line reports).
 *
 * @return string[]
 */
function slyExportsGetTabGroupKeysCore()
{
	return array('so_details', 'so_inv_details', 'shipment_details', 'po_details', 'po_inv_details');
}

/**
 * Tab keys: receivable/payable lists and missing-deposit alerts.
 *
 * @return string[]
 */
function slyExportsGetTabGroupKeysCash()
{
	return array('so_inv_receivable', 'po_inv_payable', 'so_deposit_invoice_missing', 'po_deposit_invoice_missing');
}

/**
 * Token that matches no tab id (no row highlighted).
 *
 * @return string
 */
function slyExportsInactiveTabToken()
{
	return '__sly_export_none__';
}

/**
 * Dolibarr leftmenu GET param for sidebar highlight: derive from active export tab.
 *
 * @param string $activeTab Dataset key (tab id).
 * @return string
 */
function slyExportsResolveLeftmenuFromTab($activeTab)
{
	if (in_array($activeTab, slyExportsGetTabGroupKeysCash(), true)) {
		return slyExportsLeftmenuCashflow();
	}
	return slyExportsLeftmenuDetails();
}

/**
 * Build dol_get_fiche_head $links array from dataset keys.
 *
 * @param array  $datasets Export datasets from getSlyExportDatasets().
 * @param string[] $tabKeys Ordered keys.
 * @param string $baseUrl   URL to tools.php (with DOL_URL_ROOT) without query string.
 * @param string $leftmenu  Dolibarr leftmenu value for sidebar highlight (sly_export_invoices vs sly_export_cashflow).
 * @param Translate $langs
 * @return array
 */
function slyExportsBuildFicheHeadFromDatasets(array $datasets, array $tabKeys, $baseUrl, $leftmenu, $langs)
{
	$head = array();
	$i = 0;
	foreach ($tabKeys as $key) {
		if (empty($datasets[$key])) {
			continue;
		}
		$head[$i] = array(
			$baseUrl.'?mainmenu=tools&leftmenu='.urlencode($leftmenu).'&tab='.$key,
			$langs->trans($datasets[$key]['label']),
			$key,
		);
		$i++;
	}
	return $head;
}

/**
 * Print one tab bar: line-detail + ALL-in-One, or AR/AP & deposits only (matches active tab / menu branch).
 *
 * @param Translate $langs
 * @param array     $datasets
 * @param string    $activeTab
 * @param string|null $baseUrl Base URL for tab links (null = current script).
 * @return void
 */
function slyExportsPrintTabNavigation($langs, array $datasets, $activeTab, $baseUrl = null)
{
	if ($baseUrl === null || $baseUrl === '') {
		$baseUrl = $_SERVER['PHP_SELF'];
	}

	$langs->load('slycustom@slycustom');

	$coreKeys = slyExportsGetTabGroupKeysCore();
	$cashKeys = slyExportsGetTabGroupKeysCash();
	$inactive = slyExportsInactiveTabToken();
	$lmDetail = slyExportsLeftmenuDetails();
	$lmCash = slyExportsLeftmenuCashflow();

	if (in_array($activeTab, $cashKeys, true)) {
		$headCash = slyExportsBuildFicheHeadFromDatasets($datasets, $cashKeys, $baseUrl, $lmCash, $langs);
		print '<div class="opacitymedium" style="margin: 4px 0 2px 0;">'.$langs->trans('SLYExportTabGroupCashflow').'</div>'."\n";
		print dol_get_fiche_head($headCash, $activeTab, '', -1, '', 0, '', '', 0, '_sly_cashflow');
		return;
	}

	$headCore = slyExportsBuildFicheHeadFromDatasets($datasets, $coreKeys, $baseUrl, $lmDetail, $langs);
	$n = count($headCore);
	$urlAll = DOL_URL_ROOT.'/custom/slycustom/exports/export_all.php?mainmenu=tools&leftmenu='.urlencode($lmDetail);
	$headCore[$n] = array($urlAll, $langs->trans('SLYExportAllInOne'), 'export_all');
	$activeCore = (in_array($activeTab, $coreKeys, true) || $activeTab === 'export_all') ? $activeTab : $inactive;

	print '<div class="opacitymedium" style="margin: 4px 0 2px 0;">'.$langs->trans('SLYExportTabGroupDetails').'</div>'."\n";
	print dol_get_fiche_head($headCore, $activeCore, '', -1, '', 0, '', '', 0, '_sly_details');
}

/**
 * @deprecated Use {@see slyExportsPrintTabNavigation()}
 */
function slyExportsPrintDualTabNavigation($langs, array $datasets, $activeTab, $baseUrl = null)
{
	slyExportsPrintTabNavigation($langs, $datasets, $activeTab, $baseUrl);
}
