<?php
/* Copyright (C) 2026 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Software Foundation; either version 3 of the
 * License, or (at your option) any later version.
 */

/**
 * \file    custom/slycustom/webhook/wise.php
 * \ingroup slycustom
 * \brief   Wise Platform webhook receiver.
 *
 * Public endpoint: NO login, NO CSRF check, NO IP restrict.
 * URL: /custom/slycustom/webhook/wise.php
 *
 * Wise URL constraints (differ from ShipsGo — see docs.wise.com webhooks guide):
 *  - The registered URL must NOT contain query arguments, so entity cannot be
 *    passed as ?entity=N. Set WISE_WEBHOOK_ENTITY below instead (deploy-time).
 *  - Wise must receive a direct 2xx answer; redirects are delivery errors.
 *  - Wise retries non-2xx with exponential backoff (1 min up to 24 h).
 *
 * Security: each delivery is signed with RSA-SHA256 over the exact raw body
 * (header X-Signature-SHA256, base64). Verification uses the subscription
 * public key stored in DOL_DATA_ROOT/wise_webhook/verification_key.pem.
 * Until that key file exists the receiver runs in bootstrap mode, where the
 * accepted authentication is decided by WISE_WEBHOOK_IPCHECK:
 *  - off (default): the delivery is acknowledged with 200 but NOT ingested, so
 *    an unsigned public endpoint can never write payments or fill the disk.
 *  - on: the delivery already passed the Wise egress IP allowlist, so it is
 *    ingested (this is the documented interim path for business accounts whose
 *    subscription API is partner-gated and cannot return the public key).
 * Every delivery — ingested, rejected or dropped — leaves a diagnostic dump in
 * DOL_DATA_ROOT/wise_webhook, truncated to 2 KB and rotated at 50 files when
 * it could not be verified.
 *
 * Ingested events (WiseIncomingPayment::createFromWebhook):
 *  - balances#credit / balances#update(credit): queued into the incoming
 *    payments reconcile table. The v2.0.0 credit payload carries no payment
 *    reference — details are recovered from the statement API at enrichment.
 *  - X-Test-Notification: true deliveries (and zero-uuid subscriptions) are
 *    stored with is_test=1 and NOT queued.
 *  - Other event types are stored only (transfers#state-change will drive the
 *    outbound-payment flow later).
 */

if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('USESUFFIXINLOG')) {
	define('USESUFFIXINLOG', '_wise_webhook');
}

// Entity: Wise cannot send ?entity=N (no query args allowed), so the entity
// comes from this deploy-time constant. The GET/POST parameter is still
// honoured for manual curl tests only. On multicompany setups set this to the
// company that owns the Wise profile.
if (!defined('WISE_WEBHOOK_ENTITY')) {
	define('WISE_WEBHOOK_ENTITY', 1);
}
$entity = 0;
if (!empty($_GET['entity'])) {
	$entity = (int) $_GET['entity'];
} elseif (!empty($_POST['entity'])) {
	$entity = (int) $_POST['entity'];
}
$entityExplicit = $entity >= 1;
if ($entity < 1) {
	$entity = (int) WISE_WEBHOOK_ENTITY;
}
if (!defined('DOLENTITY')) {
	define('DOLENTITY', $entity);
}

require_once __DIR__.'/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_Incoming.class.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

/**
 * Send a JSON response and stop.
 *
 * @param  int    $httpCode HTTP status code
 * @param  array  $payload  Response body
 * @return void
 */
function wiseWebhookRespond($httpCode, array $payload)
{
	http_response_code($httpCode);
	echo json_encode($payload);
	if (function_exists('dol_syslog')) {
		dol_syslog('wise_webhook respond http='.$httpCode.' '.json_encode($payload), LOG_INFO);
	}
	exit;
}

if (!is_object($db) || !is_object($conf)) {
	wiseWebhookRespond(500, array('error' => 'Dolibarr context not loaded'));
}

/**
 * Write a diagnostic dump of one delivery into DOL_DATA_ROOT/wise_webhook/<bucket>.
 *
 * Dumps are the only evidence available when a delivery is rejected before
 * ingestion, so they are written for unsigned/unverified deliveries too — but
 * then truncated to a small head slice and the directory is rotated, so an
 * anonymous caller cannot fill the disk.
 *
 * Rejected deliveries go to their own bucket: with the IP allowlist armed, an
 * anonymous caller hitting the endpoint would otherwise churn the shared
 * 50-file rotation and evict genuine Wise evidence. Split buckets mean scanners
 * can only evict their own noise.
 *
 * @param  string $raw       Exact request body
 * @param  array  $headers   Collected request headers
 * @param  bool   $bootstrap True when the body was NOT signature-verified
 * @param  int    $entity    Target entity
 * @param  string $bucket    'accepted' or 'rejected'
 * @return string            Path written, or '' on failure
 */
