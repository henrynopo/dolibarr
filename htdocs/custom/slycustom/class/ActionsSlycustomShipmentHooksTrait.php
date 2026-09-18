<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: SLY Custom shipment/expedition hooks (ShipsGo).
 *
 * Extracted from actions_slycustom.class.php to reduce file size.
 * Hook method names and signatures are kept identical to preserve behavior.
 */
trait ActionsSlycustomShipmentHooksTrait
{
	/**
	 * Add mass actions into selectMassAction().
	 *
	 * @param array        $parameters  Hook parameters (currentcontext, ...)
	 * @param CommonObject $object      Context object
	 * @param string       $action      Current action
	 * @param HookManager  $hookmanager Hook manager
	 * @return int
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;

		if (empty($parameters['currentcontext']) || !in_array($parameters['currentcontext'], array('shipmentlist'), true)) {
			return 0;
		}
		if (!isModEnabled('slycustom') || !$user->hasRight('expedition', 'creer')) {
			return 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_Update.class.php';
		if (!ShipmentStatus::hasApiKeyInCurrentContext($this->db, $conf)) {
			return 0;
		}

		$langs->load("slycustom@slycustom");
		$this->resprints = '<option value="updateships" data-html="'.dol_escape_htmltag(img_picto('', 'calendar', 'class="pictofixedwidth"').$langs->trans("UpdateShips")).'">'.img_picto('', 'calendar', 'class="pictofixedwidth"').$langs->trans("UpdateShips").'</option>';
		return 0;
	}

	/**
	 * Get carrier code from c_shipment_mode for a given shipping_method_id.
	 *
	 * @param int $shippingMethodId  Shipping method rowid
	 * @return string Carrier code or empty string
	 */
	protected function getCarrierCode($shippingMethodId)
	{
		$code = '';
		$sqlsm = "SELECT code FROM ".MAIN_DB_PREFIX."c_shipment_mode WHERE rowid = ".(int) $shippingMethodId;
		$resm = $this->db->query($sqlsm);
		if ($resm && $rowm = $this->db->fetch_object($resm)) {
			$code = $rowm->code ?? '';
		}
		return $code;
	}

