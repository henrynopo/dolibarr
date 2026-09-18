<?php
/* Copyright (C) 2025 SLY Custom
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
 * SLY rules for accounting date (date to use for accounting / Odoo sync).
 *
 * - Sales invoice: if linked to shipment → shipment ATA or ETA; else invoice date.
 * - PO Standard invoice: if PO is linked to SO and SO has shipment → that shipment ATA/ETA
 *   (only one non-cancelled standard invoice per PO uses shipment date; others use invoice date).
 *   Deposit and credit note → invoice date.
 * - Expense report: approval date.
 */
class SLY_AccountingDate
{
	/**
	 * Get accounting date for a customer invoice (Facture).
	 * If linked to shipment, use shipment ATA (or ETA if ATA empty); else invoice date.
	 *
	 * @param DoliDB   $db       Database
	 * @param Facture $facture  Customer invoice (must have id, date (or datef), origin, origin_id)
	 * @return int Unix timestamp of accounting date (or 0 to use default)
	 */
	public static function getForCustomerInvoice($db, $facture)
	{
		$expid = 0;
		if (!empty($facture->origin) && (strtolower($facture->origin) === 'shipping' || strtolower($facture->origin) === 'expedition') && !empty($facture->origin_id)) {
			$expid = (int) $facture->origin_id;
		} else {
			// Also check element_element for expedition link
			$expid = self::getExpeditionIdLinkedTo($db, 'facture', (int) $facture->id);
		}
		if ($expid > 0) {
			$ataOrEta = self::getShipmentAtaOrEta($db, $expid);
			if ($ataOrEta !== null) {
				return $ataOrEta;
			}
		}
		return $facture->date ? (int) $facture->date : ($facture->datef ? strtotime($facture->datef) : 0);
	}

	/**
	 * Get accounting date for a supplier invoice (FactureFournisseur).
	 * Standard: if PO linked to SO and SO has shipment, use shipment ATA/ETA for the one standard invoice per PO that gets it; else invoice date.
	 * Deposit and credit note: invoice date.
	 *
	 * @param DoliDB              $db     Database
	 * @param FactureFournisseur  $invoice Supplier invoice
	 * @return int Unix timestamp
	 */
	public static function getForSupplierInvoice($db, $invoice)
	{
		$invDate = self::dateToTimestamp($invoice->date);
		if ($invDate <= 0 && !empty($invoice->date_facture)) {
			$invDate = strtotime($invoice->date_facture);
		}
		if ($invDate <= 0) {
			$invDate = time();
		}
		if ($invoice->type == 2) { // TYPE_CREDIT_NOTE
			return $invDate;
		}
		if ($invoice->type == 3) { // TYPE_DEPOSIT
			return $invDate;
		}
		// Prefer direct invoice <-> shipment link (dropshipping: one invoice per shipment)
		$expid = self::getExpeditionIdLinkedToSupplierInvoice($db, (int) $invoice->id);
		if ($expid > 0) {
			$ataOrEta = self::getShipmentAtaOrEta($db, $expid);
			if ($ataOrEta !== null) {
				return $ataOrEta;
			}
		}
		// Else: PO linked to shipment (dropshipping trigger) or PO → SO → shipment
		$poid = self::getSupplierOrderIdLinkedToInvoice($db, (int) $invoice->id);
		if ($poid <= 0) {
			return $invDate;
		}
		$ataOrEta = self::getShipmentAtaOrEtaFromPO($db, $poid);
		if ($ataOrEta === null) {
			return $invDate;
		}
		// When using PO chain (no direct invoice-shipment link): only one standard invoice per PO uses shipment date
		$firstStandardId = self::getFirstStandardSupplierInvoiceIdForPO($db, $poid);
		if ($firstStandardId === (int) $invoice->id) {
			return $ataOrEta;
		}
		return $invDate;
	}

	/**
	 * Get accounting date for an expense report. Use approval date.
	 *
	 * @param DoliDB        $db   Database
	 * @param ExpenseReport $exp  Expense report
	 * @return int Unix timestamp
	 */
	public static function getForExpenseReport($db, $exp)
	{
		if (!empty($exp->date_approbation)) {
			$t = is_numeric($exp->date_approbation) ? $exp->date_approbation : strtotime($exp->date_approbation);
			if ($t > 0) {
				return (int) $t;
			}
		}
		if (!empty($exp->date_approve)) {
			$t = is_numeric($exp->date_approve) ? $exp->date_approve : strtotime($exp->date_approve);
			if ($t > 0) {
				return (int) $t;
			}
		}
		// Fallback: date_valid or report date
		if (!empty($exp->date_validation)) {
			$t = is_numeric($exp->date_validation) ? $exp->date_validation : strtotime($exp->date_validation);
			if ($t > 0) {
				return (int) $t;
			}
		}
		return $exp->date_debut ? (int) $exp->date_debut : time();
	}

