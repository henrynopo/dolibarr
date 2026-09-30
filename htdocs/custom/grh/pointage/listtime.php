<?php
/* Copyright (C) 2005		Rodolphe Quiedeville 	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2013	Laurent Destailleur  	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010	Regis Houssin        	<regis.houssin@capnetworks.com>
 * Copyright (C) 2010     	François Legastelois 	<flegastelois@teclib.com>
 * Copyright (C) 2014-2015	charlie BENKE			<charlie@patas-monkey.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       /rh/pointage/listtime.php
 *	\ingroup    pointage
 *	\brief      List  
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

//require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/grh/pointage/class/pointage.class.php');
// dol_include_once('/grh/management/core/lib/management.lib.php');
dol_include_once('/grh/salaire_user/class/salaire_user.class.php');
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');

$action=GETPOST('action');
$mode=GETPOST("mode");
$id=GETPOST('id','int');
$radio      		= GETPOST('radio');
$users      		= GETPOST('users');
$periodyear=GETPOST('periodyear','int');
$periodmonth = (GETPOST('periodmonth','int')) ? GETPOST('periodmonth','int') : (date('m') + 0);
// echo "<br><br>".$periodmonth;
// echo "<br><br>".(date('m') + 0);
if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
	$users     = "";
	$radio      = '';
	$periodyear     = "";
	$periodmonth     = (date('m') + 0);
}



if(GETPOST('removedata') && GETPOST('removedata') == 'true'){
	$sql2 = "DELETE FROM `".MAIN_DB_PREFIX."salaire_user` WHERE 1>0;";
	$resql2 = $db->query($sql2);
	$sql2 = "DELETE FROM `".MAIN_DB_PREFIX."l_pointage` WHERE 1>0;";
	$resql2 = $db->query($sql2);			
}


// $objdocs  = new pointage($db);
// $modtxt = 'grh';
// global $dolibarr_main_data_root;
// if (!dolibarr_get_const($db, strtoupper($modtxt).'_CHANGEPATHDOCS',0)){
// 	$source = dol_buildpath('/uploads/'.$modtxt);
// 	if(@is_dir($source)){
// 		$docdir = $dolibarr_main_data_root.'/'.$modtxt;
// 		$dmkdir = dol_mkdir($docdir, '', 0755);
// 		if($dmkdir >= 0){
// 			@chmod($docdir, 0775);
// 			$dcopy = dolCopyDir($source, $docdir, 0775, 1);
// 			// if($dcopy >= 0){
// 				dolibarr_set_const($db, strtoupper($modtxt).'_CHANGEPATHDOCS',1,'chaine',0,'',0);
// 				$objdocs->grhpermissionto($docdir);
// 			// }
// 		}
// 	}
// }



if (!$user->rights->grh->lire) {
	accessforbidden();
}


$object  = new pointage($db);
$object->upgradeModuleGRH();


if (!$periodyear)
	$periodyear=date('Y');

if (!$radio)
	$radio='both';

if (!$periodmonth)
	$periodmonth=date('m');

$perioduser=GETPOST('perioduser','int');
if (!$perioduser)
	$perioduser=$user->id;
	
// récupération du nombre de jour dans le mois
$time = mktime(0, 0, 0, $periodmonth+1, 1, $periodyear); // premier jour du mois suivant
$time--; // Recule d'une seconde
$nbdaymonth=date('d', $time); // on récupère le dernier jour

/*$projectid='';
$projectid=isset($_GET["id"])?$_GET["id"]:$_POST["projectid"];
*/
// Security check
$socid=0;
if ($user->societe_id > 0) $socid=$user->societe_id;
// $result = restrictedArea($user, 'projet', $projectid);

$form=new Form($db);
$formother = new FormOther($db);
$pointage= new pointage($db);
$user_array=new User($db);
$salaire_user= new salaire_user($db);
//$project = new Project($db);


/*
 * Actions
 */

if ($action == 'addtime')
{
	// on boucle sur les lignes de taches 
	$result=setsheetLines($j, 0, $user_array, $level, $projectsrole, $tasksrole, $perioduser);
	if($result)
	setEventMessage($langs->trans("RecordSaved"));
	
	$action ="";
	// Redirect to avoid submit twice on back
	//header('Location: '.$_SERVER["PHP_SELF"].'?id='.$projectid.($mode?'&mode='.$mode:''));
	//exit;
}

/*
 * View
 */


