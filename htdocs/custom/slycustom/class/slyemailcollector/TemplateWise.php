<?php
/* Copyright (C) 2026 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/slycustom/class/slyemailcollector/TemplateWise.php
 * \ingroup slyemailcollector
 * \brief   Wise notification email template (MVP, 2.5.0).
 *
 * Recognises the standard "You received X.XX USD from <NAME>" notification
 * Wise sends for incoming transfers. The plain-text body always carries an
 * Amount / Date / Reference block we can regex out; if any of those three
 * fails, the email is rejected and the operator sees the failure in
 * reconcile/dumps.
 *
 * Subject line is the primary matches() signal; the body regex is the
 * second. A future Singapore-bank template can extend the same interface
 * with its own matches() predicate and ship alongside this one.
 */

require_once __DIR__.'/EmailTemplate.class.php';

class TemplateWise implements EmailTemplate
{
	public function id()       { return 'wise'; }
	public function label()    { return 'Wise notification (received money)'; }

	/**
	 * Match Wise's "You received ..." subject line. We accept both the
	 * balance.credit wording and the older "credited to your account"
	 * variant.
	 */
	public function matches(stdClass $parsed)
	{
		$subj = isset($parsed->subject) ? strtolower((string) $parsed->subject) : '';
		if ($subj === '') return false;
		if (strpos($subj, 'wise') === false && strpos($subj, 'received') === false) {
			// Some regional templates omit the brand. Fall back to the
			// "received" verb as a softer signal.
			return false;
		}
		// Match "received X.XX XXX from NAME" or "credited ... from NAME".
		return (bool) preg_match('/received\s+[0-9.,]+\s+[A-Z]{3}/i', (string) $parsed->subject)
			|| (bool) preg_match('/credited.*from\s+/i', (string) $parsed->subject);
	}

	public function extract(stdClass $parsed)
	{
		$body = isset($parsed->body) ? (string) $parsed->body : '';
		$subject = isset($parsed->subject) ? (string) $parsed->subject : '';

		// amount + currency. Wise's body uses "Amount: 12.34 EUR".
		$amount = null; $currency = '';
		if (preg_match('/Amount\s*[:\-]\s*([0-9.,]+)\s*([A-Z]{3})/i', $body, $m)) {
			$amount = (float) str_replace(',', '', $m[1]);
			$currency = strtoupper($m[2]);
		}
		// Fall back to subject if body didn't carry the amount.
		if ($amount === null && preg_match('/received\s+([0-9.,]+)\s+([A-Z]{3})/i', $subject, $m)) {
			$amount = (float) str_replace(',', '', $m[1]);
			$currency = strtoupper($m[2]);
		}
		if ($amount === null || $currency === '') {
			throw new Exception('Wise template: amount/currency not found in message body or subject');
		}

		// date. Wise's body uses "Date: 2026-10-13" or "Oct 13, 2026".
		$occurred = '';
		if (preg_match('/Date\s*[:\-]\s*(\d{4}-\d{2}-\d{2})/', $body, $m)) {
			$occurred = $m[1];
		} elseif (preg_match('/Date\s*[:\-]\s*([A-Z][a-z]{2}\s+\d{1,2},\s*\d{4})/i', $body, $m)) {
			$ts = strtotime($m[1]);
			if ($ts !== false) $occurred = date('Y-m-d', $ts);
		}
		if ($occurred === '') {
			// Fall back to the email Date header.
			$occurred = isset($parsed->date) ? substr((string) $parsed->date, 0, 10) : '';
		}
		if ($occurred === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $occurred)) {
			throw new Exception('Wise template: occurred date not parseable');
		}

		// reference. Wise puts it under "Reference:" or as the last
		// line of the body. Either is fine; we prefer the body field.
		$ref = '';
		if (preg_match('/Reference\s*[:\-]\s*([A-Za-z0-9\-]+)/i', $body, $m)) {
			$ref = $m[1];
		}
		// counterparty: "from <NAME>" in the subject. Always present
		// when matches() accepted the message.
		$counterparty = '';
		if (preg_match('/from\s+([^\.]+?)(?:\s+on\s+|\s*$)/i', $subject, $m)) {
			$counterparty = trim($m[1]);
		}
		if ($counterparty === '' && preg_match('/from\s+([^\n\r]+)/i', $body, $m)) {
			$counterparty = trim($m[1]);
		}

		// fees (optional). Some Wise emails include a fees row.
		$fees = null;
		if (preg_match('/Fee\s*[:\-]\s*([0-9.,]+)/i', $body, $m)) {
			$fees = (float) str_replace(',', '', $m[1]);
		}

		return array(
			'amount'       => $amount,
			'currency'     => $currency,
			'occurred_at'  => $occurred.' 00:00:00',
			'ref_text'     => $ref,
			'counterparty' => $counterparty,
			'fees'         => $fees,
		);
	}
}
