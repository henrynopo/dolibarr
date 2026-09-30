<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Export datasets (SQL + columns) definition.
 *
 * Kept in a separate file to reduce the size/complexity of tools.php.
 *
 * @return array
 */
function getSlyExportDatasets()
{
	$prefix = MAIN_DB_PREFIX;
	$eCommande = getEntity('commande');
	$eInvoice = getEntity('invoice');
	$eExpedition = getEntity('expedition');
	$eSupplierOrder = getEntity('supplier_order');
	$eSupplierInvoice = getEntity('supplier_invoice');

	// so_inv_details extrafields: support both
	// 1) attribute code style (lowercase, no underscores)
	// 2) legacy/camelcase column names (created by older patch iterations)
	global $db;
	$fxExisting = array();
	$fxTable = $prefix.'facture_extrafields';
	$fxCandidates = array(
		'datecustpay', 'Date_Cust_Pay',
		'datesuppdocdeliver', 'Date_Supp_Doc_Deliver',
		'datesuppdocdelivered', 'Date_Supp_Doc_Deliver',
		'suppcourrier', 'Supp_Courrier',
		'courriernumber', 'couriernumber', 'Supp_Courrier_No',
		'recipient', 'Recipient',
		'datesuppdocreceived', 'Date_Supp_Doc_Received',
		'datetr', 'Date_TR',
		'dateslydocdeliver', 'Date_SLY_Doc_Deliver',
		'dateslydocdelivered', 'Date_SLY_Doc_Deliver',
		'slycourrier', 'SLY_Courrier',
		'slycourriernumber', 'SLY_Courrier_No',
		'remark', 'Remark',
	);
	if (is_object($db)) {
		$in = implode(',', array_map(static function ($c) { return "'".addslashes((string) $c)."'"; }, $fxCandidates));
		$sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = '".addslashes((string) $fxTable)."'
				AND COLUMN_NAME IN (".$in.")";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				if (!empty($obj->COLUMN_NAME)) {
					$col = (string) $obj->COLUMN_NAME;
					// Store mapping lower(column_name) => actual column_name
					$fxExisting[strtolower($col)] = $col;
				}
			}
		}
	}
	$pickFxCol = static function (array $candidates) use ($fxExisting) {
		foreach ($candidates as $c) {
			$lower = strtolower((string) $c);
			if (isset($fxExisting[$lower])) {
				return $fxExisting[$lower];
			}
		}
		return '';
	};
	$fxColDateCustPay = $pickFxCol(array('datecustpay', 'Date_Cust_Pay'));
	$fxColDateSuppDocDeliver = $pickFxCol(array('datesuppdocdeliver', 'datesuppdocdelivered', 'Date_Supp_Doc_Deliver'));
	$fxColSuppCourrier = $pickFxCol(array('suppcourrier', 'Supp_Courrier'));
	$fxColCourierNumber = $pickFxCol(array('courriernumber', 'couriernumber', 'Supp_Courrier_No'));
	$fxColRecipient = $pickFxCol(array('recipient', 'Recipient'));
	$fxColDateSuppDocReceived = $pickFxCol(array('datesuppdocreceived', 'Date_Supp_Doc_Received'));
	$fxColDateTR = $pickFxCol(array('datetr', 'Date_TR'));
	$fxColDateSLYDocDeliver = $pickFxCol(array('dateslydocdeliver', 'dateslydocdelivered', 'Date_SLY_Doc_Deliver'));
	$fxColSLYCourrier = $pickFxCol(array('slycourrier', 'SLY_Courrier'));
	$fxColSLYCourrierNo = $pickFxCol(array('slycourriernumber', 'SLY_Courrier_No'));
	$fxColRemark = $pickFxCol(array('remark', 'Remark'));

	$fxExprDateCustPay = $fxColDateCustPay !== '' ? 'MAX(fx.'.$fxColDateCustPay.') AS Date_Cust_Pay,' : 'NULL AS Date_Cust_Pay,';
	$fxExprDateSuppDocDeliver = $fxColDateSuppDocDeliver !== '' ? 'MAX(fx.'.$fxColDateSuppDocDeliver.') AS Date_Supp_Doc_Deliver,' : 'NULL AS Date_Supp_Doc_Deliver,';
	$fxExprSuppCourrier = $fxColSuppCourrier !== ''
		? 'MAX(CASE WHEN fx.'.$fxColSuppCourrier.' IS NULL OR fx.'.$fxColSuppCourrier.' = 0 THEN \'\' ELSE (SELECT cm.libelle FROM '.$prefix.'c_shipment_mode cm WHERE cm.rowid = fx.'.$fxColSuppCourrier.' AND cm.active = 1 LIMIT 1) END) AS Supp_Courrier,'
		: 'NULL AS Supp_Courrier,';
