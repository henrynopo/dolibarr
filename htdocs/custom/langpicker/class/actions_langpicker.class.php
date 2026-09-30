<?php

/**
 * Class ActionsLangPicker
 */
class ActionsLangPicker
{
	/**
	 * Return default language from LangPicker order (position ASC).
	 *
	 * @return string
	 */
	private function getDefaultLangFromPicker()
	{
		$default_lang = '';

		dol_include_once('/langpicker/class/langpicker.class.php');
		$langpicker = new LangPicker();
		$langpicker->fetchAll(1, 0, 't.position', 'ASC');
		if (!empty($langpicker->lines) && !empty($langpicker->lines[0]) && !empty($langpicker->lines[0]->lang_code)) {
			$default_lang = $langpicker->lines[0]->lang_code;
		}

		return $default_lang;
	}

	/**
	 * Overloading the getLoginPageExtraOptions function
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process
	 * @param   string          &$action        Current action
	 * @param   HookManager     $hookmanager    Hook manager
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function getLoginPageExtraOptions($parameters, &$object, &$action, $hookmanager)
	{
		// 国旗仅在下拉/Select2 内显示；闭合状态下把选项的 data-html 写入 Select2 已选区
		$this->resprints = '<script>
		(function() {
			function langpickerShowFlagInSelect2Selection() {
				var sel = document.getElementById("lang_code");
				if (!sel || !window.jQuery) return;
				var $ = window.jQuery;
				var $c = $(sel).next(".select2-container");
				if (!$c.length) return;
				var opt = sel.options[sel.selectedIndex];
				if (!opt) return;
				var html = opt.getAttribute("data-html");
				if (!html) return;
				var dec = $("<textarea/>").html(html).text();
				var $rend = $c.find(".select2-selection__rendered");
				if ($rend.length) {
					$rend.html(dec);
				}
			}
			function bind() {
				var sel = document.getElementById("lang_code");
				if (!sel) return;
				if (document.addEventListener) {
					document.addEventListener("change", function(e) {
						if (e && e.target && e.target.id === "lang_code") {
							langpickerShowFlagInSelect2Selection();
						}
					});
				}
				if (window.jQuery) {
					window.jQuery(sel).on("select2:select", langpickerShowFlagInSelect2Selection);
				}
				setTimeout(langpickerShowFlagInSelect2Selection, 0);
				setTimeout(langpickerShowFlagInSelect2Selection, 300);
			}
			if (document.readyState === "complete" || document.readyState === "interactive") {
				bind();
			} else if (document.addEventListener) {
				document.addEventListener("DOMContentLoaded", bind);
			}
		})();
		</script>';

		return 0;
	}

	/**
	 * Overloading the afterLogin function
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function afterLogin($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $conf, $user, $langs;

		$error = 0; // Error counter

		if (in_array('login', explode(':', $parameters['context'])) && $conf->global->LANG_PICKER_ADD_TO_LOGIN_PAGE)
		{
			require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

			$lang_code = GETPOST('lang_code', 'alphanohtml');

			if (empty($lang_code)) {
				$default_lang = $this->getDefaultLangFromPicker();
				if (empty($default_lang)) {
					$default_lang = getDolGlobalString('LANG_PICKER_LOGIN_PAGE_DEFAULT_LANG', '');
				}
				if (empty($default_lang)) {
					$default_lang = getDolGlobalString('MAIN_LANG_DEFAULT', 'en_US');
				}
				$lang_code = $default_lang;
			}

			// Persist as user preference and apply immediately to current session
			if (empty($user->conf->MAIN_LANG_DEFAULT) || $user->conf->MAIN_LANG_DEFAULT != $lang_code) {
				dol_set_user_param($db, $conf, $user, array('MAIN_LANG_DEFAULT' => $lang_code));
				$user->conf->MAIN_LANG_DEFAULT = $lang_code;
			}
			if (is_object($langs)) {
				$langs->setDefaultLang($lang_code);
			}
		}

		if (! $error)
		{
			return 0; // or return 1 to replace standard code
		}
		else
		{
			return -1;
		}
	}

	/**
	 * Overloading the getLoginPageOptions function
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function getLoginPageOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $db;

		$error = 0; // Error counter

		if (in_array('mainloginpage', explode(':', $parameters['context'])) && $conf->global->LANG_PICKER_ADD_TO_LOGIN_PAGE)
		{
			require_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';

			$formadmin = new FormAdmin($db);
			$langs->load('langpicker@langpicker');

			$lang_code = GETPOST('lang_code', 'alphanohtml');
			if (empty($lang_code)) {
				$default_lang = $this->getDefaultLangFromPicker();
				if (empty($default_lang)) {
					$default_lang = getDolGlobalString('LANG_PICKER_LOGIN_PAGE_DEFAULT_LANG', '');
				}
				if (empty($default_lang)) {
					$default_lang = getDolGlobalString('MAIN_LANG_DEFAULT', 'en_US');
				}
				$lang_code = $default_lang;
			}

			// 与 core picto_from_langcode("auto") 一致：左侧黑色 fa-language；国旗仅在下拉框内（Select2 + getLoginPageExtraOptions）
			$langs->load("admin");
			$this->resprints = '<div class="trinputlogin"><div class="tagtd nowraponall center valignmiddle tdinputlogin">';
			$this->resprints .= '<span class="fa fa-language" aria-hidden="true"></span>';
			$this->resprints .= $formadmin->select_language($lang_code, 'lang_code', 0, array(), $langs->trans('SelectLanguage'), 0, 0, 'minwidth150', 0, 0);
			$this->resprints .= '</div></div>';
		}

		if (! $error)
		{
			return 0; // or return 1 to replace standard code
		}
		else
		{
			return -1;
		}
	}

	/**
	 * Overloading the printTopRightMenu function
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printTopRightMenu($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $conf, $langs;

		$error = 0; // Error counter

		$canUseLangPicker = (!empty($user) && !empty($user->rights) && !empty($user->rights->langpicker) && !empty($user->rights->langpicker->use));
		if (in_array('toprightmenu', explode(':', $parameters['context'])) && $canUseLangPicker && empty($conf->global->LANG_PICKER_HIDDEN))
		{
			// Load languages file
			$langs->load("admin");
			$langs->load("languages");

			// Load object class
			dol_include_once('/langpicker/class/langpicker.class.php');

			// Fetch languages
			$langpicker = new LangPicker();
			$langpicker->fetchAll(0, 0, 't.position', 'ASC');

			// Add language picker
			$default_lang = (isset($user->conf->MAIN_LANG_DEFAULT) ? $user->conf->MAIN_LANG_DEFAULT : $conf->global->MAIN_LANG_DEFAULT);
			$default_lang_picto = picto_from_langcode($default_lang);
			$default_lang_code = explode('_', $default_lang);
			$default_lang_abbr = strtoupper($default_lang_code[0]);
			$default_lang_trans = ($default_lang == 'auto' ? $langs->trans("AutoDetectLang") : $langs->trans("Language_".$default_lang));

			$out = '';
			$out .= '<div class="language-dropdown login_block_elem">';
			$out .= '<a id="lang-toggle" class="langpicker-toggle atoplogin valignmiddle" href="#" role="button" aria-haspopup="true" aria-expanded="false" title="'.dol_escape_htmltag($default_lang_trans).'">'.$default_lang_picto.' <span class="langpicker-abbr">'.$default_lang_abbr.'</span></a>';
			$out .= '<ul class="lang-list">';
			foreach ($langpicker->lines as $lang)
			{
				$lang_code = explode('_', $lang->lang_code);
				$abbr = strtoupper($lang_code[0]);
				$url = dol_buildpath('/langpicker/set_lang.php', 1).'?lang_code='.$lang->lang_code.'&backtopage='.urlencode($_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
				$title = $langs->trans("Language_".$lang->lang_code);
				$out .= '<li class="lang'.($default_lang == $lang->lang_code ? ' selected' : '').'"><a class="langpicker-item" href="'.dol_escape_htmltag($url).'" title="'.dol_escape_htmltag($title).'">'.picto_from_langcode($lang->lang_code).' <span class="langpicker-abbr">'.$abbr.'</span></a></li>';
			}
			$out .= '</ul>';
			$out .= '</div>';

			// Dolibarr hook convention: use $this->resprints (preferred) for HTML output.
			// Keep also $hookmanager->resPrint for forward compatibility.
			if (!isset($this->resprints)) {
				$this->resprints = '';
			}
			$this->resprints .= $out;
			$hookmanager->resPrint .= $out;
		}

		if (! $error)
		{
			return 0; // or return 1 to replace standard code
		}
		else
		{
			return -1;
		}
	}
}
