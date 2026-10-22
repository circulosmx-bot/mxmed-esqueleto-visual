#!/usr/bin/env python3
"""Browser-owned, enum-only review navigation. No HTTP command endpoint or shell.

Reuses an intact CLOSURE02 disposable runtime without modifying it. Real medical
login remains manual on the original HTTPS origin, in a separate browser context.
"""
import argparse, asyncio, fcntl, hashlib, http.server, importlib.util, json, os
import pathlib, ssl, subprocess, sys, threading, time, urllib.request, urllib.error
from urllib.parse import urlparse
ROOT = pathlib.Path(__file__).resolve().parents[1]
BASE = '4fc81b61b51b940e0c6c102a9f2ba2355c11790c'
BRANCH = 'feature/mxmed-interface-hub01'
MEDICAL = 'https://localhost:8443'
TITLES = dict(zip(['director','advisor','physician','operator','public','provider'], [
 'PANEL DIRECTOR','PANEL ASESOR','PANEL USUARIO MÉDICO','PANEL OPERADOR DE USUARIO MÉDICO',
 'PERFIL PÚBLICO USUARIO MÉDICO','PANEL PROVEEDOR']))
ENABLED = {'director','advisor'}
ACTIONS = {'status','hub',*TITLES}

def require(ok, reason):
    if not ok: raise RuntimeError(reason)

def module(path):
    spec=importlib.util.spec_from_file_location('hub_previous',path)
    result=importlib.util.module_from_spec(spec);spec.loader.exec_module(result);return result

def write(path, value):
    require(not path.is_symlink(),'symlink_forbidden')
    temporary=path.with_suffix('.new');require(not temporary.is_symlink(),'symlink_forbidden')
    temporary.write_text(json.dumps(value,ensure_ascii=False));temporary.chmod(0o600);temporary.replace(path)

def load(owned):
    owned=pathlib.Path(owned).resolve(strict=True)
    require(owned.parent==pathlib.Path('/private/tmp') and owned.name.startswith('mxmed-ase-adm02imp01-'),'owned_root_required')
    require(owned.stat().st_uid==os.getuid() and owned.stat().st_mode&0o777==0o700,'private_owner_required')
    approval_path=owned/'workflow-approval.json'
    require(not approval_path.is_symlink() and approval_path.stat().st_mode&0o077==0,'private_approval_required')
    source=pathlib.Path(json.loads(approval_path.read_text())['root']).resolve()
    require(source.parent==ROOT.parent and source.name=='mxmed-advisor-capability-closure02','approved_baseline_source_required')
    require(subprocess.check_output(['git','rev-parse','HEAD'],cwd=source,text=True).strip()==BASE,'baseline_head_mismatch')
    require(subprocess.check_output(['git','branch','--show-current'],cwd=ROOT,text=True).strip()==BRANCH,'isolated_branch_required')
    subprocess.run(['git','merge-base','--is-ancestor',BASE,'HEAD'],cwd=ROOT,check=True)
    previous=module(source/'scripts/advisor-capability-closure02-review.py').review
    owner, approval=previous.owned_configuration(owned)
    info, origin, expiry, record=previous.verified_preview(owner,approval)
    return owner, previous, origin, expiry, record

def medical_ready():
    ca=pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-tls/ca.crt'
    try:
        with urllib.request.urlopen(MEDICAL+'/public/identity/acceso.php',context=ssl.create_default_context(cafile=str(ca)),timeout=3) as response:
            return response.status==200 and b'login-email' in response.read(200000)
    except (OSError,urllib.error.URLError):return False

def pins():
    return {p:hashlib.sha256((ROOT/p).read_bytes()).hexdigest() for p in ['scripts/interface-hub01.py','tools/interface-hub01/navigation.js','tools/interface-hub01/qa.py']}

class ReadOnlyHub(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path!='/': self.send_error(404);return
        body=b'<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mexico Medico - Revision local</title><body style="margin:0;font-family:system-ui"><main style="padding:20px"><h1>Revision de interfaces</h1><p>Usa Interfaces para abrir o mostrar cada ventana independiente.</p><p>El acceso medico requiere tu login habitual en su ventana. No compartas credenciales con el staff sintetico.</p><p>Este hub funciona solamente en Chromium abierto por el lanzador local.</p></main></body></html>'
        self.send_response(200);self.send_header('Content-Type','text/html; charset=utf-8');self.send_header('Cache-Control','no-store');self.send_header('X-Content-Type-Options','nosniff');self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body)
    def log_message(self,*args): pass

