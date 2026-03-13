<?php
/* Copyright (C) 2014-2022  Charlene BENKE  <charlene@patas-monkey.com>
 * Copyright (C) 2025  Customlink link rules (SO/PO/Shipment/Invoice)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modCustomlink_LinkRulesTriggers.class.php
 * \ingroup customlink
 * \brief   Trigger to enforce link rules when core adds a link (OBJECT_LINK_INSERT).
 *          Validates e.g. "invoice only one shipment", "shipment only one SO", etc.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 *  Class of triggers for Customlink module - link rules validation
 */
class InterfaceLinkRulesTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "Patas-Tools";
		$this->description = "Customlink: enforce link rules (e.g. one PO per SO, one SO/shipment per invoice).";
		$this->version = self::VERSION_DEVELOPMENT;
		$this->picto = 'customlink@customlink';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 * OBJECT_LINK_INSERT: core has just inserted a row into element_element; we validate and rollback if rule violated.
	 *
	 * @param string        $action Event action code
	 * @param CommonObject  $object Object (the document we're on: facture, commande, shipping, order_supplier, etc.)
	 * @param User          $user   Object user
	 * @param Translate     $langs  Object langs
	 * @param Conf          $conf   Object conf
	 * @return int                  <0 if KO (rollback), 0 if nothing done, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('customlink')) {
			return 0;
		}
		if (empty($conf->global->CUSTOMLINK_ENABLE_LINK)) {
			return 0;
		}

		if ($action !== 'OBJECT_LINK_INSERT') {
			return 0;
		}

		// When core adds a link from card of $object, the link is (source=origin, target=object)
		$link_origin = isset($object->context['link_origin']) ? $object->context['link_origin'] : '';
		$link_origin_id = isset($object->context['link_origin_id']) ? $object->context['link_origin_id'] : 0;
		if ($link_origin === '' || (int) $link_origin_id <= 0) {
			return 0;
		}

		$type_source = $object->element;
		$fk_source = isset($object->id) ? $object->id : (isset($object->rowid) ? $object->rowid : 0);
		if ((int) $fk_source <= 0) {
			return 0;
		}

		require_once DOL_DOCUMENT_ROOT.'/custom/customlink/core/lib/customlink.lib.php';
		$validation = customlink_validate_link_allowed($this->db, $type_source, (int) $fk_source, $link_origin);
		if (!$validation['ok']) {
			$langs->load("customlink@customlink");
			$msg = $langs->trans("CUSTOMLINK_LinkNotAllowedByRules");
			$object->error = $msg;
			if (!is_array($object->errors)) {
				$object->errors = array();
			}
			$object->errors[] = $msg;
			$this->error = $msg;
			$this->errors[] = $msg;
			return -1;
		}

		return 1;
	}
}
