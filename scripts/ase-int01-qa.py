#!/usr/bin/env python3
"""Real canonical reads in the owned disposable instance, never existing environments."""
import hashlib,json,os,pathlib,ssl,subprocess,sys,time,urllib.error,urllib.parse,urllib.request
from playwright.sync_api import sync_playwright
ROOT=pathlib.Path(__file__).resolve().parents[1];OUT=ROOT/'docs/evidence/ase-int01';OUT.mkdir(parents=True,exist_ok=True)
path=pathlib.Path(json.loads(pathlib.Path('/tmp/mxmed-ase-int01-owned.json').read_text())['configuration']);cfg=json.loads(path.read_text());owned=pathlib.Path(cfg['owned']);assert owned.parent==pathlib.Path('/tmp') and owned.name.startswith('mxmed-ase-int01-') and cfg['root']==str(ROOT)
env=cfg['env'];env['ASE_INT01_CONFIGURATION']=str(path);tokens=json.loads((owned/'sessions.json').read_text())['tokens'];origin='https://127.0.0.1:8141';ctx=ssl._create_unverified_context();checks=[]
def fixture(action):return json.loads(subprocess.check_output(['php','modules/identity/tests/TeamReadFixture.php',action],cwd=ROOT,env=env,text=True))
def check(name,ok,detail=None):
    checks.append({'name':name,'pass':bool(ok),**({'detail':detail} if detail is not None else {})})
    if not ok:raise AssertionError(name+': '+str(detail))
def request(actor='qa_director',query='',method='GET',extra=None,path='/api/internal/team/read.php'):
    headers={**({'Cookie':'__Host-mxmed_session='+tokens[actor]} if actor else {}),**(extra or {})};req=urllib.request.Request(origin+path+query,method=method,headers=headers)
    try:
        with urllib.request.urlopen(req,context=ctx) as r:return r.status,dict(r.headers),r.read()
    except urllib.error.HTTPError as e:return e.code,dict(e.headers),e.read()
def json_read(actor='qa_director',query=''):
    status,headers,body=request(actor,query);check(actor+' successful canonical read '+query,status==200);return json.loads(body)
def private_keys(value):
    if isinstance(value,dict):return [k for k in value if k in ['email_address','email_normalized','password_hash','credential_version','token','session_id','phone','storage_key','document_url','mfa_secret']]+sum((private_keys(v) for v in value.values()),[])
    if isinstance(value,list):return sum((private_keys(v) for v in value),[])
    return []

before=fixture('counts');root_data=json_read();master_data=json_read('qa_master');
check('Director authority independent of Media grant',root_data['authority']['class']=='DIRECTOR' and request('qa_director',path='/internal/media-review/index.php')[0]==403 and request('qa_director',path='/internal/media-review/index.php?section=equipo')[0]==200)
check('Original Media boundary still accepts exact Media-only Advisor',request('qa_media_only',path='/internal/media-review/index.php')[0]==200)
for actor,expected in [(None,401),('qa_advisor',403),('qa_media_only',403),('qa_no_management_master',403),('qa_suspended_actor',403),('qa_disabled',401),('qa_credential_changed',401),('qa_customer',403),('qa_expired',401),('qa_revoked_session',401)]:
    for path in ['/api/internal/team/read.php','/internal/media-review/index.php?section=equipo']:
        status,headers,body=request(actor,path=path);check(str(actor)+' denied at '+path,status==expected and 'qa_advisor_00' not in body.decode())
check('No PHP-session/header identity fallback',request(None,extra={'Cookie':'PHPSESSID=fabricated','X-Actor':'qa_director','X-Role':'DIRECTOR'})[0]==401)
check('Forged role/actor headers cannot elevate Advisor',request('qa_advisor',extra={'X-Actor':'qa_director','X-Role':'DIRECTOR'})[0]==403)
for query in ['?actor=qa_director','?role=DIRECTOR','?capability=internal_advisors_manage','?page_size=51','?page=0','?page=1001','?q=a','?q=%25','?q=person%40example.test','?target[]=qa_director','?category=ADVISOR&category=MASTER_ADMIN']:
    check('Unsafe/ambiguous query denied '+query,request(query=query)[0]==400)
check('Cross-origin read denied',request(extra={'Origin':'https://example.test'})[0]==403)
check('Cross-site read denied',request(extra={'Sec-Fetch-Site':'cross-site'})[0]==403)
check('Same-origin read allowed',request(extra={'Origin':origin})[0]==200)
for method in ['POST','PUT','PATCH','DELETE']:
    for p in ['/api/internal/team/read.php','/internal/media-review/index.php?section=equipo']:
        status,headers,body=request(method=method,path=p);check(method+' denied by product boundary '+p,status==405 and headers.get('Allow')=='GET, HEAD')
