-- SQL executable versioned for future subscription checkout/payment storage.
-- Do not run automatically.
-- Run only in a future authorized DB microphase.
-- Creates the subscription checkout/payment tables below.
-- Does not implement backend, frontend, productive checkout, payments or webhooks.
-- Does not alter existing tables.
-- Based on PP-Decisiones 61, 62, 63 and 64.

-- subscription_checkout_intents records the checkout attempt before payment.
-- It does not activate a subscription by itself.
-- profile_subscriptions must be created only after confirmed payment.
-- contract_acceptance_uuid points to the pending contractual acceptance.
-- subscription_id is filled only during internal activation.
-- Pricing snapshot is stored for audit and provider reconciliation.
CREATE TABLE IF NOT EXISTS subscription_checkout_intents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,

  entity_type VARCHAR(64) NOT NULL,
  entity_id VARCHAR(64) NOT NULL,
  doctor_id VARCHAR(64) NULL DEFAULT NULL,
  profile_id VARCHAR(64) NULL DEFAULT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  actor_role VARCHAR(32) NULL DEFAULT NULL,

  plan_code VARCHAR(64) NOT NULL,
  billing_period VARCHAR(32) NOT NULL,
  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'MXN',
  price_source VARCHAR(128) NULL DEFAULT NULL,
  price_version VARCHAR(64) NULL DEFAULT NULL,

  status VARCHAR(32) NOT NULL DEFAULT 'pending_contract',

  contract_version VARCHAR(64) NOT NULL,
  contract_hash VARCHAR(128) NOT NULL,
  contract_snapshot_url VARCHAR(255) NOT NULL,
  contract_acceptance_uuid CHAR(36) NULL DEFAULT NULL,

  idempotency_key_hash CHAR(64) NULL DEFAULT NULL,
  request_hash CHAR(64) NULL DEFAULT NULL,

  provider VARCHAR(64) NULL DEFAULT NULL,
  provider_checkout_id VARCHAR(128) NULL DEFAULT NULL,
  provider_payment_id VARCHAR(128) NULL DEFAULT NULL,
  checkout_url VARCHAR(512) NULL DEFAULT NULL,

  subscription_id CHAR(36) NULL DEFAULT NULL,

  expires_at DATETIME NOT NULL,
  completed_at DATETIME NULL DEFAULT NULL,
  cancelled_at DATETIME NULL DEFAULT NULL,
  activated_at DATETIME NULL DEFAULT NULL,

  source VARCHAR(128) NOT NULL DEFAULT 'mxmed_subscription_checkout_intent_v1',
  notes TEXT NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL DEFAULT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY ux_sub_checkout_intents_uuid (uuid),
  KEY idx_sub_checkout_intents_entity (entity_type, entity_id),
  KEY idx_sub_checkout_intents_doctor (doctor_id),
  KEY idx_sub_checkout_intents_user (user_id),
  KEY idx_sub_checkout_intents_status_expires (status, expires_at),
  KEY idx_sub_checkout_intents_plan (plan_code, billing_period),
  KEY idx_sub_checkout_intents_contract_acceptance (contract_acceptance_uuid),
  KEY idx_sub_checkout_intents_subscription (subscription_id),
  KEY idx_sub_checkout_intents_provider_checkout (provider, provider_checkout_id),
  KEY idx_sub_checkout_intents_provider_payment (provider, provider_payment_id),
  KEY idx_sub_checkout_intents_created_at (created_at),
  KEY idx_sub_checkout_intents_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- subscription_payment_intents models the live payment attempt with a provider.
-- Provider state is kept separate from the general checkout intent state.
-- Relations are validated by backend in v1; no real relational constraints are declared here.
CREATE TABLE IF NOT EXISTS subscription_payment_intents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,

  checkout_intent_uuid CHAR(36) NOT NULL,

  provider VARCHAR(64) NOT NULL,
  provider_payment_id VARCHAR(128) NOT NULL,
  provider_checkout_id VARCHAR(128) NULL DEFAULT NULL,

  normalized_status VARCHAR(32) NOT NULL DEFAULT 'created',
  provider_status VARCHAR(64) NULL DEFAULT NULL,

  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'MXN',

  created_at_provider DATETIME NULL DEFAULT NULL,
  expires_at DATETIME NULL DEFAULT NULL,
  paid_at DATETIME NULL DEFAULT NULL,
  failed_at DATETIME NULL DEFAULT NULL,
  cancelled_at DATETIME NULL DEFAULT NULL,

  source VARCHAR(128) NOT NULL DEFAULT 'mxmed_subscription_payment_intent_v1',
  notes TEXT NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL DEFAULT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY ux_sub_payment_intents_uuid (uuid),
  UNIQUE KEY ux_sub_payment_intents_provider_payment (provider, provider_payment_id),
  KEY idx_sub_payment_intents_checkout (checkout_intent_uuid),
  KEY idx_sub_payment_intents_status (normalized_status),
  KEY idx_sub_payment_intents_provider_status (provider, provider_status),
  KEY idx_sub_payment_intents_created_at (created_at),
  KEY idx_sub_payment_intents_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- subscription_payment_events is the idempotent webhook/event ledger.
-- provider + provider_event_id prevents processing the same provider event twice.
-- event_hash is a fallback fingerprint for provider events.
-- payload_text_sanitized is optional and must not contain sensitive card data.
CREATE TABLE IF NOT EXISTS subscription_payment_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,

  checkout_intent_uuid CHAR(36) NULL DEFAULT NULL,
  payment_intent_uuid CHAR(36) NULL DEFAULT NULL,

  provider VARCHAR(64) NOT NULL,
  provider_event_id VARCHAR(128) NOT NULL,
  provider_payment_id VARCHAR(128) NULL DEFAULT NULL,
  event_type VARCHAR(128) NOT NULL,
  provider_status VARCHAR(64) NULL DEFAULT NULL,
  normalized_status VARCHAR(32) NULL DEFAULT NULL,

  amount_cents BIGINT UNSIGNED NULL DEFAULT NULL,
  currency CHAR(3) NULL DEFAULT NULL,

  event_hash CHAR(64) NOT NULL,

  signature_validated_at DATETIME NULL DEFAULT NULL,
  received_at DATETIME NOT NULL,
  processed_at DATETIME NULL DEFAULT NULL,
  processing_status VARCHAR(32) NOT NULL DEFAULT 'received',
  error_message TEXT NULL DEFAULT NULL,
  payload_text_sanitized TEXT NULL DEFAULT NULL,

  source VARCHAR(128) NOT NULL DEFAULT 'mxmed_subscription_payment_event_v1',
  notes TEXT NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL DEFAULT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY ux_sub_payment_events_uuid (uuid),
  UNIQUE KEY ux_sub_payment_events_provider_event (provider, provider_event_id),
  KEY idx_sub_payment_events_event_hash (event_hash),
  KEY idx_sub_payment_events_provider_payment (provider, provider_payment_id),
  KEY idx_sub_payment_events_checkout (checkout_intent_uuid),
  KEY idx_sub_payment_events_payment_intent (payment_intent_uuid),
  KEY idx_sub_payment_events_processing_status (processing_status),
  KEY idx_sub_payment_events_received_at (received_at),
  KEY idx_sub_payment_events_processed_at (processed_at),
  KEY idx_sub_payment_events_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
