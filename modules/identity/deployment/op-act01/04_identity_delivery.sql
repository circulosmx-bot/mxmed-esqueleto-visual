-- OP-ACT01 separately reviewed deployment artifact; original rehearsals remain guarded.
CREATE TEMPORARY TABLE act01_target_guard(n INT CHECK(n=1));
INSERT INTO act01_target_guard VALUES(IF(@mxmed_operator_deploy_target=DATABASE() AND @mxmed_operator_deploy_server_uuid=@@server_uuid AND @mxmed_operator_deploy_version=VERSION() AND LENGTH(@mxmed_operator_deploy_authorization)=64,1,0));
DROP TEMPORARY TABLE act01_target_guard;
CREATE TABLE IF NOT EXISTS auth_identity_notification_deliveries (
 delivery_id VARCHAR(64) PRIMARY KEY,
 purpose VARCHAR(32) NOT NULL,
 invitation_id VARCHAR(64) NOT NULL UNIQUE,
 recipient VARCHAR(190) NOT NULL,
 ciphertext BLOB NULL,
 key_id VARCHAR(64) NOT NULL,
 state VARCHAR(24) NOT NULL,
 attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
 next_attempt_at DATETIME(6) NOT NULL,
 lease_token CHAR(32) NULL,
 lease_until DATETIME(6) NULL,
 provider_message_id VARCHAR(190) NULL,
 expires_at DATETIME(6) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(invitation_id) REFERENCES auth_profile_operator_invitations(invitation_id),
 KEY ix_identity_delivery_due(state,next_attempt_at),
 CHECK(purpose='operator_invitation'),
 CHECK(state IN ('queued','dispatching','retry','provider_accepted','failed','uncertain','retired')),
 CHECK(attempt_count<=5),
 CHECK(expires_at=DATE_ADD(created_at,INTERVAL 7 DAY)),
 CHECK((state IN ('queued','dispatching','retry') AND ciphertext IS NOT NULL) OR (state IN ('provider_accepted','failed','uncertain','retired') AND ciphertext IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
