<?php
/* Copyright (C) 2014-2023	Charlene BENKE	<charlene@patas-monkey.com>
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

/*		Function called to complete substitution array (before generating on ODT, or a personalized email)
 *		functions xxx_completesubstitutionarray are called by make_substitutions() if file
 *		is inside directory htdocs/core/substitutions
 *
 *		@param	array		$substitutionarray	Array with substitution key=>val
 *		@param	Translate	$langs				Output langs
 *		@param	Object		$object				Object to use to get values
 *		@return	void		The entry parameter $substitutionarray is modified
 */
function customlinknbventilproject_completesubstitutionarray(&$substitutionarray, $langs, $object, $parameters)
{
	global $db;
	$infoAdd="";
	$nbVentil=0;
	$langs->load("customlink@customlink");

	if (is_object($object)) {
		dol_include_once('/custom/customlink/class/customlink.class.php');
		$customlink = new Customlink($db);
		
		// on récupère la liste des taches du projet
		$sql="SELECT rowid FROM ".MAIN_DB_PREFIX."projet_task";
		$sql.=" WHERE fk_projet=".$object->id;
		$resql=$db->query($sql);
		$nbVentil=0;
		if ($resql) {
			while ($obj=$db->fetch_object($resql))
				$nbVentil+= $customlink->getNbVentil($obj->rowid, 4); // 4 = tache
		}

		if ($nbVentil> 0) 
			$infoAdd= '&nbsp;<span class="badge">'.$nbVentil.'</span>';
	}


	$substitutionarray['customlinknbventilproject']=$langs->trans('InvoiceDivision').$infoAdd;
}