$fxExprCourierNumber = $fxColCourierNumber !== ''
	? 'MAX(CASE WHEN fx.'.$fxColCourierNumber.' IS NULL OR fx.'.$fxColCourierNumber.' = \'\' OR fx.'.$fxColCourierNumber.' = \'0\' THEN \'\' ELSE fx.'.$fxColCourierNumber.' END) AS Supp_Courrier_No,'
	: 'NULL AS Supp_Courrier_No,';
	$fxExprRecipient = $fxColRecipient !== ''
		? 'MAX(CASE WHEN fx.'.$fxColRecipient.' IS NULL OR fx.'.$fxColRecipient.' = 0 THEN \'\' ELSE (SELECT soc.nom FROM '.$prefix.'societe soc WHERE soc.rowid = fx.'.$fxColRecipient.' LIMIT 1) END) AS Recipient,'
		: 'NULL AS Recipient,';
	$fxExprDateSuppDocReceived = $fxColDateSuppDocReceived !== '' ? 'MAX(fx.'.$fxColDateSuppDocReceived.') AS Date_Supp_Doc_Received,' : 'NULL AS Date_Supp_Doc_Received,';
	$fxExprDateTR = $fxColDateTR !== '' ? 'MAX(fx.'.$fxColDateTR.') AS Date_TR,' : 'NULL AS Date_TR,';
	$fxExprDateSLYDocDeliver = $fxColDateSLYDocDeliver !== '' ? 'MAX(fx.'.$fxColDateSLYDocDeliver.') AS Date_SLY_Doc_Deliver,' : 'NULL AS Date_SLY_Doc_Deliver,';
	$fxExprSLYCourrier = $fxColSLYCourrier !== ''
		? 'MAX(CASE WHEN fx.'.$fxColSLYCourrier.' IS NULL OR fx.'.$fxColSLYCourrier.' = 0 THEN \'\' ELSE (SELECT cm.libelle FROM '.$prefix.'c_shipment_mode cm WHERE cm.rowid = fx.'.$fxColSLYCourrier.' AND cm.active = 1 LIMIT 1) END) AS SLY_Courrier,'
		: 'NULL AS SLY_Courrier,';
