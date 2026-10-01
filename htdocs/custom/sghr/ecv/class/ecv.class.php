<?php 
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php'; 

// dol_include_once('/sghr/ecv/class/ecv.class.php');
 
class ecv extends Commonobject{ 

	public $errors = array();
	public $rowid;
	public $module;
	public $datehire;
	public $poste;
	public $objectifs;
	public $useroradherent;

	public $element='ecv';
	public $table_element='ecv';
	
	public function __construct($db){
		$this->db = $db;
		return 1;
    }

	public function create($insert, $echo_sql = 0)
	{

		$sql  = "INSERT INTO " . MAIN_DB_PREFIX .get_class($this)." ( ";

		foreach ($insert as $column => $value) {
			$alias = (is_numeric($value)) ? "" : "'";
			if($value != ""){
				$sql_column .= " , `".$column."`";
				$sql_value .= " , ".$alias.$value.$alias;
			}
		}

		$sql .= substr($sql_column, 2)." ) VALUES ( ".substr($sql_value, 2)." )";
    	// print_r($sql);die();
		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error '.get_class($this).' '. $this->db->lasterror();
			print_r($this->errors);die();
			return 0;
		} 
		// return $this->db->db->insert_id;
		return $this->db->last_insert_id(MAIN_DB_PREFIX.'ecv');
	}

	public function update($id, array $data,$echo_sql=0)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		if (!$id || $id <= 0)
			return false;

        $sql = 'UPDATE ' . MAIN_DB_PREFIX .get_class($this). ' SET ';

        if (count($data) && is_array($data))
            foreach ($data as $key => $val) {
                $val = is_numeric($val) ? $val : '"'. $val .'"';
                $val = ($val == '') ? 'NULL' : $val;
                $sql .= '`'. $key. '` = '. $val .',';
            }

        $sql  = substr($sql, 0, -1);
        $sql .= ' WHERE rowid = ' . $id;
        // die($sql);

        $resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error '.get_class($this).' : '. $this->db->lasterror();
			return -1;
		} 
		return 1;
	}

	public function delete($echo_sql=0)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql 	= 'DELETE FROM ' . MAIN_DB_PREFIX .get_class($this).' WHERE rowid = ' . $this->rowid;
		$resql 	= $this->db->query($sql);
		
		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error '.get_class($this).' : '.$this->db->lasterror();
			return -1;
		} 

		return 1;
	}

    
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = '', $filtermode = 'AND')
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = "SELECT * FROM ";
		$sql .= MAIN_DB_PREFIX .get_class($this);
        $sql .= ' WHERE entity='.$conf->entity;

		if (!empty($filter)) {
			$sql .= " ".$filter;
		}
		
		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}

		if (!empty($limit)) {
			if($offset==1)
				$sql .= " limit ".$limit;
			else
				$sql .= " limit ".$offset.",".$limit;				
		}

		// die($sql);
		$this->rows = array();
		$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new stdClass;
                $line->id    = $obj->rowid;
				$line->rowid 	= $obj->rowid;
				$line->ref 	= $obj->ref;
				$line->fk_user 	=  $obj->fk_user;
				$line->module 	    =  $obj->module;
				$line->datehire 	=  $obj->datehire;
				$line->poste 	=  $obj->poste;
				$line->objectifs 	=  $obj->objectifs;
				$line->entity 	=  $obj->entity;
				$line->useroradherent 	=  $obj->useroradherent;
                // ....

				$this->rows[] 	= $line;
			}
			$this->db->free($resql);

			return $num;
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		}
	}


	public function fetch($id)
	{
	    global $conf;

		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX .get_class($this). ' WHERE rowid = ' . $id;
        $sql .= ' AND entity='.$conf->entity;
		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			
			if ($numrows) {
				$obj 			  = $this->db->fetch_object($resql);
                $this->id         = $obj->rowid;
                $this->rowid      = $obj->rowid;
                $this->ref        = $obj->ref;
				$this->fk_user 	  = $obj->fk_user;
				$this->module 	  = $obj->module;
				$this->datehire 	  = $obj->datehire;
				$this->poste 	  = $obj->poste;
				$this->objectifs  = $obj->objectifs;
				$this->entity     = $obj->entity;
				$this->useroradherent  = $obj->useroradherent;
                // ....
			}

			$this->db->free($resql);

			if ($numrows) {
				return 1 ;
			} else {
				return 0;
			}
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			return -1;
		}
	}

	public function select_with_filter($selected=0,$name='select_',$showempty=1,$val="rowid",$opt="label",$id='',$attr=''){

	    global $conf;

	    $moreforfilter = '';
	    $nodatarole = '';
	    $id = (!empty($id)) ? $id : $name;

	    $moreforfilter.='<select width="100%" '.$attr.' class="flat" id="select_'.$id.'" name="'.$name.'">';
	    if ($showempty) $moreforfilter.='<option value="0">&nbsp;</option>';

    	$sql = "SELECT ".$val.",".$opt." FROM ".MAIN_DB_PREFIX.get_class($this);
        $sql .= ' WHERE entity='.$conf->entity;

		//echo $sql."<br>";
    	$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$moreforfilter.='<option value="'.$obj->$val.'"';
	            if ($obj->$val == $selected) $moreforfilter.=' selected';
	            $moreforfilter.='>'.$obj->$opt.'</option>';
			}
			$this->db->free($resql);
		}

	    $moreforfilter.='</select>';
	    $moreforfilter.='<style>#s2id_select_'.$name.'{ width: 100% !important;}</style>';
	    return $moreforfilter;
	}

    function getNomUrl($withpicto=0, $option='', $get_params='', $notooltip=0, $save_lastsearch_value=-1)
    {
        global $langs, $conf, $user;

        if (! empty($conf->dol_no_mouse_hover)) $notooltip=1;   // Force disable tooltips

        $result='';
        $label='';
        $url='';

        // if ($user->rights->propal->lire){}

        $linkclose='';
        if (empty($notooltip))
        {
            $linkclose.= ' title="'.dol_escape_htmltag($label, 1).'"';
            $linkclose.=' class="classfortooltip"';
        }
        $linkstart = "";
        $linkend = "";
        $result = "";
        if(!empty($this->ref)){
        	$ref=$this->ref;
        }else
        	$ref=$this->rowid;
        if ($ref) {
            $linkstart = '<a href="'.$url.'"';
            $linkstart.=$linkclose.'>';
            $linkend='</a>';

            $result .= $linkstart;
            if ($withpicto) 
                $result.= '<img height="16" src="'.dol_buildpath('/sghr/ecv/img/object_ecv.png',1).'" >&nbsp;';
            if ($withpicto != 2) $result.= $ref;
        }

        $result .= $linkend;

        return $result;
    }








    public function getcountrows(){
    	global $conf;
        $tot = 0;
        $sql = "SELECT COUNT(rowid) as tot FROM ".MAIN_DB_PREFIX.get_class($this);
        $sql .= ' WHERE entity='.$conf->entity;

        $resql = $this->db->query($sql);

        if($resql){
            while ($obj = $this->db->fetch_object($resql)) 
            {
                $tot = $obj->tot;
            }
        }
        return $tot;
    }

    public function getdateformat($date,$time=true){
        
        $d = explode(' ', $date);
        $date = explode('-', $d[0]);
        $d2 = explode(':', $d[1]);
        $result = $date[2]."/".$date[1]."/".$date[0];
        if ($time) {
            $result .= " ".$d2[0].":".$d2[1];
        }
        return $result;
    }

    public function getYears($debut="debut")
    {
    	global $conf;
        $sql = 'SELECT YEAR('.$debut.') as years FROM ' . MAIN_DB_PREFIX.get_class($this);
        $sql .= ' WHERE entity='.$conf->entity;

        $resql = $this->db->query($sql);
        $years = array();
        if ($resql) {
            $num = $this->db->num_rows($resql);
            while ($obj = $this->db->fetch_object($resql)) {
                $years[$obj->years] = $obj->years;
            }
            $this->db->free($resql);
        }

        return $years;
    }


    public function getmonth($year)
    {
    	global $conf;
        $sql = 'SELECT MONTH(debut) as years FROM ' . MAIN_DB_PREFIX.get_class($this).' WHERE YEAR(debut) = '.$year;
        $sql .= ' AND entity='.$conf->entity;
        $resql  = $this->db->query($sql);
        $years = array();
        if ($resql) {
            $num = $this->db->num_rows($resql);
            while ($obj = $this->db->fetch_object($resql)) {
                $years[$obj->years] = $obj->years;
            }
            $this->db->free($resql);
        }

        return $years;
    }


	public function select_user($selected=0,$name='select_',$showempty=1,$val="rowid",$opt="label",$id=''){
	    global $conf;
	    $moreforfilter = '';
	    $nodatarole = '';
	    $id = (!empty($id)) ? $id : $name;
	    
	    $objet = "label";
	    $moreforfilter.='<select class="flat" id="'.$id.'" name="'.$name.'" '.$nodatarole.'>';
	    if ($showempty) $moreforfilter.='<option value="0">&nbsp;</option>';

    	$sql= "SELECT * FROM ".MAIN_DB_PREFIX."user";
    	$sql .= ' WHERE entity = '.$conf->entity.' OR entity=0 OR entity IS NULL';

    	$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			
			while ($obj = $this->db->fetch_object($resql)) {
				$moreforfilter.='<option value="'.$obj->$val.'" data-ref="'.$obj->$opt.'"';
	            if ($obj->$val == $selected) $moreforfilter.=' selected';
	            $moreforfilter.='>'.$obj->lastname.' '.$obj->firstname.'</option>';
			}
			$this->db->free($resql);
		}

	    $moreforfilter.='</select>';
	    $moreforfilter.='<style>#s2id_select_'.$name.'{ width: 100% !important;}</style>';
	    return $moreforfilter;
	}



	public function select_adherent($selected=0,$name='select_',$showempty=1,$val="rowid",$opt="label",$id=''){
	    global $conf;
	    $moreforfilter = '';
	    $nodatarole = '';
	    $id = (!empty($id)) ? $id : $name;
	    
	    $objet = "label";
	    $moreforfilter.='<select class="flat" id="'.$id.'" name="'.$name.'" '.$nodatarole.'>';
	    if ($showempty) $moreforfilter.='<option value="0">&nbsp;</option>';

    	$sql= "SELECT * FROM ".MAIN_DB_PREFIX."adherent";
    	$sql .= ' WHERE entity='.$conf->entity;
    	$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			
			while ($obj = $this->db->fetch_object($resql)) {
				$moreforfilter.='<option value="'.$obj->$val.'"';
	            if ($obj->$val == $selected) $moreforfilter.=' selected';
	            $moreforfilter.='>'.$obj->lastname.' '.$obj->firstname.'</option>';
			}
			$this->db->free($resql);
		}
	    $moreforfilter.='</select>';
	    $moreforfilter.='<style>#s2id_select_'.$name.'{ width: 100% !important;}</style>';
	    return $moreforfilter;
	}

	public function select_year_cv($year=0,$id='annee'){
		$years='<select class="select2" id="'.$id.'" name="'.$id.'" >';
		$years.='<option value="0" ></option>';
		for($i = 1990; $i <= 2040; $i++){
			$years.='<option value="'.$i.'" >'.$i.'</option>';
		}
		$years=str_replace('value="'.$year.'"','value="'.$year.'" selected',$years);
		$years.='</select>';
	    $years.='<script>$(document).ready(function(){$("#'.$id.'").select2()})</script>';

		return $years;
	}

	public function format_year($date){
        $d = explode(' ', $date);
        $date = explode('-', $d[0]);
        
        return $date[0];
    }


    public function ecvpermissionto($source){
	    if(is_dir($source)) {
	    	@chmod($source, 0775);
	        $dir_handle=opendir($source);
	        while($file=readdir($dir_handle)){
	            if($file!="." && $file!=".."){
	                if(is_dir($source."/".$file)){
	                    @chmod($source."/".$file, 0775);
	                    $this->ecvpermissionto($source."/".$file);
	                } else {
	                    @chmod($source."/".$file, 0664);
	                }
	            }
	        }
	        closedir($dir_handle);
	    } else {
	        @chmod($source, 0664);
	    }
	}

	
	public function upgradeModuleEcv()
    {
        global $conf, $langs;

        dol_include_once('/sghr/ecv/core/modules/modecv.class.php');
        $modcore = new modecv($this->db);
        
        $lastversion    = $modcore->version;
        $currentversion = dolibarr_get_const($this->db, 'ECV_LAST_VERSION_OF_MODULE', $conf->entity);
        if (!$currentversion || ($currentversion && $lastversion != $currentversion)){
            $res = $this->InitEcv();
            if($res)
                dolibarr_set_const($this->db, 'ECV_LAST_VERSION_OF_MODULE', $lastversion, 'chaine', 0, '', $conf->entity);
            return 1;
        }
        return 0;
    }

	public function InitEcv()
	{
		global $conf;


		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."ecv` (
			`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`ref` varchar(355) NULL,
			`date` datetime NULL,
			`module` varchar(355) NOT NULL,
			`objectifs` text NULL,
			`poste` varchar(355) NULL,
			`fk_user` int(11) NOT NULL,
	  		`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);

			$sql = 'ALTER TABLE `'.MAIN_DB_PREFIX.'ecv`
				MODIFY `ref` varchar(355) NULL,
				MODIFY `date` datetime NULL,
				MODIFY `objectifs` text NULL,
				MODIFY `module` varchar(355) NULL,
				MODIFY `poste` varchar(355) NULL,
				MODIFY `fk_user` int(11)  NULL';
			$resql = $this->db->query($sql);

			$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."ecv` ADD `useroradherent` VARCHAR(10) NULL DEFAULT 'USER';");
			$resql = $this->db->query('ALTER TABLE `'.MAIN_DB_PREFIX.'ecv` CHANGE `date` `datehire` DATETIME NULL DEFAULT NULL;');
        	$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."ecv` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		// ALTER TABLE ged_categories 

		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."ecvexperiences` (
				`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`societe` varchar(355) NULL,
				`debut` date NULL,
				`fin` date NULL,
				`projets` varchar(355) NULL,
				`profile_soc` varchar(355) NULL,
				`description` text NULL,
				`nosjours` int(1) NULL DEFAULT '0',
				`fk_ecv` int(11) NOT NULL,
				`fk_user` int(11) NOT NULL,
	  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
				);";
		$resql = $this->db->query($sql);

		// ALTER table ecvexperiences
			$sql = "ALTER TABLE `".MAIN_DB_PREFIX."ecvexperiences`
			 	MODIFY `societe` varchar(355) NULL,
				MODIFY `debut` date NULL,
				MODIFY `fin` date NULL,
				MODIFY `projets`varchar(355) NULL,
				MODIFY `profile_soc` varchar(355) NULL,
				MODIFY `fk_user` int(11) NULL,
				MODIFY `fk_ecv` int(11) NULL,
				MODIFY `description` text NULL";
			$resql = $this->db->query($sql);
    		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."ecvexperiences` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);


		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."ecvformations` (
			`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`etablissement` text NULL,
			`niveau` varchar(355) NULL,
			`intitule` text NULL,
			`filiere` varchar(355) NULL,
			`debut` date NULL,
			`fin` date NULL,
			`nosjours` int(1) NULL DEFAULT '0',
			`fk_ecv` int(11) NOT NULL,
			`fk_user` int(11) NOT NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);


		// ALTER table ecvformations
			$sql = "ALTER TABLE `".MAIN_DB_PREFIX."ecvformations`
			 	MODIFY `etablissement` text NULL,
				MODIFY `niveau` varchar(355) NULL,
				MODIFY `intitule` text NULL,
				MODIFY `filiere` varchar(355) NULL,
				MODIFY `debut` date NULL,
				MODIFY `fin` date NULL,
				MODIFY `nosjours` int(1) NULL DEFAULT '0',
				MODIFY `fk_ecv` int(11)  NULL,
				MODIFY `fk_user` int(11)  NULL";
			$resql = $this->db->query($sql);
    		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."ecvformations` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."ecvcertificats` (
			`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`intitule` text NULL,
			`etablissement` text NULL,
			`debut` date NULL,
			`fin` date NULL,
			`description` text NULL,
			`copie` varchar(355) NULL,
			`fk_ecv` int(11) NOT NULL,
			`fk_user` int(11) NOT NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);

		// ALTER table ecvcertificats
			$sql = "ALTER TABLE `".MAIN_DB_PREFIX."ecvcertificats`
				MODIFY `intitule` text NULL,
				MODIFY `etablissement` text NULL,
				MODIFY `debut` date NULL,
				MODIFY `fin` date NULL,
				MODIFY `description` text NULL,
				MODIFY `fk_user` int(11) NULL,
				MODIFY `fk_ecv` int(11) NULL,
				MODIFY `copie` varchar(355) NULL";
			$resql = $this->db->query($sql);
    		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."ecvcertificats` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);


		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."ecvcompetances` (
			`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`value` varchar(255) NULL,
			`fk_ecv` int(11) NOT NULL,
			`fk_user` int(11) NOT NULL,
			`fk_competance` int(11) NOT NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);

		// ALTER table ecvcompetances
			$sql = "ALTER TABLE `".MAIN_DB_PREFIX."ecvcompetances`
				MODIFY `value` varchar(255) NULL,
				MODIFY `fk_user` int(11) NULL,
				MODIFY `fk_ecv` int(11) NULL,
				MODIFY `fk_competance` varchar(255) NULL";
			$resql = $this->db->query($sql);
    		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."ecvcompetances` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		
		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."competances` (
			`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`name`  varchar(355) NULL,
			`icon`  varchar(355) NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);

		// ALTER table competances
			$sql = "ALTER TABLE `".MAIN_DB_PREFIX."competances`
				MODIFY `name` varchar(255) NULL,
				MODIFY `icon` varchar(255) NULL";
			$resql = $this->db->query($sql);
    		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."competances` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);


		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."ecvlangues` (
			`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`name`  varchar(355) NULL,
			`value` varchar(355) NULL,
			`fk_ecv` int(11) NOT NULL,
			`fk_user` int(11) NOT NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);

		// ALTER table ecvlangues
			$sql = "ALTER TABLE `".MAIN_DB_PREFIX."ecvlangues`
				MODIFY `name` varchar(255) NULL,
				MODIFY `fk_user` int(11) NULL,
				MODIFY `fk_ecv` int(11) NULL,
				MODIFY `value` varchar(255) NULL";
			$resql = $this->db->query($sql);
    		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."ecvlangues` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);


		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."ecvqualifications` (
			`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`name`  varchar(355) NULL,
			`fk_ecv` int(11) NOT NULL,
			`fk_user` int(11) NOT NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);

		// ALTER table ecvqualifications
			$sql = "ALTER TABLE `".MAIN_DB_PREFIX."ecvqualifications`
				MODIFY `fk_user` int(11) NULL,
				MODIFY `fk_ecv` int(11) NULL,
				MODIFY `name` varchar(255) NULL";
			$resql = $this->db->query($sql);
    		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."ecvqualifications` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);


		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."ecvpermis` (
			`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`exist` varchar(5) DEFAULT 'no',
			`year` YEAR DEFAULT NULL,
			`type` varchar(355) DEFAULT NULL,
			`fk_ecv` int(11) NOT NULL,
			`fk_user` int(11) NOT NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);

		// ALTER table ecvpermis
		$sql = "ALTER TABLE `".MAIN_DB_PREFIX."ecvpermis`
			MODIFY `fk_user` int(11) NULL,
			MODIFY `fk_ecv` int(11) NULL";
		$resql = $this->db->query($sql);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."ecvpermis` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		return 1;
	}

} 


?>