$title=$langs->trans("TimeSpentPeriod");
$morejs  = array("/grh/js/pointage.js");
llxHeader("",$title,'','','','',$morejs);
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num);
dol_htmloutput_mesg($mesg);

// 
?>
<style type="text/css">
	.weekendtoshow{
		display: table-cell !important;
	    padding: 1px !important;
	}
	td.salary_user * {
	    color: #E7505A;
	}
	td.non_salary_user * {
	    color: #3598DC;
	}
	.ScrollOnlyMe table td input[type=text]{
	    padding: 4px 0px
	}
	.periodeaaaamm{max-width: 190px; width: 190px;}
	.select_period{width:145px;}
	.declare_no{width: 290px;text-align: left;}
	td.declare_no label { padding: 0 8px; }
	#id-container .side-nav {
	    float: left !important;
	}
	#id-container {
	    display: block !important;
	}
	#id-right {
	    display: block !important;
	    opacity : 0;
	    float: left !important;
	}
</style>

<?php
print '<div style="width: 100%; margin: 5px 0 13px; text-align: right;">';
print '<a href="./addpointage.php" class="butAction">'.$langs->trans('consulte').'</a></div>';
print '<br>';
print '<form name="selectperiod" method="POST" action="'.$_SERVER["PHP_SELF"].'" style="    background: #efefef;padding:4px;">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="selectperiod">';

print '<table width="100%">';
print '<tr >';
print '<td class="periodeaaaamm">'.$langs->trans("PeriodAAAAMM").'</td>';
print '<td class="select_period">'.$formother->selectyear($periodyear,'periodyear').$formother->select_month($periodmonth,'periodmonth',0,0,'maxwidth70imp').'</td>';
print '<td class="declare_no">';
	$checkedb ='';
	$checkedd ='';
	$checkedn ='';
	if (isset($radio) ) {
		if($radio=='declar' )
		$checkedd ='checked="checked"';
		elseif($radio=='nodeclar')
		$checkedn ='checked="checked"';
		else
		$checkedb ='checked="checked"';
	}
	print '<label><input type="radio" '.$checkedb.' name="radio" value="both">'.$langs->trans("All").'</label><label><input type="radio" '.$checkedd.' name="radio" value="declar">'.$langs->trans("Déclarés").'</label>
<label><input type="radio" '.$checkedn.' name="radio" value="nodeclar">'.$langs->trans("Non_Declarés").'</label>';
print '</td>';
print '<td style="min-width:66px;"><input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'"><input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
print '</td>';
print '<td align="right">';
print '<input style="float:right;background: #5999A7;color: #fff;text-shadow:none;font-size: 12px;" class="butAction" type="button" id="lblBlurWeekEnd" name="button" value="'.$langs->trans("ShowHideWeekend").'">';
print '<input style="background: #52919e;color: #fff;text-shadow:none;font-size: 12px;" class="butAction" type="button" id="emptyAll" name="button" value="'.$langs->trans("Vider_tous_les_champs").'">';
print '</td>';
print "</tr>\n";

print "</table>";

print '<table style="">';
print '<tr >';
print '<td style="max-width: 190px; width: 190px;">'.$langs->trans("UserToDisplay").'</td>';
$showempty=0;
// attention le dernier paramétre n'est dispo que sur la 3.7 et le patch fournis
print '<td style="width: 85%;">';
$users_array = $pointage->getUsers();
print $form->multiselectarray('users', $users_array, $users, null, null, null,null, "100%");
//print $form->select_users($perioduser,"perioduser",0);
print '</td>';
print '</tr>';
print "</table>";
print '<table style="margin-bottom: 7px;">';
print '<tr >';
print "</tr>\n";
print '</table>';
print '</form>';

// print '<div style="width: 100%;"">Après términer la modification cliquer sur Valider</div>';

print '<form name="addtime" method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="addtime">';
print '<input type="hidden" name="mode" value="'.$mode.'">';

print '<input type="hidden" name="periodyear" value="'.$periodyear.'">';
print '<input type="hidden" name="periodmonth" value="'.$periodmonth.'">';
print '<input type="hidden" name="perioduser" value="'.$perioduser.'">';
print '<input type="hidden" name="radio" value="'.$radio.'">';
print '<input type="hidden" name="users" value="'.$users.'">';
//print '<input type="hidden" name="periodproject" value="'.$periodproject.'">';
// ---------------------------------------B------------------------------------------------------

