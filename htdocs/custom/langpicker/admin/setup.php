<?php

// Load Dolibase
include_once '../autoload.php';
// Load Dolibase SetupPage class
dolibase_include_once('/core/pages/setup.php');
// Load object class
dol_include_once('/langpicker/class/langpicker.class.php');
// Load FormAdmin class
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';

// Create Setup Page using Dolibase
$page = new SetupPage('Setup', '$user->admin');

// Get parameters
$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$id = GETPOST('id', 'int');
$lang_code = GETPOST('lang_code', 'alpha');

global $langs, $conf;

// Init objects
$langpicker = new LangPicker();
$formadmin = new FormAdmin($langpicker->db);

// Set fields
$fields[] = new Field('lang_code', 'Language', 'required|validID');
$page->setFields($fields);

// Set actions ---

// Add
if ($action == 'add' && $page->checkFields())
{
	$data = array(
		'lang_code' => str_escape($lang_code),
		'position' => $langpicker->getNextPosition()
	);

	$id = $langpicker->create($data);

	if ($id > 0) {
		// Creation OK
		setEventMessage($langs->trans('AddLangSuccess'), 'mesgs');
		dolibase_redirect($_SERVER['PHP_SELF']);
	}
}

// Delete

else if ($action == 'delete')
{
	$page->askForConfirmation($_SERVER["PHP_SELF"] . '?id=' . $id, 'Delete', 'ConfirmDeleteLang', 'confirm_delete');
}

else if ($action == 'confirm_delete' && $confirm == 'yes')
{
	$result = $langpicker->deleteWhere('rowid = '.$id);
	if ($result > 0) {
		setEventMessage($langs->trans('DeleteLangSuccess'), 'mesgs');
		dolibase_redirect($_SERVER['PHP_SELF']);
	}
}

// Up

else if ($action == 'up')
{
	$result = $langpicker->up($id);
	if ($result > 0) {
		dolibase_redirect($_SERVER['PHP_SELF']);
	}
}

// Down

else if ($action == 'down')
{
	$result = $langpicker->down($id);
	if ($result > 0) {
		dolibase_redirect($_SERVER['PHP_SELF']);
	}
}

// --- End actions

$page->begin();

/*
 * Settings
 */

$page->addSubTitle('LoginPageSettings');

$page->newOptionsTable();

$page->addSwitchOption('AddToLoginPage', 'LANG_PICKER_ADD_TO_LOGIN_PAGE');

if ($conf->global->LANG_PICKER_ADD_TO_LOGIN_PAGE)
{
	$default_lang = $conf->global->LANG_PICKER_LOGIN_PAGE_DEFAULT_LANG;

	if (empty($default_lang)) {
		$default_lang = 'en_US';
	}

	$page->addOption('DefaultLangOnLoginPage', $formadmin->select_language($default_lang, 'LANG_PICKER_LOGIN_PAGE_DEFAULT_LANG', 0, null, '', 0, 0, 'left'), 'LANG_PICKER_LOGIN_PAGE_DEFAULT_LANG', '', '40%');
}

$page->closeTable();

$page->addLineBreak();

$page->addSubTitle('GeneralSettings');

$page->newOptionsTable();

$page->addSwitchOption('HideLangPicker', 'LANG_PICKER_HIDDEN');

if (empty($conf->global->LANG_PICKER_HIDDEN))
{
	$page->closeTable();

	$page->addLineBreak();

	$page->addSubTitle('LanguageSettings');

	/*
	 * Add form
	 */

	// open form
	$page->openForm('add');

	// open table
	$table_header = array(
		array('name' => 'Parameter', 'attr' => 'width="30%"'),
		array('name' => 'Value')
	);

	$page->openTable($table_header);

	// Lang Code
	$page->openRow();
	$page->addColumn($langs->trans('Language'), 'class="fieldrequired"');
	$page->addColumn($formadmin->select_language('', 'lang_code', 0, null, $langs->trans('SelectLanguage')));
	$page->closeRow();

	// close table
	$page->closeTable();
	$page->addLineBreak();

	// submit button
	echo '<div class="center"><input class="butAction" value="'.$langs->trans('Add').'" type="submit"></div>';

	// close form
	$page->closeForm();
	$page->addLineBreak();

	/*
	 * Languages list
	 */

	$table_header = array(
		array('name' => 'Language'),
		array('name' => 'LangCode'),
		array('name' => 'Position', 'attr' => 'align="center"'),
		array('name' => '', 'attr' => 'align="right"')
	);

	$page->openTable($table_header);

	if ($langpicker->fetchAll(0, 0, 't.position', 'ASC'))
	{
		$odd = true;
		$i = 0;

		foreach ($langpicker->lines as $lang)
		{
			$odd = ! $odd;
			$page->openRow($odd);

			// Language with picto
			$picto = picto_from_langcode($lang->lang_code);
			$trans = ($lang->lang_code == 'auto' ? $langs->trans("AutoDetectLang") : $langs->trans("Language_".$lang->lang_code));
			$page->addColumn($picto.' '.$trans);

			// Lang Code
			$page->addColumn($lang->lang_code);

			// Position
			$up_link = '';
			$down_link = '';
			if ($i > 0) {
				$up_link = '<a class="lineupdown" href="'.$_SERVER["PHP_SELF"].'?action=up&id='.$lang->id.'">'.img_up('default', 0, 'imgupforline').'</a>';
			}
			if ($i < $langpicker->count - 1) {
				$down_link = '<a class="lineupdown" href="'.$_SERVER["PHP_SELF"].'?action=down&id='.$lang->id.'">'.img_down('default', 0, 'imgdownforline').'</a>';
			}
			$page->addColumn($up_link.$down_link, 'class="linecolmove tdlineupdown" align="center"');

			// Delete link
			$page->addColumn('<a href="'.$_SERVER['PHP_SELF'].'?action=delete&id='.$lang->id.'">'.img_delete($langs->trans("Delete")).'</a>', 'align="right"');

			$page->closeRow();

			$i++;
		}
	}
} // end if (empty($conf->global->LANG_PICKER_HIDDEN))

$page->closeTable();

$page->end();
