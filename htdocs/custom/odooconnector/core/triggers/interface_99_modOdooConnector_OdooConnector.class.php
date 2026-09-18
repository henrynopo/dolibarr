<?php
/* Copyright (C) 2025  Odoo Connector (Dolibarr)
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
 * \file    htdocs/custom/odooconnector/core/triggers/interface_99_modOdooConnector_OdooConnector.class.php
 * \ingroup odoo_connector
 * \brief   Odoo Connector trigger: push an invoice/vendor bill to Odoo right when it is validated.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Class of triggers for Odoo Connector module
 */
class InterfaceOdooConnector extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = "odoo_connector";
		$this->description = "Odoo Connector: push invoice/vendor bill to Odoo on validate.";
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
		if (!isModEnabled('odooconnector')) {
			return 0;
		}
		// Dolibarr trigger names: customer invoice validate = BILL_VALIDATE (facture.class.php),
		// vendor bill validate = BILL_SUPPLIER_VALIDATE (fournisseur.facture.class.php).
		if ($action !== 'BILL_VALIDATE' && $action !== 'BILL_SUPPLIER_VALIDATE') {
			return 0;
		}
		if (empty($object) || (int) $object->id <= 0) {
			return 0;
		}

		$type = ($action === 'BILL_VALIDATE') ? 'invoice' : 'bill';

		// dol_include_once (not a hardcoded DOL_DOCUMENT_ROOT.'/custom/...'):
		// official module convention, resolves whatever dol_document_root the
		// module is installed under
		dol_include_once('odooconnector/class/OdooSync.class.php');
		$sync = new OdooSync($this->db);
		$sync->syncDocumentNow($type, (int) $object->id);

		// Sync failures are logged inside the connector (synclog + syslog) and must
		// never block the Dolibarr validation flow; the hourly cron stays the fallback.
		return 0;
	}
}
