<?php
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label($userben->table_element, true);
$userben->fetch_optionals($userben->id, $extralabels);
$field 		= $userben->array_options;
// $employeeCIN= $field['options_nx_cin'] ? strtoupper($field['options_nx_cin']) : '';

$html = <<<EOD
<br /><br />
<table width="100%">
<tr>
	<td align="center" width="70%">&nbsp;</td>
	<td align="left" width="30%"><strong style="">$city le $today</strong></td>
</tr>
</table>
<br /><br /><br /><br /><br />
<table width="100%">
<tr>
	<td align="center"><strong style="font-size:20px;text-decoration:underline;">ATTESTATION DE TRAVAIL</strong></td>
</tr>
<tr><td align="center" width="100%">&nbsp;</td></tr>
<tr><td align="center" width="100%">&nbsp;</td></tr>
<tr><td align="center" width="100%">&nbsp;</td></tr>
<tr>
	<td align="left" width="100%">
	&nbsp;&nbsp;&nbsp;&nbsp; Je soussigné <strong>$directorName</strong>, gérant de la société $societe Atteste par la présente que <strong>$employeeName</strong>, titulaire de la CIN N&deg; <strong>$employeeCIN</strong>, a travaillée au sein de notre B.E.T en qualité de <strong>$employeeJob</strong>.
	</td>
</tr>
<tr><td align="center" width="100%">&nbsp;</td></tr>
<tr>
	<td align="center" width="100%">
	Cette attestation est délivrée à l'intéressée pour servir et valoir ce que de droit.
	</td>
</tr>
<tr><td align="center" width="100%">&nbsp;</td></tr>
<tr><td align="center" width="100%">&nbsp;</td></tr>
</table>
<table width="100%">
<tr><td align="center" width="100%">&nbsp;</td></tr>
<tr>
	<td align="left" width="60%">&nbsp;</td>
	<td align="center" width="40%"><strong>Signé :</strong></td>
</tr>
<tr>
	<td align="left" width="60%">&nbsp;</td>
	<td align="center" width="40%"><strong>$directorName</strong></td>
</tr>
</table>
EOD;
?>