-- Salaries contracts info
-- Copyright (C) 2015 Yassine Belkaid  <y.belkaid@nextconcept.ma>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see <http://www.gnu.org/licenses/>.

CREATE TABLE `llx_salariescontracts` (
  `rowid` int(11) NOT NULL,
  `fk_user` int(11) NOT NULL,
  `fk_user_create` int(11) DEFAULT NULL,
  `type` int(8) NOT NULL,
  `date_create` datetime NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `salarie_sig_date` date DEFAULT NULL,
  `direction_sig_date` date DEFAULT NULL,
  `dpae_date` date DEFAULT NULL,
  `medical_visit_date` date DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;