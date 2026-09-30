<?php
/*
 * Copyright (C) 2016  Yassine Belkaid <y.belkaid@nextconcept.ma>
 *
 */

/**
 *     \file       htdocs/marches/admin/admin_setup.php
 *     \ingroup    marches
 *     \brief      Page de configuration du module marches
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 

if (!$user->admin) accessforbidden();

$action = GETPOST('action','alpha');

/*
 * Actions
 */

/*
 * View
 */

llxHeader();
$form=new Form($db);

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans('MainTopTitleSetupCS'),$linkback,'title_setup');

dol_fiche_head();


print '<table class="noborder" width="100%">';

// Cas des parametres TAX_MODE_SELL/BUY_SERVICE/PRODUCT
print '<form action="'.$_SERVER["PHP_SELF"].'" method="post">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';

print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans('MainTopTitleCS').'</td>';
print "</tr>\n";
print '<td colspan="2">'. nl2br($langs->trans('MainTopTitleSetupSentenceCS')) ."</td></tr>\n";
print "</table>\n";

print "<br>\n";


$db->close();

llxFooter();