	/**
	 * Expedition card actions: updateships, confirm_modif
	 *
	 * @param array      $parameters Hook parameters
	 * @param Expedition $object     Object
	 * @param string     $action     Current action
	 * @return int
	 */
	protected function doActionsExpeditionCard($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;

		$id = GETPOSTINT('id');
		if ($id <= 0) {
			return 0;
		}

		// SLY ShipsGo: action=updateships - create or update shipment status from API
		if ($action == 'updateships' && isModEnabled('slycustom') && $user->hasRight('expedition', 'creer')) {
			// Enforce CSRF token check even if MAIN_SECURITY_CSRF_WITH_TOKEN is not strict enough.
			$token = GETPOST('token', 'alpha');
			if (empty($token) || $token !== currentToken()) {
				accessforbidden('Invalid CSRF token');
			}
			require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_Update.class.php';
			require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_API.class.php';
			$object->fetch($id);
			$apiKey = ShipmentStatus::getApiKeyForExpedition($this->db, $object, $conf);
			if ($object->status > 0 && !empty($object->tracking_number) && $apiKey !== '') {
				$shipsGo = new ShipsGo_API($apiKey);
				$sqlef = "SELECT requestid, blno, sailingstatusid, updatedtime FROM ".MAIN_DB_PREFIX."expedition_extrafields WHERE fk_object = ".(int) $object->id;
				$resef = $this->db->query($sqlef);
				$requestid = '';
				$blno = '';
				$current_status = 0;
				$updatedtime = '';
				if ($resef && $rowef = $this->db->fetch_object($resef)) {
					$requestid = (string) ($rowef->requestid ?? '');
					$blno = (string) ($rowef->blno ?? '');
					$current_status = (int) $rowef->sailingstatusid;
					$updatedtime = $rowef->updatedtime ?? '';
				}
				$langs->load("slycustom@slycustom");

				if (empty($requestid)) {
					// requestid is empty: create shipment on ShipsGo
					$object->fetchObjectLinked();
					$so_ref = '';
					if (!empty($object->linkedObjects['commande'])) {
						$lastOrder = end($object->linkedObjects['commande']);
						$so_ref = $lastOrder->ref ?? '';
					}
					$ShippingLine = $this->getCarrierCode($object->shipping_method_id);
					$email = !empty($user->email) ? $user->email : '';
					$Referance = $so_ref ? ($so_ref.' / '.$object->ref) : $object->ref;

					if (!empty($ShippingLine) && !empty($Referance)) {
						if (!empty($blno)) {
							$ship_result = $shipsGo->createShipmentWithBl($object->tracking_number, $blno, $ShippingLine, $email ? array($email) : array(), $Referance);
						} else {
							$ship_result = $shipsGo->createShipment($object->tracking_number, $ShippingLine, $email ? array($email) : array(), $Referance);
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
							setEventMessages($langs->trans("ShipsGoShipmentCreated", $shipId), null, 'mesgs');
						} else {
							$errMsg = $ship_result['message'] ?? ($ship_result['error'] ?? 'Unknown error');
							setEventMessages('ShipsGo create error: '.$errMsg, null, 'errors');
						}
					} else {
						setEventMessages($langs->trans("ShipsGoMissingInfo"), null, 'warnings');
					}
				} else {
					// requestid exists: update status (skip if already arrived/discharged)
					$isV1 = empty($updatedtime) || ($updatedtime < '2026-05-19 12:00:00');
					$skipStatus = $isV1 ? array(3, 4) : array(5, 6);
					if (!in_array($current_status, $skipStatus)) {
						$ship_status_list = $shipsGo->getContainerInfo($object->tracking_number);
						$rawData = $ship_status_list;
						if (!isset($ship_status_list['error'])) {
							$ship_status = $shipsGo->normalizeResponse($ship_status_list);
							if (!empty($ship_status['Success'])) {
								$updatesql = "UPDATE ".MAIN_DB_PREFIX."expedition_extrafields SET";
								$updatesql .= " sailingstatusid = ".(int) ($ship_status['SailingStatusId'] ?? 0);
								$updatesql .= ", pol = '".$this->db->escape($ship_status['Pol'] ?? '')."'";
								// ETD and ATD
								if (!empty($ship_status['Etd'])) {
									$etaTs = strtotime(str_replace('/', '-', $ship_status['Etd']));
									if ($etaTs !== false) {
										$updatesql .= ", etd = '".$this->db->escape(date('Y-m-d', $etaTs))."'";
									}
								}
								if (!empty($ship_status['Atd'])) {
									$etaTs = strtotime(str_replace('/', '-', $ship_status['Atd']));
									if ($etaTs !== false) {
										$updatesql .= ", atd = '".$this->db->escape(date('Y-m-d', $etaTs))."'";
									}
								}
								$updatesql .= ", pod = '".$this->db->escape($ship_status['Pod'] ?? '')."'";
								// ATA and ETA
								if (!empty($ship_status['Ata'])) {
									$etaTs = strtotime(str_replace('/', '-', $ship_status['Ata']));
									if ($etaTs !== false) {
										$updatesql .= ", ata = '".$this->db->escape(date('Y-m-d', $etaTs))."'";
									}
								}
								if (!empty($ship_status['Eta'])) {
									$etaTs = strtotime(str_replace('/', '-', $ship_status['Eta']));
									if ($etaTs !== false) {
										$updatesql .= ", eta = '".$this->db->escape(date('Y-m-d', $etaTs))."'";
									}
								}
								$mapUrl = $ship_status['MapUrl'] ?? '';
								if (!empty($mapUrl)) {
									$updatesql .= ", livemapurl = '".$this->db->escape($mapUrl)."'";
								}
								$updatesql .= ", updatedtime = '".$this->db->idate(dol_now())."'";
								$updatesql .= " WHERE fk_object = ".(int) $object->id;
								$this->db->query($updatesql);
								setEventMessages($langs->trans("ShipmentUpdated"), null, 'mesgs');
							} else {
								setEventMessages('ShipsGo: '.$rawData['Message'] ?? 'No data returned', null, 'warnings');
							}
						} else {
							setEventMessages('ShipsGo API error: '.$ship_status_list['error'] ?? 'Unknown', null, 'errors');
						}
					}
				}
				header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
				exit;
			}
		}

		// SLY: action=confirm_modif - unvalidate shipment (set back to draft)
		if ($action == 'confirm_modif' && GETPOST('confirm') == 'yes' && getDolGlobalString('SLYCUSTOM_SHIPPING_ENABLE_UNVALIDATE', 1)
			&& $user->hasRight('expedition', 'creer')
			&& (!getDolGlobalString('MAIN_USE_ADVANCED_PERMS') || $user->hasRight('expedition', 'shipping_advance', 'validate'))) {
			$object->fetch($id);
			$result = $object->setDraft($user);
			if ($result < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
			} else {
				header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
				exit;
			}
		}

		return 0;
	}

