<?php

/* Copyright (C) 2024		Frédéric France			<frederic.france@free.fr>
 * Copyright (C) 2025		MDW					<mdeweerd@users.noreply.github.com>
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
 *
 */

/**
 * @var DoliDB $db
 * @var Form $form
 * @var Translate $langs
 *
 * @var int	$trforbreaknobg
 * @var array{nbfield:int,type?:array<int,string>,pos?:array<int,string>,val?:array<int,float>} $totalarray
 */
'
@phan-var-force array{nbfield:int,type?:array<int,string>,pos?:array<int,string>,val?:array<int,float>} $totalarray
@phan-var-force ?string $sqlfields
@phan-var-force ?int $limit
';

if (!function_exists('printTotalValCell')) { // allow two list with total on same screen

	/** print a total cell value according to its type
	 *
	 * @param string $type of field (duration, string..)
	 * @param string $val the value to display
	 *
	 * @return void (direct print)
	 */
	function printTotalValCell($type, $val)
	{
		// if $totalarray['type'] not present we consider it as number
		if (empty($type)) {
			$type = 'real';
		}
		switch ($type) {
			case 'duration':
				print '<td class="right">';
				print(!empty($val) ? convertSecondToTime((int) $val, 'allhourmin') : 0);
				print '</td>';
				break;
			case 'string':	// This type is no more used. type is now varchar(x)
				print '<td class="left">';
				print(!empty($val) ? $val : '');
				print '</td>';
				break;
			case 'stock':
				print '<td class="right">';
				print price2num(!empty($val) ? $val : 0, 'MS');
				print '</td>';
				break;
			default:
				print '<td class="right">';
				print price(!empty($val) ? $val : 0);
				print '</td>';
				break;
		}
	}
}

if (!function_exists('printTotalValCellByCurrency')) {
	/** Print a total cell with amounts per currency, one currency per line (e.g. "USD 10,000" then "+ EUR 5,000" on next line)
	 *
	 * @param array<string,float> $amountsByCurrency currency code => amount (only non-zero are shown)
	 * @return void
	 */
	function printTotalValCellByCurrency($amountsByCurrency)
	{
		global $langs;
		$parts = array();
		foreach ($amountsByCurrency as $currency_code => $amount) {
			if ($amount != 0 && $amount !== '' && $amount !== null) {
				// Format as "USD 10,000" (same as Related Objects: code + space + price)
				$parts[] = $currency_code . ' ' . price(price2num($amount), 0, $langs, 1, -1, -1, '');
			}
		}
		// Line break: use real newline + pre-line so it works even if <br> is escaped by theme/JS
		print '<td class="right total-by-currency-cell" style="white-space: pre-line !important;">';
		print implode("\n+ ", $parts);
		print '</td>';
	}
}

// Move fields of totalizable into the common array pos and val
if (!empty($totalarray['totalizable']) && is_array($totalarray['totalizable'])) {
	foreach ($totalarray['totalizable'] as $keytotalizable => $valtotalizable) {
		$totalarray['pos'][$valtotalizable['pos']] = $keytotalizable;
		$totalarray['val'][$keytotalizable] = isset($valtotalizable['total']) ? $valtotalizable['total'] : 0;
	}
}
// Show total line (when we have totalizable columns with pos, or when we have data and column count so at least "Total" label is shown)
$showTotalLine = isset($totalarray['pos']) && is_array($totalarray['pos'])
	|| (isset($num) && $num > 0 && !empty($totalarray['nbfield']));
