<?php 
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php'; 
 
class utilisateur_info extends Commonobject{ 
	public $errors = array();
	public $fields;

	public function __construct($db){ 
		$this->db = $db;
		return 1;
	}
	
	public function getNomUrl($withpicto = 0,  $id = null, $ref = null){
        global $langs;

        $result	= '';
        $setRef = (null !== $ref) ? $ref : '';
        $id  	= ($id  ?: '');
        $label  = $langs->trans("Show").': '. $setRef;

        $link 	 = '<a href="'.DOL_URL_ROOT.'/gestion_plan/'.get_class($this).'/card/index.php?id='. $id .'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip"><div class="icon-versement mainvmenu"></div> ';
        $linkend ='</a>';
        $picto   = 'elemt@gestion_plan';

        if ($withpicto) $result.=($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$link.$setRef.$linkend;
        return $result;
    }

	public function create($echo_sql=0,$insert=[],$sql2 = null){

		// if($sql2){
		// 	$sql = $sql2;
		// }else{
		// 	$sql  = "INSERT INTO " . MAIN_DB_PREFIX ."".get_class($this)." ( ";

		// 	foreach ($insert as $column => $value) {
		// 		$alias = (is_numeric($value)) ? "" : "'";
		// 		$sql_column .= " , `".$column."`";
		// 		$sql_value .= " , ".$alias.$value.$alias;
		// 	}

		// 	$sql .= substr($sql_column, 2)." ) VALUES ( ".substr($sql_value, 2)." )";
		// }
		// // $this->db->begin();

		// if ($echo_sql)
		// 	return $sql;

		// $resql = $this->db->query($sql);

		// if (!$resql) {
		// 	$this->db->rollback();
		// 	$this->errors[] = 'Error '.get_class($this).' '. $this->db->lasterror();
		// 	return 0;
		// } 

		// return $this->db->db->insert_id;
	}

	public function update($id, array $data,$echo_sql=0){
		dol_syslog(__METHOD__, LOG_DEBUG);

		if (!$id || $id <= 0)
			return false;

		$sql = 'UPDATE ' . MAIN_DB_PREFIX .'user SET ';

		if (count($data) && is_array($data))
			foreach ($data as $key => $val) {
				$val = is_numeric($val) ? $val : '"'. $val .'"';
				$sql .= '`'. $key. '` = '. $val .',';
			}

		$sql  = substr($sql, 0, -1);
		$sql .= ' WHERE rowid = ' . $id;
		// echo $sql;
		// die();
		

		// $this->db->begin();

		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error '.get_class($this).' : '. $this->db->lasterror();
			return -1;
		} 
		return 1;
	}

	public function delete($echo_sql=0)	{
		//dol_syslog(__METHOD__, LOG_DEBUG);

		// $sql 	= 'DELETE FROM ' . MAIN_DB_PREFIX .get_class($this).' WHERE rowid = ' . $this->rowid;
	
		// if ($echo_sql) {
		// 	echo "<br>".$sql."<br>";
		// }

		// $resql 	= $this->db->query($sql);
		
		// if (!$resql) {
		// 	$this->db->rollback();
		// 	$this->errors[] = 'Error '.get_class($this).' : '.$this->db->lasterror();
		// 	return -1;
		// } 
		// return 1;
	}

	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = '', $filtermode = 'AND')	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		global $conf;
		$sql = "SELECT * FROM ";
		$sql .= MAIN_DB_PREFIX .get_class($this);
		$sql .= ' WHERE entity='.$conf->entity;
		if (!empty($filter)) {
			$sql .= " ".$filter;
		}

		
		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}

		if (!empty($limit)) {
			if($offset==1)
				$sql .= " limit ".$limit;
			else
				$sql .= " limit ".$offset.",".$limit;
		}

		$this->rows = array();
		$resql = $this->db->query($sql);
		//echo $sql;
		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($row=$this->db->fetch_object($resql)) 
			{
				$this->rows[] 	= $row;
			}

			$this->db->free($resql);
			return $num;
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	public function fetch($id, $ref = null){
		//dol_syslog(__METHOD__, LOG_DEBUG);
		global $conf;
		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX .'user u'. ' WHERE rowid = ' . $id;
		$sql .= ' AND u.entity='.$conf->entity;
		$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				$user = new stdClass();
				$user = clone $obj;
			}
			$this->db->free($resql);
    	}

    	// print_r($user);
    	return $user;
	}


	public function getAllUsers($userid="")
    {
    	global $conf;
    	$sql = "SELECT * ";
        
        $sql.= " FROM ".MAIN_DB_PREFIX ."user u WHERE u";
       	if ($userid != "" && $userid != -1) {
        	$sql .=" AND u.rowid = ".$userid;
       	}
        $sql .= ' AND u.entity IN (0,'.$conf->entity);
       	$sql .= " ORDER BY u.declar DESC " ;

    	$resql = $this->db->query($sql);
    	$users = array();
		if ($resql) {
			$num = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				$user = new stdClass();
				$user->rowid 			= $obj->rowid;
				$user->lastname		 	= $obj->lastname;
				$user->firstname 		= $obj->firstname;
				$user->salary_base 		= $obj->salary_base;
				$user->cin 				= $obj->cin;
				$user->mat 				= $obj->mat;
				$user->nb_holiday 		= $obj->nb_holiday;
				$user->situation_familiale 		= $obj->situation_familiale;
				$user->situation_assure 		= $obj->situation_assure;
				$user->nbr_enfants 		= $obj->nbr_enfants;
				$user->date_embauche 	= $obj->date_embauche;
				$user->immatriculation 	= $obj->immatriculation;
				$user->etablissement 	= $obj->etablissement;
				$user->etablissement_opt= $obj->etablissement_opt;
				$user->declar 			= $obj->declar;
				$user->stag 			= $obj->stag;
				$user->entity 			= $obj->entity;

				$users[] = $user;
			}
			$this->db->free($resql);
    	}
    	// echo $sql;
    	// print_r($users);
    	return $users;
    }

}
?>