-- SLY expedition + shipment line extrafields (match Setup → Extra fields)
-- Run on 22.0; if a column already exists (e.g. from manual setup), that ALTER will fail — skip or comment out that line.
-- Table prefix: change llx_ to your MAIN_DB_PREFIX if needed.

-- ========== 1. Expedition (shipment) level — llx_expedition_extrafields ==========
ALTER TABLE llx_expedition_extrafields ADD COLUMN vesselname VARCHAR(30) NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN blno VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN hcno VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN etd DATE NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN eta DATE NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN origin INT NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN requestid INT NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN sailingstatusid INT NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN pol VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN atd DATE NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN pod VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN ata DATE NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN atdetd INT NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN ataeta INT NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN livemapurl VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN updatedtime DATETIME NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN totalnetweight DOUBLE(24,3) NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN totalnocartons INT NULL DEFAULT NULL;
ALTER TABLE llx_expedition_extrafields ADD COLUMN totalgrossweight DOUBLE(24,3) NULL DEFAULT NULL;

-- ========== 2. Shipment line level — llx_expeditiondet_extrafields ==========
ALTER TABLE llx_expeditiondet_extrafields ADD COLUMN grossweight DOUBLE(24,3) NULL DEFAULT NULL;
ALTER TABLE llx_expeditiondet_extrafields ADD COLUMN quantitycarton INT NULL DEFAULT NULL;
