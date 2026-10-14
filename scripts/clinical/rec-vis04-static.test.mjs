import assert from 'node:assert/strict';
import fs from 'node:fs';

const html=fs.readFileSync('index.html','utf8');
const app=fs.readFileSync('assets/js/app.js','utf8');
const host=fs.readFileSync('assets/js/clinical/prescription-editor-host.js','utf8');
const css=fs.readFileSync('assets/css/style.css','utf8');
const projection=fs.readFileSync('api/_lib/clinical_prescription_context.php','utf8');

assert.match(app,/patients\/\$\{encodeURIComponent\(patientId\)\}\/prescription-context/);
assert.match(app,/rxClinicalContextRevision/);
assert.match(app,/rxAssertClinicalContextFresh/);
assert.match(app,/prescriptionCompactAllergy/);
assert.match(app,/Alergia activa/);
assert.match(app,/Alergias no revisadas/);
assert.match(app,/Sin alergias conocidas registradas/);
assert.match(app,/Información de alergias no disponible/);
assert.match(app,/medicamento\$\{count===1\?'':'s'\} registrado/);
assert.match(app,/problema\$\{count===1\?'':'s'\} activo/);
assert.match(app,/data-rx-context-toggle aria-expanded="false"/);
assert.match(app,/aria-controls="rx-clinical-context-detail"/);
assert.match(app,/data-rx-context-detail hidden/);
assert.match(app,/Contexto clínico al/);
assert.match(app,/window\.mxmedPrescriptionHost\?\.caller/);
assert.match(host,/workspace\.dataset\.rxCaller=caller/);
assert.match(host,/const global=options\?\.caller==='global'/);
assert.match(html,/data-rx-encounter-choice[\s\S]*id="ne_rx_context"[\s\S]*id="ne_rx_dx_block"/);
assert.match(css,/\.rx-safety-summary/);
assert.match(css,/\.rx-context-toggle:focus-visible/);
assert.match(css,/\.rx-context-detail\[hidden\]/);
assert.match(projection,/revision.*hash\('sha256'/s);
assert.doesNotMatch(app.slice(app.indexOf('const renderRecetaContextPanel'),app.indexOf('const rxFetchClinicalContext')),
  /<div class="rx-context-card"><strong>Consulta<\/strong>[\s\S]*<div class="rx-context-card"><strong>Alergias<\/strong>[\s\S]*<div class="rx-context-card"><strong>Medicación actual<\/strong>/);

console.log('REC-VIS04 compact clinical context contract: PASS');
