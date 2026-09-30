<?php
/* Copyright (C) 2014-2022		charlene Benke		<charlene@patas-monkey.com>
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
 */

/**
 * 	\file	   htdocs/customlink/class/actions_customlink.class.php
 * 	\ingroup	customlink
 * 	\brief	  Fichier de la classe des actions/hooks des customlink
 */

class ActionsCustomlink // extends CommonObject 
{	

	/** Overloading the doActions function : replacing the parent's function with the one below 
	 *  @param	  parameters  meta datas of the hook (context, etc...) 
	 *  @param	  object			 the object you want to process 
	 *  @param	  action			 current action (if set). Generally create or edit or null 
	 *  @return	   void 
	 */ 
	function showLinkedObjectBlock($parameters, $object, $action) 
	{ 
		global $conf, $db, $langs;

		$langs->load("customlink@customlink");
		dol_include_once("/custom/customlink/core/lib/customlink.lib.php");

		if (empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != 'on')
			$szhttp = "http";
		else
			$szhttp = "https";

		if (getDolGlobalString('CUSTOMLINK_ENABLE_TAG')) {
			print "<br>";
			print load_fiche_titre($langs->trans('AddNewTag'));
			print '<form action="'.dol_buildpath("/custom/customlink", 1).'/addtag.php" method="POST">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="redirect" value="'.$szhttp.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'">';
			print '<input type="hidden" name="type_source" value="'.$object->element.'">';
			print '<input type="hidden" name="fk_source" value="'.(!empty($object->rowid) ? $object->rowid : $object->id).'">';
			print "<table class='noborder allwidth'>";
			print "<tr class='liste_titre'><td>".$langs->trans("ElementTags")."</td><td align=right>";
			print "<input type=submit name=join value=".$langs->trans("Add")."></td></tr>";
			print '<tr><td colspan=2><input type="text" name=tag value=""></td></tr>';
			print "</table></form>";
			print_tag_list($object->element, (!empty($object->rowid) ? $object->rowid : $object->id));
		}

		if (getDolGlobalString('CUSTOMLINK_ENABLE_LINK')) {
			$fk_source = !empty($object->rowid) ? $object->rowid : $object->id;
			// Per-source allowed target types (SO/PO/Shipment/Invoice rules); fallback to global config
			$allowed = customlink_get_allowed_target_types($db, $object->element, $fk_source);
			if (empty($allowed)) {
				$global = getDolGlobalString('CUSTOMLINK_ALLOWED_LINK_TYPES', '');
				$allowed = !empty($global) ? array_map('trim', explode(',', $global)) : array();
			}
			print "<br>";
			print load_fiche_titre($langs->trans('AddNewLink'));
			print '<form action="'.dol_buildpath("/custom/customlink", 1).'/addlink.php" method="POST">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="redirect" value="'.$szhttp.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'">';
			print '<input type="hidden" name="type_source" value="'.$object->element.'">';
			print '<input type="hidden" name="fk_source" value="'.$fk_source.'">';
			print "<table class='noborder allwidth'>";
			print "<tr class='liste_titre'><td>".$langs->trans("Element")."</td><td>".$langs->trans("Ref")."</td>";
			print "<td align=right><input type=submit name=join value=".$langs->trans("JoinElement")."></td></tr>";
			print '<tr><td>';
			select_element_type("", 'type_target', 0, 1, $allowed);
			print '</td><td colspan=2><input type="text" name=ref_target value=""></td></tr>';
			print "</table></form>";
		}

		$num = count($object->linkedObjects);
		$this->resprints = $num;
		return 0;
	}

	function addSearchEntry ($parameters, $object, $action) 
	{
		global $confg, $langs;
		$resArray=array();
		$resArray['searchintocustomlink']=array(
						'position'=>231, 'img'=>'object_customlink@customlink', 
						'label'=>$langs->trans("CustomLink", $parameters['search_boxvalue']), 
						'text'=>img_picto('', 'object_customlink@customlink').' '.$langs->trans("CustomLink", GETPOST('q')), 
						'url'=>dol_buildpath('/custom/customlink/listetag.php?sall='.urlencode(GETPOST('q')), 1)
		);
		$this->results = $resArray;
		return 0;
	}
}