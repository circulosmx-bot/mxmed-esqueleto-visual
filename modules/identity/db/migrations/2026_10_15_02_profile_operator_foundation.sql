-- OP-IMP01. Prepared only; apply to an empty disposable schema, never mxmed/QA05.
-- Canonical memberships remain the assignment authority. No account/password/seat table.
CREATE TABLE IF NOT EXISTS auth_profile_operator_invitations (
 invitation_id VARCHAR(64) PRIMARY KEY,
 profile_doctor_id VARCHAR(64) NOT NULL,
 owner_membership_id VARCHAR(64) NOT NULL,
 email_normalized VARCHAR(190) NOT NULL,
 role_code VARCHAR(32) NOT NULL,
 offer_revision INT UNSIGNED NOT NULL DEFAULT 1,
 offered_capabilities JSON NOT NULL,
 offer_hash CHAR(64) NOT NULL,
 token_hash CHAR(64) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 created_at DATETIME(6) NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 closed_at DATETIME(6) NULL,
 accepted_membership_id VARCHAR(64) NULL,
 pending_contact VARCHAR(190) GENERATED ALWAYS AS (CASE WHEN status='pending' THEN email_normalized ELSE NULL END) STORED,
 UNIQUE KEY uq_operator_pending_contact(profile_doctor_id,pending_contact),
 UNIQUE KEY uq_operator_token_hash(token_hash),
 KEY ix_operator_invitation_profile(profile_doctor_id,status,expires_at),
 FOREIGN KEY(profile_doctor_id) REFERENCES profiles_doctors(doctor_id),
 FOREIGN KEY(owner_membership_id) REFERENCES auth_account_memberships(membership_id),
 FOREIGN KEY(accepted_membership_id) REFERENCES auth_account_memberships(membership_id),
 CHECK(role_code IN ('administrator','collaborator')),
 CHECK(status IN ('pending','accepted','rejected','cancelled','expired')),
 CHECK(offer_revision=1),
 CHECK((status='accepted' AND accepted_membership_id IS NOT NULL) OR (status<>'accepted' AND accepted_membership_id IS NULL)),
 CHECK((status='pending' AND closed_at IS NULL) OR (status<>'pending' AND closed_at IS NOT NULL)),
 CHECK(expires_at=DATE_ADD(created_at, INTERVAL 7 DAY)),
 CHECK(JSON_TYPE(offered_capabilities)='ARRAY')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable revisions: updating grants supersedes the old revision, never deletes it.
CREATE TABLE IF NOT EXISTS auth_profile_operator_grant_revisions (
 revision_id VARCHAR(64) PRIMARY KEY,
 membership_id VARCHAR(64) NOT NULL,
 invitation_id VARCHAR(64) NOT NULL,
 owner_membership_id VARCHAR(64) NOT NULL,
 revision INT UNSIGNED NOT NULL,
 status VARCHAR(24) NOT NULL,
 valid_from DATETIME(6) NOT NULL,
 valid_until DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 current_membership VARCHAR(64) GENERATED ALWAYS AS (CASE WHEN status IN ('active','review_required') THEN membership_id ELSE NULL END) STORED,
 UNIQUE KEY uq_operator_revision(membership_id,revision),
 UNIQUE KEY uq_operator_current_revision(current_membership),
 FOREIGN KEY(membership_id) REFERENCES auth_account_memberships(membership_id),
 FOREIGN KEY(invitation_id) REFERENCES auth_profile_operator_invitations(invitation_id),
 FOREIGN KEY(owner_membership_id) REFERENCES auth_account_memberships(membership_id),
 CHECK(status IN ('active','superseded','review_required','revoked')),
 CHECK(revision>0),
 CHECK(valid_until IS NULL OR valid_until>valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_profile_operator_grants (
 revision_id VARCHAR(64) NOT NULL,
 capability VARCHAR(64) NOT NULL,
 PRIMARY KEY(revision_id,capability),
 FOREIGN KEY(revision_id) REFERENCES auth_profile_operator_grant_revisions(revision_id),
 CHECK(capability IN ('agenda.appointments.read','agenda.appointments.create','agenda.appointments.reschedule','agenda.appointments.cancel','agenda.availability.read'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotency is scoped to authenticated actor/profile. Neither result nor digest stores a raw token.
CREATE TABLE IF NOT EXISTS auth_profile_operator_commands (
 profile_doctor_id VARCHAR(64) NOT NULL,
 actor_account_id VARCHAR(64) NOT NULL,
 command_id VARCHAR(64) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 result_json JSON NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(profile_doctor_id,actor_account_id,command_id),
 FOREIGN KEY(profile_doctor_id) REFERENCES profiles_doctors(doctor_id),
 FOREIGN KEY(actor_account_id) REFERENCES auth_accounts(account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
