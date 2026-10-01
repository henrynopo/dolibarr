<?php 
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php'; 

dol_include_once('/sghr/recruitment/class/Tag.class.php');
dol_include_once('/sghr/recruitment/class/Source.class.php');
dol_include_once('/sghr/recruitment/class/CandidateStage.class.php');
 
class Candidate extends Commonobject{ 

	public $errors = array();
	public $rowid;
	public $sujet;
	public $nom;
	public $prenom;
	public $Tag;
	public $niveau;
	public $email;
	public $tel;
	public $mobile;
	public $appreciation;
	public $origine;
	public $apport_par;
	public $resume;
	public $Department;
	public $responsable;
	public $contact;
	public $poste;
	public $salaire_demande;
	public $salaire_propose;
	public $date_disponible;
	public $date_depot;
	public $etape;
	public $employe;
	public $refuse;

	public $element = 'Candidate';
	public $table_element = 'candidatures';
	
	public function __construct($db){ 
		$this->db = $db;
		return 1;
    }

	public function create($echo_sql=0,$insert)
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
		$resql = $this->db->query($sql);
    	// print_r($sql);die();
    	// print_r($this->db->lasterror());die();

		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error '.get_class($this).' '. $this->db->lasterror();
			// print_r($this->errors);die();

			return 0;
		} 
		// return $this->db->db->insert_id;
		return $this->db->last_insert_id(MAIN_DB_PREFIX.$table_element);
	}

	public function update($id, array $data,$echo_sql=0)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		if (!$id || $id <= 0)
			return false;

        $sql = "UPDATE " . MAIN_DB_PREFIX .get_class($this). " SET ";

        if (count($data) && is_array($data))
            foreach ($data as $key => $val) {
                $val = is_numeric($val) ? $val : "'". $val ."'";
                $val = ($val == '') ? 'NULL' : $val;
                $sql .= "`". $key. "` = ". $val .",";
            }

        $sql  = substr($sql, 0, -1);
        $sql .= " WHERE rowid = " . $id;
        // die($sql);
        $resql = $this->db->query($sql);
        // die($this->db->lasterror());

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
		// echo $sql."<br>";
		$this->rows = array();
		$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new stdClass;
                $line->id    		 	 =  $obj->rowid;
				$line->rowid 		 	 =  $obj->rowid;
				$line->sujet 		 	 =  $obj->sujet;
				$line->nom 			 	 =  $obj->nom;
				$line->prenom 			 =  $obj->prenom;
				$line->Tag 		 =  $obj->Tag;
				$line->contact 	     	 =  $obj->contact;
				$line->email 	     	 =  $obj->email;
				$line->tel 	     	     =  $obj->tel;
				$line->mobile 	     	 =  $obj->mobile;
				$line->niveau 		 	 =  $obj->niveau;
				$line->appreciation  	 =  $obj->appreciation;
				$line->origine 	     	 =  $obj->origine;
				$line->apport_par 	 	 =  $obj->apport_par;
				$line->resume 	     	 =  $obj->resume;
				$line->poste         	 =  $obj->poste;
				$line->Department 	 	 =  $obj->Department;
				$line->responsable 	 	 =  $obj->responsable;
				$line->salaire_propose 	 =  $obj->salire_propose;
				$line->salaire_demande 	 =  $obj->salaire_demande;
				$line->date_disponible 	 =  $obj->date_disponible;
				$line->date_depot 	     =  $obj->date_depot;
				$line->etape 	 		 =  $obj->etape;
				$line->employe 	 		 =  $obj->employe;
				$line->refuse 	 		 =  $obj->refuse;
				$line->entity 	 		 =  $obj->entity;
                // ....

				$this->rows[] 	= $line;
			}
			// print_r($this->rows);die();
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
				$obj 			  	  	 = $this->db->fetch_object($resql);
                $this->id         	  	 = $obj->rowid;
                $this->rowid      	  	 = $obj->rowid;
				$this->sujet 	  	  	 = $obj->sujet;
                $this->nom        	  	 = $obj->nom;
                $this->prenom        	 = $obj->prenom;
                $this->Tag        = $obj->Tag;
				$this->niveau 	  	  	 = $obj->niveau;
				$this->email   	  	     = $obj->email;
				$this->tel   	  	     = $obj->tel;
				$this->mobile   	  	 = $obj->mobile;
				$this->appreciation   	 = $obj->appreciation;
				$this->origine   	     = $obj->origine;
				$this->apport_par   	 = $obj->apport_par;
				$this->resume   	  	 = $obj->resume;
				$this->poste  	      	 = $obj->poste;
				$this->Department    	 = $obj->Department;
				$this->responsable    	 = $obj->responsable;
				$this->contact 	      	 = $obj->contact;
				$this->salaire_demande 	 = $obj->salaire_demande;
				$this->salaire_propose 	 = $obj->salaire_propose;
				$this->date_disponible 	 = $obj->date_disponible;
				$this->date_depot   	 = $obj->date_depot;
				$this->etape 	 		 = $obj->etape;
				$this->employe 	         = $obj->employe;
				$this->refuse 	         = $obj->refuse;
				$this->entity 	         = $obj->entity;
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

	public function select_with_filter($selected=0,$name='select_',$showempty=1,$val="rowid",$opt="nom, prenom",$id='',$attr=''){

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
	            $moreforfilter.='>'.$obj->nom.' '.$obj->prenom.'</option>';
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
        $url=dol_buildpath('/sghr/recruitment/Candidate/card.php?id='.$this->id,2);

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
        $res = '';
        if($this->nom) $res .= $this->nom;
        if($this->prenom) $res .= ' '.$this->prenom;
        if(!empty($res)){
        	$ref=$res;
        }else
        	$ref=$this->rowid;
        if ($ref) {
            $linkstart = '<a href="'.$url.'"';
            $linkstart.=$linkclose.'>';
            $linkend='</a>';

            $result .= $linkstart;
            if ($withpicto) 
                $result.= '<img height="16" src="'.DOL_URL_ROOT.'/JobPosition/img/object_postes.png" >&nbsp;';
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


	public function select_product($selected=0,$name='product')
	{
		global $conf;

	    $id = (!empty($id)) ? $id : $name;

	    $select = '';
		// $select.='<select class="flat" id="'.$id.'" name="'.$name.'" >';
	    $select.='<option value="0">&nbsp;</option>';
    	$sql = "SELECT rowid ,ref,entity,label FROM ".MAIN_DB_PREFIX."product WHERE fk_product_type = 0";
    	$sql .= ' WHERE entity = '.$conf->entity;

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


	public function modifier_stock($prod,$qte,$id_entrepot,$movement)
	{
		global $user;
		$msg='';
        $mouvementstock = new MouvementStock($this->db);
        $product = new Product($this->db);
        $product->fetch($prod);
        $q = $movement.trim($qte);
        $type=0;
        if($movement=="+"){
        	$type=1;
        }
        
        if($id_entrepot){
            $t=$mouvementstock->_create($user,$prod,$id_entrepot,$q,$type,0,'','');
        }
        else{
            $msg.='La quantité demandée de '.$product->label.' n\'est pas disponible <br>';
        }
        return $msg;
	}

	public function select_postes($selected=0,$name='JobPosition')
	{
		global $conf;
		$id = (!empty($id)) ? $id : $name;

		$JobPosition = $this->fetchAll();
		$nb=count($this->rows);
		$select = '<select class="flat" id="select_'.$id.'" name="'.$name.'" >';
	    	$select.='<option value="0">&nbsp;</option>';
			for ($i=0; $i < $nb; $i++) { 
				$item=$this->rows[$i];
				$select.='<option value="'.$item->rowid.'"';
	            if ($item->rowid == $selected) $select.='selected';
	            $select.='>'.$item->ref.'</option>';
			}
    	
		$select.='</select>';
		$select.='<script>$(function(){$("#select_'.$id.'").select2()})</script>';
	    return $select;
	}
	public function select_etiquette($value='',$name='etiquette')
	{
		global $conf;
		$id = (!empty($id)) ? $id : $name;
		$etiquette = new Tag($this->db);
		$etiquette->fetchAll();
		$nb=count($etiquette->rows);
		if($value)
			$arr=explode(",", $value);
		$select = '<select class="flat" id="select_etiquette" name="'.$name.'" multiple >';
		// $select = '<select class="flat" id="select_etiquette" name="'.$name.'" multiple onchange="get_etiquette(this)" >';
	    	$select.='<option value="0">&nbsp;</option>';
			for ($i=0; $i < $nb; $i++) { 
				$item=$etiquette->rows[$i];
				$select.='<option value="'.$item->rowid.'"';
				if($value){
					// $arr=json_decode($value);
	            	if (count($arr)>0 && in_array($item->rowid, $arr)) $select.='selected';
				}
	            $select.=' data-color="'.$item->color.'"  data-name="'.$item->label.'">'.$item->label.'</option>';
			}
		$select.='</select>';
		$select.='<script>$(function(){$("#select_etiquette").select2()})</script>';
	    return $select;
	}

	public function select_contact($value='',$name='contact')
	{
		global $conf;
		$id = (!empty($id)) ? $id : $name;
		$select = '<select required class="flat" id="select_'.$id.'" name="'.$name.'" >';
	    	$select.='<option value="0">&nbsp;</option>';
		$sql ='SELECT * FROM '.MAIN_DB_PREFIX.'socpeople';
    	$sql .= ' WHERE entity = '.$conf->entity;

		$resql=$this->db->query($sql);
		if ($resql)
		{
			if ($this->db->num_rows($resql))
			{
				while ( $obj = $this->db->fetch_object($resql)) {
					$select.='<option value="'.$obj->rowid.'"';
		            if ($obj->rowid == $value) $select.='selected';
		            $select.=' data-id="'.$obj->rowid.'">'.$obj->firstname.' '.$obj->lastname.'</option>';
				};
			}
		}
		$select.='</select>';
		$select.='<script>$(function(){$("#select_'.$id.'").select2()})</script>';

	    return $select;
	}

	public function select_origine($value='',$name='origine')
	{
		global $conf;
		$id = (!empty($id)) ? $id : $name;
		$origine = new Source($this->db);
		$origine->fetchAll();
		$nb=count($origine->rows);
		$select = '<select class="flat" id="select_'.$id.'" name="'.$name.'"  >';
	    	$select.='<option value="0">&nbsp;</option>';
			for ($i=0; $i < $nb; $i++) { 
				$item=$origine->rows[$i];
				$select.='<option value="'.$item->rowid.'"';
	            if ($item->rowid == $value) $select.='selected';
	            $select.='>'.$item->source.'</option>';
			}
    	
		$select.='</select>';
		$select.='<script>$(function(){$("#select_'.$id.'").select2()})</script>';
	    return $select;
	}

	public function select_niveau($niveau='',$name='niveau'){
	   	global $langs;
	    $select.='<select class="flat" id="niveau" name="'.$name.'" >';
	    $select.='<option value="0" >&nbsp;</option>';
		$select.='<option value="Bac"  >Bac </option>';
		$select.='<option value="Bac+1" >Bac+1 </option>';
		$select.='<option value="Bac+2" >Bac+2 </option>';
		$select.='<option value="Bac+3" >Bac+3 </option>';
		$select.='<option value="Master_specialisé" >'.$langs->trans("Master_specialisé").' </option>';
		$select.='<option value="Master_recherche" >'.$langs->trans("Master_recherche").' </option>';
		$select.='<option value="Doctorat" >'.$langs->trans("Doctorat").' </option>';
	    $select.='</select>';
	    $select = str_replace('value="'.$niveau.'"', 'value="'.$niveau.'" selected', $select);

	    return $select;
	}

	public function select_etapes($value='',$name='etape')
	{
		global $conf,$langs;
		$id = (!empty($id)) ? $id : $name;
		$etapes = new CandidateStage($this->db);
		$etapes->fetchAll();
		$nb=count($etapes->rows);
		$select = '<select class="flat" id="select_'.$id.'" name="'.$name.'"  >';
	    	$select.='<option value="0">&nbsp;</option>';
			for ($i=0; $i < $nb; $i++) { 
				$item=$etapes->rows[$i];
				$select.='<option value="'.$item->rowid.'"';
	            if ($item->rowid == $value) $select.='selected';
	            $select.='>'.$langs->trans($item->label).'</option>';
			}
    	
		$select.='</select>';
		$select.='<script>$(function(){$("#select_'.$id.'").select2()})</script>';
	    return $select;
	}

	public function select_candidatures($value='',$name='etape')
	{
		global $conf,$langs;
		$id = (!empty($id)) ? $id : $name;
		// $Candidate = new Candidate($this->db);
		$this->fetchAll();
		$nb=count($this->rows);
		$select = '<select class="flat" id="select_'.$id.'" name="'.$name.'"  >';
	    	$select.='<option value="0">&nbsp;</option>';
			for ($i=0; $i < $nb; $i++) { 
				$item=$this->rows[$i];
				$select.='<option value="'.$item->rowid.'"';
	            if ($item->rowid == $value) $select.='selected';
	            $select.='>'.$langs->trans($item->sujet).'</option>';
			}
    	
		$select.='</select>';
		$select.='<script>$(function(){$("#select_'.$id.'").select2()})</script>';
	    return $select;
	}

	public function select_majors($value='', $filter='', $empty=1)
	{
		global $conf;
		// $select = '<select name="majors" id="select_majors">';
		if($empty)
			$select='<option></option>';
		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'rect_majors';
    	$sql .= ' WHERE entity = '.$conf->entity;

		if($filter)
			$sql .= ' '.$filter;
		$resql = $this->db->query($sql);
		if($resql){
			while ($obj=$this->db->fetch_object($resql)) {
				$select .= '<option value="'.$obj->rowid.'" >'.$obj->label.'</option>';
			}
		}
		// $select .= '</select>';
		$select = str_replace('value="'.$value.'"','value="'.$value.'" selected', $select);

		return $select;
	}
	public function select_degrees($value='')
	{
		global $conf;
		$select='<option></option>';

		// $select = '<select name="majors" id="select_degrees">';
		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'rect_degrees';
    	$sql .= ' WHERE entity = '.$conf->entity;
		$resql = $this->db->query($sql);
		if($resql){
			while ($obj=$this->db->fetch_object($resql)) {
				$select .= '<option value="'.$obj->rowid.'">'.$obj->label.'</option>';
			}
		}
		// $select .= '</select>';
		$select = str_replace('value="'.$value.'"','value="'.$value.'" selected', $select);
		
		return $select;
	}

	public function selectyear($selected = '', $htmlname = 'yearid', $useempty = 0, $min_year = 10, $max_year = 5, $offset = 0, $invert = 0, $option = '', $morecss = 'valignmiddle maxwidth75imp')
    {
        $out = '';

        $currentyear = date("Y") + $offset;
        $max_year = $currentyear + $max_year;
        $min_year = $currentyear - $min_year;
        if (empty($selected) && empty($useempty)) $selected = $currentyear;

        if ($useempty)
        {
        	$selected_html = '';
            if ($selected == '') $selected_html = ' selected';
            $out .= '<option value=""'.$selected_html.'>&nbsp;</option>';
        }
        for ($y = $max_year; $y >= $min_year; $y--)
        {
            $selected_html = '';
            if ($selected > 0 && $y == $selected) $selected_html = ' selected';
            $out .= '<option value="'.$y.'"'.$selected_html.' >'.$y.'</option>';
        }
       

        return $out;
    }
} 

/**
 * 
 */
class RecruitmentDegree extends Commonobject
{
	
	/**
	* @var string Error code (or message)
	* @deprecated
	* @see test::errors
	*/
	public $error;
	/**
	* @var string[] Error codes (or messages)
	*/
	public $errors = array();
	/**
	* @var string Id to identify managed objects
	*/
	// public $element = 'salle';
	/**
	* @var string Name of table without prefix where object is stored
	*/
	public $table_element = 'rect_degrees';

	  /**
	 * Draft status
	 */
	
    public $id;
	public $fk_user;
	public $rows = array();
	

	public function __construct($db)
	{
		$this->db 		 = $db;
		return 1;
	}


	public function create($insert)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql  = "INSERT INTO " . MAIN_DB_PREFIX .$this->table_element." ( ";
		foreach ($insert as $column => $value) {
			if($value != ""){
				$sql_column .= " , `".$column."`";
				$sql_value .= (is_numeric($value)) ? ", ".$value : ", '".$this->db->escape($value)."'";
			}
		}
		$sql .= substr($sql_column, 2)." ) VALUES ( ".substr($sql_value, 2)." )";
		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error '.get_class($this).' '. $this->db->lasterror();
			print_r($this->errors);die();
			return 0;
		} 

		return $this->db->db->insert_id;
	}

	
	public function update($id, array $data)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		if (!$id || $id <= 0)
			return false;

		// Update request
		$sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element  . ' SET ';
		if (count($data) && is_array($data))
			foreach ($data as $key => $val) {
				$val = is_numeric($val) ? $val : '"'. $val .'"';
				$sql .= '`'. $key. '` = '. $val .',';
			}
		$sql  = substr($sql, 0, -1);
		$sql .= ' WHERE  rowid = ' . $id;
        
        // echo $sql."<br>";
        // die;
		$this->db->begin();
		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();
			
			$this->error 	= 'Error ' . $this->db->lasterror();
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		} else {
			$this->db->commit();

			return 1;
		}
	}
	
	public function delete()
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$this->db->begin();

		$sql 	= 'DELETE FROM ' . MAIN_DB_PREFIX . $this->table_element .' WHERE  rowid = ' . $this->rowid;
		$resql 	= $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();

			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		} else {
			$this->db->commit();

			return 1;
		}
	}
	
	
	
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = '',$id = '',$month='')
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		global $conf;
		$pafp = $conf->global->pointcontrol_ACTIF_FORPROJECTS;

	

		$sql = 'SELECT *  FROM ' . MAIN_DB_PREFIX .$this->table_element;
    	$sql .= ' WHERE entity = '.$conf->entity;

		if (!empty($filter)) {
			$sql .=  ' '.$filter;
		}
		
		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}	
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit,$offset);
		}	
		$this->rows = array();
		$resql 		 = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);
 	       	$this->num     = $num;

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new stdClass;
     	       	$line->rowid   = $obj->rowid;
				$line->label   = $obj->label;			
				$line->entity   = $obj->entity;			

				$this->rows[]  = $line;
			}
			$this->db->free($resql);
			return $num;
		} else {
			$this->error 	= 'Error ' . $this->db->lasterror();
			$this->errors[] = $this->error;
			print_r($this->error);
			die();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			return -1;
		}
	}

	public function fetch($id=0,$year=0,$month=0)
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . $this->table_element ;
		$sql .= ' WHERE rowid = '.$id;
    	$sql .= ' AND entity = '.$conf->entity;
		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 		  = $this->db->fetch_object($resql);
				$this->id  = $obj->rowid;
				$this->rowid  = $obj->rowid;
				$this->label  = $obj->label;
				$this->entity  = $obj->entity;
			}

			$this->db->free($resql);

			if ($numrows) {
				return 1;
			} else {
				return 0;
			}
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			return -1;
		}
	}
	
	public function LabelArr()
	{
		
    	$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
    	$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$data[]=$obj->label;
			}
			$this->db->free($resql);
		}

		return $data;
	}

	

	public function IdDegrees()
	{
		global $conf;
		
    	$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
    	$sql .= ' WHERE entity = '.$conf->entity;

    	$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$data[]=$obj->rowid;
			}
			$this->db->free($resql);
		}

		return $data;
	}
	
	
	public function select_with_filter($selected=0,$name='degrees',$showempty=1,$val="rowid",$opt="label",$id='',$attr=''){

	    global $conf;

	    $moreforfilter = '';
	    $nodatarole = '';
	    $id = (!empty($id)) ? $id : $name;

	    $moreforfilter.='<select width="100%" '.$attr.' class="flat" id="select_degrees" name="'.$name.'">';
	    if ($showempty) $moreforfilter.='<option value="0">&nbsp;</option>';

    	$sql = "SELECT ".$val.",".$opt." FROM ".MAIN_DB_PREFIX.$this->table_element;
    	$sql .= ' WHERE entity = '.$conf->entity;

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

	public function getNomUrl($withpicto=0, $nblettr='')
    {
        global $langs, $conf, $user;
        if (! empty($conf->dol_no_mouse_hover)) $notooltip=1;   // Force disable tooltips

        $result='';
        $label='';
        $url = dol_buildpath('/sghr/recruitment/Candidate/degrees/card.php?id='.$this->rowid,2);

        $linkclose='';
        if (empty($notooltip))
        {
            $linkclose.= ' title="'.dol_escape_htmltag($label, 1).'"';
            $linkclose.=' class="classfortooltip"';
        }
        $linkstart = "";
        $linkend = "";
        $result = "";

        

		$ref = '';
		if($this->num)
    		$ref .= '<b>'.$this->num.'</b> - ';

        if(!empty($this->label)){
        	$ref.=$this->label;
        }else
        	$ref.=$this->rowid;

        if ($ref) {
            $linkstart = '<a href="'.$url.'" ';
            $linkstart.=$linkclose.'>';
            $linkend='</a>';

            $result .= $linkstart;
            if($nblettr) $ref = dol_trunc($ref,$nblettr);
           	$result .= $ref;
        }

        $result .= $linkend;

        return $result;
    }

    public function showNavigations($object, $linkback, $htmlleft='', $paramid = 'id', $fieldid = 'rowid', $moreparam = '')
	{

		global $langs, $conf;

		$ret = $result = '';
		$previous_ref = $next_ref = '';

		$fieldref = $fieldid;

		$object->ref = $object->rowid;

		$object->load_previous_next_ref('', $fieldid, 0);

		$navurl = $_SERVER["PHP_SELF"];

		$page = GETPOST('page');

		// accesskey is for Windows or Linux:  ALT + key for chrome, ALT + SHIFT + KEY for firefox
		// accesskey is for Mac:               CTRL + key for all browsers
		$stringforfirstkey = $langs->trans("KeyboardShortcut");
		if ($conf->browser->name == 'chrome')
		{
		    $stringforfirstkey .= ' ALT +';
		}
		elseif ($conf->browser->name == 'firefox')
		{
		    $stringforfirstkey .= ' ALT + SHIFT +';
		}
		else
		{
		    $stringforfirstkey .= ' CTL +';
		}

		$previous_ref = $object->ref_previous ? '<a accesskey="p" title="'.$stringforfirstkey.' p" class="classfortooltip" href="'.$navurl.'?'.$paramid.'='.urlencode($object->ref_previous).$moreparam.'"><i class="fa fa-chevron-left"></i></a>' : '<span class="inactive"><i class="fa fa-chevron-left opacitymedium"></i></span>';
		$next_ref     = $object->ref_next ? '<a accesskey="n" title="'.$stringforfirstkey.' n" class="classfortooltip" href="'.$navurl.'?'.$paramid.'='.urlencode($object->ref_next).$moreparam.'"><i class="fa fa-chevron-right"></i></a>' : '<span class="inactive"><i class="fa fa-chevron-right opacitymedium"></i></span>';

		$ret = '';
		// print "xx".$previous_ref."x".$next_ref;
		// $ret .= '<div style="vertical-align: middle">';

		if ($previous_ref || $next_ref || $linkback)
		{
		    $ret .= '<div class="pagination paginationref"><ul class="right">';
		}

		if ($linkback)
		{
		    $ret .= '<li class="noborder litext">'.$linkback.'</li>';
		}
		if (($previous_ref || $next_ref))
		{
		    $ret .= '<li class="pagination">'.$previous_ref.'</li>';
		    $ret .= '<li class="pagination">'.$next_ref.'</li>';
		}
		if ($previous_ref || $next_ref || $linkback)
		{
		    $ret .= '</ul></div>';
		}

		$result .= '<div style="height: 41px;">';
		if($htmlleft)
			$result.= $htmlleft;
		$result .= $ret;
		$result .= '</div>';

		return $result;
    }

}
/**
 * 
 */
