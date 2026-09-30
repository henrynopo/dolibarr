<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/admin/setup.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      EmbeddedBookkeeping setup: journal codes + AI provider + key + prompts.
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
	die('Include of main.inc.php failed for EmbeddedBookkeeping setup.php');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';

$form = new Form($db);
$formadmin = new FormAdmin($db);

if (empty($user->admin)) {
	accessforbidden();
	exit;
}

$langs->loadLangs(array('admin', 'embeddedbookkeeping@embeddedbookkeeping'));

// --- Save action ---------------------------------------------------------
$action = GETPOST('action', 'aZ09');
$error = 0;

if ($action === 'save') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans('ErrorTokenMismatch'), null, 'errors');
		$error++;
	}

	if (!$error) {
		// We rebuild a typed config map. "chaine" entries go through dolibarr_set_const
		// as plain strings; the Anthropic key gets the "chaine" + KEY suffix so the
		// FormSetup encrypts it on write.
		$generalKeys = array(
			'EMBEDDEDBOOKKEEPING_JOURNAL_SALES'        => 'chaine',
			'EMBEDDEDBOOKKEEPING_JOURNAL_PURCHASES'    => 'chaine',
			'EMBEDDEDBOOKKEEPING_JOURNAL_EXPENSE'      => 'chaine',
			'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_CUSTOMER' => 'chaine',
			'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_SUPPLIER' => 'chaine',
			'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_EXPENSE'  => 'chaine',
			'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_DEPOSIT' => 'chaine',
			'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_CREDIT_NOTE' => 'chaine',
			'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_REPLACEMENT' => 'chaine',
		);
		$providerKeys = array(
			'EMBEDDEDBOOKKEEPING_AI_PROVIDER'          => 'chaine',
			'EMBEDDEDBOOKKEEPING_ANTHROPIC_KEY'        => 'chaine', // suffixed with KEY → FormSetup encrypts it
			'EMBEDDEDBOOKKEEPING_AI_CLAUDE_MODEL'      => 'chaine',
			'EMBEDDEDBOOKKEEPING_AI_DEBUG'             => 'yesno',
			'EMBEDDEDBOOKKEEPING_AI_MAX_LINES'         => 'int',
			'EMBEDDEDBOOKKEEPING_AI_CONFIDENCE_THRESHOLD' => 'chaine',
			'EMBEDDEDBOOKKEEPING_AI_SYSTEM_PROMPT_EN'  => 'chaine',
			'EMBEDDEDBOOKKEEPING_AI_SYSTEM_PROMPT_ZH'  => 'chaine',
		);

		$all = array_merge($generalKeys, $providerKeys);
		foreach ($all as $name => $type) {
			$val = GETPOST($name, 'alphanohtml');
			if ($val === '') {
				// Allow clearing the Anthropic key.
				if ($name === 'EMBEDDEDBOOKKEEPING_ANTHROPIC_KEY') {
					$val = '';
				} else {
					continue;
				}
			}
			if ($type === 'int') {
				$val = (string) ((int) $val);
			}
			$res = dolibarr_set_const($db, $name, $val, $type, 0, '', (int) $conf->entity);
			if ($res < 0) {
				setEventMessages($langs->trans('Error'), null, 'errors');
				$error++;
				break;
			}
		}
	}

	if (!$error) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
}

// --- Render --------------------------------------------------------------
$pageTitle = $langs->trans('EBKSetupTitle');
llxHeader('', $pageTitle, '', 0, 0);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($pageTitle, $linkback, 'title_setup');

$head = array();
$h = 0;
$head[$h][0] = DOL_URL_ROOT.'/custom/embeddedbookkeeping/admin/setup.php';
$head[$h][1] = $langs->trans('EBKSetupTabGeneral');
$head[$h][2] = 'general';
$h++;

print dol_get_fiche_head($head, GETPOST('tab', 'alpha') ?: 'general', '', -1, '');

