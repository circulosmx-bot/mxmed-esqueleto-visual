'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../..');
const app = fs.readFileSync(path.join(root, 'assets/js/app.js'), 'utf8');
const policyUi = fs.readFileSync(path.join(root, 'assets/js/subscription-policy-ui.js'), 'utf8');
const previewAdapter = fs.readFileSync(path.join(root, 'assets/js/subscription-preview-qa-adapter.js'), 'utf8');
const index = fs.readFileSync(path.join(root, 'index.html'), 'utf8');

assert.strictEqual(app.includes('SUBSCRIPTION_PLAN_PRICE_MATRIX'), false);
assert.strictEqual(app.includes('SUBSCRIPTION_PLAN_RANK'), false);
assert.strictEqual(app.includes('UI_PLAN_TO_BACKEND_PLAN'), false);
assert.strictEqual(app.includes('function qaReviewPreviewActive()'), true);
assert.strictEqual(app.includes("flowType === 'downgrade_at_renewal'"), true);
assert.strictEqual(app.includes('Disponible al renovar'), true);
assert.strictEqual(app.includes('data-subp-upgrade-calculation'), true);
assert.strictEqual(app.includes('data-subp-payments-lifecycle="scheduled_downgrade"'), true);
assert.strictEqual(app.includes('data-subp-payments-lifecycle="grace"'), true);
assert.strictEqual(app.includes('data-subp-payments-lifecycle="archived_read_only"'), true);
assert.strictEqual(app.includes('aria-current="${isCurrent ? \'true\' : \'false\'}"'), true);
assert.strictEqual(app.includes('data-subp-scheduled-addon-impacts'), false);
assert.strictEqual(app.includes('data-subp-addon-eligibility'), false);
assert.strictEqual(app.includes('data-subp-policy-denials'), false);
assert.strictEqual(policyUi.includes('COMMERCIAL_PLAN_PRESENTATION'), true);
assert.strictEqual(policyUi.includes(').concat(quotaFeatureLabels(plan))'), false);
assert.strictEqual(previewAdapter.includes('fetch('), false);
assert.strictEqual(previewAdapter.includes('XMLHttpRequest'), false);
assert(index.indexOf('subscription-preview-qa-adapter.js') < index.indexOf('assets/js/app.js'));
assert.strictEqual((policyUi.match(/themeToken: 'basico'/g) || []).length, 1);
assert.strictEqual((policyUi.match(/themeToken: 'estandar'/g) || []).length, 1);
assert.strictEqual((policyUi.match(/themeToken: 'optimo'/g) || []).length, 1);
assert.strictEqual((policyUi.match(/themeToken: 'pro'/g) || []).length, 1);

process.stdout.write(JSON.stringify({ ok: true, suite: 'subscription-visual-reconciliation', assertions: 23 }) + '\n');
