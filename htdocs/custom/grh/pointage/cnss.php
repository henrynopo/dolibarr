<?php

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
dol_include_once('/grh/pointage/class/pointage.class.php');
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
dol_include_once('/grh/salaire_user/class/salaire_user.class.php');
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');

$extrafields = new ExtraFields($db);
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
$radio2      		= GETPOST('radio2');	

$periodyear=GETPOST('periodyear','int');

 // Both test are required to be compatible with all browsers
if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
		$periodyear    = "";
		$radio2      = '';
}
if (!$periodyear)
	$periodyear=date('Y');
if (!$mid)
	$mid=date('m');
if (!$radio2)
	$radio2='both';

if ( !empty($periodyear) && $action == "xsl") {
	

$filename="etat_cnss_".$periodyear."_".$mid.".xls";
      require_once dol_buildpath('/grh/pointage/tpl/cnss_xsl.php');
 die();
}


llxHeader(array(), $langs->trans('etatcnss'),'','','','',array('/grh/js/etat_stock.js'));


	print_fiche_titre($langs->trans("etatcnss").' '.$periodyear, '', 'title_project');
	print '<div style="float: left; margin: -8px;">';

	print '<form style="background-color: transparent" method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	print $formother->selectyear($periodyear,'periodyear');
	print ' &nbsp;&nbsp;&nbsp;';	

$checkedb ='';
$checkedd ='';
$checkedn ='';
if (isset($radio2) ) {
    			if($radio2=='paie' )
    				$checkedd ='checked="checked"';
    			elseif($radio2=='nopaie')
    				$checkedn ='checked="checked"';
    			else
					$checkedb ='checked="checked"';
    	}