status,headers,body=request(method='HEAD');check('HEAD authorized without response body',status==200 and not body and 'no-store' in headers.get('Cache-Control','') and 'private' in headers.get('Cache-Control',''))
check('Minimized output excludes private credentials/contact/evidence fields',not private_keys(root_data) and not private_keys(master_data))
check('Exact canonical catalog18/16, no new executable keys',len(root_data['catalog'])==18 and sum(d['kind']=='operational' for d in root_data['catalog'].values())==16)
check('Bounded default paging',len(root_data['items'])==20 and root_data['has_more'])
page2=json_read(query='?page=2');check('Stable disjoint pagination',not {r['account_reference'] for r in root_data['items']} & {r['account_reference'] for r in page2['items']})
check('Scoped reference search',all('qa_advisor_00' in r['account_reference'] for r in json_read(query='?q=qa_advisor_00')['items']))
check('No unrestricted accounts outside staff',json_read(query='?q=qa_customer')['items']==[])
check('Truthful empty result',json_read(query='?q=zz_nomatch')['items']==[])
check('Master cannot enumerate Masters',request('qa_master','?category=MASTER_ADMIN')[0]==403)
check('Master target lookup cannot distinguish other Master from missing',request('qa_master','?target=qa_other_master')[0]==request('qa_master','?target=zz_nomatch')[0]==404)
check('Director reads Masters independently',all(r['governance_class']=='MASTER_ADMIN' for r in json_read(query='?category=MASTER_ADMIN')['items']))
direct=json_read(query='?target=qa_advisor_00')['items'][0];delegated=json_read('qa_master','?target=qa_advisor_00')['items'][0]
check('Stored grant differs from use/availability, unknown grant filtered',len(direct['grants'])==4 and 'profile_create' in direct['grants'] and 'unregistered_read' not in direct['grants'])
check('Master gets only exact current delegated grants and count',set(delegated['grants'])=={'media_review_read','media_review_approve'} and delegated['active_operational_grant_count']==2 and delegated['master_delegations'] is None)
check('Missing personal/review/history/commercial sources remain null',all(direct[k] is None for k in ['personal_profile','identity_review','commercial_activity','history']))
check('SELECT-only runtime principal rejects SQL writes',fixture('readonly_proof')['runtime_sql_write_denied'])
initial_http_unchanged=before==fixture('counts');check('HTTP reads/denials caused no business SQL changes',initial_http_unchanged)
for revoke,restore,assertion in [('revoke_manager','restore_manager',lambda:request('qa_master')[0]==403),('revoke_delegation','restore_delegation',lambda:'media_review_read' not in json_read('qa_master','?target=qa_advisor_00')['items'][0]['grants']),('revoke_grant','restore_grant',lambda:'media_review_read' not in json_read(query='?target=qa_advisor_00')['items'][0]['grants'])]:
    try:fixture(revoke);check('Current authority/grant revocation reflected '+revoke,assertion())
    finally:fixture(restore)
native_after=fixture('counts')

