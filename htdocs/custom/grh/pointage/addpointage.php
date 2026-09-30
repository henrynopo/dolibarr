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
 *	\file       /management/projet/listtime.php
 *	\ingroup    projet
 *	\brief      List activities of tasks
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory

//require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/grh/pointage/class/pointage.class.php');
dol_include_once('/grh/salaire_user/class/salaire_user.class.php');
// dol_include_once('/grh/management/core/lib/management.lib.php');
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');

$action=GETPOST('action');
$mode=GETPOST("mode");
$id=GETPOST('id','int');

$periodyears=GETPOST('periodyears','int');
// $periodmonths=GETPOST('periodmonths','int');
$periodmonths = (GETPOST('periodmonths','int')) ? GETPOST('periodmonths','int') : (date('m') + 0);
$search_dated      		= GETPOST('search_dated');
$search_datef      		= GETPOST('search_datef');
$radio      		= GETPOST('radio');
$users      		= GETPOST('users');
$show      		= GETPOST('show','int');


if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
	$search_datef = "";
	$search_dated = "";
	$users     = "";
	$radio     = "";
	$periodyears     = "";
	$periodmonths     = (date('m') + 0);
}
if (!$periodyears)
	$periodyears=date('Y');
if (!$periodmonths)
	$periodmonths=date('m');

	
// récupération du nombre de jour dans le mois
/*$time = mktime(0, 0, 0, $periodmonths+1, 1, $periodyears); // premier jour du mois suivant
$time--; // Recule d'une seconde
$nbdaymonth=date('d', $time);*/ // on récupère le dernier jour

$form=new Form($db);
$formother = new FormOther($db);
$pointage= new pointage($db);
$user_array=new User($db);
$salaire_user= new salaire_user($db);
//$project = new Project($db);
/*
 * Actions
 */
$title=$langs->trans("consulte");
if ( $action == "exl" ) {

$filename="etat_pointage_".$periodyears.".xls";
      require_once dol_buildpath('/grh/pointage/tpl/etat_pointage_xsl.php');
 die();
 
}

/*
 * View
 */

$morejs  = array("/grh/js/pointage.js");

llxHeader("",$title,'','','','',$morejs);
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num);
dol_htmloutput_mesg($mesg);


?>

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
<?php
print '<div style="width: 100%; margin: 5px 0 13px; text-align: right;">';
print '<a href="./listtime.php" class="butAction">'.$langs->trans('TimeSpentPeriod').'</a></div>';
print '<br>';

global $symbole_curren;
$symbole_curren = $langs->getCurrencySymbol($conf->currency);
print '<form name="selectperiod" method="POST" action="'.$_SERVER["PHP_SELF"].'" style="    background: #efefef;padding:4px;">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="selectperiod">';

print '<table width="100%">';
print '<tr >';
print '<td class="periodeaaaamm">'.$langs->trans("PeriodAAAAMM").'</td>';
print '<td class="select_period">';
print $formother->selectyear($periodyears,'periodyears').$formother->select_month($periodmonths,'periodmonths');

print '<input autocomplete="off" class="flat datepicker" type="text" autocomplete="off" name="search_dated" style="margin: 0 2px;" value="'.$search_dated.'"/>';
print '<input autocomplete="off" class="flat datepicker" type="text" name="search_datef" autocomplete="off" style="margin: 0 2px;" value="'.$search_datef.'"/>';
print '</td>';
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
print '</td>';
print "</tr>\n";

print "</table>";

print '<table style="width:100%;">';
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
print '</form>';

print '<form name="selectperiod" method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="selectperiod">';

