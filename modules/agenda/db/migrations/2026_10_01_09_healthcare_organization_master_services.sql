-- CORECLOSE01: organization service authority; no clinical document or result authority.
CREATE TABLE healthcare_organization_master_services (
  master_service_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  master_service_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  group_id VARCHAR(64) NOT NULL,
  study_type_id BIGINT UNSIGNED NOT NULL,
  operational_state ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  commercial_unit VARCHAR(32) NOT NULL DEFAULT 'STUDY',
  internal_cost DECIMAL(12,2) NULL,
  default_public_price DECIMAL(12,2) NULL,
  public_visibility TINYINT(1) NOT NULL DEFAULT 0,
  default_preparation_instructions VARCHAR(2000) NULL,
  default_requires_appointment TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (master_service_id),
  UNIQUE KEY uq_master_service_uuid (master_service_uuid),
  UNIQUE KEY uq_master_group_study (group_id,study_type_id),
  UNIQUE KEY uq_master_scope_reference (master_service_id,group_id,study_type_id),
  CONSTRAINT fk_master_group FOREIGN KEY (group_id) REFERENCES medical_groups(group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_master_study FOREIGN KEY (study_type_id) REFERENCES clinical_study_types(study_type_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT ck_master_cost CHECK (internal_cost IS NULL OR internal_cost>=0),
  CONSTRAINT ck_master_price CHECK (default_public_price IS NULL OR default_public_price>=0),
  CONSTRAINT ck_master_visibility CHECK (public_visibility IN (0,1)),
  CONSTRAINT ck_master_appointment CHECK (default_requires_appointment IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO healthcare_organization_master_services (master_service_uuid,group_id,study_type_id)
SELECT UUID(),l.group_id,o.study_type_id
FROM healthcare_organization_location_study_offerings o
JOIN healthcare_organization_locations l ON l.location_id=o.location_id
GROUP BY l.group_id,o.study_type_id;

ALTER TABLE healthcare_organization_locations
  ADD UNIQUE KEY uq_healthcare_location_id_group (location_id,group_id);

ALTER TABLE healthcare_organization_location_study_offerings
  ADD COLUMN group_id VARCHAR(64) NULL AFTER location_id,
  ADD COLUMN master_service_id BIGINT UNSIGNED NULL AFTER study_type_id,
  ADD COLUMN requires_appointment_override TINYINT(1) NULL AFTER requires_appointment,
  ADD COLUMN preparation_instructions_override VARCHAR(2000) NULL AFTER preparation_instructions,
  ADD COLUMN preparation_override_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER preparation_instructions_override;

UPDATE healthcare_organization_location_study_offerings o
JOIN healthcare_organization_locations l ON l.location_id=o.location_id
JOIN healthcare_organization_master_services m ON m.group_id=l.group_id AND m.study_type_id=o.study_type_id
SET o.group_id=l.group_id,o.master_service_id=m.master_service_id,
    o.requires_appointment_override=o.requires_appointment,
    o.preparation_instructions_override=o.preparation_instructions,
    o.preparation_override_enabled=1;

ALTER TABLE healthcare_organization_location_study_offerings
  MODIFY group_id VARCHAR(64) NOT NULL,
  MODIFY master_service_id BIGINT UNSIGNED NOT NULL,
  ADD KEY idx_offering_master_scope (master_service_id,group_id,study_type_id),
  ADD KEY idx_offering_location_scope (location_id,group_id),
  ADD CONSTRAINT fk_offering_location_scope FOREIGN KEY (location_id,group_id)
    REFERENCES healthcare_organization_locations(location_id,group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_offering_master_scope FOREIGN KEY (master_service_id,group_id,study_type_id)
    REFERENCES healthcare_organization_master_services(master_service_id,group_id,study_type_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT ck_offering_appointment_override CHECK (requires_appointment_override IS NULL OR requires_appointment_override IN (0,1)),
  ADD CONSTRAINT ck_offering_preparation_override CHECK (preparation_override_enabled IN (0,1));

DELIMITER $$
CREATE TRIGGER trg_master_service_identity_immutable BEFORE UPDATE ON healthcare_organization_master_services FOR EACH ROW
BEGIN
  IF NOT (NEW.master_service_id <=> OLD.master_service_id)
    OR NOT (NEW.master_service_uuid <=> OLD.master_service_uuid)
    OR NOT (NEW.group_id <=> OLD.group_id)
    OR NOT (NEW.study_type_id <=> OLD.study_type_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MASTER_SERVICE_IDENTITY_IMMUTABLE';
  END IF;
END$$
DELIMITER ;
