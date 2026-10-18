"""Owned browser integration only; called after native security regression in the same fresh instance."""
import json,time,hmac,hashlib,struct,subprocess,traceback
from playwright.sync_api import sync_playwright,expect

def _run(c):
    r=c['r'];qa=c['qa'];root=c['ROOT'];sql=c['sql'];owned=c['owned'];env=c['env'];check=c['check'];success=c['success'];now=c['now'];origin=r['origin'];shots=qa.OUT/'screenshots';shots.mkdir(exist_ok=True);results=[];errors=[]
    def fresh_factor():
        sql("UPDATE auth_staff_mfa_factors SET status='REVOKED',revoked_at=UTC_TIMESTAMP(6) WHERE account_id='qa_director' AND status='ACTIVE'");now[0]=int(time.time());success('enroll')
    def restart(enabled=True):
        live=next(p for p in reversed(qa.processes) if p.poll() is None and p.args[0]=='php' and '-S' in p.args);live.terminate();live.wait(timeout=10)
        config=env.copy()
        if not enabled:config.pop('MXMED_MASTER_GOVERNANCE_ENABLED',None)
        r['spawn']('ui-http',['php','-S','127.0.0.1:'+str(r['php_port']),'-t',str(root),str(root/qa.ROUTER)],config);qa.wait_until(lambda:r['port_ready'](r['php_port']))
    def rebind():
        authorization=success('deployment');env['MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION_SHA256']=authorization['pin'];restart()
    restart();fresh_factor()
    req=r['request'];path='/api/internal/team/master/csrf.php'
    check('GET rejects absent Origin and Referer',req(path=path,method='GET',headers={'Origin':None})[0]==403)
    check('GET rejects foreign Referer',req(path=path,method='GET',headers={'Origin':None,'Referer':'https://evil.example/'})[0]==403)
    check('Browser GET accepts exact same-origin Referer',req(path=path,method='GET',headers={'Origin':None,'Referer':origin+'/internal/media-review/index.php?section=equipo'})[0]==200)
    def reset():
        sql("UPDATE internal_staff SET governance_class='MASTER_ADMIN',status='ACTIVE' WHERE account_id='qa_target'");sql("UPDATE auth_accounts SET status='active' WHERE account_id='qa_target'")
        sql("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=UTC_TIMESTAMP(6) WHERE account_id='qa_target' AND capability='internal_advisors_manage' AND status='ACTIVE'")
        sql("UPDATE internal_capability_delegations SET status='REVOKED',revoked_at=UTC_TIMESTAMP(6) WHERE master_account_id='qa_target' AND status='ACTIVE'")
    def audit():return sql("SELECT event_id FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operation')) IN ('appoint_master','remove_master','grant','revoke','delegate','revoke_delegation')")
    def code():
        secret=(owned/'mfa-secret').read_bytes();step=int(time.time())//30+1;digest=hmac.new(secret,struct.pack('>Q',step),hashlib.sha1).digest();off=digest[-1]&15;return str((int.from_bytes(digest[off:off+4],'big')&0x7fffffff)%1000000).zfill(6)
    password=(owned/'fixture-password').read_text().strip()
    def page(browser,actor='director',w=1440,h=900,master=True):
        ctx=browser.new_context(ignore_https_errors=True,viewport={'width':w,'height':h});ctx.add_cookies([{'name':'__Host-mxmed_session','value':r['tokens'][actor],'domain':'127.0.0.1','path':'/','secure':True,'httpOnly':True,'sameSite':'Strict'}]);p=ctx.new_page();p.set_default_timeout(12000);p.on('pageerror',lambda e:errors.append(str(e)));p.goto(origin+'/internal/media-review/index.php?section=equipo')
        if master:p.locator('#filter-masters').click()
        p.locator('[data-member="qa_target" ]').click();p.locator('#show-detail').evaluate('(e)=>e.click()');return ctx,p
    def state(p,s):expect(p.locator('#confirmation-body')).to_have_attribute('data-master-state',s)
    def shot(p,name):
        # Screenshots only when no entered credential/code is present. Inputs are masked regardless.
        p.screenshot(path=str(shots/(name+'.png')),full_page=True,mask=[p.locator('#master-password'),p.locator('#master-code'),p.locator('#master-email')])
    def begin(p,selector,tag=None):
        fresh_factor();p.locator(selector).click();state(p,'PENDING_CONFIRMATION');
        check('Modal has no horizontal overflow',p.locator('#confirmation').evaluate('(e)=>e.scrollWidth<=e.clientWidth'))
        check('Acknowledgement touch row',p.locator('.ack-row').last.evaluate('(e)=>e.getBoundingClientRect().height>=44'))
        if tag:shot(p,tag+'-confirmation')
        p.keyboard.press('Tab');check('Keyboard focus remains in modal',p.evaluate('document.querySelector("#confirmation").contains(document.activeElement)'));p.keyboard.press('Shift+Tab')
        check('Explicit acknowledgement required',p.locator('#accept-confirmation').is_disabled());p.locator('[data-member="qa_master"]').evaluate_all('(els)=>els.forEach(e=>e.click())');check('Target switching locked', 'qa_target' in p.locator('#confirmation-body').inner_text());p.locator('#master-ack').check()
        def opening(route):
            state(p,'EXECUTION_PENDING');check('Loading blocks confirmation and cancellation',p.locator('#accept-confirmation').is_disabled() and p.locator('#cancel-confirmation').is_disabled())
            if tag:shot(p,tag+'-loading')
            route.continue_()
        p.route('**/api/internal/team/master/open.php',opening);p.locator('#accept-confirmation').click();state(p,'REAUTHENTICATION_REQUIRED');p.unroute('**/api/internal/team/master/open.php',opening)
    def auth(p,tag=None):
        p.locator('#master-email').fill('qa_director@example.test');p.locator('#master-password').fill(password);p.locator('#accept-confirmation').click();state(p,'MFA_CHALLENGE_PENDING')
        if tag:shot(p,tag+'-mfa')
        p.locator('#master-code').fill(code());p.locator('#accept-confirmation').click();state(p,'MFA_VERIFIED')
    def finish(p):p.locator('#accept-confirmation').click();state(p,'CONFIRMED');p.keyboard.press('Escape');expect(p.locator('#confirmation')).not_to_be_visible()
    def management(p):p.locator('.master-governance').evaluate('(e)=>e.open=true');expect(p.locator('#master-management')).to_be_enabled()
    with sync_playwright() as pw:
      for engine in ['webkit','chromium']:
        browser=getattr(pw,engine).launch()
        for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
            reset();fresh_factor();ctx,p=page(browser,w=w,h=h);tag=f'{engine}-{w}x{h}';management(p)
            presentation=p.evaluate("window.teamMasterActionPresentation({operation:'INTERNAL_MASTER_APPOINT',capability:null,member:{}})")
            dom=ctx.new_page();dom.set_content('<main><h1>Proponer como Master · presentación aislada</h1>'+presentation+'</main>');check(tag+' appointment DOM only consequences','no concede gestión, delegación ni permisos operativos' in dom.locator('main').inner_text());dom.close()
            shot(p,tag+'-management');begin(p,'#master-management',tag);shot(p,tag+'-password');check(tag+' password semantics',p.locator('#master-password').get_attribute('type')=='password');auth(p,tag);shot(p,tag+'-verified');n=len(audit());finish(p);check(tag+' management audit',len(audit())==n+1)
            management(p);begin(p,'#master-management');auth(p);finish(p)
            management(p);p.locator('[data-expand="delegate-media"]').click();sel='#delegation-matrix [data-capability="media_review_read"]';begin(p,sel);auth(p);finish(p)
            management(p);p.locator('[data-expand="delegate-media"]').click();begin(p,sel);auth(p);finish(p)
            expect(p.locator('#designation')).to_be_enabled();begin(p,'#designation');check(tag+' demotion consequences','suspensión vigente se conserva' in p.locator('#confirmation-body').inner_text());auth(p);finish(p)
            expect(p.locator('#master-status')).to_contain_text('falta conectar');expect(p.locator('#advisor-detail .team-reference').first).to_contain_text('qa_target');check(tag+' appointment disabled',p.locator('#designation').is_disabled());expect(p.locator('#detail-title')).to_be_focused();check(tag+' focus restored after refresh',True);shot(p,tag+'-appointment-unavailable')
            check(tag+' no overflow',p.evaluate('document.documentElement.scrollWidth<=innerWidth'))
            check(tag+' no browser storage',p.evaluate('localStorage.length===0&&sessionStorage.length===0'))
            results.append({'engine':engine,'viewport':f'{w}x{h}','five_real_http_ui_operations':'PASS','appointment':'PRODUCT_BLOCKED','mobile':'INTERIM_SMOKE_ONLY' if w==390 else 'N/A'});ctx.close()
        # Existing Advisor editor and lifecycle remain usable; draft navigation requires resolution.
        reset();sql("UPDATE internal_staff SET governance_class='ADVISOR' WHERE account_id='qa_target'");sql("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=UTC_TIMESTAMP(6) WHERE account_id='qa_target' AND capability='media_review_approve' AND status='ACTIVE'");ctx,p=page(browser,master=False)
        expect(p.locator('#lifecycle')).to_be_enabled();p.locator('[data-expand="use-media"]').click();grant=p.locator('#operational-matrix [data-capability="media_review_approve"]');grant.check();p.locator('#filter-masters').click();expect(p.locator('#confirmation-title')).to_contain_text('Descartar');p.locator('#cancel-confirmation').click();check(engine+' dirty draft preserved and Master context blocked',grant.is_checked() and p.locator('#filter-advisors').get_attribute('aria-pressed')=='true')
        p.locator('#save-permissions').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_contain_text('CAMBIOS CONFIRMADOS');check(engine+' Advisor grant UI retained',bool(sql("SELECT 1 FROM internal_operator_grants WHERE account_id='qa_target' AND capability='media_review_approve' AND status='ACTIVE'")))
        p.locator('#lifecycle').click();p.locator('#accept-confirmation').click();expect(p.locator('#lifecycle')).to_contain_text('REACTIVAR');expect(p.locator('#lifecycle')).to_be_enabled();p.locator('#lifecycle').click();p.locator('#accept-confirmation').click();expect(p.locator('#lifecycle')).to_contain_text('SUSPENDER');expect(p.locator('#lifecycle')).to_be_enabled();check(engine+' Advisor lifecycle UI retained',sql("SELECT status FROM internal_staff WHERE account_id='qa_target'")[0][0]=='ACTIVE');ctx.close()
        reset();sql("UPDATE auth_staff_mfa_factors SET status='REVOKED',revoked_at=UTC_TIMESTAMP(6) WHERE account_id='qa_director' AND status='ACTIVE'");ctx,p=page(browser);expect(p.locator('#master-status')).to_have_attribute('data-state','MISSING_MFA_FACTOR');check(engine+' missing MFA closes actions',p.locator('#designation').is_disabled() and p.locator('#master-management').is_disabled());ctx.close();fresh_factor()
        sql("UPDATE internal_staff SET governance_class='ADVISOR' WHERE account_id='qa_target'");ctx,p=page(browser,'master',master=False);check(engine+' Master UI cannot designate',p.locator('#designation').count()==0 and p.locator('#filter-masters').is_disabled());ctx.close()
        # Independent fault injection changes transport only, never successful product authority.
        for fault in ['cancel','wrong_password','invalid_totp','expired','conflict','lost_ack','unknown_ack','audit_failure','duplicate','suspended','disabled']:
            reset();fresh_factor()
            if fault in ['suspended','disabled']:
                sql("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'qa_target','internal_advisors_manage','ACTIVE')")
                if fault=='suspended':sql("UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_target'")
                else:sql("UPDATE auth_accounts SET status='disabled' WHERE account_id='qa_target'")
            ctx,p=page(browser);management(p);n=len(audit());posts=[]
            p.on('request',lambda req:posts.append(req.url) if '/master/execute.php' in req.url else None)
            if fault in ['suspended','disabled']:
                check(engine+' '+fault+' no delegation increase',p.locator('#delegation-matrix input:enabled').count()==0)
            begin(p,'#master-management')
            if fault=='cancel':p.keyboard.press('Escape');state(p,'CANCELED');check(engine+' canceled no mutation',len(audit())==n);p.locator('#accept-confirmation').click()
            elif fault=='wrong_password':
                p.locator('#master-email').fill('qa_director@example.test');p.locator('#master-password').fill('wrong');p.locator('#accept-confirmation').click();state(p,'NO_AUTHORITY');p.locator('#cancel-confirmation').click();state(p,'CANCELED')
            elif fault=='invalid_totp':
                p.locator('#master-email').fill('qa_director@example.test');p.locator('#master-password').fill(password);p.locator('#accept-confirmation').click();state(p,'MFA_CHALLENGE_PENDING');p.locator('#master-code').fill('000000');p.locator('#accept-confirmation').click();state(p,'NO_AUTHORITY');p.locator('#cancel-confirmation').click();state(p,'CANCELED')
            elif fault=='expired':
                # Advance browser presentation clock; server expiration remains independently tested natively.
                p.evaluate('Date.now=()=>new Date().getTime()+301000');state(p,'EXPIRED');shot(p,engine+'-expired');p.evaluate('Date.now=()=>new Date().getTime()');p.locator('#cancel-confirmation').click();state(p,'CANCELED')
            else:
                auth(p)
                if fault=='conflict':sql("UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_target'")
                if fault=='audit_failure':
                    sql("CREATE TRIGGER ui_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic UI audit failure'");rebind()
                if fault in ['lost_ack','unknown_ack']:
                    def lost(route):
                        original_headers=route.request.all_headers()
                        check(engine+' lost ACK clones only same-origin request',route.request.url.startswith(origin+'/api/internal/team/master/') and original_headers.get('origin',origin)==origin)
                        # Routing happens before browser-generated Fetch Metadata is attached.
                        # Clone the same-origin transport metadata, retaining actual cookie/body/CSRF/proof.
                        original_headers.update({'origin':origin,'sec-fetch-site':'same-origin','referer':origin+'/internal/media-review/index.php?section=equipo'})
                        route.fetch(headers=original_headers)
                        if fault=='unknown_ack':p.route('**/api/internal/team/master/read.php?*',lambda x:x.abort())
                        route.abort()
                    p.route('**/api/internal/team/master/execute.php',lost)
                p.locator('#accept-confirmation').evaluate('(e)=>{e.click();e.click();}')
                if fault=='unknown_ack':
                    state(p,'UNKNOWN_OUTCOME');shot(p,engine+'-unknown');p.unroute('**/api/internal/team/master/read.php?*');p.locator('#accept-confirmation').click();state(p,'CONFIRMED')
                elif fault in ['conflict','audit_failure']:
                    state(p,'CONFLICT');check(engine+' '+fault+' rollback',len(audit())==n);shot(p,engine+'-'+fault)
                    if fault=='audit_failure':
                        import re
                        case_id=re.search(r'master_[a-f0-9]{32}',p.locator('#confirmation-body').inner_text()).group(0)
                        check(engine+' UI audit rolls back proof and case',sql('SELECT status FROM auth_staff_mfa_proofs WHERE case_id=%s',(case_id,))==(('VERIFIED',),) and sql('SELECT status FROM auth_governance_cases WHERE case_id=%s',(case_id,))==(('OPEN',),))
                        sql('DROP TRIGGER ui_audit_failure');rebind()
                    p.locator('#cancel-confirmation').click();state(p,'CANCELED')
                else:state(p,'CONFIRMED');check(engine+' '+fault+' one audit',len(audit())==n+1)
                check(engine+' '+fault+' no retry',len(posts)==1)
            ctx.close()
        reset();restart(False);ctx,p=page(browser);expect(p.locator('#master-status')).to_have_attribute('data-state','MASTER_GATE_DISABLED');check(engine+' disabled gate closes controls',p.locator('#designation').is_disabled() and p.locator('#master-management').is_disabled());ctx.close();restart();browser.close()
    check('Browser runtime errors absent',not errors)
    boundary=json.loads((qa.OUT/'security-boundaries.json').read_text());boundary.update(master_ui_enabled=True,master_ui_scope='ONLY_NEW_OWNED_SYNTHETIC_QA',original_runtime_master_ui_enabled=False,actual_lost_network_ack_injected=True,product_master_ready=False);(qa.OUT/'security-boundaries.json').write_text(json.dumps(boundary,indent=2)+'\n')
    (qa.OUT/'browser.json').write_text(json.dumps({'result':'PASS','cases':results,'runtime_errors':errors,'physical_safari':'NOT_RUN','mobile':'INTERIM_SMOKE_ONLY','appointment_positive_http':'NOT_RUN_PRODUCT_GATE_BLOCKED','appointment_positive_presentation':'PURE_DOM_ONLY_NO_AUTHORITY_OR_HTTP','actual_lost_ack_injected':True,'lost_ack_transport':'Playwright route.fetch same-origin clone retains cookie/body/CSRF/proof and supplies browser-generated Origin/Fetch-Site; response aborted after canonical backend returns','screenshots_mask_secrets':True},indent=2)+'\n')

def run(c):
    try:_run(c)
    except Exception as e:
        # Never persist Playwright call logs: fill() errors can include secret input values.
        frames=[{'file':f.filename,'line':f.lineno,'function':f.name} for f in traceback.extract_tb(e.__traceback__)]
        (c['owned']/'ui-failure.json').write_text(json.dumps({'type':type(e).__name__,'frames':frames},indent=2))
        raise RuntimeError('browser_verification_failed; private ui-failure.json has stack locations only') from None