function wiseWriteDump($raw, array $headers, $bootstrap, $entity, $bucket = 'accepted')
{
	$payloadDir = DOL_DATA_ROOT.'/wise_webhook/'.preg_replace('/[^a-z]/', '', strtolower($bucket));
	if (!is_dir($payloadDir)) {
		@mkdir($payloadDir, 0755, true);
	}
	// Full dumps only for verified deliveries; unverified ones keep a 2 KB head
	// slice so diagnosis stays possible without an unbounded write channel.
	$full = (!$bootstrap && strlen($raw) <= 65536);
	$payloadFile = $payloadDir.'/'.date('Ymd-His').'-e'.(int) $entity.'-'.substr(md5($raw), 0, 8).'.json';
	$dump = array(
		'received_at' => dol_now(),
		'entity' => (int) $entity,
		'bucket' => $bucket,
		'bootstrap' => $bootstrap,
		'remote_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
		'headers' => $headers,
		'body_truncated' => !$full,
		'body' => $full ? $raw : substr($raw, 0, 2048),
	);
	if (@file_put_contents($payloadFile, json_encode($dump, JSON_PRETTY_PRINT)) === false) {
		dol_syslog('wise_webhook failed to save payload file '.$payloadFile, LOG_WARNING);
		return '';
	}
	if (!$full) {
		dol_syslog('wise_webhook unverified delivery: only a 2 KB head slice was kept ('.$payloadFile.')', LOG_WARNING);
	}

	// Rotation: keep the newest 50 dumps, drop the rest.
	$kept = glob($payloadDir.'/*.json');
	if (is_array($kept) && count($kept) > 50) {
		sort($kept);
		foreach (array_slice($kept, 0, count($kept) - 50) as $old) {
			@unlink($old);
		}
	}
	return $payloadFile;
}

// dolibarr_get_const lives in admin.lib.php; not every context loads it.
if (!function_exists('dolibarr_get_const')) {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
}

// 1. Read raw body once. An empty body is plausible during URL validation at
//    subscription creation — ack it instead of failing the subscription.
$raw = file_get_contents('php://input');
if ($raw === false) {
	$raw = '';
}

// 2. Collect delivery headers (case-insensitive lookup).
$headers = array();
foreach ($_SERVER as $k => $v) {
	if (strpos($k, 'HTTP_') === 0 && is_string($v)) {
		$headers[substr($k, 5)] = $v;
	}
}
$signature = isset($headers['X_SIGNATURE_SHA256']) ? trim($headers['X_SIGNATURE_SHA256']) : '';
$deliveryId = isset($headers['X_DELIVERY_ID']) ? trim($headers['X_DELIVERY_ID']) : '';
$isTestHeader = false;
if (isset($headers['X_TEST_NOTIFICATION'])) {
	$isTestHeader = filter_var($headers['X_TEST_NOTIFICATION'], FILTER_VALIDATE_BOOL);
}

// The verification key presence decides both step 3b and whether a dump may
// keep the full body, so it is resolved before the IP allowlist runs: a
// delivery rejected by the allowlist still needs a dump (into the "rejected"
// bucket), and that dump is written in its unverified/truncated form.
$keyFile = DOL_DATA_ROOT.'/wise_webhook/verification_key.pem';
$bootstrap = !is_readable($keyFile);

