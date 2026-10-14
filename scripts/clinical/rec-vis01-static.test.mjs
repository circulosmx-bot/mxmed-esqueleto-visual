import assert from 'node:assert/strict';
import fs from 'node:fs';

const html=fs.readFileSync('index.html','utf8');
const app=fs.readFileSync('assets/js/app.js','utf8');
const host=fs.readFileSync('assets/js/clinical/prescription-editor-host.js','utf8');
const history=fs.readFileSync('assets/js/clinical/rec-ux01.js','utf8');
const css=fs.readFileSync('assets/css/style.css','utf8');

assert.equal((html.match(/data-rx-host-step=/g)||[]).length,5);
for(const label of ['Contexto clínico','Medicamentos','Vista previa','Firma','Revisión y emisión']) assert.match(html,new RegExp(label,'i'));
assert.match(html,/data-rx-editor-workspace/);
assert.match(html,/data-rx-encounter-link/);
assert.match(host,/mount\.appendChild\(body\)/);
assert.match(host,/restoreModalBody/);
assert.match(host,/aria-current','step'/);
assert.match(history,/host:'embedded'/);
assert.match(app,/window\.mxmedOpenCanonicalPrescription/);
assert.match(app,/mxmedPrescriptionSelectedEncounterKey/);
assert.match(app,/window\.mxmedPrescriptionStandaloneIntent===true/);
assert.match(app,/window\.mxmedPrescriptionHost\?\.isEmbedded\(\)/);
assert.match(app,/rxAssertClinicalContextFresh/);
assert.match(app,/prescription-qr-sessions/);
assert.match(css,/\.rx-editor-stepper/);
assert.match(css,/\.rx-editor-actions\{position:sticky/);
assert.doesNotMatch(host,/fetch\(|prescription_snapshot|binding_fingerprint/);
console.log('REC-VIS01 embedded host contract: PASS');
