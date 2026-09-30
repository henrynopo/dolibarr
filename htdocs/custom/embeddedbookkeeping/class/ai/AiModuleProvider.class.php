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
 *	\brief      Default provider: reuses Dolibarr core's htdocs/ai/ module
 *	             (Ai::generateContent) to ask the configured upstream LLM
 *	             (chatgpt / groq / mistral / custom) for a balanced journal entry.
 *
 *             Self-contained: does NOT depend on the ai module being enabled;
 *             when it isn't, returns empty suggestions + a structured warning
 *             surfaced through NullProvider semantics.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiSuggester.interface.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiLine.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKAccountLookup.class.php';

if (!class_exists('AiModuleProvider', false)) {

class AiModuleProvider implements EBKAiSuggester
{
	/**
	 * Suggest a balanced journal entry via Dolibarr's native ai module.
	 *
	 * @param EBKEntryProposal $seed
	 * @param CommonObject     $object
	 * @return array<int,EBKAiLine>
	 */
	public function suggest(EBKEntryProposal $seed, $object)
	{
		global $conf, $langs, $db;

		// Short-circuit when ai module is not enabled.
		if (!isModEnabled('ai')) {
			dol_syslog(get_class()."::suggest ai module not enabled", LOG_DEBUG);
			return array();
		}

		// Lazy include — the ai module is core, present since Dolibarr 17.
		if (!class_exists('Ai', false)) {
			$file = DOL_DOCUMENT_ROOT.'/ai/class/ai.class.php';
			if (!is_file($file)) {
				dol_syslog(get_class()."::suggest ai.class.php missing", LOG_WARNING);
				return array();
			}
			require_once $file;
		}

		// Build the prompt.
		$invoiceJson = $this->invoiceAsJson($object);
		$chartLines = $this->chartAsText($db, $conf);
		$recentRows = $this->recentRowsForThirdParty($db, $object, $conf);

		$systemPrompt = $this->resolveSystemPrompt($langs);
		$userPrompt = "INVOICE_JSON:\n".$invoiceJson."\n\nCHART_OF_ACCOUNTS (filtered pcg_type):\n".$chartLines."\n\nLAST_N_BOOKKEEPINGS (same third-party):\n".$recentRows."\n\nReturn JSON only.";

		// Call the core LLM gateway.
		try {
			$ai = new Ai($db);
			$raw = $ai->generateContent($userPrompt, 'textgeneration', $systemPrompt, 0.2);
		} catch (Throwable $e) {
			dol_syslog(get_class()."::suggest Ai::generateContent threw: ".$e->getMessage(), LOG_WARNING);
			return array();
		}
		if (!is_string($raw) || $raw === '') {
			return array();
		}

		return $this->parseAiReply($raw);
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
		$sql .= " AND a.pcg_type IN ('INCOME','EXPENSE','ASSET','LIABILITY')";
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
		$thirdpartyCode = '';
		if (is_object($object) && isset($object->thirdparty) && is_object($object->thirdparty)) {
			$thirdpartyCode = isset($object->thirdparty->code_client)
				? (string) $object->thirdparty->code_client
				: (string) ($object->thirdparty->code_compta ?? '');
		}
		if ($thirdpartyCode === '') {
			return '(none)';
		}
		$entity = (int) ($conf->entity ?? 1);

		$sql = "SELECT b.doc_date, b.numero_compte, b.label_compte, b.label_operation, b.debit, b.credit, b.code_journal";
		$sql .= " FROM ".MAIN_DB_PREFIX."accounting_bookkeeping AS b";
		$sql .= " WHERE b.thirdparty_code = '".$db->escape($thirdpartyCode)."'";
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
	 * Resolve the system prompt. The admin can override per-language via
	 * EMBEDDEDBOOKKEEPING_AI_SYSTEM_PROMPT_EN / _ZH.
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
}

} // if (!class_exists('AiModuleProvider', false))
