<?php

// Load Dolibase
include_once 'autoload.php';
// Load Dolibarr functions2 lib
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

global $user, $db, $conf;

if ($user->rights->langpicker->use)
{
	// Get parameters
	$lang_code = GETPOST('lang_code', 'alpha');
	$backtopage = GETPOST('backtopage', 'alpha');

	// Set lang
	if (! empty($lang_code))
	{
		$result = dol_set_user_param($db, $conf, $user, array('MAIN_LANG_DEFAULT' => $lang_code));
		if ($result > 0) {
			dolibase_redirect($backtopage);
		}
	}
}
else
{
	accessforbidden();
}
