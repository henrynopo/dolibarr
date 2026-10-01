--
--
-- To add a column:         ALTER TABLE llx_table ADD COLUMN newcol varchar(60) NOT NULL DEFAULT '0' AFTER existingcol;
-- To rename a column:      ALTER TABLE llx_table CHANGE COLUMN oldname newname varchar(60);
-- To change type of field: ALTER TABLE llx_table MODIFY name varchar(60);
--

ALTER TABLE llx_product_perentity ADD COLUMN tva_tx double(6,3) DEFAULT NULL;

CREATE TABLE llx_entity_element_sharing
(
  rowid			integer AUTO_INCREMENT PRIMARY KEY,
  entity		integer	DEFAULT 1 NOT NULL,
  element		varchar(64) NOT NULL,
  fk_element	integer NOT NULL
)ENGINE=innodb;

ALTER TABLE llx_entity_element_sharing ADD UNIQUE INDEX idx_entity_element_sharing_id (entity, fk_element, element);
ALTER TABLE llx_entity_element_sharing ADD CONSTRAINT fk_entity_element_sharing_fk_entity FOREIGN KEY (entity) REFERENCES llx_entity (rowid);