<?php
/* Copyright (C) 2025 SLY Custom
 *
 * Purchase orders whose payment term (c_payment_term.deposit_percent) requires a supplier deposit
 * but no validated supplier deposit invoice (facture_fourn.type = 3, fk_statut 1 or 2) linked to the PO.
 * Only supplier order status validated / accepted / order sent (1,2,3); exclude draft, received (4,5), canceled (6,7), refused (9).
 */
return array(
	'label' => 'SLYExportPODepositInvoiceMissing',
	'filename' => 'SLY_PO_Deposit_Invoice_Missing.xls',
	'sql' => "SELECT
			c.ref AS SO_No,
			cf.ref AS PO_No,
			cf.ref_supplier AS Supplier_No,
			s.nom AS Supplier,
			cp.code AS Payment_Term,
			COALESCE(NULLIF(TRIM(cp.deposit_percent), ''), '') AS Deposit_Percent,
			cf.date_commande AS Date_Order,
			cf.fk_statut AS fk_statut,
			COALESCE(NULLIF(TRIM(cf.multicurrency_code), ''), mc.code) AS Currency,
			COALESCE(cf.multicurrency_total_ttc, cf.total_ttc) AS Total,
			'MISSING_PO_DEPOSIT_INVOICE' AS Alert_Message
		FROM ".$prefix."commande_fournisseur cf
		LEFT JOIN ".$prefix."societe s ON s.rowid = cf.fk_soc
		LEFT JOIN ".$prefix."c_payment_term cp ON cp.rowid = cf.fk_cond_reglement
		LEFT JOIN ".$prefix."multicurrency mc ON mc.rowid = cf.fk_multicurrency
		LEFT JOIN (
			SELECT ee1.fk_target AS so_id, ee1.fk_source AS po_id
			FROM ".$prefix."element_element ee1
			WHERE ee1.sourcetype = 'order_supplier' AND ee1.targettype = 'commande'
			UNION ALL
			SELECT ee2.fk_source AS so_id, ee2.fk_target AS po_id
			FROM ".$prefix."element_element ee2
			WHERE ee2.sourcetype = 'commande' AND ee2.targettype = 'order_supplier'
		) ee_po_so ON ee_po_so.po_id = cf.rowid
		LEFT JOIN ".$prefix."commande c ON c.rowid = ee_po_so.so_id AND c.entity IN (".$eCommande.")
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
			)
		ORDER BY cf.rowid DESC
		LIMIT 500",
	'columns' => array(
		array('label' => 'SO No', 'field' => 'SO_No'),
		array('label' => 'PO No', 'field' => 'PO_No'),
		array('label' => 'Supplier No', 'field' => 'Supplier_No'),
		array('label' => 'Supplier', 'field' => 'Supplier'),
		array('label' => 'Payment Term', 'field' => 'Payment_Term'),
		array('label' => 'Deposit %', 'field' => 'Deposit_Percent'),
		array('label' => 'Date of Order', 'field' => 'Date_Order'),
		array('label' => 'Status', 'field' => 'fk_statut'),
		array('label' => 'Currency', 'field' => 'Currency'),
		array('label' => 'Total', 'field' => 'Total'),
		array('label' => 'Alert', 'field' => 'Alert_Message'),
	),
);
