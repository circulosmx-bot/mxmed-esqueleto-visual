#!/usr/bin/env python3
"""Disposable browser checks for the IDENTITY-SESSION02 frontend boundary."""
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler
from pathlib import Path
import json, tempfile, threading, time
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[3]
CONTEXT = {
    "account": {"id": "qa-account", "status": "active"},
    "professional": {"doctor_id": "qa-doctor", "display_name": "Dra. Contexto QA", "role": "owner", "scope": "profile"},
    "capabilities": {"patients": True, "clinical_record": True, "prescriptions": True},
    "session": {"idle_expires_at": "2099-01-01T00:00:00+00:00", "absolute_expires_at": "2099-01-01T00:00:00+00:00"},
}

class Handler(SimpleHTTPRequestHandler):
    session_valid = True
    logout_count = 0
    slow_count = 0
    directory_root = ""
    def __init__(self, *args, **kwargs): super().__init__(*args, directory=self.directory_root, **kwargs)
    def log_message(self, *_): pass
    def reply(self, status, payload):
        body=json.dumps(payload).encode(); self.send_response(status)
        self.send_header("Content-Type","application/json"); self.send_header("Cache-Control","no-store")
        self.send_header("Content-Length",str(len(body))); self.end_headers(); self.wfile.write(body)
    def do_GET(self):
        if self.path == "/api/identity/index.php/current-session":
            if not self.session_valid: return self.reply(401,{"authenticated":False,"state":"SESSION_EXPIRED"})
            return self.reply(200,{"authenticated":True,"application_access":{"allowed":True,"context":CONTEXT},"csrf_token":"qa-csrf"})
        if self.path == "/api/test/forbidden": return self.reply(403,{"error":"forbidden"})
        if self.path == "/api/test/unauthorized": return self.reply(401,{"error":"authentication_required"})
        if self.path == "/api/test/slow":
            Handler.slow_count += 1; time.sleep(.7)
            try: return self.reply(200,{"private":"stale"})
            except (BrokenPipeError, ConnectionResetError): return
        return super().do_GET()
    def do_POST(self):
        if self.path == "/api/identity/index.php/logout":
            Handler.logout_count += 1; Handler.session_valid = False
            return self.reply(200,{"ok":True,"state":"LOGOUT_SUCCESS"})
        return self.reply(404,{"error":"not_found"})

def expect(name, condition):
    if not condition: raise AssertionError(name)
    print(f"{name}=PASS")

