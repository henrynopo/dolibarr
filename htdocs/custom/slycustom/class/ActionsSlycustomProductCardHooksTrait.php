<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: SLY Custom product card hook (load module lang).
 *
 * Extracted from actions_slycustom.class.php to reduce file size.
 */
trait ActionsSlycustomProductCardHooksTrait
{
	/**
	 * formObjectOptions: load SLYcustom lang on product card so CustomsCode/CustomCode show as Plant No./厂号.
	 *
	 * @param array        $parameters Hook parameters
	 * @param CommonObject $object     Object (Product on product card)
	 * @param string       $action     Current action
	 * @return int
	 */
	public function formObjectOptions($parameters, &$object, &$action)
	{
		global $langs;

		if (is_object($object) && !empty($object->element) && in_array($object->element, array('product', 'service'), true)) {
			$langs->load("slycustom@slycustom");
		}
		return 0;
	}
}

