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
 * \brief   SLY Custom triggers - ShipsGo integration on shipment validation
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
		if (!isModEnabled('slycustom') || empty($conf->global->API_KEY_SHIPSGO)) {
			return 0;
		}

		if ($action == 'SHIPPING_VALIDATE') {
			// ShipsGo: post container info when shipment is validated
			require_once DOL_DOCUMENT_ROOT.'/slycustom/class/ShipsGo_API.class.php';
			$shipsGo = new ShipsGo_API($conf->global->API_KEY_SHIPSGO);
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
			$object->fetch_delivery_methods();
			$ShippingLine = isset($object->meths[$object->shipping_method_id]) ? $object->meths[$object->shipping_method_id] : '';
			$email = !empty($user->email) ? $user->email : '';
			$Referance = $so_ref ? ($so_ref.' / '.$object->ref) : $object->ref;
			if (!empty($ContainerNumber) && !empty($ShippingLine) && !empty($Referance) && empty($requestid)) {
				if (!empty($blno)) {
					$ship_result = $shipsGo->PostContainerInfoWithBl($ContainerNumber, $blno, $ShippingLine, $email, $Referance);
				} else {
					$ship_result = $shipsGo->PostContainerInfo($ContainerNumber, $ShippingLine, $email, $Referance);
				}
				if (!empty($ship_result['RequestId'])) {
					$updatesql = "UPDATE ".MAIN_DB_PREFIX."expedition_extrafields SET";
					$updatesql .= " requestid = ".(int) $ship_result['RequestId'];
					$updatesql .= ", updatedtime = '".$this->db->idate(dol_now())."'";
					$updatesql .= " WHERE fk_object = ".(int) $object->id;
					$this->db->query($updatesql);
				}
			}
			return 1;
		}

		return 0;
	}
}
