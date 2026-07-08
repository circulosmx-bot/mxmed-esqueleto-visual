-- SQL ejecutable versionado para rutas internas de pago MXMed sin proveedor.
-- Microfase: BE/Suscripciones-PaymentRoutes-CreateEndpoint-NoProvider-01
-- Alcance:
-- - Crea subscription_payment_routes si no existe.
-- - No crea Stripe Checkout, PaymentIntent, SetupIntent ni webhooks.
-- - No modifica profile_subscriptions.
-- - No modifica tablas existentes.

CREATE TABLE IF NOT EXISTS subscription_payment_routes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,

  entity_type VARCHAR(64) NOT NULL,
  entity_id VARCHAR(64) NOT NULL,
  doctor_id VARCHAR(64) NULL DEFAULT NULL,
  profile_id VARCHAR(64) NULL DEFAULT NULL,
  user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  actor_role VARCHAR(32) NULL DEFAULT NULL,

  route_type VARCHAR(64) NOT NULL,
  current_plan_code VARCHAR(64) NULL DEFAULT NULL,
  target_plan_code VARCHAR(64) NULL DEFAULT NULL,
  billing_period VARCHAR(32) NOT NULL,
  payment_method_family VARCHAR(32) NOT NULL DEFAULT 'not_selected',
  auto_renew_requested TINYINT(1) NOT NULL DEFAULT 0,
  auto_renew_status VARCHAR(64) NOT NULL DEFAULT 'disabled',

  amount_cents BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'MXN',
  amount_source VARCHAR(64) NOT NULL DEFAULT 'server_recalculated',
  frontend_amount_cents BIGINT UNSIGNED NULL DEFAULT NULL,
  amount_mismatch TINYINT(1) NOT NULL DEFAULT 0,

  current_price_cents BIGINT UNSIGNED NULL DEFAULT NULL,
  target_price_cents BIGINT UNSIGNED NULL DEFAULT NULL,
  adjustment_amount_cents BIGINT UNSIGNED NULL DEFAULT NULL,
  renewal_amount_cents BIGINT UNSIGNED NULL DEFAULT NULL,
  remaining_days INT UNSIGNED NULL DEFAULT NULL,
  period_days INT UNSIGNED NULL DEFAULT NULL,
  renewal_duration_days INT UNSIGNED NULL DEFAULT NULL,
  current_expires_at DATETIME NULL DEFAULT NULL,
  estimated_next_expires_at DATETIME NULL DEFAULT NULL,

  status VARCHAR(64) NOT NULL DEFAULT 'created_no_provider',
  provider VARCHAR(64) NULL DEFAULT NULL,
  provider_status VARCHAR(64) NOT NULL DEFAULT 'not_created',
  next_action_type VARCHAR(96) NOT NULL DEFAULT 'stripe_checkout_sandbox_pending',
  next_action_enabled TINYINT(1) NOT NULL DEFAULT 0,
  checkout_intent_uuid CHAR(36) NULL DEFAULT NULL,
  checkout_created_at DATETIME NULL DEFAULT NULL,
  consumed_at DATETIME NULL DEFAULT NULL,

  -- Hash sha256 de la key normalizada. No guarda la key cruda como fuente principal.
  idempotency_key VARCHAR(128) NULL DEFAULT NULL,
  idempotency_key_hash CHAR(64) NULL DEFAULT NULL,
  request_hash CHAR(64) NOT NULL,

  frontend_summary_snapshot_json TEXT NULL DEFAULT NULL,
  server_preview_snapshot_json TEXT NULL DEFAULT NULL,
  warnings_json TEXT NULL DEFAULT NULL,
  reasons_json TEXT NULL DEFAULT NULL,

  expires_at DATETIME NOT NULL,
  source VARCHAR(128) NOT NULL DEFAULT 'mxmed_subscription_payment_route_v1',
  notes TEXT NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL DEFAULT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY ux_sub_payment_routes_uuid (uuid),
  KEY idx_sub_payment_routes_entity (entity_type, entity_id),
  KEY idx_sub_payment_routes_doctor (doctor_id),
  KEY idx_sub_payment_routes_user (user_id),
  KEY idx_sub_payment_routes_route_status (route_type, status),
  KEY idx_sub_payment_routes_entity_status_expires (entity_type, entity_id, status, expires_at),
  KEY idx_sub_payment_routes_checkout (checkout_intent_uuid),
  KEY idx_sub_payment_routes_plan (current_plan_code, target_plan_code, billing_period),
  KEY idx_sub_payment_routes_idempotency_hash (idempotency_key_hash),
  KEY idx_sub_payment_routes_request_hash (request_hash),
  KEY idx_sub_payment_routes_created_at (created_at),
  KEY idx_sub_payment_routes_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
