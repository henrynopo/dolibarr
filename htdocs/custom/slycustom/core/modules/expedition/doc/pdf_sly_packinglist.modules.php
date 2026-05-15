<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2012 Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin		<regis.houssin@inodbox.com>
 * Copyright (C) 2014-2015 Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2018-2020	Frédéric France    	<frederic.france@netlogic.fr>
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
 *	\file       htdocs/custom/slycustom/core/modules/expedition/doc/pdf_sly_packinglist.modules.php
 *	\ingroup    expedition
 *	\brief      Class file for SLY packing list (delivery) PDF template
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/expedition/modules_expedition.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

/**
 *	Class to build sending documents with SLY template
 */
class pdf_sly_packinglist extends ModelePdfExpedition
{
	/**
	 * @var DoliDb Database handler
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
	 * @var int     Save the name of generated file as the main doc when generating a doc with this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * @var array Minimum version of PHP required by module.
	 * e.g.: PHP ≥ 5.6 = array(5, 6)
	 */
	public $phpmin = array(5, 6);

	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * @var int page_largeur
	 */
	public $page_largeur;

	/**
	 * @var int page_hauteur
	 */
	public $page_hauteur;

	/**
	 * @var array format
	 */
	public $format;

	/**
	 * @var int marge_gauche
	 */
	public $marge_gauche;

	/**
	 * @var int marge_droite
	 */
	public $marge_droite;

	/**
	 * @var int marge_haute
	 */
	public $marge_haute;

	/**
	 * @var int marge_basse
	 */
	public $marge_basse;

	/**
	 * Issuer
	 * @var Societe object that emits
	 */
	public $emetteur;


