<?php

/* Copyright (C) 2012      Mikael Carlavan        <contact@mika-carl.fr>
 *                                                http://www.mikael-carlavan.fr
 *
 * Copyright (C) 2018      Nicolas ZABOURI        <info@inovea-conseil.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**	    \file       htdocs/tos/tpl/admin.config.tpl.php
 *		\ingroup    tos
 *		\brief      Admin setup view
 */

if(DOL_VERSION < 15){
    llxHeader('', $langs->trans("CGVSetup"), '', '', 0, 0, array('/tos/js/functions.js.php', '/tos/js/jquery.form.js'));
} else {
    llxHeader('', $langs->trans("CGVSetup"), '', '', 0, 0, array());
}

echo ($message ? dol_htmloutput_mesg($message, '', ($error ? 'error' : 'ok'), 0) : '');

print_fiche_titre($langs->trans("CGVSetup"), $linkback, 'setup');

dol_fiche_head($head, 'config', $langs->trans("CGV"));

?>
<br />
<?php if (!empty($filename)) { ?>
	<?php print_titre($langs->trans("ReadCGVFile")); ?>
	<table border="0">
	<tr>
		<td><a href="<?php echo $link; ?>"><?php echo img_object($filename, 'pdf@tos'); ?></a></td>
		<td><a href="<?php echo $link; ?>"><?php echo $filename; ?></a></td>
	</tr>
	</table>
	<br />
<?php } ?>

<?php print_titre($langs->trans("SelectCGVFile")); ?>
<form  id="upform" name="upform" action="<?php echo $_SERVER['PHP_SELF']; ?>?action=update"  enctype="multipart/form-data" method="post">
<input type="hidden" name="action" value="update" />

<div id="progressbar"></div>
    <?php
        require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
        global $db, $conf;

        if(DOL_VERSION < 15){
            print "<input type='file' id='file' name='file' />";
            print "&nbsp;";
            print "<input type='submit' class='butAction' name='update' id='update' value='" . $langs->trans('Ok') ."' />";
            print "&nbsp;<input type='submit' class='butActionDelete' name='cancel' id='cancel' value='". $langs->trans('Cancel') . "' />";
        } else {
            $formfile = new FormFile($db);
            $formfile->form_attach_new_file(
                $_SERVER["PHP_SELF"],
                '',
                0,
                0,
                1,
                60,
                '',
                '',
                1,
                "", 0, 'update'
            );
        }
    ?>

<br /><br />

<?php print_titre($langs->trans("ToSOptions")); ?>
<table class="noborder" width="100%">
	<tr class="liste_titre">
        <td width="50%"><?php echo $langs->trans("Name"); ?></td>
        <td><?php echo $langs->trans("Value"); ?></td>
    </tr>
    <tr class="impair">
        <td><?php echo $langs->trans("AutoAddToS"); ?></td>
        <td><?php echo $linkAddToS; ?></td>
    </tr>
    <tr class="pair">
        <td><?php echo $langs->trans("AddTosOnEachPage"); ?></td>
        <td><?php echo $linkAddToSPage; ?></td>
    </tr>
    <tr class="pair">
        <td><?php echo $langs->trans("AddTosPropal"); ?></td>
        <td><?php echo $linkAddP; ?></td>
    </tr>
    <tr class="pair">
        <td><?php echo $langs->trans("AddTosOrder"); ?></td>
        <td><?php echo $linkAddO; ?></td>
    </tr>
    <tr class="pair">
        <td><?php echo $langs->trans("AddTosInvoice"); ?></td>
        <td><?php echo $linkAddI; ?></td>
    </tr>
</table>
</form>

<br />
<?php llxFooter(''); ?>
