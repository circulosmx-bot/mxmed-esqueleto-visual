#!/usr/bin/env python3
"""Targeted lifecycle QA using existing fresh-instance primitives, no old full suites."""
import importlib.util,json,pathlib,subprocess,hashlib,re,socket,hmac,time
import pymysql
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('owned',ROOT/'scripts/ase-int02a-qa.py');qa=importlib.util.module_from_spec(spec);spec.loader.exec_module(qa)
qa.PHASE='ase-int03a';qa.EXPECTED_BRANCH='feature/ase-int03a-advisor-lifecycle';qa.BASE='36eeb51655a1c24248405feae579cefc06ff3b44';qa.OUT=ROOT/'docs/evidence/ase-int03a';qa.ROUTER='scripts/ase-int03a-router.php';qa.EXTRA_PROTECTED=['mxmed-ase-int02a-governed-grants','mxmed-ase-int02b-team-permission-ui'];qa.EXTRA_WRITABLE_TABLES=['internal_staff']
qa.EXTRA_SOURCES=['scripts/ase-int03a-qa.py',qa.ROUTER,'api/internal/team/lifecycle.php','assets/js/internal-team-editor.js','assets/js/internal-team.js','partials/identity/internal-team.php','modules/identity/repositories/InternalOperatorGrantRepository.php','modules/identity/services/InternalOperatorAuthority.php','modules/identity/services/StaffMfaService.php']

