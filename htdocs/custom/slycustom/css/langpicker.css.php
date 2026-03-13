<?php
/* Copyright (C) 2025 SLY Custom - Language picker dropdown styles */

define('NOREDIRECTBYMAINTOLOGIN', 1);
define('NOTOKENRENEWAL', 1);
require_once __DIR__.'/../../main.inc.php';

global $langs, $user, $conf;

$default_lang = (isset($user->conf->MAIN_LANG_DEFAULT) ? $user->conf->MAIN_LANG_DEFAULT : getDolGlobalString('MAIN_LANG_DEFAULT', 'en_US'));
if (empty($default_lang)) {
	$default_lang = 'en_US';
}

header('Content-Type: text/css; charset=utf-8');
// 开发时避免浏览器强缓存，修改 CSS 后刷新即可看到效果；生产环境可改为长期缓存
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

$is_rtl = ($langs->trans("DIRECTION") == 'rtl');
?>
/**
 * Language Picker CSS (SLY)
 * Position:fixed so it does not sit in the login_block flow (user never pushed down).
 * JS sets right/left dynamically so the picker sits just to the left of the user button.
 */
/* 
 * Ensure the container flows nicely. 
 * login_block_other contains tools, version, and langpicker. 
 */
.login_block_other {
  display: inline-flex !important;
  flex-direction: row;
  align-items: center;
  gap: 8px; /* Standardize gap */
  float: none !important; /* Override Eldy's float if necessary to keep flex active */
}

/* 
 * Lang picker block: remove absolute positioning. 
 * Use order: 10 to place it after tools and version (which have default order 0). 
 */
.login_block_lang {
  order: 10; 
  position: relative !important;
  display: inline-flex;
  align-items: center;
  line-height: normal !important;
  height: auto !important;
  margin: 0 !important;
  padding: 0 4px !important;
  border: none;
  box-sizing: border-box;
  z-index: 10;
}
<?php if (!empty($conf->theme) && $conf->theme == 'md') { ?>
@media (max-width: 768px) {
  .login_block_lang { line-height: 35px; height: 35px; }
}
<?php } ?>

#topmenu-lang-dropdown.language-dropdown {
  position: relative;
  display: inline-flex;
  align-items: center;
  padding: 0;
  line-height: normal !important;
  height: auto !important;
}
#topmenu-lang-dropdown .dropdown-toggle::after {
  display: none;
}
/* 与顶栏 login_block_user 同高，避免把 user 挤下去 */
#topmenu-lang-dropdown a#lang-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 40px; /* Sensible default for top menu height */
  height: 40px;
  padding: 0 8px;
  border-radius: 4px;
}
#topmenu-lang-dropdown a#lang-toggle:hover {
  background: rgba(0,0,0,0.05);
  text-decoration: none;
}
#topmenu-lang-dropdown .lang-btn-inner {
  min-height: 0;
}
#topmenu-lang-dropdown .lang-picto:empty {
  display: none;
}
/* 继承主题字号，避免被父级 font-size:0 导致简写不可见 */
#topmenu-lang-dropdown .lang-abbr {
  font-size: inherit;
  line-height: 1.2;
}
/* 下拉：与主题 .dropdown-menu 一致的主体文字/背景，避免继承顶栏浅色导致白字 */
#topmenu-lang-dropdown .dropdown-menu {
  min-width: 140px;
  max-width: 220px;
  display: none;
  background-color: #fff;
  color: #333;
}
#topmenu-lang-dropdown.open .dropdown-menu {
  display: block;
}
#topmenu-lang-dropdown .dropdown-menu .lang-list {
  margin: 0;
  padding: 4px 0;
  list-style: none;
}
#topmenu-lang-dropdown .dropdown-menu .lang-list li a {
  display: block;
  padding: 8px 14px;
  white-space: nowrap;
  color: #333;
}
#topmenu-lang-dropdown .dropdown-menu .lang-list li a:hover {
  color: #333;
  background-color: rgba(0, 0, 0, 0.05);
}
#topmenu-lang-dropdown .dropdown-menu .lang-list li.selected {
  display: none;
}

/* Login page (eldy and others): ensure icons in every row (Login, Password, Language, TOTP/2FA) stay visible and are not obscured */
.bodylogin .login_table .trinputlogin .tdinputlogin > .fa,
.login_table .trinputlogin .tdinputlogin > .fa {
  position: relative;
  z-index: 2;
  visibility: visible !important;
  opacity: 1 !important;
}
/* Keep each login row in its own stacking context; last row (e.g. TOTP/2FA) on top so its icon is not covered */
.bodylogin .login_table .trinputlogin,
.login_table .trinputlogin {
  position: relative;
  z-index: 0;
}
.bodylogin .login_table .trinputlogin:last-of-type,
.login_table .trinputlogin:last-of-type {
  z-index: 1;
}
.bodylogin .login_table .trinputlogin .tdinputlogin,
.login_table .trinputlogin .tdinputlogin {
  overflow: visible;
}
/* Prevent language select from overflowing into next row */
.bodylogin .login_table .trinputlogin .tdinputlogin select#lang_code,
.login_table .trinputlogin .tdinputlogin select#lang_code {
  max-width: 100%;
  vertical-align: middle;
}

/* Language row: keep icon and dropdown on same line (inline-block), same spacing as input rows */
.bodylogin .login_table .trinputlogin .tdinputlogin .select2-container,
.login_table .trinputlogin .tdinputlogin .select2-container {
  display: inline-block !important;
  min-width: 180px;
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
