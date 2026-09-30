<?php
/*
 * Export dataset definition: SO Details (so_details).
 *
 * Included by tools.datasets.php inside getSlyExportDatasets() so it can
 * access local variables like $prefix, $eCommande, $eSupplierOrder.
 */

return array(
	'label' => 'SLYExportSODetails',
	'filename' => 'SLY_SO_Details.xls',
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
		AND c.fk_statut NOT IN (0, 3)
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
);

