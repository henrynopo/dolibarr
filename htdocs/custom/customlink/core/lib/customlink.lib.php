<?php
/* Copyright (C) 2014-2020	Charlene BENKE	<charlie@patas-monkey.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 *		\file	   htdocs/custom-parc/core/lib/custom-parc.lib.php
 *		\brief	  Ensemble de fonctions de base pour custom-parc
 */

function customlink_admin_prepare_head ()
{
	global $langs; //, $conf, $user;
	
	$h = 0;
	$head = array();
	
	$head[$h][0] = 'setup.php';
	$head[$h][1] = $langs->trans("Setup");
	$head[$h][2] = 'admin';
	
	$h++;
	$head[$h][0] = 'about.php';
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';

	return $head;
}

/**
 * Element type groups for link rules (sales vs purchase, and single-link types).
 * Used by customlink_get_allowed_target_types and customlink_validate_link_allowed.
 */
function customlink_get_sales_element_types()
{
	return array('propal', 'contrat', 'commande', 'shipping', 'facture', 'facture_rec');
}

/**
 * Purchase-side element types for link rules.
 */
function customlink_get_purchase_element_types()
{
	return array('supplier_proposal', 'order_supplier', 'reception', 'invoice_supplier', 'facture_fourn');
}

/**
 * Get count of existing links involving this object and each related type, in both directions.
 * A link can be created from either card (A→B or B→A); both store one row in element_element.
 * We count: (1) links where this object is SOURCE, by targettype;
 *           (2) links where this object is TARGET, by sourcetype.
 * So "how many commande linked" = links we have to commande (as source) + links from commande to us (as target).
 *
 * @param DoliDB $db Database
 * @param string $type_source Source element type (e.g. commande, order_supplier, shipping, facture)
 * @param int    $fk_source   Source object id
 * @return array [ other_type => count, ... ] (e.g. 'commande' => 1, 'facture' => 0)
 */
function customlink_get_existing_link_counts($db, $type_source, $fk_source)
{
	$counts = array();
	if ((int) $fk_source <= 0) {
		return $counts;
	}
	$id = (int) $fk_source;
	$type = $db->escape($type_source);
	$table = MAIN_DB_PREFIX."element_element";

	// (1) This object as SOURCE: count by targettype
	$sql1 = "SELECT targettype as ot, COUNT(*) as cnt FROM ".$table;
	$sql1 .= " WHERE fk_source = ".$id." AND sourcetype = '".$type."' GROUP BY targettype";
	$resql = $db->query($sql1);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$counts[$obj->ot] = (int) $obj->cnt;
		}
	}
	// (2) This object as TARGET: count by sourcetype (the other end's type)
	$sql2 = "SELECT sourcetype as ot, COUNT(*) as cnt FROM ".$table;
	$sql2 .= " WHERE fk_target = ".$id." AND targettype = '".$type."' GROUP BY sourcetype";
	$resql2 = $db->query($sql2);
	if ($resql2) {
		while ($obj = $db->fetch_object($resql2)) {
			if (!isset($counts[$obj->ot])) {
				$counts[$obj->ot] = 0;
			}
			$counts[$obj->ot] += (int) $obj->cnt;
		}
	}
	return $counts;
}

/**
 * Get allowed target types for the "add link" dropdown on a card, based on source type and existing links.
 * Rules:
 * - Sales order (commande): sales types from global config; + order_supplier only if no PO linked yet (max 1).
 * - Purchase order (order_supplier): purchase types from global config; + commande only if no SO linked yet (max 1).
 * - Shipment (shipping): only commande (max 1) and facture (max 1).
 * - Sales invoice (facture): only commande (max 1) and shipping (max 1).
 * - Supplier invoice (invoice_supplier): only order_supplier (max 1).
 *
 * @param DoliDB $db          Database
 * @param string $type_source  Source element type
 * @param int    $fk_source   Source object id
 * @return array List of allowed target type codes (for select_element_type $allowed_types)
 */
function customlink_get_allowed_target_types($db, $type_source, $fk_source)
{
	$global_allowed = getDolGlobalString('CUSTOMLINK_ALLOWED_LINK_TYPES', '');
	$global_arr = !empty($global_allowed) ? array_map('trim', explode(',', $global_allowed)) : array();
	$global_arr = array_filter($global_arr);

	$counts = customlink_get_existing_link_counts($db, $type_source, $fk_source);
	$sales = customlink_get_sales_element_types();
	$purchase = customlink_get_purchase_element_types();

	switch ($type_source) {
		case 'commande':
			// Sales types from global; + order_supplier only if count < 1
			$out = array();
			foreach ($global_arr as $t) {
				if (in_array($t, $sales)) {
					$out[] = $t;
				}
			}
			if (!isset($counts['order_supplier']) || $counts['order_supplier'] < 1) {
				if (in_array('order_supplier', $global_arr)) {
					$out[] = 'order_supplier';
				}
			}
			return array_unique($out);

		case 'order_supplier':
			// Purchase types from global; + commande only if count < 1
			$out = array();
			foreach ($global_arr as $t) {
				if (in_array($t, $purchase)) {
					$out[] = $t;
				}
			}
			if (!isset($counts['commande']) || $counts['commande'] < 1) {
				if (in_array('commande', $global_arr)) {
					$out[] = 'commande';
				}
			}
			return array_unique($out);

		case 'shipping':
			// Only commande (max 1) and facture (max 1)
			$out = array();
			if (!isset($counts['commande']) || $counts['commande'] < 1) {
				$out[] = 'commande';
			}
			if (!isset($counts['facture']) || $counts['facture'] < 1) {
				$out[] = 'facture';
			}
			return $out;

		case 'facture':
			// Only one same-type order (commande) and one shipment
			$out = array();
			if (!isset($counts['commande']) || $counts['commande'] < 1) {
				$out[] = 'commande';
			}
			if (!isset($counts['shipping']) || $counts['shipping'] < 1) {
				$out[] = 'shipping';
			}
			return $out;

		case 'invoice_supplier':
		case 'facture_fourn':
			// Only one order_supplier
			$out = array();
			if (!isset($counts['order_supplier']) || $counts['order_supplier'] < 1) {
				$out[] = 'order_supplier';
			}
			return $out;

		default:
			// Other types: use global allowed as-is
			return $global_arr;
	}
}

