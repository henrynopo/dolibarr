<?php
/* Copyright (C) 2026 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/slycustom/class/slyemailcollector/EmailTemplate.class.php
 * \ingroup slyemailcollector
 * \brief   Interface for email-notification templates.
 *
 * An EmailTemplate takes a parsed email (Subject + From + Date + plain-text
 * body) and either claims the message ("matches") or hands it back to the
 * next template. When it matches, it must produce a normalised payload of
 * the same shape the Wise webhook writes to its `data` field:
 *
 *   array(
 *       'amount'     => (float),
 *       'currency'   => 'EUR',
 *       'occurred_at'=> 'YYYY-MM-DD HH:MM:SS',
 *       'ref_text'   => '...',
 *       'counterparty' => '...',
 *       'fees'       => (float|null),
 *   )
 *
 * 2.5.0 ships a single Wise implementation; bank and other providers can
 * add their own class implementing this interface and register it through
 * the EmailCollector::getTemplates() hook.
 */

interface EmailTemplate
{
	/**
	 * Stable id used in sidecar source_match.
	 * Example: 'wise', 'sg-bank'.
	 * @return string
	 */
	public function id();

	/**
	 * Human-readable label shown in the admin Email tab.
	 * @return string
	 */
	public function label();

	/**
	 * Test whether this template applies to the parsed email.
	 *
	 * @param  stdClass $parsed  Fields: subject, from, date, body (plain text)
	 * @return bool
	 */
	public function matches(stdClass $parsed);

	/**
	 * Extract the normalised payload. MUST be called only when matches()
	 * returns true. Implementations should validate required fields and
	 * throw an exception on parse error so EmailCollector can route the
	 * message to the rejected bucket with a meaningful note.
	 *
	 * @param  stdClass $parsed
	 * @return array<string,mixed>  normalised payload (see class docblock)
	 */
	public function extract(stdClass $parsed);
}
