<?php

require_once DOL_DOCUMENT_ROOT."/expedition/class/ShipsGo_API.class.php";
require_once DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php"; 

/**
 *		Class to update ShipsGo Shipment Status
 */
class ShipmentStatus
{

	/**
	 *	Constructor
	 *
	 *  @param	DoliDB	$db		Database handler
	 */
	public $db;
	public $output; // Used by Cron method to return message
	public $result; // Used by Cron method to return data

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 *  Purge files into directory of data files.
	 *  CAN BE A CRON TASK
	 *
	 *  @return	int						   0 if OK, < 0 if KO (this function is used also by cron so only 0 is OK)
	 *  @nbtoupdate							number of rows to update for each run
	 */
	public function updateships($nbtoupdate = 20) 
	{
		global $conf;

		@ini_set('max_execution_time', '300');	//extend PHP maximum execution time to 300s (5min)
		$time = dol_now();

		$sql = 'SELECT a.rowid, a.tracking_number FROM '.MAIN_DB_PREFIX.'expedition AS a';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'expedition_extrafields AS b';
		$sql .= ' ON b.fk_object = a.rowid';
		$sql .= ' WHERE b.updatedtime IS NOT NULL';
		$sql .= ' AND ((b.SailingStatusID <> 3 AND b.SailingStatusID <> 4) OR b.SailingStatusID IS NULL)';
		$sql .= ' AND ('.$time.' - unix_timestamp(b.updatedtime) > 86300)';

		$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);
			$num = $num > $nbtoupdate ? $nbtoupdate : $num;
			$i = 0; 
			$count = 0;

			while($i < $num) {
				$line = $this->db->fetch_object($resql);
				$this->db->begin();

				$shipsGotmp = new ShipsGo_API($conf->global->API_KEY_SHIPSGO);
				
				$ship_status = $shipsGotmp->GetContainerInfo($line->tracking_number);
				if (!empty($ship_status['Message'])) {
					$updatesql = "UPDATE ".MAIN_DB_PREFIX."expedition_extrafields SET";
					$updatesql .= " sailingstatusid = 0";
					$updatesql .= " WHERE fk_object = ".$line->rowid;
					if ($this->db->query($updatesql)) {
						$this->db->commit();
						$count++;
					} else {
						$this->db->rollback();
					}
				} elseif ($ship_status[0]['Message'] == 'Success') {
					$updatesql = "UPDATE ".MAIN_DB_PREFIX."expedition_extrafields SET";
					$updatesql .= " sailingstatusid = ".$ship_status[0]['SailingStatusId'];
					$updatesql .= ", pol = '".$ship_status[0]['Pol']."'";
					if (!empty($ship_status[0]['DepartureDate'])) {
						$updatesql .= ", atd = '".date('Y-m-d', strtotime(str_replace('/', '-', $ship_status[0]['DepartureDate'])))."'";
					}
					$updatesql .= ", pod = '".$ship_status[0]['Pod']."'";
					if (!empty($ship_status[0]['ArrivalDate'])) {
						$updatesql .= ", ata = '".date('Y-m-d', strtotime(str_replace('/', '-', $ship_status[0]['ArrivalDate'])))."'";						
					}
					$updatesql .= ", livemapurl = '".$ship_status[0]['LiveMapUrl']."'";
					$updatesql .= ", updatedtime = '".date('Y-m-d H:i:s', dol_now())."'";
					$updatesql .= " WHERE fk_object = ".$line->rowid;
					if ($this->db->query($updatesql)) {
						$this->db->commit();
						$count++;
					} else {
						$this->db->rollback();
					}
				}
				$i++;
			}
		}
		$this->output = "Updated ".$count." shipments.";
		return 0; // This function can be called by cron so must return 0 if OK
	}
}

?>