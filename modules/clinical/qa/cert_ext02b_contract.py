#!/usr/bin/env python3
"""Disposable V3 preoperative certificate contract and V1/V2 preservation checks."""
import copy
from cert_ext02a_contract import fixture, result, expect_validation, run, QR_PHP


def preop_fixture():
    body = fixture('certificado_general')
    body['payload']['certificate'].update(
        type='valoracion_preoperatoria',
        type_label='Constancia de valoración médica preoperatoria',
        usage_type='', usage_custom='')
    body['payload']['report']['title'] = 'Constancia de valoración médica preoperatoria'
    body['payload']['content'] = dict(
        declaration_text='Se documenta una valoración médica preoperatoria para resección electiva. '
                         'La conclusión se limita a esta valoración; no autoriza cirugía ni anestesia.',
        care_date='', rest_days='', rest_start_date='', rest_end_date='', return_note='',
        general_condition='', general_condition_label='', observations_mode='', observations='',
        validity_term='', usage_note_type='', usage_note_custom='', usage_note='',
        recipient_header='', validity_note='', closing_statement='',
        preop=dict(planned_procedure='Resección electiva de lesión cutánea superficial',
                   planned_date='', requester='', assessment_scope='Consulta y exploración general',
                   current_findings='Clínicamente estable en esta valoración.',
                   history_state='', history_detail='', medications_state='', medications_detail='',
                   allergies_state='', allergies_detail='', studies_state='', studies_detail='',
                   clinical_considerations='', conclusion='valoracion_documentada', recommendations=''))
    return body


def main():
    body = preop_fixture()
    base = result(body)
    assert base['projection']['version'] == 3
    assert 'preop' in base['projection'] and 'assessment' not in base['projection']
    assert 'Constancia de valoración médica preoperatoria' in base['html']
    assert 'Procedimiento previsto' in base['html'] and 'Firma del médico' in base['html']
    for forbidden in ('Antecedentes relevantes:', 'Medicamentos revisados:', 'Alergias revisadas:',
                      'Estudios disponibles:', 'sin alergias', 'sin medicamentos'):
        assert forbidden not in base['html'], f'blank optional rendered as finding: {forbidden}'
    for field in body['payload']['content']['preop']:
        changed = copy.deepcopy(body)
        changed['payload']['content']['preop'][field] += ' X'
        assert result(changed)['hash'] != base['hash'], f'V3 omits {field}'
    for field in ('declaration_text',):
        changed = copy.deepcopy(body)
        changed['payload']['content'][field] += ' X'
        assert result(changed)['hash'] != base['hash']
    hidden = copy.deepcopy(body)
    hidden['payload']['presentation']['professional_header'] = 'hidden'
    assert result(hidden)['hash'] != base['hash']
    for field in ('planned_procedure', 'current_findings', 'conclusion'):
        invalid = copy.deepcopy(body)
        invalid['payload']['content']['preop'][field] = ''
        expect_validation(invalid, 'CERTIFICADO_PREOP_' + field.upper() + '_REQUIRED')
    for unsafe in ('apto_para_cirugia', 'autorizado_para_cirugia', 'riesgo_cero'):
        invalid = copy.deepcopy(body)
        invalid['payload']['content']['preop']['conclusion'] = unsafe
        expect_validation(invalid, 'CERTIFICADO_PREOP_CONCLUSION_INVALID')
    invalid = copy.deepcopy(body)
    invalid['payload']['content']['preop'].update(allergies_state='reviewed_information', allergies_detail='')
    expect_validation(invalid, 'CERTIFICADO_PREOP_INFORMATION_STATE_INVALID')
    invalid = copy.deepcopy(body)
    invalid['payload']['content']['preop']['history_state'] = 'unknown'
    expect_validation(invalid, 'CERTIFICADO_PREOP_INFORMATION_STATE_INVALID')
    invalid = copy.deepcopy(body)
    invalid['payload']['content']['assessment'] = {'activity': 'cross-type leak'}
    expect_validation(invalid, 'CERTIFICADO_HIDDEN_FIELD_CONTAMINATION')
    invalid = copy.deepcopy(body)
    invalid['payload']['content']['validity_term'] = '7 días'
    expect_validation(invalid, 'CERTIFICADO_HIDDEN_FIELD_CONTAMINATION')
    documented = copy.deepcopy(body)
    documented['payload']['content']['preop'].update(
        history_state='reviewed_information', history_detail='Antecedente en seguimiento.')
    assert 'Antecedente en seguimiento.' in result(documented)['html']
    negative = copy.deepcopy(body)
    negative['payload']['content']['preop']['allergies_state'] = 'reviewed_no_relevant_findings'
    assert 'Alergias revisadas: sin hallazgos relevantes' in result(negative)['html']
    qr = run(['php', '-r', QR_PHP.replace("['assessment']['activity']", "['preop']['planned_procedure']")], body)
    assert qr == dict(current='pending', wrong_version='stale', expired='expired',
                      consumed='consumed', changed='stale'), qr
    print('Preoperative V3 projection, rendering, validation and QR stale/replay PASS')


if __name__ == '__main__':
    main()