// ---- Tab 1: General -----------------------------------------------------
if (GETPOST('tab', 'alpha') !== 'provider') {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
	print '<input type="hidden" name="tab" value="general">';

	print '<table class="noborder allwidth">'."\n";
	print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('EBKSetupTabGeneral').'</th></tr>'."\n";

	print '<tr><td class="titlefield">'.$langs->trans('EBKJournalSales').'</td>';
	print '<td><input type="text" name="EMBEDDEDBOOKKEEPING_JOURNAL_SALES" value="'.dol_escape_htmltag((string) getDolGlobalString('EMBEDDEDBOOKKEEPING_JOURNAL_SALES', 'VT')).'" class="minwidth100"></td></tr>'."\n";

	print '<tr><td>'.$langs->trans('EBKJournalPurchases').'</td>';
	print '<td><input type="text" name="EMBEDDEDBOOKKEEPING_JOURNAL_PURCHASES" value="'.dol_escape_htmltag((string) getDolGlobalString('EMBEDDEDBOOKKEEPING_JOURNAL_PURCHASES', 'AC')).'" class="minwidth100"></td></tr>'."\n";

	print '<tr><td>'.$langs->trans('EBKJournalExpense').'</td>';
	print '<td><input type="text" name="EMBEDDEDBOOKKEEPING_JOURNAL_EXPENSE" value="'.dol_escape_htmltag((string) getDolGlobalString('EMBEDDEDBOOKKEEPING_JOURNAL_EXPENSE', 'EX')).'" class="minwidth100"></td></tr>'."\n";

	// Default accounting-date source per document type (semantic keys consumed
	// by EBKTabData::resolveDateByPreference()). The tab still allows a
	// per-entry override from the related-date picker.
	$datePresets = array(
		'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_CUSTOMER' => array(
			'document' => 'EBKTabDateDocument', 'due' => 'EBKTabDateDue',
			'incoterm_shipment' => 'EBKTabDateIncotermShipmentShort', 'incoterm_delivery' => 'EBKTabDateIncotermDeliveryShort',
			'today' => 'EBKTabDateToday',
		),
		'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_SUPPLIER' => array(
			'document' => 'EBKTabDateDocument', 'due' => 'EBKTabDateDue',
			'incoterm_shipment' => 'EBKTabDateIncotermShipmentShort', 'incoterm_delivery' => 'EBKTabDateIncotermDeliveryShort',
			'today' => 'EBKTabDateToday',
		),
		'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_EXPENSE' => array(
			'document' => 'EBKTabDateDocument', 'validation' => 'EBKTabDateValid',
			'approval' => 'EBKTabDateApprove', 'payment' => 'EBKTabDatePayment', 'today' => 'EBKTabDateToday',
		),
		'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_DEPOSIT' => array(
			'document' => 'EBKTabDateDocument', 'payment' => 'EBKTabDatePayment', 'today' => 'EBKTabDateToday',
		),
		'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_CREDIT_NOTE' => array(
			'document' => 'EBKTabDateDocument', 'source' => 'EBKTabDateSource', 'today' => 'EBKTabDateToday',
		),
		'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_REPLACEMENT' => array(
			'document' => 'EBKTabDateDocument', 'source' => 'EBKTabDateSource', 'today' => 'EBKTabDateToday',
		),
	);
	$presetLabels = array(
		'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_CUSTOMER' => 'EBKSetupDateCustomer',
		'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_SUPPLIER' => 'EBKSetupDateSupplier',
		'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_EXPENSE'  => 'EBKSetupDateExpense',
		'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_DEPOSIT' => 'EBKSetupDateDeposit',
		'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_CREDIT_NOTE' => 'EBKSetupDateCreditNote',
		'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_REPLACEMENT' => 'EBKSetupDateReplacement',
	);
	foreach ($datePresets as $constName => $options) {
		$current = (string) getDolGlobalString($constName, 'document');
		print '<tr><td>'.$langs->trans($presetLabels[$constName]).'</td>';
		print '<td><select name="'.dol_escape_htmltag($constName).'" class="minwidth200">';
		foreach ($options as $value => $labelKey) {
			print '<option value="'.dol_escape_htmltag($value).'"'.($current === $value ? ' selected' : '').'>'.dol_escape_htmltag($langs->trans($labelKey)).'</option>';
		}
		print '</select></td></tr>'."\n";
	}

	print '</table>';

	print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('Save').'"></div>';
	print '</form>';
}

