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
// General tab: module-wide options only (feature-specific settings live on their own tabs).
$arrayofparameters = array(
	'SLYCUSTOM_SHIPPING_ENABLE_UNVALIDATE' => array(
		'label' => 'SLYCUSTOM_SHIPPING_ENABLE_UNVALIDATE',
		'type' => 'yesno',
		'tooltip' => 'SLYCUSTOM_SHIPPING_ENABLE_UNVALIDATETooltip',
	),
);
// ShipsGo tab parameters.
$shipsgoparameters = array(
	'API_KEY_SHIPSGO' => array(
		'label' => 'API_KEY_SHIPSGO',
		'type' => 'chaine',
		'css' => 'minwidth400',
		'tooltip' => 'API_KEY_SHIPSGOTooltip',
	),
	'SHIPSGO_WEBHOOK_SECRET' => array(
		'label' => 'SHIPSGO_WEBHOOK_SECRET',
		'type' => 'chaine',
		'css' => 'minwidth400',
		'tooltip' => 'SHIPSGO_WEBHOOK_SECRETTooltip',
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

// Register webhook URL with ShipsGo for the current entity.
if ($action == 'register_shipsgo_webhook') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_API.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_Update.class.php';
		$apiKey = ShipmentStatus::getApiKeyForEntity($db, (int) $conf->entity);
		if ($apiKey === '') {
			setEventMessages($langs->trans("API_KEY_SHIPSGO").': '.$langs->trans("NotConfigured"), null, 'errors');
		} else {
			$callbackUrl = ShipmentStatus::getWebhookUrl((int) $conf->entity);
			$shipsGo = new ShipsGo_API($apiKey);
			$result = $shipsGo->registerWebhook($callbackUrl);
			$httpCode = isset($result['httpCode']) ? (int) $result['httpCode'] : 0;
			$message = '';
			if (isset($result['message'])) {
				$message = $result['message'];
			} elseif (isset($result['error'])) {
				$message = $result['error'];
			}
			if ($httpCode >= 200 && $httpCode < 300) {
				setEventMessages($langs->trans("SHIPSGO_WEBHOOK_REGISTERED").' '.$callbackUrl.' ('.$message.')', null, 'mesgs');
				dol_syslog(__METHOD__.' ShipsGo webhook registered entity='.$conf->entity.' url='.$callbackUrl, LOG_INFO);
			} else {
				setEventMessages($langs->trans("SHIPSGO_WEBHOOK_REGISTER_FAILED").' HTTP '.$httpCode.' '.$message, null, 'errors');
				dol_syslog(__METHOD__.' ShipsGo webhook register failed entity='.$conf->entity.' http='.$httpCode.' response='.json_encode($result), LOG_WARNING);
			}
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?tab=shipsgo'.$urlparams_str);
	exit;
}

// Generate a fresh random webhook secret for the current entity (overwrites the existing one).
if ($action == 'generate_shipsgo_secret') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		// 64-char hex (256-bit), same format as the manually-entered secrets. random_bytes is CSPRNG.
		$newSecret = bin2hex(random_bytes(32));
		$result = dolibarr_set_const($db, 'SHIPSGO_WEBHOOK_SECRET', $newSecret, 'chaine', 0, '', $conf->entity);
		if ($result < 0) {
			setEventMessages($langs->trans("Error"), null, 'errors');
		} else {
			setEventMessages($langs->trans("SHIPSGO_WEBHOOK_SECRET_GENERATED"), null, 'mesgs');
			dol_syslog(__METHOD__.' ShipsGo webhook secret regenerated entity='.$conf->entity, LOG_INFO);
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?tab=shipsgo'.$urlparams_str);
	exit;
}

// Wise: save connection + matching + bank mapping settings for the current entity.
if ($action == 'update_wise' && !GETPOST('cancel', 'alpha')) {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		$db->begin();
		$wiseConsts = array(
			// Feature toggles (checkboxes: absent from POST = 0)
			'WISE_INCOMING_ENABLED' => (string) (int) GETPOST('WISE_INCOMING_ENABLED', 'int'),
			'WISE_OUTGOING_ENABLED' => (string) (int) GETPOST('WISE_OUTGOING_ENABLED', 'int'),
			'WISE_WEBHOOK_IPCHECK' => (string) (int) GETPOST('WISE_WEBHOOK_IPCHECK', 'int'),
			'WISE_API_TOKEN' => GETPOST('WISE_API_TOKEN', 'alphanohtml'),
			'WISE_PROFILE_ID' => GETPOST('WISE_PROFILE_ID', 'alphanohtml'),
			'WISE_SO_REF_PATTERN' => GETPOST('WISE_SO_REF_PATTERN', 'alphanohtml'),
			'WISE_PAYMENT_MODE' => GETPOST('WISE_PAYMENT_MODE', 'aZ09'),
			'WISE_BANK_ACCOUNT_DEFAULT' => (string) GETPOSTINT('WISE_BANK_ACCOUNT_DEFAULT'),
		);
		// Per-currency bank mapping: wise_bank_map[CUR] = bank account rowid (0 = none)
		$bankMap = GETPOST('wise_bank_map', 'array');
		if (is_array($bankMap)) {
			foreach ($bankMap as $cur => $accid) {
				$cur = strtoupper(substr(trim((string) $cur), 0, 3));
				if (preg_match('/^[A-Z]{3}$/', $cur)) {
					$wiseConsts['WISE_BANK_ACCOUNT_'.$cur] = (string) (int) $accid;
				}
			}
		}
		foreach ($wiseConsts as $name => $value) {
			$result = dolibarr_set_const($db, $name, $value, 'chaine', 0, '', $conf->entity);
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
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?tab=wise'.$urlparams_str);
	exit;
}

// Wise: test the API token, list profiles and auto-fill WISE_PROFILE_ID when empty.
if ($action == 'wise_test') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_API.class.php';
		$apiToken = trim((string) getDolGlobalString('WISE_API_TOKEN'));
		if ($apiToken === '') {
			setEventMessages($langs->trans("WISE_TOKEN_NOT_SET"), null, 'errors');
		} else {
			$api = new Wise_API($apiToken);
			$result = $api->getProfiles();
			$httpCode = isset($result['httpCode']) ? (int) $result['httpCode'] : 0;
			unset($result['httpCode']);
			if ($httpCode >= 200 && $httpCode < 300 && !empty($result)) {
				$found = array();
				foreach ($result as $prof) {
					if (is_array($prof) && isset($prof['id'])) {
						$found[] = $prof['id'].' ('.(isset($prof['type']) ? $prof['type'] : '?').')';
						// Auto-fill the profile id: prefer business type
						if (getDolGlobalString('WISE_PROFILE_ID') === '' && isset($prof['type']) && $prof['type'] === 'business') {
							dolibarr_set_const($db, 'WISE_PROFILE_ID', (string) $prof['id'], 'chaine', 0, '', $conf->entity);
						}
					}
				}
				setEventMessages($langs->trans("WISE_TEST_OK").' '.implode(', ', $found), null, 'mesgs');
			} else {
				$detail = isset($result['error']) ? $result['error'] : json_encode($result);
				setEventMessages($langs->trans("WISE_TEST_KO").' HTTP '.$httpCode.' '.substr((string) $detail, 0, 300), null, 'errors');
			}
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?tab=wise'.$urlparams_str);
	exit;
}

// Wise: fetch webhook subscriptions and persist the signature public key to
// DOL_DATA_ROOT/wise_webhook/verification_key.pem (switches the receiver out
// of bootstrap mode: unsigned deliveries then get HTTP 401).
if ($action == 'wise_save_signature_key') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_API.class.php';
		$apiToken = trim((string) getDolGlobalString('WISE_API_TOKEN'));
		$profileId = trim((string) getDolGlobalString('WISE_PROFILE_ID'));
		if ($apiToken === '' || $profileId === '') {
			setEventMessages($langs->trans("WISE_TOKEN_NOT_SET"), null, 'errors');
		} else {
			$api = new Wise_API($apiToken);
			$result = $api->getSubscriptions((int) $profileId);
			$httpCode = isset($result['httpCode']) ? (int) $result['httpCode'] : 0;
			unset($result['httpCode']);
			$keyFound = '';
			$keySubId = '';
			$keySubType = '';
			if ($httpCode >= 200 && $httpCode < 300 && is_array($result)) {
				foreach ($result as $sub) {
					if (!is_array($sub)) {
						continue;
					}
					$candidate = '';
					// Field name varies across API versions (signature_key,
					// delivery_signature_key, ...). Also scan any "*key*" string.
					foreach (array('signature_key', 'delivery_signature_key', 'signatureKey') as $f) {
						if (!empty($sub[$f]) && is_string($sub[$f])) {
							$candidate = trim($sub[$f]);
							break;
						}
					}
					if ($candidate === '') {
						foreach ($sub as $f => $v) {
							if (is_string($v) && stripos($f, 'key') !== false
								&& (stripos($v, 'BEGIN PUBLIC KEY') !== false || preg_match('/^[A-Za-z0-9+\/=\s]{100,}$/', $v))) {
								$candidate = trim($v);
								break;
							}
						}
					}
					if ($candidate !== '') {
						// Prefer the balances#credit subscription when several exist
						$keyFound = $candidate;
						$keySubId = isset($sub['id']) ? (string) $sub['id'] : '';
						$keySubType = isset($sub['notification_type']) ? (string) $sub['notification_type'] : '';
						if (stripos($keySubType, 'credit') !== false) {
							break;
						}
					}
				}
			}
			if ($keyFound === '') {
				$detail = json_encode($result);
				setEventMessages($langs->trans("WISE_SIGNATURE_KEY_NOT_FOUND").' profileId='.$profileId.' HTTP '.$httpCode.' '.substr((string) $detail, 0, 400), null, 'errors');
			} else {
				$keyDir = DOL_DATA_ROOT.'/wise_webhook';
				if (!is_dir(dol_osencode($keyDir))) {
					dol_mkdir($keyDir);
				}
				$keyFile = $keyDir.'/verification_key.pem';
				if (@file_put_contents(dol_osencode($keyFile), $keyFound."\n") === false) {
					setEventMessages($langs->trans("WISE_SIGNATURE_KEY_WRITE_FAILED").' '.$keyFile, null, 'errors');
				} else {
					$fingerprint = substr(preg_replace('/\s+/', '', $keyFound), 0, 24);
					setEventMessages($langs->trans("WISE_SIGNATURE_KEY_SAVED").' ('.$keySubType.' '.$keySubId.', '.$fingerprint.'...)', null, 'mesgs');
					dol_syslog(__METHOD__.' Wise signature key saved for subscription '.$keySubId.' ('.$keySubType.')', LOG_INFO);
				}
			}
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?tab=wise'.$urlparams_str);
	exit;
}

if ($action == 'update' && !GETPOST('cancel', 'alpha')) {
	// CSRF check — update writes SHIPSGO/WISE secrets and must not be triggerable cross-site via GET
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans("Error"), null, 'errors');
		$action = '';
	} else {
		$db->begin();
		foreach (array_merge($arrayofparameters, $shipsgoparameters) as $key => $val) {
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
}

/*
 * View – tabbed by feature
 */
$tab = GETPOST('tab', 'aZ09');
if (!in_array($tab, array('general', 'shipsgo', 'pdf', 'boxes', 'rubis', 'wise'), true)) {
	$tab = 'general';
}
// Ensure module lang is loaded so tab labels are translated
$langs->load("slycustom@slycustom", 0, 0, '', 0, 1);
// Tab labels: use trans() and fallback to inline strings when key is not translated (no dependency on file path)
$tab_fallbacks = array(
	'en_US' => array('General', 'ShipsGo', 'PDF templates', 'Dashboard', 'Terms & conditions', 'Wise'),
	'zh_CN' => array('常规', 'ShipsGo 物流', 'PDF 模板', '仪表盘', '销售条款', 'Wise 收款'),
);
$tab_keys = array('SLYCUSTOM_TAB_GENERAL', 'SLYCUSTOM_TAB_SHIPSGO', 'SLYCUSTOM_TAB_PDF', 'SLYCUSTOM_TAB_BOXES', 'SLYCUSTOM_TAB_RUBIS', 'SLYCUSTOM_TAB_WISE');
$langcode = (!empty($langs->defaultlang) ? $langs->defaultlang : 'en_US');
if (!isset($tab_fallbacks[$langcode])) {
	$langcode = 'en_US';
}
$tab_labels = array();
for ($i = 0; $i < 6; $i++) {
	$t = $langs->trans($tab_keys[$i]);
	$tab_labels[$i] = ($t !== $tab_keys[$i] && $t !== '') ? $t : $tab_fallbacks[$langcode][$i];
}
// Use absolute URL so tab links work in any environment (subdir, rewrite, etc.)
$taburl = DOL_URL_ROOT.'/custom/slycustom/admin/setup.php';

$page_name = "SLYCustomSetup";
$help_url = '';
llxHeader('', $langs->trans($page_name), $help_url, '', 0, 0, '', '', '', 'mod-slycustom page-admin_setup');

$backurl = ($backtopage !== '' && $backtopage !== null)
	? $backtopage
	: (DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1');
$linkback = '<a href="'.dol_escape_htmltag($backurl).'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Custom tab bar (always visible; some themes hide dol_get_fiche_head tabs on setup pages)
$tab_ids = array('general', 'shipsgo', 'pdf', 'boxes', 'rubis', 'wise');
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

// Tab: General (module-wide options)
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

	// Core patch tool (sly24.0-* series): status overview and one-click apply
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("SlyPatchTool").'</td></tr>';
	print '<tr class="oddeven"><td colspan="2">';
	print $langs->trans("SlyPatchToolEntryTooltip");
	print '<br><br><div class="tabsAction">';
	print '<a class="butAction" href="'.DOL_URL_ROOT.'/custom/slycustom/admin/patches.php">'.$langs->trans("SlyPatchToolOpen").'</a>';
	print '</div></td></tr>';
	print '</table>';
	print '</div>';
}

// Tab: ShipsGo (API key, webhook secret, webhook registration)
if ($tab == 'shipsgo') {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("SLYCUSTOM_SHIPSGO_SECTION").'</td></tr>';
	if ($action == 'edit') {
		print '<tr><td colspan="2">';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update">';
		print '<input type="hidden" name="tab" value="shipsgo">';
		if ($save_lastsearch_values) {
			print '<input type="hidden" name="save_lastsearch_values" value="1">';
		}
		if ($backtopage !== '' && $backtopage !== null) {
			print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
		}
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';
		foreach ($shipsgoparameters as $key => $val) {
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
		foreach ($shipsgoparameters as $key => $val) {
			$tooltip = isset($val['tooltip']) ? $langs->trans($val['tooltip']) : '';
			print '<tr class="oddeven"><td>';
			print $form->textwithpicto($langs->trans($val['label']), $tooltip);
			print '</td><td>';
			if (isset($val['type']) && $val['type'] == 'yesno') {
				$v = getDolGlobalString($key, 1);
				print $v ? $langs->trans("Yes") : $langs->trans("No");
			} else {
				$v = getDolGlobalString($key);
				$preview = ($key === 'SHIPSGO_WEBHOOK_SECRET' && strlen((string) $v) > 8) ? substr((string) $v, 0, 8).'...' : $v;
				print $v ? dol_escape_htmltag($preview) : '<span class="opacitymedium">'.$langs->trans("None").'</span>';
			}
			print '</td></tr>';
		}
		print '<tr><td colspan="2"><div class="tabsAction">';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?tab=shipsgo&action=edit&token='.newToken().$urlparams_str.'">'.$langs->trans("Modify").'</a>';
		print '</div></td></tr>';
	}
	// ShipsGo webhook: per-entity URL and registration
	if (isModEnabled('slycustom')) {
		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/ShipsGo_Update.class.php';
		$webhookUrl = ShipmentStatus::getWebhookUrl((int) $conf->entity);
		// Use getDolGlobalString (auto-decrypts dolcrypt: values) — matches the same path used by the input above.
		$webhookSecret = trim((string) getDolGlobalString('SHIPSGO_WEBHOOK_SECRET'));
		print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("SHIPSGO_WEBHOOK_URL").'</td></tr>';
		print '<tr class="oddeven"><td colspan="2">';
		print '<p><strong>'.$langs->trans("Entity").':</strong> '.((int) $conf->entity).'</p>';
		print '<p><strong>URL:</strong> <code style="user-select:all;">'.dol_escape_htmltag($webhookUrl).'</code></p>';
		print '<p><strong>'.$langs->trans("SHIPSGO_WEBHOOK_SECRET").':</strong> ';
		if ($webhookSecret !== '') {
			// Use substr (NOT dol_trunc) — the secret is an ASCII hex string, no multibyte processing needed,
			// and dol_trunc's 4th arg is encoding, which caused a PHP fatal when we passed '...' as ellipsis.
			$preview = strlen($webhookSecret) > 8 ? substr($webhookSecret, 0, 8).'...' : $webhookSecret;
			print '<span class="opacitymedium">'.dol_escape_htmltag($preview).' ('.$langs->trans("Configured").', len='.strlen($webhookSecret).')</span>';
		} else {
			print '<span class="opacitymedium">'.$langs->trans("NotConfigured").'</span>';
		}
		print '</p>';
		print '<p class="opacitymedium">'.$langs->trans("SHIPSGO_WEBHOOK_URL_TOOLTIP").'</p>';
		print '<div class="tabsAction">';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;"'
			.(($webhookSecret !== '') ? ' onsubmit="return confirm(\''.dol_escape_js($langs->trans("SHIPSGO_WEBHOOK_GENERATE_CONFIRM")).'\');"' : '')
			.'>';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="generate_shipsgo_secret">';
		print '<input type="hidden" name="tab" value="shipsgo">';
		print '<input type="submit" class="butAction" value="'.$langs->trans("SHIPSGO_WEBHOOK_GENERATE_SECRET").'">';
		print '</form>';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline; margin-left:4px;">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="register_shipsgo_webhook">';
		print '<input type="hidden" name="tab" value="shipsgo">';
		print '<input type="submit" class="butAction" value="'.$langs->trans("SHIPSGO_WEBHOOK_REGISTER").'">';
		print '</form>';
		print '</div></td></tr>';
	}
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

// Tab: Wise incoming payments (connection, matching, bank mapping, status)
if ($tab == 'wise') {
	require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_API.class.php';
	require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_Incoming.class.php';

	$wiseToken = trim((string) getDolGlobalString('WISE_API_TOKEN'));
	$wiseProfileId = trim((string) getDolGlobalString('WISE_PROFILE_ID'));
	$wiseKeyFile = DOL_DATA_ROOT.'/wise_webhook/verification_key.pem';
	$wiseKeyOk = is_readable($wiseKeyFile);
	$wiseWebhookUrl = DOL_MAIN_URL_ROOT.'/custom/slycustom/webhook/wise.php';

	// Bank accounts (for the per-currency mapping) and payment modes
	$bankAccounts = array();
	$sql = 'SELECT rowid, ref, label, currency_code, clos FROM '.MAIN_DB_PREFIX.'bank_account';
	$sql .= ' WHERE entity IN ('.getEntity('bank_account').') ORDER BY currency_code, ref';
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$bankAccounts[(int) $obj->rowid] = array(
				'ref' => $obj->ref,
				'label' => $obj->label,
				'currency' => strtoupper((string) $obj->currency_code),
				'clos' => (int) $obj->clos,
			);
		}
		$db->free($resql);
	}
	$currencies = array();
	foreach ($bankAccounts as $acc) {
		if ($acc['currency'] !== '' && !in_array($acc['currency'], $currencies)) {
			$currencies[] = $acc['currency'];
		}
	}

	$paymentModes = array();
	$sql = 'SELECT code, libelle FROM '.MAIN_DB_PREFIX.'c_paiement';
	$sql .= ' WHERE active = 1 AND entity IN (0, '.(int) $conf->entity.') ORDER BY position ASC';
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$paymentModes[$obj->code] = $obj->libelle.' ('.$obj->code.')';
		}
		$db->free($resql);
	}

	// Incoming queue status
	$queueCounts = array();
	$sql = 'SELECT status, COUNT(*) AS n, SUM(amount) AS total FROM '.MAIN_DB_PREFIX.'slycustom_wise_incoming';
	$sql .= ' WHERE entity = '.(int) $conf->entity.' GROUP BY status';
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$queueCounts[$obj->status] = array('n' => (int) $obj->n, 'total' => (float) $obj->total);
		}
		$db->free($resql);
	}

	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';

	// Status overview
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("WISE_STATUS_SECTION").'</td></tr>';
	$incomingOn = WiseIncomingPayment::isIncomingEnabled($db, (int) $conf->entity);
	$outgoingOn = WiseIncomingPayment::isOutgoingEnabled($db, (int) $conf->entity);
	print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("WISE_FLOW_STATUS"), $langs->transnoentities("WISE_FLOW_STATUSTooltip")).'</td><td>'
		.$langs->trans("WISE_FLOW_INCOMING").': '.($incomingOn ? '<strong>'.$langs->trans("Activated").'</strong>' : '<span class="warning">'.$langs->trans("Disabled").'</span>')
		.' &nbsp;|&nbsp; '.$langs->trans("WISE_FLOW_OUTGOING").': '.($outgoingOn ? '<strong>'.$langs->trans("Activated").'</strong>' : '<span class="opacitymedium">'.$langs->trans("Disabled").'</span>')
		.'</td></tr>';
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans("WISE_WEBHOOK_URL").'</td><td><code style="user-select:all;">'.dol_escape_htmltag($wiseWebhookUrl).'</code></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans("WISE_API_TOKEN").'</td><td>'.($wiseToken !== '' ? '<span class="opacitymedium">'.substr($wiseToken, 0, 6).'... ('.$langs->trans("Configured").', len='.strlen($wiseToken).')</span>' : '<span class="opacitymedium">'.$langs->trans("NotConfigured").'</span>').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans("WISE_PROFILE_ID").'</td><td>'.($wiseProfileId !== '' ? dol_escape_htmltag($wiseProfileId) : '<span class="opacitymedium">'.$langs->trans("NotConfigured").'</span>').'</td></tr>';
	print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("WISE_SIGNATURE_KEY"), $langs->transnoentities("WISE_SIGNATURE_KEYTooltip")).'</td><td>'.($wiseKeyOk
		? $langs->trans("WISE_SIGNATURE_KEY_FOUND")
		: '<span class="warning">'.$langs->trans("WISE_SIGNATURE_KEY_MISSING").'</span>'
			.((string) getDolGlobalString('WISE_WEBHOOK_IPCHECK') === '1' ? '<br>'.$langs->trans("WISE_IPCHECK_ACTIVE") : '')).'</td></tr>';
	// Effective SO pattern: manual override > derived from order numbering mask > default
	$wiseManualPattern = trim((string) getDolGlobalString('WISE_SO_REF_PATTERN'));
	$wiseDerivedPattern = WiseIncomingPayment::deriveSoPatternFromNumbering($db, (int) $conf->entity);
	$wiseEffectivePattern = WiseIncomingPayment::getSoPattern($db, (int) $conf->entity);
	if ($wiseManualPattern !== '') {
		$wisePatternSource = $langs->trans("WISE_SO_REF_SOURCE_MANUAL");
	} elseif ($wiseDerivedPattern !== '') {
		$wisePatternSource = $langs->trans("WISE_SO_REF_SOURCE_MASK");
	} else {
		$wisePatternSource = $langs->trans("WISE_SO_REF_SOURCE_DEFAULT");
	}
	print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans("WISE_SO_REF_PATTERN_EFFECTIVE"), $langs->transnoentities("WISE_SO_REF_PATTERNTooltip")).'</td>';
	print '<td><code>'.dol_escape_htmltag($wiseEffectivePattern).'</code><br><span class="opacitymedium">'.$wisePatternSource.(($wiseManualPattern === '' && $wiseDerivedPattern !== '') ? ' — '.dol_escape_htmltag($wiseDerivedPattern) : '').'</span></td></tr>';
	$queueLine = array();
	foreach (array(WiseIncomingPayment::STATUS_NEW, WiseIncomingPayment::STATUS_ENRICHED, WiseIncomingPayment::STATUS_RECORDED, WiseIncomingPayment::STATUS_IGNORED) as $st) {
		if (isset($queueCounts[$st])) {
			$queueLine[] = $st.': '.$queueCounts[$st]['n'];
		}
	}
	print '<tr class="oddeven"><td>'.$langs->trans("WISE_QUEUE_STATUS").'</td><td>'.(empty($queueLine) ? '<span class="opacitymedium">'.$langs->trans("None").'</span>' : implode(' &nbsp;|&nbsp; ', $queueLine)).'</td></tr>';
	print '</table></div>';

	// Settings form
	print '<br>';
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update_wise">';
	print '<input type="hidden" name="tab" value="wise">';
	if ($backtopage !== '' && $backtopage !== null) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
	}
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("SLYCUSTOM_WISE_SECTION").'</td></tr>';

	// Feature toggles
	print '<tr class="oddeven"><td class="titlefield">'.$form->textwithpicto($langs->trans("WISE_INCOMING_ENABLED"), $langs->transnoentities("WISE_INCOMING_ENABLEDTooltip")).'</td>';
	print '<td>'.$form->selectyesno('WISE_INCOMING_ENABLED', WiseIncomingPayment::isIncomingEnabled($db, (int) $conf->entity) ? 1 : 0, 1).'</td></tr>';
	print '<tr class="oddeven"><td class="titlefield">'.$form->textwithpicto($langs->trans("WISE_OUTGOING_ENABLED"), $langs->transnoentities("WISE_OUTGOING_ENABLEDTooltip")).'</td>';
	print '<td>'.$form->selectyesno('WISE_OUTGOING_ENABLED', WiseIncomingPayment::isOutgoingEnabled($db, (int) $conf->entity) ? 1 : 0, 1).'</td></tr>';
	print '<tr class="oddeven"><td class="titlefield">'.$form->textwithpicto($langs->trans("WISE_WEBHOOK_IPCHECK"), $langs->transnoentities("WISE_WEBHOOK_IPCHECKTooltip")).'</td>';
	print '<td>'.$form->selectyesno('WISE_WEBHOOK_IPCHECK', (string) getDolGlobalString('WISE_WEBHOOK_IPCHECK') === '1' ? 1 : 0, 1).'</td></tr>';

	$wiseTokenTooltip = $langs->trans("WISE_API_TOKENTooltip");
	print '<tr class="oddeven"><td class="titlefield">'.$form->textwithpicto($langs->trans("WISE_API_TOKEN"), $wiseTokenTooltip).'</td>';
	print '<td><input type="text" name="WISE_API_TOKEN" class="flat minwidth400" value="'.dol_escape_htmltag($wiseToken).'" autocomplete="off"></td></tr>';

	print '<tr class="oddeven"><td class="titlefield">'.$form->textwithpicto($langs->trans("WISE_PROFILE_ID"), $langs->trans("WISE_PROFILE_IDTooltip")).'</td>';
	print '<td><input type="text" name="WISE_PROFILE_ID" class="flat minwidth200" value="'.dol_escape_htmltag($wiseProfileId).'" autocomplete="off"></td></tr>';

	print '<tr class="oddeven"><td class="titlefield">'.$form->textwithpicto($langs->trans("WISE_SO_REF_PATTERN"), $langs->trans("WISE_SO_REF_PATTERNTooltip")).'</td>';
	print '<td><input type="text" name="WISE_SO_REF_PATTERN" class="flat minwidth400" value="'.dol_escape_htmltag(getDolGlobalString('WISE_SO_REF_PATTERN')).'" placeholder="/\b([A-Z]{0,4}[0-9]{2,5}[-\/]?[0-9]{1,6})\b/i" autocomplete="off"></td></tr>';

	print '<tr class="oddeven"><td class="titlefield">'.$form->textwithpicto($langs->trans("WISE_PAYMENT_MODE"), $langs->trans("WISE_PAYMENT_MODETooltip")).'</td><td>';
	$currentMode = getDolGlobalString('WISE_PAYMENT_MODE') !== '' ? getDolGlobalString('WISE_PAYMENT_MODE') : 'VIR';
	print '<select name="WISE_PAYMENT_MODE" class="flat">';
	foreach ($paymentModes as $code => $label) {
		print '<option value="'.dol_escape_htmltag($code).'"'.($code === $currentMode ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
	}
	print '</select></td></tr>';

	print '<tr class="oddeven"><td class="titlefield">'.$form->textwithpicto($langs->trans("WISE_BANK_ACCOUNT_DEFAULT"), $langs->trans("WISE_BANK_ACCOUNT_DEFAULTTooltip")).'</td><td>';
	print '<select name="WISE_BANK_ACCOUNT_DEFAULT" class="flat">';
	print '<option value="0">--</option>';
	$currentDefault = (int) getDolGlobalString('WISE_BANK_ACCOUNT_DEFAULT');
	foreach ($bankAccounts as $accid => $acc) {
		print '<option value="'.$accid.'"'.($accid === $currentDefault ? ' selected' : '').'>'.dol_escape_htmltag($acc['ref'].' — '.$acc['label'].' ('.$acc['currency'].($acc['clos'] ? ', '.$langs->trans("Closed") : '').')').'</option>';
	}
	print '</select></td></tr>';

	// Per-currency mapping: one row per currency found among bank accounts
	foreach ($currencies as $cur) {
		$currentAcc = (int) getDolGlobalString('WISE_BANK_ACCOUNT_'.$cur);
		print '<tr class="oddeven"><td class="titlefield">'.$form->textwithpicto($langs->trans("WISE_BANK_MAPPING_CUR", $cur), $langs->trans("WISE_BANK_MAPPING_CURTooltip", $cur)).'</td><td>';
		print '<select name="wise_bank_map['.$cur.']" class="flat">';
		print '<option value="0">--</option>';
		foreach ($bankAccounts as $accid => $acc) {
			if ($acc['currency'] !== $cur) {
				continue;
			}
			print '<option value="'.$accid.'"'.($accid === $currentAcc ? ' selected' : '').'>'.dol_escape_htmltag($acc['ref'].' — '.$acc['label'].($acc['clos'] ? ', '.$langs->trans("Closed") : '')).'</option>';
		}
		print '</select></td></tr>';
	}

	print '</table>';
	print '<br><div class="center">';
	print '<input class="button button-save" type="submit" value="'.$langs->trans("Save").'">';
	print '</div>';
	print '</form>';
	print '</div>';

	// Test connection + fetch signature key
	print '<br><div class="tabsAction">';
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline;">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="wise_test">';
	print '<input type="hidden" name="tab" value="wise">';
	print '<input type="submit" class="butAction" value="'.$langs->trans("WISE_TEST_CONNECTION").'">';
	print '</form>';
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline; margin-left:4px;">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="wise_save_signature_key">';
	print '<input type="hidden" name="tab" value="wise">';
	print '<input type="submit" class="butAction" value="'.$langs->trans("WISE_SAVE_SIGNATURE_KEY").'">';
	print '</form>';
	print '</div>';
}

print dol_get_fiche_end(0);

llxFooter();
$db->close();
