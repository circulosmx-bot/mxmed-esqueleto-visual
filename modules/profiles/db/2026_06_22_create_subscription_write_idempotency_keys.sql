-- SQL ejecutable versionado para crear subscription_write_idempotency_keys.
-- Proyecto: Mexico Medico / MXMed
-- Microfase: DB-Suscripciones-ContractAcceptance-IdempotencyExecutableSql-Create-01
-- No fue ejecutado por esta microfase.
-- Su aplicacion contra DB local o remota requiere microfase posterior explicita.
--
-- Basado en:
-- - Adenda PP-Decisiones 55: Diseno de idempotencia del endpoint write contractual.
-- - Adenda PP-Decisiones 56: Decision de storage de idempotencia del endpoint write contractual.
-- - Adenda PP-Decisiones 57: Cierre del draft SQL de idempotencia contractual.
--
-- Alcance:
-- - Crea la tabla subscription_write_idempotency_keys.
-- - La tabla es un ledger de idempotencia para writes contractuales de suscripcion.
-- - No reemplaza profile_subscriptions.
-- - No reemplaza subscription_contract_acceptances.
-- - No modifica tablas existentes.
-- - No crea seeds ni datos iniciales.
-- - No crea FKs reales.
-- - No usa tipos cerrados nativos.
-- - No usa restricciones de validacion declarativa.
-- - No conecta backend.
-- - No conecta frontend.
-- - No conecta cobros.
-- - No conecta facturacion.
-- - No activa funcionalidades productivas.
-- - No toca perfil publico.
-- - No toca SEO productivo.
--
-- Decisiones de compatibilidad:
-- - Usar VARCHAR para estados, operacion y fuente.
-- - Usar DATETIME para fechas de control del flujo.
-- - Usar TIMESTAMP para created_at y updated_at.
-- - Mantener deleted_at para borrado logico controlado.
-- - Mantener multi-entidad mediante entity_type + entity_id.
-- - Alinear entity_id, doctor_id y profile_id con profile_subscriptions.
-- - Alinear subscription_id con profile_subscriptions.subscription_id.
-- - Alinear contract_acceptance_uuid con subscription_contract_acceptances.uuid.
-- - Guardar response opcional como TEXT para compatibilidad MySQL/MariaDB.
--
-- Reglas funcionales recordatorio:
-- - Esta tabla guarda control de idempotencia, no evidencia legal.
-- - No guarda payload completo.
-- - No guarda direccion de red ni agente de usuario.
-- - No guarda datos sensibles.
-- - Las relaciones se validan por backend.
-- - status se valida por backend.
-- - expires_at habilita TTL y limpieza futura.
-- - processing puede tener referencias nulas.
-- - completed debe tener referencias si la operacion creo datos.
-- - failed no debe dejar aceptacion ni suscripcion huerfana.

CREATE TABLE IF NOT EXISTS subscription_write_idempotency_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,

  -- Hash sha256 de la key normalizada. No guardar la key cruda como fuente principal.
  idempotency_key_hash CHAR(64) NOT NULL,
  -- Hash sha256 del request canonicalizado para detectar misma key con payload distinto.
  request_hash CHAR(64) NOT NULL,

  entity_type VARCHAR(64) NOT NULL,
  entity_id VARCHAR(64) NOT NULL,
  doctor_id VARCHAR(64) NULL DEFAULT NULL,
  profile_id VARCHAR(64) NULL DEFAULT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  actor_role VARCHAR(32) NULL DEFAULT NULL,
  operation VARCHAR(96) NOT NULL,

  -- Estados conceptuales: processing, completed, failed, expired, cancelled.
  status VARCHAR(32) NOT NULL DEFAULT 'processing',

  -- Referencias llenadas al completar el write contractual.
  subscription_id CHAR(36) NULL DEFAULT NULL,
  contract_acceptance_uuid CHAR(36) NULL DEFAULT NULL,
  response_http_status SMALLINT UNSIGNED NULL DEFAULT NULL,
  -- Respuesta sanitizada opcional. La fuente principal del replay son las referencias.
  response_body_text TEXT NULL DEFAULT NULL,

  locked_at DATETIME NULL DEFAULT NULL,
  completed_at DATETIME NULL DEFAULT NULL,
  expires_at DATETIME NOT NULL,

  source VARCHAR(128) NOT NULL DEFAULT 'mxmed_subscription_idempotency_v1',
  notes TEXT NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL DEFAULT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY ux_sub_write_idem_uuid (uuid),
  UNIQUE KEY ux_sub_write_idem_scope (
    idempotency_key_hash,
    user_id,
    entity_type,
    entity_id,
    operation
  ),
  KEY idx_sub_write_idem_entity (entity_type, entity_id),
  KEY idx_sub_write_idem_doctor (doctor_id),
  KEY idx_sub_write_idem_user (user_id),
  KEY idx_sub_write_idem_operation_status (operation, status),
  KEY idx_sub_write_idem_status_expires (status, expires_at),
  KEY idx_sub_write_idem_subscription (subscription_id),
  KEY idx_sub_write_idem_acceptance (contract_acceptance_uuid),
  KEY idx_sub_write_idem_deleted_at (deleted_at),
  KEY idx_sub_write_idem_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
