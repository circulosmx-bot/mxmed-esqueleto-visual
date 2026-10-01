-- PROV04E: private provider-management retry records and location assertion audit.
CREATE TABLE IF NOT EXISTS healthcare_organization_management_requests (
  request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id VARCHAR(64) NOT NULL,
  group_id VARCHAR(64) NOT NULL,
  operation_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  submission_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  response_json JSON NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (request_id),
  UNIQUE KEY uq_provider_management_retry (account_id,group_id,operation_code,submission_key),
  CONSTRAINT fk_provider_management_request_account FOREIGN KEY (account_id)
    REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_management_request_group FOREIGN KEY (group_id)
    REFERENCES medical_groups(group_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS healthcare_organization_location_edit_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id VARCHAR(64) NOT NULL,
  location_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_account_id VARCHAR(64) NOT NULL,
  changed_fields_json JSON NOT NULL,
  material_change TINYINT(1) NOT NULL,
  previous_verification_state ENUM('UNVERIFIED','VERIFIED','REJECTED') NOT NULL,
  new_verification_state ENUM('UNVERIFIED','VERIFIED','REJECTED') NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (event_id),
  KEY idx_provider_location_edit (group_id,location_uuid,event_id),
  CONSTRAINT fk_provider_location_edit_group FOREIGN KEY (group_id)
    REFERENCES medical_groups(group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_location_edit_location FOREIGN KEY (location_uuid)
    REFERENCES healthcare_organization_locations(location_uuid) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_location_edit_actor FOREIGN KEY (actor_account_id)
    REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT ck_provider_location_edit_material CHECK (material_change IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
