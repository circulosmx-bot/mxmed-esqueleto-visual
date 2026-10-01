-- PROV03C-A: provider eligibility is independent of organization review and subscriptions.
CREATE TABLE IF NOT EXISTS healthcare_organization_provider_status (
  group_id VARCHAR(64) NOT NULL,
  operational_state ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'INACTIVE',
  verification_state ENUM('UNVERIFIED','VERIFIED','REJECTED') NOT NULL DEFAULT 'UNVERIFIED',
  verification_actor_user_id VARCHAR(64) NULL,
  verification_at DATETIME NULL,
  verification_note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id),
  KEY idx_healthcare_provider_status_eligibility (operational_state,verification_state),
  CONSTRAINT fk_healthcare_provider_status_group FOREIGN KEY (group_id)
    REFERENCES medical_groups(group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT ck_healthcare_provider_verification CHECK (
    (verification_state='UNVERIFIED' AND verification_actor_user_id IS NULL AND verification_at IS NULL) OR
    (verification_state<>'UNVERIFIED' AND verification_actor_user_id IS NOT NULL AND verification_at IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing ON_SITE rows remain ON_SITE. HOME_SERVICE and MOBILE are representable
-- contracts; no search, travel, booking, or remote diagnostic behavior is enabled.
ALTER TABLE healthcare_organization_location_study_offerings
  MODIFY COLUMN service_mode ENUM('ON_SITE','HOME_SERVICE','MOBILE') NOT NULL DEFAULT 'ON_SITE';

-- V1 geography is Mexican postal codes. State/municipality text and public SEO
-- slugs are not stable canonical region identities in the current source.
CREATE TABLE IF NOT EXISTS healthcare_organization_location_study_service_areas (
  service_area_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  offering_id BIGINT UNSIGNED NOT NULL,
  scope_type ENUM('POSTAL_CODE') NOT NULL,
  region_key CHAR(11) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  operational_state ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  verification_state ENUM('UNVERIFIED','VERIFIED','REJECTED') NOT NULL DEFAULT 'UNVERIFIED',
  verification_actor_user_id VARCHAR(64) NULL,
  verification_at DATETIME NULL,
  verification_note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (service_area_id),
  UNIQUE KEY uq_healthcare_offering_region (offering_id,scope_type,region_key),
  KEY idx_healthcare_service_area_region_state (scope_type,region_key,operational_state,verification_state,offering_id),
  CONSTRAINT fk_healthcare_service_area_offering FOREIGN KEY (offering_id)
    REFERENCES healthcare_organization_location_study_offerings(offering_id)
    ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT ck_healthcare_service_area_region CHECK (
    scope_type='POSTAL_CODE' AND LEFT(region_key,6)='MX|CP|'
    AND RIGHT(region_key,5) REGEXP '^[0-9]{5}$'
  ),
  CONSTRAINT ck_healthcare_service_area_verification CHECK (
    (verification_state='UNVERIFIED' AND verification_actor_user_id IS NULL AND verification_at IS NULL) OR
    (verification_state<>'UNVERIFIED' AND verification_actor_user_id IS NOT NULL AND verification_at IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