print '<label style="margin: 0 7px;"><input type="radio" '.$checkedb.' name="radio2" value="both"> '.$langs->trans("All").' </label><label style="margin: 0 7px;"><input type="radio" '.$checkedd.' name="radio2" value="paie"> '.$langs->trans("Salariés_avec_paie").' </label>
 <label style="margin: 0 7px;"><input type="radio" '.$checkedn.' name="radio2" value="nopaie"> '.$langs->trans("Salariés_sans_paie").' </label>';
	print ' &nbsp;&nbsp;&nbsp;';
 print '<input type="hidden" name="mid" value="'.$mid.'">';
 print '<input type="submit" name="submit" class="butAction" value="'.$langs->trans('Validate').'">';
    print '</form>'."\n";
    print '</div>';

    print '<div style="float: right; margin: -8px;">';
	  print '<a href="./cnss.php?action=xsl&periodyear='.$periodyear.'&mid='.$mid.'&radio2='.$radio2.'" class="butAction">'.$langs->trans('Generate_EXCEL').'</a>';
	  print '</div><br/><br/>';

	if (!empty($periodyear)) {

	/*$all_users['salary'] = $pointage->getUsersCNSS();
 	$all_users['nosalary'] = $pointage->getUsersWithS(false);*/
 	$all_users = $pointage->getUsersCNSS();
 	if($salaire_user->Check_exist_SU($mid,$periodyear))
 	$all_users = $salaire_user->getUsersCNSS($periodyear,$mid);
	

	$h 	  = 0;
	$first = 1 ;
	$head = array();
	foreach($month_names as $m=>$val) 
	{
		if($h == 0)
			$first = $m;
		$head[$h][0] = dol_buildpath('/grh/pointage/cnss.php?mid='.$m.'&periodyear='.$periodyear,1);
    $head[$h][1] = $month_names[$m];
    $head[$h][2] = 'month_'.$m;

    $h++;
	}
	if($mid)
		$first =($mid+0);
	// echo "first : ".$first;
    complete_head_from_modules($conf,$langs,$object,$head,$h,'etatcnss');

	complete_head_from_modules($conf,$langs,$object,$head,$h,'etatcnss','remove');

	dol_fiche_head($head, 'month_'.$first, $langs->trans("etatcnss").' '.$periodyear, 1, '');
	
	// print '<br>';
	
	// Lines
	$total_salary = 0 ;
	$total_day = 0 ;
	print '<table class="border" style="width:100%">';
	print "<tr class=\"liste_titre\" >";
	print '<th style="text-align: center" >'.$langs->trans("Names").'</th>';
	print '<th style="text-align: center"> '.$langs->trans("Nombre_de_jours").' </th>';
	print '<th style="text-align: center"> '.$langs->trans("Salaire").' ('.$langs->getCurrencySymbol($conf->currency).') </th>';
	print '<th style="text-align: center"> '.$langs->trans("CIN").' </th>';
	print '<th style="text-align: center"> '.$langs->trans("CNSS").' </th>';
	print '</tr>';
	$extrafields->fetch_name_optionals_label('user');
	//foreach($all_users as $index=>$data) {
   foreach($all_users as $key=>$value) {
    	$userp->fetch($key);
    	$user_arr = $pointage->nc_getUserInfo($key);
    	/*if (isset($radio2) && !empty($radio2)) {
    			if($radio2=='paie' && !$user_arr->salary && $index=='salary' )
    				continue;
    			elseif($radio2=='nopaie'&& $user_arr->salary && $index=='salary' )
    				continue;
    	}
    	if (isset($radio2) && !empty($radio2)) {
    			if($radio2=='paie' && !$user_arr->thm && $index=='nosalary' )
    				continue;
    			elseif($radio2=='nopaie'&& $user_arr->thm && $index=='nosalary' )
    				continue;
    	}*/
    	if (isset($radio2) && !empty($radio2) && $radio2=='paie') 
    			if(  !$userp->thm && !$userp->salary )
    				continue;
    	if (isset($radio2) && !empty($radio2) && $radio2=='nopaie') 
    			if(  $userp->thm || $userp->salary )
    				continue;

    	if(!$userp->array_options['options_nx_is_declared'])
    		continue;
    	
    	print '<tr>';
    	print '<td align="left">'.$userp->getNomUrl(1).'</td>';
    	$nb_holiday = $userp->array_options['options_nx_num_holiday'];
    	// $nb_holiday = $user_arr->nb_holiday;
 		// $nb_holiday = $extrafields->showOutputField('nb_holiday',$nb_holiday);
 		$total_day += intval($nb_holiday);
    	print '<td align="center">'.$nb_holiday.'</td>';
    	$salary = 0;
    	if($salaire_user->getSalary($mid,$periodyear,$key))
    		$salary = $salaire_user->getSalary($mid,$periodyear,$key);
    	elseif($salaire_user->getThm($mid,$periodyear,$key)){
    		$nbr = $pointage->getValByMonth($periodyear,$mid,$key);
    		$salary = $nbr * $salaire_user->getThm($mid,$periodyear,$key);
    	}
    	$total_salary += $salary;
    	print '<td align="center">'.number_format($salary,2,',',' ').'</td>';
    	$cin = $userp->array_options['options_nx_cin'];
    	// $cin = $user_arr->cin;
 		// $cin = $extrafields->showOutputField('cin',$cin);
    	print '<td align="center">'.$cin.'</td>';
		$cnss = $userp->array_options['options_nx_cnss'];
		// $cnss = $user_arr->immatriculation;
 		// $cnss = $extrafields->showOutputField('immatriculation',$cnss);
		
    	print '<td align="center">'.$cnss.'</td>';
    	print '</tr>';
    	}
	//}
		print '<tr>';
	print '<td align="center"><strong>Total</strong></td>';
	print '<td align="center"><strong>'.$total_day.'</strong></td>';
	print '<td align="center"><strong>'.number_format($total_salary,2,',',' ').'</strong></td>';
	print '<td align="center" colspan="2"></td>';
	print '</tr>';
	 print '</table>';
	 print '<hr>';
	 ?>
    <script type="text/javascript">
        $("#periodyear").change(function(){
            $("#year_change").val(this.value);
        }).trigger('change');
    </script>
    <?php

	
}
llxFooter();



?>