	/**
	 *	Constructor
	 *
	 *	@param	DoliDB	$db		Database handler
	 */
	public function __construct($db = 0)
	{
		global $conf, $langs, $mysoc;

		$this->db = $db;
		$this->name = "sly_packinglist";
		$this->description = $langs->trans("DocumentModelStandardPDF");
		$this->update_main_doc_field = 1; // Save the name of generated file as the main doc when generating a doc with this template

		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

		$this->option_logo = 1; // Display logo

		// Get source company
		$this->emetteur = $mysoc;
		if (!$this->emetteur->country_code) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2); // By default if not defined
		}

		$this->tabTitleHeight = 5; // default height
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Function to build pdf onto disk
	 *
	 *	@param		Expedition	$object			    Object expedition to generate (or id if old method)
	 *	@param		Translate	$outputlangs		Lang output object
	 *  @param		string		$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int			$hidedetails		Do not show line details
	 *  @param		int			$hidedesc			Do not show desc
	 *  @param		int			$hideref			Do not show ref
	 *  @return     int         	    			1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $conf, $langs, $hookmanager;

		$object->fetch_thirdparty();

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		// Load traductions files required by page
		$outputlangs->loadLangs(array("main", "bills", "orders", "products", "dict", "companies", "propal", "deliveries", "sendings", "productbatch"));

		global $outputlangsbis;
		$outputlangsbis = null;
		$pdfUseAlsoLangCode = getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE');
		if (!empty($pdfUseAlsoLangCode) && $outputlangs->defaultlang != $pdfUseAlsoLangCode) {
			$outputlangsbis = new Translate('', $conf);
			$outputlangsbis->setDefaultLang($pdfUseAlsoLangCode);
			$outputlangsbis->loadLangs(array("main", "bills", "orders", "products", "dict", "companies", "propal", "deliveries", "sendings", "productbatch"));
		}

		$nblines = count($object->lines);

		// Loop on each lines to detect if there is at least one image to show
		$realpatharray = array();
		$this->atleastonephoto = false;
		if (getDolGlobalInt('MAIN_GENERATE_SHIPMENT_WITH_PICTURE')) {
			$objphoto = new Product($this->db);

			for ($i = 0; $i < $nblines; $i++) {
				if (empty($object->lines[$i]->fk_product)) {
					continue;
				}

				$objphoto->fetch($object->lines[$i]->fk_product);

				if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {
					$pdir = get_exdir($object->lines[$i]->fk_product, 2, 0, 0, $objphoto, 'product').$object->lines[$i]->fk_product."/photos/";
					$dir = $conf->product->dir_output.'/'.$pdir;
				} else {
					$pdir = get_exdir(0, 0, 0, 0, $objphoto, 'product');
					$dir = $conf->product->dir_output.'/'.$pdir;
				}

				$realpath = '';

				foreach ($objphoto->liste_photos($dir, 1) as $key => $obj) {
					if (!getDolGlobalInt('CAT_HIGH_QUALITY_IMAGES')) {		// If CAT_HIGH_QUALITY_IMAGES not defined, we use thumb if defined and then original photo
						if ($obj['photo_vignette']) {
							$filename = $obj['photo_vignette'];
						} else {
							$filename = $obj['photo'];
						}
					} else {
						$filename = $obj['photo'];
					}

					$realpath = $dir.$filename;
					$this->atleastonephoto = true;
					break;
				}

				if ($realpath) {
					$realpatharray[$i] = $realpath;
				}
			}
		}

		if (count($realpatharray) == 0) {
			$this->posxpicture = $this->posxweightvol;
		}

		if ($conf->expedition->dir_output) {
			// Definition of $dir and $file
			if ($object->specimen) {
				$dir = $conf->expedition->dir_output."/sending";
				$file = $dir."/SPECIMEN.pdf";
			} else {
				$expref = dol_sanitizeFileName($object->ref);
				$dir = $conf->expedition->dir_output."/sending/".$expref;
				$file = $dir."/".$expref.".pdf";
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
				$parameters = array('file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

				// Set nblines with the new facture lines content after hook
				$nblines = count($object->lines);

				$pdf = pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs);
				$heightforinfotot = 8; // Height reserved to output the info and total part
				$heightforfreetext = getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5); // Height reserved to output the free text on last page
				$heightforfooter = $this->marge_basse + 8; // Height reserved to output the footer (value include bottom margin)
				if (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS')) {
					$heightforfooter += 6;
				}
				$pdf->SetAutoPageBreak(1, 0);

				if (class_exists('TCPDF')) {
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));
				// Set path to the background PDF File
				$pdfBackground = getDolGlobalString('MAIN_ADD_PDF_BACKGROUND');
				if (!empty($pdfBackground)) {
					$pagecount = $pdf->setSourceFile($conf->mycompany->dir_output.'/'.$pdfBackground);
					$tplidx = $pdf->importPage(1);
				}

				$pdf->Open();
				$pagenb = 0;
				$pdf->SetDrawColor(128, 128, 128);

				if (method_exists($pdf, 'AliasNbPages')) {
					$pdf->AliasNbPages();
				}

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("Shipment"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("Shipment"));
				if (getDolGlobalInt('MAIN_DISABLE_PDF_COMPRESSION')) {
					$pdf->SetCompression(false);
				}

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

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

				// SLY: dynamic tab_top from header bottom (same as proforma)
				$gap_after_header = 4;
				$tab_top = (isset($this->pagehead_bottom_y) ? ($this->pagehead_bottom_y + $gap_after_header) : (90 + $top_shift + (isset($this->letterhead_height) ? $this->letterhead_height : 0)));
				$tab_top_newpage = (getDolGlobalString('MAIN_PDF_DONOTREPEAT_HEAD') ? 10 : 42 + $top_shift + (isset($this->letterhead_height) ? $this->letterhead_height : 0));
				$tab_height = 130;
				$tab_height_newpage = 150;

				$this->posxdesc = $this->marge_gauche + 1;

				// display note
				$notetoshow = empty($object->note_public) ? '' : $object->note_public;

				// Extrafields in note
				$extranote = $this->getExtrafieldsInHtml($object, $outputlangs);
				if (!empty($extranote)) {
					$notetoshow = dol_concatdesc($notetoshow, $extranote);
				}

				if (!empty($notetoshow) || !empty($object->tracking_number)) {
					$tab_top_alt = $tab_top;

					$pdf->SetFont('', 'B', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top - 1, $outputlangs->transnoentities("TrackingNumber")." :", 0, 1, false, true, 'L');
					$pdf->writeHTMLCell(190, 3, 109, $tab_top - 1, "Shipping Company :", 0, 1, false, true, 'L');
					
					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, 62, $tab_top - 1, $object->tracking_number, 0, 1, false, true, 'L');	
					$code = $outputlangs->getLabelFromKey($this->db, $object->shipping_method_id, 'c_shipment_mode', 'rowid', 'code');
					$pdf->writeHTMLCell(190, 3, 156, $tab_top - 1, $outputlangs->trans("SendingMethod".strtoupper($code)), 0, 1, false, true, 'L');	

					$tab_top_alt = $pdf->GetY();

					$pdf->SetFont('', 'B', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3,  $this->posxdesc - 1, $tab_top_alt, "Vessel :", 0, 1, false, true, 'L');
					
					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, 62, $tab_top_alt,  $this->getExtrafieldContent($object, 'vesselname'), 0, 1, false, true, 'L');

					$tab_top_alt = $pdf->GetY();

					$pdf->SetFont('', 'B', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3,  $this->posxdesc - 1, $tab_top_alt, "ETD :", 0, 1, false, true, 'L');
					$pdf->writeHTMLCell(190, 3,  109, $tab_top_alt, "ETA :", 0, 1, false, true, 'L');
					
					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, 62, $tab_top_alt,  $this->getExtrafieldContent($object, 'etd'), 0, 1, false, true, 'L');
					$pdf->writeHTMLCell(190, 3, 156, $tab_top_alt,  $this->getExtrafieldContent($object, 'eta'), 0, 1, false, true, 'L');
					
					$tab_top_alt = $pdf->GetY();

					$pdf->SetFont('', 'B', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3,  $this->posxdesc - 1, $tab_top_alt, "Country of Origin :", 0, 1, false, true, 'L');
					$pdf->writeHTMLCell(190, 3,  109, $tab_top_alt, "Port of Destination :", 0, 1, false, true, 'L');
					
					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, 62, $tab_top_alt, $this->getExtrafieldContent($object, 'origin'), 0, 1, false, true, 'L');
					$pdf->writeHTMLCell(190, 3, 156, $tab_top_alt, $object->location_incoterms, 0, 1, false, true, 'L');
					
					$tab_top_alt = $pdf->GetY();
					$tab_top_alt += 1;

					// Notes
					if (!empty($notetoshow)) {
					    $pdf->SetFont('', '', $default_font_size - 1); // In loop to manage multi-page
						$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top_alt, dol_htmlentitiesbr($notetoshow), 0, 1);
					}
					
					$nexY = $pdf->GetY();
					
					
					//Added port of destination, using incoterms location. SLY 2020.12.27
 
					$tab_top_alt = $pdf->GetY();
					
					$height_note = $nexY - $tab_top;

					// Rect takes a length in 3rd parameter
					$pdf->SetDrawColor(192, 192, 192);
					$pdf->Rect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_note + 1);

					$tab_height = $tab_height - $height_note;
					$tab_top = $nexY + 6;
				} else {
					$height_note = 0;
				}


				// Use new auto column system
				$this->prepareArrayColumnField($object, $outputlangs, $hidedetails, $hidedesc = 1, $hideref);

				// Table simulation to know the height of the title line
				$pdf->startTransaction();
				$this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs);
				$pdf->rollbackTransaction(true);


				$nexY = $tab_top + $this->tabTitleHeight;

				// Loop on each lines
				$pageposbeforeprintlines = $pdf->getPage();
				$pagenb = $pageposbeforeprintlines;
				for ($i = 0; $i < $nblines; $i++) {
					$curY = $nexY;
					$pdf->SetFont('', '', $default_font_size - 1); // Into loop to work with multipage
					$pdf->SetTextColor(0, 0, 0);

					// Define size of image if we need it
					$imglinesize = array();
					if (!empty($realpatharray[$i])) {
						$imglinesize = pdf_getSizeForImage($realpatharray[$i]);
					}

					$pdf->setTopMargin($tab_top_newpage);
					$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext + $heightforinfotot); // The only function to edit the bottom margin of current page to set it.
					$pageposbefore = $pdf->getPage();

					$showpricebeforepagebreak = 1;
					$posYAfterImage = 0;
					$posYAfterDescription = 0;
