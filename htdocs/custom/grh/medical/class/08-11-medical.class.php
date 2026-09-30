<?php
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class medical extends Commonobject{

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
	public $datec;
	public $daten;
	public $fk_user;
  	public $status;
  	public $risques;
	public $adress;
	public $rows = array();

	public $now;
	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct($db)
	{
		$this->db 		 = $db;
		$this->now 		 = new \DateTime("now");
		$this->now 		 = $this->now->format('Y-m-d H:i:s');
		return 1;
	}


	/**
     *	Return clicable mat (with picto eventually)
     *
     *  @author Yassine Belkaid <y.belkaid@nextconcept.ma>
     *	@param		int			$withpicto		0=_No picto, 1=Includes the picto in the linkn, 2=Picto only
     *	@return		string						String with URL
     */
    public function getNomUrl($withpicto = 0,  $id = null, $ref = null)
    {
        global $langs;

        $result	= '';
        $setRef = (null !== $ref) ? $ref : '';
        $id  	= ($id  ?: '');
        $label  = $langs->trans("Show").': '. $setRef;

        $link 	 = '<a href="'.dol_buildpath('/grh/medical/index.php?id='. $id ,1).'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        $linkend ='</a>';
        $picto   = 'elemt@grh';

        if ($withpicto) $result.=($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$link.$setRef.$linkend;
        return $result;
    }

    /**
	 * Create object into database
	 *
	 * @return int   id of last inserted id, otherwise -1 if error arised 
	 */
	public function create()
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		// Clean parameters
		// $this->datec				= $this->now ;
		// Insert request
		$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . 'medical' . ' (datec,daten,fk_user,status,risques,adress ) VALUES (';
		$sql .= '"'.$this->datec.'","'.$this->daten.'",'.$this->fk_user.','.$this->status.',"'.$this->risques.'","'.$this->adress.'")';
		//die($sql);
		$this->db->begin();
		$resql = $this->db->query($sql);
		
		if (!$resql) {
			$this->db->rollback();

			$this->errors[] = 'Error medical ' . $this->db->lasterror();
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
	public function fetch($id, $ref = null)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'medical';
		
		if (null !== $ref) {
			$sql .= ' WHERE ref = ' . '\'' . $ref . '\'';
		} else {
			$sql .= ' WHERE rowid = ' . $id;
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 				   = $this->db->fetch_object($resql);
				$this->id 			   = $obj->rowid;
				$this->datec           = $this->db->jdate($obj->datec);
				$this->daten           = $this->db->jdate($obj->daten);
				$this->fk_user           = $obj->fk_user;
			  	$this->status            = $obj->status;
			  	$this->adress            = $obj->adress;
			  	$this->risques           = $obj->risques;
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
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'medical';

		if (!empty($filter)) {
			$sql .= ' WHERE '. substr($filter, 4);
		}
		
		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		
		$this->rows = array();
		$resql 		 = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new stdClass;
					$line->id 			 = $obj->rowid;
					$line->datec           = $this->db->jdate($obj->datec);
					$line->daten           = $this->db->jdate($obj->daten);
					$line->fk_user            = $obj->fk_user;
				  	$line->status             = $obj->status;
				  	$line->adress             = $obj->adress;
				  	$line->risques          = $obj->risques;
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
		$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'medical' . ' SET ';

		if (count($data) && is_array($data))
			foreach ($data as $key => $val) {
				$val = is_numeric($val) ? $val : '"'. $val .'"';
				$sql .= '`'. $key. '` = '. $val .',';
			}

		$sql  = substr($sql, 0, -1);
		$sql .= ' WHERE rowid = ' . $id;

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

		$sql 	= 'DELETE FROM ' . MAIN_DB_PREFIX . 'medical' .' WHERE rowid = ' . $this->id;
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

	
   

    public function getYears()
    {
    	$sql = 'SELECT 	 YEAR(datec) as years FROM ' . MAIN_DB_PREFIX . 'medical';
    	$resql 		 = $this->db->query($sql);
    	$years = array();
		if ($resql) {
			$num = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				$years[$obj->years] = $obj->years;
			}
			$this->db->free($resql);
    	}

    	return $years;
    }

    public function getMedicalHeader()
    {
    	global $langs;
    	$userm = new User($this->db);
    	$userm->fetch($this->fk_user);
    	$html = '<table class="border" width="100%">';
		$html .= '<tr><td width="20%">'.$langs->trans("Ref").'</td>';
		$html .= '<td colspan="2">'. $this->id .'</td></tr>';
		$html .= '<tr><td width="20%">'.$langs->trans("nom").'</td>';
		$html .= '<td colspan="2">'. $userm->lastname .'</td></tr>';
		$html .= '<tr><td width="20%">'.$langs->trans("prenom").'</td>';
		$html .= '<td colspan="2">'. $userm->firstname .'</td></tr>';
		$html .= '<tr><td width="20%">'.$langs->trans("poste").'</td>';
		$html .= '<td colspan="2">'. $userm->job .'</td></tr>';
		$html .= '</table><br />';

		return $html;
    }

    public function check_user($user)
	{
		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'medical WHERE fk_user ='.$user;
		
		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);

			$this->db->free($resql);

			if ($numrows) {
				return 1;
			} else {
				return 0;
			}
		} else {
			return 0;
		}
	}

	 public function getUsers()
    {
    	$sql = 'SELECT DISTINCT fk_user FROM ' . MAIN_DB_PREFIX . 'medical';
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
?>