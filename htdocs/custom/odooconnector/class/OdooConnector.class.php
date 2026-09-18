<?php
/* Copyright (C) 2025  Odoo Connector (Dolibarr)
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
 * Odoo JSON-RPC API client for use by Odoo Connector sync.
 * Connects to Odoo Online (or self-hosted) via JSON-RPC.
 */
class OdooConnector
{
	/** @var string Base URL e.g. https://yourcompany.odoo.com */
	public $baseUrl;

	/** @var string Database name (Odoo Online: often company subdomain) */
	public $db;

	/** @var string Username */
	public $username;

	/** @var string Password or API key */
	public $password;

	/** @var int|null UID after login */
	protected $uid;

	/** @var string Last error message */
	public $error;

	/** @var int Last HTTP status */
	public $lastHttpCode;

	/**
	 * Constructor
	 *
	 * @param string $baseUrl  Base URL e.g. https://yourcompany.odoo.com
	 * @param string $db       Database name
	 * @param string $username Username
	 * @param string $password Password or API key
	 */
	public function __construct($baseUrl, $db, $username, $password)
	{
		$this->baseUrl = rtrim($baseUrl, '/');
		$this->db = $db;
		$this->username = $username;
		$this->password = $password;
		$this->uid = null;
		$this->error = '';
		$this->lastHttpCode = 0;
	}

	/**
	 * Authenticate and get UID
	 *
	 * @return int|false User ID on success, false on failure
	 */
	public function authenticate()
	{
		$payload = array(
			'jsonrpc' => '2.0',
			'method' => 'call',
			'params' => array(
				'service' => 'common',
				'method' => 'authenticate',
				'args' => array($this->db, $this->username, $this->password, array()),
			),
			'id' => 1,
		);

		$resp = $this->call($payload);
		if ($resp === false) {
			return false;
		}

		if (isset($resp['result']) && $resp['result'] !== false && $resp['result'] !== null) {
			$this->uid = (int) $resp['result'];
			return $this->uid;
		}

		$this->error = isset($resp['error']['data']['message']) ? $resp['error']['data']['message'] : 'Authentication failed';
		return false;
	}

	/**
	 * Call Odoo model method (execute_kw)
	 *
	 * @param string $model  Odoo model e.g. account.move, res.partner
	 * @param string $method Method e.g. search_read, create, write
	 * @param array  $args   Positional args (e.g. domain, fields)
	 * @param array  $kwargs Optional kwargs (e.g. limit, offset)
	 * @return array|int|bool Result or false on failure
	 */
	public function executeKw($model, $method, $args = array(), $kwargs = array())
	{
		if ($this->uid === null && $this->authenticate() === false) {
			return false;
		}

		$payload = array(
			'jsonrpc' => '2.0',
			'method' => 'call',
			'params' => array(
				'service' => 'object',
				'method' => 'execute_kw',
				'args' => array($this->db, $this->uid, $this->password, $model, $method, $args, $kwargs),
			),
			'id' => 2,
		);

		$resp = $this->call($payload);
		if ($resp === false) {
			return false;
		}

		if (isset($resp['error'])) {
			$this->error = isset($resp['error']['data']['message']) ? $resp['error']['data']['message'] : json_encode($resp['error']);
			return false;
		}

		return isset($resp['result']) ? $resp['result'] : true;
	}

	/**
	 * Search_read helper
	 *
	 * @param string $model  Model name
	 * @param array  $domain Search domain
	 * @param array  $fields Fields to return
	 * @param int    $limit  Limit
	 * @param int    $offset Offset
	 * @return array|false List of records or false
	 */
	public function searchRead($model, $domain = array(), $fields = array(), $limit = 0, $offset = 0, $order = '')
	{
		$kwargs = array();
		if (!empty($fields)) {
			$kwargs['fields'] = $fields;
		}
		if ($limit > 0) {
			$kwargs['limit'] = $limit;
		}
		if ($offset > 0) {
			$kwargs['offset'] = $offset;
		}
		if ($order !== '') {
			$kwargs['order'] = $order;
		}
		return $this->executeKw($model, 'search_read', array($domain), $kwargs);
	}

	/**
	 * Create a record
	 *
	 * @param string $model Model name
	 * @param array  $data  Field values
	 * @return int|false New id or false
	 */
	public function create($model, $data)
	{
		$result = $this->executeKw($model, 'create', array($data));
		return is_numeric($result) ? (int) $result : false;
	}

	/**
	 * Update records
	 *
	 * @param string $model Model name
	 * @param array  $ids   IDs to update
	 * @param array  $data  Field values
	 * @return bool Success
	 */
	public function write($model, $ids, $data)
	{
		if (empty($ids)) {
			return true;
		}
		$result = $this->executeKw($model, 'write', array($ids, $data));
		return $result === true;
	}

	/**
	 * Delete records
	 *
	 * @param string $model Model name
	 * @param array  $ids   IDs to delete
	 * @return bool Success
	 */
	public function unlink($model, $ids)
	{
		if (empty($ids)) {
			return true;
		}
		$result = $this->executeKw($model, 'unlink', array(array_values($ids)));
		return $result === true;
	}

	/**
	 * Send JSON-RPC request to Odoo
	 *
	 * @param array $payload JSON-RPC payload
	 * @return array|false Decoded response or false
	 */
	protected function call($payload)
	{
		$url = $this->baseUrl . '/jsonrpc';
		$body = json_encode($payload);
		$this->error = '';
		$this->lastHttpCode = 0;

		// cURL transport (same as ShipsGo_API): allow_url_fopen is often disabled on shared hosting
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // the validate trigger calls us inline: fail fast when Odoo is unreachable
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept: application/json',
			'Content-Type: application/json'
		));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		$this->lastHttpCode = (int) $httpCode;

		if ($response === false || $curlError !== '') {
			$this->error = 'HTTP request failed: '.$curlError;
			return false;
		}

		$decoded = json_decode($response, true);
		if (!is_array($decoded)) {
			$this->error = 'Invalid JSON response (HTTP '.$httpCode.')';
			return false;
		}

		return $decoded;
	}
}
