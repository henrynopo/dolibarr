<?php

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
dol_include_once('/grh/pointage/class/pointage.class.php');
dol_include_once('/grh/salaire_user/class/salaire_user.class.php');
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');

$pointage= new pointage($db);
$salaire_user= new salaire_user($db);
$var = false;
$form 			  = new Form($db);
$userp 			  = new User($db);
$formother        = new FormOther($db);
$month_names = array(
                 1 => $langs->trans("Month01"),
                 2 => $langs->trans("Month02"),
                 3 => $langs->trans("Month03"),
                 4 => $langs->trans("Month04"),
                 5 => $langs->trans("Month05"),
                 6 => $langs->trans("Month06"),
                 7 => $langs->trans("Month07"),
                 8 => $langs->trans("Month08"),
                 9 => $langs->trans("Month09"),
                10 => $langs->trans("Month10"),
                11 => $langs->trans("Month11"),
                12 => $langs->trans("Month12")
            );
// Protection if external user
if (!$user) accessforbidden();

$langs->load('users');

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$tasktab   = GETPOST("tasktab",'int');
$page = GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$filter = '';
$id_markets = array();
if (! $sortfield) $sortfield = "rowid";
if (! $sortorder) $sortorder = "DESC";

$offset   = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
$action   = GETPOST('action','alpha');
$mid 	  = GETPOST('mid','int');
$id 	  = GETPOST('id','int');

$periodyear=GETPOST('periodyear','int');

 // Both test are required to be compatible with all browsers
if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
		$periodyear    = "";
}
if (!$periodyear)
	$periodyear=date('Y');

if (!$mid)
	$mid=date('m');

if ( $action == "update") {
	 $all_users['salary'] = $pointage->getUsersWithS();
	 $all_users['nosalary'] = $pointage->getUsersWithS(false);
	 foreach($all_users as $index=>$data) {
    foreach($data as $key=>$value) {
    	$userp->fetch($key);
    	$salary = 0;
    	$salary_base = 0;
    	$thm = 0;
    	if($userp->salary_base)
    		$salary_base= $userp->salary_base;
    	if($userp->salary)
    		$salary= $userp->salary;
    	// elseif($userp->thm)
    	// 	$thm = $userp->thm;
        if($userp->thm)
            $thm = $userp->thm;
    	$current_id = $salaire_user->getID($mid,$periodyear,$key);
    	if($current_id){
    		 $data = array(
            'salary'            => $salary,
            'salary_base'            => $salary_base,
            'thm'            => $thm
        );
        $salaire_user->update($current_id, $data);
    	}else{
    	$salaire_user->year_point=$periodyear;
        $salaire_user->created_by=$user->id;
        $salaire_user->month_point=$mid;
        $salaire_user->fk_user=$key;
        $salaire_user->salary=$salary;
        $salaire_user->salary_base=$salary_base;
        $salaire_user->thm=$thm;
		$salaire_user->create();}
	}
}
header('Location: index.php?mid='.$mid.'&periodyear='.$periodyear);	
exit;
}

if ( $action == "generate") {
	 $all_users['salary'] = $pointage->getUsersWithS();
	 $all_users['nosalary'] = $pointage->getUsersWithS(false);
	 foreach($all_users as $index=>$data) {
    foreach($data as $key=>$value) {
    	$userp->fetch($key);
    	$salary = 0;
    	$salary_base = 0;
    	$thm = 0;
    	if($userp->salary_base)
    		$salary_base= $userp->salary_base;
    	if($userp->salary)
    		$salary= $userp->salary;
    	// elseif($userp->thm)
    	// 	$thm = $userp->thm;
        if($userp->thm)
            $thm = $userp->thm;
    	$salaire_user->year_point=$periodyear;
        $salaire_user->created_by=$user->id;
        $salaire_user->month_point=$mid;
        $salaire_user->fk_user=$key;
        $salaire_user->salary=$salary;
        $salaire_user->salary_base=$salary_base;
        $salaire_user->thm=$thm;
	$salaire_user->create();
	}
}
header('Location: index.php?mid='.$mid.'&periodyear='.$periodyear);	
exit;
}

if ( !empty($periodyear) && $action == "xsl") {
	

$filename="gestion_stock_".$periodyear.".xls";
      require_once dol_buildpath('/grh/stock_initial/tpl/gestion_stock_xsl.php');
 die();
}


