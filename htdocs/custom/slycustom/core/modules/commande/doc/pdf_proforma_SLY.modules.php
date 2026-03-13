<?php
/* Copyright (C) 2004-2014	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin		<regis.houssin@inodbox.com>
 * Copyright (C) 2008		Raphael Bertrand	<raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2013	Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2012      	Christophe Battarel <christophe.battarel@altairis.fr>
 * Copyright (C) 2012       Cedric Salvador     <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015       Marcos García       <marcosgdf@gmail.com>
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
 *	\file       htdocs/custom/slycustom/core/modules/commande/doc/pdf_proforma_SLY.modules.php
 *	\ingroup    commande
 *	\brief      File of Class to generate PDF orders with template Proforma
 */

require_once __DIR__.'/pdf_eratosthene_SLY.modules.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';


/**
 *	Class to generate PDF orders with template Proforma
 */
class pdf_proforma_SLY extends pdf_eratosthene_SLY
{

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		parent::__construct($db);

		$this->name = "proforma_SLY";
		$this->description = $langs->trans('PDFProformaDescription');
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
	 *  @return	int                         Return topshift value
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null, $titlekey = "InvoiceProForma")
	{
		// phpcs:enable
		global $conf, $langs, $hookmanager;

		return parent::_pagehead($pdf, $object, $showaddress, $outputlangs, $outputlangsbis, $titlekey);
	}

	/**
	 * Draws the deposit and total table on the PDF document.
	 *
	 * @param TCPDF $pdf          PDF object used to generate the document.
	 * @param Commande $object    Order object containing details like total amount and deposit percentage.
	 * @param float $deja_regle   Amount already paid for the order.
	 * @param float $posy         Current vertical position on the PDF document.
	 * @param Translate $outputlangs Language object for translations.
	 * @return float              Updated vertical position after drawing the table.
	 */
	// Ensure method signature matches parent for inheritance
	protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs)
	{
	
		global $conf, $mysoc, $hookmanager;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$outputlangsbis = null;

		// Call parent drawTotalTable to keep original behavior
		$posy = parent::drawTotalTable($pdf, $object, $deja_regle, $posy, $outputlangs);

/*		if (
			(property_exists($object, 'cond_reglement_deposit_percent') || isset($object->cond_reglement_deposit_percent))
			&& !empty($object->cond_reglement_deposit_percent)
			&& $object->cond_reglement_deposit_percent > 0
		) {
*/			


			$deposit_percent = property_exists($object, 'cond_reglement_deposit_percent') ? $object->cond_reglement_deposit_percent : 0;

			$deposit = price2num($deposit_percent * ((!empty($conf->multicurrency->enabled) && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ttc : $object->total_ttc / 100), 'MT');

			$tab2_top = $posy;
			$tab2_hl = 4;
			$pdf->SetFont('', '', $default_font_size - 1);

			$col1x = 150;		//change col1x from 120 to 150. SLY 12/11/2020
			$col2x = 170;
			if ($this->page_largeur < 210) { // To work with US executive format
				$col2x -= 20;
			}
			$largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

			$useborder = 0;
			$index = 0;

			// Draw label cell
			$index++;
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->SetFillColor(224, 224, 224);
			$pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("Prepayment").(is_object($outputlangsbis) ? ' / '.$outputlangsbis->transcountrynoentities("Prepayment", $mysoc->country_code) : ''), $useborder, 'L', 1);

			// Draw value cell
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($deposit, 0, $outputlangs, 1, -1, -1, $object->multicurrency_code), $useborder, 'R', 1);

			$posy += $tab2_top + ($tab2_hl * $index);
//		}

		return $posy;
	}

}
