<?php
		return array(
			'label' => 'SLYExportPOInvoiceDetails',
			'filename' => 'SLY_PO_Inv_details.xls',
		'sql' => "SELECT
			c.ref AS SO_No,
			cf.ref AS PO_No,
			cf.ref_supplier AS Supplier_No,
			MIN(se.ata) AS ATA,
			ff.ref AS Inv_No,
			s.nom AS Supplier,
			ff.datef AS Date_Inv,
			ff.date_lim_reglement AS Date_Due,
			ff.paye AS Paid,
			ff.fk_statut AS fk_statut,
			MAX(DATE(pf.datep)) AS Date_Payment_Latest,
			COALESCE(NULLIF(TRIM(ff.multicurrency_code), ''), mc.code) AS Currency,
			COALESCE(SUM(CASE WHEN ff.multicurrency_total_ttc IS NOT NULL THEN pff.multicurrency_amount ELSE pff.amount END), 0) AS Amount_Paid,
			CASE
				WHEN ff.fk_statut = 2 AND ff.close_code = 'discount_vat' THEN 0
				ELSE (
					COALESCE(ff.multicurrency_total_ttc, ff.total_ttc)
					- COALESCE(SUM(CASE WHEN ff.multicurrency_total_ttc IS NOT NULL THEN pff.multicurrency_amount ELSE pff.amount END), 0)
					- COALESCE(CASE WHEN ff.multicurrency_total_ttc IS NOT NULL THEN cn_used.creditnotes_used_multicurrency ELSE cn_used.creditnotes_used_amount END, 0)
					- COALESCE(CASE WHEN ff.multicurrency_total_ttc IS NOT NULL THEN dep_used.deposits_used_multicurrency ELSE dep_used.deposits_used_amount END, 0)
				)
			END AS Pending_Payment,
			CASE
				WHEN ff.fk_statut = 2 AND ff.paye = 1
				THEN (COALESCE(ff.multicurrency_total_ttc, ff.total_ttc) - COALESCE(SUM(CASE WHEN ff.multicurrency_total_ttc IS NOT NULL THEN pff.multicurrency_amount ELSE pff.amount END), 0))
				ELSE 0
			END AS Fees_or_Loss,
			ff.note_private AS Note_Private,
			ff.note_public AS Note_Public,
			pr.ref AS Product,
			ffd.description AS Description,
			ffd.qty AS Qty,
			COALESCE(NULLIF(TRIM(cu.short_label), ''), NULLIF(TRIM(cu.label), ''), '') AS Unit,
			COALESCE(ffd.multicurrency_subprice, ffd.pu_ht) AS Price,
			COALESCE(ffd.multicurrency_total_ttc, ffd.total_ttc) AS SubTotal
		FROM ".$prefix."facture_fourn ff
		LEFT JOIN ".$prefix."facture_fourn_det ffd ON ffd.fk_facture_fourn = ff.rowid
		LEFT JOIN ".$prefix."societe s ON s.rowid = ff.fk_soc
		LEFT JOIN ".$prefix."multicurrency mc ON mc.rowid = ff.fk_multicurrency
		LEFT JOIN ".$prefix."product pr ON pr.rowid = ffd.fk_product
		LEFT JOIN ".$prefix."c_units cu ON cu.rowid = ffd.fk_unit
		LEFT JOIN ".$prefix."paiementfourn_facturefourn pff ON pff.fk_facturefourn = ff.rowid
		LEFT JOIN ".$prefix."paiementfourn pf ON pf.rowid = pff.fk_paiementfourn
		LEFT JOIN (
			SELECT
				rc.fk_invoice_supplier AS invoice_id,
				COALESCE(SUM(rc.multicurrency_amount_ttc), 0) AS creditnotes_used_multicurrency,
				COALESCE(SUM(rc.amount_ttc), 0) AS creditnotes_used_amount
			FROM ".$prefix."societe_remise_except rc
			INNER JOIN ".$prefix."facture_fourn fsrc ON fsrc.rowid = rc.fk_invoice_supplier_source
			WHERE fsrc.type IN (0, 2)
			GROUP BY rc.fk_invoice_supplier
		) cn_used ON cn_used.invoice_id = ff.rowid
		LEFT JOIN (
			SELECT
				rc.fk_invoice_supplier AS invoice_id,
				COALESCE(SUM(rc.multicurrency_amount_ttc), 0) AS deposits_used_multicurrency,
				COALESCE(SUM(rc.amount_ttc), 0) AS deposits_used_amount
			FROM ".$prefix."societe_remise_except rc
			INNER JOIN ".$prefix."facture_fourn fsrc ON fsrc.rowid = rc.fk_invoice_supplier_source
			WHERE fsrc.type = 3
			GROUP BY rc.fk_invoice_supplier
		) dep_used ON dep_used.invoice_id = ff.rowid
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
			SELECT
				ee1.fk_target AS so_id,
				ee1.fk_source AS po_id
			FROM ".$prefix."element_element ee1
			WHERE ee1.sourcetype = 'order_supplier' AND ee1.targettype = 'commande'
			UNION ALL
			SELECT
				ee2.fk_source AS so_id,
				ee2.fk_target AS po_id
			FROM ".$prefix."element_element ee2
			WHERE ee2.sourcetype = 'commande' AND ee2.targettype = 'order_supplier'
		) ee_po_so ON ee_po_so.po_id = cf.rowid
		LEFT JOIN ".$prefix."commande c ON c.rowid = ee_po_so.so_id
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
			AND cf.rowid IS NOT NULL
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
);
