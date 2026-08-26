<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/slycustom/webhook/shipsgo.php
 * \ingroup slycustom
 * \brief   ShipsGo v2 webhook receiver.
 *
 * Public endpoint: NO login, NO CSRF check, NO IP restrict.
 * URL: /custom/slycustom/webhook/shipsgo.php?entity=<N>
 *
 * Authentication: ShipsGo signs each delivery with HMAC-SHA256 using the
 * per-entity secret stored in SHIPSGO_WEBHOOK_SECRET. The signature appears
 * in the X-Shipsgo-Webhook-Signature header.
 *
 * Accepted event: OCEAN.SHIPMENTS.SHIPMENT_UPDATED.
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
	define('USESUFFIXINLOG', '_shipsgo_webhook');
}

// Multicompany: resolve entity BEFORE main.inc.php so $conf->entity is set correctly.
$entity = 0;
if (!empty($_GET['entity'])) {
	$entity = (int) $_GET['entity'];
} elseif (!empty($_POST['entity'])) {
	$entity = (int) $_POST['entity'];
}
$entityExplicit = $entity >= 1;
if ($entity < 1) {
	$entity = 1;
}
if (is_numeric($entity)) {
	if (!defined('DOLENTITY')) {
		define('DOLENTITY', $entity);
	}
}

require_once __DIR__.'/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_Update.class.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

/**
 * Send a JSON response and stop.
 *
 * @param int    $httpCode HTTP status code
 * @param array  $payload  Response body
 * @return void
 */
function slyWebhookRespond($httpCode, array $payload)
{
	http_response_code($httpCode);
	echo json_encode($payload);
	if (function_exists('dol_syslog')) {
		dol_syslog('shipsgo_webhook respond http='.$httpCode.' '.json_encode($payload), LOG_INFO);
	}
	exit;
}

if (!is_object($db) || !is_object($conf)) {
	slyWebhookRespond(500, array('error' => 'Dolibarr context not loaded'));
}

if (!function_exists('getDolGlobalString')) {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
}

// Multicompany safety: if enabled, refuse requests without explicit entity parameter.
if (isModEnabled('multicompany') && !$entityExplicit) {
	dol_syslog('shipsgo_webhook refused: multicompany enabled but entity param missing', LOG_WARNING);
	slyWebhookRespond(400, array('error' => 'Entity parameter required when multicompany is enabled'));
}

// 1. Read raw body once (signature must be over the exact bytes ShipsGo signed).
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
	slyWebhookRespond(400, array('error' => 'Empty body'));
}

// 2. Read signature header (case-insensitive lookup).
$signature = '';
foreach ($_SERVER as $k => $v) {
	if (strcasecmp($k, 'HTTP_X_SHIPSGO_WEBHOOK_SIGNATURE') === 0) {
		$signature = $v;
		break;
	}
}
$signature = trim($signature);
// Tolerate "sha256=<hex>" prefix convention (GitHub-style) in addition to bare hex.
if (strpos($signature, 'sha256=') === 0) {
	$signature = substr($signature, strlen('sha256='));
}
if ($signature === '') {
	dol_syslog('shipsgo_webhook missing signature header', LOG_WARNING);
	slyWebhookRespond(401, array('error' => 'Missing X-Shipsgo-Webhook-Signature'));
}

// 3. Verify HMAC using per-entity secret. Refuse if no secret configured.
$secret = ShipmentStatus::getSecretKeyForEntity($db, $entity);
if ($secret === '') {
	dol_syslog('shipsgo_webhook no secret configured for entity='.$entity, LOG_WARNING);
	slyWebhookRespond(401, array('error' => 'No webhook secret for entity'));
}

$expected = hash_hmac('sha256', $raw, $secret);
if (!hash_equals($expected, $signature)) {
	// Log the received prefix (truncated) to diagnose real-provider signature formats without leaking ours.
	dol_syslog('shipsgo_webhook bad signature for entity='.$entity.' received_prefix='.substr($signature, 0, 12), LOG_WARNING);
	slyWebhookRespond(401, array('error' => 'Bad signature'));
}

// 4. Persist the verified raw payload (audit + schema diagnostics).
//    One file per delivery inside DOL_DATA_ROOT (outside webroot, not web-accessible).
//    Only saved AFTER signature verification — unauthenticated callers must not be able to fill the disk.
//    Filename hash suffix makes re-deliveries of an identical payload visible at a glance.
$payloadDir = DOL_DATA_ROOT.'/shipsgo_webhook';
if (!is_dir($payloadDir)) {
	@mkdir($payloadDir, 0755, true);
}
$payloadFile = $payloadDir.'/'.date('Ymd-His').'-e'.$entity.'-'.substr(md5($raw), 0, 8).'.json';
if (@file_put_contents($payloadFile, $raw) === false) {
	// Saving is diagnostic only — a failure must not break delivery processing.
	dol_syslog('shipsgo_webhook failed to save payload file '.$payloadFile, LOG_WARNING);
}

// 5. Parse JSON.
$body = json_decode($raw, true);
if (!is_array($body)) {
	slyWebhookRespond(400, array('error' => 'Invalid JSON'));
}

