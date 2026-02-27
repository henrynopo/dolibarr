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

header('Content-Type: text/css');
?>
/**
 * Language Picker CSS (SLY) – 顶栏固定顺序：工具 → 其它 → 语言 → 用户；LTR 布局
 */
.login_block.usedropdown {
  direction: ltr;
  display: flex;
  flex-direction: row;
  flex-wrap: nowrap;
  align-items: center;
}
.login_block.usedropdown > .login_block_tools { order: 1; }
.login_block.usedropdown > .login_block_other { order: 2; }
.login_block.usedropdown > .login_block_lang { order: 3; }
.login_block.usedropdown > .login_block_user { order: 4; }

.login_block_lang {
  display: inline-block;
  line-height: 50px;
  margin-left: 8px;
  padding-left: 10px;
  border-left: 1px solid rgba(0, 0, 0, 0.12);
}
<?php if (!empty($conf->theme) && $conf->theme == 'md') { ?>
@media (max-width: 768px) {
  .login_block_lang { line-height: 35px; }
}
<?php } ?>

#topmenu-lang-dropdown.language-dropdown {
  position: relative;
  display: inline-block;
  padding: 0 5px;
  line-height: 50px;
}
<?php if (!empty($conf->theme) && $conf->theme == 'md') { ?>
@media (max-width: 768px) {
  #topmenu-lang-dropdown.language-dropdown { line-height: 35px; }
}
<?php } ?>
#topmenu-lang-dropdown .dropdown-toggle::after {
  display: none;
}
/* 链接与书签同高，内层 flex 才能正确撑开，简写可见 */
#topmenu-lang-dropdown a#lang-toggle {
  display: inline-block;
  line-height: 50px;
  height: 50px;
}
<?php if (!empty($conf->theme) && $conf->theme == 'md') { ?>
@media (max-width: 768px) {
  #topmenu-lang-dropdown a#lang-toggle { line-height: 35px; height: 35px; }
}
<?php } ?>
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
