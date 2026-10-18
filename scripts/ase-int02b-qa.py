#!/usr/bin/env python3
"""Browser integration over the shared, newly owned MySQL/Valkey/TLS QA primitives.
Network faults interrupt real commands; no synthetic successful API responses.
"""
import importlib.util, json, pathlib, subprocess, threading, time, hashlib, socket, hmac
import pymysql
from playwright.sync_api import sync_playwright, expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('owned',ROOT/'scripts/ase-int02a-qa.py');qa=importlib.util.module_from_spec(spec);spec.loader.exec_module(qa)
qa.PHASE='ase-int02b';qa.EXPECTED_BRANCH='feature/ase-int02b-team-permission-ui';qa.BASE='05e428cf4354b1f722090c7532ced3784e0d4985';qa.OUT=ROOT/'docs/evidence/ase-int02b';qa.ROUTER='scripts/ase-int02b-router.php';qa.EXTRA_PROTECTED=['mxmed-ase-int02a-governed-grants']
qa.EXTRA_SOURCES=['scripts/ase-int02b-qa.py',qa.ROUTER,'assets/js/internal-team.js','assets/js/internal-team-editor.js','partials/identity/internal-team.php']
VISUAL='312ee388f93c892e6caf7e845b11e2b42f9c3565'
measure="""() => Object.fromEntries(['.advisor-workspace','.workspace-columns','.directory','.detail','h1','.directory.panel'].map(selector=>{const e=document.querySelector(selector),s=getComputedStyle(e),r=e.getBoundingClientRect();return [selector,{width:Math.round(r.width*100)/100,font:s.fontFamily,fontSize:s.fontSize,color:s.color,background:s.backgroundColor,radius:s.borderRadius,gap:s.gap,columns:s.gridTemplateColumns,display:s.display}]}))"""

