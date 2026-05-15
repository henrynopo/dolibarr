<?php

require_once __DIR__.'/ShipsGo_API.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class to update ShipsGo shipment status (cron + shared multicompany helpers).
 */
class ShipmentStatus
{
	/** @var DoliDB */
	public $db;
	/** @var string Used by cron to return message */
	public $output;
	/** @var int|string Used by cron to return data */
	public $result;

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * API key stored in llx_const for a given entity (Multicompany: one key per company).
	 *
	 * @param DoliDB $db       Database handler
	 * @param int    $entityId Entity id
	 * @return string Non-empty key or ''
	 */
	public static function getApiKeyForEntity($db, $entityId)
	{
		if (!function_exists('dolibarr_get_const')) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		}
		$v = dolibarr_get_const($db, 'API_KEY_SHIPSGO', (int) $entityId);
		if (!is_string($v)) {
			return '';
		}
		$v = trim($v);
		return $v;
	}

	/**
	 * Resolve API key for an expedition: object's entity first, then current $conf->entity.
	 *
	 * @param DoliDB       $db
	 * @param CommonObject $expedition Expedition object (must be loaded)
	 * @param Conf         $conf
	 * @return string
	 */
	public static function getApiKeyForExpedition($db, $expedition, $conf)
	{
		$e = (is_object($expedition) && isset($expedition->entity)) ? (int) $expedition->entity : (int) $conf->entity;
		$key = self::getApiKeyForEntity($db, $e);
		if ($key !== '') {
			return $key;
		}
		if ($e !== (int) $conf->entity) {
			return self::getApiKeyForEntity($db, (int) $conf->entity);
		}
		return '';
	}

	/**
	 * Distinct entity ids that have a non-empty API_KEY_SHIPSGO in database.
	 *
	 * @param DoliDB $db Database handler
	 * @return int[]
	 */
	public static function getEntityIdsWithShipsGoConfigured($db)
	{
		$ids = array();
		$sql = "SELECT DISTINCT entity FROM ".MAIN_DB_PREFIX."const";
		$sql .= " WHERE name = 'API_KEY_SHIPSGO'";
		$sql .= " AND value IS NOT NULL AND value <> ''";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$ids[] = (int) $obj->entity;
			}
			$db->free($resql);
		}
		return $ids;
	}

	/**
	 * Whether the active company context has a ShipsGo key (buttons / mass actions).
	 *
	 * @param DoliDB $db
	 * @param Conf   $conf
	 * @return bool
	 */
	public static function hasApiKeyInCurrentContext($db, $conf)
	{
		return self::getApiKeyForEntity($db, (int) $conf->entity) !== '';
	}

	/**
	 * Apply GetContainerInfo response to expedition_extrafields row.
	 *
	 * @param int   $fkExpedition Expedition row id
	 * @param array $ship_status  Normalized status array (Success branch)
	 * @return bool True if UPDATE OK
	 */
	protected function applyShipsGoStatusToExtrafields($fkExpedition, array $ship_status)
	{
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
		$etaDate = $ship_status['Eta'] ?? $ship_status['EstimatedArrivalDate'] ?? null;
		if (!empty($etaDate)) {
			$updatesql .= ", eta = '".$this->db->escape(date('Y-m-d', strtotime(str_replace('/', '-', $etaDate))))."'";
		}
		$updatesql .= ", livemapurl = '".$this->db->escape($ship_status['LiveMapUrl'] ?? '')."'";
		$updatesql .= ", updatedtime = '".$this->db->idate(dol_now())."'";
		$updatesql .= " WHERE fk_object = ".((int) $fkExpedition);

		$this->db->begin();
		$result = $this->db->query($updatesql);
		if ($result) {
			$this->db->commit();
			return true;
		}
		$this->db->rollback();
		return false;
	}

	/**
	 * Cron task: refresh ShipsGo data per entity using that entity's API key (Multicompany-safe).
	 * Budget $nbtoupdate is shared across all entities in one run.
	 *
	 * @param int $nbtoupdate Max shipments to attempt in this run (default 20)
	 * @return int 0 (OK for cron)
	 */
	public function updateships($nbtoupdate = 20)
	{
		$remaining = max(1, (int) $nbtoupdate);
		$entityIds = self::getEntityIdsWithShipsGoConfigured($this->db);
		if (empty($entityIds)) {
			$this->output = 'ShipsGo API key is not configured for any entity (API_KEY_SHIPSGO).';
			$this->result = 0;
			if (function_exists('dol_syslog')) {
				dol_syslog(__METHOD__.': no API_KEY_SHIPSGO in llx_const for any entity', LOG_WARNING);
			}
			return 0;
		}
		sort($entityIds, SORT_NUMERIC);

		$count = 0;
		$error = 0;
		$perEntity = array();

		$time = dol_now();
		$cutoff = $this->db->idate($time - 86400);

		foreach ($entityIds as $entityId) {
			if ($remaining <= 0) {
				break;
			}
			$apiKey = self::getApiKeyForEntity($this->db, $entityId);
			if ($apiKey === '') {
				continue;
			}

			$sql = 'SELECT a.rowid, a.tracking_number FROM '.MAIN_DB_PREFIX.'expedition_extrafields AS b';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'expedition AS a ON b.fk_object = a.rowid';
			$sql .= " WHERE a.entity = ".((int) $entityId);
			$sql .= " AND (";
			$sql .= " (b.updatedtime IS NOT NULL AND b.updatedtime <= '".$this->db->escape($cutoff)."' AND (b.sailingstatusid IS NULL OR b.sailingstatusid NOT IN (3,4)))";
			$sql .= " OR (b.sailingstatusid IS NULL AND a.fk_statut > 0)";
			$sql .= " )";
			$sql .= ' ORDER BY b.ata, b.updatedtime ASC';
			$sql .= $this->db->plimit($remaining, 0);

			$resql = $this->db->query($sql);
			if (!$resql) {
				continue;
			}

			$shipsGotmp = new ShipsGo_API($apiKey);
			$doneThisEntity = 0;

			while ($remaining > 0) {
				$line = $this->db->fetch_object($resql);
				if (empty($line) || empty($line->tracking_number)) {
					if (empty($line)) {
						break;
					}
					$error++;
					$remaining--;
					continue;
				}
				$ship_status_list = $shipsGotmp->GetContainerInfo($line->tracking_number);
				if (!empty($ship_status_list['Message'])) {
					$error++;
					$remaining--;
					continue;
				}
				$ship_status = is_array($ship_status_list) && isset($ship_status_list[0]) ? $ship_status_list[0] : (is_array($ship_status_list) ? $ship_status_list : array());
				if (!empty($ship_status['Message']) && $ship_status['Message'] == 'Success') {
					if ($this->applyShipsGoStatusToExtrafields((int) $line->rowid, $ship_status)) {
						$count++;
						$doneThisEntity++;
					} else {
						$error++;
					}
				} else {
					$error++;
				}
				$remaining--;
			}
			$this->db->free($resql);

			if ($doneThisEntity > 0) {
				$perEntity[] = 'e'.(int) $entityId.':'.$doneThisEntity;
			}
		}

		$detail = count($perEntity) ? ' ('.implode(', ', $perEntity).')' : '';
		$this->output = 'Updated: '.$count.' shipments'.$detail.'. Errors: '.$error.'.';
		return 0;
	}
}