// print '<table width="100%">';
// print '<tr >';
// print '<td style="white-space: nowrap;">'.$langs->trans("PeriodAAAAMM");
// print $formother->selectyear($periodyears,'periodyears').$formother->select_month($periodmonths,'periodmonths').'<input autocomplete="off" class="flat datepicker" type="text"  name="search_dated" value="'.$search_dated.'"/><input autocomplete="off" class="flat datepicker" type="text" name="search_datef" value="'.$search_datef.'"/>';
// print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'"></td>';
// //print '<div >'.$langs->trans("ShowHideWeekend").'</div>';
// print '<td align="center"><input type=submit align="center" name="select" value="'.$langs->trans("Select").'">';
// print '<input class="butAction" type="button" id="lblBlurWeekEnd" name="button" value="'.$langs->trans("ShowHideWeekend").'"></td>';
// print "<tr>\n";
// print '<td colspan="2">';

// $users_array = $pointage->getUsers();
// print $form->multiselectarray('users', $users_array, $users, null, null, null,null, "50%");
// print '</td>';
// print "</tr>\n";
// print "<tr>\n";
// print '<td colspan="2">';
// $checkedb ='';
// $checkedd ='';
// $checkedn ='';
// if (isset($radio) ) {
//     			if($radio=='declar' )
//     				$checkedd ='checked="checked"';
//     			elseif($radio=='nodeclar')
//     				$checkedn ='checked="checked"';
//     			else
// 					$checkedb ='checked="checked"';
//     	}
// print '<label><input type="radio" '.$checkedb.' name="radio" value="both">Tous</label><label><input type="radio" '.$checkedd.' name="radio" value="declar">Déclarés</label>
// <label><input type="radio" '.$checkedn.' name="radio" value="nodeclar">Non Declarés</label>';
// print '</td>';
// print "</tr>\n";
// print '</table>';
print '</form>';
print '<div style="text-align: right; margin: 10px 0;">';
	print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">'."\n";
		print '<input  type="hidden" name="action" value="exl">';
	 	print '<input type="hidden" name="periodyears" value="'.$periodyears.'">';
		print '<input type="hidden" name="periodmonths" value="'.$periodmonths.'">';
		print '<input type="hidden" name="radio" value="'.$radio.'">';
		print '<input  type="hidden"  name="search_datef" value="'.$search_datef.'">';
	 	print '<input  type="hidden"  name="search_dated" value="'.$search_dated.'">';
	 	print '<input  type="hidden"  name="users" value="'.$users.'">';
	  print '<input id="show"  type="hidden"  name="show" value="0">';
	  print '<input type="submit" name="submit" class="butAction" value="Export Excel" style="padding: .69em 1em;">';
	  print '<a href="#" onclick="javascript:imprimer_bloc();" class="butAction">'.$langs->trans("Imprimer").'</a>';
	  // print '<a href="./listtime.php" class="butAction">'.$langs->trans('TimeSpentPeriod').'</a>';
	 print '</form>'."\n";
	 

	 print '</div>';
	 print '<div class="guide_salariegrh">
		<div class="salarie_">
			<span class="bg_red"></span> 
			'.$langs->trans("Salariés").' ('.$langs->trans("Salaire").' > '.price(0,0,$langs,1,-1,-1,$conf->currency).')
		</div>
		<div class="non_salarie_">
			<span class="bg_blue"></span> 
			'.$langs->trans("Non_Salariés").'
		</div>
	  </div>';


$showempty=0;
// attention le dernier paramétre n'est dispo que sur la 3.7 et le patch fournis





/*print '<td  align="center">'.$langs->trans("nbHeurseup").'</td>';
print '<td  align="center">'.$langs->trans("avance").'</td>';*/






timesheetLines($j,$pointage->rows, $level, $projectsrole, $tasksrole, $mine, $perioduser);


print '</form>';
?>
<script language="javascript">
$(window).on('load',function(){
var side = ($("#id-container .side-nav").width() + 4);
var all = $("#id-container").width();
$("#id-right").css({
    width : (all-side)+'px',
    opacity: 1
});
// $('#lblBlurWeekEnd').click();
});
</script>
<?php

