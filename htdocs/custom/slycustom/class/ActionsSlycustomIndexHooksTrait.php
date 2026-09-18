<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: SLY Custom index hooks (multicurrency boxes on lists/index).
 *
 * Extracted from actions_slycustom.class.php to reduce file size.
 */
trait ActionsSlycustomIndexHooksTrait
{
	/**
	 * Orders index: add SELECT columns for multicurrency (core calls this and appends to SQL).
	 */
	public function ordersIndexSelectSuffix($parameters, $object, $action, $hookmanager)
	{
		if (isModEnabled('multicurrency')) {
			$this->resprints = ", c.multicurrency_total_ht, c.multicurrency_code";
		}
		return 0;
	}

	/**
	 * Orders index: format amount for one row (multicurrency or local).
	 *
	 * @param array         $parameters Hook parameters ('obj' => fetched row)
	 */
	public function ordersIndexRowAmount($parameters, $object, $action, $hookmanager)
	{
		global $conf, $langs;
		$obj = isset($parameters['obj']) ? $parameters['obj'] : null;
		if (!$obj) {
			return 0;
		}
		if (isModEnabled('multicurrency') && !empty($obj->multicurrency_code)) {
			$currency = $obj->multicurrency_code;
			$amount = $obj->multicurrency_total_ht;
		} else {
			$currency = !empty($conf->currency) ? $conf->currency : '';
			$amount = isset($obj->total_ht) ? $obj->total_ht : 0;
		}
		$this->resprints = '<span class="amount">'.price($amount, 1, $langs, 0, -1, -1, $currency).'</span>';
		return 0;
	}

	/**
	 * Invoice index (compta): add SELECT columns for multicurrency.
	 */
	public function invoiceIndexSelectSuffix($parameters, $object, $action, $hookmanager)
	{
		$type = isset($parameters['type']) ? $parameters['type'] : '';
		if (!isModEnabled('multicurrency')) {
			return 0;
		}
		if ($type === 'customer') {
			$this->resprints = ", f.multicurrency_code, f.multicurrency_total_ht, f.multicurrency_total_ttc";
		} elseif ($type === 'supplier') {
			$this->resprints = ", ff.multicurrency_code, ff.multicurrency_total_ht, ff.multicurrency_total_ttc";
		} elseif ($type === 'orderToBill' && isModEnabled('order')) {
			$this->resprints = ", c.multicurrency_code, c.multicurrency_total_ht, c.multicurrency_total_ttc";
		}
		return 0;
	}

	/**
	 * Invoice index (compta): return amount_ht, amount_ttc, currency for one row.
	 *
	 * @param array         $parameters Hook parameters ('obj' => row, 'type' => 'customer'|'supplier'|'orderToBill')
	 */
	public function invoiceIndexAmountDisplay($parameters, $object, $action, $hookmanager)
	{
		global $conf;
		$obj = isset($parameters['obj']) ? $parameters['obj'] : null;
		$type = isset($parameters['type']) ? $parameters['type'] : '';
		if (!$obj || !$type) {
			return 0;
		}
		if (!isModEnabled('multicurrency') || empty($obj->multicurrency_code)) {
			return 0;
		}
		$this->results = array(
			'amount_ht' => $obj->multicurrency_total_ht,
			'amount_ttc' => $obj->multicurrency_total_ttc,
			'currency' => $obj->multicurrency_code,
		);
		return 0;
	}
}

