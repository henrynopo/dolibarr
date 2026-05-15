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
	switch ($status) {
		case 0:
			return $langs->transnoentitiesnoconv('StatusSendingDraftShort');
		case 1:
			return $langs->transnoentitiesnoconv('StatusSendingValidatedShort');
		case 2:
			return $langs->transnoentitiesnoconv('StatusSendingProcessedShort');
		case -1:
			return $langs->transnoentitiesnoconv('StatusSendingCanceledShort');
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
 * @return string
 */
function slyFormatExportCellValue($datasetKey, $field, $value, $langs, $row = null)
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
		return price((float) $value, 0, $langs, 1, -1, -1, '');
	}
	return (string) $value;
}

/**
 * Build dataset config (Multicompany: each SQL is restricted to visible entities via getEntity()).
 *
 * @return array
 */
function getSlyExportDatasets__legacy()
{
	// Legacy wrapper: moved to tools.datasets.php. Keeping the old code
	// temporarily to avoid risky large deletions while refactoring.
	return getSlyExportDatasets();
	/*
	$prefix = MAIN_DB_PREFIX;
	$eCommande = getEntity('commande');
	$eInvoice = getEntity('invoice');
	$eExpedition = getEntity('expedition');
	$eSupplierOrder = getEntity('supplier_order');
	$eSupplierInvoice = getEntity('supplier_invoice');

	return array(
		'so_details' => array(
			'label' => 'SLYExportSODetails',
			'filename' => 'SLY_SO_Details.csv',
			'sql' => "SELECT
				(SELECT TRIM(CONCAT(COALESCE(fu.firstname, ''), ' ', COALESCE(fu.lastname, '')))
					FROM ".$prefix."element_contact ec
					INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
					INNER JOIN ".$prefix."user fu ON fu.rowid = ec.fk_socpeople
					WHERE ec.element_id = c.rowid
						AND tc.element = 'commande'
						AND tc.source = 'internal'
						AND tc.code = 'SALESREPFOLL'
						AND tc.active = 1
					ORDER BY ec.rowid ASC
					LIMIT 1) AS SalesPerson,
				c.ref AS SO_No,
				COALESCE(
					NULLIF((SELECT GROUP_CONCAT(DISTINCT cf.ref_supplier ORDER BY cf.ref_supplier SEPARATOR ', ')
						FROM ".$prefix."element_element ee
						INNER JOIN ".$prefix."commande_fournisseur cf ON cf.rowid = ee.fk_source AND cf.entity IN (".$eSupplierOrder.")
						WHERE ee.fk_target = c.rowid AND ee.targettype = 'commande' AND ee.sourcetype = 'order_supplier'), ''),
					NULLIF((SELECT GROUP_CONCAT(DISTINCT cf.ref_supplier ORDER BY cf.ref_supplier SEPARATOR ', ')
						FROM ".$prefix."element_element ee
						INNER JOIN ".$prefix."commande_fournisseur cf ON cf.rowid = ee.fk_target AND cf.entity IN (".$eSupplierOrder.")
						WHERE ee.fk_source = c.rowid AND ee.sourcetype = 'commande' AND ee.targettype = 'order_supplier'), '')
				) AS Supplier_No,
				s.nom AS Customer,
				(SELECT TRIM(CONCAT(COALESCE(sp.firstname, ''), ' ', COALESCE(sp.lastname, '')))
					FROM ".$prefix."element_contact ec
					INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact AND tc.element = 'commande' AND tc.code = 'CUSTOMER' AND tc.source = 'external' AND tc.active = 1
					INNER JOIN ".$prefix."socpeople sp ON sp.rowid = ec.fk_socpeople
					WHERE ec.element_id = c.rowid ORDER BY ec.rowid ASC LIMIT 1) AS Cust_Contact,
				(SELECT TRIM(CONCAT(COALESCE(sp.firstname, ''), ' ', COALESCE(sp.lastname, '')))
					FROM ".$prefix."element_contact ec
					INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact AND tc.element = 'commande' AND tc.code = 'BILLING' AND tc.source = 'external' AND tc.active = 1
					INNER JOIN ".$prefix."socpeople sp ON sp.rowid = ec.fk_socpeople
					WHERE ec.element_id = c.rowid ORDER BY ec.rowid ASC LIMIT 1) AS Bill_To,
				c.date_commande AS Date_Order,
				c.fk_statut AS fk_statut,
				c.facture AS Billed,
				cex.ShipmentSchedule AS Shipment_Schedule,
				cp.code AS Payment_Term,
				ci.code AS Incoterm,
				c.location_incoterms AS Port_Arrival,
				cex.ConsigneeAppointedBy AS Consignee_Appointed_By,
				COALESCE(NULLIF(TRIM(c.multicurrency_code), ''), mc.code) AS Currency,
				COALESCE(c.multicurrency_total_ttc, c.total_ttc) AS Total,
				c.note_private AS Note_Private,
				c.note_public AS Note_Public,
				p.ref AS Product,
				cd.description AS description,
				cd.qty AS Qty,
				COALESCE(NULLIF(TRIM(cu.short_label), ''), NULLIF(TRIM(cu.label), ''), '') AS Unit,
				COALESCE(cd.multicurrency_subprice, cd.subprice) AS Price,
				COALESCE(cd.multicurrency_total_ttc, cd.total_ttc) AS SubTotal
			FROM ".$prefix."commande c
			LEFT JOIN ".$prefix."commandedet cd ON cd.fk_commande = c.rowid
			LEFT JOIN ".$prefix."societe s ON s.rowid = c.fk_soc
			LEFT JOIN ".$prefix."commande_extrafields cex ON cex.fk_object = c.rowid
			LEFT JOIN ".$prefix."user u ON u.rowid = c.fk_user_author
			LEFT JOIN ".$prefix."c_payment_term cp ON cp.rowid = c.fk_cond_reglement
			LEFT JOIN ".$prefix."c_incoterms ci ON ci.rowid = c.fk_incoterms
			LEFT JOIN ".$prefix."multicurrency mc ON mc.rowid = c.fk_multicurrency
			LEFT JOIN ".$prefix."product p ON p.rowid = cd.fk_product
			LEFT JOIN ".$prefix."c_units cu ON cu.rowid = cd.fk_unit
			WHERE c.entity IN (".$eCommande.")
			ORDER BY c.rowid DESC
			LIMIT 1000",
			'columns' => array(
				array('label' => 'Salesperson', 'field' => 'SalesPerson'),
				array('label' => 'SO No', 'field' => 'SO_No'),
				array('label' => 'Supplier No', 'field' => 'Supplier_No'),
				array('label' => 'Customer', 'field' => 'Customer'),
				array('label' => 'Cust_Contact', 'field' => 'Cust_Contact'),
				array('label' => 'Bill_To', 'field' => 'Bill_To'),
				array('label' => 'Date of Order', 'field' => 'Date_Order'),
				array('label' => 'Status', 'field' => 'fk_statut'),
				array('label' => 'Billed?', 'field' => 'Billed'),
				array('label' => 'Shipment Schedule', 'field' => 'Shipment_Schedule'),
				array('label' => 'Payment Term', 'field' => 'Payment_Term'),
				array('label' => 'Incoterm', 'field' => 'Incoterm'),
				array('label' => 'POA', 'field' => 'Port_Arrival'),
				array('label' => 'Consignee Appointed by', 'field' => 'Consignee_Appointed_By'),
				array('label' => 'Currency', 'field' => 'Currency'),
				array('label' => 'Total', 'field' => 'Total'),
				array('label' => 'Note_Private', 'field' => 'Note_Private'),
				array('label' => 'Note_Public', 'field' => 'Note_Public'),
				array('label' => 'Product', 'field' => 'Product'),
				array('label' => 'Description', 'field' => 'description'),
				array('label' => 'Qty', 'field' => 'Qty'),
				array('label' => 'Unit', 'field' => 'Unit'),
				array('label' => 'Price', 'field' => 'Price'),
				array('label' => 'Sub Total', 'field' => 'SubTotal'),
			),
		),
		'so_inv_details' => array(
			'label' => 'SLYExportSOInvoiceDetails',
			'filename' => 'SLY_SO_Inv_details.csv',
			'sql' => "SELECT
				(SELECT TRIM(CONCAT(COALESCE(fu.firstname, ''), ' ', COALESCE(fu.lastname, '')) )
					FROM ".$prefix."element_contact ec
					INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
					INNER JOIN ".$prefix."user fu ON fu.rowid = ec.fk_socpeople
					WHERE ec.element_id = c.rowid
						AND tc.element = 'commande'
						AND tc.source = 'internal'
						AND tc.code = 'SALESREPFOLL'
						AND tc.active = 1
					ORDER BY ec.rowid ASC
					LIMIT 1) AS Salesperson,
				c.ref AS SO_No,
				COALESCE(
					NULLIF((SELECT GROUP_CONCAT(DISTINCT cf.ref_supplier ORDER BY cf.ref_supplier SEPARATOR ', ')
						FROM ".$prefix."element_element ee
						INNER JOIN ".$prefix."commande_fournisseur cf ON cf.rowid = ee.fk_source AND cf.entity IN (".$eSupplierOrder.")
						WHERE ee.fk_target = c.rowid AND ee.targettype = 'commande' AND ee.sourcetype = 'order_supplier'), ''),
					NULLIF((SELECT GROUP_CONCAT(DISTINCT cf.ref_supplier ORDER BY cf.ref_supplier SEPARATOR ', ')
						FROM ".$prefix."element_element ee
						INNER JOIN ".$prefix."commande_fournisseur cf ON cf.rowid = ee.fk_target AND cf.entity IN (".$eSupplierOrder.")
						WHERE ee.fk_source = c.rowid AND ee.sourcetype = 'commande' AND ee.targettype = 'order_supplier'), '')
				) AS Supplier_No,
				MIN(se.ata) AS ATA,
				f.ref AS Inv_No,
				s.nom AS Customer,
				(SELECT TRIM(CONCAT(COALESCE(sp.firstname, ''), ' ', COALESCE(sp.lastname, '')))
					FROM ".$prefix."element_contact ec
					INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
					INNER JOIN ".$prefix."socpeople sp ON sp.rowid = ec.fk_socpeople
					WHERE ec.element_id = f.rowid
						AND tc.element = 'facture'
						AND tc.source = 'external'
						AND tc.code = 'BILLING'
						AND tc.active = 1
					ORDER BY ec.rowid ASC
					LIMIT 1) AS Billing_Company,
				f.datef AS Date_Inv,
				f.date_lim_reglement AS Date_Due,
				f.paye AS Paid,
				f.fk_statut AS fk_statut,
				MAX(p.datep) AS Date_Payment_Latest,
				COALESCE(NULLIF(f.multicurrency_code, ''), mc.code) AS Currency,
				COALESCE(SUM(pf.multicurrency_amount), 0) AS Amount_Received,
				(COALESCE(f.multicurrency_total_ttc, f.total_ttc) - COALESCE(SUM(pf.multicurrency_amount), 0)) AS Pending_Payment,
				CASE
					WHEN f.fk_statut = 2 AND f.paye = 1
					THEN (COALESCE(f.multicurrency_total_ttc, f.total_ttc) - COALESCE(SUM(pf.multicurrency_amount), 0))
					ELSE 0
				END AS Fees_or_Loss,
				f.note_private AS Note_Private,
				f.note_public AS Note_Public,
				pr.ref AS Product,
				fd.description AS Description,
				fd.qty AS Qty,
				COALESCE(NULLIF(TRIM(cu.short_label), ''), NULLIF(TRIM(cu.label), ''), '') AS Unit,
				COALESCE(fd.multicurrency_subprice, fd.subprice) AS Price,
				COALESCE(fd.multicurrency_total_ttc, fd.total_ttc) AS SubTotal,
				MAX(fx.datecustpay) AS Date_Cust_Pay,
				MAX(fx.datesuppdocdelivered) AS Date_Supp_Doc_Deliver,
				MAX(fx.suppcourrier) AS Supp_Courrier,
				MAX(fx.couriernumber) AS Supp_Courrier_No,
				MAX(fx.recipient) AS Recipient,
				MAX(fx.datesuppdocreceived) AS Date_Supp_Doc_Received,
				MAX(fx.datetr) AS Date_TR,
				MAX(fx.dateslydocdelivered) AS Date_SLY_Doc_Deliver,
				MAX(fx.slycourrier) AS SLY_Courrier,
				MAX(fx.slycourriernumber) AS SLY_Courrier_No,
				MAX(fx.remark) AS Remark
			FROM ".$prefix."facture f
			LEFT JOIN ".$prefix."facturedet fd ON fd.fk_facture = f.rowid
			LEFT JOIN ".$prefix."societe s ON s.rowid = f.fk_soc
			LEFT JOIN ".$prefix."multicurrency mc ON mc.rowid = f.fk_multicurrency
			LEFT JOIN ".$prefix."product pr ON pr.rowid = fd.fk_product
			LEFT JOIN ".$prefix."paiement_facture pf ON pf.fk_facture = f.rowid
			LEFT JOIN ".$prefix."paiement p ON p.rowid = pf.fk_paiement
			LEFT JOIN ".$prefix."element_element ee ON ee.targettype = 'facture' AND ee.fk_target = f.rowid AND ee.sourcetype = 'commande'
			LEFT JOIN ".$prefix."commande c ON c.rowid = ee.fk_source
			LEFT JOIN ".$prefix."c_units cu ON cu.rowid = fd.fk_unit
			LEFT JOIN ".$prefix."facture_extrafields fx ON fx.fk_object = f.rowid
			LEFT JOIN (
				SELECT ee1.fk_source AS cmd_id, ee1.fk_target AS ship_id
				FROM ".$prefix."element_element ee1
				WHERE ee1.sourcetype = 'commande' AND ee1.targettype = 'shipping'
				UNION ALL
				SELECT ee1.fk_target AS cmd_id, ee1.fk_source AS ship_id
				FROM ".$prefix."element_element ee1
				WHERE ee1.targettype = 'commande' AND ee1.sourcetype = 'shipping'
			) ee_cmd_ship ON ee_cmd_ship.cmd_id = c.rowid
			LEFT JOIN ".$prefix."expedition exp ON exp.rowid = ee_cmd_ship.ship_id
			LEFT JOIN ".$prefix."expedition_extrafields se ON se.fk_object = exp.rowid
			WHERE f.ref IS NOT NULL AND f.entity IN (".$eInvoice.")
			GROUP BY f.rowid, fd.rowid
			ORDER BY f.rowid DESC
			LIMIT 1500",
			'columns' => array(
				array('label' => 'Salesperson', 'field' => 'Salesperson'),
				array('label' => 'SO No', 'field' => 'SO_No'),
				array('label' => 'Supplier No', 'field' => 'Supplier_No'),
				array('label' => 'ATA', 'field' => 'ATA'),
				array('label' => 'Inv No', 'field' => 'Inv_No'),
				array('label' => 'Customer', 'field' => 'Customer'),
				array('label' => 'Bill To', 'field' => 'Billing_Company'),
				array('label' => 'Invoice Date', 'field' => 'Date_Inv'),
				array('label' => 'Due Date', 'field' => 'Date_Due'),
				array('label' => 'Paid', 'field' => 'Paid'),
				array('label' => 'Status', 'field' => 'fk_statut'),
				array('label' => 'Latest Payment', 'field' => 'Date_Payment_Latest'),
				array('label' => 'Currency', 'field' => 'Currency'),
				array('label' => 'Amount Received', 'field' => 'Amount_Received'),
				array('label' => 'Pending Amount', 'field' => 'Pending_Payment'),
				array('label' => 'Fee', 'field' => 'Fees_or_Loss'),
				array('label' => 'Note_Private', 'field' => 'Note_Private'),
				array('label' => 'Note_Public', 'field' => 'Note_Public'),
				array('label' => 'Product', 'field' => 'Product'),
				array('label' => 'Description', 'field' => 'Description'),
				array('label' => 'Qty', 'field' => 'Qty'),
				array('label' => 'Unit', 'field' => 'Unit'),
				array('label' => 'Price', 'field' => 'Price'),
				array('label' => 'Sub Total', 'field' => 'SubTotal'),
				array('label' => 'Date_Cust_Pay', 'field' => 'Date_Cust_Pay'),
				array('label' => 'Date_Supp_Doc_Deliver', 'field' => 'Date_Supp_Doc_Deliver'),
				array('label' => 'Supp_Courrier', 'field' => 'Supp_Courrier'),
				array('label' => 'Supp_Courrier_No', 'field' => 'Supp_Courrier_No'),
				array('label' => 'Recipient', 'field' => 'Recipient'),
				array('label' => 'Date_Supp_Doc_Received', 'field' => 'Date_Supp_Doc_Received'),
				array('label' => 'Date_TR', 'field' => 'Date_TR'),
				array('label' => 'Date_SLY_Doc_Deliver', 'field' => 'Date_SLY_Doc_Deliver'),
				array('label' => 'SLY_Courrier', 'field' => 'SLY_Courrier'),
				array('label' => 'SLY_Courrier_No', 'field' => 'SLY_Courrier_No'),
				array('label' => 'Remark', 'field' => 'Remark'),
			),
		),
		'shipment_details' => array(
			'label' => 'SLYExportShipmentDetails',
			'filename' => 'SLY_Shipment.csv',
			'sql' => "SELECT
				(SELECT TRIM(CONCAT(COALESCE(fu.firstname, ''), ' ', COALESCE(fu.lastname, '')))
					FROM ".$prefix."element_contact ec
					INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
					INNER JOIN ".$prefix."user fu ON fu.rowid = ec.fk_socpeople
					WHERE ec.element_id = c.rowid
						AND tc.element = 'commande'
						AND tc.source = 'internal'
						AND tc.code = 'SALESREPFOLL'
						AND tc.active = 1
					ORDER BY ec.rowid ASC
					LIMIT 1) AS SalesPerson,
				c.ref AS SO_No,
				COALESCE(
					NULLIF((SELECT GROUP_CONCAT(DISTINCT cf.ref_supplier ORDER BY cf.ref_supplier SEPARATOR ', ')
						FROM ".$prefix."element_element ee
						INNER JOIN ".$prefix."commande_fournisseur cf ON cf.rowid = ee.fk_source AND cf.entity IN (".$eSupplierOrder.")
						WHERE ee.fk_target = c.rowid AND ee.targettype = 'commande' AND ee.sourcetype = 'order_supplier'), ''),
					NULLIF((SELECT GROUP_CONCAT(DISTINCT cf.ref_supplier ORDER BY cf.ref_supplier SEPARATOR ', ')
						FROM ".$prefix."element_element ee
						INNER JOIN ".$prefix."commande_fournisseur cf ON cf.rowid = ee.fk_target AND cf.entity IN (".$eSupplierOrder.")
						WHERE ee.fk_source = c.rowid AND ee.sourcetype = 'commande' AND ee.targettype = 'order_supplier'), '')
				) AS Supplier_No,
				e.ref AS Shipment_No,
				s.nom AS Customer,
				e.fk_statut AS fk_statut,
				e.billed AS billed,
				sex.pol AS POL,
				sex.atd AS ATD,
				sex.pod AS POD,
				sex.ata AS ATA,
				sex.etd AS ETD,
				sex.eta AS ETA,
				'' AS Shipment_Company,
				e.tracking_number AS Container_No,
				sex.blno AS BL_No,
				sex.hcno AS HC_No,
				e.note_private AS Note_Private,
				e.note_public AS Note_Public,
				p.ref AS Product,
				COALESCE(NULLIF(TRIM(cu.short_label), ''), NULLIF(TRIM(cu.label), ''), '') AS Unit,
				ed.qty AS Net_Weight,
				COALESCE(edex.grossweight, ed.weight) AS Gross_Weight,
				COALESCE(edex.quantitycarton, ed.qty) AS Qty_Cartons
			FROM ".$prefix."expedition e
			LEFT JOIN ".$prefix."expeditiondet ed ON ed.fk_expedition = e.rowid
			LEFT JOIN ".$prefix."commandedet cd ON cd.rowid = ed.fk_origin_line
			LEFT JOIN ".$prefix."commande c ON c.rowid = cd.fk_commande
			LEFT JOIN ".$prefix."societe s ON s.rowid = c.fk_soc
			LEFT JOIN ".$prefix."product p ON p.rowid = cd.fk_product
			LEFT JOIN ".$prefix."c_units cu ON cu.rowid = cd.fk_unit
			LEFT JOIN ".$prefix."expedition_extrafields sex ON sex.fk_object = e.rowid
			LEFT JOIN ".$prefix."expeditiondet_extrafields edex ON edex.fk_object = ed.rowid
			WHERE e.entity IN (".$eExpedition.")
			ORDER BY e.rowid DESC
			LIMIT 1500",
			'columns' => array(
				array('label' => 'Salesperson', 'field' => 'SalesPerson'),
				array('label' => 'SO No', 'field' => 'SO_No'),
				array('label' => 'Supplier No', 'field' => 'Supplier_No'),
				array('label' => 'Shipment No', 'field' => 'Shipment_No'),
				array('label' => 'Customer', 'field' => 'Customer'),
				array('label' => 'Status', 'field' => 'fk_statut'),
				array('label' => 'Billed?', 'field' => 'billed'),
				array('label' => 'POL', 'field' => 'POL'),
				array('label' => 'ATD', 'field' => 'ATD'),
				array('label' => 'POD', 'field' => 'POD'),
				array('label' => 'ATA', 'field' => 'ATA'),
				array('label' => 'ETD', 'field' => 'ETD'),
				array('label' => 'ETA', 'field' => 'ETA'),
				array('label' => 'Shipment Company', 'field' => 'Shipment_Company'),
				array('label' => 'Container No', 'field' => 'Container_No'),
				array('label' => 'BL_No', 'field' => 'BL_No'),
				array('label' => 'HC_No', 'field' => 'HC_No'),
				array('label' => 'Note_Private', 'field' => 'Note_Private'),
				array('label' => 'Note_Public', 'field' => 'Note_Public'),
				array('label' => 'Product', 'field' => 'Product'),
				array('label' => 'Unit', 'field' => 'Unit'),
				array('label' => 'Net Weight', 'field' => 'Net_Weight'),
				array('label' => 'Gross Weight', 'field' => 'Gross_Weight'),
				array('label' => 'Cartons', 'field' => 'Qty_Cartons'),
			),
		),
		'po_details' => array(
			'label' => 'SLYExportPODetails',
			'filename' => 'SLY_PO_Details.csv',
			'sql' => "SELECT
				CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')) AS SalesPerson,
				'' AS SO_No,
				cf.ref AS PO_No,
				s.code_fournisseur AS Supplier_No,
				s.nom AS Supplier,
				cf.date_commande AS Date_Order,
				cf.fk_statut AS fk_statut,
				cf.billed AS Billed,
				cp.code AS Payment_Term,
				ci.code AS Incoterm,
				'' AS Port_Arrival,
				mc.code AS Currency,
				cf.total_ttc AS Total,
				cf.note_private AS Note_Private,
				cf.note_public AS Note_Public,
				p.ref AS Product,
				cfd.description AS description,
				cfd.qty AS Qty,
				cfd.product_type AS Unit,
				cfd.subprice AS Price,
				cfd.total_ttc AS SubTotal
			FROM ".$prefix."commande_fournisseur cf
			LEFT JOIN ".$prefix."commande_fournisseurdet cfd ON cfd.fk_commande = cf.rowid
			LEFT JOIN ".$prefix."societe s ON s.rowid = cf.fk_soc
			LEFT JOIN ".$prefix."user u ON u.rowid = cf.fk_user_author
			LEFT JOIN ".$prefix."c_payment_term cp ON cp.rowid = cf.fk_cond_reglement
			LEFT JOIN ".$prefix."c_incoterms ci ON ci.rowid = cf.fk_incoterms
			LEFT JOIN ".$prefix."multicurrency mc ON mc.rowid = cf.fk_multicurrency
			LEFT JOIN ".$prefix."product p ON p.rowid = cfd.fk_product
			WHERE cf.entity IN (".$eSupplierOrder.")
			ORDER BY cf.rowid DESC
			LIMIT 1000",
			'columns' => array(
				array('label' => 'Purchase Person', 'field' => 'SalesPerson'),
				array('label' => 'SO No', 'field' => 'SO_No'),
				array('label' => 'PO No', 'field' => 'PO_No'),
				array('label' => 'Supplier_No', 'field' => 'Supplier_No'),
				array('label' => 'Supplier', 'field' => 'Supplier'),
				array('label' => 'Date of Order', 'field' => 'Date_Order'),
				array('label' => 'Status', 'field' => 'fk_statut'),
				array('label' => 'Billed?', 'field' => 'Billed'),
				array('label' => 'Payment Term', 'field' => 'Payment_Term'),
				array('label' => 'Incoterm', 'field' => 'Incoterm'),
				array('label' => 'POA', 'field' => 'Port_Arrival'),
				array('label' => 'Currency', 'field' => 'Currency'),
				array('label' => 'Total', 'field' => 'Total'),
				array('label' => 'Note_Private', 'field' => 'Note_Private'),
				array('label' => 'Note_Public', 'field' => 'Note_Public'),
				array('label' => 'Product', 'field' => 'Product'),
				array('label' => 'Description', 'field' => 'description'),
				array('label' => 'Qty', 'field' => 'Qty'),
				array('label' => 'Unit', 'field' => 'Unit'),
				array('label' => 'Price', 'field' => 'Price'),
				array('label' => 'Sub Total', 'field' => 'SubTotal'),
			),
		),
		'po_inv_details' => array(
			'label' => 'SLYExportPOInvoiceDetails',
			'filename' => 'SLY_PO_Inv_details.csv',
			'sql' => "SELECT
				'' AS SO_No,
				cf.ref AS PO_No,
				s.code_fournisseur AS Supplier_No,
				MIN(se.ata) AS ATA,
				ff.ref AS Inv_No,
				s.nom AS Supplier,
				ff.datef AS Date_Inv,
				ff.date_lim_reglement AS Date_Due,
				ff.paye AS Paid,
				ff.fk_statut AS fk_statut,
				MAX(p.datep) AS Date_Payment_Latest,
				mc.code AS Currency,
				COALESCE(SUM(pff.amount), 0) AS Amount_Paid,
				(ff.total_ttc - COALESCE(SUM(pff.amount), 0)) AS Pending_Payment,
				0 AS Fees_or_Loss,
				ff.note_private AS Note_Private,
				ff.note_public AS Note_Public,
				pr.ref AS Product,
				ffd.description AS Description,
				ffd.qty AS Qty,
				ffd.product_type AS Unit,
				ffd.subprice AS Price,
				ffd.total_ttc AS SubTotal
			FROM ".$prefix."facture_fourn ff
			LEFT JOIN ".$prefix."facture_fourn_det ffd ON ffd.fk_facture_fourn = ff.rowid
			LEFT JOIN ".$prefix."societe s ON s.rowid = ff.fk_soc
			LEFT JOIN ".$prefix."multicurrency mc ON mc.rowid = ff.fk_multicurrency
			LEFT JOIN ".$prefix."product pr ON pr.rowid = ffd.fk_product
			LEFT JOIN ".$prefix."paiementfourn_facturefourn pff ON pff.fk_facturefourn = ff.rowid
			LEFT JOIN ".$prefix."paiementfourn pf ON pf.rowid = pff.fk_paiementfourn
			LEFT JOIN ".$prefix."paiement p ON p.rowid = pf.rowid
			LEFT JOIN (
				SELECT ee1.fk_target AS inv_id, ee1.fk_source AS po_id
				FROM ".$prefix."element_element ee1
				WHERE (ee1.targettype = 'invoice_supplier' OR ee1.targettype = 'facture_fourn') AND ee1.sourcetype = 'order_supplier'
				UNION ALL
				SELECT ee1.fk_source AS inv_id, ee1.fk_target AS po_id
				FROM ".$prefix."element_element ee1
				WHERE (ee1.sourcetype = 'invoice_supplier' OR ee1.sourcetype = 'facture_fourn') AND ee1.targettype = 'order_supplier'
			) ee_inv_po ON ee_inv_po.inv_id = ff.rowid
			LEFT JOIN ".$prefix."commande_fournisseur cf ON cf.rowid = ee_inv_po.po_id
			LEFT JOIN (
				SELECT ee2.fk_source AS po_id, ee2.fk_target AS cmd_id
				FROM ".$prefix."element_element ee2
				WHERE ee2.sourcetype = 'order_supplier' AND ee2.targettype = 'commande'
				UNION ALL
				SELECT ee2.fk_target AS po_id, ee2.fk_source AS cmd_id
				FROM ".$prefix."element_element ee2
				WHERE ee2.targettype = 'order_supplier' AND ee2.sourcetype = 'commande'
			) ee_po_cmd ON ee_po_cmd.po_id = cf.rowid
			LEFT JOIN (
				SELECT ee3.fk_source AS cmd_id, ee3.fk_target AS ship_id
				FROM ".$prefix."element_element ee3
				WHERE ee3.sourcetype = 'commande' AND ee3.targettype = 'shipping'
				UNION ALL
				SELECT ee3.fk_target AS cmd_id, ee3.fk_source AS ship_id
				FROM ".$prefix."element_element ee3
				WHERE ee3.targettype = 'commande' AND ee3.sourcetype = 'shipping'
			) ee_cmd_ship ON ee_cmd_ship.cmd_id = ee_po_cmd.cmd_id
			LEFT JOIN ".$prefix."expedition exp ON exp.rowid = ee_cmd_ship.ship_id
			LEFT JOIN ".$prefix."expedition_extrafields se ON se.fk_object = exp.rowid
			WHERE ff.ref IS NOT NULL AND ff.entity IN (".$eSupplierInvoice.")
			GROUP BY ff.rowid, ffd.rowid
			ORDER BY ff.rowid DESC
			LIMIT 1500",
			'columns' => array(
				array('label' => 'SO No', 'field' => 'SO_No'),
				array('label' => 'PO No', 'field' => 'PO_No'),
				array('label' => 'Supplier_No', 'field' => 'Supplier_No'),
				array('label' => 'ATA', 'field' => 'ATA'),
				array('label' => 'Inv No', 'field' => 'Inv_No'),
				array('label' => 'Supplier', 'field' => 'Supplier'),
				array('label' => 'Invoice Date', 'field' => 'Date_Inv'),
				array('label' => 'Due Date', 'field' => 'Date_Due'),
				array('label' => 'Paid', 'field' => 'Paid'),
				array('label' => 'Status', 'field' => 'fk_statut'),
				array('label' => 'Latest Payment', 'field' => 'Date_Payment_Latest'),
				array('label' => 'Currency', 'field' => 'Currency'),
				array('label' => 'Amount Paid', 'field' => 'Amount_Paid'),
				array('label' => 'Pending Amount', 'field' => 'Pending_Payment'),
				array('label' => 'Fee', 'field' => 'Fees_or_Loss'),
				array('label' => 'Note_Private', 'field' => 'Note_Private'),
				array('label' => 'Note_Public', 'field' => 'Note_Public'),
				array('label' => 'Product', 'field' => 'Product'),
				array('label' => 'Description', 'field' => 'Description'),
				array('label' => 'Qty', 'field' => 'Qty'),
				array('label' => 'Unit', 'field' => 'Unit'),
				array('label' => 'Price', 'field' => 'Price'),
				array('label' => 'Sub Total', 'field' => 'SubTotal'),
			),
		),
	);
	*/
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
			$out[] = slyCsvEscape(slyFormatExportCellValue($datasetKey, $field, $raw, $langs, $row));
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

	if ($activeTab === 'so_details' || $activeTab === 'so_deposit_invoice_missing') {
		$langs->load('orders');
		$opts['-1'] = $langs->trans('StatusOrderCanceledShort');
		$opts['0'] = $langs->trans('StatusOrderDraftShort');
		$opts['1'] = $langs->trans('StatusOrderValidated');
		$opts['2'] = $langs->trans('StatusOrderSentShort');
		$opts['3'] = $langs->trans('StatusOrderDelivered');
		return $opts;
	}

	if ($activeTab === 'so_inv_details' || $activeTab === 'so_inv_receivable') {
		$langs->load('bills');
		$opts['0'] = $langs->trans('BillShortStatusDraft');
		$opts['1'] = $langs->trans('BillShortStatusValidated');
		$opts['2'] = $langs->trans('BillShortStatusPaid');
		$opts['3'] = $langs->trans('BillShortStatusCanceled');
		return $opts;
	}

	if ($activeTab === 'shipment_details') {
		$langs->load('sendings');
		$opts['-1'] = $langs->trans('StatusSendingCanceledShort');
		$opts['0'] = $langs->trans('StatusSendingDraftShort');
		$opts['1'] = $langs->trans('StatusSendingValidatedShort');
		$opts['2'] = $langs->trans('StatusSendingProcessedShort');
		return $opts;
	}

	if ($activeTab === 'po_details' || $activeTab === 'po_deposit_invoice_missing') {
		$langs->load('orders');
		$opts['0'] = $langs->transnoentitiesnoconv('StatusSupplierOrderDraftShort');
		$opts['1'] = $langs->transnoentitiesnoconv('StatusSupplierOrderValidatedShort');
		$opts['2'] = $langs->transnoentitiesnoconv('StatusSupplierOrderApprovedShort');
		$opts['3'] = $langs->transnoentitiesnoconv('StatusSupplierOrderOnProcessShort');
		$opts['4'] = $langs->transnoentitiesnoconv('StatusSupplierOrderReceivedPartiallyShort');
		$opts['5'] = $langs->transnoentitiesnoconv('StatusSupplierOrderReceivedAllShort');
		$opts['6'] = $langs->transnoentitiesnoconv('StatusSupplierOrderCanceledShort');
		$opts['7'] = $langs->transnoentitiesnoconv('StatusSupplierOrderCanceledShort');
		$opts['9'] = $langs->transnoentitiesnoconv('StatusSupplierOrderRefusedShort');
		return $opts;
	}

	if ($activeTab === 'po_inv_details' || $activeTab === 'po_inv_payable') {
		$langs->load('bills');
		$opts['0'] = $langs->trans('BillShortStatusDraft');
		$opts['1'] = $langs->trans('BillShortStatusValidated');
		$opts['2'] = $langs->trans('BillShortStatusPaid');
		$opts['3'] = $langs->trans('BillShortStatusCanceled');
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
