import assert from 'node:assert/strict';
import fs from 'node:fs';

const html=fs.readFileSync('index.html','utf8');
const js=fs.readFileSync('assets/js/app.js','utf8');
const api=fs.readFileSync('api/clinical/index.php','utf8');
const service=fs.readFileSync('api/_lib/clinical_prescription_templates.php','utf8');
const migration=fs.readFileSync('modules/clinical/db/migrations/2026_10_13_41_prescription_templates.sql','utf8');

assert.match(migration,/CREATE TABLE IF NOT EXISTS clinical_prescription_templates/);
assert.doesNotMatch(migration,/clinical_consent_templates|patient_id|encounter_id|signature|fingerprint|qr/i);
assert.match(api,/\(\$segments\[2\]\?\?''\)===\'prescription-templates\'/);
assert.match(api,/'doctors\/\{doctor_id\}\/prescription-templates'/);
assert.match(api,/clinical_require_doctor_context\(\$doctorAuthorityRoute\)/);
assert.match(api,/doctor scope mismatch/);
assert.match(api,/prescription-templates/);
assert.match(service,/clinical_prescription_template_(list|get|create|update|duplicate|archive|delete)/);
assert.match(service,/clinical_prescription_template_medication_fields/);
for(const forbidden of ['patient_id','encounter_id','allergies','current_medications','active_problems','signature','qr','fingerprint','private_notes']){
  assert.equal(service.includes(`'${forbidden}'`),false,`service whitelist must omit ${forbidden}`);
}
assert.match(html,/id="ne_rx_use_template">Usar plantilla/);
assert.match(html,/data-rx-template-safety/);
assert.match(html,/id="modalPrescriptionTemplateFlow"/);
assert.match(html,/id="ne_rx_save_template">Guardar como plantilla/);
assert.doesNotMatch(html,/Guardar como plantilla de receta|Al emitir, podrá guardar esta receta|ne_rx_template_hint/);
assert.match(js,/TEMPLATE|rxTemplateContentFromCurrent|rxApplyTemplateContent/);
assert.match(js,/rxTemplateMedicationFields = \['name','presentation','dose','route','frequency','duration','quantity','instructions'\]/);
assert.doesNotMatch(js,/rxTemplateMedicationFields[^\n]*private_notes/);
const canonicalBody=js.slice(js.indexOf('const rxCanonicalBody'),js.indexOf('const rxShowCapture'));
assert.doesNotMatch(canonicalBody,/template_uuid|template_version/);
assert.match(js,/Esta plantilla reemplazará los medicamentos capturados/);
assert.match(js,/stageSafety\.innerHTML=.*Seguridad actual/);
assert.match(js,/ELIMINAR/);
assert.match(js,/include_diagnostic/);
assert.match(js,/include_observations/);
assert.match(js,/include_header/);

console.log('REC-TPL02 static contract: PASS');
