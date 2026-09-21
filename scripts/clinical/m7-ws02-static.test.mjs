import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const ui = readFileSync(resolve(root, 'assets/js/clinical/m7-workspace.js'), 'utf8');
const api = readFileSync(resolve(root, 'api/clinical/index.php'), 'utf8');
const html = readFileSync(resolve(root, 'index.html'), 'utf8');
const save = ui.slice(ui.indexOf('async function saveSection(){'), ui.indexOf('\n  function reset(){'));

assert.match(ui, /reason:'reason_evolution', assessment:'assessment', plan:'plan'/);
assert.match(ui, /sectionVersion = row \? Number\(row\.row_version\) : null/);
assert.match(save, /data\.row_version = expectedVersion/);
assert.match(save, /method:'PUT'/);
assert.match(save, /VERSION_CONFLICT/);
assert.match(save, /ENCOUNTER_TERMINAL/);
assert.match(save, /M6_WRITE_WINDOW_BLOCKED/);
assert.doesNotMatch(save, /\/patients\/\$\{[^}]+\}\/(history|physical-exam)/);
assert.doesNotMatch(save, /mxmedExplicitStartEncounter|\/finalize|\/observations|\/documents/);
assert.match(api, /AND section_type IN \('reason_evolution', 'assessment', 'plan'\)/);
assert.match(api, /'sections' => \$structuredSections/);
assert.match(html, /data-m7-section="measurements" disabled/);
assert.match(html, /data-m7-section="exam" disabled/);
assert.match(html, /data-m7-section="documents" disabled/);
assert.match(html, /data-m7-section="finalize" disabled/);
console.log('M7 WS02 static authority: PASS');
