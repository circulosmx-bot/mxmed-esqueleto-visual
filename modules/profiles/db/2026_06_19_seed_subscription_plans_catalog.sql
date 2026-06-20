-- Seed idempotente para catalogo inicial de planes de suscripcion.
-- No ejecutado en esta microfase.
-- Requiere que exista la tabla subscription_plans.
-- No crea suscripciones.
-- No toca profile_subscriptions.
-- No activa planes reales por si mismo.
-- No conecta backend.
-- No conecta UI.
-- No conecta PublicProfilePlanCapabilities.
-- No cambia capacidades publicas.
-- No toca SEO productivo.

INSERT INTO subscription_plans (
  plan_code,
  plan_label,
  billing_period,
  duration_days,
  is_active,
  sort_order,
  source
) VALUES
  (
    'free',
    'Gratuito',
    'annual',
    365,
    1,
    10,
    'mxmed_seed_subscription_plans_v1'
  ),
  (
    'basic',
    'Básico',
    'annual',
    365,
    1,
    20,
    'mxmed_seed_subscription_plans_v1'
  ),
  (
    'standard',
    'Estándar',
    'annual',
    365,
    1,
    30,
    'mxmed_seed_subscription_plans_v1'
  ),
  (
    'optimum',
    'Óptimo',
    'annual',
    365,
    1,
    40,
    'mxmed_seed_subscription_plans_v1'
  ),
  (
    'professional',
    'Profesional',
    'annual',
    365,
    1,
    50,
    'mxmed_seed_subscription_plans_v1'
  )
ON DUPLICATE KEY UPDATE
  plan_label = VALUES(plan_label),
  duration_days = VALUES(duration_days),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order),
  source = VALUES(source),
  updated_at = CURRENT_TIMESTAMP;
