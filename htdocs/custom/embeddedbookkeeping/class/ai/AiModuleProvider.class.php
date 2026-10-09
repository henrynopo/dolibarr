<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/ai/AiModuleProvider.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Default provider: reuses the Dolibarr system AI module's
 *	             configured upstream LLM (chatgpt / groq / mistral / anthropic /
 *	             google / custom OpenAI-compatible) for a balanced journal
 *	             entry suggestion. An alternative 'ebk_custom' provider lets
 *	             the admin configure a fully independent LLM (different
 *	             service / key / URL / model) without touching the system
 *	             AI module's configuration at all.
 *
 *             Design (per project CLAUDE.md §1 "独立与完整性 / 卸载不影响核心"):
 *             - **TWO PROVIDERS** are selectable from
 *               `EMBEDDEDBOOKKEEPING_AI_PROVIDER`:
 *                 * `ai_module`  — BORROW credentials (service, API key,
 *                   endpoint, model) from the system AI module's own setup
 *                   (`AI_API_SERVICE`, `AI_API_<SERVICE>_KEY`,
 *                   `AI_API_<SERVICE>_URL`, `AI_API_<SERVICE>_MODEL_TEXT`).
 *                   The admin configures these once in `ai/admin/setup.php`.
 *                 * `ebk_custom` — USE EBK's own credentials stored under
 *                   `EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE` / `_KEY` / `_URL`
 *                   / `_MODEL`. Fully independent of the system AI module;
 *                   we never read or write its constants in this mode.
 *             - **PROMPT** is fully owned by EBK in both modes, stored in
 *               this module's own constants `EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE`
 *               / `_PROMPT_POST`. The system AI module's
 *               `AI_CONFIGURATIONS_PROMPT` JSON is NEVER written by EBK and
 *               the bookkeeping-suggest key is NEVER added to it. Two modules
 *               serve different business purposes, so their prompts MUST NOT
 *               pollute each other's namespace.
 *             - **HTTP dispatch** calls `UniversalLLMAdapter` (the same class
 *               the system AI module uses internally) directly with our own
 *               pre+user prompt. We never go through `Ai::generateContent()`
 *               because that path forces the prompt through the
 *               `AI_CONFIGURATIONS_PROMPT[$function]` JSON dispatcher, which
 *               is exactly the cross-module leakage we are avoiding.
 *             - Built-in EN/ZH default prompt is hard-coded in
 *               `getDefaultBookkeepingPrompt()` and used at runtime when the
 *               EBK constants are blank (admin cleared both textareas). This
 *               is local in-memory fallback, NOT a write to the system AI
 *               module's JSON.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiSuggester.interface.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiLine.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKAccountLookup.class.php';
// UniversalLLMAdapter is the system AI module's internal HTTP protocol
// dispatcher (OpenAI / Anthropic / Google). We require it lazily below —
// the file exists whenever the ai module is shipped (Dolibarr 17+), but
// we don't want to require ai.module be enabled at module-load time.

