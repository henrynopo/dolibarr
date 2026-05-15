<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

$res = 0;
if (!empty($res) && !empty($_GET["dol_ajax"])) {
	echo $res;
	exit;
}

// Try to locate main.inc.php for both repo layout (htdocs/...) and deployed layout (/custom under webroot)
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../main.inc.php')) {
	$res = @include __DIR__.'/../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php failed for SLY Custom setup.php');
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';

$form = new Form($db);
$formadmin = new FormAdmin($db);

if (!$user->admin) {
	accessforbidden();
	exit;
}

$langs->loadLangs(array("admin", "slycustom@slycustom"));

// Ensure SLY PDF templates and module models path are registered (so Vendor Invoice etc. list SLY templates)
if (isModEnabled('slycustom')) {
	require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/core/modules/modSlyCustom.class.php';
	$moduleSly = new modSlyCustom($db);
	$moduleSly->syncDocumentModels();
	$moduleSly->syncModulePartsModels();
	$moduleSly->syncMenuPrefixes(); // Ensure Search Order and SLY Exports menu icons in left menu
		// Sync hook contexts from module descriptor to DB so new contexts (e.g. formfile for "Include sales terms") work without re-enabling the module
		$descriptorHooks = isset($moduleSly->module_parts['hooks']['data']) && is_array($moduleSly->module_parts['hooks']['data'])
			? $moduleSly->module_parts['hooks']['data'] : array();
		$hooksVal = dolibarr_get_const($db, 'MAIN_MODULE_SLYCUSTOM_HOOKS', 0);
		$currentArr = (is_string($hooksVal) && $hooksVal !== '') ? json_decode($hooksVal, true) : array();
		if (!is_array($currentArr)) {
			$currentArr = array();
		}
		$merged = array_unique(array_merge($currentArr, $descriptorHooks));
		if (count($merged) > count($currentArr)) {
			dolibarr_set_const($db, 'MAIN_MODULE_SLYCUSTOM_HOOKS', json_encode(array_values($merged)), 'chaine', 0, '', 0);
		}
	}

$action = GETPOST('action', 'aZ09');
$error = 0;
$backtopage = GETPOST('backtopage', 'none');
$save_lastsearch_values = GETPOST('save_lastsearch_values', 'int');
$urlparams = array();
if ($save_lastsearch_values) {
	$urlparams['save_lastsearch_values'] = '1';
}
if ($backtopage !== '' && $backtopage !== null) {
	$urlparams['backtopage'] = $backtopage;
}
$urlparams_str = empty($urlparams) ? '' : ('&'.http_build_query($urlparams));

// Parameters: key => array('label' => lang key, 'type' => 'chaine'|'yesno', 'css' => ..., 'tooltip' => lang key)
$arrayofparameters = array(
	'API_KEY_SHIPSGO' => array(
		'label' => 'API_KEY_SHIPSGO',
		'type' => 'chaine',
		'css' => 'minwidth400',
		'tooltip' => 'API_KEY_SHIPSGOTooltip',
	),
	'SLYCUSTOM_SHIPPING_ENABLE_UNVALIDATE' => array(
		'label' => 'SLYCUSTOM_SHIPPING_ENABLE_UNVALIDATE',
		'type' => 'yesno',
		'tooltip' => 'SLYCUSTOM_SHIPPING_ENABLE_UNVALIDATETooltip',
	),
);

/*
 * Actions
 */