	/**
	 * Get expedition id directly linked to a supplier invoice (element_element facture_fourn <-> expedition).
	 *
	 * @param DoliDB $db        Database
	 * @param int    $invoiceId Facture fournisseur rowid
	 * @return int 0 or expedition rowid
	 */
	protected static function getExpeditionIdLinkedToSupplierInvoice($db, $invoiceId)
	{
		$prefix = $db->prefix();
		$sql = "SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".(int) $invoiceId." AND targettype IN ('invoice_supplier','facture_fourn') AND sourcetype IN ('expedition','shipping')";
		$resql = $db->query($sql);
		if ($resql && $obj = $db->fetch_object($resql)) {
			return (int) $obj->id;
		}
		$sql = "SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".(int) $invoiceId." AND sourcetype IN ('invoice_supplier','facture_fourn') AND targettype IN ('expedition','shipping')";
		$resql = $db->query($sql);
		if ($resql && $obj = $db->fetch_object($resql)) {
			return (int) $obj->id;
		}
		return 0;
	}

	/**
	 * Get one expedition id directly linked to a PO (element_element). Used for dropshipping when shipment is linked to PO.
	 *
	 * @param DoliDB $db   Database
	 * @param int    $poid Commande fournisseur rowid
	 * @return int 0 or expedition rowid
	 */
	protected static function getExpeditionIdLinkedToPO($db, $poid)
	{
		$prefix = $db->prefix();
		$poid = (int) $poid;
		if ($poid <= 0) {
			return 0;
		}
		$sql = "SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".$poid." AND targettype IN ('order_supplier','commande_fournisseur') AND sourcetype IN ('expedition','shipping')";
		$resql = $db->query($sql);
		if ($resql && $obj = $db->fetch_object($resql)) {
			return (int) $obj->id;
		}
		$sql = "SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".$poid." AND sourcetype IN ('order_supplier','commande_fournisseur') AND targettype IN ('expedition','shipping')";
		$resql = $db->query($sql);
		if ($resql && $obj = $db->fetch_object($resql)) {
			return (int) $obj->id;
		}
		return 0;
	}

	/**
	 * Get expedition (shipment) id linked to a facture (from element_element).
	 *
	 * @param DoliDB $db     Database
	 * @param string $element 'facture' or element type
	 * @param int    $factureId Facture rowid
	 * @return int 0 or expedition rowid
	 */
	protected static function getExpeditionIdLinkedTo($db, $element, $factureId)
	{
		$prefix = $db->prefix();
		$sql = "SELECT fk_source AS id FROM ".$prefix."element_element";
		$sql .= " WHERE (fk_target = ".(int) $factureId." AND targettype IN ('facture','invoice')) AND sourcetype IN ('expedition','shipping')";
		$resql = $db->query($sql);
		if ($resql && $obj = $db->fetch_object($resql)) {
			return (int) $obj->id;
		}
		$sql = "SELECT fk_target AS id FROM ".$prefix."element_element";
		$sql .= " WHERE (fk_source = ".(int) $factureId." AND sourcetype IN ('facture','invoice')) AND targettype IN ('expedition','shipping')";
		$resql = $db->query($sql);
		if ($resql && $obj = $db->fetch_object($resql)) {
			return (int) $obj->id;
		}
		return 0;
	}

	/**
	 * Get ATA or ETA from expedition_extrafields (filled by ShipsGo updates). Prefer ATA; fallback ETA.
	 *
	 * @param DoliDB $db   Database
	 * @param int    $expid Expedition rowid
	 * @return int|null Unix timestamp or null if none
	 */
	protected static function getShipmentAtaOrEta($db, $expid)
	{
		$prefix = $db->prefix();
		$sql = "SELECT ata, eta FROM ".$prefix."expedition_extrafields WHERE fk_object = ".(int) $expid;
		$resql = $db->query($sql);
		if (!$resql) {
			return null;
		}
		$obj = $db->fetch_object($resql);
		if (!$obj) {
			return null;
		}
		if (!empty($obj->ata) && $obj->ata !== '0000-00-00') {
			$t = strtotime($obj->ata);
			return $t ? $t : null;
		}
		if (!empty($obj->eta) && $obj->eta !== '0000-00-00') {
			$t = strtotime($obj->eta);
			return $t ? $t : null;
		}
		return null;
	}

