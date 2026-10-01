<?php 

if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);

$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");       // For root directory
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php"); // For "custom" 

dol_include_once('/sghr/recrutement/class/candidatures.class.php');
dol_include_once('/sghr/recrutement/class/departement.class.php');
dol_include_once('/sghr/recrutement/lib/recrutement.lib.php');
dol_include_once('/sghr/recrutement/class/postes.class.php');
dol_include_once('/sghr/recrutement/class/cv.class.php');
dol_include_once('/core/class/html.form.class.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

$langs->load('recrutement@sghr');

$modname = $langs->trans("poste");
// Initial Objects
$poste  = new postes($db);
$formproduct = new FormProduct($db);
// $composante = new composantes($db);
$departement      = new departements($db);
$form        = new Form($db);
$cv        = new cv_recrutement($db);

$candidatures = new candidatures($db);
$societe        = new Societe($db);
// $produitrebut = new produitrebut($db);
// Get parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action         = GETPOST('action', 'alpha');
$etat         = GETPOST('etat', 'alpha');
$page           = GETPOST('page');
$id             = (int) ( (!empty($_GET['id'])) ? $_GET['id'] : GETPOST('id') ) ;



if(!empty($id)){
    $object = new postes($db);
    $object->fetch($id);
    if (!($object->rowid > 0))
    {
        $langs->load("errors");
        print($langs->trans('ErrorRecordNotFound'));
        exit;
    }
} 


global $conf;
    // print_r($user->rights);die;
$error  = false;
if (!$user->rights->sghr->rec->read) {
    accessforbidden();
}

if(in_array($action, ["add","edit"])) {
    if (!$user->rights->sghr->rec->write) {
      accessforbidden();
    }
}
if($action == "delete") {
    if (!$user->rights->sghr->rec->delete) {
      accessforbidden();
    }

}

if($etat == 'finaliser'){
    // $poste->fetch($id);
    $candidatures->fetchAll('','',0,0,' AND poste = '.$id.' AND etape = 5');
    $nb=count($candidatures->rows);

    if($nb>0){
        $poste->update($id,['status'=>'Recrutementfinalise']);
        setEventMessages('Le recrutement est finalisé pour ce poste', null);
    }
    else{
        setEventMessages('Aucun candidat signé une contrat pour ce poste', null, 'warnings');
    }

    header('Location: ./card.php?id='.$id);
    exit();
   
}

if($etat == 'arreter'){
    $poste_id=GETPOST('id');
    $poste->fetch($poste_id);
    $error=$poste->update($poste_id,['status'=>'Recrutementarrete']);
    // die();
    if($error == 1){
        setEventMessages('Le recrutement est arrêté pour ce poste', null);
        $data='ok';
    }
    else{
        setEventMessages('Erreur', null, 'errors');
        $data='Erreur';
    }

    header('Location: ./card.php?id='.$id);
    exit();
   
}

if($etat == 'lancer'){
    $poste_id=GETPOST('id');
    $poste->fetch($poste_id);
    $error=$poste->update($poste_id,['status'=>'Recrutementencours']);
    if($error == 1){
        setEventMessages('Le recrutement est relancé pour ce poste', null, 'mesgs');
        $data='ok';
    }
    else{
        setEventMessages('Erreur', null, 'errors');
        $data='Erreur';
    }

    header('Location: ./card.php?id='.$id);
    exit();
   
}

if (!empty($id) && $action == "pdf") {
    global $langs,$mysoc;

    require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
    require_once dol_buildpath('/sghr/recrutement/pdf/pdf.lib.php');
    
    $object = new postes($db);
    $object->fetch($id);
    
    $pdf->SetFont('times', '', 9, '', true);
    $pdf->AddPage('P');
    $array_format = pdf_getFormat();

    $marge_haute =isset($conf->global->MAIN_PDF_MARGIN_TOP)?$conf->global->MAIN_PDF_MARGIN_TOP:10;
    $marge_basse =isset($conf->global->MAIN_PDF_MARGIN_BOTTOM)?$conf->global->MAIN_PDF_MARGIN_BOTTOM:10;
    $margin = $marge_haute+$marge_basse+45;

    $page_largeur = $formatarray['width'];
    $page_hauteur = $formatarray['height'];
    $format = array($page_largeur,$page_hauteur);

    $marge_gauche=isset($conf->global->MAIN_PDF_MARGIN_LEFT)?$conf->global->MAIN_PDF_MARGIN_LEFT:10;
    $marge_droite=isset($conf->global->MAIN_PDF_MARGIN_RIGHT)?$conf->global->MAIN_PDF_MARGIN_RIGHT:10;
    $marge_haute =isset($conf->global->MAIN_PDF_MARGIN_TOP)?$conf->global->MAIN_PDF_MARGIN_TOP:10;
    $marge_basse =isset($conf->global->MAIN_PDF_MARGIN_BOTTOM)?$conf->global->MAIN_PDF_MARGIN_BOTTOM:10;
    $emetteur = $mysoc;



    $default_font_size = pdf_getPDFFontSize($langs);

    pdf_pagehead($pdf,$langs,$page_hauteur);

    // // Show Draft Watermark
    // if($object->statut==0 && (! empty($conf->global->COMMANDE_DRAFT_WATERMARK)) )
    // {
    //     pdf_watermark($pdf,$langs,$page_hauteur,$page_largeur,'mm',$conf->global->COMMANDE_DRAFT_WATERMARK);
    // }

    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('helvetica','B', $default_font_size + 2);

    $posy=$marge_haute;
    $posx=$page_largeur-$marge_droite-100;

    $pdf->SetXY($marge_gauche,$posy);

    // Logo
    $logo=$conf->mycompany->dir_output.'/logos/'.$emetteur->logo;

    if ($emetteur->logo)
    {
        if (is_readable($logo))
        {
            $height=pdf_getHeightForLogo($logo);
            $pdf->Image($logo, $marge_gauche, $posy, 0, $height); // width=0 (auto)
        }
        else
        {
            $pdf->SetTextColor(200,0,0);
            $pdf->SetFont('helvetica','B', $default_font_size -2);
            $pdf->MultiCell(100, 3, $langs->transnoentities("ErrorLogoFileNotFound",$logo), 0, 'L');
            $pdf->MultiCell(100, 3, $langs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
        }
    }
    else
    {
        $text=$emetteur->name;
        $pdf->MultiCell(40, 2, $langs->convToOutputCharset($text), 0, 'L');
    }


    $pdf->SetFont('helvetica','B', $default_font_size );
    $pdf->SetXY($posx,$posy);
    $pdf->SetTextColor(0,0,0);
    $pdf->MultiCell(100, 3, date("d/m/Y"), '', 'R');

    // $pdf->SetFont('helvetica','B',$default_font_size);
    // $posy+=5;
    // $pdf->SetXY($posx,$posy);
    // $pdf->SetTextColor(0,0,60);
    // $pdf->MultiCell(100, 4, $langs->transnoentities("Objet")." : " . $langs->convToOutputCharset($object->objet), '', 'R');

    //info vehicule
    $pdf->SetFont('helvetica', '', 9, '', true);
    // Sender properties
    $carac_emetteur='';
    // Add internal contact of proposal if defined
    $arrayidcontact=$object->getIdContact('internal','SALESREPFOLL');
  
    // Show sender
    $posy=42+$top_shift;
    $posx=$marge_gauche+5;
    if (! empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx=$page_largeur-$marge_droite;
    $hautcadre=10;

    // Show sender frame
    $pdf->SetFont('helvetica','', $default_font_size - 2);
    $pdf->SetXY($posx,$posy);
    
    $pdf->SetXY($posx,$posy);
    $pdf->SetFillColor(230,230,230);
    $pdf->SetTextColor(0,0,60);

    
    

    $pdf->setPrintFooter(true);
    // require template
    require_once dol_buildpath('/sghr/recrutement/export/show.php');
    // echo $html;
    // die();
    $posy=$pdf->getY();
    $pdf->SetXY($posx,$posy);
    $pdf->writeHTML($html, true, false, true, false, '');
    ob_start();
    $pdf->Output('poste-'.$id.'.pdf', 'I');
    // ob_end_clean();
    die();
}

// ------------------------------------------------------------------------- Actions "Create/Update/Delete"
if ($action == 'create' && $request_method === 'POST') {
    require_once 'z-actions/create.php';
}

if ($action == 'update' && $request_method === 'POST') {
    require_once 'z-actions/edit.php';
}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    require_once 'z-actions/show.php';
}

if ($action == 'confirm_deconstruction' && GETPOST('confirm') == 'yes' ) {
    require_once 'z-actions/edit.php';
}

if ($action == 'confirm_rebut' && GETPOST('confirm') == 'yes' ) {
    require_once 'z-actions/edit.php';
}

$action_export=GETPOST('action_export');
$id_cv=GETPOST('id_cv');


$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);
print_fiche_titre($modname);

$head = menu_poste($id);
if($action != 'add'){
    dol_fiche_head(
        $head,
        'general',
        '', 
        0,
        "recrutement@sghr"
    );
}

// die($action);
// ------------------------------------------------------------------------- Views
if($action == "add")
    require_once 'z-actions/create.php';

if($action == "edit")
    require_once 'z-actions/edit.php';

if( ($id && empty($action)) || $action == "delete" )
    require_once 'z-actions/show.php';

llxFooter();
$db->close();
?>
<style type="text/css">
    div.tabBarWithBottom{
            border-bottom: 0px ;
    }
</style>

<script>
    $(document).ready(function(){
        
        textarea_autosize();
        $('#fk_user').select2();
        
        $('.select_product').select2();
        $('.select2').css('width','100%');
        // $('.quantite').css('width','30%');
        $('#valider').click(function(){
            $id=$('#valider').data('id');
            $.ajax({
                data:{'id_':$id,},
                url:"<?php echo dol_buildpath('/sghr/recrutement/verifierdisponibilite.php?action=valider',2); ?>",
                type:'POST',
                dataType: 'json',
                success:function(data){
                    console.log(data);
                    if(data['msg'] == ''){
                        $('#valider').hide();
                        $('.verification').html('<a id="produire" data-id="'+$id+'" > <?php echo $langs->trans('produire'); ?></a> ');
                        location.reload();
                    }else{
                        location.reload();
                    }
                }
                

            });
        });
       

        $('#arret').click(function(){
            console.log('ddd');
            $id=$('#arret').data('id');
            $.ajax({
                data:{'poste':$id,},
                url:"<?php echo dol_buildpath('/sghr/recrutement/candidatures/info_contact.php?action_=arreter',2); ?>",
                type:'POST',
                success:function($data){
                    if($data == 'Ok'){
                        $('#arret').css('display','none');
                        $('#lancer').css('display','block');
                    }
                        location.reload();
                }
            });
        });


        $('#lancer').click(function(){
            $id=$('#lancer').data('id');
            $.ajax({
                data:{'poste':$id,},
                url:"<?php echo dol_buildpath('/sghr/recrutement/candidatures/info_contact.php?action_=lancer',2); ?>",
                type:'POST',
                success:function($data){
                    if($data == 'Ok'){
                        $('#lancer').css('display','none');
                        $('#arret').css('display','block');
                    }
                        location.reload();
                }
            });
        });

        
        
    });
    
    function delete_tr(tr){
        $(tr).parent().parent().remove();
        if($(tr).data('id')){
            $id=$(tr).data('id')
            $data=$('#composants_deleted').val();
            if ($data =='') {
                $('#composants_deleted').val($id);
            }else
                $('#composants_deleted').val($data+","+$id);
        }
    }
    function change_qt(x) {
        $('#qtt').val($(x).val());
    }
    function change_qt_rebut(x) {
        $('#qtt_rebut').val($(x).val());
    }
    function show_composante(x){
        // if($(x).val()=='Valider'){
        //     $('#listcomposante').show();
        // }
        // else{
        //     $('#listcomposante').hide();
        // }
    }
</script>
<?php

// llxFooter();

// if (is_object($db)) $db->close();
?>