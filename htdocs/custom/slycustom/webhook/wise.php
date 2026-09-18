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
 * Until that key file exists (bootstrap phase, before the first subscription
 * is created) deliveries are accepted and logged with a warning.
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

// 3a. Optional source-IP allowlist (Wise egress networks) — hardening used
//     when the signature public key is not retrievable (business Developer
//     tools accounts expose no subscription API). Enable via WISE_WEBHOOK_IPCHECK=1.
//     Production CIDRs per docs.wise.com webhooks guide; sandbox IPs included.
if ((string) getDolGlobalString('WISE_WEBHOOK_IPCHECK') === '1') {
	$wiseAllowedSources = array(
		// Current documented egress ranges
		'45.129.54.0/24', '45.129.55.0/24',
		// Legacy AWS egress still in use: the 2026-09-16 test delivery came
		// from 18.184.251.153 (dump evidence), so these MUST stay allowed.
		'18.184.251.153', '18.185.120.233', '18.188.246.233', '18.197.14.100',
		// Sandbox
		'18.199.110.249', '3.67.109.66', '3.78.113.13', '35.157.106.141', '54.93.137.122', '18.196.39.9');
	$ipCandidates = array();
	if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
		$ipCandidates[] = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
	}
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
		dol_syslog('wise_webhook rejected by IP allowlist ip='.implode(',', $ipCandidates), LOG_WARNING);
		wiseWebhookRespond(403, array('error' => 'Source not allowed'));
	}
}

// 3b. Verify RSA-SHA256 signature when the verification key is configured.
//    Without the key (bootstrap phase) accept and log — Wise must not see a
//    failure here or the subscription cannot be created at all.
$keyFile = DOL_DATA_ROOT.'/wise_webhook/verification_key.pem';
$bootstrap = !is_readable($keyFile);
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
	dol_syslog('wise_webhook bootstrap: no verification key yet, accepting unsigned delivery', LOG_WARNING);
}

// 4. Persist a diagnostic dump (headers + raw body) inside DOL_DATA_ROOT,
//    outside the webroot. Bounded to 64 KB per delivery.
$payloadDir = DOL_DATA_ROOT.'/wise_webhook';
if (!is_dir($payloadDir)) {
	@mkdir($payloadDir, 0755, true);
}
if (strlen($raw) <= 65536) {
	$dump = array(
		'received_at' => dol_now(),
		'entity' => $entity,
		'bootstrap' => $bootstrap,
		'remote_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
		'headers' => $headers,
		'body' => $raw,
	);
	$payloadFile = $payloadDir.'/'.date('Ymd-His').'-e'.$entity.'-'.substr(md5($raw.$signature), 0, 8).'.json';
	if (@file_put_contents($payloadFile, json_encode($dump, JSON_PRETTY_PRINT)) === false) {
		dol_syslog('wise_webhook failed to save payload file '.$payloadFile, LOG_WARNING);
	}
} else {
	dol_syslog('wise_webhook body over 64 KB ('.strlen($raw).' bytes), diagnostic dump skipped', LOG_WARNING);
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
