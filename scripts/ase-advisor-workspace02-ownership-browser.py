#!/usr/bin/env python3
"""Focused real-browser ownership continuation on an existing owned synthetic preview.

Uses canonical applicant/Advisor logins and UI actions; no membership/owner SQL writes.
"""
import importlib.util
import json
import os
import pathlib
import re
import sys
import subprocess
import time

import pymysql
from playwright.sync_api import sync_playwright, expect

ROOT = pathlib.Path(__file__).resolve().parents[1]


def module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    result = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(result)
    return result


flow = module('workspace02_flow', ROOT / 'scripts/ase-advisor-workspace02-browser.py')


def run(owned):
    owned = pathlib.Path(owned).resolve()
    assert owned.parent == pathlib.Path('/private/tmp') and re.fullmatch(r'mxmed-ase-adm02imp01-[A-Za-z0-9_]+', owned.name)
    assert owned.stat().st_uid == os.getuid() and owned.stat().st_mode & 0o777 == 0o700
    approval = json.loads((owned / 'workflow-approval.json').read_text())
    assert approval['root'] == str(ROOT) and approval['expires'] > time.time()
    env = json.loads((owned / 'review-env.json').read_text())
    origin = env['MXMED_PREVIEW_ORIGIN']
    assert re.fullmatch(r'https://127\.0\.0\.1:[0-9]+', origin)
    connection = pymysql.connect(unix_socket=str(owned / 'mysql.sock'), user='root', database=env['MXMED_DB_NAME'], autocommit=True)

    def sql(statement, values=()):
        assert statement.lstrip().upper().startswith('SELECT ')
        with connection.cursor() as cursor:
            cursor.execute(statement, values)
            return cursor.fetchall()

    assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve() == (owned / 'mysql').resolve()
    advisor = json.loads((owned / 'oper09-advisor.json').read_text())
    cases = json.loads((owned / 'workspace02-fixture.json').read_text())
    password = (owned / 'applicant-password').read_text().strip()
    evidence = ROOT / 'docs/evidence/ase-advisor-workspace02'
    evidence.mkdir(parents=True, exist_ok=True)
    (evidence / 'screens').mkdir(exist_ok=True)
    results = {'status': 'RUNNING', 'checks': [], 'cases': [], 'browser': 'Chromium', 'page_errors': []}

    def check(label, condition):
        assert condition, label
        results['checks'].append({'name': label, 'result': 'PASS'})
        (owned / 'workspace02-ownership-progress.json').write_text(json.dumps(results))
        (evidence / 'ownership-browser.json').write_text(json.dumps(results, ensure_ascii=False, indent=2))
        print('PASS', label, flush=True)

    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=True)
        staff_context = browser.new_context(ignore_https_errors=True, viewport={'width': 1440, 'height': 900})
        staff = staff_context.new_page()
        staff.on('pageerror', lambda error: results['page_errors'].append(str(error)))
        flow.review.login(staff, origin, advisor['email'], password, flow.review.ADVISOR)
        director_context = browser.new_context(ignore_https_errors=True, viewport={'width': 1440, 'height': 900})
        director = director_context.new_page()
        flow.review.login(director, origin, 'qa_director@example.test', (owned / 'fixture-password').read_text().strip(), flow.review.DIRECTOR)
        information = cases['information']
        ic = browser.new_context(ignore_https_errors=True, viewport={'width': 390, 'height': 844})
        ip = ic.new_page()
        flow.review.login(ip, origin, information['email'], password, '/public/identity/perfil-medico.php?application=' + information['application_id'])
        expect(ip.locator('#request-state')).to_have_text('Se requiere información')
        expect(ip.locator('#request-accept')).not_to_be_visible()
        if not sql('SELECT response_id FROM auth_physician_profile_request_responses WHERE origin_id=%s', (information['application_id'],)):
            expect(ip.locator('#request-response')).to_be_visible()
            ip.locator('#request-response-text').fill('Respuesta ficticia del solicitante: información adicional para revisar la solicitud.')
            ip.locator('#request-response button').click()
            expect(ip.locator('#request-message')).to_have_text('Respuesta registrada.', timeout=20000)
        expect(ip.locator('#request-response')).not_to_be_visible()
        check('Submitted information response is shown while duplicate response form is hidden', True)
        check('Applicant responds in the existing Identity continuation; request remains unresolved', sql('SELECT state,profile_doctor_id FROM auth_physician_profile_request_decisions WHERE origin_id=%s ORDER BY resolution_version DESC LIMIT 1', (information['application_id'],)) == (('NEEDS_INFORMATION', None),) and len(sql('SELECT response_id FROM auth_physician_profile_request_responses WHERE origin_id=%s', (information['application_id'],))) == 1)
        flow.open_request(staff, information['application_id'])
        expect(staff.locator('#request-detail')).to_contain_text('Respuesta ficticia del solicitante')
        check('Advisor sees exact information reason and applicant response in the same request', True)
        ip.screenshot(path=str(evidence / 'screens' / 'information-response-applicant-mobile.png'), full_page=True)
        ic.close()
        for key in ['claim_approve', 'new_approve']:
            record = cases[key]
            request_id = record['application_id']
            profile = sql("SELECT profile_doctor_id FROM auth_physician_profile_request_decisions WHERE origin_id=%s AND state='APPROVED'", (request_id,))
            assert len(profile) == 1, 'Approve the synthetic request first: ' + key
            doctor = profile[0][0]
            binding = sql('SELECT b.status,a.status,b.profile_doctor_id,a.evidence_request_id FROM auth_account_professional_bindings b JOIN profiles_physician_admissions a ON a.admission_id=b.admission_id WHERE b.account_id=%s AND b.profile_doctor_id=%s', (record['account_id'], doctor))
            check(key + ': actual canonical C4 admission and professional binding are active for the applicant', len(binding) == 1 and binding[0][:3] == ('ACTIVE', 'APPROVED', doctor))
            if key == 'new_approve':
                check('New profile uses a distinct canonical C4 evidence request after its own PUB-ENR03 resolution', binding[0][3] != record['evidence_request_id'])
            before = sql('SELECT doctor_id,display_name,profile_status,plan_code,is_public_candidate FROM profiles_doctors WHERE doctor_id=%s', (doctor,))[0]
            context = browser.new_context(ignore_https_errors=True, viewport={'width': 1366, 'height': 768})
            applicant = context.new_page()
            applicant.on('pageerror', lambda error: results['page_errors'].append(str(error)))
            target = '/public/identity/perfil-medico.php?application=' + request_id
            flow.review.login(applicant, origin, record['email'], password, target)
            expect(applicant.locator('#request-state')).to_have_text('Solicitud aprobada')
            active = sql("SELECT account_id FROM auth_account_memberships WHERE profile_doctor_id=%s AND role_code='owner' AND status='active'", (doctor,))
            if not active:
                if not sql("SELECT membership_id FROM auth_account_memberships WHERE account_id=%s AND profile_doctor_id=%s AND status='active'", (record['account_id'], doctor)):
                    expect(applicant.locator('#request-prepare')).to_be_visible(timeout=20000)
                    applicant.locator('#request-prepare').click()
                    expect(applicant.locator('#request-message')).to_contain_text('Vínculo preparado', timeout=20000)
                check(key + ': applicant prepares its own canonical membership', sql("SELECT account_id,assignment_source FROM auth_account_memberships WHERE account_id=%s AND profile_doctor_id=%s AND status='active'", (record['account_id'], doctor)) == ((record['account_id'], 'physician_profile_request'),))
                if key == 'claim_approve':
                    expiry_file = owned / 'workspace02-ownership-expiry.json'
                    if expiry_file.exists():
                        expiry_result = json.loads(expiry_file.read_text())
                    else:
                        recovery = subprocess.run(['php', str(ROOT / 'scripts/ase-advisor-workspace02-ownership-expiry.php'), key], cwd=ROOT, env=env, capture_output=True, text=True)
                        assert recovery.returncode == 0, recovery.stdout[:500]
                        expiry_result = json.loads(recovery.stdout)
                    check('Expired owner case safely recovers with retained immutable link and real-clock fresh TTL', expiry_result['result'] == 'PASS' and expiry_result['prior_case_retained'])
                    (evidence / 'ownership-expiry.json').write_text(json.dumps(expiry_result, ensure_ascii=False, indent=2))
                flow.open_request(staff, request_id)
                staff.locator('#refresh-ownership').click()
                expect(staff.locator('#continue-ownership')).to_be_visible(timeout=20000)
                staff.locator('#continue-ownership').click()
                staff.locator('#request-password').fill(password)
                staff.locator('#request-mfa').fill(flow.fresh_mfa(staff, owned, sql, advisor['account']))
                staff.locator('#request-confirmation').check()
                staff.locator('#confirm-request-decision').click()
                expect(staff.locator('#request-feedback')).to_contain_text('Autorización de titularidad registrada', timeout=30000)
                check(key + ': Advisor authorizes exact request through canonical MFA UI', sql("SELECT c.status,e.capability FROM auth_physician_profile_request_ownership l JOIN auth_governance_cases c ON c.case_id=l.case_id JOIN auth_profile_ownership_approval_endorsements e ON e.case_reference=c.case_id WHERE l.origin_id=%s ORDER BY l.link_version DESC LIMIT 1", (request_id,)) == (('APPROVED', 'physician_claim_resolve' if key.startswith('claim') else 'physician_new_profile_resolve'),))
                applicant.locator('#request-refresh').click()
                expect(applicant.locator('#request-accept')).to_be_visible(timeout=20000)
                applicant.locator('#request-password').fill(password)
                applicant.locator('#request-confirm').check()
                intercepted = []

                def lose_committed_response(route):
                    payload = route.request.post_data_json if route.request.method == 'POST' else None
                    if payload and payload.get('action') == 'owner_finalize':
                        forwarded = route.request.all_headers()
                        forwarded['sec-fetch-site'] = 'same-origin'
                        response = route.fetch(headers=forwarded)
                        intercepted.append(response.status)
                        route.abort('failed')
                    else:
                        route.continue_()

                if key == 'new_approve':
                    applicant.route('**/api/internal/workspace/requests.php', lose_committed_response)
                applicant.locator('#request-accept button[type=submit]').click()
                expect(applicant.locator('#request-outcome')).to_have_text('Tu titularidad está activada.', timeout=30000)
                if key == 'new_approve':
                    applicant.unroute('**/api/internal/workspace/requests.php', lose_committed_response)
                    check('Lost committed owner response reconciles through authorized lookup without retry', intercepted == [200])
            expect(applicant.locator('#request-outcome')).to_have_text('Tu titularidad está activada.', timeout=30000)
            expect(applicant.locator('#request-accept')).not_to_be_visible()
            expect(applicant.locator('#request-response')).not_to_be_visible()
            check(key + ': completed applicant screen hides irrelevant response and acceptance forms', True)
            check(key + ': applicant is the sole actual owner and Advisor has no membership', sql("SELECT account_id FROM auth_account_memberships WHERE profile_doctor_id=%s AND role_code='owner' AND status='active'", (doctor,)) == ((record['account_id'],),) and not sql('SELECT membership_id FROM auth_account_memberships WHERE profile_doctor_id=%s AND account_id=%s', (doctor, advisor['account'])))
            check(key + ': ownership preserves profile ID/content/free/publication state', before == sql('SELECT doctor_id,display_name,profile_status,plan_code,is_public_candidate FROM profiles_doctors WHERE doctor_id=%s', (doctor,))[0])
            flow.open_request(staff, request_id)
            flow.previous.tab(staff, 'Historial')
            expect(staff.locator('#history-list')).to_contain_text('Propiedad inicial activada', timeout=20000)
            history = flow.previous.read(staff, flow.API + '?mode=history&request_id=' + request_id)
            events = history['body']['data']['items']
            owner_events = [event for event in events if event['action'] == 'PROFILE_OWNERSHIP_ASSIGNED']
            team = flow.previous.read(director, '/api/internal/team/read.php?category=ADVISOR&target=' + advisor['account'])
            team_events = team['body']['items'][0]['history']['items']
            check(key + ': physician and Director Advisor history project same canonical owner event', len(owner_events) == 1 and owner_events[0]['actor']['reference'] == 'account:' + record['account_id'] and any(event['id'] == owner_events[0]['id'] and event['direction'] == 'AUTHORIZED_BY_MEMBER' for event in team_events))
            check(key + ': no decision proof can activate another applicant profile', flow.command(staff, 'owner_prepare', {'request_id': request_id})['status'] == 403)
            applicant.screenshot(path=str(evidence / 'screens' / (key + '-ownership-applicant.png')), full_page=True)
            staff.screenshot(path=str(evidence / 'screens' / (key + '-ownership-history.png')), full_page=True)
            results['cases'].append({'type': key, 'profile_id': doctor, 'ownership_active': True, 'event_id': owner_events[0]['id']})
            context.close()
        check('Ownership browser journeys have no page exceptions', not results['page_errors'])
        browser.close()
    connection.close()
    results['status'] = 'COMPLETE'
    (evidence / 'ownership-browser.json').write_text(json.dumps(results, ensure_ascii=False, indent=2))
    return results


if __name__ == '__main__':
    os.umask(0o077)
    run(sys.argv[1])