/* //SLY 2021.8.27
					if ($this->getColumnStatus('photo')) {
						// We start with Photo of product line
						if (isset($imglinesize['width']) && isset($imglinesize['height']) && ($curY + $imglinesize['height']) > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforsignature + $heightforinfotot))) {	// If photo too high, we moved completely on new page
							$pdf->AddPage('', '', true);
							if (!empty($tplidx)) {
								$pdf->useTemplate($tplidx);
							}
							//if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 0, $outputlangs);
							$pdf->setPage($pageposbefore + 1);

							$curY = $tab_top_newpage;

							// Allows data in the first page if description is long enough to break in multiples pages
							if (getDolGlobalString('MAIN_PDF_DATA_ON_FIRST_PAGE')) {
								$showpricebeforepagebreak = 1;
							} else {
								$showpricebeforepagebreak = 0;
							}
						}


						if (!empty($this->cols['photo']) && isset($imglinesize['width']) && isset($imglinesize['height'])) {
							$pdf->Image($realpatharray[$i], $this->getColumnContentXStart('photo'), $curY, $imglinesize['width'], $imglinesize['height'], '', '', '', 2, 300); // Use 300 dpi
							// $pdf->Image does not increase value return by getY, so we save it manually
							$posYAfterImage = $curY + $imglinesize['height'];
						}
					}
*/
					// Description of product line
					if ($this->getColumnStatus('desc')) {
						$pdf->startTransaction();

						$this->printColDescContent($pdf, $curY, 'desc', $object, $i, $outputlangs, $hideref, $hidedesc);

						$pageposafter = $pdf->getPage();
						if ($pageposafter > $pageposbefore) {	// There is a pagebreak
							$pdf->rollbackTransaction(true);

							$this->printColDescContent($pdf, $curY, 'desc', $object, $i, $outputlangs, $hideref, $hidedesc);

							$pageposafter = $pdf->getPage();
							$posyafter = $pdf->GetY();
							//var_dump($posyafter); var_dump(($this->page_hauteur - ($heightforfooter+$heightforfreetext+$heightforinfotot))); exit;
							if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforsignature + $heightforinfotot))) {	// There is no space left for total+free text
								if ($i == ($nblines - 1)) {	// No more lines, and no space left to show total, so we create a new page
									$pdf->AddPage('', '', true);
									if (!empty($tplidx)) {
										$pdf->useTemplate($tplidx);
									}
									//if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 0, $outputlangs);
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
						} else {	// No pagebreak
							$pdf->commitTransaction();
						}
						$posYAfterDescription = $pdf->GetY();
					}

					$nexY = max($pdf->GetY(), $posYAfterImage);
					$pageposafter = $pdf->getPage();

					$pdf->setPage($pageposbefore);
					$pdf->setTopMargin($this->marge_haute);
					$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.

					// We suppose that a too long description or photo were moved completely on next page
					if ($pageposafter > $pageposbefore && empty($showpricebeforepagebreak)) {
						$pdf->setPage($pageposafter);
						$curY = $tab_top_newpage;
					}

					// We suppose that a too long description is moved completely on next page
					if ($pageposafter > $pageposbefore) {
						$pdf->setPage($pageposafter);
						$curY = $tab_top_newpage;
					}

					$pdf->SetFont('', '', $default_font_size - 1); // We reposition the default font

					// weight
