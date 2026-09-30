<?php

// Load Dolibase
dol_include_once('/langpicker/autoload.php');
// Load Dolibase Module class
dolibase_include_once('/core/class/module.php');

/**
 *	Class to describe and enable module
 */
class modLangPicker extends DolibaseModule
{
	/**
	 * Function called after module configuration.
	 * 
	 */
	public function loadSettings()
	{
		global $conf;

		// Update picto for Dolibarr 12++
		if (function_exists('version_compare') && version_compare(DOL_VERSION, '12.0.0') >= 0) {
			$this->picto = "translate_128.png@langpicker";
		}

		// Cleanup legacy constant (no longer used in Dolibarr 22-compatible layout)
		// We remove it from database to keep admin setup page clean.
		if (!empty($conf->global->LANG_PICKER_POSITION)) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
			// Delete for current entity only (constants are entity-scoped)
			dolibarr_del_const($this->db, 'LANG_PICKER_POSITION', (int) $conf->entity);
			unset($conf->global->LANG_PICKER_POSITION);
		}

		// Permissions
		$this->addPermission("use", "Use Language Picker");

		// Hooks
		$this->enableHooks(array(
			'toprightmenu',
			'mainloginpage',
			'login'
		));

		// Add CSS & JS files
		$this->addCssFile('langpicker.css.php');
		$this->addJsFile('langpicker.js');
	}
}
