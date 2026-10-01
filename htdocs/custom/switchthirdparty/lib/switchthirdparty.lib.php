<?php
/* changetiers
 * Copyright (C) 2018       Inovea-conseil.com     <info@inovea-conseil.com>
 */

/**
 * \file    lib/switchthirdparty.lib.php
 * \ingroup switchthirdparty
 * \brief   changetiers
 *
 * Show admin header
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function switchthirdpartyPrepareHead()
{
	global $langs, $conf;

	$langs->load("switchthirdparty@switchthirdparty");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/switchthirdparty/admin/admin.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;
        
	complete_head_from_modules($conf, $langs, null, $head, $h, 'switchthirdparty');

	return $head;
}