// pour activer ou non le week-end
print '<script language="javascript">';
print '$(document).ready(function() {';
print '$(".weekendtohide").toggle();';
print '	$("#lblBlurWeekEnd").click(function() {';
print '		$(".weekendtohide").toggle();';
print '		$(".weekendtoshow").toggle();';
print '	});';
print '});';
print '</script>';

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
function timesheetLines(&$inc,$lines, &$level, &$projectsrole, &$tasksrole, $mytask=0, $perioduser='')
{
	global $user, $bc, $langs;
	global $form, $formother, $pointage,$salaire_user, $taskstatic;
	global $periodyears,$radio,$periodmonths,$users,$search_datef,$search_dated,$pointage;
     global $lines;
     global $numlines,$user_array,$project,$symbole_curren;
     $filter="and year_point=".$periodyears;
     if (empty($search_datef) && empty($search_dated) )
     $filter.=" and month_point=".$periodmonths;
     $sd = 0 ;
     $ed = 0 ;
    //$filter.=" and month_point=".$periodmonths;
     if (isset($search_dated) && !empty($search_dated)) {
	list($sd, $sm, $sy)  = explode("/", $search_dated);
	if (isset($search_datef) && !empty($search_datef)) {
	list($ed, $em, $ey)  = explode("/", $search_datef);
	/*$filter .=  " AND month_point BETWEEN ". $sm ." AND ". $em ;
	$filter .=  " AND jour BETWEEN ". $sd ." AND ". $ed ;*/
}
}

$sd = intval($sd);
$ed = intval($ed);	
$numtd = 0 ;
	
    $pointage->fetchAll('','p.rowid',0,0,$filter);
    $all_users['salary'] = $pointage->getUsersWithS();
 	$all_users['nosalary'] = $pointage->getUsersWithS(false);
 	if($salaire_user->Check_exist_SU($periodmonths,$periodyears))
 	{ $all_users['salary'] = $salaire_user->getUsersWithS(true,$periodyears,$periodmonths);
	$all_users['nosalary'] = $salaire_user->getUsersWithS(false,$periodyears,$periodmonths);}
	//$lastprojectid=0;
	$var=true;
	$array=['J'];
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
    $numlines=count($array);
    if (!empty($lines))
    	$num=count($lines);

    $nbdaymonth = 0;
    
    $monthArray = monthArray($langs);
    //$months = array() ;
    $total_day = array();
    $total_month = array();
    //$months[]=$periodmonths;
    $test = 0;
    $time = mktime(0, 0, 0, $lperiodmonths+1, 1, $periodyears); // premier jour du mois suivant
			$time--; // Recule d'une seconde
			$nbdaymonth=date('d', $time); 
			if (!empty($search_datef) && !empty($search_dated) ){
				/*if($periodmonths == $sm && $periodmonths != $em)
					$numtd = ($nbdaymonth-$sd)+2;
				elseif($periodmonths == $em)
					$numtd = $ed+1;
				else*/
					$numtd = ($ed-$sd)+2;
			}
			else
				$numtd = $nbdaymonth+1;

     		print '<div class="ScrollOnlyMe" >';
     		print '<table class="noborder table_content" width="100%" >';
     		// print'<tr><td colspan="'.$numtd.'" align="center"><strong>'.$monthArray[$periodmonths].' '.$periodyears.'</strong></td></tr>';
			print '<tr class="liste_titre">';
			print '<td  align="center" >'.$langs->trans("Names").'</td>';
			print '<td  align="center">'.$langs->trans("t.h").' ('.$symbole_curren.')</td>';
			print '<td colspan="'.intval($numtd-1).'" align="right"></td>';
			print '<td  align="center">'.$langs->trans("total").'</td>';
			print '<td  align="left">'.$langs->trans("netap").' ('.$symbole_curren.')</td>';
			print "</tr>\n";

			print '<tr class="firsttrtab1"><td colspan="2" style="text-align: center;width:186px !important;"><div class="right-arrow">'.$month_names[$periodmonths].'</div></td>';
            	for ($i = 0 ; $i <$numlines ; $i++)
	  		 {      $nbr=0;
	   
	                for ($day=1;$day <= $nbdaymonth ;$day++)
				{ 

					if (!empty($search_datef) && !empty($search_dated) ) {
						if($day<$sd  || $day>$ed )
							continue ;
					}
					  $curday=mktime(0, 0, 0, $periodmonths, $day, $periodyears);
					  $bgcolor="";
					
				    	
							if (date('N', $curday) == 6 || date('N', $curday) == 7)
					{
						print '<td width=1px class=weekendtoshow bgcolor=grey ></td>';
						$bgcolor=" class=weekendtohide bgcolor=grey ";}
							print '<td '.$bgcolor.' align=center>';
							// print substr($langs->trans(date('l', $curday)),0,1)." ".$day.'</td>';
							print $langs->trans('Short'.date('l', $curday))." ".$day.'</td>';
						
	        }
	        }
           print '</tr>';
$nombr = 0;
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
		if(!empty($users) && !in_array($key, $users))
 			continue ;
 		$var = !$var;
        if (count($pointage->rows)) {
	     	foreach($pointage->rows as $line) {
				if($line->fk_user == $key){
					$test = 1;

					$user_array->fetch($line->fk_user);
					$title = '';
					$nombr = $nombr + 1;
					for ($i = 0 ; $i <$numlines ; $i++){
						$nbr=0;

						if(!empty($array[$i])){
							print '<tr '.$bc[$var].' data="td_td">';
							if ($index=='salary') {
								$added_cls = "salary_user";
							}else{
								$added_cls = "non_salary_user";
							}
							print '<td align="left" class="user_pointage '.$added_cls.'">'.$user_array->getNomUrl(1).'</td>';
							// if($user_array->thm)
							if($salaire_user->getThm($periodmonths,$periodyears,$key))
								print '<td align="center" class="thm_td">'.number_format($salaire_user->getThm($periodmonths,$periodyears,$key),2).'</td>';
							else
								print '<td class="thm_td"></td>';
							// if($index=='nosalary')
							// 	print '<td align="center" class="thm_td">'.number_format($user_array->thm,2).'</td>';
							// else
							// 	print '<td class="thm_td"></td>';
						}

						for ($day=1;$day <= $nbdaymonth ;$day++){ 
							if (!empty($search_datef) && !empty($search_dated) ) {
								if($day<$sd  || $day>$ed )
								continue ;
							}

							$curday=mktime(0, 0, 0, $periodmonths, $day, $periodyears);

							$bgcolor="";

							if(!empty($array[$i])){
								if (date('N', $curday) == 6 || date('N', $curday) == 7)
								{
									print '<td width=1px class=weekendtoshow bgcolor=grey style="display: table-cell;"></td>';
									$bgcolor=" class=weekendtohide bgcolor=grey ";
								}


								print '<td '.$bgcolor.' align=center >';
								// le nom du champs c'est à la fois le jour et l'id de la tache
								print '<input type=text id="inputday" name="'.$array[$i].'-'.$day.'-'.$line->fk_user.'"'; 	

								$val=$pointage->getVal($periodmonths,$periodyears,$line->fk_user,$array[$i],$day);
								$nbr=$nbr+$val;
								if(!array_key_exists($periodmonths.'-'.$day, $total_day))
									$total_day[$periodmonths.'-'.$day] = $val;
								else
									$total_day[$periodmonths.'-'.$day] += $val;

								print ' value="'.$val.'"';
								print ' size=2 style="font-size:0.7em;text-align:center;" disabled>';
								print '</td>';
							}
						}

						for ($com=1; $com <$i ; $com++) {
							if(!empty($array[$i])) 
								print '<td align="center"></td>';
						}

						if(!empty($array[$i])){
							print '<td align="center">'.$nbr.'</td>';
						}

						$total = 0;
						if($salaire_user->getSalary($periodmonths,$periodyears,$key)){
							$total = $nbr*$salaire_user->getThm($periodmonths,$periodyears,$key);
							if($salaire_user->getThm($periodmonths,$periodyears,$key)){
								$total = $total + $salaire_user->getSalary($periodmonths,$periodyears,$key);
							}
							print '<td align="center" class="net_a_paye_with_thm">'.number_format($total,2).'</td>';
							if(!array_key_exists($periodmonths.'_'.$index, $total_month))
								$total_month[$periodmonths.'_'.$index] = $total;
							else
								$total_month[$periodmonths.'_'.$index] += $total;
						}elseif($salaire_user->getThm($periodmonths,$periodyears,$key)){
							$total = $nbr*$salaire_user->getThm($periodmonths,$periodyears,$key);
							print '<td align="center" class="net_a_paye_with_thm">'.number_format($total,2).'</td>';
							if(!array_key_exists($periodmonths.'_'.$index, $total_month))
								$total_month[$periodmonths.'_'.$index] = $total;
							else
								$total_month[$periodmonths.'_'.$index] += $total;
						}else{
							print '<td align="center" class="emptyy"></td>';
						}
						// if($salaire_user->getThm($periodmonths,$periodyears,$key)){
						// 	$total = $nbr*$salaire_user->getThm($periodmonths,$periodyears,$key);
						// 	print '<td align="center" class="eeeeeeeeeee">'.number_format($total,2).'</td>';
						// 	if(!array_key_exists($periodmonths.'_'.$index, $total_month))
						// 		$total_month[$periodmonths.'_'.$index] = $total;
						// 	else
						// 		$total_month[$periodmonths.'_'.$index] += $total;
						// }
						for ($c=$com; $c<1 ; $c++) {
							if(!empty($array[$i])) 
								print '<td align="center"></td>';
						}
						print'</tr>';
					}
					print'</tr>';
				}
			}
		}

		if($test==0){

	     	$user_array->fetch($key);
		    print '<tr '.$bc[$var].' data="td_td">';
		    if ($index=='salary') {
				$added_cls = "salary_user";
			}else{
				$added_cls = "non_salary_user";
			}
			print '<td align="left" class="user_pointage '.$added_cls.'">'.$user_array->getNomUrl(1).'</td>';

			if($salaire_user->getThm($periodmonths,$periodyears,$key))
				print '<td align="center" class="thm_td">'.number_format($salaire_user->getThm($periodmonths,$periodyears,$key),2).'</td>';
			else
				print '<td class="thm_td"></td>';


			
			// if($index=='nosalary')
			// 	print '<td class="thm_td" align="center">'.number_format($user_array->thm,2).'</td>';
			//    else
			//  print '<td class="thm_td"></td>';
			
			for ($day=1;$day <= $nbdaymonth ;$day++)
			{ 
				if (!empty($search_datef) && !empty($search_dated) ) {
					if($day<$sd  || $day>$ed )
						continue ;
				}
				$curday=mktime(0, 0, 0, $periodmonths, $day, $periodyears);
				$bgcolor="";

				if (date('N', $curday) == 6 || date('N', $curday) == 7){
					print '<td width=1px class=weekendtoshow bgcolor=grey style="display: table-cell;"></td>';
					$bgcolor=" class=weekendtohide bgcolor=grey ";
				}

				print '<td '.$bgcolor.' align=center >';

				print '<input type=text id="inputday" name="'.$array[0].'-'.$day.'-'.$key.'"'; 	

				print ' size=2 style="font-size:0.7em;text-align:center;" disabled>';
				print '</td>';
			}
		            
	   		for ($com=1; $com <$i ; $com++) {
				if(!empty($array[$i])) 
					print '<td align="center"></td>';	
	 		}

	   		//if(!empty($array[$i]))
			$total = 0;
			print '<td align="center" class="heu_td">'.$nbr.'</td>';
			if($salaire_user->getSalary($periodmonths,$periodyears,$key)){
				$total = $salaire_user->getSalary($periodmonths,$periodyears,$key);
				if($salaire_user->getThm($periodmonths,$periodyears,$key)){
					$total = $total + ($nbr*$salaire_user->getThm($periodmonths,$periodyears,$key));
				}
				print '<td class="net_paye_td 22" align="center">'.number_format($total,2).'</td>';
				if(!array_key_exists($periodmonths.'_'.$index, $total_month))
					$total_month[$periodmonths.'_'.$index] = $total;
				else
					$total_month[$periodmonths.'_'.$index] += $total;
			}elseif($salaire_user->getThm($periodmonths,$periodyears,$key)){
				$total = $nbr*$salaire_user->getThm($periodmonths,$periodyears,$key);
				print '<td class="net_paye_td" align="center">'.number_format($total,2).'</td>';
				if(!array_key_exists($periodmonths.'_'.$index, $total_month))
					$total_month[$periodmonths.'_'.$index] = $total;
				else
					$total_month[$periodmonths.'_'.$index] += $total;
			}else{
				print '<td align="center" class="emptyy"></td>';
			}

			// if($salaire_user->getThm($periodmonths,$periodyears,$key)){
			// 	$total = $nbr*$salaire_user->getThm($periodmonths,$periodyears,$key);
			// 	print '<td class="net_paye_td" align="center">'.number_format($total,2).'</td>';
			// 	if(!array_key_exists($periodmonths.'_'.$index, $total_month))
			// 		$total_month[$periodmonths.'_'.$index] = $total;
			// 	else
			// 		$total_month[$periodmonths.'_'.$index] += $total;
			// }elseif($salaire_user->getSalary($periodmonths,$periodyears,$key)){
			// 	$total = $salaire_user->getSalary($periodmonths,$periodyears,$key);
			// 	print '<td class="net_paye_td" align="center">'.number_format($total,2).'</td>';
			// 	if(!array_key_exists($periodmonths.'_'.$index, $total_month))
			// 		$total_month[$periodmonths.'_'.$index] = $total;
			// 	else
			// 		$total_month[$periodmonths.'_'.$index] += $total;
			// }
		}
		$test = 0;
		$nbr = 0;
	}
	print '<tr class="liste_titre"><td  align="left">'.$langs->trans("total").'</td><td></td>';


     		 for ($i = 0 ; $i <$numlines ; $i++){
	  		 	$total_heurs = 0;
     		 	for ($day=1;$day <= $nbdaymonth ;$day++){ 
					if (!empty($search_datef) && !empty($search_dated) ) {
						if($day<$sd  || $day>$ed )
							continue ;
					}
					
					$curday=mktime(0, 0, 0, $periodmonths, $day, $periodyears);

					  $bgcolor="";
					if(isset($total_day[$periodmonths.'-'.$day])){
						$total_heurs +=  $total_day[$periodmonths.'-'.$day];
					
						if (date('N', $curday) == 6 || date('N', $curday) == 7)
					{
						print '<td width=1px class=weekendtoshow bgcolor=grey ></td>';
						$bgcolor=" class=weekendtohide bgcolor=grey ";}
							print '<td '.$bgcolor.' align=center>'.$total_day[$periodmonths.'-'.$day].'</td>';
						}
						else{

							if (date('N', $curday) == 6 || date('N', $curday) == 7)
					{
						print '<td width=1px class=weekendtoshow bgcolor=grey ></td>';
						$bgcolor=" class=weekendtohide bgcolor=grey ";}
							print '<td '.$bgcolor.' align=center></td>';

						}
					}
				}
				print '<td align="center" class="tor_heu"><strong>'.$total_heurs.'</strong></td>';
				print '<td align="center" class="tor_net_pay"><strong>'.number_format($total_month[$periodmonths.'_'.$index],2).'</strong></td>';
				print "</tr>\n";	

}
print '<tr class="liste_titre" style="">';
			print '<td colspan="'.intval($numtd+1).'" align="center"><strong>TOTAL GlOBAL</strong></td>';
			print '<td  align="center"></td>';
			$total_global = 0;
			foreach ($total_month as $value) {
				$total_global += $value;
			}
			print '<td  align="center"><strong>'.number_format($total_global,2).'</strong></td>';
			print "</tr>\n";

print "</table>";
print "</div>";
print '<div id="imprime" style="display:none;" >';
  dol_include_once('/grh/pointage/tpl/etat_pointage_imp.php');
//print $output;
print '</div>';

	return $inc;
}
?>