WARNING="Refused to apply a stylesheet because its hash, its nonce, or 'unsafe-inline' does not appear in the style-src directive of the Content Security Policy."
browser_cases=[];visual=[];browser_out=OUT/'browser';browser_out.mkdir(exist_ok=True)
def run_browser(browser,engine,w,h):
    c=browser.new_context(ignore_https_errors=True,viewport={'width':w,'height':h},locale='es-MX',reduced_motion='reduce');c.add_cookies([{'name':'__Host-mxmed_session','value':tokens['qa_director'],'url':origin,'secure':True,'httpOnly':True,'sameSite':'Strict'}]);p=c.new_page();errors=[];forbidden=[];notes=[];shots=[];case_checks=[];capture=False;warnings=[];expected_http=[];expected_phase=False;hold_api=True;held=[]
    def bc(name,ok):case_checks.append({'name':name,'pass':bool(ok)});assert ok,name
    def route(r):
        u=urllib.parse.urlsplit(r.request.url)
        if u.netloc!='127.0.0.1:8141' or r.request.method not in ['GET','HEAD']:forbidden.append({'path':u.path,'method':r.request.method});r.abort()
        elif hold_api and u.path=='/api/internal/team/read.php':held.append(r)
        else:r.continue_()
    c.route('**/*',route);p.on('pageerror',lambda e:errors.append(str(e)))
    def console(m):
        if m.type=='error':
            if capture and engine=='webkit' and m.text==WARNING:warnings.append(m.text)
            elif expected_phase and urllib.parse.urlsplit(m.location.get('url','')).path=='/api/internal/team/read.php' and m.text in ['Failed to load resource: the server responded with a status of 403 (Forbidden)','Failed to load resource: the server responded with a status of 503 (Service Unavailable)']:expected_http.append({'message':m.text,'path':'/api/internal/team/read.php','scope':'Explicit revocation/unavailability test only'})
            else:errors.append(m.text)
    p.on('console',console)
    c.add_init_script("""(()=>{window.__effects=[];window.__styles=[];for(const k of ['setItem','removeItem','clear'])Storage.prototype[k]=function(){window.__effects.push(k);};new MutationObserver(rs=>rs.forEach(r=>r.addedNodes.forEach(n=>{if(n.nodeName==='STYLE')window.__styles.push(n.textContent);}))).observe(document,{subtree:true,childList:true});})();""")
    def shot(name):
        nonlocal capture
        path=browser_out/f'{engine}-{w}x{h}-{name}.png';start=p.evaluate('window.__styles.length');n=len(warnings);capture=True
        try:p.screenshot(path=str(path),full_page=True)
        finally:capture=False
        new=warnings[n:];styles=p.evaluate('(n)=>window.__styles.slice(n)',start)
        if new:bc('Exact screenshotter diagnostic only',new==[WARNING] and styles==['body {}']);notes.append({'screenshot':name,'style':'body {}','csp_rejected':True})
        shots.append(str(path.relative_to(ROOT)))
    def layout():
        return p.evaluate("""()=>({overflow:Math.max(document.body.scrollWidth,document.documentElement.scrollWidth)>innerWidth,small:[...document.querySelectorAll('button,input:not([type=checkbox]),summary,.capability-row,.family-select,.team-navigation a')].filter(e=>e.getClientRects().length&&e.getBoundingClientRect().height<43.9).map(e=>({id:e.id,height:e.getBoundingClientRect().height}))})""")
    result='FAIL';error=None
    try:
        response=p.goto(origin+'/internal/media-review/index.php?section=equipo',wait_until='domcontentloaded');p.wait_for_function("()=>document.getElementById('directory-content').getAttribute('aria-busy')==='true'");bc('Loading state while canonical read is pending',p.locator('#feedback').inner_text()=='Cargando equipo…');shot('loading');hold_api=False
        for pending in held:pending.continue_()
        p.wait_for_selector('[data-member]');p.wait_for_selector('#operational-matrix',state='attached')
        if w<640:p.locator('#show-detail').click()
        bc('Authorized existing-workspace section connected',response.status==200 and p.locator('h1').inner_text()=='EQUIPO INTERNO Y PERMISOS')
        bc('Navigation retains original staff sections',p.locator('.team-navigation a').count()==4)
        bc('Five canonical families16 keys retained',p.locator('#operational-matrix [data-family]').count()==5 and p.locator('#operational-matrix [data-capability]').count()==16)
        bc('All mutation inputs and actions disabled',p.locator('#advisor-detail input:not(:disabled)').count()==0 and p.locator('#add-member').is_disabled() and p.locator('#save-permissions').is_disabled())
        bc('Neutral missing personal fields, no invented portrait/review',p.locator('#advisor-detail img').count()==0 and 'no disponible' in p.locator('#detail-title').inner_text() and 'revisión aún no registrada' in p.locator('.admission-indicators').inner_text())
        bc('No applicant/password/file/document endpoint controls',p.locator('input[type=file],input[type=password],a[download]').count()==0)
        metrics=layout();bc('Viewport fits and44px targets',not metrics['overflow'] and not metrics['small']);shot('directory')
        p.locator('#operational-matrix [data-family="media"] [data-expand]').focus();p.keyboard.press('Enter');bc('Partial stored grants retain mixed group display',p.locator('#operational-matrix [data-family=media] [data-group-count]').evaluate('e=>e.indeterminate'));bc('Native keyboard expands without changing grants',p.locator('#use-media').is_visible());p.keyboard.press('Enter');bc('Native keyboard collapses',not p.locator('#use-media').is_visible())
        p.locator('#commercial-conditions').evaluate('e=>e.open=true');bc('Commercial pending values and historical discount separated',p.locator('#commercial-conditions').inner_text().count('PENDIENTE DE DEFINICIÓN')==2 and p.locator('#own-commercial-activity').is_disabled());shot('unavailable')
        if w<640:p.locator('#show-directory').click()
        p.locator('#advisor-search').fill('zz_nomatch');p.wait_for_selector('.empty-state strong');bc('Truthful bounded empty search',p.locator('[data-member]').count()==0);shot('empty');p.locator('#advisor-search').fill('');p.wait_for_selector('[data-member]')
        p.locator('#filter-masters').click();p.wait_for_function("()=>document.querySelector('#filter-masters').getAttribute('aria-pressed')==='true'");p.wait_for_selector('#delegation-matrix',state='attached');bc('Director Master read separated from commands',p.locator('#designation').is_disabled())
        # New canonical context, same production boundary; no role selection in the client.
        c.clear_cookies();c.add_cookies([{'name':'__Host-mxmed_session','value':tokens['qa_master'],'url':origin,'secure':True,'httpOnly':True,'sameSite':'Strict'}]);p.reload(wait_until='networkidle');p.wait_for_selector('#operational-matrix',state='attached');bc('Master exact scoped read and Masters filter denied',p.locator('#filter-masters').is_disabled() and 'sólo asesores' in p.locator('#authority-note').inner_text());
        if w<640:p.locator('#show-detail').click()
        p.locator('#operational-matrix [data-family="media"] [data-expand]').click();bc('Unavailable grant scope does not claim unassigned',p.locator('[data-exposed="false"]').count()>0);shot('master')
        # Actual revocation in the owned fixture, then fresh GET must clear prior directory state.
        try:
            expected_phase=True;fixture('revoke_manager');
            if w<640:p.locator('#show-directory').click()
            p.locator('#refresh-team').click();p.wait_for_selector('#retry-team');bc('Live revoked management clears sensitive rendered data',p.locator('[data-member]').count()==0 and p.locator('[data-capability]').count()==0)
        finally:fixture('restore_manager')
        expected_phase=False
        p.locator('#refresh-team').click();p.wait_for_selector('[data-member]')
        try:
            expected_phase=True;fixture('deny_reader');p.locator('#refresh-team').click();p.wait_for_selector('#retry-team');bc('Unavailable canonical reader shows truthful error and no private data',p.locator('[data-member]').count()==0 and 'No pudimos consultar' in p.locator('#feedback').inner_text());shot('error')
        finally:fixture('restore_reader')
        expected_phase=False
        bc('No storage or application errors/foreign writes',not p.evaluate('window.__effects') and not forbidden and not errors)
        result='PASS'
    except Exception as e:error=str(e)
    finally:c.close()
    return {'engine':engine,'viewport':f'{w}x{h}','result':result,'error':error,'checks':case_checks,'errors':errors,'forbidden_requests':forbidden,'capture_diagnostics':notes,'expected_http_diagnostics':expected_http,'screenshots':shots}

