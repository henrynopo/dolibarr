<?php
return array(
			'label' => 'SLYExportShipmentDetails',
			'filename' => 'SLY_Shipment.xls',
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
				$sexExprPol,
				$sexExprAtd,
				$sexExprPod,
				$sexExprAta,
				$sexExprEtD,
				$sexExprEta,
				$shipCompanyExpr
				e.tracking_number AS Container_No,
				$sexExprBlno,
				$sexExprHcno,
				e.note_private AS Note_Private,
				e.note_public AS Note_Public,
				p.ref AS Product,
				COALESCE(NULLIF(TRIM(cu.short_label), ''), NULLIF(TRIM(cu.label), ''), '') AS Unit,
				ed.qty AS Net_Weight,
				$edexExprGross,
				$edexExprCarton
			FROM ".$prefix."expedition e
			LEFT JOIN ".$prefix."expeditiondet ed ON ed.fk_expedition = e.rowid
			LEFT JOIN ".$prefix."commandedet cd ON cd.rowid = ed.fk_elementdet
			LEFT JOIN ".$prefix."commande c ON c.rowid = cd.fk_commande
			LEFT JOIN ".$prefix."societe s ON s.rowid = c.fk_soc
			LEFT JOIN ".$prefix."product p ON p.rowid = cd.fk_product
			LEFT JOIN ".$prefix."c_units cu ON cu.rowid = cd.fk_unit
			LEFT JOIN ".$prefix."expedition_extrafields sex ON sex.fk_object = e.rowid
			LEFT JOIN ".$prefix."expeditiondet_extrafields edex ON edex.fk_object = ed.rowid
			WHERE e.entity IN (".$eExpedition.")
				AND e.fk_statut NOT IN (0, 3, -1)
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
);