/* //SLY 2021.8.27
					$weighttxt = '';
					if ($object->lines[$i]->fk_product_type == 0 && $object->lines[$i]->weight) {
						$weighttxt = round($object->lines[$i]->weight * $object->lines[$i]->qty_shipped, 5).' '.measuringUnitString(0, "weight", $object->lines[$i]->weight_units, 1);
					}
					$voltxt = '';
					if ($object->lines[$i]->fk_product_type == 0 && $object->lines[$i]->volume) {
						$voltxt = round($object->lines[$i]->volume * $object->lines[$i]->qty_shipped, 5).' '.measuringUnitString(0, "volume", $object->lines[$i]->volume_units ? $object->lines[$i]->volume_units : 0, 1);
					}


					if ($this->getColumnStatus('weight')) {
						$this->printStdColumnContent($pdf, $curY, 'weight', $weighttxt.(($weighttxt && $voltxt) ? '<br>' : '').$voltxt, array('html'=>1));
						$nexY = max($pdf->GetY(), $nexY);
					}

					if ($this->getColumnStatus('qty_asked')) {
						$this->printStdColumnContent($pdf, $curY, 'qty_asked', $object->lines[$i]->qty_asked);
						$nexY = max($pdf->GetY(), $nexY);
					}
*/
					//Unit of Order. SLY 2021.8.27
					if ($this->getColumnStatus('unit_order')) {
						$this->printStdColumnContent($pdf, $curY, 'unit_order', measuringUnitString($object->lines[$i]->fk_unit));
						$nexY = max($pdf->GetY(), $nexY);
					}

					if ($this->getColumnStatus('qty_shipped')) {
						$this->printStdColumnContent($pdf, $curY, 'qty_shipped', price($object->lines[$i]->qty_shipped, 0, '', 1, 3));
						$nexY = max($pdf->GetY(), $nexY);
					}
					//Gross Weight. SLY 2021.8.27 — format same as Net Weight (thousand sep + 3 decimals)
					if ($this->getColumnStatus('weight_gross')) {
						$grossRaw = isset($object->lines[$i]->array_options['options_grossweight']) ? $object->lines[$i]->array_options['options_grossweight'] : '';
						$grossFormatted = ($grossRaw !== '' && $grossRaw !== null) ? price((float) $grossRaw, 0, '', 1, 3) : '';
						$this->printStdColumnContent($pdf, $curY, 'weight_gross', $grossFormatted);
						$nexY = max($pdf->GetY(), $nexY);
					}
					//Qty Cartons. SLY 2021.8.27
					if ($this->getColumnStatus('qty_carton')) {
						$this->printStdColumnContent($pdf, $curY, 'qty_carton', price($this->getExtrafieldContent($object->lines[$i], 'quantitycarton'), 0, '', 1, 0));
						$nexY = max($pdf->GetY(), $nexY);
					}
