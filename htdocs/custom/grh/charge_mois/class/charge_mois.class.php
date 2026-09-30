<?php
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class charge_mois extends Commonobject{

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

	public $id;
	public $id_charge;
	public $datec;
	public $discription;
	public $montant;
	public $cat;
	public $prix;
	public $bon;
	public $quantity;

	public function __construct($db)
	{
		$this->db 		 = $db;
		$this->datec 	 = new \DateTime("now");
		$this->datec 	 = $this->datec->format('Y-m-d');
		return 1;
	}

	public function getNomUrl($withpicto = 0,  $id = null, $ref = null)
    {
        global $langs;

        $result	= '';
        $setRef = (null !== $ref) ? $ref : '';
        $id  	= ($id  ?: '');
        $label  = $langs->trans("Show").': '. $setRef;

        $link 	 = '<a href="'.dol_buildpath('/grh/charge_mois/card.php?id='. $id ,1).'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        $linkend ='</a>';
        $picto   = 'elemt@grh';

        if ($withpicto) $result.=($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$link.$setRef.$linkend;
        return $result;
    }

	public function getNomUrl2($withpicto = 0,  $id = null, $ref = null)
    {
        global $langs;

        $result	= '';
        $setRef = (null !== $ref) ? $ref : '';
        $id  	= ($id  ?: '');
        $label  = $langs->trans("Show").': '. $setRef;

        $link 	 = '<a href="'.dol_buildpath('/grh/charge_mois/charge.php?id='. $id ,1).'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        $linkend ='</a>';
        $picto   = 'elemt@grh';

        if ($withpicto) $result.=($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$link.$setRef.$linkend;
        return $result;
    }

	public function create()
	{		
		global $conf;
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "charge_mois" . " (datec,discription,cat,entity) VALUES (";
		$sql .= " '".$this->datec."' , '".$this->discription."' ,".$this->cat.", ".$conf->entity.") ; ";

		$this->db->begin();
		$resql = $this->db->query($sql);

		if (!empty($this->prix)) {

			$this->db->free($resql);

			$sql = " SELECT MAX(id) as 'max' FROM " . MAIN_DB_PREFIX . "charge_mois ";
			$sql .= ' WHERE entity ='.$conf->entity;
			$this->db->begin();
			$resql = $this->db->query($sql);

			$max;
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
						$max = $obj->max;
				}
			}
			$this->db->free($resql);

			$sql = " INSERT INTO " . MAIN_DB_PREFIX . "charge_mois_entre" . " (id_charge,prix,quantity,entity) VALUES (";
			$sql .= $max." , ".$this->prix." , 1, ".$conf->entity.") ; ";

			$this->db->begin();
			$resql = $this->db->query($sql);

		}	

		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error charge_mois ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			var_dump($errors);
			die();
			return -1;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	public function create_charge()
	{
		global $conf;
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "charge_mois_entre" . " (id_charge,date_entre,bon,prix,quantity,entity) VALUES (";
		$sql .= $this->id_charge." , '".$this->datec."' , ".$this->bon." , ".$this->prix." ,".$this->quantity.",".$conf->entity.") ; ";

		$this->db->begin();
		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();

			$this->errors[] = 'Error charge_mois ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			var_dump($errors);
			die();
			return -1;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	public function fetch($id)
	{
		global $conf;
		$sql  ="SELECT c.* , e.libele ";
		$sql .="FROM ". MAIN_DB_PREFIX ."charge_mois c , ". MAIN_DB_PREFIX ."charge_mois_cat e ";
		$sql .="WHERE c.id = ".$id." and e.id_cat = c.cat  ";
		$sql .=' AND c.entity ='.$conf->entity;
		$sql .=' AND e.entity ='.$conf->entity;
		
		$resql = $this->db->query($sql);
		$item = array();

		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
					$item['id'] = $obj->id;
					$item['datec'] = $obj->datec;
					$item['discription'] = $obj->discription;
					$item['cat'] = $obj->cat;
					$item['libele'] = $obj->libele;
			}
			$this->db->free($resql);
			return $item;
		}
	}

	public function update_charge($id, array $data)
	{
		if (!$id || $id <= 0)
			return false;

		// Update request
		$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'charge_mois_entre' . ' SET ';

		if (count($data) && is_array($data))
			foreach ($data as $key => $val) {
				$val = is_numeric($val) ? $val : '"'. $val .'"';
				$sql .= '`'. $key. '` = '. $val .',';
			}

		$sql  = substr($sql, 0, -1);
		$sql .= ' WHERE id_entre = ' . $id;

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



	public function get_charge($id, $field = "id_charge")
	{
		global $conf;
		$sql  ="SELECT * FROM ". MAIN_DB_PREFIX ."charge_mois_entre WHERE ".$field." = ".$id; 
		$sql .= ' AND entity ='.$conf->entity;
		$resql = $this->db->query($sql);
		$item = array();

		if ($resql) {
			$i = 0;
			while ($obj = $this->db->fetch_object($resql)) {
				$it = array();
					$it['id_charge'] = $obj->id_charge;
					$it['id_entre'] = $obj->id_entre;
					$it['date_entre'] = $obj->date_entre;
					$it['bon'] = $obj->bon;
					$it['prix'] = $obj->prix;
					$it['quantity'] = $obj->quantity;

					$item[$i] = $it;
					
					$i++;
			}
			$this->db->free($resql);
			return $item;
		}
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param string $sortorder Sort Order
	 * @param string $sortfield Sort field
	 * @param int    $limit     offset limit
	 * @param int    $offset    offset limit
	 * @param array  $filter    filter array
	 * @param string $filtermode filter mode (AND or OR)
	 *
	 * @return int <0 if KO, >0 if OK
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = '', $filtermode = 'AND')
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql  = "SELECT c.id, c.discription, c.datec, c.cat, SUM(e.prix * e.quantity) as 'montant' ";
		$sql .= "FROM ".MAIN_DB_PREFIX."charge_mois c ";
		$sql .= "LEFT JOIN ".MAIN_DB_PREFIX."charge_mois_entre e ON `id` = `id_charge` ";
		$sql .= ' WHERE c.entity ='.$conf->entity;
		// $sql .= ' AND e.entity ='.$conf->entity;
		$sql .= ' group by c.id ';

		if (!empty($filter)) {
			$sql .= " having 1>0 ".$filter; 
			//SUM(e.prix * e.quantity) = 4500 and c.`discription` like '%test%' 
		}
			
		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}

		$this->rows = array();
		$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new stdClass;
					$line->id 			 	= $obj->id;
					$line->cat 			 	= $obj->cat;
					$line->discription 	= $obj->discription;
					$line->datec 		 	= $this->db->jdate($obj->datec);
					$line->montant 			= ($obj->montant != null) ? $obj->montant : 0;
					$line->entity 			= $obj->entity;
					$this->rows[] = $line;
			}
			$this->db->free($resql);

			return $num;
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	/**
	 * Update object into database
	 *
	 * @return int <0 if KO, >0 if OK
	 */
	public function update($id, array $data)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		if (!$id || $id <= 0)
			return false;

		// Update request
		$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'charge_mois' . ' SET ';

		if (count($data) && is_array($data))
			foreach ($data as $key => $val) {
				$val = is_numeric($val) ? $val : '"'. $val .'"';
				$sql .= '`'. $key. '` = '. $val .',';
			}

		$sql  = substr($sql, 0, -1);
		$sql .= ' WHERE id = ' . $id;

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


	public function delete()
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$this->db->begin();

		$sql 	= 'DELETE FROM ' . MAIN_DB_PREFIX . 'charge_mois' .' WHERE id = ' . $this->id;
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

	public function delete_charge($id)
	{
		$sql 	= 'DELETE FROM ' . MAIN_DB_PREFIX . 'charge_mois_entre' .' WHERE id_entre = ' . $id;
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

	public function get_part_date($dt = 'year' ,$filter = '')
	{
		global $conf;
		$sql = "SELECT distinct ".$dt."(`datec`) as 'part' FROM ";
		$sql .= MAIN_DB_PREFIX . "charge_mois";
		$sql .= ' WHERE entity ='.$conf->entity;

		if (!empty($filter)) {
			$sql .= " ".$filter;
		}
		
		$resql = $this->db->query($sql);
		$dates = array();

		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
					$dates[ $obj->part ] = $obj->part;
			}
			$this->db->free($resql);
			return $dates;
		}
	}

	public function get_cat()
	{
		global $conf;
		$sql  = "SELECT * FROM ".MAIN_DB_PREFIX."charge_mois_cat";
		$sql .= ' WHERE entity ='.$conf->entity;
		
		$resql = $this->db->query($sql);
		$cates = array();

		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
					$cates[$obj->id_cat] = $obj->id_cat;
					$cates[$obj->id_cat] = $obj->libele;
			}
			$this->db->free($resql);
			return $cates;
		}
	}


	public function get_montant($id)
	{
		global $conf;
		$sql  = "SELECT SUM(prix * quantity) as 'montant' ";
		$sql .= "FROM ".MAIN_DB_PREFIX."charge_mois_entre ";
		$sql .= "where id_charge = ".$id;
		$sql .= ' AND entity ='.$conf->entity;

		$resql = $this->db->query($sql);
		$montant;

		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
					$montant = $obj->montant;
			}
			$this->db->free($resql);
			return $montant;
		}
	}

	public function set_montant($id,$montant)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."charge_mois_entre SET prix = ".$montant." WHERE id_charge = ".$id." ;";
		$this->db->begin();
		$resql = $this->db->query($sql);

		if ($resql) {
			return 1;
		}else
			return -1;
	}
	public function get_cat_libele($id)
	{
		global $conf;
		$sql = "SELECT libele FROM ".MAIN_DB_PREFIX."charge_mois_cat WHERE id_cat = ".$id;
		$sql .= ' AND entity ='.$conf->entity;
		$this->db->begin();
		$resql = $this->db->query($sql);
		$libele;

		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
					$libele = $obj->libele;
			}
			$this->db->free($resql);
			return $libele;
		}
	}
}



