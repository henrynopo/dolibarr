-- Copyright (C) 2025  Odoo Connector
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
-- along with this program.  If not, see https://www.gnu.org/licenses/.

CREATE TABLE IF NOT EXISTS llx_odoo_connector_sync (
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	element_type varchar(32) NOT NULL COMMENT 'facture, facture_fourn, expensereport',
	fk_source_id integer NOT NULL COMMENT 'Dolibarr record ID',
	odoo_model varchar(64) NOT NULL COMMENT 'account.move, etc.',
	odoo_id integer NOT NULL,
	odoo_write_date datetime NULL,
	last_sync datetime NULL,
	UNIQUE KEY uk_odoo_connector_sync (entity, element_type, fk_source_id)
) ENGINE=innodb;
