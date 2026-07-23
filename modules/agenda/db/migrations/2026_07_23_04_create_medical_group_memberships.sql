-- Declarative CUT01-C artifact. This migration is not executed by Activity 13.
CREATE TABLE IF NOT EXISTS medical_group_memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id VARCHAR(64) NOT NULL,
    consultorio_id VARCHAR(64) NOT NULL,
    group_id VARCHAR(64) NOT NULL,
    status ENUM('pending','verified','rejected','unlinked') NOT NULL DEFAULT 'pending',
    submitted_group_name VARCHAR(190) DEFAULT NULL,
    submitted_logo_url TEXT DEFAULT NULL,
    display_name_override VARCHAR(190) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_medical_group_membership_scope (doctor_id, consultorio_id, group_id),
    KEY idx_medical_group_memberships_group_status (group_id, status),
    KEY idx_medical_group_memberships_doctor_consultorio (doctor_id, consultorio_id),
    CONSTRAINT fk_medical_group_memberships_group
      FOREIGN KEY (group_id) REFERENCES medical_groups(group_id)
      ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