// 6. Event filter. Accept only ocean SHIPMENT_UPDATED; ack others so ShipsGo does not retry.
// Real deliveries carry "event" as a structured value (observed: array/object, not a plain
// string — casting caused "Array to string conversion"). Extract candidate names from any
// shape; compare normalized (dots and underscores both tolerated).
$eventRaw = isset($body['event']) ? $body['event'] : null;
$eventNames = array();
if (is_string($eventRaw)) {
	$eventNames[] = $eventRaw;
} elseif (is_array($eventRaw)) {
	// Object form {"name": "..."} / {"type": "..."}; list form ["NAME", ...].
	if (isset($eventRaw['name']) && is_string($eventRaw['name'])) {
		$eventNames[] = $eventRaw['name'];
	}
	if (isset($eventRaw['type']) && is_string($eventRaw['type'])) {
		$eventNames[] = $eventRaw['type'];
	}
	foreach ($eventRaw as $v) {
		if (is_string($v) && $v !== '') {
			$eventNames[] = $v;
		}
	}
}
$event = implode(',', array_unique($eventNames));
$eventAccepted = false;
foreach ($eventNames as $en) {
	if (strtoupper(preg_replace('/[._]/', '', $en)) === 'OCEANSHIPMENTSSHIPMENTUPDATED') {
		$eventAccepted = true;
		break;
	}
}
if (!$eventAccepted) {
	// Log the raw event value and top-level keys (payload is already signature-verified and
	// non-sensitive) so real-provider formats can be adapted from the log instead of guessing.
	dol_syslog('shipsgo_webhook ignored event entity='.$entity.' keys='.implode(',', array_keys($body)).' event_raw='.json_encode($eventRaw), LOG_INFO);
	slyWebhookRespond(200, array('received' => true, 'ignored' => true, 'event' => $event));
}

// 7. Locate shipment payload. Tolerate {shipment:...}, {data:{shipment:...}}, {data:{...}},
// {event:{shipment:...}} or a flat object — first candidate carrying a tracking-ish field wins.
$ship = null;
foreach (array(
	isset($body['shipment']) ? $body['shipment'] : null,
	isset($body['data']['shipment']) ? $body['data']['shipment'] : null,
	isset($body['data']) && is_array($body['data']) ? $body['data'] : null,
	is_array($eventRaw) && isset($eventRaw['shipment']) ? $eventRaw['shipment'] : null,
	$body,
) as $cand) {
	if (is_array($cand) && (isset($cand['booking_number']) || isset($cand['container_number']) || isset($cand['id']))) {
		$ship = $cand;
		break;
	}
}
if ($ship === null) {
	// Full raw body (truncated) so the real schema is captured for mapping on the next delivery.
	dol_syslog('shipsgo_webhook shipment object not found entity='.$entity.' keys='.implode(',', array_keys($body)).' raw='.substr($raw, 0, 2000), LOG_WARNING);
	slyWebhookRespond(400, array('error' => 'Missing shipment object'));
}

// 8-9. Match the shipment against expedition.tracking_number by trying every reference
// the payload carries. Dolibarr tracking_number values are typically container numbers
// (the cron path queries them via getContainerInfo), while deliveries also carry
// booking_number — a single fixed candidate would miss. First candidate with matching
// rows wins; strict entity match prevents cross-company writes.
$trackingCandidates = array();
foreach (array('booking_number', 'container_number', 'id') as $field) {
	if (isset($ship[$field]) && (is_string($ship[$field]) || is_numeric($ship[$field]))) {
		$cand = trim((string) $ship[$field]);
		if ($cand !== '' && !in_array($cand, $trackingCandidates, true)) {
			$trackingCandidates[] = $cand;
		}
	}
}
if (empty($trackingCandidates)) {
	slyWebhookRespond(422, array('error' => 'Missing tracking reference'));
}

$expeditionIds = array();
$tracking = '';
foreach ($trackingCandidates as $cand) {
	$sql = 'SELECT a.rowid FROM '.MAIN_DB_PREFIX.'expedition AS a';
	$sql .= ' WHERE a.entity = '.((int) $entity);
	$sql .= " AND a.tracking_number = '".$db->escape($cand)."'";
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog('shipsgo_webhook DB error entity='.$entity.' ref='.$cand.': '.$db->lasterror(), LOG_ERR);
		slyWebhookRespond(500, array('error' => 'DB error'));
	}
	while ($row = $db->fetch_object($resql)) {
		$expeditionIds[] = (int) $row->rowid;
	}
	$db->free($resql);
	if (!empty($expeditionIds)) {
		$tracking = $cand;
		break;
	}
}

if (empty($expeditionIds)) {
	// Ack with 200: not_found is permanent for this delivery, a retry would never succeed.
	dol_syslog('shipsgo_webhook no expedition entity='.$entity.' tried='.implode(',', $trackingCandidates), LOG_INFO);
	slyWebhookRespond(200, array('received' => true, 'updated' => 0, 'reason' => 'not_found', 'tried' => $trackingCandidates));
}

// 10. Normalize payload and apply to each matching expedition.
$updater = new ShipmentStatus($db);
$normalized = $updater->normalizeWebhookPayload($ship);

$updated = 0;
$failed = 0;
foreach ($expeditionIds as $expid) {
	if ($updater->applyShipsGoStatusToExtrafields($expid, $normalized)) {
		$updated++;
	} else {
		$failed++;
	}
}

dol_syslog('shipsgo_webhook event='.$event.' entity='.$entity.' ref='.$tracking.' updated='.$updated.' failed='.$failed, LOG_INFO);
slyWebhookRespond(200, array(
	'received' => true,
	'event' => $event,
	'entity' => $entity,
	'tracking' => $tracking,
	'matched' => count($expeditionIds),
	'updated' => $updated,
	'failed' => $failed,
));
