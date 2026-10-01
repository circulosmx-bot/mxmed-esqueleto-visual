-- PROV04D. Requires the existing subscription plan lifecycle schema.
-- Existing catalog rows remain DOCTOR. No provider plan or subscription is seeded.
ALTER TABLE subscription_plans
  ADD COLUMN product_family ENUM('DOCTOR','PROVIDER_ORGANIZATION') NOT NULL DEFAULT 'DOCTOR';

CREATE TABLE IF NOT EXISTS provider_subscription_plan_capabilities (
  plan_code VARCHAR(64) NOT NULL,
  billing_period VARCHAR(32) NOT NULL,
  capability VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (plan_code,billing_period,capability),
  CONSTRAINT fk_provider_plan_capability_plan FOREIGN KEY (plan_code,billing_period)
    REFERENCES subscription_plans(plan_code,billing_period) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT ck_provider_plan_capability CHECK (capability IN (
    'provider_profile_manage','provider_locations_manage','provider_offerings_manage',
    'provider_service_areas_manage','provider_matching_participation','provider_public_profile_publish'
  ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Database backstop for one live provider subscription per organization.
-- Historical expired/cancelled/renewed rows remain available.
ALTER TABLE profile_subscriptions
  ADD COLUMN active_provider_entity_key VARCHAR(64) GENERATED ALWAYS AS (
    CASE WHEN entity_type='provider_organization' AND deleted_at IS NULL
      AND status IN ('active','expiring_soon','grace_period') THEN entity_id ELSE NULL END
  ) STORED,
  ADD UNIQUE KEY uq_active_provider_subscription_entity (active_provider_entity_key),
  ADD CONSTRAINT ck_provider_subscription_shape CHECK (
    entity_type<>'provider_organization' OR (doctor_id IS NULL AND profile_id IS NULL)
  );

CREATE TABLE IF NOT EXISTS provider_subscription_governance_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subscription_id CHAR(36) NOT NULL,
  group_id VARCHAR(64) NOT NULL,
  actor_account_id VARCHAR(64) NOT NULL,
  action ENUM('ACTIVATED','CANCELLED') NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (event_id),
  KEY idx_provider_subscription_events (subscription_id,event_id),
  CONSTRAINT fk_provider_subscription_event_subscription FOREIGN KEY (subscription_id)
    REFERENCES profile_subscriptions(subscription_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_subscription_event_group FOREIGN KEY (group_id)
    REFERENCES medical_groups(group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_subscription_event_actor FOREIGN KEY (actor_account_id)
    REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- This shared read authority is used by both the entitlement port and matcher.
-- It does not change any provider verification state.
CREATE OR REPLACE VIEW healthcare_provider_commercial_active_capabilities AS
SELECT s.entity_id AS group_id, c.capability, s.subscription_id
FROM profile_subscriptions s
JOIN medical_groups mg ON mg.group_id=s.entity_id AND mg.status IN ('pending','verified')
JOIN subscription_plans p ON p.plan_code=s.plan_code AND p.billing_period=s.billing_period
  AND p.product_family='PROVIDER_ORGANIZATION'
JOIN provider_subscription_plan_capabilities c ON c.plan_code=p.plan_code AND c.billing_period=p.billing_period
WHERE s.entity_type='provider_organization' AND s.doctor_id IS NULL AND s.profile_id IS NULL
  AND s.deleted_at IS NULL AND s.starts_at IS NOT NULL AND s.starts_at<=UTC_TIMESTAMP()
  AND (
    (s.status IN ('active','expiring_soon') AND s.expires_at IS NOT NULL AND s.expires_at>=UTC_TIMESTAMP())
    OR (s.status IN ('active','expiring_soon','grace_period')
      AND s.grace_ends_at IS NOT NULL AND s.grace_ends_at>=UTC_TIMESTAMP()
      AND COALESCE(s.grace_starts_at,s.expires_at)<=UTC_TIMESTAMP()
      AND (s.status='grace_period' OR s.expires_at<UTC_TIMESTAMP()))
  );
