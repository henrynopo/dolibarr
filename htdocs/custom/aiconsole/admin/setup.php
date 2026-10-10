<?php
/* Copyright (C) 2026  HaoSG Group
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
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       htdocs/custom/aiconsole/admin/setup.php
 * \ingroup    aiconsole
 * \brief      Standalone host page of the AI console.
 *
 * This file is only the frame: it boots Dolibarr, states the contract variables and calls
 * the renderer in _console.inc.php. Every line of console logic - the section registry, the
 * POST actions, the read-only status tables, the MCP log tool switch - lives in that
 * separate file, so that "what belongs to the page frame and what does not" is decided by
 * the file boundary instead of by whoever reads it next.
 *
 * This page is the module's own configuration page (config_page_url in the descriptor),
 * reachable from the module list "Configure" button and by URL. The module publishes no
 * left menu entry of its own: it is an admin-only settings module, and the module list
 * button is how Dolibarr expects such a module to be reached. v1.3.0 removed the old
 * "Tools > AI Console" tree for that reason.
 */

// This page has exactly one write action (the MCP log tool switch), so CSRF protection
// is forced for GET as well as POST. Must be defined before main.inc.php.
if (!defined('CSRFCHECK_WITH_TOKEN')) {
	define('CSRFCHECK_WITH_TOKEN', '1');
}

// Load Dolibarr environment. main.inc.php is looked up rather than hardcoded at
// '../../main.inc.php': this file lives three levels down (custom/<module>/admin/),
// so the correct depth depends on where the module is deployed. Probing upwards is
// the convention already used by slycustom, sghr, sfrs_reports and odooconnector.
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php failed for AI Console setup.php');
}
/**
 * @var DoliDB $db
 * @var Conf $conf
 * @var User $user
 * @var Translate $langs
 */
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array('admin', 'other'));

// Access control. The core ai admin pages gate on $user->admin (not on a module right),
// so this page mirrors that exactly. This check runs BEFORE llxHeader() so a denied user
// gets the core "access denied" page rather than half a page followed by one.
if (!$user->admin) {
	accessforbidden();
}
if (!isModEnabled('aiconsole')) {
	accessforbidden('Module AI Console not activated.');
}

// GETPOST filters alone are not enough for backtopage: it lands in an href attribute,
// and neither 'alpha' nor 'nohtml' rejects a 'javascript:' scheme. Core uses 'alpha'
// (which also strips the slashes, making the link useless - see the audit table in
// README). This accepts only a local absolute path, and escapes it on output.
$backtopage = GETPOST('backtopage', 'nohtml');
if (!preg_match('/^\/[A-Za-z0-9\-\_\.\/%\?&=+:@]*$/', (string) $backtopage)) {
	$backtopage = '';
}

/*
 * Contract with the renderer. See _console.inc.php for the full description.
 *
 * Every section link is built on this page's own URL, so switching sections always
 * comes back here - the console is a self-contained module with a page of its own, not
 * a view embedded in some other module's setup screen.
 */
$aiconsoleBaseUrl = DOL_URL_ROOT.'/custom/aiconsole/admin/setup.php';
$aiconsoleExtraParams = array();
if ($backtopage !== '') {
	$aiconsoleExtraParams['backtopage'] = $backtopage;
}

// Handles the POST actions and queues their messages. It must run before
// llxHeader(), which is what flushes those messages into the page.
require_once DOL_DOCUMENT_ROOT.'/custom/aiconsole/admin/_console.inc.php';


/*
 * View
 */

$help_url = '';
$title = 'AiConsoleTitle';

// Signature: ($head, $title, $help_url, $target, $disablejs, $disablehead,
// $arrayofjs, $arrayofcss, $morequerystring, $morecssonbody, $replacemainareaby, ...)
// The body class goes in slot 10. Slot 11 REPLACES the whole main area with its own
// string, so a page there renders as nothing but that literal text.
llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-aiconsole page-admin');

$backurl = $backtopage ? $backtopage : dolBuildUrl(DOL_URL_ROOT.'/admin/modules.php', array('restore_lastsearch_values' => 1));
$linkback = '<a href="'.dol_escape_htmltag($backurl).'">'.img_picto($langs->trans('BackToModuleList'), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans('BackToModuleList').'</span></a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

aiconsoleRenderConsole();

// Page end
print dol_get_fiche_end();

llxFooter();

$db->close();