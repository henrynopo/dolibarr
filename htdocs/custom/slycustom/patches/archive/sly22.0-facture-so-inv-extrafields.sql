-- SLY SO/PO Invoice Details extrafields for exports (slycustom)
-- Target columns will be created in llx_facture_extrafields and llx_facture_fourn_extrafields.

ALTER TABLE llx_facture_extrafields
	ADD COLUMN datecustpay DATE NULL DEFAULT NULL,
	ADD COLUMN datesuppdocdelivered DATE NULL DEFAULT NULL,
	ADD COLUMN suppcourrier VARCHAR(64) NULL DEFAULT NULL,
	ADD COLUMN couriernumber VARCHAR(64) NULL DEFAULT NULL,
	ADD COLUMN recipient VARCHAR(255) NULL DEFAULT NULL,
	ADD COLUMN datesuppdocreceived DATE NULL DEFAULT NULL,
	ADD COLUMN datetr DATE NULL DEFAULT NULL,
	ADD COLUMN dateslydocdelivered DATE NULL DEFAULT NULL,
	ADD COLUMN slycourrier VARCHAR(64) NULL DEFAULT NULL,
	ADD COLUMN slycourriernumber VARCHAR(64) NULL DEFAULT NULL,
	ADD COLUMN remark TEXT NULL;

ALTER TABLE llx_facture_fourn_extrafields
	ADD COLUMN datecustpay DATE NULL DEFAULT NULL,
	ADD COLUMN datesuppdocdelivered DATE NULL DEFAULT NULL,
	ADD COLUMN suppcourrier VARCHAR(64) NULL DEFAULT NULL,
	ADD COLUMN couriernumber VARCHAR(64) NULL DEFAULT NULL,
	ADD COLUMN recipient VARCHAR(255) NULL DEFAULT NULL,
	ADD COLUMN datesuppdocreceived DATE NULL DEFAULT NULL,
	ADD COLUMN datetr DATE NULL DEFAULT NULL,
	ADD COLUMN dateslydocdelivered DATE NULL DEFAULT NULL,
	ADD COLUMN slycourrier VARCHAR(64) NULL DEFAULT NULL,
	ADD COLUMN slycourriernumber VARCHAR(64) NULL DEFAULT NULL,
	ADD COLUMN remark TEXT NULL;