class rect_majors extends Commonobject
{
	
	/**
	* @var string Error code (or message)
	* @deprecated
	* @see test::errors
	*/
	public $error;
	/**
	* @var string[] Error codes (or messages)
	*/
	public $errors = array();
	/**
	* @var string Id to identify managed objects
	*/
	// public $element = 'salle';
	/**
	* @var string Name of table without prefix where object is stored
	*/
	public $table_element = 'rect_majors';

	  /**
	 * Draft status
	 */
	
    public $id;
	public $fk_user;
	public $rows = array();
	

	public function __construct($db)
	{
		$this->db 		 = $db;
		return 1;
	}


	public function create($insert)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql  = "INSERT INTO " . MAIN_DB_PREFIX .$this->table_element." ( ";
		foreach ($insert as $column => $value) {
			if($value != ""){
				$sql_column .= " , `".$column."`";
				$sql_value .= (is_numeric($value)) ? ", ".$value : ", '".$this->db->escape($value)."'";
			}
		}
		$sql .= substr($sql_column, 2)." ) VALUES ( ".substr($sql_value, 2)." )";
		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error '.get_class($this).' '. $this->db->lasterror();
			// print_r($this->errors);die();
			return 0;
		} 

		return $this->db->db->insert_id;
	}

	
	public function update($id, array $data)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		if (!$id || $id <= 0)
			return false;

		// Update request
		$sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element  . ' SET ';
		if (count($data) && is_array($data))
			foreach ($data as $key => $val) {
				$val = is_numeric($val) ? $val : '"'. $val .'"';
				$sql .= '`'. $key. '` = '. $val .',';
			}
		$sql  = substr($sql, 0, -1);
		$sql .= ' WHERE  rowid = ' . $id;
        
        // echo $sql."<br>";
        // die;
		$this->db->begin();
		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();
			
			$this->error 	= 'Error ' . $this->db->lasterror();
			$this->errors[] = $this->error;
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		} else {
			$this->db->commit();

			return 1;
		}
	}
	
	public function delete()
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$this->db->begin();

		$sql 	= 'DELETE FROM ' . MAIN_DB_PREFIX . $this->table_element .' WHERE  rowid = ' . $this->rowid;
		$resql 	= $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();

			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		} else {
			$this->db->commit();

			return 1;
		}
	}
	

	
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = '',$id = '',$month='')
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		global $conf;
		$pafp = $conf->global->pointcontrol_ACTIF_FORPROJECTS;

	

		$sql = 'SELECT *  FROM ' . MAIN_DB_PREFIX .$this->table_element;
    	$sql .= ' WHERE entity = '.$conf->entity;


		if (!empty($filter)) {
			$sql .= ' '.$filter;
		}
		
		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}	
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit,$offset);
		}	
		$this->rows = array();
		$resql 		 = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
 	       	$this->num     = $num;

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new stdClass;
     	       	$line->id   = $obj->rowid;
     	       	$line->rowid   = $obj->rowid;
				$line->label   = $obj->label;			
				$line->fk_degree   = $obj->fk_degree;			
				$line->entity   = $obj->entity;			

				$this->rows[]  = $line;
			}
			$this->db->free($resql);
			return $num;
		} else {
			$this->error 	= 'Error ' . $this->db->lasterror();
			$this->errors[] = $this->error;
			print_r($this->error);
			die();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			return -1;
		}
	}

	public function fetch($id=0,$year=0,$month=0)
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX . $this->table_element ;
		$sql .= ' WHERE rowid = '.$id;
    	$sql .= ' AND entity = '.$conf->entity;

		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj 		  = $this->db->fetch_object($resql);
				$this->id  = $obj->rowid;
				$this->rowid  = $obj->rowid;
				$this->label  = $obj->label;
				$this->fk_degree  = $obj->fk_degree;
				$this->entity  = $obj->entity;
			}

			$this->db->free($resql);

			if ($numrows) {
				return 1;
			} else {
				return 0;
			}
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);
			return -1;
		}
	}
	
	public function LabelArr()
	{
		
    	$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
    	$sql .= ' WHERE entity = '.$conf->entity;
    	$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$data[]=$obj->label;
			}
			$this->db->free($resql);
		}

		return $data;
	}

	

	public function IdDegrees()
	{
		global $conf;
    	$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
    	$sql .= ' WHERE entity = '.$conf->entity;
    	$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$data[]=$obj->rowid;
			}
			$this->db->free($resql);
		}

		return $data;
	}
	
	
	public function select_with_filter($selected=0,$name='degrees',$showempty=1,$val="rowid",$opt="label",$id='',$attr=''){

	    global $conf;

	    $moreforfilter = '';
	    $nodatarole = '';
	    $id = (!empty($id)) ? $id : $name;

	    $moreforfilter.='<select width="100%" '.$attr.' class="flat" id="select_degrees" name="'.$name.'">';
	    if ($showempty) $moreforfilter.='<option value="0">&nbsp;</option>';

    	$sql = "SELECT ".$val.",".$opt." FROM ".MAIN_DB_PREFIX.$this->table_element;
    	$sql .= ' WHERE entity = '.$conf->entity;
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

	
	public function getNomUrl($withpicto=0, $nblettr='')
    {
        global $langs, $conf, $user;
        if (! empty($conf->dol_no_mouse_hover)) $notooltip=1;   // Force disable tooltips

        $result='';
        $label='';
        $url = dol_buildpath('/sghr/recruitment/Candidate/degrees/cardmajor.php?id='.$this->rowid.'&id_degree='.$this->fk_degree,2);

        $linkclose='';
        if (empty($notooltip))
        {
            $linkclose.= ' title="'.dol_escape_htmltag($label, 1).'"';
            $linkclose.=' class="classfortooltip"';
        }
        $linkstart = "";
        $linkend = "";
        $result = "";

        

		$ref = '';
		if($this->num)
    		$ref .= '<b>'.$this->num.'</b> - ';

        if(!empty($this->label)){
        	$ref.=$this->label;
        }else
        	$ref.=$this->rowid;

        if ($ref) {
            $linkstart = '<a href="'.$url.'" ';
            $linkstart.=$linkclose.'>';
            $linkend='</a>';

            $result .= $linkstart;
            if($nblettr) $ref = dol_trunc($ref,$nblettr);
           	$result .= $ref;
        }

        $result .= $linkend;

        return $result;
    }

    public function showNavigations($object, $linkback, $htmlleft='', $paramid = 'id', $fieldid = 'rowid', $moreparam = '')
	{

		global $langs, $conf;

		$ret = $result = '';
		$previous_ref = $next_ref = '';

		$fieldref = $fieldid;

		$object->ref = $object->rowid;

		$object->load_previous_next_ref('', $fieldid, 0);

		$navurl = $_SERVER["PHP_SELF"];

		$page = GETPOST('page');

		// accesskey is for Windows or Linux:  ALT + key for chrome, ALT + SHIFT + KEY for firefox
		// accesskey is for Mac:               CTRL + key for all browsers
		$stringforfirstkey = $langs->trans("KeyboardShortcut");
		if ($conf->browser->name == 'chrome')
		{
		    $stringforfirstkey .= ' ALT +';
		}
		elseif ($conf->browser->name == 'firefox')
		{
		    $stringforfirstkey .= ' ALT + SHIFT +';
		}
		else
		{
		    $stringforfirstkey .= ' CTL +';
		}

		$previous_ref = $object->ref_previous ? '<a accesskey="p" title="'.$stringforfirstkey.' p" class="classfortooltip" href="'.$navurl.'?'.$paramid.'='.urlencode($object->ref_previous).$moreparam.'"><i class="fa fa-chevron-left"></i></a>' : '<span class="inactive"><i class="fa fa-chevron-left opacitymedium"></i></span>';
		$next_ref     = $object->ref_next ? '<a accesskey="n" title="'.$stringforfirstkey.' n" class="classfortooltip" href="'.$navurl.'?'.$paramid.'='.urlencode($object->ref_next).$moreparam.'"><i class="fa fa-chevron-right"></i></a>' : '<span class="inactive"><i class="fa fa-chevron-right opacitymedium"></i></span>';

		$ret = '';
		// print "xx".$previous_ref."x".$next_ref;
		// $ret .= '<div style="vertical-align: middle">';

		if ($previous_ref || $next_ref || $linkback)
		{
		    $ret .= '<div class="pagination paginationref"><ul class="right">';
		}

		if ($linkback)
		{
		    $ret .= '<li class="noborder litext">'.$linkback.'</li>';
		}
		if (($previous_ref || $next_ref))
		{
		    $ret .= '<li class="pagination">'.$previous_ref.'</li>';
		    $ret .= '<li class="pagination">'.$next_ref.'</li>';
		}
		if ($previous_ref || $next_ref || $linkback)
		{
		    $ret .= '</ul></div>';
		}

		$result .= '<div style="height: 41px;">';
		if($htmlleft)
			$result.= $htmlleft;
		$result .= $ret;
		$result .= '</div>';

		return $result;
    }

}
?>