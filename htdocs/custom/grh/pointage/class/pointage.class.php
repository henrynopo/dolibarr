<?php

dol_include_once('/grh/db/nx_db.class.php');

class pointage extends NX_db{

	/**
	 * @var string Error code (or message)
	 * @deprecated
	 * @see test::errors
	 */
	public $error;
	/**
	 * @var string[] Error codes (or messages)
	 */
	public $errors = array();
 /**
	 * @var string Id to identify managed objects
	 */
	public $element = 'l_pointage';
	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'l_pointage';

	public $rowid;
	public $year_point ;
	public $month_point;
	public $created_by;
	public $fk_user;
	public $rows = array();
	public $now;
    public $type;
    public $val;
    public $jour;
    public $somme;
   // public $fk_project;
    public $id;
	public function __construct($db)
	{
		$this->db 		 = $db;
		$this->now 		 = new \DateTime("now");
		$this->now 		 = $this->now->format('Y-m-d H:i:s');
		return 1;
	}

	public function create()
	{
		global $conf;
 		dol_syslog(__METHOD__, LOG_DEBUG);

		// Clean parameters
		$this->created_by 			    = $this->created_by ? $this->db->escape($this->created_by): null;
		$this->year_point			= $this->year_point ? $this->db->escape($this->year_point): 0;
		$this->month_point 			    = $this->month_point ? $this->db->escape($this->month_point): 0;
		$this->type			    = $this->type ? $this->db->escape($this->type): 0;
		$this->val			    = $this->val ? $this->db->escape($this->val): 0;
		$this->jour			    = $this->jour ? $this->db->escape($this->jour): 0;
		$this->fk_user			    = $this->fk_user ? $this->db->escape($this->fk_user): null;

		//$this->fk_project			    = $this->fk_project ? $this->db->escape($this->fk_project): null;

		// Insert request
		$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . $this->table_element .'(created_by,fk_user,type,val,jour,month_point,year_point,created_at,updated_at,entity) VALUES (';
		$sql .= ''.$this->created_by.', '.$this->fk_user.',"'.$this->type.'",'.$this->val.','.$this->jour.','.$this->month_point.',
		'.$this->year_point.',"'.$this->now.'","'.$this->now.'",'.$conf->entity.')';
		$this->db->begin();
		$resql = $this->db->query($sql);
		
		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error Evenement ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			var_dump($errors);
			return -1;
		} else {
			$this->db->commit();
	return $this->getLasInsrtedId();
		}
	}
