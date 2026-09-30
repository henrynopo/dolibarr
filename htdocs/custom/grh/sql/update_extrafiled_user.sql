ALTER TABLE `llx_payment_salary` ADD `payslip` SMALLINT(4) NULL DEFAULT NULL;
ALTER TABLE `llx_user_extrafields` ADD `nx_cnss` varchar(10) NULL DEFAULT NULL;
ALTER TABLE `llx_user_extrafields` ADD `nx_num_holiday` int(4) NULL DEFAULT NULL;
ALTER TABLE `llx_user_extrafields` ADD `nx_cin` varchar(10) NULL DEFAULT NULL;
ALTER TABLE `llx_user_extrafields` ADD `nx_is_declared` varchar(400) NULL DEFAULT NULL;
ALTER TABLE `llx_user_extrafields` ADD `nx_salaire_base` int(11) NULL DEFAULT NULL;
ALTER TABLE `llx_user_extrafields` ADD `nx_is_stagiaire` varchar(100) NULL DEFAULT NULL;


-- ALTER TABLE `llx_user_extrafields` ADD `nx_date_embauche` date NULL DEFAULT NULL;
-- ALTER TABLE `llx_user_extrafields` ADD `nx_sit_family` varchar(400) NULL DEFAULT NULL;
-- ALTER TABLE `llx_user_extrafields` ADD `nx_sit_assure` varchar(400) NULL DEFAULT NULL;
-- ALTER TABLE `llx_user_extrafields` ADD `nx_etablissement` varchar(400) NULL DEFAULT NULL;
-- ALTER TABLE `llx_user_extrafields` ADD `nx_etab_opt` varchar(400) NULL DEFAULT NULL;
-- ALTER TABLE `llx_user_extrafields` ADD `nx_nbr_enfants` int(4) NULL DEFAULT NULL;
