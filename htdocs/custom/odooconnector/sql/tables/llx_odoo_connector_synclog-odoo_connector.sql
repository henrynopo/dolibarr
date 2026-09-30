-- Copyright (C) 2025  Odoo Connector
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

-- One row per document whose last sync attempt failed (replaced on each retry,
-- removed automatically when a retry succeeds).
CREATE TABLE IF NOT EXISTS llx_odoo_connector_synclog (
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	element_type varchar(32) NOT NULL COMMENT 'facture, facture_fourn, expensereport',
	fk_source_id integer NOT NULL COMMENT 'Dolibarr record ID',
	ref varchar(128) NULL COMMENT 'Dolibarr document reference',
	odoo_id integer NULL COMMENT 'Odoo move id when the failure was on an existing move',
	action varchar(16) NOT NULL COMMENT 'create, update, delete, partner, currency, journal',
	error text NULL,
	date_try datetime NOT NULL,
	UNIQUE KEY uk_odoo_connector_synclog (entity, element_type, fk_source_id)
) ENGINE=innodb;
