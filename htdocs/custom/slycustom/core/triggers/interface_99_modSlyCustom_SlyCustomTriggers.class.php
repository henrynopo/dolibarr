<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/triggers/interface_99_modSlyCustom_SlyCustomTriggers.class.php
 * \ingroup slycustom
 * \brief   SLY Custom triggers - ShipsGo integration; dropshipping: link shipment to PO when created from SO.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Class of triggers for SLY Custom module
 */
class InterfaceSlyCustomTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = "slycustom";
		$this->description = "SLY Custom triggers - ShipsGo integration.";
		$this->version = self::VERSIONS['prod'];
		$this->picto = 'generic';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 *
	 * @param string       $action Event action code
	 * @param CommonObject $object Object
	 * @param User         $user   Object user
	 * @param Translate    $langs  Object langs
	 * @param Conf         $conf   Object conf
	 * @return int Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		// Dropshipping: when shipment is created from SO, link it to all POs linked to that SO
		if ($action == 'SHIPPING_CREATE' && isModEnabled('slycustom')) {
			$soid = 0;
			if (!empty($object->origin_type) && (strtolower($object->origin_type) === 'commande' || strtolower($object->origin_type) === 'order') && !empty($object->origin_id)) {
				$soid = (int) $object->origin_id;
			} elseif (!empty($object->origin) && (strtolower($object->origin) === 'commande' || strtolower($object->origin) === 'order') && !empty($object->origin_id)) {
				$soid = (int) $object->origin_id;
			}
			if ($soid > 0 && (int) $object->id > 0) {
				$prefix = $this->db->prefix();
				// Find all POs linked to this SO (both directions: SO created first then PO, or PO created first then SO)
				$poIds = array();
				$sql1 = "SELECT fk_target AS poid FROM ".$prefix."element_element WHERE fk_source = ".$soid." AND sourcetype IN ('commande','order') AND targettype IN ('order_supplier','commande_fournisseur')";
				$res1 = $this->db->query($sql1);
				if ($res1) {
					while ($r = $this->db->fetch_object($res1)) {
						if ((int) $r->poid > 0) {
							$poIds[(int) $r->poid] = true;
						}
					}
				}
				$sql2 = "SELECT fk_source AS poid FROM ".$prefix."element_element WHERE fk_target = ".$soid." AND targettype IN ('commande','order') AND sourcetype IN ('order_supplier','commande_fournisseur')";
				$res2 = $this->db->query($sql2);
				if ($res2) {
					while ($r = $this->db->fetch_object($res2)) {
						if ((int) $r->poid > 0) {
							$poIds[(int) $r->poid] = true;
						}
					}
				}
				$expid = (int) $object->id;
				foreach (array_keys($poIds) as $poid) {
					$chk = "SELECT 1 FROM ".$prefix."element_element WHERE ((fk_source = ".$expid." AND sourcetype IN ('shipping','expedition') AND fk_target = ".$poid." AND targettype IN ('order_supplier','commande_fournisseur')) OR (fk_target = ".$expid." AND targettype IN ('shipping','expedition') AND fk_source = ".$poid." AND sourcetype IN ('order_supplier','commande_fournisseur'))) LIMIT 1";
					$rchk = $this->db->query($chk);
					if ($rchk && $this->db->num_rows($rchk) > 0) {
						continue;
					}
					$this->db->query("INSERT INTO ".$prefix."element_element (fk_source, sourcetype, fk_target, targettype) VALUES (".$expid.", 'shipping', ".$poid.", 'order_supplier')");
				}
			}
			return 1;
		}

		if ($action == 'SHIPPING_VALIDATE' && isModEnabled('slycustom')) {
			// ShipsGo: post container info when shipment is validated (API key per entity / Multicompany)
			require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_Update.class.php';
			require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_API.class.php';
			$apiKey = ShipmentStatus::getApiKeyForExpedition($this->db, $object, $conf);
			if ($apiKey === '') {
				return 0;
			}
			$shipsGo = new ShipsGo_API($apiKey);
			$ContainerNumber = $object->tracking_number ?? '';
			$blno = '';
			$requestid = '';
			$sqlef = "SELECT requestid, blno FROM ".MAIN_DB_PREFIX."expedition_extrafields WHERE fk_object = ".(int) $object->id;
			$resef = $this->db->query($sqlef);
			if ($resef && $rowef = $this->db->fetch_object($resef)) {
				$requestid = (string) ($rowef->requestid ?? '');
				$blno = (string) ($rowef->blno ?? '');
			}
			$object->fetchObjectLinked();
			$so_ref = '';
			if (!empty($object->linkedObjects['commande'])) {
				$lastOrder = end($object->linkedObjects['commande']);
				$so_ref = $lastOrder->ref ?? '';
			}
			// v2 API requires carrier code from c_shipment_mode.code, not translated label from meths
			$ShippingLine = '';
			$sqlsm = "SELECT code FROM ".MAIN_DB_PREFIX."c_shipment_mode WHERE rowid = ".(int) $object->shipping_method_id;
			$resm = $this->db->query($sqlsm);
			if ($resm && $rowm = $this->db->fetch_object($resm)) {
				$ShippingLine = $rowm->code ?? '';
			}
			$email = !empty($user->email) ? $user->email : '';
			$Referance = $so_ref ? ($so_ref.' / '.$object->ref) : $object->ref;
			if (!empty($ContainerNumber) && !empty($ShippingLine) && !empty($Referance) && empty($requestid)) {
				if (!empty($blno)) {
					$ship_result = $shipsGo->createShipmentWithBl($ContainerNumber, $blno, $ShippingLine, $email ? array($email) : array(), $Referance);
				} else {
					$ship_result = $shipsGo->createShipment($ContainerNumber, $ShippingLine, $email ? array($email) : array(), $Referance);
				}
				$shipId = null;
				if (!empty($ship_result['shipment']['id'])) {
					$shipId = $ship_result['shipment']['id'];
				}

				$message = strtoupper($ship_result['message'] ?? '');

				if ($shipId && ($message === 'SUCCESS' || $message === 'ALREADY_EXISTS')) {
					$updatesql = "UPDATE ".MAIN_DB_PREFIX."expedition_extrafields SET";
					$updatesql .= " requestid = ".(int) $shipId;
					$updatesql .= ", updatedtime = '".$this->db->idate(dol_now())."'";
					$updatesql .= " WHERE fk_object = ".(int) $object->id;
					$this->db->query($updatesql);
					dol_syslog(__METHOD__.': ShipsGo shipment '.$message.' for expedition '.$object->id.', id: '.$shipId, LOG_INFO);
				} else {
					dol_syslog(__METHOD__.': ShipsGo shipment creation failed for expedition '.$object->id.'. Response: '.json_encode($ship_result), LOG_WARNING);
				}
			}
			return 1;
		}

		return 0;
	}
}
