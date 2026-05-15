<?php

/* Copyright (C) 2010-2011  Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2010-2014  Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2015       Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2018-2025  Frédéric France      <frederic.france@free.fr>
 * Copyright (C) 2024-2025	MDW					 <mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024	    Nick Fragoulis
 * Copyright (C) 2025       Joachim Küter        <git-jk@bloxera.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/custom/slycustom/core/modules/supplier_invoice/doc/pdf_sly_debitnote.modules.php
 *	\ingroup    fournisseur
 *	\brief      SLY Debit Note：我们发给供应商的借记通知单。不自动生成 PDF，需向供应商发送时再手动生成。
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/supplier_invoice/modules_facturefournisseur.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';


/**
 *	SLY Debit Note PDF template.
 *	Used when we (the buyer) send a debit note to the supplier. Do not set as default;
 *	PDF is generated manually only when the user needs to send the document to the supplier.
 *	(In normal flow we receive credit notes from suppliers; this template is for the reverse case.)
 */
class pdf_sly_debitnote extends ModelePDFSuppliersInvoices
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description (short text)
	 */
	public $description;

	/**
	 * @var int 	Save the name of generated file as the main doc when generating a doc with this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * Dolibarr version of the loaded document
	 * @var string Version, possible values are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'''|'development'|'dolibarr'|'experimental'
	 */
	public $version = 'dolibarr';

	/**
	 * @var string Constant name for ODT dir scan (empty = PDF, no scan). Required by admin/supplier_invoice.php for URL params.
	 */
	public $scandir = '';

	/** @var int SLY format: letterhead height (logo + company block) for dynamic tab_top */
	public $letterhead_height = 0;

	/** @var int SLY format: bottom Y of page header for dynamic tab_top */
	public $pagehead_bottom_y = 0;

	/** @var bool True to show VAT/GST column and totals (false for Singapore non-GST company, same as SLY invoice) */
	public $show_vat_for_pdf = true;


	/**
	 *	Constructor
	 *
	 *  @param	DoliDB		$db     	Database handler
	 *  @param	object		$object		Optional (FactureFournisseur or Societe when called from admin setup)
	 */
	public function __construct($db, $object = null)
	{
		global $conf, $langs, $mysoc;

		// Translations (safe if $langs not yet loaded in some contexts)
		if (!empty($langs)) {
			$langs->loadLangs(array("main", "bills", "slycustom@slycustom"));
		}

		$this->db = $db;
		$this->name = "sly_debitnote";
		$this->description = (!empty($langs) && $langs->trans("SLYDebitNoteModelDesc") != "SLYDebitNoteModelDesc")
			? $langs->trans("SLYDebitNoteModelDesc")
			: "SLY Debit Note (generate manually when sending to supplier)";
		$this->update_main_doc_field = 1; // Save the name of generated file as the main doc when generating a doc with this template

		// Page dimensions
		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->corner_radius = getDolGlobalInt('MAIN_PDF_FRAME_CORNER_RADIUS', 0);
		$this->option_logo = 1; // Display logo
		$this->option_tva = 1; // Manage the vat option FACTURE_TVAOPTION
		$this->option_modereg = 1; // Display payment mode
		$this->option_condreg = 1; // Display payment terms
		$this->option_multilang = 1; // Available in several languages

		// Define column position
		$this->posxdesc = $this->marge_gauche + 1;

		// SLY: balance U.P. and Total column width so both fit amounts like -2,421.44 (Total needs ~22mm)
		if (getDolGlobalInt('PRODUCT_USE_UNITS')) {
			$this->posxtva = 99;
			$this->posxup = 114;
			$this->posxqty = 134;
			$this->posxunit = 151;
		} else {
			$this->posxtva = 106;
			$this->posxup = 122;
			$this->posxqty = 149;
			$this->posxunit = 166;
		}
		$this->posxdiscount = 166;
		$this->postotalht = 178;

		/* if (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT') || getDolGlobalString('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN')) {
			$this->posxtva = $this->posxup;
		} */
		$this->posxpicture = $this->posxtva - (getDolGlobalInt('MAIN_DOCUMENTS_WITH_PICTURE_WIDTH', 20)); // width of images
		if ($this->page_largeur < 210) { // To work with US executive format
			$this->posxpicture -= 20;
			$this->posxtva -= 20;
			$this->posxup -= 20;
			$this->posxqty -= 20;
			$this->posxunit -= 20;
			$this->posxdiscount -= 20;
			$this->postotalht -= 20;
		}

		$this->tva = array();
		$this->tva_array = array();
		$this->localtax1 = array();
		$this->localtax2 = array();
		$this->atleastoneratenotnull = 0;
		$this->atleastonediscount = 0;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build a document on disk using the generic odt module.
	 *
	 *  @param		FactureFournisseur	$object				Object to generate
	 *  @param		Translate			$outputlangs		Lang output object
	 *  @param		string				$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int<0,1>			$hidedetails		Do not show line details
	 *  @param		int<0,1>			$hidedesc			Do not show desc
	 *  @param		int<0,1>			$hideref			Do not show ref
	 *  @return		int<-1,1>								1=OK, <=0=KO
	 */
	public function write_file($object, $outputlangs = null, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $langs, $conf, $mysoc, $hookmanager, $nblines;

		// Get source company
		if (!is_object($object->thirdparty)) {
			$object->fetch_thirdparty();
		}
		if (!is_object($object->thirdparty)) {
			$object->thirdparty = $mysoc; // If fetch_thirdparty fails, object has no socid (specimen)
		}

		// SLY Debit Note: we (buyer) send the document to the supplier, so emetteur = our company
		$this->emetteur = $mysoc;
		if (!is_object($this->emetteur)) {
			$this->emetteur = $object->thirdparty; // fallback for specimen
		}
		if (!$this->emetteur->country_code) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2);
		}

		// Singapore non-GST company: do not show VAT/GST (same as SLY invoice/order)
		$this->show_vat_for_pdf = !getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT')
			&& !(!empty($mysoc->country_code) && $mysoc->country_code == 'SG' && empty($mysoc->tva_assuj));

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		// Load translation files required by the page
		$outputlangs->loadLangs(array("main", "dict", "companies", "bills", "products"));

		$nblines = count($object->lines);

		if ($conf->fournisseur->facture->dir_output) {
			$deja_regle = $object->getSommePaiement((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? 1 : 0);
			$amount_credit_notes_included = $object->getSumCreditNotesUsed((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? 1 : 0);
			$amount_deposits_included = $object->getSumDepositsUsed((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? 1 : 0);

			// Definition of $dir and $file
			if ($object->specimen) {
				$dir = $conf->fournisseur->facture->dir_output;
				$file = $dir."/SPECIMEN.pdf";
			} else {
				$objectref = dol_sanitizeFileName($object->ref);
				$objectrefsupplier = dol_sanitizeFileName($object->ref_supplier);
				$dir = $conf->fournisseur->facture->dir_output.'/'.get_exdir($object->id, 2, 0, 0, $object, 'invoice_supplier').$objectref;
				$file = $dir."/".$objectref.".pdf";
				if (getDolGlobalString('SUPPLIER_REF_IN_NAME')) {
					$file = $dir."/".$objectref.($objectrefsupplier ? "_".$objectrefsupplier : "").".pdf";
				}
			}

			if (!file_exists($dir)) {
				if (dol_mkdir($dir) < 0) {
					$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
					return 0;
				}
			}

			if (file_exists($dir)) {
				// Add pdfgeneration hook
				if (!is_object($hookmanager)) {
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager = new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

				// Set nblines with the new facture lines content after hook
				$nblines = count($object->lines);
				$nbpayments = count($object->getListOfPayments());

				// Create pdf instance
				$pdf = pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
				$pdf->setAutoPageBreak(true, 0);

				$heightforinfotot = 50 + (4 * $nbpayments); // Height reserved to output the info and total part and payment part
				if ($heightforinfotot > 220) {
					$heightforinfotot = 220;
				}
				$heightforfreetext = getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5); // Height reserved to output the free text on last page
				$heightforfooter = $this->marge_basse + 8; // Height reserved to output the footer (value include bottom margin)
				if (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS')) {
					$heightforfooter += 6;
				}

				if (class_exists('TCPDF')) {
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));
				// Set path to the background PDF File
				if (getDolGlobalString('MAIN_ADD_PDF_BACKGROUND')) {
					$logodir = $conf->mycompany->dir_output;
					if (!empty($conf->mycompany->multidir_output[$object->entity])) {
						$logodir = $conf->mycompany->multidir_output[$object->entity];
					}
					$pagecount = $pdf->setSourceFile($logodir .'/' . getDolGlobalString('MAIN_ADD_PDF_BACKGROUND'));
					$tplidx = $pdf->importPage(1);
				}

				$pdf->Open();
				$pagenb = 0;
				$pdf->SetDrawColor(128, 128, 128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("PdfInvoiceTitle"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("PdfInvoiceTitle")." ".$outputlangs->convToOutputCharset($object->thirdparty->name));
				if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
					$pdf->SetCompression(false);
				}

				// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

				// Set $this->atleastonediscount if you have at least one discount
				for ($i = 0; $i < $nblines; $i++) {
					if ($object->lines[$i]->remise_percent) {
						$this->atleastonediscount++;
					}
				}
				if (empty($this->atleastonediscount)) {
					$delta = ($this->postotalht - $this->posxdiscount);
					$this->posxpicture += $delta;
					$this->posxtva += $delta;
					$this->posxup += $delta;
					$this->posxqty += $delta;
					$this->posxunit += $delta;
					$this->posxdiscount += $delta;
					// post of fields after are not modified, stay at same position
				}

				// New page
				$pdf->AddPage();
				if (!empty($tplidx)) {
					$pdf->useTemplate($tplidx);
				}
				$pagenb++;
				$top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs);
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell(0, 3, ''); // Set interline to 3
				$pdf->SetTextColor(0, 0, 0);

				// SLY format: dynamic tab_top from header bottom (same as sly_invoice)
				$gap_after_header = 4;
				$tab_top = (isset($this->pagehead_bottom_y) ? ($this->pagehead_bottom_y + $gap_after_header) : (90 + $top_shift + (isset($this->letterhead_height) ? $this->letterhead_height : 0)));
				$tab_top_newpage = (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 42 + $top_shift + (isset($this->letterhead_height) ? $this->letterhead_height : 0) : 10);

				// Incoterm
				if (isModEnabled('incoterm')) {
					$desc_incoterms = $object->getIncotermsForPDF();
					if ($desc_incoterms) {
						$tab_top -= 2;

						$pdf->SetFont('', '', $default_font_size - 1);
						$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top - 1, dol_htmlentitiesbr($desc_incoterms), 0, 1);
						$nexY = $pdf->GetY();
						$height_incoterms = $nexY - $tab_top;

						// Rect takes a length in 3rd parameter
						$pdf->SetDrawColor(192, 192, 192);
						$pdf->RoundedRect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_incoterms + 3, $this->corner_radius, '1234', 'D');

						$tab_top = $nexY + 6;
					}
				}

				// Displays notes
				$notetoshow = empty($object->note_public) ? '' : $object->note_public;

				// Extrafields in note
				if (getDolGlobalString('INVOICE_ADD_EXTRAFIELD_IN_NOTE')) {
					$extranote = $this->getExtrafieldsInHtml($object, $outputlangs);
					if (!empty($extranote)) {
						$notetoshow = dol_concatdesc($notetoshow, $extranote);
					}
				}

				if ($notetoshow) {
					$tab_top -= 2;

					$substitutionarray = pdf_getSubstitutionArray($outputlangs, null, $object);
					complete_substitutions_array($substitutionarray, $outputlangs, $object);
					$notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
					$notetoshow = convertBackOfficeMediasLinksToPublicLinks($notetoshow);

					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top - 1, dol_htmlentitiesbr($notetoshow), 0, 1);
					$nexY = $pdf->GetY();
					$height_note = $nexY - $tab_top;

					// Rect takes a length in 3rd parameter
					$pdf->SetDrawColor(192, 192, 192);
					$pdf->RoundedRect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_note + 2, $this->corner_radius, '1234', 'D');

					$tab_top = $nexY + 6;
				}

				$iniY = $tab_top + 7;
				$curY = $tab_top + 7;
				$nexY = $tab_top + 7;

				// Loop on each lines
				for ($i = 0; $i < $nblines; $i++) {
					$curY = $nexY;
					$pdf->SetFont('', '', $default_font_size - 1); // Into loop to work with multipage
					$pdf->SetTextColor(0, 0, 0);

					// Define size of image if we need it
					//$imglinesize = array();
					//if (!empty($realpatharray[$i])) {
					//	$imglinesize = pdf_getSizeForImage($realpatharray[$i]);
					//}

					$pdf->setTopMargin($tab_top_newpage);
					$pdf->setPageOrientation('', true, $heightforfooter + $heightforfreetext + $heightforinfotot); // The only function to edit the bottom margin of current page to set it.
					$pageposbefore = $pdf->getPage();

					$showpricebeforepagebreak = 1;
					$posYAfterImage = 0;

					// Description of product line (wider when VAT column hidden, e.g. Singapore non-GST)
					$curX = $this->posxdesc - 1;
					$descWidth = ($this->show_vat_for_pdf ? $this->posxtva : $this->posxup) - $curX;

					$pdf->startTransaction();
					pdf_writelinedesc($pdf, $object, $i, $outputlangs, $descWidth, 3, $curX, $curY, $hideref, $hidedesc, 1);
					$pageposafter = $pdf->getPage();
					if ($pageposafter > $pageposbefore) {	// There is a pagebreak
						$pdf->rollbackTransaction(true);
						$pageposafter = $pageposbefore;
						//print $pageposafter.'-'.$pageposbefore;exit;
						$pdf->setPageOrientation('', true, $heightforfooter); // The only function to edit the bottom margin of current page to set it.
						pdf_writelinedesc($pdf, $object, $i, $outputlangs, $descWidth, 4, $curX, $curY, $hideref, $hidedesc, 1);
						$posyafter = $pdf->GetY();
						if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforinfotot))) {	// There is no space left for total+free text
							if ($i == ($nblines - 1)) {	// No more lines, and no space left to show total, so we create a new page
								$pdf->AddPage('', '', true);
								if (!empty($tplidx)) {
									$pdf->useTemplate($tplidx);
								}
								if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
									$this->_pagehead($pdf, $object, 0, $outputlangs);
								}
								$pdf->setPage($pageposafter + 1);
							}
						} else {
							// We found a page break
							// Allows data in the first page if description is long enough to break in multiples pages
							if (getDolGlobalString('MAIN_PDF_DATA_ON_FIRST_PAGE')) {
								$showpricebeforepagebreak = 1;
							} else {
								$showpricebeforepagebreak = 0;
							}
						}
					} else { // No pagebreak
						$pdf->commitTransaction();
					}
					$posYAfterDescription = $pdf->GetY();

					$nexY = $pdf->GetY();
					$pageposafter = $pdf->getPage();
					$pdf->setPage($pageposbefore);
					$pdf->setTopMargin($this->marge_haute);
					$pdf->setPageOrientation('', true, 0); // The only function to edit the bottom margin of current page to set it.

					// We suppose that a too long description or photo were moved completely on next page
					if ($pageposafter > $pageposbefore && empty($showpricebeforepagebreak)) {
						$pdf->setPage($pageposafter);
						$curY = $tab_top_newpage;
					}

					$pdf->SetFont('', '', $default_font_size - 1); // We reposition the default font

					// VAT Rate (hidden for Singapore non-GST company)
					if ($this->show_vat_for_pdf) {
						$vat_rate = pdf_getlinevatrate($object, $i, $outputlangs, $hidedetails);
						$pdf->SetXY($this->posxtva, $curY);
						$pdf->MultiCell($this->posxup - $this->posxtva - 1, 3, $vat_rate, 0, 'C');
					}

					// Unit price before discount (SLY: invert sign so positive)
					$line_sign = 1;
					if ($object->type == 2 && getDolGlobalString('INVOICE_POSITIVE_CREDIT_NOTE')) {
						$line_sign = -1;
					}
					$line_sign = -$line_sign;
					$subprice = (isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? $object->lines[$i]->multicurrency_subprice : $object->lines[$i]->subprice;
					// List: no currency symbol in cells (currency shown above table as "Currency: US Dollars")
					$up_excl_tax = price($line_sign * $subprice, 0, $outputlangs, 0, -1, -1, '');
					$pdf->SetXY($this->posxup, $curY);
					$pdf->MultiCell($this->posxqty - $this->posxup - 0.8, 3, $up_excl_tax, 0, 'C', false);

					// Quantity
					$qty = pdf_getlineqty($object, $i, $outputlangs, $hidedetails);
					$pdf->SetXY($this->posxqty, $curY);
					$pdf->MultiCell($this->posxunit - $this->posxqty - 0.8, 4, $qty, 0, 'C'); // Enough for 6 chars

					// Unit
					if (getDolGlobalInt('PRODUCT_USE_UNITS')) {
						$unit = pdf_getlineunit($object, $i, $outputlangs, $hidedetails);
						$pdf->SetXY($this->posxunit, $curY);
						$pdf->MultiCell($this->posxdiscount - $this->posxunit - 0.8, 4, $unit, 0, 'C');
					}

					// Discount on line
					if ($object->lines[$i]->remise_percent) {
						$pdf->SetXY($this->posxdiscount - 2, $curY);
						$remise_percent = pdf_getlineremisepercent($object, $i, $outputlangs, $hidedetails);
						$pdf->MultiCell($this->postotalht - $this->posxdiscount - 1, 3, $remise_percent, 0, 'C');
					}

					// Total HT line (SLY: invert sign so positive)
					$total_ht_line = (isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? $object->lines[$i]->multicurrency_total_ht : $object->lines[$i]->total_ht;
					if (!empty($object->lines[$i]->situation_percent) && $object->lines[$i]->situation_percent > 0 && method_exists($object->lines[$i], 'get_prev_progress')) {
						$prev_progress = $object->lines[$i]->get_prev_progress($object->id);
						$progress = ($object->lines[$i]->situation_percent - $prev_progress) / 100;
						$total_ht_line = ($total_ht_line / ($object->lines[$i]->situation_percent / 100)) * $progress;
					}
					$total_excl_tax = price($line_sign * (float) $total_ht_line, 0, $outputlangs, 0, -1, -1, '');
					$pdf->SetXY($this->postotalht, $curY);
					$pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->postotalht, 3, $total_excl_tax, 0, 'C', false);

					// Collection of totals by VAT value in $this->tva["taux"]=total_tva
					if (isModEnabled("multicurrency") && $object->multicurrency_tx != 1) {
						$tvaligne = $object->lines[$i]->multicurrency_total_tva;
					} else {
						$tvaligne = $object->lines[$i]->total_tva;
					}

					$localtax1ligne = $object->lines[$i]->total_localtax1;
					$localtax2ligne = $object->lines[$i]->total_localtax2;

					// TODO remise_percent is an obsolete field for object parent
					/*if (!empty($object->remise_percent)) {
						$tvaligne -= ($tvaligne * $object->remise_percent) / 100;
					}*/

					$vatrate = (string) $object->lines[$i]->tva_tx;
					$localtax1rate = (string) $object->lines[$i]->localtax1_tx;
					$localtax2rate = (string) $object->lines[$i]->localtax2_tx;

					if (($object->lines[$i]->info_bits & 0x01) == 0x01) {
						$vatrate .= '*';
					}
					if (empty($this->tva[$vatrate])) {
						$this->tva[$vatrate] = 0;
					}
					if (empty($this->localtax1[$localtax1rate])) {
						$this->localtax1[$localtax1rate] = 0;
					}
					if (empty($this->localtax2[$localtax2rate])) {
						$this->localtax2[$localtax2rate] = 0;
					}
					$this->tva[$vatrate] += $tvaligne;
					$this->localtax1[$localtax1rate] += $localtax1ligne;
					$this->localtax2[$localtax2rate] += $localtax2ligne;

					if ($posYAfterImage > $posYAfterDescription) {
						$nexY = $posYAfterImage;
					}

					// Add line
					if (getDolGlobalString('MAIN_PDF_DASH_BETWEEN_LINES') && $i < ($nblines - 1)) {
						$pdf->setPage($pageposafter);
						$pdf->SetLineStyle(array('dash' => '1,1', 'color' => array(80, 80, 80)));
						//$pdf->SetDrawColor(190,190,200);
						$pdf->line($this->marge_gauche, $nexY + 1, $this->page_largeur - $this->marge_droite, $nexY + 1);
						$pdf->SetLineStyle(array('dash' => 0));
					}

					$nexY += 2; // Add space between lines

					// Detect if some page were added automatically and output _tableau for past pages
					while ($pagenb < $pageposafter) {
						$pdf->setPage($pagenb);
						if ($pagenb == 1) {
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1, $object->multicurrency_code);
						} else {
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code);
						}
						$this->_pagefoot($pdf, $object, $outputlangs, 1);
						$pagenb++;
						$pdf->setPage($pagenb);
						$pdf->setPageOrientation('', true, 0); // The only function to edit the bottom margin of current page to set it.
						if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
							$this->_pagehead($pdf, $object, 0, $outputlangs);
						}
					}
					if (isset($object->lines[$i + 1]->pagebreak) && $object->lines[$i + 1]->pagebreak) {  // @phan-suppress-current-line PhanUndeclaredProperty  // @phan-suppress-current-line PhanUndeclaredProperty
						if ($pagenb == 1) {
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1, $object->multicurrency_code);
						} else {
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code);
						}
						$this->_pagefoot($pdf, $object, $outputlangs, 1);
						// New page
						$pdf->AddPage();
						if (!empty($tplidx)) {
							$pdf->useTemplate($tplidx);
						}
						$pagenb++;
						if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
							$this->_pagehead($pdf, $object, 0, $outputlangs);
						}
					}
				}

				// Show square
				if ($pagenb == 1) {
					$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0, $object->multicurrency_code);
					$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				} else {
					$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0, $object->multicurrency_code);
					$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				}

				// Display total area
				$posy = $this->_tableau_tot($pdf, $object, $deja_regle, $bottomlasttab, $outputlangs);

				$amount_credit_notes_included = 0;
				$amount_deposits_included = 0;

				// Display Payments area
				if (($deja_regle || $amount_credit_notes_included || $amount_deposits_included) && !getDolGlobalString('SUPPLIER_INVOICE_NO_PAYMENT_DETAILS')) {
					$this->_tableau_versements($pdf, $object, $posy, $outputlangs, $heightforfooter);
				}
				$posy = max($posy, $pdf->GetY()) + 4;

				// SLY: our company bank account for receiving payment (same as customer invoice)
				$posy = $this->_drawBankAccount($pdf, $object, $posy, $outputlangs);

				// Pagefoot
				$this->_pagefoot($pdf, $object, $outputlangs);
				if (method_exists($pdf, 'AliasNbPages')) {
					$pdf->AliasNbPages();  // @phan-suppress-current-line PhanUndeclaredMethod
				}

				$pdf->Close();

				$pdf->Output($file, 'F');

				// Add pdfgeneration hook
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0) {
					$this->error = $hookmanager->error;
					$this->errors = $hookmanager->errors;
				}

				dolChmod($file);

				$this->result = array('fullpath' => $file);

				return 1; // No error
			} else {
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		} else {
			$this->error = $langs->transnoentities("ErrorConstantNotDefined", "SUPPLIER_OUTPUTDIR");
			return 0;
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Show total to pay
	 *
	 *	@param	TCPDF				$pdf            Object PDF
	 *	@param  FactureFournisseur	$object         Object invoice
	 *	@param  float				$deja_regle     Amount already paid (in the currency of invoice)
	 *	@param	float				$posy			Position depart
	 *	@param	Translate			$outputlangs	Object langs
	 *	@return float								Position of cursor after output
	 */
	protected function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs)
	{
		// phpcs:enable
		global $conf, $mysoc, $hookmanager;

		// SLY debit note: invert original sign so amounts show positive (we issue to supplier)
		$sign = 1;
		if ($object->type == 2 && getDolGlobalString('INVOICE_POSITIVE_CREDIT_NOTE')) {
			$sign = -1;
		}
		$sign = -$sign; // invert so debit note always displays positive

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$tab2_top = $posy;
		$tab2_hl = 4;
		$pdf->SetFont('', '', $default_font_size - 1);

		// Total table
		$col1x = 120;
		$col2x = 170;
		if ($this->page_largeur < 210) { // To work with US executive format
			$col2x -= 20;
		}
		$largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

		$useborder = 0;
		$index = 0;

		$total_ht = ((isModEnabled("multicurrency") && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ht : $object->total_ht);
		$total_ttc = (isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ttc : $object->total_ttc;

		// First total row: "Total" only (Singapore non-GST); or "Total HT" then VAT then "Total TTC" (with VAT)
		$pdf->SetFillColor(255, 255, 255);
		$pdf->SetXY($col1x, $tab2_top);
		$totalLabel = $this->show_vat_for_pdf ? $outputlangs->transnoentities("TotalHT") : $outputlangs->transnoentities("Total");
		$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalLabel, 0, 'L', true);

		$currencyCode = (isModEnabled("multicurrency") && !empty($object->multicurrency_code)) ? $object->multicurrency_code : '';
		$pdf->SetXY($col2x, $tab2_top);
		$pdf->MultiCell($largcol2, $tab2_hl, price($sign * ($total_ht + (!empty($object->remise) ? $object->remise : 0)), 0, $outputlangs, 1, -1, -1, $currencyCode), 0, 'R', true);

		// Show VAT by rates and total (hidden for Singapore non-GST company, same as SLY invoice)
		$pdf->SetFillColor(248, 248, 248);

		$this->atleastoneratenotnull = 0;
		if ($this->show_vat_for_pdf) {
			foreach ($this->tva as $tvakey => $tvaval) {
				if ($tvakey > 0) {    // We do not display rate 0
					$this->atleastoneratenotnull++;

					$index++;
					$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

					$tvacompl = '';

					if (preg_match('/\*/', $tvakey)) {
						$tvakey = str_replace('*', '', $tvakey);
						$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
					}

					$totalvat = $outputlangs->transcountrynoentities("TotalVAT", $mysoc->country_code).' ';
					$totalvat .= vatrate($tvakey, true).$tvacompl;
					$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', true);

					$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $tvaval, 0, $outputlangs, 1, -1, -1, $currencyCode), 0, 'R', true);
				}
			}
			if (!$this->atleastoneratenotnull) { // If no vat at all
				$index++;
				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transcountrynoentities("TotalVAT", $mysoc->country_code), 0, 'L', true);
				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $object->total_tva, 0, $outputlangs, 1, -1, -1, $currencyCode), 0, 'R', true);

				// Total LocalTax1
				if (getDolGlobalString('FACTURE_LOCAL_TAX1_OPTION') == 'localtax1on' && $object->total_localtax1 > 0) {
					$index++;
					$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code), 0, 'L', true);
					$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $object->total_localtax1, 0, $outputlangs, 1, -1, -1, $currencyCode), 0, 'R', true);
				}

				// Total LocalTax2
				if (getDolGlobalString('FACTURE_LOCAL_TAX2_OPTION') == 'localtax2on' && $object->total_localtax2 > 0) {
					$index++;
					$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code), 0, 'L', true);
					$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $object->total_localtax2, 0, $outputlangs, 1, -1, -1, $currencyCode), 0, 'R', true);
				}
			} else {
				//Local tax 1
				foreach ($this->localtax1 as $tvakey => $tvaval) {
					if ($tvakey != 0) {
						$index++;
						$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

						$tvacompl = '';
						if (preg_match('/\*/', (string) $tvakey)) {
							$tvakey = str_replace('*', '', (string) $tvakey);
							$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
						}
						$totalvat = $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code).' ';
						$totalvat .= vatrate((string) abs((float) $tvakey), true).$tvacompl;
						$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', true);

						$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
						$pdf->MultiCell($largcol2, $tab2_hl, price($sign * (float) $tvaval, 0, $outputlangs, 1, -1, -1, $currencyCode), 0, 'R', true);
					}
				}

				//Local tax 2
				foreach ($this->localtax2 as $tvakey => $tvaval) {
					if ($tvakey != 0) {
						$index++;
						$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

						$tvacompl = '';
						if (preg_match('/\*/', (string) $tvakey)) {
							$tvakey = str_replace('*', '', (string) $tvakey);
							$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
						}
						$totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code).' ';
						$totalvat .= vatrate((string) abs((float) $tvakey), true).$tvacompl;
						$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', true);

						$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
						$pdf->MultiCell($largcol2, $tab2_hl, price($sign * (float) $tvaval, 0, $outputlangs, 1, -1, -1, $currencyCode), 0, 'R', true);
					}
				}
			}
		}

		// Total TTC (only when VAT is shown; when non-GST we already showed a single "Total" row)
		if ($this->show_vat_for_pdf) {
			$index++;
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->SetFillColor(224, 224, 224);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalTTC"), $useborder, 'L', true);

			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $total_ttc, 0, $outputlangs, 1, -1, -1, $currencyCode), $useborder, 'R', true);
		}

		$pdf->SetTextColor(0, 0, 0);
		$creditnoteamount = $object->getSumCreditNotesUsed((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? 1 : 0); // Warning, this also include excess received
		$depositsamount = $object->getSumDepositsUsed((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? 1 : 0);
		//print "x".$creditnoteamount."-".$depositsamount;exit;
		$resteapayer = price2num($total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 'MT');
		if (!empty($object->paid)) {
			$resteapayer = 0;
		}

		if (($deja_regle > 0 || $creditnoteamount > 0 || $depositsamount > 0) && !getDolGlobalString('INVOICE_NO_PAYMENT_DETAILS')) {
			// Already paid + Deposits
			$index++;
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("Paid"), 0, 'L', false);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($sign * ($deja_regle + $depositsamount), 0, $outputlangs, 1, -1, -1, $currencyCode), 0, 'R', false);

			// Credit note
			if ($creditnoteamount) {
				$labeltouse = ($outputlangs->transnoentities("CreditNotesOrExcessReceived") != "CreditNotesOrExcessReceived") ? $outputlangs->transnoentities("CreditNotesOrExcessReceived") : $outputlangs->transnoentities("CreditNotes");
				$index++;
				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $labeltouse, 0, 'L', false);
				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $creditnoteamount, 0, $outputlangs, 1, -1, -1, $currencyCode), 0, 'R', false);
			}

			// Escompte
			if ($object->close_code == FactureFournisseur::CLOSECODE_DISCOUNTVAT) {
				$index++;
				$pdf->SetFillColor(255, 255, 255);

				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("EscompteOfferedShort"), $useborder, 'L', true);
				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($sign * ($total_ttc - $deja_regle - $creditnoteamount - $depositsamount), 0, $outputlangs, 1, -1, -1, $currencyCode), $useborder, 'R', true);

				$resteapayer = 0;
			}

			$index++;
			$pdf->SetTextColor(0, 0, 60);
			$pdf->SetFillColor(224, 224, 224);
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("RemainderToPay"), $useborder, 'L', true);

			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $resteapayer, 0, $outputlangs, 1, -1, -1, $currencyCode), $useborder, 'R', true);
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetTextColor(0, 0, 0);
		}

		$parameters = array('pdf' => &$pdf, 'object' => &$object, 'outputlangs' => $outputlangs, 'index' => &$index, 'posy' => $posy);

		$reshook = $hookmanager->executeHooks('afterPDFTotalTable', $parameters, $this); // Note that $action and $object may have been modified by some hooks
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
		}

		$index++;
		return ($tab2_top + ($tab2_hl * $index));
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  SLY: draw our company (emetteur) bank account for receiving payment (same as invoice).
	 *
	 *  @param	TCPDF                 $pdf            Object PDF
	 *  @param	FactureFournisseur    $object         Supplier invoice object
	 *  @param	float                 $posy           Current Y position
	 *  @param	Translate             $outputlangs    Langs object
	 *  @return	float                                 New Y position after block (or unchanged if no bank)
	 */
	protected function _drawBankAccount(&$pdf, $object, $posy, $outputlangs)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
		$account = new Account($this->db);

		$bankid = 0;
		$factureRibNumber = getDolGlobalInt('FACTURE_RIB_NUMBER');
		if ($factureRibNumber) {
			$bankid = (int) $factureRibNumber;
		}
		if ($bankid <= 0) {
			// Fallback: first bank account of current entity (SLY receiving account)
			$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."bank_account";
			$sql .= " WHERE entity IN (".getEntity('bank_account').")";
			$sql .= " ORDER BY rowid ASC LIMIT 1";
			$resql = $this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				if ($obj) {
					$bankid = (int) $obj->rowid;
				}
			}
		}

		if ($bankid <= 0) {
			return $posy;
		}

		$account->fetch($bankid);
		if ($account->id <= 0) {
			return $posy;
		}

		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$curx = $this->marge_gauche;
		$cury = $posy + 4;

		$posy = pdf_bank($pdf, $outputlangs, $curx, $cury, $account, 0, $default_font_size);

		return $posy + 2;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   Show table for lines
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		float|int	$tab_top		Top position of table
	 *   @param		float|int	$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y (not used)
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @param		string		$currency		Currency code
	 *   @return	void
	 */
	protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $currency = '')
	{
		global $conf;

		// Force to disable hidetop and hidebottom
		$hidebottom = 0;
		if ($hidetop) {
			$hidetop = -1;
		}

		$currency = !empty($currency) ? $currency : $conf->currency;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in currency (SLY: same as invoice — "Currency: US Dollars", bold currency name)
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', 'B', $default_font_size);

		if (empty($hidetop)) {
			$currencyLabel = $outputlangs->transnoentitiesnoconv("Currency".$currency);
			$prefix = $outputlangs->transnoentities("Currency").": ";
			$pdf->SetFont('', '', $default_font_size - 2);
			$w1 = $pdf->GetStringWidth($prefix);
			$pdf->SetFont('', 'B', $default_font_size);
			$w2 = $pdf->GetStringWidth($currencyLabel);
			$w = $w1 + $w2 + 3;
			$x0 = $this->page_largeur - $this->marge_droite - $w;
			$pdf->SetXY($x0, $tab_top - 4);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->Write(2, $prefix);
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->Write(2, $currencyLabel);

			if (getDolGlobalString('MAIN_PDF_TITLE_BACKGROUND_COLOR')) {
				$pdf->RoundedRect($this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_droite - $this->marge_gauche, 5, $this->corner_radius, '1001', 'F', array(), explode(',', getDolGlobalString('MAIN_PDF_TITLE_BACKGROUND_COLOR')));
			}
		}

		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetFont('', '', $default_font_size - 1);

		// Output Rect
		$this->printRoundedRect($pdf, $this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height, $this->corner_radius, $hidetop, $hidebottom, 'D'); // Rect takes a length in 3rd parameter and 4th parameter

		if (empty($hidetop)) {
			$pdf->line($this->marge_gauche, $tab_top + 5, $this->page_largeur - $this->marge_droite, $tab_top + 5); // line takes a position y in 2nd parameter and 4th parameter

			// Description: width aligned with data column, centered (same as SLY invoice)
			$descColWidth = ($this->show_vat_for_pdf ? $this->posxtva : $this->posxup) - ($this->posxdesc - 1);
			$pdf->SetXY($this->posxdesc - 1, $tab_top + 1);
			$pdf->MultiCell($descColWidth, 2, $outputlangs->transnoentities("Designation"), '', 'C');
		}

		// VAT column (hidden for Singapore non-GST company, same as SLY invoice)
		if ($this->show_vat_for_pdf && !getDolGlobalString('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN')) {
			$pdf->line($this->posxtva - 1, $tab_top, $this->posxtva - 1, $tab_top + $tab_height);
			if (empty($hidetop)) {
				$pdf->SetXY($this->posxtva - 3, $tab_top + 1);
				$pdf->MultiCell($this->posxup - $this->posxtva + 3, 2, $outputlangs->transnoentities("VAT"), '', 'C');
			}
		}

		$pdf->line($this->posxup - 1, $tab_top, $this->posxup - 1, $tab_top + $tab_height);
		if (empty($hidetop)) {
			$pdf->SetXY($this->posxup - 1, $tab_top + 1);
			$pdf->MultiCell($this->posxqty - $this->posxup - 0.8, 2, $outputlangs->transnoentities("PriceUHT"), '', 'C');
		}

		$pdf->line($this->posxqty - 1, $tab_top, $this->posxqty - 1, $tab_top + $tab_height);
		if (empty($hidetop)) {
			$pdf->SetXY($this->posxqty - 1, $tab_top + 1);
			$pdf->MultiCell($this->posxunit - $this->posxqty - 0.8, 2, $outputlangs->transnoentities("Qty"), '', 'C');
		}

		if (getDolGlobalInt('PRODUCT_USE_UNITS')) {
			$pdf->line($this->posxunit - 1, $tab_top, $this->posxunit - 1, $tab_top + $tab_height);
			if (empty($hidetop)) {
				$pdf->SetXY($this->posxunit - 1, $tab_top + 1);
				$pdf->MultiCell(
					$this->posxdiscount - $this->posxunit - 0.8,
					2,
					$outputlangs->transnoentities("Unit"),
					'',
					'C'
				);
			}
		}

		if ($this->atleastonediscount) {
			$pdf->line($this->posxdiscount - 1, $tab_top, $this->posxdiscount - 1, $tab_top + $tab_height);
			if (empty($hidetop)) {
				$pdf->SetXY($this->posxdiscount - 1, $tab_top + 1);
				$pdf->MultiCell($this->postotalht - $this->posxdiscount - 1, 2, $outputlangs->transnoentities("ReductionShort"), '', 'C');
			}
		}

		$pdf->line($this->postotalht, $tab_top, $this->postotalht, $tab_top + $tab_height);
		if (empty($hidetop)) {
			// SLY: column header "Total" only (same as total row; no "Total (excl. tax)")
			$totalColWidth = $this->page_largeur - $this->marge_droite - $this->postotalht;
			$pdf->SetXY($this->postotalht - 1, $tab_top + 1);
			$pdf->MultiCell($totalColWidth, 2, $outputlangs->transnoentities("Total"), '', 'C');
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Show payments table
	 *
	 *  @param  TCPDF       $pdf            	Object PDF
	 *  @param  Object		$object         	Object to show
	 *  @param  float       $posy           	Position y in PDF
	 *  @param  Translate   $outputlangs    	Object langs for output
	 *  @param  float		$heightforfooter 	Height for footer
	 *  @return int                             Return integer <0 if KO, >0 if OK
	 */
	protected function _tableau_versements(&$pdf, $object, $posy, $outputlangs, $heightforfooter = 0)
	{
		// phpcs:enable
		global $conf;

		// SLY: invert sign so amounts display positive
		$sign = 1;
		if ($object->type == 2 && getDolGlobalString('INVOICE_POSITIVE_CREDIT_NOTE')) {
			$sign = -1;
		}
		$sign = -$sign;

		$tab3_posx = 120;
		$tab3_top = $posy + 8;
		$tab3_width = 80;
		$tab3_height = 4;
		if ($this->page_largeur < 210) { // To work with US executive format
			$tab3_posx -= 20;
		}

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont('', '', $default_font_size - 3);
		$pdf->SetXY($tab3_posx, $tab3_top - 4);
		$pdf->MultiCell(60, 3, $outputlangs->transnoentities("PaymentsAlreadyDone"), 0, 'L', false);

		$pdf->line($tab3_posx, $tab3_top, $tab3_posx + $tab3_width, $tab3_top);

		$pdf->SetFont('', '', $default_font_size - 4);
		$pdf->SetXY($tab3_posx, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Payment"), 0, 'L', false);
		$pdf->SetXY($tab3_posx + 21, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Amount"), 0, 'L', false);
		$pdf->SetXY($tab3_posx + 40, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Type"), 0, 'L', false);
		$pdf->SetXY($tab3_posx + 58, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Num"), 0, 'L', false);

		$pdf->line($tab3_posx, $tab3_top - 1 + $tab3_height, $tab3_posx + $tab3_width, $tab3_top - 1 + $tab3_height);

		$y = 0;

		$pdf->SetFont('', '', $default_font_size - 4);

		// Loop on each deposits and credit notes included
		//

		// Loop on each payment
		$sql = "SELECT p.datep as date, p.fk_paiement as type, p.num_paiement as num_payment, pf.amount as amount, pf.multicurrency_amount,";
		$sql .= " cp.code";
		$sql .= " FROM ".MAIN_DB_PREFIX."paiementfourn_facturefourn as pf, ".MAIN_DB_PREFIX."paiementfourn as p";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_paiement as cp ON p.fk_paiement = cp.id";
		$sql .= " WHERE pf.fk_paiementfourn = p.rowid and pf.fk_facturefourn = ".((int) $object->id);
		$sql .= " ORDER BY p.datep";
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$y += 3;
				$row = $this->db->fetch_object($resql);

				$pdf->SetXY($tab3_posx, $tab3_top + $y);
				$pdf->MultiCell(20, 3, dol_print_date($this->db->jdate($row->date), 'day', false, $outputlangs, true), 0, 'L', false);
				$pdf->SetXY($tab3_posx + 21, $tab3_top + $y);
				$pdf->MultiCell(20, 3, price($sign * ((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? $row->multicurrency_amount : $row->amount)), 0, 'L', false);
				$pdf->SetXY($tab3_posx + 40, $tab3_top + $y);
				$oper = $outputlangs->transnoentitiesnoconv("PaymentTypeShort".$row->code);

				$pdf->MultiCell(20, 3, $oper, 0, 'L', false);
				$pdf->SetXY($tab3_posx + 58, $tab3_top + $y);
				$pdf->MultiCell(30, 3, $row->num_payment, 0, 'L', false);

				$pdf->line($tab3_posx, $tab3_top + $y + 3, $tab3_posx + $tab3_width, $tab3_top + $y + 3);

				$i++;
			}
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return -1;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page. SLY format: letterhead (logo + our company) + document box + Bill To (supplier).
	 *
	 *  @param  TCPDF               $pdf            Object PDF
	 *  @param  FactureFournisseur  $object         Object to show
	 *  @param  int                 $showaddress    0=no, 1=yes
	 *  @param  Translate           $outputlangs    Object lang for output
	 *  @return	float|int                   		Return topshift value
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
	{
		global $conf, $langs;

		$ltrdirection = 'L';
		if ($outputlangs->trans("DIRECTION") == 'rtl') {
			$ltrdirection = 'R';
		}

		$outputlangs->loadLangs(array("main", "bills", "companies", "slycustom@slycustom"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Use system default language for company info labels
		$headerlangs = new Translate('', $conf);
		$headerlangs->setDefaultLang($conf->global->MAIN_LANG_DEFAULT ?? 'en_US');
		$headerlangs->loadLangs(array("main", "companies"));

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		// SLY: Letterhead = logo (left) + company info (right), same as sly_invoice
		$letterhead_height = 0;
		$posy = $this->marge_haute;
		$logo_width_mm = 38;
		$logo_max_height_mm = 20;
		$logo_company_gap_mm = 2;
		$company_info_x = $this->marge_gauche + $logo_width_mm + $logo_company_gap_mm;
		$company_info_width = $this->page_largeur - $company_info_x - $this->marge_droite - 5;

		$logo_height_used = 0;
		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO') && is_object($this->emetteur)) {
			if (!empty($this->emetteur->logo)) {
				$logodir = $conf->mycompany->dir_output;
				if (!empty($conf->mycompany->multidir_output[$object->entity])) {
					$logodir = $conf->mycompany->multidir_output[$object->entity];
				}
				$logo = (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') ? $logodir.'/logos/thumbs/'.$this->emetteur->logo_small : $logodir.'/logos/'.$this->emetteur->logo);
				if (is_readable($logo)) {
					$logo_height_used = min(pdf_getHeightForLogo($logo), $logo_max_height_mm);
					$pdf->Image($logo, $this->marge_gauche, $posy, 0, $logo_height_used);
				} else {
					$pdf->SetTextColor(200, 0, 0);
					$pdf->SetFont('', 'B', $default_font_size - 2);
					$pdf->SetXY($this->marge_gauche, $posy);
					$pdf->MultiCell($logo_width_mm, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
					$logo_height_used = 6;
				}
			} else {
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->SetFont('', 'B', $default_font_size - 1);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->MultiCell($logo_width_mm, 5, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
				$logo_height_used = 6;
			}
		}

		$pdf->SetTextColor(0, 0, 60);
		$reg = array();
		$company_start_y = $posy;
		if (is_object($this->emetteur)) {
			$pdf->SetXY($company_info_x, $posy);
			if (!empty($this->emetteur->country_code) && $this->emetteur->country_code == 'SG') {
				if ($this->emetteur->name) {
					$uenLabel = $headerlangs->transcountrynoentities("ProfId1", "SG");
					if (preg_match('/\((.*)\)/i', $uenLabel, $reg)) {
						$uenLabel = $reg[1];
					}
					$lh1 = $this->emetteur->name;
					if (!empty($this->emetteur->idprof1)) {
						$lh1 .= " (".$uenLabel.": ".$outputlangs->convToOutputCharset($this->emetteur->idprof1).")";
					}
					$pdf->SetFont('', 'B', $default_font_size);
					$pdf->MultiCell($company_info_width, 5, $outputlangs->convToOutputCharset($lh1), 0, 'L');
				}
				$addrParts = array();
				if (!empty($this->emetteur->address)) {
					$addrParts[] = str_replace("\n", ", ", $this->emetteur->address);
				}
				if (!empty($this->emetteur->town) || !empty($this->emetteur->zip)) {
					$addrParts[] = trim(($this->emetteur->town ? $this->emetteur->town : '').($this->emetteur->town && $this->emetteur->zip ? ' ' : '').($this->emetteur->zip ? $this->emetteur->zip : ''));
				}
				if (count($addrParts) > 0) {
					$pdf->SetX($company_info_x);
					$pdf->SetFont('', '', $default_font_size - 2);
					$pdf->MultiCell($company_info_width, 4, strtoupper(implode(", ", $addrParts)), 0, 'L');
				}
				$contactParts = array();
				if (!empty($this->emetteur->phone)) {
					$contactParts[] = $headerlangs->transnoentities("Phone").": ".$this->emetteur->phone;
				}
				if (!empty($this->emetteur->email)) {
					$contactParts[] = $headerlangs->transnoentities("Email").": ".$outputlangs->convToOutputCharset($this->emetteur->email);
				}
				if (count($contactParts) > 0) {
					$pdf->SetX($company_info_x);
					$pdf->SetFont('', '', $default_font_size - 2);
					$pdf->MultiCell($company_info_width, 4, implode(" - ", $contactParts), 0, 'L');
				}
			} else {
				$lh1 = $headerlangs->transnoentities("RegisteredOffice").": ".$this->emetteur->name;
				if (!empty($this->emetteur->address)) {
					$lh1 .= " - ".str_replace("\n", ", ", $this->emetteur->address);
				}
				if (!empty($this->emetteur->zip)) {
					$lh1 .= " - ".$this->emetteur->zip;
				}
				if (!empty($this->emetteur->town)) {
					$lh1 .= " ".$this->emetteur->town;
				}
				if (!empty($this->emetteur->country)) {
					$lh1 .= ", ".$this->emetteur->country;
				}
				$pdf->SetFont('', 'B', $default_font_size - 1);
				$pdf->MultiCell($company_info_width, 4, $outputlangs->convToOutputCharset($lh1), 0, 'L');
				$lh2 = '';
				if (!empty($this->emetteur->phone)) {
					$lh2 .= $headerlangs->transnoentities("Phone").": ".$this->emetteur->phone;
				}
				if (!empty($this->emetteur->email)) {
					$lh2 .= ($lh2 ? " - " : "").$headerlangs->transnoentities("Email").": ".$outputlangs->convToOutputCharset($this->emetteur->email);
				}
				if ($lh2 !== '') {
					$pdf->SetX($company_info_x);
					$pdf->SetFont('', '', $default_font_size - 2);
					$pdf->MultiCell($company_info_width, 4, $lh2, 0, 'L');
				}
			}
			$company_end_y = $pdf->GetY();
		} else {
			$company_end_y = $company_start_y;
		}
		$letterhead_height = max($logo_height_used, $company_end_y - $company_start_y) + 3;
		$this->letterhead_height = (int) $letterhead_height;
		$top_shift = 0;

		if ($showaddress) {
			$posy = (isset($letterhead_height) ? $letterhead_height : 0) + (getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 28 : 30);
			$posy += $top_shift;
			$posx = $this->marge_gauche;
			if (getDolGlobalInt('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->page_largeur - $this->marge_droite - 80;
			}

			$hautcadre = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 38 : 40;
			$widthrecbox = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 82;

			// SLY: Document info box (left) — Debit Note title, Ref, Date, Ref supplier, Date due
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($posx, $posy);
			$pdf->SetFillColor(230, 230, 230);
			$pdf->MultiCell($widthrecbox, $hautcadre, "", 0, 'R', 1);
			$pdf->SetTextColor(0, 0, 60);

			$docx = $posx + 2;
			$docy = $posy + 3;
			$pdf->SetXY($docx, $docy);
			$pdf->SetFont('', 'B', $default_font_size + 2);
			$title = $outputlangs->trans("SLYDebitNoteModel") != "SLYDebitNoteModel" ? $outputlangs->transnoentities("SLYDebitNoteModel") : "DEBIT NOTE";
			$pdf->MultiCell($widthrecbox - 2, 5, (function_exists('mb_strtoupper') ? mb_strtoupper($title, 'UTF-8') : strtoupper($title)), 0, 'R');
			$docy = $pdf->GetY();
			$pdf->SetXY($docx, $docy);
			$pdf->SetFont('', 'B', $default_font_size);
			$textref = $outputlangs->transnoentities("Ref")." : ".$outputlangs->convToOutputCharset($object->ref);
			$statut_draft = ($object->statut == 0); // 0 = draft for supplier invoice
			if ($statut_draft) {
				$pdf->SetTextColor(128, 0, 0);
				$textref .= ' - '.$outputlangs->transnoentities("NotValidated");
			}
			$pdf->MultiCell($widthrecbox - 2, 4, $textref, 0, 'R');
			$pdf->SetTextColor(0, 0, 60);
			$docy = $pdf->GetY();
			$pdf->SetXY($docx, $docy);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->transnoentities("DateInvoice")." : ".dol_print_date($object->date, "day", false, $outputlangs, true), 0, 'R');
			if (!empty($object->ref_supplier)) {
				$docy = $pdf->GetY();
				$pdf->SetXY($docx, $docy);
				$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->transnoentities("RefSupplier")." : ".$outputlangs->convToOutputCharset($object->ref_supplier), 0, 'R');
			}
			if ($object->type != 2) {
				$docy = $pdf->GetY();
				$pdf->SetXY($docx, $docy);
				$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->transnoentities("DateDue")." : ".dol_print_date($object->date_lim_reglement, "day", false, $outputlangs, true), 0, 'R');
			}
			$y_after_docbox = $pdf->GetY();

			// Bill To = supplier (recipient of the debit note)
			$usecontact = false;
			$arrayidcontact = $object->getIdContact('external', 'BILLING');
			if (count($arrayidcontact) > 0) {
				$usecontact = true;
				$object->fetch_contact($arrayidcontact[0]);
			}
			$thirdparty = ($usecontact && getDolGlobalInt('MAIN_USE_COMPANY_NAME_OF_CONTACT', 1)) ? $object->contact : $object->thirdparty;
			$carac_client_name = pdfBuildThirdpartyName($thirdparty, $outputlangs);
			$carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, ($usecontact ? $object->contact : ''), $usecontact, 'target', $object);

			$widthrecbox = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 100;
			if ($this->page_largeur < 210) {
				$widthrecbox = 84;
			}
			$posy = (isset($letterhead_height) ? $letterhead_height : 0) + (getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 28 : 30);
			$posy += $top_shift;
			$posx = $this->page_largeur - $this->marge_droite - $widthrecbox;
			if (getDolGlobalInt('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->marge_gauche;
			}

			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posx + 2, $posy - 5);
			$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillTo"), 0, $ltrdirection);
			$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

			$pdf->SetXY($posx + 2, $posy + 3);
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($widthrecbox, 2, $carac_client_name, 0, $ltrdirection);
			$posy = $pdf->getY();
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posx + 2, $posy + 1);
			$pdf->MultiCell($widthrecbox - 2, 4, $carac_client, 0, $ltrdirection);
			$y_after_tobox = max($pdf->GetY(), $posy + $hautcadre);
			$this->pagehead_bottom_y = max($y_after_docbox, $y_after_tobox);
		}

		$pdf->SetTextColor(0, 0, 0);
		return $top_shift;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show footer of page. Need this->emetteur object
	 *
	 *  @param  TCPDF               $pdf                PDF
	 *  @param  FactureFournisseur  $object             Object to show
	 *  @param  Translate           $outputlangs        Object lang for output
	 *  @param  int                 $hidefreetext       1=Hide free text
	 *  @return int                                     Return height of bottom margin including footer text
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		// SLY format: minimal footer (company at top in letterhead), no footer line
		$showdetails = 0;
		return pdf_pagefoot($pdf, $outputlangs, 'SUPPLIER_INVOICE_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, '', 1);
	}
}
