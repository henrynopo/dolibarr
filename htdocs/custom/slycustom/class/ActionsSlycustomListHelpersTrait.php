<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: shared list SQL / cell helpers for SLY Custom list hooks.
 */
trait ActionsSlycustomListHelpersTrait
{
	/**
	 * Build SQL IN list for element types.
	 *
	 * @param array|string $types One type or list of types
	 * @return string
	 */
	protected function sqlTypeIn($types)
	{
		$list = is_array($types) ? $types : array($types);
		$out = array();
		foreach ($list as $t) {
			$t = trim((string) $t);
			if ($t === '') {
				continue;
			}
			$out[] = "'".$this->db->escape($t)."'";
		}
		return empty($out) ? "''" : implode(',', $out);
	}

	/**
	 * Build a bidirectional element_element UNION subquery.
	 *
	 * @param string       $alias          SQL alias for derived table
	 * @param string       $leftIdAlias    Left ID alias in derived table
	 * @param string       $rightIdAlias   Right ID alias in derived table
	 * @param array|string $leftTypes      Element types for left side (current list object)
	 * @param array|string $rightTypes     Element types for right side (source object)
	 * @param string       $leftTableAlias Current list table alias to join on
	 * @return string
	 */
	protected function buildBidirectionalElementJoin($alias, $leftIdAlias, $rightIdAlias, $leftTypes, $rightTypes, $leftTableAlias)
	{
		$p = MAIN_DB_PREFIX;
		$leftIn = $this->sqlTypeIn($leftTypes);
		$rightIn = $this->sqlTypeIn($rightTypes);

		$sql = " LEFT JOIN (";
		$sql .= "SELECT ee.fk_target AS ".$leftIdAlias.", ee.fk_source AS ".$rightIdAlias;
		$sql .= " FROM ".$p."element_element ee";
		$sql .= " WHERE ee.targettype IN (".$leftIn.") AND ee.sourcetype IN (".$rightIn.")";
		$sql .= " UNION ALL ";
		$sql .= "SELECT ee.fk_source AS ".$leftIdAlias.", ee.fk_target AS ".$rightIdAlias;
		$sql .= " FROM ".$p."element_element ee";
		$sql .= " WHERE ee.sourcetype IN (".$leftIn.") AND ee.targettype IN (".$rightIn.")";
		$sql .= ") ".$alias." ON ".$alias.".".$leftIdAlias." = ".$leftTableAlias.".rowid";

		return $sql;
	}

	/**
	 * Return HTML for a table cell with optional link for a ref column (for list hooks: use resPrint)
	 *
	 * @param object $obj     Row object
	 * @param string $refKey  Property name for ref (e.g. sly_order_ref)
	 * @param string $idKey   Property name for id (e.g. sly_order_id)
	 * @param string $baseUrl Base URL for card (e.g. DOL_URL_ROOT.'/commande/card.php')
	 * @return string <td>...</td>
	 */
	protected function getListRefCellHtml($obj, $refKey, $idKey, $baseUrl)
	{
		$ref = isset($obj->$refKey) ? $obj->$refKey : '';
		if ($ref !== '' && $ref !== null) {
			$id = isset($obj->$idKey) ? (int) $obj->$idKey : 0;
			if ($id > 0) {
				return '<td class="nowraponall">'.'<a href="'.dol_escape_htmltag($baseUrl.'?id='.$id).'">'.dol_escape_htmltag($ref).'</a>'.'</td>';
			}
			return '<td class="nowraponall">'.dol_escape_htmltag($ref).'</td>';
		}
		return '<td class="nowraponall">&nbsp;</td>';
	}

	/**
	 * Get source order (commande) linked to an invoice for payment card invoice list.
	 *
	 * @param int $facid Invoice id (llx_facture.rowid)
	 * @return array|null {'ref' => string, 'id' => int} or null
	 */
	protected function getInvoiceSourceOrder($facid)
	{
		$p = MAIN_DB_PREFIX;
		$sql = "SELECT c.ref, c.rowid AS id";
		$sql .= " FROM (SELECT ee.fk_target AS inv_id, ee.fk_source AS cmd_id FROM ".$p."element_element ee WHERE ee.targettype = 'facture' AND ee.sourcetype = 'commande'";
		$sql .= " UNION ALL SELECT ee.fk_source AS inv_id, ee.fk_target AS cmd_id FROM ".$p."element_element ee WHERE ee.sourcetype = 'facture' AND ee.targettype = 'commande') eeord";
		$sql .= " INNER JOIN ".$p."commande c ON c.rowid = eeord.cmd_id";
		$sql .= " WHERE eeord.inv_id = ".((int) $facid);
		$sql .= " LIMIT 1";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$obj = $this->db->fetch_object($resql);
			$this->db->free($resql);
			return array('ref' => $obj->ref, 'id' => (int) $obj->id);
		}
		if ($resql) {
			$this->db->free($resql);
		}
		return null;
	}
}