/* //SLY 2021.8.27
					if ($this->getColumnStatus('subprice')) {
						$this->printStdColumnContent($pdf, $curY, 'subprice', price($object->lines[$i]->subprice, 0, $outputlangs));
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Extrafields
					if (!empty($object->lines[$i]->array_options)) {
						foreach ($object->lines[$i]->array_options as $extrafieldColKey => $extrafieldValue) {
							if ($this->getColumnStatus($extrafieldColKey)) {
								$extrafieldValue = $this->getExtrafieldContent($object->lines[$i], $extrafieldColKey);
								$this->printStdColumnContent($pdf, $curY, $extrafieldColKey, $extrafieldValue);
								$nexY = max($pdf->GetY(), $nexY);
							}
						}
					}
*/
					// Add line
					if (getDolGlobalString('MAIN_PDF_DASH_BETWEEN_LINES') && $i < ($nblines - 1)) {
						$pdf->setPage($pageposafter);
						$pdf->SetLineStyle(array('dash'=>'1,1', 'color'=>array(80, 80, 80)));
						//$pdf->SetDrawColor(190,190,200);
						$pdf->line($this->marge_gauche, $nexY, $this->page_largeur - $this->marge_droite, $nexY);
						$pdf->SetLineStyle(array('dash'=>0));
					}

					// Detect if some page were added automatically and output _tableau for past pages
					while ($pagenb < $pageposafter) {
						$pdf->setPage($pagenb);
						if ($pagenb == $pageposbeforeprintlines) {
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1);
						} else {
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1);
						}
						$this->_pagefoot($pdf, $object, $outputlangs, 1);
						$pagenb++;
						$pdf->setPage($pagenb);
						$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
						if (!getDolGlobalString('MAIN_PDF_DONOTREPEAT_HEAD')) {
							$this->_pagehead($pdf, $object, 0, $outputlangs);
						}
					}
					if (isset($object->lines[$i + 1]->pagebreak) && $object->lines[$i + 1]->pagebreak) {
						if ($pagenb == 1) {
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1);
						} else {
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1);
						}
						$this->_pagefoot($pdf, $object, $outputlangs, 1);
						// New page
						$pdf->AddPage();
						if (!empty($tplidx)) {
							$pdf->useTemplate($tplidx);
						}
						$pagenb++;
						if (!getDolGlobalString('MAIN_PDF_DONOTREPEAT_HEAD')) {
							$this->_pagehead($pdf, $object, 0, $outputlangs);
						}
					}
				}

				// Show square
				if ($pagenb == 1) {
					$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0);
					$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				} else {
					$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0);
					$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				}

				// Display total area
				$posy = $this->_tableau_tot($pdf, $object, 0, $bottomlasttab, $outputlangs);

				// Pagefoot
				$this->_pagefoot($pdf, $object, $outputlangs);
				if (method_exists($pdf, 'AliasNbPages')) {
					$pdf->AliasNbPages();
				}

				$pdf->Close();

				$pdf->Output($file, 'F');

				// Add pdfgeneration hook
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0) {
					$this->error = $hookmanager->error;
					$this->errors = $hookmanager->errors;
				}

				$mainUmask = getDolGlobalString('MAIN_UMASK');
				if (!empty($mainUmask)) {
					@chmod($file, octdec($mainUmask));
				}

				$this->result = array('fullpath'=>$file);

				return 1; // No error
			} else {
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		} else {
			$this->error = $langs->transnoentities("ErrorConstantNotDefined", "EXP_OUTPUTDIR");
			return 0;
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Show total to pay
	 *
	 *	@param	TCPDF		$pdf            Object PDF
	 *	@param  Expedition	$object         Object expedition
	 *	@param  int			$deja_regle     Amount already paid
	 *	@param	int         $posy           Start Position
	 *	@param	Translate	$outputlangs	Objet langs
	 *	@return int							Position for suite
	 */
	protected function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs)
	{
		// phpcs:enable
		global $conf, $mysoc;

		$sign = 1;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$tab2_top = $posy;
		$tab2_hl = 4;
		$pdf->SetFont('', 'B', $default_font_size - 1);

		// Total table
		$col1x = $this->posxweightvol - 50;
		$col2x = $this->posxweightvol;
		/*if ($this->page_largeur < 210) // To work with US executive format
		{
			$col2x-=20;
		}*/
		if (!getDolGlobalString('SHIPPING_PDF_HIDE_ORDERED')) {
			$largcol2 = ($this->posxqtyordered - $this->posxweightvol);
		} else {
			$largcol2 = ($this->posxqtytoship - $this->posxweightvol);
		}

		$useborder = 0;
		$index = 0;

		$totalWeighttoshow = '';
		$totalVolumetoshow = '';

		// Load dim data
		$tmparray = $object->getTotalWeightVolume();
/* //SLY 2021.8.27
		$totalWeight = $tmparray['weight'];
		$totalVolume = $tmparray['volume'];
		$totalOrdered = $tmparray['ordered'];
*/
		$totalToShip = $tmparray['toship'];
		// Set trueVolume and volume_units not currently stored into database
/* //SLY 2021.8.27
		if ($object->trueWidth && $object->trueHeight && $object->trueDepth) {
			$object->trueVolume = price(($object->trueWidth * $object->trueHeight * $object->trueDepth), 0, $outputlangs, 0, 0);
			$object->volume_units = $object->size_units * 3;
		}

		if ($totalWeight != '') {
			$totalWeighttoshow = showDimensionInBestUnit($totalWeight, 0, "weight", $outputlangs);
		}
		if ($totalVolume != '') {
			$totalVolumetoshow = showDimensionInBestUnit($totalVolume, 0, "volume", $outputlangs);
		}
		if ($object->trueWeight) {
			$totalWeighttoshow = showDimensionInBestUnit($object->trueWeight, $object->weight_units, "weight", $outputlangs);
		}
		if ($object->trueVolume) {
			$totalVolumetoshow = showDimensionInBestUnit($object->trueVolume, $object->volume_units, "volume", $outputlangs);
		}
*/
		if ($this->getColumnStatus('desc')) {
			$this->printStdColumnContent($pdf, $tab2_top, 'desc', $outputlangs->transnoentities("Total"));
		}
/* //SLY 2021.8.27
		if ($this->getColumnStatus('weight')) {
			if ($totalWeighttoshow) {
				$this->printStdColumnContent($pdf, $tab2_top, 'weight', $totalWeighttoshow);
				$index++;
			}

			if ($totalVolumetoshow) {
				$y = $tab2_top + ($tab2_hl * $index);
				$this->printStdColumnContent($pdf, $y, 'weight', $totalVolumetoshow);
			}
		}

		if ($this->getColumnStatus('qty_asked') && $totalOrdered) {
			$this->printStdColumnContent($pdf, $tab2_top, 'qty_asked', $totalOrdered);
		}
*/
		if ($this->getColumnStatus('qty_shipped') && $totalToShip) {
			$this->printStdColumnContent($pdf, $tab2_top, 'qty_shipped', price($totalToShip, 0, '', 1, 3));
		}

		if ($this->getColumnStatus('weight_gross')) {
		    $grossTotalRaw = isset($object->array_options['options_totalgrossweight']) ? $object->array_options['options_totalgrossweight'] : '';
		    $grossTotalFormatted = ($grossTotalRaw !== '' && $grossTotalRaw !== null) ? price((float) $grossTotalRaw, 0, '', 1, 3) : '';
		    $this->printStdColumnContent($pdf, $tab2_top, 'weight_gross', $grossTotalFormatted);
		}
		
		if ($this->getColumnStatus('qty_carton')) {
		    $this->printStdColumnContent($pdf, $tab2_top, 'qty_carton', price($this->getExtrafieldContent($object, 'totalnocartons'), 0, '', 1, 0));
		}
/* //SLY 2021.8.27
		if ($this->getColumnStatus('subprice')) {
			$this->printStdColumnContent($pdf, $tab2_top, 'subprice', price($object->total_ht, 0, $outputlangs));
		}
*/
		$pdf->SetTextColor(0, 0, 0);

		return ($tab2_top + ($tab2_hl * $index));
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   Show table for lines
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		string		$tab_top		Top position of table
	 *   @param		string		$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		Hide top bar of array
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @return	void
	 */
	protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0)
	{
		global $conf;

		// Force to disable hidetop and hidebottom
		$hidebottom = 0;
		if ($hidetop) {
			$hidetop = -1;
		}

		$currency = !empty($currency) ? $currency : $conf->currency;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in (at tab_top - 1)
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $default_font_size - 2);

		if (empty($hidetop)) {
			//$conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR='230,230,230';
			$titleBgColor = getDolGlobalString('MAIN_PDF_TITLE_BACKGROUND_COLOR');
			if (!empty($titleBgColor)) {
				$pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_droite - $this->marge_gauche, $this->tabTitleHeight, 'F', null, explode(',', $titleBgColor));
			}
		}

		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetFont('', '', $default_font_size - 1);

		// Output Rect
		$this->printRect($pdf, $this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height, $hidetop, $hidebottom); // Rect takes a length in 3rd parameter and 4th parameter


		$this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs, $hidetop);

		if (empty($hidetop)) {
			$pdf->line($this->marge_gauche, $tab_top + $this->tabTitleHeight, $this->page_largeur - $this->marge_droite, $tab_top + $this->tabTitleHeight); // line takes a position y in 2nd parameter and 4th parameter
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page.
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Expedition	$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @return	void
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
	{
		global $conf, $langs, $mysoc;

		$langs->load("orders");
		$outputlangs->loadLangs(array("main", "orders", "sendings", "companies"));

		$ltrdirection = ($outputlangs->trans("DIRECTION") == 'rtl') ? 'R' : 'L';
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Use system default language for company info labels
		$headerlangs = new Translate('', $conf);
		$headerlangs->setDefaultLang($conf->global->MAIN_LANG_DEFAULT ?? 'en_US');
		$headerlangs->loadLangs(array("main", "companies"));

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		$draftWatermark = getDolGlobalString('SHIPPING_DRAFT_WATERMARK');
		if ($object->statut == $object::STATUS_DRAFT && !empty($draftWatermark)) {
			pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', $draftWatermark);
		}

		// SLY: Letterhead = logo (left) + company info (right), same as proforma
		$letterhead_height = 0;
		$posy = $this->marge_haute;
		$logo_width_mm = 38;
		$logo_max_height_mm = 20;
		$logo_company_gap_mm = 2;
		$company_info_x = $this->marge_gauche + $logo_width_mm + $logo_company_gap_mm;
		$company_info_width = $this->page_largeur - $company_info_x - $this->marge_droite - 5;

		$logo_height_used = 0;
		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO') && is_object($this->emetteur)) {
			if ($this->emetteur->logo) {
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
				if ($this->emetteur->address) {
					$addrParts[] = str_replace("\n", ", ", $this->emetteur->address);
				}
				if ($this->emetteur->town || $this->emetteur->zip) {
					$addrParts[] = trim(($this->emetteur->town ? $this->emetteur->town : '').($this->emetteur->town && $this->emetteur->zip ? ' ' : '').($this->emetteur->zip ? $this->emetteur->zip : ''));
				}
				if (count($addrParts) > 0) {
					$pdf->SetX($company_info_x);
					$pdf->SetFont('', '', $default_font_size - 2);
					$pdf->MultiCell($company_info_width, 4, strtoupper(implode(", ", $addrParts)), 0, 'L');
				}
				$contactParts = array();
				if ($this->emetteur->phone) {
					$contactParts[] = $headerlangs->transnoentities("Phone").": ".$this->emetteur->phone;
				}
				if ($this->emetteur->email) {
					$contactParts[] = $headerlangs->transnoentities("Email").": ".$outputlangs->convToOutputCharset($this->emetteur->email);
				}
				if (count($contactParts) > 0) {
					$pdf->SetX($company_info_x);
					$pdf->SetFont('', '', $default_font_size - 2);
					$pdf->MultiCell($company_info_width, 4, implode(" - ", $contactParts), 0, 'L');
				}
			} else {
				$lh1 = $headerlangs->transnoentities("RegisteredOffice").": ".$this->emetteur->name;
				if ($this->emetteur->address) {
					$lh1 .= " - ".str_replace("\n", ", ", $this->emetteur->address);
				}
				if ($this->emetteur->zip) {
					$lh1 .= " - ".$this->emetteur->zip;
				}
				if ($this->emetteur->town) {
					$lh1 .= " ".$this->emetteur->town;
				}
				if ($this->emetteur->country) {
					$lh1 .= ", ".$this->emetteur->country;
				}
				$pdf->SetFont('', 'B', $default_font_size - 1);
				$pdf->MultiCell($company_info_width, 4, $outputlangs->convToOutputCharset($lh1), 0, 'L');
				$lh2 = '';
				if ($this->emetteur->phone) {
					$lh2 .= $headerlangs->transnoentities("Phone").": ".$this->emetteur->phone;
				}
				if ($this->emetteur->email) {
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

		$origin = $object->origin;
		$origin_id = $object->origin_id;
		$object->fetch_origin(); // Load origin_object so recipient = customer shipping contact (third-party)

		if ($showaddress) {
			$posy = (isset($letterhead_height) ? $letterhead_height : 0) + (getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 28 : 30);
			$posy += $top_shift;
			$posx = $this->marge_gauche;
			if (getDolGlobalInt('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->page_largeur - $this->marge_droite - 80;
			}

			$hautcadre = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 38 : 40;
			$widthrecbox = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 82;

			// SLY: Document info box (left), no title — SendingSheet, Ref, Ref order, Order date, Customer code
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($posx, $posy);
			$pdf->SetFillColor(230, 230, 230);
			$pdf->MultiCell($widthrecbox, $hautcadre, "", 0, 'R', 1);
			$pdf->SetTextColor(0, 0, 60);

			$docx = $posx + 2;
			$docy = $posy + 3;
			$pdf->SetXY($docx, $docy);
			$pdf->SetFont('', 'B', $default_font_size + 2);
			$doctitle = $outputlangs->transnoentities("SendingSheet");
			$pdf->MultiCell($widthrecbox - 2, 5, (function_exists('mb_strtoupper') ? mb_strtoupper($doctitle, 'UTF-8') : strtoupper($doctitle)), 0, 'R');
			$docy = $pdf->GetY();
			$pdf->SetXY($docx, $docy);
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->transnoentities("RefSending")." : ".$outputlangs->convToOutputCharset($object->ref), 0, 'R');
			$pdf->SetFont('', '', $default_font_size - 2);

			if (!empty($conf->$origin->enabled)) {
				$outputlangs->load('orders');
				$classname = ucfirst($origin);
				$linkedobject = new $classname($this->db);
				if ($linkedobject->fetch($origin_id) >= 0) {
					$docy = $pdf->GetY();
					$pdf->SetXY($docx, $docy);
					$text = $linkedobject->ref;
					if ($linkedobject->ref_client) {
						$text .= ' ('.$linkedobject->ref_client.')';
					}
					$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->transnoentities("RefOrder")." : ".$outputlangs->convToOutputCharset($text), 0, 'R');
					$docy = $pdf->GetY();
					$pdf->SetXY($docx, $docy);
					$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->transnoentities("OrderDate")." : ".dol_print_date($linkedobject->date, "day", false, $outputlangs, true), 0, 'R');
				}
			}
			if (!empty($object->thirdparty->code_client)) {
				$docy = $pdf->GetY();
				$pdf->SetXY($docx, $docy);
				$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->transnoentities("CustomerCode")." : ".$outputlangs->convToOutputCharset($object->thirdparty->code_client), 0, 'R');
			}
			$y_after_docbox = $pdf->GetY();

			$carac_emetteur = '';
			$arrayidcontact = array();
			if (getDolGlobalInt('DOC_SHOW_FIRST_SALES_REP') && !empty($origin) && is_object($object->origin_object)) {
				$arrayidcontact = $object->origin_object->getIdContact('internal', 'SALESREPFOLL');
			}
			if (is_array($arrayidcontact) && count($arrayidcontact) > 0) {
				$object->fetch_user(reset($arrayidcontact));
				$carac_emetteur .= $outputlangs->transnoentities("Name").": ".$outputlangs->convToOutputCharset($object->user->getFullName($outputlangs))."\n";
			}
			$carac_emetteur .= pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

			// Recipient = third-party contact/address — Customer shipping contact (from source order). Use local contact so we don't alter $object->contact (card page must keep original third party).
			$usecontact = false;
			$recipientContact = null;
			if (!empty($origin) && is_object($object->origin_object)) {
				$arrayidcontact = $object->origin_object->getIdContact('external', 'SHIPPING');
				if (is_array($arrayidcontact) && count($arrayidcontact) > 0) {
					$recipientContact = new Contact($this->db);
					if ($recipientContact->fetch($arrayidcontact[0]) > 0) {
						$usecontact = true;
					} else {
						$recipientContact = null;
					}
				}
			}
			$thirdparty = ($usecontact && $recipientContact && ($recipientContact->socid != $object->thirdparty->id) && getDolGlobalInt('MAIN_USE_COMPANY_NAME_OF_CONTACT', 1)) ? $recipientContact : $object->thirdparty;
			$carac_client_name = pdfBuildThirdpartyName($thirdparty, $outputlangs);
			$carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, $recipientContact, $usecontact, 'targetwithdetails', $object);

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
			$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("Recipient"), 0, $ltrdirection);
			$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

			$pdf->SetXY($posx + 2, $posy + 3);
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($widthrecbox, 2, $carac_client_name, 0, $ltrdirection);
			$posy = $pdf->getY();
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetXY($posx + 2, $posy);
			$pdf->MultiCell($widthrecbox - 2, 4, $carac_client, 0, $ltrdirection);
			$y_after_tobox = max($pdf->GetY(), $posy + $hautcadre);
			$this->pagehead_bottom_y = max($y_after_docbox, $y_after_tobox);
		}

		$pdf->SetTextColor(0, 0, 0);
		return $top_shift;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   	Show footer of page. Need this->emetteur object
	 *
	 *   	@param	TCPDF		$pdf     			PDF
	 * 		@param	Expedition	$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	int								Return height of bottom margin including footer text
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		global $conf;
		// SLY: Use letterhead (company at top), so do not show company details in footer; no footer line
		$showdetails = 0;
		return pdf_pagefoot($pdf, $outputlangs, 'SHIPPING_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, '', 1);
	}

	/**
	 *   	Define Array Column Field
	 *
	 *   	@param	Expedition	   $object    	    common object
	 *   	@param	Translate	   $outputlangs     langs
	 *      @param	int			   $hidedetails		Do not show line details
	 *      @param	int			   $hidedesc		Do not show desc
	 *      @param	int			   $hideref			Do not show ref
	 *      @return	null
	 */
	public function defineColumnField($object, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $hookmanager;

		// Default field style for content
		$this->defaultContentsFieldsStyle = array(
			'align' => 'C', // R,C,L
			'padding' => array(1, 0.5, 1, 0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		);

		// Default field style for content
		$this->defaultTitlesFieldsStyle = array(
			'align' => 'C', // R,C,L
			'padding' => array(0.5, 0, 0.5, 0), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		);

		/*
		 * For exemple
		 $this->cols['theColKey'] = array(
		 'rank' => $rank, // int : use for ordering columns
		 'width' => 20, // the column width in mm
		 'title' => array(
		 'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
		 'label' => ' ', // the final label : used fore final generated text
		 'align' => 'L', // text alignement :  R,C,L
		 'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		 ),
		 'content' => array(
		 'align' => 'L', // text alignement :  R,C,L
		 'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		 ),
		 );
		 */

		$rank = 0; // do not use negative rank
		$this->cols['desc'] = array(
			'rank' => $rank,
			'width' => false, // only for desc
			'status' => true,
			'title' => array(
				'textkey' => 'Designation', // use lang key is usefull in somme case with module
				'align' => 'C',
				// 'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
				// 'label' => ' ', // the final label
				'padding' => array(0.5, 1, 0.5, 1.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
			'content' => array(
				'padding' => array(1, 0.5, 1, 1.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
		);
/*	//SLY 2021.8.27
		$rank = $rank + 10;
		$this->cols['photo'] = array(
			'rank' => $rank,
			'width' => getDolGlobalInt('MAIN_DOCUMENTS_WITH_PICTURE_WIDTH', 20), // in mm
			'status' => false,
			'title' => array(
				'textkey' => 'Photo',
				'label' => ' '
			),
			'content' => array(
				'padding' => array(0, 0, 0, 0), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
			'border-left' => false, // remove left line separator
		);

		if (getDolGlobalInt('MAIN_GENERATE_SHIPMENT_WITH_PICTURE') && !empty($this->atleastonephoto)) {
			$this->cols['photo']['status'] = true;
		}

		$rank = $rank + 10;
		$this->cols['weight'] = array(
			'rank' => $rank,
			'width' => 30, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'WeightVolShort'
			),
			'border-left' => true, // add left line separator
		);


		$rank = $rank + 10;
		$this->cols['subprice'] = array(
			'rank' => $rank,
			'width' => 19, // in mm
			'status' => getDolGlobalInt('MAIN_PDF_SHIPPING_DISPLAY_AMOUNT_HT') ? 1 : 0,
			'title' => array(
				'textkey' => 'PriceUHT'
			),
			'border-left' => true, // add left line separator
		);

		$rank = $rank + 10;
		$this->cols['totalexcltax'] = array(
			'rank' => $rank,
			'width' => 26, // in mm
			'status' => getDolGlobalInt('MAIN_PDF_SHIPPING_DISPLAY_AMOUNT_HT') ? 1 : 0,
			'title' => array(
				'textkey' => 'TotalHT'
			),
			'border-left' => true, // add left line separator
		);

		$rank = $rank + 10;
		$this->cols['qty_asked'] = array(
			'rank' => $rank,
			'width' => 30, // in mm
			'status' => !getDolGlobalString('SHIPPING_PDF_HIDE_ORDERED') ? 1 : 0,
			'title' => array(
				'textkey' => 'QtyOrdered'
			),
			'border-left' => true, // add left line separator
			'content' => array(
				'align' => 'C',
			),
		);
*/
		// Unit of order. SLY 2021.8.27
		$rank = $rank + 10;
		$this->cols['unit_order'] = array(
			'rank' => $rank,
			'width' => 15, // in mm
			'status' => getDolGlobalInt('PRODUCT_USE_UNITS') ? 1 : 0,
			'title' => array(
				'textkey' => 'Unit'
			),
			'border-left' => true, // add left line separator
			'content' => array(
				'align' => 'C',
			),
		);

		// Net Weight. SLY 2020.12.27
		$rank = $rank + 10;
		$this->cols['qty_shipped'] = array(
			'rank' => $rank,
			'width' => 25, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'Net Weight'	//'textkey' => 'QtyToShip', replaced. SLY2020.12.27
			),
			'border-left' => true, // add left line separator
			'content' => array(
				'align' => 'C',
			),
		);

		// Gross Weight. SLY 2020.12.27
		$rank = $rank + 10;
		$this->cols['weight_gross'] = array(
			'rank' => $rank,
			'width' => 25, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'Gross Weight'
			),
			'border-left' => true, // add left line separator
			'content' => array(
				'align' => 'C',
			),
		);
		
		// Qty Cartons. SLY 2020.12.27
		$rank = $rank + 10;
		$this->cols['qty_carton'] = array(
			'rank' => $rank,
			'width' => 25, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'Qty Carton'
			),
			'border-left' => true, // add left line separator
			'content' => array(
				'align' => 'C',
			),
		);

/*	//SLY 2020.12.27
		// Add extrafields cols
		if (!empty($object->lines)) {
			$line = reset($object->lines);
			$this->defineColumnExtrafield($line, $outputlangs, $hidedetails);
		}
*/
		$parameters = array(
			'object' => $object,
			'outputlangs' => $outputlangs,
			'hidedetails' => $hidedetails,
			'hidedesc' => $hidedesc,
			'hideref' => $hideref
		);

		$reshook = $hookmanager->executeHooks('defineColumnField', $parameters, $this); // Note that $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		} elseif (empty($reshook)) {
			$this->cols = array_replace($this->cols, $hookmanager->resArray); // array_replace is used to preserve keys
		} else {
			$this->cols = $hookmanager->resArray;
		}
	}
}
