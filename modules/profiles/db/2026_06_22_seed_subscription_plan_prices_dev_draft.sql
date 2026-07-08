-- DRAFT ONLY - DO NOT RUN.
-- DEV/LOCAL ONLY - placeholder subscription plan prices for local QA.
-- This is a conceptual seed draft, not final executable SQL.
-- This draft does not modify any real database in this microphase.
-- These prices are placeholders and are not production pricing.
-- These prices are not commercially approved.
-- The free plan is intentionally excluded from this v1 paid checkout seed.
-- This draft does not implement checkout, payments, webhooks, billing or capabilities.
-- Based on PP-Decisiones 70.

-- DRAFT ONLY - DO NOT RUN.
-- DEV/LOCAL ONLY - annual prices in MXN cents aligned to the current UI reference matrix.
INSERT INTO subscription_plan_prices (
  uuid,
  plan_code,
  billing_period,
  amount_cents,
  currency,
  price_source,
  price_version,
  valid_from,
  valid_until,
  is_active,
  source,
  notes
) VALUES
  (
    'b1111111-2026-4220-8220-000000000001',
    'basic',
    'annual',
    699000,
    'MXN',
    'subscription_plan_prices_dev_seed',
    'mxmed-dev-pricing-2026-v2-cents',
    '2026-06-22 00:00:00',
    NULL,
    1,
    'mxmed_subscription_plan_price_dev_seed_v1',
    'DEV/LOCAL annual price in MXN cents, not production pricing'
  ),
  (
    'b1111111-2026-4220-8220-000000000002',
    'standard',
    'annual',
    999000,
    'MXN',
    'subscription_plan_prices_dev_seed',
    'mxmed-dev-pricing-2026-v2-cents',
    '2026-06-22 00:00:00',
    NULL,
    1,
    'mxmed_subscription_plan_price_dev_seed_v1',
    'DEV/LOCAL annual price in MXN cents, not production pricing'
  ),
  (
    'b1111111-2026-4220-8220-000000000003',
    'optimum',
    'annual',
    1299000,
    'MXN',
    'subscription_plan_prices_dev_seed',
    'mxmed-dev-pricing-2026-v2-cents',
    '2026-06-22 00:00:00',
    NULL,
    1,
    'mxmed_subscription_plan_price_dev_seed_v1',
    'DEV/LOCAL annual price in MXN cents, not production pricing'
  ),
  (
    'b1111111-2026-4220-8220-000000000004',
    'professional',
    'annual',
    2199000,
    'MXN',
    'subscription_plan_prices_dev_seed',
    'mxmed-dev-pricing-2026-v2-cents',
    '2026-06-22 00:00:00',
    NULL,
    1,
    'mxmed_subscription_plan_price_dev_seed_v1',
    'DEV/LOCAL annual price in MXN cents, not production pricing'
  );
