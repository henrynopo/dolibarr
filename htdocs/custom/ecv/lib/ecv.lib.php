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
 *	\file		lib/ecv.lib.php
 *	\ingroup	ecv
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function ecvAdminPrepareHead($id_ecv)
{
    global $langs, $conf, $db;
    $langs->load("ecv@ecv");

    $h = 0;
    $head = array();
	dol_include_once('/ecv/class/ecvcompetances.class.php');
	dol_include_once('/ecv/class/ecv.class.php');
    if(!empty($id_ecv)){
        $competances = new ecvcompetances($db);
        $head[$h][0] = dol_buildpath("/ecv/card.php?id=".$id_ecv, 2);
        $head[$h][1] = $langs->trans("e_cv");
        $head[$h][2] = 'ecv';
        $h++;
        $head[$h][0] = dol_buildpath("/ecv/experiences/index.php?id_ecv=".$id_ecv,2);
        $head[$h][1] = $langs->trans("ecv_experiences");
        $head[$h][2] = 'experiences';
        $h++;
        $head[$h][0] = dol_buildpath("/ecv/formations/index.php?id_ecv=".$id_ecv,2);
        $head[$h][1] = $langs->trans("ecv_formations");
        $head[$h][2] = 'formations';
        $h++;
        $head[$h][0] = dol_buildpath("/ecv/certificats/index.php?id_ecv=".$id_ecv,2);
        $head[$h][1] = $langs->trans("ecv_certificats");
        $head[$h][2] = 'certificats';
        $h++;
        $head[$h][0] = dol_buildpath("/ecv/qualifications/index.php?id_ecv=".$id_ecv,2);
        $head[$h][1] = $langs->trans("ecv_qualifications");
        $head[$h][2] = 'qualifications';
        $h++;
        $head[$h][0] = dol_buildpath("/ecv/competances/index.php?id_ecv=".$id_ecv,2);
        $head[$h][1] = $langs->trans("ecv_competences");
        $head[$h][2] = 'competances';
        $h++;
        $head[$h][0] = dol_buildpath("/ecv/langues/index.php?id_ecv=".$id_ecv,2);
        $head[$h][1] = $langs->trans("ecv_langues");
        $head[$h][2] = 'langues';
        $h++;
        $head[$h][0] = dol_buildpath("/ecv/permis/index.php?id_ecv=".$id_ecv,2);
        $head[$h][1] = $langs->trans("ecv_permis_circulations");
        $head[$h][2] = 'permis';
        $h++;
    }
    else{
        $head[$h][0] = dol_buildpath("/ecv/card.php?action=add", 2);
        $head[$h][1] = $langs->trans("e_cv");
        $head[$h][2] = 'ecv';
        $h++;
    }
    return $head;
}


