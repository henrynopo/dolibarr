<?php

if (!class_exists('ShipsGo_API', false)) {
class ShipsGo_API
{
	/**
	 * API Base URL
	 */
	private const API_BASE = 'https://api.shipsgo.com/v2';

	/**
	 * @var string API token
	 */
	private string $apiToken;

	/**
	 * Constructor
	 *
	 * @param string $apiToken  ShipsGo API token (X-Shipsgo-User-Token)
	 */
	public function __construct(string $apiToken)
	{
		$this->apiToken = $apiToken;
	}

	/**
	 * Make HTTP GET request
	 *
	 * @param string $endpoint  API endpoint (e.g. /ocean/shipments)
	 * @param array  $query     Query parameters
	 * @return array            Decoded JSON response
	 */
	private function get(string $endpoint, array $query = array())
	{
		$url = self::API_BASE . $endpoint;
		if (!empty($query)) {
			$url .= '?' . http_build_query($query);
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept: application/json',
			'X-Shipsgo-User-Token: ' . $this->apiToken
		));

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error) {
			return array('error' => $error, 'httpCode' => $httpCode);
		}

		$result = json_decode($response, true);
		if (!is_array($result)) {
			$result = array('raw' => $response, 'httpCode' => $httpCode);
		} else {
			$result['httpCode'] = $httpCode;
		}

		return $result;
	}

	/**
	 * Make HTTP POST request
	 *
	 * @param string $endpoint  API endpoint (e.g. /ocean/shipments)
	 * @param array  $data      POST data
	 * @return array            Decoded JSON response
	 */
	private function post(string $endpoint, array $data = array())
	{
		$url = self::API_BASE . $endpoint;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept: application/json',
			'Content-Type: application/json',
			'X-Shipsgo-User-Token: ' . $this->apiToken
		));
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error) {
			return array('error' => $error, 'httpCode' => $httpCode);
		}

		$result = json_decode($response, true);
		if (!is_array($result)) {
			$result = array('raw' => $response, 'httpCode' => $httpCode);
		} else {
			$result['httpCode'] = $httpCode;
		}

		return $result;
	}

	/**
	 * Create shipment by container number (v2 API)
	 *
	 * @param string $containerNumber  Container number
	 * @param string $carrier         Shipping line/carrier code
	 * @param array  $emails          Follower emails
	 * @param string $referenceNo     Internal reference
	 * @return array                  API response with shipment id
	 */
	public function createShipment(string $containerNumber, string $carrier, array $emails = array(), string $referenceNo = '')
	{
		$data = array(
			'container_number' => $containerNumber,
			'carrier' => $carrier,
		);

		if (!empty($emails)) {
			$data['followers'] = $emails;
		}

		if (!empty($referenceNo)) {
			$data['reference'] = $referenceNo;
		}

		return $this->post('/ocean/shipments', $data);
	}

	/**
	 * Create shipment with B/L reference (v2 API)
	 *
	 * @param string $containerNumber  Container number
	 * @param string $blReference      B/L booking number
	 * @param string $carrier           Shipping line/carrier code
	 * @param array  $emails           Follower emails
	 * @param string $referenceNo       Internal reference
	 * @return array                   API response with shipment id
	 */
	public function createShipmentWithBl(string $containerNumber, string $blReference, string $carrier, array $emails = array(), string $referenceNo = '')
	{
		$data = array(
			'container_number' => $containerNumber,
			'booking_number' => $blReference,
			'carrier' => $carrier,
		);

		if (!empty($emails)) {
			$data['followers'] = $emails;
		}

		if (!empty($referenceNo)) {
			$data['reference'] = $referenceNo;
		}

		return $this->post('/ocean/shipments', $data);
	}

	/**
	 * Get carrier list
	 *
	 * @param array $filters  Optional filters (status, name, etc.)
	 * @return array
	 */
	public function getCarrierList(array $filters = array())
	{
		$query = array();
		if (!empty($filters)) {
			foreach ($filters as $key => $val) {
				$query['filters[' . $key . ']'] = $val;
			}
		}
		return $this->get('/ocean/carriers', $query);
	}

	/**
	 * Search shipment by container number
	 *
	 * @param string $containerNumber  Container number (e.g. 'MSCU1234567')
	 * @return array                  List of matching shipments (OceanShipmentsListData)
	 */
	public function searchByContainer(string $containerNumber)
	{
		return $this->get('/ocean/shipments', array(
			'filters[container_number]' => 'eq:' . $containerNumber
		));
	}

	/**
	 * Search shipment by booking number (B/L reference)
	 *
	 * @param string $bookingNumber  Booking number / B/L reference
	 * @return array                 List of matching shipments
	 */
	public function searchByBooking(string $bookingNumber)
	{
		return $this->get('/ocean/shipments', array(
			'filters[booking_number]' => 'eq:' . $bookingNumber
		));
	}

	/**
	 * Search by B/L number or container number (B/L first, then container)
	 * This matches the v1 behavior where B/L was preferred
	 *
	 * @param string $trackingRef  B/L number or container number
	 * @return array              First matching shipment or error
	 */
	public function searchShipment(string $trackingRef)
	{
		$trackingRef = trim($trackingRef);
		if (empty($trackingRef)) {
			return array('error' => 'Empty tracking reference');
		}

		// First try: Search by booking number (B/L)
		$result = $this->searchByBooking($trackingRef);
		// v2 returns 'shipments' array, also check 'data' for compatibility
		if (!isset($result['error']) && (!empty($result['shipments']) || !empty($result['data']))) {
			$result['search_type'] = 'booking';
			return $result;
		}

		// Second try: Search by container number
		$result = $this->searchByContainer($trackingRef);
		if (!isset($result['error']) && (!empty($result['shipments']) || !empty($result['data']))) {
			$result['search_type'] = 'container';
			return $result;
		}

		// Return last result even if empty (no shipment found)
		$result['search_type'] = 'none';
		return $result;
	}

	/**
	 * Get shipment details by ID
	 *
	 * @param int|string $shipmentId  ShipsGo shipment ID
	 * @return array                  OceanShipment details
	 */
	public function getShipment($shipmentId)
	{
		return $this->get('/ocean/shipments/' . $shipmentId);
	}

	/**
	 * Get container info by tracking reference (B/L or container number)
	 * Uses B/L first, then falls back to container number
	 *
	 * @param string $trackingRef  B/L number or container number
	 * @return array               Full shipment detail with containers
	 */
	public function getContainerInfo(string $trackingRef)
	{
		// Search: B/L first, then container number
		$searchResult = $this->searchShipment($trackingRef);

		if (isset($searchResult['error'])) {
			return $searchResult;
		}

		// Check if we got any results
		$shipments = $searchResult['shipments'] ?? null;
		$data = $searchResult['data'] ?? null;

		if (empty($shipments) && empty($data)) {
			return array(
				'Message' => 'No shipment found for ' . $trackingRef,
				'data' => array(),
				'httpCode' => $searchResult['httpCode'] ?? 200
			);
		}

		// Get shipment data (could be in 'shipments' array or 'data' object, or nested 'shipment' singular)
		$shipmentData = null;
		$shipmentId = null;

		if (!empty($shipments) && is_array($shipments) && isset($shipments[0])) {
			// List response - get first shipment's ID
			$first = $shipments[0];
			$shipmentId = $first['id'] ?? null;
			if ($shipmentId) {
				$detailResult = $this->getShipment($shipmentId);
				$detailResult['search_type'] = $searchResult['search_type'] ?? 'none';
				return $detailResult;
			}
		} elseif (!empty($data)) {
			// Could be detail response with 'shipment' object directly
			if (isset($data['shipment']) && is_array($data['shipment'])) {
				$shipmentData = $data['shipment'];
			} elseif (is_array($data) && isset($data['id'])) {
				$shipmentData = $data;
			}
		} elseif (!empty($searchResult['shipment']) && is_array($searchResult['shipment'])) {
			// Direct 'shipment' object in response
			$shipmentData = $searchResult['shipment'];
		}

		if ($shipmentData) {
			// Return the direct shipment data
			$result = $searchResult;
			$result['shipment'] = $shipmentData;
			unset($result['shipments'], $result['data']);
			return $result;
		}

		return array(
			'Message' => 'Invalid shipment response structure',
			'httpCode' => $searchResult['httpCode'] ?? 200
		);
	}

	/**
	 * Normalize v2 response to match v1 format for backward compatibility
	 * This helps maintain compatibility with existing code that expects v1 response structure
	 *
	 * @param array $v2Response  Raw v2 API response
	 * @return array              Normalized response with 'Message' and status fields
	 */
	public function normalizeResponse(array $v2Response)
	{
		if (isset($v2Response['error'])) {
			return array(
				'Message' => $v2Response['error'],
				'Success' => false
			);
		}

		// v2 API structure varies:
		// - List/search: { message, shipments: [...], meta, httpCode }
		// - Detail: { message, shipment: {...}, httpCode }
		// Also check for nested 'data' wrapper
		$shipments = null;
		$singleShipment = null;

		if (!empty($v2Response['shipments']) && is_array($v2Response['shipments'])) {
			// List/search response with 'shipments' array
			$shipments = $v2Response['shipments'];
		} elseif (!empty($v2Response['shipment']) && is_array($v2Response['shipment'])) {
			// Detail response with single 'shipment' object (not array)
			$singleShipment = $v2Response['shipment'];
		} elseif (!empty($v2Response['data'])) {
			// Might be wrapped in 'data'
			$data = $v2Response['data'];
			if (is_array($data)) {
				if (isset($data['shipments']) && is_array($data['shipments'])) {
					$shipments = $data['shipments'];
				} elseif (isset($data['shipment']) && is_array($data['shipment'])) {
					$singleShipment = $data['shipment'];
				} elseif (isset($data[0]) && is_array($data[0])) {
					// Numeric array
					$shipments = $data;
				} else {
					// Single object
					$singleShipment = $data;
				}
			}
		}

		if ($shipments === null && $singleShipment === null) {
			return array(
				'Message' => 'No shipment data found',
				'Success' => false,
				'raw' => $v2Response
			);
		}

		// Handle shipments array (list response)
		if ($shipments !== null && isset($shipments[0]) && is_array($shipments[0])) {
			$first = $shipments[0];
			$status = $first['status'] ?? 'UNKNOWN';
			$route = $first['route'] ?? array();

			return array(
				'Message' => 'Success',
				'Success' => true,
				'SailingStatusId' => $this->mapStatusToId($status),
				'Status' => $status,
				'Pol' => $route['port_of_loading']['location']['name'] ?? $route['port_of_loading']['location']['code'] ?? '',
				'Pod' => $route['port_of_discharge']['location']['name'] ?? $route['port_of_discharge']['location']['code'] ?? '',
				'Etd' => $route['port_of_loading']['date_of_loading_initial'] ?? '',
				'Atd' => $route['port_of_loading']['date_of_loading'] ?? '',
				'Eta' => $route['port_of_discharge']['date_of_discharge_initial'] ?? '',
				'Ata' => $route['port_of_discharge']['date_of_discharge'] ?? '',
				'TransshipmentCount' => $route['ts_count'] ?? 0,
				'Containers' => $first['containers'] ?? array(),
				'MapUrl' => !empty($first['tokens']['map']) && !empty($first['id'])
				? 'https://map.shipsgo.com/ocean/shipments/'.$first['id'].'?token='.$first['tokens']['map']
				: '',
				'raw' => $v2Response
			);
		}

		// Handle single shipment detail response
		if ($singleShipment !== null) {
			$status = $singleShipment['status'] ?? 'UNKNOWN';
			$route = $singleShipment['route'] ?? array();

			return array(
				'Message' => 'Success',
				'Success' => true,
				'SailingStatusId' => $this->mapStatusToId($status),
				'Status' => $status,
				'Pol' => $route['port_of_loading']['location']['name'] ?? $route['port_of_loading']['location']['code'] ?? '',
				'Pod' => $route['port_of_discharge']['location']['name'] ?? $route['port_of_discharge']['location']['code'] ?? '',
				'Etd' => $route['port_of_loading']['date_of_loading_initial'] ?? '',
				'Atd' => $route['port_of_loading']['date_of_loading'] ?? '',
				'Eta' => $route['port_of_discharge']['date_of_discharge_initial'] ?? '',
				'Ata' => $route['port_of_discharge']['date_of_discharge'] ?? '',
				'Eta' => $route['port_of_discharge']['date_of_discharge'] ?? '',
				'TransshipmentCount' => $route['ts_count'] ?? 0,
				'TransitTime' => $route['transit_time'] ?? 0,
				'TransitPercentage' => $route['transit_percentage'] ?? 0,
				'Containers' => $singleShipment['containers'] ?? array(),
				'ShipmentId' => $singleShipment['id'] ?? null,
			'MapToken' => $singleShipment['tokens']['map'] ?? '',
			'MapUrl' => !empty($singleShipment['tokens']['map']) && !empty($singleShipment['id'])
				? 'https://map.shipsgo.com/ocean/shipments/'.$singleShipment['id'].'?token='.$singleShipment['tokens']['map']
				: '',
				'Carrier' => $singleShipment['carrier'] ?? array(),
				'BookingNumber' => $singleShipment['booking_number'] ?? '',
				'ContainerNumber' => $singleShipment['container_number'] ?? '',
				'raw' => $v2Response
			);
		}

		return array(
			'Message' => 'Failed to parse response',
			'Success' => false,
			'raw' => $v2Response
		);
	}

	/**
	 * Map v2 status string to v1-style numeric ID for backward compatibility
	 *
	 * @param string $status  V2 status enum value
	 * @return int
	 */
	private function mapStatusToId(string $status): int
	{
		$map = array(
			'NEW' => 0,
			'INPROGRESS' => 1,
			'BOOKED' => 2,
			'LOADED' => 3,
			'SAILING' => 4,
			'ARRIVED' => 5,
			'DISCHARGED' => 6,
			'UNTRACKED' => 99
		);
		return $map[$status] ?? 99;
	}
}
}