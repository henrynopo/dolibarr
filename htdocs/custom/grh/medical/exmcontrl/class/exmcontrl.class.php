<?php
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class exmcontrl extends Commonobject{

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
	public $type;
  	public $apte;
  	public $dated;
  	public $datef;
  	public $observation;
  	public $da;
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
    public function getNomUrl($withpicto = 0,  $id = null, $ref = null,$da)
    {
        global $langs;

        $result	= '';
        $setRef = (null !== $ref) ? $ref : '';
        $id  	= ($id  ?: '');
        $label  = $langs->trans("Show").': '. $setRef;

        $link 	 = '<a href="'.dol_buildpath('/grh/medical/exmcontrl/index.php?id='. $id ,1).'&daId='.$da.'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        $linkend ='</a>';
        $picto   = 'icon@grh';

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
		$this->dated				= $this->dated ? $this->db->escape($this->dated) : 'null';
		$this->observation				= $this->observation ? $this->db->escape($this->observation) : null;
		$this->datec				= $this->now;
		// Insert request
		$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . 'exmcontrl' . ' (datec,type,apte,dated,datef,da,observation ) VALUES (';
		$sql .= '"'.$this->datec.'",'.$this->type.','.$this->apte.',"'.$this->dated.'","'.$this->datef.'",'.$this->da.',"'.$this->observation.'")';
		
		$this->db->begin();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->db->rollback();

			$this->errors[] = 'Error exmcontrl ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			var_dump($this->errors);
			//die();
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
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'exmcontrl';
		
		if (null !== $ref) {
			$sql .= ' WHERE ref = ' . '\'' . $ref . '\'';
		} else {
			$sql .= ' WHERE rowid = ' . $id;
		}
		$sql .= ' AND entity='.$conf->entity;

		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 				   = $this->db->fetch_object($resql);
				$this->id 			   = $obj->rowid;
				$this->datec           = $this->db->jdate($obj->datec);
				$this->type           = $obj->type;
			  	$this->apte        = $obj->apte;
			  	$this->dated           = $this->db->jdate($obj->dated);
			  	$this->datef          = $this->db->jdate($obj->datef);
			  	$this->observation     = $obj->observation;
			  	$this->da              = $obj->da;
			  	$this->entity          = $obj->entity;
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

	public function fetchbyDI($id)
	{
		global $conf;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'exmcontrl';
		$sql .= ' WHERE da = ' . $id;
		$sql .= ' AND entity='.$conf->entity;		

		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				$line = new stdClass;
					$line->id 		   = $obj->rowid;
					$line->datec       = $this->db->jdate($obj->datec);
					$line->type        = $obj->type;
				  	$line->apte        = $obj->apte;
				  	$line->dated       = $this->db->jdate($obj->dated);
				  	$line->datef       = $this->db->jdate($obj->datef);
				  	$line->observation = $obj->observation;
				  	$line->da          = $obj->da;
				  	$line->entity      = $obj->entity;
					$this->rows[] = $line;
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

			return 0;
		}
	}


	public function getAptebyDI($id)
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT apte FROM ' . MAIN_DB_PREFIX . 'exmcontrl';
		$sql .= ' WHERE da = ' . $id;
		$sql .= ' AND entity='.$conf->entity;
		$sql .= ' ORDER BY rowid DESC LIMIT 1';
		$apte = 0;
	
		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				  	$apte      = $obj->apte;
			}
			$this->db->free($resql);
			if ($numrows) {
				return $apte;
			} else {
				return -1;
			}
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return 0;
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

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . 'exmcontrl';
		$sql .= ' WHERE entity='.$conf->entity;

		if (!empty($filter)) {
			$sql .= ' '.$filter;
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
					$line->datec         = $this->db->jdate($obj->datec);
					$line->type         = $obj->type;
				  	$line->apte      = $obj->apte;
				  	$line->dated         = $this->db->jdate($obj->dated);
				  	$line->datef        = $this->db->jdate($obj->datef);
				  	$line->observation         = $obj->observation;
				  	$line->da            = $obj->da;
				  	$line->entity            = $obj->entity;
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
		$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'exmcontrl' . ' SET ';

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

		$sql 	= 'DELETE FROM ' . MAIN_DB_PREFIX . 'exmcontrl' .' WHERE rowid = ' . $this->id;
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


    

   


}
?>