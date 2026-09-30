<?php

require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';

if (!class_exists('TCPDF')) {
    die(sprintf("Class 'TCPDF' not found in %s", DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php'));
}

// Extend the TCPDF class to create custom Header and Footer
class NCPDF extends TCPDF {

    //Page header
    public function Header() {
        // Logo
        $image_file = DOL_DOCUMENT_ROOT .'/documents/pdf_header.jpg';

        if (!file_exists($image_file)) {
            $image_file = '';
        }

$html = <<<EOD
<img src="$image_file" alt="" />
EOD;
		$this->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
    }

	// Page footer
    public function Footer() {
        $this->SetY(-19);
        // Footer
        $image_ftr = DOL_DOCUMENT_ROOT .'/documents/pdf_footer.jpg';

        if (!file_exists($image_ftr)) {
            $image_ftr = '';
        }

$html = <<<EOD
<img src="$image_ftr" alt="" />
EOD;
		$this->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
    }
}

$pdf = new NCPDF('P', 'mm', 'A4', true, 'UTF-8', false, false);
// set document information
$pdf->SetCreator('NextGestion');
$pdf->SetAuthor('Yassine Belkaid');
$pdf->SetTitle((isset($title) ? $title : ''));
$pdf->SetSubject((isset($title) ? $title : ''));
$pdf->SetKeywords('');
// set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE.' 001', PDF_HEADER_STRING, array(0,64,255), array(0,64,128));
$pdf->setFooterData(array(0,64,0), array(0,64,128));

// set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(0); // PDF_MARGIN_HEADER
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
    require_once(dirname(__FILE__).'/lang/eng.php');
    $pdf->setLanguageArray($l);
}

// set default font subsetting mode
$pdf->setFontSubsetting(true);