if ($action == 'sync_sly_boxes') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/core/modules/modSlyCustom.class.php';
		$module = new modSlyCustom($db);
		$err = $module->insert_boxes('newboxdefonly');
		if ($err == 0) {
			setEventMessages($langs->trans("SLYCUSTOM_BOXES_SYNCED"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?tab=boxes'.$urlparams_str);
	exit;
}
if ($action == 'sync_menu_icons') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/core/modules/modSlyCustom.class.php';
		$module = new modSlyCustom($db);
		$err = $module->syncMenuPrefixes();
		if ($err == 0) {
			setEventMessages($langs->trans("SLYCUSTOM_MENU_ICONS_SYNCED"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?tab=general'.$urlparams_str);
	exit;
}
if ($action == 'sync_sly_pdf_models') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/core/modules/modSlyCustom.class.php';
		$module = new modSlyCustom($db);
		$err = $module->syncDocumentModels();
		if ($err == 0) {
			setEventMessages($langs->trans("SLYCUSTOM_PDF_MODELS_SYNCED"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?tab=pdf'.$urlparams_str);
	exit;
}

// Terms & conditions: upload, delete, or migrate (legacy cgv/ → terms/)
$terms_base_dir = DOL_DATA_ROOT.'/mycompany/terms';
$terms_base_dir_old = DOL_DATA_ROOT.'/mycompany/cgv'; // legacy Rubis path, read-only fallback

if ($action == 'upload_terms' && GETPOST('token', 'none') === newToken()) {
	$terms_lang = GETPOST('terms_lang', 'aZ09');
	$terms_upload_mode = GETPOST('terms_upload_mode', 'aZ'); // 'new' or 'overwrite'
	$terms_filename = trim(GETPOST('terms_filename', 'alphanohtml'));
	$terms_overwrite_file = GETPOST('terms_overwrite_file', 'alphanohtml');

	$target_dir = ($terms_lang !== '') ? $terms_base_dir.'/'.dol_sanitizeFileName($terms_lang) : $terms_base_dir;
	$existing_pdfs = array();
	if (is_dir(dol_osencode($target_dir))) {
		$list = dol_dir_list($target_dir, 'files', 0, '', array(), 'name', SORT_ASC, 0);
		foreach ($list as $e) {
			if (preg_match('/\.pdf$/i', $e['name'])) {
				$existing_pdfs[] = $e['name'];
			}
		}
	}

	$target_filename = '';
	if ($terms_upload_mode === 'overwrite' && preg_match('/^[a-zA-Z0-9_\.\-]+\.pdf$/i', $terms_overwrite_file) && in_array($terms_overwrite_file, $existing_pdfs, true)) {
		$target_filename = $terms_overwrite_file;
	} elseif ($terms_upload_mode === 'new' && preg_match('/^[a-zA-Z0-9_\.\-]+\.pdf$/i', $terms_filename)) {
		$target_filename = $terms_filename;
	}

	if ($target_filename === '' && !empty($_FILES['terms_pdf']['name'])) {
		if ($terms_upload_mode === 'overwrite' && empty($existing_pdfs)) {
			setEventMessages($langs->trans("SLYCUSTOM_TERMS_NO_FILE_TO_OVERWRITE"), null, 'errors');
		} elseif ($terms_upload_mode === 'new' && $terms_filename === '') {
			setEventMessages($langs->trans("SLYCUSTOM_TERMS_FILENAME_REQUIRED"), null, 'errors');
		} else {
			setEventMessages($langs->trans("SLYCUSTOM_TERMS_UPLOAD_CHOOSE_MODE"), null, 'errors');
		}
	} elseif (isset($_FILES['terms_pdf']) && $_FILES['terms_pdf']['name'] && $target_filename !== '') {
		if (!preg_match('/\.pdf$/i', $_FILES['terms_pdf']['name'])) {
			setEventMessages($langs->trans("ErrorBadFormat"), null, 'errors');
		} else {
			if (!dol_is_dir($target_dir)) {
				dol_mkdir($target_dir);
			}
			$target_file = $target_dir.'/'.$target_filename;
			$result = dol_move_uploaded_file($_FILES['terms_pdf']['tmp_name'], $target_file, 1, 0, isset($_FILES['terms_pdf']['error']) ? $_FILES['terms_pdf']['error'] : 0);
			if ($result > 0) {
				setEventMessages($langs->trans("SLYCUSTOM_CGV_UPLOADED"), null, 'mesgs');
			} else {
				setEventMessages($langs->trans("ErrorFailedToSaveFile"), null, 'errors');
			}
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?tab=rubis'.$urlparams_str);
	exit;
}
if ($action == 'delete_cgv' && GETPOST('token', 'none') === newToken()) {
	$terms_lang = GETPOST('terms_lang', 'aZ09');
	$terms_filename = GETPOST('terms_filename', 'alphanohtml');
	$targets = array();
	if (preg_match('/^[a-zA-Z0-9_\.\-]+\.pdf$/i', $terms_filename)) {
		$dir = ($terms_lang !== '') ? $terms_base_dir.'/'.dol_sanitizeFileName($terms_lang) : $terms_base_dir;
		$targets[] = $dir.'/'.$terms_filename;
	} else {
		$targets[] = ($terms_lang !== '') ? $terms_base_dir.'/'.dol_sanitizeFileName($terms_lang).'/terms.pdf' : $terms_base_dir.'/terms.pdf';
		$targets[] = ($terms_lang !== '') ? $terms_base_dir_old.'/'.dol_sanitizeFileName($terms_lang).'/cgv.pdf' : $terms_base_dir_old.'/cgv.pdf';
	}
	$done = false;
	foreach ($targets as $target_file) {
		$target_file_os = dol_osencode($target_file);
		if (is_file($target_file_os) && dol_delete_file($target_file_os)) {
			$done = true;
			break;
		}
	}
	if ($done) {
		setEventMessages($langs->trans("SLYCUSTOM_CGV_DELETED"), null, 'mesgs');
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?tab=rubis'.$urlparams_str);
	exit;
}
if ($action == 'migrate_terms' && GETPOST('token', 'none') === newToken()) {
	$terms_lang = GETPOST('terms_lang', 'aZ09');
	$src = ($terms_lang !== '') ? $terms_base_dir_old.'/'.dol_sanitizeFileName($terms_lang).'/cgv.pdf' : $terms_base_dir_old.'/cgv.pdf';
	$dst_dir = ($terms_lang !== '') ? $terms_base_dir.'/'.dol_sanitizeFileName($terms_lang) : $terms_base_dir;
	$dst = $dst_dir.'/terms.pdf';
	$src_os = dol_osencode($src);
	$dst_os = dol_osencode($dst);
	if (is_file($src_os)) {
		if (!dol_is_dir(dol_osencode($dst_dir))) {
			dol_mkdir($dst_dir);
		}
		if (@copy($src_os, $dst_os)) {
			dol_delete_file($src_os);
			setEventMessages($langs->trans("SLYCUSTOM_TERMS_MIGRATED"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("ErrorFailedToSaveFile"), null, 'errors');
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?tab=rubis'.$urlparams_str);
	exit;
}
if ($action == 'set_default_terms' && GETPOST('token', 'none') === newToken()) {
	$terms_lang = GETPOST('terms_lang', 'aZ09');
	$terms_filename = GETPOST('terms_filename', 'alphanohtml');
	if (preg_match('/^[a-zA-Z0-9_\.\-]+\.pdf$/i', $terms_filename)) {
		$json = getDolGlobalString('SLYCUSTOM_TERMS_DEFAULT_BY_LANG');
		$arr = is_string($json) ? json_decode($json, true) : array();
		if (!is_array($arr)) {
			$arr = array();
		}
		$arr[$terms_lang] = $terms_filename;
		dolibarr_set_const($db, 'SLYCUSTOM_TERMS_DEFAULT_BY_LANG', json_encode($arr), 'chaine', 0, '', $conf->entity);
		setEventMessages($langs->trans("SLYCUSTOM_TERMS_DEFAULT_SET"), null, 'mesgs');
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?tab=rubis'.$urlparams_str);
	exit;
}

if ($action == 'update' && !GETPOST('cancel', 'alpha')) {
	$db->begin();
	foreach ($arrayofparameters as $key => $val) {
		if (!GETPOSTISSET($key)) {
			continue;
		}
		if (isset($val['type']) && $val['type'] == 'yesno') {
			$val_const = GETPOSTINT($key);
		} else {
			$val_const = GETPOST($key, 'alphanohtml');
		}
		$result = dolibarr_set_const($db, $key, $val_const, 'chaine', 0, '', $conf->entity);
		if ($result < 0) {
			$error++;
			break;
		}
	}
	if (!$error) {
		$db->commit();
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($langs->trans("SetupNotSaved"), null, 'errors');
	}
	$action = '';
}

/*
 * View – tabbed by feature
 */
$tab = GETPOST('tab', 'aZ09');
if (!in_array($tab, array('general', 'pdf', 'boxes', 'rubis'), true)) {
	$tab = 'general';
}
// Ensure module lang is loaded so tab labels are translated
$langs->load("slycustom@slycustom", 0, 0, '', 0, 1);
// Tab labels: use trans() and fallback to inline strings when key is not translated (no dependency on file path)
$tab_fallbacks = array(
	'en_US' => array('General', 'PDF templates', 'Dashboard', 'Terms & conditions'),
	'zh_CN' => array('常规', 'PDF 模板', '仪表盘', '销售条款'),
);
$tab_keys = array('SLYCUSTOM_TAB_GENERAL', 'SLYCUSTOM_TAB_PDF', 'SLYCUSTOM_TAB_BOXES', 'SLYCUSTOM_TAB_RUBIS');
$langcode = (!empty($langs->defaultlang) ? $langs->defaultlang : 'en_US');
if (!isset($tab_fallbacks[$langcode])) {
	$langcode = 'en_US';
}
$tab_labels = array();
for ($i = 0; $i < 4; $i++) {
	$t = $langs->trans($tab_keys[$i]);
	$tab_labels[$i] = ($t !== $tab_keys[$i] && $t !== '') ? $t : $tab_fallbacks[$langcode][$i];
}
// Use absolute URL so tab links work in any environment (subdir, rewrite, etc.)
$taburl = DOL_URL_ROOT.'/custom/slycustom/admin/setup.php';
$head = array();
$head[0] = array($taburl.'?tab=general', $tab_labels[0], 'general');
$head[1] = array($taburl.'?tab=pdf', $tab_labels[1], 'pdf');
$head[2] = array($taburl.'?tab=boxes', $tab_labels[2], 'boxes');
$head[3] = array($taburl.'?tab=rubis', $tab_labels[3], 'rubis');

$page_name = "SLYCustomSetup";
$help_url = '';
llxHeader('', $langs->trans($page_name), $help_url, '', 0, 0, '', '', '', 'mod-slycustom page-admin_setup');

$backurl = ($backtopage !== '' && $backtopage !== null)
	? $backtopage
	: (DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1');
$linkback = '<a href="'.dol_escape_htmltag($backurl).'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Custom tab bar (always visible; some themes hide dol_get_fiche_head tabs on setup pages)
$tab_ids = array('general', 'pdf', 'boxes', 'rubis');
print '<!-- SLYCUSTOM_SETUP_TABS_V2 tab=('.dol_escape_htmltag($tab).') -->'."\n";
print '<div class="slycustom-setup-tabbar" style="margin:10px 0 0 0;padding:0 0 8px 0;border-bottom:1px solid #bbb;clear:both;">'."\n";
// Escape only < and > for tab labels so "Terms & conditions" displays correctly
$tab_esc = function ($s) { return str_replace(array('<', '>'), array('&lt;', '&gt;'), $s); };
foreach ($tab_ids as $i => $tid) {
	$label = isset($tab_labels[$i]) ? $tab_labels[$i] : $tid;
	$url = $taburl.'?tab='.$tid.$urlparams_str;
	$active = ($tid === $tab);
	$css = $active ? 'font-weight:bold;background:#e8e8e8;border:1px solid #bbb;border-bottom:1px solid #e8e8e8;margin-bottom:-1px;' : 'background:#f5f5f5;border:1px solid transparent;';
	print '<a href="'.dol_escape_htmltag($url).'" style="'.$css.'display:inline-block;padding:8px 14px;margin-right:2px;text-decoration:none;color:inherit;border-radius:4px 4px 0 0;">'.$tab_esc($label).'</a>'."\n";
}
print '</div>'."\n";
print '<div class="tabBar tabBarWithBottom slycustom-setup-content" style="padding-top:16px; max-width:960px;">'."\n";

// Tab: General (ShipsGo, Unvalidate)
if ($tab == 'general') {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("SLYCUSTOM_GENERAL_OPTIONS").'</td></tr>';
	if ($action == 'edit') {
		print '<tr><td colspan="2">';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update">';
		print '<input type="hidden" name="tab" value="general">';
		if ($save_lastsearch_values) {
			print '<input type="hidden" name="save_lastsearch_values" value="1">';
		}
		if ($backtopage !== '' && $backtopage !== null) {
			print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
		}
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';
		foreach ($arrayofparameters as $key => $val) {
			$tooltip = isset($val['tooltip']) ? $langs->trans($val['tooltip']) : '';
			print '<tr class="oddeven"><td>';
			print $form->textwithpicto($langs->trans($val['label']), $tooltip);
			print '</td><td>';
			if (isset($val['type']) && $val['type'] == 'yesno') {
				print $form->selectyesno($key, getDolGlobalString($key, 1), 1);
			} else {
				$css = isset($val['css']) ? $val['css'] : 'minwidth200';
				$current = getDolGlobalString($key);
				print '<input type="text" name="'.$key.'" class="flat '.$css.'" value="'.dol_escape_htmltag($current).'" autocomplete="off">';
			}
			print '</td></tr>';
		}
		print '</table>';
		print '<br><div class="center">';
		print '<input class="button button-save" type="submit" value="'.$langs->trans("Save").'"> ';
		print '<input class="button button-cancel" type="submit" name="cancel" value="'.$langs->trans("Cancel").'">';
		print '</div>';
		print '</form>';
		print '</td></tr>';
	} else {
		print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';
		foreach ($arrayofparameters as $key => $val) {
			$tooltip = isset($val['tooltip']) ? $langs->trans($val['tooltip']) : '';
			print '<tr class="oddeven"><td>';
			print $form->textwithpicto($langs->trans($val['label']), $tooltip);
			print '</td><td>';
			if (isset($val['type']) && $val['type'] == 'yesno') {
				$v = getDolGlobalString($key, 1);
				print $v ? $langs->trans("Yes") : $langs->trans("No");
			} else {
				$v = getDolGlobalString($key);
				print $v ? dol_escape_htmltag($v) : '<span class="opacitymedium">'.$langs->trans("None").'</span>';
			}
			print '</td></tr>';
		}
		print '<tr><td colspan="2"><div class="tabsAction">';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?tab=general&action=edit&token='.newToken().$urlparams_str.'">'.$langs->trans("Modify").'</a>';
		print '</div></td></tr>';
	}
	// Sync menu icons (Search Order, SLY Exports) in left menu
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("SLYCUSTOM_MENU_ICONS").'</td></tr>';
	print '<tr class="oddeven"><td colspan="2">';
	print $langs->trans("SLYCUSTOM_SYNC_MENU_ICONS_TOOLTIP");
	print '<br><br><div class="tabsAction">';
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="sync_menu_icons">';
	print '<input type="hidden" name="tab" value="general">';
	print '<input type="submit" class="butAction" value="'.$langs->trans("SLYCUSTOM_SYNC_MENU_ICONS").'">';
	print '</form>';
	print '</div></td></tr>';
	print '</table>';
	print '</div>';
}

// Tab: PDF templates
if ($tab == 'pdf') {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("SLYCUSTOM_PDF_TEMPLATES_SECTION").'</td></tr>';
	print '<tr class="oddeven"><td colspan="2">';
	print $langs->trans("SLYCUSTOM_SYNC_PDF_MODELS_TOOLTIP");
	print '<br><br><div class="tabsAction">';
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="sync_sly_pdf_models">';
	print '<input type="hidden" name="tab" value="pdf">';
	print '<input type="submit" class="butAction" value="'.$langs->trans("SLYCUSTOM_SYNC_PDF_MODELS").'">';
	print '</form>';
	print '</div></td></tr>';
	print '</table>';
	print '</div>';
}

// Tab: Dashboard boxes
if ($tab == 'boxes') {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("SLYCUSTOM_DASHBOARD_BOXES").'</td></tr>';
	print '<tr class="oddeven"><td colspan="2">';
	print $langs->trans("SLYCUSTOM_DASHBOARD_BOXES_DESC");
	print '<br><br>';
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.DOL_URL_ROOT.'/admin/boxes.php?mainmenu=home">'.$langs->trans("SLYCUSTOM_GO_TO_DASHBOARD_SETUP").'</a>';
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline; margin-left:4px;">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="sync_sly_boxes">';
	print '<input type="hidden" name="tab" value="boxes">';
	print '<input type="submit" class="butAction" value="'.$langs->trans("SLYCUSTOM_SYNC_BOXES").'">';
	print '</form>';
	print '</div>';
	print '<br><span class="opacitymedium">'.$langs->trans("SLYCUSTOM_SYNC_BOXES_TOOLTIP").'</span>';
	print '</td></tr>';
	print '</table>';
	print '</div>';
}

// Tab: 销售条款 / Terms & conditions (upgraded from Rubis module)
if ($tab == 'rubis') {
	$langs->load("errors");
	$langs->load("slycustom@slycustom", 0, 0, '', 0, 1);
	print '<style type="text/css">';
	print '.sly-terms-upload-block{ margin-top:1em; padding-top:0.75em; border-top:1px solid #ddd; clear:both; }';
	print '.sly-terms-upload-block .sly-upload-line{ margin-top:0.35em; }';
	print '.sly-terms-upload-block .sly-upload-line:first-child{ margin-top:0; }';
	print '.sly-terms-upload-block label{ margin-right:0.5em; }';
	print '.sly-terms-upload-block input[type=file]{ margin-right:0.5em; }';
	print '.sly-terms-legacy-actions form{ display:inline; margin-right:0.25em; }';
	print '</style>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	// Only escape < and > so "Terms & conditions" and "<lang>" display correctly (avoid &amp; / &lt;&gt; double-encoding)
	$esc = function ($s) { return str_replace(array('<', '>'), array('&lt;', '&gt;'), $s); };
	print '<tr class="liste_titre"><td colspan="2">'.$esc($langs->trans("SLYCUSTOM_CGV_SECTION")).'</td></tr>';
	print '<tr class="oddeven"><td colspan="2"><p class="opacitymedium">'.$esc($langs->trans("SLYCUSTOM_RUBIS_UPGRADE_DESC")).'</p></td></tr>';
	print '<tr class="oddeven"><td colspan="2">'.$esc($langs->trans("SLYCUSTOM_CGV_DESC")).'</td></tr>';
	print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$esc($langs->trans("SLYCUSTOM_CGV_OPTIONS_NOTE")).'</span></td></tr>';
	// Current files: default + per-language. Support multiple PDFs per folder and SLYCUSTOM_TERMS_DEFAULT_BY_LANG.
	$multilang = getDolGlobalInt('MAIN_MULTILANGS');
	$terms_default_by_lang = array();
	$json_default = getDolGlobalString('SLYCUSTOM_TERMS_DEFAULT_BY_LANG');
	if ($json_default !== '' && $json_default !== null) {
		$decoded = json_decode($json_default, true);
		if (is_array($decoded)) {
			$terms_default_by_lang = $decoded;
		}
	}
	$default_filename = isset($terms_default_by_lang['']) ? $terms_default_by_lang[''] : 'terms.pdf';
	// Default (single language) block: list/table mode
	$default_pdfs = array();
	if (is_dir(dol_osencode($terms_base_dir))) {
		$all = dol_dir_list($terms_base_dir, 'files', 0, '', array(), 'name', SORT_ASC, 0);
		foreach ($all as $e) {
			if (preg_match('/\.pdf$/i', $e['name'])) {
				$default_pdfs[] = $e['name'];
			}
		}
	}
	$default_path_old = $terms_base_dir_old.'/cgv.pdf';
	$default_exists_old = is_file(dol_osencode($default_path_old));
	print '</table></div>';
	$refresh_url = $_SERVER["PHP_SELF"].'?tab=rubis&token='.newToken();
	print load_fiche_titre($langs->trans("SLYCUSTOM_CGV_DEFAULT").' <span class="opacitymedium">(terms/)</span>', '<a class="butAction" href="'.dol_escape_htmltag($refresh_url).'">'.img_picto($langs->trans("Refresh"), 'refresh').'</a>', 'file-upload', 0, '', 'sly-terms-block-default');
	if ($default_exists_old && count($default_pdfs) === 0) {
		print '<div class="sly-terms-legacy-actions" style="margin-bottom:8px;">';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="migrate_terms">';
		print '<input type="hidden" name="tab" value="rubis">';
		print '<input type="hidden" name="terms_lang" value="">';
		print '<input type="submit" class="butAction" value="'.$langs->trans("SLYCUSTOM_TERMS_MIGRATE").'">';
		print '</form> ';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;" onsubmit="return confirm(\''.dol_escape_js($langs->trans("SLYCUSTOM_CGV_CONFIRM_DELETE")).'\');">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="delete_cgv">';
		print '<input type="hidden" name="tab" value="rubis">';
		print '<input type="submit" class="butActionDelete" value="'.$langs->trans("Delete").'">';
		print '</form>';
		print '</div>';
	}
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("Documents2").'</td><td class="right">'.$langs->trans("Size").'</td><td class="center">'.$langs->trans("Date").'</td><td class="right"></td></tr>';
	if (count($default_pdfs) > 0) {
		foreach ($default_pdfs as $fn) {
			$os_path = dol_osencode($terms_base_dir.'/'.$fn);
			$sz = @filesize($os_path);
			$size_show = ($sz !== false && $sz > 0) ? round($sz / 1024).' KB' : '-';
			$mtime = @filemtime($os_path);
			$date_show = ($mtime !== false) ? dol_print_date($mtime, 'dayhour') : '-';
			$doc_url = DOL_URL_ROOT.'/document.php?modulepart=mycompany&file=terms/'.urlencode($fn).'&attachment=0';
			$is_def = ($fn === $default_filename);
			print '<tr class="oddeven">';
			print '<td>';
			print img_mime($fn, dol_escape_htmltag($fn), 'inline-block valignmiddle paddingright');
			print '<a class="alink" href="'.dol_escape_htmltag($doc_url).'" target="_blank" rel="noopener">'.dol_escape_htmltag($fn).'</a>';
			if ($is_def) {
				print ' <span class="opacitymedium">('.$langs->trans("SLYCUSTOM_TERMS_DEFAULT_TEMPLATE").')</span>';
			}
			print '</td>';
			print '<td class="right">'.$size_show.'</td>';
			print '<td class="center">'.$date_show.'</td>';
			print '<td class="right">';
			if (!$is_def) {
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="set_default_terms">';
				print '<input type="hidden" name="tab" value="rubis">';
				print '<input type="hidden" name="terms_lang" value="">';
				print '<input type="hidden" name="terms_filename" value="'.dol_escape_htmltag($fn).'">';
				print '<input type="submit" class="butAction" value="'.$langs->trans("SLYCUSTOM_TERMS_SET_DEFAULT").'"> ';
				print '</form>';
			}
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;" onsubmit="return confirm(\''.dol_escape_js($langs->trans("SLYCUSTOM_CGV_CONFIRM_DELETE")).'\');">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="delete_cgv">';
			print '<input type="hidden" name="tab" value="rubis">';
			print '<input type="hidden" name="terms_lang" value="">';
			print '<input type="hidden" name="terms_filename" value="'.dol_escape_htmltag($fn).'">';
			print '<input type="submit" class="butActionDelete" value="'.$langs->trans("Delete").'">';
			print '</form>';
			print '</td></tr>';
		}
	} else {
		print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}
	print '</table></div>';
	print '<div class="sly-terms-upload-block" style="margin-top:1em; padding-top:0.75em; border-top:1px solid #ddd;">';
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="upload_terms">';
	print '<input type="hidden" name="tab" value="rubis">';
	print '<input type="hidden" name="terms_lang" value="">';
	print '<div class="sly-upload-line"><span class="opacitymedium">'.$langs->trans("SLYCUSTOM_TERMS_UPLOAD_MODE").':</span> ';
	print '<label><input type="radio" name="terms_upload_mode" value="new" checked> '.$langs->trans("SLYCUSTOM_TERMS_SAVE_AS_NEW").'</label> ';
	print '<label><input type="radio" name="terms_upload_mode" value="overwrite"> '.$langs->trans("SLYCUSTOM_TERMS_OVERWRITE").'</label></div> ';
	print '<div class="sly-upload-line terms-new-fields">'.$langs->trans("SLYCUSTOM_TERMS_FILENAME_REMARK").': <input type="text" name="terms_filename" class="flat width150" placeholder="terms_contract.pdf" pattern="[a-zA-Z0-9_.\-]+\.pdf" title="'.dol_escape_htmltag($langs->trans("SLYCUSTOM_TERMS_FILENAME_REMARK")).'"></div> ';
	print '<div class="sly-upload-line terms-overwrite-fields" style="display:none">'.$langs->trans("SLYCUSTOM_TERMS_OVERWRITE_SELECT").': <select name="terms_overwrite_file" class="flat">';
	print '<option value="">--</option>';
	foreach ($default_pdfs as $fn) {
		print '<option value="'.dol_escape_htmltag($fn).'">'.dol_escape_htmltag($fn).'</option>';
	}
	print '</select></div> ';
	print '<div class="sly-upload-line"><input type="file" name="terms_pdf" accept=".pdf" required=""> ';
	print '<input type="submit" class="butAction" value="'.$langs->trans("SLYCUSTOM_CGV_UPLOAD").'"></div>';
	print '</form></div>';
	print '<script nonce="'.getNonce().'">document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("form[enctype=\"multipart/form-data\"]").forEach(function(form){if(!form.querySelector("input[name=terms_upload_mode]"))return;var r=form.querySelectorAll("input[name=terms_upload_mode]");var n=form.querySelectorAll(".terms-new-fields");var o=form.querySelectorAll(".terms-overwrite-fields");function up(){var v=form.querySelector("input[name=terms_upload_mode]:checked");if(v&&v.value==="overwrite"){n.forEach(function(e){e.style.display="none";});o.forEach(function(e){e.style.display="block";});}else{n.forEach(function(e){e.style.display="block";});o.forEach(function(e){e.style.display="none";});}}r.forEach(function(el){el.addEventListener("change",up);});up();});});</script>';
	print '<br>';
	if ($multilang) {
		// Per-language: collect lang dirs from terms/ and cgv/, list all PDFs in terms/[lang], show default and Set as default
		$lang_dirs = array();
		foreach (array($terms_base_dir, $terms_base_dir_old) as $langdir) {
			if (!is_dir(dol_osencode($langdir))) {
				continue;
			}
			$list = dol_dir_list($langdir, 'directories', 0, '', array(), 'name', SORT_ASC, 0);
			foreach ($list as $ent) {
				$langcode = basename($ent['name']);
				$lang_dirs[$langcode] = true;
			}
		}
		ksort($lang_dirs);
		foreach (array_keys($lang_dirs) as $langcode) {
			if ($langcode === '' || $langcode === '-1' || is_numeric($langcode)) {
				continue;
			}
			$trans = ($langcode === 'auto' ? $langs->trans("AutoDetectLang") : $langs->trans("Language_".$langcode));
			if ($trans === 'Language_'.$langcode) {
				$trans = $langcode;
			}
			$lang_default = isset($terms_default_by_lang[$langcode]) ? $terms_default_by_lang[$langcode] : 'terms.pdf';
			$terms_lang_dir = $terms_base_dir.'/'.dol_sanitizeFileName($langcode);
			$lang_pdfs = array();
			if (is_dir(dol_osencode($terms_lang_dir))) {
				$all = dol_dir_list($terms_lang_dir, 'files', 0, '', array(), 'name', SORT_ASC, 0);
				foreach ($all as $e) {
					if (preg_match('/\.pdf$/i', $e['name'])) {
						$lang_pdfs[] = $e['name'];
					}
				}
			}
			$legacy_path = $terms_base_dir_old.'/'.dol_sanitizeFileName($langcode).'/cgv.pdf';
			$has_legacy = is_file(dol_osencode($legacy_path));
			$lang_refresh_url = $_SERVER["PHP_SELF"].'?tab=rubis&token='.newToken();
			print load_fiche_titre($langs->trans("SLYCUSTOM_CGV_LANG", $trans).' <span class="opacitymedium">(terms/'.$langcode.'/)</span>', '<a class="butAction" href="'.dol_escape_htmltag($lang_refresh_url).'">'.img_picto($langs->trans("Refresh"), 'refresh').'</a>', 'file-upload', 0, '', 'sly-terms-block-'.$langcode);
			if ($has_legacy && count($lang_pdfs) === 0) {
				print '<div class="sly-terms-legacy-actions" style="margin-bottom:8px;">';
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="migrate_terms">';
				print '<input type="hidden" name="tab" value="rubis">';
				print '<input type="hidden" name="terms_lang" value="'.dol_escape_htmltag($langcode).'">';
				print '<input type="submit" class="butAction" value="'.$langs->trans("SLYCUSTOM_TERMS_MIGRATE").'">';
				print '</form> ';
				print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;" onsubmit="return confirm(\''.dol_escape_js($langs->trans("SLYCUSTOM_CGV_CONFIRM_DELETE")).'\');">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="delete_cgv">';
				print '<input type="hidden" name="tab" value="rubis">';
				print '<input type="hidden" name="terms_lang" value="'.dol_escape_htmltag($langcode).'">';
				print '<input type="submit" class="butActionDelete" value="'.$langs->trans("Delete").'">';
				print '</form>';
				print '</div>';
			}
			print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
			print '<tr class="liste_titre"><td>'.$langs->trans("Documents2").'</td><td class="right">'.$langs->trans("Size").'</td><td class="center">'.$langs->trans("Date").'</td><td class="right"></td></tr>';
			if (count($lang_pdfs) > 0) {
				foreach ($lang_pdfs as $fn) {
					$os_path = dol_osencode($terms_lang_dir.'/'.$fn);
					$sz = @filesize($os_path);
					$size_show = ($sz !== false && $sz > 0) ? round($sz / 1024).' KB' : '-';
					$mtime = @filemtime($os_path);
					$date_show = ($mtime !== false) ? dol_print_date($mtime, 'dayhour') : '-';
					$doc_url = DOL_URL_ROOT.'/document.php?modulepart=mycompany&file=terms/'.urlencode($langcode).'/'.urlencode($fn).'&attachment=0';
					$is_def = ($fn === $lang_default);
					print '<tr class="oddeven">';
					print '<td>';
					print img_mime($fn, dol_escape_htmltag($fn), 'inline-block valignmiddle paddingright');
					print '<a class="alink" href="'.dol_escape_htmltag($doc_url).'" target="_blank" rel="noopener">'.dol_escape_htmltag($fn).'</a>';
					if ($is_def) {
						print ' <span class="opacitymedium">('.$langs->trans("SLYCUSTOM_TERMS_DEFAULT_TEMPLATE").')</span>';
					}
					print '</td>';
					print '<td class="right">'.$size_show.'</td>';
					print '<td class="center">'.$date_show.'</td>';
					print '<td class="right">';
					if (!$is_def) {
						print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
						print '<input type="hidden" name="token" value="'.newToken().'">';
						print '<input type="hidden" name="action" value="set_default_terms">';
						print '<input type="hidden" name="tab" value="rubis">';
						print '<input type="hidden" name="terms_lang" value="'.dol_escape_htmltag($langcode).'">';
						print '<input type="hidden" name="terms_filename" value="'.dol_escape_htmltag($fn).'">';
						print '<input type="submit" class="butAction" value="'.$langs->trans("SLYCUSTOM_TERMS_SET_DEFAULT").'"> ';
						print '</form>';
					}
					print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;" onsubmit="return confirm(\''.dol_escape_js($langs->trans("SLYCUSTOM_CGV_CONFIRM_DELETE")).'\');">';
					print '<input type="hidden" name="token" value="'.newToken().'">';
					print '<input type="hidden" name="action" value="delete_cgv">';
					print '<input type="hidden" name="tab" value="rubis">';
					print '<input type="hidden" name="terms_lang" value="'.dol_escape_htmltag($langcode).'">';
					print '<input type="hidden" name="terms_filename" value="'.dol_escape_htmltag($fn).'">';
					print '<input type="submit" class="butActionDelete" value="'.$langs->trans("Delete").'">';
					print '</form>';
					print '</td></tr>';
				}
			} else {
				print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
			}
			print '</table></div>';
			print '<div class="sly-terms-upload-block" style="margin-top:1em; padding-top:0.75em; border-top:1px solid #ddd;">';
			print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="upload_terms">';
			print '<input type="hidden" name="tab" value="rubis">';
			print '<input type="hidden" name="terms_lang" value="'.dol_escape_htmltag($langcode).'">';
			print '<div class="sly-upload-line"><span class="opacitymedium">'.$langs->trans("SLYCUSTOM_TERMS_UPLOAD_MODE").':</span> ';
			print '<label><input type="radio" name="terms_upload_mode" value="new" checked> '.$langs->trans("SLYCUSTOM_TERMS_SAVE_AS_NEW").'</label> ';
			print '<label><input type="radio" name="terms_upload_mode" value="overwrite"> '.$langs->trans("SLYCUSTOM_TERMS_OVERWRITE").'</label></div> ';
			print '<div class="sly-upload-line terms-new-fields">'.$langs->trans("SLYCUSTOM_TERMS_FILENAME_REMARK").': <input type="text" name="terms_filename" class="flat width150" placeholder="terms_contract.pdf" pattern="[a-zA-Z0-9_.\-]+\.pdf"></div> ';
			print '<div class="sly-upload-line terms-overwrite-fields" style="display:none">'.$langs->trans("SLYCUSTOM_TERMS_OVERWRITE_SELECT").': <select name="terms_overwrite_file" class="flat">';
			print '<option value="">--</option>';
			foreach ($lang_pdfs as $fn) {
				print '<option value="'.dol_escape_htmltag($fn).'">'.dol_escape_htmltag($fn).'</option>';
			}
			print '</select></div> ';
			print '<div class="sly-upload-line"><input type="file" name="terms_pdf" accept=".pdf" required=""> ';
			print '<input type="submit" class="butAction" value="'.$langs->trans("SLYCUSTOM_CGV_UPLOAD").'"></div>';
			print '</form></div>';
			print '<br>';
		}
		// Add new per-language terms (standalone section)
		print load_fiche_titre($langs->trans("SLYCUSTOM_CGV_UPLOAD_FOR_LANG"), '', 'file-upload', 0, '', 'sly-terms-upload-for-lang');
		print '<div class="sly-terms-upload-block">';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="upload_terms">';
		print '<input type="hidden" name="tab" value="rubis">';
		print '<div class="sly-upload-line">'.$langs->trans("SLYCUSTOM_CGV_UPLOAD_FOR_LANG").' ';
		print $formadmin->select_language('', 'terms_lang', 0, null, $langs->trans("SelectLanguage"));
		print '</div> ';
		print '<div class="sly-upload-line"><span class="opacitymedium">'.$langs->trans("SLYCUSTOM_TERMS_UPLOAD_MODE").':</span> ';
		print '<label><input type="radio" name="terms_upload_mode" value="new" checked> '.$langs->trans("SLYCUSTOM_TERMS_SAVE_AS_NEW").'</label> ';
		print '<label><input type="radio" name="terms_upload_mode" value="overwrite"> '.$langs->trans("SLYCUSTOM_TERMS_OVERWRITE").'</label></div> ';
		print '<div class="sly-upload-line terms-new-fields">'.$langs->trans("SLYCUSTOM_TERMS_FILENAME_REMARK").': <input type="text" name="terms_filename" class="flat width150" placeholder="terms_contract.pdf" pattern="[a-zA-Z0-9_.\-]+\.pdf"></div> ';
		print '<div class="sly-upload-line terms-overwrite-fields" style="display:none">'.$langs->trans("SLYCUSTOM_TERMS_OVERWRITE_SELECT").': <select name="terms_overwrite_file" class="flat"><option value="">--</option></select></div> ';
		print '<div class="sly-upload-line"><input type="file" name="terms_pdf" accept=".pdf" required=""> ';
		print '<input type="submit" class="butAction" value="'.$langs->trans("SLYCUSTOM_CGV_UPLOAD").'"></div>';
		print '</form>';
		print '</div>';
	}
}

print dol_get_fiche_end(0);

llxFooter();
$db->close();
