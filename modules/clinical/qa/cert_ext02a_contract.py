#!/usr/bin/env python3
"""Disposable CERT-EXT02A projection, rendering and pre-DB validation gate."""
import copy
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]


def run(command, body):
    result = subprocess.run(command, cwd=ROOT, input=json.dumps(body, ensure_ascii=False),
                            text=True, capture_output=True, check=True)
    return json.loads(result.stdout)


PHP = r'''
require 'api/_lib/clinical_certificado_write.php';
$body=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$out=['projection'=>clinical_certificado_binding_projection($body),
      'hash'=>clinical_certificado_binding_fingerprint($body),
      'html'=>clinical_certificado_render_html($body['payload'])];
try {
    clinical_certificado_write(new PDO('sqlite::memory:'),
        ['user_id'=>'qa-doctor','doctor_id'=>'qa-doctor'], 'qa-patient', $body, 'qa-idempotency-0123456789');
    $out['validation']='accepted';
} catch (Throwable $error) { $out['validation']=$error->getMessage(); }
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
'''
NODE = r'''
const fs=require('fs'),vm=require('vm');
const {webcrypto}=require('crypto'); global.crypto=webcrypto; global.window={};
vm.runInThisContext(fs.readFileSync('assets/js/clinical/legal-document-presentation.js','utf8'));
vm.runInThisContext(fs.readFileSync('assets/js/clinical/certificado-signature-binding.js','utf8'));
let input='';process.stdin.setEncoding('utf8');process.stdin.on('data',v=>input+=v);
process.stdin.on('end',async()=>{const body=JSON.parse(input); const binding=window.mxmedCertificadoSignatureBinding;
  process.stdout.write(JSON.stringify({projection:binding.projection(body),hash:await binding.hash(body)}));});
'''
QR_PHP = r'''
require 'api/_lib/clinical_certificado_qr.php';
$body=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE clinical_documents (document_uuid TEXT,document_type TEXT,version INTEGER,status TEXT,patient_id TEXT,created_by_user_id TEXT,payload_json TEXT)');
$stmt=$pdo->prepare('INSERT INTO clinical_documents VALUES (?,?,?,?,?,?,?)');
$stmt->execute([$body['draft_ref'],'certificado_medico',3,'draft',$body['context']['patient_id'],
    $body['payload']['actor_snapshot']['user_id'],json_encode($body['payload'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
$review=clinical_certificado_qr_review_html($body['payload']);
$session=['document_uuid'=>$body['draft_ref'],'document_version'=>3,
    'patient_id'=>$body['context']['patient_id'],'actor_user_id'=>$body['payload']['actor_snapshot']['user_id'],
    'doctor_id'=>'qa-doctor','role'=>'doctor','signer_role_label'=>'Médico responsable','signer_name'=>'Dra. Sintética',
    'signer_authority'=>clinical_certificado_binding_authority($body,'qa-doctor'),
    'fingerprint_version'=>clinical_certificado_binding_projection($body)['version'],
    'content_fingerprint'=>clinical_certificado_binding_fingerprint($body),
    'review_html'=>$review,'review_html_sha256'=>hash('sha256',$review),
    'status'=>'pending','expires_at'=>gmdate('Y-m-d H:i:s',time()+900),'invalidated_at'=>null];
$out=[];
$out['current']=clinical_certificado_qr_status($pdo,$session)['status'];
$wrong=$session;$wrong['fingerprint_version']=1;
$out['wrong_version']=clinical_certificado_qr_status($pdo,$wrong)['status'];
$expired=$session;$expired['expires_at']=gmdate('Y-m-d H:i:s',time()-1);
$out['expired']=clinical_certificado_qr_status($pdo,$expired)['status'];
$consumed=$session;$consumed['status']='consumed';
$out['consumed']=clinical_certificado_qr_status($pdo,$consumed)['status'];
$changed=$body['payload'];$changed['content']['assessment']['activity'].=' editada';
$stmt=$pdo->prepare('UPDATE clinical_documents SET payload_json=?');
$stmt->execute([json_encode($changed,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
$out['changed']=clinical_certificado_qr_status($pdo,$session)['status'];
echo json_encode($out,JSON_THROW_ON_ERROR);
'''


