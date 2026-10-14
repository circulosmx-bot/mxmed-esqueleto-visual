import assert from 'node:assert/strict';
import fs from 'node:fs';
import {execFileSync} from 'node:child_process';

const html=fs.readFileSync('private-app.php','utf8');
const css=fs.readFileSync('assets/css/style.css','utf8');
const app=fs.readFileSync('assets/js/app.js','utf8');

const stage2=html.match(/<section data-rx-engine-stage="2"[\s\S]*?<\/section>/)?.[0]||'';
const stage3=html.match(/<div id="ne_rx_review"[\s\S]*?<div id="ne_rx_sign"/)?.[0]||'';
assert.doesNotMatch(stage2,/ne_rx_professional_header|Encabezado profesional/);
assert.match(stage2,/ne-rx-added-head[\s\S]*MEDICAMENTOS AGREGADOS[\s\S]*id="ne_rx_add"/);
assert.match(stage3,/PRESENTACIÓN DEL DOCUMENTO/);
for(const mode of ['hidden','data_only','logo_and_data']) assert.match(stage3,new RegExp(`value="${mode}"`));
assert.match(css,/\.ne-rx-added-head\{[^}]*justify-content:space-between/);
assert.match(css,/\.rx-medication-composition:has\(\.ne-rx-row\[data-ne-rx-state="expanded"\]\) \.ne-rx-added-head\{display:none!important\}/);
assert.match(css,/@media\(max-width:850px\)[^{]*\{[^}]*\.rx-editor-embedded-body \.ne-rx-added-head\{[^}]*flex-direction:column/);
assert.match(app,/presentation: \{ version: 2, professional_header: rxPresentationMode\(\) \}/);
assert.match(app,/rxPresentation\?\.addEventListener\('change'/);
assert.match(app,/rxReviewedFingerprint = '';/);

const payload={contract_version:2,status:'draft',prescription:{items:[{name:'Losartán',presentation:'50 mg',dose:'1',route:'Oral',frequency:'Cada 24 horas',duration:'30 días',quantity:'30',instructions:'Tomar diariamente'}],observaciones:'Control'},diagnostic_context:{mode:'diagnostico',text:'Hipertensión'},patient_snapshot:{id:'patient-1',full_name:'Paciente QA',age:'42',sex:'F'},physician_snapshot:{user_id:'user-1',doctor_id:'1',full_name:'Dra. Leticia Muñoz Romo',prefix:'Dra.',designation:'Médica cirujana',specialty:'Medicina interna',license:'123',specialty_license:'456'},professional_context:{logo_url:'/api/media/index.php/public/11111111-1111-4111-8111-111111111111',consultorio:null},professional_presentation:{version:2,offices:[]},presentation:{version:2,professional_header:'logo_and_data'},encounter_reference:null,issue_date:'2026-10-14',signatures:{doctor:null}};
const body={draft_ref:'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',expected_version:3,payload};
const php=JSON.parse(execFileSync('php',['-r',`require 'api/_lib/clinical_prescription_v2.php'; require 'api/_lib/clinical_prescription_templates.php'; $body=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR); $out=[]; foreach(['logo_and_data','data_only','hidden'] as $mode){$body['payload']['presentation']=['version'=>2,'professional_header'=>$mode];$out[$mode]=['hash'=>clinical_prescription_binding_fingerprint($body),'html'=>clinical_prescription_v2_render($body['payload'])];$template=clinical_prescription_template_validate(['template_name'=>'QA','content'=>['medications'=>[['name'=>'Losartán']],'professional_header'=>$mode]]);$out['template_'.$mode]=$template['content']['professional_header'];} $body['payload']['presentation']=['version'=>1,'professional_header'=>'shown'];$out['legacy_shown_hash']=clinical_prescription_binding_fingerprint($body);$out['legacy_shown_mode']=clinical_prescription_v2_presentation_mode($body['payload']);$body['payload']['presentation']=['version'=>1,'professional_header'=>'hidden'];$out['legacy_hidden_mode']=clinical_prescription_v2_presentation_mode($body['payload']);echo json_encode($out,JSON_THROW_ON_ERROR);`],{input:JSON.stringify(body)}));
assert.match(php.logo_and_data.html,/clinical-doc-head-logo-slot/);
assert.match(php.logo_and_data.html,/Dra\. Leticia Muñoz Romo/);
assert.doesNotMatch(php.data_only.html,/clinical-doc-head-logo-slot/);
assert.match(php.data_only.html,/Dra\. Leticia Muñoz Romo/);
assert.doesNotMatch(php.hidden.html,/clinical-doc-head/);
assert.match(php.hidden.html,/Médico responsable/);
assert.equal(new Set(['logo_and_data','data_only','hidden'].map(mode=>php[mode].hash)).size,3);
assert.equal(php.legacy_shown_hash,'c304d123f8fc69e352aea3785f1d79f1fc0aa03f60223b848c36e6c3534f5f98');
assert.equal(php.legacy_shown_mode,'logo_and_data');
assert.equal(php.legacy_hidden_mode,'hidden');
for(const mode of ['logo_and_data','data_only','hidden']) assert.equal(php[`template_${mode}`],mode);

globalThis.window={};
await import('../../assets/js/clinical/prescription-signature-binding.js');
for(const mode of ['logo_and_data','data_only','hidden']){
  body.payload.presentation={version:2,professional_header:mode};
  assert.equal(await window.mxmedPrescriptionSignatureBinding.hash(body),php[mode].hash,`${mode} PHP/JS parity`);
}
body.payload.presentation={version:1,professional_header:'shown'};
assert.equal(await window.mxmedPrescriptionSignatureBinding.hash(body),php.legacy_shown_hash,'legacy v1 parity');
console.log('REC-VIS08 presentation and binding contract: PASS');
