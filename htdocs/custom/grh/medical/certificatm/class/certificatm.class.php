<?php
/* Copyright (C) Yassine Belkaid  y.belkaid@nextconcept.ma
 *
 */

/**
 * \file    grh/certificatm/.class.php
 * \ingroup grh
 * \brief   This file is certificatm CRUD class file
 *
 */

dol_include_once('/grh/db/nx_db.class.php');

/**
 * Class grh
 *
 * Contract Management class
 */
class certificatm extends NX_db
{
	/**
	 * @var string Error code (or message)
	 * @deprecated
	 * @see grh::errors
	 */
	public $error;
	/**
	 * @var string[] Error codes (or messages)
	 */
	public $errors = array();
	/**
	 * @var string Id to identify managed objects
	 */
	public $element = 'certificatm';
	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'certificatm';

	/**
	 * @var int ID
	 */
	public $id;
	public $da;
	public $created_at;
	
	public $now;
	public $lines = array();
 
	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct($db)
	{
		$this->db 		  = $db;
		$this->now 		  = new \DateTime("now");
		$this->now 		  = $this->now->format('Y-m-d H:i:s');
		$this->updated_at = $this->now;
		
		return 1;
	}

	/**
	 * Create object into database
	 *
	 * @return int   id of last inserted id, otherwise -1 if error arised 
	 */
	public function create()
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		// Clean parameters
		$this->da 	= $this->da ?: null;
		$this->created_at 	  	= $this->now;

		// Insert request
		$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . $this->table_element . ' (da, created_at, entity) VALUES (';
		$sql .= $this->da.', "'.$this->created_at.'", '.$conf->entity.')';
		//die($sql);
		$this->db->begin();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->db->rollback();

			$this->error 	= 'Error Concurrents ' . $this->db->lasterror();
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		} else {
			$this->db->commit();

			return $this->getLasInsrtedId();
		}
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param int    $id  Id object
	 * @param string $num num
	 *
	 * @return int <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id = null, $da = null)
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . $this->table_element;

		if (null !== $id) {
			$sql .= ' WHERE rowid = ' . $id;
		} 
		if (null !== $da) {
			$sql .= ' WHERE da = ' . $da;
		} 
		$sql .= ' AND entity = ' . $conf->entity;

		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 			  	  = $this->db->fetch_object($resql);
				$this->id 		  	  = $obj->rowid;
				$this->da = $obj->da;
				$this->entity = $obj->entity;
				
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
	public function fetchAll($da, $sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = '', $filtermode = 'AND')
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . $this->table_element ;
		if($da != 0)
			$sql.=' WHERE da = '.$da;
		if (!empty($filter)) {
			$sql .= substr($filter, 4);
		}
		$sql .= ' AND entity='.$conf->entity;

		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}

		$this->lines = array();
		$resql 		 = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new stdClass;

				$line->id 		  	  = $obj->rowid;
				$line->da             = $obj->da;
				$line->entity         = $obj->entity;
			
				$this->lines[] 	  	  = $line;
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
		$sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element . ' SET ';

		if (count($data) && is_array($data)) {
			foreach ($data as $key => $val) {
				$val = is_numeric($val) ? $val : "'". $val ."'";
				$sql .= '`'. $key. '` = '. $val .',';
			}
		} else 
			return;

		$sql  = substr($sql, 0, -1);
		$sql .= ' WHERE rowid = ' . (int) $id ;

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

		$sql 	= 'DELETE FROM ' . MAIN_DB_PREFIX . $this->table_element .' WHERE rowid = ' . $this->id ;
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

	/**
     *	Return clicable name (with picto eventually)
     *
     *  @author Yassine Belkaid <y.belkaid@nextconcept.ma>
     *	@param		int			$withpicto		0=_No picto, 1=Includes the picto in the linkn, 2=Picto only
     *	@return		string						String with URL
     */
    public function getNomUrl($withpicto = 0,  $id, $da, $num = null)
    {
        global $langs;

        $result	= '';
        $num = (null !== $num) ? $num : '';
        $id  	= ($id  ?: '');
        $label  = $langs->trans("Show").': '. $num;

        $link 	 = '<a href="'.dol_buildpath('/grh/certificatm/index.php?id='. $id .'&daId='. $da ,1).'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        $linkend ='</a>';
        $picto   = $this->defaultPicto;

        if ($withpicto) $result.=($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$link.$num.$linkend;
        return $result;
    }

}
