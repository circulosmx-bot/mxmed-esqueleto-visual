import assert from 'node:assert/strict';
import fs from 'node:fs';

const api = fs.readFileSync('api/clinical/index.php', 'utf8');
const projection = fs.readFileSync('api/_lib/clinical_prescription_context.php', 'utf8');
const ui = fs.readFileSync('assets/js/app.js', 'utf8');

assert.match(api, /clinical_require_doctor_context\(\$routeName\)/);
assert.match(api, /patients\/{patient_id}\/prescription-context/);
assert.match(projection, /ClinicalLongitudinalAntecedents/);
assert.match(projection, /ClinicalLongitudinalMedications/);
assert.match(projection, /ClinicalLongitudinalProblems/);
assert.match(projection, /ClinicalEncounterIntegrityRepository/);
assert.match(projection, /America\/Mexico_City/);
assert.match(ui, /rxAssertClinicalContextFresh/);
assert.match(ui, /Información de alergias no disponible/);
assert.match(ui, /Alergias no revisadas/);
assert.match(ui, /Sin alergias conocidas registradas/);
assert.doesNotMatch(ui.slice(ui.indexOf('const collectPrescriptionClinicalContext'),
  ui.indexOf('window.mxmedBuildClinicalHeaderData')), /#t-historia|currentDraft|getRxDraft/);

console.log('REC-CTX01 static safety contract: PASS');