def fixture(kind):
    assessment = dict(activity='Carrera recreativa', intensity='Moderada',
                      participation_purpose='Participación recreativa',
                      current_findings='Clínicamente estable en la valoración actual.',
                      conclusion='apto_para_actividad_indicada',
                      restriction_mode='sin_restricciones_documentadas', restriction_detail='',
                      general_condition='', recommendations='Progresar gradualmente.',
                      additional_considerations='No incluye competencia.')
    if kind == 'aptitud_fisica':
        assessment.update(activity='Actividad física habitual', intensity='', participation_purpose='',
                          general_condition='Estabilidad clínica actual.', additional_considerations='')
    labels = dict(aptitud_fisica='Certificado de aptitud física',
                  actividad_deportiva='Certificado para actividad deportiva',
                  certificado_general='Certificado médico general',
                  constancia_atencion='Constancia de atención médica',
                  reposo_medico='Constancia de incapacidad temporal')
    content = dict(declaration_text='Declaración clínica limitada a la actividad descrita.',
                   care_date='', rest_days='', rest_start_date='', rest_end_date='', return_note='',
                   general_condition='', general_condition_label='', observations_mode='', observations='',
                   validity_term='7 días', usage_note_type='', usage_note_custom='', usage_note='',
                   recipient_header='', validity_note='', closing_statement='')
    if kind in ('aptitud_fisica', 'actividad_deportiva'):
        content['assessment'] = assessment
    elif kind == 'certificado_general':
        content.update(general_condition='estable', general_condition_label='Estable',
                       usage_note_type='administrativo', usage_note='trámite administrativo')
    elif kind == 'constancia_atencion':
        content['care_date'] = '2026-10-10'
    elif kind == 'reposo_medico':
        content.update(rest_days='3', rest_start_date='2026-10-10', rest_end_date='2026-10-12')
    payload = dict(contract_version=2, status='issued', presentation=dict(version=1, professional_header='shown'),
                   report=dict(emission_date='2026-10-10', title=labels[kind]),
                   certificate=dict(type=kind, type_label=labels[kind], purpose='A quien corresponda',
                                    purpose_category='A quien corresponda', usage_type='', usage_custom=''),
                   patient_snapshot=dict(full_name='Paciente sintético', age='32', sex='Femenino', identifier='QA'),
                   actor_snapshot=dict(user_id='qa-doctor', full_name='Dra. Sintética', license='QA',
                                       specialty='', specialty_license='', place='', institution='', facility=''),
                   branding=dict(logo_url_resolved='', facility_visible='', location_line_visible='',
                                 group_name='', address_line='', consultorio_phone=''),
                   content=content, signatures=dict(doctor={}))
    return dict(document_type='certificado_medico', type='certificado_medico',
                draft_ref='00000000-0000-4000-8000-000000000001',
                actor=dict(user_id='qa-doctor'), context=dict(patient_id='qa-patient'), payload=payload)


def result(body):
    php = run(['php', '-r', PHP], body)
    js = run(['node', '-e', NODE], body)
    assert php['projection'] == js['projection'], 'PHP/JS projection mismatch'
    assert php['hash'] == js['hash'], 'PHP/JS hash mismatch'
    return php


def expect_validation(body, code):
    got = result(body)['validation']
    assert got == code, f'{code}: got {got}'


def main():
    baseline_v1 = {
        'certificado_general': '80bbbe64778c3e0124841e6ae4e2ae05dcc9c057fd34f67270b572cb8ae3ea16',
        'constancia_atencion': '9709af5a7df7afcdd129656144d71139123617d811d8406fdebfa7dd7eeb0279',
        'reposo_medico': '80b5c9303ffe439c9522e72ba62e6d0f2982d900bd73666b38b7bdaf18076298',
    }
    for kind in ('certificado_general', 'constancia_atencion', 'reposo_medico'):
        old_result = result(fixture(kind))
        assert old_result['projection']['version'] == 1
        assert 'assessment' not in old_result['projection']
        assert old_result['hash'] == baseline_v1[kind], f'{kind} V1 changed'
    historical = copy.deepcopy(fixture('certificado_general'))
    historical['payload']['certificate'].pop('type')
    assert result(historical)['projection']['version'] == 1
    for kind in ('aptitud_fisica', 'actividad_deportiva'):
        body = fixture(kind)
        base = result(body)
        assert base['projection']['version'] == 2
        assert 'Hallazgos actuales' in base['html'] and 'Firma del médico' in base['html']
        assert 'Paciente sintético' in base['html']
        hidden = copy.deepcopy(body); hidden['payload']['presentation']['professional_header'] = 'hidden'
        assert result(hidden)['hash'] != base['hash']
        for field in body['payload']['content']['assessment']:
            changed = copy.deepcopy(body)
            changed['payload']['content']['assessment'][field] += ' Cambió.'
            assert result(changed)['hash'] != base['hash'], f'{kind}.{field} absent from V2'
        for field in ('declaration_text', 'validity_term'):
            changed = copy.deepcopy(body)
            changed['payload']['content'][field] += ' Cambió.'
            assert result(changed)['hash'] != base['hash'], f'{kind}.{field} absent from V2'
        for field in ('activity', 'current_findings', 'conclusion', 'restriction_mode'):
            invalid = copy.deepcopy(body); invalid['payload']['content']['assessment'][field] = ''
            expect_validation(invalid, 'CERTIFICADO_ASSESSMENT_' + field.upper() + '_REQUIRED')
        invalid = copy.deepcopy(body); invalid['payload']['content']['assessment']['conclusion'] = 'otro'
        expect_validation(invalid, 'CERTIFICADO_CONCLUSION_INVALID')
        invalid = copy.deepcopy(body); invalid['payload']['content']['assessment']['restriction_mode'] = 'otro'
        expect_validation(invalid, 'CERTIFICADO_RESTRICTION_MODE_INVALID')
        invalid = copy.deepcopy(body); invalid['payload']['content']['assessment'].update(
            conclusion='apto_con_restricciones', restriction_mode='con_restricciones', restriction_detail='')
        expect_validation(invalid, 'CERTIFICADO_RESTRICTION_DETAIL_REQUIRED')
        invalid = copy.deepcopy(body); invalid['payload']['content']['care_date'] = '2026-10-10'
        expect_validation(invalid, 'CERTIFICADO_HIDDEN_FIELD_CONTAMINATION')
        print(kind, 'V2 parity/render/validation PASS', base['hash'])
        qr = run(['php', '-r', QR_PHP], body)
        assert qr == dict(current='pending', wrong_version='stale', expired='expired',
                          consumed='consumed', changed='stale'), qr
        print(kind, 'QR version/stale/expiry/replay status PASS')
    print('V1 and historical projection PASS')


if __name__ == '__main__':
    main()
