<?php
/* Copyright (C) 2007-2012  Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2014       Juanjo Menent       <jmenent@2byte.es>
 * Copyright (C) 2015       Florian Henry       <florian.henry@open-concept.pro>
 * Copyright (C) 2015       Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) ---Put here your own copyright and developer email---
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
 * \file    salariescontracts/salariescontracts.class.php
 * \ingroup salariescontracts
 * \brief   This file is an example for a CRUD class file (Create/Read/Update/Delete)
 *          Put some comments here
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
//require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
//require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

/**
 * Class Salariescontracts
 *
 * Put here description of your class
 */
class Salariescontracts extends CommonObject
{
	/**
	 * @var string Error code (or message)
	 * @deprecated
	 * @see Salariescontracts::errors
	 */
	public $error;
	/**
	 * @var string[] Error codes (or messages)
	 */
	public $errors = array();
	/**
	 * @var string Id to identify managed objects
	 */
	public $element = 'salariescontracts';
	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'salariescontracts';

	/**
	 * @var SalariescontractsLine[] Lines
	 */
	public $lines = array();

	/** @var array */
	public $lines_sc = array();

	/**
	 * @var int ID
	 */
	public $id;
	public $ref;
	/**
	 */
	
	public $fk_user;
	public $fk_user_create;
	public $type;
	public $date_create = '';
	public $start_date = '';
	public $end_date = '';
	public $salarie_sig_date = '';
	public $direction_sig_date = '';
	public $dpae_date = '';
	public $medical_visit_date = '';
	public $description;

