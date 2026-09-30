ALTER TABLE `llx_certificates`
  ADD PRIMARY KEY (`rowid`),
  ADD KEY `rowid` (`rowid`),
  ADD KEY `idx_certifications_user_ben` (`fk_user_ben`),
  ADD KEY `idx_certifications_user_appro` (`fk_user_appro`);

ALTER TABLE `llx_certificates`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;