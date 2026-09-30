<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/ai/ClaudeProvider.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Anthropic Claude (Messages API) provider — used when the ai
 *	             module is not available or the operator wants to bypass it.
 *
 *             Reads EMBEDDEDBOOKKEEPING_ANTHROPIC_KEY + EMBEDDEDBOOKKEEPING_AI_CLAUDE_MODEL.
 *             Uses core getURLContent() for the HTTPS POST so all network
 *             timeouts / proxy settings from Dolibarr apply.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiSuggester.interface.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiLine.class.php';

if (!class_exists('ClaudeProvider', false)) {

class ClaudeProvider implements EBKAiSuggester
{
	/** @var string Anthropic API base URL */
	const API_URL = 'https://api.anthropic.com/v1/messages';

	/** @var string Anthropic API version header */
	const API_VERSION = '2023-06-01';

	/** @var int Default max_tokens for bookkeeping suggestions */
	const DEFAULT_MAX_TOKENS = 1024;

	/**
	 * @param EBKEntryProposal $seed
	 * @param CommonObject     $object
	 * @return array<int,EBKAiLine>
	 */
	public function suggest(EBKEntryProposal $seed, $object)
	{
		global $conf, $langs, $db;

		$key = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_ANTHROPIC_KEY');
		if ($key === '') {
			dol_syslog(get_class()."::suggest anthropic key missing", LOG_WARNING);
			return array();
		}
		$model = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CLAUDE_MODEL', 'claude-sonnet-4-5');
		if ($model === '') {
			$model = 'claude-sonnet-4-5';
		}

		// Build the same JSON payload the AiModuleProvider ships, then wrap it
		// into the Anthropic Messages format. We deliberately reuse no
		// third-party data — only the public invoice header.
		$invoicePayload = $this->invoicePayload($object);
		$systemPrompt   = $this->resolveSystemPrompt($langs);
		$userBlock      = "INVOICE_JSON:\n".$invoicePayload."\n\nReturn JSON only.";

		$body = array(
			'model'       => $model,
			'max_tokens'  => self::DEFAULT_MAX_TOKENS,
			'temperature' => 0.2,
			'system'      => $systemPrompt,
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $userBlock,
				),
			),
		);

		$bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($bodyJson === false) {
			return array();
		}

		$headers = array(
			'Content-Type: application/json',
			'x-api-key: '.$key,
			'anthropic-version: '.self::API_VERSION,
		);

		// Use Dolibarr's getURLContent() so the same proxy / timeout settings apply.
		if (!function_exists('getURLContent')) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
		}

		$resp = getURLContent(self::API_URL, 'POST', $bodyJson, $headers, array('http', 'https'), 0, -1, 2, 0, 'application/json', '', '', 0, 0);
		if (!is_array($resp) || !empty($resp['curl_error_no'])) {
			dol_syslog(get_class()."::suggest curl_error=".(is_array($resp) ? ($resp['curl_error'] ?? 'unknown') : 'unknown'), LOG_WARNING);
			return array();
		}
		$httpCode = isset($resp['http_code']) ? (int) $resp['http_code'] : 0;
		if ($httpCode < 200 || $httpCode >= 300) {
			dol_syslog(get_class()."::suggest http_code=".$httpCode, LOG_WARNING);
			return array();
		}
		$payload = isset($resp['content']) ? (string) $resp['content'] : '';
		if ($payload === '') {
			return array();
		}

		return $this->parseAnthropicResponse($payload);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Build the same compact JSON we ship to AiModuleProvider — the schema is
	 * intentionally stable so swapping providers requires zero prompt changes.
	 *
	 * @param CommonObject $object
	 * @return string JSON (defensive-capped to 8 KiB)
	 */
	private function invoicePayload($object)
	{
		$out = array(
			'side'             => property_exists($object, 'element') ? ($object->element === 'facture' ? 'customer_invoice' : 'supplier_invoice') : 'unknown',
			'ref'              => isset($object->ref) ? (string) $object->ref : '',
			'date'             => isset($object->date) ? (int) $object->date : 0,
			'total_ht'         => isset($object->total_ht) ? (float) $object->total_ht : 0.0,
			'total_ttc'        => isset($object->total_ttc) ? (float) $object->total_ttc : 0.0,
			'total_vat'        => isset($object->total_tva) ? (float) $object->total_tva : 0.0,
			'currency'         => isset($object->multicurrency_code) ? (string) $object->multicurrency_code : '',
			'multicurrency_total_ttc' => isset($object->multicurrency_total_ttc) ? (float) $object->multicurrency_total_ttc : null,
			'thirdparty_name'  => isset($object->thirdparty) && is_object($object->thirdparty) ? (string) $object->thirdparty->name : '',
			'lines'            => array(),
		);
		if (!empty($object->lines) && is_array($object->lines)) {
			foreach ($object->lines as $ln) {
				$out['lines'][] = array(
					'description' => isset($ln->desc) ? (string) $ln->desc : (isset($ln->libelle) ? (string) $ln->libelle : ''),
					'qty'         => isset($ln->qty) ? (float) $ln->qty : 1.0,
					'subprice'    => isset($ln->subprice) ? (float) $ln->subprice : 0.0,
					'total_ht'    => isset($ln->total_ht) ? (float) $ln->total_ht : 0.0,
				);
			}
		}
		$json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			return '{}';
		}
		if (strlen($json) > 8192) {
			$json = substr($json, 0, 8192).'…(truncated)';
		}
		return $json;
	}

	/**
	 * Resolve the system prompt per locale (overrideable).
	 *
	 * @param Translate $langs
	 * @return string
	 */
	private function resolveSystemPrompt($langs)
	{
		$locale = is_object($langs) ? strtoupper(substr((string) $langs->defaultlang, 0, 2)) : 'EN';
		if ($locale === 'ZH') {
			$override = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_SYSTEM_PROMPT_ZH');
			if ($override !== '') {
				return $override;
			}
			return "你是一名资深会计。基于一份 Dolibarr 发票（JSON），建议一组借贷平衡的会计分录。严格只输出 JSON，形如 {\"lines\":[{\"debit_account\":\"<num>\",\"debit_label\":\"<label>\",\"credit_account\":\"<num>\",\"credit_label\":\"<label>\",\"amount\":\"<含税总额>\",\"currency\":\"<ISO-4217>\",\"label_operation\":\"<短描述>\",\"rationale\":\"<1-2句>\",\"confidence\":0.0}]}。confidence ∈ [0,1]。不要输出 JSON 之外的任何文字。";
		}
		$override = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_SYSTEM_PROMPT_EN');
		if ($override !== '') {
			return $override;
		}
		return "You are an expert accountant. Given one Dolibarr invoice as a JSON document, propose ONE balanced journal entry (debit one account, credit another) using only account_number values present in the provided chart of accounts. Output STRICT JSON of the form {\"lines\":[{\"debit_account\":\"<num>\",\"debit_label\":\"<label>\",\"credit_account\":\"<num>\",\"credit_label\":\"<label>\",\"amount\":\"<ttc-amount as string>\",\"currency\":\"<ISO-4217>\",\"label_operation\":\"<short>\",\"rationale\":\"<1-2 sentences>\",\"confidence\":0.0}]}. Confidence is in [0,1]. Do not add commentary or prose.";
	}

	/**
	 * Anthropic returns {content: [{type:"text", text:"<json>"}], …}. We accept either
	 * the raw JSON directly inside content[0].text, or markdown-fenced JSON inside it.
	 *
	 * @param string $payload
	 * @return array<int,EBKAiLine>
	 */
	private function parseAnthropicResponse($payload)
	{
		$out = array();
		$decoded = json_decode($payload, true);
		if (!is_array($decoded) || empty($decoded['content']) || !is_array($decoded['content'])) {
			return $out;
		}
		$text = '';
		foreach ($decoded['content'] as $block) {
			if (isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
				$text .= (string) $block['text'];
			}
		}
		if ($text === '') {
			return $out;
		}

		// Strip code fences.
		$stripped = trim($text);
		$stripped = preg_replace('/^```(?:json)?/i', '', $stripped);
		$stripped = preg_replace('/```\s*$/i', '', $stripped);
		$stripped = trim($stripped);

		$start = strpos($stripped, '{');
		if ($start === false) return $out;
		$depth = 0;
		$end = -1;
		$len = strlen($stripped);
		for ($i = $start; $i < $len; $i++) {
			$ch = $stripped[$i];
			if ($ch === '{') $depth++;
			elseif ($ch === '}') {
				$depth--;
				if ($depth === 0) { $end = $i; break; }
			}
		}
		if ($end === -1) return $out;
		$json = substr($stripped, $start, $end - $start + 1);
		$obj = json_decode($json, true);
		if (!is_array($obj) || empty($obj['lines']) || !is_array($obj['lines'])) return $out;

		foreach ($obj['lines'] as $ln) {
			if (!is_array($ln)) continue;
			$line = new EBKAiLine();
			$line->debit_account   = isset($ln['debit_account'])  ? (string) $ln['debit_account']  : '';
			$line->debit_label     = isset($ln['debit_label'])    ? (string) $ln['debit_label']    : '';
			$line->credit_account  = isset($ln['credit_account']) ? (string) $ln['credit_account'] : '';
			$line->credit_label    = isset($ln['credit_label'])   ? (string) $ln['credit_label']   : '';
			$line->amount          = isset($ln['amount'])         ? (float)  $ln['amount']         : 0.0;
			$line->currency        = isset($ln['currency'])       ? (string) $ln['currency']       : '';
			$line->label_operation = isset($ln['label_operation'])? (string) $ln['label_operation']: '';
			$line->confidence      = isset($ln['confidence'])     ? (float)  $ln['confidence']     : null;
			$line->rationale       = isset($ln['rationale'])      ? (string) $ln['rationale']      : null;
			$line->provider_id     = 'claude';

			if ($line->debit_account === '' || $line->credit_account === '') continue;
			if ($line->amount <= 0) continue;
			$out[] = $line;
		}
		return $out;
	}
}

} // if (!class_exists('ClaudeProvider', false))
