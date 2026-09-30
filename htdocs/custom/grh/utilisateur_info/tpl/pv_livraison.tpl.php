<?php
$nom_soc=$conf->global->MAIN_INFO_SOCIETE_NOM;
$pv_livraison->fetch($id);
$spot->fetch($pv_livraison->fields->num_spot);
$plan->fetch($spot->fields->plan);
$operation->fetch($plan->fields->operation);
$type_spot->fetch($spot->fields->type_spot);
$Societe->fetch($spot->fields->client);

$op_name = $operation->fields->nom;
$op_adresse = $operation->fields->adresse;
$type_spot_name = $type_spot->fields->type_spot;
$spot_name = $spot->fields->name;
$num_dossier = $spot->fields->num_dossier;
$spot_surface = $spot->fields->surface;
$titre_foncier = $spot->fields->titre_foncier;
$pv_name = $pv_livraison->fields->num_pv;
$pv_name = $pv_livraison->fields->num_pv;
$spot_prix = number_format($spot->fields->prix,2,',',' ');

$html = <<<EOD

<br><br>
<h3 align="center"><u>PROCES VERBAL DE LIVRAISON</u></h3>
<style>#table_1 td{font-size:15px;font-family: "Times New Roman", Times, serif;}</style>
<table cellpadding="5px" cellspacing="0" id="table_1">

<tbody>

	<tr>
		<td  align="left" width="150px" ><b>Opération :</b></td>
		<td  align="left" >$op_name</td>
	</tr>
	<tr>
		<td  align="left" ><b>Adresse :</b></td>
		<td  align="left" >$op_adresse</td>
	</tr>
	<tr>
		<td  align="left" ><b>Type de l’unité :</b></td>
		<td  align="left" >$type_spot_name</td>
	</tr>
	<tr>
		<td  align="left" ><b>N° du Dossier :</b></td>
		<td  align="left" >$num_dossier</td>
	</tr>
	<tr>
		<td  align="left" ><b>N° de l’unité :</b></td>
		<td  align="left" >$spot_name</td>
	</tr>
	<tr>
		<td  align="left" ><b>N° Titre Foncier :</b></td>
		<td  align="left" >$titre_foncier</td>
	</tr>
	<tr>
		<td  align="left" ><b>Surface Provisoire :</b></td>
		<td  align="left" >$spot_surface</td>
	</tr>
	<tr>
		<td  align="left" ><b>N° PV :</b></td>
		<td  align="left" >$pv_name</td>
	</tr>
	<tr>
		<td  align="left" ><b>Prix Totale de cession :</b></td>
		<td  align="left" >$spot_prix</td>
	</tr>

</tbody> 
</table>

<br><br>

<h3><u>Je Soussigné :</u></h3>

<table cellpadding="5px" cellspacing="0"  border="1">

<tbody>
	<tr bgcolor="#538DD5" style="color:#fff;font-weight:bold;">
		<td  align="center" width="35%">Nom & Prénom</td>
		<td  align="center" width="20%">C.I.N </td>
		<td  align="center" width="45%">Adresse</td>
	</tr>
	<tr>
		<td  align="center">$Societe->nom</td>
		<td  align="center">$Societe->cin</td>
		<td  align="center">$Societe->address</td>
	</tr>
</tbody>

</table>
<br>
<div><b>Reconnais avoir prix livraison de la date unité ce jour, pour l'avoir visitée et avoir<br>constaté son entière conformité avec les caractéristique convenues.<br>A compter de ce jour ,date de jouissance de l'unité, je m'engage à:<br></b>
</div>

<div>
<b>1-</b>    prendre la propriété livrée dans son état actuel, sans pouvoir prétendre à aucune désistement, indemnité				
Ni diminution de prix pour quelque cause que soit.<br>						
<b>2-</b>    Souffrir les servitudes passives et jour de celles active.<br>
<b>3-</b>    Maintenir les bornes limites de la propriété, entretenir ses équipements et assurer sa garde sous notre	
Responsabilité et a nos frais sans pouvoir prétendre a aucune réclamation future.	<br>							
<b>4-</b>    Respectes les prescriptions réglementaires en vigueur en matière d’urbanisme, de construction et						
Les réglementes municipaux.<br>								
<b>5-</b>    Acquitter à compter de ce jour tous les impôts actuels et futur, contributions et charges de toutes			
Natures relatives à la propriété présentement livrée et transférée.	<br>								
<b>6-</b>    Signer le contrat de vente des disponibilités du titre foncier et réception de l'avis de Société <b>$nom_soc</b><br>
<b>7-</b>    Enregistre le contrat de cession et inscrire la vente à l'agence nationale de la conservation foncière,
Du cadastre et de la cartographie et prendre en charge les frais y afférents ainsi que tous les frais				
Et honoraires nécessaires à la réalisation de la transaction à mon profil.	<br>							
</div>

<br><br>

<div>Société <b>$nom_soc</b> , reconnait avoir perçu la totalité du prix de cession  convenu.<br><br>
				
Fait à Laâyoune, en  (03) exemplaires originaux, le __/__/____ <br><br>
pour Société <b>$nom_soc</b>				
									

</div>
EOD;
?>