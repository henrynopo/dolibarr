<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/EBKAccountLookup.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Read-side helper: returns active accounts from llx_accounting_account,
 *	             filtered by pcg_type, for the modal <select> dropdowns.
 */

if (!class_exists('EBKAccountLookup', false)) {

class EBKAccountLookup
{
	/**
	 * Return active accounts whose pcg_type is in $pcgTypeCsv (comma-separated).
	 *
	 * @param DoliDB $db
	 * @param string $pcgTypeCsv Examples: 'ASSET,EXPENSE' (debit side) or 'INCOME,LIABILITY' (credit side)
	 * @param int    $limit
	 * @param int    $entity
	 * @return array<int,array{account_number:string,label:string,pcg_type:string}>
	 */
	public static function accountCandidates($db, $pcgTypeCsv, $limit = 50, $entity = 0)
	{
		global $conf;

		$out = array();
		if ($db === null || empty($pcgTypeCsv)) {
			return $out;
		}
		$entityToUse = ((int) $entity > 0) ? (int) $entity : (int) $conf->entity;
		$limit = max(1, min(500, (int) $limit));

		// Build a safe IN list from $pcgTypeCsv.
		$raw = array_filter(array_map('trim', explode(',', $pcgTypeCsv)), 'strlen');
		$validPcg = array('INCOME', 'EXPENSE', 'ASSET', 'LIABILITY', 'CAPITAL');
		$quoted = array();
		foreach ($raw as $p) {
			$up = strtoupper($p);
			if (in_array($up, $validPcg, true)) {
				$quoted[] = "'".$db->escape($up)."'";
			}
		}
		if (empty($quoted)) {
			return $out;
		}

		$sql = "SELECT a.account_number, a.label, a.pcg_type";
		$sql .= " FROM ".MAIN_DB_PREFIX."accounting_account AS a";
		$sql .= " WHERE a.active = 1";
		$sql .= " AND a.entity IN (0, ".$entityToUse.")";
		$sql .= " AND a.pcg_type IN (".implode(',', $quoted).")";
		$sql .= " ORDER BY a.account_number ASC";
		$sql .= " LIMIT ".$limit;

		$resql = $db->query($sql);
		if ($resql === false) {
			dol_syslog(get_class()."::accountCandidates sql=".$sql." err=".$db->lasterror, LOG_ERR);
			return $out;
		}
		while ($obj = $db->fetch_object($resql)) {
			$out[] = array(
				'account_number' => (string) $obj->account_number,
				'label'          => (string) $obj->label,
				'pcg_type'       => (string) $obj->pcg_type,
			);
		}
		$db->free($resql);
		return $out;
	}

	/**
	 * Resolve a label_compte for a given account number (used by the writer
	 * when the UI did not supply one — e.g. an AI suggestion that omitted label).
	 *
	 * @param DoliDB  $db
	 * @param string  $accountNumber
	 * @param int     $entity
	 * @return string Empty when not found.
	 */
	public static function labelForAccount($db, $accountNumber, $entity = 0)
	{
		global $conf;

		$accountNumber = trim((string) $accountNumber);
		if ($db === null || $accountNumber === '') {
			return '';
		}
		$entityToUse = ((int) $entity > 0) ? (int) $entity : (int) $conf->entity;

		$sql = "SELECT a.label";
		$sql .= " FROM ".MAIN_DB_PREFIX."accounting_account AS a";
		$sql .= " WHERE a.account_number = '".$db->escape($accountNumber)."'";
		$sql .= " AND a.entity IN (0, ".$entityToUse.")";
		$sql .= " ORDER BY a.entity DESC";
		$sql .= " LIMIT 1";

		$resql = $db->query($sql);
		if ($resql === false) {
			return '';
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		return $obj ? (string) $obj->label : '';
	}
}

} // if (!class_exists('EBKAccountLookup', false))
