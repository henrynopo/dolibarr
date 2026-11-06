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
 * \ingroup odooconnector
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
		$this->family = "odooconnector";
		$this->description = "Odoo Connector: push invoice/vendor bill/expense report to Odoo on validate.";
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
		// vendor bill validate = BILL_SUPPLIER_VALIDATE (fournisseur.facture.class.php);
		// reopening for modification ("Modify", back to draft) = BILL_UNVALIDATE / BILL_SUPPLIER_UNVALIDATE.
		$validates = array('BILL_VALIDATE', 'BILL_SUPPLIER_VALIDATE');
		$unvalidates = array('BILL_UNVALIDATE', 'BILL_SUPPLIER_UNVALIDATE');
		// Expense reports (core trigger codes): EXPENSE_REPORT_VALIDATE = the report
		// was submitted for approval, EXPENSE_REPORT_APPROVE = it was approved.
		// Both make the report a real accounting document that must reach Odoo
		// without waiting for the hourly cron.
		$expenses = array('EXPENSE_REPORT_VALIDATE', 'EXPENSE_REPORT_APPROVE');
		if (!in_array($action, $validates) && !in_array($action, $unvalidates) && !in_array($action, $expenses)) {
			return 0;
		}
		if (empty($object) || (int) $object->id <= 0) {
			return 0;
		}

		// Expense report: push it as soon as it is submitted/approved
		if (in_array($action, $expenses)) {
			require_once DOL_DOCUMENT_ROOT . '/expensereport/class/expensereport.class.php';
			if (!($object instanceof ExpenseReport)) {
				return 0;
			}
			dol_include_once('odooconnector/class/OdooSync.class.php');
			$sync = new OdooSync($this->db);
			$sync->syncExpenseNow((int) $object->id);
			return 0;
		}

		// dol_include_once (not a hardcoded DOL_DOCUMENT_ROOT.'/custom/...'):
		// official module convention, resolves whatever dol_document_root the
		// module is installed under
		dol_include_once('odooconnector/class/OdooSync.class.php');
		$sync = new OdooSync($this->db);
		if (in_array($action, $validates)) {
			$type = ($action === 'BILL_VALIDATE') ? 'invoice' : 'bill';
			$sync->syncDocumentNow($type, (int) $object->id);
		} else {
			// Event-driven unlock: reset the Odoo entry to draft right when the
			// document is reopened, so the next validation pushes into it directly
			$type = ($action === 'BILL_UNVALIDATE') ? 'invoice' : 'bill';
			$sync->syncDocumentUnvalidate($type, (int) $object->id);
		}

		// Sync failures are logged inside the connector (synclog + syslog) and must
		// never block the Dolibarr validation flow; the hourly cron stays the fallback.
		return 0;
	}
}
