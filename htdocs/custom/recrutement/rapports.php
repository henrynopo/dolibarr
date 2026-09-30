<?php
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");       // For root directory
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php"); // For "custom


dol_include_once('/recrutement/class/postes.class.php');
dol_include_once('/recrutement/class/candidatures.class.php');
dol_include_once('/recrutement/class/etapescandidature.class.php');
dol_include_once('/recrutement/lib/recrutement.lib.php');
dol_include_once('/core/class/html.form.class.php');



$langs->load('recrutement@recrutement');

$modname = $langs->trans("analyses");
$candidatures = new candidatures($db);
$etapescandidature = new etapescandidature($db);
$postes = new postes($db);
$id_poste=GETPOST('id_poste');

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

print_fiche_titre($modname);



print ' <div class="board div_rapports" style="width:100% !important">';
	$postes->fetchAll();
	$nb_poste = count($postes->rows);

	$etapescandidature->fetchAll();
	$nb_etapes=count($etapescandidature->rows);
	print '<div class="div-table-responsive"">';


	$candidatures->fetchAll();
	$nb_candidature=count($candidatures->rows);
	print '<table class="border nc_table_" width="100%">';
		print '<tr>';
			print '<td style="width:20% !important" >';
				print '<b>'.$langs->trans("etap_candidat").'</b> <b class="border_italic"> </b> <b>  '.$langs->trans("postes").'</b>';
			print '</td>';
			for ($i=0; $i < $nb_poste; $i++) { 
				$poste = $postes->rows[$i];
				print '<td style="width:10% ;color: #6b6969;" align="center"  ><b>'.$poste->label.'</b></td>';
			}
			print '<td style="width:10%" align="center" ><b>Total</b></td>';

		print '</tr>';
		$nbT_2=[];
		for ($i=0; $i < $nb_etapes; $i++) { 
			$etape = $etapescandidature->rows[$i];
			$nbT_1=0;
			print '<tr>';
				print '<td align="" style="color: #6b6969;"><b>'.$langs->trans($etape->label).'</b></td>';
				for ($j=0; $j < $nb_poste; $j++) { 
					$poste_ = $postes->rows[$j];
					$nbT_1+=$nb;
					$nb=0;
					$nbT_2[$poste_->rowid]=0;
					for ($k=0; $k < $nb_candidature; $k++) { 
						$candidature = $candidatures->rows[$k];
						if($candidature->poste == $poste_->rowid ){
							$nbT_2[$poste_->rowid]++;
							if($candidature->etape == $etape->rowid){
								$nb++;
							}
						}
					}
					print '<td align="center">'.$nb.'</td>';
				}
				print '<td align="center">'.$nbT_1.'</td>';
			print '</tr>';
		}


		print '<tr>';
			print '<td align="left"><b>'.$langs->trans("total").'</b></td>';
			foreach ($nbT_2 as $key => $value) {
				print '<td align="center" class="dd">'.$value.'</td>';
			}
			print '<td align="center">'.$nb_candidature.'</td>';

		print '</tr>';
	print '</table>';
	print '</div>';
print '</div>';

// $candidatures->fetchAll('','',0,0,' and poste = '.$id_poste);
// $nb=count($candidatures->rows);


llxFooter();
?>





