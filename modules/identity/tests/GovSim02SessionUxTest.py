#!/usr/bin/env python3
"""Original session JS/CSS/dirty guard, synthetic API and browser clocks only."""
from datetime import datetime, timezone
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler
from pathlib import Path
import argparse, json, re, tempfile, threading
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[3]
ap = argparse.ArgumentParser()
ap.add_argument('--evidence', required=True)
args = ap.parse_args()
evidence = Path(args.evidence)
evidence.mkdir(mode=0o700, parents=True, exist_ok=True)
context = {
    'account': {'id': 'sim02-physician', 'status': 'active'},
    'professional': {'doctor_id': 'sim02-profile', 'display_name': 'Profesional sintético', 'role': 'owner', 'scope': 'profile'},
    'capabilities': {'patients': True, 'clinical_record': True, 'prescriptions': True},
    'session': {'idle_expires_at': '2026-10-16T01:00:00+00:00', 'absolute_expires_at': '2026-10-16T12:00:00+00:00'},
}
css = re.search(r'<style id="mxmed-auth-boundary-style">(.*?)</style>', (ROOT/'private-app.php').read_text(), re.S).group(1)
class Handler(SimpleHTTPRequestHandler):
    def log_message(self, *_): pass

with tempfile.TemporaryDirectory(prefix='mxmed-sim02-ux-') as temp:
    folder = Path(temp)
    for name in ['assets/js/core/private-session.js', 'assets/js/clinical/patient-workspace-navigation-guard.js']:
        target = folder/name; target.parent.mkdir(parents=True, exist_ok=True); target.write_bytes((ROOT/name).read_bytes())
    (folder/'fixture.html').write_text('<!doctype html><html class="mxmed-auth-checking"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style>'
        +'<script id="mxmed-private-bootstrap" type="application/json">'+json.dumps(context)+'</script>'
        +'<script src="/assets/js/core/private-session.js"></script></head><body><main><textarea id="draft"></textarea></main>'
        +'<script src="/assets/js/clinical/patient-workspace-navigation-guard.js"></script></body></html>')
    server = ThreadingHTTPServer(('127.0.0.1',0), lambda *a,**k: Handler(*a,directory=temp,**k))
    threading.Thread(target=server.serve_forever,daemon=True).start()
    results=[]
    try:
        with sync_playwright() as pw:
            for engine in ['chromium','webkit']:
                browser=getattr(pw,engine).launch(headless=True)
                try:
                    for width,height in [(1440,900),(1366,768),(820,1180)]:
                        page=browser.new_page(viewport={'width':width,'height':height})
                        page.clock.install(time=datetime(2026,10,16,tzinfo=timezone.utc))
                        calls=[]; state={'expired':False}
                        def api(route):
                            calls.append({'url':route.request.url,'method':route.request.method})
                            payload={'authenticated':True,'state':'AUTHENTICATED','account_status':'active','csrf_token':'synthetic-csrf','application_access':{'allowed':True,'context':context}}
                            route.fulfill(status=401 if state['expired'] else 200,content_type='application/json',body=json.dumps({} if state['expired'] else payload))
                        page.route('**/api/**',api)
                        page.on('dialog',lambda dialog:dialog.dismiss())
                        page.goto('http://127.0.0.1:'+str(server.server_port)+'/fixture.html')
                        page.wait_for_function("mxmedPrivateSession.state==='authenticated'")
                        page.evaluate("void mxmedPatientWorkspaceNavigationGuard.register({id:'sim02-consultation',isDirty:()=>document.querySelector('#draft').value!=='',discard:()=>false})")
                        page.locator('#draft').fill('Consulta sintética sin guardar')
                        before=len(calls)
                        page.clock.fast_forward(58*60*1000+100)
                        notice=page.locator('#mxmed-session-warning')
                        assert notice.is_visible() and 'Tienes cambios pendientes' in notice.inner_text(), page.evaluate("({state:mxmedPrivateSession.state,now:new Date().toISOString(),active:mxmedPatientWorkspaceNavigationGuard.active().map(x=>x.id),notice:document.querySelector('#mxmed-session-warning')?.textContent})")
                        assert page.locator('#draft').input_value()=='Consulta sintética sin guardar'
                        assert page.evaluate('mxmedPatientWorkspaceNavigationGuard.active().length')==1
                        assert len(calls)==before, 'warning must not keepalive or autosave'
                        rect=notice.bounding_box(); assert rect['x']>=0 and rect['x']+rect['width']<=width+1
                        assert page.evaluate('document.documentElement.scrollWidth')<=width
                        page.screenshot(path=str(evidence/(engine+'-'+str(width)+'-warning.png')))
                        state['expired']=True
                        page.clock.fast_forward(120500)
                        page.wait_for_function("mxmedPrivateSession.state==='locked'")
                        assert 'Por seguridad, inicia sesión nuevamente para continuar.' in page.locator('#mxmed-session-lock').inner_text()
                        writes_before=sum(c['method']=='POST' for c in calls)
                        denied=page.evaluate("fetch('/api/clinical/index.php/draft',{method:'POST',body:'{}'}).then(()=>false).catch(e=>e.name==='AbortError')")
                        assert denied and sum(c['method']=='POST' for c in calls)==writes_before==0
                        results.append({'engine':engine,'width':width,'height':height,'warning':True,'draft_preserved_before_expiry':True,'no_keepalive_or_autosave':True,'locked_after_server_401':True,'post_expiry_write_blocked':True})
                        print('SIM02_UX_'+engine+'_'+str(width)+'x'+str(height)+'=PASS')
                        page.close()
                finally: browser.close()
        (evidence/'results.json').write_text(json.dumps(results,indent=2)+'\n')
    finally: server.shutdown();server.server_close()
