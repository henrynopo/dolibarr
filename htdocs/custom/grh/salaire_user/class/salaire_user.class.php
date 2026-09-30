<?php
dol_include_once('/grh/db/nx_db.class.php');

class salaire_user extends NX_db{

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
	public $element = 'salaire_user';
	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'salaire_user';

	public $rowid;
	public $year_point ;
	public $month_point;
	public $created_by;
	public $fk_user;
	public $rows = array();
	public $now;
    public $salary;
    public $salary_base;
    public $thm;
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
		$this->salary			    = $this->salary ? $this->db->escape($this->salary): 0;
		$this->salary_base			    = $this->salary_base ? $this->db->escape($this->salary_base): 0;
		$this->thm			    = $this->thm ? $this->db->escape($this->thm): 0;
		$this->fk_user			    = $this->fk_user ? $this->db->escape($this->fk_user): null;
		//$this->fk_project			    = $this->fk_project ? $this->db->escape($this->fk_project): null;

		// Insert request
		$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . $this->table_element .'(created_by,fk_user,salary,salary_base,thm,month_point,year_point,created_at,updated_at,entity) VALUES (';
		$sql .= ''.$this->created_by.', '.$this->fk_user.',"'.$this->salary.'","'.$this->salary_base.'",'.$this->thm.','.$this->month_point.',
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
public function fetch($id)
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . $this->table_element ;
		$sql.=' WHERE  rowid = ' . $id;
		$sql .= ' AND entity='.$conf->entity;
		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 			   = $this->db->fetch_object($resql);
				$this->year_point  = $obj->year_point;
				$this->rowid       = $obj->rowid;
				$this->month_point = $obj->month_point;
				$this->salary = $obj->salary;
				$this->salary_base = $obj->salary_base;
				$this->thm = $obj->thm;
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

		$sql = 'SELECT *  FROM ' . MAIN_DB_PREFIX .$this->table_element;
         //$filter.=" and p.fk_user=u.rowid";
		$sql .= ' WHERE entity='.$conf->entity;
		if (!empty($filter)) {
			$sql .= ' WHERE '.$filter;
		}
		
		$sql .= " GROUP BY  fk_user,month_point,year_point";

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
				$line->salary      = $obj->salary ;
				$line->salary_base = $obj->salary_base ;
				$line->thm 	       = $obj->thm ;
				$line->entity 	   = $obj->entity ;
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

	

