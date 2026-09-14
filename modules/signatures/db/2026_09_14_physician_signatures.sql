-- SIG03A current authority. No browser backfill, no handoff sessions.
CREATE TABLE IF NOT EXISTS physician_signatures (
 doctor_id VARCHAR(64) NOT NULL,
 asset_id CHAR(36) NOT NULL,
 storage_key VARCHAR(255) NOT NULL,
 checksum_sha256 CHAR(64) NOT NULL,
 byte_size INT UNSIGNED NOT NULL,
 width INT UNSIGNED NOT NULL,
 height INT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY (doctor_id),
 UNIQUE KEY uq_signature_asset (asset_id),
 CONSTRAINT fk_signature_doctor FOREIGN KEY (doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
