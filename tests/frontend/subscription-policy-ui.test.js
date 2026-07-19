'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'assets/js/subscription-policy-ui.js'), 'utf8');
const sandbox = { window: {} };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'subscription-policy-ui.js' });

const ui = sandbox.window.MXMedPlanCapabilityUI;
assert(ui, 'policy UI adapter must be exported');

const denialCodes = [
  'profile_not_approved', 'ownership_required', 'ownership_disputed', 'ownership_suspended',
  'capability_not_in_plan', 'addon_required', 'addon_not_eligible', 'implementation_not_available',
  'capability_pending_activation', 'dependency_missing', 'quota_exhausted', 'actor_role_not_allowed',
  'actor_scope_not_allowed', 'subscription_pending_payment', 'subscription_in_grace',
  'capability_grace_limited', 'capability_read_only', 'capability_suspended', 'profile_type_not_supported'
];
assert.deepStrictEqual(Array.from(ui.denialCodes), denialCodes);
denialCodes.forEach((code) => {
  const mapped = ui.mapDenial(code);
  assert.strictEqual(mapped.code, code);
  assert(mapped.message.length > 20);
  assert(mapped.state);
  assert(mapped.action);
});

const readModel = {
  policy_version: 'MXMED_PLAN_CAPABILITY_POLICY_V1',
  plan_aliases: { basico: 'basic', pro: 'professional' },
  profile_approval_state: 'approved',
  ownership_state: 'claimed',
  purchase_allowed: true,
  admin_allowed: true,
  commercial_state: 'active',
  scheduled_addon_impacts: [{ code: 'call_center_integral', status: 'cancel_at_period_end' }],
  grace: { ends_at: '2026-08-02 00:00:00' },
  denial_reasons: ['implementation_not_available'],
  archived_module_summaries: [],
  future_capabilities: [{ code: 'ai_agenda_agent', operational: false }],
  addon_eligibility: { call_center_integral: { code: 'call_center_integral', label: 'Call Center Integral', eligible: false, purchasable: false, operational: false } },
  capabilities: {
    profile_publication: { allowed: true, state: 'enabled', denial_reason: null },
    ai_agenda_agent: { allowed: false, state: 'blocked_dependency', denial_reason: 'implementation_not_available' }
  },
  plan_catalog: [
    { code: 'free', label: 'Gratuito', rank: 0, capabilities: [{ code: 'profile_publication', label: 'Perfil', operational: true }], prices: [] },
    { code: 'basic', label: 'Básico', rank: 1, capabilities: [{ code: 'profile_publication', label: 'Perfil', operational: true }], quotas: { public_gallery: { value: 21 }, ai_image_generation: { value: 3 }, ai_content_writing: { value: 15 } }, prices: [{ billing_period: 'annual', amount_cents: 699000, currency: 'MXN', price_version: 'qa-v1' }] },
    { code: 'standard', label: 'Estándar', rank: 2, capabilities: [{ code: 'agenda', label: 'Agenda', operational: true }], prices: [] },
    { code: 'optimum', label: 'Óptimo', rank: 3, capabilities: [{ code: 'clinical_record', label: 'Expediente', operational: true }], prices: [] },
    { code: 'professional', label: 'Profesional', rank: 4, capabilities: [{ code: 'ai_agenda_agent', label: 'Agente IA', operational: false }], prices: [] }
  ]
};

assert.strictEqual(ui.normalizePlanCode('basico', readModel), 'basic');
assert.strictEqual(ui.normalizePlanCode('premium', readModel), null);
const plans = ui.plansFromReadModel(readModel);
assert.strictEqual(plans.length, 4);
assert.strictEqual(plans[0].id, 'basic');
assert.strictEqual(plans[0].rank, 1);
assert.strictEqual(plans[0].prices.annual.amount_cents, 699000);
assert(plans[3].features.some((feature) => feature.includes('no operativa')));
assert(plans[0].features.some((feature) => feature.includes('Galería: 21')));
const status = ui.statusPresentation(readModel);
assert.strictEqual(status.purchaseAllowed, true);
assert.strictEqual(status.capabilityStates.length, 2);
assert.strictEqual(status.futureCapabilities.length, 1);
assert.strictEqual(status.grace.ends_at, '2026-08-02 00:00:00');
assert.strictEqual(status.scheduledAddOnImpacts.length, 1);
assert.strictEqual(status.addOnEligibility.length, 1);
const chips = ui.summaryFeatureChips(readModel);
assert.strictEqual(chips.length, 5);
assert.strictEqual(chips[4].operational, false);

process.stdout.write(JSON.stringify({ ok: true, suite: 'subscription-policy-ui', assertions: 56 }) + '\n');