public function fetch($id=0,$year=0,$month=0)
	{
		global $conf;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . $this->table_element ;
		 if($id!=0) {
			$sql .= ' WHERE fk_user = ' . $id;
		}
		if($month!=0){
	   $sql .= ' and month_point = ' . $id;
	
		}
		if($year!=0){
		$sql .= ' and year_point = ' . $id;
	
		}
		$sql .= ' and entity = ' . $conf->entity;

		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 			   = $this->db->fetch_object($resql);
				$this->year_point  = $obj->year_point;
				$this->rowid       = $obj->rowid;
				$this->month_point = $obj->month_point;
				$this->fk_user     = $obj->fk_user;
				$this->entity     = $obj->entity;
			}

			$this->db->free($resql);

			if ($numrows) {
				return 1;
			} else {
				return 0;
			}
		} else {
	
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	public function delete($id)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$this->db->begin();

		$sql 	= 'DELETE FROM ' . MAIN_DB_PREFIX . $this->table_element .' WHERE  rowid = ' . $id;
		$resql 	= $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();

			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		} else {
			$this->db->commit();

			return 1;
		}
	}
	
	public function update($id, array $data)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		if (!$id || $id <= 0)
			return false;

		// Update request
		$sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element  . ' SET ';
		if (count($data) && is_array($data))
			foreach ($data as $key => $val) {
				$val = is_numeric($val) ? $val : '"'. $val .'"';
				$sql .= '`'. $key. '` = '. $val .',';
			}
		$sql  = substr($sql, 0, -1);
		$sql .= ' WHERE  rowid = ' . $id;
        
		$this->db->begin();
		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();
			
			$this->error 	= 'Error ' . $this->db->lasterror();
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		} else {
			$this->db->commit();

			return 1;
		}
	}
	//search
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = '',$id = '',$month='')
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT p.fk_user,p.month_point,p.year_point,CONCAT( u.lastname," ", u.firstname ) as  fs  FROM ' . MAIN_DB_PREFIX .$this->table_element.' p,'.MAIN_DB_PREFIX.'user u';
         $filter.=" and p.fk_user=u.rowid";
		$sql .= ' WHERE p.entity='.$conf->entity;
		if (!empty($filter)) {
			$sql .= ' '.$filter;

		}
		$sql .= " GROUP BY  p.fk_user,p.month_point,p.year_point";
		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}	
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit,$offset);
		}	
		$this->rows = array();
		$resql 		 = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new stdClass;
     	       	$line->year_point  = $obj->year_point;
				$line->rowid       = $obj->rowid;
				$line->month_point = $obj->month_point;
				$line->fk_user     = $obj->fk_user;
				$line->type        = $obj->type ;
				$line->jour 	   = $obj->jour ;
				$line->val	       = $obj->val ;
				$line->entity	   = $obj->entity ;
				//$line->fk_project  = $obj->fk_project ;
				$this->rows[] 	   = $line;
				
			}

			$this->db->free($resql);

			return $num;
		} else {
			echo 'erreur';

			die();
			$this->error 	= 'Error ' . $this->db->lasterror();
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	public function getProjetId($mois=0,$annee=0,$iduser=0,$projet=0){

		global $conf;

        dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . $this->table_element ;
		
		if ($iduser!=0) {
			$sql .= ' WHERE  fk_user= ' . '\'' . $iduser . '\'';
		}
		if ($annee!=0) {
			$sql .= ' and year_point= ' . '\'' . $annee . '\'';
		}
		if ($mois!=0) {
			$sql .= ' and month_point= ' . '\'' . $mois . '\'';
		}/*if ($projet!=-1) {
			$sql .= ' and fk_project= ' . '\'' . $projet . '\'';
		}*/

		$sql .= ' AND entity ='.$conf->entity;

		$resql = $this->db->query($sql);
		
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if($numrows){
				return $numrows;
			} else {
				return 0;
			}
		} else {
	
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		}
}

	public function getVal($mois=0,$annee=0,$iduser=0,$type='',$jour=0){

		global $conf;
        dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . $this->table_element ;
		
		if ($iduser!=0) {
			$sql .= ' WHERE  fk_user= ' . '\'' . $iduser . '\'';
		}
		if ($annee!=0) {
			$sql .= ' and year_point= ' . '\'' . $annee . '\'';
		}
		if ($mois!=0) {
			$sql .= ' and month_point= ' . '\'' . $mois . '\'';
		}if ($type!='') {
			$sql .= ' and type= ' . '\'' . $type . '\'';
		}if ($jour!=0) {
			$sql .= ' and jour= ' . '\'' . $jour . '\'';
		}/*if ($projet!=-1) {
			$sql .= ' and fk_project= ' . '\'' . $projet . '\'';
		}*/
		$sql .= ' AND entity ='.$conf->entity;


		$resql = $this->db->query($sql);
		
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 		   = $this->db->fetch_object($resql);
				$this->val	   = $obj->val ;
			}

			$this->db->free($resql);

			if ($numrows) {
				return $this->val;
			} else {
				return 0;
			}
		} else {
	
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		}
}
////////////////////////////////////
	public function getID($mois=0,$annee=0,$iduser=0,$type='',$jour=0){
		global $conf;

        dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . $this->table_element ;
		
		if ($iduser!=0) {
			$sql .= ' WHERE  fk_user= ' . '\'' . $iduser . '\'';
		}
		if ($annee!=0) {
			$sql .= ' and year_point= ' . '\'' . $annee . '\'';
		}
		if ($mois!=0) {
			$sql .= ' and month_point= ' . '\'' . $mois . '\'';
		}if ($type!='') {
			$sql .= ' and type= ' . '\'' . $type . '\'';
		}if ($jour!=0) {
			$sql .= ' and jour= ' . '\'' . $jour . '\'';
		}
		/*if ($projet!=-1) {
			$sql .= ' and fk_project= ' . '\'' . $projet . '\'';
		}*/
		$sql .= ' AND entity ='.$conf->entity;


		$resql = $this->db->query($sql);
		
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 			   = $this->db->fetch_object($resql);
				$this->rowid	   = $obj->rowid ;
			}

			$this->db->free($resql);

			if ($numrows) {
				return $this->rowid;
			} else {
				return 0;
			}
		} else {
	
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		}
}

	////////////////////////status
	public function fetchStatus($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = '',$id = '',$type='')
	{
		global $conf;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT p.fk_user,p.year_point,p.month_point,p.type,sum(p.val) as somme FROM ' . MAIN_DB_PREFIX .$this->table_element.' p,'.MAIN_DB_PREFIX.'user u';

		$sql .= ' WHERE p.entity='.$conf->entity;
 		if (!empty($type)) {
			$filter.= $type;
		}
		$filter.=" and p.fk_user=u.rowid";
		
		if (!empty($filter)) {
			$sql .= ' '.$filter;
		}
		$sql .= " GROUP BY  p.fk_user,p.month_point,p.year_point";
	   
			
		$resql 		 = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 			   = $this->db->fetch_object($resql);
				$this->somme	   = $obj->somme ;
			}

			$this->db->free($resql);

			if ($numrows) {
				return $this->somme;
			} else {
				return 0;
			}
		} else {
	
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		}

	}

	public function getUsers()
    {
    	global $conf;
    	$sql = "SELECT rowid, lastname , firstname ";
        
        $sql.= " FROM ".MAIN_DB_PREFIX ."user ";
        $sql.= " WHERE entity IN (0,".$conf->entity.')';
    	$resql 		 = $this->db->query($sql);
    	$users = array();
		if ($resql) {
			$num = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				$users[$obj->rowid] = $obj->firstname.' '.$obj->lastname;
			}
			$this->db->free($resql);
    	}

    	return $users;
    }

	public function getUsersWithS($salary=true)
    {
    	global $conf;

    	$sql = "SELECT u.rowid as rowid ";
        
        $sql.= " FROM ".MAIN_DB_PREFIX ."user u, ".MAIN_DB_PREFIX."user_extrafields e";
       
        // $sql.=" WHERE u.statut = 1 AND e.nx_is_stagiaire <> 1 ";
        $sql.=" WHERE u.statut = 1 ";
       
        if($salary)
        	$sql.="AND u.salary IS NOT NULL";
        else
        	$sql.="AND u.salary IS NULL";
        // $sql .= ' AND entity='.$conf->entity;
		$sql .= ' AND u.entity IN ('.getEntity('user').')';
       	// $sql .= " ORDER BY e.clas " ;
    	$resql 		 = $this->db->query($sql);
    	$users = array();
		if ($resql) {
			$num = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				$users[$obj->rowid] = $obj->rowid;
			}
			$this->db->free($resql);
    	}else{
    		$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			print_r($this->errors);
    	}
    	// print_r($users);
    	return $users;
    }

    public function getUsersCNSS()
    {
    	$sql = "SELECT u.rowid as rowid ";
        
        $sql.= " FROM ".MAIN_DB_PREFIX ."user u , ".MAIN_DB_PREFIX."user_extrafields e";
        // $sql.=" WHERE u.statut = 1 AND e.nx_is_stagiaire = 0 ";
        $sql.=" WHERE u.statut = 1 ";
       	$sql.=' AND e.nx_is_declared=1 ';
    	$sql.= 'AND u.rowid=e.fk_object ';
    	$sql.=" ORDER BY e.nx_cnss ASC";
      
    	// echo $sql;
    	$resql 		 = $this->db->query($sql);
    	$users = array();
		if ($resql) {
			$num = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				$users[$obj->rowid] = $obj->firstname.' '.$obj->lastname;
			}
			$this->db->free($resql);
    	}

    	return $users;
    }


	public function getValByMonth($annee,$mois,$iduser){
    	global $conf;

        dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT SUM(val) as total FROM ' . MAIN_DB_PREFIX . $this->table_element ;
		
			$sql .= ' WHERE fk_user= ' . '\'' . $iduser . '\'';
		
			$sql .= ' and year_point= ' . '\'' . $annee . '\'';
		
			$sql .= ' and month_point= ' . '\'' . $mois . '\'';
    		
    		$sql.= ' AND entity='.$conf->entity;
		
			$sql .= ' GROUP BY fk_user ' ;
		$resql = $this->db->query($sql);
		$total = 0;
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 		   = $this->db->fetch_object($resql);
				$total	   = $obj->total ;
			}

			$this->db->free($resql);

		}

		 return $total;
		
	}

	public function nc_getUserInfo($key){
    	global $conf;

		$user_array = [];
		$sql = "SELECT * FROM ".MAIN_DB_PREFIX."user where rowid =".$key;
		$sql .= " AND entity IN (0,".$conf->entity.")";
    	$result = $this->db->query($sql);
		if ($result)
		{
			$obj = $this->db->fetch_object($result);
			// print_r($obj);
			if ($obj)
			{
				$user_array = clone $obj;
			}
		}

		return $user_array;
	}






	public function grhpermissionto($source){
	    if(is_dir($source)) {
	    	@chmod($source, 0775);
	        $dir_handle=opendir($source);
	        while($file=readdir($dir_handle)){
	            if($file!="." && $file!=".."){
	                if(is_dir($source."/".$file)){
	                    @chmod($source."/".$file, 0775);
	                    $this->grhpermissionto($source."/".$file);
	                } else {
	                    @chmod($source."/".$file, 0664);
	                }
	            }
	        }
	        closedir($dir_handle);
	    } else {
	        @chmod($source, 0664);
	    }
	}
   	
   	public function upgradeModuleGRH()
    {
        global $conf, $langs;

		dol_include_once('/grh/core/modules/modgrh.class.php');

        $modcore = new modgrh($this->db);
        
        $lastversion    = $modcore->version;
        $currentversion = dolibarr_get_const($this->db, 'GRH_LAST_VERSION_OF_MODULE', $conf->entity);
        
        if (!$currentversion || ($currentversion && $lastversion != $currentversion)){
            $res = $this->InitGRH();
            if($res)
                dolibarr_set_const($this->db, 'GRH_LAST_VERSION_OF_MODULE', $lastversion, 'chaine', 0, '', $conf->entity);
            return 1;
        }

        return 0;
    }

	public function InitGRH()
	{
    	global $conf, $langs;

		activateModule("modSalaries");
		$langs->load("grh@grh");
		
		$sql2 = "DELETE FROM `".MAIN_DB_PREFIX."menu` WHERE module like '%modgestion_grh%';";
		$resql = $this->db->query($sql2);

		$sql2 = "DELETE FROM `".MAIN_DB_PREFIX."rights_def` WHERE module like '%modgestion_grh%';";
		$resql = $this->db->query($sql2);


		$sql2 = "INSERT INTO `".MAIN_DB_PREFIX."extrafields` (`name`, `entity`, `elementtype`, `tms`, `label`, `type`, `size`, `fieldunique`, `fieldrequired`, `perms`, `pos`, `alwayseditable`, `param`, `list`) VALUES ('nx_cnss', 1, 'user', '2016-02-25 16:33:15', '".$langs->trans('Immatriculation_de_la_CNSS')."', 'varchar', '10', 1, 0, NULL, 2, 1, 'a:1:{s:7:\"options\";a:1:{s:0:\"\";N;}}', 3);";
		$resql = $this->db->query($sql2);

		// if(!$resql){
		// 	print_r($this->db->lasterror());die;
		// }

		$sql2 = "INSERT INTO `".MAIN_DB_PREFIX."extrafields` (`name`, `entity`, `elementtype`, `tms`, `label`, `type`, `size`, `fieldunique`, `fieldrequired`, `perms`, `pos`, `alwayseditable`, `param`, `list`) VALUES ('nx_num_holiday', 1, 'user', '2016-02-25 16:33:26', '".$langs->trans('Nombre_des_jours_de_congé')."', 'int', '4', 0, 0, NULL, 5, 1, 'a:1:{s:7:\"options\";a:1:{s:0:\"\";N;}}', 3);";
		$resql = $this->db->query($sql2);
		

		$sql2 = "INSERT INTO `".MAIN_DB_PREFIX."extrafields` (`name`, `entity`, `elementtype`, `tms`, `label`, `type`, `size`, `fieldunique`, `fieldrequired`, `perms`, `pos`, `alwayseditable`, `param`, `list`) VALUES ('nx_salaire_base', 1, 'user', '2016-02-25 16:33:26', '".$langs->trans('Salaire_de_base')."', 'int', '11', 0, 0, NULL, 1, 1, 'a:1:{s:7:\"options\";a:1:{s:0:\"\";N;}}', 3);";
		$resql = $this->db->query($sql2);

		$sql2 = "INSERT INTO `".MAIN_DB_PREFIX."extrafields` (`name`, `entity`, `elementtype`, `tms`, `label`, `type`, `size`, `fieldunique`, `fieldrequired`, `perms`, `pos`, `alwayseditable`, `param`, `list`) VALUES ('nx_cin', 1, 'user', '2016-02-25 16:33:30', '".$langs->trans('CIN')."', 'varchar', '10', 0, 0, NULL, 1, 1, 'a:1:{s:7:\"options\";a:1:{s:0:\"\";N;}}', 3);";
		$resql = $this->db->query($sql2);

		$sql2 = "INSERT INTO `".MAIN_DB_PREFIX."extrafields` (`name`, `entity`, `elementtype`, `tms`, `label`, `type`, `size`, `fieldunique`, `fieldrequired`, `perms`, `pos`, `alwayseditable`, `param`, `list`) VALUES ('nx_is_declared', 1, 'user', '2016-02-25 16:33:46', '".$langs->trans('Déjà_déclaré')."', 'select', '', 0, 0, NULL, 4, 1, 'a:1:{s:7:\"options\";a:3:{i:0;s:".strlen($langs->trans('No')).":\"".$langs->trans('No')."\";i:1;s:".strlen($langs->trans('Yes')).":\"".$langs->trans('Yes')."\";i:2;s:".strlen($langs->trans('Outgoing')).":\"".$langs->trans('Outgoing')."\";}}', 3);";
		$resql = $this->db->query($sql2);

		// if(!$resql){
		// 	echo $sql2."<br>";
		// 	echo $this->db->lasterror();
		// 	die();
		// }

		$sql2 = "INSERT INTO `".MAIN_DB_PREFIX."extrafields` (`name`, `entity`, `elementtype`, `tms`, `label`, `type`, `size`, `fieldunique`, `fieldrequired`, `perms`, `pos`, `alwayseditable`, `param`, `list`) VALUES ('nx_is_stagiaire', 1, 'user', '2016-02-25 16:33:46', '".$langs->trans('Stagiaire')."', 'select', '', 0, 0, NULL, 3, 1, 'a:1:{s:7:\"options\";a:2:{i:0;s:".strlen($langs->trans('No')).":\"".$langs->trans('No')."\";i:1;s:".strlen($langs->trans('Yes')).":\"".$langs->trans('Yes')."\";}}', 3);";
		$resql = $this->db->query($sql2);

		$sql2 = "INSERT INTO `".MAIN_DB_PREFIX."extrafields` (`name`, `entity`, `elementtype`, `tms`, `label`, `type`, `size`, `fieldunique`, `fieldrequired`, `perms`, `pos`, `alwayseditable`, `param`, `list`) VALUES ('underline1', 1, 'user', '2016-02-25 16:32:59', '".$langs->trans('Module_Gestion_RH')."', 'separate', '', 0, 0, NULL, 0, 1, 'a:1:{s:7:\"options\";a:1:{s:0:\"\";N;}}', 3);";
		$resql = $this->db->query($sql2);

		$sql2 = "INSERT INTO `".MAIN_DB_PREFIX."extrafields` (`name`, `entity`, `elementtype`, `tms`, `label`, `type`, `size`, `fieldunique`, `fieldrequired`, `perms`, `pos`, `alwayseditable`, `param`, `list`) VALUES ('underline2', 1, 'user', '2016-02-25 16:32:59', '', 'separate', '', 0, 0, NULL, 12, 1, 'a:1:{s:7:\"options\";a:1:{s:0:\"\";N;}}', 3);";
		$resql = $this->db->query($sql2);



		
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."antfamily` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."antpersnl` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."aparielcir` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."aparieldig` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."aparielend` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."apariellm` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."aparielres` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."avance_utilisateur` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."certificatm` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."charge_mois` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."charge_mois_cat` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."charge_mois_entre` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."cnss_utilisateur` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."exmclinic` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."exmcomp` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."exmcontrl` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."l_pointage` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."medical` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."neropsy` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."salaire_user` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."vacination` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);


		return 1;
   	}
}
