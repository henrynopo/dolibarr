<?php
	$image_file = 'check.png';

        if (!file_exists($image_file)) {
            $image_file = '';
        }
 dol_include_once('/grh/medical/antpersnl/class/antpersnl.class.php');
 dol_include_once('/grh/medical/vacination/class/vacination.class.php');
 dol_include_once('/grh/medical/antfamily/class/antfamily.class.php');
dol_include_once('/grh/medical/exmclinic/class/exmclinic.class.php');
dol_include_once('/grh/medical/aparielres/class/aparielres.class.php');
dol_include_once('/grh/medical/aparielcir/class/aparielcir.class.php');
dol_include_once('/grh/medical/aparieldig/class/aparieldig.class.php');
dol_include_once('/grh/medical/aparielgu/class/aparielgu.class.php');
dol_include_once('/grh/medical/apariellm/class/apariellm.class.php');
dol_include_once('/grh/medical/aparielend/class/aparielend.class.php');
dol_include_once('/grh/medical/neropsy/class/neropsy.class.php');
dol_include_once('/grh/medical/exmcomp/class/exmcomp.class.php');
dol_include_once('/grh/medical/exmcontrl/class/exmcontrl.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($userm->table_element);

$situation_fa = $userm->situation_familiale;

 $situation_fa = $extrafields->showOutputField('situation_familiale',$situation_fa);
$daten = dol_print_date($medical->daten,'day') ;
$html = <<<EOD
<br/><br/>
<table border="0"  >
<tr>
<td width="50%">NOM/PRÉNOM : $userm->lastname $userm->firstname</td>
<td width="50%">POSTE DE TRAVAIL : $userm->job</td>
</tr>
<tr>
<td width="50%">DATE DE NAISSANCE : $daten</td>
<td width="50%">RISQUES : $medical->risques</td>
</tr>
<tr>
<td width="50%">ADRESSE : $medical->adress</td>
<td width="50%"></td>
</tr>
<tr>
<td width="50%">SITUATION FAMILIALE : $situation_fa</td>
</tr>
</table>
<br /><br />
<hr>
<br /><br />
<h2 align="center"><u> ANTÉCÉDANTS PERSONNELS </u></h2>
EOD;

$antpersnl 	  = new antpersnl($db);
$test = $antpersnl->fetchbyDI($medical->id);

	$html .= '<table border="0" width="100%">';
	$html .= '<tr ><td>MALADIES : '.((!empty($test) ) ? $antpersnl->malades : '').'</td></tr>';
    $html .= '<tr ><td>INTERVENTIONS CHIRURGICALES : '.((!empty($test) ) ?$antpersnl->interviews: '').'</td></tr>';
    $html .= '<tr ><td>ACCIDENTS DU TRAVAIL : '.((!empty($test) ) ?$antpersnl->accidents: '').'</td></tr>';
    $html .= '<tr ><td>MALADIES PROFESSIONNELLES : '.((!empty($test) ) ?$antpersnl->malad_pro: '').'</td></tr>';
    $html .= '<tr ><td>HABITUDES TOXIQUES : '.((!empty($test) ) ?$antpersnl->habitud: '').'</td></tr>';
    $html .= '</table>';

$html .= '<br /><h2 align="center"><u> VACCINATIONS </u></h2>';

$vacination 	  = new vacination($db);
$test = $vacination->fetchbyDI($medical->id);

	$html .= '<table border="0" width="100%">';
	$html .= '<tr ><td>'.((!empty($test) ) ? $vacination->description : '').'</td></tr>';
    $html .= '</table>';

$html .= '<br /><h2 align="center"><u> ANTÉCÉDANTS FAMILIAUX </u></h2>';
$antfamily 	  = new antfamily($db);
$test = $antfamily->fetchbyDI($medical->id);

	$html .= '<table border="0" width="100%">';
	$html .= '<tr ><td>'.((!empty($test) ) ? $antfamily->description : '').'</td></tr>';
    $html .= '</table>';

$html .= '<br /><h2 align="center"><u> EXAMENS CLINIQUES </u></h2>';
$exmclinic 	  = new exmclinic($db);
$test = $exmclinic->fetchbyDI($medical->id);

	$html .= '<table border="0" width="100%">';
	$html .= '<tr ><td>POIDS : '.((!empty($test) ) ? $exmclinic->poid.' kg' : '').'</td>';
    $html .= '<td>TAILLE : '.((!empty($test) ) ?$exmclinic->taille.' m': '').'</td></tr>';
    $html .= '<tr ><td>VISION : '.((!empty($test) ) ?$exmclinic->vision: '').'</td>';
    $html .= '<td>AUDITION : '.((!empty($test) ) ?$exmclinic->audition: '').'</td></tr>';
    $html .= '<tr ><td>DENTURE : '.((!empty($test) ) ?$exmclinic->denture: '').'</td>';
    $html .= '<td>PEAUX/PHANÈRES : '.((!empty($test) ) ?$exmclinic->peux: '').'</td></tr>';
    $html .= '</table>';

$html .= '<br /><h2 align="center"><u> APPAREIL RESPIRATOIRE </u></h2>';
$aparielres 	  = new aparielres($db);
$test = $aparielres->fetchbyDI($medical->id);

	$html .= '<table border="0" width="100%">';
	$html .= '<tr ><td>EXAMEN CLINIQUE : '.((!empty($test) ) ? $aparielres->examenc : '').'</td>';
    $html .= '<td>EXAMEN RADIOLOGIQUE : '.((!empty($test) ) ?$aparielres->examenr: '').'</td></tr>';
    $html .= '</table>';

$html .= '<br /><h2 align="center"><u> APPAREIL CIRCULAIRE </u></h2>';
$aparielcir 	  = new aparielcir($db);
$test = $aparielcir->fetchbyDI($medical->id);

	$html .= '<table border="0" width="100%">';
	$html .= '<tr ><td>COEUR : '.((!empty($test) ) ? $aparielcir->coeur : '').'</td>';
    $html .= '<td>T.A: '.((!empty($test) ) ?$aparielcir->ta: '').'</td></tr>';
    $html .= '<tr ><td>VAISSEAUX/POULS : '.((!empty($test) ) ?$aparielcir->vaisc: '').'</td>';
    $html .= '<td>GONGLION : '.((!empty($test) ) ?$aparielcir->cong: '').'</td></tr>';
    $html .= '<tr ><td>VARICES : '.((!empty($test) ) ?$aparielcir->varic: '').'</td>';
    $html .= '<td>E.C.G '.((!empty($test) ) ?$aparielcir->egg: '').'</td></tr>';
    $html .= '<tr ><td>AUTRES EXAMENS : '.((!empty($test) ) ?$aparielcir->otherex: '').'</td></tr>';
    $html .= '</table>';

$html .= '<br /><h2 align="center"><u> APPAREIL DIGESTIF </u></h2>';
$aparieldig 	  = new aparieldig($db);
$test = $aparieldig->fetchbyDI($medical->id);

	$html .= '<table border="0" width="100%">';
	$html .= '<tr ><td>BOUCHE : '.((!empty($test) ) ? $aparieldig->bouch : '').'</td></tr>';
    $html .= '<tr ><td>AMYGDALS : '.((!empty($test) ) ?$aparieldig->amyg: '').'</td></tr>';
    $html .= '<tr ><td>ABDOMEN : -FOIE :'.((!empty($test) ) ?$aparieldig->foie: '').'</td></tr>';
    $html .= '<tr ><td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-RATE : '.((!empty($test) ) ?$aparieldig->rate: '').'</td></tr>';
    $html .= '<tr ><td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-HERNIES : '.((!empty($test) ) ?$aparieldig->hern: '').'</td></tr>';
    $html .= '</table>';

$html .= '<br /><h2 align="center"><u> APPAREIL GÉNITO-URINAIRE </u></h2>';
$aparielgu 	  = new aparielgu($db);
$test = $aparielgu->fetchbyDI($medical->id);

	$html .= '<table border="0" width="100%">';
	$html .= '<tr ><td>EXAMEN CLINIQUE : '.((!empty($test) ) ? $aparielgu->examenc : '').'</td>';
    $html .= '<td>LABSTRIX : '.((!empty($test) ) ?$aparielgu->labstrix: '').'</td></tr>';
    $html .= '</table>';

$html .= '<br /><h2 align="center"><u> APPAREIL LOCO-MOTEUR </u></h2>';
$apariellm 	  = new apariellm($db);
$test = $apariellm->fetchbyDI($medical->id);

	$html .= '<table border="0" width="100%">';
	$html .= '<tr ><td>'.((!empty($test) ) ? $apariellm->description : '').'</td></tr>';
    $html .= '</table>';

$html .= '<br /><h2 align="center"><u> APPAREIL ENDOCRINIEN </u></h2>';
$aparielend 	  = new aparielend($db);
$test = $aparielend->fetchbyDI($medical->id);

	$html .= '<table border="0" width="100%">';
	$html .= '<tr ><td>'.((!empty($test) ) ? $aparielend->description : '').'</td></tr>';
    $html .= '</table>';

$html .= '<br /><h2 align="center"><u> NEURO-PSYCHISME </u></h2>';
$neropsy 	  = new neropsy($db);
$test = $neropsy->fetchbyDI($medical->id);

	$html .= '<table border="0" width="100%">';
	$html .= '<tr ><td>ATCD : '.((!empty($test) ) ? $neropsy->atcd : '').'</td></tr>';
    $html .= '<tr ><td>ÉQUILIBRE S.DE REMBERG : '.((!empty($test) ) ?$neropsy->aquilibr: '').'</td></tr>';
    $html .= '<tr ><td>TREMBLEMENT '.((!empty($test) ) ?$neropsy->trembl: '').'</td></tr>';
    $html .= '<tr ><td>REFLESUS : '.((!empty($test) ) ?$neropsy->refles: '').'</td></tr>';
    $html .= '<tr ><td>E.E.G : '.((!empty($test) ) ?$neropsy->eeg: '').'</td></tr>';
    $html .= '</table>';

$html .= '<br /><h2 align="center"><u> EXAMENS COMPLÉMENTAIRES </u></h2>';
$exmcomp 	  = new exmcomp($db);
$test = $exmcomp->fetchbyDI($medical->id);

	$html .= '<table border="0" width="100%">';
	$html .= '<tr ><td>'.((!empty($test) ) ? $exmcomp->description : '').'</td></tr>';
    $html .= '</table>';
$exmcontrl 	  = new exmcontrl($db);
$html .= '<br /><h2 align="center"><u> CONCLUSION </u></h2>';
$apte = $exmcontrl->getAptebyDI($medical->id);
$crossed = "background-image: linear-gradient(to bottom right,  transparent calc(50% - 1px), black, transparent calc(50% + 1px)";

$html .= '<table align="center" border="0" width="100%">';
$html .= '<tr ><td align="center">';
	$html .= '<table align="left" border="1" width="40%">';
	$html .= '<tr ><td align="center">APTE';
		if($apte != -1  && $apte==1)
             $html .='<br/><img src="'.$image_file.'" alt="">';
    	
	$html .='</td></tr>';
    $html .= '</table>';
$html .= '</td><td width="55%" align="center">&nbsp;</td>';
$html .= '<td align="right">';
	$html .= '<table align="right" border="1" width="40%">';
	$html .= '<tr ><td align="center">INAPTE';
	if($apte != -1  && $apte==2)
         $html .='<br/><img src="'.$image_file.'" alt="">';
    	
	$html .='</td></tr>';
    $html .= '</table>';
$html .= '</td></tr></table>'; 
$html .= '<br /><hr><br /><h2 align="center"><u> EXAMENS DE CONTRÔLE </u></h2>';
$html .= '<table align="center" border="1" width="100%">';
	$html .= '<tr ><td align="center">DATE</td>';
    $html .= '<td align="center">OBSERVATIONS</td></tr>';

if($exmcontrl->fetchbyDI($medical->id)){
	foreach ($exmcontrl->rows as $line) {
		$html .= '<tr ><td align="center">';
	$html .= dol_print_date($line->dated,'day').'<br/>';
    $html .= dol_print_date($line->datef,'day').'</td>';
    $html .= '<td align="center">'.$line->observation.'</td></tr>';
	}
	
}
    $html .= '</table>';

$html .= <<<EOD
<table border="0" width="100%">
<tr>
<td width="20%">&nbsp;</td>
</tr>
</table>
EOD;

?>