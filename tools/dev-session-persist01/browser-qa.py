"""Own services only; canonical login against an existing authorized synthetic fixture.
No fixture reset, grants, original credentials or original session access.
"""
import json,os,pathlib,socket,subprocess,sys,time,tempfile
from playwright.sync_api import sync_playwright
ROOT=pathlib.Path(__file__).resolve().parents[2]
fixture=pathlib.Path(sys.argv[1]).resolve();state=json.loads((fixture/'state.json').read_text())
assert fixture.name.startswith('mxmed-init02-') and fixture.stat().st_uid==os.getuid() and state['expires']>time.time()
assert state['source']=='/Users/circulodigital/Documents/GitHub/mxmed-med-consultorios-integration01'
assert subprocess.check_output(['ps','-p',str(state['owner_pid']),'-o','command='],text=True).strip()==state['command']
env=json.loads((fixture/'environment.json').read_text());assert env['MXMED_DB_HOST']=='127.0.0.1' and int(env['MXMED_DB_PORT'])!=3306 and env['MXMED_DB_NAME']=='mxmed_gate4d_preview_adminqa_init02'
os.umask(0o077);base=pathlib.Path.home()/'Library/Application Support/MXMED/dev-session-persist01';base.mkdir(exist_ok=True,parents=True,mode=0o700)
d=pathlib.Path(tempfile.mkdtemp(prefix='qa-',dir=base));results=[];children=[]
def port():
 with socket.socket() as s:s.bind(('127.0.0.1',0));return s.getsockname()[1]
def spawn(args):
 p=subprocess.Popen(args,cwd=ROOT,env=env,stdout=(d/'services.log').open('ab'),stderr=subprocess.STDOUT);children.append(p);return p
def stop(p):
 p.terminate();p.wait(timeout=15)
def passed(name):results.append({'test':name,'result':'PASS'});(d/'results.json').write_text(json.dumps(results,indent=2));print(name+'=PASS',flush=True)
vport,phpport,tlsport=port(),port(),port();origin='https://127.0.0.1:'+str(tlsport)
env.update(MXMED_DEV_PERSISTENT_COOKIE='1',MXMED_SESSION_STORE_PORT=str(vport),MXMED_PREVIEW_ORIGIN=origin)
valkey=[str(pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-runtime/valkey-server'),'--bind','127.0.0.1','--port',str(vport),'--dir',str(d),'--save','','--appendonly','yes','--appendfsync','always']
php=['php','-S','127.0.0.1:'+str(phpport),'-t',str(ROOT)]
try:
 vp=spawn(valkey);hp=spawn(php)
 proxy=(ROOT/'scripts/qa05-https-proxy.py').read_text().replace('8444',str(tlsport)).replace('__Host-mxmed_qa05_session','__Host-mxmed_persist01_session')
 proxy=proxy.replace("        if 'Cookie' in headers: headers['Cookie']=backend_cookie_header(headers['Cookie'])","        keys=[k for k in headers if k.lower()=='cookie']\n        raw='; '.join(headers.pop(k) for k in keys)\n        if raw: headers['Cookie']=backend_cookie_header(raw)")
 (d/'proxy.py').write_text(proxy);spawn([sys.executable,str(d/'proxy.py'),'--cert',str(fixture/'tls.crt'),'--key',str(fixture/'tls.key'),'--backend-port',str(phpport)]);time.sleep(1)
 credentials=json.loads((fixture/'credentials.json').read_text())['owner']
 with sync_playwright() as pw:
  for engine in ['chromium','webkit']:
   browser=getattr(pw,engine);profile=d/('profile-'+engine)
   def launch():return browser.launch_persistent_context(str(profile),headless=True,ignore_https_errors=True)
   c=launch();p=c.new_page();p.goto(origin+'/public/identity/acceso.php');p.locator('[name=email]').fill(credentials['email']);p.locator('[name=password]').fill(credentials['password']);p.locator('[data-submit]').click();p.wait_for_url('**/private-app.php');p.wait_for_function("window.mxmedPrivateSession?.state==='authenticated'")
   passed(engine+' canonical login')
   cookie=next(x for x in c.cookies(origin) if x['name']=='__Host-mxmed_persist01_session');assert time.time()<cookie['expires']<=time.time()+43201 and cookie['secure'] and cookie['httpOnly'] and cookie['sameSite']=='Lax';del cookie
   p.reload();p.wait_for_function("window.mxmedPrivateSession?.state==='authenticated'");passed(engine+' reload')
   c.close();c=launch();p=c.new_page();p.goto(origin+'/private-app.php');p.wait_for_function("window.mxmedPrivateSession?.state==='authenticated'");passed(engine+' actual browser close/reopen')
   stop(hp);hp=spawn(php);time.sleep(.5);p.reload();p.wait_for_function("window.mxmedPrivateSession?.state==='authenticated'");passed(engine+' PHP process restart')
   stop(vp);vp=spawn(valkey);time.sleep(.5);p.reload();p.wait_for_function("window.mxmedPrivateSession?.state==='authenticated'");passed(engine+' owned Valkey AOF restart')
   other=browser.launch(headless=True);oc=other.new_context(ignore_https_errors=True);op=oc.new_page();op.goto(origin+'/private-app.php');assert '/public/identity/acceso.php' in op.url;other.close();passed(engine+' independent context unauthenticated')
   if engine=='chromium':
    import importlib.util
    spec=importlib.util.spec_from_file_location('owned_hub',ROOT/'scripts/interface-hub02.py');hub=importlib.util.module_from_spec(spec);spec.loader.exec_module(hub)
    assert hub.running()
    subprocess.run([sys.executable,str(ROOT/'scripts/interface-hub02.py'),'stop'],check=True,stdout=subprocess.DEVNULL)
    for _ in range(50):
     if not hub.running():break
     time.sleep(.1)
    assert not hub.running()
    subprocess.run([sys.executable,str(ROOT/'scripts/interface-hub02.py'),'start'],check=True,stdout=subprocess.DEVNULL)
    p.goto('https://127.0.0.1:57452/');assert p.locator('li').count()==6
    p.goto(origin+'/private-app.php');p.wait_for_function("window.mxmedPrivateSession?.state==='authenticated'");passed(engine+' hub restart and navigation preserve session')
   assert p.evaluate('window.mxmedPrivateSession.logout()')
   p.goto(origin+'/private-app.php');assert '/public/identity/acceso.php' in p.url;passed(engine+' canonical logout denies protected shell')
   c.close()
 print('PRIVATE_EVIDENCE='+str(d),flush=True)
finally:
 for p in reversed(children):
  if p.poll() is None:stop(p)
