<?php
/* Copyright (C) 2025 SLY Custom
 * SLY ALL-in-One: triggers sequential download of all SLY export reports.
 */

require_once __DIR__.'/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

if (!isModEnabled('slycustom')) {
	accessforbidden();
	exit;
}

$langs->loadLangs(array("other", "slycustom@slycustom", "main"));

$exportBase = DOL_URL_ROOT.'/custom/slycustom/exports/tools.php';
$prefix = MAIN_DB_PREFIX;
$eCommande = getEntity('commande');
$eInvoice = getEntity('invoice');
$eExpedition = getEntity('expedition');
$eSupplierOrder = getEntity('supplier_order');
$eSupplierInvoice = getEntity('supplier_invoice');

/**
 * Validate before triggering sequential downloads.
 *
 * For ALL-in-One, we want to avoid exporting inconsistent linkage data
 * (multiple PO per SO, multiple shipments per invoice, etc.).
 */
function slyAllInOneValidate(&$db, &$langs, $prefix, $eCommande, $eInvoice, $eExpedition, $eSupplierOrder, $eSupplierInvoice)
{
	$errors = array(
		// Each rule keeps an associative set of refs to avoid duplicates.
		'so_details' => array(
			'po_cnt_gt_1' => array(),
			'salesperson_cnt_ne_1' => array(),
			'billto_cnt_gt_1' => array(),
			'shipment_cnt_gt_1' => array(),
			'consignee_ship_contact_cnt_gt_1' => array(),
		),
		'so_inv_details' => array(
			'so_cnt_gt_1' => array(),
			'shipment_cnt_gt_1' => array(),
			'billto_cnt_gt_1' => array(),
		),
		'shipment_details' => array(
			'so_cnt_ne_1' => array(),
			'billto_cnt_gt_1' => array(),
			'customer_ship_contact_cnt_gt_1' => array(),
			'invoice_cnt_gt_1' => array(),
		),
		'po_details' => array(
			'so_cnt_gt_1' => array(),
			'purchase_cnt_ne_1' => array(),
		),
		'po_inv_details' => array(
			'po_cnt_gt_1' => array(),
		),
	);

	/**
	 * Helper: push unique ref.
	 */
	$pushRef = static function (array &$arr, $ref) {
		$ref = (string) $ref;
		if ($ref !== '') $arr[$ref] = 1;
	};

	$sqlSo = "SELECT
		c.ref,
		(
			SELECT COUNT(DISTINCT t.po_id)
			FROM (
				SELECT ee.fk_source AS po_id
				FROM ".$prefix."element_element ee
				INNER JOIN ".$prefix."commande_fournisseur cf ON cf.rowid = ee.fk_source AND cf.entity IN (".$eSupplierOrder.")
				WHERE ee.fk_target = c.rowid
					AND ee.targettype = 'commande'
					AND ee.sourcetype = 'order_supplier'
				UNION ALL
				SELECT ee.fk_target AS po_id
				FROM ".$prefix."element_element ee
				INNER JOIN ".$prefix."commande_fournisseur cf ON cf.rowid = ee.fk_target AND cf.entity IN (".$eSupplierOrder.")
				WHERE ee.fk_source = c.rowid
					AND ee.sourcetype = 'commande'
					AND ee.targettype = 'order_supplier'
			) t
		) AS po_cnt,
		(
			SELECT COUNT(DISTINCT ec.fk_socpeople)
			FROM ".$prefix."element_contact ec
			INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
			WHERE ec.element_id = c.rowid
				AND tc.element = 'commande'
				AND tc.source = 'internal'
				AND tc.code = 'SALESREPFOLL'
				AND tc.active = 1
		) AS salesperson_cnt,
		(
			SELECT COUNT(DISTINCT ec.fk_socpeople)
			FROM ".$prefix."element_contact ec
			INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
			WHERE ec.element_id = c.rowid
				AND tc.element = 'commande'
				AND tc.source = 'external'
				AND tc.code = 'BILLING'
				AND tc.active = 1
		) AS billto_cnt,
		(
			SELECT COUNT(DISTINCT exp.rowid)
			FROM (
				SELECT ee.fk_target AS ship_id
				FROM ".$prefix."element_element ee
				WHERE ee.sourcetype = 'commande'
					AND ee.targettype = 'shipping'
					AND ee.fk_source = c.rowid
				UNION ALL
				SELECT ee.fk_source AS ship_id
				FROM ".$prefix."element_element ee
				WHERE ee.sourcetype = 'shipping'
					AND ee.targettype = 'commande'
					AND ee.fk_target = c.rowid
			) s
			INNER JOIN ".$prefix."expedition exp ON exp.rowid = s.ship_id AND exp.entity IN (".$eExpedition.")
		) AS shipment_cnt,
		(
			CASE
				WHEN cex.ConsigneeAppointedBy IS NULL OR TRIM(cex.ConsigneeAppointedBy) = '' THEN 0
				WHEN cex.ConsigneeAppointedBy LIKE '%,%' OR cex.ConsigneeAppointedBy LIKE '%;%' THEN 2
				ELSE 1
			END
		) AS consignee_ship_contact_cnt
	FROM ".$prefix."commande c
	LEFT JOIN ".$prefix."commande_extrafields cex ON cex.fk_object = c.rowid
	WHERE c.entity IN (".$eCommande.")
		AND c.fk_statut NOT IN (0, 3)
	HAVING po_cnt > 1
		OR salesperson_cnt <> 1
		OR billto_cnt > 1
		OR shipment_cnt > 1
		OR consignee_ship_contact_cnt > 1
	";

	$resql = $db->query($sqlSo);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$poCnt = (int) $obj->po_cnt;
			$salespersonCnt = (int) $obj->salesperson_cnt;
			$billtoCnt = (int) $obj->billto_cnt;
			$shipmentCnt = (int) $obj->shipment_cnt;
			$consigneeShipContactCnt = (int) $obj->consignee_ship_contact_cnt;

			if ($poCnt > 1) $pushRef($errors['so_details']['po_cnt_gt_1'], $obj->ref);
			if ($salespersonCnt !== 1) $pushRef($errors['so_details']['salesperson_cnt_ne_1'], $obj->ref);
			if ($billtoCnt > 1) $pushRef($errors['so_details']['billto_cnt_gt_1'], $obj->ref);
			if ($shipmentCnt > 1) $pushRef($errors['so_details']['shipment_cnt_gt_1'], $obj->ref);
			if ($consigneeShipContactCnt > 1) $pushRef($errors['so_details']['consignee_ship_contact_cnt_gt_1'], $obj->ref);
		}
		$db->free($resql);
	} else {
		$errors['so_details']['__sql_error__'] = array('__SQL_ERROR__:' . $db->lasterror());
	}

	$sqlSoInv = "SELECT
		f.ref,
		(
			SELECT COUNT(DISTINCT t.so_id)
			FROM (
				SELECT ee.fk_source AS so_id
				FROM ".$prefix."element_element ee
				WHERE ee.sourcetype = 'commande'
					AND ee.targettype = 'facture'
					AND ee.fk_target = f.rowid
				UNION ALL
				SELECT ee.fk_target AS so_id
				FROM ".$prefix."element_element ee
				WHERE ee.sourcetype = 'facture'
					AND ee.targettype = 'commande'
					AND ee.fk_source = f.rowid
			) t
			INNER JOIN ".$prefix."commande c2 ON c2.rowid = t.so_id AND c2.entity IN (".$eCommande.")
		) AS so_cnt,
		(
			SELECT COUNT(DISTINCT exp.rowid)
			FROM (
				SELECT ee_cmd_ship.ship_id
				FROM (
					SELECT ee1.fk_source AS cmd_id, ee1.fk_target AS ship_id
					FROM ".$prefix."element_element ee1
					WHERE ee1.sourcetype = 'commande'
						AND ee1.targettype = 'shipping'
					UNION ALL
					SELECT ee2.fk_target AS cmd_id, ee2.fk_source AS ship_id
					FROM ".$prefix."element_element ee2
					WHERE ee2.sourcetype = 'shipping'
						AND ee2.targettype = 'commande'
				) ee_cmd_ship
				INNER JOIN (
					SELECT ee.fk_source AS so_id
					FROM ".$prefix."element_element ee
					WHERE ee.sourcetype = 'commande'
						AND ee.targettype = 'facture'
						AND ee.fk_target = f.rowid
					UNION ALL
					SELECT ee.fk_target AS so_id
					FROM ".$prefix."element_element ee
					WHERE ee.sourcetype = 'facture'
						AND ee.targettype = 'commande'
						AND ee.fk_source = f.rowid
				) so_ids ON so_ids.so_id = ee_cmd_ship.cmd_id
			) s
			INNER JOIN ".$prefix."expedition exp ON exp.rowid = s.ship_id AND exp.entity IN (".$eExpedition.")
		) AS shipment_cnt,
		(
			SELECT COUNT(DISTINCT ec.fk_socpeople)
			FROM ".$prefix."element_contact ec
			INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
			WHERE ec.element_id = f.rowid
				AND tc.element = 'facture'
				AND tc.source = 'external'
				AND tc.code = 'BILLING'
				AND tc.active = 1
		) AS billto_cnt
	FROM ".$prefix."facture f
	WHERE f.entity IN (".$eInvoice.")
		AND f.fk_statut NOT IN (0, 3)
	HAVING so_cnt > 1
		OR shipment_cnt > 1
		OR billto_cnt > 1
	";

	$resql = $db->query($sqlSoInv);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$soCnt = (int) $obj->so_cnt;
			$shipmentCnt = (int) $obj->shipment_cnt;
			$billtoCnt = (int) $obj->billto_cnt;

			if ($soCnt > 1) $pushRef($errors['so_inv_details']['so_cnt_gt_1'], $obj->ref);
			if ($shipmentCnt > 1) $pushRef($errors['so_inv_details']['shipment_cnt_gt_1'], $obj->ref);
			if ($billtoCnt > 1) $pushRef($errors['so_inv_details']['billto_cnt_gt_1'], $obj->ref);
		}
		$db->free($resql);
	} else {
		$errors['so_inv_details']['__sql_error__'] = array('__SQL_ERROR__:' . $db->lasterror());
	}

	$sqlShipment = "SELECT
		e.ref,
		(
			SELECT COUNT(DISTINCT t.so_id)
			FROM (
				SELECT ee.fk_source AS so_id
				FROM ".$prefix."element_element ee
				WHERE ee.sourcetype = 'commande'
					AND ee.targettype = 'shipping'
					AND ee.fk_target = e.rowid
				UNION ALL
				SELECT ee.fk_target AS so_id
				FROM ".$prefix."element_element ee
				WHERE ee.sourcetype = 'shipping'
					AND ee.targettype = 'commande'
					AND ee.fk_source = e.rowid
			) t
			INNER JOIN ".$prefix."commande c2 ON c2.rowid = t.so_id AND c2.entity IN (".$eCommande.")
		) AS so_cnt,
		(
			SELECT COUNT(DISTINCT fi.rowid)
			FROM (
				SELECT DISTINCT ee_inv.fk_target AS inv_id
				FROM ".$prefix."element_element ee_inv
				INNER JOIN (
					SELECT ee.fk_source AS so_id
					FROM ".$prefix."element_element ee
					WHERE ee.sourcetype = 'commande'
						AND ee.targettype = 'shipping'
						AND ee.fk_target = e.rowid
					UNION ALL
					SELECT ee.fk_target AS so_id
					FROM ".$prefix."element_element ee
					WHERE ee.sourcetype = 'shipping'
						AND ee.targettype = 'commande'
						AND ee.fk_source = e.rowid
				) so_ids ON so_ids.so_id = ee_inv.fk_source
				WHERE ee_inv.sourcetype = 'commande'
					AND ee_inv.targettype = 'facture'
			) inv_ids
			INNER JOIN ".$prefix."facture fi ON fi.rowid = inv_ids.inv_id AND fi.entity IN (".$eInvoice.")
		) AS invoice_cnt,
		(
			SELECT COUNT(DISTINCT ec.fk_socpeople)
			FROM ".$prefix."element_contact ec
			INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
			INNER JOIN (
				SELECT DISTINCT ee_inv.fk_target AS inv_id
				FROM ".$prefix."element_element ee_inv
				INNER JOIN (
					SELECT ee.fk_source AS so_id
					FROM ".$prefix."element_element ee
					WHERE ee.sourcetype = 'commande'
						AND ee.targettype = 'shipping'
						AND ee.fk_target = e.rowid
					UNION ALL
					SELECT ee.fk_target AS so_id
					FROM ".$prefix."element_element ee
					WHERE ee.sourcetype = 'shipping'
						AND ee.targettype = 'commande'
						AND ee.fk_source = e.rowid
				) so_ids ON so_ids.so_id = ee_inv.fk_source
				WHERE ee_inv.sourcetype = 'commande'
					AND ee_inv.targettype = 'facture'
			) inv_ids ON inv_ids.inv_id = ec.element_id
			WHERE tc.element = 'facture'
				AND tc.source = 'external'
				AND tc.code = 'BILLING'
				AND tc.active = 1
		) AS billto_cnt
	FROM ".$prefix."expedition e
	WHERE e.entity IN (".$eExpedition.")
		AND e.fk_statut NOT IN (0, 3)
	HAVING so_cnt <> 1
		OR billto_cnt > 1
		OR invoice_cnt > 1
	";

	$resql = $db->query($sqlShipment);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$soCnt = (int) $obj->so_cnt;
			$billtoCnt = (int) $obj->billto_cnt;
			$invoiceCnt = (int) $obj->invoice_cnt;

			if ($soCnt !== 1) $pushRef($errors['shipment_details']['so_cnt_ne_1'], $obj->ref);
			if ($billtoCnt > 1) $pushRef($errors['shipment_details']['billto_cnt_gt_1'], $obj->ref);
			if ($invoiceCnt > 1) $pushRef($errors['shipment_details']['invoice_cnt_gt_1'], $obj->ref);
		}
		$db->free($resql);
	} else {
		$errors['shipment_details']['__sql_error__'] = array('__SQL_ERROR__:' . $db->lasterror());
	}

	$sqlPo = "SELECT
		cf.ref,
		(
			SELECT COUNT(DISTINCT t.so_id)
			FROM (
				SELECT ee1.fk_target AS so_id
				FROM ".$prefix."element_element ee1
				WHERE ee1.sourcetype = 'order_supplier'
					AND ee1.targettype = 'commande'
					AND ee1.fk_source = cf.rowid
				UNION ALL
				SELECT ee2.fk_source AS so_id
				FROM ".$prefix."element_element ee2
				WHERE ee2.sourcetype = 'commande'
					AND ee2.targettype = 'order_supplier'
					AND ee2.fk_target = cf.rowid
			) t
			INNER JOIN ".$prefix."commande c2 ON c2.rowid = t.so_id AND c2.entity IN (".$eCommande.")
		) AS so_cnt,
		(
			SELECT COUNT(DISTINCT ec.fk_socpeople)
			FROM ".$prefix."element_contact ec
			INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
			WHERE ec.element_id = cf.rowid
				AND tc.element = 'order_supplier'
				AND tc.source = 'internal'
				AND tc.code = 'SALESREPFOLL'
				AND tc.active = 1
		) AS purchase_cnt
	FROM ".$prefix."commande_fournisseur cf
	WHERE cf.entity IN (".$eSupplierOrder.")
		AND cf.fk_statut NOT IN (0, 6, 7, 9)
	HAVING so_cnt > 1
		OR purchase_cnt <> 1
	";

	$resql = $db->query($sqlPo);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$soCnt = (int) $obj->so_cnt;
			$purchaseCnt = (int) $obj->purchase_cnt;

			if ($soCnt > 1) $pushRef($errors['po_details']['so_cnt_gt_1'], $obj->ref);
			if ($purchaseCnt !== 1) $pushRef($errors['po_details']['purchase_cnt_ne_1'], $obj->ref);
		}
		$db->free($resql);
	} else {
		$errors['po_details']['__sql_error__'] = array('__SQL_ERROR__:' . $db->lasterror());
	}

	$sqlPoInv = "SELECT
		ff.ref,
		(
			SELECT COUNT(DISTINCT t.po_id)
			FROM (
				SELECT ee1.fk_source AS po_id
				FROM ".$prefix."element_element ee1
				WHERE ee1.sourcetype = 'order_supplier'
					AND (ee1.targettype = 'invoice_supplier' OR ee1.targettype = 'facture_fourn')
					AND ee1.fk_target = ff.rowid
				UNION ALL
				SELECT ee2.fk_target AS po_id
				FROM ".$prefix."element_element ee2
				WHERE (ee2.sourcetype = 'invoice_supplier' OR ee2.sourcetype = 'facture_fourn')
					AND ee2.targettype = 'order_supplier'
					AND ee2.fk_source = ff.rowid
			) t
			INNER JOIN ".$prefix."commande_fournisseur po ON po.rowid = t.po_id AND po.entity IN (".$eSupplierOrder.")
		) AS po_cnt,
	FROM ".$prefix."facture_fourn ff
	WHERE ff.entity IN (".$eSupplierInvoice.")
		AND ff.fk_statut NOT IN (0, 3)
	HAVING po_cnt > 1
	";

	$resql = $db->query($sqlPoInv);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$poCnt = (int) $obj->po_cnt;

			if ($poCnt > 1) $pushRef($errors['po_inv_details']['po_cnt_gt_1'], $obj->ref);
		}
		$db->free($resql);
	} else {
		$errors['po_inv_details']['__sql_error__'] = array('__SQL_ERROR__:' . $db->lasterror());
	}

	// Convert inner sets to arrays.
	foreach ($errors as $dataset => $rules) {
		foreach ($rules as $rule => $set) {
			if ($rule === '__sql_error__') {
				// Keep SQL error message(s) as-is (do not convert to array_keys()).
				continue;
			}
			if (is_array($set)) {
				$errors[$dataset][$rule] = array_keys($set);
			}
		}
	}

	return $errors;
}