	/**
	 * Shipment list actions: massaction updateships
	 *
	 * @param array      $parameters Hook parameters
	 * @param Expedition $object     Object
	 * @param string     $action     Current action
	 * @return int
	 */
	protected function doActionsShipmentList($parameters, &$object, &$action)
	{
		global $conf, $user;

		$massaction = GETPOST('massaction', 'alpha');
		$toselect = GETPOST('toselect', 'array');

		if ($massaction === 'updateships' && isModEnabled('slycustom') && !empty($toselect) && is_array($toselect) && $user->hasRight('expedition', 'creer')) {
			// Enforce CSRF token check even if MAIN_SECURITY_CSRF_WITH_TOKEN is not strict enough.
			$token = GETPOST('token', 'alpha');
			if (empty($token) || $token !== currentToken()) {
				accessforbidden('Invalid CSRF token');
			}
			require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
			require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_Update.class.php';
			require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_API.class.php';
			$objecttmp = new Expedition($this->db);
			foreach ($toselect as $expid) {
				$result = $objecttmp->fetch((int) $expid);
				$apiKey = ($result > 0) ? ShipmentStatus::getApiKeyForExpedition($this->db, $objecttmp, $conf) : '';
				if ($result > 0 && !empty($objecttmp->tracking_number) && $apiKey !== '') {
					$shipsGotmp = new ShipsGo_API($apiKey);
					$sqlcheck = "SELECT requestid, blno, sailingstatusid, updatedtime FROM ".MAIN_DB_PREFIX."expedition_extrafields WHERE fk_object = ".(int) $expid;
					$resqlcheck = $this->db->query($sqlcheck);
					$objcheck = $resqlcheck ? $this->db->fetch_object($resqlcheck) : null;
					$requestid = $objcheck ? (string) ($objcheck->requestid ?? '') : '';
					$blno = $objcheck ? (string) ($objcheck->blno ?? '') : '';
					$current_status = $objcheck ? (int) $objcheck->sailingstatusid : 0;
					$updatedtime = $objcheck ? ($objcheck->updatedtime ?? '') : '';

					if (empty($requestid)) {
						// Create shipment on ShipsGo
						$objecttmp->fetchObjectLinked();
						$so_ref = '';
						if (!empty($objecttmp->linkedObjects['commande'])) {
							$lastOrder = end($objecttmp->linkedObjects['commande']);
							$so_ref = $lastOrder->ref ?? '';
						}
						$ShippingLine = $this->getCarrierCode($objecttmp->shipping_method_id);
						$email = !empty($user->email) ? $user->email : '';
						$Referance = $so_ref ? ($so_ref.' / '.$objecttmp->ref) : $objecttmp->ref;

						if (!empty($ShippingLine) && !empty($Referance)) {
							if (!empty($blno)) {
								$ship_result = $shipsGotmp->createShipmentWithBl($objecttmp->tracking_number, $blno, $ShippingLine, $email ? array($email) : array(), $Referance);
							} else {
								$ship_result = $shipsGotmp->createShipment($objecttmp->tracking_number, $ShippingLine, $email ? array($email) : array(), $Referance);
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
								$updatesql .= " WHERE fk_object = ".(int) $expid;
								$this->db->query($updatesql);
							}
						}
					} else {
						// Update existing shipment status
						$isV1 = empty($updatedtime) || ($updatedtime < '2026-05-19 12:00:00');
						$skipStatus = $isV1 ? array(3, 4) : array(5, 6);
						if (!in_array($current_status, $skipStatus)) {
							$ship_status_list = $shipsGotmp->getContainerInfo($objecttmp->tracking_number);
							if (!isset($ship_status_list['error'])) {
								$ship_status = $shipsGotmp->normalizeResponse($ship_status_list);
								if (!empty($ship_status['Success'])) {
									$updatesql = "UPDATE ".MAIN_DB_PREFIX."expedition_extrafields SET";
									$updatesql .= " sailingstatusid = ".(int) ($ship_status['SailingStatusId'] ?? 0);
									$updatesql .= ", pol = '".$this->db->escape($ship_status['Pol'] ?? '')."'";
									// ETD and ATD
									if (!empty($ship_status['Etd'])) {
										$etaTs = strtotime(str_replace('/', '-', $ship_status['Etd']));
										if ($etaTs !== false) {
											$updatesql .= ", etd = '".$this->db->escape(date('Y-m-d', $etaTs))."'";
										}
									}
									if (!empty($ship_status['Atd'])) {
										$etaTs = strtotime(str_replace('/', '-', $ship_status['Atd']));
										if ($etaTs !== false) {
											$updatesql .= ", atd = '".$this->db->escape(date('Y-m-d', $etaTs))."'";
										}
									}
									$updatesql .= ", pod = '".$this->db->escape($ship_status['Pod'] ?? '')."'";
									// ATA and ETA
									if (!empty($ship_status['Ata'])) {
										$etaTs = strtotime(str_replace('/', '-', $ship_status['Ata']));
										if ($etaTs !== false) {
											$updatesql .= ", ata = '".$this->db->escape(date('Y-m-d', $etaTs))."'";
										}
									}
									if (!empty($ship_status['Eta'])) {
										$etaTs = strtotime(str_replace('/', '-', $ship_status['Eta']));
										if ($etaTs !== false) {
											$updatesql .= ", eta = '".$this->db->escape(date('Y-m-d', $etaTs))."'";
										}
									}
									$mapUrl = $ship_status['MapUrl'] ?? '';
									if (!empty($mapUrl)) {
										$updatesql .= ", livemapurl = '".$this->db->escape($mapUrl)."'";
									}
									$updatesql .= ", updatedtime = '".$this->db->idate(dol_now())."'";
									$updatesql .= " WHERE fk_object = ".(int) $expid;
									$this->db->query($updatesql);
								}
							}
						}
					}
				}
			}
		}

		return 0;
	}
}
