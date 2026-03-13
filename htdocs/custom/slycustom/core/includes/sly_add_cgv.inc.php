<?php
/* Copyright (C) 2020 SAGOT Philippe (Philazerty) - Rubis
 * Copyright (C) 2025 SLY Custom - migrated to SLY module, 22.0 getDolGlobal
 *
 * Append Terms & conditions PDF to the current document.
 * Expects in scope: $pdf (TCPDF), $object (Commande|Facture|...), $outputlangs, $this (PDF template with _pagefoot).
 * Terms path: DOL_DATA_ROOT/mycompany/terms/[lang]/<filename> or .../terms/<filename> (single lang).
 * Default filename per language from SLYCUSTOM_TERMS_DEFAULT_BY_LANG (JSON, e.g. {"":"terms.pdf","zh_CN":"terms_contract.pdf"}).
 * Legacy fallback only: .../cgv/[lang]/cgv.pdf (old Rubis path); primary path is always terms/.
 */

if (!isset($pdf) || !isset($object) || !isset($outputlangs) || !isset($this)) {
	return;
}
if (!isset($sly_add_terms_template)) {
	$sly_add_terms_template = null;
}

$terms_default_by_lang = array();
$json_default = getDolGlobalString('SLYCUSTOM_TERMS_DEFAULT_BY_LANG');
if ($json_default !== '' && $json_default !== null) {
	$decoded = json_decode($json_default, true);
	if (is_array($decoded)) {
		$terms_default_by_lang = $decoded;
	}
}

$lang_code = (getDolGlobalInt('MAIN_MULTILANGS') && !empty($outputlangs->defaultlang)) ? $outputlangs->defaultlang : '';
$default_filename = 'terms.pdf';
// Global default filename (key '') if defined
if (isset($terms_default_by_lang['']) && preg_match('/^[a-zA-Z0-9_\.\-]+\.pdf$/i', $terms_default_by_lang[''])) {
	$default_filename = $terms_default_by_lang[''];
}
// User-selected template: relative path (e.g. zh_CN/terms.pdf) or filename only
$terms_base = DOL_DATA_ROOT.'/mycompany/terms';
$candidates = array();
if (!empty($sly_add_terms_template) && strpos($sly_add_terms_template, '..') === false) {
	if (strpos($sly_add_terms_template, '/') !== false && preg_match('/^[a-zA-Z0-9_\-]+\/[a-zA-Z0-9_\.\-]+\.pdf$/i', $sly_add_terms_template)) {
		// Explicit relative path from builddoc form (language-independent)
		$candidates[] = $terms_base.'/'.$sly_add_terms_template;
	} elseif (preg_match('/^[a-zA-Z0-9_\.\-]+\.pdf$/i', $sly_add_terms_template)) {
		$default_filename = $sly_add_terms_template;
	}
}
if (empty($candidates)) {
	if (!isset($default_filename)) {
		$default_filename = 'terms.pdf';
		if (isset($terms_default_by_lang['']) && preg_match('/^[a-zA-Z0-9_\.\-]+\.pdf$/i', $terms_default_by_lang[''])) {
			$default_filename = $terms_default_by_lang[''];
		} elseif ($lang_code !== '' && isset($terms_default_by_lang[$lang_code]) && preg_match('/^[a-zA-Z0-9_\.\-]+\.pdf$/i', $terms_default_by_lang[$lang_code])) {
			$default_filename = $terms_default_by_lang[$lang_code];
		}
	}
	// Candidate paths: terms/ only first; legacy cgv/ only if terms not found
	if (getDolGlobalInt('MAIN_MULTILANGS') && $lang_code !== '') {
		$candidates[] = $terms_base.'/'.$lang_code.'/'.$default_filename;
		$candidates[] = $terms_base.'/'.$default_filename;
		$candidates[] = DOL_DATA_ROOT.'/mycompany/cgv/'.$lang_code.'/cgv.pdf';
		$candidates[] = DOL_DATA_ROOT.'/mycompany/cgv/cgv.pdf';
	} else {
		$candidates[] = $terms_base.'/'.$default_filename;
		$candidates[] = DOL_DATA_ROOT.'/mycompany/cgv/cgv.pdf';
	}
}

$terms_pdf = '';
$terms_pdf_os = '';
foreach ($candidates as $candidate) {
	$candidate_os = dol_osencode($candidate);
	if (@is_file($candidate_os)) {
		$terms_pdf = $candidate;
		$terms_pdf_os = $candidate_os;
		break;
	}
}

if (empty($terms_pdf)) {
	return;
}

$pagecount = $pdf->setSourceFile($terms_pdf_os);
for ($i = 1; $i <= $pagecount; $i++) {
	$tplidx = $pdf->importPage($i);
	$s = $pdf->getTemplateSize($tplidx);
	$pdf->AddPage('P', array($s['w'], $s['h']));
	$pdf->useTemplate($tplidx);
	// Draft watermark on appended pages (order → COMMANDE, invoice → FACTURE)
	$isDraft = isset($object->statut) && (
		(defined(get_class($object).'::STATUS_DRAFT') && (int) $object->statut === (int) $object::STATUS_DRAFT)
		|| (!defined(get_class($object).'::STATUS_DRAFT') && (int) $object->statut === 0)
	);
	if ($isDraft) {
		$draftWatermark = '';
		if (method_exists($object, 'element') && $object->element == 'commande') {
			$draftWatermark = getDolGlobalString('COMMANDE_DRAFT_WATERMARK');
		} else {
			$draftWatermark = getDolGlobalString('FACTURE_DRAFT_WATERMARK');
		}
		if ($draftWatermark !== '') {
			pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', $draftWatermark);
			$pdf->SetTextColor(0, 0, 60);
		}
	}
	// Footer on each terms page
	if (method_exists($this, '_pagefoot')) {
		$this->_pagefoot($pdf, $object, $outputlangs);
	}
}
