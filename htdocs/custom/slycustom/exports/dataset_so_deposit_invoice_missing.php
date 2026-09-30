<?php
/* Copyright (C) 2025 SLY Custom
 *
 * Sales orders whose payment terms require a deposit (SO deposit_percent or c_payment_term.deposit_percent)
 * but no customer deposit invoice (facture.type = 3) linked to the order with validated status (fk_statut 1 or 2).
 * Only Commande::STATUS_VALIDATED (1) and Commande::STATUS_SHIPMENTONPROCESS (2): exclude draft, canceled (-1), closed/delivered (3).
 */
return array(
	'label' => 'SLYExportSODepositInvoiceMissing',
	'filename' => 'SLY_SO_Deposit_Invoice_Missing.xls',
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
			s.nom AS Customer,
			cp.code AS Payment_Term,
			COALESCE(NULLIF(TRIM(c.deposit_percent), ''), NULLIF(TRIM(cp.deposit_percent), ''), '') AS Deposit_Percent,
			c.date_commande AS Date_Order,
			c.fk_statut AS fk_statut,
			COALESCE(NULLIF(TRIM(c.multicurrency_code), ''), mc.code) AS Currency,
			COALESCE(c.multicurrency_total_ttc, c.total_ttc) AS Total,
			'MISSING_DEPOSIT_INVOICE' AS Alert_Message
		FROM ".$prefix."commande c
		LEFT JOIN ".$prefix."societe s ON s.rowid = c.fk_soc
		LEFT JOIN ".$prefix."c_payment_term cp ON cp.rowid = c.fk_cond_reglement
		LEFT JOIN ".$prefix."multicurrency mc ON mc.rowid = c.fk_multicurrency
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
			)
		ORDER BY c.rowid DESC
		LIMIT 500",
	'columns' => array(
		array('label' => 'Salesperson', 'field' => 'SalesPerson'),
		array('label' => 'SO No', 'field' => 'SO_No'),
		array('label' => 'Customer', 'field' => 'Customer'),
		array('label' => 'Payment Term', 'field' => 'Payment_Term'),
		array('label' => 'Deposit %', 'field' => 'Deposit_Percent'),
		array('label' => 'Date of Order', 'field' => 'Date_Order'),
		array('label' => 'Status', 'field' => 'fk_statut'),
		array('label' => 'Currency', 'field' => 'Currency'),
		array('label' => 'Total', 'field' => 'Total'),
		array('label' => 'Alert', 'field' => 'Alert_Message'),
	),
);
