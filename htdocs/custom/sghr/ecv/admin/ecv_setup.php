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
 *  \file       admin/ecv.php
 *  \ingroup    ecv
 *  \brief      This file is an example module setup page
 *              Put some comments here
 */
// Dolibarr environment
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/ecv.lib.php';
dol_include_once('/sghr/ecv/class/ecv.class.php');

$ecv  = new ecv($db);
// Translations
$langs->load("ecv@sghr");
$langs->load("propal");
$langs->load("admin");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');


if ($action == 'ecv_GenerateCVFor') {
    $error = 0;

    $name = 'ECV_GENERATE_CV_FOR_ADHERENTS';
    if (isset($_POST[$name])){
        $res = dolibarr_set_const($db, $name, 1, 'chaine', 0, '', 0);
    }else{
        $res = dolibarr_set_const($db, $name, 0, 'chaine', 0, '', 0);
    }
    if (! $res > 0) $error ++;


    if (! $error) {
        setEventMessage($langs->trans("SetupSaved"), 'mesgs');
    } else {
        setEventMessage($langs->trans("Error"), 'errors');
    }
}







/*
 * View
 */
$page_name = "ecvSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
// $head = ecvAdminPrepareHead();
// dol_fiche_head(
//     $head,
//     'Proposals',
//     $langs->trans("ecv"),
//     0,
//     "ecv@sghr"
// );

// Setup page goes here
$form=new Form($db);
$var=false;


$MenuMembers=$conf->global->ECV_GENERATE_CV_FOR_ADHERENTS;

// print_r($status);
print '<form id="col4-form" method="post" action="ecv_setup.php">';
print '<input type="hidden" name="action" value="ecv_GenerateCVFor">';
print '<table class="noborder" width="100%">';

print '<tr>';
    print '<td style="width:200px;">';
            print $langs->trans("ecv_GenerateCVFor");
    print '</td>';
    print '<td class="generatecvforcheck">';
        print '<label>';
        print '<input type="checkbox" name="USERS" checked disabled value="USERS"> '.$langs->trans("Users");
        print '</label>';
        print '<label>';
        $chkd = "";
        if(isset($MenuMembers) && $MenuMembers > 0){
            $chkd = "checked";
        }
        print '<input type="checkbox" name="ECV_GENERATE_CV_FOR_ADHERENTS" '.$chkd.' value="ADHERENTS"> '.$langs->trans("MenuMembers");
        print '</label>';
    print '</td>';
    // print '<td>';
    // print '<input type="submit" class="button" value="Valider">';
    // print '</td>';
print '</tr>';

print '</table>';
print '<br><div style="text-align:left;"><input type="submit" class="butAction" value="'.$langs->trans("Validate").'"></div>';
print '</form>';



llxFooter();

$db->close();