	/**
	 * Get PO (commande_fournisseur) rowid linked to this supplier invoice.
	 *
	 * @param DoliDB $db        Database
	 * @param int    $invoiceId Facture fournisseur rowid
	 * @return int 0 or PO rowid
	 */
	protected static function getSupplierOrderIdLinkedToInvoice($db, $invoiceId)
	{
		$prefix = $db->prefix();
		$sql = "SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".(int) $invoiceId." AND targettype IN ('invoice_supplier','facture_fourn') AND sourcetype IN ('order_supplier','commande_fournisseur')";
		$resql = $db->query($sql);
		if ($resql && $obj = $db->fetch_object($resql)) {
			return (int) $obj->id;
		}
		$sql = "SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".(int) $invoiceId." AND sourcetype IN ('invoice_supplier','facture_fourn') AND targettype IN ('order_supplier','commande_fournisseur')";
		$resql = $db->query($sql);
		if ($resql && $obj = $db->fetch_object($resql)) {
			return (int) $obj->id;
		}
		return 0;
	}

	/**
	 * Get shipment ATA or ETA from PO: first try direct PO <-> expedition (dropshipping); else PO → SO → expedition.
	 *
	 * @param DoliDB $db   Database
	 * @param int    $poid Commande fournisseur rowid
	 * @return int|null Unix timestamp or null
	 */
	protected static function getShipmentAtaOrEtaFromPO($db, $poid)
	{
		// Dropshipping: PO may be directly linked to shipment (trigger on SHIPPING_CREATE)
		$expid = self::getExpeditionIdLinkedToPO($db, $poid);
		if ($expid > 0) {
			return self::getShipmentAtaOrEta($db, $expid);
		}
		$prefix = $db->prefix();
		// PO → SO: element_element (order_supplier/commande_fournisseur ↔ commande)
		$sql = "SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".(int) $poid." AND sourcetype IN ('order_supplier','commande_fournisseur') AND targettype IN ('commande','order')";
		$resql = $db->query($sql);
		if (!$resql || !($obj = $db->fetch_object($resql))) {
			$sql = "SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".(int) $poid." AND targettype IN ('order_supplier','commande_fournisseur') AND sourcetype IN ('commande','order')";
			$resql = $db->query($sql);
			if (!$resql || !($obj = $db->fetch_object($resql))) {
				return null;
			}
		}
		$soid = (int) $obj->id;
		if ($soid <= 0) {
			return null;
		}
		// SO → expedition
		$sql = "SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".(int) $soid." AND sourcetype IN ('commande','order') AND targettype IN ('expedition','shipping')";
		$resql = $db->query($sql);
		if (!$resql || !($obj = $db->fetch_object($resql))) {
			$sql = "SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".(int) $soid." AND targettype IN ('commande','order') AND sourcetype IN ('expedition','shipping')";
			$resql = $db->query($sql);
			if (!$resql || !($obj = $db->fetch_object($resql))) {
				return null;
			}
		}
		$expid = (int) $obj->id;
		if ($expid <= 0) {
			return null;
		}
		return self::getShipmentAtaOrEta($db, $expid);
	}

	/**
	 * Among non-cancelled standard supplier invoices linked to this PO, return the rowid of the one with smallest rowid (the one that gets shipment ATA).
	 *
	 * @param DoliDB $db   Database
	 * @param int    $poid Commande fournisseur rowid
	 * @return int 0 or facture_fourn rowid
	 */
	protected static function getFirstStandardSupplierInvoiceIdForPO($db, $poid)
	{
		$prefix = $db->prefix();
		$poid = (int) $poid;
		$sql = "SELECT f.rowid FROM ".$prefix."facture_fourn f";
		$sql .= " INNER JOIN ".$prefix."element_element e ON (";
		$sql .= " (e.fk_target = f.rowid AND e.targettype IN ('invoice_supplier','facture_fourn') AND e.fk_source = ".$poid." AND e.sourcetype IN ('order_supplier','commande_fournisseur'))";
		$sql .= " OR (e.fk_source = f.rowid AND e.sourcetype IN ('invoice_supplier','facture_fourn') AND e.fk_target = ".$poid." AND e.targettype IN ('order_supplier','commande_fournisseur'))";
		$sql .= " ) WHERE f.type = 0 AND f.fk_statut != 3"; // TYPE_STANDARD, not STATUS_ABANDONED
		$sql .= " ORDER BY f.rowid ASC LIMIT 1";
		$resql = $db->query($sql);
		if (!$resql) {
			return 0;
		}
		$obj = $db->fetch_object($resql);
		return $obj ? (int) $obj->rowid : 0;
	}

	/**
	 * Convert Dolibarr date (int timestamp or string) to Unix timestamp.
	 *
	 * @param int|string|null $date
	 * @return int
	 */
	protected static function dateToTimestamp($date)
	{
		if (empty($date)) {
			return 0;
		}
		if (is_numeric($date)) {
			return (int) $date;
		}
		$t = strtotime($date);
		return $t ? $t : 0;
	}
}