/**
 * New ALL-in-One validation (V2).
 *
 * Problem in V1: MySQL doesn't allow derived-table correlation on outer aliases
 * in the way we used it, causing "Unknown column ... in where clause".
 * V2 uses PHP-side counting based on pre-fetched mappings/contacts, which is
 * deterministic and avoids correlated derived tables.
 */
function slyAllInOneValidateV2(&$db, &$langs, $prefix, $eCommande, $eInvoice, $eExpedition, $eSupplierOrder, $eSupplierInvoice)
{
	global $conf;

	$errors = array(
		'so_details' => array(
			'po_cnt_gt_1' => array(),
			'salesperson_cnt_ne_1' => array(),
			'billto_cnt_gt_1' => array(),
			'shipment_cnt_gt_1' => array(),
			'consignee_ship_contact_cnt_gt_1' => array(),
		),
		'so_inv_details' => array(
			'so_cnt_gt_1' => array(),
			'shipment_cnt_gt_1' => array(),
			'billto_cnt_gt_1' => array(),
		),
		'shipment_details' => array(
			'so_cnt_ne_1' => array(),
			'billto_cnt_gt_1' => array(),
			'invoice_cnt_gt_1' => array(),
		),
		'po_details' => array(
			'so_cnt_gt_1' => array(),
			'purchase_cnt_ne_1' => array(),
		),
		'po_inv_details' => array(
			'po_cnt_gt_1' => array(),
		),
	);

	$pushRef = static function (array &$arr, $ref) {
		$ref = (string) $ref;
		if ($ref !== '') $arr[$ref] = 1;
	};

	// Helper: build ref map (rowid => ref).
	$fetchRefMap = static function (string $sql) use (&$db) {
		$map = array();
		$res = $db->query($sql);
		if ($res) {
			while ($obj = $db->fetch_object($res)) {
				if (isset($obj->rowid)) {
					$map[(int) $obj->rowid] = (string) ($obj->ref ?? '');
				}
			}
			$db->free($res);
		}
		return $map;
	};

	// Candidate refs.
	$soRefById = $fetchRefMap(
		"SELECT c.rowid, c.ref FROM ".$prefix."commande c WHERE c.entity IN (".$eCommande.") AND c.fk_statut NOT IN (0, 3)"
	);
	$invRefById = $fetchRefMap(
		"SELECT f.rowid, f.ref FROM ".$prefix."facture f WHERE f.entity IN (".$eInvoice.") AND f.fk_statut NOT IN (0, 3) AND f.ref IS NOT NULL AND f.ref <> ''"
	);
	$shipRefById = $fetchRefMap(
		"SELECT e.rowid, e.ref FROM ".$prefix."expedition e WHERE e.entity IN (".$eExpedition.")
			AND e.fk_statut NOT IN (0, 3, -1)
			AND e.ref IS NOT NULL AND e.ref <> ''"
	);
	$shipSailingStatusId = array(); // [ship_id] => int|null
	if (!empty($shipRefById)) {
		$shipIds = array_map(static function ($id) { return (int) $id; }, array_keys($shipRefById));
		$shipIdsCsv = implode(',', $shipIds);
		$sqlShipSailing = "SELECT fk_object, sailingstatusid FROM ".$prefix."expedition_extrafields WHERE fk_object IN (".$shipIdsCsv.")";
		$res = $db->query($sqlShipSailing);
		if ($res) {
			while ($obj = $db->fetch_object($res)) {
				$shipId = (int) $obj->fk_object;
				$val = $obj->sailingstatusid;
				if ($val === null) $shipSailingStatusId[$shipId] = null;
				else $shipSailingStatusId[$shipId] = (int) $val;
			}
			$db->free($res);
		}
	}
	$poRefById = $fetchRefMap(
		"SELECT cf.rowid, cf.ref FROM ".$prefix."commande_fournisseur cf WHERE cf.entity IN (".$eSupplierOrder.") AND cf.fk_statut NOT IN (0, 6, 7, 9)"
	);
	$poInvRefById = $fetchRefMap(
		"SELECT ff.rowid, ff.ref FROM ".$prefix."facture_fourn ff WHERE ff.entity IN (".$eSupplierInvoice.") AND ff.fk_statut NOT IN (0, 3) AND ff.ref IS NOT NULL AND ff.ref <> ''"
	);

	// Mappings (built via SQL UNION ALL with joins).
	$soToPo = array();
	$poToSo = array();
	$soToShip = array();
	$shipToSo = array();
	$soToInv = array();
	$invToSo = array();
	$shipToInv = array();
	$invToShip = array();
	$poInvToPo = array();

	// SO <-> PO
	$sqlSoPo = "SELECT so_id, po_id FROM (
		SELECT ee.fk_target AS so_id, ee.fk_source AS po_id
		FROM ".$prefix."element_element ee
		INNER JOIN ".$prefix."commande c ON c.rowid = ee.fk_target AND c.entity IN (".$eCommande.") AND c.fk_statut NOT IN (0, 3)
		INNER JOIN ".$prefix."commande_fournisseur cf ON cf.rowid = ee.fk_source AND cf.entity IN (".$eSupplierOrder.") AND cf.fk_statut NOT IN (0, 6, 7, 9)
		WHERE ee.sourcetype = 'order_supplier' AND ee.targettype = 'commande'
		UNION ALL
		SELECT ee.fk_source AS so_id, ee.fk_target AS po_id
		FROM ".$prefix."element_element ee
		INNER JOIN ".$prefix."commande c ON c.rowid = ee.fk_source AND c.entity IN (".$eCommande.") AND c.fk_statut NOT IN (0, 3)
		INNER JOIN ".$prefix."commande_fournisseur cf ON cf.rowid = ee.fk_target AND cf.entity IN (".$eSupplierOrder.") AND cf.fk_statut NOT IN (0, 6, 7, 9)
		WHERE ee.sourcetype = 'commande' AND ee.targettype = 'order_supplier'
	) t";
	$res = $db->query($sqlSoPo);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$soId = (int) $obj->so_id;
			$poId = (int) $obj->po_id;
			if (isset($soRefById[$soId]) && isset($poRefById[$poId])) {
				$soToPo[$soId][$poId] = true;
				$poToSo[$poId][$soId] = true;
			}
		}
		$db->free($res);
	}

	// SO <-> Shipment (commande <-> shipping)
	$sqlSoShip = "SELECT so_id, ship_id FROM (
		SELECT ee.fk_source AS so_id, ee.fk_target AS ship_id
		FROM ".$prefix."element_element ee
		INNER JOIN ".$prefix."commande c ON c.rowid = ee.fk_source AND c.entity IN (".$eCommande.")
		INNER JOIN ".$prefix."expedition e ON e.rowid = ee.fk_target AND e.entity IN (".$eExpedition.") AND e.fk_statut NOT IN (0, 3, -1)
		WHERE ee.sourcetype = 'commande' AND ee.targettype = 'shipping'
		UNION ALL
		SELECT ee.fk_target AS so_id, ee.fk_source AS ship_id
		FROM ".$prefix."element_element ee
		INNER JOIN ".$prefix."commande c ON c.rowid = ee.fk_target AND c.entity IN (".$eCommande.")
		INNER JOIN ".$prefix."expedition e ON e.rowid = ee.fk_source AND e.entity IN (".$eExpedition.") AND e.fk_statut NOT IN (0, 3, -1)
		WHERE ee.sourcetype = 'shipping' AND ee.targettype = 'commande'
	) t";
	$res = $db->query($sqlSoShip);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$soId = (int) $obj->so_id;
			$shipId = (int) $obj->ship_id;
			// shipment_details export filters expedition only; it does not filter commande status.
			// So for validation mapping we only require shipment in candidate set,
			// and we keep linked SOs even if they are draft/canceled.
			if (isset($shipRefById[$shipId])) {
				$soToShip[$soId][$shipId] = true;
				$shipToSo[$shipId][$soId] = true;
			}
		}
		$db->free($res);
	}

	// Shipment <-> Invoice (direct mapping via element_element shipping <-> facture).
	// This matches how the shipment card typically shows "linked documents".
	$sqlShipInv = "SELECT ship_id, inv_id FROM (
		SELECT ee.fk_source AS ship_id, ee.fk_target AS inv_id
		FROM ".$prefix."element_element ee
		WHERE ee.sourcetype = 'shipping' AND ee.targettype = 'facture'
		UNION ALL
		SELECT ee.fk_target AS ship_id, ee.fk_source AS inv_id
		FROM ".$prefix."element_element ee
		WHERE ee.sourcetype = 'facture' AND ee.targettype = 'shipping'
	) t";
	$res = $db->query($sqlShipInv);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$shipId = (int) $obj->ship_id;
			$invId = (int) $obj->inv_id;
			if (isset($shipRefById[$shipId]) && isset($invRefById[$invId])) {
				$shipToInv[$shipId][$invId] = true;
			}
		}
		$db->free($res);
	}

	// Build inverse mapping: invoice -> shipments (direct via element_element bidirectional map).
	foreach ($shipToInv as $shipId => $invIds) {
		foreach ($invIds as $invId => $_) {
			$invToShip[(int) $invId][(int) $shipId] = true;
		}
	}

	// SO <-> Invoice (commande <-> facture)
	$sqlSoInv = "SELECT so_id, inv_id FROM (
		SELECT ee.fk_source AS so_id, ee.fk_target AS inv_id
		FROM ".$prefix."element_element ee
		INNER JOIN ".$prefix."commande c ON c.rowid = ee.fk_source AND c.entity IN (".$eCommande.") AND c.fk_statut NOT IN (0, 3)
		INNER JOIN ".$prefix."facture f ON f.rowid = ee.fk_target AND f.entity IN (".$eInvoice.") AND f.fk_statut NOT IN (0, 3) AND f.ref IS NOT NULL AND f.ref <> ''
		WHERE ee.sourcetype = 'commande' AND ee.targettype = 'facture'
		UNION ALL
		SELECT ee.fk_target AS so_id, ee.fk_source AS inv_id
		FROM ".$prefix."element_element ee
		INNER JOIN ".$prefix."commande c ON c.rowid = ee.fk_target AND c.entity IN (".$eCommande.") AND c.fk_statut NOT IN (0, 3)
		INNER JOIN ".$prefix."facture f ON f.rowid = ee.fk_source AND f.entity IN (".$eInvoice.") AND f.fk_statut NOT IN (0, 3) AND f.ref IS NOT NULL AND f.ref <> ''
		WHERE ee.sourcetype = 'facture' AND ee.targettype = 'commande'
	) t";
	$res = $db->query($sqlSoInv);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$soId = (int) $obj->so_id;
			$invId = (int) $obj->inv_id;
			if (isset($soRefById[$soId]) && isset($invRefById[$invId])) {
				$soToInv[$soId][$invId] = true;
				$invToSo[$invId][$soId] = true;
			}
		}
		$db->free($res);
	}

	// PO <-> PO Invoice (order_supplier <-> invoice_supplier / facture_fourn)
	$sqlPoInv = "SELECT po_id, po_inv_id FROM (
		SELECT ee.fk_source AS po_id, ee.fk_target AS po_inv_id
		FROM ".$prefix."element_element ee
		INNER JOIN ".$prefix."commande_fournisseur po ON po.rowid = ee.fk_source AND po.entity IN (".$eSupplierOrder.") AND po.fk_statut NOT IN (0, 6, 7, 9)
		INNER JOIN ".$prefix."facture_fourn ff ON ff.rowid = ee.fk_target AND ff.entity IN (".$eSupplierInvoice.") AND ff.fk_statut NOT IN (0, 3) AND ff.ref IS NOT NULL AND ff.ref <> ''
		WHERE ee.sourcetype = 'order_supplier' AND (ee.targettype = 'invoice_supplier' OR ee.targettype = 'facture_fourn')
		UNION ALL
		SELECT ee.fk_target AS po_id, ee.fk_source AS po_inv_id
		FROM ".$prefix."element_element ee
		INNER JOIN ".$prefix."commande_fournisseur po ON po.rowid = ee.fk_target AND po.entity IN (".$eSupplierOrder.") AND po.fk_statut NOT IN (0, 6, 7, 9)
		INNER JOIN ".$prefix."facture_fourn ff ON ff.rowid = ee.fk_source AND ff.entity IN (".$eSupplierInvoice.") AND ff.fk_statut NOT IN (0, 3) AND ff.ref IS NOT NULL AND ff.ref <> ''
		WHERE (ee.sourcetype = 'invoice_supplier' OR ee.sourcetype = 'facture_fourn') AND ee.targettype = 'order_supplier'
	) t";
	$res = $db->query($sqlPoInv);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$poId = (int) $obj->po_id;
			$poInvId = (int) $obj->po_inv_id;
			if (isset($poRefById[$poId]) && isset($poInvRefById[$poInvId])) {
				$poInvToPo[$poInvId][$poId] = true;
			}
		}
		$db->free($res);
	}

	// Contacts:
	$soSalesPeople = array(); // [so_id][person_id] = true
	$soBilltoPeople = array();
	$invBilltoPeople = array(); // [inv_id][person_id]
	$shipBilltoPeople = array(); // [ship_id][person_id] = true
	$shipCustomerShipContactCnt = array(); // [ship_id] => int(0..n)
	$soConsigneeShipContactPeople = array(); // (legacy) not used when counting without fk_socpeople dedup
	$soConsigneeShipContactCnt = array(); // [so_id] => int(0..n)
	$poPurchasePeople = array(); // [po_id][person_id]

	// SO sales rep(s)
	$sqlSoSales = "SELECT ec.element_id AS so_id, ec.fk_socpeople AS person_id
		FROM ".$prefix."element_contact ec
		INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
		INNER JOIN ".$prefix."commande c ON c.rowid = ec.element_id AND c.entity IN (".$eCommande.") AND c.fk_statut NOT IN (0, 3)
		WHERE tc.element = 'commande' AND tc.source = 'internal' AND tc.code = 'SALESREPFOLL' AND tc.active = 1";
	$res = $db->query($sqlSoSales);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$soId = (int) $obj->so_id;
			$personId = (int) $obj->person_id;
			if (isset($soRefById[$soId])) {
				$soSalesPeople[$soId][$personId] = true;
			}
		}
		$db->free($res);
	}

	// SO bill-to contact(s)
	$sqlSoBillto = "SELECT ec.element_id AS so_id, ec.fk_socpeople AS person_id
		FROM ".$prefix."element_contact ec
		INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
		INNER JOIN ".$prefix."commande c ON c.rowid = ec.element_id AND c.entity IN (".$eCommande.") AND c.fk_statut NOT IN (0, 3)
		WHERE tc.element = 'commande' AND tc.source = 'external' AND tc.code = 'BILLING' AND tc.active = 1";
	$res = $db->query($sqlSoBillto);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$soId = (int) $obj->so_id;
			$personId = (int) $obj->person_id;
			if (isset($soRefById[$soId])) {
				$soBilltoPeople[$soId][$personId] = true;
			}
		}
		$db->free($res);
	}

	// Invoice bill-to contact(s) for customer invoices (facture)
	$sqlInvBillto = "SELECT ec.element_id AS inv_id, ec.fk_socpeople AS person_id
		FROM ".$prefix."element_contact ec
		INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
		INNER JOIN ".$prefix."facture f ON f.rowid = ec.element_id AND f.entity IN (".$eInvoice.") AND f.fk_statut NOT IN (0, 3)
		WHERE tc.element = 'facture' AND tc.source = 'external' AND tc.code = 'BILLING' AND tc.active = 1";
	$res = $db->query($sqlInvBillto);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$invId = (int) $obj->inv_id;
			$personId = (int) $obj->person_id;
			if (isset($invRefById[$invId])) {
				$invBilltoPeople[$invId][$personId] = true;
			}
		}
		$db->free($res);
	}

	// Shipment Customer invoice contact(s) (consignee invoice = shipment BILLING contact).
	// We intentionally use tc.element = 'shipping' (and sometimes 'expedition') so it matches expedition card context.
	if (!empty($shipRefById)) {
		$shipIds = array_map(static function ($id) { return (int) $id; }, array_keys($shipRefById));
		$shipIdsCsv = implode(',', $shipIds);
		if ($shipIdsCsv !== '') {
			$sqlShipBillto = "SELECT ec.element_id AS ship_id, ec.fk_socpeople AS person_id
				FROM ".$prefix."element_contact ec
				INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
				WHERE ec.element_id IN (".$shipIdsCsv.")
					AND tc.source = 'external'
					AND tc.code = 'BILLING'
					AND tc.active = 1
					AND tc.element IN ('shipping','expedition')";
			$res = $db->query($sqlShipBillto);
			if ($res) {
				while ($obj = $db->fetch_object($res)) {
					$shipId = (int) $obj->ship_id;
					$personId = (int) $obj->person_id;
					$shipBilltoPeople[$shipId][$personId] = true;
				}
				$db->free($res);
			}
		}
	}

	// Shipment Customer shipping contact(s) (consignee shipment).
	// In core Dolibarr, c_type_contact for expedition/shipping uses tc.element='shipping' and tc.code='CUSTOMER'.
	if (!empty($shipRefById)) {
		$shipIds = array_map(static function ($id) { return (int) $id; }, array_keys($shipRefById));
		$shipIdsCsv = implode(',', $shipIds);
		if ($shipIdsCsv !== '') {
			$sqlShipCustomerShipping = "SELECT ec.element_id AS ship_id
				FROM ".$prefix."element_contact ec
				INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
				WHERE ec.element_id IN (".$shipIdsCsv.")
					AND tc.source = 'external'
					AND tc.code = 'CUSTOMER'
					AND tc.element = 'shipping'";
			$res = $db->query($sqlShipCustomerShipping);
			if ($res) {
				while ($obj = $db->fetch_object($res)) {
					$shipId = (int) $obj->ship_id;
					$shipCustomerShipContactCnt[$shipId] = (int) ($shipCustomerShipContactCnt[$shipId] ?? 0) + 1;
				}
				$db->free($res);
			}
		}
	}

	// Purchase person(s) for PO (order_supplier)
	$sqlPoPurchase = "SELECT ec.element_id AS po_id, ec.fk_socpeople AS person_id
		FROM ".$prefix."element_contact ec
		INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
		INNER JOIN ".$prefix."commande_fournisseur po ON po.rowid = ec.element_id AND po.entity IN (".$eSupplierOrder.") AND po.fk_statut NOT IN (0, 6, 7, 9)
		WHERE tc.element = 'order_supplier' AND tc.source = 'internal' AND tc.code = 'SALESREPFOLL' AND tc.active = 1";
	$res = $db->query($sqlPoPurchase);
	if ($res) {
		while ($obj = $db->fetch_object($res)) {
			$poId = (int) $obj->po_id;
			$personId = (int) $obj->person_id;
			if (isset($poRefById[$poId])) {
				$poPurchasePeople[$poId][$personId] = true;
			}
		}
		$db->free($res);
	}

	// Customer shipping contact count for SO (SO contacts)
	// Use element_contact + c_type_contact where:
	// tc.element='commande', tc.code='SHIPPING', tc.source='external'
	if (!empty($soRefById)) {
		$soIds = array_map(static function ($id) { return (int) $id; }, array_keys($soRefById));
		$soIdsCsv = implode(',', $soIds);
		if ($soIdsCsv !== '') {
			$sqlSoCustomerShipContact = "SELECT ec.element_id AS so_id, ec.fk_socpeople AS person_id
				FROM ".$prefix."element_contact ec
				INNER JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
				WHERE ec.element_id IN (".$soIdsCsv.")
					AND tc.element = 'commande'
					AND tc.code = 'SHIPPING'
					AND tc.source = 'external'
					AND ec.fk_socpeople IS NOT NULL";
			$res = $db->query($sqlSoCustomerShipContact);
			if ($res) {
				// Count raw contact rows (no fk_socpeople dedup).
				while ($obj = $db->fetch_object($res)) {
					$soId = (int) $obj->so_id;
					if (!isset($soRefById[$soId])) continue;
					$soConsigneeShipContactCnt[$soId] = (int) ($soConsigneeShipContactCnt[$soId] ?? 0) + 1;
				}
				$db->free($res);
			}
		}
	}

	// ---------------------------
	// 1) SO validations
	// ---------------------------
	foreach ($soRefById as $soId => $soRef) {
		$poCnt = isset($soToPo[$soId]) ? count($soToPo[$soId]) : 0;
		$salesCnt = isset($soSalesPeople[$soId]) ? count($soSalesPeople[$soId]) : 0;
		$billtoCnt = isset($soBilltoPeople[$soId]) ? count($soBilltoPeople[$soId]) : 0;
		$shipmentCnt = isset($soToShip[$soId]) ? count($soToShip[$soId]) : 0;
		$consigneeCnt = isset($soConsigneeShipContactCnt[$soId]) ? (int) $soConsigneeShipContactCnt[$soId] : 0;

		if ($poCnt > 1) $pushRef($errors['so_details']['po_cnt_gt_1'], $soRef);
		if ($salesCnt !== 1) $pushRef($errors['so_details']['salesperson_cnt_ne_1'], $soRef);
		if ($billtoCnt > 1) $pushRef($errors['so_details']['billto_cnt_gt_1'], $soRef);
		if ($shipmentCnt > 1) $pushRef($errors['so_details']['shipment_cnt_gt_1'], $soRef);
		if ($consigneeCnt > 1) $pushRef($errors['so_details']['consignee_ship_contact_cnt_gt_1'], $soRef);
	}

	// ---------------------------
	// 2) SO Inv validations
	// ---------------------------
	foreach ($invRefById as $invId => $invRef) {
		$linkedSoIds = isset($invToSo[$invId]) ? array_keys($invToSo[$invId]) : array();
		$soCnt = count($linkedSoIds);

		// Shipment count based on SO Inv <-> shipment bidirectional mapping.
		$shipmentCnt = isset($invToShip[$invId]) ? count($invToShip[$invId]) : 0;

		$billtoCnt = isset($invBilltoPeople[$invId]) ? count($invBilltoPeople[$invId]) : 0;

		if ($soCnt > 1) $pushRef($errors['so_inv_details']['so_cnt_gt_1'], $invRef);
		if ($shipmentCnt > 1) $pushRef($errors['so_inv_details']['shipment_cnt_gt_1'], $invRef);
		if ($billtoCnt > 1) $pushRef($errors['so_inv_details']['billto_cnt_gt_1'], $invRef);
	}

	// ---------------------------
	// 3) Shipment validations
	// ---------------------------
	foreach ($shipRefById as $shipId => $shipRef) {
		// Use bidirectional element_element mapping (commande <-> shipping).
		$linkedSoIds = isset($shipToSo[$shipId]) ? array_keys($shipToSo[$shipId]) : array();
		$soCnt = count($linkedSoIds);
		if ($soCnt !== 1) $pushRef($errors['shipment_details']['so_cnt_ne_1'], $shipRef);

		// For invoice/bill-to linkage checks, only apply to shipments that are not yet arrived:
		// SailingStatusId != 3/4 (see SailingStatusId in llx_expedition_extrafields).
		$sailingStatusId = $shipSailingStatusId[$shipId] ?? null;
		$isArrived = in_array($sailingStatusId, array(3, 4), true);

		// Invoices associated directly with this shipment.
		if (!$isArrived) {
			$invSet = isset($shipToInv[$shipId]) ? $shipToInv[$shipId] : array();
			$invoiceCnt = count($invSet);
			if ($invoiceCnt > 1) $pushRef($errors['shipment_details']['invoice_cnt_gt_1'], $shipRef);

			// Bill-to contacts on the shipment itself (Customer invoice contact for shipment).
			$billtoCnt = isset($shipBilltoPeople[$shipId]) ? count($shipBilltoPeople[$shipId]) : 0;
			if ($billtoCnt > 1) $pushRef($errors['shipment_details']['billto_cnt_gt_1'], $shipRef);

			// Customer shipping contact(s) (consignee shipment):
			// Use shipment's own contacts (no linked-SO aggregation).
			$customerShipContactCnt = (int) ($shipCustomerShipContactCnt[$shipId] ?? 0);
			if ($customerShipContactCnt > 1) $pushRef($errors['shipment_details']['customer_ship_contact_cnt_gt_1'], $shipRef);
		}
	}

	// ---------------------------
	// 4) PO validations
	// ---------------------------
	foreach ($poRefById as $poId => $poRef) {
		$linkedSoIds = isset($poToSo[$poId]) ? array_keys($poToSo[$poId]) : array();
		$soCnt = count($linkedSoIds);

		$personCnt = isset($poPurchasePeople[$poId]) ? count($poPurchasePeople[$poId]) : 0;

		if ($soCnt > 1) $pushRef($errors['po_details']['so_cnt_gt_1'], $poRef);
		if ($personCnt !== 1) $pushRef($errors['po_details']['purchase_cnt_ne_1'], $poRef);
	}

	// ---------------------------
	// 5) PO Inv validations
	// ---------------------------
	foreach ($poInvRefById as $poInvId => $poInvRef) {
		$linkedPoIds = isset($poInvToPo[$poInvId]) ? array_keys($poInvToPo[$poInvId]) : array();
		$poCnt = count($linkedPoIds);

		$personSet = array();
		foreach ($linkedPoIds as $poId) {
			if (isset($poPurchasePeople[$poId])) {
				foreach ($poPurchasePeople[$poId] as $personId => $_) {
					$personSet[$personId] = true;
				}
			}
		}
		$purchaseCnt = count($personSet);

		if ($poCnt > 1) $pushRef($errors['po_inv_details']['po_cnt_gt_1'], $poInvRef);
	}

	// Convert inner sets to arrays of refs.
	foreach ($errors as $dataset => $rules) {
		foreach ($rules as $rule => $set) {
			if (is_array($set)) {
				$errors[$dataset][$rule] = array_keys($set);
			}
		}
	}

	return $errors;
}

