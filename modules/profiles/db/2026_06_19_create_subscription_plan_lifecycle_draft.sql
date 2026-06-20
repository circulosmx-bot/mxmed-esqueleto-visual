-- DRAFT / NO EJECUTADO
-- No aplicar en produccion todavia.
-- No conectado aun a backend.
-- No conectado aun a UI.
-- No conectado aun a PublicProfilePlanCapabilities.
-- Basado en docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md,
-- Adenda PP-Decisiones 22: Decision de schema para suscripciones de planes.
--
-- Proposito:
-- - Definir el borrador inicial del schema de suscripciones de planes.
-- - Mantener separada la definicion del catalogo de planes y la vigencia contractual.
-- - Preparar soporte multi-entidad sin activar planes reales.
--
-- Restricciones de esta fase:
-- - No ejecutar este archivo todavia.
-- - No insertar seeds productivos.
-- - No conectar estas tablas a capacidades publicas.
-- - No recalcular fechas contractuales dinamicamente.

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
-- Nota: expiring_soon podria ser estado calculado y no necesariamente persistido.

-- Reglas funcionales recordatorio:
-- - starts_at se fija al aceptar/contratar.
-- - expires_at se calcula una sola vez.
-- - Anualidad base = 365 dias.
-- - Las fechas contractuales no deben recalcularse dinamicamente por lecturas.
-- - La renovacion debe crear nueva fila y enlazar con renewed_from_subscription_id.
-- - El vencimiento no borra perfil, agenda, contactos, expediente ni configuracion.
-- - Despues de gracia se retiran capacidades premium segun el plan efectivo.
-- - PublicProfilePlanCapabilities no debe conectarse a estas tablas hasta una microfase posterior.

CREATE TABLE IF NOT EXISTS subscription_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  plan_code VARCHAR(64) NOT NULL,
  plan_label VARCHAR(120) NOT NULL,
  billing_period VARCHAR(32) NOT NULL DEFAULT 'annual',
  duration_days INT UNSIGNED NOT NULL DEFAULT 365,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,

  source VARCHAR(64) NOT NULL DEFAULT 'mxmed_schema_draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY ux_subscription_plans_code_period (plan_code, billing_period),
  KEY idx_subscription_plans_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogo objetivo futuro, sin seeds en esta fase:
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

  source VARCHAR(64) NOT NULL DEFAULT 'mxmed_schema_draft',
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

-- Unicidad logica pendiente:
-- La unicidad de una sola suscripcion vigente por entidad debe reforzarse en backend
-- y/o mediante una estrategia MySQL posterior con columna generada o constraint
-- especifica compatible con el motor disponible. No se intenta implementar un indice
-- parcial en este borrador para mantener portabilidad.

-- NO SEEDS PRODUCTIVOS EN ESTA MICROFASE.