// $HowMuchDayInMonthSelected = cal_days_in_month(CAL_GREGORIAN, $periodmonth, $periodyear);
$HowMuchDayInMonthSelected = days_in_month($periodmonth, $periodyear);
$colspanNumber=$HowMuchDayInMonthSelected + 1;

print '<div class="noborderrr">';
print '<div style="max-height:95px;width:100%;">';
print '<div class="guide_salariegrh">
		<div class="salarie_">
			<span class="bg_blue"></span> 
			'.$langs->trans("Salariés").' ('.$langs->trans("Salaire").' > '.price(0,0,$langs,1,-1,-1,$conf->currency).')
		</div>
		<div class="non_salarie_">
			<span class="bg_red"></span> 
			'.$langs->trans("Non_Salariés").'
		</div>
	  </div>';
print '<table id="table-1" class="noborder" style="min-width:100%;width:auto;margin-bottom:0;">';
print "<thead>";
// -------------------------------------E--------------------------------------------------------
print '<tr class="liste_titre">';
print '<td style="line-height: 28px;">'.$langs->trans("Names").'</td>';
// --------------------------------------B-------------------------------------------------------
print '<td  style="line-height: 28px;"colspan="40" align="right">'.$langs->trans("TimeSpent").'&nbsp;<input style="float: right;" type="submit" name="save" value="'.$langs->trans("Validate").'"></td>';
print "</tr>";

// --------------------------------------E-------------------------------------------------------

timesheetLines($j, 0, $user_array, $level, $projectsrole, $tasksrole, $mine, $perioduser);

// ---------------------------------------B------------------------------------------------------
?>
<!--////////////////////////////////////////////////////////////////////////////////////////////////-->

