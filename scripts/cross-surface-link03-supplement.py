#!/usr/bin/env python3
"""Additional boundary tests; only the owned review is mutable."""
import pathlib,json,sys,importlib.util,concurrent.futures,time,pymysql,hashlib,re
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1];o=pathlib.Path(sys.argv[1]).resolve();assert o.parent==pathlib.Path('/private/tmp') and o.name.startswith('mxmed-ase-adm02imp01-')
a=json.loads((o/'workflow-approval.json').read_text());assert a['root']==str(ROOT)
c=json.loads((o/'administration-source.json').read_text());secret=json.loads(pathlib.Path(c['secret_file']).read_text());env=json.loads((o/'review-env.json').read_text());origin=json.loads((o/'workspace-runtime.json').read_text())['origin'];ref=c['origin']+':'+secret['ids'][0];out=o/'link03-evidence';checks=[]
def check(n,v):checks.append({'name':n,'pass':bool(v)});print(('PASS ' if v else 'FAIL ')+n,flush=True);assert v,n
spec=importlib.util.spec_from_file_location('native',ROOT/'scripts/ase-advisor-workspace-review.py');native=importlib.util.module_from_spec(spec);spec.loader.exec_module(native)
db=pymysql.connect(unix_socket=str(o/'mysql.sock'),user='ase_fixture',password=env['ASE_INT02A_FIXTURE_PASSWORD'],database=env['MXMED_DB_NAME'],autocommit=True)
def sql(s,args=()):
 assert s.startswith('SELECT ')
 with db.cursor() as q:q.execute(s,args);return q.fetchall()
check('No original physician copied to isolated profiles',sql('SELECT COUNT(*) FROM profiles_doctors WHERE doctor_id=%s OR display_name=%s',(secret['ids'][0],'Leticia Muñoz Romo'))==((0,),))
password=(o/'fixture-password').read_text().strip()
with sync_playwright() as pw:
 b=pw.chromium.launch();p=b.new_page(ignore_https_errors=True,viewport={'width':1366,'height':850});native.login(p,origin,'qa_target@example.test',password,'/internal/staff-workspace/index.php')
 def api(mode,**v):
  from urllib.parse import urlencode
  r=p.request.get(origin+'/api/internal/workspace/requests.php?'+urlencode({'mode':mode,**v}));return r.status,r.json()
 before=api('pending')[1]['data'];check('Existing grouped pending API operational',isinstance(before['groups'],list))
 check('New strict admin request keys',api('admin_summary',profile_ref=ref,query='foreign')[0]==400)
 p.locator('#physician-search').fill('Leticia');p.locator('#physician-search-form').evaluate('(f)=>f.requestSubmit()');p.locator('[data-doctor-id="'+ref+'"]').click();expect(p.locator('#physician-name')).to_have_text('Leticia Muñoz Romo')
 p.locator('#tab-images').click();expect(p.locator('#restricted-message')).to_contain_text('no tiene un lector autorizado');check('Unsupported source tab precisely unavailable',True)
 p.locator('#pending-toggle').click();expect(p.locator('#discovery-title')).to_have_text('Pendientes por atender');expect(p.locator('#discovery-list [data-doctor-id="'+ref+'"]')).to_have_count(0);check('Pending navigation stays separate from source discovery',True)
 after=api('pending')[1]['data'];check('Ordinary discovery does not change pending totals',all(before[k]==after[k] for k in ['actionable_count','group_count']))
 p.locator('#change-physician').click();p.locator('#physician-search').fill('Aurora');p.locator('#physician-search-form').evaluate('(f)=>f.requestSubmit()');p.locator('[data-doctor-id="workspace_aurora"]').first.click();expect(p.locator('#physician-name')).to_contain_text('Aurora');check('Switch to synthetic context clears original',not p.locator('#summary-facts').inner_text().find('mxmed-local-development')>=0)
 # Delay an authorized original response until after a synthetic context has loaded.
 p.locator('#change-physician').click();p.locator('#physician-search').fill('');p.locator('#physician-search-form').evaluate('(f)=>f.requestSubmit()');expect(p.locator('[data-doctor-id="'+ref+'"]').first).to_be_visible()
 delayed=[]
 def hold(route):delayed.append((route,route.fetch()))
 p.route('**/requests.php?mode=admin_summary&*',hold)
 p.locator('[data-doctor-id="'+ref+'"]').click();p.wait_for_timeout(150);p.locator('[data-doctor-id="workspace_aurora"]').first.click();expect(p.locator('#physician-name')).to_contain_text('Aurora')
 for route,response in delayed:route.fulfill(response=response)
 p.wait_for_timeout(200);expect(p.locator('#physician-name')).to_contain_text('Aurora');check('Late original response cannot overwrite synthetic context',bool(delayed));p.unroute('**/requests.php?mode=admin_summary&*',hold)
 # Restricted credential fault only; never touch original listener/users during fault injection.
 saved=(o/'administration-source.json').read_bytes();bad=dict(secret);bad['port']=1;fault=o/'link03-fault-secret.json';fault.write_text(json.dumps(bad));fault.chmod(0o600);changed=dict(c);changed['secret_file']=str(fault)
 try:
  (o/'administration-source.json').write_text(json.dumps(changed));check('Connection refusal returns safe 503',api('admin_summary',profile_ref=ref)[0]==503)
  scoped=dict(changed);scoped['staff']={};(o/'administration-source.json').write_text(json.dumps(scoped));check('Unauthorized scope denied before unavailable source connection',api('admin_summary',profile_ref=ref)[0]==403)
 finally:(o/'administration-source.json').write_bytes(saved);fault.unlink()
 check('Source recovery returns exact reference',api('admin_summary',profile_ref=ref)[1]['data']['physician']['doctor_id']==ref)
 for key in ['auth','clinical','password','token_hash','email_normalized','patient_id']:
  check('No protected field '+key,key not in json.dumps(api('admin_operators',profile_ref=ref)[1]['data']))
 p.screenshot(path=str(out/'synthetic-after-switch.png'),full_page=True);b.close()
