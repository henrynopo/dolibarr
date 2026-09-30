<?php
/* Copyright (C) 2004-2014  Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin		<regis.houssin@inodbox.com>
 * Copyright (C) 2008		Raphael Bertrand	<raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2013	Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2012      	Christophe Battarel <christophe.battarel@altairis.fr>
 * Copyright (C) 2012       Cedric Salvador     <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015       Marcos García       <marcosgdf@gmail.com>
 * Copyright (C) 2017       Ferran Marcet       <fmarcet@2byte.es>
 * Copyright (C) 2018-2020  Frédéric France     <frederic.france@netlogic.fr>
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
 *	\file       htdocs/custom/slycustom/core/modules/commande/doc/pdf_sly_order.modules.php
 *	\ingroup    commande
 *	\brief      File of Class to generate PDF orders with template Eratosthène
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/commande/modules_commande.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';

// Define pdf_bank_sly if not already defined (safe override to increase BankCode width)
if (!function_exists('pdf_bank_sly')) {
	function pdf_bank_sly(&$pdf, $outputlangs, $curx, $cury, $account, $onlynumber = 0, $default_font_size = 10)
	{
		global $mysoc, $conf;

		require_once DOL_DOCUMENT_ROOT . '/core/class/html.formbank.class.php';

		$diffsizetitle = getDolGlobalInt('PDF_DIFFSIZE_TITLE', 3);
		$diffsizecontent = getDolGlobalInt('PDF_DIFFSIZE_CONTENT', 4);
		$pdf->SetXY($curx, $cury);

		if (empty($onlynumber)) {
			$pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByTransferOnThisBankAccount') . ':', 0, 'L', false);
			$cury += 4;
		}

		$outputlangs->load("banks");

		$bickey = "BICNumber";
		if ($account->getCountryCode() == 'IN') {
			$bickey = "SWIFT";
		}

		$usedetailedbban = $account->useDetailedBBAN();
		if ($usedetailedbban) {
			$savcurx = $curx;

			if (empty($onlynumber)) {
				$pdf->SetFont('', '', $default_font_size - $diffsizecontent);
				$pdf->SetXY($curx, $cury);
				$pdf->MultiCell(100, 3, $outputlangs->transnoentities("Bank") . ': ' . $outputlangs->convToOutputCharset($account->bank), 0, 'L', false);
				$cury += 3;
			}

			if (!getDolGlobalString('PDF_BANK_HIDE_NUMBER_SHOW_ONLY_BICIBAN')) {
				if (empty($onlynumber)) {
					$pdf->line($curx + 1, $cury + 1, $curx + 1, $cury + 6);
				}

				$bank_number_length = 0;
				foreach ($account->getFieldsToShow() as $val) {
					$pdf->SetXY($curx, $cury + 4);
					$pdf->SetFont('', '', $default_font_size - 3);

					if ($val == 'BankCode') {
						$tmplength = 24; // increased width
						$content = $account->code_banque;
					} elseif ($val == 'DeskCode') {
						$tmplength = 18;
						$content = $account->code_guichet;
					} elseif ($val == 'BankAccountNumber') {
						$tmplength = 24;
						$content = $account->number;
					} elseif ($val == 'BankAccountNumberKey') {
						$tmplength = 15;
						$content = $account->cle_rib;
					} elseif ($val == 'IBAN' || $val == 'BIC') {
						$tmplength = 0;
						$content = '';
					} else {
						dol_print_error($account->db, 'Unexpected value for getFieldsToShow: ' . $val);
						break;
					}

					if ($content == '') {
						continue;
					}

					$pdf->MultiCell($tmplength, 3, $outputlangs->convToOutputCharset($content), 0, 'C', false);
					$pdf->SetXY($curx, $cury + 1);
					$curx += $tmplength;
					$pdf->SetFont('', 'B', $default_font_size - $diffsizecontent);
					$pdf->MultiCell($tmplength, 3, $outputlangs->transnoentities($val), 0, 'C', false);
					if (empty($onlynumber)) {
						$pdf->line($curx, $cury + 1, $curx, $cury + 7);
					}

					$bank_number_length = 8;
				}

				$curx = $savcurx;
				$cury += $bank_number_length;
			}
		} elseif (!empty($account->number)) {
			$pdf->SetFont('', 'B', $default_font_size - $diffsizecontent);
			$pdf->SetXY($curx, $cury);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("Bank") . ': ' . $outputlangs->convToOutputCharset($account->bank), 0, 'L', false);
			$cury += 3;

			$pdf->SetFont('', 'B', $default_font_size - $diffsizecontent);
			$pdf->SetXY($curx, $cury);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("BankAccountNumber") . ': ' . $outputlangs->convToOutputCharset($account->number), 0, 'L', false);
			$cury += 3;

			if ($diffsizecontent <= 2) {
				$cury += 1;
			}
		}

		$pdf->SetFont('', '', $default_font_size - $diffsizecontent);

		if (empty($onlynumber) && !empty($account->address)) {
			$pdf->SetXY($curx, $cury);
			$val = $outputlangs->transnoentities("Residence") . ': ' . $outputlangs->convToOutputCharset($account->address);
			$pdf->MultiCell(100, 3, $val, 0, 'L', false);
			$tmpy = $pdf->getStringHeight(100, $val);
			$cury += $tmpy;
		}

		if (!empty($account->owner_name)) {
			$pdf->SetXY($curx, $cury);
			$val = $outputlangs->transnoentities("BankAccountOwner") . ': ' . $outputlangs->convToOutputCharset($account->owner_name);
			$pdf->MultiCell(100, 3, $val, 0, 'L', false);
			$tmpy = $pdf->getStringHeight(100, $val);
			$cury += $tmpy;
		} elseif (!$usedetailedbban) {
			$cury += 1;
		}

		$ibankey = FormBank::getIBANLabel($account);

		if (!empty($account->iban)) {
			$ibanDisplay_temp = str_replace(' ', '', $outputlangs->convToOutputCharset($account->iban));
			$ibanDisplay = "";

			$nbIbanDisplay_temp = dol_strlen($ibanDisplay_temp);
			for ($i = 0; $i < $nbIbanDisplay_temp; $i++) {
				$ibanDisplay .= $ibanDisplay_temp[$i];
				if ($i % 4 == 3 && $i > 0) {
					$ibanDisplay .= " ";
				}
			}

			$pdf->SetFont('', 'B', $default_font_size - 3);
			$pdf->SetXY($curx, $cury);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities($ibankey) . ': ' . $ibanDisplay, 0, 'L', false);
			$cury += 3;
		}

		if (!empty($account->bic)) {
			$pdf->SetFont('', 'B', $default_font_size - 3);
			$pdf->SetXY($curx, $cury);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities($bickey) . ': ' . $outputlangs->convToOutputCharset($account->bic), 0, 'L', false);
		}

		return $pdf->getY();
	}
}


/**
 *	Class to generate PDF orders with template Eratosthene
 */
class pdf_sly_order extends ModelePDFCommandes
{
	/**
	 * @var DoliDb Database handler
	 */
	public $db;

	/**
	 * @var int The environment ID when using a multicompany module
	 */
	public $entity;

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
	 * @var Societe	Object that emits
	 */
	public $emetteur;

	/**
	 * @var array of document table columns
	 */
	public $cols;


	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		// Translations
		$langs->loadLangs(array("main", "bills", "products"));

		$this->db = $db;
		$this->name = "sly_order";
		$this->description = $langs->trans('PDFEratostheneDescription');
		$this->update_main_doc_field = 1; // Save the name of generated file as the main doc when generating a doc with this template

		// Dimension page
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
		$this->option_codeproduitservice = 1; // Display product-service code
		$this->option_multilang = 1; // Available in several languages
		$this->option_escompte = 0; // Displays if there has been a discount
		$this->option_credit_note = 0; // Support credit notes
		$this->option_freetext = 1; // Support add of a personalised text
		$this->option_draft_watermark = 1; // Support add of a watermark on drafts