def run(runtime):
    sql=runtime['sql'];tokens=runtime['tokens'];origin=runtime['origin'];command=runtime['command'];request=runtime['request'];env=runtime['env'];owned=runtime['owned']
    qa.OUT.mkdir(parents=True,exist_ok=True);shots=qa.OUT/'screenshots';shots.mkdir(exist_ok=True)
    results=[];visual=[];receipts=[];errors=[];post_payloads=[];overlaps=[]
    check=qa.check
    for i in range(21):
        aid=f'qa_zz_{i:02d}';sql("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status) VALUES(%s,%s,%s,'active')",(aid,aid+'@example.test',aid+'@example.test'));sql("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(%s,'ADVISOR','ACTIVE')",(aid,))
    def grants():return [r[0] for r in sql("SELECT capability FROM internal_operator_grants WHERE account_id='qa_target' AND status='ACTIVE' AND revoked_at IS NULL ORDER BY capability")]
    def audit():return sql("SELECT real_actor_reference,resource_reference,metadata_json FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED'")
    def reset():
        # Fixture edits confined to the runner's newly-created DB. Audit events are never erased.
        sql("DELETE FROM internal_operator_grants WHERE account_id='qa_target'")
        sql("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'qa_target','media_review_read','ACTIVE')")
        sql("UPDATE internal_staff SET status='ACTIVE',governance_class='ADVISOR' WHERE account_id='qa_target'")
        sql("UPDATE internal_staff SET status='ACTIVE' WHERE account_id='qa_master'")
        sql("UPDATE internal_operator_grants SET status='ACTIVE',revoked_at=NULL WHERE account_id='qa_master' AND capability='internal_advisors_manage'")
        sql("UPDATE internal_capability_delegations SET status='ACTIVE',revoked_at=NULL WHERE master_account_id='qa_master'")
        sql("DELETE FROM internal_capability_delegations WHERE master_account_id='qa_master' AND capability<>'media_review_approve'")
    def delegate(cap):sql("INSERT INTO internal_capability_delegations(delegation_id,master_account_id,capability,authorized_by_account_id,status) VALUES(UUID(),'qa_master',%s,'qa_director','ACTIVE')",(cap,))
    approved=subprocess.check_output(['git','show',VISUAL+':prototypes/ase-vis01/index.html'],cwd=ROOT,text=True)
    for old,new in [('style.css','internal-team.css'),('commercial.css','internal-team-commercial.css'),('admission.css','internal-team-admission.css')]:
        css=subprocess.check_output(['git','show',VISUAL+':prototypes/ase-vis01/'+old],cwd=ROOT,text=True)
        check('Approved CSS byte-identical '+new,css.encode()==(ROOT/'assets/css'/new).read_bytes())
        approved=approved.replace('<link rel="stylesheet" href="/'+old+'">','<style>'+css+'</style>')
    approved=approved.replace('<link rel="stylesheet" href="/assets/css/media-review-inbox.css">','<style>'+(ROOT/'assets/css/media-review-inbox.css').read_text()+'</style>')
    # Static approved composition, no prototype service or mutations needed.
    import re
    approved=re.sub(r'<script\b[^>]*>.*?</script>','',approved,flags=re.S)
    def context(browser,actor,w=1440,h=900):
        ctx=browser.new_context(ignore_https_errors=True,viewport={'width':w,'height':h})
        ctx.add_cookies([{'name':'__Host-mxmed_session','value':tokens[actor],'domain':'127.0.0.1','path':'/','secure':True,'httpOnly':True,'sameSite':'Strict'}])
        page=ctx.new_page();page.set_default_timeout(12000);page.on('pageerror',lambda e:errors.append(str(e)))
        outstanding=set()
        def started(req):
            if '/api/internal/team/capability.php' in req.url:
                outstanding.add(req);post_payloads.append(req.post_data_json);overlaps.append(len(outstanding))
        page.on('request',started);page.on('requestfinished',lambda req:outstanding.discard(req));page.on('requestfailed',lambda req:outstanding.discard(req))
        def record(response):
            if '/api/internal/team/capability.php' in response.url:
                body=response.request.post_data_json
                receipts.append({'status':response.status,'operation':body['operation'],'target':body['target'],'capability':body['capability']})
        page.on('response',record)
        return ctx,page
    def open_target(page):
        page.goto(origin+'/internal/media-review/index.php?section=equipo');page.locator('[data-member="qa_target"]').click();expect(page.locator('.detail .team-reference')).to_contain_text('qa_target')
        expect(page.locator('#team-mode')).to_have_text('PERMISOS CON CONFIRMACIÓN')
    def cap(page,key):return page.locator('#operational-matrix [data-capability="'+key+'"]')
    def select_keys(page,*keys):
        if page.locator('#use-media').is_hidden():page.locator('[data-expand="use-media"]').click()
        for key in keys:cap(page,key).check()
    def submit(page,expectation='CAMBIOS CONFIRMADOS'):
        page.locator('#save-permissions').click();expect(page.locator('#confirmation')).to_be_visible();page.locator('#accept-confirmation').click()
        expect(page.locator('#permission-result-title')).to_contain_text(expectation)
        expect(page.locator('#pending-permissions')).not_to_contain_text('Procesando')
    def shot(page,name):page.screenshot(path=str(shots/(name+'.png')),full_page=True)
    with sync_playwright() as pw:
      for engine in ['chromium','webkit']:
        browser=getattr(pw,engine).launch()
        for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
            reset();ctx,page=context(browser,'director',w,h);open_target(page);tag=f'{engine}-{w}x{h}'
            ref=ctx.new_page();ref.set_content(approved);ref.wait_for_timeout(100)
            # Integrated mobile detail is deliberately switched; compare approved shared geometry with directory visible.
            page.locator('#show-directory').evaluate('(e)=>e.click()')
            before=ref.evaluate(measure);after=page.evaluate(measure)
            differences=[{'selector':sel,'property':key,'approved':val,'integrated':after[sel][key]} for sel,values in before.items() for key,val in values.items() if after[sel][key]!=val]

            if differences:(qa.OUT/'visual-diagnostic.json').write_text(json.dumps(differences,indent=2)+'\n')
            check(tag+' approved shared geometry',not differences);visual.append({'engine':engine,'viewport':f'{w}x{h}','differences':differences,'approved':before,'integrated':after});ref.close()
            page.locator('#show-detail').evaluate('(e)=>e.click()')
            check(tag+' five families',page.locator('#operational-matrix .family').count()==5)
            check(tag+' gated unavailable actions',page.locator('#lifecycle').is_disabled() and page.locator('#designation').is_disabled() and page.locator('#add-member').is_disabled())
            group=page.locator('#operational-matrix [data-family="media"] [data-group-count]')
            check(tag+' E mixed state',group.evaluate('(e)=>e.indeterminate'))
            group.click();select_keys(page,'media_review_approve');cap(page,'media_review_improve').uncheck()
            check(tag+' D individual override',group.evaluate('(e)=>e.indeterminate'))
            check(tag+' no autosave',grants()==['media_review_read'])
            page.locator('#revert-permissions').click();check(tag+' revert',not cap(page,'media_review_approve').is_checked())
            group.click();cap(page,'media_review_improve').uncheck();shot(page,tag+'-draft')
            page.locator('#save-permissions').click();expect(page.locator('#confirmation')).to_be_visible()
            check(tag+' confirmation Spanish and sensitive', 'qa_target' in page.locator('#confirmation-description').text_content() and 'originales privados' in page.locator('#confirmation-body').text_content())
            check(tag+' keyboard cancel focus',page.locator('#cancel-confirmation').evaluate('(e)=>document.activeElement===e'))
            shot(page,tag+'-confirmation');page.keyboard.press('Escape');expect(page.locator('#confirmation')).not_to_be_visible();check(tag+' cancel no write',grants()==['media_review_read'])
            n=len(audit());progress=[False]
            def progress_capture(route):
                if not progress[0]:shot(page,tag+'-progress');progress[0]=True
                route.continue_()
            page.route('**/api/internal/team/capability.php',progress_capture)
            submit(page);page.unroute('**/api/internal/team/capability.php');check(tag+' progress busy observed',progress[0]);check(tag+' C D F U canonical group subset',len(grants())==5 and 'media_review_improve' not in grants() and len(audit())==n+4)
            check(tag+' all per-command confirmed',page.locator('[data-outcome="success"]').count()==4)
            shot(page,tag+'-confirmed');check(tag+' no overflow',page.evaluate('document.documentElement.scrollWidth<=innerWidth'))
            sizes=page.locator('.family-toggle').evaluate_all('(es)=>es.filter(e=>e.getBoundingClientRect().width).map(e=>({w:e.getBoundingClientRect().width,h:e.getBoundingClientRect().height}))')
            check(tag+' touch controls approved minimum',all(x['w']>=24 and x['h']>=24 for x in sizes))
            check(tag+' ephemeral storage',page.evaluate('localStorage.length===0 && sessionStorage.length===0'))
            reset();open_target(page);select_keys(page,'media_review_approve','media_review_source_download','media_review_corrected_upload');responsive_posts=[0]
            def responsive_partial(route):
                responsive_posts[0]+=1
                if responsive_posts[0]==2:sql("CREATE TRIGGER ase_int02b_responsive_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'")
                response=route.fetch()
                if responsive_posts[0]==2:sql('DROP TRIGGER ase_int02b_responsive_failure')
                route.fulfill(response=response)
            page.route('**/api/internal/team/capability.php',responsive_partial);submit(page,'CAMBIOS APLICADOS PARCIALMENTE');shot(page,tag+'-partial-uncertain')
            check(tag+' partial uncertain stopped',responsive_posts[0]==2 and page.locator('[data-outcome="unknown"]').count()==1 and page.locator('[data-outcome="not_attempted"]').count()==1)
            check(tag+' partial no overflow',page.evaluate('document.documentElement.scrollWidth<=innerWidth'))
            page.unroute('**/api/internal/team/capability.php');select_keys(page,'media_review_improve');check(tag+' competing canonical update',command(capability='media_review_source_download')[0]==200)
            page.locator('#save-permissions').click();expect(page.locator('#permission-result-title')).to_contain_text('CONFLICTO');shot(page,tag+'-conflict')
            results.append({'engine':engine,'viewport':f'{w}x{h}','result':'PASS','touch_controls':sizes});ctx.close()
        # Security and failure suite runs on real APIs in both engines.
        reset();ctx,page=context(browser,'director');open_target(page);select_keys(page,'media_review_approve');n=len(audit());submit(page)
        check(engine+' A individual grant',grants()==['media_review_approve','media_review_read'] and len(audit())==n+1)
        select_keys(page,'media_review_approve');cap(page,'media_review_approve').uncheck();submit(page);check(engine+' B individual revoke',grants()==['media_review_read'])
        select_keys(page,'media_review_improve');page.locator('[data-member="qa_advisor"]').click();expect(page.locator('#confirmation')).to_be_visible();page.locator('#cancel-confirmation').click();check(engine+' W dirty cancel retains target', 'qa_target' in page.locator('.detail .team-reference').text_content() and cap(page,'media_review_improve').is_checked())
        page.locator('#next-team').click();expect(page.locator('#confirmation')).to_be_visible();page.locator('#cancel-confirmation').click();check(engine+' page dirty guard',page.locator('#team-page').text_content()=='Página 1' and cap(page,'media_review_improve').is_checked())
        page.locator('#advisor-search').fill('qa_advisor');expect(page.locator('#confirmation')).to_be_visible();page.locator('#cancel-confirmation').click();check(engine+' search dirty guard',page.locator('#advisor-search').input_value()=='')
        page.locator('#filter-masters').click();expect(page.locator('#confirmation')).to_be_visible();page.locator('#cancel-confirmation').click();check(engine+' category dirty guard',cap(page,'media_review_improve').is_checked())
        page.locator('.team-navigation a').first.click();expect(page.locator('#confirmation')).to_be_visible();page.locator('#cancel-confirmation').click();check(engine+' section dirty guard','section=equipo' in page.url)
        page.locator('[data-member="qa_advisor"]').click();page.locator('#accept-confirmation').click();expect(page.locator('.detail .team-reference')).to_contain_text('qa_advisor');check(engine+' discard no save',grants()==['media_review_read'])
        page.locator('#filter-masters').click();expect(page.locator('.detail .eyebrow')).to_have_text('Administrador maestro');check(engine+' I master edit disabled',page.locator('#operational-matrix input:enabled').count()==0 and page.locator('#delegation-matrix input:enabled').count()==0)
        ctx.close();ctx,page=context(browser,'advisor');resp=page.goto(origin+'/internal/media-review/index.php?section=equipo');check(engine+' J advisor denied',resp.status==403);ctx.close()
        reset();ctx,page=context(browser,'master');open_target(page);group=page.locator('[data-family="media"] [data-group-count]');group.click();select_keys(page,'media_review_approve')
        check(engine+' G delegated subset',page.locator('#operational-matrix [data-capability]:enabled').count()==1)
        check(engine+' H hidden unknown',cap(page,'media_review_read').evaluate('(e)=>e.indeterminate&&e.disabled'))
        submit(page);group=page.locator('[data-family="media"] [data-group-count]');group.click();submit(page);check(engine+' H hidden grant retained',grants()==['media_review_read']);ctx.close()
        # Drift after editing: no POST and conflict. A concurrent canonical command supplies the new state.
        reset();ctx,page=context(browser,'director');open_target(page);select_keys(page,'media_review_approve');n=len(receipts);check(engine+' concurrent writer succeeds',command(capability='media_review_improve')[0]==200)
        page.locator('#save-permissions').click();expect(page.locator('#permission-result-title')).to_contain_text('CONFLICTO');check(engine+' O snapshot conflict no write',len(receipts)==n);shot(page,engine+'-conflict');ctx.close()
        for fault in ['409','503','lost_ack','failed_refresh','management','delegation','target_suspended','actor_suspended','session_expired','csrf_rejected']:
            reset();actor='master' if fault in ['management','delegation','actor_suspended'] else 'director'
            if actor=='master':delegate('media_review_source_download');delegate('media_review_corrected_upload')
            ctx,page=context(browser,actor);open_target(page);select_keys(page,'media_review_approve','media_review_source_download','media_review_corrected_upload')
            count=[0];post_states=[];start_audit=len(audit());start_receipts=len(receipts)
            def intercept(route):
                count[0]+=1
                if count[0]==2 and fault=='409':check(engine+' competing writer',command(capability='media_review_source_download')[0]==200)
                if count[0]==2 and fault=='503':
                    sql("CREATE TRIGGER ase_int02b_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'")
                headers=route.request.headers
                if count[0]==2 and fault=='csrf_rejected':headers['x-csrf-token']='invalid-synthetic-token'
                response=route.fetch(headers=headers);post_states.append(response.status)
                if count[0]==2 and fault=='503':sql('DROP TRIGGER ase_int02b_audit_failure')
                if count[0]==1:
                    if fault=='management':sql("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='qa_master' AND capability='internal_advisors_manage'")
                    if fault=='delegation':sql("UPDATE internal_capability_delegations SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE master_account_id='qa_master' AND capability='media_review_source_download'")
                    if fault=='actor_suspended':sql("UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_master'")
                    if fault=='target_suspended':sql("UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_target'")
                    if fault=='session_expired':
                        # Force TTL expiry only in our private Valkey, for this synthetic canonical session.
                        digest=hmac.new(env['MXMED_PREVIEW_PEPPER'].encode(),tokens[actor].encode(),hashlib.sha256).hexdigest()
                        key='mxmed:gate4d:preview:session:'+digest
                        args=['PEXPIRE',key,'1'];data='*3\r\n'+''.join('$'+str(len(a))+'\r\n'+a+'\r\n' for a in args)
                        with socket.create_connection(('127.0.0.1',runtime['valkey_port'])) as sock:sock.sendall(data.encode());reply=sock.recv(100)
                        check(engine+' owned session TTL set',reply==b':1\r\n');time.sleep(.02)
                if fault=='failed_refresh' and count[0]==1:page.route('**/api/internal/team/read.php?*',lambda r:r.abort())
                if fault in ['lost_ack','failed_refresh'] and count[0]==1:route.abort()
                else:route.fulfill(response=response)
            page.route('**/api/internal/team/capability.php',intercept)
            expected='ESTADO PENDIENTE DE CONFIRMAR' if fault in ['lost_ack','failed_refresh','session_expired','management','actor_suspended'] else 'CAMBIOS APLICADOS PARCIALMENTE'
            submit(page,expected)
            check(engine+' '+fault+' stops sequence',count[0]==(2 if fault in ['409','503','csrf_rejected'] else 1))
            check(engine+' '+fault+' not attempted retained',page.locator('[data-outcome="not_attempted"]').count()>=1)
            check(engine+' '+fault+' no compensation', 'media_review_approve' in grants())
            check(engine+' '+fault+' audit committed only',len(audit())==start_audit+(2 if fault=='409' else 1))
            if fault=='csrf_rejected':check(engine+' CSRF stop no replay',post_states==[200,403] and page.locator('[data-outcome="failed"]').count()==1)
            if fault=='409':check(engine+' P 409 not success',post_states==[200,409] and page.locator('[data-outcome="failed"]').count()==1)
            if fault in ['503','lost_ack','failed_refresh']:check(engine+' Q unknown retained',page.locator('[data-outcome="unknown"]').count()==1)
            shot(page,engine+'-'+fault)
            if fault=='failed_refresh':
                page.unroute('**/api/internal/team/read.php?*');page.locator('#reconcile-permissions').click();expect(page.locator('#permission-result-title')).to_contain_text('no se atribuye');check(engine+' explicit follow-up no replay',count[0]==1)
            ctx.close()
            if fault=='session_expired':
                # Refresh fixture sessions after this deliberate expiry for subsequent cases.
                r=subprocess.run(runtime['fixture']+['refresh_sessions'],cwd=ROOT,env=env,text=True,capture_output=True,check=True);tokens.update(json.loads(r.stdout));(owned/'sessions.json').write_text(json.dumps(tokens))
                for who in ['director','master']:runtime['csrf'][who]=request(who,path='/api/internal/team/csrf.php',method='GET')[1]['csrf_token']
        reset();ctx,page=context(browser,'director');open_target(page);select_keys(page,'media_review_approve');n=len(audit())
        page.locator('#save-permissions').evaluate('(e)=>{e.click();e.click();}');expect(page.locator('#confirmation')).to_be_visible();page.locator('#accept-confirmation').evaluate('(e)=>{e.click();e.click();}');expect(page.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS');check(engine+' X duplicate suppressed',len(audit())==n+1);ctx.close()
        reset();ctx,page=context(browser,'director');page.route('**/api/internal/team/csrf.php',lambda r:r.abort());page.goto(origin+'/internal/media-review/index.php?section=equipo');page.locator('[data-member="qa_target"]').click();expect(page.locator('#permission-result-title')).to_contain_text('SÓLO CONSULTA');check(engine+' bootstrap fail closed',page.locator('#operational-matrix input:enabled').count()==0 and page.locator('#save-permissions').is_disabled());ctx.close()
        browser.close()
      # True independent SQL connection blocks the HTTP writer. Interleave target/authority changes under lock.
      reset()
      for name,lock,change,restore,actor in [
        ('target suspension','qa_target',"UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_target'","UPDATE internal_staff SET status='ACTIVE' WHERE account_id='qa_target'",'director'),
        ('management revocation','qa_master',"UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='qa_master' AND capability='internal_advisors_manage'","UPDATE internal_operator_grants SET status='ACTIVE',revoked_at=NULL WHERE account_id='qa_master' AND capability='internal_advisors_manage'",'master'),
        ('delegation revocation','qa_master',"UPDATE internal_capability_delegations SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE master_account_id='qa_master'","UPDATE internal_capability_delegations SET status='ACTIVE',revoked_at=NULL WHERE master_account_id='qa_master'",'master')]:
        blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=runtime['fixture_password'],database=runtime['database'],autocommit=False)
        try:
            with blocker.cursor() as cur:cur.execute('SELECT status FROM auth_accounts WHERE account_id=%s FOR UPDATE',(lock,));cur.fetchall()
            with qa.concurrent.futures.ThreadPoolExecutor(max_workers=1) as pool:
                future=pool.submit(command,actor,'grant','qa_target','media_review_approve')
                qa.wait_until(lambda:bool(sql('SELECT 1 FROM performance_schema.data_lock_waits LIMIT 1')),10)
                waits=sql('SELECT REQUESTING_ENGINE_TRANSACTION_ID,REQUESTING_THREAD_ID,BLOCKING_ENGINE_TRANSACTION_ID,BLOCKING_THREAD_ID FROM performance_schema.data_lock_waits')
                with blocker.cursor() as cur:cur.execute(change)
                blocker.commit();response=future.result(timeout=15)
            check('Real two-connection '+name,response[0]==403 and len(waits)>0)
            qa.concurrency.append({'name':name,'connections':'independent fixture transaction + HTTP canonical writer','waits':waits,'http_status':response[0]})
        finally:blocker.rollback();blocker.close();sql(restore)
      check('Y no group grant created',not sql("SELECT capability FROM internal_operator_grants WHERE capability IN ('media_review','all','*','accreditation','providers')"))
      events=audit();check('Audit canonical actor target operation capability',all(e[0].startswith('account:qa_') and json.loads(e[2])['producer_metadata']['target_account_id']==e[1] and 'capability' in json.loads(e[2])['producer_metadata'] for e in events))
      # Restart only the exact PHP process owned by this invocation with the gate removed.
      php=next(p for p in qa.processes if p.args[0]=='php' and '-S' in p.args);php.terminate();php.wait(timeout=10)
      off=env.copy();off.pop('MXMED_TEAM_ADVISOR_MUTATIONS_ENABLED')
      runtime['spawn']('php-gate-off',['php','-S','127.0.0.1:'+str(runtime['php_port']),'-t',str(ROOT),str(ROOT/qa.ROUTER)],off);qa.wait_until(lambda:runtime['port_ready'](runtime['php_port']))
      status,data,_=request(path='/api/internal/team/read.php?target=qa_target',method='GET');check('Z default gate server reports unavailable',status==200 and data['authority']['mutations_enabled'] is False and data['items'][0]['editable_capabilities']==[])
      check('Z default gate command denied',command()[0]==503)
      for engine in ['chromium','webkit']:
        browser=getattr(pw,engine).launch();ctx,page=context(browser,'director');page.goto(origin+'/internal/media-review/index.php?section=equipo');page.locator('[data-member="qa_target"]').click();expect(page.locator('.detail .team-reference')).to_contain_text('qa_target');check(engine+' Z default gate UI readonly',page.locator('#operational-matrix input:enabled').count()==0 and page.locator('#save-permissions').is_disabled());ctx.close();browser.close()
    check('Sequential browser requests no overlap',max(overlaps)==1)
    check('Exact individual schema and target',all(set(p)=={'operation','target','capability'} and p['target']=='qa_target' and isinstance(p['capability'],str) for p in post_payloads))
    check('No browser script exceptions',not errors)
    report={'result':'PASS','cases':results,'visual_comparison':visual,'approved_visual_commit':VISUAL,'comparison':'Approved shared geometry/styles; state content differs by authorized UI-2 connection. No zero-pixel claim.','api_receipts':receipts,'browser_post_count':len(post_payloads),'maximum_simultaneous_browser_posts':max(overlaps),'audit_event_count':len(events),'audit_evidence':[{'actor':a,'target':t,'operation':json.loads(m)['producer_metadata']} for a,t,m in events],'javascript_errors':errors,'physical_safari':'NOT_RUN','mobile':'INTERIM_SMOKE_ONLY','mutations':'owned synthetic runtime only','no_live_activation':True}
    (qa.OUT/'browser.json').write_text(json.dumps(report,indent=2)+'\n')
    print('ASE-INT02B browser cases',len(results),'checks',len(qa.checks),flush=True)
qa.HOOK=run
if __name__=='__main__':qa.main()