with sync_playwright() as pw:
    for engine in ['chromium','webkit']:
        browser=getattr(pw,engine).launch()
        for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
            case=run_browser(browser,engine,w,h);browser_cases.append(case);print(engine,case['viewport'],case['result'],case['error'],flush=True)
        browser.close()
report={'result':'PASS' if all(c['result']=='PASS' for c in browser_cases) else 'FAIL','checks':checks,'native_check_count':len(checks),'browser_check_count':sum(len(c['checks']) for c in browser_cases),'cases':browser_cases,'physical_safari':'NOT_RUN','mobile':'INTERIM_MOBILE_SMOKE_ONLY','scope':'Canonical Identity+SQL read-only integration in a new disposable MySQL instance; no real staff/data','business_sql_unchanged_by_initial_http_phase':initial_http_unchanged,'fixture_mutations':'Explicit synthetic-only revocation experiments recorded separately; restored before browser reads','source_sha256':{str(p.relative_to(ROOT)):hashlib.sha256(p.read_bytes()).hexdigest() for p in [ROOT/'scripts/ase-int01-qa.py',ROOT/'scripts/ase-int01-isolated.py',ROOT/'scripts/ase-int01-router.php',ROOT/'modules/identity/tests/TeamReadFixture.php',ROOT/'modules/identity/tests/TeamReadDestinationTest.mjs',ROOT/'public/identity/identity-access.js',ROOT/'internal/media-review/index.php',ROOT/'partials/identity/physician-onboarding.php',ROOT/'partials/profiles/correction-review.php',ROOT/'modules/identity/services/InternalCapabilityCatalog.php',ROOT/'modules/identity/services/SessionService.php',*sorted((ROOT/'modules/identity/Team').glob('*.php')),ROOT/'api/internal/team/read.php',ROOT/'partials/identity/internal-team.php',ROOT/'assets/js/internal-team.js',*sorted((ROOT/'assets/css').glob('internal-team*.css'))]}}
# updated_at changes in fixture-only revocation controls are expected; application reads remain SELECT-only.
(OUT/'qa.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');(browser_out/'summary.json').write_text(json.dumps({'result':report['result'],'cases':browser_cases},ensure_ascii=False,indent=2)+'\n');print(json.dumps({'result':report['result'],'native_checks':len(checks),'browser_checks':report['browser_check_count']}))
raise SystemExit(0 if report['result']=='PASS' else 1)
