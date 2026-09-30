<?php
/* List of salaries certificates, you can add/edit/delete it
 * Copyright (C) 2015  Yassine Belkaid <y.belkaid@nextconcept.ma>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup	Certificates	Certificates module
 * 	\brief		Certificates module descriptor.
 * 	\file		core/modules/modCertificates.class.php
 * 	\ingroup	Certificates
 * 	\brief		Certificates is for creating/Editing et deleting salaries' contracts
 */
include_once DOL_DOCUMENT_ROOT . "/core/modules/DolibarrModules.class.php";

/**
 * Description and activation class for module MyModule
 */
class modCertificates extends DolibarrModules
{

	/**
	 * 	Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * 	@param	DoliDB		$db	Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;
		$this->numero = 800800;
		$this->rights_class = 'certificates';
		$this->family = "hr";
		$this->name = preg_replace('/^mod/i', '', get_class($this));		
		$this->description = "Manage certificate types including (certificate of employment, training certificate and salary certificate)";
		$this->version  = '1.0.0';
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		$this->special = 0;
		$this->picto = 'certificates@certificates';
		$this->module_parts = array(
			'triggers' 	=> 1,
			'css' 		=> array(),
		);
		$this->dirs = array();
		$this->config_page_url = array("admin_setup.php@certificates");
		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(5, 3);
		$this->need_dolibarr_version = array(3, 0);
		$this->langfiles = array("certificate@certificates");
		$this->const = array();
		$this->tabs = array();
		// Dictionaries
		if (! isset($conf->certificates->enabled)) {
			$conf->certificates 		 = new stdClass();
			$conf->certificates->enabled = 0;
		}
		$this->dictionaries = array();
		// Boxes
		// Add here list of php file(s) stored in core/boxes that contains class to show a box.
		$this->boxes = array(); // Boxes list

		// Permissions
		$this->rights = array(); // Permission array used by this module
		$r = 0;
//// Permission id (must not be already used)
		$this->rights[$r][0] = 800801;
		$this->rights[$r][1] = 'Lire le module d\'attestations';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'read';
		$this->rights[$r][5] = '';
		$r++;

		/*$this->rights[$r][0] = 800802;
		$this->rights[$r][1] = 'Créer/modifier les attestations';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'write';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 800803;
		$this->rights[$r][1] = 'Supprimer les attestations';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'delete';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 800804;
		$this->rights[$r][1] = 'Lire les attestations pour tout le monde';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'read_all';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 800805;
		$this->rights[$r][1] = 'Créer/modifier les attestations pour tout le monde';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'write_all';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 800806;
		$this->rights[$r][1] = 'Setup certificate of users (setup and updat)';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'setup_certificate';
		$this->rights[$r][5] = '';
		$r++;*/
        		$this->rights[$r][0] = 800807;
		$this->rights[$r][1] = 'Lire Les attestations de salaire';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'read_salaire';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 800808;
		$this->rights[$r][1] = 'Demander/approuver Les attestations de salaire';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'write_salaire';
		$this->rights[$r][5] = '';
		$r++;
		/*$this->rights[$r][0] = 800803;
		$this->rights[$r][1] = 'Lire le module ';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'write_salaire';
		$this->rights[$r][5] = '';
		$r++;*/

		$this->rights[$r][0] = 800809;
		$this->rights[$r][1] = 'Lire Les attestations de travail';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'read_travail';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 800810;
		$this->rights[$r][1] = 'Demander/approuver Les attestations de travail';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'write_travail';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 800811;
		$this->rights[$r][1] = 'Lire Les attestations de stage';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'read_stage';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = 800812;
		$this->rights[$r][1] = 'Demander/approuver Les attestations de stage';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'write_stage';
		$this->rights[$r][5] = '';
		$r++;
		$r = 0;
		$this->menu[$r] = array(
			'fk_menu'	=> 'fk_mainmenu=hrm',
			'type'		=> 'left',
			'titre'		=> "ListOfCertificates",
			'leftmenu'	=> 'certificates',
			'url'		=> '/grh/certificates/list.php',
			'langs'		=> 'certificate@certificates',
			'position'	=> 50,
			'enabled' 	=> '$conf->certificates->enabled',
			'perms'	 	=> '$user->rights->certificates->read_salaire',
			'target'	=> '',
			'user'		=> 2 // 0=Menu for internal users, 1=external users, 2=both
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu'	=> 'fk_mainmenu=hrm,fk_leftmenu=certificates',
			'type'		=> 'left',
			'titre'		=> "ListOfCertificatesOfSalaries",
			'url'		=> '/grh/certificates/list.php',
			'langs'		=> 'certificate@certificates',
			'position'	=> 51,
			'perms'	 	=> '$user->rights->certificates->read_salaire',
			'enabled' 	=> '$conf->certificates->enabled',
			'target'	=> '',
			'user'		=> 2
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu'	=> 'fk_mainmenu=hrm,fk_leftmenu=certificates',
			'type'		=> 'left',
			'titre'		=> "ListOfCertificatesOfWork",
			'url'		=> '/grh/certificates/work_certificate.php',
			'langs'		=> 'certificate@certificates',
			'position'	=> 52,
			'perms'	 	=> '$user->rights->certificates->read_travail',

			'enabled' 	=> '$conf->certificates->enabled',
			'target'	=> '',
			'user'		=> 2
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu'	=> 'fk_mainmenu=hrm,fk_leftmenu=certificates',
			'type'		=> 'left',
			'titre'		=> "ListOfCertificatesOfTraining",
			'url'		=> '/grh/certificates/training_certificate.php',
			'langs'		=> 'certificate@certificates',
			'position'	=> 53,
			'perms'	 	=> '$user->rights->certificates->read_stage',

			'enabled' 	=> '$conf->certificates->enabled',
			'target'	=> '',
			'user'		=> 2
		);
		$r++;
		// Exports
		$r = 0;
	}
	/**
	 * Function called when module is enabled.
	 * The init function add constants, boxes, permissions and menus
	 * (defined in constructor) into Dolibarr database.
	 * It also creates data directories
	 *
	 * 	@param		string	$options	Options when enabling module ('', 'noboxes')
	 * 	@return		int					1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$sql = array();

		$result = $this->loadTables();

		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 * Remove from database constants, boxes and permissions from Dolibarr database.
	 * Data directories are not deleted
	 *
	 * 	@param		string	$options	Options when enabling module ('', 'noboxes')
	 * 	@return		int					1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		// $sql = array("DROP TABLE llx_certificates");
		// return $this->_remove($sql, $options);
	}

	/**
	 * Create tables, keys and data required by module
	 * Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
	 * and create data commands must be stored in directory /mymodule/sql/
	 * This function is called by this->init
	 *
	 * 	@return		int		<=0 if KO, >0 if OK
	 */
	private function loadTables()
	{
		return $this->_load_tables('/grh/certificates/sql/');
	}
}
