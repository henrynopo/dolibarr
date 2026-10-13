<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__.'/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once __DIR__.'/tools.datasets.php';
require_once __DIR__.'/sly_export_tabs.lib.php';

if (!is_object($conf->slycustom) || empty($conf->slycustom->enabled)) {
	accessforbidden();
	exit;
}

// Exports expose the full business dataset: require the module export right
// (admins pass) and never serve external/thirdparty users.
if (empty($user->admin) && empty($user->rights->slycustom->export->read)) {
	accessforbidden();
	exit;
}
if (!empty($user->socid)) {
	accessforbidden();
	exit;
}

$langs->loadLangs(array("slycustom@slycustom", "other", "main"));

@ini_set('max_execution_time', 180);

/**
 * Escape tab-separated value for XLS-compatible export.
 *
 * @param mixed $value
 * @return string
 */
function slyCsvEscape($value)
{
	$value = (string) $value;
	// Neutralize spreadsheet formula injection: a leading = + - @ would be
	// evaluated by Excel/LibreOffice when the exported file is opened.
	// Pure numeric strings (digits, separators, spaces only — e.g. negative
	// credit note amounts like -84.80 or -1,234.56) are exempt: they can never
	// form a formula, and the quote prefix would break Excel number parsing.
	if (isset($value[0]) && strpos('=+-@', $value[0]) !== false
		&& !preg_match('/^[-+]?[\d][\d,. ]*$/', $value)) {
		$value = "'".$value;
	}
	$value = str_replace("\t", "\\t", $value);
	$value = preg_replace("/\r?\n/", "\\n", $value);
	$value = str_replace('"', '""', $value);
	return '"'.$value.'"';
}

/**
 * Plain text label for customer order status (matches Commande status constants).
 *
 * @param string|int $status
 * @param Translate $langs
 * @return string
 */
function slyCommandeStatusPlainLabel($status, $langs)
{
	$langs->load('orders');
	$status = (int) $status;
	switch ($status) {
		case Commande::STATUS_CANCELED:
			return $langs->trans('StatusOrderCanceledShort');
		case Commande::STATUS_DRAFT:
			return $langs->trans('StatusOrderDraftShort');
		case Commande::STATUS_VALIDATED:
			return $langs->trans('StatusOrderValidated');
		case Commande::STATUS_SHIPMENTONPROCESS:
			return $langs->trans('StatusOrderSentShort');
		case Commande::STATUS_CLOSED:
			return $langs->trans('StatusOrderDelivered');
		default:
			return (string) $status;
	}
}

/**
 * Plain text label for supplier order status (fournisseur.commande.class.php).
 *
 * @param string|int $status
 * @param Translate $langs
 * @return string
 */
function slySupplierOrderStatusPlainLabel($status, $langs)
{
	global $conf;
	$langs->load('orders');
	$status = (int) $status;

	// Dolibarr has many supplier-order states, map the main ones to the core keys.
	switch ($status) {
		case 0:
			return $langs->transnoentitiesnoconv('StatusSupplierOrderDraftShort');
		case 1:
			return $langs->transnoentitiesnoconv('StatusSupplierOrderValidatedShort');
		case 2:
			return $langs->transnoentitiesnoconv('StatusSupplierOrderApprovedShort');
		case 3:
				// Keep filter/dropdown labels consistent with core list (short label mode=1).
				return $langs->transnoentitiesnoconv('StatusSupplierOrderOnProcessShort');
		case 4:
			return $langs->transnoentitiesnoconv('StatusSupplierOrderReceivedPartiallyShort');
		case 5:
			return $langs->transnoentitiesnoconv('StatusSupplierOrderReceivedAllShort');
		case 6:
		case 7:
			return $langs->transnoentitiesnoconv('StatusSupplierOrderCanceledShort');
		case 9:
			return $langs->transnoentitiesnoconv('StatusSupplierOrderRefusedShort');
		default:
			return (string) $status;
	}
}

/**
 * Plain text label for expedition (sending) status.
 *
 * @param string|int $status
 * @param Translate $langs
 * @return string
 */
