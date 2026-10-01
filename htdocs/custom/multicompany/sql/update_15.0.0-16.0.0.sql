--
--
-- To add a column:         ALTER TABLE llx_table ADD COLUMN newcol varchar(60) NOT NULL DEFAULT '0' AFTER existingcol;
-- To rename a column:      ALTER TABLE llx_table CHANGE COLUMN oldname newname varchar(60);
-- To change type of field: ALTER TABLE llx_table MODIFY name varchar(60);
--

ALTER TABLE llx_product_perentity MODIFY tva_tx double(7,4) DEFAULT NULL;

ALTER TABLE llx_product_perentity ADD COLUMN default_vat_code varchar(10);
ALTER TABLE llx_product_perentity ADD COLUMN recuperableonly integer NOT NULL DEFAULT '0';
ALTER TABLE llx_product_perentity ADD COLUMN localtax1_tx    double(7,4)  DEFAULT 0;
ALTER TABLE llx_product_perentity ADD COLUMN localtax1_type  varchar(10)  NOT NULL DEFAULT '0';
ALTER TABLE llx_product_perentity ADD COLUMN localtax2_tx    double(7,4)  DEFAULT 0;
ALTER TABLE llx_product_perentity ADD COLUMN localtax2_type  varchar(10)  NOT NULL DEFAULT '0';
ALTER TABLE llx_product_perentity ADD COLUMN tosell          tinyint      DEFAULT 1;
ALTER TABLE llx_product_perentity ADD COLUMN tobuy           tinyint      DEFAULT 1;
ALTER TABLE llx_product_perentity ADD COLUMN url             varchar(255);
ALTER TABLE llx_product_perentity ADD COLUMN barcode         varchar(180) DEFAULT NULL;
ALTER TABLE llx_product_perentity ADD COLUMN fk_barcode_type integer      DEFAULT NULL;
