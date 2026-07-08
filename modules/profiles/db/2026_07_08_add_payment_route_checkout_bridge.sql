-- SQL ejecutable versionado para el puente payment_route -> checkout_intent.
-- Microfase: BE/Suscripciones-PaymentRoute-CheckoutBridge-Endpoint-NoProvider-01
-- Alcance:
-- - Agrega columnas de relacion entre subscription_payment_routes y subscription_checkout_intents.
-- - No modifica PaymentIntent, PaymentEvents ni profile_subscriptions.
-- - No ejecuta Stripe ni activa suscripciones.

ALTER TABLE subscription_checkout_intents
  ADD COLUMN payment_route_uuid CHAR(36) NULL DEFAULT NULL AFTER request_hash,
  ADD UNIQUE KEY ux_sub_checkout_intents_payment_route (payment_route_uuid);

ALTER TABLE subscription_payment_routes
  ADD COLUMN checkout_intent_uuid CHAR(36) NULL DEFAULT NULL AFTER next_action_enabled,
  ADD COLUMN checkout_created_at DATETIME NULL DEFAULT NULL AFTER checkout_intent_uuid,
  ADD COLUMN consumed_at DATETIME NULL DEFAULT NULL AFTER checkout_created_at,
  ADD KEY idx_sub_payment_routes_checkout (checkout_intent_uuid);
