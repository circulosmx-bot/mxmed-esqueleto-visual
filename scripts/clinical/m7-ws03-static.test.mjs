import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const ui = readFileSync(resolve(root, 'assets/js/clinical/m7-ws03.js'), 'utf8');
const shell = readFileSync(resolve(root, 'assets/js/clinical/m7-workspace.js'), 'utf8');
const api = readFileSync(resolve(root, 'api/clinical/index.php'), 'utf8');
const html = readFileSync(resolve(root, 'index.html'), 'utf8');

assert.match(ui, /\['NOT_REVIEWED','Sin revisión'\]/);
assert.match(ui, /if\(item\.state==='NOT_REVIEWED'\) continue/);
assert.match(ui, /data\.row_version=examVersion/);
assert.match(ui, /Idempotency-Key/);
assert.match(ui, /row_version:Number\(row\.row_version\)/);
assert.match(ui, /M6_WRITE_WINDOW_BLOCKED/);
assert.match(ui, /ENCOUNTER_TERMINAL/);
assert.doesNotMatch(ui, /\/patients\/.*physical-exam|\/finalize|mxmedExplicitStartEncounter/);
assert.match(shell, /ws03\?\.isDirty\(\)/);
assert.match(shell, /ws03\?\.remember\(\)/);
assert.match(api, /AND section_type IN \('reason_evolution', 'assessment', 'plan', 'physical_exam'\)/);
assert.match(api, /FROM clinical_observations WHERE encounter_id = :encounter_id/);
assert.match(api, /'observations' => \$structuredObservations/);
assert.match(html, /data-m7-measurements-form/);
assert.match(html, /data-m7-exam-systems/);
assert.match(html, /data-m7-section="documents" disabled/);
assert.match(html, /data-m7-section="finalize" disabled/);

console.log('M7 WS03 static authority: PASS');
