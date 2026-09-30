<?php
/* Copyright (C) 2019  NextConcept <st2@nextconcept.ma>
 */
include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module MyModule
 */
class modgrh extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
        global $langs,$conf;

        $this->db = $db;
		$this->numero = 55087054;
		$this->editor_name = 'NextGestion';
		$this->editor_url = 'https://www.nextgestion.com';

		$this->rights_class = "grh";
		$this->family = "NextGestion";
		$this->module_position = 1;
		$this->name = preg_replace('/^mod/i','',get_class($this));
		$this->description = "DESCRIPTION55087054";
		$this->version = '16.4';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->special = 0;
		$this->picto = 'grh@grh';

		$this->module_parts = array(
			'triggers' 	=> 1,
			'css' 		=> array('grh/css/grh.css'),
			'js' 		=> array('grh/js/grh.js'),
		);
		$this->dirs = array();
		$this->config_page_url = array();

		// Dependencies
		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(5,0);
		$this->need_dolibarr_version = array(3,0);
		$this->langfiles = array("grh@grh");
		$this->const = array();
        $this->tabs = array();

        // Dictionaries
	    if (!isset($conf->grh->enabled))
        {
        	$conf->grh=new stdClass();
        	$conf->grh->enabled=0;
        }
		$this->dictionaries=array();
        $this->boxes = array();
		$this->cronjobs = array();
		$this->rights = array();
		$r=10;
		$r++;

		// Permission Gestion Avancée des Ressources Humains
		$this->rights[$r][0] = $this->numero + $r;
		$this->rights[$r][1] = 'Consulter';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'lire';
		$r++;
		$this->rights[$r][0] = $this->numero + $r;
		$this->rights[$r][1] = 'Ajouter / Modifier';
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
		// $this->rights[$r][0] = $this->numero + $r;
		// //// Permission label
		// $this->rights[$r][1] = 'Consulter le contrat de salarie';
		// //// Permission by default for new user (0/1)
		// $this->rights[$r][3] = 1;
		// //// In php code, permission will be checked by test
		// //// if ($user->rights->permkey->level1->level2)
		// $this->rights[$r][4] = 'salariescontracts';
		// //// In php code, permission will be checked by test
		// //// if ($user->rights->permkey->level1->level2)
		// $this->rights[$r][5] = 'read';
		// $r++;

		// $this->rights[$r][0] = $this->numero + $r; 				// Permission id (must not be already used)
		// $this->rights[$r][1] = 'Créer / modifier vos propres contrats';	// Permission label
		// $this->rights[$r][3] = 1; 					// Permission by default for new user (0/1)
		// $this->rights[$r][4] = 'salariescontracts';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		// $this->rights[$r][5] = 'write';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		// $r++;

		// $this->rights[$r][0] = $this->numero + $r; 				// Permission id (must not be already used)
		// $this->rights[$r][1] = 'Supprimer les contrats';	// Permission label
		// $this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		// $this->rights[$r][4] = 'salariescontracts';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		// $this->rights[$r][5] = 'delete';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		// $r++;

		// $this->rights[$r][0] = $this->numero + $r; 				// Permission id (must not be already used)
		// $this->rights[$r][1] = 'Consulter les contrats pour tout le monde';	// Permission label
		// $this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		// $this->rights[$r][4] = 'salariescontracts';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		// $this->rights[$r][5] = 'read_all';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		// $r++;

		// $this->rights[$r][0] = $this->numero + $r; 				// Permission id (must not be already used)
		// $this->rights[$r][1] = 'Création / modification des contrats pour tout le monde';	// Permission label
		// $this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		// $this->rights[$r][4] = 'salariescontracts';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		// $this->rights[$r][5] = 'write_all';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		// $r++;

		// $this->rights[$r][0] = $this->numero + $r; 				// Permission id (must not be already used)
		// $this->rights[$r][1] = 'Configurer les contrats des utilisateurs (configuration et mise à jour)';	// Permission label
		// $this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		// $this->rights[$r][4] = 'salariescontracts';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		// $this->rights[$r][5] = 'define_contract';				// In php code, permission will be checked by test if ($user->rights->permkey->level1->level2)
		// $r++;

		// Liste des attestations
		// $this->rights[$r][0] = $this->numero + $r;
		// $this->rights[$r][1] = 'Consulter le module d\'attestations';
		// $this->rights[$r][3] = 1;
		// $this->rights[$r][4] = 'certificates';
		// $this->rights[$r][5] = 'read';
		// $r++;
		// $this->rights[$r][0] = $this->numero + $r;
		// $this->rights[$r][1] = 'Consulter Les attestations de salaire';
		// $this->rights[$r][3] = 1;
		// $this->rights[$r][4] = 'certificates';
		// $this->rights[$r][5] = 'read_salaire';
		// $r++;

		// $this->rights[$r][0] = $this->numero + $r;
		// $this->rights[$r][1] = 'Demander/approuver Les attestations de salaire';
		// $this->rights[$r][3] = 1;
		// $this->rights[$r][4] = 'certificates';
		// $this->rights[$r][5] = 'write_salaire';
		// $r++;

		// $this->rights[$r][0] = $this->numero + $r;
		// $this->rights[$r][1] = 'Consulter Les attestations de travail';
		// $this->rights[$r][3] = 0;
		// $this->rights[$r][4] = 'certificates';
		// $this->rights[$r][5] = 'read_travail';
		// $r++;

		// $this->rights[$r][0] = $this->numero + $r;
		// $this->rights[$r][1] = 'Demander/approuver Les attestations de travail';
		// $this->rights[$r][3] = 0;
		// $this->rights[$r][4] = 'certificates';
		// $this->rights[$r][5] = 'write_travail';
		// $r++;

		// $this->rights[$r][0] = $this->numero + $r;
		// $this->rights[$r][1] = 'Consulter Les attestations de stage';
		// $this->rights[$r][3] = 0;
		// $this->rights[$r][4] = 'certificates';
		// $this->rights[$r][5] = 'read_stage';
		// $r++;

		// $this->rights[$r][0] = $this->numero + $r;
		// $this->rights[$r][1] = 'Demander/approuver Les attestations de stage';
		// $this->rights[$r][3] = 0;
		// $this->rights[$r][4] = 'certificates';
		// $this->rights[$r][5] = 'write_stage';
		$r=0;

		$this->menu[$r]=array(	'fk_menu'=>0,
			'type'=>'top',
			'titre'=>'Gestion_RH',
			'mainmenu'=>'grh',
			'leftmenu'=>'grh',
			'url'=>'/grh/pointage/listtime.php',
			'langs'=>'grh@grh',
			'position'=>100,
			'enabled'=>'1',
			'perms'=>'$user->rights->grh->lire',
			'target'=>'',
			'user'=>2);
		$r++;
			$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=grh',
				'type'=>'left',
				'titre'=>'Gestion_RH',
				//'mainmenu'=>'grh',
	            'leftmenu'=>'grh1',
				'url'=>'/grh/pointage/listtime.php',
				'langs'=>'grh@grh',
				'position'=>1,
				'enabled'=>'1',
				'perms'=>'$user->rights->grh->lire',
				'target'=>'',
				'user'=>2);
			$r++;
				$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=grh,fk_leftmenu=grh1',
					'type'=>'left',
					'titre'=>'Pointage_des_Personnels',
					'url'=>'/grh/pointage/listtime.php',
					'langs'=>'grh@grh',
					'position'=>2,
					'enabled'=>'1',
					'perms'=>'$user->rights->grh->lire',
					'target'=>'',
					'user'=>2);		
				$r++;
				$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=grh,fk_leftmenu=grh1',
					'type'=>'left',
					'titre'=>'Dossiers_médicaux',
					'url'=>'/grh/medical/index.php',
					'langs'=>'grh@grh',
					'position'=>3,
					'enabled'=>'1',
					'perms'=>'$user->rights->grh->lire',
					'target'=>'',
					'user'=>2);		
				$r++;
				$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=grh,fk_leftmenu=grh1',
					'type'=>'left',
					'titre'=>'Les_avances_salariés',
					'url'=>'/grh/avance_utilisateur/index.php',
					'langs'=>'grh@grh',
					'position'=>4,
					'enabled'=>'1',
					'perms'=>'$user->rights->grh->lire',
					'target'=>'',
					'user'=>2);		
				$r++;
				$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=grh,fk_leftmenu=grh1',
					'type'=>'left',
					'titre'=>'CNSS_des_salariés',
					'url'=>'/grh/cnss_utilisateur/index.php',
					'langs'=>'grh@grh',
					'position'=>5,
					'enabled'=>'1',
					'perms'=>'$user->rights->grh->lire',
					'target'=>'',
					'user'=>2);		
				$r++;
				$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=grh,fk_leftmenu=grh1',
					'type'=>'left',
					'titre'=>'Gestion_de_Paie',
					'url'=>'/grh/pointage/racapsp.php',
					'langs'=>'grh@grh',
					'position'=>6,
					'enabled'=>'1',
					'perms'=>'$user->rights->grh->lire',
					'target'=>'',
					'user'=>2);		
				$r++;
				$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=grh,fk_leftmenu=grh1',
					'type'=>'left',
					'titre'=>'Recap_Globale',
					'url'=>'/grh/pointage/list.php',
					'langs'=>'grh@grh',
					'position'=>7,
					'enabled'=>'1',
					'perms'=>'$user->rights->grh->lire',
					'target'=>'',
					'user'=>2);		
				$r++;
				$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=grh,fk_leftmenu=grh1',
					'type'=>'left',
					'titre'=>'Charges_mois',
					'url'=>'/grh/charge_mois/index.php',
					'langs'=>'grh@grh',
					'position'=>8,
					'enabled'=>'1',
					'perms'=>'$user->rights->grh->lire',
					'target'=>'',
					'user'=>2);		
				$r++;
				$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=grh,fk_leftmenu=grh1',
					'type'=>'left',
					'titre'=>'État_CNSS',
					'url'=>'/grh/pointage/cnss.php',
					'langs'=>'grh@grh',
					'position'=>9,
					'enabled'=>'1',
					'perms'=>'$user->rights->grh->lire',
					'target'=>'',
					'user'=>2);
				// $r++;
				// $this->menu[$r]=array('fk_menu'=>'fk_mainmenu=grh,fk_leftmenu=grh1',
				// 	'type'=>'left',
				// 	'titre'=>'Infos des salariés',
				// 	'url'=>'/grh/utilisateur_info/index.php',
				// 	'langs'=>'grh@grh',
				// 	'position'=>9,
				// 	'enabled'=>'1',
				// 	'perms'=>'$user->rights->grh->lire',
				// 	'target'=>'',
				// 	'user'=>2);
			// $r++;

		// Gestion des Contrats
		// $this->menu[$r] = array(
		// 		'fk_menu'	=> 'fk_mainmenu=grh',
		// 		'type'		=> 'left',
		// 		'titre'		=> "ListOfSalaries",
		// 		'leftmenu'	=> 'salariescontracts',
		// 		'url'		=> '/grh/salariescontracts/list.php',
		// 		'langs'		=> 'scontracts@salariescontracts',
		// 		'position'	=> 30,
		// 		'enabled' 	=> '1',
		// 		'perms'=>'$user->rights->grh->salariescontracts->read',
		// 		'target'	=> '',
		// 		'user'		=> 2 // 0=Menu for internal users, 1=external users, 2=both
		// 	);
		// 	$r++;
		// 		$this->menu[$r] = array(
		// 			'fk_menu'	=> 'fk_mainmenu=grh,fk_leftmenu=salariescontracts',
		// 			'type'		=> 'left',
		// 			'titre'		=> "Liste des contrats",
		// 			'url'		=> '/grh/salariescontracts/list.php?leftmenu=grh',
		// 			'langs'		=> 'scontracts@salariescontracts',
		// 			'position'	=> 31,
		// 			'perms'=>'$user->rights->grh->salariescontracts->read',
		// 			'enabled' 	=> '1',
		// 			'target'	=> '',
		// 			'user'		=> 2
		// 		);
		// 		$r++;
		// 		$this->menu[$r] = array(
		// 			'fk_menu'	=> 'fk_mainmenu=grh,fk_leftmenu=salariescontracts',
		// 			'type'		=> 'left',
		// 			'titre'		=> "Nouveau contrat",
		// 			'url'		=> '/grh/salariescontracts/card.php?action=request',
		// 			'langs'		=> 'scontracts@salariescontracts',
		// 			'position'	=> 32,
		// 			'perms'=>'$user->rights->grh->salariescontracts->write',
		// 			'enabled' 	=> '1',
		// 			'target'	=> '',
		// 			'user'		=> 2
		// 		);
		// 		$r++;

		// // Liste des attestations		
		// $this->menu[$r] = array(
		// 	'fk_menu'	=> 'fk_mainmenu=grh',
		// 	'type'		=> 'left',
		// 	'titre'		=> "ListOfCertificates",
		// 	'leftmenu'	=> 'certificates',
		// 	'url'		=> '/grh/certificates/list.php',
		// 	'langs'		=> 'certificate@certificates',
		// 	'position'	=> 50,
		// 	'enabled' 	=> '1',
		// 	'perms'	 	=> '$user->rights->grh->certificates->read_salaire',
		// 	'target'	=> '',
		// 	'user'		=> 2 // 0=Menu for internal users, 1=external users, 2=both
		// );
		// $r++;
		// 	$this->menu[$r] = array(
		// 		'fk_menu'	=> 'fk_mainmenu=grh,fk_leftmenu=certificates',
		// 		'type'		=> 'left',
		// 		'titre'		=> "ListOfCertificatesOfSalaries",
		// 		'url'		=> '/grh/certificates/list.php',
		// 		'langs'		=> 'certificate@certificates',
		// 		'position'	=> 51,
		// 		'perms'	 	=> '$user->rights->grh->certificates->read_salaire',
		// 		'enabled' 	=> '1',
		// 		'target'	=> '',
		// 		'user'		=> 2
		// 	);
		// 	$r++;
		// 	$this->menu[$r] = array(
		// 		'fk_menu'	=> 'fk_mainmenu=grh,fk_leftmenu=certificates',
		// 		'type'		=> 'left',
		// 		'titre'		=> "ListOfCertificatesOfWork",
		// 		'url'		=> '/grh/certificates/work_certificate.php',
		// 		'langs'		=> 'certificate@certificates',
		// 		'position'	=> 52,
		// 		'perms'	 	=> '$user->rights->grh->certificates->read_travail',

		// 		'enabled' 	=> '1',
		// 		'target'	=> '',
		// 		'user'		=> 2
		// 	);
		// 	$r++;
		// 	$this->menu[$r] = array(
		// 		'fk_menu'	=> 'fk_mainmenu=grh,fk_leftmenu=certificates',
		// 		'type'		=> 'left',
		// 		'titre'		=> "ListOfCertificatesOfTraining",
		// 		'url'		=> '/grh/certificates/training_certificate.php',
		// 		'langs'		=> 'certificate@certificates',
		// 		'position'	=> 53,
		// 		'perms'	 	=> '$user->rights->grh->certificates->read_stage',

		// 		'enabled' 	=> '1',
		// 		'target'	=> '',
		// 		'user'		=> 2
		// 	);
		// 	$r++;


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
	public function init($options='')
	{
		$sql = array();
		global $langs, $conf;

		dol_include_once('/grh/pointage/class/pointage.class.php');
		
		$this->_load_tables('/grh/sql/');

		$object = new pointage($this->db);
		$object->InitGRH();

		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 * Remove from database constants, boxes and permissions from Dolibarr database.
	 * Data directories are not deleted
	 *
	 * @param      string	$options    Options when enabling module ('', 'noboxes')
	 * @return     int             	1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{

		$firstuse = GETPOST("firstuse",'alpha');

		if($firstuse == 'firstuse'){
			$sql2 = "DELETE FROM `".MAIN_DB_PREFIX."l_pointage` WHERE 1>0";
			$resql = $this->db->query($sql2);
			$sql2 = "DELETE FROM `".MAIN_DB_PREFIX."salaire_user` WHERE 1>0";
			$resql = $this->db->query($sql2);
		}

		$sql2 = "DELETE FROM `".MAIN_DB_PREFIX."menu` WHERE module like '%modgestion_grh%';";
		$resql = $this->db->query($sql2);

		$sql2 = "DELETE FROM `".MAIN_DB_PREFIX."rights_def` WHERE module like '%modgestion_grh%';";
		$resql = $this->db->query($sql2);

		// $sql = $this->dropTables();
		// $sql 	= array();
		$sql 	= $this->dropColmunsAndRows();
		return $this->_remove($sql, $options);
	}


	private function dropTables()
	{
		return array(
		);
	}

	private function dropColmunsAndRows()
	{
		return array(
			'DELETE FROM `'.MAIN_DB_PREFIX.'extrafields` WHERE `name` = "nx_cnss"',
			'DELETE FROM `'.MAIN_DB_PREFIX.'extrafields` WHERE `name` = "nx_num_holiday"',
			'DELETE FROM `'.MAIN_DB_PREFIX.'extrafields` WHERE `name` = "nx_cin"',
			'DELETE FROM `'.MAIN_DB_PREFIX.'extrafields` WHERE `name` = "nx_is_declared"',
			'DELETE FROM `'.MAIN_DB_PREFIX.'extrafields` WHERE `name` = "nx_salaire_base"',
			'DELETE FROM `'.MAIN_DB_PREFIX.'extrafields` WHERE `name` = "nx_is_stagiaire"',
			'DELETE FROM `'.MAIN_DB_PREFIX.'extrafields` WHERE `name` = "underline1"',
			'DELETE FROM `'.MAIN_DB_PREFIX.'extrafields` WHERE `name` = "underline2"',


			// 'DELETE FROM `".MAIN_DB_PREFIX."extrafields` WHERE `name` = "nx_sit_family"',
			// 'DELETE FROM `".MAIN_DB_PREFIX."extrafields` WHERE `name` = "nx_date_embauche"',
			// 'DELETE FROM `".MAIN_DB_PREFIX."extrafields` WHERE `name` = "nx_sit_assure"',
			// 'DELETE FROM `".MAIN_DB_PREFIX."extrafields` WHERE `name` = "nx_etablissement"',
			// 'DELETE FROM `".MAIN_DB_PREFIX."extrafields` WHERE `name` = "nx_etab_opt"',
			// 'DELETE FROM `".MAIN_DB_PREFIX."extrafields` WHERE `name` = "nx_nbr_enfants"',
		);
	}



}

