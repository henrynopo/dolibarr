CREATE TABLE `llx_certificates` (
  `rowid` int(11) NOT NULL,
  `fk_user_appro` int(11) NOT NULL,
  `fk_user_ben` int(11) NOT NULL,
  `cert_type` int(2) NOT NULL DEFAULT '1' COMMENT '1=salaire,2=travail,3=stage',
  `entity` int(8) DEFAULT NULL,
  `status` int(2) DEFAULT '0',
  `date_appro` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;