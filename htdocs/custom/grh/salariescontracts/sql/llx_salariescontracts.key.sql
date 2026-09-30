-- Salaries contracts info
-- Copyright (C) 2016 Yassine Belkaid  <y.belkaid@nextconcept.ma>
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

ALTER TABLE `llx_salariescontracts`
  ADD PRIMARY KEY (`rowid`),
  ADD KEY `idx_salariescontracts_fk_user` (`fk_user`),
  ADD KEY `idx_salariescontracts_fk_user_create` (`fk_user_create`),
  ADD KEY `idx_salariescontracts_date_create` (`date_create`),
  ADD KEY `idx_salariescontracts_start_date` (`start_date`),
  ADD KEY `idx_salariescontracts_end_date` (`end_date`);

ALTER TABLE `llx_salariescontracts`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;