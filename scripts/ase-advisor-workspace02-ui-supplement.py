#!/usr/bin/env python3
"""Focused supplement after WORKSPACE02 main browser stage, in its owned runtime.

One canonical Media replacement, shared event projections, request outage
isolation, and native review screenshots. Never resets a synthetic record.
"""
import argparse
import importlib.util
import json
import os
import pathlib
import time

from playwright.sync_api import sync_playwright, expect

ROOT = pathlib.Path(__file__).resolve().parents[1]
OUT = ROOT / 'docs/evidence/ase-advisor-workspace02'


def module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    value = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(value)
    return value


control = module('workspace02_supplement_control', ROOT / 'scripts/ase-advisor-workspace02-review.py')
journey = module('workspace02_supplement_journey', ROOT / 'scripts/ase-advisor-workspace02-browser.py')


def count_smoke(owned):
    """Read-only count regression; no replay of request or Media mutations."""
    owned, approval = control.owned_configuration(owned)
    _, origin, _, record = control.verified_preview(owned, approval)
    fixture = json.loads((owned / 'workspace02-fixture.json').read_text())
    claim = fixture['claim_ready']['application_id']
    doctor = fixture['claim_ready']['doctor_id']
    proof = {'checks': [], 'page_errors': [], 'synthetic_only': True,
             'business_mutations': 0}

    def check(label, passed):
        proof['checks'].append({'name': label, 'pass': bool(passed)})
        print(('PASS ' if passed else 'FAIL ') + label, flush=True)
        assert passed, label

    with sync_playwright() as pw:
        browser, context, page = journey.review.native_browser(pw)
        page.on('pageerror', lambda error: proof['page_errors'].append(str(error)))
        journey.review.login(page, origin, record['email'], (owned / 'applicant-password').read_text().strip())
        journey.open_request(page, claim)
        media = journey.previous.read(page, '/api/internal/workspace/read.php?doctor_id=' + doctor)['body']['data']
        requests = journey.snapshot(page)
        pending_requests = {entry['request_id'] for entry in requests['requests']
                            if entry['doctor_id'] == doctor and entry['actionable']}
        expected = media['physician']['pending_count'] + len(pending_requests)
        expect(page.locator('#physician-statuses .review-badge')).to_have_text(str(expected) + ' pendientes de revisión')
        journey.previous.tab(page, 'Resumen')
        expect(page.locator('#summary-facts .fact').filter(has_text='Pendientes de revisión').locator('strong')).to_have_text(str(expected))
        check('Context header and Resumen count three canonical Media items plus one claim as four',
              media['physician']['pending_count'] == 3 and pending_requests == {claim} and expected == 4)
        journey.previous.tab(page, 'Solicitudes')
        page.screenshot(path=str(OUT / 'screens/native-advisor-ready-claim.png'))

        # Duplicate only an already-authorized read projection in this browser;
        # canonical data, pending_count and all mutations remain untouched.
        pattern = '**/api/internal/workspace/requests.php?mode=read'

        def duplicate_projection(route):
            response = route.fetch()
            body = response.json()
            duplicate = next(entry for entry in body['data']['requests'] if entry['request_id'] == claim)
            body['data']['requests'].append(duplicate)
            route.fulfill(response=response, json=body)

        page.route(pattern, duplicate_projection)
        page.reload()
        expect(page.locator('#discovery-list')).to_have_attribute('aria-busy', 'false')
        cards = page.locator('#discovery-list [data-request-id="' + claim + '"]')
        expect(cards).to_have_count(2)
        cards.first.click()
        expect(page.locator('#request-detail')).to_have_attribute('data-request-id', claim)
        expect(page.locator('#physician-statuses .review-badge')).to_have_text('4 pendientes de revisión')
        journey.previous.tab(page, 'Resumen')
        expect(page.locator('#summary-facts .fact').filter(has_text='Pendientes de revisión').locator('strong')).to_have_text('4')
        check('A duplicated authorized read projection counts its canonical request ID once', True)
        page.unroute(pattern, duplicate_projection)

        page.reload()
        completed_id = fixture['new_approve']['application_id']
        journey.open_request(page, completed_id)
        completed = journey.snapshot(page, completed_id)['request']
        expect(page.locator('#physician-statuses .review-badge')).to_have_count(0)
        journey.previous.tab(page, 'Resumen')
        expect(page.locator('#summary-facts .fact').filter(has_text='Pendientes de revisión').locator('strong')).to_have_text('0')
        check('Completed materialized new profile has no phantom pending request or Media count',
              completed['state'] == 'APPROVED' and completed['doctor_id']
              and not completed['actionable'] and not page.locator('#summary-pending [data-submission-id],#summary-pending [data-request-id]').count())
        page.screenshot(path=str(OUT / 'screens/native-advisor-completed-new-count.png'))
        assert not proof['page_errors']
        proof.update({'media_pending': 3, 'request_pending': 1, 'context_pending': expected,
                      'completed_new_profile_pending': 0})
        (OUT / 'count-smoke.json').write_text(json.dumps(proof, indent=2))
        assert control.canonical_logout(page)
        browser.close()
    return proof


