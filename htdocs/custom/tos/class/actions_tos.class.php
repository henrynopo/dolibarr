<?php
/* Copyright (C) 2012      Mikael Carlavan        <contact@mika-carl.fr>
 *                                                http://www.mikael-carlavan.fr
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

/**
 *      \file       htdocs/tos/class/tos.class.php
 *      \ingroup    tos
 *      \brief      File of class to add terms of sale
 */

require_once(DOL_DOCUMENT_ROOT . "/core/class/commonobject.class.php");
require_once(DOL_DOCUMENT_ROOT . "/core/lib/functions.lib.php");
require_once(DOL_DOCUMENT_ROOT . "/core/lib/functions2.lib.php");
require_once(DOL_DOCUMENT_ROOT . '/core/lib/price.lib.php');
require_once(DOL_DOCUMENT_ROOT . "/core/lib/files.lib.php");
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';

/**
 *      \class      Tos
 *      \brief
 */
class ActionsToS
{

    function formBuilddocOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $db, $form, $mysoc, $conf;

        $langs->load('tos@tos');

        $modulepart = $parameters['modulepart'];

        $out = '';
        $addInput = false;

        if ($modulepart == 'facture' && !empty($conf->global->ADD_TOS_INVOICE) || $modulepart == 'commande' && !empty($conf->global->ADD_TOS_ORDER) || $modulepart == 'propal' && !empty($conf->global->ADD_TOS_PROPAL)) {
            $addInput = true;

        }

        if ($addInput) {
            $attachTOS = $conf->global->AUTO_ADD_TOS ? true : false;
            if (GETPOST("action") == "builddoc") {
                if (isset($_POST['attach_tos'])) {
                    $attachTOS = GETPOST('attach_tos');
                } else {
                    $attachTOS = false;
                }
            }

            $out .= '<div style="display:inline">' . $langs->trans('AddTOSToPDF') . '&nbsp;<input type="checkbox" value="1" name="attach_tos" id="attach_tos" ' . ($attachTOS ? 'checked="checked"' : '') . '  /></div>';
        }

        $this->resprints = $out;
        return 0;

    }

    /**
     *    afterPDFCreation
     * @param object            Linked object
     */
    function afterPDFCreation($parameters = false, &$pdfclass, &$action = '')
    {
        global $conf, $user, $langs, $db;


        $file = $parameters['file'];
        $object = $parameters['object'];

        // Load Terms of Sale
        $upload_dir = $conf->tos->dir_output;
        $filearray = dol_dir_list($upload_dir, 'files', 0, '', '\.meta$', '', SORT_ASC, 1);

        $tosFile = array_pop($filearray);

        $tosFilename = '';
        $tosFilePath = '';
        if (is_array($tosFile)) {
            $tosFilename = $tosFile['name'];
            $tosFilePath = $upload_dir . "/" . $tosFilename;
        }

        $attachTOS = $conf->global->AUTO_ADD_TOS ? true : false;
        if (GETPOST("action") == "builddoc") {
            if (isset($_POST['attach_tos'])) {
                $attachTOS = GETPOST('attach_tos');
            } else {
                $attachTOS = false;
            }
        }


        if (($object->element == 'facture' && !empty($conf->global->ADD_TOS_INVOICE) || $object->element == 'commande' && !empty($conf->global->ADD_TOS_ORDER) || $object->element == 'propal' && !empty($conf->global->ADD_TOS_PROPAL)) && $attachTOS) {

            $pdf = pdf_getInstance($pdfclass->format);
            $pdf->Open();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // Load PDF file
            $pagesNbr = $pdf->setSourceFile($file);
            dol_syslog("ActionsToS::afterPDFCreation load file=" . $file, LOG_DEBUG);
            for ($p = 1; $p <= $pagesNbr; $p++) {
                $templateIdx = $pdf->ImportPage($p);
                $size = $pdf->getTemplatesize($templateIdx);

                $portrait = $size['h'] > $size['w'] ? true : false;

                $pdf->AddPage($portrait ? 'P' : 'L');

                $pdf->useTemplate($templateIdx);

                if ($conf->global->ADD_TOS_ON_EACH_PAGE && !empty($tosFilePath)) {
                    $pagesNbrTos = $pdf->setSourceFile($tosFilePath);
                    dol_syslog("ActionsToS::afterPDFCreation load file=" . $tosFilePath, LOG_DEBUG);
                    for ($pTos = 1; $pTos <= $pagesNbrTos; $pTos++) {
                        $templateIdx = $pdf->ImportPage($pTos);
                        $size = $pdf->getTemplatesize($templateIdx);

                        $portrait = $size['h'] > $size['w'] ? true : false;

                        $pdf->AddPage($portrait ? 'P' : 'L');

                        $pdf->useTemplate($templateIdx);
                    }
                    $pagesNbr = $pdf->setSourceFile($file);
                }
            }

            if (empty($conf->global->ADD_TOS_ON_EACH_PAGE) && !empty($tosFilePath)) {
                $pagesNbr = $pdf->setSourceFile($tosFilePath);
                dol_syslog("ActionsToS::afterPDFCreation load file=" . $tosFilePath, LOG_DEBUG);
                for ($p = 1; $p <= $pagesNbr; $p++) {
                    $templateIdx = $pdf->ImportPage($p);
                    $size = $pdf->getTemplatesize($templateIdx);

                    $portrait = $size['h'] > $size['w'] ? true : false;

                    $pdf->AddPage($portrait ? 'P' : 'L');

                    $pdf->useTemplate($templateIdx);
                }
            }


            // Save file
            if (method_exists($pdf, 'AliasNbPages')) $pdf->AliasNbPages();

            $pdf->Close();

            dol_syslog("ActionsToS::afterPDFCreation save file=" . $file, LOG_DEBUG);
            $pdf->Output($file, 'F');
        }

        return 0;
    }

}


