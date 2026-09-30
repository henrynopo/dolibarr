<?php
/* Copyright (C) 2026 Henry Guo <hbg@hbg.sg>
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
 */

/**
 * \file   htdocs/custom/sfrs_reports/tpl/report_currency_filter.tpl.php
 * \brief  Render the "Reporting Currency" dropdown for SFRS report pages
 *
 * Included by pages/balance_sheet.php, pages/profit_loss.php, pages/cash_flow.php
 * to let the user pick which currency slice of the bookkeeping to render.
 *
 * Expects the following in the calling scope:
 *   - $db        DoliDB instance
 *   - $langs     Translate instance
 *   - $conf      Conf instance (for $conf->currency / $conf->entity)
 *   - $mc_code   string Current selection: 'FUNC' / 'ALL' / 'USD' / etc.
 *
 * Outputs:
 *   - one <tr>...</tr> table row (caller wraps in <table>)
 *   - one <tr>...</tr> for the company info section showing current selection
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
	die('Not a valid Dolibarr entry point');
}

$functional_cc = getDolGlobalString('MAIN_MONNAIE', $conf->currency ?? 'XXX');

// Discover all currencies that have ever been used in bookkeeping (or registered)
// Entity scoping: bookkeeping follows the core accounting convention (no getEntity),
// multicurrency follows getEntity('multicurrency') as in multicurrency/class/multicurrency.class.php.
$used_codes = array();
$res_mc = $db->query("SELECT DISTINCT multicurrency_code FROM ".MAIN_DB_PREFIX."accounting_bookkeeping WHERE entity = ".((int) $conf->entity)." AND multicurrency_code IS NOT NULL AND multicurrency_code <> '' ORDER BY multicurrency_code");
if ($res_mc) {
	while ($obj = $db->fetch_object($res_mc)) {
		$used_codes[] = $obj->multicurrency_code;
	}
}

// Also include currencies registered in llx_multicurrency (in case none booked yet)
$res_mc2 = $db->query("SELECT DISTINCT code FROM ".MAIN_DB_PREFIX."multicurrency WHERE entity IN (".getEntity('multicurrency').") ORDER BY code");
if ($res_mc2) {
	while ($obj = $db->fetch_object($res_mc2)) {
		if (!in_array($obj->code, $used_codes, true)) {
			$used_codes[] = $obj->code;
		}
	}
}
sort($used_codes);

print '<tr class="oddeven">';
print '<td width="20%">'.$langs->trans('ReportingCurrency').'</td>';
print '<td>';
print '<select name="mc_code" class="flat">';
// Option 1: functional currency (default)
print '<option value="FUNC"'.(($mc_code === 'FUNC' || $mc_code === '') ? ' selected' : '').'>';
print $langs->trans('FunctionalCurrencyOnly').' — '.$functional_cc;
print '</option>';
// Option 2: all currencies summed (no filter)
print '<option value="ALL"'.($mc_code === 'ALL' ? ' selected' : '').'>'.$langs->trans('AllCurrenciesSummed').'</option>';
// One option per registered / used currency
foreach ($used_codes as $code) {
	print '<option value="'.dol_escape_htmltag($code).'"'.($mc_code === $code ? ' selected' : '').'>';
	print $langs->trans('CurrencyOnly').' — '.dol_escape_htmltag($code);
	print '</option>';
}
print '</select>';
print ' <input type="submit" class="button" value="'.$langs->trans('Generate').'">';
print '</td>';
print '</tr>';

// Echo current selection back as a labeled cell (used in the report header table below)
$_sfrs_current_mc_label = $mc_code;
if ($mc_code === '' || $mc_code === 'FUNC') {
	$_sfrs_current_mc_label = $functional_cc.' ('.$langs->trans('FunctionalCurrencyOnly').')';
} elseif ($mc_code === 'ALL') {
	$_sfrs_current_mc_label = $langs->trans('AllCurrenciesSummed').' ('.implode(', ', $used_codes).')';
}