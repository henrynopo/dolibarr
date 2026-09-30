<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Trait: shipment list — bidirectional shipping <-> sales order join.
 */
trait ActionsSlycustomListShipmentListTrait
{
	/**
	 * @param array $parameters Hook parameters ('order_join' => &$orderJoin)
	 * @return int
	 */
	protected function slyListShipment_applyOrderJoin($parameters)
	{
		if (!isset($parameters['order_join']) || !is_string($parameters['order_join'])) {
			return 0;
		}
		$p = MAIN_DB_PREFIX;
		$parameters['order_join'] = $this->buildBidirectionalElementJoin('eecommande', 'ship_id', 'cmd_id', 'shipping', 'commande', 'e');
		$parameters['order_join'] .= " LEFT JOIN ".$p."commande as c ON (c.rowid = eecommande.cmd_id)";
		return 0;
	}
}