function slyShipmentStatusPlainLabel($status, $langs)
{
	$langs->load('sendings');
	$status = (int) $status;

	// Core expedition/list.php uses 0/1/2 only:
	// 0 Draft, 1 Validated, 2 Processed
	// 3 = STATUS_SHIPMENT_IN_PROGRESS (declared in v24 core, no core flow sets it)
	switch ($status) {
		case 0:
			return $langs->transnoentitiesnoconv('StatusSendingDraftShort');
		case 1:
			return $langs->transnoentitiesnoconv('StatusSendingValidatedShort');
		case 2:
			return $langs->transnoentitiesnoconv('StatusSendingProcessedShort');
		case -1:
			return $langs->transnoentitiesnoconv('StatusSendingCanceledShort');
		case 3:
			$langs->load('slycustom@slycustom');
			return $langs->transnoentitiesnoconv('SLYSendingStatusInProgress');
		default:
			return (string) $status;
	}
}

/**
 * Format comma-separated customer invoice fk_statut codes (0–3) for display/CSV.
 *
 * @param string $value Raw "1" or "1,2"
 * @param Translate $langs
 * @return string
 */
function slyExportsFormatCommaSeparatedInvoiceStatuts($value, $langs)
{
	$value = trim((string) $value);
	if ($value === '') {
		return '';
	}
	$langs->load('bills');
	$parts = array_filter(array_map('trim', explode(',', $value)), static function ($v) {
		return $v !== '';
	});
	$out = array();
	foreach ($parts as $p) {
		$st = (int) $p;
		if ($st === 0) {
			$out[] = $langs->trans('BillShortStatusDraft');
		} elseif ($st === 1) {
			$out[] = $langs->trans('BillShortStatusValidated');
		} elseif ($st === 2) {
			$out[] = $langs->trans('BillShortStatusPaid');
		} elseif ($st === 3) {
			$out[] = $langs->trans('BillShortStatusCanceled');
		} else {
			$out[] = (string) $p;
		}
	}
	return implode(', ', $out);
}

/**
 * Format cell for screen/CSV (raw DB values kept in row for filters).
 *
 * @param string $datasetKey
 * @param string $field
 * @param string $value
 * @param Translate $langs
 * @param array|null $row Full dataset row (optional) for cross-field formatting.
 * @param bool $plainNumber True for CSV export: amounts without thousand separators
 *                          (price() 'none' locale = international '.' decimal, no grouping)
 *                          so Excel keeps them numeric.
 * @return string
 */
