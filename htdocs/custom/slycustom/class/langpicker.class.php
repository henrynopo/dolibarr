<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * Language picker data (from custom/langpicker). Table llx_lang_picker.
 */

require_once DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php";

/**
 * Class SlyLangPicker - list of languages shown in top menu dropdown (SLY Custom, avoids conflict with custom/langpicker)
 */
class SlyLangPicker extends CommonObject
{
	/** @var string Table name without prefix */
	public $table_element = 'lang_picker';
	/** @var array Fields to fetch */
	public $fetch_fields = array('rowid', 'lang_code', 'position');
	/** @var string Primary key */
	public $pk_name = 'rowid';
	/** @var array Fetched lines */
	public $lines = array();
	/** @var int Total count */
	public $total = 0;
	/** @var int Fetched count */
	public $count = 0;
	/** @var array Languages by rowid */
	public $languages = array();

	public function __construct($db = null)
	{
		$this->db = $db;
		if (!$this->db) {
			global $db;
			$this->db = $db;
		}
	}

	/**
	 * Create one row
	 *
	 * @param array $data ['lang_code'=>..., 'position'=>...]
	 * @return int <0 KO, id OK
	 */
	public function create($data)
	{
		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= implode(", ", array_map(function ($k) {
			return "`".$k."`";
		}, array_keys($data)));
		$sql .= ") VALUES (";
		$vals = array();
		foreach ($data as $v) {
			$vals[] = is_null($v) ? 'NULL' : "'".$this->db->escape($v)."'";
		}
		$sql .= implode(", ", $vals).")";
		$this->db->begin();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->db->rollback();
			return -1;
		}
		$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element, $this->pk_name);
		$this->db->commit();
		return $this->id;
	}

	/**
	 * Load all rows into $this->lines
	 *
	 * @param int $limit
	 * @param int $offset
	 * @param string $sort_field
	 * @param string $sort_order
	 * @return int <0 KO, 0 none, >0 OK
	 */
	public function fetchAll($limit = 0, $offset = 0, $sort_field = '', $sort_order = 'ASC', $more_fields = '', $join = '', $where = '', $get_total = false, $table_alias = 't')
	{
		$this->lines = array();
		$sql = "SELECT ".($table_alias ? $table_alias.'.' : '')."`".implode("`,`", $this->fetch_fields)."`";
		if (!empty($more_fields)) {
			$sql .= (strpos($more_fields, ',') === 0 ? '' : ', ').$more_fields;
		}
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element.($table_alias ? " AS ".$table_alias : "");
		if (!empty($join)) {
			$sql .= " ".$join;
		}
		if (!empty($where)) {
			$sql .= " WHERE ".$where;
		}
		if (!empty($sort_field)) {
			$sql .= $this->db->order($sort_field, $sort_order === 'DESC' ? 'DESC' : 'ASC');
		}
		if ($limit > 0) {
			$sql .= " ".$this->db->plimit($limit, $offset);
		}
		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}
		$num = $this->db->num_rows($resql);
		$this->count = $num;
		for ($i = 0; $i < $num; $i++) {
			$obj = $this->db->fetch_object($resql);
			$line = new self($this->db);
			foreach ($this->fetch_fields as $f) {
				$line->$f = $obj->$f;
			}
			$line->id = (int) $obj->{$this->pk_name};
			$this->lines[] = $line;
		}
		$this->db->free($resql);
		return $num > 0 ? 1 : 0;
	}

	/**
	 * Delete rows by WHERE clause (without "WHERE")
	 *
	 * @param string $where
	 * @return int <0 KO, >0 OK
	 */
	public function deleteWhere($where)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE ".$where;
		if ($this->db->query($sql)) {
			return 1;
		}
		return -1;
	}

	/**
	 * Get next position value
	 *
	 * @return int
	 */
	public function getNextPosition()
	{
		$sql = "SELECT COALESCE(MAX(position), 0) + 1 AS np FROM ".MAIN_DB_PREFIX.$this->table_element;
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			$this->db->free($resql);
			return (int) $obj->np;
		}
		return 1;
	}

	protected function getLanguages()
	{
		$sql = "SELECT rowid, position FROM ".MAIN_DB_PREFIX.$this->table_element." ORDER BY position ASC";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}
		$this->languages = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$this->languages[(int) $obj->rowid] = $obj;
		}
		$this->db->free($resql);
		return 1;
	}

	protected function setPosition($from, $to, $id = 0, $not = 0)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET position = ".(int) $to." WHERE position = ".(int) $from;
		if ($id > 0) {
			$sql .= " AND rowid ".($not ? "!=" : "=")." ".(int) $id;
		}
		return $this->db->query($sql) ? 1 : -1;
	}

	/**
	 * Move one row up
	 *
	 * @param int $id rowid
	 * @return int
	 */
	public function up($id)
	{
		if (empty($this->languages)) {
			$this->getLanguages();
		}
		if (!isset($this->languages[$id])) {
			return 0;
		}
		$pos = (int) $this->languages[$id]->position;
		$newpos = $pos - 1;
		if ($newpos < 1) {
			return 0;
		}
		if ($this->setPosition($newpos, $pos) > 0) {
			return $this->setPosition($pos, $newpos, $id);
		}
		return 0;
	}

	/**
	 * Move one row down
	 *
	 * @param int $id rowid
	 * @return int
	 */
	public function down($id)
	{
		if (empty($this->languages)) {
			$this->getLanguages();
		}
		if (!isset($this->languages[$id])) {
			return 0;
		}
		$pos = (int) $this->languages[$id]->position;
		$newpos = $pos + 1;
		$max = 0;
		foreach ($this->languages as $o) {
			if ((int) $o->position > $max) {
				$max = (int) $o->position;
			}
		}
		if ($newpos > $max) {
			return 0;
		}
		if ($this->setPosition($newpos, $pos) > 0) {
			return $this->setPosition($pos, $newpos, $id);
		}
		return 0;
	}
}
