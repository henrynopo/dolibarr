<?php

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class NX_db extends CommonObject {
	public $defaultPicto = 'grh@grh';

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct($db)
	{
		$this->db 		 = $db;
		return 1;
	}

	/**
	 * Get last inserted id
	 *
	 * @return integer
	 */
	public function getLasInsrtedId($alt_table = '')
	{
		$table = (isset($alt_table) && !empty($alt_table) ? $alt_table : $this->table_element);
		$resql = $this->db->query('SELECT rowid FROM '. MAIN_DB_PREFIX . $table .' order by rowid DESC limit 1');
		return $this->db->fetch_object($resql)->rowid;
	}
}