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
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/AiModuleProvider.class.php';

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

// Provider switcher: a GET-only handler that updates ONLY
// EMBEDDEDBOOKKEEPING_AI_PROVIDER in the DB and redirects back to
// setup.php?tab=provider. It deliberately does NOT write the four
// CUSTOM_* fields or the prompt textareas — those are still
// 'Save'-button writes only. This is the cure for the previous
// "switching the provider dropdown auto-saved the form and discarded
// any unsaved prompt text" regression.
//
// CSRF: provider changes go through newToken() too, even though it's
// a GET, to prevent a malicious link from flipping the AI provider on
// an admin who clicks it.
if ($action === 'switch_provider') {
	$newProvider = GETPOST('provider', 'alphanohtml');
	if (!in_array($newProvider, array('disabled', 'ai_module', 'ebk_custom'), true)) {
		$newProvider = 'ai_module';
	}
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans('ErrorTokenMismatch'), null, 'errors');
	} else {
		$res = dolibarr_set_const($db, 'EMBEDDEDBOOKKEEPING_AI_PROVIDER', $newProvider, 'chaine', 0, '', (int) $conf->entity);
		if ($res < 0) {
			setEventMessages($langs->trans('Error'), null, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?tab=provider');
	exit;
}

if ($action === 'save') {
	if (GETPOST('token', 'none') !== newToken()) {
		setEventMessages($langs->trans('ErrorTokenMismatch'), null, 'errors');
		$error++;
	}

	if (!$error) {
		// General keys (always)
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
		// Provider keys (shared tunables: debug flag, max lines,
		// confidence threshold). EMBEDDEDBOOKKEEPING_AI_PROVIDER is
		// INTENTIONALLY NOT in this map: the provider dropdown uses its
		// own GET-based switcher (action=switch_provider) so that
		// changing the dropdown does NOT trigger a save of the rest of
		// the form. This keeps unsaved prompt / key / URL typed into
		// the other fields when the admin switches provider.
		$providerKeys = array(
			'EMBEDDEDBOOKKEEPING_AI_DEBUG'             => 'yesno',
			'EMBEDDEDBOOKKEEPING_AI_MAX_LINES'         => 'int',
			'EMBEDDEDBOOKKEEPING_AI_CONFIDENCE_THRESHOLD' => 'chaine',
			// Offline knowledge base (custom/embeddedbookkeeping/knowledge/*.md).
			// MAXCHARS is an int and the loop below casts it; an empty field is
			// skipped by the "if ($val === '') continue" rule above, so a blank
			// box simply keeps the previous value rather than wiping it.
			'EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_ENABLED'  => 'yesno',
			'EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_MAXCHARS' => 'int',
		);
		// 'ebk_custom' provider keys — fully independent LLM config
		// (service / key / URL / model) stored entirely in EBK's own
		// constants, with no read/write to the system AI module's
		// AI_API_* constants. The KEY is written with the 'chaine:KEY'
		// suffix so dolibarr_set_const encrypts it on disk (matches how
		// ai/admin/setup.php stores AI_API_*_KEY).
		$ebkCustomKeys = array(
			'EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE' => 'chaine',
			'EMBEDDEDBOOKKEEPING_AI_CUSTOM_URL'     => 'chaine',
			'EMBEDDEDBOOKKEEPING_AI_CUSTOM_MODEL'   => 'chaine',
		);

		$all = array_merge($generalKeys, $providerKeys, $ebkCustomKeys);
		foreach ($all as $name => $type) {
			$val = GETPOST($name, 'alphanohtml');
			if ($val === '') {
				continue;
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

		// KEY is special: the :KEY type suffix is what tells
		// dolibarr_set_const to encrypt the value on disk. GETPOST above
		// skips empty values; we still want to allow the admin to
		// intentionally clear a saved key (uncommon, but the previous
		// behaviour treated any empty input as "leave unchanged"). To
		// preserve that, we only write when the field is non-empty.
		$ebkKey = GETPOST('EMBEDDEDBOOKKEEPING_AI_CUSTOM_KEY', 'alphanohtml');
		if ($ebkKey !== '' && !$error) {
			$res = dolibarr_set_const($db, 'EMBEDDEDBOOKKEEPING_AI_CUSTOM_KEY', $ebkKey, 'chaine:KEY', 0, '', (int) $conf->entity);
			if ($res < 0) {
				setEventMessages($langs->trans('Error'), null, 'errors');
				$error++;
			}
		}

		// Bookkeeping-suggest prompts: stored entirely in this module's own
		// EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE / _POST constants. We do NOT
		// touch the system AI module's AI_CONFIGURATIONS_PROMPT JSON — that
		// namespace belongs to a different module serving different
		// business purposes, and CLAUDE.md §1 forbids cross-module
		// namespace pollution ("禁用或卸载模块时，绝对不能影响核心系统的
		// 运行，严禁遗留脏数据"). The system AI module's
		// custom_prompt.php page edits a separate AI_CONFIGURATIONS_PROMPT
		// key and never sees our bookkeeping prompt.
		//
		// We allow an empty value to be saved: resolvePrompt() then falls
		// back to the in-process EBK built-in EN/ZH default so the LLM
		// still receives a sensible prompt out of the box.
		if (!$error) {
			// 'restricthtml' keeps line breaks — see the note on the knowledge-base
			// notes below. alphanohtml strips them, which flattened these prompts.
			$bookkeepPre  = (string) GETPOST('EBK_BOOKKEEPING_PROMPT', 'restricthtml');
			$bookkeepPost = (string) GETPOST('EBK_BOOKKEEPING_POST_PROMPT', 'restricthtml');
			foreach (array(
				'EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE'  => $bookkeepPre,
				'EMBEDDEDBOOKKEEPING_AI_PROMPT_POST' => $bookkeepPost,
			) as $name => $val) {
				$res = dolibarr_set_const($db, $name, $val, 'chaine', 0, '', (int) $conf->entity);
				if ($res < 0) {
					setEventMessages($langs->trans('Error'), null, 'errors');
					$error++;
					break;
				}
			}
		}

		// Company-specific accounting notes fed to the assistant on EVERY
		// question (not keyword-scored — the admin decides, not the scorer).
		// Saved separately because this one MUST be clearable: the generic
		// loop above skips empty values, so an admin removing all their notes
		// would silently keep the old ones forever.
		if (!$error && GETPOST('tab', 'alpha') === 'provider') {
			// 'restricthtml', not 'alphanohtml': alphanohtml routes through
			// dol_string_nohtmltag() which defaults to $removelinefeed=1, i.e. it
			// flattens every newline out of the saved text. For a multi-paragraph
			// prompt that silently destroys the structure the admin typed.
			// restricthtml keeps line breaks and is core's documented filter for
			// textarea input; the value is escaped again on render, so it can
			// never be injected as HTML.
			$kbNotes = (string) GETPOST('EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_EXTRA', 'restricthtml');
			$res = dolibarr_set_const($db, 'EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_EXTRA', $kbNotes, 'chaine', 0, '', (int) $conf->entity);
			if ($res < 0) {
				setEventMessages($langs->trans('Error'), null, 'errors');
				$error++;
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
$head[$h][0] = DOL_URL_ROOT.'/custom/embeddedbookkeeping/admin/setup.php?tab=provider';
$head[$h][1] = $langs->trans('EBKSetupTabProvider');
$head[$h][2] = 'provider';
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
	// Switching the provider is a GET-only operation that updates
	// EMBEDDEDBOOKKEEPING_AI_PROVIDER in the DB and redirects back to
	// this page. We deliberately do NOT submit the main form here:
	// the previous behaviour used onchange="this.form.submit()", which
	// caused an auto-save that discarded any unsaved prompt / key /
	// URL typed in the four input fields below. Changing the dropdown
	// should only commit the provider choice, not the rest of the
	// form. The action="switch_provider" handler at the top of this
	// file does exactly that and exits before the save() branch.
	print '<select name="provider" onchange="window.location=\''.$_SERVER['PHP_SELF'].'?action=switch_provider&token='.newToken().'&provider=\'+this.value">';
	print '<option value="disabled"'.(($providerVal === 'disabled') ? ' selected' : '').'>'.$langs->trans('EBKAiProviderDisabled').'</option>';
	print '<option value="ai_module"'.(($providerVal === 'ai_module') ? ' selected' : '').'>'.$langs->trans('EBKAiProviderAiModule').'</option>';
	print '<option value="ebk_custom"'.(($providerVal === 'ebk_custom') ? ' selected' : '').'>'.$langs->trans('EBKAiProviderEbkCustom').'</option>';
	print '</select>';
	print ' '.$form->textwithpicto('', $langs->trans('EBKAiProviderTooltip'));
	print '</td></tr>'."\n";

	// 'ai_module' mode: render a READ-ONLY summary panel showing which
	// upstream LLM EBK is currently borrowing from the system AI module.
	// This is purely a visibility aid — the four CUSTOM_* input fields
	// below are NOT auto-populated, NOT overwritten, and NOT synced on
	// save. The admin can still type independent values into them as a
	// staging area for a future switch to 'ebk_custom' without losing
	// what they typed.
	//
	// Only shown when the system AI module is actually present and
	// enabled (otherwise there's nothing to borrow and the panel would
	// be misleading). Keys are masked with bullets — we never echo
	// the decrypted key plaintext back to the browser here, only the
	// service / model / URL which are non-sensitive and useful for the
	// admin to see at a glance.
	if ($providerVal === 'ai_module' && isModEnabled('ai')) {
		if (!function_exists('getListOfAIServices')) {
			$f = DOL_DOCUMENT_ROOT.'/ai/lib/ai.lib.php';
			if (is_file($f)) require_once $f;
		}
		$borrowedService = (string) getDolGlobalString('AI_API_SERVICE', '');
		$borrowedServices = function_exists('getListOfAIServices') ? getListOfAIServices() : array();
		$borrowedLabel = '';
		if ($borrowedService !== '' && isset($borrowedServices[$borrowedService]['label'])) {
			$borrowedLabel = (string) $borrowedServices[$borrowedService]['label'];
		} elseif ($borrowedService !== '') {
			$borrowedLabel = $borrowedService;
		}
		$borrowedKeySet = (string) getDolGlobalString('AI_API_'.strtoupper($borrowedService).'_KEY', '') !== '';
		$borrowedUrl    = (string) getDolGlobalString('AI_API_'.strtoupper($borrowedService).'_URL', '');
		$borrowedModel  = (string) getDolGlobalString('AI_API_'.strtoupper($borrowedService).'_MODEL_TEXT', '');

		// Fall back to the catalog defaults for url / model when the
		// admin hasn't overridden them — that's exactly what
		// resolveAdapter() does at runtime, so the panel stays truthful
		// about what the next AI call will actually hit.
		if ($borrowedUrl === '' && $borrowedService !== '' && isset($borrowedServices[$borrowedService]['url'])) {
			$borrowedUrl = (string) $borrowedServices[$borrowedService]['url'];
		}
		if ($borrowedModel === '' && $borrowedService !== '' && isset($borrowedServices[$borrowedService]['textgeneration']['default'])) {
			$borrowedModel = (string) $borrowedServices[$borrowedService]['textgeneration']['default'];
		}

		print '<tr><td colspan="2">';
		print '<div class="info" style="margin: 8px 0; padding: 8px; background: #f5f5f5; border-left: 3px solid #888;">';
		print '<strong>'.$langs->trans('EBKAiBorrowedPanelTitle').'</strong><br>';
		print $langs->trans('EBKAiBorrowedService').': <code>'.dol_escape_htmltag($borrowedLabel !== '' ? $borrowedLabel : $langs->trans('EBKAiBorrowedNotSet')).'</code><br>';
		print $langs->trans('EBKAiBorrowedKey').': <code>'.($borrowedKeySet ? '••••••••' : $langs->trans('EBKAiBorrowedNotSet')).'</code><br>';
		print $langs->trans('EBKAiBorrowedUrl').': <code>'.dol_escape_htmltag($borrowedUrl !== '' ? $borrowedUrl : $langs->trans('EBKAiBorrowedNotSet')).'</code><br>';
		print $langs->trans('EBKAiBorrowedModel').': <code>'.dol_escape_htmltag($borrowedModel !== '' ? $borrowedModel : $langs->trans('EBKAiBorrowedNotSet')).'</code><br>';
		print '<span class="opacitymedium">'.$langs->trans('EBKAiBorrowedPanelTooltip').'</span>';
		print '</div>';
		print '</td></tr>'."\n";
	}

	// 'ebk_custom' provider: a fully independent LLM config (service /
	// API key / endpoint / model) that does NOT touch the system AI
	// module's own AI_API_* configuration. Credentials live entirely in
	// EBK's own constants (EMBEDDEDBOOKKEEPING_AI_CUSTOM_*). The KEY
	// field is written with the 'chaine:KEY' suffix so it is encrypted
	// on disk like the system AI module's keys.
	//
	// IMPORTANT: the section is rendered REGARDLESS of the currently
	// selected provider, on purpose. Reason: the previous behaviour
	// gated it on `$providerVal === 'ebk_custom'`, which made the four
	// inputs invisible until the admin had already switched to
	// ebk_custom — a chicken-and-egg that left the admin with nowhere
	// to type the service / key / URL / model. The provider dropdown
	// auto-submits on change (see the <select> above), so the admin
	// can either (a) pre-fill the parameters here, then switch
	// provider, or (b) switch first, then fill — both work. Inputs are
	// always saved on submit regardless of which provider is active,
	// so an admin using 'ai_module' can still stage a future switch
	// to 'ebk_custom' without losing the values.
	//
	// Lazy include getListOfAIServices() so the service dropdown
	// matches what the ai module's own setup page shows. The file is
	// bundled with Dolibarr 17+.
	if (!function_exists('getListOfAIServices')) {
		$file = DOL_DOCUMENT_ROOT.'/ai/lib/ai.lib.php';
		if (is_file($file)) {
			require_once $file;
		}
	}
	$ebkServices = function_exists('getListOfAIServices') ? getListOfAIServices() : array();
	$ebkService = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE', 'chatgpt');
	$ebkUrl     = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_URL', '');
	$ebkModel   = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_MODEL', '');

	print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('EBKAiEbkCustomSection').'</th></tr>'."\n";

	// Service dropdown — chatgpt / anthropic / google / custom / …
	print '<tr><td class="titlefield">'.$langs->trans('EBKAiEbkCustomService').'</td>';
	print '<td>';
	if (!empty($ebkServices)) {
		print '<select name="EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE">';
		foreach ($ebkServices as $svcKey => $svcMeta) {
			$svcLabel = isset($svcMeta['label']) ? $svcMeta['label'] : $svcKey;
			print '<option value="'.dol_escape_htmltag($svcKey).'"'.(($ebkService === $svcKey) ? ' selected' : '').'>'.dol_escape_htmltag($svcLabel).'</option>';
		}
		print '</select>';
	} else {
		// ai module's service catalog is unavailable — fall back to a
		// plain text input so the admin can still type a service key.
		print '<input type="text" name="EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE" value="'.dol_escape_htmltag($ebkService).'" class="minwidth200">';
	}
	print ' '.$form->textwithpicto('', $langs->trans('EBKAiEbkCustomServiceTooltip'));
	print '</td></tr>'."\n";

	// API key — type=password so it does not leak over the shoulder.
	// We pre-fill with a placeholder ("••••") so a saved key is
	// visibly present, but the actual value is never re-rendered to
	// the browser (matches ai/admin/setup.php's behaviour).
	$ebkKeyMasked = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_CUSTOM_KEY', '');
	$ebkKeyShown  = $ebkKeyMasked !== '' ? '••••••••' : '';
	print '<tr><td>'.$langs->trans('EBKAiEbkCustomKey').'</td>';
	print '<td>';
	print '<input type="password" name="EMBEDDEDBOOKKEEPING_AI_CUSTOM_KEY" value="'.dol_escape_htmltag($ebkKeyShown).'" autocomplete="new-password" class="minwidth300">';
	print ' '.$form->textwithpicto('', $langs->trans('EBKAiEbkCustomKeyTooltip'));
	print '</td></tr>'."\n";

	// URL — only consulted when service='custom'; shown for all
	// services so the admin can override (e.g. point at a regional
	// OpenAI endpoint).
	print '<tr><td>'.$langs->trans('EBKAiEbkCustomUrl').'</td>';
	print '<td>';
	print '<input type="text" name="EMBEDDEDBOOKKEEPING_AI_CUSTOM_URL" value="'.dol_escape_htmltag($ebkUrl).'" placeholder="https://..." class="minwidth500">';
	print ' '.$form->textwithpicto('', $langs->trans('EBKAiEbkCustomUrlTooltip'));
	print '</td></tr>'."\n";

	// Model — overrides the per-service default from
	// getListOfAIServices(). Empty falls back to the catalog default.
	print '<tr><td>'.$langs->trans('EBKAiEbkCustomModel').'</td>';
	print '<td>';
	print '<input type="text" name="EMBEDDEDBOOKKEEPING_AI_CUSTOM_MODEL" value="'.dol_escape_htmltag($ebkModel).'" placeholder="gpt-4o-mini" class="minwidth300">';
	print ' '.$form->textwithpicto('', $langs->trans('EBKAiEbkCustomModelTooltip'));
	print '</td></tr>'."\n";

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

	// Bookkeeping-suggest prompts: read from EBK's own constants
	// (EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE / _POST). These are NOT stored in
	// the system AI module's AI_CONFIGURATIONS_PROMPT JSON — that namespace
	// belongs to the system AI module and is reserved for its own
	// functions (textgenerationemail / textgenerationwebpage / …). Mixing
	// our bookkeeping prompt into it would pollute a different module's
	// slot and violates CLAUDE.md §1 "独立与完整性 / 卸载不影响核心".
	//
	// When the admin hasn't saved a value yet (first visit, or both fields
	// are blank), we PRE-FILL the textarea body with the EBK module-level
	// built-in default (from AiModuleProvider::getDefaultBookkeepingPrompt()).
	// HTML placeholder text cannot be selected, edited or saved directly —
	// it disappears on the first keystroke — so placeholder alone is not a
	// workable starting point for an admin who wants to tweak and save.
	// Pre-filling `value=` makes the default fully selectable, fully
	// editable, and one click away from being saved.
	//
	// At runtime, resolvePrompt() does the same empty-→-default fallback,
	// so the textarea content and the actual prompt sent to the LLM are
	// always the same source of truth.
	$bookkeepPre  = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE', '');
	$bookkeepPost = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_PROMPT_POST', '');

	// EBK module-level built-in defaults — sourced from AiModuleProvider so
	// the prefilled textarea value and the runtime fallback are the SAME
	// source of truth and can never diverge.
	$ebkDefault = AiModuleProvider::getDefaultBookkeepingPrompt($langs);
	$ebkDefaultPre  = $ebkDefault['prePrompt'];
	$ebkDefaultPost = $ebkDefault['postPrompt'];

	// Single source of truth: $bookkeepPre wins when admin has saved it,
	// otherwise we fall back to the EBK built-in default so the textarea is
	// never empty on first load.
	$prePromptValue  = ($bookkeepPre  !== '' ? $bookkeepPre  : $ebkDefaultPre);
	$postPromptValue = ($bookkeepPost !== '' ? $bookkeepPost : $ebkDefaultPost);

	print '<tr><td>'.$langs->trans('EBKAiBookkeepPrePrompt').'</td>';
	print '<td>';
	print '<textarea name="EBK_BOOKKEEPING_PROMPT" rows="6" class="minwidth500">'.dol_escape_htmltag($prePromptValue, 0, 1).'</textarea>';
	print '<br/><span class="opacitymedium">'.$langs->trans('EBKAiBookkeepPrePromptHelp').'</span>';
	print '</td></tr>'."\n";
	print '<tr><td>'.$langs->trans('EBKAiBookkeepPostPrompt').'</td>';
	print '<td>';
	print '<textarea name="EBK_BOOKKEEPING_POST_PROMPT" rows="3" class="minwidth500">'.dol_escape_htmltag($postPromptValue, 0, 1).'</textarea>';
	print '<br/><span class="opacitymedium">'.$langs->trans('EBKAiBookkeepPostPromptHelp').'</span>';
	print '</td></tr>'."\n";

	// ---- Offline knowledge base -----------------------------------------
	// The assistant answers accounting questions out of markdown files shipped
	// with the module (Dolibarr usage / SG accounting standards / IRAS GST+tax)
	// instead of from the model's memory. EBKKnowledgeBase::select() scores each
	// file against the user's question and injects only the relevant ones, so an
	// unrelated question ships nothing.
	print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('EBKAiKnowledgeTitle').'</th></tr>'."\n";

	$kbEnabled = (int) getDolGlobalInt('EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_ENABLED', 1);
	print '<tr><td class="titlefield">'.$langs->trans('EBKAiKnowledgeEnabled').'</td>';
	print '<td><input type="checkbox" name="EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_ENABLED" value="1"'.($kbEnabled ? ' checked="checked"' : '').'></td></tr>'."\n";

	$kbMax = (int) getDolGlobalInt('EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_MAXCHARS', 6000);
	print '<tr><td>'.$langs->trans('EBKAiKnowledgeMaxChars').'</td>';
	print '<td><input type="number" name="EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_MAXCHARS" value="'.$kbMax.'" min="500" step="500" class="minwidth100">';
	print ' <span class="opacitymedium">'.$langs->trans('EBKAiKnowledgeMaxCharsHelp').'</span></td></tr>'."\n";

	// List what is on disk, with the file size, so the admin can see what the
	// assistant is being fed. Plain file names only — no path is echoed.
	$kbDir = DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/knowledge';
	$kbFiles = is_dir($kbDir) ? glob($kbDir.'/*.md') : array();
	print '<tr><td>'.$langs->trans('EBKAiKnowledgeFiles').'</td>';
	print '<td>';
	if (empty($kbFiles)) {
		print '<span class="opacitymedium">'.$langs->trans('EBKAiKnowledgeNoFiles').'</span>';
	} else {
		foreach ($kbFiles as $f) {
			$title = basename($f);
			if (preg_match('/<!--\s*kb:title=(.*?)\s*-->/i', (string) file_get_contents($f), $m)) {
				$title = $m[1];
			}
			print '<code>'.dol_escape_htmltag($title).'</code><br>';
		}
		print '<span class="opacitymedium">'.count($kbFiles).' '.$langs->trans('EBKAiKnowledgeFileCount').'</span>';
	}
	print '</td></tr>'."\n";

	$kbNotes = (string) getDolGlobalString('EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_EXTRA', '');
	print '<tr><td>'.$langs->trans('EBKAiKnowledgeNotes').'</td>';
	print '<td>';
	print '<textarea name="EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_EXTRA" rows="6" class="minwidth500">'.dol_escape_htmltag($kbNotes, 0, 1).'</textarea>';
	print '<br/><span class="opacitymedium">'.$langs->trans('EBKAiKnowledgeNotesHelp').'</span>';
	print '</td></tr>'."\n";

	print '</table>';

	print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('Save').'"></div>';
	print '</form>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