function slyFormatExportCellValue($datasetKey, $field, $value, $langs, $row = null, $plainNumber = false)
{
	if (($datasetKey === 'so_details' || $datasetKey === 'so_deposit_invoice_missing') && $field === 'fk_statut') {
		return slyCommandeStatusPlainLabel($value, $langs);
	}
	if ($datasetKey === 'so_deposit_invoice_missing' && $field === 'Alert_Message') {
		$langs->load('slycustom@slycustom');
		if ((string) $value === 'MISSING_DEPOSIT_INVOICE') {
			return $langs->trans('SLYDepositInvoiceMissingAlertShort');
		}
	}
	if ($datasetKey === 'so_details' && $field === 'Billed') {
		if ((string) $value === '1') {
			return $langs->trans('Yes');
		}
		if ((string) $value === '0' || $value === '') {
			return $langs->trans('No');
		}
		return (string) $value;
	}
	if ($datasetKey === 'shipment_details' && $field === 'billed') {
		if ((string) $value === '1') {
			return $langs->trans('Yes');
		}
		if ((string) $value === '0' || $value === '') {
			return $langs->trans('No');
		}
		return (string) $value;
	}

	// SLY PO Details / PO missing supplier deposit: translate supplier order status & billed
	if ($datasetKey === 'po_details') {
		if ($field === 'Billed') {
			if ((string) $value === '1') return $langs->trans('Yes');
			if ((string) $value === '0' || (string) $value === '') return $langs->trans('No');
			return (string) $value;
		}
		if ($field === 'fk_statut') return slySupplierOrderStatusPlainLabel($value, $langs);
	}
	if ($datasetKey === 'po_deposit_invoice_missing') {
		$langs->load('slycustom@slycustom');
		if ($field === 'fk_statut') {
			return slySupplierOrderStatusPlainLabel($value, $langs);
		}
		if ($field === 'Alert_Message' && (string) $value === 'MISSING_PO_DEPOSIT_INVOICE') {
			return $langs->trans('SLYPoDepositInvoiceMissingAlertShort');
		}
	}

	// SLY PO Invoice Details / PO payable list: translate invoice paid/status
	if ($datasetKey === 'po_inv_details' || $datasetKey === 'po_inv_payable') {
		$langs->load('bills');
		if ($field === 'Paid') {
			$paid = (int) $value;
			if ($paid === 1) return $langs->trans('Yes');
			return $langs->trans('No');
		}
		if ($datasetKey === 'po_inv_payable' && ($field === 'SO_Cust_Deposit_Invoice_Status' || $field === 'SO_Cust_Standard_Invoice_Status')) {
			return slyExportsFormatCommaSeparatedInvoiceStatuts($value, $langs);
		}
		if ($field === 'fk_statut') {
			$status = (int) $value;
			if ($status === 0) {
				return $langs->trans('BillShortStatusDraft');
			}
			if ($status === 1) {
				return $langs->trans('BillShortStatusValidated');
			}
			if ($status === 2) {
				return $langs->trans('BillShortStatusPaid');
			}
			if ($status === 3) {
				return $langs->trans('BillShortStatusCanceled');
			}
			return (string) $value;
		}
	}

	// SLY Shipment Details: translate expedition status
	if ($datasetKey === 'shipment_details' && $field === 'fk_statut') {
		return slyShipmentStatusPlainLabel($value, $langs);
	}
	if ($datasetKey === 'so_inv_details' || $datasetKey === 'so_inv_receivable') {
		$langs->load('bills');
		if ($field === 'Paid') {
			$paid = (int) $value;
			if ($paid === 1) return $langs->trans('Yes');
			return $langs->trans('No');
		}
		if ($field === 'fk_statut') {
			$langs->load('bills');
			$status = (int) $value;
			if ($status === 0) {
				return $langs->trans('BillShortStatusDraft');
			}
			if ($status === 1) {
				return $langs->trans('BillShortStatusValidated');
			}
			if ($status === 2) {
				return $langs->trans('BillShortStatusPaid');
			}
			if ($status === 3) {
				return $langs->trans('BillShortStatusCanceled');
			}
			return (string) $value;
		}
	}
	if (in_array($field, array('Total', 'Price', 'SubTotal', 'Amount_Received', 'Pending_Payment', 'Fees_or_Loss', 'Amount_Paid'), true)) {
		if ($value === '' || !is_numeric($value)) {
			return (string) $value;
		}
		return price((float) $value, 0, $plainNumber ? 'none' : $langs, 1, -1, -1, '');
	}
	return (string) $value;
}

/**
 * Fetch all rows for a dataset.
 *
 * @param DoliDB $db
 * @param array $dataset
 * @param string $errorMessage
 * @return array
 */
function fetchDatasetRows($db, $dataset, &$errorMessage = '')
{
	$rows = array();
	$resql = $db->query($dataset['sql']);
	if (!$resql) {
		$errorMessage = $db->lasterror();
		return $rows;
	}
	while ($obj = $db->fetch_object($resql)) {
		$row = array();
		foreach ($dataset['columns'] as $column) {
			$field = $column['field'];
			$value = isset($obj->{$field}) ? $obj->{$field} : '';
			if ($value === null) {
				$value = '';
			}
			$row[$field] = (string) $value;
		}
		$rows[] = $row;
	}
	return $rows;
}

/**
 * Apply text filters to rows.
 *
 * @param array $rows
 * @param array $filters
 * @return array
 */
