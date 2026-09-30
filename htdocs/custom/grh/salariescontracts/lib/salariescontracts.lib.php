<?php
/* Copyright (C) 2015 Yassine Belkaid  <y.belkaid@nextconcept.ma>
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
 *	    \file       salariescontracts/lib/salariescontracts.lib.php
 *		\brief      Ensemble de fonctions de base pour les adherents
 */

/**
 *  Return array head with list of tabs to view object informations
 *
 *  @param	Object	$object         Salariescontracts
 *  @return array           		head
 */
function salariescontracts_prepare_head($object)
{
	global $langs, $conf, $user;

	$h = 0;
	$head = array();

    $head[$h][0] = dol_buildpath('/grh/salariescontracts/card.php?id='.$object->id ,1);
    $head[$h][1] = $langs->trans("Card");
    $head[$h][2] = 'card';
    $h++;

	$head[$h][0] = dol_buildpath('/grh/salariescontracts/document.php?id='.$object->id ,1);
	$head[$h][1] = $langs->trans('Document');
	$head[$h][2] = 'documents';
	$h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname);   												to remove a tab
    complete_head_from_modules($conf,$langs,$object,$head,$h,'salariescontracts');

	complete_head_from_modules($conf,$langs,$object,$head,$h,'salariescontracts','remove');

	return $head;
}