async def serve(owned, previous, origin, expiry, record, state):
    from playwright.async_api import async_playwright
    server=http.server.ThreadingHTTPServer(('127.0.0.1',0),ReadOnlyHub)
    tls=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);tls.load_cert_chain(owned/'cert.pem',owned/'key.pem');server.socket=tls.wrap_socket(server.socket,server_side=True)
    hub='https://127.0.0.1:'+str(server.server_port)
    threading.Thread(target=server.serve_forever,daemon=True).start()
    windows={}; opening=set(); statuses={}; checks=[]
    if medical_ready():ENABLED.add('physician')
    allowed_origins={hub,origin,MEDICAL}
    routes={'hub':hub+'/','director':origin+previous.DIRECTOR,'advisor':origin+previous.ADVISOR,'physician':MEDICAL+'/private-app.php'}
    code=(ROOT/'tools/interface-hub01/navigation.js').read_text()+'('+json.dumps({'origins':list(allowed_origins)})+');'
    current_pins=pins()
    def snapshot():
        items=[]
        reasons={'director':'Gobierno del staff sintético.','advisor':'Fuente administrativa original restringida; no concede autoridad médica.',
          'physician':('Login original manual; membresía colaborador de Leticia verificada para circulodigital@msn.com.' if 'physician' in ENABLED else 'Identity original no disponible. Diagnóstico: falta su directorio privado de notificaciones; no se modifica el runtime original.'),
          'operator':'Faltan cuenta de prueba y contexto operador granular verificados.',
          'public':'Ruta original candidate: route_enabled=0 y canonical_enabled=0.',
          'provider':'Faltan sesión y relación organizacional verificadas para este contexto.'}
        for key,title in TITLES.items():
            opened=key in windows and not windows[key][2].is_closed()
            status=statuses.get(key,'Disponible; requiere login' if key in ENABLED else 'No disponible funcionalmente; publicación deshabilitada' if key=='public' else 'Existe; sin contexto preparado')
            if key=='physician' and key not in ENABLED:status='No disponible funcionalmente; Identity original HTTP 503'
            if not opened and key in ENABLED:status='Disponible; requiere login'
            items.append({'id':key,'title':title,'enabled':key in ENABLED,'open':opened,'status':status,'reason':reasons[key]})
        return {'interfaces':items,'hub_url':hub+'/','director_url':routes['director'],'advisor_url':routes['advisor'],'physician_url':routes['physician'],'expiry':expiry,'source_head':BASE,'checked_at':time.time(),'pid':os.getpid(),'checks':checks}
    async with async_playwright() as pw:
        async def command(source, action):
            page=source['page'];frame=source['frame']
            require(page in [w[2] for w in windows.values()] and frame==page.main_frame,'top_level_owned_window_required')
            parsed=urlparse(frame.url);require(parsed.scheme+'://'+parsed.netloc in allowed_origins,'review_origin_required')
            require(isinstance(action,str) and action in ACTIONS,'allowlisted_action_required')
            require(time.time()<expiry and not (owned/'stop-review').exists(),'review_expired')
            if action=='status':return snapshot()
            require(action=='hub' or action in ENABLED,'context_not_prepared')
            await open_window(action);return snapshot()
        async def login(page, key):
            from urllib.parse import quote
            target=previous.DIRECTOR if key=='director' else previous.ADVISOR
            await page.goto(origin+'/public/identity/acceso.php?next='+quote(target,safe=''))
            await page.locator('#login-email').fill('qa_director@example.test' if key=='director' else record['email'])
            await page.locator('#login-password').fill(previous.private_file(owned,'fixture-password' if key=='director' else 'applicant-password').read_text().strip())
            await page.locator('[data-submit]').click();await page.wait_for_url(origin+target)
        async def open_window(key):
            require(key=='hub' or key in ENABLED,'context_not_prepared')
            if key in opening:return
            if key in windows and not windows[key][2].is_closed():
                await windows[key][2].bring_to_front();return
            opening.add(key)
            print('OPEN',key,flush=True)
            write(state/'status.json',snapshot())
            try:
                browser=await pw.chromium.launch(headless=False,args=['--window-position=0,30','--window-size=1280,800'])
                context=await browser.new_context(ignore_https_errors=True,no_viewport=True)
                await context.expose_binding('mxmedReviewNavigation',command)
                await context.add_init_script(script=code)
                page=await context.new_page();page.set_default_timeout(15000);windows[key]=(browser,context,page)
                if key in {'director','advisor'}:
                    await login(page,key);statuses[key]='Disponible y abierta; Identity autenticada'
                    if key=='advisor':
                        await page.locator('#physician-search').fill('Leticia')
                        await page.locator('#physician-search-form button[type=submit]').click()
                        await page.locator('#discovery-list [data-doctor-id="mxmed-local-development:1"]').first.click()
                else:await page.goto(routes[key])
                if key=='hub':await page.locator('#mxmed-review-navigation').evaluate('(e)=>e.shadowRoot.querySelector("details").open=true')
                await page.bring_to_front()
            except Exception as error:
                print('WINDOW_ERROR',key,str(error).split('Call log:')[0],flush=True)
                statuses[key]='Acceso pendiente; revisar ventana';raise
            finally:opening.discard(key)
        await open_window('hub')
        for key in ['director','advisor']+(['physician'] if 'physician' in ENABLED else []):
            try:await open_window(key)
            except Exception as error:checks.append({'window':key,'error':type(error).__name__})
        last_verify=0
        try:
            while time.time()<expiry and not (state/'stop').exists() and not (owned/'stop-review').exists():
                require(pins()==current_pins,'hub_source_changed')
                if time.time()-last_verify>30:
                    previous.verified_preview(owned,json.loads((owned/'workflow-approval.json').read_text()))
                    if medical_ready():ENABLED.add('physician')
                    else:ENABLED.discard('physician')
                    last_verify=time.time()
                for key,(_,context,page) in list(windows.items()):
                    if page.is_closed():continue
                    if key=='physician':
                        proof=await page.locator('#mxmed-private-bootstrap').count()
                        if proof:
                            data=json.loads(await page.locator('#mxmed-private-bootstrap').text_content())
                            match=data.get('professional',{}).get('doctor_id')=='1' and bool(data.get('account',{}).get('id'))
                            statuses[key]='Disponible y abierta; Leticia autenticada' if match else 'Abierta; contexto médico distinto al solicitado'
                        else:statuses[key]='Disponible y abierta; requiere login médico'
                    elif key in {'director','advisor'} and '/public/identity/' in page.url:statuses[key]='Disponible y abierta; requiere login'
                if (state/'qa-request').exists():
                    (state/'qa-request').unlink()
                    try:checks.extend(await module(ROOT/'tools/interface-hub01/qa.py').run(windows,command,open_window,snapshot,state,origin,hub))
                    except Exception as error:checks.append({'qa_error':str(error)[:500]})
                for key in ['hub','director','advisor','physician']:
                    marker=state/('open-'+key)
                    if marker.exists():
                        require(not marker.is_symlink(),'symlink_forbidden');marker.unlink()
                        if key=='hub' or key in ENABLED:await open_window(key)
                write(state/'status.json',snapshot());await asyncio.sleep(.3)
        finally:
            for browser,_,_ in windows.values():await browser.close()
            server.shutdown();server.server_close()

