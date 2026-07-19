'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'assets/js/subscription-preview-qa-adapter.js'), 'utf8');
const sandbox = { window: {} };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'subscription-preview-qa-adapter.js' });

const adapter = sandbox.window.MXMedSubscriptionPreviewQAAdapter;
assert(adapter, 'QA preview adapter must be exported');
assert.strictEqual(adapter.contractVersion, 'MXMED_SUBSCRIPTIONS_PREVIEW_QA_FIXTURE_V1');
const readModel = { qa_review_fixture: true };
const local = { hostname: '127.0.0.1', protocol: 'http:', search: '?mxmed_subscription_review=1' };
assert.strictEqual(adapter.enabled(local, readModel), true);
assert.strictEqual(adapter.enabled({ hostname: 'mxmed.mx', protocol: 'https:', search: '?mxmed_subscription_review=1' }, readModel), false);
assert.strictEqual(adapter.enabled({ hostname: '127.0.0.1', protocol: 'http:', search: '' }, readModel), false);
assert.strictEqual(adapter.enabled(local, {}), false);
assert.strictEqual(source.includes('fetch('), false);
assert.strictEqual(source.includes('XMLHttpRequest'), false);

(async () => {
  const monthly = await adapter.preview({
    route_type: 'new_subscription',
    target_plan_code: 'standard',
    billing_period: 'monthly'
  }, { location: local, readModel });
  assert.strictEqual(monthly.ok, true);
  assert.strictEqual(monthly.data.amount_cents, 327000);
  assert.strictEqual(monthly.data.pricing_contract.unit_amount_cents, 109000);
  assert.strictEqual(monthly.data.pricing_contract.annual_savings_amount_cents, 309000);
  assert.strictEqual(monthly.data.pricing_contract.payment_execution_enabled, false);
  assert.strictEqual(monthly.data.next_action.enabled, false);

  const upgrade = await adapter.preview({
    route_type: 'upgrade_subscription',
    current_plan_code: 'standard',
    target_plan_code: 'professional',
    billing_period: 'annual'
  }, { location: local, readModel });
  assert.strictEqual(upgrade.ok, true);
  assert.strictEqual(upgrade.data.current_price_cents, 999000);
  assert.strictEqual(upgrade.data.target_price_cents, 2199000);
  assert.strictEqual(upgrade.data.adjustment_amount_cents, 1160548);
  assert.strictEqual(upgrade.data.remaining_days, 353);
  assert.strictEqual(upgrade.data.period_days, 365);

  const failure = await adapter.preview({
    route_type: 'upgrade_subscription',
    current_plan_code: 'standard',
    target_plan_code: 'optimum',
    billing_period: 'annual'
  }, {
    location: { ...local, search: '?mxmed_subscription_review=1&mxmed_preview=error' },
    readModel
  });
  assert.strictEqual(failure.ok, false);
  assert.strictEqual(failure.httpStatus, 503);
  assert.strictEqual(failure.errorCode, 'qa_preview_unavailable');

  const disabled = await adapter.preview({ route_type: 'new_subscription' }, {
    location: { hostname: 'mxmed.mx', protocol: 'https:', search: '?mxmed_subscription_review=1' },
    readModel
  });
  assert.strictEqual(disabled.ok, false);
  assert.strictEqual(disabled.errorCode, 'qa_fixture_disabled');

  process.stdout.write(JSON.stringify({ ok: true, suite: 'subscription-preview-qa-adapter', assertions: 25 }) + '\n');
})().catch((error) => {
  process.stderr.write(String(error && error.stack || error) + '\n');
  process.exitCode = 1;
});
