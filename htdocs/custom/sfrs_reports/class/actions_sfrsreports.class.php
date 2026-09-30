<?php
/* Copyright (C) 2026 Henry Guo <hbg@hbg.sg>
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
 * \file   htdocs/custom/sfrs_reports/class/actions_sfrsreports.class.php
 * \brief  Hook callbacks for SFRS Reports module
 *
 * Implements the 'accountancyindex' hook to surface SFRS reports
 * (Balance Sheet / P&L / Cash Flow) on the main Accounting home page.
 */


/**
 * Class ActionsSfrsReports — Dolibarr hook callbacks
 *
 * Class name MUST follow the convention: actions_{module_dirname}
 * (Dolibarr auto-loads this from the module's class/ directory when
 * the module declares 'accountancyindex' in $module_parts['hooks']).
 */
class ActionsSfrsReports
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error;

	/**
	 * @var string[] Error messages
	 */
	public $errors = array();

	/**
	 * @var array Hook context (filled by Dolibarr before executeHooks)
	 */
	public $context = array();

	/**
	 * @var array Current hook parameters
	 */
	public $parameters = array();

	/**
	 * @var Object|null Object on which the hook is fired
	 */
	public $object;

	/**
	 * @var string Action name
	 */
	public $action;

	/**
	 * @var HookManager Hook manager instance
	 */
	public $hookmanager;


	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Hook callback for 'accountancyindex'
	 *
	 * Triggered by htdocs/accountancy/index.php after the standard
	 * "Step A - E" intro. We append a quick-access block pointing
	 * to the three SFRS reports so users can find them without
	 * hunting through the left sidebar.
	 *
	 * @param array $parameters Hook context
	 * @param Object|null $object Triggering object (null here)
	 * @param string $action Action name
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0 on success
	 */
	public function accountancyindex($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;

		$langs->loadLangs(array('sfrs_reports@sfrs_reports', 'accountancy'));

		// Permission check — match the pages' own check
		if (!$user->hasRight('sfrsreports', 'reports', 'read')) {
			return 0;
		}

		$framework = getDolGlobalString('SFRS_FRAMEWORK', 'FRS');
		$currency = getDolGlobalString('SFRS_PRESENTATION_CURRENCY', 'SGD');

		print '<br>';
		print '<div class="div-table-responsive-no-min">';
		print '<table class="nobordernopadding">';
		print '<tr class="liste_titre">';
		print '<th colspan="3"><span class="fa fa-chart-bar"></span> ';
		print $langs->trans('SFRSReportsQuickAccess').' — '.$framework.' ('.$currency.')';
		print '</th>';
		print '</tr>';
		print '<tr class="oddeven">';
		print '<td width="32%"><a href="'.DOL_URL_ROOT.'/custom/sfrs_reports/pages/balance_sheet.php">';
		print img_picto('', 'balance', 'class="pictofixedwidth"').' <strong>'.$langs->trans('SFRSBalanceSheet').'</strong></a></td>';
		print '<td>'.$langs->trans('SFRSBalanceSheetDesc').'</td>';
		print '<td align="right">'.img_picto('', 'chevron-right').'</td>';
		print '</tr>';
		print '<tr class="oddeven">';
		print '<td><a href="'.DOL_URL_ROOT.'/custom/sfrs_reports/pages/profit_loss.php">';
		print img_picto('', 'accountancy', 'class="pictofixedwidth"').' <strong>'.$langs->trans('SFRSProfitLoss').'</strong></a></td>';
		print '<td>'.$langs->trans('SFRSProfitLossDesc').'</td>';
		print '<td align="right">'.img_picto('', 'chevron-right').'</td>';
		print '</tr>';
		print '<tr class="oddeven">';
		print '<td><a href="'.DOL_URL_ROOT.'/custom/sfrs_reports/pages/cash_flow.php">';
		print img_picto('', 'payment', 'class="pictofixedwidth"').' <strong>'.$langs->trans('SFRSCashFlow').'</strong></a></td>';
		print '<td>'.$langs->trans('SFRSCashFlowDesc').'</td>';
		print '<td align="right">'.img_picto('', 'chevron-right').'</td>';
		print '</tr>';
		print '<tr class="oddeven">';
		print '<td><a href="'.DOL_URL_ROOT.'/custom/sfrs_reports/pages/aging.php?type=ar">';
		print img_picto('', 'bill', 'class="pictofixedwidth"').' <strong>'.$langs->trans('SFRSARAgingReport').'</strong></a></td>';
		print '<td>'.$langs->trans('SFRSARAgingReportDesc').'</td>';
		print '<td align="right">'.img_picto('', 'chevron-right').'</td>';
		print '</tr>';
		print '<tr class="oddeven">';
		print '<td><a href="'.DOL_URL_ROOT.'/custom/sfrs_reports/pages/aging.php?type=ap">';
		print img_picto('', 'supplier_invoice', 'class="pictofixedwidth"').' <strong>'.$langs->trans('SFRSAPAgingReport').'</strong></a></td>';
		print '<td>'.$langs->trans('SFRSAPAgingReportDesc').'</td>';
		print '<td align="right">'.img_picto('', 'chevron-right').'</td>';
		print '</tr>';
		print '</table>';
		print '</div>';

		return 0;
	}


	/**
	 * Hook callback for 'bookkeepingCreateBefore'
	 *
	 * Called by htdocs/accountancy/class/bookkeeping.class.php BookKeeping::create()
	 * just before the INSERT is executed. We may mutate $object in place; the
	 * core rebuilds the SQL using our updated values and persists them.
	 *
	 * Purpose: when sellsjournal / purchasesjournal / bankjournal creates a
	 * BookKeeping line for a multicurrency invoice / payment, those journals do
	 * NOT populate the bookkeeping row's multicurrency_amount / multicurrency_code
	 * fields. Without our intervention, the new line goes into the ledger with
	 * multicurrency_code = NULL and the SFRS Reports can never filter by currency.
	 *
	 * We reverse-lookup the source document (llx_facture / llx_facture_fourn /
	 * llx_paiement / llx_bank) via $object->doc_type + $object->fk_doc, read its
	 * multicurrency_code / multicurrency_tx, and set:
	 *   $object->multicurrency_code   = (the source currency code)
	 *   $object->multicurrency_amount = abs(debit-credit) / multicurrency_tx
	 *
	 * The accounting equation still balances on debit / credit (in functional
	 * currency); multicurrency_amount is informational metadata for SFRS reports.
	 *
	 * @param array $parameters Hook context (contains 'object' = the BookKeeping being created)
	 * @param BookKeeping $object The BookKeeping line about to be inserted
	 * @param string $action Action name
	 * @param HookManager $hookmanager Hook manager
	 * @return int 0 always (we never fail the insert, we only enrich)
	 */
	public function bookkeepingCreateBefore($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		// No-op if no source document, or caller already set multicurrency fields.
		if (!is_object($object)) {
			return 0;
		}
		if (empty($object->fk_doc)) {
			return 0;
		}
		if (!empty($object->multicurrency_code)) {
			// Caller already set it — don't override.
			return 0;
		}

		// Determine which source table to query based on doc_type.
		// Doc-type values are those written by the core journals
		// (see accountancy/journal/*.php):
		//   customer_invoice -> llx_facture        (sellsjournal)
		//   supplier_invoice -> llx_facture_fourn  (purchasesjournal)
		//   bank             -> llx_bank           (bankjournal)
		//   expense_report   -> NOT covered: although llx_expensereport has
		//                      multicurrency columns in its schema, the module
		//                      implements no multicurrency logic (no currency
		//                      picker in card.php, no rate lookup in the class,
		//                      no mc usage in expensereportsjournal.php) — the
		//                      fields are always NULL, so a lookup branch would
		//                      be dead code.
		//   other            -> skip
		// Entity scoping follows the core bookkeeping convention
		// ("Do not use getEntity for accounting features").
		$doc_type = isset($object->doc_type) ? (string) $object->doc_type : '';
		$sql = null;

		switch ($doc_type) {
			case 'customer_invoice':
				$sql = "SELECT multicurrency_code, multicurrency_tx";
				$sql .= " FROM ".MAIN_DB_PREFIX."facture";
				$sql .= " WHERE rowid = ".(int) $object->fk_doc;
				$sql .= " AND entity = ".((int) $conf->entity);
				break;
			case 'supplier_invoice':
				$sql = "SELECT multicurrency_code, multicurrency_tx";
				$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn";
				$sql .= " WHERE rowid = ".(int) $object->fk_doc;
				$sql .= " AND entity = ".((int) $conf->entity);
				break;
			case 'bank':
				// Bank transactions (llx_bank) — usually functional currency only,
				// but if mc is set on the bank row we capture it.
				$sql = "SELECT multicurrency_code, 1 AS multicurrency_tx";
				$sql .= " FROM ".MAIN_DB_PREFIX."bank";
				$sql .= " WHERE rowid = ".(int) $object->fk_doc;
				break;
			default:
				// Unknown doc_type — leave the bookkeeping row alone.
				return 0;
		}

		$res = $this->db->query($sql);
		if (!$res) {
			// Query failed — log but do not abort the insert.
			dol_syslog(__METHOD__.' SFRS Reports: lookup failed for doc_type='.$doc_type.', fk_doc='.$object->fk_doc.', error='.$this->db->lasterror(), LOG_WARNING);
			return 0;
		}
		$row = $this->db->fetch_object($res);
		if (!$row || empty($row->multicurrency_code)) {
			// Source document is in functional currency (no multicurrency row) —
			// leave bookkeeping fields NULL, which is the correct state for the
			// 'FUNC' mc_code filter in SfrsReport.
			return 0;
		}

		// Set the code (always present if the source row has one).
		$object->multicurrency_code = $row->multicurrency_code;

		// Compute the multicurrency_amount: base-currency amount divided by the rate.
		// We use abs(debit - credit) so direction (debit/credit) doesn't matter.
		$base_amount = (float) $object->debit - (float) $object->credit;
		$base_amount_abs = abs($base_amount);
		$rate = (float) ($row->multicurrency_tx ?? 0);

		if ($base_amount_abs > 0 && $rate > 0) {
			// For sales invoices, multicurrency_amount is the foreign-currency total.
			$object->multicurrency_amount = round($base_amount_abs / $rate, 8);
		}
		// If base amount is zero or rate is zero/one, leave multicurrency_amount NULL —
		// 'FUNC' filter will still match (because multicurrency_code is set).

		return 0;
	}
}