def main():
    os.umask(0o077)
    parser=argparse.ArgumentParser();parser.add_argument('owned');parser.add_argument('action',choices=['start','run','qa','status','open','open-director','open-advisor','open-physician','stop','credentials-director','credentials-advisor','mfa-director','mfa-advisor'])
    args=parser.parse_args();owned,previous,origin,expiry,record=load(args.owned)
    state=owned/'interface-hub01';require(not state.is_symlink(),'symlink_forbidden');state.mkdir(mode=0o700,exist_ok=True)
    require(state.stat().st_uid==os.getuid() and state.stat().st_mode&0o777==0o700,'private_state_required')
    if args.action.startswith(('credentials-','mfa-')):
        subprocess.run([sys.executable,str(previous.LAUNCHER),str(owned),args.action],check=True);return
    if args.action=='qa':(state/'qa-request').touch(mode=0o600);print('QA dirigida solicitada.');return
    if args.action=='stop':(state/'stop').touch(mode=0o600);print('Cierre del hub y sus ventanas solicitado; runtime original y preview base conservados.');return
    if args.action=='status':
        status=json.loads((state/'status.json').read_text());require(time.time()-status['checked_at']<45,'hub_not_running');print(json.dumps(status,ensure_ascii=False));return
    if args.action.startswith('open'):
        active=False
        if (state/'status.json').is_file():active=time.time()-json.loads((state/'status.json').read_text()).get('checked_at',0)<45 and not (state/'stop').exists()
        if not active:
            with (state/'runner.log').open('ab') as log:subprocess.Popen([sys.executable,__file__,str(owned),'run'],stdout=log,stderr=log,stdin=subprocess.DEVNULL,start_new_session=True)
        (state/('open-hub' if args.action=='open' else args.action)).touch(mode=0o600);print('Ventana solicitada.');return
    if args.action=='start':
        with (state/'runner.log').open('ab') as log:subprocess.Popen([sys.executable,__file__,str(owned),'run'],stdout=log,stderr=log,stdin=subprocess.DEVNULL,start_new_session=True)
        print('Hub independiente solicitado.');return
    require(not (state/'runner.lock').is_symlink(),'symlink_forbidden')
    lock=(state/'runner.lock').open('a+')
    try:fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
    except BlockingIOError:(state/'open-hub').touch(mode=0o600);return
    stop=state/'stop'
    if stop.exists():require(not stop.is_symlink(),'symlink_forbidden');stop.unlink()
    asyncio.run(serve(owned,previous,origin,expiry,record,state))

if __name__=='__main__':main()