		// Get source company
		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2); // By default, if was not defined
		}

		// Define position of columns
		$this->posxdesc = $this->marge_gauche + 1; // used for notes ans other stuff


		$this->tabTitleHeight = 5; // default height

		//  Use new system for position of columns, view  $this->defineColumnField()

		$this->tva = array();
		$this->tva_array = array();
		$this->localtax1 = array();
		$this->localtax2 = array();
		$this->atleastoneratenotnull = 0;
		$this->atleastonediscount = 0;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build pdf onto disk
	 *
	 *  @param		Commande	$object				Object to generate
	 *  @param		Translate	$outputlangs		Lang output object
	 *  @param		string		$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int			$hidedetails		Do not show line details
	 *  @param		int			$hidedesc			Do not show desc
	 *  @param		int			$hideref			Do not show ref
	 *  @param		array|null	$moreparams		Extra options (e.g. 'add_terms' => 1 to append sales terms PDF)
	 *  @return     int             			    1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		// phpcs:enable
		global $user, $langs, $conf, $mysoc, $db, $hookmanager, $nblines;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		// Load translation files required by the page (slycustom for GST/Prepayment etc.)
		$outputlangs->loadLangs(array("main", "dict", "companies", "bills", "products", "orders", "deliveries", "compta", "slycustom"));

		global $outputlangsbis;
		$outputlangsbis = null;
		$pdfUseAlsoLangCode = getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE');
		if (!empty($pdfUseAlsoLangCode) && $outputlangs->defaultlang != $pdfUseAlsoLangCode) {
			$outputlangsbis = new Translate('', $conf);
			$outputlangsbis->setDefaultLang($pdfUseAlsoLangCode);
			$outputlangsbis->loadLangs(array("main", "dict", "companies", "bills", "products", "orders", "deliveries", "compta", "slycustom"));
		}

		$nblines = count($object->lines);

		$hidetop = getDolGlobalInt('MAIN_PDF_DISABLE_COL_HEAD_TITLE', 0);

		// Loop on each lines to detect if there is at least one image to show
		$realpatharray = array();
		$this->atleastonephoto = false;
		if (getDolGlobalInt('MAIN_GENERATE_ORDERS_WITH_PICTURE')) {
			$objphoto = new Product($this->db);

			for ($i = 0; $i < $nblines; $i++) {
				if (empty($object->lines[$i]->fk_product)) {
					continue;
				}

				$objphoto->fetch($object->lines[$i]->fk_product);
				//var_dump($objphoto->ref);exit;
				if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {
					$pdir[0] = get_exdir($objphoto->id, 2, 0, 0, $objphoto, 'product') . $objphoto->id . "/photos/";
					$pdir[1] = get_exdir(0, 0, 0, 0, $objphoto, 'product') . dol_sanitizeFileName($objphoto->ref) . '/';
				} else {
					$pdir[0] = get_exdir(0, 0, 0, 0, $objphoto, 'product'); // default
					$pdir[1] = get_exdir($objphoto->id, 2, 0, 0, $objphoto, 'product') . $objphoto->id . "/photos/"; // alternative
				}

				$arephoto = false;
				foreach ($pdir as $midir) {
					if (!$arephoto) {
						if ($conf->product->entity != $objphoto->entity) {
							$dir = $conf->product->multidir_output[$objphoto->entity] . '/' . $midir; //Check repertories of current entities
						} else {
							$dir = $conf->product->dir_output . '/' . $midir; //Check repertory of the current product
						}

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

							$realpath = $dir . $filename;
							$arephoto = true;
							$this->atleastonephoto = true;
						}
					}
				}

				if ($realpath && $arephoto) {
					$realpatharray[$i] = $realpath;
				}
			}
		}



		if ($conf->commande->multidir_output[$conf->entity]) {
			$object->fetch_thirdparty();

			$deja_regle = 0;

			// Definition of $dir and $file
			if ($object->specimen) {
				$dir = $conf->commande->multidir_output[$conf->entity];
				$file = $dir . "/SPECIMEN.pdf";
			} else {
				$objectref = dol_sanitizeFileName($object->ref);
				$dir = $conf->commande->multidir_output[$object->entity] . "/" . $objectref;
				$file = $dir . "/" . $objectref . ".pdf";
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
					include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
					$hookmanager = new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

				// Set nblines with the new command lines content after hook
				$nblines = count($object->lines);

				// Create pdf instance
				$pdf = pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
				$pdf->SetAutoPageBreak(1, 0);

				$heightforinfotot = 40; // Height reserved to output the info and total part
				if ($this->name == 'sly_proforma') {
					$heightforinfotot += 4; // Extra height for Prepayment row
				}
				$heightforfreetext = getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5); // Height reserved to output the free text on last page
				$heightforfooter = $this->marge_basse + (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 22 : 12); // Height reserved to output the footer (value include bottom margin)

				if (class_exists('TCPDF')) {
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));
				// Set path to the background PDF File
				$pdfBackground = getDolGlobalString('MAIN_ADD_PDF_BACKGROUND');
				if (!empty($pdfBackground)) {
					$logodir = $conf->mycompany->dir_output;
					if (!empty($conf->mycompany->multidir_output[$object->entity])) {
						$logodir = $conf->mycompany->multidir_output[$object->entity];
					}
					$pagecount = $pdf->setSourceFile($logodir . '/' . $pdfBackground);
					$tplidx = $pdf->importPage(1);
				}

				$pdf->Open();
				$pagenb = 0;
				$pdf->SetDrawColor(128, 128, 128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("PdfOrderTitle"));
				$pdf->SetCreator("Dolibarr " . DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref) . " " . $outputlangs->transnoentities("PdfOrderTitle") . " " . $outputlangs->convToOutputCharset($object->thirdparty->name));
				if (getDolGlobalInt('MAIN_DISABLE_PDF_COMPRESSION')) {
					$pdf->SetCompression(false);
				}

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

				// Set $this->atleastonediscount if you have at least one discount
				for ($i = 0; $i < $nblines; $i++) {
					if ($object->lines[$i]->remise_percent) {
						$this->atleastonediscount++;
					}
				}


				// New page
				$pdf->AddPage();
				if (!empty($tplidx)) {
					$pdf->useTemplate($tplidx);
				}
				$pagenb++;
				$pagehead = $this->_pagehead($pdf, $object, 1, $outputlangs, $outputlangsbis);
				$top_shift = (is_array($pagehead) ? $pagehead['top_shift'] : (int) $pagehead);
				$shipp_shift = (is_array($pagehead) && isset($pagehead['shipp_shift']) ? $pagehead['shipp_shift'] : 0);
				$pdf->SetTextColor(0, 0, 0);

				// SLY: dynamic tab_top from header bottom (supplementary block and table follow address boxes with small gap)
				$gap_after_header = 4;
				$tab_top = (isset($this->pagehead_bottom_y) ? ($this->pagehead_bottom_y + $gap_after_header) : (90 + $top_shift + (isset($this->letterhead_height) ? $this->letterhead_height : 0)));
				$tab_top_newpage = (getDolGlobalString('MAIN_PDF_DONOTREPEAT_HEAD') ? 10 : 42 + $top_shift + (isset($this->letterhead_height) ? $this->letterhead_height : 0));

				// Displays notes
				$notetoshow = empty($object->note_public) ? '' : $object->note_public;
				if (getDolGlobalInt('MAIN_ADD_SALE_REP_SIGNATURE_IN_NOTE')) {
					// Get first sale rep
					if (is_object($object->thirdparty)) {
						$salereparray = $object->thirdparty->getSalesRepresentatives($user);
						$salerepobj = new User($this->db);
						$salerepobj->fetch($salereparray[0]['id']);
						if (!empty($salerepobj->signature)) {
							$notetoshow = dol_concatdesc($notetoshow, $salerepobj->signature);
						}
					}
				}

				// Consignee Shipping  //added. SLY 2021.8.17
				$usecontact_shipping = false;
				$arrayidcontact_shipping = $object->getIdContact('external', 'SHIPPING');
				if (count($arrayidcontact_shipping) > 0) {
					$usecontact_shipping = true;
					$result = $object->fetch_contact($arrayidcontact_shipping[0]);
				}
				if ($usecontact_shipping && getDolGlobalInt('MAIN_USE_COMPANY_NAME_OF_CONTACT', 1)) {
					$consigneeshipping = $object->contact;
				} else {
					$consigneeshipping = $object->thirdparty;
				}

				// Consignee Billing  //added. SLY 2021.8.17
				$usecontact_billing = false;
				$arrayidcontact_billing = $object->getIdContact('external', 'BILLING');
				if (count($arrayidcontact_billing) > 0) {
					$usecontact_billing = true;
					$result = $object->fetch_contact($arrayidcontact_billing[0]);
				}
				if ($usecontact_billing && getDolGlobalInt('MAIN_USE_COMPANY_NAME_OF_CONTACT', 1)) {
					$consigneebilling = $object->contact;
				} else {
					$consigneebilling = $object->thirdparty;
				}

				//added. Compare billing / shipping contact and split if different. SLY 2021.8.17
				if ($usecontact_shipping && $usecontact_billing && $consigneeshipping->socid == $consigneebilling->socid && $consigneeshipping->socid != $object->thirdparty->id) {
					$carac_consignee_name = $outputlangs->trans('consignee') . ' : ' . pdfBuildThirdpartyName($consigneeshipping, $outputlangs);
					$notetoshow = dol_concatdesc($notetoshow, $carac_consignee_name);

					$carac_consignee = pdf_build_address($outputlangs, $this->emetteur, $consigneeshipping, ($usecontact_shipping ? $consigneeshipping : ''), $usecontact_shipping, 'target', $object);
					$notetoshow = dol_concatdesc($notetoshow, $carac_consignee);
				} else {
					if ($usecontact_shipping && $consigneeshipping->socid != $object->thirdparty->id) {
						$carac_consigneeshipping_name = $outputlangs->trans('consigneeshipping') . ' : ';
						$notetoshow = dol_concatdesc($notetoshow, $carac_consigneeshipping_name);

						$carac_consigneeshipping = pdf_build_address($outputlangs, $this->emetteur, $consigneeshipping, ($usecontact_shipping ? $consigneeshipping : ''), $usecontact_shipping, 'target', $object);
						$notetoshow = dol_concatdesc($notetoshow, $carac_consigneeshipping);
					}
					if ($usecontact_billing && $consigneebilling->socid != $object->thirdparty->id) {
						$carac_consigneebilling_name = $outputlangs->trans('consigneebilling') . ' : ' . pdfBuildThirdpartyName($consigneebilling, $outputlangs);
						$notetoshow = dol_concatdesc($notetoshow, $carac_consigneebilling_name);
					}
				}

				// Incoterm		//Move to the table of extrafield notes. SLY.2021.8.17
				$height_incoterms = 0;
				if (!empty($conf->incoterm->enabled)) {
					$desc_incoterms = $object->getIncotermsForPDF();
					$notetoshow = dol_concatdesc($notetoshow, $desc_incoterms);
				}

				// Extrafields in note
				$extranote = $this->getExtrafieldsInHtml($object, $outputlangs);
				if (!empty($extranote)) {
					$notetoshow = dol_concatdesc($notetoshow, $extranote);
				}

				$pagenb = $pdf->getPage();
				if ($notetoshow) {
					$tab_top -= 2;

					$tab_width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
					$pageposbeforenote = $pagenb;

					$substitutionarray = pdf_getSubstitutionArray($outputlangs, null, $object);
					complete_substitutions_array($substitutionarray, $outputlangs, $object);
					$notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
					$notetoshow = convertBackOfficeMediasLinksToPublicLinks($notetoshow);

					$pdf->startTransaction();

					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
					// Description
					$pageposafternote = $pdf->getPage();
					$posyafter = $pdf->GetY();

					if ($pageposafternote > $pageposbeforenote) {
						$pdf->rollbackTransaction(true);

						// prepare pages to receive notes
						while ($pagenb < $pageposafternote) {
							$pdf->AddPage();
							$pagenb++;
							if (!empty($tplidx)) {
								$pdf->useTemplate($tplidx);
							}
							if (!getDolGlobalString('MAIN_PDF_DONOTREPEAT_HEAD')) {
								$this->_pagehead($pdf, $object, 0, $outputlangs);
							}
							// $this->_pagefoot($pdf,$object,$outputlangs,1);
							$pdf->setTopMargin($tab_top_newpage);
							// The only function to edit the bottom margin of current page to set it.
							$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
						}

						// back to start
						$pdf->setPage($pageposbeforenote);
						$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
						$pdf->SetFont('', '', $default_font_size - 1);
						$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
						$pageposafternote = $pdf->getPage();

						$posyafter = $pdf->GetY();

						if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + 20))) {	// There is no space left for total+free text
							$pdf->AddPage('', '', true);
							$pagenb++;
							$pageposafternote++;
							$pdf->setPage($pageposafternote);
							$pdf->setTopMargin($tab_top_newpage);
							// The only function to edit the bottom margin of current page to set it.
							$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
							//$posyafter = $tab_top_newpage;
						}


						// apply note frame to previous pages
						$i = $pageposbeforenote;
						while ($i < $pageposafternote) {
							$pdf->setPage($i);


							$pdf->SetDrawColor(128, 128, 128);
							// Draw note frame
							if ($i > $pageposbeforenote) {
								$height_note = $this->page_hauteur - ($tab_top_newpage + $heightforfooter);
								$pdf->Rect($this->marge_gauche, $tab_top_newpage - 1, $tab_width, $height_note + 1);
							} else {
								$height_note = $this->page_hauteur - ($tab_top + $heightforfooter);
								$pdf->Rect($this->marge_gauche, $tab_top - 1, $tab_width, $height_note + 1);
							}

							// Add footer
							$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
							$this->_pagefoot($pdf, $object, $outputlangs, 1);

							$i++;
						}

						// apply note frame to last page
						$pdf->setPage($pageposafternote);
						if (!empty($tplidx)) {
							$pdf->useTemplate($tplidx);
						}
						if (!getDolGlobalString('MAIN_PDF_DONOTREPEAT_HEAD')) {
							$this->_pagehead($pdf, $object, 0, $outputlangs);
						}
						$height_note = $posyafter - $tab_top_newpage;
						$pdf->Rect($this->marge_gauche, $tab_top_newpage - 1, $tab_width, $height_note + 1);
					} else // No pagebreak
					{
						$pdf->commitTransaction();
						$posyafter = $pdf->GetY();
						$height_note = $posyafter - $tab_top;
						$pdf->Rect($this->marge_gauche, $tab_top - 1, $tab_width, $height_note + 1);


						if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + 20))) {
							// not enough space, need to add page
							$pdf->AddPage('', '', true);
							$pagenb++;
							$pageposafternote++;
							$pdf->setPage($pageposafternote);
							if (!empty($tplidx)) {
								$pdf->useTemplate($tplidx);
							}
							if (!getDolGlobalString('MAIN_PDF_DONOTREPEAT_HEAD')) {
								$this->_pagehead($pdf, $object, 0, $outputlangs);
							}

							$posyafter = $tab_top_newpage;
						}
					}

					$tab_height = $tab_height - $height_note;
					$tab_top = $posyafter + 6;
				} else {
					$height_note = 0;
				}


				// Use new auto column system
				$this->prepareArrayColumnField($object, $outputlangs, $hidedetails, $hidedesc, $hideref);

				// Table simulation to know the height of the title line
				$pdf->startTransaction();
				$this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs, $hidetop);
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

					if ($this->getColumnStatus('photo')) {
						// We start with Photo of product line
						if (isset($imglinesize['width']) && isset($imglinesize['height']) && ($curY + $imglinesize['height']) > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforinfotot))) {	// If photo too high, we moved completely on new page
							$pdf->AddPage('', '', true);
							if (!empty($tplidx)) {
								$pdf->useTemplate($tplidx);
							}
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

					// Description of product line
					if ($this->getColumnStatus('desc')) {
						$pdf->startTransaction();

						$this->printColDescContent($pdf, $curY, 'desc', $object, $i, $outputlangs, $hideref, $hidedesc);
						$pageposafter = $pdf->getPage();

						if ($pageposafter > $pageposbefore) {	// There is a pagebreak
							$pdf->rollbackTransaction(true);
							$pageposafter = $pageposbefore;
							//print $pageposafter.'-'.$pageposbefore;exit;
							$pdf->setPageOrientation('', 1, $heightforfooter); // The only function to edit the bottom margin of current page to set it.

							$this->printColDescContent($pdf, $curY, 'desc', $object, $i, $outputlangs, $hideref, $hidedesc);
							$pageposafter = $pdf->getPage();
							$posyafter = $pdf->GetY();
							if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforinfotot))) {	// There is no space left for total+free text
								if ($i == ($nblines - 1)) {	// No more lines, and no space left to show total, so we create a new page
									$pdf->AddPage('', '', true);
									if (!empty($tplidx)) {
										$pdf->useTemplate($tplidx);
									}
									//if (!getDolGlobalString('MAIN_PDF_DONOTREPEAT_HEAD')) $this->_pagehead($pdf, $object, 0, $outputlangs);
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
						} else // No pagebreak
						{
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

					$pdf->SetFont('', '', $default_font_size - 1); // We reposition the default font

					// VAT Rate
					if ($this->getColumnStatus('vat')) {
						$vat_rate = pdf_getlinevatrate($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'vat', $vat_rate);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Unit price before discount
					if ($this->getColumnStatus('subprice')) {
						$up_excl_tax = pdf_getlineupexcltax($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'subprice', $up_excl_tax);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Quantity (SLY: format with thousand separator like other numeric columns)
					if ($this->getColumnStatus('qty')) {
						$qty = pdf_getlineqty($object, $i, $outputlangs, $hidedetails);
						if (is_numeric($qty)) {
							$qty = number_format((float) $qty, 0, '.', ',');
						}
						$this->printStdColumnContent($pdf, $curY, 'qty', $qty);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Unit
					if ($this->getColumnStatus('unit')) {
						$unit = pdf_getlineunit($object, $i, $outputlangs, $hidedetails, $hookmanager);
						$this->printStdColumnContent($pdf, $curY, 'unit', $unit);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Discount on line
					if ($this->getColumnStatus('discount') && $object->lines[$i]->remise_percent) {
						$remise_percent = pdf_getlineremisepercent($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'discount', $remise_percent);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Total excl tax line (HT)
					if ($this->getColumnStatus('totalexcltax')) {
						$total_excl_tax = pdf_getlinetotalexcltax($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'totalexcltax', $total_excl_tax);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Total with tax line (TTC)
					if ($this->getColumnStatus('totalincltax')) {
						$total_incl_tax = pdf_getlinetotalwithtax($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'totalincltax', $total_incl_tax);
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

					$parameters = array(
						'object' => $object,
						'i' => $i,
						'pdf' => &$pdf,
						'curY' => &$curY,
						'nexY' => &$nexY,
						'outputlangs' => $outputlangs,
						'hidedetails' => $hidedetails
					);
					$reshook = $hookmanager->executeHooks('printPDFline', $parameters, $this); // Note that $object may have been modified by hook


					// Collection of totals by value of vat in $this->tva["rate"] = total_tva
					if (isModEnabled('multicurrency') && $object->multicurrency_tx != 1) {
						$tvaligne = $object->lines[$i]->multicurrency_total_tva;
					} else {
						$tvaligne = $object->lines[$i]->total_tva;
					}

					$localtax1ligne = $object->lines[$i]->total_localtax1;
					$localtax2ligne = $object->lines[$i]->total_localtax2;
					$localtax1_rate = $object->lines[$i]->localtax1_tx;
					$localtax2_rate = $object->lines[$i]->localtax2_tx;
					$localtax1_type = $object->lines[$i]->localtax1_type;
					$localtax2_type = $object->lines[$i]->localtax2_type;

					if ($object->remise_percent) {
						$tvaligne -= ($tvaligne * $object->remise_percent) / 100;
					}
					if ($object->remise_percent) {
						$localtax1ligne -= ($localtax1ligne * $object->remise_percent) / 100;
					}
					if ($object->remise_percent) {
						$localtax2ligne -= ($localtax2ligne * $object->remise_percent) / 100;
					}

					$vatrate = (string) $object->lines[$i]->tva_tx;

					// Retrieve type from database for backward compatibility with old records
					if (
						(!isset($localtax1_type) || $localtax1_type == '' || !isset($localtax2_type) || $localtax2_type == '') // if tax type not defined
						&& (!empty($localtax1_rate) || !empty($localtax2_rate))
					) { // and there is local tax
						$localtaxtmp_array = getLocalTaxesFromRate($vatrate, 0, $object->thirdparty, $mysoc);
						$localtax1_type = isset($localtaxtmp_array[0]) ? $localtaxtmp_array[0] : '';
						$localtax2_type = isset($localtaxtmp_array[2]) ? $localtaxtmp_array[2] : '';
					}

					// retrieve global local tax
					if ($localtax1_type && $localtax1ligne != 0) {
						$this->localtax1[$localtax1_type][$localtax1_rate] += $localtax1ligne;
					}
					if ($localtax2_type && $localtax2ligne != 0) {
						$this->localtax2[$localtax2_type][$localtax2_rate] += $localtax2ligne;
					}

					if (($object->lines[$i]->info_bits & 0x01) == 0x01) {
						$vatrate .= '*';
					}
					if (!isset($this->tva[$vatrate])) {
						$this->tva[$vatrate] = 0;
					}
					$this->tva[$vatrate] += $tvaligne;

					// Add line
					if (getDolGlobalString('MAIN_PDF_DASH_BETWEEN_LINES') && $i < ($nblines - 1)) {
						$pdf->setPage($pageposafter);
						$pdf->SetLineStyle(array('dash' => '1,1', 'color' => array(80, 80, 80)));
						//$pdf->SetDrawColor(190,190,200);
						$pdf->line($this->marge_gauche, $nexY, $this->page_largeur - $this->marge_droite, $nexY);
						$pdf->SetLineStyle(array('dash' => 0));
					}


					// Detect if some page were added automatically and output _tableau for past pages
					while ($pagenb < $pageposafter) {
						$pdf->setPage($pagenb);
						if ($pagenb == $pageposbeforeprintlines) {
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, $hidetop, 1, $object->multicurrency_code);
						} else {
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code);
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
						if ($pagenb == $pageposafter) {
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, $hidetop, 1, $object->multicurrency_code);
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
						if (!getDolGlobalString('MAIN_PDF_DONOTREPEAT_HEAD')) {
							$this->_pagehead($pdf, $object, 0, $outputlangs);
						}
					}
				}

				// Show square
				if ($pagenb == $pageposbeforeprintlines) {
					$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, $hidetop, 0, $object->multicurrency_code);
				} else {
					$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0, $object->multicurrency_code);
				}
				$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;

				// Display infos area
				$posy = $this->drawInfoTable($pdf, $object, $bottomlasttab, $outputlangs);

				// Display total zone
				$posy = $this->drawTotalTable($pdf, $object, $deja_regle, $bottomlasttab, $outputlangs, $outputlangsbis);

				// Affiche zone versements
				/*
				if ($deja_regle)
				{
					$posy=$this->drawPaymentsTable($pdf, $object, $posy, $outputlangs);
				}
				*/

				// Pied de page
				$this->_pagefoot($pdf, $object, $outputlangs);

				$pdf->Image(DOL_DATA_ROOT . '/mycompany/logos/SLYStamp.png', 50, $this->page_hauteur - 45, 0, 20); // width=0 (auto) //SLY 2021.8.15

				// Append Terms & conditions to PDF only when user chose "Include sales terms" in builddoc form
				if (!empty($moreparams['add_terms'])) {
					$sly_add_terms_template = (isset($moreparams['add_terms_template']) && preg_match('/^([a-zA-Z0-9_\-]+\/)?[a-zA-Z0-9_\.\-]+\.pdf$/i', $moreparams['add_terms_template']))
						? $moreparams['add_terms_template'] : null;
					include DOL_DOCUMENT_ROOT . '/custom/slycustom/core/includes/sly_add_cgv.inc.php';
				}

				if (method_exists($pdf, 'AliasNbPages')) {
					$pdf->AliasNbPages();
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

				$mainUmask = getDolGlobalString('MAIN_UMASK');
				if (!empty($mainUmask)) {
					@chmod($file, octdec($mainUmask));
				}

				$this->result = array('fullpath' => $file);

				return 1; // No error
			} else {
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		} else {
			$this->error = $langs->transnoentities("ErrorConstantNotDefined", "COMMANDE_OUTPUTDIR");
			return 0;
		}
	}

	/**
	 *  Show payments table
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Commande	$object			Object order
	 *	@param	int			$posy			Position y in PDF
	 *	@param	Translate	$outputlangs	Object langs for output
	 *	@return int							<0 if KO, >0 if OK
	 */
	protected function drawPaymentsTable(&$pdf, $object, $posy, $outputlangs)
	{
	}

	/**
	 *   Show miscellaneous information (payment mode, payment term, ...)
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		Commande	$object			Object to show
	 *   @param		int			$posy			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @return	int							Pos y
	 */
	protected function drawInfoTable(&$pdf, $object, $posy, $outputlangs)
	{
		global $conf, $mysoc;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont('', '', $default_font_size - 1);

		// If France, show VAT mention if not applicable
		if ($this->emetteur->country_code == 'FR' && empty($mysoc->tva_assuj)) {
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("VATIsNotUsedForInvoice"), 0, 'L', 0);

			$posy = $pdf->GetY() + 4;
		}

		$posxval = 52;

		// Show payments conditions
		if ($object->cond_reglement_code || $object->cond_reglement) {
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("PaymentConditions") . ':';
			$pdf->MultiCell(43, 4, $titre, 0, 'L');

			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posxval, $posy);
			$lib_condition_paiement = $outputlangs->transnoentities("PaymentCondition" . $object->cond_reglement_code) != ('PaymentCondition' . $object->cond_reglement_code) ? $outputlangs->transnoentities("PaymentCondition" . $object->cond_reglement_code) : $outputlangs->convToOutputCharset($object->cond_reglement_doc ? $object->cond_reglement_doc : $object->cond_reglement_label);
			$lib_condition_paiement = str_replace('\n', "\n", $lib_condition_paiement);
			$pdf->MultiCell(67, 4, $lib_condition_paiement, 0, 'L');

			$posy = $pdf->GetY() + 3;
		}

		// Check a payment mode is defined
		/* Not used with orders
		if (empty($object->mode_reglement_code)
			&& ! getDolGlobalInt('FACTURE_CHQ_NUMBER')
			&& ! getDolGlobalInt('FACTURE_RIB_NUMBER'))
		{
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->SetTextColor(200,0,0);
			$pdf->SetFont('','B', $default_font_size - 2);
			$pdf->MultiCell(80, 3, $outputlangs->transnoentities("ErrorNoPaiementModeConfigured"),0,'L',0);
			$pdf->SetTextColor(0,0,0);

			$posy=$pdf->GetY()+1;
		}
		*/
		/* TODO
		else if (! empty($object->availability_code))
		{
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->SetTextColor(200,0,0);
			$pdf->SetFont('','B', $default_font_size - 2);
			$pdf->MultiCell(80, 3, $outputlangs->transnoentities("AvailabilityPeriod").': '.,0,'L',0);
			$pdf->SetTextColor(0,0,0);

			$posy=$pdf->GetY()+1;
		}*/

		// Show planed date of delivery
/* deactivated SLY 2021.1.6
		if (!empty($object->delivery_date)) {
			$outputlangs->load("sendings");
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("DateDeliveryPlanned").':';
			$pdf->MultiCell(80, 4, $titre, 0, 'L');
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posxval, $posy);
			$dlp = dol_print_date($object->delivery_date, "daytext", false, $outputlangs, true);
			$pdf->MultiCell(80, 4, $dlp, 0, 'L');

			$posy = $pdf->GetY() + 1;
		} elseif ($object->availability_code || $object->availability) {    // Show availability conditions
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("AvailabilityPeriod").':';
			$pdf->MultiCell(80, 4, $titre, 0, 'L');
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posxval, $posy);
			$lib_availability = $outputlangs->transnoentities("AvailabilityType".$object->availability_code) != ('AvailabilityType'.$object->availability_code) ? $outputlangs->transnoentities("AvailabilityType".$object->availability_code) : $outputlangs->convToOutputCharset(isset($object->availability) ? $object->availability : '');
			$lib_availability = str_replace('\n', "\n", $lib_availability);
			$pdf->MultiCell(80, 4, $lib_availability, 0, 'L');

			$posy = $pdf->GetY() + 1;
		}
*/

		// Show payment mode
		if (
			$object->mode_reglement_code
			&& $object->mode_reglement_code != 'CHQ'
			&& $object->mode_reglement_code != 'VIR'
		) {
			$pdf->SetFont('', 'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("PaymentMode") . ':';
			$pdf->MultiCell(80, 5, $titre, 0, 'L');

			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posxval, $posy);
			$lib_mode_reg = $outputlangs->transnoentities("PaymentType" . $object->mode_reglement_code) != ('PaymentType' . $object->mode_reglement_code) ? $outputlangs->transnoentities("PaymentType" . $object->mode_reglement_code) : $outputlangs->convToOutputCharset($object->mode_reglement);
			$pdf->MultiCell(80, 5, $lib_mode_reg, 0, 'L');

			$posy = $pdf->GetY() + 2;
		}

		// Show payment mode CHQ
		$factureChqNumber = getDolGlobalInt('FACTURE_CHQ_NUMBER');
		if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ') {
			// Si mode reglement non force ou si force a CHQ
			if ($factureChqNumber) {
				$diffsizetitle = getDolGlobalInt('PDF_DIFFSIZE_TITLE', 3);

				if ($factureChqNumber > 0) {
					$account = new Account($this->db);
					$account->fetch($factureChqNumber);

					$pdf->SetXY($this->marge_gauche, $posy);
					$pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
					$pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo', $account->proprio), 0, 'L', 0);
					$posy = $pdf->GetY() + 1;

					if (!getDolGlobalInt('MAIN_PDF_HIDE_CHQ_ADDRESS')) {
						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('', '', $default_font_size - $diffsizetitle);
						$pdf->MultiCell(100, 3, $outputlangs->convToOutputCharset($account->owner_address), 0, 'L', 0);
						$posy = $pdf->GetY() + 2;
					}
				}
				if ($factureChqNumber == -1) {
					$pdf->SetXY($this->marge_gauche, $posy);
					$pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
					$pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo', $this->emetteur->name), 0, 'L', 0);
					$posy = $pdf->GetY() + 1;

					if (!getDolGlobalInt('MAIN_PDF_HIDE_CHQ_ADDRESS')) {
						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('', '', $default_font_size - $diffsizetitle);
						$pdf->MultiCell(100, 3, $outputlangs->convToOutputCharset($this->emetteur->getFullAddress()), 0, 'L', 0);
						$posy = $pdf->GetY() + 2;
					}
				}
			}
		}

		// If payment mode not forced or forced to VIR, show payment with BAN
		$factureRibNumber = getDolGlobalInt('FACTURE_RIB_NUMBER');
		if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'VIR') {
			if ($object->fk_account > 0 || $object->fk_bank > 0 || $factureRibNumber) {
				$bankid = ($object->fk_account <= 0 ? $factureRibNumber : $object->fk_account);
				if ($object->fk_bank > 0) {
					$bankid = $object->fk_bank; // For backward compatibility when object->fk_account is forced with object->fk_bank
				}
				$account = new Account($this->db);
				$account->fetch($bankid);

				$curx = $this->marge_gauche;
				$cury = $posy;

				$posy = pdf_bank_sly($pdf, $outputlangs, $curx, $cury, $account, 0, $default_font_size);

				$posy += 2;
			}
		}

		return $posy;
	}


	/**
	 *	Show total to pay
	 *
	 *	@param	TCPDF		$pdf            Object PDF
	 *	@param  Commande	$object         Object to show
	 *	@param  int			$deja_regle     Montant deja regle
	 *	@param	int			$posy			Position depart
	 *	@param	Translate	$outputlangs	Objet langs
	 *	@param	Translate	$outputlangsbis	Objet langs bis (optional, for dual language)
	 *	@return int							Position pour suite
	 */
	protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis = null)
	{
		global $conf, $mysoc, $hookmanager;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		if ($outputlangsbis === null) {
			$pdfUseAlsoLangCode = getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE');
			if (!empty($pdfUseAlsoLangCode) && $outputlangs->defaultlang != $pdfUseAlsoLangCode) {
				$outputlangsbis = new Translate('', $conf);
				$outputlangsbis->setDefaultLang($pdfUseAlsoLangCode);
				$outputlangsbis->loadLangs(array("main", "dict", "companies", "bills", "products", "propal", "orders", "compta"));
				$default_font_size--;
			}
		}

		$tab2_top = $posy;
		$tab2_hl = 4;
		$pdf->SetFont('', '', $default_font_size - 1);

		// Total table
		$col1x = 150;		//change col1x from 120 to 150. SLY 12/11/2020
		$col2x = 170;
		if ($this->page_largeur < 210) { // To work with US executive format
			$col2x -= 20;
		}
		$largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

		$useborder = 0;
		$index = 0;

		// Total discount on lines (22.x sync: show TotalHTBeforeDiscount / TotalDiscount when line discounts exist)
		$total_discount_on_lines = 0;
		$multicurrency_total_discount_on_lines = 0;
		if (!empty($object->lines) && is_array($object->lines)) {
			foreach ($object->lines as $i => $line) {
				$resdiscount = pdfGetLineTotalDiscountAmount($object, $i, $outputlangs, 2);
				$multicurrency_resdiscount = pdfGetLineTotalDiscountAmount($object, $i, $outputlangs, 2, 1);
				$total_discount_on_lines += (is_numeric($resdiscount) ? $resdiscount : 0);
				$multicurrency_total_discount_on_lines += (is_numeric($multicurrency_resdiscount) ? $multicurrency_resdiscount : 0);
				if (!empty($line->total_ht) && $line->total_ht < 0) {
					$total_discount_on_lines += -$line->total_ht;
					$multicurrency_total_discount_on_lines += -(isset($line->multicurrency_total_ht) ? $line->multicurrency_total_ht : 0);
				}
			}
		}
		if ($total_discount_on_lines > 0) {
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetXY($col1x, $tab2_top);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalHTBeforeDiscount") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("TotalHTBeforeDiscount") : ''), 0, 'L', true);
			$pdf->SetXY($col2x, $tab2_top);
			$total_before_discount_to_show = ((isModEnabled('multicurrency') && isset($object->multicurrency_tx) && (float) $object->multicurrency_tx != 1) ? ($object->multicurrency_total_ht + $multicurrency_total_discount_on_lines) : ($object->total_ht + $total_discount_on_lines));
			$pdf->MultiCell($largcol2, $tab2_hl, price($total_before_discount_to_show, 0, $outputlangs, 1, -1, -1, isset($object->multicurrency_code) ? $object->multicurrency_code : ''), 0, 'R', true);
			$index++;
			$pdf->SetFillColor(255, 255, 255);
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalDiscount") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("TotalDiscount") : ''), 0, 'L', true);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$total_discount_to_show = ((isModEnabled('multicurrency') && isset($object->multicurrency_tx) && (float) $object->multicurrency_tx != 1) ? $multicurrency_total_discount_on_lines : $total_discount_on_lines);
			$pdf->MultiCell($largcol2, $tab2_hl, price($total_discount_to_show, 0, $outputlangs, 1, -1, -1, isset($object->multicurrency_code) ? $object->multicurrency_code : ''), 0, 'R', true);
			$index++;
		}

		// Total HT
		$pdf->SetFillColor(255, 255, 255);
		$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
		$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalHT") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("TotalHT") : ''), 0, 'L', 1);
		$total_ht = ((isModEnabled('multicurrency') && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ht : $object->total_ht);
		$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
		$pdf->MultiCell($largcol2, $tab2_hl, price($total_ht + (!empty($object->remise) ? $object->remise : 0), 0, $outputlangs, 1, -1, -1, isset($object->multicurrency_code) ? $object->multicurrency_code : ''), 0, 'R', 1);

		// Show VAT by rates and total
		$pdf->SetFillColor(248, 248, 248);

		$total_ttc = (isModEnabled('multicurrency') && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ttc : $object->total_ttc;

		$this->atleastoneratenotnull = 0;
		// Singapore non-GST-registered: do not show any VAT/GST (company setting tva_assuj = no)
		$show_vat_block = !getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT')
			&& !(!empty($mysoc->country_code) && $mysoc->country_code == 'SG' && empty($mysoc->tva_assuj));
		if ($show_vat_block) {
			$tvaisnull = ((!empty($this->tva) && count($this->tva) == 1 && isset($this->tva['0.000']) && is_float($this->tva['0.000'])) ? true : false);
			if (getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_IFNULL') && $tvaisnull) {
				// Nothing to do
			} else {
				//Local tax 1 before VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
				//{
				foreach ($this->localtax1 as $localtax_type => $localtax_rate) {
					if (in_array((string) $localtax_type, array('1', '3', '5'))) {
						continue;
					}
					foreach ($localtax_rate as $tvakey => $tvaval) {
						if ($tvakey != 0) {    // On affiche pas taux 0
							//$this->atleastoneratenotnull++;
							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl = '';
							if (preg_match('/\*/', $tvakey)) {
								$tvakey = str_replace('*', '', $tvakey);
								$tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
							}
							$totalvat = $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalLT1", $mysoc->country_code) : '');
							$totalvat .= ' ';
							$totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;
							$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs, 1, -1, -1, $object->multicurrency_code), 0, 'R', 1);
						}
					}
				}
				//}
				//Local tax 2 before VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
				//{
				foreach ($this->localtax2 as $localtax_type => $localtax_rate) {
					if (in_array((string) $localtax_type, array('1', '3', '5'))) {
						continue;
					}
					foreach ($localtax_rate as $tvakey => $tvaval) {
						if ($tvakey != 0) {    // On affiche pas taux 0
							//$this->atleastoneratenotnull++;

							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl = '';
							if (preg_match('/\*/', $tvakey)) {
								$tvakey = str_replace('*', '', $tvakey);
								$tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
							}
							$totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalLT2", $mysoc->country_code) : '');
							$totalvat .= ' ';
							$totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;
							$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs, 1, -1, -1, $object->multicurrency_code), 0, 'R', 1);
						}
					}
				}
				//}
				// VAT
				foreach ($this->tva as $tvakey => $tvaval) {
					if ($tvakey != 0) {    // On affiche pas taux 0
						$this->atleastoneratenotnull++;

						$index++;
						$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

						$tvacompl = '';
						if (preg_match('/\*/', $tvakey)) {
							$tvakey = str_replace('*', '', $tvakey);
							$tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
						}
						$totalvat = $outputlangs->transcountrynoentities("TotalVAT", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalVAT", $mysoc->country_code) : '');
						$totalvat .= ' ';
						$totalvat .= vatrate($tvakey, 1) . $tvacompl;
						$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

						$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
						$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs, 1, -1, -1, $object->multicurrency_code), 0, 'R', 1);
					}
				}

				//Local tax 1 after VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
				//{
				foreach ($this->localtax1 as $localtax_type => $localtax_rate) {
					if (in_array((string) $localtax_type, array('2', '4', '6'))) {
						continue;
					}

					foreach ($localtax_rate as $tvakey => $tvaval) {
						if ($tvakey != 0) {    // On affiche pas taux 0
							//$this->atleastoneratenotnull++;

							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl = '';
							if (preg_match('/\*/', $tvakey)) {
								$tvakey = str_replace('*', '', $tvakey);
								$tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
							}
							$totalvat = $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalLT1", $mysoc->country_code) : '');
							$totalvat .= ' ';
							$totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;

							$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);
							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs, 1, -1, -1, $object->multicurrency_code), 0, 'R', 1);
						}
					}
				}
				//}
				//Local tax 2 after VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
				//{
				foreach ($this->localtax2 as $localtax_type => $localtax_rate) {
					if (in_array((string) $localtax_type, array('2', '4', '6'))) {
						continue;
					}

					foreach ($localtax_rate as $tvakey => $tvaval) {
						// retrieve global local tax
						if ($tvakey != 0) {    // On affiche pas taux 0
							//$this->atleastoneratenotnull++;

							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl = '';
							if (preg_match('/\*/', $tvakey)) {
								$tvakey = str_replace('*', '', $tvakey);
								$tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
							}
							$totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalLT2", $mysoc->country_code) : '');
							$totalvat .= ' ';

							$totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;
							$pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs, 1, -1, -1, $object->multicurrency_code), 0, 'R', 1);
						}
					}
				}
				//}

				// Total TTC
				$index++;
				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->SetFillColor(224, 224, 224);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalTTC") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalTTC", $mysoc->country_code) : ''), $useborder, 'L', 1);

				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($total_ttc, 0, $outputlangs, 1, -1, -1, $object->multicurrency_code), $useborder, 'R', 1);
			}
		}

		$pdf->SetTextColor(0, 0, 0);

		// Proforma: show Prepayment amount only (highlighted, same style as Total TTC)
		if ($this->name == 'sly_proforma') {
			$deposit_percent = null;
			if (isset($object->deposit_percent) && $object->deposit_percent !== '' && $object->deposit_percent !== null) {
				$deposit_percent = (float) $object->deposit_percent;
			}
			if (($deposit_percent === null || $deposit_percent == 0) && !empty($object->cond_reglement_id)) {
				$deposit_percent = (float) getDictionaryValue('c_payment_term', 'deposit_percent', $object->cond_reglement_id);
			}
			if (!empty($deposit_percent) && $deposit_percent > 0) {
				$outputlangs->loadLangs(array('slycustom'));
				$prepayment_amount = price2num($total_ttc * ($deposit_percent / 100), 'MT');
				$index++;
				$pdf->SetTextColor(0, 0, 60);
				$pdf->SetFillColor(224, 224, 224);
				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("Prepayment") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("Prepayment") : ''), $useborder, 'L', 1);
				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($prepayment_amount, 0, $outputlangs, 1, -1, -1, isset($object->multicurrency_code) ? $object->multicurrency_code : ''), $useborder, 'R', 1);
				$pdf->SetTextColor(0, 0, 0);
			}
		}

		$creditnoteamount = 0;
		$depositsamount = 0;
		//$creditnoteamount=$object->getSumCreditNotesUsed();
		//$depositsamount=$object->getSumDepositsUsed();
		//print "x".$creditnoteamount."-".$depositsamount;exit;
		$resteapayer = price2num($total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 'MT');
		if (!empty($object->paye)) {
			$resteapayer = 0;
		}

		if ($deja_regle > 0) {
			// Already paid + Deposits
			$index++;

			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("AlreadyPaid") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("AlreadyPaid") : ''), 0, 'L', 0);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($deja_regle, 0, $outputlangs), 0, 'R', 0);

			$index++;
			$pdf->SetTextColor(0, 0, 60);
			$pdf->SetFillColor(224, 224, 224);
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("RemainderToPay") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("AlreadyPaid") : ''), $useborder, 'L', 1);

			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($resteapayer, 0, $outputlangs, 1, -1, -1, $object->multicurrency_code), $useborder, 'R', 1);

			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetTextColor(0, 0, 0);
		}

		$index++;
		return ($tab2_top + ($tab2_hl * $index));
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   Show table for lines
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		string		$tab_top		Top position of table
	 *   @param		string		$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y (not used)
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @param		string		$currency		Currency code
	 *   @param		Translate	$outputlangsbis	Langs object bis
	 *   @return	void
	 */
	protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $currency = '', $outputlangsbis = null)
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
			// SLY: "Currency: US Dollars" — simple and clear; currency name bold+larger
			$currencyLabel = $outputlangs->transnoentitiesnoconv("Currency" . $currency);
			$prefix = $outputlangs->transnoentities("Currency") . ": ";
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

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page.
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Commande	$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @param  Translate	$outputlangsbis	Object lang for output bis
	 *  @param	string		$titlekey		Translation key to show as title of document
	 *  @return	array|int                   Return array('top_shift'=>int,'shipp_shift'=>int) or legacy int topshift
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null, $titlekey = "PdfOrderTitle")
	{
		// phpcs:enable
		global $conf, $langs, $hookmanager;

		$ltrdirection = 'L';
		if ($outputlangs->trans("DIRECTION") == 'rtl')
			$ltrdirection = 'R';

		// Load traductions files required by page
		$outputlangs->loadLangs(array("main", "bills", "propal", "orders", "companies"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Use system default language for company info labels
		$headerlangs = new Translate('', $conf);
		$headerlangs->setDefaultLang($conf->global->MAIN_LANG_DEFAULT ?? 'en_US');
		$headerlangs->loadLangs(array("main", "bills", "propal", "orders", "companies"));

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		// Show Draft Watermark
		$draftWatermark = getDolGlobalString('COMMANDE_DRAFT_WATERMARK');
		if ($object->statut == $object::STATUS_DRAFT && !empty($draftWatermark)) {
			pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', $draftWatermark);
		}

		// SLY: Letterhead = logo (left) + company info (right), balanced on one band
		$letterhead_height = 0;
		$posy = $this->marge_haute;
		$logo_width_mm = 38; // reserved width for logo so company info has room
		$logo_max_height_mm = 20; // cap logo height for balanced letterhead
		$logo_company_gap_mm = 2; // gap between logo and company text for balance
		$company_info_x = $this->marge_gauche + $logo_width_mm + $logo_company_gap_mm;
		$company_info_width = $this->page_largeur - $company_info_x - $this->marge_droite - 5; // use full line to right margin

		// Logo (left part of letterhead)
		$logo_height_used = 0;
		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO') && is_object($this->emetteur)) {
			if ($this->emetteur->logo) {
				$logodir = $conf->mycompany->dir_output;
				if (!empty($conf->mycompany->multidir_output[$object->entity])) {
					$logodir = $conf->mycompany->multidir_output[$object->entity];
				}
				if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO')) {
					$logo = $logodir . '/logos/thumbs/' . $this->emetteur->logo_small;
				} else {
					$logo = $logodir . '/logos/' . $this->emetteur->logo;
				}
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

		// Company info (right of logo, same band) — format: name bold+larger, address/contact normal
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
						$lh1 .= " (" . $uenLabel . ": " . $outputlangs->convToOutputCharset($this->emetteur->idprof1) . ")";
					}
					$pdf->SetFont('', 'B', $default_font_size);
					$pdf->MultiCell($company_info_width, 5, $outputlangs->convToOutputCharset($lh1), 0, 'L');
				}
				$addrParts = array();
				if ($this->emetteur->address) {
					$addrParts[] = str_replace("\n", ", ", $this->emetteur->address);
				}
				if ($this->emetteur->town || $this->emetteur->zip) {
					$addrParts[] = trim(($this->emetteur->town ? $this->emetteur->town : '') . ($this->emetteur->town && $this->emetteur->zip ? ' ' : '') . ($this->emetteur->zip ? $this->emetteur->zip : ''));
				}
				if (count($addrParts) > 0) {
					$pdf->SetX($company_info_x);
					$pdf->SetFont('', '', $default_font_size - 2);
					$pdf->MultiCell($company_info_width, 4, strtoupper(implode(", ", $addrParts)), 0, 'L');
				}
				$contactParts = array();
				if ($this->emetteur->phone) {
					$contactParts[] = $headerlangs->transnoentities("Phone") . ": " . $this->emetteur->phone;
				}
				if ($this->emetteur->email) {
					$contactParts[] = $headerlangs->transnoentities("Email") . ": " . $outputlangs->convToOutputCharset($this->emetteur->email);
				}
				if (count($contactParts) > 0) {
					$pdf->SetX($company_info_x);
					$pdf->SetFont('', '', $default_font_size - 2);
					$pdf->MultiCell($company_info_width, 4, implode(" - ", $contactParts), 0, 'L');
				}
			} else {
				$lh1 = $headerlangs->transnoentities("RegisteredOffice") . ": " . $this->emetteur->name;
				if ($this->emetteur->address) {
					$lh1 .= " - " . str_replace("\n", ", ", $this->emetteur->address);
				}
				if ($this->emetteur->zip) {
					$lh1 .= " - " . $this->emetteur->zip;
				}
				if ($this->emetteur->town) {
					$lh1 .= " " . $this->emetteur->town;
				}
				if ($this->emetteur->country) {
					$lh1 .= ", " . $this->emetteur->country;
				}
				$pdf->SetFont('', 'B', $default_font_size - 1);
				$pdf->MultiCell($company_info_width, 4, $outputlangs->convToOutputCharset($lh1), 0, 'L');
				$lh2 = '';
				if ($this->emetteur->phone) {
					$lh2 .= $headerlangs->transnoentities("Phone") . ": " . $this->emetteur->phone;
				}
				if ($this->emetteur->email) {
					$lh2 .= ($lh2 ? " - " : "") . $headerlangs->transnoentities("Email") . ": " . $outputlangs->convToOutputCharset($this->emetteur->email);
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
		// SLY: No title/ref/date in header — only logo + company. Document type/ref/date shown once in Document box below.
		$top_shift = 0;
		$shipp_shift = 0;

		if ($showaddress) {
			// Show document info (left box, former sender position) — below letterhead
			$posy = (isset($letterhead_height) ? $letterhead_height : 0) + (getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 28 : 30);
			$posy += $top_shift;
			$posx = $this->marge_gauche;
			if (getDolGlobalInt('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->page_largeur - $this->marge_droite - 80;
			}

			$hautcadre = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 38 : 40;
			$widthrecbox = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 82;

			// Show document info frame (no "Document" label)
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($posx, $posy);
			$pdf->SetFillColor(230, 230, 230);
			$pdf->MultiCell($widthrecbox, $hautcadre, "", 0, 'R', 1);
			$pdf->SetTextColor(0, 0, 60);

			// Show document information with original format: title bold+larger, Ref bold, date/ref_client normal; right-aligned like original header
			$docx = $posx + 2;
			$docy = $posy + 3;
			$pdf->SetXY($docx, $docy);
			$pdf->SetFont('', 'B', $default_font_size + 2);
			$doctitle = $outputlangs->transnoentities($titlekey);
			$pdf->MultiCell($widthrecbox - 2, 5, (function_exists('mb_strtoupper') ? mb_strtoupper($doctitle, 'UTF-8') : strtoupper($doctitle)), 0, 'R');
			$docy = $pdf->GetY();
			$pdf->SetXY($docx, $docy);
			$pdf->SetFont('', 'B', $default_font_size);
			$textref = $outputlangs->transnoentities("Ref") . " : " . $outputlangs->convToOutputCharset($object->ref);
			if ($object->statut == $object::STATUS_DRAFT) {
				$pdf->SetTextColor(128, 0, 0);
				$textref .= ' - ' . $outputlangs->transnoentities("NotValidated");
			}
			$pdf->MultiCell($widthrecbox - 2, 4, $textref, 0, 'R');
			$pdf->SetTextColor(0, 0, 60);
			$docy = $pdf->GetY();
			$pdf->SetXY($docx, $docy);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->transnoentities("OrderDate") . " : " . dol_print_date($object->date, "day", false, $outputlangs, true), 0, 'R');
			if ($object->ref_client) {
				$docy = $pdf->GetY();
				$pdf->SetXY($docx, $docy);
				$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->transnoentities("RefCustomer") . " : " . $outputlangs->convToOutputCharset($object->ref_client), 0, 'R');
			}
			$y_after_docbox = $pdf->GetY();

			// If CUSTOMER contact defined, we use it
			$usecontact = false;
			$arrayidcontact = $object->getIdContact('external', 'CUSTOMER');
			if (count($arrayidcontact) > 0) {
				$usecontact = true;
				$result = $object->fetch_contact($arrayidcontact[0]);
			}

			//Recipient name
			if ($usecontact && ($object->contact->socid != $object->thirdparty->id && getDolGlobalInt('MAIN_USE_COMPANY_NAME_OF_CONTACT', 1))) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			$carac_client_name = pdfBuildThirdpartyName($thirdparty, $outputlangs);

			$mode = 'target';
			$carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, ($usecontact ? $object->contact : ''), $usecontact, $mode, $object);

			// Show recipient
			$widthrecbox = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 100;
			if ($this->page_largeur < 210) {
				$widthrecbox = 84; // To work with US executive format
			}
			$posy = (isset($letterhead_height) ? $letterhead_height : 0) + (getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 28 : 30);
			$posy += $top_shift;
			$posx = $this->page_largeur - $this->marge_droite - $widthrecbox;
			if (getDolGlobalInt('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->marge_gauche;
			}

			// Show recipient frame
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posx + 2, $posy - 5);
			$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillTo"), 0, $ltrdirection);
			$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

			// Show recipient name
			$pdf->SetXY($posx + 2, $posy + 3);
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->MultiCell($widthrecbox, 2, $carac_client_name, 0, $ltrdirection);

			$posy = $pdf->getY();

			// Show recipient information
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetXY($posx + 2, $posy);
			$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, $ltrdirection);
			// SLY: supplementary block follows dynamically after Document and To boxes (use lower edge of both)
			$y_after_tobox = max($pdf->GetY(), $posy + $hautcadre);
			$this->pagehead_bottom_y = max($y_after_docbox, $y_after_tobox);
		}

		$pdf->SetTextColor(0, 0, 0);
		return array('top_shift' => $top_shift, 'shipp_shift' => $shipp_shift);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   	Show footer of page. Need this->emetteur object
	 *
	 *   	@param	TCPDF		$pdf     			PDF
	 * 		@param	Commande	$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	int								Return height of bottom margin including footer text
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		// phpcs:enable
		global $conf;
		// SLY: Use letterhead (company at top), so do not show company details in footer; no footer line
		$showdetails = 0;
		return pdf_pagefoot($pdf, $outputlangs, 'ORDER_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, '', 1);
	}



	/**
	 *   	Define Array Column Field
	 *
	 *   	@param	Commande		$object    		common object
	 *   	@param	Translate		$outputlangs    langs
	 *      @param	int				$hidedetails	Do not show line details
	 *      @param	int				$hidedesc		Do not show desc
	 *      @param	int				$hideref		Do not show ref
	 *      @return	null
	 */
	public function defineColumnField($object, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $hookmanager;

		// Default field style for content
		$this->defaultContentsFieldsStyle = array(
			'align' => 'C', // R,C,L	//Adjusted from 'R' to 'C' by SLY 12/11/2020
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
				'align' => 'C',		//Adjusted from 'L' to 'C' by SLY 12/11/2020
				// 'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
				// 'label' => ' ', // the final label
				'padding' => array(0.5, 0.5, 0.5, 0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
			'content' => array(
				'align' => 'L',
				'padding' => array(1, 0.5, 1, 1.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
		);

		// Image of product
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

		if (getDolGlobalInt('MAIN_GENERATE_ORDERS_WITH_PICTURE') && !empty($this->atleastonephoto)) {
			$this->cols['photo']['status'] = true;
		}

		$rank = $rank + 10;
		// Singapore: no VAT, only GST. GST-registered (tva_assuj) → show "GST"; non-GST-registered → hide tax column. Other countries → "VAT".
		$is_sg = !empty($this->emetteur->country_code) && $this->emetteur->country_code == 'SG';
		$sg_use_tax = $is_sg && !empty($this->emetteur->tva_assuj); // Singapore: show tax only if GST registered
		$vattitlekey = ($is_sg && $this->emetteur->tva_assuj) ? 'GST' : 'VAT';
		$this->cols['vat'] = array(
			'rank' => $rank,
			'status' => false,
			'width' => 16, // in mm
			'title' => array(
				'textkey' => $vattitlekey
			),
			'border-left' => true, // add left line separator
		);

		if (!getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT') && !getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN') && ($sg_use_tax || !$is_sg)) {
			$this->cols['vat']['status'] = true;
		}

		$rank = $rank + 10;
		$this->cols['subprice'] = array(
			'rank' => $rank,
			'width' => 20, // in mm.  Adjusted from 19 to 20 by SLY 2020.12.22
			'status' => true,
			'title' => array(
				'textkey' => 'PriceUHT'
			),
			'border-left' => true, // add left line separator
		);

		// Adapt dynamically the width of subprice, if text is too long.
		$tmpwidth = 0;
		$nblines = count($object->lines);
		for ($i = 0; $i < $nblines; $i++) {
			$tmpwidth2 = dol_strlen(dol_string_nohtmltag(pdf_getlineupexcltax($object, $i, $outputlangs, $hidedetails)));
			$tmpwidth = max($tmpwidth, $tmpwidth2);
		}
		if ($tmpwidth > 10) {
			$this->cols['subprice']['width'] += (2 * ($tmpwidth - 10));
		}

		$rank = $rank + 10;
		$this->cols['qty'] = array(
			'rank' => $rank,
			'width' => 16, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'Qty'
			),
			'border-left' => true, // add left line separator
		);

		$rank = $rank + 10;
		$this->cols['unit'] = array(
			'rank' => $rank,
			'width' => 16, // in mm.  Adjusted from 11 to 16 by SLY 12/11/2020
			'status' => false,
			'title' => array(
				'textkey' => 'Unit'
			),
			'border-left' => true, // add left line separator
		);
		if (getDolGlobalInt('PRODUCT_USE_UNITS')) {
			$this->cols['unit']['status'] = true;
		}

		$rank = $rank + 10;
		$this->cols['discount'] = array(
			'rank' => $rank,
			'width' => 16, // in mm. Adjusted from 13 to 16 by SLY 12/11/2020
			'status' => false,
			'title' => array(
				'textkey' => 'ReductionShort'
			),
			'border-left' => true, // add left line separator
		);
		if ($this->atleastonediscount) {
			$this->cols['discount']['status'] = true;
		}

		$rank = $rank + 1000; // add a big offset to be sure is the last col because default extrafield rank is 100
		$this->cols['totalexcltax'] = array(
			'rank' => $rank,
			'width' => 20, // in mm. Adjusted from 26 to 20 by SLY 2020.12.22
			'status' => !getDolGlobalInt('PDF_PROPAL_HIDE_PRICE_EXCL_TAX'),
			'title' => array(
				'textkey' => 'TotalHT'
			),
			'border-left' => true, // add left line separator
		);

		$rank = $rank + 1010; // add a big offset to be sure is the last col because default extrafield rank is 100
		$this->cols['totalincltax'] = array(
			'rank' => $rank,
			'width' => 26, // in mm
			'status' => getDolGlobalInt('PDF_PROPAL_SHOW_PRICE_INCL_TAX'),
			'title' => array(
				'textkey' => 'TotalTTC'
			),
			'border-left' => true, // add left line separator
		);

		// Add extrafields cols
		if (!empty($object->lines)) {
			$line = reset($object->lines);
			$this->defineColumnExtrafield($line, $outputlangs, $hidedetails);
		}

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