<style>
*{padding: 0;margin: 0;}
.guide_salariegrh{
	/*font-family: roboto,Open Sans !important;*/
	font-size: 12px;
}
.guide_salariegrh>div{
	float: left;
	padding: 10px;
}
.guide_salariegrh span{
	width: 11px;
    height: 11px;
    border-radius: 50%;
    display: inline-block;
}
.guide_salariegrh .non_salarie_ span{
	background: #3598DC;
}
.guide_salariegrh .salarie_ span{
	background: #E7505A;
}
.guide_salariegrh .salarie_ {
	margin-right: 20px;
}
table#header-fixed{margin-bottom: 0;}
.firsttrtab1 td{color: #fff;font-size: 10px;}
.firsttrtab1{background-color: #808080 !important;background: #808080 !important;height: 30px;}
.firsttrtab1 .right-arrow{text-align: center;display: inline-block;
    position: relative;
    background: #f07b6e;
    padding: 0 3px 0 3px;}
.firsttrtab1 .right-arrow:after{content: '';display: block;position: absolute;left: 100%;top: 50%;margin-top: -10px;width: 0;height: 0;
	border-top: 10px solid transparent;border-right: 10px solid transparent;border-bottom: 10px solid transparent;
	border-left: 10px solid #f07b6e;}
.icon-plus-filter.search_icons_container{margin-right: 4px;}
</style>

<script>

	// $('.ScrollOnlyMe').on( 'mousewheel DOMMouseScroll', function (e) { 
  
	//   var e0 = e.originalEvent;
	//   var delta = e0.wheelDelta || -e0.detail;

	//   this.scrollTop += ( delta < 0 ? 1 : -1 ) * 50;
	//   e.preventDefault();  
	// });
</script>
<!--////////////////////////////////////////////////////////////////////////////////////////////////-->
<?php

print "</tbody>";
print "</table>";
print "</div>";
print "</div>";
print '</form>';

// ----------------------------------------E-----------------------------------------------------

// pour activer ou non le week-end
?>
<script language="javascript">
$(document).ready(function() {
	
	$("#lblBlurWeekEnd").click(function() {
		$(".weekendtohide").toggle();
		$(".weekendtoshow").toggle();
	});
});
</script>
<script language="javascript">
$(window).on('load',function(){
var side = ($("#id-container .side-nav").width() + 4);
var all = $("#id-container").width();
$("#id-right").css({
    width : (all-side)+'px',
    opacity: 1
});

$('#lblBlurWeekEnd').click();
});
</script>
<style type="text/css">
	table#header-fixed tr td input{text-align: center;}
</style>
<?php
llxFooter();

$db->close();


/**
 * Output a task line
 *
 * @param	string	   	&$inc			?
 * @param   string		$parent			?
 * @param   Object		$lines			?
 * @param   int			&$level			?
 * @param   string		&$projectsrole	?
 * @param   string		&$tasksrole		?
 * @param   int			$mytask			0 or 1 to enable only if task is a task i am affected to
 * @return  $inc
 */
function timesheetLines(&$inc, $lines, &$level, &$projectsrole, &$tasksrole, $mytask=0, $perioduser='')
{
	global $user, $bc, $langs, $conf;
	global $form, $formother, $pointage, $taskstatic;
	global $periodyear,$radio, $periodmonth,$salaire_user, $nbdaymonth,$pointage,$users,$perioduser,$periodproject ;
    global $lines, $project;
    global $numlines,$user_array;

    $var=true;
	$array=['','J'];
// ----------------------------------------B-----------------------------------------------------

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
// ----------------------------------------E-----------------------------------------------------

    $numlines=count($array);
    $all_users['salary'] = $pointage->getUsersWithS();
	$all_users['nosalary'] = $pointage->getUsersWithS(false);
	// echo "periodmonth : ".$periodmonth."<br>";
	// echo "periodyear : ".$periodyear."<br>";
	if($salaire_user->Check_exist_SU($periodmonth,$periodyear))
 	{
		$all_users['salary'] = $salaire_user->getUsersWithS(true,$periodyear,$periodmonth);
		$all_users['nosalary'] = $salaire_user->getUsersWithS(false,$periodyear,$periodmonth);
	}
	// print_r($all_users);
// ----------------------------------------B-----------------------------------------------------


	// print '<tr class="firsttrtab1"><td style="text-align: center;width:186px !important;"><div class="right-arrow">'.$month_names[$periodmonth].'</div></td>';
 //    for ($i = 0 ; $i <$numlines ; $i++)
	// {
	//   	for ($day=1;$day <= $nbdaymonth ;$day++)
	// 	{ 	
	// 		$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
	// 		$bgcolor="";
	// 		if(empty($array[$i])){
	// 		if (date('N', $curday) == 6 || date('N', $curday) == 7)
	// 		{
	// 			print '<td width=1px class=weekendtoshow bgcolor=grey ></td>';
	// 			$bgcolor=" class=weekendtohide bgcolor=grey ";
	// 			$BgOfTd="";
	// 		}
			
	// 		print '<td '.$bgcolor.'style="'.$BgOfTd.'" align="center">';
	// 		print substr($langs->trans(date('l', $curday)),0,1)." ".$day.'</td>';}
	// 	}
	// }

 //    print '</tr>';


	print "</table>";
	print "</div>";

	print '<div class="ScrollOnlyMe" style="overflow-y:scroll;max-height:600px;">';
	print '<table id="header-fixed" class="noborder">';
		print '<thead>';
		$html_thead == "";
		$html_thead .= '<tr class="firsttrtab1"><td style="text-align: center;width:186px !important;"><div class="right-arrow">'.$month_names[$periodmonth].'</div></td>';
		for ($i = 0 ; $i <$numlines ; $i++){
			for ($day2=1;$day2 <= $nbdaymonth ;$day2++){ 	
				$curday2=mktime(0, 0, 0, $periodmonth, $day2, $periodyear);
				$bgcolor2="";
				if(empty($array[$i])){
					if (date('N', $curday2) == 6 || date('N', $curday2) == 7){
						$html_thead .= '<td width=1px class=weekendtoshow bgcolor=grey ></td>';
						$bgcolor2=" class=weekendtohide bgcolor=grey ";
						$BgOfTd2="";
					}
					$alignTdOfDate2 = "center";

					$html_thead .= '<td '.$bgcolor2.'style="'.$BgOfTd2.'" align='.$alignTdOfDate2.'>';
					// $html_thead .= substr($langs->trans(date('l', $curday2)),0,1)." ".$day2.'</td>';
					$html_thead .= $langs->trans('Short'.date('l', $curday2))." ".$day2.'</td>';
				}
			}
		}
		$html_thead .= '</tr>';

		echo $html_thead;
	    $nombr = 0;
		print '</thead>';
		print '<tbody>';
		$inc_tr = 0;
		// print_r($all_users);
		foreach($all_users as $index=>$data) {
		    foreach($data as $key=>$value) {
		    	if(!empty($users) && is_array($users) && !in_array($key, $users)){
		     		continue ;
		    	}

		    	$user_array->fetch($key);
				// print_r($user_array);
		    	$user_arr = $pointage->nc_getUserInfo($key);

		    	if (isset($radio) && !empty($radio)) {
					if($radio=='declar' && $user_array->array_options['options_nx_is_declared']==0 ){
						continue;
					}
					elseif($radio=='nodeclar' && $user_array->array_options['options_nx_is_declared']==1){
						continue;
					}
		    	}
		    	$var = !$var;
		    	$nombr = $nombr + 1;
				for ($i = 0 ; $i <$numlines ; $i++)
				{   

					if ($inc_tr == 15) {
						echo $html_thead;
						$inc_tr = 1;
					}
					
					$BgOfTr = ($i % 2 == 0 ? "background-color:#e6e6e6;" : "");
				   
					if(!empty($array[$i])){
					    print '<tr '.$bc[$var].' data="dd">';

					    $salary_user_class = "non_salary_user";
					    if($index =="salary")
							$salary_user_class = "salary_user";

					    print '<td class="'.$salary_user_class.'" align="left" style="white-space: nowrap;border-right: 1px solid #e6e6e6;font-size: 11px;">'.$user_array->getNomUrl(1).'<p style="margin-left:30px;"></p>
					               </td>';
				                
					}
				    for ($day=1;$day <= $nbdaymonth ;$day++)
					{ 
						$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
						$BgOfTd = ($day % 2 == 0 ? "background-color:#efefef;" : "");
						$bgcolor="";
						if(!empty($array[$i])){
							if (date('N', $curday) == 6 || date('N', $curday) == 7)
							{
								print '<td width=1px class=weekendtoshow bgcolor=grey ></td>';
								$bgcolor=" class=weekendtohide bgcolor=grey ";
								$BgOfTd="";

							}
							print '<td '.$bgcolor.'style="'.$BgOfTd.'" align=center >';
							// le nom du champs c'est à la fois le jour et l'id de l'utilisateur
							print '<input type=text id="inputday" name="'.$array[$i].'-'.$day.'-'.$key.'"';
							$val=$pointage->getVal($periodmonth,$periodyear,$key,$array[$i],$day);
							print ' value="'.$val.'"';
							// if($index =="salary")
							// 	print ' disabled';
							print ' size=2 style="font-size:0.7em">';
							print '</td>';

						}
			   		}
						print '</tr>';
				}	
				$inc_tr++;
			}
		}
		if ($nombr == 0) {
			print '<tr><td align="center" colspan="40">Aucun Personne</td></tr>';
		}
		// $BgOfTd = "";
		// print '<tr class="firsttrtab1"><td style="text-align: center;width:186px !important;"><div class="right-arrow">'.$month_names[$periodmonth].'</div></td>';
		// for ($i = 0 ; $i <$numlines ; $i++)
		// {
		//   	for ($day=1;$day <= $nbdaymonth ;$day++)
		// 	{ 	
		// 		$curday=mktime(0, 0, 0, $periodmonth, $day, $periodyear);
		// 		$bgcolor="";
		// 		if(empty($array[$i])){
		// 		if (date('N', $curday) == 6 || date('N', $curday) == 7)
		// 		{
		// 			print '<td width=1px class=weekendtoshow bgcolor=grey ></td>';
		// 			$bgcolor=" class=weekendtohide bgcolor=grey ";
		// 			$BgOfTd="";
		// 		}
		// 		 $alignTdOfDate = ($day < 19 ? "center" : "right");
				
		// 		print '<td '.$bgcolor.'style="'.$BgOfTd.'" align='.$alignTdOfDate.'>';
		// 		print substr($langs->trans(date('l', $curday)),0,1)." ".$day.'</td>';}
		// 	}
		// }

		// print '</tr>';
		
	return $inc;
}
// ------------------------------------------------E---------------------------------------------
          
/**
 * Output a task line
 *
 * @param	string	   	&$inc			?
 * @param   string		$parent			?
 * @param   Object		$lines			?
 * @param   int			&$level			?
 * @param   string		&$projectsrole	?
 * @param   string		&$tasksrole		?
 * @param   int			$mytask			0 or 1 to enable only if task is a task i am affected to
 * @return  $inc
 */
function setsheetLines(&$inc, $parent, $lines, &$level, &$projectsrole, &$tasksrole, $perioduser)
{
	global $db, $bc, $langs, $user;
	global $form, $projectstatic, $taskstatic,$pointage,$salaire_user;
	global $periodyear,$radio, $periodmonth, $nbdaymonth,$users;
// echo '<pre>';
// print_r($lines); die();
// echo '</pre>';

	$lastprojectid=0;
	$var=true;
    $ar=['J'];
	$numline=count($ar);
    $all_users['salary'] = $pointage->getUsersWithS();
	$all_users['nosalary'] = $pointage->getUsersWithS(false);
	$all_users1['salary'] = $pointage->getUsersWithS();
	$all_users1['nosalary'] = $pointage->getUsersWithS(false);
	if($salaire_user->Check_exist_SU($periodmonth,$periodyear))
 	{ 
 		$all_users['salary'] = $salaire_user->getUsersWithS(true,$periodyear,$periodmonth);
	    $all_users['nosalary'] = $salaire_user->getUsersWithS(false,$periodyear,$periodmonth);
	    $all_users1['salary'] = $salaire_user->getUsersWithS(true,$periodyear,$periodmonth);
	    $all_users1['nosalary'] = $salaire_user->getUsersWithS(false,$periodyear,$periodmonth);}
	/*$fk_project = GETPOST('periodprojectselected','int');
	$test = $pointage->getProjetId($periodmonth,$periodyear,$perioduser,$fk_project);
	if($test && $fk_project!=$periodproject){
		setEventMessage($langs->trans("projetExict"), 'errors');
	return 0;
	}*/
	$user_array=new User($db);
	foreach($all_users as $index=>$data) {
    foreach($data as $key=>$value) {
    	$user_array->fetch($key);
    	$user_arr = $pointage->nc_getUserInfo($key);
    	if (isset($radio) && !empty($radio)) {
    			if($radio=='declar' && $user_array->array_options['options_nx_is_declared']==0 )
    				continue;
    			elseif($radio=='nodeclar' && $user_array->array_options['options_nx_is_declared']==1)
    				continue;
    	}
    	if(!empty($users) && is_array($users) && !in_array($key, $users))
     		continue ;
	for ($i = 0 ; $i < $numline ; $i++)
	{

		for ($day=1;$day <= $nbdaymonth;$day++){
			$durationsuppr 	= 0;
			$curday 	 	= mktime(0, 0, 0, $periodmonth, $day, $periodyear);
			// dans le doute on supprime les enregs si ils existent
			// on supprime les lignes du mois
	     	$getPointageId = $pointage->getID($periodmonth,$periodyear,$key,$ar[$i],$day);
		    
	        $valeur=GETPOST($ar[$i].'-'.$day.'-'.$key);
		      	if($getPointageId){
			      	if(intval($valeur) != 0 ){
						$data = array('val'=>$valeur);
						$pointage->update($getPointageId, $data);
                    }else{
                     	$pointage->delete($getPointageId);
                 	}
		      	}else{
	      	      	if($valeur){
                        $pointage->year_point=$periodyear;
	                    $pointage->created_by=$user->id;
			            $pointage->month_point=$periodmonth;
			            $pointage->fk_user=$key;
			            $pointage->jour=$day;
			            $pointage->val=$valeur;
			            $pointage->type=$ar[$i];

			            //$pointage->fk_project=$fk_project;
			            $getpoinID = $pointage->create();
			        
			            if(!$salaire_user->Check_exist_SU($periodmonth,$periodyear)){
			            	$salaire_user	= new salaire_user($db);
			            	$userp 			= new User($db);
		            	 	foreach($all_users1 as $index1=>$data1) {
							    foreach($data1 as $key1=>$value1) {
							    	$userp->fetch($key1);
							    	$salary = 0;
							    	$thm = 0;
							    	$salary_base = 0;
							    	$user_arr2 = $pointage->nc_getUserInfo($key1);
							    	if($userp->salary)
							    		$salary= $userp->salary;
							    	// elseif($userp->thm)
							    	// 	$thm = $userp->thm;
							    	if($userp->thm)
							    		$thm = $userp->thm;
							    	$salaire_user->year_point=$periodyear;
							        $salaire_user->created_by=$user->id;
							        $salaire_user->month_point=$periodmonth;
							        $salaire_user->fk_user=$key1;
							        $salaire_user->salary=$salary;
							        $salaire_user->salary_base=$userp->array_options['options_nx_salaire_base'];
							        $salaire_user->thm=$thm;
									$salaire_user->create();
								}
							}
			        	}
			        }
			    }            
       	}
          
				
	}
}
}
 //$periodproject = $fk_project;
			
		
	
	return 1;
}

// calculate number of days in a month
function days_in_month($month, $year)
{
	return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31);
}
?>

