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

dol_include_once('/docsemployes/class/docsemployes.class.php');
dol_include_once('/compta/facture/class/facture.class.php');

class Actionsdocsemployes{
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
	function Actionsdocsemployes($db){
		$this->db = $db;
	}

	function doActions($parameters, &$object, &$action, $hookmanager){
		global $langs, $user, $confirm;

		$error = 0; // Error counter
		
		$documents = new docsemployes($this->db);
		$langs->load('docsemployes@docsemployes');

		// $client = 0;
		// if(version_compare(DOL_VERSION, '4.0.0') >= 0){
		// 	if (!empty($this->socid) && !empty($this->fk_soc) && !empty($this->fk_thirdparty) && !empty($force_thirdparty_id)){
	 //      		$object->fetch_thirdparty();
	 //      		$client = $object->thirdparty;
		// 	}
	 //    }else{
	 //      	$client = $object->client;
	 //    }

		// $context = $parameters['currentcontext'];
	 	
		// echo $context."<br>";


		// $documents->get_document_user();



		// echo  "<br><br>Stop Here";
		// if($context == 'ordercard'){ /* COMMANDE */
		
		// }elseif($context == 'propalcard'){ /* PROPAL */
		// }elseif($context == 'invoicecard'){ /* FACTURE */
		// }elseif($context == 'productcard'){ /* FICHE PRODUIT */
		// }elseif($context == 'thirdpartycard'){ /* FICHE SOC */
		// }
	}

	function completeTabsHead($parameters, &$object, &$action, $hookmanager){
		global $langs, $db;
		$head = $parameters['head'];

		// tab_docsemploye 只存在于 user 卡片;无对象(如 salaries/admin 等设置页)或非 user 对象时直接返回,
		// 不设置 $this->results,否则核心 array_merge($head, resArray) 会令 tabs 翻倍
		if (!is_object($object) || empty($object->id) || (isset($object->element) && $object->element != 'user')) {
			return 0;
		}

		$docsemployes  = new docsemployes($db);
		$nbdocs = $docsemployes->fetchAll('', '', 0, 0, ' AND fk_user = '.((int) $object->id));

		foreach ($head as $key => $value) {
			if($head[$key][2] == 'tab_docsemploye' && !empty($nbdocs)){
			    $head[$key][1] = $langs->trans("tab_docsemploye").'<span class="badge marginleftonlyshort">'.$nbdocs.'</span>';
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

		// $hookmanager->resArray = $head;
		$this->results = $head;
		
	    return 1;

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
