"""Focused PANEL04 interactions in a new owned synthetic instance. No financial fixtures inferred."""
import importlib.util,json,subprocess,uuid,time,hmac,struct

def run(r,check,races):
    spec=importlib.util.spec_from_file_location('panel_fixture',r['ROOT']/'scripts/ase-adm02-uxflow03-checks.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
    m.browser=browser # Reuse only canonical fixture preparation, not historical browser suites.
    m.run(r,check,races)

def browser(r,check,records,command,consumer,good,csrf,secret,password):
    from playwright.sync_api import sync_playwright,expect
    root,owned,sql,tokens,origin,request=[r[k] for k in ['ROOT','owned','sql','tokens','origin','request']]
    shots=root/'docs/evidence/ase-adm02-panel04/screens';shots.mkdir(parents=True,exist_ok=True)
    url=origin+'/internal/media-review/index.php?section=equipo'
    def team(actor,target,category='ADVISOR'):
        return request(actor,path='/api/internal/team/read.php?category='+category+'&target='+target,method='GET')
    def fixture(action):
        x=subprocess.run(['php','modules/identity/tests/StaffWorkflowFixture.php',action],cwd=root,env=r['env'],input='{}',text=True,capture_output=True)
        assert x.returncode==0 and json.loads(x.stdout)['ok'],action
    fixture('panel_limited_master')
    csrf['master']=good('bootstrap',actor='master')['csrf_token']
    check('Master scope contains only two explicit delegations',team('master',records['bruno']['account'])[1]['authority']['delegations']==['media_review_approve','media_review_read'])
    check('Master cannot enumerate peers or Director',team('master','qa_master','MASTER_ADMIN')[0]!=200 and team('master','qa_director')[0]!=200)
    check('Applicant cannot read staff financial/activity projection',team('fernanda',records['bruno']['account'])[0]!=200)
    check('Master cannot prepare admissions',request('master',path='/api/internal/staff-admission/workflow.php',method='POST',body={'action':'prepare','input':dict(operation_id=str(uuid.uuid4()),recipient_reference=str(uuid.uuid4()),channel='ASSISTED',proposed_category='ADVISOR',given_names='Negativa',first_surname='Sintética',second_surname='',operation_city='',operation_state='')},headers={'X-CSRF-Token':csrf['master']})[0]!=200)
    with sync_playwright() as pw:
        b=pw.chromium.launch();errors=[]
        def page(actor):
            c=b.new_context(ignore_https_errors=True,viewport={'width':1440,'height':900});c.add_cookies([{'name':'__Host-mxmed_session','value':tokens[actor],'url':origin,'secure':True,'httpOnly':True,'sameSite':'Strict'}]);p=c.new_page();p.on('pageerror',lambda e:errors.append(str(e)));p.goto(url);expect(p.locator('[data-member]')).not_to_have_count(0);return p
        p=page('director')
        def select(actor):
            p.locator('[data-member="'+records[actor]['account']+'"]').click();expect(p.locator('#detail-title')).to_have_text(records[actor]['name'])
        check('Exact header and canonical Director role',p.locator('.workspace-header .eyebrow').inner_text()=='MÉXICO MÉDICO · EQUIPO INTERNO' and p.locator('h1').inner_text()=='EQUIPO INTERNO Y PERMISOS' and p.locator('.subtitle').inner_text()=='Administración de asesores, administradores y funciones autorizadas.' and p.locator('#team-role').inner_text()=='Director')
        check('Unrelated menu and persistent banners absent',p.locator('.team-navigation,.preview-notice,.preview-footer').count()==0 and 'Consulta de relaciones staff' not in p.locator('body').inner_text())
        check('Staff hierarchy and exact category order',p.locator('.team-introduction').inner_text()=='Staff\n\nRevisa el alcance disponible para cada integrante.' and p.locator('.category-filter button').all_inner_texts()==['Administradores maestros','Asesores'])
        check('Add button follows the member list',p.locator('#add-member').evaluate('(b)=>Boolean(document.getElementById("directory-content").compareDocumentPosition(b)&Node.DOCUMENT_POSITION_FOLLOWING)'))
        p.locator('#add-member').click();expect(p.locator('#onboarding')).to_be_visible();p.locator('#new-given_names').fill('Gloria Panel');p.locator('#new-first_surname').fill('Demostración');p.locator('#save-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Borrador guardado')
        for n in [2,3,4]:p.locator('#next-onboarding').click();expect(p.locator('#onboarding-title')).to_contain_text('paso '+str(n))
        check('Approved wizard still writes canonical draft',sql("SELECT COUNT(*) FROM auth_staff_admission_versions WHERE given_names='Gloria Panel'")[0][0]==1)
        p.locator('#cancel-onboarding').click();select('bruno')
        titles=['DATOS PERSONALES','DOCUMENTACIÓN','ACCESO Y FUNCIONES','GOBIERNO RESERVADO Y LÍMITES','CLIENTES','HISTORIAL']
        check('Requested section order with commercial excluded without assignment',p.locator('#advisor-detail > details > summary').all_inner_texts()==titles and p.locator('#commercial-conditions').count()==0)
        check('Five permission groups only',p.locator('#operational-matrix .family').count()==5 and p.locator('#commercial-proposal').count()==0)
        p.locator('#identity-evidence > summary').click();expect(p.locator('#identity-evidence')).to_contain_text('Identificación INE');p.locator('#member-evidence').click();expect(p.locator('#staff-case')).to_be_visible();expect(p.locator('[data-review-action="READ"]')).to_be_enabled()
        p.locator('[data-review-action="READ"]').click();expect(p.locator('#staff-stepup')).to_be_visible();p.locator('#staff-password').fill(password);p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible()
        last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
        while int(time.time())//30<=last:p.wait_for_timeout(300)
        digest=hmac.new(secret,struct.pack('>Q',int(time.time())//30),'sha1').digest();off=digest[-1]&15;p.locator('#staff-code').fill(str((int.from_bytes(digest[off:off+4],'big')&0x7fffffff)%1000000).zfill(6));p.locator('#staff-execute').click();expect(p.locator('#staff-stepup')).to_be_hidden(timeout=20000);expect(p.locator('#case-pages img')).to_have_count(2)
        check('Private documents still require canonical password and MFA',True);p.locator('#close-staff-case').click()
        select('alma');p.locator('#personal-data > summary').click();p.locator('#member-edit').click();p.locator('#edit-operation_city').fill('Ciudad Panel');p.locator('#save-staff-profile').click();expect(p.locator('#staff-profile')).to_be_hidden();select('alma');p.locator('#personal-data > summary').click();expect(p.locator('#personal-data')).to_contain_text('Ciudad Panel')
        check('Personal edit versioned and persisted',sql('SELECT operation_city,provenance FROM auth_staff_admission_versions WHERE case_id=%s ORDER BY version DESC LIMIT 1',(records['alma']['case'],))[0]==('Ciudad Panel','ADMIN_PREPARED'))
        g=p.locator('#operational-matrix [data-family="media"] [data-group-count]');expect(g).to_be_enabled();g.check();p.locator('[data-expand="use-media"]').click();p.locator('[data-capability="media_review_approve"]').uncheck();check('Individual and group mixed state',g.evaluate('(e)=>e.indeterminate'));p.locator('#revert-permissions').click();expect(g).not_to_be_checked();g.check();p.locator('[data-capability="media_review_approve"]').uncheck();p.locator('#save-permissions').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000)
        check('Permissions persist and actual consumer allows',consumer('alma'));p.reload();select('alma');p.locator('[data-expand="use-media"]').click();p.locator('[data-capability="media_review_read"]').uncheck();p.locator('#save-permissions').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000);check('Revoke enforced by actual consumer',not consumer('alma'))
        select('celia');p.locator('#remove-member').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIO DE ACCESO CONFIRMADO',timeout=20000);expect(p.locator('[data-member]')).to_have_count(2);p.reload();expect(p.locator('[data-member]')).to_have_count(2);p.locator('#staff-status').select_option('REMOVED');expect(p.locator('#advisor-detail .status')).to_have_text('Baja definitiva');check('Removal persists outside active list and keeps history',bool(team('director',records['celia']['account'])[1]['items'][0]['history']['items']))
        check('Removed and suspended grants denied server-side',command('grant',records['celia']['account'],'media_review_read')[0]!=200 and command('grant',records['diego']['account'],'media_review_read')[0]!=200)
        p.locator('#staff-status').select_option('ACTIVE');select('bruno');p.locator('#member-history > summary').click()
        payload=team('director',records['bruno']['account'])[1]['items'][0];events=payload['history']['items'];dbids={x[0] for x in sql('SELECT event_id FROM platform_audit_events')}
        check('History contains only existing unique chronological events',events and all(e['id'] in dbids for e in events) and len({e['id'] for e in events})==len(events) and [(e['at'],e['id']) for e in events]==sorted([(e['at'],e['id']) for e in events],reverse=True))
        check('History distinguishes actor from administered subject',{e['direction'] for e in events}=={'BY_MEMBER','ON_MEMBER'})
        check('No financial facts or seller inferred',payload['commercial_activity'] is None and 'commission' not in json.dumps(payload))
        oldids=p.locator('[data-event]').evaluate_all('(es)=>es.map(e=>e.dataset.event)');select('alma');newids=p.locator('[data-event]').evaluate_all('(es)=>es.map(e=>e.dataset.event)');check('Member switch never retains another history',not set(oldids)&set(newids));select('bruno')
        for w,h in [(1440,900),(1366,768),(390,844)]:
            p.set_viewport_size({'width':w,'height':h})
            if w==390:p.locator('#show-directory').click()
            p.locator('#add-member').click();expect(p.locator('#onboarding')).to_be_visible();check('Add reachable and wizard fits '+str(w),p.locator('#onboarding').evaluate('(e)=>e.scrollWidth<=e.clientWidth+1'));p.locator('#cancel-onboarding').click()
            if w==390:p.locator('#show-detail').click()
            for section in ['personal-data','identity-evidence','member-limits','member-clients','member-history']:
                p.locator('#'+section).evaluate('(e)=>e.open=true')
            check('Panel responsive '+str(w),p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));p.screenshot(path=str(shots/f'panel-{w}.png'),full_page=True)
        p.set_viewport_size({'width':1440,'height':900});p.locator('#filter-masters').click();expect(p.locator('#advisor-detail .eyebrow')).to_have_text('Administrador maestro');check('Master category and active-only list',all(x['staff_status']=='ACTIVE' for x in request('director',path='/api/internal/team/read.php?category=MASTER_ADMIN',method='GET')[1]['items']))
        p.locator('#filter-advisors').click();p.locator('#advisor-search').fill('no-match-panel');expect(p.locator('[data-member]')).to_have_count(0);expect(p.locator('#add-member')).to_be_enabled();p.locator('#add-member').click();expect(p.locator('#onboarding')).to_be_visible();p.locator('#cancel-onboarding').click();check('Add remains reachable with empty list',True)
        mp=page('master');expect(mp.locator('#team-role')).to_have_text('Administrador maestro');expect(mp.locator('#filter-masters')).to_be_disabled();expect(mp.locator('#add-member')).to_be_disabled();mp.locator('[data-member="'+records['bruno']['account']+'"]').click();expect(mp.locator('#remove-member')).to_be_disabled();mp.locator('[data-expand="use-media"]').click();expect(mp.locator('[data-capability="media_review_read"]')).to_be_enabled();expect(mp.locator('[data-capability="physician_accreditation_review"]')).to_be_disabled()
        master_data=team('master',records['bruno']['account'])[1]['items'][0];check('Master minimized projection excludes personal and document data',master_data['personal_profile'] is None and 'documents' not in master_data and 'admission_case_id' not in master_data)
        check('Master history only delegated grants',master_data['history']['coverage']=='DELEGATED_GRANTS' and all('Permiso' in e['description'] for e in master_data['history']['items']))
        mp.locator('[data-capability="media_review_read"]').uncheck();mp.locator('#save-permissions').click();mp.locator('#accept-confirmation').click();expect(mp.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000);check('Limited Master legitimately revokes delegated permission',not consumer('bruno'))
        check('Master cannot forge undelegated command',command('grant',records['bruno']['account'],'physician_accreditation_review','master')[0]==403)
        assert command('grant',records['bruno']['account'],'media_review_read')[0]==200
        # Confirm logout actually revokes the previous token, including direct protected requests.
        p.locator('#team-logout').click();expect(p.locator('h1')).to_have_text('Sesión cerrada');check('Canonical logout revokes server session',team('director',records['bruno']['account'])[0]!=200 and not p.locator('[data-member]').count())
        check('No JavaScript errors',not errors);b.close()
    # Re-authenticate canonically for preserved Director review, no lifetime changes.
    x=subprocess.run(['php','modules/identity/tests/StaffInvitationFixture.php','login'],cwd=root,env=r['env'],input=json.dumps({'actor':'director','email':'qa_director@example.test','existing':True}),text=True,capture_output=True);assert x.returncode==0 and json.loads(x.stdout)['ok'];tokens.update(json.loads((owned/'sessions.json').read_text()))
    print('PANEL04 focused interactions complete',flush=True)
