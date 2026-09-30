<?php
return array(
			'label' => 'SLYExportSOInvoiceDetails',
			'filename' => 'SLY_SO_Inv_details.xls',
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
				MAX(DATE(p.datep)) AS Date_Payment_Latest,
				COALESCE(NULLIF(f.multicurrency_code, ''), mc.code) AS Currency,
				COALESCE(SUM(CASE WHEN f.multicurrency_total_ttc IS NOT NULL THEN pf.multicurrency_amount ELSE pf.amount END), 0) AS Amount_Received,
				CASE
					WHEN f.fk_statut = 2 AND f.close_code = 'discount_vat' THEN 0
					ELSE (
						COALESCE(f.multicurrency_total_ttc, f.total_ttc)
						- COALESCE(SUM(CASE WHEN f.multicurrency_total_ttc IS NOT NULL THEN pf.multicurrency_amount ELSE pf.amount END), 0)
						- COALESCE(CASE WHEN f.multicurrency_total_ttc IS NOT NULL THEN cn_used.creditnotes_used_multicurrency ELSE cn_used.creditnotes_used_amount END, 0)
						- COALESCE(CASE WHEN f.multicurrency_total_ttc IS NOT NULL THEN dep_used.deposits_used_multicurrency ELSE dep_used.deposits_used_amount END, 0)
					)
				END AS Pending_Payment,
				CASE
					WHEN f.fk_statut = 2 AND f.paye = 1
					THEN (COALESCE(f.multicurrency_total_ttc, f.total_ttc) - COALESCE(SUM(CASE WHEN f.multicurrency_total_ttc IS NOT NULL THEN pf.multicurrency_amount ELSE pf.amount END), 0))
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
				".$fxExprDateCustPay."
				".$fxExprDateSuppDocDeliver."
				".$fxExprSuppCourrier."
				".$fxExprCourierNumber."
				".$fxExprRecipient."
				".$fxExprDateSuppDocReceived."
				".$fxExprDateTR."
				".$fxExprDateSLYDocDeliver."
				".$fxExprSLYCourrier."
				".$fxExprSLYCourrierNo."
				".$fxExprRemark."
			FROM ".$prefix."facture f
			LEFT JOIN ".$prefix."facturedet fd ON fd.fk_facture = f.rowid
			LEFT JOIN ".$prefix."societe s ON s.rowid = f.fk_soc
			LEFT JOIN ".$prefix."multicurrency mc ON mc.rowid = f.fk_multicurrency
			LEFT JOIN ".$prefix."product pr ON pr.rowid = fd.fk_product
			LEFT JOIN ".$prefix."paiement_facture pf ON pf.fk_facture = f.rowid
			LEFT JOIN ".$prefix."paiement p ON p.rowid = pf.fk_paiement
			LEFT JOIN (
				SELECT fk_target AS fac_id, fk_source AS cmd_id
				FROM ".$prefix."element_element
				WHERE sourcetype = 'commande' AND targettype = 'facture'
				UNION ALL
				SELECT fk_source AS fac_id, fk_target AS cmd_id
				FROM ".$prefix."element_element
				WHERE sourcetype = 'facture' AND targettype = 'commande'
			) ee_so ON ee_so.fac_id = f.rowid
			LEFT JOIN ".$prefix."commande c ON c.rowid = ee_so.cmd_id AND c.entity IN (".$eCommande.")
			LEFT JOIN ".$prefix."c_units cu ON cu.rowid = fd.fk_unit
			LEFT JOIN ".$prefix."facture_extrafields fx ON fx.fk_object = f.rowid
			LEFT JOIN (
				SELECT
					rc.fk_facture AS invoice_id,
					COALESCE(SUM(rc.multicurrency_amount_ttc), 0) AS creditnotes_used_multicurrency,
					COALESCE(SUM(rc.amount_ttc), 0) AS creditnotes_used_amount
				FROM ".$prefix."societe_remise_except rc
				INNER JOIN ".$prefix."facture fsrc ON fsrc.rowid = rc.fk_facture_source
				WHERE fsrc.type IN (0, 2, 5)
				GROUP BY rc.fk_facture
			) cn_used ON cn_used.invoice_id = f.rowid
			LEFT JOIN (
				SELECT
					rc.fk_facture AS invoice_id,
					COALESCE(SUM(rc.multicurrency_amount_ttc), 0) AS deposits_used_multicurrency,
					COALESCE(SUM(rc.amount_ttc), 0) AS deposits_used_amount
				FROM ".$prefix."societe_remise_except rc
				INNER JOIN ".$prefix."facture fsrc ON fsrc.rowid = rc.fk_facture_source
				WHERE fsrc.type = 3
				GROUP BY rc.fk_facture
			) dep_used ON dep_used.invoice_id = f.rowid
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
				AND f.fk_statut NOT IN (0, 3)
				AND c.rowid IS NOT NULL
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
);
