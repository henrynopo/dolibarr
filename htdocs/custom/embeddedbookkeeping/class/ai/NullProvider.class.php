<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/ai/NullProvider.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Disabled / unconfigured stub. Always returns empty suggestions.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiSuggester.interface.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiLine.class.php';

if (!class_exists('NullProvider', false)) {

class NullProvider implements EBKAiSuggester
{
	/** @var string Reason for returning empty (used by AJAX endpoint to surface a translated warning) */
	private $reason;

	public function __construct($reason = 'disabled')
	{
		$this->reason = (string) $reason;
	}

	/**
	 * @param EBKEntryProposal $seed
	 * @param CommonObject     $object
	 * @return array<int,EBKAiLine> Always empty.
	 */
	public function suggest(EBKEntryProposal $seed, $object)
	{
		return array();
	}

	/**
	 * Reason accessor for the AJAX endpoint to surface a translated warning.
	 *
	 * @return string
	 */
	public function getReason()
	{
		return $this->reason;
	}
}

} // if (!class_exists('NullProvider', false))
