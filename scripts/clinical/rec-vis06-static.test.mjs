import assert from 'node:assert/strict';
import fs from 'node:fs';

const html=fs.readFileSync('private-app.php','utf8');
const css=fs.readFileSync('assets/css/style.css','utf8');
const app=fs.readFileSync('assets/js/app.js','utf8');
const writer=fs.readFileSync('api/_lib/clinical_prescription_v2.php','utf8');
const host=fs.readFileSync('assets/js/clinical/prescription-editor-host.js','utf8');

assert.match(html,/<section class="rx-ux-panel" data-rx-view="landing">\s*<h2>RECETAS<\/h2>/);
assert.match(html,/rx-ux-history-head"><h2>CONSULTAR RECETAS<\/h2>/);
assert.match(html,/<h2 id="rx-editor-title" tabindex="-1">NUEVA RECETA<\/h2>/);
assert.match(html,/MEDICAMENTOS AGREGADOS \(<span id="ne_rx_count">0<\/span>\)/);
assert.equal((html.match(/id="ne_rx_add"/g)||[]).length,1);
assert.match(css,/data-ne-rx-state="collapsed"/);
assert.match(css,/has\(\.ne-rx-row\[data-ne-rx-state="expanded"\]\) \.ne-rx-add-row\{display:none!important\}/);
assert.match(host,/stage === 2 \? 'Continuar a vista previa →'/);
assert.match(app,/data-ne-rx-existing', '1'/);
assert.match(writer,/new ClinicalIdempotentCreateExecutor\(\$pdo\)\)->execute/);
assert.doesNotMatch(writer,/\$service->idempotentCreate\('CREATE_ENCOUNTER_DOCUMENT'/);
assert.match(writer,/clinical_encounter_integrity_assert_schema_ready\(\$pdo\)/);
assert.match(writer,/clinical_prescription_v2_encounter\(\$pdo, \$encounterKey, \$patientId/);

console.log('REC-VIS06 visual and patient-level writer contract: PASS');