if (!class_exists('AiModuleProvider', false)) {

class AiModuleProvider implements EBKAiSuggester
{
	/**
	 * Suggest a balanced journal entry via the system AI module's
	 * configured LLM.
	 *
	 * @param EBKEntryProposal $seed
	 * @param CommonObject     $object
	 * @return array<int,EBKAiLine>
	 */
	public function suggest(EBKEntryProposal $seed, $object)
	{
		global $conf, $langs, $db;

		// Short-circuit when admin picked 'disabled'.
		$provider = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_PROVIDER', 'ai_module');
		if ($provider === 'disabled') {
			return array();
		}

		// 'ai_module' provider requires the system AI module to be enabled
		// (we borrow its credentials). 'ebk_custom' is self-contained and
		// works even when the system AI module is not present in the
		// deployment.
		if ($provider === 'ai_module' && !isModEnabled('ai')) {
			dol_syslog(get_class()."::suggest ai_module selected but system AI module not enabled", LOG_DEBUG);
			return array();
		}

		// Resolve credentials + instantiate the HTTP adapter. Two paths:
		//   - 'ai_module'  → BORROW service/key/url/model from the system AI
		//                   module's own AI_API_* configuration.
		//   - 'ebk_custom' → USE EBK's own EMBEDDEDBOOKKEEPING_AI_CUSTOM_*
		//                   constants. Fully independent of the system AI
		//                   module.
		$adapter = self::resolveAdapter($conf, $provider);
		if (!$adapter) {
			dol_syslog(get_class()."::suggest adapter not resolvable (key/endpoint missing for provider=".$provider.")", LOG_DEBUG);
			return array();
		}

		// Build the user-side data: invoice JSON + chart of accounts + recent
		// bookkeeping rows for the same third-party.
		$invoiceJson = $this->invoiceAsJson($object);
		$chartLines = $this->chartAsText($db, $conf);
		$recentRows = $this->recentRowsForThirdParty($db, $object, $conf);

		$userPrompt = "INVOICE_JSON:\n".$invoiceJson."\n\nCHART_OF_ACCOUNTS (filtered pcg_type):\n".$chartLines."\n\nLAST_N_BOOKKEEPINGS (same third-party):\n".$recentRows."\n\nReturn JSON only.";

		// Read EBK's own prompt constants. These live in EBK's namespace
		// (`EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE/_POST`) — see the file header
		// for why this MUST NOT be `AI_CONFIGURATIONS_PROMPT` (cross-module
		// namespace pollution). When both are empty (admin cleared them
		// intentionally), we fall back to the in-process EBK built-in EN/ZH
		// default. No DB write, no system AI module interaction.
		$prePrompt  = self::resolvePrompt($conf, $langs, 'pre');
		$postPrompt = self::resolvePrompt($conf, $langs, 'post');
		$fullInstructions = $userPrompt;
		if ($postPrompt !== '') {
			$sep = (preg_match('/[\.\!\?]$/', $userPrompt) ? ' ' : '. ');
			$fullInstructions .= $sep.$postPrompt;
		}

		// mode='json' tells the adapter to add response_format=json_object
		// for OpenAI-compatible endpoints that support it (chatgpt /
		// deepseek / perplexity / mistral / zai) per
		// llmadapter.class.php:113-118.
		$raw = $adapter->generate($prePrompt, $fullInstructions, 'json');
		if (!is_string($raw) || $raw === '') {
			return array();
		}
		// UniversalLLMAdapter returns "Error: …" strings (not exceptions)
		// when the upstream API fails. Treat those as no suggestion.
		if (strpos($raw, 'Error:') === 0) {
			dol_syslog(get_class()."::suggest adapter error: ".$raw, LOG_WARNING);
			return array();
		}

		return $this->parseAiReply($raw);
	}

	// ------------------------------------------------------------------
	// Prompt + adapter resolution
	// ------------------------------------------------------------------

	/**
	 * Read EBK's own prompt constant. When the admin-saved value is empty
	 * (or the constant was never set), fall back to the hard-coded EBK
	 * built-in EN/ZH default from `getDefaultBookkeepingPrompt()`. This
	 * fallback is in-process only — no DB write, no system AI module
	 * interaction, no cross-module namespace pollution.
	 *
	 * @param  stdClass         $conf
	 * @param  Translate|null   $langs
	 * @param  string           $which  'pre' or 'post'
	 * @return string
	 */
	public static function resolvePrompt($conf, $langs, $which)
	{
		$constName = $which === 'post'
			? 'EMBEDDEDBOOKKEEPING_AI_PROMPT_POST'
			: 'EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE';
		$saved = isset($conf->global->$constName) ? (string) $conf->global->$constName : '';
		if ($saved !== '') {
			return $saved;
		}
		$defaults = self::getDefaultBookkeepingPrompt($langs);
		return $which === 'post' ? $defaults['postPrompt'] : $defaults['prePrompt'];
	}

	/**
	 * Resolve the configured LLM into a ready to use `UniversalLLMAdapter`.
	 * Returns null when the admin hasn't configured any of the required
	 * fields (the bookkeeping tab then falls back to manual entry).
	 *
	 * Two providers are supported:
	 *
	 *   - **'ai_module'** (default) — BORROW credentials from the system
	 *     AI module's own setup. Constants read (all owned by the system
	 *     AI module — we do not duplicate them):
	 *       - AI_API_SERVICE
	 *       - AI_API_<SERVICE>_KEY
	 *       - AI_API_<SERVICE>_URL   (optional override)
	 *       - AI_API_<SERVICE>_MODEL_TEXT (optional override)
	 *
	 *   - **'ebk_custom'** — USE EBK's own constants. Fully independent
	 *     of the system AI module. Constants read (all owned by EBK):
	 *       - EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE  (chatgpt / anthropic /
	 *         google / custom; default 'chatgpt')
	 *       - EMBEDDEDBOOKKEEPING_AI_CUSTOM_KEY     (stored with the
	 *         'chaine:KEY' suffix so dolibarr_set_const encrypts it)
	 *       - EMBEDDEDBOOKKEEPING_AI_CUSTOM_URL     (only consulted when
	 *         service='custom'; falls back to the system AI module's
	 *         catalog default URL)
	 *       - EMBEDDEDBOOKKEEPING_AI_CUSTOM_MODEL   (overrides the
	 *         catalog default for this provider)
	 *
	 * @param  stdClass $conf
	 * @param  string   $provider   EMBEDDEDBOOKKEEPING_AI_PROVIDER value
	 *                              ('ai_module' | 'ebk_custom' | 'disabled')
	 * @return UniversalLLMAdapter|null
	 */
	public static function resolveAdapter($conf, $provider = 'ai_module')
	{
		// ai module is present when provider='ai_module' (caller already
		// checked isModEnabled). For 'ebk_custom' the system AI module is
		// NOT required — but we still need UniversalLLMAdapter's class, so
		// try to load it either way.
		if (!class_exists('UniversalLLMAdapter', false)) {
			$file = DOL_DOCUMENT_ROOT.'/ai/class/llmadapter.class.php';
			if (!is_file($file)) {
				return null;
			}
			require_once $file;
		}
		// getListOfAIServices() is the system AI module's service catalog.
		// It provides default URL / model per service. It is bundled with
		// Dolibarr 17+ in the same ai/ directory, so it's loadable
		// regardless of whether the AI module is enabled at runtime.
		if (!function_exists('getListOfAIServices')) {
			$file = DOL_DOCUMENT_ROOT.'/ai/lib/ai.lib.php';
			if (!is_file($file)) {
				return null;
			}
			require_once $file;
		}

		$arrayofai = getListOfAIServices();
		if (!is_array($arrayofai) || empty($arrayofai)) {
			return null;
		}

		if ($provider === 'ebk_custom') {
			return self::resolveAdapterEbkCustom($conf, $arrayofai);
		}
		// Default: 'ai_module' — borrow system AI module configuration.
		return self::resolveAdapterAiModule($conf, $arrayofai);
	}

	/**
	 * 'ai_module' provider: borrow service/key/url/model from the system
	 * AI module's own configuration. Same logic as before; factored out so
	 * the dual-provider resolveAdapter() stays readable.
	 *
	 * @param  stdClass $conf
	 * @param  array    $arrayofai   Service catalog from getListOfAIServices()
	 * @return UniversalLLMAdapter|null
	 */
	private static function resolveAdapterAiModule($conf, $arrayofai)
	{
		$service = (string) getDolGlobalString('AI_API_SERVICE', 'chatgpt');
		if (!isset($arrayofai[$service])) {
			dol_syslog(__METHOD__." unknown AI service '".$service."'", LOG_WARNING);
			return null;
		}
		$key = (string) getDolGlobalString('AI_API_'.strtoupper($service).'_KEY', '');
		if ($key === '' && in_array($service, array('chatgpt', 'groq', 'mistral'), true)) {
			// chatgpt / groq / mistral require a real key; others (custom,
			// anthropic, google) might pass it via URL or as query param
			// and tolerate empty here.
			dol_syslog(__METHOD__." AI service '".$service."' has no key configured", LOG_DEBUG);
			return null;
		}
		$url = (string) getDolGlobalString('AI_API_'.strtoupper($service).'_URL', $arrayofai[$service]['url']);
		if ($service === 'custom' && $url === '') {
			dol_syslog(__METHOD__." 'custom' AI service has no URL configured", LOG_DEBUG);
			return null;
		}
		$modelDefault = isset($arrayofai[$service]['textgeneration']['default'])
			? (string) $arrayofai[$service]['textgeneration']['default']
			: '';
		$model = (string) getDolGlobalString('AI_API_'.strtoupper($service).'_MODEL_TEXT', $modelDefault);
		return self::buildAdapter($service, $key, $url, $model);
	}

	/**
	 * 'ebk_custom' provider: use EBK's own constants
	 * (EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE/_KEY/_URL/_MODEL). Fully
	 * independent of the system AI module — no read/write to AI_API_*.
	 *
	 * @param  stdClass $conf
	 * @param  array    $arrayofai
	 * @return UniversalLLMAdapter|null
	 */
	private static function resolveAdapterEbkCustom($conf, $arrayofai)
	{
		$service = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE', 'chatgpt');
		if (!isset($arrayofai[$service])) {
			dol_syslog(__METHOD__." unknown EBK AI service '".$service."'", LOG_WARNING);
			return null;
		}
		$key = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_KEY', '');
		if ($key === '' && in_array($service, array('chatgpt', 'groq', 'mistral'), true)) {
			dol_syslog(__METHOD__." EBK AI service '".$service."' has no key configured", LOG_DEBUG);
			return null;
		}
		$urlDefault = (string) (isset($arrayofai[$service]['url']) ? $arrayofai[$service]['url'] : '');
		$url = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_URL', $urlDefault);
		if ($service === 'custom' && $url === '') {
			dol_syslog(__METHOD__." 'custom' EBK AI service has no URL configured", LOG_DEBUG);
			return null;
		}
		$modelDefault = isset($arrayofai[$service]['textgeneration']['default'])
			? (string) $arrayofai[$service]['textgeneration']['default']
			: '';
		$model = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_MODEL', $modelDefault);
		return self::buildAdapter($service, $key, $url, $model);
	}

	/**
	 * Construct the UniversalLLMAdapter with a 30s timeout. Factored out
	 * so the two resolveAdapter*() helpers don't duplicate the same
	 * constructor call.
	 *
	 * @param string $service
	 * @param string $key
	 * @param string $url
	 * @param string $model
	 * @return UniversalLLMAdapter
	 */
	private static function buildAdapter($service, $key, $url, $model)
	{
		// UniversalLLMAdapter picks protocol by $service (openai / anthropic
		// / google / custom) — see llmadapter.class.php:77-85. 30s timeout
		// matches what the system AI module uses by default.
		return new UniversalLLMAdapter($service, $key, $url, $model, 30);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Render the source invoice as a compact JSON object. Sensitive fields
	 * (bank account, internal note_private) are intentionally excluded —
	 * the prompt should never leak them to a remote LLM.
	 *
	 * @param CommonObject $object
	 * @return string
	 */
	private function invoiceAsJson($object)
	{
		$out = array(
			'side'            => method_exists($object, 'getLibType') || property_exists($object, 'type')
				? ($object->element === 'facture' ? 'customer_invoice' : 'supplier_invoice')
				: 'unknown',
			'ref'             => isset($object->ref) ? (string) $object->ref : '',
			'date'            => isset($object->date) ? (int) $object->date : 0,
			'total_ht'        => isset($object->total_ht) ? (float) $object->total_ht : 0.0,
			'total_ttc'       => isset($object->total_ttc) ? (float) $object->total_ttc : 0.0,
			'total_vat'       => isset($object->total_tva) ? (float) $object->total_tva : 0.0,
			'currency'        => isset($object->multicurrency_code) ? (string) $object->multicurrency_code : '',
			'multicurrency_total_ttc' => isset($object->multicurrency_total_ttc) ? (float) $object->multicurrency_total_ttc : null,
			'thirdparty_name' => isset($object->thirdparty) && is_object($object->thirdparty) ? (string) $object->thirdparty->name : '',
			'lines'           => array(),
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
		// Defensive cap — never ship more than 8 KiB of invoice to the LLM.
		if (strlen($json) > 8192) {
			$json = substr($json, 0, 8192).'…(truncated)';
		}
		return $json;
	}

	/**
	 * Render a filtered chart of accounts as plain text (so it fits the
	 * prompt budget). We filter to the four pcg_types that are actually
	 * selectable from the UI modal — INCOME / EXPENSE / ASSET / LIABILITY.
	 *
	 * @param DoliDB    $db
	 * @param stdClass  $conf
	 * @return string
	 */
	private function chartAsText($db, $conf)
	{
		$entity = (int) ($conf->entity ?? 1);
		$sql = "SELECT a.account_number, a.label, a.pcg_type";
		$sql .= " FROM ".MAIN_DB_PREFIX."accounting_account AS a";
		$sql .= " WHERE a.active = 1";
		$sql .= " AND a.entity IN (0, ".$entity.")";
		$sql .= " AND a.pcg_type IN ('INCOME','EXPENSE','ASSET','LIABILITY','TAX')";
		$sql .= " ORDER BY a.account_number ASC";
		$sql .= " LIMIT 200";

		$lines = array();
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$lines[] = $obj->account_number."\t".$obj->pcg_type."\t".$obj->label;
			}
			$db->free($resql);
		}
		return implode("\n", $lines);
	}

	/**
	 * Return up to 5 recent bookkeeping rows for the same third-party, so
	 * the LLM can see the company's established accounting conventions.
	 *
	 * @param DoliDB        $db
	 * @param CommonObject  $object
	 * @param stdClass      $conf
	 * @return string
	 */
	private function recentRowsForThirdParty($db, $object, $conf)
	{
		// Dolibarr core writes bookkeeping.thirdparty_code from
		//   sellsjournal.php:632   → code_client           (customer)
		//   purchasesjournal.php:533 → code_fournisseur     (supplier)
		// Our own EBK tabs/bookkeeping.php:153 / EBKTabData.class.php:377
		// currently set only code_client, so for supplier invoices EBK-
		// generated rows end up with empty thirdparty_code (separate
		// concern, NOT fixed here). To make the read-side robust regardless
		// of which field the writer used, OR together every non-empty code
		// candidate the third-party object exposes. Empty strings are
		// dropped so the IN clause stays well-formed.
		$tp = (is_object($object) && isset($object->thirdparty) && is_object($object->thirdparty))
			? $object->thirdparty
			: null;
		if (!$tp) {
			return '(none)';
		}
		$candidates = array();
		foreach (array('code_client', 'code_compta', 'code_fournisseur', 'code_compta_fournisseur') as $f) {
			if (isset($tp->$f) && (string) $tp->$f !== '') {
				$candidates[(string) $tp->$f] = true;
			}
		}
		if (empty($candidates)) {
			return '(none)';
		}
		// Cast every value to string — PHP coerces numeric-looking string
		// keys to integers when used as array keys, so we re-cast to avoid
		// the in_array() (production-equivalent) / SQL escape paths from
		// tripping on mixed int+string candidates.
		$inList = array();
		foreach (array_map('strval', array_keys($candidates)) as $code) {
			$inList[] = "'".$db->escape($code)."'";
		}
		$entity = (int) ($conf->entity ?? 1);

		$sql = "SELECT b.doc_date, b.numero_compte, b.label_compte, b.label_operation, b.debit, b.credit, b.code_journal";
		$sql .= " FROM ".MAIN_DB_PREFIX."accounting_bookkeeping AS b";
		$sql .= " WHERE b.thirdparty_code IN (".implode(',', $inList).")";
		$sql .= " AND b.entity IN (0, ".$entity.")";
		$sql .= " ORDER BY b.doc_date DESC, b.piece_num DESC";
		$sql .= " LIMIT 5";

		$rows = array();
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$rows[] = sprintf(
					'%s | %s | %s | %s | D=%.2f C=%.2f | J=%s',
					$obj->doc_date,
					$obj->numero_compte,
					$obj->label_compte,
					str_replace(array("\r","\n","\t"), ' ', $obj->label_operation),
					(float) $obj->debit,
					(float) $obj->credit,
					$obj->code_journal
				);
			}
			$db->free($resql);
		}
		return $rows ? implode("\n", $rows) : '(none)';
	}

	/**
	 * Parse a raw LLM reply into EBKAiLine[]. Tolerant to noise: extract the
	 * first JSON object found and fall back to a single-line interpretation
	 * (some upstream models wrap JSON in markdown fences).
	 *
	 * @param string $raw
	 * @return array<int,EBKAiLine>
	 */
	private function parseAiReply($raw)
	{
		$out = array();

		// Strip markdown fences if present.
		$stripped = trim($raw);
		$stripped = preg_replace('/^```(?:json)?/i', '', $stripped);
		$stripped = preg_replace('/```\s*$/i', '', $stripped);
		$stripped = trim($stripped);

		// Find the first { ... } block.
		$start = strpos($stripped, '{');
		if ($start === false) {
			return $out;
		}
		// Find matching closing brace via nesting.
		$depth = 0;
		$end = -1;
		$len = strlen($stripped);
		for ($i = $start; $i < $len; $i++) {
			$ch = $stripped[$i];
			if ($ch === '{') $depth++;
			elseif ($ch === '}') {
				$depth--;
				if ($depth === 0) {
					$end = $i;
					break;
				}
			}
		}
		if ($end === -1) {
			return $out;
		}
		$json = substr($stripped, $start, $end - $start + 1);
		$decoded = json_decode($json, true);
		if (!is_array($decoded) || empty($decoded['lines']) || !is_array($decoded['lines'])) {
			return $out;
		}

		foreach ($decoded['lines'] as $ln) {
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
			$line->provider_id     = 'ai_module';

			// Filter out obvious garbage: empty accounts, non-positive amounts.
			if ($line->debit_account === '' || $line->credit_account === '') continue;
			if ($line->amount <= 0) continue;

			$out[] = $line;
		}
		return $out;
	}

	// ------------------------------------------------------------------
	// Built-in default prompt (process-scoped fallback)
	// ------------------------------------------------------------------

	/**
	 * EN/ZH built-in default for the bookkeeping-suggest function key.
	 *
	 * This method is the single source of truth for the EBK out-of-the-box
	 * prompt, shared between:
	 *   - resolvePrompt() — runtime fallback when the admin left the
	 *       EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE/_POST constants empty.
	 *   - admin/setup.php — pre-filled value shown in the textareas so the
	 *       admin can copy-edit it instead of starting from a blank page.
	 *
	 * Both EN and ZH live here on purpose (no separate lang file): the
	 * prompt is a developer-facing template, not user copy — admin edits
	 * it once and saves under their preferred language. Language auto-
	 * detected from $langs->defaultlang when available.
	 *
	 * @param  Translate $langs   (may be null; falls back to EN)
	 * @return array{prePrompt:string,postPrompt:string}
	 */
	public static function getDefaultBookkeepingPrompt($langs)
	{
		$lang = '';
		if (is_object($langs) && !empty($langs->defaultlang)) {
			$lang = strtolower((string) $langs->defaultlang);
		}
		// Treat both zh_CN and zh_HK/zh_TW as Chinese.
		$isChinese = (strpos($lang, 'zh') === 0);

		if ($isChinese) {
			$prePrompt = "你是一位会计助理，帮助为一张发票生成一笔借贷平衡的会计分录。\n\n"
				. "输入包含三个块：\n"
				. "1. INVOICE_JSON — 源发票（客户发票或供应商发票）\n"
				. "2. CHART_OF_ACCOUNTS — 可供选择的科目（已按四个大类过滤）\n"
				. "3. LAST_N_BOOKKEEPINGS — 同一第三方最近的分录（用以沿用既有记账习惯）\n\n"
				. "任务 — 为该发票提出一笔借贷平衡的会计分录。\n"
				. "严格返回 JSON（不要 markdown 包裹、不要任何额外文字）：\n"
				. "{\n"
				. "  \"lines\": [\n"
				. "    {\"debit_account\":\"<编号>\",\"debit_label\":\"<科目名>\",\"credit_account\":\"<编号>\",\"credit_label\":\"<科目名>\",\"amount\":<正数>,\"currency\":\"<ISO 货币或空>\",\"label_operation\":\"<简短描述>\",\"confidence\":<0..1>,\"rationale\":\"<一句话理由>\"}\n"
				. "  ]\n"
				. "}\n\n"
				. "规则\n"
				. "- `lines` 中每一项是一条单边分录，金额恒为正（>0）。所有借方合计 = 所有贷方合计（分位精度，保留 2 位小数）。\n"
				. "- 科目编号必须出现在 CHART_OF_ACCOUNTS 中。\n"
				. "- 对方科目：若 LAST_N_BOOKKEEPINGS 中有同一第三方的记录，沿用其科目；否则在 LIABILITY（客户）/ ASSET（供应商）类中选 label 含 \"客户\" / \"供应商\" / \"应付\" / \"应收\" 的科目。\n"
				. "- 客户发票 → 贷 INCOME 类；供应商发票 → 借 EXPENSE 类。\n"
				. "- 增值税（如有）单独立一行，借/贷对应的 VAT 科目。\n"
				. "- 不要假设特定国家 GAAP（SG SFRS / FR PCG / IFRS 等）。从 CHART_OF_ACCOUNTS 和 LAST_N_BOOKKEEPINGS 的实际数据中归纳本公司的记账习惯。\n"
				. "- confidence：与 LAST_N_BOOKKEEPINGS 完全一致 = 1.0；推断得出 = 0.6–0.8；不确定 < 0.5。\n"
				. "- 仅返回 JSON，不要任何前后缀文字。";
			$postPrompt = "再次核对：所有借方合计必须精确等于所有贷方合计（分位精度，保留 2 位小数）。仅输出 JSON。";
		} else {
			$prePrompt = "You are an accountant's assistant helping to book a single journal entry for an invoice.\n\n"
				. "The input has THREE blocks:\n"
				. "1. INVOICE_JSON — the source invoice (customer or supplier)\n"
				. "2. CHART_OF_ACCOUNTS — selectable accounts (already filtered to a subset)\n"
				. "3. LAST_N_BOOKKEEPINGS — recent entries for the same third-party (use them to mirror conventions)\n\n"
				. "TASK — propose ONE balanced journal entry for this invoice.\n"
				. "Return STRICT JSON (no markdown fence, no commentary):\n"
				. "{\n"
				. "  \"lines\": [\n"
				. "    {\"debit_account\":\"<num>\",\"debit_label\":\"<label>\",\"credit_account\":\"<num>\",\"credit_label\":\"<label>\",\"amount\":<positive number>,\"currency\":\"<ISO or empty>\",\"label_operation\":\"<short desc>\",\"confidence\":<0..1>,\"rationale\":\"<one sentence>\"}\n"
				. "  ]\n"
				. "}\n\n"
				. "RULES\n"
				. "- Each item in `lines` is a single-leg row. amount is always positive (>0). SUM(debits) MUST exactly equal SUM(credits) (cent precision, 2 decimal places).\n"
				. "- Account numbers MUST come from CHART_OF_ACCOUNTS.\n"
				. "- Third-party counter-account: when LAST_N_BOOKKEEPINGS has rows for this third-party, mirror the convention. Otherwise pick a LIABILITY (customer) or ASSET (supplier) account whose label matches \"Customers\" / \"Suppliers\" / \"Receivables\" / \"Payables\".\n"
				. "- Customer invoice → credit INCOME class; supplier invoice → debit EXPENSE class.\n"
				. "- VAT (if any) gets its own line — debit/credit the matching VAT account.\n"
				. "- Do NOT assume any specific country's GAAP (SG SFRS, FR PCG, IFRS, US GAAP, etc.). Infer the company's actual convention from the account labels and LAST_N_BOOKKEEPINGS you can see.\n"
				. "- confidence: 1.0 when pattern matches LAST_N_BOOKKEEPINGS exactly, 0.6–0.8 when you inferred, <0.5 if unsure.\n"
				. "- Return ONLY the JSON. No prose before/after.";
			$postPrompt = "Double-check: SUM(debits) MUST exactly equal SUM(credits) (cent precision, 2 decimal places). Output JSON only.";
		}

		return array(
			'prePrompt'  => $prePrompt,
			'postPrompt' => $postPrompt,
		);
	}
}

} // if (!class_exists('AiModuleProvider', false))
