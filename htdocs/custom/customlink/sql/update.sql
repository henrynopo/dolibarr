ALTER TABLE llx_element_tag ADD COLUMN entity INTEGER DEFAULT 1 NOT NULL;
ALTER TABLE llx_element_tag ADD COLUMN lang varchar(5) not null;
ALTER TABLE llx_element_tag ADD COLUMN tag varchar(255) not null;
ALTER TABLE llx_element_tag ADD COLUMN element varchar(64) not null;
ALTER TABLE llx_element_tag ADD COLUMN fk_categorie integer not null;