#!/usr/bin/env python3
"""Synthetic read-only QA of ORIGINAL public/onboarding templates; no DB or Identity session."""
import argparse, functools, hashlib, http.server, json, os, pathlib, subprocess, threading
from urllib.parse import parse_qs, quote, urlsplit
from playwright.sync_api import sync_playwright, expect

ROOT=pathlib.Path(__file__).resolve().parents[1]
FIXTURE=ROOT/'modules/profiles/tests/PublicEnrollmentTemplateFixture.php'

def main():
    ap=argparse.ArgumentParser();ap.add_argument('--evidence',required=True);args=ap.parse_args()
    out=pathlib.Path(args.evidence);out.mkdir(parents=True,exist_ok=True);out.chmod(0o700)
    tested=['profiles/doctor.php','profiles/listing.php','modules/profiles/services/PublicProfileClaimEligibility.php',
        'modules/profiles/services/PublicEnrollmentPresentation.php','modules/identity/Accreditation/PhysicianOnboardingPage.php',
        'partials/identity/physician-onboarding.php','assets/js/identity/physician-onboarding.js',
        'modules/profiles/tests/PublicEnrollmentTemplateFixture.php','scripts/ux-align01-public-browser-qa.py']
    manifest={p:hashlib.sha256((ROOT/p).read_bytes()).hexdigest() for p in tested}
    results=[]
    class Handler(http.server.SimpleHTTPRequestHandler):
        def log_message(self,*args):pass
        def do_GET(self):
            uri=urlsplit(self.path);path=uri.path
            if path.startswith('/api/profiles/public/doctor/'):
                path='/fixture-dto';query='profile='+quote(uri.path.rsplit('/',1)[1])
            elif path in ['/profiles/doctor.php','/profiles/listing.php','/public/identity/perfil-medico.php']:query=uri.query
            else:return super().do_GET()
            env={**os.environ,'MXMED_PUBLIC_ENROLLMENT_SYNTHETIC_QA':'1','MXMED_PROFILES_API_BASE':self.server.origin}
            # Every render is CLI + synthetic input. No real config, SQL or cookie is read.
            answer=subprocess.run(['php',str(FIXTURE),path,query],cwd=ROOT,env=env,text=True,capture_output=True,timeout=20)
            if answer.returncode or answer.stderr:raise RuntimeError('synthetic template failed: '+answer.stderr[-1500:])
            payload=answer.stdout.encode();self.send_response(200);self.send_header('Content-Type','application/json' if path=='/fixture-dto' else 'text/html; charset=utf-8');self.send_header('Content-Length',str(len(payload)));self.end_headers();self.wfile.write(payload)
    server=http.server.ThreadingHTTPServer(('127.0.0.1',0),functools.partial(Handler,directory=str(ROOT)))
    server.origin='http://127.0.0.1:'+str(server.server_port);thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start()
    try:
        with sync_playwright() as playwright:
            for engine in ['chromium','webkit']:
                browser=getattr(playwright,engine).launch(headless=True)
                try:
                    for width,height in [(1440,900),(1366,768),(820,1180)]:
                        context=browser.new_context(viewport={'width':width,'height':height});page=context.new_page();errors=[];writes=[]
                        page.on('pageerror',lambda e:errors.append(str(e)))
                        def readonly(route):
                            if route.request.method not in ['GET','HEAD']:writes.append(route.request.method);route.abort()
                            else:route.continue_()
                        context.route('**/*',readonly)
                        page.goto(server.origin+'/profiles/doctor.php?doctor_id=synthetic-free')
                        cta=page.locator('.mxpp-action-claim');expect(cta).to_be_visible()
                        expect(cta).to_have_attribute('href','/public/identity/perfil-medico.php?entry=claim&profile=synthetic-free')
                        assert page.locator('footer a',has_text='FORMAR PARTE').count()==1
                        cta.click();expect(page.locator('[data-origin-profile]')).to_contain_text('Profesional Sintética de Prueba')
                        assert page.locator('#profile-reference,[name=profile_reference],[name=doctor_id]').count()==0
                        assert page.locator('fieldset:enabled').count()==0
                        expect(page.locator('[data-onboarding-status]')).to_contain_text('no se ha guardado ni enviado')
                        login=page.locator('a',has_text='Ya tengo cuenta');dest=parse_qs(urlsplit(login.get_attribute('href')).query)['next'][0]
                        assert dest=='/public/identity/perfil-medico.php?entry=claim&profile=synthetic-free'
                        page.reload();expect(page.locator('[data-origin-profile]')).to_contain_text('Profesional Sintética de Prueba')
                        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
                        page.screenshot(path=str(out/f'{engine}-{width}-claim.png'),full_page=True)
                        for profile in ['synthetic-owned','synthetic-paid']:
                            page.goto(server.origin+'/profiles/doctor.php?doctor_id='+profile);assert page.locator('.mxpp-action-claim').count()==0
                        page.goto(server.origin+'/profiles/listing.php?state=Estado&city=Ciudad&specialty=Especialidad')
                        page.locator('footer a',has_text='FORMAR PARTE').click();expect(page.locator('h1')).to_have_text('Forma parte de México Médico')
                        expect(page.locator('#enrollment-name')).to_be_disabled();expect(page.locator('#enrollment-first')).to_be_disabled()
                        assert page.locator('[data-origin-profile],#profile-reference,[name=profile_reference],[name=doctor_id]').count()==0
                        for field in page.locator('input').all():assert field.evaluate('element => element.labels?.length > 0'),'input has no accessible native label'
                        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
                        page.screenshot(path=str(out/f'{engine}-{width}-join.png'),full_page=True)
                        page.goto(server.origin+'/public/identity/perfil-medico.php?entry=claim&profile=synthetic-owned')
                        expect(page.locator('[data-onboarding-status]')).to_contain_text('No encontramos un perfil disponible')
                        assert not errors,errors
                        assert not writes,writes
                        results.append({'engine':engine,'width':width,'height':height,'result':'PASS','mutations':0})
                        context.close()
                finally:browser.close()
        assert manifest=={p:hashlib.sha256((ROOT/p).read_bytes()).hexdigest() for p in tested},'tested source changed'
        (out/'result.json').write_text(json.dumps({'result':'PASS','scope':'original_template_synthetic_readonly','authenticated_flow':'NOT_RUN_HERE','origin_persistence':'NOT_IMPLEMENTED','checks':results,'source_manifest':manifest},indent=2))
        print('UX_ALIGN01_PUBLIC_TEMPLATES=PASS_2_ENGINES_3_SIZES_NO_MUTATIONS',flush=True)
    finally:server.shutdown();server.server_close()

if __name__=='__main__':main()
