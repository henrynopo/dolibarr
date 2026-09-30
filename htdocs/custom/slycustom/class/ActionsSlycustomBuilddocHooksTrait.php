<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: SLY Custom builddoc hooks (include sales terms).
 *
 * Extracted from actions_slycustom.class.php to reduce file size.
 * Hook method names and signatures are kept identical to preserve behavior.
 */
trait ActionsSlycustomBuilddocHooksTrait
{
	/**
	 * Add column for "Include sales terms" in builddoc form (FormFile::showdocuments).
	 *
	 * Only for sales order (commande) so the core adds one extra <th>.
	 *
	 * @return void
	 */
	public function formBuilddocLineOptions()
	{
		// Method exists so that the core adds one extra column for our formBuilddocOptions content
	}

	/**
	 * Add "Include sales terms" row on order document form: checkbox + flat template list.
	 * All UI and template list logic lives in SLY module (no core SLY-specific code).
	 *
	 * @param array        $parameters Hook parameters (modulepart, id, socid, colspan, ...)
	 * @param CommonObject $object     Object (e.g. Commande)
	 * @return int                     0
	 */
	public function formBuilddocOptions($parameters, $object)
	{
		global $hookmanager, $langs, $conf;

		if (empty($parameters['modulepart']) || $parameters['modulepart'] !== 'commande') {
			return 0;
		}
		if (!isModEnabled('slycustom')) {
			return 0;
		}

		$langs->load('slycustom@slycustom');
		$langs->load('languages');
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$terms_base = DOL_DATA_ROOT.'/mycompany/terms';
		$sly_terms_flat = array();
		if (is_dir(dol_osencode($terms_base))) {
			$default_label = $langs->trans('Default');
			if ($default_label === 'Default' && $langs->defaultlang != 'en_US') {
				$default_label = 'Default';
			}
			$all = dol_dir_list($terms_base, 'files', 0, '', array(), 'name', SORT_ASC, 0);
			foreach ($all as $e) {
				if (preg_match('/\.pdf$/i', $e['name'])) {
					$name_no_ext = preg_replace('/\.pdf$/i', '', $e['name']);
					$sly_terms_flat[] = array('rel' => $e['name'], 'lang_label' => $default_label, 'langcode' => '', 'filename_display' => $name_no_ext);
				}
			}
			$list_dirs = dol_dir_list($terms_base, 'directories', 0, '', array(), 'name', SORT_ASC, 0);
			foreach ($list_dirs as $ent) {
				$lc = isset($ent['name']) ? trim($ent['name']) : '';
				if ($lc === '' || $lc === '.' || $lc === '..' || $lc === '-1' || is_numeric($lc)) {
					continue;
				}
				$lc = basename($lc);
				$lang_dir = $terms_base.'/'.$lc;
				if (!is_dir(dol_osencode($lang_dir))) {
					continue;
				}
				// Use Language_xx_YY so label follows current UI language (e.g. 中文 when UI is Chinese)
				$lang_key = 'Language_'.str_replace('-', '_', $lc);
				$lang_label = $langs->trans($lang_key);
				if ($lang_label === $lang_key) {
					$lang_label = $lc;
				}
				$files = dol_dir_list($lang_dir, 'files', 0, '', array(), 'name', SORT_ASC, 0);
				foreach ($files as $f) {
					if (isset($f['name']) && preg_match('/\.pdf$/i', $f['name'])) {
						$name_no_ext = preg_replace('/\.pdf$/i', '', $f['name']);
						$sly_terms_flat[] = array('rel' => $lc.'/'.$f['name'], 'lang_label' => $lang_label, 'langcode' => $lc, 'filename_display' => $name_no_ext);
					}
				}
			}
		}

		$add_terms_label = $langs->trans('SLYCUSTOM_ADD_TERMS_LABEL');
		if ($add_terms_label === 'SLYCUSTOM_ADD_TERMS_LABEL') {
			$add_terms_label = 'Include sales terms';
		}
		$no_tpl_msg = $langs->trans('SLYCUSTOM_TERMS_NO_TEMPLATE');
		if ($no_tpl_msg === 'SLYCUSTOM_TERMS_NO_TEMPLATE') {
			$no_tpl_msg = 'No sales terms template for this language';
		}
		$add_terms_checked = GETPOSTINT('add_terms') ? ' checked' : '';
		$selected_tpl = GETPOST('add_terms_template', 'alphanohtml');
		$colspan = isset($parameters['colspan']) ? (int) $parameters['colspan'] : 10;

		$this->resprints .= '<tr><td colspan="'.$colspan.'" class="oddeven">';
		$this->resprints .= '<label class="valignmiddle"><input type="checkbox" name="add_terms" id="sly_add_terms_cb" value="1"'.$add_terms_checked.'> '.dol_escape_htmltag($add_terms_label).'</label>';
		$this->resprints .= ' <span id="sly_terms_template_block" style="margin-left:8px;">';
		if (count($sly_terms_flat) === 0) {
			$this->resprints .= '<span class="opacitymedium">'.dol_escape_htmltag($no_tpl_msg).'</span>';
		} elseif (count($sly_terms_flat) === 1) {
			$one = $sly_terms_flat[0];
			$one_display = ($one['langcode'] !== '' ? picto_from_langcode($one['langcode'], 'class="saturatemedium paddingrightonly"').' ' : '')
				.dol_escape_htmltag($one['lang_label']).': '.dol_escape_htmltag($one['filename_display']);
			$this->resprints .= $one_display;
			$this->resprints .= '<input type="hidden" name="add_terms_template" value="'.dol_escape_htmltag($one['rel']).'">';
		} else {
			$this->resprints .= '<select name="add_terms_template" id="sly_add_terms_template" class="flat maxwidth200">';
			foreach ($sly_terms_flat as $idx => $item) {
				$sel = ($selected_tpl !== '' && $selected_tpl === $item['rel']) || ($selected_tpl === '' && $idx === 0) ? ' selected' : '';
				// Same format as FormAdmin::select_language: flag icon + "语言：文件名" for combobox HTML display
				$opt_html = ($item['langcode'] !== '' ? picto_from_langcode($item['langcode'], 'class="saturatemedium"').' ' : '')
					.dol_escape_htmltag($item['lang_label']).': '.dol_escape_htmltag($item['filename_display']);
				$this->resprints .= '<option value="'.dol_escape_htmltag($item['rel']).'"'.$sel.' data-html="'.dol_escape_htmltag($opt_html).'">'.$opt_html.'</option>';
			}
			$this->resprints .= '</select>';
			if (!empty($conf->use_javascript_ajax)) {
				include_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
				$this->resprints .= ajax_combobox('sly_add_terms_template');
			}
		}
		$this->resprints .= '</span>';
		$this->resprints .= '</td></tr>';
		$this->resprints .= '<script nonce="'.getNonce().'">document.addEventListener("DOMContentLoaded",function(){var cb=document.getElementById("sly_add_terms_cb");var block=document.getElementById("sly_terms_template_block");function sync(){if(block)block.style.display=cb&&cb.checked?"inline":"none";}if(cb){cb.addEventListener("change",sync);sync();}var form=document.getElementById("builddoc_form");if(form){form.addEventListener("submit",function(){if(cb&&cb.checked){var m=window.location.search.match(/[?&]id=(\d+)/);var id=m?m[1]:"";if(id){form.action="'.dol_escape_js(DOL_URL_ROOT.'/custom/slycustom/builddoc_order.php').'?id="+id;}}},false);}});</script>';

		return 0;
	}
}

