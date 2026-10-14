import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { webcrypto } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here=path.dirname(fileURLToPath(import.meta.url));
const root=path.resolve(here,'../../..');
const window={mxmedConsentSignatureBinding:{}};
const context=vm.createContext({window,crypto:webcrypto,TextEncoder,console});
vm.runInContext(fs.readFileSync(path.join(root,'assets/js/clinical/prescription-signature-binding.js'),'utf8'),context);
const binding=window.mxmedPrescriptionSignatureBinding;

const body={draft_ref:'123e4567-e89b-42d3-a456-426614174000',expected_version:7,
  context:{patient_id:'patient-a'},payload:{contract_version:2,
    patient_snapshot:{id:'patient-a',full_name:'Paciente Prueba',age:'42',sex:'F'},
    physician_snapshot:{user_id:'user-a',doctor_id:'doctor-a',full_name:'Dra. Prueba',prefix:'Dra.',
      designation:'Médica cirujana',specialty:'Medicina interna',license:'1234567',specialty_license:'7654321'},
    professional_context:{logo_url:'',consultorio:{name:'Consulta vinculada',address:'Dirección vinculada'}},
    diagnostic_context:{mode:'diagnostico',text:'Diagnóstico de prueba'},
    prescription:{items:[{name:'Medicamento',presentation:'Tabletas',dose:'10 mg',route:'Oral',
      frequency:'Cada 8 horas',duration:'5 días',quantity:'15',instructions:'Después de alimentos',private_notes:'Nunca visible'}],
      observaciones:'Observación'},presentation:{version:1,professional_header:'shown'},
    encounter_reference:{encounter_id:10,appointment_id:'appointment-a'},issue_date:'2026-10-14',signatures:{doctor:null}}};

assert.equal(binding.projection(body).version,1);
assert.equal(await binding.hash(body),'b2d98a7c200c4feadbb71d27c034fea8a1a136626c4eb3110b89a6ddb071200c');
body.payload.professional_presentation={version:2,offices:[
  {name:'Consultorio Principal',address:'Avenida de la Atención Médica Integral 1234, interior Torre B piso 12, Col. Desarrollo Profesional, Aguascalientes, Aguascalientes, C.P. 20999',phones:['449 111 2233'],is_primary:true,display_order:2},
  {name:'Consultorio Norte',address:'Calle Norte 20, Col. Centro, Aguascalientes, Aguascalientes, C.P. 20000',phones:[],is_primary:false,display_order:1},
  {name:'Consultorio Sur',address:'Calle Sur 30, Col. Jardines, Aguascalientes, Aguascalientes, C.P. 20100',phones:['449 333 4455'],is_primary:false,display_order:3}
]};
assert.equal(binding.projection(body).version,2);
assert.equal(await binding.hash(body),'7f0e6e322dd15f9f80bb842c4782b5954f4c692c98626a35084acc852f6b1e66');
const changed=structuredClone(body);
changed.payload.professional_presentation.offices[0].address+=' cambio';
assert.notEqual(await binding.hash(changed),await binding.hash(body));
console.log('DOC_REC_STD03A_BINDING_JS=PASS');
