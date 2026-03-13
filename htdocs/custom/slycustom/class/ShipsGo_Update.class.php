<?php

require_once __DIR__.'/ShipsGo_API.class.php';
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

		$time = dol_now();
		$cutoff = $this->db->idate($time - 86400);

		$sql = 'SELECT a.rowid, a.tracking_number FROM '.MAIN_DB_PREFIX.'expedition_extrafields AS b';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'expedition AS a';
		$sql .= ' ON b.fk_object = a.rowid';
		$sql .= " WHERE (";
		$sql .= " (b.updatedtime IS NOT NULL AND b.updatedtime <= '".$this->db->escape($cutoff)."' AND (b.sailingstatusid IS NULL OR b.sailingstatusid NOT IN (3,4)))";
		$sql .= " OR (b.sailingstatusid IS NULL AND a.fk_statut > 0)";
		$sql .= " )";
		$sql .= ' ORDER BY b.ata, b.updatedtime ASC';

		$resql = $this->db->query($sql);
		$count = 0;
		$error = 0;
		
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$nbtoupdate = $num < $nbtoupdate ? $num : $nbtoupdate;
			$i = 0;

			$shipsGotmp = new ShipsGo_API($conf->global->API_KEY_SHIPSGO);

			while($i++ < $nbtoupdate) {	
				$line = $this->db->fetch_object($resql);
				if (empty($line) || empty($line->tracking_number)) {
					$error++;
					continue;
				}
				$ship_status_list = $shipsGotmp->GetContainerInfo($line->tracking_number);
				if (!empty($ship_status_list['Message'])) {
					$error++;
					continue;
				}
				$ship_status = is_array($ship_status_list) && isset($ship_status_list[0]) ? $ship_status_list[0] : (is_array($ship_status_list) ? $ship_status_list : array());
				if (!empty($ship_status['Message']) && $ship_status['Message'] == 'Success') {
					$updatesql = "UPDATE ".MAIN_DB_PREFIX."expedition_extrafields SET";
					$updatesql .= " sailingstatusid = ".(int) ($ship_status['SailingStatusId'] ?? 0);
					$updatesql .= ", pol = '".$this->db->escape($ship_status['Pol'] ?? '')."'";
					if (!empty($ship_status['DepartureDate'])) {
						$updatesql .= ", atd = '".$this->db->escape(date('Y-m-d', strtotime(str_replace('/', '-', $ship_status['DepartureDate']))))."'";
					}
					$updatesql .= ", pod = '".$this->db->escape($ship_status['Pod'] ?? '')."'";
					if (!empty($ship_status['ArrivalDate'])) {
						$updatesql .= ", ata = '".$this->db->escape(date('Y-m-d', strtotime(str_replace('/', '-', $ship_status['ArrivalDate']))))."'";						
					}
					$updatesql .= ", livemapurl = '".$this->db->escape($ship_status['LiveMapUrl'] ?? '')."'";
					$updatesql .= ", updatedtime = '".$this->db->idate(dol_now())."'";
					$updatesql .= " WHERE fk_object = ".((int) $line->rowid);

					$this->db->begin();
					$result = $this->db->query($updatesql);
					if ($result) {
						$this->db->commit();
						$count++;
					} else {
						$this->db->rollback();
						$error++;
					}
				} else {
					$error++;
					continue;
				}
			}
		}
		$this->output = "Updated: ".$count." shipments. Errors: ".$error.".";
		return 0; // This function can be called by cron so must return 0 if OK
	}
}

?>