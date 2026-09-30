<?php
/* Copyright (C) 2003      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   docsemployes     Module docsemployes
 *  \brief      Example of a module descriptor.
 *				Such a file must be copied into htdocs/docsemployes/core/modules directory.
 *  \file       htdocs/docsemployes/core/modules/moddocsemployes.class.php
 *  \ingroup    docsemployes
 *  \brief      Description and activation file for module docsemployes
 */
include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module docsemployes
 */
class moddocsemployes extends DolibarrModules
{
	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *   @param      DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
        global $langs,$conf;

        $this->db = $db;

		// $this->editor_name = 'Editor';
		// $this->editor_url = 'https://www.site.ma';
		
		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 677720088;

		$this->editor_name = 'NextGestion';
		$this->editor_url = 'https://www.nextgestion.com';

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'docsemployes';

		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		// It is used to group modules in module setup page
		$this->family = "NextGestion";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i','',get_class($this));
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "ModuleDesc677720088";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = '14.2';
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 0;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto='docsemployes@docsemployes';
		
		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		// for default path (eg: /docsemployes/core/xxxxx) (0=disable, 1=enable)
		// for specific path of parts (eg: /docsemployes/core/modules/barcode)
		// for specific css file (eg: /docsemployes/css/docsemployes.css.php)
		//$this->module_parts = array(
		//                        	'triggers' => 0,                                 	// Set this to 1 if module has its own trigger directory (core/triggers)
		//							'login' => 0,                                    	// Set this to 1 if module has its own login method directory (core/login)
		//							'substitutions' => 0,                            	// Set this to 1 if module has its own substitution function file (core/substitutions)
		//							'menus' => 0,                                    	// Set this to 1 if module has its own menus handler directory (core/menus)
		//							'theme' => 0,                                    	// Set this to 1 if module has its own theme directory (theme)
		//                        	'tpl' => 0,                                      	// Set this to 1 if module overwrite template dir (core/tpl)
		//							'barcode' => 0,                                  	// Set this to 1 if module has its own barcode directory (core/modules/barcode)
		//							'models' => 0,                                   	// Set this to 1 if module has its own models directory (core/modules/xxx)
		//							'css' => array('/docsemployes/css/docsemployes.css.php'),	// Set this to relative path of css file if module has its own css file
	 	//							'js' => array('/docsemployes/js/docsemployes.js'),          // Set this to relative path of js file if module must load a js on all pages
		//							'hooks' => array('hookcontext1','hookcontext2')  	// Set here all hooks context managed by module
		//							'dir' => array('output' => 'othermodulename'),      // To force the default directories names
		//							'workflow' => array('WORKFLOW_MODULE1_YOURACTIONTYPE_MODULE2'=>array('enabled'=>'! empty($conf->module1->enabled) && ! empty($conf->module2->enabled)', 'picto'=>'yourpicto@docsemployes')) // Set here all workflow context managed by module
		//                        );
		$this->module_parts = array(
		    'hooks' => array('docsemployesindex','docsemployescard','completetabs','main'),
		    'css' => array('/docsemployes/css/docsemployes.css'),
		    'js' => array('/docsemployes/js/docsemployes.js'),
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/docsemployes/temp");
		$this->dirs = array();

		// Config pages. Put here list of php page, stored into docsemployes/admin directory, to use to setup module.
		// $this->config_page_url = array();
		$this->config_page_url = array("docsemployes_setup.php@docsemployes");

		// Dependencies
		$this->hidden = false;			// A condition to hide module
		$this->depends = array();		// List of modules id that must be enabled if this module is enabled
		$this->requiredby = array();	// List of modules id to disable if this one is disabled
		$this->conflictwith = array();	// List of modules id this module is in conflict with
		$this->phpmin = array(5,0);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(3,0);	// Minimum version of Dolibarr required by module
		$this->langfiles = array("docsemployes@docsemployes");

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(0=>array('MYMODULE_MYNEWCONST1','chaine','myvalue','This is a constant to add',1),
		//                             1=>array('MYMODULE_MYNEWCONST2','chaine','myvalue','This is another constant to add',0, 'current', 1)
		// );
		$this->const = array();


		$this->tabs = array('user:+tab_docsemploye:tab_docsemploye:docsemployes@docsemployes:$user->rights->docsemployes->lire:/docsemployes/index.php?id=__ID__');


		// Array to add new pages in new tabs
		// Example: $this->tabs = array('objecttype:+tabname1:Title1:docsemployes@docsemployes:$user->rights->docsemployes->read:/docsemployes/mynewtab1.php?id=__ID__',  	// To add a new tab identified by code tabname1
        //                              'objecttype:+tabname2:Title2:docsemployes@docsemployes:$user->rights->othermodule->read:/docsemployes/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2
        //                              'objecttype:-tabname:NU:conditiontoremove');                                                     						// To remove an existing tab identified by code tabname
		// where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'invoice_supplier' to add a tab in supplier invoice view
		// 'member'           to add a tab in fundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in customer order view
		// 'order_supplier'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'payment_supplier' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view
        // $this->tabs = array();

        // Dictionaries
	    if (! isset($conf->docsemployes->enabled))
        {
        	$conf->docsemployes=new stdClass();
        	$conf->docsemployes->enabled=0;
        }
		$this->dictionaries=array();
        /* Example:
        if (! isset($conf->docsemployes->enabled)) $conf->docsemployes->enabled=0;	// This is to avoid warnings
        $this->dictionaries=array(
            'langs'=>'docsemployes@docsemployes',
            'tabname'=>array(MAIN_DB_PREFIX."table1",MAIN_DB_PREFIX."table2",MAIN_DB_PREFIX."table3"),		// List of tables we want to see into dictonnary editor
            'tablib'=>array("Table1","Table2","Table3"),													// Label of tables
            'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table1 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table2 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table3 as f'),	// Request to select fields
            'tabsqlsort'=>array("label ASC","label ASC","label ASC"),																					// Sort order
            'tabfield'=>array("code,label","code,label","code,label"),																					// List of fields (result of select to show dictionary)
            'tabfieldvalue'=>array("code,label","code,label","code,label"),																				// List of fields (list of fields to edit a record)
            'tabfieldinsert'=>array("code,label","code,label","code,label"),																			// List of fields (list of fields for insert)
            'tabrowid'=>array("rowid","rowid","rowid"),																									// Name of columns with primary key (try to always name it 'rowid')
            'tabcond'=>array($conf->docsemployes->enabled,$conf->docsemployes->enabled,$conf->docsemployes->enabled)												// Condition to show each dictionary
        );
        */

        // Boxes
		// Add here list of php file(s) stored in core/boxes that contains class to show a box.
        $this->boxes = array();			// List of boxes
		// Example:
		//$this->boxes=array(array(0=>array('file'=>'myboxa.php','note'=>'','enabledbydefaulton'=>'Home'),1=>array('file'=>'myboxb.php','note'=>''),2=>array('file'=>'myboxc.php','note'=>'')););

		// Permissions
		$this->rights = array();		// Permission array used by this module
		$r=0;

		// Add here list of permission defined by an id, a label, a boolean and two constant strings.

		$this->rights[$r][0] = $this->numero + $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'Consulter';	// Permission label
		$this->rights[$r][2] = 'r';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$this->rights[$r][3] = 1; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'lire';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		$r++;

		$this->rights[$r][0] = $this->numero + $r;
		$this->rights[$r][1] = 'Ajouter/Modifier';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'creer';
		$r++;

		$this->rights[$r][0] = $this->numero + $r;
		$this->rights[$r][1] = 'Supprimer';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'supprimer';
		$r++;

		// Main menu entries
		$this->menu = array();			// List of menus to add
		$r=0;

		// Add here entries to declare new menus

		// Top Menu
		$this->menu[$r]=array(	'fk_menu'=>0,
			'type'=>'top',
			'titre'=>'docsemployes',
			'mainmenu'=>'docsemployes',
			'leftmenu'=>'docsemployes',
			'url'=>'/docsemployes/index.php',
			'langs'=>'docsemployes@docsemployes',
			'position'=>400,
			'enabled'=>'1',
			'perms'=>'$user->rights->docsemployes->lire',
			'target'=>'',
			'user'=>2);
		$r++;

		// Top Menu
		// $this->menu[$r]=array(	'fk_menu'=>0,
		// 	'type'=>'top',
		// 	'titre'=>'docsemployes',
		// 	'mainmenu'=>'docsemployes',
		// 	'leftmenu'=>'documentsemploye',
		// 	'url'=>'/docsemployes/documents.php',
		// 	'langs'=>'docsemployes@docsemployes',
		// 	'position'=>400,
		// 	'enabled'=>'1',
		// 	'perms'=>'$user->rights->docsemployes->lire',
		// 	'target'=>'',
		// 	'user'=>2);
		// $r++;

		// // Liste
		// // Liste
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=docsemployes',
			'type'=>'left',
			'titre'=>'docsemployes',
            'leftmenu'=>'docsemployes1',
			'url'=>'/docsemployes/index.php',
			'langs'=>'docsemployes@docsemployes',
			'position'=>1,
			'enabled'=>'1',
			'perms'=>'$user->rights->docsemployes->lire',
			'target'=>'',
			'user'=>2);
		$r++;
			$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=docsemployes,fk_leftmenu=docsemployes1',
				'type'=>'left',
				'titre'=>'Liste_des_docsemployes',
				'url'=>'/docsemployes/index.php',
				'langs'=>'docsemployes@docsemployes',
				'position'=> 2,
				'enabled'=>'1',
				'perms'=>'$user->rights->docsemployes->lire',
				'target'=>'',
				'user'=>2);		
			$r++;
			$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=docsemployes,fk_leftmenu=docsemployes1',
				'type'=>'left',
				'titre'=>'Ajouter_un_docsemployes',
				'url'=>'/docsemployes/card.php?action=add',
				'langs'=>'docsemployes@docsemployes',
				'position'=> 3,
				'enabled'=>'1',
				'perms'=>'$user->rights->docsemployes->creer',
				'target'=>'',
				'user'=>2);
			$r++;

			// Liste des types documnet
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=docsemployes',
			'type'=>'left',
			'titre'=>'type_document',
            'leftmenu'=>'type_document',
			'url'=>'/docsemployes/type_document/index.php',
			'langs'=>'docsemployes@docsemployes',
			'position'=>4,
			'enabled'=>'1',
			'perms'=>'$user->rights->docsemployes->lire',
			'target'=>'',
			'user'=>2);
		$r++;
			$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=docsemployes,fk_leftmenu=type_document',
				'type'=>'left',
				'titre'=>'Liste_des_type_document',
				'url'=>'/docsemployes/type_document/index.php',
				'langs'=>'docsemployes@docsemployes',
				'position'=> 5,
				'enabled'=>'1',
				'perms'=>'$user->rights->docsemployes->lire',
				'target'=>'',
				'user'=>2);		
			$r++;
			$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=docsemployes,fk_leftmenu=type_document',
				'type'=>'left',
				'titre'=>'Ajouter_un_type_document',
				'url'=>'/docsemployes/type_document/card.php?action=add',
				'langs'=>'docsemployes@docsemployes',
				'position'=> 6,
				'enabled'=>'1',
				'perms'=>'$user->rights->docsemployes->creer',
				'target'=>'',
				'user'=>2);
			$r++;

		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=docsemployes',
			'type'=>'left',
			'titre'=>'Setup',
            'leftmenu'=>'configuration',
			'url'=>'/docsemployes/admin/docsemployes_setup.php',
			'langs'=>'docsemployes@docsemployes',
			'position'=>4,
			'enabled'=>'1',
			'perms'=>'$user->admin',
			'target'=>'',
			'user'=>2);
		$r++;
		// Exports
		$r=1;
	}



	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function init($options='')
	{
		global $conf;
		$sqlm = array();

		dol_include_once('/docsemployes/class/docsemployes.class.php');
		$object  = new docsemployes($this->db);

		$object->InitDocsEmpl();

		return $this->_init($sqlm, $options);
	}

	/**
	 *		Function called when module is disabled.
	 *      Remove from database constants, boxes and permissions from Dolibarr database.
	 *		Data directories are not deleted
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function remove($options='')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}

}