def run(owned):
    import pymysql
    owned, approval = control.owned_configuration(owned)
    _, origin, expiry, record = control.verified_preview(owned, approval)
    env = json.loads(control.private_file(owned, 'review-env.json').read_text())
    connection = pymysql.connect(unix_socket=str(owned / 'mysql.sock'), user='ase_fixture',
                                 password=env['ASE_INT02A_FIXTURE_PASSWORD'], database=approval['database'], autocommit=True)

    def sql(query, args=()):
        with connection.cursor() as cursor:
            cursor.execute(query, args)
            return cursor.fetchall()

    assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve() == owned / 'mysql'
    fixture = json.loads((owned / 'workspace02-fixture.json').read_text())
    claim = fixture['claim_ready']['application_id']
    doctor = fixture['claim_ready']['doctor_id']
    assert doctor == 'workspace_aurora'
    checkpoint_path = owned / 'workspace02-media-supplement.json'
    checkpoint = json.loads(checkpoint_path.read_text()) if checkpoint_path.exists() else {}
    proof = {'checks': [], 'page_errors': [], 'synthetic_only': True, 'expires': expiry}
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / 'screens').mkdir(exist_ok=True)

    def check(label, result):
        passed = bool(result)
        proof['checks'].append({'name': label, 'pass': passed})
        (owned / 'workspace02-ui-supplement-progress.json').write_text(json.dumps(proof))
        print(('PASS ' if passed else 'FAIL ') + label, flush=True)
        assert passed, label

    with sync_playwright() as pw:
        ab, ac, a = journey.review.native_browser(pw)
        a.on('pageerror', lambda error: proof['page_errors'].append(str(error)))
        journey.review.login(a, origin, record['email'], (owned / 'applicant-password').read_text().strip())
        journey.open_request(a, claim)
        check('Ready claim enters exact original Aurora context', a.locator('#work-panel').get_attribute('data-doctor-id') == doctor)
        media_path = '/api/internal/workspace/read.php?doctor_id=' + doctor
        data = journey.previous.read(a, media_path)['body']['data']
        if not checkpoint:
            candidates = [item for item in data['pending_items'] if item['purpose'] == 'DOCTOR_GALLERY']
            assert candidates and len(data['pending_items']) >= 2
            checkpoint = {'submission_id': candidates[-1]['submission_id'], 'pending_before': len(data['pending_items'])}
            checkpoint_path.write_text(json.dumps(checkpoint))
        submission = checkpoint['submission_id']
        current = sql('SELECT owner_id,review_status FROM media_review_submissions WHERE submission_id=%s', (submission,))[0]
        assert current[0] == doctor
        if current[1] == 'PENDING_REVIEW':
            journey.previous.tab(a, 'Imágenes')
            a.locator('#media-items [data-submission-id="' + submission + '"]').click()
            expect(a.locator('#review-image')).to_be_visible()
            expect(a.locator('#review-image')).to_have_js_property('complete', True)
            assert a.locator('#review-image').evaluate('(img)=>img.naturalWidth>0')
            a.locator('#request-replacement').click()
            a.locator('#replacement-reason').select_option('QUALITY_INSUFFICIENT')
            a.locator('#replacement-feedback').fill('Ensayo ficticio: enviar una imagen con mayor nitidez.')
            a.locator('#send-replacement').click()
            expect(a.locator('#workspace-feedback')).to_contain_text('Se registró la solicitud de otra imagen.', timeout=20000)
        after = journey.previous.read(a, media_path)['body']['data']
        check('Actual canonical Media replacement changes only its pending item and preserves useful work',
              sql('SELECT review_status FROM media_review_submissions WHERE submission_id=%s', (submission,))[0][0] == 'NEEDS_WORK'
              and len(after['pending_items']) == checkpoint['pending_before'] - 1 and len(after['pending_items']) > 0)
        check('Media operation retains the selected claim and physician', a.locator('#work-panel').get_attribute('data-doctor-id') == doctor and a.locator('#work-panel').get_attribute('data-request-id') == claim)
        events = sql("SELECT event_id,real_actor_reference FROM platform_audit_events WHERE action='MEDIA_REVIEW_REPLACEMENT_REQUESTED' AND resource_reference=%s AND outcome='SUCCESS'", (submission,))
        check('Media mutation and canonical audit persist once with the actual Advisor', len(events) == 1 and events[0][1] == 'account:' + record['account'])
        event_id = events[0][0]
        journey.previous.tab(a, 'Historial')
        expect(a.locator('[data-event-id="' + event_id + '"]')).to_be_visible()
        history = journey.previous.read(a, journey.API + '?mode=history&doctor_id=' + doctor + '&request_id=' + claim)['body']['data']['items']
        entry = next(item for item in history if item['id'] == event_id)
        check('Same physician/request Historial projects canonical Media event', entry['target']['doctor_id'] == doctor and entry['target']['request_id'] == claim)
        a.screenshot(path=str(OUT / 'screens/native-advisor-shared-history.png'))

        db, dc, d = journey.review.native_browser(pw)
        d.on('pageerror', lambda error: proof['page_errors'].append(str(error)))
        journey.review.login(d, origin, 'qa_director@example.test', (owned / 'fixture-password').read_text().strip(), journey.review.DIRECTOR)
        journey.old.selected(d, record)
        d.locator('#member-history summary').click()
        expect(d.locator('[data-event="' + event_id + '"]')).to_be_visible()
        team = journey.previous.read(d, '/api/internal/team/read.php?category=ADVISOR&target=' + record['account'])['body']['items'][0]['history']['items']
        projected = next(item for item in team if item['id'] == event_id)
        check('Director Advisor-history and physician history project the identical immutable event and label',
              projected['id'] == entry['id'] and projected['description'] == entry['description'] and projected['direction'] == 'BY_MEMBER')
        d.locator('[data-event="' + event_id + '"]').scroll_into_view_if_needed()
        d.screenshot(path=str(OUT / 'screens/native-director-shared-history.png'))

        def unavailable(route):
            route.fulfill(status=503, json={'ok': False, 'error': 'unavailable_reconcile_required'})

        pattern = '**/api/internal/workspace/requests.php?mode=read*'
        a.route(pattern, unavailable)
        a.reload()
        expect(a.locator('#staff-name')).to_contain_text('Irene')
        expect(a.locator('#workspace-feedback')).to_contain_text('Puedes continuar con las imágenes disponibles')
        expect(a.locator('#discovery-list')).to_have_attribute('aria-busy', 'false')
        a.locator('#discovery-list [data-doctor-id="' + doctor + '"][data-submission-id]').first.click()
        expect(a.locator('#review-image')).to_be_visible()
        expect(a.locator('#review-image')).to_have_js_property('complete', True)
        check('Injected request503 leaves real authorized Media directory and review operable',
              a.locator('#review-image').evaluate('(img)=>img.naturalWidth>0') and a.locator('#work-panel').get_attribute('data-doctor-id') == doctor)
        a.unroute(pattern, unavailable)
        a.reload()
        journey.open_request(a, claim)
        check('Request service recovers without resetting physician or application records', journey.snapshot(a, claim)['request']['state'] == 'PENDING_REVIEW')
        geometry = a.evaluate('({width:innerWidth,height:innerHeight,outerHeight,availableHeight:screen.availHeight,scrollWidth:document.documentElement.scrollWidth})')
        check('Final native viewport and compact selected context remain within physical bounds',
              geometry['outerHeight'] <= geometry['availableHeight'] and geometry['scrollWidth'] <= geometry['width']
              and not a.locator('#discovery').is_visible() and not a.locator('#physician-search-form').is_visible())
        a.screenshot(path=str(OUT / 'screens/native-advisor-ready-claim.png'))
        journey.open_request(a, fixture['new_ready']['application_id'])
        a.screenshot(path=str(OUT / 'screens/native-advisor-ready-application.png'))
        d.reload()
        journey.old.selected(d, record)
        if not d.locator('#use-requests').is_visible():
            d.locator('[data-expand="use-requests"]').click()
        d.locator('#use-requests').scroll_into_view_if_needed()
        d.screenshot(path=str(OUT / 'screens/native-director-request-grants.png'))
        check('No JavaScript errors in supplemental real browser journeys', not proof['page_errors'])
        proof.update({'event_id': event_id, 'submission_id': submission, 'native': geometry,
                      'ready_claim': claim, 'ready_application': fixture['new_ready']['application_id'],
                      'remaining_media_pending_for_physician': len(after['pending_items'])})
        (OUT / 'ui-supplement.json').write_text(json.dumps(proof, indent=2))
        assert control.canonical_logout(a) and control.canonical_logout(d)
        ab.close()
        db.close()
    connection.close()
    return proof


if __name__ == '__main__':
    os.umask(0o077)
    parser = argparse.ArgumentParser()
    parser.add_argument('owned')
    parser.add_argument('--counts-only', action='store_true')
    args = parser.parse_args()
    count_smoke(args.owned) if args.counts_only else run(args.owned)
