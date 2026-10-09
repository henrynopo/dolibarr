<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/ai/EBKAiProviderFactory.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Resolves the active AI provider from EMBEDDEDBOOKKEEPING_AI_PROVIDER.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiSuggester.interface.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/NullProvider.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/AiModuleProvider.class.php';

if (!class_exists('EBKAiProviderFactory', false)) {

class EBKAiProviderFactory
{
	/**
	 * @return EBKAiSuggester A provider that always answers — NullProvider when disabled,
	 *                        AiModuleProvider otherwise. AiModuleProvider is robust to
	 *                        "ai module not enabled" — it falls back to NullProvider internally
	 *                        with a structured warning. So returning it here always works.
	 */
	public static function resolve()
	{
		global $conf;

		$choice = strtolower(trim((string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_PROVIDER', 'ai_module')));
		if ($choice === '' || $choice === 'disabled') {
			return new NullProvider('disabled');
		}
		if ($choice === 'ai_module' || $choice === 'ebk_custom') {
			// Both providers end up in AiModuleProvider: it branches on the very
			// same constant when it resolves credentials (resolveAdapter() →
			// resolveAdapterAiModule / resolveAdapterEbkCustom). Returning
			// NullProvider for 'ebk_custom' — as this factory used to — only
			// happened to work because the chat path falls back to the adapter
			// anyway, while logging a bogus "unknown_provider" warning.
			return new AiModuleProvider();
		}
		// Unknown value — be permissive and fall back to NullProvider.
		dol_syslog(get_class()."::resolve unknown provider choice='".$choice."'", LOG_WARNING);
		return new NullProvider('unknown_provider');
	}
}

} // if (!class_exists('EBKAiProviderFactory', false))
