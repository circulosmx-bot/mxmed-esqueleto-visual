"""WORKSPACE02 focused browser journeys on the caller's owned synthetic runtime.

No suite setup, database reset, external delivery, or original listener access.
Canonical state allows a failed targeted run to resume without replaying decisions.
"""
import hashlib
import hmac
import importlib.util
import json
import pathlib
import struct
import time

from playwright.sync_api import sync_playwright, expect

ROOT = pathlib.Path(__file__).resolve().parents[1]
API = '/api/internal/workspace/requests.php'
CAPS = ['physician_request_read', 'physician_request_evidence_read',
        'physician_claim_resolve', 'physician_new_profile_resolve']
MEDIA = ['media_review_read', 'media_review_source_download', 'media_review_corrected_upload',
         'media_review_approve', 'media_review_improve', 'media_review_request_replacement']


def module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    value = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(value)
    return value


previous = module('workspace02_previous', ROOT / 'scripts/ase-advisor-workspace-browser.py')
review, old = previous.review, previous.old


def snapshot(page, request_id=None):
    path = API + '?mode=read' + ('&request_id=' + request_id if request_id else '')
    result = previous.read(page, path)
    assert result['status'] == 200 and result['body']['ok'], result['status']
    return result['body']['data']


def command(page, action, values):
    return page.evaluate('''async ([path,action,input])=>{
      const bootstrap=await(await fetch(path+'?mode=read',{cache:'no-store'})).json();
      const r=await fetch(path,{method:'POST',headers:{'Content-Type':'application/json',
        'X-CSRF-Token':bootstrap.data.csrf_token},body:JSON.stringify({action,input})});
      return {status:r.status,body:await r.json()};
    }''', [API, action, values])


def open_request(page, request_id):
    if not page.locator('#discovery').is_visible() or page.locator('#pending-toggle').get_attribute('aria-expanded') != 'true':
        page.locator('#pending-toggle').click()
    card = page.locator('#discovery-list [data-request-id="' + request_id + '"]')
    expect(page.locator('#discovery-list')).to_have_attribute('aria-busy', 'false', timeout=20000)
    if not card.count():
        data = snapshot(page, request_id)['request']
        if page.locator('#search-toggle').is_visible():
            page.locator('#search-toggle').click()
        page.locator('#physician-search').fill(data['label'])
        page.locator('#physician-search-form button').click()
        expect(page.locator('#discovery-list')).to_have_attribute('aria-busy', 'false', timeout=20000)
        card = page.locator('#discovery-list [data-request-id="' + request_id + '"]')
        if not card.count() and data['doctor_id']:
            page.locator('#discovery-list [data-doctor-id="' + data['doctor_id'] + '"]').click()
            previous.tab(page, 'Solicitudes')
            card = page.locator('#request-list [data-request-id="' + request_id + '"]')
    card.click()
    expect(page.locator('#request-detail')).to_have_attribute('data-request-id', request_id)
    expect(page.locator('#requests-panel')).to_be_visible()


def inspect_evidence(page):
    buttons = page.locator('#request-evidence button')
    assert buttons.count() == 2
    for index in range(buttons.count()):
        buttons.nth(index).click()
        expect(page.locator('#evidence-dialog')).to_be_visible()
        expect(page.locator('#evidence-image')).to_be_visible()
        expect(page.locator('#evidence-image')).to_have_js_property('complete', True)
        assert page.locator('#evidence-image').evaluate('(img)=>img.naturalWidth>0')
        page.locator('#close-evidence').click()
        expect(page.locator('#evidence-dialog')).not_to_be_visible()