/**
 * Validate if adding a link (source -> target type) is allowed by business rules.
 * Use in addlink.php before create().
 *
 * @param DoliDB $db          Database
 * @param string $type_source Source element type
 * @param int    $fk_source   Source object id
 * @param string $type_target Target element type
 * @return array [ 'ok' => bool, 'error' => string ] error empty when ok
 */
function customlink_validate_link_allowed($db, $type_source, $fk_source, $type_target)
{
	$allowed = customlink_get_allowed_target_types($db, $type_source, $fk_source);
	if (!in_array($type_target, $allowed)) {
		return array('ok' => false, 'error' => 'LinkFromThisSourceToTargetTypeNotAllowed');
	}
	return array('ok' => true, 'error' => '');
}

/**
 *	Return list of type
 *
 *	@param  string	$selected	   Preselected type
 *	@param  string	$htmlname	   Name of field in html form
 * 	@param	int		$showempty		Add an empty field
 * 	@param	int		$hidetext		Do not show label before combo box
 * 	@param	string|array	$allowed_types	Optional. Comma-separated or array of type codes to show; empty = all
 *  @return	void
 */
function select_element_type($selected='', $htmlname='typeelement', $showempty=0, $hidetext=0, $allowed_types = null)
{
	global $db, $langs; //, $user, $conf;

	if (empty($hidetext)) print $langs->trans("ElementType").': ';

	$allowed = array();
	if (!empty($allowed_types)) {
		if (is_array($allowed_types)) {
			$allowed = array_map('trim', $allowed_types);
		} else {
			$allowed = array_map('trim', explode(',', $allowed_types));
		}
		$allowed = array_filter($allowed);
	}

	// boucle sur les éléments
	$sql = "SELECT rowid, label, type, translatefile";
	$sql.= " FROM ".MAIN_DB_PREFIX."c_element_type";
	$sql.= " ORDER BY incore desc";

	dol_syslog("Customlink.Lib::select_element_type sql=".$sql);

	$resql=$db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;
		if ($num) {
			print '<select class="flat" name="'.$htmlname.'">';
			if ($showempty) {
				print '<option value="-1"';
				if ($selected == -1) print ' selected="selected"';
				print '>&nbsp;</option>';
			}
			while ($i < $num) {

				$obj = $db->fetch_object($resql);
				if (!empty($allowed) && !in_array($obj->type, $allowed)) {
					$i++;
					continue;
				}
				$langs->load($obj->translatefile);
				print '<option value="'.$obj->type.'"';
				if ($obj->type == $selected) print ' selected="selected"';
				print ">".$langs->trans($obj->label)."</option>";
				$i++;
			}
			print '</select>';
		} else {
			// si pas de liste, on positionne un hidden à vide
			print '<input type="hidden" name="'.$htmlname.'" value=-1>';
		}
	}
}
// search the list of tag
function print_tag_list($element, $id)
{
	global $db; //, $langs, $user, $conf;
	
	$sql = "SELECT distinct rowid, tag FROM ".MAIN_DB_PREFIX."element_tag";
	$sql.=" WHERE element='".$db->escape($element)."' AND fk_element=".((int) $id);
	$resql=$db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;
		if ($num) {
			print "<table class='noborder allwidth'>";
			print '<tr><td>';
			print '<form action="'.dol_buildpath("/custom/customlink", 1).'/deltag.php" method="POST">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="redirect" value="http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'">';
			print '<input type="hidden" name="element" value="'.$element.'">';
			print '<input type="hidden" name="fk_element" value="'.$id.'">';
			print "<div style='display: inline;'>";
			while ($i < $num) {
				$obj = $db->fetch_object($resql);
				print '<div style="float: left; background:#E0E0E0;margin:2px;padding:5px;">';
				print '<a href="'.dol_buildpath("/custom/customlink", 1).'/listetag.php?tag='.urlencode($obj->tag).'">'.dol_escape_htmltag($obj->tag).'</a>';

				// pour la suppression c'est trop chiant, on verra plus tard
				print '&nbsp;<button type="submit" name="delete" value="'.$obj->rowid.'">X</button>'; 
				print '</div>';
				$i++;
			}
			print "</div>";
			print '</form>';
			print '</td></tr>';
			print "</table>"; 
		}
	}
}

// search the list of tag
function print_tag_list_count($max=6)
{
	global $db; //, $langs, $user, $conf;

	$sql = "SELECT tag, count(*) as nb FROM ".MAIN_DB_PREFIX."element_tag";
	$sql.=" group by tag" ;
	$sql.=" order by count(*) desc, tag" ;
	$sql.= $db->plimit($max, 0);
	$resql=$db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;
		if ($num) {
			print "<div style='display: inline;'>";
			while ($i < $num) {
				$obj = $db->fetch_object($resql);
				print '<div style="float: left; background:#E0E0E0;margin:2px;padding:5px;">';
				print '<a href="listetag.php?tag='.$obj->tag.'">';
				print $obj->tag.'('.$obj->nb.')</a></div>';
				$i++;
			}
			print "</div>";
		}
	}
}