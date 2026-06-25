-- DRAFT ONLY - DO NOT RUN.
-- DEV/LOCAL ONLY - checkout-intents QA doctor fixture draft.
-- This file is not a migration and must not run automatically.
-- Do not use in production.
-- This draft does not alter schema.
-- This draft does not clean data.
-- This draft does not touch doctors 1, 2 or 3.
-- This draft does not create profile_subscriptions.
-- This draft does not create subscription_checkout_intents.
-- This draft does not create subscription_contract_acceptances.
-- This draft does not create subscription_payment_intents.
-- This draft does not create subscription_payment_events.
-- This draft does not implement provider, webhook, billing, capabilities, public profile or SEO.
-- If executed in a future authorized DEV/local DB microphase, keep the row as a persistent local fixture.
-- No cleanup policy: do not update, delete, truncate, drop or manually roll back this fixture destructively.
-- Based on PP-Decisiones 96.
-- Collation fix:
--   Future execution previously failed with ERROR 1267 Illegal mix of collations.
--   Comparisons against profiles_doctors.display_name use explicit utf8mb4_unicode_ci.
--   Second fix also normalizes the display_name variable/literal to utf8mb4_unicode_ci.
--   Third fix removes display_name comparisons from the executable INSERT guard.
--   The INSERT guard uses doctor_id only; display_name checks remain read-only validations.
--   Fourth fix removes all executable display_name comparisons after another ERROR 1267.
--   All executable validations now depend on doctor_id only.
--   This does not change fixture scope and does not authorize execution in this microphase.

-- Fixture target:
--   table: profiles_doctors
--   display_name: QA Checkout Doctor Libre
--   purpose: provide a doctor without active subscription for future controlled 201 checkout-intents QA.

-- Proposed DEV/local-only variables.
-- The high doctor_id avoids doctors 1, 2 and 3 and remains easy to identify in local QA.
SET @mxmed_checkout_qa_doctor_id := CONVERT('900001' USING utf8mb4) COLLATE utf8mb4_unicode_ci;
SET @mxmed_checkout_qa_display_name := CONVERT('QA Checkout Doctor Libre' USING utf8mb4) COLLATE utf8mb4_unicode_ci;

-- Future pre-execution validations.
-- 1) Confirm the proposed doctor_id does not already exist.
SELECT
  doctor_id,
  display_name,
  profile_status,
  is_public_candidate
FROM profiles_doctors
WHERE doctor_id = @mxmed_checkout_qa_doctor_id;

-- 2) Confirm doctors 1, 2 and 3 remain visible for inspection only.
SELECT
  doctor_id,
  display_name,
  profile_status,
  is_public_candidate
FROM profiles_doctors
WHERE doctor_id IN ('1', '2', '3');

-- 3) Inspect the next numeric doctor_id available in DEV/local before choosing a final id.
SELECT
  COALESCE(MAX(CAST(doctor_id AS UNSIGNED)), 0) + 1 AS next_numeric_doctor_id
FROM profiles_doctors
WHERE doctor_id REGEXP '^[0-9]+$';

-- 4) Confirm the proposed fixture has no active or historical subscription rows.
SELECT COUNT(*) AS fixture_profile_subscriptions_rows
FROM profile_subscriptions
WHERE entity_type = 'doctor'
  AND entity_id = @mxmed_checkout_qa_doctor_id;

-- 5) Confirm the proposed fixture has no pending checkout intent rows.
SELECT COUNT(*) AS fixture_pending_checkout_intents_rows
FROM subscription_checkout_intents
WHERE entity_type = 'doctor'
  AND entity_id = @mxmed_checkout_qa_doctor_id
  AND status = 'pending_payment'
  AND deleted_at IS NULL;

-- 6) Capture base counts before any future functional QA.
SELECT COUNT(*) AS subscription_checkout_intents_rows FROM subscription_checkout_intents;
SELECT COUNT(*) AS subscription_contract_acceptances_rows FROM subscription_contract_acceptances;
SELECT COUNT(*) AS profile_subscriptions_rows FROM profile_subscriptions;
SELECT COUNT(*) AS subscription_payment_intents_rows FROM subscription_payment_intents;
SELECT COUNT(*) AS subscription_payment_events_rows FROM subscription_payment_events;

-- Draft fixture insert.
-- Execute only in a future explicitly authorized DEV/local DB microphase.
-- This insert intentionally targets only profiles_doctors.
-- It does not use ON DUPLICATE KEY behavior so an unexpected existing fixture fails visibly.
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
  @mxmed_checkout_qa_doctor_id,
  @mxmed_checkout_qa_display_name,
  'Dr.',
  NULL,
  'QA-CHECKOUT-900001',
  NULL,
  'Medicina General',
  '[]',
  'Fixture DEV/local para QA controlada de checkout-intents. No usar en produccion.',
  NULL,
  NULL,
  NULL,
  'active',
  1
WHERE NOT EXISTS (
  SELECT 1
  FROM profiles_doctors
  WHERE doctor_id = @mxmed_checkout_qa_doctor_id
);

-- Future post-execution validations.
-- 1) The fixture exists and is visible to SubscriptionEntityResolverService.
SELECT
  doctor_id,
  display_name,
  profile_status,
  is_public_candidate
FROM profiles_doctors
WHERE doctor_id = @mxmed_checkout_qa_doctor_id;

-- 2) The fixture still has zero profile_subscriptions.
SELECT COUNT(*) AS fixture_profile_subscriptions_rows
FROM profile_subscriptions
WHERE entity_type = 'doctor'
  AND entity_id = @mxmed_checkout_qa_doctor_id;

-- 3) The fixture still has zero pending checkout intents before the future positive 201 QA.
SELECT COUNT(*) AS fixture_pending_checkout_intents_rows
FROM subscription_checkout_intents
WHERE entity_type = 'doctor'
  AND entity_id = @mxmed_checkout_qa_doctor_id
  AND status = 'pending_payment'
  AND deleted_at IS NULL;

-- 4) Re-capture base counts before future endpoint QA.
SELECT COUNT(*) AS subscription_checkout_intents_rows FROM subscription_checkout_intents;
SELECT COUNT(*) AS subscription_contract_acceptances_rows FROM subscription_contract_acceptances;
SELECT COUNT(*) AS profile_subscriptions_rows FROM profile_subscriptions;
SELECT COUNT(*) AS subscription_payment_intents_rows FROM subscription_payment_intents;
SELECT COUNT(*) AS subscription_payment_events_rows FROM subscription_payment_events;