function filterDatasetRows($rows, $filters)
{
	if (empty($filters)) {
		return $rows;
	}
	$result = array();
	foreach ($rows as $row) {
		$matched = true;
		foreach ($filters as $field => $filterMeta) {
			$widgetType = isset($filterMeta['type']) ? $filterMeta['type'] : 'text';
			$haystack = isset($row[$field]) ? (string) $row[$field] : '';

			if ($widgetType === 'date') {
				$from = isset($filterMeta['from']) ? (string) $filterMeta['from'] : '';
				$to = isset($filterMeta['to']) ? (string) $filterMeta['to'] : '';
				if ($from === '' && $to === '') {
					continue;
				}
				$rowDate = substr($haystack, 0, 10);
				if ($rowDate === '' || $rowDate === '0000-00-00') {
					$matched = false;
					break;
				}
				if ($from !== '' && $rowDate < $from) {
					$matched = false;
					break;
				}
				if ($to !== '' && $rowDate > $to) {
					$matched = false;
					break;
				}
			} else {
				$needle = isset($filterMeta['value']) ? (string) $filterMeta['value'] : '';
				if ($needle === '') {
					continue;
				}
				if ($widgetType === 'currency') {
					if (strtoupper(trim($haystack)) !== strtoupper(trim($needle))) {
						$matched = false;
						break;
					}
					continue;
				}
				if ($widgetType === 'invoice_statut_codes') {
					$hay = trim($haystack);
					if ($hay === '') {
						$matched = false;
						break;
					}
					$codes = array();
					foreach (array_map('trim', explode(',', $hay)) as $c) {
						if ($c !== '') {
							$codes[] = (string) $c;
						}
					}
					if (!in_array((string) $needle, $codes, true)) {
						$matched = false;
						break;
					}
					continue;
				}
				if ($widgetType === 'status') {
					// Dolibarr core sometimes encodes a combined status as comma-separated values (e.g. "6,7").
					// Support matching any of the encoded statuses.
					if ($needle === '-2') {
						// Core for customer orders uses -2 to represent "Validated + Sent".
						$allowed = array('1', '2');
					} elseif ($needle === '-3') {
						// Core for customer orders uses -3 to represent "Validated + Sent + Delivered".
						$allowed = array('1', '2', '3');
					} elseif (strpos($needle, ',') !== false) {
						$allowed = array_filter(array_map('trim', explode(',', $needle)), static function ($v) { return $v !== ''; });
					} else {
						$allowed = array($needle);
					}
					if (!in_array($haystack, $allowed, true)) {
						$matched = false;
						break;
					} else {
						// no-op (kept for structure)
					}
				} elseif ($widgetType === 'bool') {
					if ((string) $haystack !== (string) $needle) {
						$matched = false;
						break;
					}
				} else {
					if (function_exists('mb_stripos')) {
						if (mb_stripos($haystack, $needle, 0, 'UTF-8') === false) {
							$matched = false;
							break;
						}
					} else {
						if (stripos($haystack, $needle) === false) {
							$matched = false;
							break;
						}
					}
				}
			}
		}
		if ($matched) {
			$result[] = $row;
		}
	}
	return $result;
}

/**
 * Output rows as XLS-compatible tab-separated text.
 *
 * @param string $filename
 * @param array $columns
 * @param array $rows
 * @param string $datasetKey Tab key for dataset-specific formatting (e.g. so_details).
 * @return void
 */
function outputDatasetCsv($filename, $columns, $rows, $datasetKey = '')
{
	global $langs;

	$filename = preg_replace('/\.(csv|xlsx?)$/i', '.xls', (string) $filename);
	if (substr($filename, -4) !== '.xls') {
		$filename .= '.xls';
	}

	header('Content-Type: application/vnd.ms-excel; charset=UTF-16LE');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Pragma: no-cache');
	header('Expires: 0');
	echo chr(255).chr(254);

	$headerRow = array();
	foreach ($columns as $column) {
		$headerRow[] = slyCsvEscape($column['label']);
	}
	echo iconv('UTF-8', 'UTF-16LE//IGNORE', implode("\t", $headerRow)."\n");

	foreach ($rows as $row) {
		$out = array();
		foreach ($columns as $column) {
			$field = $column['field'];
			$raw = isset($row[$field]) ? $row[$field] : '';
			$out[] = slyCsvEscape(slyFormatExportCellValue($datasetKey, $field, $raw, $langs, $row, true));
		}
		echo iconv('UTF-8', 'UTF-16LE//IGNORE', implode("\t", $out)."\n");
	}
}

/**
 * Guess filter widget type from column metadata.
 *
 * @param array $column
 * @return string
 */
function getFilterWidgetType($column)
{
	$field = strtolower($column['field']);
	$label = strtolower($column['label']);

	// Comma-separated fk_statut from linked customer invoices — dropdown BillShortStatus + membership filter.
	if ($field === 'so_cust_deposit_invoice_status' || $field === 'so_cust_standard_invoice_status') {
		return 'invoice_statut_codes';
	}

	if ($field === 'currency') {
		return 'currency';
	}

	if ($field === 'fk_statut' || strpos($label, 'status') !== false) {
		return 'status';
	}
	// "Amount Paid" label contains "paid" but is a money column, not Yes/No.
	if ($field === 'billed' || $field === 'paid' || strpos($label, 'billed') !== false) {
		return 'bool';
	}
	if (strpos($label, 'paid') !== false && strpos($field, 'amount_') !== 0) {
		return 'bool';
	}
	if (strpos($field, 'date') !== false || strpos($label, 'date') !== false || in_array($field, array('ata', 'atd', 'etd', 'eta'), true)) {
		return 'date';
	}

	return 'text';
}