// ---- Tab 2: Provider + AI ----------------------------------------------
print '<br>';
if (GETPOST('tab', 'alpha') === 'provider') {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
	print '<input type="hidden" name="tab" value="provider">';

	print '<table class="noborder allwidth">'."\n";
	print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('EBKSetupTabProvider').'</th></tr>'."\n";

	// Provider choice
	$providerVal = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_PROVIDER', 'ai_module');
	print '<tr><td class="titlefield">'.$langs->trans('EBKAiProvider').'</td>';
	print '<td>';
	print '<select name="EMBEDDEDBOOKKEEPING_AI_PROVIDER">';
	print '<option value="disabled"'.(($providerVal === 'disabled') ? ' selected' : '').'>'.$langs->trans('EBKAiProviderDisabled').'</option>';
	print '<option value="ai_module"'.(($providerVal === 'ai_module') ? ' selected' : '').'>'.$langs->trans('EBKAiProviderAiModule').'</option>';
	print '<option value="claude"'.(($providerVal === 'claude') ? ' selected' : '').'>'.$langs->trans('EBKAiProviderClaude').'</option>';
	print '</select>';
	print ' '.$form->textwithpicto('', $langs->trans('EBKAiProviderTooltip'));
	print '</td></tr>'."\n";

	// Anthropic key (encrypted; suffixed _KEY triggers FormSetup encryption)
	$aiKeySet = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_ANTHROPIC_KEY', '');
	print '<tr><td>'.$langs->trans('EBKAnthropicKey').'</td>';
	print '<td><input type="password" name="EMBEDDEDBOOKKEEPING_ANTHROPIC_KEY" value="" placeholder="'.(empty($aiKeySet) ? '' : $langs->trans('EBKKeyAlreadySet')).'" class="minwidth300">';
	print ' '.$form->textwithpicto('', $langs->trans('EBKAnthropicKeyTooltip'));
	print '</td></tr>'."\n";

	// Model
	print '<tr><td>'.$langs->trans('EBKAiClaudeModel').'</td>';
	print '<td><input type="text" name="EMBEDDEDBOOKKEEPING_AI_CLAUDE_MODEL" value="'.dol_escape_htmltag((string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CLAUDE_MODEL', 'claude-sonnet-4-5')).'" class="minwidth200"></td></tr>'."\n";

	// Debug
	print '<tr><td>'.$langs->trans('EBKAiDebug').'</td>';
	print '<td><input type="checkbox" name="EMBEDDEDBOOKKEEPING_AI_DEBUG" value="1"'.((string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_DEBUG', '0') ? ' checked' : '').'>';
	print ' '.$form->textwithpicto('', $langs->trans('EBKAiDebugTooltip'));
	print '</td></tr>'."\n";

	// Max lines
	print '<tr><td>'.$langs->trans('EBKAiMaxLines').'</td>';
	print '<td><input type="number" min="1" max="10" name="EMBEDDEDBOOKKEEPING_AI_MAX_LINES" value="'.((int) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_MAX_LINES', 1)).'" class="minwidth100"></td></tr>'."\n";

	// Confidence threshold
	print '<tr><td>'.$langs->trans('EBKAiConfidenceThreshold').'</td>';
	print '<td><input type="number" step="0.05" min="0" max="1" name="EMBEDDEDBOOKKEEPING_AI_CONFIDENCE_THRESHOLD" value="'.((float) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CONFIDENCE_THRESHOLD', 0.6)).'" class="minwidth100"></td></tr>'."\n";

	// Custom prompt overrides
	print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('EBKAiPromptOverrides').'</th></tr>'."\n";
	print '<tr><td>'.$langs->trans('EBKAiPromptEn').'</td>';
	print '<td><textarea name="EMBEDDEDBOOKKEEPING_AI_SYSTEM_PROMPT_EN" rows="4" class="minwidth500">'.dol_escape_htmltag((string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_SYSTEM_PROMPT_EN', '')).'</textarea></td></tr>'."\n";
	print '<tr><td>'.$langs->trans('EBKAiPromptZh').'</td>';
	print '<td><textarea name="EMBEDDEDBOOKKEEPING_AI_SYSTEM_PROMPT_ZH" rows="4" class="minwidth500">'.dol_escape_htmltag((string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_SYSTEM_PROMPT_ZH', '')).'</textarea></td></tr>'."\n";

	print '</table>';

	print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('Save').'"></div>';
	print '</form>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
