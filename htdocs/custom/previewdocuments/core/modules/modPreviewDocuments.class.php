<?php

/* <one line to give the program's name and a brief idea of what it does.>

 * Copyright (C) 2018 NextConcept

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

 * 	\defgroup	previewdocuments	previewdocuments module

 * 	\brief		previewdocuments module descriptor.

 * 	\file		core/modules/modpreviewdocuments.class.php

 * 	\ingroup	previewdocuments

 * 	\brief		Description and activation file for module previewdocuments

 */

include_once DOL_DOCUMENT_ROOT . "/core/modules/DolibarrModules.class.php";



/**

 * Description and activation class for module previewdocuments

 */

class modPreviewDocuments extends DolibarrModules

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



        

        $this->numero = 688810801;

        $this->editor_name = 'NextGestion';
        $this->editor_url = 'https://www.nextgestion.com';

        $this->rights_class = 'previewdocuments';



        // Family can be 'crm','financial','hr','projects','products','ecm','technic','other'

        // It is used to group modules in module setup page

        $this->family = "NextGestion";

        // Module label (no space allowed)

        // used if translation string 'ModuleXXXName' not found

        // (where XXX is value of numeric property 'numero' of module)

        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // Module description

        // used if translation string 'ModuleXXXDesc' not found

        // (where XXX is value of numeric property 'numero' of module)

        $this->description = $langs->trans("Module688810801Desc");

        // Possible values for version are: 'development', 'experimental' or version

        $this->version = '8.2';

        // Key used in llx_const table to save module status enabled/disabled

        // (where previewdocuments is value of property name of module in uppercase)

        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

        // Where to store the module in setup page

        // (0=common,1=interface,2=others,3=very specific)

        $this->special = 2;

        // Name of image file used for this module.

        // If file is in theme/yourtheme/img directory under name object_pictovalue.png

        // use this->picto='pictovalue'

        // If file is in module/img directory under name object_pictovalue.png

        // use this->picto='pictovalue@module'

        $this->picto = 'previewdocuments@previewdocuments'; // mypicto@previewdocuments

        // Defined all module parts (triggers, login, substitutions, menus, css, etc...)

        // for default path (eg: /previewdocuments/core/xxxxx) (0=disable, 1=enable)

        // for specific path of parts (eg: /previewdocuments/core/modules/barcode)

        // for specific css file (eg: /previewdocuments/css/previewdocuments.css.php)

       

        // Data directories to create when module is enabled.

        // Example: this->dirs = array("/previewdocuments/temp");

        $this->dirs = array();



        // Config pages. Put here list of php pages

        // stored into previewdocuments/admin directory, used to setup module.

        $this->config_page_url = false;



        // Dependencies

        // List of modules id that must be enabled if this module is enabled

        $this->depends = array();

        // List of modules id to disable if this one is disabled

        $this->requiredby = array();

        // Minimum version of PHP required by module

        $this->phpmin = array(5, 0);

        // Minimum version of Dolibarr required by module

        $this->need_dolibarr_version = array(3, 3);

		$this->langfiles = array("previewdocuments@previewdocuments"); // langfiles@mymodule

        // Constants

        // List of particular constants to add when module is enabled

        // (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)

        // Example:

	$this->const=array();     





     	$this->module_parts = array(

                'js' => array('/previewdocuments/js/previewdocuments.js.php'),

				'css' => array('/previewdocuments/css/previewdocuments.css')

		);

       

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

        $file = DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

        $newfile = DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class-old.php';

        

        if (!file_exists($newfile)) {

            if (!copy($file, $newfile)) {

                echo "failed to copy html.formfile.class.php file";

            }

            @chmod($file, octdec(644));
            file_put_contents($file,str_replace(

                '\'&file=\'',

                '\'&full_path_nc=\'.$file[\'fullname\'].\'&full_name_nc=\'.$file[\'name\'].\'&data_nc=null&file=\'',

                file_get_contents($file)

            ));
            @chmod($file, octdec(644));
            file_put_contents($file,str_replace(

                '\'&amp;file=\'',

                '\'&full_path_nc=\'.$file[\'fullname\'].\'&full_name_nc=\'.$file[\'name\'].\'&data_nc=null&file=\'',

                file_get_contents($file)

            ));

        }



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

        $file = DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

        $newfile = DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class-old.php';

        @chmod($file, octdec(644));

        // if (file_exists($newfile)) {

            // rename(DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php', DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class-old2.php');

            // rename(DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class-old.php', DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php');

            // unlink(DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class-old2.php');

        // }

        $sql = array();

        return $this->_remove($sql, $options);

    }



    /**

     * Create tables, keys and data required by module

     * Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys

     * and create data commands must be stored in directory /previewdocuments/sql/

     * This function is called by this->init

     *

     * 	@return		int		<=0 if KO, >0 if OK

     */

    private function loadTables()

    {

        return $this->_load_tables('/previewdocuments/sql/');

    }

}

