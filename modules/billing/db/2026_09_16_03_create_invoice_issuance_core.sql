-- FISC02B. Forward-only; FISC01 and FISC02A must already be active.
CREATE TABLE `billing_issuer_profiles` (
  `issuer_profile_id` CHAR(36) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `alias` VARCHAR(80) NOT NULL,
  `issuer_legal_name` VARCHAR(254) NOT NULL,
  `rfc` VARCHAR(13) NOT NULL,
  `fiscal_regime_code` CHAR(3) NOT NULL,
  `expedition_postal_code` CHAR(5) NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `archived_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `active_default_slot` TINYINT GENERATED ALWAYS AS
    (CASE WHEN `archived_at` IS NULL AND `is_default`=1 THEN 1 ELSE NULL END) STORED,
  PRIMARY KEY (`issuer_profile_id`),
  UNIQUE KEY `uq_issuer_scope` (`issuer_profile_id`,`doctor_id`),
  UNIQUE KEY `uq_issuer_default` (`doctor_id`,`active_default_slot`),
  KEY `idx_issuer_doctor_active` (`doctor_id`,`archived_at`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `billing_csd_credentials` (
  `credential_id` CHAR(36) NOT NULL,
  `issuer_profile_id` CHAR(36) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `certificate_serial` VARCHAR(80) NOT NULL,
  `certificate_rfc` VARCHAR(13) DEFAULT NULL,
  `certificate_sha256` CHAR(64) NOT NULL,
  `valid_from` DATETIME NOT NULL,
  `valid_to` DATETIME NOT NULL,
  `certificate_type` VARCHAR(24) NOT NULL DEFAULT 'UNVERIFIED',
  `certificate_storage_key` VARCHAR(255) NOT NULL,
  `private_key_storage_key` VARCHAR(255) NOT NULL,
  `password_storage_key` VARCHAR(255) NOT NULL,
  `archived_at` DATETIME(6) DEFAULT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`credential_id`),
  UNIQUE KEY `uq_csd_issuer_certificate` (`issuer_profile_id`,`certificate_sha256`),
  KEY `idx_csd_active` (`doctor_id`,`issuer_profile_id`,`archived_at`,`valid_to`),
  CONSTRAINT `fk_csd_issuer_scope` FOREIGN KEY (`issuer_profile_id`,`doctor_id`)
    REFERENCES `billing_issuer_profiles` (`issuer_profile_id`,`doctor_id`),
  CONSTRAINT `ck_csd_certificate_type` CHECK (`certificate_type` IN ('UNVERIFIED','CSD_VERIFIED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `billing_invoice_drafts` (
  `draft_id` CHAR(36) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `patient_id` VARCHAR(64) NOT NULL,
  `issuer_profile_id` CHAR(36) NOT NULL,
  `billing_profile_id` CHAR(36) NOT NULL,
  `cfdi_version` VARCHAR(8) NOT NULL DEFAULT '4.0',
  `currency_code` CHAR(3) NOT NULL,
  `cfdi_use_code` VARCHAR(4) NOT NULL,
  `payment_method_code` CHAR(3) NOT NULL,
  `payment_form_code` CHAR(2) NOT NULL,
  `series` VARCHAR(25) DEFAULT NULL,
  `internal_folio` VARCHAR(40) DEFAULT NULL,
  `state` VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
  `revision` INT UNSIGNED NOT NULL DEFAULT 1,
  `subtotal` DECIMAL(18,6) NOT NULL DEFAULT 0,
  `discount` DECIMAL(18,6) NOT NULL DEFAULT 0,
  `tax_total` DECIMAL(18,6) NOT NULL DEFAULT 0,
  `total` DECIMAL(18,6) NOT NULL DEFAULT 0,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`draft_id`),
  UNIQUE KEY `uq_draft_scope` (`draft_id`,`doctor_id`),
  KEY `idx_draft_doctor` (`doctor_id`,`state`,`updated_at`),
  KEY `idx_draft_patient` (`doctor_id`,`patient_id`,`updated_at`),
  KEY `idx_draft_receiver_scope` (`billing_profile_id`,`doctor_id`,`patient_id`),
  KEY `idx_draft_issuer_scope` (`issuer_profile_id`,`doctor_id`),
  CONSTRAINT `fk_draft_patient_scope` FOREIGN KEY (`doctor_id`,`patient_id`)
    REFERENCES `patients_doctor_links` (`doctor_id`,`patient_id`),
  CONSTRAINT `fk_draft_receiver_scope` FOREIGN KEY (`billing_profile_id`,`doctor_id`,`patient_id`)
    REFERENCES `billing_patient_profiles` (`billing_profile_id`,`doctor_id`,`patient_id`),
  CONSTRAINT `fk_draft_issuer_scope` FOREIGN KEY (`issuer_profile_id`,`doctor_id`)
    REFERENCES `billing_issuer_profiles` (`issuer_profile_id`,`doctor_id`),
  CONSTRAINT `ck_draft_state` CHECK (`state` IN ('DRAFT','VALIDATION_FAILED','READY','CERTIFICATION_PENDING','CERTIFIED','CERTIFICATION_FAILED','RECONCILIATION_REQUIRED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `billing_invoice_draft_items` (
  `item_id` CHAR(36) NOT NULL,
  `draft_id` CHAR(36) NOT NULL,
  `line_no` SMALLINT UNSIGNED NOT NULL,
  `product_service_code` CHAR(8) NOT NULL,
  `description` VARCHAR(1000) NOT NULL,
  `quantity` DECIMAL(18,6) NOT NULL,
  `unit_code` VARCHAR(3) NOT NULL,
  `unit_value` DECIMAL(18,6) NOT NULL,
  `discount` DECIMAL(18,6) NOT NULL DEFAULT 0,
  `line_subtotal` DECIMAL(18,6) NOT NULL,
  `line_total` DECIMAL(18,6) NOT NULL,
  `tax_object_code` CHAR(2) NOT NULL,
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `uq_draft_item_line` (`draft_id`,`line_no`),
  CONSTRAINT `fk_draft_item` FOREIGN KEY (`draft_id`) REFERENCES `billing_invoice_drafts` (`draft_id`),
  CONSTRAINT `ck_draft_item_positive` CHECK (`quantity`>0 AND `unit_value`>=0 AND `discount`>=0 AND `line_subtotal`>=0 AND `line_total`>=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `billing_invoice_draft_item_taxes` (
  `item_tax_id` CHAR(36) NOT NULL,
  `item_id` CHAR(36) NOT NULL,
  `direction` VARCHAR(12) NOT NULL,
  `tax_code` CHAR(3) NOT NULL,
  `factor_code` VARCHAR(8) NOT NULL,
  `rate` DECIMAL(12,6) DEFAULT NULL,
  `tax_base` DECIMAL(18,6) NOT NULL,
  `amount` DECIMAL(18,6) DEFAULT NULL,
  PRIMARY KEY (`item_tax_id`),
  KEY `idx_item_taxes` (`item_id`,`direction`,`tax_code`),
  CONSTRAINT `fk_draft_item_tax` FOREIGN KEY (`item_id`) REFERENCES `billing_invoice_draft_items` (`item_id`),
  CONSTRAINT `ck_item_tax_direction` CHECK (`direction` IN ('TRANSFER','WITHHOLD')),
  CONSTRAINT `ck_item_tax_factor` CHECK (`factor_code` IN ('Tasa','Cuota','Exento')),
  CONSTRAINT `ck_item_tax_rate` CHECK ((`factor_code`='Exento' AND `rate` IS NULL AND `amount` IS NULL)
    OR (`factor_code` IN ('Tasa','Cuota') AND `rate` IS NOT NULL AND `amount` IS NOT NULL AND `rate`>=0 AND `amount`>=0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `billing_invoice_certification_attempts` (
  `attempt_id` CHAR(36) NOT NULL,
  `draft_id` CHAR(36) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `draft_revision` INT UNSIGNED NOT NULL,
  `idempotency_key` CHAR(64) NOT NULL,
  `provider_code` VARCHAR(64) NOT NULL,
  `provider_request_id` VARCHAR(128) DEFAULT NULL,
  `request_sha256` CHAR(64) NOT NULL,
  `state` VARCHAR(32) NOT NULL,
  `invoice_id` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`attempt_id`),
  UNIQUE KEY `uq_attempt_draft_revision` (`draft_id`,`draft_revision`),
  UNIQUE KEY `uq_attempt_idempotency` (`idempotency_key`),
  KEY `idx_attempt_doctor_state` (`doctor_id`,`state`,`created_at`),
  CONSTRAINT `fk_attempt_draft_scope` FOREIGN KEY (`draft_id`,`doctor_id`)
    REFERENCES `billing_invoice_drafts` (`draft_id`,`doctor_id`),
  CONSTRAINT `fk_attempt_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `billing_invoices` (`invoice_id`),
  CONSTRAINT `ck_attempt_state` CHECK (`state` IN ('CERTIFICATION_PENDING','CERTIFIED','CERTIFICATION_FAILED','RECONCILIATION_REQUIRED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `billing_invoices`
  ADD COLUMN `issuer_profile_id` CHAR(36) DEFAULT NULL,
  ADD COLUMN `issuer_legal_name_snapshot` VARCHAR(254) DEFAULT NULL,
  ADD COLUMN `issuer_rfc_snapshot` VARCHAR(13) DEFAULT NULL,
  ADD COLUMN `issuer_regime_code_snapshot` CHAR(3) DEFAULT NULL,
  ADD COLUMN `expedition_postal_code_snapshot` CHAR(5) DEFAULT NULL,
  ADD COLUMN `payment_method_code_snapshot` CHAR(3) DEFAULT NULL,
  ADD COLUMN `payment_form_code_snapshot` CHAR(2) DEFAULT NULL,
  ADD COLUMN `cfdi_use_code_issued_snapshot` VARCHAR(4) DEFAULT NULL,
  ADD COLUMN `certified_at` DATETIME DEFAULT NULL,
  ADD COLUMN `sat_certificate_serial` VARCHAR(80) DEFAULT NULL,
  ADD COLUMN `source_draft_id` CHAR(36) DEFAULT NULL,
  ADD UNIQUE KEY `uq_invoice_source_draft` (`source_draft_id`),
  ADD KEY `idx_invoice_issuer_scope` (`issuer_profile_id`,`doctor_id`),
  ADD CONSTRAINT `fk_invoice_issuer_scope` FOREIGN KEY (`issuer_profile_id`,`doctor_id`)
    REFERENCES `billing_issuer_profiles` (`issuer_profile_id`,`doctor_id`),
  ADD CONSTRAINT `fk_invoice_source_draft` FOREIGN KEY (`source_draft_id`)
    REFERENCES `billing_invoice_drafts` (`draft_id`),
  ADD CONSTRAINT `ck_issued_invoice_snapshots` CHECK (`source_type`<>'PAC_ISSUED' OR
    (`issuer_profile_id` IS NOT NULL AND `issuer_legal_name_snapshot` IS NOT NULL AND
     `issuer_rfc_snapshot` IS NOT NULL AND `issuer_regime_code_snapshot` IS NOT NULL AND
     `expedition_postal_code_snapshot` IS NOT NULL AND `payment_method_code_snapshot` IS NOT NULL AND
     `payment_form_code_snapshot` IS NOT NULL AND `cfdi_use_code_issued_snapshot` IS NOT NULL AND
     `certified_at` IS NOT NULL AND `source_draft_id` IS NOT NULL));

CREATE TABLE `billing_invoice_issuance_events` (
  `event_id` CHAR(36) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `patient_id` VARCHAR(64) DEFAULT NULL,
  `draft_id` CHAR(36) DEFAULT NULL,
  `invoice_id` CHAR(36) DEFAULT NULL,
  `issuer_profile_id` CHAR(36) DEFAULT NULL,
  `billing_profile_id` CHAR(36) DEFAULT NULL,
  `action` VARCHAR(40) NOT NULL,
  `result` VARCHAR(40) NOT NULL,
  `occurred_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`event_id`),
  KEY `idx_billing_issuance_events_doctor_time` (`doctor_id`,`occurred_at`),
  KEY `idx_billing_issuance_events_draft_time` (`draft_id`,`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
