<?php 

$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");       // For root directory
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php"); // For "custom" 

dol_include_once('/sghr/cv/class/ecv.class.php');
dol_include_once('/sghr/cv/class/CvSkill.class.php');
dol_include_once('/sghr/cv/class/CvLanguage.class.php');
dol_include_once('/sghr/cv/class/CvExperience.class.php');
dol_include_once('/sghr/cv/class/CvEducation.class.php');
dol_include_once('/sghr/cv/class/CvCertificate.class.php');
dol_include_once('/sghr/cv/class/CvQualification.class.php');
dol_include_once('/sghr/cv/class/CvLicense.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/sghr/cv/lib/cv.lib.php');


// dol_include_once('/projet/class/project.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

//
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

$langs->load('cv@sghr');

$modname = $langs->trans("ecv2");

// Initial Objects
$cvProfile  = new Cv($db);
$CvLanguage  = new CvLanguage($db);
$CvSkill  = new CvSkill($db);
$Skill  = new Skill($db);
$CvCertificate  = new CvCertificate($db);
$CvEducation  = new CvEducation($db);
$CvExperience  = new CvExperience($db);
$CvQualification  = new CvQualification($db);
$CvLicense  = new CvLicense($db);
$form        = new Form($db);
$user_cv = new User($db);
require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
$adherent_cv = new Adherent($db);

// Get parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action         = GETPOST('action', 'alpha');
$page           = GETPOST('page');
$id             = (int) ( (!empty($_GET['id'])) ? $_GET['id'] : GETPOST('id') ) ;

$error  = false;
if (!$user->rights->sghr->cv->read) {
    accessforbidden();
}

if(in_array($action, ["add","edit"])) {
    if (!$user->rights->sghr->cv->write) {
      accessforbidden();
    }
}
if($action == "delete") {
    if (!$user->rights->sghr->cv->delete) {
      accessforbidden();
    }
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

$action_export=GETPOST('action_export');
$id_cv=GETPOST('id_cv');

// $id_cv='cv_4';
if (!empty($id) && $action_export == "pdf") {
    global $langs,$mysoc;
    require_once dol_buildpath('/sghr/cv/pdf/pdf.lib.php');
    // print '<link rel="stylesheet" href= "'.dol_buildpath('/sghr/cv/Skill/css/rating.css',2).'">';
    global $conf;
    if($id_cv=='cv_1'){
        $pdf->SetAutoPageBreak(TRUE,20);
        $pdf->setPrintFooter(true);
        $pdf->SetFooterMargin(18);
        $pdf->SetMargins(12, 12, 12, false);
    }

    if($id_cv=='cv_3'){
        $pdf->SetFooterMargin(18);
        $pdf->SetAutoPageBreak(TRUE,20);
        $pdf->SetMargins(0, 0, 0, true);
    }

    if($id_cv=='cv_2'){
        $pdf->SetMargins(0, 5, 0, false);
        $pdf->SetFooterMargin(18);
        $pdf->setPrintFooter(true);
        $pdf->SetAutoPageBreak(TRUE,20);

    }

    $height=$pdf->getPageHeight();
    $pdf->SetFont('helvetica', '', 9, '', true);
    $pdf->AddPage('P');
    $margint=$pdf->getMargins()['top'];
    $marginb=$pdf->getMargins()['bottom'];
    $array_format = pdf_getFormat();
    $cvProfile->fetch($id);
    $item = $cvProfile;
    $object=$cvProfile;
    
    require_once dol_buildpath('/sghr/cv/export/export_'.$id_cv.'.php');
    $html.='<style> table.info_user{height:'.$height.'mm;} .td-2{height:35mm;} .td-4{height:'.($height-76).'mm;} .td-1{height:35mm;} #photo_user{border-radius: 50%; border: 1px solid red;}</style>';
    $html.='<style>  </style>';
    $html.='<style> </style>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    ob_start();
    $pdf->Output('E-cv.pdf', 'I');
    // ob_end_clean();
    die();

}


/* ------------------------ View ------------------------------ */

$morejs  = array();
// llxHeader(array(), $modname,'','','','',$morejs,0,0);

// print_fiche_titre($modname);


// Call JS.php
// require_once 'script/js.php';

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

$head = ecvAdminPrepareHead($id);
print_fiche_titre($modname);
// if($action != 'add'){
    dol_fiche_head(
        $head,
        'ecv',
        '', 
        0,
        "cv@sghr"
    );
// }


// ------------------------------------------------------------------------- Views
if($action == "add")
    require_once 'z-actions/create.php';

if($action == "edit")
    require_once 'z-actions/edit.php';

if( ($id && empty($action)) || $action == "delete" )
    require_once 'z-actions/show.php';

?>


<script>
    
    $(document).ready(function(){
        $('.lightbox_trigger').click(function(e) {
            e.preventDefault();
            var image_href = $(this).attr("href");
            $('#lightbox #content').html('<img src="' + image_href + '" />');
            $('#lightbox').show();
        });
        $('#lightbox,#lightbox p').click(function() {
            $('#lightbox').hide();
        });
    });
</script>
<?php

llxFooter();

if (is_object($db)) $db->close();
?>