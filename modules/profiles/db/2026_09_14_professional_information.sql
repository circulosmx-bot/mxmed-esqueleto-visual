-- IP01A. No backfill: browser storage is never canonical physician data.
CREATE TABLE IF NOT EXISTS profiles_doctor_professional_information (
 doctor_id VARCHAR(64) NOT NULL PRIMARY KEY,
 public_professional_summary TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_professional_information_doctor FOREIGN KEY (doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS profiles_doctor_professional_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 doctor_id VARCHAR(64) NOT NULL,
 item_type VARCHAR(32) NOT NULL,
 value VARCHAR(50) NOT NULL,
 sort_order SMALLINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_professional_item_order (doctor_id,item_type,sort_order),
 CONSTRAINT fk_professional_items_parent FOREIGN KEY (doctor_id) REFERENCES profiles_doctor_professional_information(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_professional_item_type CHECK (item_type IN ('CERTIFICATION','COURSE','DIPLOMA','MEMBERSHIP','SERVICE','DISEASE','TREATMENT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
