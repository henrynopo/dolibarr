<?php
/* Copyright (C) 2016      Garcia MICHEL <garcia@soamichel.fr>
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


dol_include_once('/gestionnotifs/class/gt_notifcs.class.php');
dol_include_once('/gestionnotifs/class/gt_comments.class.php');

class Actionsgestionnotifs{
	protected $db;
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();


	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	function Actionsgestionnotifcs($db){
		$this->db = $db;
	}
	function completeTabsHead($parameters, &$object, &$action, $hookmanager){
		global $langs, $db;
		$head = $parameters['head'];
		$object = $parameters['object'];
		$name_attr = [
			'member' => 'rowid',
			'societe' => 'socid',
			'project' => 'id',
			'product' => 'id',
		];
		$notif  = new gt_notifcs($db);
		$comment  = new gt_comments($db);
		$nbNote = $notif->fetchAll('','',0,0,' AND name_module ="'.$object->element.'" AND fk_module ='.$object->id);
		$nbComment = $comment->fetchAll('','',0,0,' AND name_module ="'.$object->element.'" AND fk_module ='.$object->id);

		foreach ($head as $key => $value) {
			if($head[$key][2] == 'tab_notification'){
			    $head[$key][1] = $langs->trans("Notifications").'<span class="badge marginleftonlyshort">'.$nbNote.'</span>';
			}
			elseif($head[$key][2] == 'tab_commentaire'){
			    $head[$key][1] = $langs->trans("Comments").'<span class="badge marginleftonlyshort">'.$nbComment.'</span>';
			}
		}

		// $h = count($head);
		// $head[$h][0] = dol_buildpath("/gestionnotifs/".$object->element."/notification.php?".$name_attr[$object->element]."=".$object->id, 1);
	 //    $head[$h][1] = $langs->trans("Notifications").'<span class="badge marginleftonlyshort">'.$nbNote.'</span>';
	 //    $head[$h][2] = 'modnotification';

		// $h ++;
		// $head[$h][0] = dol_buildpath("/gestionnotifs/".$object->element."/commentaire.php?".$name_attr[$object->element]."=".$object->id, 1);
	 //    $head[$h][1] = $langs->trans("Comments").'<span class="badge marginleftonlyshort">'.$nbComment.'</span>';
	 //    $head[$h][2] = 'modcommentaire';

		$hookmanager->resArray = $head;
	    return 1;

	}

	function doActions($parameters, &$object, &$action, $hookmanager){
		global $langs, $user, $confirm;

		$params = explode(':',$parameters['context']);
		$element = $object->element;
		foreach ($params as $key => $value) {
			if($value != 'main'){
				// print '<script>';
				// 	print '$(document).ready(function(){
				// 		console.log("fggggg");
				// 	})';
				// print '<script>';
			}
		}
		// print_r($object);
		
	}


	/**
	 * Overloading the interface function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActionInterface($parameters, &$object, &$action, $hookmanager)
	{
	    $error = 0; // Error counter
	    global $langs, $db, $conf, $user;
	    
	}
	
}
