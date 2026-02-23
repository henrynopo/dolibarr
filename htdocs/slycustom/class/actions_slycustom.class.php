<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 *	\file       htdocs/slycustom/class/actions_slycustom.class.php
 *	\ingroup    slycustom
 *	\brief      Hook actions for SLY Custom module
 */
/**
 *	Class ActionsSlycustom
 */
class ActionsSlycustom
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var int Hook priority (higher = earlier)
	 */
	public $priority = 50;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	// Add hook methods here when needed, e.g.:
	// function formObjectOptions($parameters, $object, $action) { ... }
	// function printFieldListValue($parameters, $object, $action) { ... }
}
