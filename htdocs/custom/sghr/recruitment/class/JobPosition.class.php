<?php 
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php'; 
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT .'/product/stock/class/mouvementstock.class.php';
 
dol_include_once('/sghr/recruitment/class/Department.class.php');

class JobPosition extends Commonobject{ 

	public $errors = array();
	public $rowid;
	public $label;
	public $status;
	public $lieu;
	public $email;
	public $Department;
	public $responsable_recrutement;
	public $nb_nouveauemploye;
	public $responsable_RH;
	public $description;
	public $date;

	public $element='JobPosition';
	public $table_element='JobPosition';
	
	public function __construct($db){ 
		$this->db = $db;
		return 1;
    }

	public function create($echo_sql=0,$insert)
	{

		$sql  = "INSERT INTO " . MAIN_DB_PREFIX .get_class($this)." ( ";

		foreach ($insert as $column => $value) {
			$alias = (is_numeric($value)) ? "" : "'";
			$sql_column .= " , `".$column."`";
			$sql_value .= " , ".$alias.$value.$alias;
		}

		$sql .= substr($sql_column, 2)." ) VALUES ( ".substr($sql_value, 2)." )";
		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error '.get_class($this).' '. $this->db->lasterror();
			print_r($this->errors);die();

			return 0;
		} 
		// return $this->db->db->insert_id;
		return $this->db->last_insert_id(MAIN_DB_PREFIX.'postes');
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
                $sql .= '`'. $key. '` = '. $val .',';
            }

        $sql  = substr($sql, 0, -1);
        $sql .= ' WHERE rowid = ' . $id;
        // die($sql);

        $resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error '.get_class($this).' : '. $this->db->lasterror();
			print_r($this->errors);die();
			
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

		$this->rows = array();
		$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new stdClass;
                $line->id    		 =  $obj->rowid;
				$line->rowid 		 =  $obj->rowid;
				$line->label 	     =  $obj->label;
				$line->status 		 =  $obj->status;
				$line->lieu 		 =  $obj->lieu;
				$line->email 		 =  $obj->email;
				$line->date 		 =  $obj->date;
				$line->Department 	 =  $obj->Department;
				$line->nb_nouveauemploye 	 =  $obj->nb_nouveauemploye;
				$line->responsable_RH 		 =  $obj->responsable_RH;
				$line->responsable_recrutement 	 =  $obj->responsable_recrutement;
				$line->description =  $obj->description;
				$line->entity =  $obj->entity;
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
				$obj 			  	  = $this->db->fetch_object($resql);
                $this->id         	  = $obj->rowid;
                $this->rowid      	  = $obj->rowid;
				$this->label 	  	  = $obj->label;
				$this->status 	  	  = $obj->status;
				$this->lieu 	  	  = $obj->lieu;
				$this->email   	      = $obj->email;
				$this->date   	      = $obj->date;
				$this->Department  		  = $obj->Department;
				$this->responsable_RH  		  = $obj->responsable_RH;
				$this->responsable_recrutement  	  = $obj->responsable_recrutement;
				$this->nb_nouveauemploye  = $obj->nb_nouveauemploye;
				$this->description 	  = $obj->description;
				$this->entity 	  = $obj->entity;
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
        $url = dol_buildpath('/sghr/recruitment/card.php?id='.$this->id,2);

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
        if(!empty($this->label)){
        	$ref=$this->label;
        }else
        	$ref=$this->rowid;
        if ($ref) {
            $linkstart = '<a href="'.$url.'"';
            $linkstart.=$linkclose.'>';
            $linkend='</a>';

            $result .= $linkstart;
            if ($withpicto) 
                $result.= '<img height="16" src="'.dol_buildpath('/sghr/recruitment/img/object_recrutement.png',2).'" >&nbsp;';
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
		$sql .= ' WHERE entity='.$conf->entity.' OR entity=0 OR entity IS NULL';

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
	    $moreforfilter.='<style> >#'.$name.'{ width: 100% !important;}</style>';
	    $moreforfilter.='<script>$(function(){$("#'.$name.'").select2();})</script>';
	    return $moreforfilter;
	}


	public function select_product($selected=0,$name='product')
	{
	    $id = (!empty($id)) ? $id : $name;

	    $select = '';
		// $select.='<select class="flat" id="'.$id.'" name="'.$name.'" >';
	    $select.='<option value="0">&nbsp;</option>';
		global $conf;
    	$sql = "SELECT rowid ,ref,entity,label FROM ".MAIN_DB_PREFIX."product WHERE fk_product_type = 0";
		//echo $sql."<br>";
    	$resql = $this->db->query($sql);
    	$select.='<option value="0"></option>'; 
		if ($resql) {
			$num = $this->db->num_rows($resql);
			while ($obj = $this->db->fetch_object($resql)) {
				$select.='<option value="'.$obj->rowid.'"';
	            if ($obj->rowid == $selected) $select.='selected';
	            $select.='>'.$obj->label.'</option>';
			}
			$this->db->free($resql);
		}

		// $select.='</select>';
		// $select.='<script>$(function(){$("#'.$id.'").select2()})</script>';
	    return $select;
	}


	
	public function select_postes($selected=0,$name='JobPosition')
	{
		global $conf;
		$id = (!empty($id)) ? $id : $name;


		$JobPosition = $this->fetchAll();

		$nb=count($this->rows);
		$select = '<select required class="flat" id="select_'.$id.'" name="'.$name.'" >';
	    	$select.='<option value="0">&nbsp;</option>';
			for ($i=0; $i < $nb; $i++) { 
				$item=$this->rows[$i];
				$select.='<option value="'.$item->rowid.'"';
	            if ($item->rowid == $selected) $select.='selected';
	            $select.='>'.$item->label.'</option>';
			}
    	
		$select.='</select>';
		$select.='<script>$(function(){$("#select_'.$id.'").select2()})</script>';
	    return $select;
	}
	
	public function select_departement($selected='',$name)
	{
		global $conf;
		$id = (!empty($id)) ? $id : $name;
		$Department = new departements($this->db);
		$Department->fetchAll();
		$nb=count($Department->rows);
		$select = '<select class="flat" id="select_'.$id.'" name="'.$name.'" >';
	    	$select.='<option value="0">&nbsp;</option>';
			for ($i=0; $i < $nb; $i++) { 
				$item=$Department->rows[$i];
				$select.='<option value="'.$item->rowid.'"';
	            if ($item->rowid == $selected) $select.='selected';
	            $select.='>'.$item->label.'</option>';
			}
    	
		$select.='</select>';
		$select.='<script>$(function(){$("#select_'.$id.'").select2()})</script>';
	    return $select;
	}
	
	public function recrutementpermissionto($source){
	    if(is_dir($source)) {
	    	@chmod($source, 0775);
	        $dir_handle=opendir($source);
	        while($file=readdir($dir_handle)){
	            if($file!="." && $file!=".."){
	                if(is_dir($source."/".$file)){
	                    @chmod($source."/".$file, 0775);
	                    $this->recrutementpermissionto($source."/".$file);
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



    public function upgradeModuleRecrut()
    {
        global $conf, $langs;
        
        dol_include_once('/sghr/recruitment/core/modules/modrecrutement.class.php');
        $modcore = new modrecrutement($this->db);
        
        $lastversion    = $modcore->version;
        $currentversion = dolibarr_get_const($this->db, 'RECRUT_LAST_VERSION_OF_MODULE', $conf->entity);
        
        if (!$currentversion || ($currentversion && $lastversion != $currentversion)){
            $res = $this->InitRecrut();
            if($res)
                dolibarr_set_const($this->db, 'RECRUT_LAST_VERSION_OF_MODULE', $lastversion, 'chaine', 0, '', $conf->entity);
            return 1;
        }

        return 0;
    }

    public function InitRecrut()
    {
        global $conf, $langs;

        $sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."departements` (
			  	`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			  	`label` varchar(255),
			  	`gestionnaire` int(11) NOT NULL,
	  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
			);";
		$resql = $this->db->query($sql);

		// ALTER TABLE  DEPARTEMENT
		
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."departements` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE  `".MAIN_DB_PREFIX."departements` MODIFY label varchar(355) NULL");


		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."JobPosition` (
			  	`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			  	`label` varchar(100) NULL,
			  	`status` varchar(100) NULL,
			  	`lieu` int(11) NULL,
			  	`email` varchar(100) NULL,
			  	`date` date NULL,
			  	`Department` int(11) NULL,
			  	`responsable_recrutement` int(11) NULL,
			  	`nb_nouveauemploye` int(11) NULL,
			  	`description` text NULL,
			   	`responsable_RH` int(11) NULL,
	  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
			);";
		$resql = $this->db->query($sql);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."JobPosition` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."Source` (
			  	`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			  	`source` varchar(255) NULL,
	  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
			);";
		$resql = $this->db->query($sql);

		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."Source` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."cv_recrutement` (
			  	`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			  	`nom` varchar(255) NULL,
			  	`fichier` varchar(255) NULL,
			  	`poste` int(11) NULL,
			  	`candidature` int(11) NULL,
			  	`type` varchar(20) NULL,
			  	`date` date NULL,
	  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
			);";
		$resql = $this->db->query($sql);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."cv_recrutement` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."candid_events` (
			  	`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			  	`event` varchar(255) NULL,
			  	`fk_candidat` int(11) NULL,
			  	`date` datetime NULL,
	  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
			);";
		$resql = $this->db->query($sql);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."candid_events` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."candidatures` (
		  	`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		    `sujet` varchar(355) NULL,
		    `nom` varchar(355) NULL,
		    `niveau` varchar(100) NULL,
		    `email` varchar(100) NULL,
		    `tel` varchar(100) NULL,
		    `mobile` varchar(100) NULL,
		    `Tag`  varchar(355) NULL,
		    `appreciation` int(11) NULL,
		    `apport_par` varchar(355) NULL,
		    `resume` text NULL,
		   	`poste` int(11)  NULL,
		  	`Department` int(11) NULL,
		   	`responsable` int(11) NULL,
		   	`contact` int(11) NULL,
		   	`origine` int(11) NULL,
		   	`salaire_demande` int(11) NULL,
		   	`salaire_propose` int(11) NULL,
		   	`date_disponible` date  NULL,
		   	`date_depot` date  NULL,
		   	`etape` int(11) NULL,
		   	`employe` int(2) NULL,
		   	`refuse` int(2) NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);
		
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."candidatures` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."candidatures` ADD prenom varchar(355) NULL");


		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."etapescandidature` (
		  	`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  	`label` varchar(255) NULL,
		  	`color` varchar(10) NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."etapescandidature` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		// $sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."etapescandidature` (
		// 	  	`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		// 	  	`label` varchar(255) NULL
		// 	);";
		// $resql = $this->db->query($sql);

		$sql = "INSERT INTO `".MAIN_DB_PREFIX."etapescandidature` (`rowid`, `label`, `color`) VALUES
			(1, 'Qualification_initiale','#DBE270'),
			(2, 'Premier_entretien','#F59A9A'),
			(3, 'Second_entretien','#62B0F7'),
			(4, 'Proposition_contrat','#FFB164'),
			(5, 'Contrat_signe','#59D859');";

		$resql = $this->db->query($sql);

		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."Tag` (
		  	`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  	`label` varchar(255) NULL,
		  	`color` varchar(255) NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."Tag` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."RecruitmentDegree` (
		  	`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  	`label` varchar(250) DEFAULT NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."RecruitmentDegree` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		$sql ="INSERT INTO `".MAIN_DB_PREFIX."RecruitmentDegree` (`rowid`, `label`) VALUES
		(1, 'B.Tech'),
		(2, 'BCA'),
		(3, 'M.Tech'),
		(4, 'MBA');
		";
		$resql = $this->db->query($sql);
		
		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."rect_majors` (
		  	`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  	`label` varchar(250) DEFAULT NULL,
		  	`fk_degree` int(11) DEFAULT NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."rect_majors` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."levels_candid` (
		  	`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  	`fk_candidat` int(11) DEFAULT NULL,
		  	`fk_major` int(11) DEFAULT NULL,
		  	`fk_degree` int(11) DEFAULT NULL,
		  	`year` int(11) DEFAULT NULL,
  			`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);
		
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."levels_candid` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
			

		$sql = "INSERT INTO `".MAIN_DB_PREFIX."rect_majors` (`rowid`, `fk_degree`, `label`) VALUES
		(1, 1, 'Finance'),
		(2, 1, 'Finance & Marketing'),
		(3, 1, 'Marketing')";
		$resql = $this->db->query($sql);

        return 1;
    }
	
} 


?>