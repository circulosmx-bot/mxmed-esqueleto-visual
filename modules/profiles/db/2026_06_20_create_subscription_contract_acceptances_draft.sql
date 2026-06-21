-- DRAFT ONLY
-- Proyecto: Mexico Medico / MXMed
-- Microfase: DB-Suscripciones-ContractAcceptance-CreateSchemaDraft-01
-- No ejecutar directamente en produccion.
-- No aplicado a DB local en esta microfase.
-- Tabla candidata para auditoria/evidencia de aceptacion contractual de suscripciones.
--
-- Alcance:
-- - Define el borrador de subscription_contract_acceptances.
-- - Mantiene el enfoque hibrido aprobado:
--   - profile_subscriptions conserva snapshot operativo/read-model.
--   - subscription_contract_acceptances conserva evidencia legal/auditoria.
-- - No conecta backend.
-- - No conecta UI.
-- - No conecta pagos.
-- - No conecta facturacion.
-- - No activa contratacion real.
-- - No conecta PublicProfilePlanCapabilities.
-- - No activa capacidades productivas.
-- - No genera seeds ni datos iniciales.
--
-- Decisiones de compatibilidad:
-- - No usar tipos enumerados nativos.
-- - No usar restricciones de validacion declarativa.
-- - No declarar relaciones referenciales reales en este draft.
-- - Usar VARCHAR para estados y fuentes.
-- - Usar DATETIME para fechas contractuales/auditoria.
-- - Usar TIMESTAMP para created_at/updated_at.
-- - Mantener deleted_at para borrado logico.
-- - Mantener multi-entidad mediante entity_type + entity_id.
-- - Alinear entity_id, doctor_id y profile_id con profile_subscriptions.
-- - Mantener doctor_id y profile_id como auxiliares nullable.
-- - Mantener subscription_id nullable porque la aceptacion puede prepararse
--   antes o dentro de la misma transaccion futura que cree la suscripcion.
-- - subscription_id apunta conceptualmente a profile_subscriptions.subscription_id.
--
-- Reglas funcionales recordatorio:
-- - Esta tabla es evidencia/auditoria contractual.
-- - profile_subscriptions sigue siendo el snapshot operativo para lectura.
-- - La tabla separada es la fuente de evidencia legal.
-- - free no debe contratarse como plan pagado.
-- - No se crean filas free por defecto.
-- - No se conectan pagos.
-- - No se conectan capacidades.
-- - No se activa PublicProfilePlanCapabilities.
-- - Este draft no se ejecuta todavia.
-- - subscription_id sera el enlace principal hacia profile_subscriptions.
-- - El read-model seguira leyendo profile_subscriptions.
-- - La auditoria legal consultara subscription_contract_acceptances.
-- - Pagos y checkout son dominios separados; futuras tablas podran enlazar por
--   subscription_id, acceptance_id o checkout intent segun diseno posterior.

CREATE TABLE IF NOT EXISTS subscription_contract_acceptances (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,

  entity_type VARCHAR(64) NOT NULL,
  entity_id VARCHAR(64) NOT NULL,
  doctor_id VARCHAR(64) NULL DEFAULT NULL,
  profile_id VARCHAR(64) NULL DEFAULT NULL,
  subscription_id CHAR(36) NULL DEFAULT NULL,

  -- Snapshot historico del plan validado por backend contra subscription_plans.
  -- free no se contrata, no genera filas free y debe bloquearse en el flujo normal.
  plan_code VARCHAR(64) NOT NULL,
  billing_period VARCHAR(32) NOT NULL,
  duration_days INT UNSIGNED NOT NULL DEFAULT 0,

  -- Hash/snapshot quedan nullable en schema inicial; produccion debe exigir
  -- version, hash y evidencia equivalente desde el endpoint write futuro.
  contract_version VARCHAR(64) NOT NULL,
  contract_hash VARCHAR(128) NULL DEFAULT NULL,
  contract_snapshot_url VARCHAR(512) NULL DEFAULT NULL,
  contract_title VARCHAR(255) NULL DEFAULT NULL,

  accepted_at DATETIME NOT NULL,
  accepted_by_user_id BIGINT UNSIGNED NOT NULL,
  -- Roles conceptuales: doctor, operator, admin, system.
  accepted_by_actor_role VARCHAR(32) NULL DEFAULT NULL,
  accepted_by_operator_id BIGINT UNSIGNED NULL DEFAULT NULL,
  -- Fuentes conceptuales: panel_subscription, admin_panel, checkout, migration, system.
  acceptance_source VARCHAR(64) NOT NULL,

  ip_address VARCHAR(45) NULL DEFAULT NULL,
  user_agent VARCHAR(512) NULL DEFAULT NULL,

  -- Estados conceptuales: accepted, pending_link, superseded, void, expired, cancelled.
  status VARCHAR(32) NOT NULL DEFAULT 'accepted',
  source VARCHAR(128) NOT NULL DEFAULT 'mxmed_contract_acceptance_v1',
  notes TEXT NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- Evidencia legal no debe borrarse fisicamente en el flujo normal.
  -- deleted_at es ocultamiento logico o correccion controlada.
  deleted_at DATETIME NULL DEFAULT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY ux_subscription_contract_acceptances_uuid (uuid),
  KEY idx_sub_contract_acceptances_entity (entity_type, entity_id),
  KEY idx_sub_contract_acceptances_doctor (doctor_id),
  KEY idx_sub_contract_acceptances_profile (profile_id),
  KEY idx_sub_contract_acceptances_subscription (subscription_id),
  KEY idx_sub_contract_acceptances_plan_period (plan_code, billing_period),
  KEY idx_sub_contract_acceptances_contract (contract_version),
  KEY idx_sub_contract_acceptances_accepted_at (accepted_at),
  KEY idx_sub_contract_acceptances_user (accepted_by_user_id),
  KEY idx_sub_contract_acceptances_status (status),
  KEY idx_sub_contract_acceptances_deleted_at (deleted_at),
  KEY idx_sub_contract_acceptances_entity_accepted (entity_type, entity_id, accepted_at),
  KEY idx_sub_contract_acceptances_subscription_status (subscription_id, status),
  KEY idx_sub_contract_acceptances_doctor_accepted (doctor_id, accepted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