$slyErrors = slyAllInOneValidateV2($db, $langs, $prefix, $eCommande, $eInvoice, $eExpedition, $eSupplierOrder, $eSupplierInvoice);
$hasErrors = false;
foreach ($slyErrors as $dataset => $rules) {
	foreach ($rules as $rule => $refs) {
		if (!empty($refs)) { $hasErrors = true; break 2; }
	}
}

$confirm = (int) GETPOST('confirm', 'int');

$activeTab = 'export_all';

require_once __DIR__.'/tools.datasets.php';
require_once __DIR__.'/sly_export_tabs.lib.php';
$slyExportDatasetsForTabs = getSlyExportDatasets();

if ($confirm !== 1) {
	llxHeader('', $langs->trans('SLYAllInOneCheckHeader'), 'EN:Automatic_Reports_En|FR:Automatic_Reports');
	slyExportsPrintTabNavigation($langs, $slyExportDatasetsForTabs, $activeTab, $exportBase);
	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print '<h1>'.$langs->trans("SLYExportAllInOne").'</h1>';
	print '<div style="max-width:900px;margin:0 auto 10px auto;">';
	print '<b>'.$langs->trans('SLYAllInOneCheckTitle').'</b><br>';
	print '<span style="opacity:0.9;">'.$langs->trans('SLYAllInOneCheckHelp').'</span>';
	print '</div>';

	$renderRule = static function ($datasetKey, $label, array $refs) use ($langs) {
		if (empty($refs)) {
			print '<li style="margin:6px 0;"><b>'.$label.':</b> <span style="color:#28a745;"><b>'.$langs->trans('SLYAllInOnePassedNone').'</b></span></li>';
			return;
		}
		$cnt = count($refs);
		sort($refs);
		print '<li style="margin:6px 0;"><b>'.$label.':</b> <span style="color:#dc3545;"><b>'.$cnt.'</b></span> ';
		$parts = array();
		foreach ($refs as $ref) {
			$ref = (string) $ref;
			$url = '';
			if ($datasetKey === 'so_details') {
				$url = DOL_URL_ROOT.'/commande/list.php?search_ref='.urlencode($ref);
			} elseif ($datasetKey === 'so_inv_details') {
				$url = DOL_URL_ROOT.'/compta/facture/list.php?search_ref='.urlencode($ref);
			} elseif ($datasetKey === 'po_details') {
				$url = DOL_URL_ROOT.'/fourn/commande/list.php?search_ref='.urlencode($ref);
			} elseif ($datasetKey === 'po_inv_details') {
				$url = DOL_URL_ROOT.'/fourn/facture/list.php?search_ref='.urlencode($ref);
			} elseif ($datasetKey === 'shipment_details') {
				$url = DOL_URL_ROOT.'/expedition/list.php?search_ref='.urlencode($ref);
			}
			if (!empty($url)) {
				$parts[] = '<a href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($ref).'</a>';
			} else {
				$parts[] = dol_escape_htmltag($ref);
			}
		}
		print '<span>'.implode(', ', $parts).'</span>';
		print '</li>';
	};

	$renderDataset = static function ($datasetKey, $title, array $rulesMap) use ($renderRule) {
		print '<div style="max-width:900px;margin:14px auto 0 auto;">';
		print '<h3 style="margin:0 0 8px 0;">'.$title.'</h3>';
		print '<ul style="margin:0;padding-left:20px;">';
		foreach ($rulesMap as $label => $refs) {
			$renderRule($datasetKey, $label, $refs);
		}
		print '</ul>';
		print '</div>';
	};

	$renderDataset('so_details', $langs->trans('SLYExportSODetails'), array(
		$langs->trans('SLYAllInOneRule_SO_PoCntGt1') => $slyErrors['so_details']['po_cnt_gt_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_SO_SalespersonNe1') => $slyErrors['so_details']['salesperson_cnt_ne_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_SO_BillToCntGt1') => $slyErrors['so_details']['billto_cnt_gt_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_SO_ShipmentCntGt1') => $slyErrors['so_details']['shipment_cnt_gt_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_SO_ConsigneeShipContactCntGt1') => $slyErrors['so_details']['consignee_ship_contact_cnt_gt_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_SQL_ERROR') => $slyErrors['so_details']['__sql_error__'] ?? array(),
	));

	$renderDataset('so_inv_details', $langs->trans('SLYExportSOInvoiceDetails'), array(
		$langs->trans('SLYAllInOneRule_SOInv_SoCntGt1') => $slyErrors['so_inv_details']['so_cnt_gt_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_SOInv_ShipmentCntGt1') => $slyErrors['so_inv_details']['shipment_cnt_gt_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_SOInv_BillToCntGt1') => $slyErrors['so_inv_details']['billto_cnt_gt_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_SQL_ERROR') => $slyErrors['so_inv_details']['__sql_error__'] ?? array(),
	));

	$renderDataset('shipment_details', $langs->trans('SLYExportShipmentDetails'), array(
		$langs->trans('SLYAllInOneRule_Shipment_SoCntNe1') => $slyErrors['shipment_details']['so_cnt_ne_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_Shipment_BillToCntGt1') => $slyErrors['shipment_details']['billto_cnt_gt_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_Shipment_CustomerShipContactCntGt1') => $slyErrors['shipment_details']['customer_ship_contact_cnt_gt_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_Shipment_InvoiceCntGt1') => $slyErrors['shipment_details']['invoice_cnt_gt_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_SQL_ERROR') => $slyErrors['shipment_details']['__sql_error__'] ?? array(),
	));

	$renderDataset('po_details', $langs->trans('SLYExportPODetails'), array(
		$langs->trans('SLYAllInOneRule_PO_SoCntGt1') => $slyErrors['po_details']['so_cnt_gt_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_PO_PurchasePersonCntNe1') => $slyErrors['po_details']['purchase_cnt_ne_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_SQL_ERROR') => $slyErrors['po_details']['__sql_error__'] ?? array(),
	));

	$renderDataset('po_inv_details', $langs->trans('SLYExportPOInvoiceDetails'), array(
		$langs->trans('SLYAllInOneRule_POInv_PoCntGt1') => $slyErrors['po_inv_details']['po_cnt_gt_1'] ?? array(),
		$langs->trans('SLYAllInOneRule_SQL_ERROR') => $slyErrors['po_inv_details']['__sql_error__'] ?? array(),
	));

	if (!$hasErrors) {
		print '<div style="max-width:900px;margin:18px auto 0 auto;text-align:center;">';
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?mainmenu=tools&leftmenu=sly_export_invoices&tab=export_all&confirm=1">'.$langs->trans('SLYAllInOneConfirmStart').'</a>';
		print '</div>';
	} else {
		print '<div style="max-width:900px;margin:18px auto 0 auto;text-align:center;">';
		print '<span style="color:#dc3545;font-weight:bold;">'.$langs->trans('SLYAllInOneHasErrors').'</span>';
		print '</div>';
	}

	print '</div>';
	llxFooter();
	$db->close();
	exit;
}

