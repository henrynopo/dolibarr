<?php

if (!class_exists('Wise_API', false)) {
class Wise_API
{
	/**
	 * API Base URL (production). Sandbox: https://api.sandbox.transferwise.tech
	 */
	private const API_BASE = 'https://api.wise.com';

	/**
	 * @var string API token (Bearer)
	 */
	private string $apiToken;

	/**
	 * Constructor
	 *
	 * @param string $apiToken  Wise API token created in Developer tools
	 */
	public function __construct(string $apiToken)
	{
		$this->apiToken = $apiToken;
	}

	/**
	 * Build an instance from the per-entity Dolibarr constant WISE_API_TOKEN.
	 *
	 * @param  DoliDB    $db       Database handler
	 * @param  int       $entityId Company entity
	 * @return Wise_API|null       Null when no token configured
	 */
	public static function fromEntity($db, $entityId)
	{
		$token = trim((string) dolibarr_get_const($db, 'WISE_API_TOKEN', (int) $entityId));
		if ($token === '') {
			return null;
		}
		return new self($token);
	}

	/**
	 * Make an HTTP request and decode the JSON response.
	 *
	 * @param  string      $method  HTTP method (GET/POST/...)
	 * @param  string      $endpoint API endpoint with leading slash
	 * @param  array       $query   Query parameters
	 * @param  array|null  $data    JSON body (null = none)
	 * @param  int         $timeout Curl timeout in seconds
	 * @return array                 Decoded response with 'httpCode' injected;
	 *                               array('error'=>..., 'httpCode'=>..) on transport failure
	 */
	private function request(string $method, string $endpoint, array $query = array(), ?array $data = null, int $timeout = 30): array
	{
		$url = self::API_BASE.$endpoint;
		if (!empty($query)) {
			$url .= '?'.http_build_query($query);
		}

		$headers = array(
			'Accept: application/json',
			'Authorization: Bearer '.$this->apiToken,
		);
		if ($data !== null) {
			$headers[] = 'Content-Type: application/json';
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
		if ($data !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$response = curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error) {
			return array('error' => $error, 'httpCode' => $httpCode);
		}

		$result = json_decode((string) $response, true);
		if (!is_array($result)) {
			$result = array('raw' => $response);
		}
		$result['httpCode'] = $httpCode;

		return $result;
	}

	/**
	 * List profiles of the token owner. First step to find the profileId.
	 *
	 * @return array
	 */
	public function getProfiles(): array
	{
		return $this->request('GET', '/v1/profiles');
	}

	/**
	 * List webhook subscriptions of a profile. The response items carry the
	 * signature public key used to verify X-Signature-SHA256 deliveries.
	 *
	 * Endpoint availability depends on the credential class: the dated /2026Q3
	 * path is partner-gated (403 on business tokens), older /v3 and /v1 paths
	 * vary by account. Try each in turn; 404/403 falls through to the next.
	 * Normalises an {'items': [...]} envelope into a flat list.
	 *
	 * @param  int $profileId Wise profile id
	 * @return array
	 */
	public function getSubscriptions(int $profileId): array
	{
		$paths = array(
			'/v3/profiles/'.$profileId.'/subscriptions',
			'/v1/profiles/'.$profileId.'/subscriptions',
			'/2026Q3/profiles/'.$profileId.'/subscriptions',
		);
		$result = array();
		$lastAuthError = null;
		foreach ($paths as $path) {
			$result = $this->request('GET', $path);
			$httpCode = isset($result['httpCode']) ? (int) $result['httpCode'] : 0;
			if ($httpCode > 0 && $httpCode < 400) {
				break; // reachable: use this response
			}
			if ($httpCode === 403 || $httpCode === 401) {
				$lastAuthError = $result; // remember the most informative failure
			}
			// 404 or 5xx: try the next path
		}
		if (isset($result['httpCode']) && (int) $result['httpCode'] >= 400 && $lastAuthError !== null) {
			$result = $lastAuthError;
		}
		if (isset($result['items']) && is_array($result['items']) && !isset($result[0])) {
			$httpCode = isset($result['httpCode']) ? (int) $result['httpCode'] : 0;
			$result = $result['items'];
			$result['httpCode'] = $httpCode;
		}
		return $result;
	}

	/**
	 * List balance accounts of a profile (id + currency pairs).
	 *
	 * @param  int $profileId Wise profile id
	 * @return array
	 */
	public function getBalances(int $profileId): array
	{
		return $this->request('GET', '/v1/profiles/'.$profileId.'/balances', array('types' => 'STANDARD'));
	}

	/**
	 * Request the generation of a balance statement for a time interval.
	 * Generation is asynchronous: poll getStatement() until status is finished.
	 *
	 * @param  int    $profileId      Wise profile id
	 * @param  int    $balanceId      Balance account id
	 * @param  string $intervalStartIso ISO 8601, e.g. 2026-09-16T02:00:00Z
	 * @param  string $intervalEndIso   ISO 8601
	 * @return array  Contains 'id' and 'status' on success
	 */
	public function requestStatement(int $profileId, int $balanceId, string $intervalStartIso, string $intervalEndIso): array
	{
		return $this->request('POST', '/v1/profiles/'.$profileId.'/balance-statements/'.$balanceId.'/statements.json', array(), array(
			'intervalStart' => $intervalStartIso,
			'intervalEnd' => $intervalEndIso,
			'type' => 'COMPACT',
		));
	}

	/**
	 * Fetch a statement (poll until generation finished).
	 *
	 * @param  int    $profileId   Wise profile id
	 * @param  int    $balanceId   Balance account id
	 * @param  string $statementId Statement id from requestStatement()
	 * @param  int    $waitSeconds Total wait budget for the async generation
	 * @return array  Statement with 'transactions' when finished
	 */
	public function getStatement(int $profileId, int $balanceId, string $statementId, int $waitSeconds = 10): array
	{
		$deadline = time() + $waitSeconds;
		do {
			$stmt = $this->request('GET', '/v1/profiles/'.$profileId.'/balance-statements/'.$balanceId.'/statements/'.$statementId.'.json');
			if (isset($stmt['error']) || empty($stmt['status']) || $stmt['status'] === 'finished' || $stmt['status'] === 'failed') {
				return $stmt;
			}
			if (time() >= $deadline) {
				return $stmt;
			}
			sleep(2);
		} while (true);
	}

	/**
	 * Convenience wrapper: request a statement for an interval and return its
	 * transactions (empty array when nothing in the interval).
	 *
	 * @param  int    $profileId        Wise profile id
	 * @param  int    $balanceId        Balance account id
	 * @param  string $intervalStartIso ISO 8601
	 * @param  string $intervalEndIso   ISO 8601
	 * @param  int    $waitSeconds      Async generation wait budget
	 * @return array  ['transactions' => [...]] or ['error' => ...]
	 */
	public function getStatementTransactions(int $profileId, int $balanceId, string $intervalStartIso, string $intervalEndIso, int $waitSeconds = 10): array
	{
		$created = $this->requestStatement($profileId, $balanceId, $intervalStartIso, $intervalEndIso);
		if (isset($created['error']) || $created['httpCode'] >= 400 || empty($created['id'])) {
			return array('error' => 'requestStatement failed: '.json_encode($created), 'detail' => $created);
		}
		$stmt = $this->getStatement($profileId, $balanceId, (string) $created['id'], $waitSeconds);
		if (isset($stmt['error']) || $stmt['httpCode'] >= 400) {
			return array('error' => 'getStatement failed: '.json_encode($stmt), 'detail' => $stmt);
		}
		if (empty($stmt['status']) || $stmt['status'] !== 'finished') {
			return array('error' => 'statement not finished in time: '.json_encode($stmt), 'detail' => $stmt);
		}
		return array('transactions' => isset($stmt['transactions']) && is_array($stmt['transactions']) ? $stmt['transactions'] : array());
	}

	/**
	 * Find the balance account id for a currency.
	 *
	 * @param  int    $profileId Wise profile id
	 * @param  string $currency  ISO 4217 code (e.g. SGD)
	 * @return int|null          Balance id or null when not found / API error
	 */
	public function findBalanceIdForCurrency(int $profileId, string $currency): ?int
	{
		$balances = $this->getBalances($profileId);
		if (isset($balances['error']) || $balances['httpCode'] >= 400 || empty($balances[0])) {
			dol_syslog('Wise_API findBalanceIdForCurrency failed for '.$currency.': '.json_encode($balances), LOG_WARNING);
			return null;
		}
		// Drop the httpCode pseudo-entry before iterating
		unset($balances['httpCode']);
		foreach ($balances as $bal) {
			if (is_array($bal) && isset($bal['id'], $bal['currency']) && strcasecmp((string) $bal['currency'], $currency) === 0) {
				return (int) $bal['id'];
			}
		}
		return null;
	}

	// ---- Outgoing transfers (flow A) -------------------------------------------------

	/**
	 * Create a quote (exchange rate + fees) for a transfer.
	 *
	 * @param  int    $profileId    Wise profile id
	 * @param  string $sourceCurrency ISO 4217 (e.g. SGD)
	 * @param  string $targetCurrency ISO 4217 (invoice currency)
	 * @param  float  $targetAmount Amount the recipient must receive
	 * @return array  Quote incl. 'id', 'rate', 'sourceAmount', 'fee'
	 */
	public function createQuote(int $profileId, string $sourceCurrency, string $targetCurrency, float $targetAmount): array
	{
		return $this->request('POST', '/v3/profiles/'.$profileId.'/quotes', array(), array(
			'sourceCurrency' => strtoupper($sourceCurrency),
			'targetCurrency' => strtoupper($targetCurrency),
			'targetAmount' => round($targetAmount, 2),
			'payOut' => 'BANK_TRANSFER',
		));
	}

	/**
	 * Create (or reuse) an IBAN recipient for a supplier. Wise may require
	 * extra fields per currency; the full API answer is returned so the caller
	 * can surface the requirements error to the operator.
	 *
	 * @param  int    $profileId Wise profile id
	 * @param  string $currency  Recipient currency
	 * @param  string $holder    Account holder (supplier name)
	 * @param  string $iban
	 * @param  string $bic       May be empty
	 * @return array  Incl. 'id' on success
	 */
	public function createRecipientIban(int $profileId, string $currency, string $holder, string $iban, string $bic = ''): array
	{
		$details = array('IBAN' => preg_replace('/\s+/', '', $iban));
		if ($bic !== '') {
			$details['BIC'] = preg_replace('/\s+/', '', $bic);
		}
		return $this->request('POST', '/v1/accounts', array(), array(
			'profile' => $profileId,
			'currency' => strtoupper($currency),
			'type' => 'iban',
			'accountHolderName' => $holder,
			'details' => $details,
		));
	}

	/**
	 * Create an UNFUNDED transfer (payment order awaiting manual funding in
	 * the Wise dashboard — that manual step is the approval gate by design).
	 * The funding endpoint is intentionally never called by this module.
	 *
	 * @param  int    $recipientId            Wise recipient account id
	 * @param  string $quoteId                Wise quote id (uuid)
	 * @param  string $customerTransactionId  Idempotency uuid (stored per mapping row)
	 * @param  string $reference              Reference shown to the recipient (vendor's own order ref)
	 * @return array  Incl. 'id', 'status' on success
	 */
	public function createTransfer(int $recipientId, string $quoteId, string $customerTransactionId, string $reference): array
	{
		return $this->request('POST', '/v1/transfers', array(), array(
			'targetAccount' => $recipientId,
			'quote' => $quoteId,
			'customerTransactionId' => $customerTransactionId,
			'details' => array(
				'reference' => substr($reference, 0, 100),
			),
		));
	}

	/**
	 * Fetch one transfer (status polling / manual refresh).
	 *
	 * @param  int $transferId Wise transfer id
	 * @return array Incl. 'id', 'status'
	 */
	public function getTransfer(int $transferId): array
	{
		return $this->request('GET', '/v1/transfers/'.(int) $transferId);
	}
}
}
