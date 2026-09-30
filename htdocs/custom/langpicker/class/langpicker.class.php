<?php

// Load Dolibarr CommonObject class
require_once DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php";

class LangPicker extends CommonObject
{
	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'lang_picker';
	/**
	 * @var array Fetch fields
	 */
	public $fetch_fields = array(); // e.: array('field_1', 'field_2', 'field_3')
	/**
	 * @var string Primary key name (id field)
	 */
	public $pk_name = 'rowid';
	/**
	 * @var array Object lines (used in fetchAll function)
	 */
	public $lines = array();
	/**
	 * @var int Total number of records (used in fetchAll function)
	 */
	public $total = 0;
	/**
	 * @var int Total number of fetched records (used in fetchAll function)
	 */
	public $count = 0;
	/**
	 * @var array Languages array (using rowid as key)
	 */
	public $languages = array();

	/**
	 * Constructor
	 * 
	 */
	public function __construct()
	{
		global $db;

		$this->db = $db;
		$this->fetch_fields = array('rowid', 'lang_code', 'position');
	}

	/**
	 * Create object into database
	 *
	 * @param  array  $data array, e.: array('my_field_name' => 'my_field_value', 'second_field_name' => 'second_field_value')
	 * @return int    <0 if KO, Id of created object if OK
	 */
	public function create($data)
	{
		$error = 0;

		// INSERT request
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . $this->table_element . "(";
		foreach ($data as $key => $value) {
			$sql.= "`" . $key . "`,";
		}
		$sql = substr($sql, 0, -1); // Remove the last ','

		$sql.= ") VALUES (";
		foreach ($data as $key => $value) {
			$sql.= $this->escape($value) . ",";
		}
		$sql = substr($sql, 0, -1); // Remove the last ','

		$sql.= ")";

		$this->db->begin();

		dol_syslog(__METHOD__ . " sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (! $resql) {
			$error ++;
			$this->errors[] = "Error " . $this->db->lasterror();
		}

		if (! $error) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . $this->table_element, $this->pk_name);
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(__METHOD__ . " " . $errmsg, LOG_ERR);
				$this->error.=($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();
			setEventMessage($this->error, 'errors');

			return -1 * $error;
		} else {
			$this->db->commit();

			return $this->id;
		}
	}

	/**
	 * Load all object entries in memory from database
	 *
	 * @param  int     $limit        fetch limit
	 * @param  int     $offset       fetch offset
	 * @param  string  $sort_field   field to sort by
	 * @param  string  $sort_order   sort order: 'DESC' or 'ASC'
	 * @param  string  $more_fields  more fields to fetch
	 * @param  string  $join         join clause
	 * @param  string  $where        where clause (without 'WHERE')
	 * @param  boolean $get_total    get total number of records or not
	 * @param  boolean $table_alias  Alias to use for table name, leave it empty if you won't
	 * @return int                   <0 if KO, >0 if OK
	 */
	public function fetchAll($limit = 0, $offset = 0, $sort_field = '', $sort_order = 'DESC', $more_fields = '', $join = '', $where = '', $get_total = false, $table_alias = 't')
	{
		// Init lines
		$this->lines = array();

		if (empty($this->fetch_fields)) {
			return 0;
		}

		// SELECT request
		$sql = "SELECT ";
		foreach ($this->fetch_fields as $field) {
			$sql.= (! empty($table_alias) ? $table_alias.'.' : '')."`" . $field . "`,";
		}
		$sql = substr($sql, 0, -1); // Remove the last ','
		if (! empty($more_fields)) {
			$sql.= $more_fields[0] == ',' ? $more_fields : ', ' . $more_fields;
		}
		$sql.= " FROM " . MAIN_DB_PREFIX . $this->table_element;
		if (! empty($table_alias)) $sql.= " as ".$table_alias;
		if (! empty($join)) $sql.= $join;
		if (! empty($where)) $sql.= " WHERE ".$where;
		if (! empty($sort_field)) $sql.= $this->db->order($sort_field, $sort_order);
		if ($get_total) {
			global $conf;
			$this->total = 0;
			if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
			{
				$result = $this->db->query($sql);
				if ($result) {
					$this->total = $this->db->num_rows($result);
				}
			}
		}
		if ($limit > 0) {
			if ($get_total) {
				$sql.= $this->db->plimit($limit+1, $offset); // for list pagination
			}
			else {
				$sql.= $this->db->plimit($limit, $offset);
			}
		}

		dol_syslog(__METHOD__ . " sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$this->count = $this->db->num_rows($resql);
			if ($this->count)
			{
				$i = 0;
				$num = ($get_total ? min($this->count, $limit) : $this->count); // also for list pagination
				$set_id = (! in_array('id', $this->fetch_fields) ? true : false);

				while ($i < $num)
				{
					$obj = $this->db->fetch_object($resql);

					$classname = get_class($this);

					$this->lines[$i] = new $classname();

					foreach ($this->fetch_fields as $field) {
						$this->lines[$i]->$field = $obj->$field;
					}

					if (! empty($more_fields)) 
					{
						$fields = explode(',', $more_fields);

						foreach ($fields as $field) 
						{
							$field = trim($field);

							if (! empty($field))
							{
								// check for ' as ' alias
								$pos = stripos($field, ' as ');
								if ($pos !== false) {
									$field = substr($field, $pos + 4); // 4 == strlen(' as ')
								}
								// add field
								$this->lines[$i]->$field = $obj->$field;
							}
						}
					}

					// enssure that $this->id is filled because we use it in update/delete/getNomUrl functions
					if ($set_id) {
						$this->lines[$i]->id = $obj->{$this->pk_name};
					}

					$i++;
				}

				$this->db->free($resql);

				return 1;
			}
			$this->db->free($resql);

			return 0;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(__METHOD__ . " " . $this->error, LOG_ERR);
			setEventMessage($this->error, 'errors');

			return -1;
		}
	}

	/**
	 * Delete row(s) in database
	 *
	 * @param  string  $where     where clause (without 'WHERE')
	 * @return int                <0 if KO, >0 if OK
	 */
	public function deleteWhere($where)
	{
		$error = 0;

		$this->db->begin();

		// DELETE request
		$sql = "DELETE FROM " . MAIN_DB_PREFIX . $this->table_element;
		$sql.= " WHERE ".$where;

		dol_syslog(__METHOD__ . " sql=" . $sql);
		$resql = $this->db->query($sql);
		if (! $resql) {
			$error ++;
			$this->errors[] = "Error " . $this->db->lasterror();
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(__METHOD__ . " " . $errmsg, LOG_ERR);
				$this->error.=($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();
			setEventMessage($this->error, 'errors');

			return -1 * $error;
		} else {
			$this->db->commit();

			return 1;
		}
	}

	/**
	 * Escape field value
	 *
	 * @param     $value     field value
	 */
	protected function escape($value)
	{
		return is_null($value) ? 'null' : "'".$value."'";
	}

	/**
	 * Return Next language position
	 *
	 * @return int next position
	 */
	public function getNextPosition()
	{
		$next_position = 1;

		$sql = "SELECT max(position) as max FROM " . MAIN_DB_PREFIX . $this->table_element;

		dol_syslog(__METHOD__ . " sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);
				$next_position = $obj->max + 1;
			}
			$this->db->free($resql);
		} else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(__METHOD__ . " " . $this->error, LOG_ERR);
		}

		return $next_position;
	}

	/**
	 * Load object in memory from database
	 *
	 * @return int <0 if KO, >0 if OK
	 */
	protected function getLanguages()
	{
		$sql = "SELECT rowid, position FROM " . MAIN_DB_PREFIX . $this->table_element;
		$sql.= " ORDER BY position ASC";

		dol_syslog(__METHOD__ . " sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$i = 0;
			$num = $this->db->num_rows($resql);
			$this->languages = array();

			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);

				$this->languages[$obj->rowid] = $obj;

				$i++;
			}
			$this->db->free($resql);

			return 1;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(__METHOD__ . " " . $this->error, LOG_ERR);

			return -1;
		}
	}

	/**
	 * Set language position
	 *
	 * @param     $from     position from
	 * @param     $to       position to
	 * @param     $id       row id
	 * @param     $not      use '!=' instead of '=' to check row id
	 * @return int <0 if KO, >0 if OK
	 */
	protected function setPosition($from, $to, $id=0, $not=0)
	{
		$error = 0;

		// Update request
		$sql = "UPDATE " . MAIN_DB_PREFIX . $this->table_element . " SET";
		$sql.= " position = " . $to;
		$sql.= " WHERE position = " . $from;
		if ($id > 0) $sql.= " AND rowid" . ($not ? " != " : " = ") . $id;

		$this->db->begin();

		dol_syslog(__METHOD__ . " sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (! $resql) {
			$error ++;
			$this->errors[] = "Error " . $this->db->lasterror();
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(__METHOD__ . " " . $errmsg, LOG_ERR);
				$this->error.=($this->error ? ', ' . $errmsg : $errmsg);
			}

			$this->db->rollback();

			return -1 * $error;
		} else {
			$this->db->commit();

			return 1;
		}
	}

	/**
	 * Change language position
	 *
	 * @param     $id     row id
	 * @return int <0 if KO, >0 if OK
	 */
	public function up($id)
	{
		if (count($this->languages) == 0) {
			$this->getLanguages();
		}

		$pos = $this->languages[$id]->position;
		$newpos = $pos - 1;

		if ($this->setPosition($newpos, $pos)) { // swap the language on new position to our position
			return $this->setPosition($pos, $newpos, $id); // move to the new position! (only selected language)
		}

		return 0;
	}

	/**
	 * Change language position
	 *
	 * @param     $id     row id
	 * @return int <0 if KO, >0 if OK
	 */
	public function down($id)
	{
		if (count($this->languages) == 0) {
			$this->getLanguages();
		}

		$pos = $this->languages[$id]->position;
		$newpos = $pos + 1;

		if ($this->setPosition($newpos, $pos)) { // swap language on new position to our position
			return $this->setPosition($pos, $newpos, $id); // move to the new position! (only selected language)
		}

		return 0;
	}
}
