-- PROV03B: operational directory authorities only. No patient or encounter data.
CREATE TABLE IF NOT EXISTS healthcare_organization_locations (
  location_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  location_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  group_id VARCHAR(64) NOT NULL,
  branch_name VARCHAR(190) NOT NULL,
  operational_state ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  verification_state ENUM('UNVERIFIED','VERIFIED','REJECTED') NOT NULL DEFAULT 'UNVERIFIED',
  verification_actor_user_id VARCHAR(64) NULL,
  verification_at DATETIME NULL,
  street VARCHAR(190) NULL,
  exterior_number VARCHAR(32) NULL,
  interior_number VARCHAR(32) NULL,
  postal_code VARCHAR(5) NULL,
  colonia VARCHAR(190) NULL,
  municipality VARCHAR(190) NULL,
  state_name VARCHAR(190) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  coordinate_source VARCHAR(32) NULL,
  phone VARCHAR(32) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (location_id),
  UNIQUE KEY uq_healthcare_location_uuid (location_uuid),
  UNIQUE KEY uq_healthcare_location_group_id (group_id,location_id),
  KEY idx_healthcare_location_scope_state (group_id,operational_state,verification_state),
  CONSTRAINT fk_healthcare_location_group FOREIGN KEY (group_id)
    REFERENCES medical_groups(group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT ck_healthcare_location_name CHECK (CHAR_LENGTH(TRIM(branch_name))>0),
  CONSTRAINT ck_healthcare_location_postal CHECK (postal_code IS NULL OR postal_code REGEXP '^[0-9]{5}$'),
  CONSTRAINT ck_healthcare_location_coordinates CHECK (
    (latitude IS NULL AND longitude IS NULL AND coordinate_source IS NULL) OR
    (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180 AND coordinate_source IS NOT NULL)
  ),
  CONSTRAINT ck_healthcare_location_verification CHECK (
    (verification_state='UNVERIFIED' AND verification_actor_user_id IS NULL AND verification_at IS NULL) OR
    (verification_state<>'UNVERIFIED' AND verification_actor_user_id IS NOT NULL AND verification_at IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS healthcare_organization_location_study_offerings (
  offering_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  location_id BIGINT UNSIGNED NOT NULL,
  study_type_id BIGINT UNSIGNED NOT NULL,
  operational_state ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  verification_state ENUM('UNVERIFIED','VERIFIED','REJECTED') NOT NULL DEFAULT 'UNVERIFIED',
  verification_actor_user_id VARCHAR(64) NULL,
  verification_at DATETIME NULL,
  service_mode ENUM('ON_SITE') NOT NULL DEFAULT 'ON_SITE',
  requires_appointment TINYINT(1) NOT NULL DEFAULT 0,
  preparation_instructions VARCHAR(2000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (offering_id),
  UNIQUE KEY uq_healthcare_location_study (location_id,study_type_id),
  KEY idx_healthcare_offering_match (study_type_id,operational_state,verification_state,location_id),
  CONSTRAINT fk_healthcare_offering_location FOREIGN KEY (location_id)
    REFERENCES healthcare_organization_locations(location_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_healthcare_offering_study FOREIGN KEY (study_type_id)
    REFERENCES clinical_study_types(study_type_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT ck_healthcare_offering_appointment CHECK (requires_appointment IN (0,1)),
  CONSTRAINT ck_healthcare_offering_verification CHECK (
    (verification_state='UNVERIFIED' AND verification_actor_user_id IS NULL AND verification_at IS NULL) OR
    (verification_state<>'UNVERIFIED' AND verification_actor_user_id IS NOT NULL AND verification_at IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
