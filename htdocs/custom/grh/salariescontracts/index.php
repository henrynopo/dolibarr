<?php
/* Copyright (C) 2015		Yassine Belkaid	<y.belkaid@nextconcept.ma>
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
 *   	\file       salariescontracts/index.php
 *		\ingroup    index
 *		\brief      Home page for Salaries contracts area.
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
// require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

// $morejs=array("/mymodule/js/mymodule.js");
// llxHeader('','Titre','','','','',$morejs,'',0,0);

// $db->begin();   // Start transaction
// $db->query("My SQL request insert, update or delete");
// $db->commit();       // Validate transaction
// or $db->rollback()  // Cancel transaction

echo 'salariescontracts';

//$db->close();