if ($showTotalLine) {
	if (!isset($totalarray['pos']) || !is_array($totalarray['pos'])) {
		$totalarray['pos'] = array();
	}
	//print '<tfoot>';
	// liste_total_wrap + inline style: allow line break in cells (multi-currency "USD x" / "+ EUR y"); style is fallback if theme strips second class
	print '<tr class="liste_total liste_total_wrap'.(empty($trforbreaknobg) ? '' : ' trforbreaknobg').'" style="white-space: normal;">';
	// When list has checkbox as first column, that column does not increment nbfield in data row; output one empty cell so Total row aligns with header/data
	if (!empty($totalarray['first_column_empty'])) {
		print '<td></td>';
	}
	$i = 0;
	while ($i < $totalarray['nbfield']) {
		$i++;
		if (!empty($totalarray['pos'][$i])) {
			$fieldName = $totalarray['pos'][$i];
			$amountsByCurrency = array();
			if (!empty($totalarray['val_by_currency']) && is_array($totalarray['val_by_currency'])) {
				foreach ($totalarray['val_by_currency'] as $currency_code => $byField) {
					if (isset($byField[$fieldName]) && $byField[$fieldName] != 0 && $byField[$fieldName] !== '' && $byField[$fieldName] !== null) {
						$amountsByCurrency[$currency_code] = $byField[$fieldName];
					}
				}
			}
			if (!empty($amountsByCurrency)) {
				printTotalValCellByCurrency($amountsByCurrency);
			} else {
				printTotalValCell($totalarray['type'][$i] ?? '', empty($totalarray['val'][$fieldName]) ? '0' : (string) $totalarray['val'][$fieldName]);
			}
		} else {
			if ($i == 1) {
				if ((!isset($limit) || $num < $limit) && empty($offset)) {
					print '<td>'.$langs->trans("Total").'</td>';
				} else {
					print '<td>';
					if (is_object($form)) {
						print $form->textwithpicto($langs->trans("Total"), $langs->transnoentitiesnoconv("Totalforthispage"));
					} else {
						print $langs->trans("Totalforthispage");
					}
					print '</td>';
				}
			} else {
				print '<td></td>';
			}
		}
	}
	print '</tr>';
	// Add grand total if necessary ie only if different of page total already printed above
	if (getDolGlobalString('MAIN_GRANDTOTAL_LIST_SHOW') && (!(is_null($limit) || $num < $limit))) {
		if (isset($totalarray['pos']) && is_array($totalarray['pos']) && count($totalarray['pos']) > 0) {
			$sumsarray = false;
			$tbsumfields = [];
			foreach ($totalarray['pos'] as $field) {
				$fieldforsum = preg_replace('/[^a-z0-9]/', '', $field);
				$tbsumfields[] = "sum($field) as $fieldforsum";
			}
			if (isset($sqlfields)) { // In project, commande list, this var is defined
				$sqlforgrandtotal = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT '. implode(",", $tbsumfields), $sql);
			} else {
				$sqlforgrandtotal = preg_replace('/^SELECT[a-zA-Z0-9\._\s\(\),=<>\:\-\']+\sFROM/', 'SELECT '. implode(",", $tbsumfields). ' FROM ', $sql);
			}
			$sqlforgrandtotal = preg_replace('/GROUP BY .*$/', '', $sqlforgrandtotal). '';
			$resql = $db->query($sqlforgrandtotal);
			if ($resql) {
				$sumsarray = $db->fetch_array($resql);
			} else {
				//dol_print_error($db); // as we're not sure it's ok for ALL lists, we don't print sq errors, they'll be in logs
			}
			if (is_array($sumsarray) && count($sumsarray) > 0) {
				print '<tr class="liste_grandtotal">';
				if (!empty($totalarray['first_column_empty'])) {
					print '<td></td>';
				}
				$i = 0;
				while ($i < $totalarray['nbfield']) {
					$i++;
					if (!empty($totalarray['pos'][$i])) {
						$fieldname = preg_replace('/[^a-z0-9]/', '', $totalarray['pos'][$i]);
						printTotalValCell($totalarray['type'][$i], $sumsarray[$fieldname]);
					} else {
						if ($i == 1) {
							print '<td>';
							if (is_object($form)) {
								print $form->textwithpicto($langs->trans("GrandTotal"), $langs->transnoentitiesnoconv("TotalforAllPages"));
							} else {
								print $langs->trans("GrandTotal");
							}
							print '</td>';
						} else {
							print '<td></td>';
						}
					}
				}
				print '</tr>';
			}
		}
	}
	//print '</tfoot>';
}
