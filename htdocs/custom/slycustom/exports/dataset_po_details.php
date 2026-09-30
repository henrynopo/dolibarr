<?php
return array(
			'label' => 'SLYExportPODetails',
			'filename' => 'SLY_PO_Details.xls',
		'sql' => "SELECT
			(SELECT TRIM(CONCAT(COALESCE(fu.firstname, ''), ' ', COALESCE(fu.lastname, '')))
				FROM ".$prefix."element_contact ec
				INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
				INNER JOIN ".$prefix."user fu ON fu.rowid = ec.fk_socpeople
				WHERE ec.element_id = cf.rowid
					AND tc.element = 'order_supplier'
					AND tc.source = 'internal'
					AND tc.code = 'SALESREPFOLL'
					AND tc.active = 1
				ORDER BY ec.rowid ASC
				LIMIT 1) AS SalesPerson,
			c.ref AS SO_No,
			cf.ref AS PO_No,
			cf.ref_supplier AS Supplier_No,
			s.nom AS Supplier,
			cf.date_commande AS Date_Order,
			cf.fk_statut AS fk_statut,
			cf.billed AS Billed,
			cp.code AS Payment_Term,
			ci.code AS Incoterm,
			cf.location_incoterms AS Port_Arrival,
			COALESCE(NULLIF(TRIM(cf.multicurrency_code), ''), mc.code) AS Currency,
			COALESCE(cf.multicurrency_total_ttc, cf.total_ttc) AS Total,
			cf.note_private AS Note_Private,
			cf.note_public AS Note_Public,
			p.ref AS Product,
			cfd.description AS description,
			cfd.qty AS Qty,
			COALESCE(NULLIF(TRIM(cu.short_label), ''), NULLIF(TRIM(cu.label), ''), '') AS Unit,
			COALESCE(cfd.multicurrency_subprice, cfd.subprice) AS Price,
			COALESCE(cfd.multicurrency_total_ttc, cfd.total_ttc) AS SubTotal
		FROM ".$prefix."commande_fournisseur cf
		LEFT JOIN ".$prefix."commande_fournisseurdet cfd ON cfd.fk_commande = cf.rowid
		LEFT JOIN ".$prefix."societe s ON s.rowid = cf.fk_soc
		LEFT JOIN ".$prefix."c_payment_term cp ON cp.rowid = cf.fk_cond_reglement
		LEFT JOIN ".$prefix."c_incoterms ci ON ci.rowid = cf.fk_incoterms
		LEFT JOIN ".$prefix."multicurrency mc ON mc.rowid = cf.fk_multicurrency
		LEFT JOIN ".$prefix."product p ON p.rowid = cfd.fk_product
		LEFT JOIN ".$prefix."c_units cu ON cu.rowid = cfd.fk_unit
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
		WHERE cf.entity IN (".$eSupplierOrder.")
			AND cf.fk_statut NOT IN (0, 6, 7, 9)
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
);