// 3a. Optional source-IP allowlist (Wise egress networks) — hardening used
//     when the signature public key is not retrievable (business Developer
//     tools accounts expose no subscription API). Enable via WISE_WEBHOOK_IPCHECK=1.
//     Production CIDRs per docs.wise.com webhooks guide; sandbox IPs included.
$ipCheckActive = ((string) getDolGlobalString('WISE_WEBHOOK_IPCHECK') === '1');
if ($ipCheckActive) {
	$wiseAllowedSources = array(
		// Current documented egress ranges
		'45.129.54.0/24', '45.129.55.0/24',
		// AWS ranges Wise has actually been observed delivering from (dump
		// evidence): 18.184.162.75 on 2026-10-10, 18.184.251.153 on 2026-09-16.
		// Wise rotates its egress across the whole /16, so pinning single hosts
		// silently rejects real deliveries — and a rejection before the dump
		// existed left no local evidence at all, which is how the 2026-10-10
		// payment went missing. Widened to the /16s in use.
		// This is an INTERIM control: an attacker would need infrastructure inside
		// these AWS ranges. Retire it as soon as verification_key.pem is saved.
		'18.184.0.0/16', '18.185.0.0/16', '18.188.0.0/16', '18.197.0.0/16',
		// Extra ranges observed in production dumps (2026-10-10 and 2026-10-12):
		// Wise uses 219.74.0.0/16 repeatedly for the same subscription and
		// 52.58.0.0/16 (Hetzner / AWS edge) for at least one delivery. Without
		// these the source-IP allowlist will reject the very deliveries it is
		// supposed to admit, with no path for the operator to discover why
		// other than the rejected/ dump bucket.
		'52.58.0.0/16', '219.74.0.0/16',
		// Sandbox
		'18.199.110.249', '3.67.109.66', '3.78.113.13', '35.157.106.141', '54.93.137.122', '18.196.39.9');
		// Operator-defined extras, kept in the WISE_WEBHOOK_IPLIST const. Newline-
		// separated so a textarea in the setup page is the natural editor; the
		// hardcoded list above is a safety net, never a constraint. Format is
		// permissive: blanks and `# comments` are skipped, so an operator can
		// paste a copy of the rejected/ IPs as a starting point and tidy.
		$customList = (string) getDolGlobalString('WISE_WEBHOOK_IPLIST');
		if ($customList !== '') {
			foreach (preg_split('/\r\n|\r|\n/', $customList) as $line) {
				$line = trim((string) $line);
				if ($line === '' || strpos($line, '#') === 0) {
					continue;
				}
				// Single IP or CIDR only. ip2long rejects garbage, so the allowlist
				// stays free of typos that would silently match nothing.
				if (strpos($line, '/') === false) {
					if (ip2long($line) !== false) {
						$wiseAllowedSources[] = $line;
					}
				} else {
					list($net, $bits) = explode('/', $line, 2);
					if (ip2long($net) !== false && ctype_digit((string) $bits) && (int) $bits >= 0 && (int) $bits <= 32) {
						$wiseAllowedSources[] = $line;
					}
				}
			}
		}
		// Only REMOTE_ADDR is trusted: forwarded headers (CF-Connecting-IP,
	// X-Forwarded-For, ...) are client-controlled and would defeat the allowlist.
	$ipCandidates = array();
	if (!empty($_SERVER['REMOTE_ADDR'])) {
		$ipCandidates[] = trim($_SERVER['REMOTE_ADDR']);
	}
	$ipOk = false;
	foreach ($ipCandidates as $ip) {
		foreach ($wiseAllowedSources as $cidr) {
			if (strpos($cidr, '/') === false) {
				if ($ip === $cidr) {
					$ipOk = true;
					break 2;
				}
				continue;
			}
			list($net, $bits) = explode('/', $cidr);
			$netLong = ip2long($net);
			$ipLong = ip2long($ip);
			if ($netLong !== false && $ipLong !== false) {
				$mask = -1 << (32 - (int) $bits);
				$mask = $mask & 0xFFFFFFFF;
				if (($ipLong & $mask) === ($netLong & $mask)) {
					$ipOk = true;
					break 2;
				}
			}
		}
	}
	if (!$ipOk) {
		// Dump BEFORE responding: a rejection that leaves no trace is
		// indistinguishable from a delivery that never arrived.
		wiseWriteDump($raw, $headers, true, $entity, 'rejected');
		dol_syslog('wise_webhook rejected by IP allowlist ip='.implode(',', $ipCandidates), LOG_WARNING);
		wiseWebhookRespond(403, array('error' => 'Source not allowed'));
	}
}