/**
 * Normalize filter value from request.
 * Dolibarr select empty value may come as -1.
 *
 * @param string $value
 * @param string $widgetType
 * @return string
 */
function normalizeFilterValue($value, $widgetType)
{
	$value = trim((string) $value);
	// Dolibarr empty select may submit -1.
	// Normalize to empty string so it will be ignored by filter logic.
	// Dolibarr empty select may submit -1 for Yes/No controls only — never strip -1 for status (SO/shipment canceled).
	if ($value === '-1' && in_array($widgetType, array('bool', 'currency', 'invoice_statut_codes'), true)) {
		return '';
	}
	if ($widgetType === 'currency' && $value !== '') {
		return strtoupper($value);
	}
	return $value;
}

/**
 * Status dropdown options for liste_titre_filter (must match slyFormatExportCellValue / *PlainLabel helpers).
 *
 * @param string $activeTab Dataset/tab key
 * @param string $field     Column field name
 * @param Translate $langs
 * @return array|null       ['' => ''] + value => label, or null to use generic fallback in view
 */
function slyExportsGetStatusFilterOptions($activeTab, $field, $langs)
{
	if ($field !== 'fk_statut') {
		return null;
	}
	$opts = array('' => '');

	// Options mirror each dataset's status domain: statuses excluded by the
	// dataset SQL (draft/canceled/refused/abandoned) are not offered, so every
	// option matches rows that can actually appear in the table above.
	if ($activeTab === 'so_details') {
		$langs->load('orders');
		$opts['1'] = $langs->trans('StatusOrderValidated');
		$opts['2'] = $langs->trans('StatusOrderSentShort');
		$opts['3'] = $langs->trans('StatusOrderDelivered');
		return $opts;
	}

	if ($activeTab === 'so_deposit_invoice_missing') {
		$langs->load('orders');
		$opts['1'] = $langs->trans('StatusOrderValidated');
		$opts['2'] = $langs->trans('StatusOrderSentShort');
		return $opts;
	}

	if ($activeTab === 'so_inv_details' || $activeTab === 'so_inv_receivable') {
		$langs->load('bills');
		$opts['1'] = $langs->trans('BillShortStatusValidated');
		$opts['2'] = $langs->trans('BillShortStatusPaid');
		return $opts;
	}

	if ($activeTab === 'shipment_details') {
		$langs->load('sendings');
		$opts['1'] = $langs->trans('StatusSendingValidatedShort');
		$opts['2'] = $langs->trans('StatusSendingProcessedShort');
		$langs->load('slycustom@slycustom');
		$opts['3'] = $langs->trans('SLYSendingStatusInProgress');
		return $opts;
	}

	if ($activeTab === 'po_details') {
		$langs->load('orders');
		$opts['1'] = $langs->transnoentitiesnoconv('StatusSupplierOrderValidatedShort');
		$opts['2'] = $langs->transnoentitiesnoconv('StatusSupplierOrderApprovedShort');
		$opts['3'] = $langs->transnoentitiesnoconv('StatusSupplierOrderOnProcessShort');
		$opts['4'] = $langs->transnoentitiesnoconv('StatusSupplierOrderReceivedPartiallyShort');
		$opts['5'] = $langs->transnoentitiesnoconv('StatusSupplierOrderReceivedAllShort');
		return $opts;
	}

	if ($activeTab === 'po_deposit_invoice_missing') {
		$langs->load('orders');
		$opts['1'] = $langs->transnoentitiesnoconv('StatusSupplierOrderValidatedShort');
		$opts['2'] = $langs->transnoentitiesnoconv('StatusSupplierOrderApprovedShort');
		$opts['3'] = $langs->transnoentitiesnoconv('StatusSupplierOrderOnProcessShort');
		return $opts;
	}

	if ($activeTab === 'po_inv_details' || $activeTab === 'po_inv_payable') {
		$langs->load('bills');
		$opts['1'] = $langs->trans('BillShortStatusValidated');
		$opts['2'] = $langs->trans('BillShortStatusPaid');
		return $opts;
	}

	return null;
}

