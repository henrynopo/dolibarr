<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/ai/EBKAiSuggester.interface.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Contract every AI provider must satisfy.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKEntryProposal.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiLine.class.php';

if (!interface_exists('EBKAiSuggester', false)) {

/**
 * Suggest bookkeeping lines for one invoice.
 *
 * @param EBKEntryProposal $seed       Pre-filled proposal (invoice header already known). The provider MUST
 *                                    NOT mutate this object; it should only read from it.
 * @param CommonObject     $object     The source Facture or FactureFournisseur. Implementations may read
 *                                    $object->lines, $object->thirdparty, $object->multicurrency_*, etc.
 * @return array<int,EBKAiLine> Empty array if nothing confident can be suggested.
 */
interface EBKAiSuggester
{
	/**
	 * Return one or more balanced journal-entry suggestions.
	 *
	 * @param EBKEntryProposal $seed
	 * @param CommonObject     $object
	 * @return array<int,EBKAiLine>
	 */
	public function suggest(EBKEntryProposal $seed, $object);
}

} // if (!interface_exists('EBKAiSuggester', false))
