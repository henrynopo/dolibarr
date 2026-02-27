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
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/slycustom/builddoc_order.php
 *	\ingroup    slycustom
 *	\brief      Build order document with optional sales terms (zero core modification).
 *              Receives POST from order card builddoc form when "Include sales terms" is checked,
 *              sets moreparams, then includes core/actions_builddoc.inc.php and redirects back.
 */

// Load Dolibarr environment
require '../main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/commande/modules_commande.php';

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

if (empty($id) || $action !== 'builddoc') {
	header('Location: '.DOL_URL_ROOT.'/commande/card.php'.($id ? '?id='.$id : ''));
	exit;
}

// Permission (same as order card)
$result = restrictedArea($user, 'commande', $id, '', 'commande');
if ($result < 0) {
	header('Location: '.DOL_URL_ROOT.'/commande/card.php?id='.$id);
	exit;
}

if ((isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'POST') || !GETPOSTINT('add_terms')) {
	header('Location: '.DOL_URL_ROOT.'/commande/card.php?id='.$id.'#builddoc');
	exit;
}

// Token check
if (!empty($conf->global->MAIN_SECURITY_CSRF_WITH_TOKEN)) {
	$token = GETPOST('token', 'aZ09');
	if (empty($token) || !dol_verify_token($token, 'builddoc')) {
		$langs->load('errors');
		setEventMessages($langs->trans('ErrorCSRFInvalid'), null, 'errors');
		header('Location: '.DOL_URL_ROOT.'/commande/card.php?id='.$id.'#builddoc');
		exit;
	}
}

$object = new Commande($db);
$ret = $object->fetch($id);
if ($ret <= 0) {
	setEventMessages($object->error, $object->errors, 'errors');
	header('Location: '.DOL_URL_ROOT.'/commande/card.php?id='.$id.'#builddoc');
	exit;
}
$object->fetch_thirdparty();

// Validate add_terms_template (same logic as SLY builddocMoreParams)
$terms_base = DOL_DATA_ROOT.'/mycompany/terms';
$tpl = GETPOST('add_terms_template', 'alphanohtml');
if ($tpl === '' || strpos($tpl, '..') !== false || !preg_match('/^([a-zA-Z0-9_\-]+\/)?[a-zA-Z0-9_\.\-]+\.pdf$/i', $tpl)) {
	$langs->load('slycustom@slycustom');
	$errmsg = $langs->trans('SLYCUSTOM_TERMS_NO_TEMPLATE');
	if ($errmsg === 'SLYCUSTOM_TERMS_NO_TEMPLATE') {
		$errmsg = 'No sales terms template for this language';
	}
	setEventMessages($errmsg, null, 'errors');
	header('Location: '.DOL_URL_ROOT.'/commande/card.php?id='.$id.'#builddoc');
	exit;
}

$full_path = $terms_base.'/'.$tpl;
$real_base = realpath(dol_osencode($terms_base));
$real_path = @realpath(dol_osencode($full_path));
if ($real_base === false || $real_path === false || !is_file($real_path)) {
	$langs->load('slycustom@slycustom');
	$errmsg = $langs->trans('SLYCUSTOM_TERMS_NO_TEMPLATE');
	if ($errmsg === 'SLYCUSTOM_TERMS_NO_TEMPLATE') {
		$errmsg = 'No sales terms template for this language';
	}
	setEventMessages($errmsg, null, 'errors');
	header('Location: '.DOL_URL_ROOT.'/commande/card.php?id='.$id.'#builddoc');
	exit;
}
$base_norm = str_replace('\\', '/', $real_base);
$path_norm = str_replace('\\', '/', $real_path);
if (strpos($path_norm, $base_norm) !== 0) {
	$langs->load('slycustom@slycustom');
	$errmsg = $langs->trans('SLYCUSTOM_TERMS_NO_TEMPLATE');
	if ($errmsg === 'SLYCUSTOM_TERMS_NO_TEMPLATE') {
		$errmsg = 'No sales terms template for this language';
	}
	setEventMessages($errmsg, null, 'errors');
	header('Location: '.DOL_URL_ROOT.'/commande/card.php?id='.$id.'#builddoc');
	exit;
}

// Set context for actions_builddoc.inc.php
$usercancreate = $user->hasRight('commande', 'creer');
$usercangeneretedoc = (!getDolGlobalString('MAIN_USE_ADVANCED_PERMS') || $user->hasRight('commande', 'order_advance', 'generetedoc'));
$permissiontoadd = $usercancreate;
$upload_dir = !empty($conf->commande->multidir_output[$object->entity]) ? $conf->commande->multidir_output[$object->entity] : $conf->commande->dir_output;
$hidedetails = GETPOSTINT('hidedetails') ? GETPOSTINT('hidedetails') : (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS') ? 1 : 0);
$hidedesc = GETPOSTINT('hidedesc') ? GETPOSTINT('hidedesc') : (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_DESC') ? 1 : 0);
$hideref = GETPOSTINT('hideref') ? GETPOSTINT('hideref') : (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_REF') ? 1 : 0);
$moreparams = array('add_terms' => 1, 'add_terms_template' => $tpl);

// Use model from form so generateDocument uses it
$modelpost = GETPOST('model', 'alpha');
if ($modelpost !== '') {
	$object->setDocModel($user, $modelpost);
	$object->model_pdf = $modelpost;
}

include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';

// Redirect back to order card (core include does not redirect when donotredirect is not set in some flows)
header('Location: '.DOL_URL_ROOT.'/commande/card.php?id='.$id.'#builddoc');
exit;
