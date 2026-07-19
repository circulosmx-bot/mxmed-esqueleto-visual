-- MXMED_PLAN_CAPABILITY_POLICY_V1 — additive storage contract.
-- Activity: PRODUCT-IMPLEMENTATION/MXMed-Plans-Capabilities-Ownership-Lifecycle-Implementation-01.
-- IMPORTANT: versioned migration only. It is not executed by this activity.
-- Preconditions: run once after schema inventory and backup in an authorized DB change.
-- No existing column is removed, renamed or rewritten. Existing rows remain nullable
-- and are interpreted through explicit legacy adapters.

ALTER TABLE profiles_doctors
  ADD COLUMN approval_status VARCHAR(32) NULL DEFAULT NULL AFTER is_public_candidate,
  ADD COLUMN approval_source VARCHAR(128) NULL DEFAULT NULL AFTER approval_status,
  ADD COLUMN owner_user_id VARCHAR(64) NULL DEFAULT NULL AFTER approval_source,
  ADD COLUMN ownership_status VARCHAR(32) NULL DEFAULT NULL AFTER owner_user_id,
  ADD COLUMN ownership_source VARCHAR(128) NULL DEFAULT NULL AFTER ownership_status,
  ADD KEY idx_profiles_doctors_approval_ownership (approval_status, ownership_status),
  ADD KEY idx_profiles_doctors_owner_user (owner_user_id);

ALTER TABLE profile_subscriptions
  ADD COLUMN policy_version VARCHAR(64) NULL DEFAULT NULL AFTER effective_plan_code,
  ADD COLUMN original_expires_at DATETIME NULL DEFAULT NULL AFTER expires_at,
  ADD COLUMN grace_extension_type VARCHAR(32) NULL DEFAULT NULL AFTER grace_ends_at,
  ADD COLUMN grace_extension_days SMALLINT UNSIGNED NULL DEFAULT NULL AFTER grace_extension_type,
  ADD COLUMN grace_extension_status VARCHAR(32) NULL DEFAULT NULL AFTER grace_extension_days,
  ADD COLUMN grace_extension_approved_at DATETIME NULL DEFAULT NULL AFTER grace_extension_status,
  ADD COLUMN restricted_at DATETIME NULL DEFAULT NULL AFTER grace_extension_approved_at,
  ADD COLUMN scheduled_plan_code VARCHAR(64) NULL DEFAULT NULL AFTER restricted_at,
  ADD COLUMN scheduled_effective_at DATETIME NULL DEFAULT NULL AFTER scheduled_plan_code,
  ADD COLUMN scheduled_change_status VARCHAR(32) NULL DEFAULT NULL AFTER scheduled_effective_at,
  ADD COLUMN active_addons_json LONGTEXT NULL DEFAULT NULL AFTER scheduled_change_status,
  ADD COLUMN archival_state VARCHAR(32) NULL DEFAULT NULL AFTER active_addons_json,
  ADD KEY idx_profile_subscriptions_scheduled_change (
    entity_type,
    entity_id,
    scheduled_change_status,
    scheduled_effective_at
  ),
  ADD KEY idx_profile_subscriptions_policy_state (policy_version, status, restricted_at);

-- Logical rollback (non-destructive and compatible with the no-DROP rule):
-- 1. deploy the previous application version, which ignores every nullable field above;
-- 2. set scheduled_change_status to 'cancelled' for unapplied scheduled changes;
-- 3. leave all new columns in place so history and preserved premium data remain intact;
-- 4. physical column removal, if ever approved, belongs to a separate reviewed migration.
-- No row deletion, subscription activation, payment operation or external call is present.
