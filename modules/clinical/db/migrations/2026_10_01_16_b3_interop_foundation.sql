-- B3-INTEROP01: digital transmission and provider work are separate from clinical documents.
CREATE TABLE healthcare_study_referrals (
  referral_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  referral_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(128) NOT NULL,
  source_order_document_id BIGINT UNSIGNED NOT NULL,
  source_order_uuid CHAR(36) NOT NULL,
  source_order_version INT NOT NULL,
  group_id VARCHAR(64) NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  state ENUM('SENT','ACCEPTED','DECLINED','CANCELED') NOT NULL DEFAULT 'SENT',
  idempotency_key VARCHAR(128) NOT NULL,
  request_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_by_user_id VARCHAR(128) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_referral_uuid (referral_uuid),
  UNIQUE KEY uq_referral_idempotency (doctor_id,idempotency_key),
  UNIQUE KEY uq_referral_scope (referral_id,group_id,location_id,patient_id),
  KEY idx_referral_source (source_order_document_id),
  CONSTRAINT fk_referral_source FOREIGN KEY (source_order_document_id) REFERENCES clinical_documents(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_referral_group FOREIGN KEY (group_id) REFERENCES medical_groups(group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_referral_location FOREIGN KEY (location_id,group_id) REFERENCES healthcare_organization_locations(location_id,group_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE healthcare_study_referral_items (
  referral_id BIGINT UNSIGNED NOT NULL,
  source_order_item_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  study_type_id BIGINT UNSIGNED NOT NULL,
  study_display_name VARCHAR(255) NOT NULL,
  study_category VARCHAR(32) NOT NULL,
  PRIMARY KEY (referral_id,source_order_item_id),
  UNIQUE KEY uq_referral_item_study (referral_id,source_order_item_id,study_type_id),
  CONSTRAINT fk_referral_item_referral FOREIGN KEY (referral_id) REFERENCES healthcare_study_referrals(referral_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_referral_item_study FOREIGN KEY (study_type_id) REFERENCES clinical_study_types(study_type_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE healthcare_study_referral_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  referral_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('SENT','ACCEPTED','DECLINED','CANCELED') NOT NULL,
  actor_type ENUM('PHYSICIAN','PROVIDER_SYSTEM','INTERNAL') NOT NULL,
  actor_id VARCHAR(128) NOT NULL,
  reason VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_referral_events (referral_id,event_id),
  CONSTRAINT fk_referral_event_referral FOREIGN KEY (referral_id) REFERENCES healthcare_study_referrals(referral_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE healthcare_provider_service_orders (
  service_order_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  service_order_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  group_id VARCHAR(64) NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  patient_id VARCHAR(128) NOT NULL,
  origin ENUM('PHYSICIAN_REFERRAL','PATIENT_REQUEST','PROVIDER_FRONT_DESK') NOT NULL,
  referral_id BIGINT UNSIGNED NULL,
  state ENUM('PENDING_COLLECTION','IN_PROCESS','READY','DELIVERED','CANCELED') NOT NULL DEFAULT 'PENDING_COLLECTION',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_service_order_uuid (service_order_uuid),
  UNIQUE KEY uq_service_order_referral (referral_id),
  UNIQUE KEY uq_service_order_scope (service_order_id,group_id,location_id,patient_id),
  UNIQUE KEY uq_service_order_referral_scope (service_order_id,referral_id),
  CONSTRAINT fk_service_order_referral FOREIGN KEY (referral_id) REFERENCES healthcare_study_referrals(referral_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_service_order_location FOREIGN KEY (location_id,group_id) REFERENCES healthcare_organization_locations(location_id,group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT ck_service_order_origin CHECK ((origin='PHYSICIAN_REFERRAL' AND referral_id IS NOT NULL) OR (origin<>'PHYSICIAN_REFERRAL' AND referral_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE healthcare_provider_service_order_items (
  service_order_id BIGINT UNSIGNED NOT NULL,
  referral_id BIGINT UNSIGNED NOT NULL,
  source_order_item_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  study_type_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (service_order_id,source_order_item_id),
  CONSTRAINT fk_service_item_order FOREIGN KEY (service_order_id,referral_id) REFERENCES healthcare_provider_service_orders(service_order_id,referral_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_service_item_referral_item FOREIGN KEY (referral_id,source_order_item_id,study_type_id) REFERENCES healthcare_study_referral_items(referral_id,source_order_item_id,study_type_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_service_item_study FOREIGN KEY (study_type_id) REFERENCES clinical_study_types(study_type_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE healthcare_provider_result_sources (
  source_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  provider_release_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  release_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  clinical_result_document_id BIGINT UNSIGNED NOT NULL,
  service_order_id BIGINT UNSIGNED NOT NULL,
  referral_id BIGINT UNSIGNED NOT NULL,
  group_id VARCHAR(64) NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  patient_id VARCHAR(128) NOT NULL,
  released_at DATETIME NOT NULL,
  released_by_professional_id VARCHAR(128) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_provider_release (provider_release_uuid),
  UNIQUE KEY uq_provider_result_document (clinical_result_document_id),
  CONSTRAINT fk_provider_result_document FOREIGN KEY (clinical_result_document_id) REFERENCES clinical_documents(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_result_service FOREIGN KEY (service_order_id,group_id,location_id,patient_id) REFERENCES healthcare_provider_service_orders(service_order_id,group_id,location_id,patient_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_result_referral FOREIGN KEY (referral_id,group_id,location_id,patient_id) REFERENCES healthcare_study_referrals(referral_id,group_id,location_id,patient_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER trg_referral_source_immutable BEFORE UPDATE ON healthcare_study_referrals FOR EACH ROW
BEGIN
  IF NOT (NEW.referral_uuid <=> OLD.referral_uuid) OR NOT (NEW.doctor_id <=> OLD.doctor_id)
    OR NOT (NEW.patient_id <=> OLD.patient_id) OR NOT (NEW.source_order_document_id <=> OLD.source_order_document_id)
    OR NOT (NEW.source_order_uuid <=> OLD.source_order_uuid) OR NOT (NEW.source_order_version <=> OLD.source_order_version)
    OR NOT (NEW.group_id <=> OLD.group_id) OR NOT (NEW.location_id <=> OLD.location_id)
    OR NOT (NEW.idempotency_key <=> OLD.idempotency_key) OR NOT (NEW.request_sha256 <=> OLD.request_sha256)
    OR NOT (NEW.created_by_user_id <=> OLD.created_by_user_id) OR NOT (NEW.sent_at <=> OLD.sent_at)
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='REFERRAL_SOURCE_IMMUTABLE'; END IF;
END$$
CREATE TRIGGER trg_referral_items_immutable BEFORE UPDATE ON healthcare_study_referral_items FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='REFERRAL_ITEM_IMMUTABLE'; END$$
CREATE TRIGGER trg_referral_items_no_delete BEFORE DELETE ON healthcare_study_referral_items FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='REFERRAL_ITEM_IMMUTABLE'; END$$
CREATE TRIGGER trg_referral_events_immutable BEFORE UPDATE ON healthcare_study_referral_events FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='REFERRAL_EVENT_APPEND_ONLY'; END$$
CREATE TRIGGER trg_referral_events_no_delete BEFORE DELETE ON healthcare_study_referral_events FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='REFERRAL_EVENT_APPEND_ONLY'; END$$
CREATE TRIGGER trg_service_order_identity_immutable BEFORE UPDATE ON healthcare_provider_service_orders FOR EACH ROW
BEGIN
  IF NOT (NEW.service_order_uuid <=> OLD.service_order_uuid) OR NOT (NEW.group_id <=> OLD.group_id)
    OR NOT (NEW.location_id <=> OLD.location_id) OR NOT (NEW.patient_id <=> OLD.patient_id)
    OR NOT (NEW.origin <=> OLD.origin) OR NOT (NEW.referral_id <=> OLD.referral_id)
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SERVICE_ORDER_IDENTITY_IMMUTABLE'; END IF;
END$$
CREATE TRIGGER trg_service_order_items_immutable BEFORE UPDATE ON healthcare_provider_service_order_items FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SERVICE_ORDER_ITEM_IMMUTABLE'; END$$
CREATE TRIGGER trg_service_order_items_no_delete BEFORE DELETE ON healthcare_provider_service_order_items FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SERVICE_ORDER_ITEM_IMMUTABLE'; END$$
CREATE TRIGGER trg_provider_source_immutable BEFORE UPDATE ON healthcare_provider_result_sources FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='PROVIDER_RESULT_SOURCE_IMMUTABLE'; END$$
CREATE TRIGGER trg_provider_source_no_delete BEFORE DELETE ON healthcare_provider_result_sources FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='PROVIDER_RESULT_SOURCE_IMMUTABLE'; END$$
DELIMITER ;
