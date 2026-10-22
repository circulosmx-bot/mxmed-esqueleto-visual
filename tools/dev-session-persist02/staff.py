import json,time,subprocess,pathlib,hashlib,uuid,sys
from urllib.parse import quote
from playwright.sync_api import sync_playwright,expect
from resp import command

def run(r,check,processes):
 root,o,origin,out,env=[r[k] for k in ['ROOT','owned','origin','OUT','env']]
 def passed(name,ok=True):check(name,ok)
 def cli(file,action='',body={}):
  p=subprocess.run(['php',str(root/file)]+([action] if action else []),cwd=root,env=env,input=json.dumps(body),capture_output=True,text=True)
  if p.returncode:(o/'focused-error.log').write_text(p.stderr);raise RuntimeError('Owned CLI failed; private log retained')
  v=json.loads(p.stdout);assert v.get('ok',True),v.get('error');return v.get('data',v)
 cli(pathlib.Path('modules/identity/tests/StaffWorkflowFixture.php'),'enroll')
 media=cli(pathlib.Path('scripts/ase-advisor-workspace-fixture.php'));(o/'workspace-media.json').write_text(json.dumps(media))
 (o/'workspace-runtime.json').write_text(json.dumps({'origin':origin,'expiry_unix':r['review_deadline']}))
 routes={'director':'/internal/media-review/index.php?section=equipo','advisor':'/internal/staff-workspace/index.php'}
 def read(p,path):return p.evaluate("async path=>{let r=await fetch(path,{referrerPolicy:'same-origin'});return {status:r.status,body:await r.json().catch(()=>null)}}",path)
 def token(c):return next(x['value'] for x in c.cookies(origin) if x['name']=='__Host-mxmed_session')
 def auth(p,role):
  v=read(p,'/api/internal/team/read.php' if role=='director' else '/api/internal/workspace/read.php');assert v['status']==200,(role,v);return v
 def login(p,role):
  p.goto(origin+'/public/identity/acceso.php?next='+quote(routes[role],safe=''))
  p.locator('[name=email]').fill('qa_director@example.test' if role=='director' else 'qa_target@example.test');p.locator('[name=password]').fill((o/'fixture-password').read_text().strip());p.locator('[data-submit]').click();p.wait_for_url(origin+routes[role],timeout=20000);auth(p,role)
 def restart(kind):
  old=next(p for p in reversed(processes) if p.poll() is None and ((kind=='php' and p.args[0]=='php') or (kind=='valkey' and 'valkey-server' in p.args[0])))
  args=list(old.args)
  if kind=='valkey':args[args.index('--appendonly')+1]='yes';args+=['--appendfsync','always']
  old.terminate();old.wait(timeout=15);r['spawn']('restart-'+kind,args);time.sleep(.6)
 with sync_playwright() as pw:
  for engine in ['chromium','webkit']:
   browser=getattr(pw,engine);contexts={};pages={}
   def open_role(role):
    c=browser.launch_persistent_context(str(o/('profile-'+engine+'-'+role)),headless=True,ignore_https_errors=True);p=c.new_page();return c,p
   try:
    for role in routes:
     contexts[role],pages[role]=open_role(role);login(pages[role],role);passed(engine+' '+role+' canonical login and panel')
    original={role:token(c) for role,c in contexts.items()};passed(engine+' simultaneous distinct cookies',original['director']!=original['advisor'])
    for role in routes:
     p=pages[role];p.reload();auth(p,role);p.goto(origin+routes[role]);auth(p,role);contexts[role].close();contexts[role],pages[role]=open_role(role);pages[role].goto(origin+routes[role]);auth(pages[role],role);passed(engine+' '+role+' reload navigation browser restart',token(contexts[role])==original[role])
    for role in routes:pages[role].goto('about:blank')
    restart('php');restart('valkey')
    for role in routes:pages[role].goto(origin+routes[role]);auth(pages[role],role);passed(engine+' '+role+' service restart retains exact valid session',token(contexts[role])==original[role])
    d,a=pages['director'],pages['advisor']
    csrf=read(d,'/api/internal/team/csrf.php')['body']['csrf_token']
    def mutate(op):
     return d.evaluate("async ([csrf,op])=>{let r=await fetch('/api/internal/team/capability.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({operation:op,target:'qa_target',capability:'media_review_read'})});return {status:r.status,body:await r.json()}}",[csrf,op])
    endpoint='/api/internal/workspace/read.php'
    before=read(a,endpoint);assert before['status']==200 and before['body']['data']['count']>0
    assert mutate('revoke')['status']==200
    denied=read(a,endpoint);passed(engine+' live revocation blocks consumer with same session',denied['status']==200 and denied['body']['data']['count']==0 and denied['body']['data']['physicians']==[] and denied['body']['data']['pending_items']==[] and token(contexts['advisor'])==original['advisor'])
    protected=media['items'][0];status=a.evaluate("async path=>(await fetch(path)).status",'/api/internal/workspace/media.php?doctor_id='+protected['owner_id']+'&submission_id='+protected['submission_id']+'&mode=review');passed(engine+' revoked direct document read denied',status==403)
    assert mutate('grant')['status']==200;auth(a,'advisor');passed(engine+' canonical restore and consumer recovery')
    passed(engine+' Advisor cannot read Director directory',read(a,'/api/internal/team/read.php')['status']==403)
    if engine=='chromium':
     result=cli(pathlib.Path('tools/dev-session-persist02/mfa.php'),body={'director':original['director'],'advisor':original['advisor']})
     for name,ok in result.items():passed('MFA '+name,ok)
    # Exact expiry is tested by expiring only the freshly authenticated owned
    # server-side record (real TTL), never changing SessionPolicy or original stores.
    # Obtain the digest from the canonical service; never infer or export raw tokens.
    binding=cli(pathlib.Path('tools/dev-session-persist02/binding.php'),body={'token':original['advisor']})
    key='mxmed:gate4d:preview:session:'+binding['digest']
    passed(engine+' expiry record exists',command(r['valkey_port'],'EXISTS',key)==1)
    command(r['valkey_port'],'EXPIRE',key,1);time.sleep(1.1)
    passed(engine+' expired session denied by protected HTTP',read(a,endpoint)['status']==401)
    auth(d,'director');passed(engine+' Advisor expiry does not invalidate Director')
    current=read(d,'/api/identity/index.php/current-session')['body']
    (o/'current-session-shape.json').write_text(json.dumps({'keys':list(current)}))
    logout=d.evaluate("async ()=>{let s=await (await fetch('/api/identity/index.php/current-session')).json();let r=await fetch('/api/identity/index.php/logout',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':s.csrf_token},body:JSON.stringify({csrf_token:s.csrf_token})});return {status:r.status,body:await r.json()}}")
    assert logout['status']==200,logout
    passed(engine+' explicit Director logout denies directory',read(d,'/api/internal/team/read.php')['status']==401)
    login(a,'advisor')
    logout=a.evaluate("async ()=>{let s=await (await fetch('/api/identity/index.php/current-session')).json();let r=await fetch('/api/identity/index.php/logout',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':s.csrf_token},body:JSON.stringify({csrf_token:s.csrf_token})});return r.status}")
    passed(engine+' explicit Advisor logout denies consumer',logout==200 and read(a,endpoint)['status']==401)
    login(d,'director');binding=cli(pathlib.Path('tools/dev-session-persist02/binding.php'),body={'token':token(contexts['director'])})
    command(r['valkey_port'],'EXPIRE','mxmed:gate4d:preview:session:'+binding['digest'],1);time.sleep(1.1)
    passed(engine+' Director server expiry enforced',read(d,'/api/internal/team/read.php')['status']==401)
   finally:
    for c in contexts.values():c.close()
 passed('Canonical grant restoration left original synthetic capability active',r['sql']("SELECT COUNT(*) FROM internal_operator_grants WHERE account_id='qa_target' AND capability='media_review_read' AND status='ACTIVE'")[0][0]==1)