$fxExprSLYCourrierNo = $fxColSLYCourrierNo !== ''
	? 'MAX(CASE WHEN fx.'.$fxColSLYCourrierNo.' IS NULL OR fx.'.$fxColSLYCourrierNo.' = \'\' OR fx.'.$fxColSLYCourrierNo.' = \'0\' THEN \'\' ELSE fx.'.$fxColSLYCourrierNo.' END) AS SLY_Courrier_No,'
	: 'NULL AS SLY_Courrier_No,';
	$fxExprRemark = $fxColRemark !== '' ? 'MAX(fx.'.$fxColRemark.') AS Remark' : 'NULL AS Remark';

	// shipment_details extrafields: expedition_extrafields + expeditiondet_extrafields
	$sexExisting = array();
	$sexTable = $prefix.'expedition_extrafields';
	$sexCandidates = array('pol', 'atd', 'pod', 'ata', 'etd', 'eta', 'blno', 'hcno');
	$edexExisting = array();
	$edexTable = $prefix.'expeditiondet_extrafields';
	$edexCandidates = array('grossweight', 'quantitycarton');

	// shipment_details: Shipment_Company (shipping method short label if available)
	$smExisting = array();
	$smTable = $prefix.'c_shipment_mode';
	$smCandidates = array('short_label', 'Short_Label', 'libelle', 'Libelle');
	if (is_object($db)) {
		$inSex = implode(',', array_map(static function ($c) { return "'".addslashes((string) $c)."'"; }, $sexCandidates));
		$sqlSex = "SELECT COLUMN_NAME FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = '".addslashes((string) $sexTable)."'
				AND COLUMN_NAME IN (".$inSex.")";
		$resqlSex = $db->query($sqlSex);
		if ($resqlSex) {
			while ($obj = $db->fetch_object($resqlSex)) {
				if (!empty($obj->COLUMN_NAME)) {
					$col = (string) $obj->COLUMN_NAME;
					$sexExisting[strtolower($col)] = $col;
				}
			}
		}

		$inEdex = implode(',', array_map(static function ($c) { return "'".addslashes((string) $c)."'"; }, $edexCandidates));
		$sqlEdex = "SELECT COLUMN_NAME FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = '".addslashes((string) $edexTable)."'
				AND COLUMN_NAME IN (".$inEdex.")";
		$resqlEdex = $db->query($sqlEdex);
		if ($resqlEdex) {
			while ($obj = $db->fetch_object($resqlEdex)) {
				if (!empty($obj->COLUMN_NAME)) {
					$col = (string) $obj->COLUMN_NAME;
					$edexExisting[strtolower($col)] = $col;
				}
			}
		}

		$inSm = implode(',', array_map(static function ($c) { return "'".addslashes((string) $c)."'"; }, $smCandidates));
		$sqlSm = "SELECT COLUMN_NAME FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = '".addslashes((string) $smTable)."'
				AND COLUMN_NAME IN (".$inSm.")";
		$resqlSm = $db->query($sqlSm);
		if ($resqlSm) {
			while ($obj = $db->fetch_object($resqlSm)) {
				if (!empty($obj->COLUMN_NAME)) {
					$col = (string) $obj->COLUMN_NAME;
					$smExisting[strtolower($col)] = $col;
				}
			}
		}
	}

	$pickSexCol = static function (array $candidates) use ($sexExisting) {
		foreach ($candidates as $c) {
			$lower = strtolower((string) $c);
			if (isset($sexExisting[$lower])) return $sexExisting[$lower];
		}
		return '';
	};
	$pickEdexCol = static function (array $candidates) use ($edexExisting) {
		foreach ($candidates as $c) {
			$lower = strtolower((string) $c);
			if (isset($edexExisting[$lower])) return $edexExisting[$lower];
		}
		return '';
	};

	$pickSmCol = static function (array $candidates) use ($smExisting) {
		foreach ($candidates as $c) {
			$lower = strtolower((string) $c);
			if (isset($smExisting[$lower])) return $smExisting[$lower];
		}
		return '';
	};

	$smShortLabelCol = $pickSmCol(array('short_label', 'Short_Label'));
	$smLibelleCol = $pickSmCol(array('libelle', 'Libelle'));
	$shipCompanyExpr = "'' AS Shipment_Company,";
	if ($smShortLabelCol !== '') {
		$shipCompanyExpr = 'COALESCE((SELECT sm.'.$smShortLabelCol.' FROM '.$prefix.'c_shipment_mode sm WHERE sm.rowid = e.fk_shipping_method AND sm.active = 1 LIMIT 1), \'\') AS Shipment_Company,';
	} elseif ($smLibelleCol !== '') {
		$shipCompanyExpr = 'COALESCE((SELECT sm.'.$smLibelleCol.' FROM '.$prefix.'c_shipment_mode sm WHERE sm.rowid = e.fk_shipping_method AND sm.active = 1 LIMIT 1), \'\') AS Shipment_Company,';
	}

	$sexPol = $pickSexCol(array('pol'));
	$sexAtd = $pickSexCol(array('atd'));
	$sexPod = $pickSexCol(array('pod'));
	$sexAta = $pickSexCol(array('ata'));
	$sexEtD = $pickSexCol(array('etd'));
	$sexEta = $pickSexCol(array('eta'));
	$sexBlno = $pickSexCol(array('blno'));
	$sexHcno = $pickSexCol(array('hcno'));

	$edexGross = $pickEdexCol(array('grossweight'));
	$edexQtyCarton = $pickEdexCol(array('quantitycarton'));

	$sexExprPol = $sexPol !== '' ? 'sex.'.$sexPol.' AS POL' : "'' AS POL";
	$sexExprAtd = $sexAtd !== '' ? 'sex.'.$sexAtd.' AS ATD' : "'' AS ATD";
	$sexExprPod = $sexPod !== '' ? 'sex.'.$sexPod.' AS POD' : "'' AS POD";
	$sexExprAta = $sexAta !== '' ? 'sex.'.$sexAta.' AS ATA' : "'' AS ATA";
	$sexExprEtD = $sexEtD !== '' ? 'sex.'.$sexEtD.' AS ETD' : "'' AS ETD";
	$sexExprEta = $sexEta !== '' ? 'sex.'.$sexEta.' AS ETA' : "'' AS ETA";
	$sexExprBlno = $sexBlno !== '' ? 'sex.'.$sexBlno.' AS BL_No' : "'' AS BL_No";
	$sexExprHcno = $sexHcno !== '' ? 'sex.'.$sexHcno.' AS HC_No' : "'' AS HC_No";

	$edexExprGross = $edexGross !== '' ? 'COALESCE(edex.'.$edexGross.', 0) AS Gross_Weight' : '0 AS Gross_Weight';
	$edexExprCarton = $edexQtyCarton !== '' ? 'COALESCE(edex.'.$edexQtyCarton.', 0) AS Qty_Cartons' : '0 AS Qty_Cartons';

	// Order: first the five detail exports, then AR/AP & deposit tabs (independent group in UI).
	return array(
		'so_details' => require __DIR__.'/dataset_so_details.php',
		'so_inv_details' => require __DIR__.'/dataset_so_inv_details.php',
		'shipment_details' => require __DIR__.'/dataset_shipment_details.php',
		'po_details' => require __DIR__.'/dataset_po_details.php',
		'po_inv_details' => require __DIR__.'/dataset_po_inv_details.php',
		'so_inv_receivable' => require __DIR__.'/dataset_so_inv_receivable.php',
		'po_inv_payable' => require __DIR__.'/dataset_po_inv_payable.php',
		'so_deposit_invoice_missing' => require __DIR__.'/dataset_so_deposit_invoice_missing.php',
		'po_deposit_invoice_missing' => require __DIR__.'/dataset_po_deposit_invoice_missing.php',
	);
}

