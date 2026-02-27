<?php
/* Copyright (C) 2003       Rodolphe Quiedeville 	<rodolphe@quiedeville.org>
 * Copyright (c) 2005-2013  Laurent Destailleur  	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009  Regis Houssin        	<regis.houssin@inodbox.com>
 * Copyright (C) 2011       Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2024		MDW						<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2025		Charlene Benke			<charlene@patas-monkey.com>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/expedition/class/expeditionstats.class.php
 *  \ingroup    expedition
 *  \brief      File of class to manage shipment statistics
 */

include_once DOL_DOCUMENT_ROOT.'/core/class/stats.class.php';
include_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';


/**
 *		Class to manage shipment statistics
 */
class ExpeditionStats extends Stats
{
	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element;

	/**
	 * @var int ID thirdparty
	 */
	public $socid;

	/**
	 * @var int ID user
	 */
	public $userid;

	/**
	 * @var string sql part from
	 */
	public $from;

	/**
	 * @var string sql part join
	 */
	public $join;

	/**
	 * @var string sql part fields
	 */
	public $field;

	/**
	 * @var string sql part where
	 */
	public $where;

	/**
	 * @var string Date column for SQL: either 'date_valid' (then used as c.date_valid) or 'ef.colname' for extrafield
	 */
	public $datefield;


	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db      	Database handler
	 * @param 	int		$socid	   	Id third party for filter
	 * @param 	string	$mode	   	Option (not used)
	 * @param   int		$userid    	Id user for filter (creation user)
	 * @param   string	$datefield  	Date column for stats: date_valid, date_creation, date_delivery, date_expedition, or ef_<name> for extrafield date
	 */
	public function __construct($db, $socid, $mode, $userid = 0, $datefield = 'date_valid')
	{
		global $user, $conf;

		$this->db = $db;

		$this->socid = ($socid > 0 ? $socid : 0);
		$this->userid = $userid;
		$this->cachefilesuffix = $mode;

		$object = new Expedition($this->db);
		$this->from = MAIN_DB_PREFIX.$object->table_element." as c";
		$this->join = '';
		$allowed = array('date_valid', 'date_creation', 'date_delivery', 'date_expedition');
		if (in_array($datefield, $allowed)) {
			$this->datefield = $datefield;
		} elseif (preg_match('/^ef_([a-z0-9_]+)$/i', $datefield, $m)) {
			$col = $this->db->escape($m[1]);
			$this->join = " LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element."_extrafields as ef ON (c.rowid = ef.fk_object)";
			$this->datefield = "ef.".$m[1]; // use unescaped for column identifier (no user input in name)
		} else {
			$this->datefield = 'date_valid';
		}

		//$this->from.= ", ".MAIN_DB_PREFIX."societe as s";
		$this->field = 'weight'; // Warning, unit of weight is NOT USED AND MUST BE
		$this->where .= " c.fk_statut > 0"; // Not draft and not cancelled

		//$this->where.= " AND c.fk_soc = s.rowid AND c.entity = ".$conf->entity;
		$this->where .= " AND c.entity = ".$conf->entity;

		if ($this->socid) {
			$this->where .= " AND c.fk_soc = ".((int) $this->socid);
		}
		if ($this->userid > 0) {
			$this->where .= ' AND c.fk_user_author = '.((int) $this->userid);
		}
	}

	/**
	 * Return the date column expression for SQL (c.date_valid or ef.colname)
	 *
	 * @return string
	 */
	protected function getDateCol()
	{
		return (strpos($this->datefield, '.') !== false) ? $this->datefield : 'c.'.$this->datefield;
	}

