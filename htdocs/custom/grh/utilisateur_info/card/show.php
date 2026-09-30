<?php 
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
dol_include_once('/grh/utilisateur_info/class/utilisateur_info.class.php');
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');

$moduleName = "Infos des salariés";

// Get parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action  = GETPOST('action', 'alpha');
$form   = new Form($db);
$htmlother      = new FormOther($db);

$user_info  = new utilisateur_info($db);
$user_id = GETPOST('user_id');
$user_id      = (int) ( (!empty($_GET['user_id'])) ? $_GET['user_id'] : GETPOST('user_id') ) ;


llxHeader(array(), $langs->trans($moduleName),'','','','',$morejs,$morecss,0,0);
print_fiche_titre($langs->trans($moduleName));


$u = $user_info->fetch($user_id);
print '<form method="POST"  action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">';
print '<input type="hidden" name="action" value="update" />';
print '<input type="hidden" name="user_id" value="'.$user_id.'" />';
   print '<table width="100%" class="border">';
print '<tr class="liste_titre" >';
print '<th colspan="2" align="center">Modifier</th>';
print '</tr>';
print '<tbody>';

    print '<tr>';
        print '<td width="150px">Utilisateur</td>';
        print '<td>';
        print '<img src="'.dol_buildpath('/grh/img/object_user.png',1).'" class="classfortooltip">';
        print '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.$u->rowid.'">'.$u->firstname.' '.$u->lastname.'</a>';
        print '</td>';
    print '</tr>';

    // salary_base
    print '<tr>';
    print '<td>'.$langs->trans("salary_base").'</td>';
    print '<td>'.number_format($u->salary_base,2,",","").'</td>';
    print "</tr>";

    // CIN
    print '<tr><td>CIN</td>';
    print '<td>';
    print $u->cin;
    print '</td>';
    print "</tr>";

    // immatriculation
    print '<tr><td>Immatriculation de la CNSS</td>';
    print '<td>';
    print $u->immatriculation;
    print '</td>';
    print "</tr>";

    // Stagaire
    print "<tr>";
    print '<td>Stagaire</td>';
    print '<td>';
        if($u->stag == 0){
            print 'Non';
        }else{
            print 'Oui';
        }
        print '</td></tr>';
    print '</td>';
    print '</tr>';
    // if ($u->stag==1) 
    // {
    //     print '<tr><td >Date Début</td><td>  '.dol_print_date($u->datec,'day').'</td></tr>';
    //     print '</td></tr><tr><td >Date Fin</td><td>  '.dol_print_date($u->datef,'day').'</td></tr>';
    // }
    // if($u->stag==0){
        print '<tr><td>Salarié déclaré</td><td>';
        if($u->declar == 0){
            print 'Non';
        }else{
            print 'Oui';
        }
        print '</td></tr>';
    // }

    print '<tr><td>Matricule</td><td>';
    print $u->mat;
    print '</td></tr>';

    // Nombre des jours de congé
    print '<tr><td>Nombre des jours de congé</td><td>';
    print $u->nb_holiday;
    print '</td></tr>';

    // Date d\'embauche
    print '<tr><td>Date d\'embauche</td><td>';
    if (!empty($u->date_embauche) && $u->date_embauche != 0 ) {
        print $u->date_embauche;
    }
    print '</td></tr>';

    // Etablissement
    print '<tr><td>Etablissement</td><td>';
    print $u->etablissement;
    print '</td></tr>';
    print '<tr><td>Etablissement option</td><td>';
    print $u->etablissement_opt;
    print '</td></tr>';

    print '<tr id="declar_slct"><td>Situation familiale</td><td>';
    if($u->situation_familiale == 0){
        print 'Célibataire';
    }elseif($u->situation_familiale == 1){
        print 'Marié(e)';
    }
    print '</td></tr>';

    print '<tr><td>Nombre d\'enfant</td><td>';
    print $u->nbr_enfants;
    print '</td></tr>';

    // Situation assuré
    print '<tr><td>Situation assuré</td>';
    print '<td>';
    $situatons = array(1 => "Sortant", 2 => "Decédé", 3 => "Maternité", 4 => "Maladie", 5 => "Accident de travail", 6 => "Congé Sans salaire", 7 => "Maintenu Sans Salaire", 8 => "Maladie Professionnelle");
    print $situatons[$u->situation_assure];
    print '</td>';
    print "</tr>\n";

    // print '<tr><td>Classement</td><td>';
    // print $u->clas;
    // print '</td></tr>';

print '</tbody>';
print '</table>'; 
print '<a href="../card/index.php?action=edit&user_id='.$u->rowid.'" class="butAction">Modifier</a>';
print '<a href="../index.php" class="butAction">Annuler</a>';
print  '</form>';

?>