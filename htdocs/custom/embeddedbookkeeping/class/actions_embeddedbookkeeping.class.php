<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/actions_embeddedbookkeeping.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Hook actions for EmbeddedBookkeeping module. Since 1.1.0 the
 *	             bookkeeping entry point lives in the "Accounting entries" TAB
 *	             (tabs/bookkeeping.php, registered via the descriptor); the only
 *	             card hook left is addMoreActionsButtons, rendering one deep-link
 *	             button to that tab.
 *
 *	             The former button + hidden-modal flow (formConfirm / doActions /
 *	             formObjectOptions traits) was removed: the modal never reached
 *	             the page ($parameters passed by value) and its only opener JS
 *	             required a working AI provider — blocking manual bookkeeping
 *	             entirely when AI was not configured.
 */

/**
 *	Class ActionsEmbeddedBookkeeping
 */

require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ActionsEmbeddedBookkeepingUiTrait.php';

class ActionsEmbeddedBookkeeping
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/** @var string|null Output for hooks that print (set by HookManager into resprints) */
	public $resprints;

	/** @var array Hook return data (rarely used here) */
	public $results = array();

	/** @var int Hook priority (higher = earlier); kept neutral to not collide with slycustom (also 50) */
	public $priority = 50;

	use ActionsEmbeddedBookkeepingUiTrait;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Hook for `addMoreActionsButtons`.
	 *
	 * @param array        $parameters
	 * @param CommonObject $object
	 * @param string       $action
	 * @param string       $hookname
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, $object, $action, $hookname)
	{
		return $this->uiAddMoreActionsButtons($parameters, $object, $action, $hookname);
	}
}
