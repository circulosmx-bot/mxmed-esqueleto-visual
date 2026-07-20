const assert = require('node:assert/strict');
const fs = require('node:fs');

const source = fs.readFileSync('assets/js/app.js', 'utf8');

assert.match(source, /feature_access/);
assert.match(source, /normalizeFeatureAccess/);
assert.match(source, /SUBSCRIPTION_VISIBLE_FEATURE_CAPABILITIES/);
assert.match(source, /data\.currentFeatureAccess/);
assert.match(source, /available: decision\.available/);
assert.doesNotMatch(source, /reason_code.*textContent/);
assert.doesNotMatch(source, /capability_id.*textContent/);

console.log('subscription-feature-access-binding.test PASS');