llxHeader(array(), $langs->trans('gstsu'),'','','','',array('/grh/js/etat_stock.js'));


	print_fiche_titre($langs->trans("gstsu").' '.$periodyear, '', 'title_project');
	if($salaire_user->Check_exist_SU($mid,$periodyear) ){
	print '<div style="float: left; margin: -8px;">';

	print '<form style="background-color: transparent" method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	print 'L\'année: '.$formother->selectyear($periodyear,'periodyear');
	print ' &nbsp;&nbsp;&nbsp;';	
	  print '<input type="submit" name="submit" class="butAction value="afficher">';
    print '</form>'."\n";
    print '</div>';

    print '<div style="float: right; margin: -8px;">';
	  print '<form method="post" action="card.php">'."\n";
 print '<input  type="hidden" name="action" value="add">';
 print '<input type="hidden" name="periodyear" value="'.$periodyear.'">';
 print '<input type="hidden" name="mid" value="'.$mid.'">';
 print '<input type="submit" name="submit" class="butAction value="Ajouter Salaire/Thm">';

 print '</form>'."\n";
	  print '</div><br/><br/>';}
if($mid == date('m') && $periodyear==date('Y') ){
	print '<div style="float: right; margin: -8px;">';

	print '<form style="background-color: transparent" method="post" action="'.$_SERVER["PHP_SELF"].'">'."\n";
 print '<input  type="hidden" name="action" value="update">';
 print '<input type="hidden" name="periodyear" value="'.$periodyear.'">';
 print '<input type="hidden" name="mid" value="'.$mid.'">';
 print '<input type="submit" name="submit" class="butAction value="Mise à jour">';

 print '</form>'."\n";
	  print '</div>';}

	

	$h 	  = 0;
	$first = 1 ;
	$head = array();
	foreach($month_names as $m=>$val) 
	{
		if($h == 0)
			$first = $m;
		$head[$h][0] = dol_buildpath('/grh/salaire_user/index.php?mid='.$m.'&periodyear='.$periodyear,1);
    $head[$h][1] = $month_names[$m];
    $head[$h][2] = 'month_'.$m;

    $h++;
	}
	if($mid)
		$first =$mid;

    complete_head_from_modules($conf,$langs,$object,$head,$h,'gstsu');

	complete_head_from_modules($conf,$langs,$object,$head,$h,'gstsu','remove');

	dol_fiche_head($head, 'month_'.$first, $langs->trans("gstsu").' '.$periodyear, 0, '');

	
	


	if(!$salaire_user->Check_exist_SU($mid,$periodyear) ){
	 print '<br/><br/><div align="center">';
	  print '<a href="./index.php?action=generate&periodyear='.$periodyear.'&mid='.$mid.'" class="butAction">Générer la table</a>';
	  print '</div>';
	}else{

		$filter="and year_point=".$periodyear;
     	$filter.=" and month_point=".$mid;
		$salaire_user->fetchAll('','rowid',0,0,$filter);
    	
	print '<table class="border" style="border-collapse: unset;width:100%">';
	print "<tr class=\"liste_titre\" >";
	 print '<th style="text-align: center" >Noms</th>';
	print '<th style="text-align: center"> Salaire ('.$langs->getCurrencySymbol($conf->currency).') </th>';
	print '<th style="text-align: center"> Salaire de base ('.$langs->getCurrencySymbol($conf->currency).') </th>';
	print '<th style="text-align: center"> THM ('.$langs->getCurrencySymbol($conf->currency).')</th>';
	print '<th style="text-align: center"> Modifier </th>';
	print '</tr>';
	foreach($salaire_user->rows as $line) {
    	print '<tr>';
    	$userp->fetch($line->fk_user);
    	print '<td align="left">'.$userp->getNomUrl(1).'</td>';
    	$salary = 0;
    	if($line->salary!=0)
    		print '<td align="center">'.number_format($line->salary,2,',',' ').'</td>';
    	else
    		print '<td align="center"></td>';
    	
    	if($line->salary_base)
    		print '<td align="center">'.number_format($line->salary_base,2,',',' ').'</td>';
    	else
    		print '<td align="center"></td>';
    	
    	if($line->thm!=0)
    		print '<td align="center">'.number_format($line->thm,2,',',' ').'</td>';
    	else
    		print '<td align="center"></td>';
    	
    	
    	print '<td align="center">';
    	print '<a href="./card.php?action=edit&id='. $line->rowid .'" ><img src="'.img_picto($langs->trans("Modifier"),'edit.png','','',1).'" /></a>';
    	print ' &nbsp;&nbsp;&nbsp;';
    	print '<a href="./index.php?action=delete&id='. $line->rowid  .'" ><img src="'.img_picto($langs->trans("delete"),'delete.png','','',1).'" /></a>';
    	print'</td>';
    	print '<tr>';
    	}
	
	 print '</table>';
	}
	if ($action == 'delete') {
            print $form->formconfirm("card.php?id=".$id, $langs->trans("TitleDelete"),$langs->trans("ConfirmDelete"),"confirm_delete", '', 0, 1);
    }

llxFooter();



?>