// 3b. Verify RSA-SHA256 signature when the verification key is configured.
//    Without the key (bootstrap phase) accept and log — Wise must not see a
//    failure here or the subscription cannot be created at all.
if (!$bootstrap) {
	if ($signature === '') {
		dol_syslog('wise_webhook missing signature header', LOG_WARNING);
		wiseWebhookRespond(401, array('error' => 'Missing X-Signature-SHA256'));
	}
	$keyPem = file_get_contents($keyFile);
	$sigBin = base64_decode($signature, true);
	if ($sigBin === false || $sigBin === '' || $keyPem === false) {
		dol_syslog('wise_webhook bad signature encoding received_prefix='.substr($signature, 0, 12), LOG_WARNING);
		wiseWebhookRespond(401, array('error' => 'Bad signature encoding'));
	}
	$pub = openssl_pkey_get_public($keyPem);
	if ($pub === false) {
		// Tolerate a base64 DER key saved without PEM wrappers.
		$der = base64_decode(preg_replace('/\s+/', '', trim($keyPem)), true);
		if ($der !== false) {
			$pemBody = chunk_split(base64_encode($der), 64, "\n");
			$pub = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".$pemBody."-----END PUBLIC KEY-----\n");
		}
		if ($pub === false) {
			dol_syslog('wise_webhook invalid verification key file '.$keyFile, LOG_ERR);
			wiseWebhookRespond(500, array('error' => 'Invalid verification key'));
		}
	}
	// Signature is over the exact bytes Wise sent — never re-encode the body.
	if (openssl_verify($raw, $sigBin, $pub, OPENSSL_ALGO_SHA256) !== 1) {
		dol_syslog('wise_webhook bad signature received_prefix='.substr($signature, 0, 12), LOG_WARNING);
		wiseWebhookRespond(401, array('error' => 'Bad signature'));
	}
} else {
	// Bootstrap phase (no verification key yet). The endpoint is publicly
	// reachable, so the only two ways a delivery can be trusted enough to
	// ingest are:
	//
	//  1. The source IP is in the Wise egress allowlist (WISE_WEBHOOK_IPCHECK=1).
	//     This is the documented interim path for accounts whose subscription
	//     API is partner-gated and cannot return the public key. The delivery
	//     already passed step 3a, so ingest it (loudly flagged as unverified).
	//  2. The operator has explicitly disabled the WISE_INCOMING_ENFORCE_SIG
	//     safety switch and the delivery carries the standard Wise headers.
	//     This is a deliberate acceptance of "any HTTP caller can trigger
	//     a queue insert" and is off by default; it exists so the diagnostic
	//     self-test (no signature, no allowlisted source) can be made to land
	//     an event row to prove the ingest path actually executes on this host.
	//
	// When neither holds (the default state on a fresh install), the delivery
	// is acknowledged with 200 so the Wise subscription/URL validation keeps
	// working, but NOT ingested. In every case the delivery is dumped first.
	wiseWriteDump($raw, $headers, true, $entity, 'accepted');
	if ($ipCheckActive) {
		dol_syslog('wise_webhook UNVERIFIED delivery accepted via WISE_WEBHOOK_IPCHECK allowlist (no verification_key.pem). Save the subscription public key on the SLY Custom setup page (Wise tab) to re-enable signature verification.', LOG_WARNING);
	} elseif ((string) getDolGlobalString('WISE_INCOMING_ENFORCE_SIG') === '0') {
		dol_syslog('wise_webhook UNVERIFIED delivery accepted because WISE_INCOMING_ENFORCE_SIG=0 (no verification_key.pem). Disable this switch and save the subscription public key to re-enable signature verification.', LOG_WARNING);
	} else {
		dol_syslog('wise_webhook bootstrap: no verification key yet ('.$keyFile.'), delivery acknowledged but NOT processed. Either save the subscription public key on the SLY Custom setup page (Wise tab), or set WISE_WEBHOOK_IPCHECK=1 to accept deliveries from the Wise egress networks.', LOG_WARNING);
		wiseWebhookRespond(200, array('received' => true, 'note' => 'bootstrap: no verification key, delivery acknowledged but not processed'));
	}
}

// 4. Diagnostic dump for verified deliveries. Bootstrap-phase deliveries were
//    already dumped above (truncated to 2 KB). Full body capped at 64 KB.
if (!$bootstrap) {
	wiseWriteDump($raw, $headers, false, $entity, 'accepted');
}

// 5. Empty / non-JSON bodies: ack 200 so Wise does not retry a permanent
//    condition; the dump in step 4 keeps the evidence for diagnosis.
if ($raw === '') {
	dol_syslog('wise_webhook empty body accepted (likely URL validation)', LOG_INFO);
	wiseWebhookRespond(200, array('received' => true, 'note' => 'empty body'));
}
$body = json_decode($raw, true);
if (!is_array($body)) {
	dol_syslog('wise_webhook non-JSON body length='.strlen($raw), LOG_WARNING);
	wiseWebhookRespond(200, array('received' => true, 'note' => 'non-JSON body'));
}

// 6. Ingest: dedupe on raw-body md5, store the event, queue credits.
//    DB failures return 500 so Wise retries the delivery later.
$result = WiseIncomingPayment::createFromWebhook($db, $entity, $body, $raw, array(
	'delivery_id' => $deliveryId,
	'is_test' => $isTestHeader,
	// Bootstrap deliveries (no verification_key.pem) are still trusted when
	// step 3a accepted the source; tag the row so reconcile page can warn.
	'unverified' => $bootstrap,
));
if ($result['error'] !== null) {
	dol_syslog('wise_webhook ingest error: '.$result['error'], LOG_ERR);
	wiseWebhookRespond(500, array('error' => 'Ingest failed'));
}
if ($result['duplicate']) {
	wiseWebhookRespond(200, array('received' => true, 'duplicate' => true, 'event' => isset($body['event_type']) ? $body['event_type'] : ''));
}

dol_syslog(
	'wise_webhook ingested event='.(isset($body['event_type']) ? $body['event_type'] : '')
	.' id='.$result['event_id'].' queued='.(empty($result['incoming_id']) ? 'no' : $result['incoming_id'])
	.' test='.($isTestHeader ? 1 : 0),
	LOG_INFO
);
wiseWebhookRespond(200, array(
	'received' => true,
	'event' => isset($body['event_type']) ? $body['event_type'] : '',
	'queued' => !empty($result['incoming_id']),
));
