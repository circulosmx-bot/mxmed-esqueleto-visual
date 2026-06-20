-- Cleanup idempotente para transicion del plan gratuito permanente.
-- No ejecutado en esta microfase.
-- Elimina unicamente la fila legacy free/annual/365.
-- Requiere que exista la fila correcta free/lifetime/0.
-- Requiere que profile_subscriptions este vacia o que no existan referencias
-- contractuales al plan legacy free.
-- No crea suscripciones.
-- No activa planes reales.
-- No conecta backend.
-- No conecta UI.
-- No conecta capacidades.
-- No toca SEO productivo.
--
-- Validacion previa obligatoria antes de ejecutar:
--
-- SELECT COUNT(*) FROM profile_subscriptions;
-- Debe ser 0 para esta microfase local.
--
-- SELECT plan_code, billing_period, duration_days
-- FROM subscription_plans
-- WHERE plan_code = 'free';
--
-- Debe existir:
-- free / lifetime / 0
--
-- Puede existir como legacy:
-- free / annual / 365

DELETE FROM subscription_plans
WHERE plan_code = 'free'
  AND billing_period = 'annual'
  AND duration_days = 365
  AND NOT EXISTS (
    SELECT 1
    FROM profile_subscriptions
    WHERE plan_code = 'free'
  )
  AND EXISTS (
    SELECT 1
    FROM (
      SELECT id
      FROM subscription_plans
      WHERE plan_code = 'free'
        AND billing_period = 'lifetime'
        AND duration_days = 0
      LIMIT 1
    ) AS free_lifetime_guard
  );