	public function getSalary($mois=0,$annee=0,$iduser=0){

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
		}
		if($iduser != 0 || $annee !=0 || $mois !=0)
			$sql .= ' AND entity='.$conf->entity;
		else $sql .= ' WHERE entity='.$conf->entity;
		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 		   = $this->db->fetch_object($resql);
				$this->salary	   = $obj->salary ;
			}

			$this->db->free($resql);

			if ($numrows) {
				return $this->salary;
			} else {
				return 0;
			}
		} else {
	
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return 0;
		}
	}

	////////////////////////////////////
	public function getSalarybase($mois=0,$annee=0,$iduser=0){
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
		}
		
		if($iduser != 0 || $annee !=0 || $mois !=0)
			$sql .= ' AND entity='.$conf->entity;
		else $sql .= ' WHERE entity='.$conf->entity;

		$resql = $this->db->query($sql);
		
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 		   = $this->db->fetch_object($resql);
				$this->salary_base	   = $obj->salary_base ;
			}

			$this->db->free($resql);

			if ($numrows) {
				return $this->salary_base;
			} else {
				return 0;
			}
		} else {
	
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return 0;
		}
	}

	////////////////////////////////////
	public function updateSalarybase($mois=0,$annee=0){
		global $conf;
		
        dol_syslog(__METHOD__, LOG_DEBUG);

        $sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . $this->table_element ;
		$sql .= ' WHERE year_point= ' . '"'.$annee.'"';
		$sql .= ' and month_point= ' . '"'.$mois.'"';
		$sql .= ' AND entity='.$conf->entity;
		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				while ($obj = $this->db->fetch_object($resql)) {
					if(empty($obj->salary_base)){
						$userp = new User($this->db);
				    	$userp->fetch($obj->fk_user);
						$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element ;
						$sql .= ' SET salary_base = '.$userp->array_options['options_nx_salaire_base'];
						$sql .= ' WHERE rowid='. $obj->rowid;
						$resql2 = $this->db->query($sql);
					}
				}
			}

			$this->db->free($resql);

			if ($numrows) {
				return $this->salary_base;
			} else {
				return 0;
			}
		} else {
	
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return 0;
		}

		
		
		if($iduser != 0 || $annee !=0 || $mois !=0)
			$sql .= ' AND entity='.$conf->entity;
		else $sql .= ' WHERE entity='.$conf->entity;

		$resql = $this->db->query($sql);
		
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 		   = $this->db->fetch_object($resql);
				$this->salary_base	   = $obj->salary_base ;
			}

			$this->db->free($resql);

			if ($numrows) {
				return $this->salary_base;
			} else {
				return 0;
			}
		} else {
	
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return 0;
		}
	}

	////////////////////////////////////
	public function getThm($mois=0,$annee=0,$iduser=0){

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
		}

		if($iduser != 0 || $annee !=0 || $mois !=0)
			$sql .= ' AND entity='.$conf->entity;
		else $sql .= ' WHERE entity='.$conf->entity;

		$resql = $this->db->query($sql);
		
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 		   = $this->db->fetch_object($resql);
				$this->thm	   = $obj->thm ;
			}

			$this->db->free($resql);

			if ($numrows) {
				return $this->thm;
			} else {
				return 0;
			}
		} else {
	
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return 0;
		}
}
	////////////////////////////////////
	public function getID($mois,$annee,$iduser){
		global $conf;

        dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . $this->table_element ;
		
			$sql .= ' WHERE  fk_user= ' . '\'' . $iduser . '\'';
		
			$sql .= ' and year_point= ' . '\'' . $annee . '\'';
		
			$sql .= ' and month_point= ' . '\'' . $mois . '\'';
		
		if($iduser != 0 || $annee !=0 || $mois !=0)
			$sql .= ' AND entity='.$conf->entity;
		else $sql .= ' WHERE entity='.$conf->entity;

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



	public function getUsers()
    {
    	global $conf;
    	$sql = "SELECT rowid, lastname , firstname ";
        $sql.= " FROM ".MAIN_DB_PREFIX ."user ";
		$sql .= " WHERE entity IN (0,".$conf->entity.")";
        
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

    public function getYears()
    {
    	global $conf;
    	 $sql = "SELECT DISTINCT year_point ";
        
        $sql.= " FROM ".MAIN_DB_PREFIX .$this->table_element;
		$sql .= " WHERE entity =".$conf->entity;
        $resql = $this->db->query($sql);
    	$years = array();
		if ($resql) {
			$num = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				$years[$obj->year_point] = $obj->year_point;
			}
			$this->db->free($resql);
    	}

    	return $years;
    }

    public function getMonths($year)
    {
    	global $conf;
    	$sql = "SELECT DISTINCT month_point ";
        
        $sql.= " FROM ".MAIN_DB_PREFIX .$this->table_element;
        $sal.= " WHERE year_point=".$year; 
		$sql.= " AND entity =".$conf->entity;
    	$resql 		 = $this->db->query($sql);
    	$months = array();
		if ($resql) {
			$num = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				$months[$obj->month_point] = $obj->month_point;
			}
			$this->db->free($resql);
    	}

    	return $months;
    }

    public function getUsersWithS($salary=true,$year,$mois)
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

    public function getUsersCNSS($year,$mois)
    {
    	global $conf;
		$sql = "SELECT u.fk_user as rowid ";

		$sql.= " FROM ".MAIN_DB_PREFIX ."salaire_user u , ".MAIN_DB_PREFIX."user_extrafields e";
		$sql.=' WHERE u.fk_user=e.fk_object ';
		$sql .= ' AND u.year_point= ' . '\'' . $year . '\'';
		$sql .= ' AND u.month_point= ' . '\'' . $mois . '\'';
		$sql .= " AND u.entity =".$conf->entity;
		$sql.=" ORDER BY e.nx_cnss ASC";

    	// echo $sql;
    	$resql 		 = $this->db->query($sql);
    	$users = array();
		if ($resql) {
			$num = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				$users[$obj->rowid] = $obj->rowid;
			}
			$this->db->free($resql);
    	}

    	return $users;
    }

    public function getOthearUsers()
    {
    	global $conf;
    	$sql = "SELECT u.rowid as rowid ";
        
        $sql.= " FROM ".MAIN_DB_PREFIX ."user u";
       
        $sql.= " WHERE u.statut = 0 OR u.stag = 1 ";
		$sql.= " AND u.entity =".$conf->entity;
       
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
		$sql .= " AND entity =".$conf->entity;
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



	public function Check_exist_SU($mois,$annee){

		global $conf;
        dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'salaire_user';
		$sql .= ' WHERE year_point= ' . '\'' . $annee . '\'';
		$sql .= ' and month_point= ' . '\'' . $mois . '\'';
		$sql .= " AND entity =".$conf->entity;
		$resql = $this->db->query($sql);
		
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if($numrows){
				// print_r($numrows);
				return $numrows;
			} else {
				return 0;
			}
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			print_r($this->errors);
			return 0;
		}
	}

	public function getUsersFromUS($year,$mois)
    {
    	global $conf;
    	 $sql = "SELECT fk_user ";
        
        $sql.= " FROM ".MAIN_DB_PREFIX ."salaire_user ";
        $sql .= ' WHERE year_point= ' . '\'' . $year . '\'';
		$sql .= ' and month_point= ' . '\'' . $mois . '\'';
		$sql .= " AND entity =".$conf->entity;
    	$resql 		 = $this->db->query($sql);
    	$users = array();
		if ($resql) {
			$num = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				$users[$obj->fk_user] = $obj->fk_user;
			}
			$this->db->free($resql);
    	}

    	return $users;
    }




   
}
