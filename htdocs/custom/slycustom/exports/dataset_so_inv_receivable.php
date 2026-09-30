<?php
/* Copyright (C) 2025 SLY Custom
 *
 * SO customer invoices pending collection: deposit (all unpaid with balance), or standard from 15 days before shipment ATA through all unpaid after arrival.
 * One row per invoice; requires linked sales order (same rule as SO Invoice Details).
 */
return array(
	'label' => 'SLYExportSOInvoiceReceivable',
	'filename' => 'SLY_SO_Inv_Receivable.xls',
	'sql' => "SELECT * FROM (
			SELECT
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
					LIMIT 1) AS Salesperson,
				c.ref AS SO_No,
				f.ref AS Inv_No,
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
					WHERE ee_cmd_ship_a.cmd_id = c.rowid
				) AS ATA,
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
				COALESCE(NULLIF(f.multicurrency_code, ''), mc.code) AS Currency,
				COALESCE((SELECT SUM(CASE WHEN f.multicurrency_total_ttc IS NOT NULL THEN pf.multicurrency_amount ELSE pf.amount END)
					FROM ".$prefix."paiement_facture pf WHERE pf.fk_facture = f.rowid), 0) AS Amount_Received,
				CASE
					WHEN f.fk_statut = 2 AND f.close_code = 'discount_vat' THEN 0
					ELSE (
						COALESCE(f.multicurrency_total_ttc, f.total_ttc)
						- COALESCE((SELECT SUM(CASE WHEN f.multicurrency_total_ttc IS NOT NULL THEN pf.multicurrency_amount ELSE pf.amount END)
							FROM ".$prefix."paiement_facture pf WHERE pf.fk_facture = f.rowid), 0)
						- COALESCE(CASE WHEN f.multicurrency_total_ttc IS NOT NULL THEN
							(SELECT COALESCE(SUM(rc.multicurrency_amount_ttc), 0)
								FROM ".$prefix."societe_remise_except rc
								INNER JOIN ".$prefix."facture fsrc ON fsrc.rowid = rc.fk_facture_source
								WHERE rc.fk_facture = f.rowid AND fsrc.type IN (0, 2, 5))
						ELSE
							(SELECT COALESCE(SUM(rc.amount_ttc), 0)
								FROM ".$prefix."societe_remise_except rc
								INNER JOIN ".$prefix."facture fsrc ON fsrc.rowid = rc.fk_facture_source
								WHERE rc.fk_facture = f.rowid AND fsrc.type IN (0, 2, 5))
						END, 0)
						- COALESCE(CASE WHEN f.multicurrency_total_ttc IS NOT NULL THEN
							(SELECT COALESCE(SUM(rc.multicurrency_amount_ttc), 0)
								FROM ".$prefix."societe_remise_except rc
								INNER JOIN ".$prefix."facture fsrc ON fsrc.rowid = rc.fk_facture_source
								WHERE rc.fk_facture = f.rowid AND fsrc.type = 3)
						ELSE
							(SELECT COALESCE(SUM(rc.amount_ttc), 0)
								FROM ".$prefix."societe_remise_except rc
								INNER JOIN ".$prefix."facture fsrc ON fsrc.rowid = rc.fk_facture_source
								WHERE rc.fk_facture = f.rowid AND fsrc.type = 3)
						END, 0)
					)
				END AS Pending_Payment,
				f.type AS Inv_Type_Raw
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
			INNER JOIN ".$prefix."commande c ON c.rowid = ee_so.cmd_id AND c.entity IN (".$eCommande.")
			LEFT JOIN ".$prefix."societe s ON s.rowid = f.fk_soc
			LEFT JOIN ".$prefix."multicurrency mc ON mc.rowid = f.fk_multicurrency
			WHERE f.ref IS NOT NULL AND f.entity IN (".$eInvoice.")
				AND f.fk_statut NOT IN (0, 3)
				AND f.paye = 0
				AND f.type IN (0, 3)
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
		ORDER BY COALESCE(invwrap.Customer, '') ASC, invwrap.Inv_No DESC
		LIMIT 800",
	'columns' => array(
		array('label' => 'Salesperson', 'field' => 'Salesperson'),
		array('label' => 'SO No', 'field' => 'SO_No'),
		array('label' => 'Inv No', 'field' => 'Inv_No'),
		array('label' => 'ATA', 'field' => 'ATA'),
		array('label' => 'Customer', 'field' => 'Customer'),
		array('label' => 'Bill To', 'field' => 'Billing_Company'),
		array('label' => 'Invoice Date', 'field' => 'Date_Inv'),
		array('label' => 'Due Date', 'field' => 'Date_Due'),
		array('label' => 'Paid', 'field' => 'Paid'),
		array('label' => 'Status', 'field' => 'fk_statut'),
		array('label' => 'Currency', 'field' => 'Currency'),
		array('label' => 'Amount Received', 'field' => 'Amount_Received'),
		array('label' => 'Pending Amount', 'field' => 'Pending_Payment'),
	),
);
