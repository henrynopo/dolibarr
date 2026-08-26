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
	 * HMAC shared secret used to verify ShipsGo webhook signatures.
	 *
	 * @param DoliDB $db       Database handler
	 * @param int    $entityId Entity id
	 * @return string Non-empty secret or ''
	 */
	public static function getSecretKeyForEntity($db, $entityId)
	{
		if (!function_exists('dolibarr_get_const')) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		}
		$v = dolibarr_get_const($db, 'SHIPSGO_WEBHOOK_SECRET', (int) $entityId);
		if (!is_string($v)) {
			return '';
		}
		return trim($v);
	}

	/**
	 * Public webhook URL for a given entity.
	 *
	 * Builds a full URL with scheme + host. Falls back to detecting from
	 * $_SERVER if DOL_URL_ROOT is empty (rare production misconfig).
	 *
	 * @param int $entityId Entity id
	 * @return string
	 */
	public static function getWebhookUrl($entityId)
	{
		$base = '';
		if (defined('DOL_MAIN_URL_ROOT') && DOL_MAIN_URL_ROOT !== '') {
			$base = DOL_MAIN_URL_ROOT;
		} elseif (defined('DOL_URL_ROOT') && DOL_URL_ROOT !== '') {
			$base = DOL_URL_ROOT;
		}
		if ($base === '') {
			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
			$host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
			$base = $host !== '' ? ($protocol.'://'.$host) : '';
		}
		// Trim trailing slash so the concatenation is clean.
		$base = rtrim($base, '/');
		return $base.'/custom/slycustom/webhook/shipsgo.php?entity='.(int) $entityId;
	}

	/**
	 * Map ShipsGo v2 status string to v1-style numeric ID (kept consistent with ShipsGo_API::mapStatusToId).
	 *
	 * @param string $status V2 status enum value
	 * @return int
	 */
	public static function mapStatusToId($status)
	{
		$map = array(
			'NEW' => 0,
			'INPROGRESS' => 1,
			'BOOKED' => 2,
			'LOADED' => 3,
			'SAILING' => 4,
			'ARRIVED' => 5,
			'DISCHARGED' => 6,
			'UNTRACKED' => 99,
		);
		return isset($map[$status]) ? $map[$status] : 99;
	}

	/**
	 * Convert a raw webhook payload (single shipment) to the normalized array
	 * consumed by applyShipsGoStatusToExtrafields().
	 *
	 * Field mapping is the single point to adjust when ShipsGo's webhook schema
	 * differs from the v2 GET response.
	 *
	 * @param array $ship Shipment object from webhook payload
	 * @return array Normalized ship_status (Success/SailingStatusId/Pol/Pod/Etd/Atd/Eta/Ata/MapUrl)
	 */
	public function normalizeWebhookPayload(array $ship)
	{
		$status = isset($ship['status']) ? (string) $ship['status'] : 'UNKNOWN';
		$route = isset($ship['route']) && is_array($ship['route']) ? $ship['route'] : array();

		// Real deliveries carry location as the UN/LOCODE string ("BRSSZ"); tolerate an
		// object {name, code} too. Dereferencing ['name'] on a string location fails
		// instead of returning the code.
		$polLoc = isset($route['port_of_loading']['location']) ? $route['port_of_loading']['location'] : '';
		$pol = is_string($polLoc) ? $polLoc : (($polLoc['name'] ?? '') ?: ($polLoc['code'] ?? ''));
		$podLoc = isset($route['port_of_discharge']['location']) ? $route['port_of_discharge']['location'] : '';
		$pod = is_string($podLoc) ? $podLoc : (($podLoc['name'] ?? '') ?: ($podLoc['code'] ?? ''));

		$shipId = isset($ship['id']) ? $ship['id'] : null;
		$mapToken = isset($ship['tokens']['map']) ? $ship['tokens']['map'] : '';
		$mapUrl = (!empty($shipId) && !empty($mapToken))
			? 'https://map.shipsgo.com/ocean/shipments/'.$shipId.'?token='.$mapToken
			: '';

		return array(
			'Message' => 'Success',
			'Success' => true,
			'SailingStatusId' => self::mapStatusToId($status),
			'Status' => $status,
			'Pol' => $pol,
			'Pod' => $pod,
			'Etd' => isset($route['port_of_loading']['date_of_loading_initial']) ? $route['port_of_loading']['date_of_loading_initial'] : '',
			'Atd' => isset($route['port_of_loading']['date_of_loading']) ? $route['port_of_loading']['date_of_loading'] : '',
			'Eta' => isset($route['port_of_discharge']['date_of_discharge_initial']) ? $route['port_of_discharge']['date_of_discharge_initial'] : '',
			'Ata' => isset($route['port_of_discharge']['date_of_discharge']) ? $route['port_of_discharge']['date_of_discharge'] : '',
			'MapUrl' => $mapUrl,
		);
	}

	/**
	 * Convert a ShipsGo date to the Y-m-d stored in extrafields.
	 *
	 * v2 dates are ISO8601 carrying the port's own UTC offset (2025-03-10T12:00:00-03:00):
	 * keep the port-local date as shown by ShipsGo rather than re-rendering through
	 * strtotime()/date(), which converts to the server timezone (off-by-one near midnight).
	 * Any other format keeps the legacy strtotime path.
	 *
	 * @param string $v Raw date string
	 * @return string Y-m-d, or '' when unparseable
	 */
	private function shipsgoDateToSql($v)
	{
		$v = trim((string) $v);
		if ($v === '') {
			return '';
		}
		if (preg_match('/^(\d{4}-\d{2}-\d{2})T/', $v, $m)) {
			return $m[1];
		}
		$ts = strtotime(str_replace('/', '-', $v));
		return $ts !== false ? date('Y-m-d', $ts) : '';
	}

	/**
	 * Apply GetContainerInfo response (or normalized webhook payload) to expedition_extrafields row.
	 *
	 * @param int   $fkExpedition Expedition row id
	 * @param array $ship_status  Normalized status array (Success branch)
	 * @return bool True if UPDATE OK
	 */
	public function applyShipsGoStatusToExtrafields($fkExpedition, array $ship_status)
	{
		$updatesql = "UPDATE ".MAIN_DB_PREFIX."expedition_extrafields SET";
		$updatesql .= " sailingstatusid = ".(int) ($ship_status['SailingStatusId'] ?? 0);
		$updatesql .= ", pol = '".$this->db->escape($ship_status['Pol'] ?? '')."'";
		// ETD (Estimated) and ATD (Actual)
		$sqlDate = $this->shipsgoDateToSql($ship_status['Etd'] ?? '');
		if ($sqlDate !== '') {
			$updatesql .= ", etd = '".$this->db->escape($sqlDate)."'";
		}
		$sqlDate = $this->shipsgoDateToSql($ship_status['Atd'] ?? '');
		if ($sqlDate !== '') {
			$updatesql .= ", atd = '".$this->db->escape($sqlDate)."'";
		}
		$updatesql .= ", pod = '".$this->db->escape($ship_status['Pod'] ?? '')."'";
		// ATA (Actual) and ETA (Estimated)
		$sqlDate = $this->shipsgoDateToSql($ship_status['Ata'] ?? '');
		if ($sqlDate !== '') {
			$updatesql .= ", ata = '".$this->db->escape($sqlDate)."'";
		}
		$sqlDate = $this->shipsgoDateToSql($ship_status['Eta'] ?? '');
		if ($sqlDate !== '') {
			$updatesql .= ", eta = '".$this->db->escape($sqlDate)."'";
		}
		$mapUrl = $ship_status['MapUrl'] ?? '';
		if (!empty($mapUrl)) {
			$updatesql .= ", livemapurl = '".$this->db->escape($mapUrl)."'";
		}
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
			$sql .= " (b.updatedtime IS NOT NULL AND b.updatedtime < '2026-05-19 12:00:00' AND b.updatedtime <= '".$this->db->escape($cutoff)."' AND (b.sailingstatusid IS NULL OR b.sailingstatusid NOT IN (3,4)))";
			$sql .= " OR (b.sailingstatusid IS NULL AND a.fk_statut > 0)";
			$sql .= " OR (b.updatedtime > '2026-05-19 12:00:00' AND b.updatedtime <= '".$this->db->escape($cutoff)."' AND (b.sailingstatusid IS NULL OR b.sailingstatusid NOT IN (5,6)))";
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
				$ship_status_list = $shipsGotmp->getContainerInfo($line->tracking_number);
				if (isset($ship_status_list['error'])) {
					$error++;
					$remaining--;
					continue;
				}
				$ship_status = $shipsGotmp->normalizeResponse($ship_status_list);
				if (!empty($ship_status['Success'])) {
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