	/**
	 */
	

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
		return 1;
	}

	/**
	 * Create object into database
	 *
	 * @param  User $user      User that creates
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 *
	 * @return int <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = false)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$error = 0;

		// Clean parameters
		if (isset($this->fk_user)) {
			 $this->fk_user = trim($this->fk_user);
		}
		if (isset($this->fk_user_create)) {
			 $this->fk_user_create = trim($this->fk_user_create);
		}
		if (isset($this->type)) {
			 $this->type = trim($this->type);
		}
		if (isset($this->description)) {
			 $this->description = trim($this->description);
		}

		// Check parameters
		// Put here code to add control on parameters values

		// Insert request
		$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . $this->table_element . '(';
		
		$sql.= 'fk_user,';
		$sql.= 'fk_user_create,';
		$sql.= 'type,';
		$sql.= 'date_create,';
		$sql.= 'start_date,';
		$sql.= 'end_date,';
		$sql.= 'salarie_sig_date,';
		$sql.= 'direction_sig_date,';
		$sql.= 'dpae_date,';
		$sql.= 'medical_visit_date,';
		$sql.= 'description';

		
		$sql .= ') VALUES (';
		
		$sql .= ' '.(! isset($this->fk_user)?'NULL':$this->fk_user).',';
		$sql .= ' '.(! isset($this->fk_user_create)?'NULL':$this->fk_user_create).',';
		$sql .= ' '.(! isset($this->type)?'NULL':$this->type).',';
		$sql .= ' '.(! isset($this->date_create) || dol_strlen($this->date_create)==0?'NULL':"'".$this->db->idate($this->date_create)."'").',';
		$sql .= ' '.(! isset($this->start_date) || dol_strlen($this->start_date)==0?'NULL':"'".$this->db->idate($this->start_date)."'").',';
		$sql .= ' '.(! isset($this->end_date) || dol_strlen($this->end_date)==0?'NULL':"'".$this->db->idate($this->end_date)."'").',';
		$sql .= ' '.(! isset($this->salarie_sig_date) || dol_strlen($this->salarie_sig_date)==0?'NULL':"'".$this->db->idate($this->salarie_sig_date)."'").',';
		$sql .= ' '.(! isset($this->direction_sig_date) || dol_strlen($this->direction_sig_date)==0?'NULL':"'".$this->db->idate($this->direction_sig_date)."'").',';
		$sql .= ' '.(! isset($this->dpae_date) || dol_strlen($this->dpae_date)==0?'NULL':"'".$this->db->idate($this->dpae_date)."'").',';
		$sql .= ' '.(! isset($this->medical_visit_date) || dol_strlen($this->medical_visit_date)==0?'NULL':"'".$this->db->idate($this->medical_visit_date)."'").',';
		$sql .= ' '.(! isset($this->description)?'NULL':"'".$this->db->escape($this->description)."'");

		$sql .= ')';

		$this->db->begin();

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error ++;
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
		}

		if (!$error) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . $this->table_element);

			if (!$notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action to call a trigger.

				//// Call triggers
				//$result=$this->call_trigger('MYOBJECT_CREATE',$user);
				//if ($result < 0) $error++;
				//// End call triggers
			}
		}

		// Commit or rollback
		if ($error) {
			$this->db->rollback();

			return - 1 * $error;
		} else {
			$this->db->commit();

			return $this->id;
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

		$sql = 'SELECT';
		$sql .= ' t.rowid,';
		
		$sql .= " t.fk_user,";
		$sql .= " t.fk_user_create,";
		$sql .= " t.type,";
		$sql .= " t.date_create,";
		$sql .= " t.start_date,";
		$sql .= " t.end_date,";
		$sql .= " t.salarie_sig_date,";
		$sql .= " t.direction_sig_date,";
		$sql .= " t.dpae_date,";
		$sql .= " t.medical_visit_date,";
		$sql .= " t.description,";
		$sql .= " t.entity";

		
		$sql .= ' FROM ' . MAIN_DB_PREFIX . $this->table_element . ' as t';
		if (null !== $ref) {
			$sql .= ' WHERE t.ref = ' . '\'' . $ref . '\'';
		} else {
			$sql .= ' WHERE t.rowid = ' . $id;
		}
		$sql .= ' AND t.entity='.$conf->entity;

		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj = $this->db->fetch_object($resql);

				$this->id = $obj->rowid;
				
				$this->fk_user = $obj->fk_user;
				$this->fk_user_create = $obj->fk_user_create;
				$this->type = $obj->type;
				$this->date_create = $this->db->jdate($obj->date_create);
				$this->start_date = $this->db->jdate($obj->start_date);
				$this->end_date = $this->db->jdate($obj->end_date);
				$this->salarie_sig_date = $this->db->jdate($obj->salarie_sig_date);
				$this->direction_sig_date = $this->db->jdate($obj->direction_sig_date);
				$this->dpae_date = $this->db->jdate($obj->dpae_date);
				$this->medical_visit_date = $this->db->jdate($obj->medical_visit_date);
				$this->description = $obj->description;

				
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

			return - 1;
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
	public function fetchAll($sortorder='', $sortfield='', $limit=0, $offset=0, $filter, $filtermode='AND')
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT';
		$sql .= ' t.rowid,';
		
		$sql .= " t.fk_user,";
		$sql .= " t.fk_user_create,";
		$sql .= " t.type,";
		$sql .= " t.date_create,";
		$sql .= " t.start_date,";
		$sql .= " t.end_date,";
		$sql .= " t.salarie_sig_date,";
		$sql .= " t.direction_sig_date,";
		$sql .= " t.dpae_date,";
		$sql .= " t.medical_visit_date,";
		$sql .= " t.description";
		$sql .= " t.entity";

		$sql .= ' FROM ' . MAIN_DB_PREFIX . $this->table_element. ' as t';
		$sql .= ' WHERE t.entity='.$conf->entity;
		// Manage filter
		/*$sqlwhere = array();
		if (count($filter) > 0) {
			foreach ($filter as $key => $value) {
				$sqlwhere[] = $key . ' LIKE \'%' . $this->db->escape($value) . '%\'';
			}
		}
		if (count($sqlwhere) > 0) {
			$sql .= ' WHERE ' . implode(' '.$filtermode.' ', $sqlwhere);
		}*/

		if (!empty($filter)) {
			$sql .= ' '.$filter;
		}
		
		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield,$sortorder);
		}

		/*
		if (!empty($limit)) {
		 $sql .=  ' ' . $this->db->plimit($limit + 1, $offset);
		}*/
		$this->lines = array();

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new SalariescontractsLine();

				$line->id = $obj->rowid;
				$line->fk_user = $obj->fk_user;
				$line->fk_user_create = $obj->fk_user_create;
				$line->type = $obj->type;
				$line->date_create = $this->db->jdate($obj->date_create);
				$line->start_date = $this->db->jdate($obj->start_date);
				$line->end_date = $this->db->jdate($obj->end_date);
				$line->salarie_sig_date = $this->db->jdate($obj->salarie_sig_date);
				$line->direction_sig_date = $this->db->jdate($obj->direction_sig_date);
				$line->dpae_date = $this->db->jdate($obj->dpae_date);
				$line->medical_visit_date = $this->db->jdate($obj->medical_visit_date);
				$line->description = $obj->description;

				$this->lines[] = $line;

				$this->ref 	= $obj->rowid;
				$this->id 	= $obj->rowid;
				$this->lines_sc[$i]['rowid'] = $obj->rowid;
				$this->lines_sc[$i]['fk_user'] = $obj->fk_user;
				$this->lines_sc[$i]['fk_user_create'] = $obj->fk_user_create;
				$this->lines_sc[$i]['type'] = $obj->type;
				$this->lines_sc[$i]['date_create'] = $obj->date_create;
				$this->lines_sc[$i]['start_date'] = $obj->start_date;
				$this->lines_sc[$i]['end_date'] = $obj->end_date;
				$this->lines_sc[$i]['salarie_sig_date'] = $obj->salarie_sig_date;
				$this->lines_sc[$i]['direction_sig_date'] = $obj->direction_sig_date;
				$this->lines_sc[$i]['dpae_date'] = $obj->dpae_date;
				$this->lines_sc[$i]['medical_visit_date'] = $obj->medical_visit_date;
				$this->lines_sc[$i]['description'] = $obj->description;

				$i++;
			}
			$this->db->free($resql);

			return $num;
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return - 1;
		}
	}

	/**
	 * Update object into database
	 *
	 * @param  User $user      User that modifies
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 *
	 * @return int <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = false)
	{
		$error = 0;

		dol_syslog(__METHOD__, LOG_DEBUG);

		// Clean parameters
		
		if (isset($this->fk_user)) {
			 $this->fk_user = trim($this->fk_user);
		}
		if (isset($this->fk_user_create)) {
			 $this->fk_user_create = trim($this->fk_user_create);
		}
		if (isset($this->type)) {
			 $this->type = trim($this->type);
		}
		if (isset($this->description)) {
			 $this->description = trim($this->description);
		}

		// Check parameters
		// Put here code to add a control on parameters values

		// Update request
		$sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element . ' SET';
		
		// $sql .= ' fk_user = '.(isset($this->fk_user)?$this->fk_user:"null").',';
		// $sql .= ' fk_user_create = '.(isset($this->fk_user_create)?$this->fk_user_create:"null").',';
		$sql .= ' type = '.(isset($this->type)?$this->type:"null").',';
		$sql .= ' date_create = '.(! isset($this->date_create) || dol_strlen($this->date_create) != 0 ? "'".$this->db->idate($this->date_create)."'" : 'null').',';
		$sql .= ' start_date = '.(! isset($this->start_date) || dol_strlen($this->start_date) != 0 ? "'".$this->db->idate($this->start_date)."'" : 'null').',';
		$sql .= ' end_date = '.(! isset($this->end_date) || dol_strlen($this->end_date) != 0 ? "'".$this->db->idate($this->end_date)."'" : 'null').',';
		$sql .= ' salarie_sig_date = '.(! isset($this->salarie_sig_date) || dol_strlen($this->salarie_sig_date) != 0 ? "'".$this->db->idate($this->salarie_sig_date)."'" : 'null').',';
		$sql .= ' direction_sig_date = '.(! isset($this->direction_sig_date) || dol_strlen($this->direction_sig_date) != 0 ? "'".$this->db->idate($this->direction_sig_date)."'" : 'null').',';
		$sql .= ' dpae_date = '.(! isset($this->dpae_date) || dol_strlen($this->dpae_date) != 0 ? "'".$this->db->idate($this->dpae_date)."'" : 'null').',';
		$sql .= ' medical_visit_date = '.(! isset($this->medical_visit_date) || dol_strlen($this->medical_visit_date) != 0 ? "'".$this->db->idate($this->medical_visit_date)."'" : 'null').',';
		$sql .= ' description = '.(isset($this->description)?"'".$this->db->escape($this->description)."'":"null");
		$sql .= ', entity='.$this->db->escape($this->entity);
        
		$sql .= ' WHERE rowid=' . $this->id;

		$this->db->begin();

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error ++;
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
		}

		if (!$error && !$notrigger) {
			// Uncomment this and change MYOBJECT to your own tag if you
			// want this action calls a trigger.

			//// Call triggers
			//$result=$this->call_trigger('MYOBJECT_MODIFY',$user);
			//if ($result < 0) { $error++; //Do also what you must do to rollback action if trigger fail}
			//// End call triggers
		}

		// Commit or rollback
		if ($error) {
			$this->db->rollback();

			return - 1 * $error;
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
	public function delete(User $user, $notrigger = false)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$error = 0;

		$this->db->begin();

		if (!$error) {
			if (!$notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action calls a trigger.

				//// Call triggers
				//$result=$this->call_trigger('MYOBJECT_DELETE',$user);
				//if ($result < 0) { $error++; //Do also what you must do to rollback action if trigger fail}
				//// End call triggers
			}
		}

		if (!$error) {
			$sql = 'DELETE FROM ' . MAIN_DB_PREFIX . $this->table_element;
			$sql .= ' WHERE rowid=' . $this->id;

			$resql = $this->db->query($sql);
			if (!$resql) {
				$error ++;
				$this->errors[] = 'Error ' . $this->db->lasterror();
				dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			}
		}

		// Commit or rollback
		if ($error) {
			$this->db->rollback();

			return - 1 * $error;
		} else {
			$this->db->commit();

			return 1;
		}
	}

	/**
	 * Load an object from its id and create a new one in database
	 *
	 * @param int $fromid Id of object to clone
	 *
	 * @return int New id of clone
	 */
	public function createFromClone($fromid)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		global $user;
		$error = 0;
		$object = new Salariescontracts($this->db);

		$this->db->begin();

		// Load source object
		$object->fetch($fromid);
		// Reset object
		$object->id = 0;

		// Clear fields
		// ...

		// Create clone
		$result = $object->create($user);

		// Other options
		if ($result < 0) {
			$error ++;
			$this->errors = $object->errors;
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
		}

		// End
		if (!$error) {
			$this->db->commit();

			return $object->id;
		} else {
			$this->db->rollback();

			return - 1;
		}
	}

	/**
	 * Initialise object with example values
	 * Id must be 0 if object instance is a specimen
	 *
	 * @return void
	 */
	public function initAsSpecimen()
	{
		$this->id = 0;
		
		$this->fk_user = '';
		$this->fk_user_create = '';
		$this->type = '';
		$this->date_create = '';
		$this->start_date = '';
		$this->end_date = '';
		$this->salarie_sig_date = '';
		$this->direction_sig_date = '';
		$this->dpae_date = '';
		$this->medical_visit_date = '';
		$this->description = '';
	}

	/**
     * return contract by salary id with filters if available
     *
     * @author Yassine Belkaid <y.belkaid@nextconcept.ma>
     * @return integer
     */
	public function fetchByUser($user_id, $order = '', $filter = '')
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT';
		$sql .= ' t.rowid,';
		
		$sql .= " t.fk_user,";
		$sql .= " t.fk_user_create,";
		$sql .= " t.type,";
		$sql .= " t.date_create,";
		$sql .= " t.start_date,";
		$sql .= " t.end_date,";
		$sql .= " t.salarie_sig_date,";
		$sql .= " t.direction_sig_date,";
		$sql .= " t.dpae_date,";
		$sql .= " t.medical_visit_date,";
		$sql .= " t.description";

		$sql .= ' FROM ' . MAIN_DB_PREFIX . $this->table_element. ' as t';

		$sql .= " WHERE t.fk_user = '".$user_id."' ". $order;
		$sql .= ' AND t.entity='.$conf->entity;
		
		// Manage filter
		if (!empty($filter)) {
			$sql .= $filter;
		}

		$this->lines = array();

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new SalariescontractsLine();

				$line->id = $obj->rowid;
				$line->fk_user = $obj->fk_user;
				$line->fk_user_create = $obj->fk_user_create;
				$line->type = $obj->type;
				$line->date_create = $this->db->jdate($obj->date_create);
				$line->start_date = $this->db->jdate($obj->start_date);
				$line->end_date = $this->db->jdate($obj->end_date);
				$line->salarie_sig_date = $this->db->jdate($obj->salarie_sig_date);
				$line->direction_sig_date = $this->db->jdate($obj->direction_sig_date);
				$line->dpae_date = $this->db->jdate($obj->dpae_date);
				$line->medical_visit_date = $this->db->jdate($obj->medical_visit_date);
				$line->description = $obj->description;
				$line->entity = $obj->entity;

				$this->lines[] = $line;
				
				$this->ref 	= $obj->rowid;
				$this->id 	= $obj->rowid;
				$this->lines_sc[$i]['rowid'] = $obj->rowid;
				$this->lines_sc[$i]['fk_user'] = $obj->fk_user;
				$this->lines_sc[$i]['fk_user_create'] = $obj->fk_user_create;
				$this->lines_sc[$i]['type'] = $obj->type;
				$this->lines_sc[$i]['date_create'] = $obj->date_create;
				$this->lines_sc[$i]['start_date'] = $obj->start_date;
				$this->lines_sc[$i]['end_date'] = $obj->end_date;
				$this->lines_sc[$i]['salarie_sig_date'] = $obj->salarie_sig_date;
				$this->lines_sc[$i]['direction_sig_date'] = $obj->direction_sig_date;
				$this->lines_sc[$i]['dpae_date'] = $obj->dpae_date;
				$this->lines_sc[$i]['medical_visit_date'] = $obj->medical_visit_date;
				$this->lines_sc[$i]['description'] = $obj->description;
				$this->lines_sc[$i]['entity'] = $obj->entity;

				$i++;
			}
			$this->db->free($resql);

			return $num;
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return - 1;
		}
	}

	/**
     *	Return clicable name (with picto eventually)
     *
     *  @author Yassine Belkaid <y.belkaid@nextconcept.ma>
     *	@param		int			$withpicto		0=_No picto, 1=Includes the picto in the linkn, 2=Picto only
     *	@return		string						String with URL
     */
    public function getNomUrl($withpicto=0,  $id = null, $ref = null)
    {
        global $langs;

        $result = '';
        $ref = ($ref ?: '');
        $id  = ($id  ?: '');
        $label  = $langs->trans("Show").': '. $ref;

        $link = '<a href="'.dol_buildpath('/grh/salariescontracts/card.php?id='. $id,1) .'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        $linkend='</a>';

        $picto='salariescontracts@salariescontracts';

        if ($withpicto) $result.=($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$link.$ref.$linkend;
        return $result;
    }

    /**
     * Type of contracts
     *
     * @author Yassine Belkaid <y.belkaid@nextconcept.ma>
     * @return array
     */
    public function getContractsTypes()
    {
    	global $langs;
    	return array(
    		1 => $langs->trans('IndefiniteDurationContract'),
    		2 => $langs->trans('FixedTermContract'),
    		3 => $langs->trans('LearningContract'),
    		4 => $langs->trans('TrainingContract'),
    		5 => $langs->trans('ANAPEC'),
    		6 => $langs->trans('ProfessionalContract')
    	);
    }

    /**
     * Type of contract assigned to selected salary
     *
     * @author Yassine Belkaid <y.belkaid@nextconcept.ma>
     * @return string
     */
    public function getContractTypeById($id)
    {
    	global $langs;

    	$typeName = '';

    	if (!$id) return $typeName;

    	switch ((int) $id) {
    		case 1:
    			$typeName = $langs->trans('IndefiniteDurationContract');
    			break;
    		case 2:
    			$typeName = $langs->trans('FixedTermContract');
    			break;
    		case 3:
    			$typeName = $langs->trans('LearningContract');
    			break;
    		case 4:
    			$typeName = $langs->trans('TrainingContract');
    			break;
    		case 5:
    			$typeName = $langs->trans('ANAPEC');
    			break;
    		case 6:
    			$typeName = $langs->trans('ProfessionalContract');
    			break;
    		default:
    			$typeName;
    			break;
    	}

    	return $typeName;
    }
}

/**
 * Class SalariescontractsLine
 */
class SalariescontractsLine
{
	/**
	 * @var int ID
	 */
	public $id;
	/**
	 * @var mixed Sample line property 1
	 */
	
	public $fk_user;
	public $fk_user_create;
	public $type;
	public $date_create = '';
	public $start_date = '';
	public $end_date = '';
	public $salarie_sig_date = '';
	public $direction_sig_date = '';
	public $dpae_date = '';
	public $medical_visit_date = '';
	public $description;

	/**
	 * @var mixed Sample line property 2
	 */
	
}
