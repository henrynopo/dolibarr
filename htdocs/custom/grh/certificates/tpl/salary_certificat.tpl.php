<?php
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label($userben->table_element, true);
$userben->fetch_optionals($userben->id, $extralabels);
$field 		= $userben->array_options;
// $employeeCIN= $field['options_nx_cin'] ? strtoupper($field['options_nx_cin']) : '';
$symbolecurency = $langs->getCurrencySymbol($conf->currency);
$html = <<<EOD
<br /><br /><br /><br /><br /><br />
<table width="100%">
<tr>
	<td align="center"><strong style="font-size:20px;text-decoration:underline;">ATTESTATION DE SALAIRE</strong></td>
</tr>
<tr><td align="center" width="100%">&nbsp;</td></tr>
<tr><td align="center" width="100%">&nbsp;</td></tr>
<tr><td align="center" width="100%">&nbsp;</td></tr>
<tr>
	<td align="left" width="100%">
	&nbsp;&nbsp;&nbsp;&nbsp; Je soussigné <strong>$directorName</strong>, gérant de la société $societe Atteste par la présente que <strong>$employeeName</strong>, titulaire de la CIN N&deg; <strong>$employeeCIN</strong>, est employée au sein de notre bureau en qualité de <strong>$employeeJob</strong>.
	</td>
</tr>
<tr><td align="center" width="100%">&nbsp;</td></tr>
<tr>
	<td align="left" width="100%">
	&nbsp;&nbsp;&nbsp;&nbsp; Elle perçoit un salaire NET mensuel de <strong>$employeeSalary $symbolecurency</strong> (<strong>$employeeSalaryLetters</strong>)
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
<tr>
	<td align="left" width="50%">&nbsp;</td>
	<td align="left" width="50%">
	<strong>Faite à $city, le $today</strong>
	</td>
</tr>
<tr><td align="center" width="100%">&nbsp;</td></tr>
<tr><td align="center" width="100%">&nbsp;</td></tr>
<tr>
	<td align="left" width="50%">&nbsp;</td>
	<td align="left" width="50%"><strong>Signé</strong></td>
</tr>
</table>
EOD;
?>