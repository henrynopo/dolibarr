<?php

$spc    = str_repeat("&nbsp;",4);

$title = $object->label;

$html = '';
$html.= '<div align="center"><h1>'.$title.'</h1></div>';
$html.= '<br><br>';
    


$html.= '<br><div class="title_div">&nbsp;&nbsp;<b>'.$langs->trans('recrutement').'</b></div>';
$html.= '<table class="border_1" width="100%" cellpadding="3" cellspacing="2">';

    $departement->fetch($object->departement);
    $html.= '<tr>';
        $html.= '<td width="25%">'.$langs->trans('PosteOccupe').'</td>';
        $html.= '<td width="75%">'.$object->label.'</td>';
    $html.= '</tr>';

    $html.= '<tr>';
        $html.= '<td >'.$langs->trans('departement').'</td>';
        $html.= '<td>'.$departement->label.'</td>';
    $html.= '</tr>';

    $html.= '<tr>';
        $html.= '<td>'.$langs->trans('lieu').'</td>';
        $societe->fetch($object->lieu);
        // $html.= '<td>'.$form->select_company($object->lieu,'lieu','','SelectThirdParty').'</td>';
        $html.= '<td>'.$societe->getFullName($langs).'</td>';
    $html.= '</tr>';

    $html.= '<tr>';
        $html.= '<td >'.$langs->trans('email').'</td>';
        $html.= '<td >'.$object->email.'</td>';
    $html.= '</tr>';

    $html.= '<tr>';
        $html.= '<td >'.$langs->trans('responsable_recrutement').'</td>';
        $responsable_recrutement = new user($db);
        $responsable_recrutement->fetch($object->responsable_recrutement);
        $html.= '<td >'.$responsable_recrutement->getFullName($langs).'</td>';
    $html.= '</tr>';

    $html.= '<tr>';
        $html.= '<td >'.$langs->trans('nb_nouveauemploye').'</td>';
        $html.= '<td >'.$object->nb_nouveauemploye.'</td>';
    $html.= '</tr>';
   $html.= '<tr><td colspan="2" style="line-height:1"></td></tr>';
$html.= '</table>';



$html.= '<br><div class="title_div" >&nbsp;&nbsp;<b> '.$langs->trans("offre").'</b></div>';
$html.= '<table class="border_1" width="100%" cellpadding="3" cellspacing="2">';

    $html.= '<tr>';
        $html.= '<td width="25%">'.$langs->trans('responsable_RH').'</td>';
        $responsable_RH = new user($db);
        $responsable_RH->fetch($object->responsable_RH);
        // $html.= '<td >'.$responsable_RH->firstname.' '.$responsable_recrutement->lastname.'</td>';
        $html.= '<td width="75%">'.$responsable_RH->getFullName($langs).'</td>';
    $html.= '</tr>';
    $html.= '<tr>';
        $data='';
        if($object->date){
            $date=explode('-', $object->date);
            $date=$date[2].'/'.$date[1].'/'.$date[0];
        }
        $html.= '<td >'.$langs->trans('date_pr_empbouche').'</td>';
        $html.= '<td >'.$date.'</td>';
    $html.= '</tr>';

    $html.= '<tr>';
        $html.= '<td >'.$langs->trans('Status').'</td>';
        $html.= '<td>';
            $html.= $langs->trans($object->status);
        $html.= '</td>';
    $html.= '</tr>';

    $html.= '<tr>';
        $html.= '<td class="firsttd200px" >'.$langs->trans('description_p').'</td>';
        $html.= '<td >'.nl2br($object->description).'</td>';
    $html.= '</tr>';
    
    $html.= '<tr><td colspan="2" style="line-height:1"></td></tr>';
$html.= '</table>';


$html.= '<br><br>';


$html.='<style>';
$html.='th {color:black;}';
$html.='table.border_1 {border:1px solid #e0e0e0; height:30px;width:100%;}';
$html.='table.border_1 td, table.border_1 th{line-height:2.3;}';
$html.='.logo {width:10%; text-align:center;}';
$html.='.sp_td {line-height:2;}';
$html.='.div_1 {width:100px;}';
$html.='.name_vehicule {width:100px;}';
$html.='.title_div {color:black; background-color: #dcdcdf; width:100%; height: 33px; line-height: 3;}';
$html.='.d_right {float:left;width:200px;border:1px solid #e0e0e0;}';
$html.='.d_left {float:left;width:800px;border:1px solid #e0e0e0;}';
// $html.='span.etape{line-height:2; color:#fff}';
$html.='span.etape{line-height:2; }';
$html.='</style>';


// die($html);