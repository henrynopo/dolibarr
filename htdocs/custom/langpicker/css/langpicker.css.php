<?php

//define('NOLOGIN', 1);
define('NOREDIRECTBYMAINTOLOGIN', 1);
define('NOTOKENRENEWAL', 1);

// Load Dolibarr environment
if (false === (@include '../../main.inc.php')) {  // From htdocs directory
  require '../../../main.inc.php'; // From "custom" directory
}

global $langs, $user, $conf;

$default_lang = (isset($user->conf->MAIN_LANG_DEFAULT) ? $user->conf->MAIN_LANG_DEFAULT : $conf->global->MAIN_LANG_DEFAULT);

$position = 130; // Legacy "pixel position" setting removed in Dolibarr 22-compatible layout

header('Content-Type: text/css');

?>

/**
 * Language Picker CSS
 */

.language-dropdown {
  position: <?php echo $conf->theme == 'md' ? 'fixed' : 'absolute'; ?>;
  top: 0;
  <?php echo ($langs->trans("DIRECTION") == 'rtl' ? 'left' : 'right').': '.$position; ?>px;
  text-align: center;
  display: inline-block;
}

/* If language picker is inside the top header login block, it must not float above the page */
header#id-top div.login_block .language-dropdown {
  position: relative !important;
  top: auto !important;
  left: auto !important;
  right: auto !important;
  margin: 0 6px;
}

/* When moved into top right header area (Dolibarr 22+), we must not use fixed/absolute positioning */
.language-dropdown.langpicker-moved-into-topright {
  position: relative !important;
  top: auto !important;
  left: auto !important;
  right: auto !important;
  margin: 0 6px;
}

/* Top-right toggle: mimic Dolibarr header button style */
.language-dropdown .langpicker-toggle {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 0 6px;
  border-radius: 4px;
  line-height: inherit;
  font: inherit;
  font-weight: inherit;
  text-decoration: none;
  user-select: none;
  -webkit-user-select: none;
  white-space: nowrap;
}

header#id-top .language-dropdown .langpicker-toggle:hover {
  text-decoration: none;
  background: rgba(255, 255, 255, 0.12);
}

header#id-top .language-dropdown .langpicker-toggle:focus {
  outline: 0;
}

.language-dropdown .langpicker-abbr {
  font: inherit;
  letter-spacing: 0.02em;
}

/* Ensure flag sprite aligns with header text */
.language-dropdown .flag-sprite {
  display: inline-block;
  vertical-align: middle;
  transform: translateY(-0.5px);
}

.language-dropdown ul.lang-list li a {
  text-decoration: none;
  color: #000;
  background-color: #fff;
}

.language-dropdown ul.lang-list li a:hover {
  background-color: #eee;
}

.language-dropdown ul.lang-list {
  position: absolute;
  display: none;
  z-index: 2000;
  margin: 6px 0 0 0;
  padding: 6px 0;
  list-style: none;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 10px 22px rgba(0, 0, 0, 0.18);
  min-width: 96px;
  width: max-content;
  <?php echo ($langs->trans("DIRECTION") == 'rtl' ? 'left' : 'right'); ?>: 0;
}

.language-dropdown.open ul.lang-list {
  display: block;
}

.language-dropdown ul.lang-list li.selected {
  display: none;
}

.language-dropdown ul.lang-list li a.langpicker-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border: 0;
  font: inherit;
  white-space: nowrap;
}

.language-dropdown ul.lang-list li a.langpicker-item:hover {
  background-color: rgba(0, 0, 0, 0.05);
}

/* 登录页：黑色 fa-language + 下拉框（国旗在 Select2 内） */
.bodylogin .login_table .trinputlogin .tdinputlogin > .fa.fa-language,
.login_table .trinputlogin .tdinputlogin > .fa.fa-language {
  position: relative;
  z-index: 2;
  display: inline-block;
  vertical-align: middle;
  visibility: visible !important;
  opacity: 1 !important;
  margin-right: 4px;
}
.bodylogin .login_table .trinputlogin .tdinputlogin select#lang_code,
.login_table .trinputlogin .tdinputlogin select#lang_code {
  max-width: 100%;
  vertical-align: middle;
}
.bodylogin .login_table .trinputlogin .tdinputlogin .select2-container,
.login_table .trinputlogin .tdinputlogin .select2-container {
  display: inline-block !important;
  min-width: 150px;
  width: calc(100% - 10px) !important;
  max-width: 100%;
  vertical-align: middle;
  box-sizing: border-box;
  margin-left: 5px;
  margin-top: 5px;
  margin-bottom: 5px;
  margin-right: 10px;
}
.bodylogin .login_table .trinputlogin .tdinputlogin .select2-container .select2-selection,
.login_table .trinputlogin .tdinputlogin .select2-container .select2-selection {
  width: 100%;
  box-sizing: border-box;
}