if ($hasErrors) {
	// Confirmed but still has errors.
	llxHeader('', $langs->trans("SLYExportAllInOne"), 'EN:Automatic_Reports_En|FR:Automatic_Reports');
	slyExportsPrintTabNavigation($langs, $slyExportDatasetsForTabs, $activeTab, $exportBase);
	print '<div class="fichecenter">';
	print '<div class="error">'.$langs->trans("Error").': '.$langs->trans('SLYAllInOneValidationFailed').'.</div>';
	print '</div>';
	llxFooter();
	$db->close();
	exit;
}

$report_files = array(
	$langs->trans("SLYExportSODetails") => $exportBase.'?mainmenu=tools&leftmenu=sly_export_invoices&tab=so_details&action=download_csv&dataset=so_details&download_all=1',
	$langs->trans("SLYExportSOInvoiceDetails") => $exportBase.'?mainmenu=tools&leftmenu=sly_export_invoices&tab=so_inv_details&action=download_csv&dataset=so_inv_details&download_all=1',
	$langs->trans("SLYExportShipmentDetails") => $exportBase.'?mainmenu=tools&leftmenu=sly_export_invoices&tab=shipment_details&action=download_csv&dataset=shipment_details&download_all=1',
	$langs->trans("SLYExportPODetails") => $exportBase.'?mainmenu=tools&leftmenu=sly_export_invoices&tab=po_details&action=download_csv&dataset=po_details&download_all=1',
	$langs->trans("SLYExportPOInvoiceDetails") => $exportBase.'?mainmenu=tools&leftmenu=sly_export_invoices&tab=po_inv_details&action=download_csv&dataset=po_inv_details&download_all=1',
);
$js_report_files = json_encode($report_files, JSON_UNESCAPED_UNICODE);

