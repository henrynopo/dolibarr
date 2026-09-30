<?php
/* Copyright (C) 2015	Yassine Belkaid	<y.belkaid@nextconcept.ma>
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
 *   	\file       salariescontracts/common.inc.php
 *		\ingroup    salariescontracts
 *		\brief      Common load of data
 */

require_once realpath(dirname(__FILE__)).'/../../main.inc.php';

if (!class_exists('Salariescontracts')) {
	require dol_buildpath('/grh/salariescontracts/class/salariescontracts.class.php');
}

$langs->load("user");
$langs->load("other");

// if (empty($conf->salariescontracts->enabled)) {
//     llxHeader('',$langs->trans('ListOfSalaries'));
//     print '<div class="tabBar">';
//     print '<span style="color: #FF0000;">'.$langs->trans('NotActiveModSC').'</span>';
//     print '</div>';
//     llxFooter();
//     exit();
// }

