import { readFileSync } from 'node:fs';

const root = new URL('../../../', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');
const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

const markup = read('index.html');
const script = read('assets/js/app.js');
const css = read('assets/css/style.css');
const route = read('api/agenda/index.php');

assert(markup.includes('id="ag_event_actions_modal"'), 'uses existing appointment detail modal');
assert(markup.includes('id="ag_event_identity_review_wrap"'), 'renders a bounded identity review block in the detail modal');
assert(markup.includes('Declaración del paciente') && markup.includes('Resolución de identidad'), 'separates declaration and identity resolution labels');
assert(markup.includes('Mantener como paciente nuevo'), 'includes the keep-new action');
assert(script.includes('getAmbiguousPatientReconciliation') && script.includes('resolveAmbiguousPatientReconciliation'), 'uses the private reconciliation endpoint from admin agenda only');
assert(script.includes("action: 'relink_existing'") && script.includes("action: 'keep_new'"), 'submits only the two permitted actions');
const reviewMarkup = markup.slice(markup.indexOf('id="ag_event_identity_review_wrap"'), markup.indexOf('id="ag_event_timeline_wrap"'));
assert(script.includes("button.dataset.agIdentityRelink = id") && !reviewMarkup.includes('patient_id'), 'candidate internal identifiers are not rendered in the review markup');
assert(css.includes('.mx-ag-identity-review') && css.includes('@media (max-width: 767.98px)'), 'includes desktop and mobile review styles');
assert(route.includes("$sub === 'identity-reconciliation'") && route.includes('AmbiguousPatientReconciliationController'), 'private appointment route owns reconciliation');

console.log('AmbiguousPatientReconciliationUiContractTest PASS');
