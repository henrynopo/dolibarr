<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/EBKBookkeepingAlreadyDone.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Guard: returns true if bookkeeping rows already exist for the given invoice.
 *	             Used by the UI to refuse a second posting, and by the writer to short-circuit.
 */

if (!class_exists('EBKBookkeepingAlreadyDone', false)) {

class EBKBookkeepingAlreadyDone
{
	/**
	 * Return true when at least one bookkeeping row is linked to the given
	 * document. We treat "any row" as "already done" — the AI/manual pair
	 * share piece_num, so a single match covers both lines.
	 *
	 * @param DoliDB $db        Database handler
	 * @param string $docType   'customer_invoice' | 'supplier_invoice'
	 * @param int    $fkDoc     facture.rowid | facture_fourn.rowid
	 * @param int    $entity    Entity id (0 = current). Defaults to current.
	 * @return bool
	 */
	public static function existsFor($db, $docType, $fkDoc, $entity = 0)
	{
		global $conf;

		if ($db === null || empty($docType) || (int) $fkDoc <= 0) {
			return false;
		}
		$entityToUse = ((int) $entity > 0) ? (int) $entity : (int) $conf->entity;

		$sql = "SELECT b.rowid";
		$sql .= " FROM ".MAIN_DB_PREFIX."accounting_bookkeeping AS b";
		$sql .= " WHERE b.doc_type = '".$db->escape($docType)."'";
		$sql .= " AND b.fk_doc = ".(int) $fkDoc;
		$sql .= " AND b.entity IN (0, ".$entityToUse.")";
		$sql .= " LIMIT 1";

		$resql = $db->query($sql);
		if ($resql === false) {
			dol_syslog(get_class()."::existsFor sql=".$sql." err=".$db->lasterror, LOG_ERR);
			return false; // be permissive: better to allow an attempted double-post than to silently break the UI
		}
		$exists = ($db->num_rows($resql) > 0);
		$db->free($resql);
		return $exists;
	}

	/**
	 * Count the bookkeeping rows already linked (for the UI badge).
	 *
	 * @param DoliDB $db
	 * @param string $docType
	 * @param int    $fkDoc
	 * @param int    $entity
	 * @return int
	 */
	public static function countFor($db, $docType, $fkDoc, $entity = 0)
	{
		global $conf;

		if ($db === null || empty($docType) || (int) $fkDoc <= 0) {
			return 0;
		}
		$entityToUse = ((int) $entity > 0) ? (int) $entity : (int) $conf->entity;

		$sql = "SELECT COUNT(*) AS c";
		$sql .= " FROM ".MAIN_DB_PREFIX."accounting_bookkeeping AS b";
		$sql .= " WHERE b.doc_type = '".$db->escape($docType)."'";
		$sql .= " AND b.fk_doc = ".(int) $fkDoc;
		$sql .= " AND b.entity IN (0, ".$entityToUse.")";

		$resql = $db->query($sql);
		if ($resql === false) {
			return 0;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		return $obj ? (int) $obj->c : 0;
	}
}

} // if (!class_exists('EBKBookkeepingAlreadyDone', false))
