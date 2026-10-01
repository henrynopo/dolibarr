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

class TObserver
{



}

class TConstraint
{

	static function check(&$value, &$TConstraint, $autoset = false)
	{

		if (empty($TConstraint)) return true;
		else {
			if (isset($TConstraint['min'])) {
				if ($value<$TConstraint['min'])return TConstraint::setCheck($value, $TConstraint['min'], $autoset);
			}
			if (isset($TConstraint['max'])) {
				if ($value>$TConstraint['max']) return TConstraint::setCheck($value, $TConstraint['max'], $autoset);
			}
			if (isset($TConstraint['not-null'])) {
				if (is_null($value)) return false;
			}
		}

		return true;
	}

	static function setCheck(&$value, $contraint, $set)
	{
		if ($set) {
			$value = $contraint;
			return true;
		} else {
			return false;
		}
	}
}