def totp(owned):
    digest = hmac.new((owned / 'workspace02-totp-secret').read_bytes(),
                      struct.pack('>Q', int(time.time()) // 30), hashlib.sha1).digest()
    offset = digest[-1] & 15
    return str((int.from_bytes(digest[offset:offset + 4], 'big') & 0x7fffffff) % 1000000).zfill(6)


def fresh_mfa(page, owned, sql, account):
    last = sql('SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id=%s', (account,))[0][0]
    while last is not None and int(time.time()) // 30 <= last:
        page.wait_for_timeout(250)
    return totp(owned)


def run(r, check, cli, races):
    owned, origin, sql, out = [r[key] for key in ['owned', 'origin', 'sql', 'OUT']]
    out.mkdir(parents=True, exist_ok=True)
    (out / 'screens').mkdir(exist_ok=True)
    record = json.loads((owned / 'oper09-advisor.json').read_text())
    fixture = json.loads((owned / 'workspace02-fixture.json').read_text())
    password = (owned / 'applicant-password').read_text().strip()
    progress=owned/'workspace02-browser-progress.json'
    previous_checks=json.loads(progress.read_text()).get('checks',[]) if progress.exists() else []
    results = {'checks': [], 'responsive': [], 'page_errors': []}

    def observed(label, passed):
        check(label, passed)
        results['checks'].append(label)
        (owned / 'workspace02-browser-progress.json').write_text(json.dumps(results))

    with sync_playwright() as pw:
        db, dc, d = review.native_browser(pw)
        review.login(d, origin, 'qa_director@example.test', (owned / 'fixture-password').read_text().strip(), review.DIRECTOR)
        expect(d.locator('#add-member')).to_be_enabled()
        old.selected(d, record)

        def grants(desired):
            # Other independent synthetic reviewers may open Director sessions.
            # Reauthenticate through the real login before this exact UI batch.
            review.login(d, origin, 'qa_director@example.test', (owned / 'fixture-password').read_text().strip(), review.DIRECTOR)
            expect(d.locator('#add-member')).to_be_enabled()
            old.selected(d, record)
            for family in ['requests', 'media']:
                if not d.locator('#use-' + family).is_visible():
                    d.locator('[data-expand="use-' + family + '"]').click()
            changed = False
            for cap in CAPS + MEDIA:
                box = d.locator('[data-capability="' + cap + '"]')
                value = cap in desired
                if box.is_checked() != value:
                    box.set_checked(value)
                    changed = True
            if changed:
                d.locator('#save-permissions').click()
                d.locator('#accept-confirmation').click()
                expect(d.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS', timeout=25000)
            d.reload()
            expect(d.locator('#add-member')).to_be_enabled()
            old.selected(d, record)
            current = {row[0] for row in sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE'", (record['account'],))}
            observed('Director grants exact narrow request capabilities through existing canonical UI', set(desired) <= current and not (set(CAPS + MEDIA) - set(desired)) & current)

        grants(MEDIA + [CAPS[0]])
        ab, ac, a = review.native_browser(pw)
        a.on('pageerror', lambda error: results['page_errors'].append(str(error)))
        review.login(a, origin, record['email'], password)
        expect(a.locator('#staff-name')).to_contain_text('Irene')
        open_request(a, fixture['claim_ready']['application_id'])
        observed('Read capability does not imply evidence access or decision authority',
                 not a.locator('#approve-request').is_visible() and not a.locator('#reject-request').is_visible() and a.locator('#request-evidence button').count() == 0)
        grants(MEDIA + CAPS)
        if not sql('SELECT factor_id FROM auth_staff_mfa_factors WHERE account_id=%s', (record['account'],)):
            cli('scripts/ase-advisor-workspace02-fixture.php', 'enroll_advisor')
        a.reload()
        expect(a.locator('#discovery-list')).to_have_attribute('aria-busy', 'false', timeout=20000)
        expect(a.locator('#staff-name')).to_contain_text('Irene')

        def resolve(key, decision, lose_response=False):
            request_id = fixture[key]['application_id']
            before = snapshot(a, request_id)['request']
            open_request(a, request_id)
            if before['state'] == decision:
                observed('Canonical persisted request decision: ' + key, sql('SELECT COUNT(*) FROM auth_physician_profile_request_decisions WHERE origin_id=%s AND state=%s', (request_id, decision))[0][0] == 1)
                if lose_response:
                    capture=json.loads((owned/'workspace02-dropped-response.json').read_text())
                    for label in ['Lost committed decision response never triggers a blind mutation retry','Authorized result lookup recovers actual committed outcome']:
                        observed(label,label in previous_checks and capture['statuses']==[200] and capture['response']['data']['request_id']==request_id)
                return before
            if decision == 'APPROVED':
                inspect_evidence(a)
            action_id = {'APPROVED': 'approve-request', 'REJECTED': 'reject-request', 'NEEDS_INFORMATION': 'request-information'}[decision]
            a.locator('#' + action_id).click()
            expect(a.locator('#request-decision-form')).to_be_visible()
            a.locator('#request-reason').fill('Resolución sintética de ' + key + '; datos ficticios revisados.')
            a.locator('#request-password').fill(password)
            code = fresh_mfa(a, owned, sql, record['account'])
            a.locator('#request-mfa').fill(code)
            a.locator('#request-confirmation').check()
            commits = []
            responses = []
            def record_response(response):
                if '/api/internal/workspace/requests.php' in response.url and response.request.method == 'POST':
                    body=response.json()
                    responses.append({'action':response.request.post_data_json.get('action'),'status':response.status,'error':body.get('error')})
                    (owned / 'workspace02-decision-responses.json').write_text(json.dumps(responses))
            a.on('response',record_response)

            def dropped(route):
                if route.request.method == 'POST' and route.request.post_data_json.get('action') == 'decide':
                    forwarded=route.request.all_headers()
                    (owned / 'workspace02-intercept-headers.json').write_text(json.dumps({key:forwarded.get(key) for key in ['origin','sec-fetch-site','referer','content-type']}))
                    # APIRequestContext does not preserve Chromium fetch-metadata
                    # automatically; replay this same-origin browser request exactly.
                    forwarded['sec-fetch-site']='same-origin'
                    response = route.fetch(headers=forwarded)
                    commits.append(response.status)
                    (owned / 'workspace02-dropped-response.json').write_text(json.dumps({'statuses':commits,'response':response.json()}))
                    if response.status == 200:
                        route.abort('failed')
                    else:
                        route.fulfill(response=response)
                else:
                    route.continue_()

            if lose_response:
                a.route('**/api/internal/workspace/requests.php', dropped)
            a.locator('#confirm-request-decision').click()
            if lose_response:
                expect(a.locator('#request-recovery')).to_be_visible(timeout=30000)
                a.unroute('**/api/internal/workspace/requests.php', dropped)
                observed('Lost committed decision response never triggers a blind mutation retry', commits == [200])
                a.locator('#lookup-request-result').click()
                expect(a.locator('#request-recovery')).not_to_be_visible(timeout=20000)
                observed('Authorized result lookup recovers actual committed outcome', 'recuperó' in a.locator('#request-feedback').inner_text())
            expect(a.locator('#request-status')).to_have_text({'APPROVED': 'Aprobada', 'REJECTED': 'Rechazada', 'NEEDS_INFORMATION': 'Información solicitada'}[decision], timeout=30000)
            a.remove_listener('response',record_response)
            result = snapshot(a, request_id)['request']
            observed('Canonical persisted request decision: ' + key, result['state'] == decision and sql('SELECT COUNT(*) FROM auth_physician_profile_request_decisions WHERE origin_id=%s AND state=%s', (request_id, decision))[0][0] == 1)
            return result

        claim_id = fixture['claim_approve']['doctor_id']
        profile_before = sql('SELECT doctor_id,display_name,profile_status,plan_code,preloaded_free_reference FROM profiles_doctors WHERE doctor_id=%s', (claim_id,))[0]
        claim = resolve('claim_approve', 'APPROVED')
        observed('Claim review preserves original free profile content and ID', profile_before == sql('SELECT doctor_id,display_name,profile_status,plan_code,preloaded_free_reference FROM profiles_doctors WHERE doctor_id=%s', (claim_id,))[0] and claim['doctor_id'] == claim_id)
        observed('Approval UI separates approval and remaining canonical ownership stages', 'titularidad' in a.locator('#request-outcome').inner_text().lower())
        previous.tab(a, 'Historial')
        expect(a.locator('.history-entry')).not_to_have_count(0)
        observed('Canonical request history identifies actual Advisor and request', record['account'] in a.locator('#history-list').inner_text() or 'Irene' in a.locator('#history-list').inner_text())
        rejected_id = fixture['claim_reject']['doctor_id']
        rejected_before = sql('SELECT doctor_id,display_name,profile_status,plan_code FROM profiles_doctors WHERE doctor_id=%s', (rejected_id,))[0]
        resolve('claim_reject', 'REJECTED')
        observed('Claim rejection preserves profile and applicant account', rejected_before == sql('SELECT doctor_id,display_name,profile_status,plan_code FROM profiles_doctors WHERE doctor_id=%s', (rejected_id,))[0] and sql('SELECT status FROM auth_accounts WHERE account_id=%s', (fixture['claim_reject']['account_id'],))[0][0] == 'active')
        information = resolve('information', 'NEEDS_INFORMATION')
        observed('Information request remains unresolved without a materialized profile', information['doctor_id'] is None and information['information_history'])
        created = resolve('new_approve', 'APPROVED', lose_response=True)
        observed('New approval materializes exactly one hidden free canonical profile', created['doctor_id'] and sql('SELECT COUNT(*) FROM auth_physician_profile_request_profiles WHERE origin_id=%s', (fixture['new_approve']['application_id'],))[0][0] == 1 and sql('SELECT profile_status,plan_code FROM profiles_doctors WHERE doctor_id=%s', (created['doctor_id'],))[0] == ('hidden', 'free'))
        previous.tab(a, 'Historial')
        expect(a.locator('.history-entry')).not_to_have_count(0)
        observed('Application history remains linked after canonical profile materialization', fixture['new_approve']['application_id'] in a.locator('#history-list').inner_text() and created['doctor_id'] in a.locator('#history-list').inner_text())
        rejected_new = resolve('new_reject', 'REJECTED')
        observed('Rejected new application creates no profile', rejected_new['doctor_id'] is None)

        open_request(a, fixture['claim_ready']['application_id'])
        selected_doctor = a.locator('#work-panel').get_attribute('data-doctor-id')
        a.locator('#request-information').click()
        a.locator('#request-reason').fill('Borrador ligado a esta solicitud')
        a.once('dialog', lambda dialog: dialog.dismiss())
        previous.tab(a, 'Historial')
        observed('Canceling navigation preserves exact request draft', a.locator('#request-reason').input_value() == 'Borrador ligado a esta solicitud' and a.locator('#requests-panel').is_visible())
        a.once('dialog', lambda dialog: dialog.accept())
        previous.tab(a, 'Imágenes')
        observed('Request to Images retains same physician and discards confirmed draft', a.locator('#work-panel').get_attribute('data-doctor-id') == selected_doctor and not a.locator('#request-decision-form').is_visible())
        image = a.locator('#media-items [data-submission-id]').first
        image.click()
        expect(a.locator('#review-image')).to_be_visible()
        observed('Existing real Media review remains functional in unified physician context', a.locator('#review-image').evaluate('(img)=>img.complete&&img.naturalWidth>0'))
        previous.tab(a, 'Historial')
        expect(a.locator('#history-message')).not_to_have_text('Cargando historial…')
        observed('History retains same physician context', a.locator('#work-panel').get_attribute('data-doctor-id') == selected_doctor)

        open_request(a, fixture['revoked']['application_id'])
        a.locator('#request-information').click()
        a.locator('#request-reason').fill('Borrador previo a revocación')
        grants(MEDIA + CAPS[:3])
        a.locator('#refresh-requests').click()
        expect(a.locator('#request-decision-form')).not_to_be_visible(timeout=20000)
        observed('Revoked decision capability clears stale draft and action in existing Advisor session', not a.locator('#request-information').is_visible() and snapshot(a, fixture['revoked']['application_id'])['request']['allowed_actions'] == [])
        current = snapshot(a, fixture['revoked']['application_id'])['request']
        denied = command(a, 'begin', {'request_id': current['request_id'], 'intake_version': current['intake_version'], 'review_version': current['review_version'], 'revision_token': current['revision_token'], 'decision': 'REJECTED', 'reason': 'Intento sintético sin permiso', 'email': record['email'], 'password': password})
        observed('Revoked decision is denied server-side', denied['status'] == 403 and sql('SELECT COUNT(*) FROM auth_physician_profile_request_decisions WHERE origin_id=%s', (fixture['revoked']['application_id'],))[0][0] == 0)
        grants(MEDIA + CAPS)
        a.reload()

        # Hold an actual authorized request read for A while the user selects B.
        pending = []
        request_a = fixture['claim_ready']['application_id']
        request_b = fixture['new_ready']['application_id']

        def delayed(route):
            pending.append((route, route.fetch()))

        pattern = '**/requests.php?mode=read&request_id=' + request_a
        a.route(pattern, delayed)
        a.locator('#discovery-list [data-request-id="' + request_a + '"]').click()
        a.wait_for_timeout(200)
        a.locator('#discovery-list [data-request-id="' + request_b + '"]').click()
        expect(a.locator('#request-detail')).to_have_attribute('data-request-id', request_b)
        for route, response in pending:
            route.fulfill(response=response)
        a.unroute(pattern, delayed)
        a.wait_for_timeout(200)
        observed('Late request A cannot replace selected new application B', len(pending) > 0 and a.locator('#request-detail').get_attribute('data-request-id') == request_b and a.locator('#physician-name').inner_text() == 'Solicitud de perfil nuevo' and a.locator('#work-panel').get_attribute('data-doctor-id') == '')
        observed('Selected application collapses competing search and pending blocks', not a.locator('#discovery').is_visible() and not a.locator('#physician-search-form').is_visible())

        native = a.evaluate('({width:innerWidth,height:innerHeight,outerHeight,availableHeight:screen.availHeight,scrollWidth:document.documentElement.scrollWidth})')
        observed('Native delivered viewport fits actual physical browser work area', native['outerHeight'] <= native['availableHeight'] and native['scrollWidth'] <= native['width'])
        a.screenshot(path=str(out / 'screens' / 'native-application.png'))
        results['native'] = native

        for engine in ['chromium', 'webkit']:
            browser = getattr(pw, engine).launch()
            for width, height in [(1440, 900), (1366, 768), (390, 844)]:
                ctx = browser.new_context(ignore_https_errors=True, viewport={'width': width, 'height': height})
                page = ctx.new_page()
                page.on('pageerror', lambda error: results['page_errors'].append(str(error)))
                review.login(page, origin, record['email'], password)
                open_request(page, request_a)
                previous.tab(page, 'Solicitudes')
                page.locator('#request-information').click()
                button = page.locator('#cancel-request-decision')
                button.scroll_into_view_if_needed()
                reachable = button.evaluate('(el)=>{const r=el.getBoundingClientRect();return r.bottom<=innerHeight&&r.top>=0&&el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}')
                page.locator('#cancel-request-decision').click()
                previous.tab(page, 'Historial')
                expect(page.locator('#history-message')).not_to_have_text('Cargando historial…')
                previous.tab(page, 'Solicitudes')
                inspect_evidence(page)
                geometry = page.evaluate('({width:innerWidth,height:innerHeight,scroll:document.documentElement.scrollWidth})')
                passed = geometry['scroll'] <= width and reachable and page.locator('#work-panel').get_attribute('data-doctor-id') == fixture['claim_ready']['doctor_id']
                observed('Responsive requests/history/evidence and reachable confirmation: %s %sx%s' % (engine, width, height), passed)
                page.screenshot(path=str(out / 'screens' / ('%s-%s-requests.png' % (engine, width))))
                results['responsive'].append({'engine': engine, 'width': width, 'height': height, 'passed': passed})
                logout=page.evaluate('''async()=>{const b=await(await fetch('/api/identity/index.php/current-session',{cache:'no-store'})).json();const r=await fetch('/api/identity/index.php/logout',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':b.csrf_token},body:JSON.stringify({csrf_token:b.csrf_token})});return r.ok&&(await r.json()).state==='LOGOUT_SUCCESS';}''')
                assert logout, 'responsive synthetic session logout'
                ctx.close()
            browser.close()
        observed('No browser JavaScript exceptions during focused journeys', results['page_errors'] == [])
        observed('Ready claim and new application preserved for Director review', snapshot(a, request_a)['request']['state'] == 'PENDING_REVIEW' and snapshot(a, request_b)['request']['state'] == 'PENDING_REVIEW')
        results['ready'] = {'claim': request_a, 'new': request_b, 'completed': fixture['claim_approve']['application_id']}
        (out / 'browser-result.json').write_text(json.dumps(results, indent=2))
        ab.close()
        db.close()
    return results
