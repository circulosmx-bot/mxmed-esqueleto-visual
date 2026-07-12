-- DEV/LOCAL ONLY - Stripe sandbox E2E real QA matrix fixture.
-- Adds doctor/990106 as the visible Optimo annual active case.
-- Does not create payment routes, checkouts, PaymentIntents, events, Stripe IDs or activation artifacts.

START TRANSACTION;

SET @mxmed_qa_doctor_id := '990106';
SET @mxmed_qa_starts_at := '2026-07-09 17:10:11';
SET @mxmed_qa_expires_at := '2027-07-09 17:10:11';

-- Read-only diagnostics before writes. If these rows show incompatible data, stop and do not proceed.
SELECT
  'pre_existing_doctor_990106' AS check_name,
  id,
  doctor_id,
  display_name,
  professional_license,
  specialty_license,
  profile_status,
  is_public_candidate
FROM profiles_doctors
WHERE doctor_id = @mxmed_qa_doctor_id;

SELECT
  'pre_active_subscriptions_990106' AS check_name,
  id,
  entity_type,
  entity_id,
  doctor_id,
  plan_code,
  billing_period,
  status,
  starts_at,
  expires_at,
  deleted_at
FROM profile_subscriptions
WHERE entity_type = 'doctor'
  AND entity_id = @mxmed_qa_doctor_id
  AND status = 'active'
  AND deleted_at IS NULL;

-- Insert the doctor fixture only when it does not already exist.
INSERT INTO profiles_doctors (
  doctor_id,
  display_name,
  prefix,
  gender_label,
  professional_license,
  specialty_license,
  specialty_primary,
  specialty_secondary_json,
  bio_short,
  photo_url,
  avatar_url,
  logo_url,
  profile_status,
  is_public_candidate
)
SELECT
  @mxmed_qa_doctor_id,
  'MXMed QA Stripe E2E Optimum Replacement',
  'Dr.',
  NULL,
  'QA-STRIPE-E2E-990106',
  'QA-STRIPE-E2E-990106',
  'Medicina General',
  '[]',
  'Fixture DEV/local para QA de matriz real: plan Optimo anual activo. No usar en produccion.',
  NULL,
  NULL,
  NULL,
  'hidden',
  0
WHERE NOT EXISTS (
  SELECT 1
  FROM profiles_doctors
  WHERE doctor_id = @mxmed_qa_doctor_id
)
AND NOT EXISTS (
  SELECT 1
  FROM profile_subscriptions
  WHERE entity_type = 'doctor'
    AND entity_id = @mxmed_qa_doctor_id
    AND status = 'active'
    AND deleted_at IS NULL
)
AND NOT EXISTS (
  SELECT 1
  FROM subscription_payment_routes
  WHERE entity_type = 'doctor'
    AND entity_id = @mxmed_qa_doctor_id
    AND deleted_at IS NULL
)
AND NOT EXISTS (
  SELECT 1
  FROM subscription_checkout_intents
  WHERE entity_type = 'doctor'
    AND entity_id = @mxmed_qa_doctor_id
    AND deleted_at IS NULL
);

-- Insert the active subscription only for the expected doctor fixture and only when no active row exists.
INSERT INTO profile_subscriptions (
  subscription_id,
  entity_type,
  entity_id,
  doctor_id,
  profile_id,
  plan_code,
  plan_label,
  billing_period,
  duration_days,
  contracted_plan_code,
  effective_plan_code,
  starts_at,
  expires_at,
  status,
  auto_renew,
  source,
  notes
)
SELECT
  UUID(),
  'doctor',
  @mxmed_qa_doctor_id,
  @mxmed_qa_doctor_id,
  NULL,
  'optimum',
  'Óptimo',
  'annual',
  365,
  'optimum',
  'optimum',
  @mxmed_qa_starts_at,
  @mxmed_qa_expires_at,
  'active',
  0,
  'devqa_fixture_by_plan_seed_v1',
  'DEVQA fixture by plan; no payment_route, checkout, PaymentIntent, Stripe, webhook or activation executed.'
WHERE EXISTS (
  SELECT 1
  FROM profiles_doctors
  WHERE doctor_id = @mxmed_qa_doctor_id
    AND professional_license = 'QA-STRIPE-E2E-990106'
    AND specialty_license = 'QA-STRIPE-E2E-990106'
    AND profile_status = 'hidden'
)
AND NOT EXISTS (
  SELECT 1
  FROM profile_subscriptions
  WHERE entity_type = 'doctor'
    AND entity_id = @mxmed_qa_doctor_id
    AND status = 'active'
    AND deleted_at IS NULL
)
AND NOT EXISTS (
  SELECT 1
  FROM subscription_payment_routes
  WHERE entity_type = 'doctor'
    AND entity_id = @mxmed_qa_doctor_id
    AND deleted_at IS NULL
)
AND NOT EXISTS (
  SELECT 1
  FROM subscription_checkout_intents
  WHERE entity_type = 'doctor'
    AND entity_id = @mxmed_qa_doctor_id
    AND deleted_at IS NULL
);

-- Postcondition evidence: this must show exactly one active Optimo annual row and no payment artifacts.
SELECT
  'post_active_subscriptions_990106' AS check_name,
  id,
  entity_type,
  entity_id,
  doctor_id,
  plan_code,
  billing_period,
  status,
  starts_at,
  expires_at,
  source,
  deleted_at
FROM profile_subscriptions
WHERE entity_type = 'doctor'
  AND entity_id = @mxmed_qa_doctor_id
  AND status = 'active'
  AND deleted_at IS NULL;

SELECT
  'active_counts_990106' AS check_name,
  SUM(CASE WHEN plan_code = 'optimum' AND billing_period = 'annual' THEN 1 ELSE 0 END) AS active_optimum_annual,
  COUNT(*) AS active_total
FROM profile_subscriptions
WHERE entity_type = 'doctor'
  AND entity_id = @mxmed_qa_doctor_id
  AND status = 'active'
  AND deleted_at IS NULL;

SELECT
  'payment_artifacts_990106' AS check_name,
  (SELECT COUNT(*) FROM subscription_payment_routes WHERE entity_type = 'doctor' AND entity_id = @mxmed_qa_doctor_id AND deleted_at IS NULL) AS routes_total,
  (SELECT COUNT(*) FROM subscription_checkout_intents WHERE entity_type = 'doctor' AND entity_id = @mxmed_qa_doctor_id AND deleted_at IS NULL) AS checkouts_total,
  (
    SELECT COUNT(*)
    FROM subscription_payment_intents pi
    JOIN subscription_checkout_intents ci
      ON ci.uuid = pi.checkout_intent_uuid
    WHERE ci.entity_type = 'doctor'
      AND ci.entity_id = @mxmed_qa_doctor_id
      AND pi.deleted_at IS NULL
      AND ci.deleted_at IS NULL
  ) AS payment_intents_total,
  (
    SELECT COUNT(*)
    FROM subscription_payment_events pe
    JOIN subscription_payment_intents pi
      ON pi.uuid = pe.payment_intent_uuid
    JOIN subscription_checkout_intents ci
      ON ci.uuid = pi.checkout_intent_uuid
    WHERE ci.entity_type = 'doctor'
      AND ci.entity_id = @mxmed_qa_doctor_id
      AND pe.deleted_at IS NULL
      AND pi.deleted_at IS NULL
      AND ci.deleted_at IS NULL
  ) AS payment_events_total;

COMMIT;
