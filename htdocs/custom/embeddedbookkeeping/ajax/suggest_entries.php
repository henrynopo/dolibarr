<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/ajax/suggest_entries.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      AJAX endpoint: load one invoice, ask the active AI provider for
 *	             suggested bookkeeping lines, return JSON.
 *
 *             Security:
 *               - require main.inc.php (login required by default; this is a Dolibarr convention).
 *               - validates a fresh token (newToken()).
 *               - whitelists doc side ('customer' | 'supplier') and forces fk_doc to int.
 *               - returns strict JSON; never echoes user input verbatim.
 *
 *             Compatibility: Dolibarr 14.0 .. 22.0.x. Uses GETPOST, isModEnabled,
 *             getDolGlobalString — all stable across the supported range.
 */

// --- Dolibarr bootstrap ---------------------------------------------------
$res = 0;
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res && file_exists('../../../../main.inc.php')) {
	$res = @include '../../../../main.inc.php';
}
if (!$res) {
	header('HTTP/1.1 500 Internal Server Error');
	header('Content-Type: application/json');
	echo json_encode(array('ok' => false, 'warning' => 'main_include_failed'));
	exit;
}

// Hard requirement: user must be logged in. conf->entity must exist (set by main.inc.php).
if (empty($user) || empty($user->id)) {
	header('HTTP/1.1 401 Unauthorized');
	header('Content-Type: application/json');
	echo json_encode(array('ok' => false, 'warning' => 'not_logged_in'));
	exit;
}

global $conf, $langs, $db, $user;

// --- Input parsing + validation -----------------------------------------
$rawBody = file_get_contents('php://input');
if (!is_string($rawBody)) {
	$rawBody = '';
}
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
	// Fallback: read from $_POST for old clients
	$payload = $_POST;
}

$side    = isset($payload['side']) ? (string) $payload['side'] : '';
$invoiceId = isset($payload['id']) ? (int) $payload['id'] : 0;
$token   = isset($payload['token']) ? (string) $payload['token'] : (string) GETPOST('token', 'none');

if ($side !== 'customer' && $side !== 'supplier' && $side !== 'expense') {
	header('HTTP/1.1 400 Bad Request');
	header('Content-Type: application/json');
	echo json_encode(array('ok' => false, 'warning' => 'bad_side'));
	exit;
}
if ($invoiceId <= 0) {
	header('HTTP/1.1 400 Bad Request');
	header('Content-Type: application/json');
	echo json_encode(array('ok' => false, 'warning' => 'bad_id'));
	exit;
}

// Token check.
if ($token === '' || $token !== newToken()) {
	header('HTTP/1.1 403 Forbidden');
	header('Content-Type: application/json');
	echo json_encode(array('ok' => false, 'warning' => 'bad_token'));
	exit;
}

// Module + permission gates.
if (!isModEnabled('embeddedbookkeeping')) {
	header('HTTP/1.1 503 Service Unavailable');
	header('Content-Type: application/json');
	echo json_encode(array('ok' => false, 'warning' => 'module_disabled'));
	exit;
}
if (empty($user->rights->embeddedbookkeeping->ai->suggest) && empty($user->admin)) {
	header('HTTP/1.1 403 Forbidden');
	header('Content-Type: application/json');
	echo json_encode(array('ok' => false, 'warning' => 'no_ai_permission'));
	exit;
}

$langs->loadLangs(array('embeddedbookkeeping@embeddedbookkeeping', 'compta'));

// --- Load the invoice ---------------------------------------------------
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/expensereport/class/expensereport.class.php';

if ($side === 'customer') {
	$invoice = new Facture($db);
} elseif ($side === 'supplier') {
	$invoice = new FactureFournisseur($db);
} else {
	$invoice = new ExpenseReport($db);
}
if ($invoice->fetch((int) $invoiceId) <= 0) {
	header('HTTP/1.1 404 Not Found');
	header('Content-Type: application/json');
	echo json_encode(array('ok' => false, 'warning' => 'invoice_not_found'));
	exit;
}

// Lazy-load third-party so the AI sees the company name (no sensitive data).
if ($side !== 'expense' && method_exists($invoice, 'fetch_thirdparty')) {
	$invoice->fetch_thirdparty();
} elseif ($side !== 'expense' && empty($invoice->thirdparty) && method_exists($invoice, 'getThirdparty')) {
	$invoice->getThirdparty();
}

// Already-posted guard — refuse to suggest if bookkeeping already exists.
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKBookkeepingAlreadyDone.class.php';
$docType = ($side === 'customer') ? 'customer_invoice' : (($side === 'supplier') ? 'supplier_invoice' : 'expense_report');
if (EBKBookkeepingAlreadyDone::existsFor($db, $docType, (int) $invoiceId, (int) ($conf->entity ?? 1))) {
	header('HTTP/1.1 409 Conflict');
	header('Content-Type: application/json');
	echo json_encode(array('ok' => false, 'warning' => 'already_posted'));
	exit;
}

// --- Resolve provider and ask for suggestions ---------------------------
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKEntryProposal.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiProviderFactory.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/NullProvider.class.php';

$provider = EBKAiProviderFactory::resolve();

$seed = new EBKEntryProposal();
$seed->doc_type   = $docType;
$seed->fk_doc     = (int) $invoiceId;
$seed->doc_ref    = isset($invoice->ref) ? (string) $invoice->ref : '';
$seed->amount     = isset($invoice->total_ttc) ? (float) $invoice->total_ttc : 0.0;
$seed->currency   = isset($invoice->multicurrency_code) ? (string) $invoice->multicurrency_code : '';

$lines = $provider->suggest($seed, $invoice);

// --- Build safe JSON response -------------------------------------------
$out = array(
	'ok'      => true,
	'lines'   => array(),
	'warning' => '',
	'debug'   => null,
);

// Convert EBKAiLine → assoc array; cap to MAX_LINES.
$maxLines = (int) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_MAX_LINES', 1);
if ($maxLines < 1) $maxLines = 1;
if ($maxLines > 10) $maxLines = 10;

$count = 0;
foreach ($lines as $ln) {
	if ($count >= $maxLines) break;
	$out['lines'][] = array(
		'debit_account'   => (string) $ln->debit_account,
		'debit_label'     => (string) $ln->debit_label,
		'credit_account'  => (string) $ln->credit_account,
		'credit_label'    => (string) $ln->credit_label,
		'amount'          => (float)  $ln->amount,
		'currency'        => (string) $ln->currency,
		'label_operation' => (string) $ln->label_operation,
		'confidence'      => $ln->confidence,
		'provider_id'     => (string) $ln->provider_id,
		'provider_label'  => (string) $ln->provider_id,
	);
	$count++;
}

if (empty($out['lines'])) {
	if ($provider instanceof NullProvider) {
		$out['warning'] = (string) $provider->getReason();
		$out['ok'] = false;
	} else {
		$out['warning'] = 'no_suggestions';
		$out['ok'] = false;
	}
}

// Optional debug payload (admin-only, gated by EMBEDDEDBOOKKEEPING_AI_DEBUG).
if (!empty($conf->global->EMBEDDEDBOOKKEEPING_AI_DEBUG) && !empty($user->admin)) {
	$out['debug'] = array(
		'provider'   => method_exists($provider, 'getReason') ? $provider->getReason() : '',
		'side'       => $side,
		'id'         => $invoiceId,
		'server_ts'  => time(),
	);
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
exit;
