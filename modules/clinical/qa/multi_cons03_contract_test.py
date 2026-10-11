"""Pure MULTI-CONS03 validation and PHP/JS fingerprint parity; no database writes."""
import copy
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]


def php(operation, body):
    code = '''require "api/_lib/clinical_multimedia_consent_binding.php";
        $body=json_decode(file_get_contents("php://stdin"),true);
        try {
            $value=($argv[1]==="normalize")
                ? clinical_multimedia_consent_normalize($body["payload"]["consent"])
                : (($argv[1]==="render") ? clinical_multimedia_consent_render_html($body["payload"])
                : clinical_multimedia_binding_fingerprint($body));
            echo json_encode(["ok"=>true,"value"=>$value],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } catch(Throwable $error) {
            echo json_encode(["ok"=>false,"error"=>$error->getMessage()]);
        }'''
    result = subprocess.run(['php', '-r', code, operation], input=json.dumps(body, ensure_ascii=False),
                            text=True, capture_output=True, cwd=ROOT, check=True)
    assert not result.stderr, result.stderr
    return json.loads(result.stdout)


def js_hash(body):
    script = '''const fs=require('fs'),vm=require('vm'),crypto=require('crypto').webcrypto;
        const body=JSON.parse(fs.readFileSync(0,'utf8'));
        const context={window:{mxmedConsentSignatureBinding:{}},crypto,TextEncoder};
        vm.runInNewContext(fs.readFileSync('assets/js/clinical/multimedia-consent-signature-binding.js','utf8'),context);
        context.window.mxmedMultimediaConsentBinding.hash(body).then(value=>process.stdout.write(value));'''
    return subprocess.check_output(['node', '-e', script], input=json.dumps(body, ensure_ascii=False),
                                   text=True, cwd=ROOT)


def check(name, condition):
    assert condition, name
    print(f'{name}=PASS', flush=True)


base = {
    'draft_ref': '11111111-1111-4111-8111-111111111111', 'expected_version': 3,
    'context': {'patient_id': 'patient-qa'},
    'payload': {
        'document_date': '2026-10-11',
        'patient_snapshot': {'full_name': 'Paciente sintético', 'age': '36', 'sex': 'Femenino'},
        'actor_snapshot': {'user_id': 'doctor-qa', 'full_name': 'Dra. QA'},
        'signer': {'mode': 'patient'}, 'presentation': {'professional_header': 'shown'},
        'consent': {
            'media_types': ['photo', 'video'], 'capture_allowed': True,
            'use_purposes': ['clinical_record', 'clinical_followup'],
            'identification_scope': 'identifiable', 'authorization_scope': 'current_condition_followup',
            'validity': {'mode': 'single_event', 'valid_from': '2026-10-01', 'valid_to': '2026-12-31'},
            'context': {'clinical_purpose_note': 'Caso sintético'},
        },
    },
}

old = copy.deepcopy(base)
check('V1_PHP_JS_PARITY', php('hash', old)['value'] == js_hash(old))
check('V1_RENDER_UNCHANGED', 'Usos clínicos autorizados' in php('render', old)['value'])
current = copy.deepcopy(base)
current['payload']['consent']['model_version'] = 2
current['payload']['consent']['validity']['mode'] = 'until_revoked'
normal = php('normalize', current)
check('V2_UNTIL_REVOKED_NO_DATES', normal['ok'] and normal['value']['validity']['valid_from'] == ''
      and normal['value']['validity']['valid_to'] == '')
check('V2_PHP_JS_PARITY', php('hash', current)['value'] == js_hash(current))
html = php('render', current)['value']
check('V2_RENDER_REVOCATION', 'solicite su revocación para usos futuros' in html)
check('V2_RECOGNITION_COPY', '¿La persona puede ser reconocida en el material?' in html)
check('V2_DATES_INACTIVE', '2026-12-31' not in html)
check('VALIDITY_CHANGE_STALES', php('hash', old)['value'] != php('hash', current)['value'])
range_case = copy.deepcopy(current)
range_case['payload']['consent']['validity'] = {'mode': 'explicit_date_range',
    'valid_from': '2026-10-11', 'valid_to': '2026-10-18'}
check('DATE_RANGE_UNCHANGED', php('normalize', range_case)['ok']
      and '2026-10-11 al 2026-10-18' in php('render', range_case)['value'])
single_case = copy.deepcopy(current)
single_case['payload']['consent']['validity'] = {'mode': 'single_event'}
check('SINGLE_EVENT_UNCHANGED', php('normalize', single_case)['ok']
      and 'Sólo para este evento o atención' in php('render', single_case)['value'])

for purpose in ['teaching', 'clinical_research', 'clinical_trial', 'scientific_publication',
                'institutional_communication', 'public_dissemination', 'other_specific_use']:
    case = copy.deepcopy(current)
    case['payload']['consent']['use_purposes'].append(purpose)
    case['payload']['consent']['sensitive_purposes_confirmed'] = True
    if purpose == 'other_specific_use':
        case['payload']['consent']['other_specific_use_description'] = 'Sesión clínica específica'
    check('PURPOSE_' + purpose.upper(), php('normalize', case)['ok'])
    check('PARITY_' + purpose.upper(), php('hash', case)['value'] == js_hash(case))
    check('PURPOSE_STALES_' + purpose.upper(), php('hash', case)['value'] != php('hash', current)['value'])
    if purpose == 'other_specific_use':
        changed = copy.deepcopy(case)
        changed['payload']['consent']['other_specific_use_description'] = 'Otro destino autorizado'
        check('OTHER_DESCRIPTION_STALES', php('hash', changed)['value'] != php('hash', case)['value'])
    unconfirmed = copy.deepcopy(case)
    unconfirmed['payload']['consent']['sensitive_purposes_confirmed'] = False
    check('CONFIRMATION_STALES_' + purpose.upper(), php('hash', unconfirmed)['value'] != php('hash', case)['value'])

missing_other = copy.deepcopy(current)
missing_other['payload']['consent']['use_purposes'].append('other_specific_use')
check('OTHER_DESCRIPTION_REQUIRED', php('normalize', missing_other)['error'] == 'MULTIMEDIA_OTHER_USE_DESCRIPTION_REQUIRED')
unsupported = copy.deepcopy(current)
unsupported['payload']['consent']['use_purposes'].append('unsupported')
check('UNSUPPORTED_PURPOSE_REJECTED', not php('normalize', unsupported)['ok'])
old_sensitive = copy.deepcopy(old)
old_sensitive['payload']['consent']['use_purposes'].append('teaching')
check('V1_NEW_PURPOSE_REJECTED', not php('normalize', old_sensitive)['ok'])
inactive = copy.deepcopy(current)
inactive['payload']['consent']['other_specific_use_description'] = 'No autorizado'
check('INACTIVE_OTHER_EXCLUDED', php('hash', inactive)['value'] == php('hash', current)['value'])
reduced = copy.deepcopy(current)
reduced['payload']['consent']['identification_scope'] = 'reduced_identification'
check('RECOGNITION_STALES', php('hash', reduced)['value'] != php('hash', current)['value'])
check('RECOGNITION_PARITY', php('hash', reduced)['value'] == js_hash(reduced))
print('MULTI_CONS03_CONTRACT=PASS')