	/**
	 * Return shipment number by month for a year
	 *
	 * @param	int		$year		Year to scan
	 *	@param	int		$format		0=Label of abscissa is a translated text, 1=Label of abscissa is month number, 2=Label of abscissa is first letter of month
	 * @return	array<int<0,11>,array{0:int<1,12>,1:int}>	Array with number by month
	 */
	public function getNbByMonth($year, $format = 0)
	{
		global $user;

		$datecol = $this->getDateCol();
		$sql = "SELECT date_format(".$datecol.",'%m') as dm, COUNT(*) as nb";
		$sql .= " FROM ".$this->from;
		$sql .= $this->join;
		if (!$user->hasRight('societe', 'client', 'voir')) {
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON c.fk_soc = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
		}
		$sql .= " WHERE ".$datecol." IS NOT NULL AND ".$datecol." BETWEEN '".$this->db->idate(dol_get_first_day($year))."' AND '".$this->db->idate(dol_get_last_day($year))."'";
		$sql .= " AND ".$this->where;
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');

		$res = $this->_getNbByMonth($year, $sql, $format);
		return $res;
	}

	/**
	 * Return shipments number per year
	 *
	 * @return	array<array{0:int,1:int}>				Array of nb each year
	 *
	 */
	public function getNbByYear()
	{
		global $user;

		$datecol = $this->getDateCol();
		$sql = "SELECT date_format(".$datecol.",'%Y') as dm, COUNT(*) as nb, SUM(c.".$this->field.")";
		$sql .= " FROM ".$this->from;
		$sql .= $this->join;
		if (!$user->hasRight('societe', 'client', 'voir')) {
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON c.fk_soc = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
		}
		$sql .= " WHERE ".$datecol." IS NOT NULL AND ".$this->where;
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');

		return $this->_getNbByYear($sql);
	}

	/**
	 * Return the orders amount by month for a year
	 *
	 * @param	int		$year		Year to scan
	 * @param	int		$format		0=Label of abscissa is a translated text, 1=Label of abscissa is month number, 2=Label of abscissa is first letter of month
	 *  @return array<int<0,11>,array{0:int<1,12>,1:int|float}>	Array of values
	 */
	public function getAmountByMonth($year, $format = 0)
	{
		global $user;

		$datecol = $this->getDateCol();
		$sql = "SELECT date_format(".$datecol.",'%m') as dm, SUM(c.".$this->field.")";
		$sql .= " FROM ".$this->from;
		$sql .= $this->join;
		if (!$user->hasRight('societe', 'client', 'voir')) {
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON c.fk_soc = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
		}
		$sql .= " WHERE ".$datecol." IS NOT NULL AND ".$this->where;
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');

		$res = $this->_getAmountByMonth($year, $sql, $format);
		return $res;
	}

	/**
	 * Return the orders amount average by month for a year
	 *
	 * @param	int		$year	year for stats
	 * @return	array<int<0,11>,array{0:int<1,12>,1:int|float}> 	Array with number by month
	 */
	public function getAverageByMonth($year)
	{
		global $user;

		$datecol = $this->getDateCol();
		$sql = "SELECT date_format(".$datecol.",'%m') as dm, AVG(c.".$this->field.")";
		$sql .= " FROM ".$this->from;
		$sql .= $this->join;
		if (!$user->hasRight('societe', 'client', 'voir')) {
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON c.fk_soc = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
		}
		$sql .= " WHERE ".$datecol." IS NOT NULL AND ".$this->where;
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');

		return $this->_getAverageByMonth($year, $sql);
	}

	/**
	 *	Return nb, total and average
	 *
	 *  @return array<array{year:string,nb:string,nb_diff:float,total?:float,avg?:float,weighted?:float,total_diff?:float,avg_diff?:float,avg_weighted?:float}>    Array of values
	 */
	public function getAllByYear()
	{
		global $user;

		$datecol = $this->getDateCol();
		$sql = "SELECT date_format(".$datecol.",'%Y') as year, COUNT(*) as nb, SUM(c.".$this->field.") as total, AVG(c.".$this->field.") as avg";
		$sql .= " FROM ".$this->from;
		$sql .= $this->join;
		if (!$user->hasRight('societe', 'client', 'voir')) {
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON c.fk_soc = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
		}
		$sql .= " WHERE ".$datecol." IS NOT NULL AND ".$this->where;
		$sql .= " GROUP BY year";
		$sql .= $this->db->order('year', 'DESC');

		return $this->_getAllByYear($sql);
	}
}