llxHeader('', $langs->trans("SLYExportAllInOne"), 'EN:Automatic_Reports_En|FR:Automatic_Reports');

slyExportsPrintTabNavigation($langs, $slyExportDatasetsForTabs, $activeTab, $exportBase);
print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<div class="download-container" style="text-align: center;">';
print '<h1>'.$langs->trans("Downloading").' XLS...</h1>';
print '<p>'.$langs->trans("Your browser will now prompt you to download the following files one by one:").'</p>';
print '<div style="max-width: 400px; margin: 10px auto 0 auto;">';
print '<div style="background:#eee;height:18px;border-radius:4px;overflow:hidden;">';
print '<div id="progress-bar" style="background:#28a745;height:18px;width:0%;transition:width 0.3s;"></div>';
print '</div>';
print '<p id="progress-text" style="margin-top:8px;">0 / '.count($report_files).'</p>';
print '</div>';
print '<ul id="file-list" style="list-style: none; padding: 0; margin: 20px auto; max-width: 400px; text-align: left;"></ul>';
print '<p id="status" style="margin-top: 20px; font-style: italic;"></p>';
print '</div>';
print '</div>';

llxFooter();
$db->close();
?>
<script>
    const reportFiles = <?php echo $js_report_files; ?>;
    const fileListElement = document.getElementById('file-list');
    const statusElement = document.getElementById('status');
    let fileIndex = 0;
    const fileKeys = Object.keys(reportFiles);
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');

    const fileListItems = [];
    fileKeys.forEach(function(displayName) {
        const listItem = document.createElement('li');
        listItem.textContent = displayName;
        listItem.style.color = 'black';
        fileListElement.appendChild(listItem);
        fileListItems.push(listItem);
    });

    function updateProgress(doneCount) {
        const total = fileKeys.length;
        const percent = total > 0 ? Math.round((doneCount / total) * 100) : 0;
        progressBar.style.width = percent + '%';
        progressText.textContent = doneCount + ' / ' + total;
    }

    function downloadFile(fileUrl) {
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = fileUrl;
        document.body.appendChild(iframe);
    }

    function startDownloads() {
        if (fileIndex < fileKeys.length) {
            if (fileIndex > 0) {
                fileListItems[fileIndex - 1].style.color = '#28a745';
                updateProgress(fileIndex);
            }
            const displayName = fileKeys[fileIndex];
            const fileName = reportFiles[displayName];
            statusElement.textContent = <?php echo json_encode($langs->trans('SLYAllInOnePreparingDownloadFor')); ?> + displayName;
            statusElement.style.color = '#dc3545';
            fileListItems[fileIndex].style.color = '#ffc107';
            downloadFile(fileName);
            fileIndex++;
            setTimeout(startDownloads, 2500);
        } else {
            if (fileIndex > 0) fileListItems[fileIndex - 1].style.color = '#28a745';
            updateProgress(fileKeys.length);
            statusElement.textContent = <?php echo json_encode($langs->trans('SLYAllInOneAllDownloadsInitiated')); ?>;
            statusElement.style.color = '#28a745';
        }
    }
    window.onload = startDownloads;
</script>