with tempfile.TemporaryDirectory(prefix="mxmed-session02-browser-") as tmp:
    directory=Path(tmp); (directory/"assets/js/core").mkdir(parents=True)
    (directory/"assets/js/core/private-session.js").write_bytes((ROOT/"assets/js/core/private-session.js").read_bytes())
    bootstrap=json.dumps({**CONTEXT,"csrf_token":"qa-csrf"},ensure_ascii=False)
    (directory/"fixture.html").write_text(f'''<!doctype html><html class="mxmed-auth-checking"><head>
      <style>html.mxmed-auth-checking body{{visibility:hidden}}.mxmed-session-lock{{visibility:visible!important}}</style>
      <script id="mxmed-private-bootstrap" type="application/json">{bootstrap}</script>
      <script src="/assets/js/core/private-session.js"></script></head>
      <body><header class="mx-global-header"><b class="mx-gh-identity-name-text"></b></header>
      <nav data-required-capability="patients">Pacientes</nav><main id="private-shell">Privado QA</main></body></html>''',encoding="utf-8")
    Handler.directory_root=str(directory)
    server=ThreadingHTTPServer(("127.0.0.1",0),Handler); threading.Thread(target=server.serve_forever,daemon=True).start()
    base=f"http://127.0.0.1:{server.server_port}"
    try:
      with sync_playwright() as pw:
        browser=pw.chromium.launch(headless=True)
        for width,height in [(1440,900),(1366,768),(820,1180)]:
            Handler.session_valid=True
            page=browser.new_page(viewport={"width":width,"height":height})
            page.goto(base+"/fixture.html"); page.wait_for_function("document.documentElement.classList.contains('mxmed-auth-checking') === false")
            expect(f"QA_{width}_{height}",page.locator("#private-shell").is_visible() and page.evaluate("innerWidth") == width)
            page.close()

        Handler.session_valid=True
        context=browser.new_context(); page=context.new_page(); page.goto(base+"/fixture.html")
        page.wait_for_function("window.mxmedPrivateSession?.state === 'authenticated'")
        expect("AUTH03",page.evaluate("mxmedStore.doctor_id") == "qa-doctor")
        page.reload(); page.wait_for_function("window.mxmedPrivateSession?.state === 'authenticated'")
        expect("AUTH04",page.locator("#private-shell").is_visible())
        status=page.evaluate("fetch('/api/test/forbidden').then(r=>r.status)")
        expect("AUTH08",status == 403 and page.evaluate("mxmedPrivateSession.state") == "authenticated")
        page.evaluate("localStorage.setItem('mxmed_rx_draft_v1:qa','patient'); localStorage.setItem('mxmed.sidebar.state','collapsed'); sessionStorage.setItem('mxmed.m7.qa','patient'); mxmedPrivateSession.clearSensitiveState()")
        expect("AUTH12",page.evaluate("localStorage.getItem('mxmed_rx_draft_v1:qa')===null && sessionStorage.getItem('mxmed.m7.qa')===null && localStorage.getItem('mxmed.sidebar.state')==='collapsed'"))
        page.evaluate("dispatchEvent(new PageTransitionEvent('pagehide',{persisted:true}))")
        expect("AUTH10_LOCK",page.evaluate("document.documentElement.classList.contains('mxmed-auth-checking')"))
        page.evaluate("dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true}))")
        page.wait_for_function("window.mxmedPrivateSession.state === 'authenticated'")
        expect("AUTH10",not page.evaluate("document.documentElement.classList.contains('mxmed-auth-checking')"))

        page.evaluate("window.__raceResult=null; fetch('/api/test/slow').then(()=>window.__raceResult=false).catch(e=>window.__raceResult='error:'+e.name); true")
        page.evaluate("mxmedPrivateSession.lock('expired',{redirect:false,broadcast:false})")
        page.wait_for_function("window.__raceResult !== null")
        expect("AUTH20",str(page.evaluate("window.__raceResult")).startswith("error:"))
        expect("AUTH06_AUTH07_AUTH19",page.evaluate("mxmedPrivateSession.state") == "locked" and page.locator("#mxmed-session-lock").is_visible())
        context.close()

        Handler.session_valid=True
        context=browser.new_context(); one=context.new_page(); two=context.new_page()
        one.goto(base+"/fixture.html"); two.goto(base+"/fixture.html")
        one.wait_for_function("mxmedPrivateSession.state==='authenticated'"); two.wait_for_function("mxmedPrivateSession.state==='authenticated'")
        one.evaluate("new BroadcastChannel('mxmed-session').postMessage({type:'logout',at:Date.now()})")
        two.wait_for_function("mxmedPrivateSession.state==='locked'")
        expect("AUTH11",two.locator("#mxmed-session-lock").is_visible())
        context.close()

        Handler.session_valid=True; Handler.logout_count=0
        context=browser.new_context(); page=context.new_page(); page.goto(base+"/fixture.html")
        page.wait_for_function("mxmedPrivateSession.state==='authenticated'")
        page.evaluate("localStorage.setItem('mxmed.activeContext','patient')")
        page.evaluate("mxmedPrivateSession.logout()")
        page.wait_for_url("**/public/identity/acceso.php?state=logged-out")
        expect("AUTH09",Handler.logout_count == 1 and page.evaluate("localStorage.getItem('mxmed.activeContext')") is None)
        context.close(); browser.close()
    finally:
      server.shutdown(); server.server_close()

print("PRIVATE_SESSION_BROWSER_QA=PASS")
