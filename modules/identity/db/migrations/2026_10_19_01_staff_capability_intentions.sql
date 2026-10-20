-- STAFF-CONTROL04: pending intentions are not internal_operator_grants.
CREATE TABLE auth_staff_capability_intentions (
 case_id VARCHAR(36) NOT NULL,
 version INT UNSIGNED NOT NULL,
 case_version INT UNSIGNED NOT NULL,
 recipient_reference CHAR(36) NOT NULL,
 bound_account_id VARCHAR(64) NULL,
 binding_reference VARCHAR(36) NULL,
 capabilities JSON NOT NULL,
 actor_account_id VARCHAR(64) NOT NULL,
 operation_id CHAR(36) NOT NULL,
 request_digest CHAR(64) NOT NULL,
 audit_event_id CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(case_id,version),
 UNIQUE KEY uq_staff_intention_operation(actor_account_id,operation_id),
 CONSTRAINT fk_staff_intention_case FOREIGN KEY(case_id) REFERENCES auth_staff_admission_cases(case_id),
 CONSTRAINT fk_staff_intention_actor FOREIGN KEY(actor_account_id) REFERENCES auth_accounts(account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_staff_intention_activations (
 case_id VARCHAR(36) NOT NULL,
 intention_version INT UNSIGNED NOT NULL,
 capability VARCHAR(128) NOT NULL,
 actor_account_id VARCHAR(64) NOT NULL,
 audit_event_id CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(case_id,intention_version,capability),
 FOREIGN KEY(case_id,intention_version) REFERENCES auth_staff_capability_intentions(case_id,version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
