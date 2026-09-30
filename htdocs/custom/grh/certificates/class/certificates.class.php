<?php
/* Copyright (C) 2015  Yassine Belkaid   <y.belkaid@nextconcept.ma>
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
 *      \file       htdocs/compta/certifications/class/certifications.class.php
 *      \ingroup    certifications
 *      \brief		Class for certifications module
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT .'/core/class/commonobject.class.php';

/**
 *  Class to manage certifications
 */
class Certificates extends CommonObject
{
	public $id;
	public $ref;

	public $fk_user_appro;
	public $fk_user_ben;
	public $cert_type;
	public $entity;
	public $status;
	public $date_appro;
	public $certifications = array();

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	function __construct($db) {
		$this->db 			 = $db;
		$this->element 		 = 'certificates';
		$this->table_element = 'certificates';
		$this->entity 		 = 1;
		$this->status 		 = 0;
		return 1;
	}

	/**
	 * Update database
	 *
	 * @param   integer	  $cert_id        	User that modify
	 * @return  int         			<0 if KO, >0 if OK
	 */
	function update($cert_id) {
		global $conf, $langs;

		$error = 0;

		// Clean parameters
		$this->fk_user_appro = (int) trim($this->fk_user_appro);
		$this->fk_user_ben   = (int) trim($this->fk_user_ben);
		$this->cert_type 	 = (int) trim($this->cert_type);
		
		if ($this->entity)
			$this->entity = (int) trim($this->entity);

		// Check parameters
		if ($cert_id <= 0 || empty($this->fk_user_appro) || $this->fk_user_appro <= 0 || $this->fk_user_ben <= 0) {
			$this->error = 'ErrorBadParameter';
			return -1;
		}

		$this->db->begin();

		// Update request
		$sql = "UPDATE ".MAIN_DB_PREFIX. $this->table_element ." SET";
		$sql.= " fk_user_appro=". $this->db->escape($this->fk_user_appro) .",";
		$sql.= " fk_user_ben=". $this->db->escape($this->fk_user_ben) .",";
		$sql.= " cert_type=". $this->db->idate($this->cert_type);
		if ($this->entity)
			$sql.= ", entity=". $this->db->idate($this->entity);
		$sql.= " WHERE rowid=".$cert_id;

		dol_syslog(get_class($this)."::update", LOG_DEBUG);
		$resql = $this->db->query($sql);
		
		if (!$resql) {
			$this->error="Error ".$this->db->lasterror();
			return -1;
		}

		if (!$error) {
			$this->db->commit();
			return $this->id;
		}
		else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *  Load object in memory from database
	 *
	 *  @param	int		$id         id object
	 *  @param  User	$user       User that load
	 *  @return int         		<0 if KO, >0 if OK
	 */
	function fetch($id, $user = null) {
		global $langs, $conf;
		$id = (int) $id;

		if ($id <= 0) {
			return -1;
		}

		$sql = "SELECT t.rowid,t.fk_user_appro,t.fk_user_ben,t.cert_type,t.entity,t.status FROM ".MAIN_DB_PREFIX. $this->table_element ." as t";
		$sql.= " WHERE t.rowid = ". $id;
		$sql .= ' AND t.entity ='.$conf->entity;

		dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id    			= $obj->rowid;
				$this->ref   			= $obj->rowid;
				$this->fk_user_appro 	= $obj->fk_user_appro;
				$this->fk_user_ben 		= $obj->fk_user_ben;
				$this->cert_type 		= $obj->cert_type;
				$this->entity 			= $obj->entity;
				$this->status 			= $obj->status;
				
			}

			$this->db->free($resql);

			return $this->id;
		}
		else {
			$this->error="Error ".$this->db->lasterror();
			return -1;
		}
	}

	/**
	 *  Load object in memory from database
	 *
	 *  @param	int		$type_id    id of type
	 *  @param  string	$filter     a group of filter
	 *  @return int         		<0 if KO, >0 if OK
	 */
	function fetchByType($type_id, $filter = null, $order = null) {
		global $langs, $conf;
		$type_id = (int) $type_id;

		if ($type_id <= 0) {
			return -1;
		}

		$sql = "SELECT t.rowid,t.fk_user_appro,t.fk_user_ben,t.cert_type,t.entity,t.status FROM ".MAIN_DB_PREFIX. $this->table_element ." as t";
		$sql.= " WHERE t.cert_type = ". $type_id;
		$sql.= ' AND t.entity ='.$conf->entity;

		if (null !== $filter) {
			$sql .= $filter;
		}

		if (null !== $order) {
			$sql .= ' '.$order;
		}

		dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			if ($this->db->num_rows($resql)) {
				while($obj = $this->db->fetch_object($resql)) {
					$this->certifications[] = $obj;
				}
			}

			$this->db->free($resql);

			return $this->certifications;
		}
		else {
			$this->error="Error ".$this->db->lasterror();
			return -1;
		}
	}

    /**
     *  Create in database
     *
     *  @param      User	$user       User that create
     *  @return     int      			<0 if KO, >0 if OK
     */
	function create($user) {
		global $conf, $langs;
		$error = 0;

		/// Clean parameters
		$this->fk_user_appro  = (int) trim($this->fk_user_appro);
		$this->fk_user_ben    = (int) trim($this->fk_user_ben);
		$this->cert_type 	  = (int) trim($this->cert_type);

		// Check parameters
		if (empty($this->fk_user_appro) || $this->fk_user_appro <= 0 || $this->fk_user_ben <= 0) {
			$this->error = 'ErrorBadParameter';
			return -1;
		}

		$this->db->begin();

		// Insert into llx_payment_salary
		$sql = "INSERT INTO ".MAIN_DB_PREFIX. $this->table_element ." (fk_user_appro, fk_user_ben, cert_type, entity, status)";
		$sql.= " VALUES (". $this->fk_user_appro .",". $this->fk_user_ben. ",". $this->cert_type .",". $this->entity .", 0)";

		dol_syslog(get_class($this)."::create", LOG_DEBUG);
		$result = $this->db->query($sql);
		
		if ($result) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

			if ($this->id > 0) {
				$this->db->commit();
				return $this->id;
			}
			else {
				$this->db->rollback();
				return -2;
			}
		}
		else {
			$this->error = $this->db->error();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Update status
	 *
	 * @return  int         			<0 if KO, >0 if OK
	 */
	public function updateStatus() {
		$error = 0;

		// Clean parameters
		$this->fk_user_appro = (int) trim($this->fk_user_appro);
		$this->fk_user_ben   = (int) trim($this->fk_user_ben);
		$this->cert_type 	 = (int) trim($this->cert_type);
		
		if (!$this->status)
			return -1;

		$this->db->begin();

		// Update request
		$sql = "UPDATE ".MAIN_DB_PREFIX. $this->table_element ." SET";
		$sql.= " status=". $this->db->escape($this->status). ", date_appro = '". date('Y-m-d') ."'";
		$sql.= " WHERE rowid=". $this->id;

		dol_syslog(get_class($this)."::update", LOG_DEBUG);
		$resql = $this->db->query($sql);
		
		if (!$resql) {
			$this->error="Error ".$this->db->lasterror();
			return -1;
		}

		if (!$error) {
			$this->db->commit();
			return $this->id;
		}
		else {
			$this->db->rollback();
			$this->error="Error ".$this->db->lasterror();
			return -1;
		}
	}

	public function statusLevel($statut)
	{
		global $langs;

		if ($statut == 0) return $langs->trans('DraftCP').' '.img_picto($langs->trans('DraftCP'),'statut0');				// Draft
		if ($statut == 1) return $langs->trans('ToReviewCP').' '.img_picto($langs->trans('ToReviewCP'),'statut1');		// Waiting approval
		if ($statut == 2) return $langs->trans('ApprovedCP').' '.img_picto($langs->trans('ApprovedCP'), 'statut4');
		if ($statut == 3) return $langs->trans('CancelCP').' '.img_picto($langs->trans('CancelCP'),'statut5');
		if ($statut == 4) return $langs->trans('RefuseCP').' '.img_picto($langs->trans('RefuseCP'),'statut5');

		return $statut;
    }

    function checkCertificateDuplication($appro, $ben, $type, $entity=0) {
		global $langs;

		// if ($appro <= 0 || $ben <= 0 || $type <= 0 || $entity <= 0) {
		// 	return -1;
		// }
		if ($appro <= 0 || $ben <= 0 || $type <= 0) {
			return -1;
		}

		$sql = "SELECT t.rowid FROM ".MAIN_DB_PREFIX. $this->table_element ." as t";
		// $sql.= " WHERE t.fk_user_appro = ". $appro ." AND t.fk_user_ben = ". $ben ." AND t.cert_type = ". $type ." AND t.entity = ".$entity;
		$sql.= " WHERE t.fk_user_appro = ". $appro ." AND t.fk_user_ben = ". $ben ." AND t.cert_type = ". $type ;
		$sql .= ' AND t.entity ='.$conf->entity;

		dol_syslog(get_class($this)."::checkDuplication", LOG_DEBUG);
		$resql = $this->db->query($sql);
		$exists = false;

		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$exists = true;
			}
			
			$this->db->free($resql);

			if (false !== $exists)
				return 1;
			return 0;
		}
		else {
			$this->error="Error ".$this->db->lasterror();
			return -1;
		}
	}

	public function convertSalaryToLetters($montant, $devise1='', $devise2='')
	{
	    if(empty($devise1)) $dev1='Dirhams';
	    else $dev1=$devise1;
	    if(empty($devise2)) $dev2='Centimes';
	    else $dev2=$devise2;

	    $valeur_entiere=intval($montant);
	    $valeur_decimal=intval(round($montant-intval($montant), 2)*100);
	    $dix_c  = intval($valeur_decimal%100/10);
	    $cent_c 	= intval($valeur_decimal%1000/100);
	    $unite[1] 	= $valeur_entiere%10;
	    $dix[1] 	= intval($valeur_entiere%100/10);
	    $cent[1] 	= intval($valeur_entiere%1000/100);
	    $unite[2] 	= intval($valeur_entiere%10000/1000);
	    $dix[2] 	= intval($valeur_entiere%100000/10000);
	    $cent[2] 	= intval($valeur_entiere%1000000/100000);
	    $unite[3] 	= intval($valeur_entiere%10000000/1000000);
	    $dix[3] 	= intval($valeur_entiere%100000000/10000000);
	    $cent[3] 	= intval($valeur_entiere%1000000000/100000000);
	    $chif=array('', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix sept', 'dix huit', 'dix neuf');
	    $secon_c='';
	    $trio_c='';
	    for($i=1; $i<=3; $i++){
	      $prim[$i]='';
	      $secon[$i]='';
	      $trio[$i]='';
	      if($dix[$i]==0){
	        $secon[$i]='';
	        $prim[$i]=$chif[$unite[$i]];
	      }
	      else if($dix[$i]==1){
	        $secon[$i]='';
	        $prim[$i]=$chif[($unite[$i]+10)];
	      }
	      else if($dix[$i]==2){
	        if($unite[$i]==1){
	          $secon[$i]='vingt et';
	          $prim[$i]=$chif[$unite[$i]];
	        }
	        else {
	          $secon[$i]='vingt';
	          $prim[$i]=$chif[$unite[$i]];
	        }
	      }
	      else if($dix[$i]==3){
	        if($unite[$i]==1){
	          $secon[$i]='trente et';
	          $prim[$i]=$chif[$unite[$i]];
	        }
	        else {
	          $secon[$i]='trente';
	          $prim[$i]=$chif[$unite[$i]];
	        }
	      }
	      else if($dix[$i]==4){
	        if($unite[$i]==1){
	          $secon[$i]='quarante et';
	          $prim[$i]=$chif[$unite[$i]];
	        }
	        else {
	          $secon[$i]='quarante';
	          $prim[$i]=$chif[$unite[$i]];
	        }
	      }
	      else if($dix[$i]==5){
	        if($unite[$i]==1){
	          $secon[$i]='cinquante et';
	          $prim[$i]=$chif[$unite[$i]];
	        }
	        else {
	          $secon[$i]='cinquante';
	          $prim[$i]=$chif[$unite[$i]];
	        }
	      }
	      else if($dix[$i]==6){
	        if($unite[$i]==1){
	          $secon[$i]='soixante et';
	          $prim[$i]=$chif[$unite[$i]];
	        }
	        else {
	          $secon[$i]='soixante';
	          $prim[$i]=$chif[$unite[$i]];
	        }
	      }
	      else if($dix[$i]==7){
	        if($unite[$i]==1){
	          $secon[$i]='soixante et';
	          $prim[$i]=$chif[$unite[$i]+10];
	        }
	        else {
	          $secon[$i]='soixante';
	          $prim[$i]=$chif[$unite[$i]+10];
	        }
	      }
	      else if($dix[$i]==8){
	        if($unite[$i]==1){
	          $secon[$i]='quatre-vingts et';
	          $prim[$i]=$chif[$unite[$i]];
	        }
	        else {
	          $secon[$i]='quatre-vingt';
	          $prim[$i]=$chif[$unite[$i]];
	        }
	      }
	      else if($dix[$i]==9){
	        if($unite[$i]==1){
	          $secon[$i]='quatre-vingts et';
	          $prim[$i]=$chif[$unite[$i]+10];
	        }
	        else {
	          $secon[$i]='quatre-vingts';
	          $prim[$i]=$chif[$unite[$i]+10];
	        }
	      }
	      if($cent[$i]==1) $trio[$i]='cent';
	      else if($cent[$i]!=0 || $cent[$i]!='') $trio[$i]=$chif[$cent[$i]] .' cents';
	    }

	    $output = '';
	    $chif2=array('', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingts', 'quatre-vingts dix');
	    $secon_c=$chif2[$dix_c];
	    if($cent_c==1) $trio_c='cent';
	    else if($cent_c!=0 || $cent_c!='') $trio_c=$chif[$cent_c] .' cents';

	    if(($cent[3]==0 || $cent[3]=='') && ($dix[3]==0 || $dix[3]=='') && ($unite[3]==1))
	      $output .= $trio[3]. '  ' .$secon[3]. ' ' . $prim[3]. ' million ';
	    else if(($cent[3]!=0 && $cent[3]!='') || ($dix[3]!=0 && $dix[3]!='') || ($unite[3]!=0 && $unite[3]!=''))
	      $output .= $trio[3]. ' ' .$secon[3]. ' ' . $prim[3]. ' millions ';
	    else
	       $output .= $trio[3]. ' ' .$secon[3]. ' ' . $prim[3];

	    if(($cent[2]==0 || $cent[2]=='') && ($dix[2]==0 || $dix[2]=='') && ($unite[2]==1))
	      $output .= ' mille ';
	    else if(($cent[2]!=0 && $cent[2]!='') || ($dix[2]!=0 && $dix[2]!='') || ($unite[2]!=0 && $unite[2]!=''))
	      $output .= $trio[2]. ' ' .$secon[2]. ' ' . $prim[2]. ' milles ';
	    else
	      $output .= $trio[2]. ' ' .$secon[2]. ' ' . $prim[2];

	    $output .= $trio[1]. ' ' .$secon[1]. ' ' . $prim[1];

	    $output .= ' '. $dev1 .' ' ;

	    if(($cent_c=='0' || $cent_c=='') && ($dix_c=='0' || $dix_c==''))
	      $output .= ' ';
	    else
	      $output .= $trio_c. ' et ' .$secon_c. ' ' . $dev2;

	  	return $output;
	}
}
