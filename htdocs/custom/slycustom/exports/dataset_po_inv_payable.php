<?php
/* Copyright (C) 2025 SLY Custom
 *
 * PO supplier invoices pending payment: deposit (all unpaid with balance), or standard from 15 days before shipment ATA through all unpaid after arrival.
 * Includes linked SO customer deposit / standard invoice payment summary when PO is tied to an SO.
 */
return array(
	'label' => 'SLYExportPOInvoicePayable',
	'filename' => 'SLY_PO_Inv_Payable.xls',
	'sql' => "SELECT * FROM (
			SELECT
				c.ref AS SO_No,
				cf.ref AS PO_No,
				cf.ref_supplier AS Supplier_No,
				ff.ref AS Inv_No,
				(
					SELECT MIN(sex.ata)
					FROM (
						SELECT ee1.fk_source AS cmd_id, ee1.fk_target AS ship_id
						FROM ".$prefix."element_element ee1
						WHERE ee1.sourcetype = 'commande' AND ee1.targettype = 'shipping'
						UNION ALL
						SELECT ee1.fk_target AS cmd_id, ee1.fk_source AS ship_id
						FROM ".$prefix."element_element ee1
						WHERE ee1.targettype = 'commande' AND ee1.sourcetype = 'shipping'
					) ee_cmd_ship_a
					INNER JOIN ".$prefix."expedition exp_a ON exp_a.rowid = ee_cmd_ship_a.ship_id AND exp_a.entity IN (".$eExpedition.")
					INNER JOIN ".$prefix."expedition_extrafields sex ON sex.fk_object = exp_a.rowid
					WHERE ee_cmd_ship_a.cmd_id = COALESCE(c.rowid, ee_po_cmd.cmd_id)
				) AS ATA,
				s.nom AS Supplier,
				ff.datef AS Date_Inv,
				ff.date_lim_reglement AS Date_Due,
				ff.paye AS Paid,
				ff.fk_statut AS fk_statut,
				COALESCE(NULLIF(TRIM(ff.multicurrency_code), ''), mc.code) AS Currency,
				COALESCE((SELECT SUM(CASE WHEN ff.multicurrency_total_ttc IS NOT NULL THEN pff2.multicurrency_amount ELSE pff2.amount END)
					FROM ".$prefix."paiementfourn_facturefourn pff2 WHERE pff2.fk_facturefourn = ff.rowid), 0) AS Amount_Paid,
				CASE
					WHEN ff.fk_statut = 2 AND ff.close_code = 'discount_vat' THEN 0
					ELSE (
						COALESCE(ff.multicurrency_total_ttc, ff.total_ttc)
						- COALESCE((SELECT SUM(CASE WHEN ff.multicurrency_total_ttc IS NOT NULL THEN pff2.multicurrency_amount ELSE pff2.amount END)
							FROM ".$prefix."paiementfourn_facturefourn pff2 WHERE pff2.fk_facturefourn = ff.rowid), 0)
						- COALESCE(CASE WHEN ff.multicurrency_total_ttc IS NOT NULL THEN
							(SELECT COALESCE(SUM(rc.multicurrency_amount_ttc), 0)
								FROM ".$prefix."societe_remise_except rc
								INNER JOIN ".$prefix."facture_fourn fsrc ON fsrc.rowid = rc.fk_invoice_supplier_source
								WHERE rc.fk_invoice_supplier = ff.rowid AND fsrc.type IN (0, 2))
						ELSE
							(SELECT COALESCE(SUM(rc.amount_ttc), 0)
								FROM ".$prefix."societe_remise_except rc
								INNER JOIN ".$prefix."facture_fourn fsrc ON fsrc.rowid = rc.fk_invoice_supplier_source
								WHERE rc.fk_invoice_supplier = ff.rowid AND fsrc.type IN (0, 2))
						END, 0)
						- COALESCE(CASE WHEN ff.multicurrency_total_ttc IS NOT NULL THEN
							(SELECT COALESCE(SUM(rc.multicurrency_amount_ttc), 0)
								FROM ".$prefix."societe_remise_except rc
								INNER JOIN ".$prefix."facture_fourn fsrc ON fsrc.rowid = rc.fk_invoice_supplier_source
								WHERE rc.fk_invoice_supplier = ff.rowid AND fsrc.type = 3)
						ELSE
							(SELECT COALESCE(SUM(rc.amount_ttc), 0)
								FROM ".$prefix."societe_remise_except rc
								INNER JOIN ".$prefix."facture_fourn fsrc ON fsrc.rowid = rc.fk_invoice_supplier_source
								WHERE rc.fk_invoice_supplier = ff.rowid AND fsrc.type = 3)
						END, 0)
					)
				END AS Pending_Payment,
				(
					SELECT GROUP_CONCAT(DISTINCT cf2.ref ORDER BY cf2.ref SEPARATOR ' | ')
					FROM ".$prefix."facture cf2
					INNER JOIN (
						SELECT fk_target AS fac_id, fk_source AS cmd_id
						FROM ".$prefix."element_element
						WHERE sourcetype = 'commande' AND targettype = 'facture'
						UNION ALL
						SELECT fk_source AS fac_id, fk_target AS cmd_id
						FROM ".$prefix."element_element
						WHERE sourcetype = 'facture' AND targettype = 'commande'
					) efc ON efc.fac_id = cf2.rowid
					WHERE COALESCE(c.rowid, ee_po_cmd.cmd_id) IS NOT NULL
						AND efc.cmd_id = COALESCE(c.rowid, ee_po_cmd.cmd_id) AND cf2.type = 3
						AND cf2.entity IN (".$eInvoice.") AND cf2.fk_statut NOT IN (0, 3)
				) AS SO_Cust_Deposit_Refs,
				(
					SELECT GROUP_CONCAT(DISTINCT cf2.fk_statut ORDER BY cf2.fk_statut SEPARATOR ',')
					FROM ".$prefix."facture cf2
					INNER JOIN (
						SELECT fk_target AS fac_id, fk_source AS cmd_id
						FROM ".$prefix."element_element
						WHERE sourcetype = 'commande' AND targettype = 'facture'
						UNION ALL
						SELECT fk_source AS fac_id, fk_target AS cmd_id
						FROM ".$prefix."element_element
						WHERE sourcetype = 'facture' AND targettype = 'commande'
					) efc ON efc.fac_id = cf2.rowid
					WHERE COALESCE(c.rowid, ee_po_cmd.cmd_id) IS NOT NULL
						AND efc.cmd_id = COALESCE(c.rowid, ee_po_cmd.cmd_id) AND cf2.type = 3
						AND cf2.entity IN (".$eInvoice.") AND cf2.fk_statut NOT IN (0, 3)
				) AS SO_Cust_Deposit_Invoice_Status,
				(
					SELECT GROUP_CONCAT(DISTINCT cf2.ref ORDER BY cf2.ref SEPARATOR ' | ')
					FROM ".$prefix."facture cf2
					INNER JOIN (
						SELECT fk_target AS fac_id, fk_source AS cmd_id
						FROM ".$prefix."element_element
						WHERE sourcetype = 'commande' AND targettype = 'facture'
						UNION ALL
						SELECT fk_source AS fac_id, fk_target AS cmd_id
						FROM ".$prefix."element_element
						WHERE sourcetype = 'facture' AND targettype = 'commande'
					) efc ON efc.fac_id = cf2.rowid
					WHERE COALESCE(c.rowid, ee_po_cmd.cmd_id) IS NOT NULL
						AND efc.cmd_id = COALESCE(c.rowid, ee_po_cmd.cmd_id) AND cf2.type = 0
						AND cf2.entity IN (".$eInvoice.") AND cf2.fk_statut NOT IN (0, 3)
				) AS SO_Cust_Standard_Refs,
				(
					SELECT GROUP_CONCAT(DISTINCT cf2.fk_statut ORDER BY cf2.fk_statut SEPARATOR ',')
					FROM ".$prefix."facture cf2
					INNER JOIN (
						SELECT fk_target AS fac_id, fk_source AS cmd_id
						FROM ".$prefix."element_element
						WHERE sourcetype = 'commande' AND targettype = 'facture'
						UNION ALL
						SELECT fk_source AS fac_id, fk_target AS cmd_id
						FROM ".$prefix."element_element
						WHERE sourcetype = 'facture' AND targettype = 'commande'
					) efc ON efc.fac_id = cf2.rowid
					WHERE COALESCE(c.rowid, ee_po_cmd.cmd_id) IS NOT NULL
						AND efc.cmd_id = COALESCE(c.rowid, ee_po_cmd.cmd_id) AND cf2.type = 0
						AND cf2.entity IN (".$eInvoice.") AND cf2.fk_statut NOT IN (0, 3)
				) AS SO_Cust_Standard_Invoice_Status,
				ff.type AS Inv_Type_Raw
			FROM ".$prefix."facture_fourn ff
			INNER JOIN (
				SELECT ee1.fk_target AS inv_id, ee1.fk_source AS po_id
				FROM ".$prefix."element_element ee1
				WHERE (ee1.targettype = 'invoice_supplier' OR ee1.targettype = 'facture_fourn') AND ee1.sourcetype = 'order_supplier'
				UNION ALL
				SELECT ee1.fk_source AS inv_id, ee1.fk_target AS po_id
				FROM ".$prefix."element_element ee1
				WHERE (ee1.sourcetype = 'invoice_supplier' OR ee1.sourcetype = 'facture_fourn') AND ee1.targettype = 'order_supplier'
			) ee_inv_po ON ee_inv_po.inv_id = ff.rowid
			INNER JOIN ".$prefix."commande_fournisseur cf ON cf.rowid = ee_inv_po.po_id AND cf.entity IN (".$eSupplierOrder.")
			LEFT JOIN ".$prefix."societe s ON s.rowid = ff.fk_soc
			LEFT JOIN ".$prefix."multicurrency mc ON mc.rowid = ff.fk_multicurrency
			LEFT JOIN (
				SELECT ee2.fk_target AS so_id, ee2.fk_source AS po_id
				FROM ".$prefix."element_element ee2
				WHERE ee2.sourcetype = 'order_supplier' AND ee2.targettype = 'commande'
				UNION ALL
				SELECT ee2.fk_source AS so_id, ee2.fk_target AS po_id
				FROM ".$prefix."element_element ee2
				WHERE ee2.targettype = 'order_supplier' AND ee2.sourcetype = 'commande'
			) ee_po_so ON ee_po_so.po_id = cf.rowid
			LEFT JOIN ".$prefix."commande c ON c.rowid = ee_po_so.so_id AND c.entity IN (".$eCommande.")
			LEFT JOIN (
				SELECT ee2.fk_source AS po_id, ee2.fk_target AS cmd_id
				FROM ".$prefix."element_element ee2
				WHERE ee2.sourcetype = 'order_supplier' AND ee2.targettype = 'commande'
				UNION ALL
				SELECT ee2.fk_target AS po_id, ee2.fk_source AS cmd_id
				FROM ".$prefix."element_element ee2
				WHERE ee2.targettype = 'order_supplier' AND ee2.sourcetype = 'commande'
			) ee_po_cmd ON ee_po_cmd.po_id = cf.rowid
			WHERE ff.ref IS NOT NULL AND ff.entity IN (".$eSupplierInvoice.")
				AND ff.fk_statut NOT IN (0, 3)
				AND ff.paye = 0
				AND ff.type IN (0, 3)
		) invwrap
		WHERE invwrap.Pending_Payment > 0.00001
			AND (
				invwrap.Inv_Type_Raw = 3
				OR (
					invwrap.Inv_Type_Raw = 0
					AND invwrap.ATA IS NOT NULL
					AND CURDATE() >= DATE_SUB(DATE(invwrap.ATA), INTERVAL 15 DAY)
				)
			)
		ORDER BY COALESCE(invwrap.Supplier, '') ASC, invwrap.Inv_No DESC
		LIMIT 800",
	'columns' => array(
		array('label' => 'SO No', 'field' => 'SO_No'),
		array('label' => 'PO No', 'field' => 'PO_No'),
		array('label' => 'Supplier No', 'field' => 'Supplier_No'),
		array('label' => 'Inv No', 'field' => 'Inv_No'),
		array('label' => 'ATA', 'field' => 'ATA'),
		array('label' => 'Supplier', 'field' => 'Supplier'),
		array('label' => 'Invoice Date', 'field' => 'Date_Inv'),
		array('label' => 'Due Date', 'field' => 'Date_Due'),
		array('label' => 'Paid', 'field' => 'Paid'),
		array('label' => 'Status', 'field' => 'fk_statut'),
		array('label' => 'Currency', 'field' => 'Currency'),
		array('label' => 'Amount Paid', 'field' => 'Amount_Paid'),
		array('label' => 'Pending Amount', 'field' => 'Pending_Payment'),
		array('label' => 'SO Customer Deposit Invoice Refs', 'field' => 'SO_Cust_Deposit_Refs'),
		array('label' => 'SO Customer Deposit Invoice Status', 'field' => 'SO_Cust_Deposit_Invoice_Status'),
		array('label' => 'SO Customer Standard Invoice Refs', 'field' => 'SO_Cust_Standard_Refs'),
		array('label' => 'SO Customer Standard Invoice Status', 'field' => 'SO_Cust_Standard_Invoice_Status'),
	),
);
