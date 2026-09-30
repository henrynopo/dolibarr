<?php
/* Change Tiers
 * Copyright (C) 2018       Inovea-conseil.com     <info@inovea-conseil.com>
 */
/**
 * \file    admin/setup.php
 * \ingroup Change Tiers
 * \brief   Change Tiers module setup page.
 *
 * 
 */
// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php"))
    $res = @include '../main.inc.php';     // to work if your module directory is into dolibarr root htdocs directory
if (!$res && file_exists("../../main.inc.php"))
    $res = @include '../../main.inc.php';   // to work if your module directory is into a subdir of root htdocs directory
if (!$res && file_exists("../../../main.inc.php"))
    $res = @include '../../../main.inc.php';   // to work if your module directory is into a subdir of root htdocs directory
if (!$res && file_exists("../../../dolibarr/htdocs/main.inc.php"))
    $res = @include '../../../dolibarr/htdocs/main.inc.php';     // Used on dev env only
if (!$res && file_exists("../../../../dolibarr/htdocs/main.inc.php"))
    $res = @include '../../../../dolibarr/htdocs/main.inc.php';   // Used on dev env only
if (!$res)
    die("Include of main fails");

global $langs, $user;
// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/changetiers.lib.php';
// Translations
$langs->load("changetiers@changetiers");
$langs->load("admin");
// Access control
if (! $user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');
if ($action == 'setvalue' && $user->admin)
{
    $error='';

    $db->begin();
    $result=dolibarr_set_const($db, "PROPAL_CHANGE_THIRDPARTY",GETPOST('PROPAL_CHANGE_THIRDPARTY','alpha'),'yesno',0,'',$conf->entity);
    if (! $result > 0) $error++;
    $result=dolibarr_set_const($db, "COMMANDE_CHANGE_THIRDPARTY",GETPOST('COMMANDE_CHANGE_THIRDPARTY','alpha'),'yesno',0,'',$conf->entity);
    if (! $result > 0) $error++;
    $result=dolibarr_set_const($db, "SHIPPING_CHANGE_THIRDPARTY",GETPOST('SHIPPING_CHANGE_THIRDPARTY','alpha'),'yesno',0,'',$conf->entity);
    if (! $result > 0) $error++;
    $result=dolibarr_set_const($db, "CONTRAT_CHANGE_THIRDPARTY",GETPOST('CONTRAT_CHANGE_THIRDPARTY','alpha'),'yesno',0,'',$conf->entity);
    if (! $result > 0) $error++;
    $result=dolibarr_set_const($db, "FACTURE_CHANGE_THIRDPARTY_NOT_ONLY_DRAFT",GETPOST('FACTURE_CHANGE_THIRDPARTY_NOT_ONLY_DRAFT','alpha'),'yesno',0,'',$conf->entity);
    if (! $result > 0) $error++;
    if(!empty(GETPOST('FACTURE_CHANGE_THIRDPARTY_NOT_ONLY_DRAFT','alpha'))) { //if option is selected for any other status than draft, we auto select this option
        $result=dolibarr_set_const($db, "FACTURE_CHANGE_THIRDPARTY",1,'yesno',0,'',$conf->entity);
        if (! $result > 0) $error++;
    }else{
        $result=dolibarr_set_const($db, "FACTURE_CHANGE_THIRDPARTY",GETPOST('FACTURE_CHANGE_THIRDPARTY','alpha'),'yesno',0,'',$conf->entity);
        if (! $result > 0) $error++;
    }

    $result=dolibarr_set_const($db, "SUPPLIER_PROPOSAL_CHANGE_THIRDPARTY",GETPOST('SUPPLIER_PROPOSAL_CHANGE_THIRDPARTY','alpha'),'yesno',0,'',$conf->entity);
    if (! $result > 0) $error++;
    $result=dolibarr_set_const($db, "ORDER_SUPPLIER_CHANGE_THIRDPARTY",GETPOST('ORDER_SUPPLIER_CHANGE_THIRDPARTY','alpha'),'yesno',0,'',$conf->entity);
    if (! $result > 0) $error++;
    $result=dolibarr_set_const($db, "RECEPTION_CHANGE_THIRDPARTY",GETPOST('RECEPTION_CHANGE_THIRDPARTY','alpha'),'yesno',0,'',$conf->entity);
    if (! $result > 0) $error++;
    $result=dolibarr_set_const($db, "INVOICE_SUPPLIER_CHANGE_THIRDPARTY",GETPOST('INVOICE_SUPPLIER_CHANGE_THIRDPARTY','alpha'),'yesno',0,'',$conf->entity);
    if (! $result > 0) $error++;
    $result=dolibarr_set_const($db, "EVENT_CHANGE_THIRDPARTY",GETPOST('EVENT_CHANGE_THIRDPARTY','alpha'),'yesno',0,'',$conf->entity);
    if (! $result > 0) $error++;

    if (! $error) {
        $db->commit();
    } else {
        $db->rollback();
        dol_print_error($db);
    }
}
/*
 * View
 */
$page_name = "changetiersSetup";
llxHeader('', $langs->trans($page_name));
// Subheader
$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');
print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setvalue">';
// Configuration header
$head = changetiersPrepareHead();
dol_fiche_head(
	$head,
	'settings',
	$langs->trans("Module432446Name"),
	0,
	"inoveaconseil@changetiers"
);

print '<table class="noborder" width="100%">';
$var=true;
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Customers").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print "</tr>\n";

$var=!$var;
print '<tr '.$bc[$var].'><td>';
print $langs->trans("PROPAL_CHANGE_THIRDPARTY").'</td><td>';
print '<input size="64" type="hidden" name="PROPAL_CHANGE_THIRDPARTY" value="0">';
print '<input size="64" type="checkbox" name="PROPAL_CHANGE_THIRDPARTY" value="1" ';
print $conf->global->PROPAL_CHANGE_THIRDPARTY ? 'checked="checked"' : '';
print '>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'><td>';
print $langs->trans("COMMANDE_CHANGE_THIRDPARTY").'</td><td>';
print '<input size="64" type="hidden" name="COMMANDE_CHANGE_THIRDPARTY" value="0">';
print '<input size="64" type="checkbox" name="COMMANDE_CHANGE_THIRDPARTY" value="1" ';
print $conf->global->COMMANDE_CHANGE_THIRDPARTY ? 'checked="checked"' : '';
print '>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'><td>';
print $langs->trans("SHIPPING_CHANGE_THIRDPARTY").'</td><td>';
print '<input size="64" type="hidden" name="SHIPPING_CHANGE_THIRDPARTY" value="0">';
print '<input size="64" type="checkbox" name="SHIPPING_CHANGE_THIRDPARTY" value="1" ';
print $conf->global->SHIPPING_CHANGE_THIRDPARTY ? 'checked="checked"' : '';
print '>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'><td>';
print $langs->trans("CONTRAT_CHANGE_THIRDPARTY").'</td><td>';
print '<input size="64" type="hidden" name="CONTRAT_CHANGE_THIRDPARTY" value="0">';
print '<input size="64" type="checkbox" name="CONTRAT_CHANGE_THIRDPARTY" value="1" ';
print $conf->global->CONTRAT_CHANGE_THIRDPARTY ? 'checked="checked"' : '';
print '>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'><td>';
print $langs->trans("FACTURE_CHANGE_THIRDPARTY").'</td><td>';
print '<input size="64" type="hidden" name="FACTURE_CHANGE_THIRDPARTY" value="0">';
print '<input size="64" type="checkbox" name="FACTURE_CHANGE_THIRDPARTY" value="1" ';
print $conf->global->FACTURE_CHANGE_THIRDPARTY ? 'checked="checked"' : '';
print '>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'><td>';
print $langs->trans("FACTURE_CHANGE_THIRDPARTY_NOT_ONLY_DRAFT").'</td><td>';
print '<input size="64" type="hidden" name="FACTURE_CHANGE_THIRDPARTY_NOT_ONLY_DRAFT" value="0">';
print '<input size="64" type="checkbox" name="FACTURE_CHANGE_THIRDPARTY_NOT_ONLY_DRAFT" value="1" ';
print $conf->global->FACTURE_CHANGE_THIRDPARTY_NOT_ONLY_DRAFT ? 'checked="checked"' : '';
print '>';
print '</td></tr>';


$var=true;
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Suppliers").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print "</tr>\n";

$var=!$var;
print '<tr '.$bc[$var].'><td>';
print $langs->trans("SUPPLIER_PROPOSAL_CHANGE_THIRDPARTY").'</td><td>';
print '<input size="64" type="hidden" name="SUPPLIER_PROPOSAL_CHANGE_THIRDPARTY" value="0">';
print '<input size="64" type="checkbox" name="SUPPLIER_PROPOSAL_CHANGE_THIRDPARTY" value="1" ';
print $conf->global->SUPPLIER_PROPOSAL_CHANGE_THIRDPARTY ? 'checked="checked"' : '';
print '>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'><td>';
print $langs->trans("ORDER_SUPPLIER_CHANGE_THIRDPARTY").'</td><td>';
print '<input size="64" type="hidden" name="ORDER_SUPPLIER_CHANGE_THIRDPARTY" value="0">';
print '<input size="64" type="checkbox" name="ORDER_SUPPLIER_CHANGE_THIRDPARTY" value="1" ';
print $conf->global->ORDER_SUPPLIER_CHANGE_THIRDPARTY ? 'checked="checked"' : '';
print '>';
print '</td></tr>';

if ((float) DOL_VERSION >= '14') {
    $var = !$var;
    print '<tr ' . $bc[$var] . '><td>';
    print $langs->trans("RECEPTION_CHANGE_THIRDPARTY") . '</td><td>';
    print '<input size="64" type="hidden" name="RECEPTION_CHANGE_THIRDPARTY" value="0">';
    print '<input size="64" type="checkbox" name="RECEPTION_CHANGE_THIRDPARTY" value="1" ';
    print $conf->global->RECEPTION_CHANGE_THIRDPARTY ? 'checked="checked"' : '';
    print '>';
    print '</td></tr>';
}

$var=!$var;
print '<tr '.$bc[$var].'><td>';
print $langs->trans("INVOICE_SUPPLIER_CHANGE_THIRDPARTY").'</td><td>';
print '<input size="64" type="hidden" name="INVOICE_SUPPLIER_CHANGE_THIRDPARTY" value="0">';
print '<input size="64" type="checkbox" name="INVOICE_SUPPLIER_CHANGE_THIRDPARTY" value="1" ';
print $conf->global->INVOICE_SUPPLIER_CHANGE_THIRDPARTY ? 'checked="checked"' : '';
print '>';
print '</td></tr>';

if (isset($user->rights->changetiers) && is_object($user->rights->changetiers) && !empty($user->rights->changetiers->event)) {
    $var=true;
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("GlobalParams").'</td>';
    print '<td>'.$langs->trans("Value").'</td>';
    print "</tr>\n";


    $var=!$var;
    print '<tr '.$bc[$var].'><td>';
    print $langs->trans("EVENT_CHANGE_THIRDPARTY").'</td><td>';
    print '<input size="64" type="hidden" name="EVENT_CHANGE_THIRDPARTY" value="0">';
    print '<input size="64" type="checkbox" name="EVENT_CHANGE_THIRDPARTY" value="1" ';
    print $conf->global->EVENT_CHANGE_THIRDPARTY ? 'checked="checked"' : '';
    print '>';
    print '</td></tr>';
}

print '</table>';
// Page end
dol_fiche_end();
print '<div class="center"><input type="submit" class="button" value="'.$langs->trans("Modify").'"></div>';
print '</form>';

llxFooter();