class charge_mois_cat extends Commonobject{

	public $errors = array();
	public $rows = array();
	public $id_cat;
	public $libele;

	public function __construct($db)
	{
		$this->db = $db;
		return 1;
	}

	public function getNomUrl($withpicto = 0,  $id = null, $ref = null)
    {
        global $langs;

        $result	= '';
        $setRef = (null !== $ref) ? $ref : '';
        $id  	= ($id  ?: '');
        $label  = $langs->trans("Show").': '. $setRef;

        $link 	 = '<a href="'.dol_buildpath('/grh/charge_mois/types/card.php?id='. $id,1) .'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        $linkend ='</a>';
        $picto   = 'elemt@grh';

        if ($withpicto) $result.=($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$link.$setRef.$linkend;
        return $result;
    }
	public function create()
	{
		global $conf;
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "charge_mois_cat (libele,entity) VALUES ( '".$this->libele."', ".$conf->entity." )";

		$this->db->begin();
		$resql = $this->db->query($sql);
		//echo $sql;
		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error charge_mois_cat ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			var_dump($errors);
			die();
			return -1;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	public function fetch($id, $ref = null)
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'charge_mois_cat WHERE id_cat = ' . $id;
		$sql .= ' AND entity ='.$conf->entity;

		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			
			if ($numrows) {
				$obj 					= $this->db->fetch_object($resql);
				$this->id_cat 			= $obj->id_cat;
				$this->libele 	 		= $obj->libele;
				$this->entity 	 		= $obj->entity;
			}

			$this->db->free($resql);

			if ($numrows) {
				return 1 ;
			} else {
				return 0;
			}
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = '', $filtermode = 'AND')
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = "SELECT * FROM ";
		$sql .= MAIN_DB_PREFIX . "charge_mois_cat ";
		$sql .= ' WHERE entity ='.$conf->entity;

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

		//echo $sql."<br>";
		$this->rows = array();
		$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new stdClass;
				$line->id_cat 			= $obj->id_cat;
				$line->libele 	 		= $obj->libele;
				$this->rows[] = $line;
			}
			$this->db->free($resql);

			return $num;
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	public function update($id, array $data)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		if (!$id || $id <= 0)
			return false;

		$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'charge_mois_cat' . ' SET ';

		if (count($data) && is_array($data))
			foreach ($data as $key => $val) {
				$val = is_numeric($val) ? $val : '"'. $val .'"';
				$sql .= '`'. $key. '` = '. $val .',';
			}

		$sql  = substr($sql, 0, -1);
		$sql .= ' WHERE id_cat = ' . $id;

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

	public function delete()
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$this->db->begin();

		$sql 	= 'DELETE FROM ' . MAIN_DB_PREFIX . 'charge_mois_cat' .' WHERE id_cat = ' . $this->id_cat;
		//echo $sql;
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

	public function get_libeles()
	{
		global $conf;
		$sql = "SELECT libele FROM ";
		$sql .= MAIN_DB_PREFIX . "charge_mois_cat";
		$sql .= ' WHERE entity ='.$conf->entity;

		$resql = $this->db->query($sql);
		$libeles = array();

		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$libeles[ $obj->libele ] = $obj->libele;
			}
			$this->db->free($resql);
			return $libeles;
		}
	}
}


?>