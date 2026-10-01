-- PROV04C: account-bound invitations; membership remains in auth_account_memberships.
-- No bearer invitation token and no clinical or patient data are stored here.
CREATE TABLE IF NOT EXISTS healthcare_organization_membership_invitations (
  invitation_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  group_id VARCHAR(64) NOT NULL,
  inviter_account_id VARCHAR(64) NOT NULL,
  invitee_account_id VARCHAR(64) NOT NULL,
  intended_role ENUM('administrator','collaborator') NOT NULL,
  status ENUM('PENDING','ACCEPTED','REVOKED','EXPIRED') NOT NULL DEFAULT 'PENDING',
  submission_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  issued_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expires_at DATETIME(6) NOT NULL,
  accepted_at DATETIME(6) NULL,
  accepted_by_account_id VARCHAR(64) NULL,
  membership_id VARCHAR(64) NULL,
  revoked_at DATETIME(6) NULL,
  pending_identity_key VARCHAR(320) GENERATED ALWAYS AS
    (CASE WHEN status='PENDING' THEN CONCAT(group_id,':',invitee_account_id) ELSE NULL END) STORED,
  PRIMARY KEY (invitation_uuid),
  UNIQUE KEY uq_provider_invite_submission (inviter_account_id,submission_key),
  UNIQUE KEY uq_provider_invite_pending (pending_identity_key),
  KEY idx_provider_invite_invitee (invitee_account_id,status),
  KEY idx_provider_invite_group (group_id,status),
  CONSTRAINT fk_provider_invite_group FOREIGN KEY (group_id) REFERENCES medical_groups(group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_invite_inviter FOREIGN KEY (inviter_account_id) REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_invite_invitee FOREIGN KEY (invitee_account_id) REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_invite_acceptor FOREIGN KEY (accepted_by_account_id) REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_invite_membership FOREIGN KEY (membership_id) REFERENCES auth_account_memberships(membership_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT ck_provider_invite_terminal CHECK (
    (status='PENDING' AND accepted_at IS NULL AND accepted_by_account_id IS NULL AND membership_id IS NULL AND revoked_at IS NULL)
    OR (status='ACCEPTED' AND accepted_at IS NOT NULL AND accepted_by_account_id=invitee_account_id AND membership_id IS NOT NULL AND revoked_at IS NULL)
    OR (status='REVOKED' AND revoked_at IS NOT NULL AND accepted_at IS NULL AND membership_id IS NULL)
    OR (status='EXPIRED' AND accepted_at IS NULL AND membership_id IS NULL AND revoked_at IS NULL)
  ),
  CONSTRAINT ck_provider_invite_expiry CHECK (expires_at>issued_at),
  CONSTRAINT ck_provider_invite_hash CHECK (request_hash REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS healthcare_organization_membership_invitation_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invitation_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  group_id VARCHAR(64) NOT NULL,
  actor_account_id VARCHAR(64) NOT NULL,
  intended_role ENUM('administrator','collaborator') NOT NULL,
  action ENUM('INVITED','ACCEPTED','REVOKED','EXPIRED') NOT NULL,
  previous_status ENUM('PENDING','ACCEPTED','REVOKED','EXPIRED') NULL,
  new_status ENUM('PENDING','ACCEPTED','REVOKED','EXPIRED') NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (event_id),
  KEY idx_provider_invite_events (invitation_uuid,event_id),
  CONSTRAINT fk_provider_invite_event_invitation FOREIGN KEY (invitation_uuid) REFERENCES healthcare_organization_membership_invitations(invitation_uuid) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_invite_event_group FOREIGN KEY (group_id) REFERENCES medical_groups(group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_invite_event_actor FOREIGN KEY (actor_account_id) REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS healthcare_organization_member_action_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id VARCHAR(64) NOT NULL,
  membership_id VARCHAR(64) NOT NULL,
  actor_account_id VARCHAR(64) NOT NULL,
  action ENUM('SUSPENDED','REVOKED') NOT NULL,
  previous_status VARCHAR(32) NOT NULL,
  new_status VARCHAR(32) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (event_id),
  KEY idx_provider_member_events (membership_id,event_id),
  CONSTRAINT fk_provider_member_event_group FOREIGN KEY (group_id) REFERENCES medical_groups(group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_member_event_membership FOREIGN KEY (membership_id) REFERENCES auth_account_memberships(membership_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_member_event_actor FOREIGN KEY (actor_account_id) REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
