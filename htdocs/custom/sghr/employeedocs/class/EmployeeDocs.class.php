<?php 
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php'; 

// dol_include_once('/sghr/employeedocs/class/docsemployes.class.php');
// require_once 'PHPMailer/src/SMTP.php';
// require_once 'PHPMailer/src/PHPMailer.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

// dol_include_once('/sghr/employeedocs/notifications/PHPMailer/src/SMTP.php');
// dol_include_once('/sghr/employeedocs/notifications/PHPMailer/src/PHPMailer.php');


class EmployeeDocs extends Commonobject{ 

	public $errors = array();
	public $rowid;
	public $number;

	public $element='docsemployes';
	public $table_element='docsemployes';
	
	public function __construct($db){ 
		$this->db = $db;
		return 1;
    }


	public function create($echo_sql = 0, $insert = array())
	{
		$sql  = "INSERT INTO " . MAIN_DB_PREFIX .get_class($this)." ( ";
		foreach ($insert as $column => $value) {
			if(!$value){
				$sql_column .= " , ".$column;
				$sql_value .= " , NULL";
			}else{
				$alias = (is_numeric($value)) ? "" : "'";
				$sql_column .= " , `".$column."`";
				$sql_value .= " , ".$alias.$value.$alias;
			}
		}

		$sql .= substr($sql_column, 2)." ) VALUES ( ".substr($sql_value, 2)." )";
		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->db->rollback();
			$this->errors[] = 'Error '.get_class($this).' '. $this->db->lasterror();
			return 0;
		} 
		return $this->db->db->insert_id;
	}

	public function update($id, array $data,$echo_sql=0)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		if (!$id || $id <= 0)
			return false;
        $sql = 'UPDATE ' . MAIN_DB_PREFIX .get_class($this). ' SET ';
        // print_r($data);die();
        if (count($data) && is_array($data))
            foreach ($data as $key => $val) {

            	if($key == 'expirydate' && $val == ''){
					$sql .= "`". $key. "` = NULL,";
				}else{
	                $val = is_numeric($val) ? $val : "'". $val ."'";
	                $sql .= "`". $key. "` = ". $val .",";
				}
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
		// echo($sql);die();

		$this->rows = array();
		$resql = $this->db->query($sql);

		if ($resql) {
			$num = $this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$line = new stdClass;
                $line->id    = $obj->rowid;
				$line->rowid 	= $obj->rowid;
				$line->number 	=  $obj->number;
				$line->issuedate 	=  $obj->issuedate;
				$line->expirydate 	=  $obj->expirydate;
				$line->date_notification 	=  $obj->date_notification;
				$line->fk_type_document 	=  $obj->fk_type_document;
				$line->fk_user 	=  $obj->fk_user;
				$line->file 	=  $obj->file;
				$line->description 	=  $obj->description;
				$line->destinataire 	=  $obj->destinataire;
				$line->sendmail 	=  $obj->sendmail;
				$line->entity 	=  $obj->entity;
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

	public function fetch($id, $name = null)
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql = 'SELECT * FROM ' . MAIN_DB_PREFIX .get_class($this). ' WHERE rowid = ' . $id;
		$sql .= ' AND entity='.$conf->entity;

		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			
			if ($numrows) {
				$obj 			= $this->db->fetch_object($resql);
                $this->id    = $obj->rowid;
				$this->rowid 	= $obj->rowid;
				$this->number 	=  $obj->number;
				$this->issuedate 	=  $obj->issuedate;
				$this->expirydate 	=  $obj->expirydate;
				$this->date_notification 	=  $obj->date_notification;
				$this->fk_type_document 	=  $obj->fk_type_document;
				$this->fk_user 	=  $obj->fk_user;
				$this->file 	=  $obj->file;
				$this->description 	=  $obj->description;
				$this->destinataire 	=  $obj->destinataire;
				$this->sendmail 	=  $obj->sendmail;
				$this->entity 	=  $obj->entity;
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

	public function select_documents_type($selected=0,$name='select_',$showempty=1,$val="rowid",$opt="label",$id=''){
	    global $conf;
	    $moreforfilter = '';
	    $nodatarole = '';
	    $id = (!empty($id)) ? $id : $name;
	    
	    $objet = "label";
	    $moreforfilter.='<select class="flat" id="'.$id.'" name="'.$name.'" '.$nodatarole.'>';
	    if ($showempty) $moreforfilter.='<option value="0">&nbsp;</option>';

    	$sql = "SELECT * FROM ".MAIN_DB_PREFIX."type_document";
		$sql .= ' WHERE entity='.$conf->entity;
    	// $sql2= "SELECT * FROM ".MAIN_DB_PREFIX."user";
    	$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			
			while ($obj = $this->db->fetch_object($resql)) {
				$moreforfilter.='<option value="'.$obj->$val.'" data-ref="'.$obj->$opt.'"';
	            if ($obj->$val == $selected) $moreforfilter.=' selected';
	            $moreforfilter.='>'.$obj->$opt.'</option>';
			}
			$this->db->free($resql);
		}

	    $moreforfilter.='</select>';
	    $moreforfilter.='<style>#s2id_select_'.$name.'{ width: 100% !important;}</style>';
	    return $moreforfilter;
	}

	public function select_user($selected=0,$name='select_',$showempty=1,$val="rowid",$opt="label",$id=''){
	    global $conf;
	    $moreforfilter = '';
	    $nodatarole = '';
	    $id = (!empty($id)) ? $id : $name;
	    
	    $objet = "label";
	    $moreforfilter.='<select class="flat" id="'.$id.'" name="'.$name.'" '.$nodatarole.'>';
	    if ($showempty) $moreforfilter.='<option value="0">&nbsp;</option>';

    	$sql = "SELECT * FROM ".MAIN_DB_PREFIX."user";
		$sql .= " WHERE entity IN (0,".$conf->entity.")";
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

    public function getNomUrl($withpicto=0, $option='', $get_params='', $notooltip=0, $save_lastsearch_value=-1){
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

        if ($this->ref) {
            $linkstart = '<a href="'.$url.'"';
            $linkstart.=$linkclose.'>';
            $linkend='</a>';

            $result .= $linkstart;
            if ($withpicto) 
                $result.= '<img height="16" src="'.DOL_URL_ROOT.'/sghr/employeedocs/img/object_docsemployes.png" >&nbsp;';
            if ($withpicto != 2) $result.= $this->ref;
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

    public function getYears($debut="debut"){
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

    public function getmonth($year){
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

    public function get_type_document($id){
	    global $conf;
    	$type='';
    	$sql ='SELECT name AS type FROM ' . MAIN_DB_PREFIX.'type_document WHERE rowid = '.$id;
		$sql .= ' AND entity='.$conf->entity;
    	$exsql=$this->db->query($sql);
    	if($exsql){
    		$type = $this->db->fetch_object($exsql)->type;
    	}
    	return '<a href="'.dol_buildpath('/sghr/employeedocs/type_document/card.php?id='.$id,2).'">'.$type.'</a>';
    }

    public function get_employe($id){
	    global $conf;
    	$name='';
    	$sql ='SELECT login AS name FROM ' . MAIN_DB_PREFIX.'user WHERE rowid = '.$id;
		$sql .= " AND entity IN (0,".$conf->entity.")";
    	$exsql=$this->db->query($sql);
    	if($exsql){
    		$name = $this->db->fetch_object($exsql)->name;
    	}
    	return '<a href="'.dol_buildpath('/user/card.php?id='.$id,2).'">'.$name.'</a>';
    }

    public function logo_user($id){
	    global $conf;
    	$logo="";
    	$sql ='SELECT logo  FROM ' . MAIN_DB_PREFIX.'user WHERE rowid = '.$id;
		$sql .= " AND entity IN (0,".$conf->entity.")";
    	$exsql=$this->db->query($sql);
    	if($exsql){
    		$logo = $this->db->fetch_object($exsql);
    	}
    	return $logo;
    }

    public function send_mail($email,$name,$subject,$title="Expiration de document"){

    	dol_include_once('/sghr/employeedocs/notifications/PHPMailer/src/SMTP.php');
		dol_include_once('/sghr/employeedocs/notifications/PHPMailer/src/PHPMailer.php');
    	global $conf;
		// ini_set( 'display_errors', 1 );
		// error_reporting( E_ALL );
		$smtp = dolibarr_get_const($this->db,'MAIN_MAIL_SMTP_SERVER',$conf->entity);
		$smtp_id = dolibarr_get_const($this->db,'MAIN_MAIL_SMTPS_ID',$conf->entity);
		$smtp_pw = dolibarr_get_const($this->db,'MAIN_MAIL_SMTPS_PW',$conf->entity);

		$mail = new PHPMailer(true);
		//Send mail using gmail
		$mail->IsSMTP(); // telling the class to use SMTP
		$mail->SMTPAuth = true; // enable SMTP authentication
		$mail->SMTPSecure = "ssl"; // sets the prefix to the servier
		$mail->Host = $smtp; // sets GMAIL as the SMTP server
		$mail->Port = 465; // set the SMTP port for the GMAIL server
		$mail->Username = $smtp_id; // GMAIL username
		$mail->Password = $smtp_pw; // GMAIL password
		$mail->isHTML(true);
		//Typical mail data (simple data or attached data)
		$mail->AddAddress($email, $name);
		// print_r($conf->global);
		$soc = "Société";
		if($conf->global->MAIN_INFO_SOCIETE_MAIL && !empty($conf->global->MAIN_INFO_SOCIETE_MAIL))
			$soc = $conf->global->MAIN_INFO_SOCIETE_MAIL;
		else{
			setEventMessages('Module Documents d\'employés: Veuillez remplir le mail de votre Société/Organisation', null, 'warnings');
			return 0;
		}

		$mail->SetFrom($soc,$conf->global->MAIN_INFO_SOCIETE_NOM);
		$mail->Subject = $title;
		// $mail->Body = $subject;
		$mail->Body = html_entity_decode($subject);


		try{
		    $mail->Send();
		     // echo "Success!";
		    return 1;
		} catch(Exception $e){
		    //Something went bad
		    // echo "Fail - " . $mail->ErrorInfo;
		    return 0;
		}
    }

    public function get_document_user(){
    	global $user,$conf;

    	$user22 = new User($this->db);
        $parameters=[3 => 3,5 => 5,10 => 10];
    	$sql1='SELECT rowid, fk_user, expirydate,number,date_notification FROM '.MAIN_DB_PREFIX.get_class($this);
		$sql .= ' WHERE entity='.$conf->entity;
    	$resql1 = $this->db->query($sql1);
    	$docs = array();
    	$msg = array();
    	$message =array();
    	if ($resql1) {
            $num = $this->db->num_rows($resql1);
            while ($obj = $this->db->fetch_object($resql1)) {
                $docs[$obj->fk_user][$obj->rowid] = $obj;
            }
            $this->db->free($resql1);
        }

        foreach ($docs as $user_ => $doc) {
    		$user22->fetch($user_);
		    if(!empty($user22->email)){
	    		$message[$user_]['mail'] = $user22->email;
			    $message[$user_]['name'] = $user22->lastname.' '.$user22->firstname;

	        	foreach ($doc as $key => $value) {
	        		
	        		$datenot = $value->date_notification;
	        		$dateexp = $value->expirydate;
	    			$date=date('Y-m-d');

			        $dif=date_diff(date_create($date),date_create($dateexp));
		        	$jour = $dif->format("%a");

		        	$diff_notification=date_diff(date_create($date),date_create($datenot));
		        	$diff_notification = $diff_notification->format("%a");

					if($dateexp < $date){
						if(isset($parameters[$jour])){
							if($diff_notification != 0 || empty($datenot)){
								$message[$user_]['documents'][$key]['message'].='Le document '.$value->number.'est expiré ça fait '.$jour.' jours ('.$dateexp.')<br>Veuillez le renouveler.';
							}
						}
					}
					else if($dateexp > $date){
			        	if($diff_notification != 0 || empty($datenot)){
			        	
							if(isset($parameters[$jour])){
								$message[$user_]['documents'][$key]['message'].='Le document : '.$value->number.' va bientôt expirer le '.$dateexp.' ('.$jour.' jours).<br>Veuillez le renouveler avant la date d\'expiration.';
							}
						}
					}
					else if($jour == 0){
						$message[$user_]['documents'][$key]['message'].='Le document : '.$value->number.' est expiré aujourd\'hui<br>Veuillez le renouveler.';
					}
	        	}
        	}
        }
        
        // print_r($message);die();
        if($message){
        	$data =  array('date_notification'=> $date);
			foreach ($message as $id_user => $documents){
				if(isset($documents['documents'])){
					$user22->fetch($id_user);
					$msguser = 'Bonjour '.$user22->lastname.' '.$user22->firstname.'<br><br>';
					foreach ($documents['documents'] as $id_doc => $msg){
						$msguser .= $msg['message'].'<br>';
						// echo $result;
						// die();
					}
					// echo $msguser."<br>";
					$result = $this->send_mail($documents['mail'],$documents['name'],$msguser);
					if($result){
						foreach ($documents['documents'] as $id_doc => $msg){
							$isvalid = $this->update($id_doc, $data);
						}
					}
				}
			}
        }
        // die();
    }

    public function docsemployespermissionto($source){
	    if(is_dir($source)) {
	    	@chmod($source, 0775);
	        $dir_handle=opendir($source);
	        while($file=readdir($dir_handle)){
	            if($file!="." && $file!=".."){
	                if(is_dir($source."/".$file)){
	                    @chmod($source."/".$file, 0775);
	                    $this->docsemployespermissionto($source."/".$file);
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

	// Config Email
	public function checkDocsMails()
	{
		global $conf, $langs;

		$langs->load('employeedocs@sghr');

		$nbrtotal = $this->fetchAll();

		$datenow = date('Y-m-d');
		$diffday = 0;

		$nbd = 7;
	    if(!empty($conf->global->DOCSEMPLOYES_EMAIL_DAYS_BEFORE)) 
	        $nbd = $conf->global->DOCSEMPLOYES_EMAIL_DAYS_BEFORE;

	    if(!empty($conf->global->DOCSEMPLOYES_SEND_EMAIL)){

		    $societename = '';
		    if(!empty($conf->global->MAIN_INFO_SOCIETE_NOM))
		    	$societename = $conf->global->MAIN_INFO_SOCIETE_NOM;

		    if($conf->docsemployes->enabled){

		    	$body = '';
		    	$frlng = 0;
		    	$lgs = explode("_", $conf->global->MAIN_LANG_DEFAULT);
		    	if (isset($lgs[0]) && $lgs[0] == 'fr'){
		    		$frlng = 1;
		    	}

		    	

				// $title = html_entity_decode($langs->trans("Expiration_of_documents"));
				$title = html_entity_decode("Expiration of documents");
		    	if($frlng) $title = html_entity_decode("Expiration des documents");

			    if (count($this->rows) > 0) {
					for ($i=0; $i < count($this->rows) ; $i++) {
						$item = $this->rows[$i];

						if(!empty($item->expirydate)){

							$dateexpired = '';
							$date2= explode('-', $item->expirydate);
					        $dateexpired=$date2[2].'/'.$date2[1].'/'.$date2[0];

							// $vehicules = new vehiculeparc($this->db);
							// $vehicules->fetch($item->vehicule);
							
							if(!empty($item->sendmail)){

								$diffday = $this->calculateDatesBetween($item->expirydate, $datenow);
								$diffday = $diffday * 1;

								// echo "diffday : ".$diffday."<br>";
								// die;
								if(($diffday < 0 && $diffday == (-1*$nbd)) || (!empty($conf->global->DOCSEMPLOYES_SEND_EMAIL_EVRYDAY_BEFORE) && $diffday < 0 && $diffday > (-1*$nbd)) ){
									$body = "Hello,<br><br>";
									$body .= "We inform you that there are only <b>__RESTEDAYS__</b> __NBRDAYS__ left before the expiration date (__EXPIRATEDAY__) of document number <b>__REFOFDOCS__</b>.<br><br>";
									$body .= "Best regards,<br>";
									
							    	if($frlng){
										$body = "Bonjour,<br><br>";
										$body .= "Nous vous informons qu'il ne reste que <b>__RESTEDAYS__</b> __NBRDAYS__ avant la date d'expiration (__EXPIRATEDAY__) du document numéro <b>__REFOFDOCS__</b>.<br><br>";
										$body .= "Bien Cordialement,<br>";
							    	}

									// echo $body."<br>";
							    	if(!empty($societename)) $body .= $societename."<br>";

									

									$item->substitutionarray['__RESTEDAYS__'] = ($diffday*-1);

									if(($diffday*-1) > 1) $dayors = "days"; else  $dayors = "day";
									if($frlng){
										if(($diffday*-1) > 1) $dayors = "jours"; else  $dayors = "jour";
									}

									$item->substitutionarray['__NBRDAYS__'] = $dayors;
									$item->substitutionarray['__EXPIRATEDAY__'] = $dateexpired;
									$item->substitutionarray['__REFOFDOCS__'] = '<a href="'.dol_buildpath('/sghr/employeedocs/card.php?id='.$item->rowid,2).'" target="_blank">#'.$item->number.'</a> ';
									// $item->substitutionarray['__VEHICULEINFO__'] = '<a href="'.dol_buildpath('/sghr/employeedocs/card.php?id='.$item->vehicule,2).'" >'.$vehicules->get_nom($item->vehicule,1).'</a> ';

									$mail = $this->sendMailToAdmin($item,$title,$body);

								}
								elseif($diffday > 0 && !empty($conf->global->DOCSEMPLOYES_SEND_EMAIL_EVRYDAY_AFTER)){
									$body = "Hello,<br><br>";
									$body .= "We inform you that the expiration date (__EXPIRATEDAY__) of document number <b>__REFOFDOCS__</b>  has expired <b>__RESTEDAYS__</b> __NBRDAYS__ ago.<br><br>";
									$body .= "Best regards,<br>";

							    	if ($frlng){
										$body = "Bonjour,<br><br>";
										$body .= "Nous vous informons que la date d'expiration (__EXPIRATEDAY__) du document numéro <b>__REFOFDOCS__</b> est expiré depuis <b>__RESTEDAYS__</b> __NBRDAYS__.<br><br>";
										$body .= "Bien Cordialement,<br>";
							    	}
							    	// echo $body."<br>";
							    	if(!empty($societename)) $body .= $societename."<br>";

									

									$item->substitutionarray['__RESTEDAYS__'] = ($diffday*1);

									if(($diffday*1) > 1) $dayors = "days"; else  $dayors = "day";
									if($frlng){
										if(($diffday*1) > 1) $dayors = "jours"; else  $dayors = "jour";
									}

									$item->substitutionarray['__NBRDAYS__'] = $dayors;
									$item->substitutionarray['__EXPIRATEDAY__'] = $dateexpired;
									$item->substitutionarray['__REFOFDOCS__'] = '<a href="'.dol_buildpath('/sghr/employeedocs/card.php?id='.$item->rowid,2).'" target="_blank">#'.$item->number.'</a> ';
									// $item->substitutionarray['__VEHICULEINFO__'] = '<a href="'.dol_buildpath('/sghr/employeedocs/card.php?id='.$item->vehicule,2).'" >'.$vehicules->get_nom($item->vehicule,1).'</a> ';


									$mail = $this->sendMailToAdmin($item,$title,$body);

			
								}
								elseif($diffday == 0){
									$body = "Hello,<br><br>";
									$body .= "We inform you that the expiration day (__EXPIRATEDAY__) of document number <b>__REFOFDOCS__</b> has been reached.<br><br>";
									$body .= "Best regards,<br>";

							    	if ($frlng){
										$body = "Bonjour,<br><br>";
										$body .= "Nous vous informons que le jour d'expiration (__EXPIRATEDAY__) du document numéro <b>__REFOFDOCS__</b> est atteint.<br><br>";
										$body .= "Bien Cordialement,<br>";
							    	}
							    	// echo $body."<br>";
							    	if(!empty($societename)) $body .= $societename."<br>";

							    	// echo $body;die;
									

									$item->substitutionarray['__EXPIRATEDAY__'] = $dateexpired;
									$item->substitutionarray['__REFOFDOCS__'] = '<a href="'.dol_buildpath('/sghr/employeedocs/card.php?id='.$item->rowid,2).'" target="_blank">#'.$item->number.'</a> ';
									// $item->substitutionarray['__VEHICULEINFO__'] = '<a href="'.dol_buildpath('/sghr/employeedocs/card.php?id='.$item->vehicule,2).'" >'.$vehicules->get_nom($item->vehicule,1).'</a> ';


									$mail = $this->sendMailToAdmin($item,$title,$body);

								}
							}
							
						}

					}
				}
		    }
	    }
		
		
		return 0;
    }

    public function sendMailToAdmin($object, $title="", $body=""){

    	global $langs, $conf;

    	$langs->load('employeedocs@sghr');

    	$object->sujet = $title;
    	$object->body = $body;

    	$socemail = "";


		if($conf->global->DOCSEMPLOYES_SENDTOMAIL)
			$socemail = $conf->global->DOCSEMPLOYES_SENDTOMAIL;

		// if($conf->global->MAIN_INFO_SOCIETE_MAIL)

		$sendfrom = $conf->global->MAIN_INFO_SOCIETE_MAIL;


		if(!empty($socemail)){

	    	$mailsendt = $socemail;
	    	// if($object->destination){
	    	// 	$mailsendt = $object->destination;
	    	// }
	    	if(!$sendfrom){
	    		$sendt = new User($this->db);
	    		$sendt->fetch($object->fk_user);
	    		$sendfrom = $sendt->email;
	    	}

	    	$object->email_from = $sendfrom;
	    	$object->sendto = $mailsendt;


	    	// Le message est-il en html
			$msgishtml = -1; // Inconnu par defaut
			if (preg_match('/[\s\t]*<html>/i', $object->body)) $msgishtml = 1;

			// other are set at begin of page
			$object->substitutionarray['__EMAIL__'] = $object->sendto;
			$object->substitutionarray['__MAILTOEMAIL__'] = '<a href="mailto:'.$object->sendto.'">'.$object->sendto.'</a>';

			// Pratique les substitutions sur le sujet et message
	        complete_substitutions_array($object->substitutionarray, $langs);
			$tmpsujet = make_substitutions($object->sujet, $object->substitutionarray);
			$tmpbody = make_substitutions($object->body, $object->substitutionarray);

			$arr_file = array();
			$arr_mime = array();
			$arr_name = array();
			$arr_css  = array();

	        // Ajout CSS
	        // if (!empty($object->bgcolor)) $arr_css['bgcolor'] = (preg_match('/^#/', $object->bgcolor) ? '' : '#').$object->bgcolor;
	        // if (!empty($object->bgimage)) $arr_css['bgimage'] = $object->bgimage;

	        $trackid = 'emailingtest';
	        $addr_bcc = '';
	        $addr_cc = '';
			$mailfile = new CMailFile($tmpsujet, $object->sendto, $object->email_from, $tmpbody, $arr_file, $arr_mime, $arr_name, $addr_cc, $addr_bcc, 0, $msgishtml, $object->email_errorsto, $arr_css, $trackid, '', 'emailing');

			$result = $mailfile->sendfile();

			if ($result)
			{
				// $date=date('Y-m-d');
	   //      	$data =  array('date_notification'=> $date);
	   //      	$isvalid = $this->update($object->rowid, $data);
				setEventMessages($langs->trans("MailSuccessfulySent", $mailfile->getValidAddress($object->email_from, 2), $mailfile->getValidAddress($object->sendto, 2)), null, 'mesgs');
				return 'MailSuccessfulySent';
			}
			else
			{
				setEventMessages($langs->trans("ResultKo").'<br>'.$mailfile->error.' '.$result, null, 'errors');
				return 'ResultKo';
			}
		}else{

			return 'AddEmailToYourSociete';

		}
    }
    
	public function calculateDatesBetween($start, $end)
	{
		$date1 = date_create($start);
		$date2 = date_create($end);
		$diff = date_diff($date1,$date2);
		
		$diffday = $diff->format("%R%a");
		return $diffday;
    }


	public function upgradeModuleDocsEmpl()
    {
        global $conf, $langs;

        dol_include_once('/sghr/employeedocs/core/modules/moddocsemployes.class.php');
        $modcore = new moddocsemployes($this->db);
        
        $lastversion    = $modcore->version;
        $currentversion = dolibarr_get_const($this->db, 'DOCSEMPL_LAST_VERSION_OF_MODULE', $conf->entity);
        if (!$currentversion || ($currentversion && $lastversion != $currentversion)){
            $res = $this->InitDocsEmpl();
            if($res)
                dolibarr_set_const($this->db, 'DOCSEMPL_LAST_VERSION_OF_MODULE', $lastversion, 'chaine', 0, '', $conf->entity);
            return 1;
        }
        return 0;
    }



	public function InitDocsEmpl()
	{
		global $conf;

		if (!dolibarr_get_const($this->db,'DOCSEMPLOYES_CHECKDOCSFORMAIL',$conf->entity))
			dolibarr_set_const($this->db,'DOCSEMPLOYES_CHECKDOCSFORMAIL',0,'chaine',0,'',$conf->entity);
		if (!dolibarr_get_const($this->db,'DOCSEMPLOYES_NUMBEROFDAYSBEFORETOSENDMAIL',$conf->entity))
			dolibarr_set_const($this->db,'DOCSEMPLOYES_NUMBEROFDAYSBEFORETOSENDMAIL',0,'chaine',0,'',$conf->entity);

		dol_include_once('/sghr/employeedocs/core/modules/moddocsemployes.class.php');
        $modcore = new moddocsemployes($this->db);

		$modcore->cronjobs[0]['entity']  	    = $conf->entity;
        $modcore->cronjobs[0]['label']  	    = 'docsemployescheckMails';
        $modcore->cronjobs[0]['jobtype']  	    = 'method';
        $modcore->cronjobs[0]['class']  	    = 'employeedocs/class/docsemployes.class.php';
        $modcore->cronjobs[0]['objectname']    = 'docsemployes';
        $modcore->cronjobs[0]['method'] 	    = 'checkDocsMails';
        $modcore->cronjobs[0]['frequency']     = 1;
        $modcore->cronjobs[0]['unitfrequency'] = 86400;
        $modcore->cronjobs[0]['priority'] 	    = 10;
        $modcore->cronjobs[0]['datestart']     = date('Y-m-d H:i:s');
        $modcore->cronjobs[0]['status'] 		= 1;
        $modcore->cronjobs[0]['test'] 			= '$conf->global->DOCSEMPLOYES_SEND_EMAIL';
        $modcore->cronjobs[0]['note'] 			= 'docsemployescheckDocsMails';


        $modcore->insert_cronjobs();

        $sql2 = "UPDATE " . MAIN_DB_PREFIX. "cronjob SET status = 1 WHERE module_name = 'docsemployes' AND classesname = 'employeedocs/class/docsemployes.class.php' AND objectname = 'docsemployes' AND methodename = 'checkDocsMails'";
        $resql = $this->db->query($sql2);

		
		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."docsemployes` (
		    `rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		    `number` varchar(50) DEFAULT NULL,
		    `issuedate` date DEFAULT NULL,
		    `expirydate` date DEFAULT NULL,
		    `date_notification` date DEFAULT NULL,
		    `file` varchar(100) DEFAULT NULL,
		    `fk_user` int(11) DEFAULT NULL,
		    `fk_type_document` int(11) DEFAULT NULL,
		    `sendmail` boolean DEFAULT NULL,
		    `description` text DEFAULT NULL,
		    `destinataire` text DEFAULT NULL, 
	  		`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);
		
		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."docsemployes` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);
		
		
		$sql = "CREATE TABLE IF NOT EXISTS `".MAIN_DB_PREFIX."type_document` (
			`rowid` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`name` varchar(50) DEFAULT NULL,
	  		`entity` int(11) NOT NULL DEFAULT ".$conf->entity."
		);";
		$resql = $this->db->query($sql);

		$resql = $this->db->query("ALTER TABLE `".MAIN_DB_PREFIX."type_document` ADD `entity` int(11) NOT NULL DEFAULT ".$conf->entity);

		$sql = "ALTER table `".MAIN_DB_PREFIX."docsemployes` add sendmail boolean NULL DEFAULT 0";
		$resql = $this->db->query($sql);
		
		$sql = "ALTER table `".MAIN_DB_PREFIX."docsemployes` add destinataire text NULL DEFAULT NULL";
		$resql = $this->db->query($sql);
		
		return 1;
	}
    
} 
?>