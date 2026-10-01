<?php

if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);

	$res=0;
	if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
	if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 

	require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

	dol_include_once('/sghr/recrutement/class/etapescandidature.class.php');
	dol_include_once('/sghr/recrutement/class/candidatures.class.php');
	dol_include_once('/sghr/recrutement/lib/recrutement.lib.php');
	dol_include_once('/sghr/recrutement/class/postes.class.php');
	dol_include_once('/core/class/html.form.class.php');

	// dol_include_once('/contact/class/contact.class.php');

	$poste = new postes($db);
	$contact = new Contact($db);
	$candidature = new candidatures($db);
	$etape = new etapescandidature($db);
	$user_ = new User($db);
	$id=GETPOST('id');
	$id_=GETPOST('id_');
	$action_=GETPOST('action_');
	if($action_ == 'cree_employe'){
		$candidature->fetch($id_);
		$poste->fetch($candidature->poste);
		$name=explode(' ', $candidature->nom);
		$user_->login=$name[0];
		$user_->lastname=$name[0];
		$user_->firstname=$name[1];
		$user_->email=$candidature->email;
		$user_->job=$poste->label;
		$user_->office_phone=$candidature->tel;
		$user_->user_mobile=$candidature->mobile;
		$user_->fk_user=$candidature->responsable;

		if($candidature->etape == 5 && !empty($user->rights->user->user->creer) ){
			$employe= $user_->create($user);
			if(!empty($employe) && $employe > 0){
				$data=['employe'=>$employe];
				$candidature->update($id_,$data);
				$msg = 'L\'employé a été créé avec succès';
				$type_msg = 'warnings';
		    	$data = 'ok';
			}
			else{
		    	$msg='Erreur';
		    	$type_msg='errors';
		    	$data='Erreur';
			}
		}
		if($data=''){
		    $type_msg='errors';
			$msg='Les étapes de candidature incomplètes';
		}
	    setEventMessages($msg, null, $type_msg);
		echo $data;

	}

	if($action_ == 'refuse'){
		// $candidature->fetch($id_);
		$resu = $candidature->update($id_,['refuse'=>1]);
		if($resu > 0)
			$data='Ok';
		echo $data;
	}

	if($action_ == 'relance'){
		$candidature->fetch($id_);
		$candidature->update($id_,['refuse'=>0]);
	}
	if($action_ == 'arreter'){
		$poste_id=GETPOST('poste');
		$poste->fetch($poste_id);
		$error=$poste->update($poste_id,['status'=>'Recrutementarrete']);
		if($error == 1){
	    	setEventMessages('Le recrutement est arrêté pour ce poste', null);
	    	$data='ok';
		}
		else{
	    	setEventMessages('Erreur', null, 'errors');
	    	$data='Erreur';
		}
		$data='Ok';
		echo $data;
	}
	
	if($action_ == 'finaliser'){
		// print_r($_SERVER);
		// die();
		$poste_id=GETPOST('poste');
		$data='';
		$poste->fetch($poste_id);
		$candidature->fetchAll('','',0,0,' AND poste = '.$poste_id.' AND etape = 5');
		$nb=count($candidature->rows);
		if($nb>0){
			$poste->update($poste_id,['status'=>'Recrutementfinalise']);
			$data='Ok';
	    	setEventMessages('Le poste est finalisé', null, 'mesgs');
		}
		else{
			setEventMessages('Aucun candidat signé une contrat', null, 'warnings');
		}
	   
		echo $data;
	}

	if($action_ == 'lancer'){
		$poste_id=GETPOST('poste');
		$poste->fetch($poste_id);
		$error=$poste->update($poste_id,['status'=>'Recrutementencours']);
		if($error == 1){
	    	setEventMessages('Le recrutement est relancé pour ce poste', null, 'mesgs');
	    	$data='ok';
		}
		else{
	    	setEventMessages('Erreur', null, 'errors');
	    	$data='Erreur';
		}
		$data='Ok';
		echo $data;
	}


	if($action_ == 'get_contact'){
		$contact->fetch($id);
		$item=$contact;
		// print_r($item);
		// die();
		$data=['email'=>$contact->mail,'tel'=>$item->phone_pro,'mobil'=> $item->phone_mobile];
		// print_r($data);
	 	echo json_encode($data);
	}
	//fiche_employe
	$id_candidature = GETPOST('id_candidature');
	if($action_ == 'delete_user'){
		$candidature->fetch($id_candidature);
		$employe = new User($db);
		$employe->fetch($candidature->employe);
		$result = -1;
		if($user->admin || !empty($user->rights->user->user->supprimer))
			$result = $employe->delete();
		if($result < 0){
			setEventMessages('Erreur de suppression', null, 'errors');
			$msg='';
		}else{
			$candidature->update($id_candidature,['employe'=>0]);
			$msg='Ok';
		}
		echo $msg;
	}
	if($action_ == 'change_etat'){
		$id_candidat=GETPOST('id_candidat');
		$id_etat=GETPOST('id_etat');
		$data = '';
		$candidature->fetch($id_candidat);
		$result=$candidature->update($id_candidat,['etape'=>$id_etat]);
		if($result>0){
			$etape->fetch($id_etat);
			$data=['color'=>$etape->color,'id_candidat'=>$id_candidat];
		}
	 	echo json_encode($data);
	}

?>