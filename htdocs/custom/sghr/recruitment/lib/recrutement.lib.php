<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file		lib/recrutement.lib.php
 *	\ingroup recruitment
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function menu_candidature($id)
{
    global $langs, $conf, $db;
    $langs->load("recruitment@sghr");
    dol_include_once('/sghr/recruitment/class/JobPosition.class.php');
    dol_include_once('/sghr/recruitment/class/Candidate.class.php');

    $h = 0;
    $head = array();
    $candidature = new Candidate($db);
    $candidature->fetch($id);
    // print_r($_SERVER);
    $link = $_SERVER["REQUEST_URI"];
    if(!empty($id))
        $link = dol_buildpath("recrutement/candidates/card.php?id=".$id,2);

        $head[$h][0] = $link;
        $head[$h][1] = $langs->trans("general");
        $head[$h][2] = 'general';
        $h++;

        if(!empty($id)){
            $head[$h][0] = dol_buildpath("recrutement/cv/index.php?candidature=".$id,2);
            $head[$h][1] = $langs->trans("cv");
            $head[$h][2] = 'cv';
            $h++;
            $head[$h][0] = dol_buildpath("recrutement/Candidate/events.php?id_candid=".$id,2);
            $head[$h][1] = $langs->trans("Events_candid");
            $head[$h][2] = 'events';
            $h++;
        }
        if($candidature->employe){
            $head[$h][0] = dol_buildpath("recrutement/Candidate/fiche_employe.php?candidature=".$id,2);
            $head[$h][1] = $langs->trans("fiche_employe");
            $head[$h][2] = 'fiche_employe';
            $h++;
        }
       
    return $head;
}


function menu_poste($id)
{
    global $langs, $conf, $db;
    $langs->load("recruitment@sghr");
    dol_include_once('/sghr/recruitment/class/JobPosition.class.php');
    dol_include_once('/sghr/recruitment/class/Candidate.class.php');

    $h = 0;
    $head = array();
        
        $head[$h][0] = dol_buildpath("recrutement/card.php?id=".$id,2);
        $head[$h][1] = $langs->trans("general");
        $head[$h][2] = 'general';
        $h++;

        $head[$h][0] = dol_buildpath("recrutement/employe.php?poste=".$id,2);
        $head[$h][1] = $langs->trans("Candidate");
        $head[$h][2] = 'employes';
        $h++;

    return $head;
}



function menu_degrees($id)
{
    global $langs, $conf, $db;
    $langs->load("recruitment@sghr");
    dol_include_once('/sghr/recruitment/class/Candidate.class.php');

    $h = 0;
    $head = array();
        
        $head[$h][0] = dol_buildpath("recrutement/Candidate/degrees/card.php?id=".$id,2);
        $head[$h][1] = $langs->trans("general");
        $head[$h][2] = 'general';
        $h++;

        $majors = new rect_majors($db);
        $majors->fetchAll('','',0,0, 'AND fk_degree='.$id);

        $nb = count($majors->rows);
        $nb = $nb ? $nb : 0; 
        $padge = '<span class="badge marginleftonlyshort">'.$nb.'</span>';

        $head[$h][0] = dol_buildpath("recrutement/Candidate/degrees/indexmajor.php?id_degree=".$id,2);
        $head[$h][1] = $langs->trans("majors").' '.$padge;
        $head[$h][2] = 'majors';
        $h++;

    return $head;
}