check('Canonical source-qualified read audit',sql("SELECT COUNT(*) FROM platform_audit_events WHERE action='SENSITIVE_ADMIN_ACTION' AND outcome='SUCCESS' AND resource_reference=%s",(ref,))[0][0]>0)
check('Canonical administrative denial audit',sql("SELECT COUNT(*) FROM platform_audit_events WHERE action='SENSITIVE_ADMIN_ACTION' AND outcome='DENIED' AND resource_reference='physician-administration'")[0][0]>0)
# Real concurrent source connections, bounded projection only. Independent of serial PHP development HTTP server.
def read(i):
 d=pymysql.connect(host=secret['host'],port=secret['port'],database=secret['database'],user=secret['summary_user'],password=secret['summary_password'],ssl={'ca':secret['ssl_ca'],'check_hostname':False},connect_timeout=2,read_timeout=2)
 try:
  with d.cursor() as q:
   q.execute('SET SESSION MAX_EXECUTION_TIME=1500');q.execute('START TRANSACTION READ ONLY');q.execute('SELECT doctor_id,display_name,specialty_primary,profile_status FROM `'+secret['prefix']+'_profiles` WHERE doctor_id=%s',(secret['ids'][0],));return hashlib.sha256(repr(q.fetchall()).encode()).hexdigest()
 finally:d.rollback();d.close()
start=time.monotonic()
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:results=list(pool.map(read,range(20)))
check('4 concurrent SQL readers / 20 stable source snapshots',len(set(results))==1)
(out/'supplement.json').write_text(json.dumps({'pass':True,'checks':checks,'concurrent_source_seconds':time.monotonic()-start,'production_load_test':False},indent=2));print('SUPPLEMENT_PASS',len(checks));db.close()
