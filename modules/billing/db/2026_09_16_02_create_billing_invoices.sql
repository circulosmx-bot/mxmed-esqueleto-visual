-- FISC02A: historical CFDI archive. Apply once after FISC01 physical activation.
-- The composite key makes optional profile provenance match the invoice owner.
ALTER TABLE `billing_patient_profiles`
  ADD UNIQUE KEY `uq_billing_profile_scope` (`billing_profile_id`, `doctor_id`, `patient_id`);

CREATE TABLE `billing_invoices` (
  `invoice_id` CHAR(36) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `patient_id` VARCHAR(64) NOT NULL,
  `billing_profile_id` CHAR(36) DEFAULT NULL,
  `source_type` VARCHAR(32) NOT NULL,
  `cfdi_version` VARCHAR(8) NOT NULL,
  `cfdi_uuid` CHAR(36) NOT NULL,
  `series` VARCHAR(25) DEFAULT NULL,
  `folio` VARCHAR(40) DEFAULT NULL,
  `issued_at` DATETIME NOT NULL,
  `currency_code` CHAR(3) NOT NULL,
  `subtotal` DECIMAL(18,6) NOT NULL,
  `discount` DECIMAL(18,6) DEFAULT NULL,
  `tax_total` DECIMAL(18,6) NOT NULL,
  `total` DECIMAL(18,6) NOT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'UNKNOWN',
  `receiver_legal_name_snapshot` VARCHAR(254) NOT NULL,
  `receiver_rfc_snapshot` VARCHAR(13) NOT NULL,
  `receiver_fiscal_zip_snapshot` CHAR(5) NOT NULL,
  `receiver_regime_code_snapshot` CHAR(3) NOT NULL,
  `cfdi_use_code_snapshot` VARCHAR(4) NOT NULL,
  `xml_storage_key` VARCHAR(255) NOT NULL,
  `pdf_storage_key` VARCHAR(255) DEFAULT NULL,
  `xml_sha256` CHAR(64) NOT NULL,
  `pdf_sha256` CHAR(64) DEFAULT NULL,
  `xml_bytes` INT UNSIGNED NOT NULL,
  `pdf_bytes` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `archived_at` DATETIME(6) DEFAULT NULL,
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `uq_billing_invoice_doctor_uuid` (`doctor_id`, `cfdi_uuid`),
  UNIQUE KEY `uq_billing_invoice_doctor_xml` (`doctor_id`, `xml_sha256`),
  KEY `idx_billing_invoice_patient` (`doctor_id`, `patient_id`, `issued_at`, `invoice_id`),
  KEY `idx_billing_invoice_doctor_date` (`doctor_id`, `issued_at`, `invoice_id`),
  KEY `idx_billing_invoice_doctor_status` (`doctor_id`, `status`, `issued_at`),
  KEY `idx_billing_invoice_doctor_rfc` (`doctor_id`, `receiver_rfc_snapshot`),
  KEY `idx_billing_invoice_profile_scope` (`billing_profile_id`, `doctor_id`, `patient_id`),
  CONSTRAINT `fk_billing_invoice_link` FOREIGN KEY (`doctor_id`, `patient_id`)
    REFERENCES `patients_doctor_links` (`doctor_id`, `patient_id`),
  CONSTRAINT `fk_billing_invoice_patient` FOREIGN KEY (`patient_id`)
    REFERENCES `patients_patients` (`patient_id`),
  CONSTRAINT `fk_billing_invoice_profile_scope` FOREIGN KEY (`billing_profile_id`, `doctor_id`, `patient_id`)
    REFERENCES `billing_patient_profiles` (`billing_profile_id`, `doctor_id`, `patient_id`),
  CONSTRAINT `ck_billing_invoice_source` CHECK (`source_type` IN ('HISTORICAL_IMPORT', 'PAC_ISSUED')),
  CONSTRAINT `ck_billing_invoice_status` CHECK (`status` IN ('UNKNOWN', 'VERIFIED_VALID', 'VERIFIED_CANCELED')),
  CONSTRAINT `ck_billing_invoice_documents` CHECK ((`pdf_storage_key` IS NULL AND `pdf_sha256` IS NULL AND `pdf_bytes` IS NULL)
    OR (`pdf_storage_key` IS NOT NULL AND `pdf_sha256` IS NOT NULL AND `pdf_bytes` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
