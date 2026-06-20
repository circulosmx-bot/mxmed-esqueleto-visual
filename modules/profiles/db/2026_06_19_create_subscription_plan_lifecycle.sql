-- SQL ejecutable para crear el schema inicial de suscripciones de planes.
-- No ejecutado aun en esta microfase.
-- Basado en docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md,
-- Adenda PP-Decisiones 22: Decision de schema para suscripciones de planes.
-- Basado en docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md,
-- Adenda PP-Decisiones 23: Constraints y compatibilidad MySQL para suscripciones.
--
-- Alcance:
-- - Crea las tablas subscription_plans y profile_subscriptions.
-- - No incluye seeds.
-- - No conecta backend.
-- - No conecta UI.
-- - No conecta PublicProfilePlanCapabilities.
-- - No activa planes reales.
-- - No modifica capacidades productivas.
--
-- Decisiones de constraints:
-- - No se crea FK real hacia subscription_plans en esta primera migracion.
--   La integridad de plan_code + billing_period se validara en backend.
-- - No se crean FKs reales hacia entity_type/entity_id, doctor_id ni profile_id.
--   entity_type + entity_id se mantiene multi-entidad y doctor_id/profile_id son auxiliares nullable.
-- - No se usa ENUM ni CHECK inicial para status por compatibilidad MySQL/MariaDB.
--   La validacion de status sera responsabilidad backend.
-- - No se implementa todavia columna generada ni UNIQUE condicional para suscripcion vigente.
--   La unicidad logica de una suscripcion vigente por entidad se refuerza inicialmente en backend.
--   En fase posterior puede evaluarse active_subscription_entity_key + UNIQUE.
-- - effective_plan_code es snapshot/read-model y no sustituye el calculo efectivo en backend.
--   En lectura debe validarse contra status, starts_at, expires_at, grace_starts_at y grace_ends_at.
-- - Fechas contractuales usan DATETIME; timestamps tecnicos usan TIMESTAMP.
-- - Pagos, facturacion, recibos y evidencia de pago quedan para tablas/microfases posteriores.
-- - El seed de free/basic/standard/optimum/professional se hara en archivo separado posterior.

-- Estados conceptuales permitidos para profile_subscriptions.status:
-- - draft
-- - active
-- - expiring_soon
-- - grace_period
-- - expired
-- - inactive
-- - cancelled
-- - renewed
--
-- Nota: expiring_soon debe ser preferentemente calculado y no persistido
-- como estado contractual principal.

-- Reglas funcionales:
-- - starts_at se fija al aceptar/contratar.
-- - expires_at se calcula una sola vez.
-- - Anualidad base = 365 dias.
-- - Las fechas contractuales no deben recalcularse dinamicamente por lecturas.
-- - La renovacion debe crear nueva fila y enlazar con renewed_from_subscription_id.
-- - El vencimiento no borra perfil, agenda, contactos, expediente ni configuracion.
-- - Despues de gracia se retiran capacidades premium segun el plan efectivo.
-- - El borrado logico no debe borrar perfil, agenda, contactos, expediente ni configuracion.
-- - PublicProfilePlanCapabilities no debe conectarse a estas tablas hasta una microfase posterior.

CREATE TABLE IF NOT EXISTS subscription_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  plan_code VARCHAR(64) NOT NULL,
  plan_label VARCHAR(120) NOT NULL,
  billing_period VARCHAR(32) NOT NULL DEFAULT 'annual',
  duration_days INT UNSIGNED NOT NULL DEFAULT 365,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,

  source VARCHAR(64) NOT NULL DEFAULT 'mxmed_schema',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY ux_subscription_plans_code_period (plan_code, billing_period),
  KEY idx_subscription_plans_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogo objetivo futuro, sin seeds en esta migracion:
-- - free
-- - basic
-- - standard
-- - optimum
-- - professional

CREATE TABLE IF NOT EXISTS profile_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  subscription_id CHAR(36) NOT NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_id VARCHAR(64) NOT NULL,
  doctor_id VARCHAR(64) NULL DEFAULT NULL,
  profile_id VARCHAR(64) NULL DEFAULT NULL,

  plan_code VARCHAR(64) NOT NULL,
  plan_label VARCHAR(120) NULL DEFAULT NULL,
  billing_period VARCHAR(32) NOT NULL DEFAULT 'annual',
  duration_days INT UNSIGNED NOT NULL DEFAULT 365,
  contracted_plan_code VARCHAR(64) NOT NULL,
  effective_plan_code VARCHAR(64) NOT NULL DEFAULT 'free',

  contract_version VARCHAR(64) NULL DEFAULT NULL,
  contract_accepted_at DATETIME NULL DEFAULT NULL,
  contract_accepted_by_user_id VARCHAR(64) NULL DEFAULT NULL,
  contract_acceptance_source VARCHAR(64) NULL DEFAULT NULL,
  contract_acceptance_ip VARCHAR(45) NULL DEFAULT NULL,
  contract_acceptance_user_agent VARCHAR(255) NULL DEFAULT NULL,

  starts_at DATETIME NULL DEFAULT NULL,
  expires_at DATETIME NULL DEFAULT NULL,
  grace_starts_at DATETIME NULL DEFAULT NULL,
  grace_ends_at DATETIME NULL DEFAULT NULL,

  status VARCHAR(32) NOT NULL DEFAULT 'draft',
  auto_renew TINYINT(1) NOT NULL DEFAULT 0,
  cancelled_at DATETIME NULL DEFAULT NULL,
  renewed_from_subscription_id CHAR(36) NULL DEFAULT NULL,
  renewed_to_subscription_id CHAR(36) NULL DEFAULT NULL,

  source VARCHAR(64) NOT NULL DEFAULT 'mxmed_schema',
  notes TEXT NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL DEFAULT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY ux_profile_subscriptions_subscription_id (subscription_id),
  KEY idx_profile_subscriptions_entity_status (entity_type, entity_id, status),
  KEY idx_profile_subscriptions_entity_dates (entity_type, entity_id, starts_at, expires_at),
  KEY idx_profile_subscriptions_plan_status (plan_code, status),
  KEY idx_profile_subscriptions_status_expires (status, expires_at),
  KEY idx_profile_subscriptions_status_grace (status, grace_ends_at),
  KEY idx_profile_subscriptions_renewed_from (renewed_from_subscription_id),
  KEY idx_profile_subscriptions_renewed_to (renewed_to_subscription_id),
  KEY idx_profile_subscriptions_doctor (doctor_id),
  KEY idx_profile_subscriptions_profile (profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tipos de entidad publicable objetivo:
-- - doctor
-- - dental
-- - hospital
-- - clinic
-- - laboratory
-- - diagnostic
-- - insurer
-- - pharmaceutical
-- - service
