-- Versioned executable SQL for subscription plan prices storage.
-- Creates subscription_plan_prices for future subscription checkout pricing.
-- Do not run automatically; run only in a future authorized DB microphase.
-- This file adds no price rows and no seed data.
-- This file does not change subscription_plans.
-- This file does not implement checkout, payments, webhooks, fiscal issuance or capabilities.
-- Based on PP-Decisiones 67 and 68.

-- subscription_plan_prices is the future server-side canonical source of price.
-- subscription_plans remains the technical catalog for plan, period and duration.
-- subscription_checkout_intents will copy amount_cents, currency, price_source and
-- price_version as an immutable snapshot when checkout is created.
-- Referential integrity to subscription_plans is validated by backend in v1.
-- Active price uniqueness by plan, period and currency is validated by backend/QA in v1.
-- If multiple active prices match, checkout-intents must return pricing_configuration_conflict.
-- If no active price matches, checkout-intents must return plan_price_not_configured.
-- If pricing storage is unavailable, checkout-intents must return pricing_source_unavailable.
CREATE TABLE IF NOT EXISTS subscription_plan_prices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,

  plan_code VARCHAR(64) NOT NULL,
  billing_period VARCHAR(32) NOT NULL,

  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'MXN',
  price_source VARCHAR(128) NOT NULL DEFAULT 'subscription_plan_prices',
  price_version VARCHAR(64) NOT NULL,

  valid_from DATETIME NOT NULL,
  valid_until DATETIME NULL DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,

  source VARCHAR(128) NOT NULL DEFAULT 'mxmed_subscription_plan_price_v1',
  notes TEXT NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL DEFAULT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY ux_sub_plan_prices_uuid (uuid),
  UNIQUE KEY ux_sub_plan_prices_version (plan_code, billing_period, currency, price_version),
  KEY idx_sub_plan_prices_lookup (plan_code, billing_period, currency, is_active, valid_from, valid_until),
  KEY idx_sub_plan_prices_plan (plan_code, billing_period),
  KEY idx_sub_plan_prices_active (is_active, deleted_at),
  KEY idx_sub_plan_prices_validity (valid_from, valid_until),
  KEY idx_sub_plan_prices_created_at (created_at),
  KEY idx_sub_plan_prices_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
