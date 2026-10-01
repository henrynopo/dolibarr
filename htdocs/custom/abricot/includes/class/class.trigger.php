<?php
/* Copyright (C) 2025 ATM Consulting
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

class TTrigger
{

	function __construct()
	{
		$this->TTrigger=array();
	}
	function execute(&$ATMdb, &$object, $className, $state)
	{
	}
	function run(&$ATMdb, &$object, $className, $state)
	{
		/* Execute les triggers */
		//print "Exécution du trigger ($className, $state)<br>";
		if (empty($this->TTrigger)) { $this->loadTrigger($ATMdb); }

		foreach ($this->TTrigger as $trigger) {
			if (is_file($triger['path'])) {
				require_once $triger['path'];

				if (class_exists($trigger['objectName'])) {
					$t = new $trigger['objectName'];

					if (method_exists($t, 'execute')) {
						$t->execute($ATMdb, $object, $className, $state);
					}
				}
			}
		}
	}

	function register(&$ATMdb, $path, $objectName)
	{
		/* Enregistre un nouveau trigger avec le chemin à charger et la method à appeler */

		//TODO add db

		$this->TTrigger[]=array('path'=>$path, 'objectName'=>$objectName);
	}
	function loadTrigger(&$ATMdb)
	{
		/* Charge la liste des triggers à exécuter */
		$this->TTrigger=array();


		return $this->TTrigger;
	}
}
