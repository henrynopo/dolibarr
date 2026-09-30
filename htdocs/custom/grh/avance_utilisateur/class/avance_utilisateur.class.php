<?php
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class avance_utilisateur extends Commonobject{

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
	public $idavance_utilisateur;
	public $datec;
	public $montant;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct($db)
	{
		$this->db 		 = $db;
		$this->datec 	 = new \DateTime("now");
		$this->datec 	 = $this->datec->format('Y-m-d');
		return 1;
	}


	/**
     *	Return clicable mat (with picto eventually)
     *
     *  @author Yassine Belkaid <y.belkaid@nextconcept.ma>
     *	@param		int			$withpicto		0=_No picto, 1=Includes the picto in the linkn, 2=Picto only
     *	@return		string						String with URL
     */
    /*public function getNomUrl($withpicto = 0,  $id = null, $ref = null)
    {
        global $langs;

        $result	= '';
        $setRef = (null !== $ref) ? $ref : '';
        $id  	= ($id  ?: '');
        $label  = $langs->trans("Show").': '. $setRef;

        $link 	 = '<a href="'.DOL_URL_ROOT.'/avance_utilisateur/index.php?id='. $id .'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        $linkend ='</a>';
        $picto   = 'elemt@grh';

        if ($withpicto) $result.=($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$link.$setRef.$linkend;
        return $result;
    }
*/

    /**
	 * Create object into database
	 *
	 * @return int   id of last inserted id, otherwise -1 if error arised 
	 */
	public function create()
	{
		global $conf;
		//dol_syslog(__METHOD__, LOG_DEBUG);

		// Clean parameters
		//$this->nom 				= $this->nom ? $this->db->escape($this->nom): null;
		//$this->datec 			= $this->datec ? $this->db->escape($this->datec): null;
		// Insert request
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "avance_utilisateur" . " (idavance_utilisateur,datec,montant,entity) VALUES (";
		$sql .= "".$this->idavance_utilisateur." , '".$this->datec."' , ".$this->montant.", ".$conf->entity.")";
		
		$this->db->begin();
		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();

			$this->errors[] = 'Error avance_utilisateur ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			var_dump($errors);
			die();
			return -1;
		} else {
			$this->db->commit();
			return 1;
			//return $this->getLasInsrtedId();
		}
	}

/**
	 * Load object in memory from the database
	 *
	 * @param int    $id  Id object
	 * @param string $ref Ref
	 *
	 * @return int <0 if KO, 0 if not found, >0 if OK
	 */


	public function getNomUrl($withpicto = 0,  $id = null, $ref = null)
    {
        global $langs;

        $result	= '';
        $setRef = (null !== $ref) ? $ref : '';
        $id  	= ($id  ?: '');
        $label  = $langs->trans("Show").': '. $setRef;

        $link 	 = '<a href="'.dol_buildpath('/grh/avance_utilisateur/card.php?id='. $id ,1) .'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        $linkend ='</a>';
        $picto   = 'elemt@grh';

        if ($withpicto) $result.=($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$link.$setRef.$linkend;
        return $result;
    }

	public function fetch($id, $ref = null)
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'avance_utilisateur';
		
		if (null !== $ref) {
			$sql .= ' WHERE ref = ' . '\'' . $ref . '\'';
		} else {
			$sql .= ' WHERE id = ' . $id;
		}
		$sql .= ' AND entity ='.$conf->entity;

		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			
			if ($numrows) {
				$obj 					= $this->db->fetch_object($resql);
				$this->id 				= $obj->id;
				$this->idavance_utilisateur	= $obj->idavance_utilisateur;
				$this->datec 		 	= $this->db->jdate($obj->datec);
				$this->montant 			= $obj->montant;
				$this->entity 			= $obj->entity;
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

		$sql = "SELECT * FROM ";
		$sql .= MAIN_DB_PREFIX . "avance_utilisateur";
		$sql .= ' WHERE entity ='.$conf->entity;

		if (!empty($filter)) {
			$sql .= " ".$filter; //substr($filter, 4); //
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
					$line->idavance_utilisateur 	= $obj->idavance_utilisateur;
					$line->datec 		 	= $this->db->jdate($obj->datec);
					$line->montant 			= $obj->montant;
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
		$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'avance_utilisateur' . ' SET ';

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


	/**
	 * Delete object in database
	 *
	 * @param User $user      User that deletes
	 * @param bool $notrigger false=launch triggers after, true=disable triggers
	 *
	 * @return int <0 if KO, >0 if OK
	 */
	public function delete()
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$this->db->begin();

		$sql 	= 'DELETE FROM ' . MAIN_DB_PREFIX . 'avance_utilisateur' .' WHERE id = ' . $this->id;
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


	public function getids()
    {
    	$this->fetchAll();
    	$ids = array();

    	if (count($this->rows))
    	foreach ($this->rows as $key => $line) {
			$ids[$line->id] = $line->id;    		
    	}

    	return $ids;
    }

	public function getidsavance_utilisateur()
    {
    	$this->fetchAll();
    	$ids = array();

    	if (count($this->rows))
    	foreach ($this->rows as $key => $line) {
			$ids[$line->idavance_utilisateur] = $line->idavance_utilisateur;    		
    	}

    	return $ids;
    }


	public function getmontant()
    {
    	$this->fetchAll();
    	$montants = array();

    	if (count($this->rows))
    	foreach ($this->rows as $key => $line) {
			$montants[$line->montant] = $line->montant;    		
    	}

    	return $montants;
    }

	public function getdates()
    {
    	$this->fetchAll();
    	$dates = array();

    	if (count($this->rows))
    	foreach ($this->rows as $key => $line) {
			$dates[date("Y-m-d", $line->datec)] = date("Y-m-d", $line->datec);   		
    	}

    	return $dates;
    }

	public function get_part_date($dt = 'year' ,$filter = '')
	{
		global $conf;
		$sql = "SELECT distinct ".$dt."(`datec`) as 'part' FROM ";
		$sql .= MAIN_DB_PREFIX . "avance_utilisateur";
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

	public function get_avance_month($year,$month,$userid)
	{
		global $conf;
		$sql = "SELECT SUM(montant) as total FROM ";
		$sql .= MAIN_DB_PREFIX . "avance_utilisateur";
		$sql .= ' WHERE entity ='.$conf->entity;

		$sql.= ' AND YEAR(datec)='.$year;
		$sql.= ' AND MONTH(datec)='.$month;
		$sql.= ' AND idavance_utilisateur='.$userid;
		$sql .=' GROUP BY idavance_utilisateur';
		
		$resql = $this->db->query($sql);
		$result = 0;

		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
					$result = $obj->total;
			}
			$this->db->free($resql);
		}
		return $result;

	}



    	
}
?>