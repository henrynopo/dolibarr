<?php
/* Change Tiers - Change Third Party since propal, invoice or order card
 * Copyright (C) 2018       Inovea-conseil.com     <info@inovea-conseil.com>
 */

include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module MyModule
 */
class modSwitchThirdParty extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
    public function __construct($db) {
        global $langs,$conf;

        $this->db = $db;
        
        $this->numero = 432446;

        $this->family = "Inovea Conseil";

        $this->special = 0;

        $this->module_position = 500;

        $this->name = "changetiers";

        // Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
        $this->description = "Module432446Desc";
        $this->editor_name = 'Inovea Conseil';
        $this->editor_url = 'https://www.inovea-conseil.com';

        $this->version = '2.1.6';

        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        
        $this->picto='inoveaconseil@switchthirdparty';

        $this->module_parts = array(
            'js' => array(/*'/switchthirdparty/js/conf_changetiers.js',*/ '/switchthirdparty/js/changetiers.js'),
            'hooks' => array(
                'propalcard',
                'invoicecard',
                'ordercard',
                'ordersuppliercard',
                'supplier_proposalcard',
                'invoicesuppliercard',
                'expeditioncard',
                'contractcard',
                'receptioncard'
            ),
            //'triggers' => 1,
        );

        $this->dirs = array();

        // Config pages. Put here list of php page, stored into dolitest/admin directory, to use to setup module.
        $this->config_page_url = array("admin.php@changetiers");

        // Dependencies
        $this->hidden = false;
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->phpmin = array(5,0);
        $this->need_dolibarr_version = array(5,0);
        $this->langfiles = array("switchthirdparty@switchthirdparty");
        // $this->warnings_activation = array('FR'=>'WarningNoteModuleInoveaConseilForFrenchLaw'); 

        // Constants
        $this->const = array ();
        $r = 0;

        $r++;
        $this->const [$r] [0] = "COMMANDE_CHANGE_THIRDPARTY";
        $this->const [$r] [1] = "yesno";
        $this->const [$r] [2] = 1;
        $this->const [$r] [3] = 'Possibilité de changer le tiers sur une commande';
        $this->const [$r] [4] = 0;
        $this->const [$r] [5] = 'current';
        $this->const [$r] [6] = 0;

        $r++;
        $this->const [$r] [0] = "FACTURE_CHANGE_THIRDPARTY";
        $this->const [$r] [1] = "yesno";
        $this->const [$r] [2] = 1;
        $this->const [$r] [3] = 'Possibilité de changer le tiers sur une facture brouillon';
        $this->const [$r] [4] = 0;
        $this->const [$r] [5] = 'current';
        $this->const [$r] [6] = 0;

        $r++;
        $this->const [$r] [0] = "PROPAL_CHANGE_THIRDPARTY";
        $this->const [$r] [1] = "yesno";
        $this->const [$r] [2] = 1;
        $this->const [$r] [3] = 'Possibilité de changer le tiers sur une proposition commerciale';
        $this->const [$r] [4] = 0;
        $this->const [$r] [5] = 'current';
        $this->const [$r] [6] = 0;



        $this->tabs = array();

        if (! isset($conf->changetiers) || ! isset($conf->changetiers->enabled)) {
                $conf->changetiers=new stdClass();
                $conf->changetiers->enabled=0;
        }
        
        // Dictionaries
        $this->dictionaries=array();
        $this->boxes = array();	

        // Cronjobs
        $this->cronjobs = array();

        // Permissions
        $this->rights_class = 'switchthirdparty';
        $this->rights = array();        // Permission array used by this module
        $r = 0;

        $this->rights[$r][0] = 4324461;
        $this->rights[$r][1] = $langs->trans('ChangeTiersRightLabelProposal');
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'proposal';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 4324462;
        $this->rights[$r][1] = $langs->trans('ChangeTiersRightLabelOrder');
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'order';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 4324463;
        $this->rights[$r][1] = $langs->trans('ChangeTiersRightLabelShipping');
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'expedition';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 4324464;
        $this->rights[$r][1] = $langs->trans('ChangeTiersRightLabelContract');
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'contract';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 4324465;
        $this->rights[$r][1] = $langs->trans('ChangeTiersRightLabelInvoice');
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'invoice';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 4324466;
        $this->rights[$r][1] = $langs->trans('ChangeTiersRightLabelSupplierProposal');
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'supplier_proposal';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 4324467;
        $this->rights[$r][1] = $langs->trans('ChangeTiersRightLabelSupplierOrder');
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'supplier_order';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 4324468;
        $this->rights[$r][1] = $langs->trans('ChangeTiersRightLabelSupplierReception');
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'reception';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 4324469;
        $this->rights[$r][1] = $langs->trans('ChangeTiersRightLabelSupplierInvoice');
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'supplier_invoice';
        $this->rights[$r][5] = '';
        $r++;
        $this->rights[$r][0] = 4324470; // Permission id (must not be already used)
        $this->rights[$r][1] = $langs->trans('ChangeTiersRightEvent');; // Permission label
        $this->rights[$r][4] = 'event';
        $this->rights[$r][5] = 'write'; // In php code, permission will be checked by test if ($user->rights->rocketschool->level1->level2)
        $r++;

        $this->menu = array();
        $r=0;
        $r=1;
    }

    /**
     * Init function
     *
     * @param      string	$options    Options when enabling module ('', 'noboxes')
     * @return     int             	1 if OK, 0 if KO
     */
    public function init($options='')
    {
        $sql = array();
        
        //écriture du fichier de config js
        //$file = __DIR__.'/../../js/conf_changetiers.js';
        //file_put_contents($file, '');
        if(DOL_VERSION < 18){
            dolibarr_set_const($this->db, "CHECKLASTVERSION_EXTERNALMODULE", '1', 'int', 0, '', $conf->entity);
        }
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
        $sql = array();

        return $this->_remove($sql, $options);
    }

}