def run(r):
    sql=r['sql'];request=r['request'];check=qa.check;tokens=r['tokens'];env=r['env'];owned=r['owned'];origin=r['origin'];events=[];receipts=[];cases=[];errors=[]
    qa.OUT.mkdir(parents=True,exist_ok=True);shots=qa.OUT/'screenshots';shots.mkdir(exist_ok=True)
    def cmd(actor='director',op='suspend',target='qa_target',**headers):return request(actor,path='/api/internal/team/lifecycle.php',body={'operation':op,'target':target},headers={'X-CSRF-Token':r['csrf'].get(actor,r['csrf']['director']),**headers})
    def state():return sql("SELECT status FROM internal_staff WHERE account_id='qa_target'")[0][0]
    def audit():return sql("SELECT real_actor_reference,resource_reference,metadata_json FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED'")
    def grants():return sql("SELECT * FROM internal_operator_grants WHERE account_id='qa_target' ORDER BY grant_id")
    def operational():
        p=subprocess.run(r['fixture']+['operational'],cwd=ROOT,env=env,text=True,capture_output=True)
        if p.returncode:(owned/'operational-error.log').write_text(p.stderr);raise RuntimeError('operational_probe_failed')
        return json.loads(p.stdout)
    def reset():
        sql("UPDATE internal_staff SET status='ACTIVE',governance_class='ADVISOR' WHERE account_id='qa_target'")
        sql("UPDATE internal_staff SET status='ACTIVE' WHERE account_id='qa_master'")
        sql("UPDATE auth_accounts SET status='active' WHERE account_id='qa_target'")
        sql("UPDATE auth_account_credentials SET credential_version=1 WHERE account_id='qa_director'")
        sql("UPDATE internal_operator_grants SET status='ACTIVE',revoked_at=NULL WHERE account_id='qa_master' AND capability='internal_advisors_manage'")
    sql("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status,revoked_at) VALUES(UUID(),'qa_target','media_review_improve','REVOKED',CURRENT_TIMESTAMP)")
    before_grants=grants();unrelated=sql("SELECT * FROM internal_staff WHERE account_id<>'qa_target' ORDER BY account_id")
    check('Operational authority initially available',operational()['allowed'])
    for actor in ['director','master']:
        n=len(audit());check(actor+' suspend canonical',cmd(actor)[0]==200 and state()=='SUSPENDED');check(actor+' suspension blocks next operational use',not operational()['allowed'])
        check(actor+' duplicate suspend conflict',cmd(actor)[0]==409)
        check(actor+' reactivate canonical',cmd(actor,'reactivate')[0]==200 and state()=='ACTIVE');check(actor+' duplicate reactivate conflict',cmd(actor,'reactivate')[0]==409)
        probe=operational();check(actor+' reactivation keeps applicable gates',probe['allowed'] and probe['mfa_context_still_required'])
        check(actor+' grants and revoked rows unchanged',grants()==before_grants);check(actor+' two atomic audit events',len(audit())==n+2)
    check('Other staff unchanged',sql("SELECT * FROM internal_staff WHERE account_id<>'qa_target' ORDER BY account_id")==unrelated)
    for actor,status in [('advisor',403),('target',403),('suspended',403),('disabled',401),('expired',401),('revoked',401),('changed',401),(None,401)]:
        for op in ['suspend','reactivate']:check('Actor denied '+str(actor)+' '+op,cmd(actor,op)[0]==status)
    for actor in ['director','master']:
        for target in ['qa_director','qa_master','qa_master2','qa_disabled_target','qa_outsider','qa_nonexistent']:
            for op in ['suspend','reactivate']:check('Target denied '+actor+' '+target+' '+op,cmd(actor,op,target)[0]==403)
    sql("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='qa_master' AND capability='internal_advisors_manage'")
    check('Management revocation stops action',cmd('master')[0]==403);reset()
    for hs in [{'X-CSRF-Token':'invalid'},{'Origin':'https://evil.example'},{'Sec-Fetch-Site':'cross-site'},{'Origin':None}]:check('CSRF origin denied '+str(len(qa.checks)),cmd(**hs)[0]==403)
    for body in [{'operation':'suspend','target':'qa_target','actor':'qa_director'},{'operation':'suspend','target':'qa_target','role':'DIRECTOR'},{'operation':'suspend','target':['qa_target']},{'operation':'suspend','target':'qa_target','capability':'media_review_read'},[],{},'{"operation":"suspend","target":"qa_target","target":"qa_master"}']:
        check('Strict lifecycle payload '+str(len(qa.checks)),request(path='/api/internal/team/lifecycle.php',body=json.dumps(body) if not isinstance(body,str) else body,headers={'X-CSRF-Token':r['csrf']['director']})[0]==400)
    for op in ['register','appoint_master','remove_master','delegate','revoke_delegation','grant','revoke']:check('Excluded '+op,cmd(op=op)[0]==400)
    for method in ['GET','HEAD','PUT','DELETE']:check('Method '+method,request(path='/api/internal/team/lifecycle.php',method=method)[0]==405)
    check('Payload bound',request(path='/api/internal/team/lifecycle.php',body='x'*1025)[0]==413)
    check('JSON type required',cmd(**{'Content-Type':'text/plain'})[0]==415)
    check('No role query',request(path='/api/internal/team/lifecycle.php?role=DIRECTOR')[0]==400)
    n=len(audit());heads=sql('SELECT * FROM platform_audit_stream_heads ORDER BY stream_key')
    sql("CREATE TRIGGER ase_int03a_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'")
    try:check('Audit failure unavailable',cmd()[0]==503)
    finally:sql('DROP TRIGGER ase_int03a_failure')
    check('Atomic rollback staff audit head',state()=='ACTIVE' and len(audit())==n and heads==sql('SELECT * FROM platform_audit_stream_heads ORDER BY stream_key'))
    # Actual independent transactions; current reads after observed lock wait.
    for name,lock,change,actor,status in [
        ('target category','qa_target',"UPDATE internal_staff SET governance_class='MASTER_ADMIN' WHERE account_id='qa_target'",'director',403),
        ('target status','qa_target',"UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_target'",'director',409),
        ('target account disabled','qa_target',"UPDATE auth_accounts SET status='disabled' WHERE account_id='qa_target'",'director',403),
        ('management revoked','qa_master',"UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='qa_master' AND capability='internal_advisors_manage'",'master',403),
        ('actor suspended','qa_master',"UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_master'",'master',403),
        ('credential changed','qa_director',"UPDATE auth_account_credentials SET credential_version=2 WHERE account_id='qa_director'",'director',403)]:
        reset();n=len(audit());blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=r['fixture_password'],database=r['database'],autocommit=False)
        try:
            with blocker.cursor() as cur:cur.execute('SELECT status FROM auth_accounts WHERE account_id=%s FOR UPDATE',(lock,));cur.fetchall()
            with qa.concurrent.futures.ThreadPoolExecutor(max_workers=1) as pool:
                future=pool.submit(cmd,actor);qa.wait_until(lambda:bool(sql('SELECT 1 FROM performance_schema.data_lock_waits LIMIT 1')),10)
                waits=sql('SELECT REQUESTING_ENGINE_TRANSACTION_ID,REQUESTING_THREAD_ID,BLOCKING_ENGINE_TRANSACTION_ID,BLOCKING_THREAD_ID FROM performance_schema.data_lock_waits')
                with blocker.cursor() as cur:cur.execute(change)
                blocker.commit();response=future.result(timeout=15)
            check('Concurrent '+name,response[0]==status and bool(waits) and len(audit())==n)
            qa.concurrency.append({'name':name,'independent_connections':True,'lock_waits':waits,'status':response[0]})
        finally:blocker.rollback();blocker.close();reset()
    # Only impacted legacy lifecycle behavior and individual permission compatibility.
    for op in ['register','suspend','reactivate']:
        p=subprocess.run(r['fixture']+['legacy','master',op,'qa_legacy'],cwd=ROOT,env=env,text=True,capture_output=True);check('IW01 legacy '+op,p.returncode==0)
    check('Capability grant regression',r['command'](capability='media_review_approve')[0]==200)
    check('Capability revoke regression',r['command'](operation='revoke',capability='media_review_approve')[0]==200)
    sql("DELETE FROM internal_operator_grants WHERE account_id='qa_target' AND capability='media_review_approve'")
    # Compare approved shared layout without touching the existing prototype service.
    visual='312ee388f93c892e6caf7e845b11e2b42f9c3565';approved=subprocess.check_output(['git','show',visual+':prototypes/ase-vis01/index.html'],cwd=ROOT,text=True)
    for old,new in [('style.css','internal-team.css'),('commercial.css','internal-team-commercial.css'),('admission.css','internal-team-admission.css')]:
        css=subprocess.check_output(['git','show',visual+':prototypes/ase-vis01/'+old],cwd=ROOT,text=True);check('Approved CSS '+new,css.encode()==(ROOT/'assets/css'/new).read_bytes());approved=approved.replace('<link rel="stylesheet" href="/'+old+'">','<style>'+css+'</style>')
    approved=approved.replace('<link rel="stylesheet" href="/assets/css/media-review-inbox.css">','<style>'+(ROOT/'assets/css/media-review-inbox.css').read_text()+'</style>');approved=re.sub(r'<script\b[^>]*>.*?</script>','',approved,flags=re.S)
    measure="""() => Object.fromEntries(['.advisor-workspace','.workspace-columns','.directory','.detail','h1'].map(sel=>{const e=document.querySelector(sel),s=getComputedStyle(e),b=e.getBoundingClientRect();return [sel,{width:b.width,font:s.fontFamily,size:s.fontSize,color:s.color,columns:s.gridTemplateColumns,gap:s.gap,radius:s.borderRadius}]}))"""
    def ctxpage(browser,actor='director',w=1440,h=900):
        ctx=browser.new_context(ignore_https_errors=True,viewport={'width':w,'height':h});ctx.add_cookies([{'name':'__Host-mxmed_session','value':tokens[actor],'domain':'127.0.0.1','path':'/','secure':True,'httpOnly':True,'sameSite':'Strict'}]);p=ctx.new_page();p.set_default_timeout(12000);p.on('pageerror',lambda e:errors.append(str(e)))
        p.on('response',lambda resp:receipts.append({'status':resp.status,**resp.request.post_data_json}) if '/api/internal/team/lifecycle.php' in resp.url else None)
        p.goto(origin+'/internal/media-review/index.php?section=equipo');p.locator('[data-member="qa_target"]').click();expect(p.locator('.detail .team-reference')).to_contain_text('qa_target');expect(p.locator('#lifecycle')).to_be_enabled();return ctx,p
    def shot(p,name):p.screenshot(path=str(shots/(name+'.png')),full_page=True)
    def submit(p,text='CAMBIO DE ACCESO CONFIRMADO'):
        p.locator('#lifecycle').click();expect(p.locator('#confirmation')).to_be_visible();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_contain_text(text);expect(p.locator('#pending-permissions')).not_to_contain_text('Procesando')
    with sync_playwright() as pw:
      for engine in ['chromium','webkit']:
        browser=getattr(pw,engine).launch()
        for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
            reset();ctx,p=ctxpage(browser,w=w,h=h);tag=f'{engine}-{w}x{h}'
            ref=ctx.new_page();ref.set_content(approved);p.locator('#show-directory').evaluate('(e)=>e.click()');a=ref.evaluate(measure);b=p.evaluate(measure);check(tag+' approved layout',a==b);ref.close();p.locator('#show-detail').evaluate('(e)=>e.click()')
            p.locator('#lifecycle').click();expect(p.locator('#confirmation')).to_be_visible();check(tag+' Spanish confirmation consequences','Se conservan permisos e historia' in p.locator('#confirmation').text_content());check(tag+' cancel focus',p.locator('#cancel-confirmation').evaluate('(e)=>document.activeElement===e'));shot(p,tag+'-confirmation');p.keyboard.press('Escape');check(tag+' cancel no mutation',state()=='ACTIVE')
            n=len(audit());submit(p);check(tag+' suspended permission readonly',p.locator('#operational-matrix input:enabled').count()==0 and p.locator('#save-permissions').is_disabled() and state()=='SUSPENDED');shot(p,tag+'-suspended');submit(p);check(tag+' reactivated preserves grants',grants()==before_grants and len(audit())==n+2);shot(p,tag+'-reactivated')
            check(tag+' no overflow',p.evaluate('document.documentElement.scrollWidth<=innerWidth'));check(tag+' excluded controls disabled',p.locator('#designation').is_disabled() and p.locator('#add-member').is_disabled());check(tag+' no token storage',p.evaluate('localStorage.length===0&&sessionStorage.length===0'));cases.append({'engine':engine,'viewport':f'{w}x{h}','result':'PASS','approved':a,'integrated':b});ctx.close()
        reset();ctx,p=ctxpage(browser,'master');submit(p);submit(p);check(engine+' Master browser lifecycle',state()=='ACTIVE');ctx.close()
        reset();ctx,p=ctxpage(browser);p.locator('[data-expand="use-media"]').click();p.locator('[data-capability="media_review_approve"]').check();n=len(audit());p.locator('#lifecycle').click();expect(p.locator('#permission-result-title')).to_contain_text('Resuelve los permisos pendientes');check(engine+' dirty preserved',p.locator('[data-capability="media_review_approve"]').is_checked() and len(audit())==n and not p.locator('#confirmation').is_visible());p.locator('#revert-permissions').click()
        p.locator('#lifecycle').evaluate('(e)=>{e.click();e.click();}');expect(p.locator('#confirmation')).to_be_visible();p.locator('#accept-confirmation').evaluate('(e)=>{e.click();e.click();}');expect(p.locator('#permission-result-title')).to_contain_text('CAMBIO DE ACCESO CONFIRMADO');check(engine+' duplicate suppressed',len(audit())==n+1);ctx.close()
        for fault in ['target_during_confirmation','management_during_confirmation','session_expired','409','lost_ack','failed_refresh']:
            reset();actor='master' if fault.startswith('management') else 'director';ctx,p=ctxpage(browser,actor);n=len(audit());posts=[0]
            def intercept(route):
                posts[0]+=1
                if fault=='409':check(engine+' competing status update',cmd()[0]==200)
                response=route.fetch()
                if fault=='failed_refresh':p.route('**/api/internal/team/read.php?*',lambda route:route.abort())
                if fault in ['lost_ack','failed_refresh']:route.abort()
                else:route.fulfill(response=response)
            p.route('**/api/internal/team/lifecycle.php',intercept);p.locator('#lifecycle').click();expect(p.locator('#confirmation')).to_be_visible()
            if fault=='target_during_confirmation':check(engine+' external suspend',cmd()[0]==200)
            if fault=='management_during_confirmation':sql("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='qa_master' AND capability='internal_advisors_manage'")
            if fault=='session_expired':
                key='mxmed:gate4d:preview:session:'+hmac.new(env['MXMED_PREVIEW_PEPPER'].encode(),tokens[actor].encode(),hashlib.sha256).hexdigest();args=['PEXPIRE',key,'1'];wire='*3\r\n'+''.join('$'+str(len(v))+'\r\n'+v+'\r\n' for v in args)
                with socket.create_connection(('127.0.0.1',r['valkey_port'])) as sock:sock.sendall(wire.encode());reply=sock.recv(100)
                check(engine+' session TTL expires',reply==b':1\r\n');time.sleep(.02)
            p.locator('#accept-confirmation').click()
            expected='CONFLICTO' if fault in ['target_during_confirmation','409'] else 'No se pudo validar' if fault in ['management_during_confirmation','session_expired'] else 'ESTADO PENDIENTE DE CONFIRMAR'
            expect(p.locator('#permission-result-title')).to_contain_text(expected);check(engine+' '+fault+' no retry',posts[0]==(0 if fault in ['target_during_confirmation','management_during_confirmation','session_expired'] else 1));check(engine+' '+fault+' audit exact',len(audit())==n+(0 if fault in ['management_during_confirmation','session_expired'] else 1));shot(p,engine+'-'+fault)
            if fault=='failed_refresh':p.unroute('**/api/internal/team/read.php?*');p.locator('#reconcile-permissions').click();expect(p.locator('#permission-result-title')).to_contain_text('no se atribuye');check(engine+' unknown stays unknown',p.locator('[data-lifecycle-outcome="unknown"]').count()==1 and posts[0]==1)
            ctx.close()
            if fault=='session_expired':
                result=subprocess.run(r['fixture']+['refresh_sessions'],cwd=ROOT,env=env,text=True,capture_output=True,check=True);tokens.update(json.loads(result.stdout));(owned/'sessions.json').write_text(json.dumps(tokens))
                for who in ['director','master']:r['csrf'][who]=request(who,path='/api/internal/team/csrf.php',method='GET')[1]['csrf_token']
        browser.close()
      reset();check('All lifecycle grants unchanged',grants()==before_grants)
      check('No external notification',not (owned/'inert-notifications.json').exists())
      # Same owned PHP process only; original services remain untouched.
      php=next(p for p in qa.processes if p.args[0]=='php' and '-S' in p.args);php.terminate();php.wait(timeout=10);off=env.copy();off.pop('MXMED_TEAM_ADVISOR_MUTATIONS_ENABLED')
      r['spawn']('php-gate-off',['php','-S','127.0.0.1:'+str(r['php_port']),'-t',str(ROOT),str(ROOT/qa.ROUTER)],off);qa.wait_until(lambda:r['port_ready'](r['php_port']))
      check('Default gate rejects lifecycle',cmd()[0]==503)
      for engine in ['chromium','webkit']:
        browser=getattr(pw,engine).launch();ctx=browser.new_context(ignore_https_errors=True);ctx.add_cookies([{'name':'__Host-mxmed_session','value':tokens['director'],'domain':'127.0.0.1','path':'/','secure':True,'httpOnly':True,'sameSite':'Strict'}]);p=ctx.new_page();p.goto(origin+'/internal/media-review/index.php?section=equipo');p.locator('[data-member="qa_target"]').click();expect(p.locator('.detail .team-reference')).to_contain_text('qa_target');check(engine+' default gate readonly',p.locator('#lifecycle').is_disabled() and p.locator('#save-permissions').is_disabled());ctx.close();browser.close()
    check('No JS errors',not errors);events=audit();lifecycle=[{'actor':a,'target':t,**json.loads(m)['producer_metadata']} for a,t,m in events if json.loads(m)['producer_metadata']['operation'] in ['suspend','reactivate']];check('Canonical audit before after',all(e['before']['status']!=e['after']['status'] and e['capability'] is None for e in lifecycle))
    (qa.OUT/'browser.json').write_text(json.dumps({'result':'PASS','cases':cases,'receipts':receipts,'lifecycle_audit':lifecycle,'javascript_errors':errors,'physical_safari':'NOT_RUN','mobile':'INTERIM_SMOKE_ONLY'},indent=2)+'\n')
    print('Lifecycle QA checks',len(qa.checks),flush=True)
qa.HOOK=run
if __name__=='__main__':qa.main()