/**
 * Count sales orders (status validated or shipment in process only) whose payment terms imply a deposit but no validated deposit customer invoice (type 3, statut 1 or 2) is linked.
 *
 * @param DoliDB $db
 * @return int Negative value means the counter query failed.
 */
function slyExportsOrdersMissingDepositInvoiceCount($db)
{
	$prefix = MAIN_DB_PREFIX;
	$eCommande = getEntity('commande');
	$eInvoice = getEntity('invoice');
	$sql = "SELECT COUNT(DISTINCT c.rowid) AS nb
		FROM ".$prefix."commande c
		LEFT JOIN ".$prefix."c_payment_term cp ON cp.rowid = c.fk_cond_reglement
		WHERE c.entity IN (".$eCommande.")
			AND c.fk_statut IN (1, 2)
			AND COALESCE(NULLIF(TRIM(c.deposit_percent), ''), NULLIF(TRIM(cp.deposit_percent), '')) IS NOT NULL
			AND CAST(COALESCE(NULLIF(TRIM(c.deposit_percent), ''), NULLIF(TRIM(cp.deposit_percent), ''), '0') AS DECIMAL(10,4)) > 0
			AND NOT EXISTS (
				SELECT 1
				FROM ".$prefix."facture f
				INNER JOIN (
					SELECT fk_target AS fac_id, fk_source AS cmd_id
					FROM ".$prefix."element_element
					WHERE sourcetype = 'commande' AND targettype = 'facture'
					UNION ALL
					SELECT fk_source AS fac_id, fk_target AS cmd_id
					FROM ".$prefix."element_element
					WHERE sourcetype = 'facture' AND targettype = 'commande'
				) ee_so ON ee_so.fac_id = f.rowid
				WHERE f.entity IN (".$eInvoice.")
					AND f.type = 3
					AND f.fk_statut IN (1, 2)
					AND ee_so.cmd_id = c.rowid
			)";
	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	return $obj ? (int) $obj->nb : 0;
}

/**
 * Count purchase orders (status validated / accepted / order sent only) whose payment term requires a supplier deposit but no validated deposit supplier invoice is linked.
 *
 * @param DoliDB $db
 * @return int Negative value means the counter query failed.
 */
function slyExportsPoOrdersMissingDepositInvoiceCount($db)
{
	$prefix = MAIN_DB_PREFIX;
	$eSupplierOrder = getEntity('supplier_order');
	$eSupplierInvoice = getEntity('supplier_invoice');
	$sql = "SELECT COUNT(DISTINCT cf.rowid) AS nb
		FROM ".$prefix."commande_fournisseur cf
		LEFT JOIN ".$prefix."c_payment_term cp ON cp.rowid = cf.fk_cond_reglement
		WHERE cf.entity IN (".$eSupplierOrder.")
			AND cf.fk_statut IN (1, 2, 3)
			AND NULLIF(TRIM(cp.deposit_percent), '') IS NOT NULL
			AND CAST(NULLIF(TRIM(cp.deposit_percent), '0') AS DECIMAL(10,4)) > 0
			AND NOT EXISTS (
				SELECT 1
				FROM ".$prefix."facture_fourn ff
				INNER JOIN (
					SELECT ee1.fk_target AS inv_id, ee1.fk_source AS po_id
					FROM ".$prefix."element_element ee1
					WHERE (ee1.targettype = 'invoice_supplier' OR ee1.targettype = 'facture_fourn') AND ee1.sourcetype = 'order_supplier'
					UNION ALL
					SELECT ee1.fk_source AS inv_id, ee1.fk_target AS po_id
					FROM ".$prefix."element_element ee1
					WHERE (ee1.sourcetype = 'invoice_supplier' OR ee1.sourcetype = 'facture_fourn') AND ee1.targettype = 'order_supplier'
				) ee_inv ON ee_inv.inv_id = ff.rowid
				WHERE ff.entity IN (".$eSupplierInvoice.")
					AND ff.type = 3
					AND ff.fk_statut IN (1, 2)
					AND ee_inv.po_id = cf.rowid
			)";
	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	return $obj ? (int) $obj->nb : 0;
}

require_once __DIR__.'/tools.controller.php';
slyExportsControllerHandle($db, $langs, $conf);
