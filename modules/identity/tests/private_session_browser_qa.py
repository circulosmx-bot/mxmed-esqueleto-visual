#!/usr/bin/env python3
"""Bounded browser regression for IDENTITY-SESSION02B (mock canonical API, no real account)."""
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
    directory_root = ""
    session_mode = "valid"
    session_plan = []
    session_count = 0
    logout_count = 0
    lock = threading.Lock()

    def __init__(self, *args, **kwargs): super().__init__(*args, directory=self.directory_root, **kwargs)
    def log_message(self, *_): pass
    def reply(self, status, payload):
        body = json.dumps(payload).encode()
        self.send_response(status); self.send_header("Content-Type", "application/json")
        self.send_header("Cache-Control", "no-store"); self.send_header("Content-Length", str(len(body)))
        self.end_headers(); self.wfile.write(body)

    @classmethod
    def reset(cls, mode="valid", plan=None):
        with cls.lock:
            cls.session_mode, cls.session_plan, cls.session_count = mode, list(plan or []), 0

    def session_reply(self):
        with Handler.lock:
            Handler.session_count += 1
            delay, mode = Handler.session_plan.pop(0) if Handler.session_plan else (0, Handler.session_mode)
        if delay: time.sleep(delay)
        if mode == "expired": return self.reply(401, {"authenticated": False, "state": "SESSION_EXPIRED"})
        if mode == "forbidden": return self.reply(403, {"authenticated": True, "state": "ACCESS_DENIED"})
        if mode == "unavailable": return self.reply(503, {"authenticated": False, "state": "TEMPORARILY_UNAVAILABLE"})
        if mode == "malformed": return self.reply(200, {"authenticated": False, "state": "AUTHENTICATED"})
        if mode == "denied": return self.reply(200, {"authenticated": True, "state": "AUTHENTICATED", "account_status": "active",
            "application_access": {"allowed": False, "context": None}, "csrf_token": "qa-csrf"})
        return self.reply(200, {"authenticated": True, "state": "AUTHENTICATED", "account_status": "active",
            "application_access": {"allowed": True, "context": CONTEXT}, "csrf_token": "qa-csrf"})

    def do_GET(self):
        path = self.path.split("?", 1)[0]
        if path == "/api/identity/index.php/current-session": return self.session_reply()
        if path in ("/api/media/owner-review.php", "/api/media/review-batch-submit.php", "/api/test/unauthorized"):
            return self.reply(401, {"ok": False, "error": "authentication_required"})
        if path == "/api/test/forbidden": return self.reply(403, {"ok": False, "error": "forbidden"})
        if path == "/api/patients/index.php": return self.reply(200, {"ok": True, "data": [{"id": "synthetic-patient"}]})
        return super().do_GET()

    def do_POST(self):
        if self.path == "/api/identity/index.php/logout":
            Handler.logout_count += 1; Handler.session_mode = "expired"
            return self.reply(200, {"ok": True, "state": "LOGOUT_SUCCESS"})
        return self.reply(404, {"error": "not_found"})

def expect(name, condition):
    if not condition: raise AssertionError(name)
    print(f"{name}=PASS")

def open_authenticated(browser, base, viewport=None):
    Handler.reset()
    page = browser.new_page(viewport=viewport)
    page.goto(base + "/fixture.html")
    page.wait_for_function("mxmedPrivateSession.state === 'authenticated'")
    return page

with tempfile.TemporaryDirectory(prefix="mxmed-session02b-browser-") as tmp:
    directory = Path(tmp); (directory / "assets/js/core").mkdir(parents=True)
    (directory / "assets/js/core/private-session.js").write_bytes((ROOT / "assets/js/core/private-session.js").read_bytes())
    bootstrap = json.dumps({**CONTEXT, "csrf_token": "qa-csrf"}, ensure_ascii=False)
    (directory / "fixture.html").write_text(f'''<!doctype html><html class="mxmed-auth-checking"><head>
      <style>html.mxmed-auth-checking body{{visibility:hidden}}.mxmed-session-lock{{visibility:visible!important}}</style>
      <script id="mxmed-private-bootstrap" type="application/json">{bootstrap}</script>
      <script src="/assets/js/core/private-session.js"></script></head>
      <body><nav data-required-capability="patients">Pacientes</nav><main id="private-shell">Privado QA</main></body></html>''', encoding="utf-8")
    Handler.directory_root = str(directory)
    server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
    threading.Thread(target=server.serve_forever, daemon=True).start()
    base = f"http://127.0.0.1:{server.server_port}"
    try:
      with sync_playwright() as pw:
        for engine_name in ("chromium", "webkit"):
          browser = getattr(pw, engine_name).launch(headless=True)
          for width, height in ((1440, 900), (1366, 768), (820, 1180)):
            page = open_authenticated(browser, base, {"width": width, "height": height})
            expect(f"QA_{engine_name.upper()}_{width}_{height}", page.locator("#private-shell").is_visible())
            page.close()

          page = open_authenticated(browser, base); Handler.reset()
          status = page.evaluate("fetch('/api/media/owner-review.php').then(r => r.status)")
          expect(f"TEST_A_{engine_name.upper()}", status == 401 and Handler.session_count == 1
                 and page.evaluate("mxmedPrivateSession.state") == "authenticated")
          page.close()

          page = open_authenticated(browser, base); Handler.reset(plan=[(.2, "valid")])
          statuses = page.evaluate("Promise.all([fetch('/api/media/owner-review.php'), fetch('/api/media/review-batch-submit.php')]).then(rs => rs.map(r => r.status))")
          expect(f"TEST_B_{engine_name.upper()}", statuses == [401, 401] and Handler.session_count == 1
                 and page.evaluate("mxmedPrivateSession.state") == "authenticated")
          page.close()

          page = open_authenticated(browser, base); Handler.reset("valid")
          status = page.evaluate("fetch('/api/test/forbidden').then(r => r.status)")
          expect(f"TEST_D_{engine_name.upper()}", status == 403 and Handler.session_count == 0
                 and page.evaluate("mxmedPrivateSession.state") == "authenticated")
          page.close()

          page = open_authenticated(browser, base); Handler.reset("denied")
          page.evaluate("fetch('/api/test/unauthorized')")
          page.wait_for_function("mxmedPrivateSession.state === 'locked'")
          expect(f"TEST_D_CANONICAL_DENIAL_{engine_name.upper()}", "finalizado" not in page.locator("#mxmed-session-lock").inner_text().lower())
          page.close()

          page = open_authenticated(browser, base); Handler.reset("unavailable")
          page.evaluate("fetch('/api/test/unauthorized')")
          page.wait_for_function("mxmedPrivateSession.state === 'unavailable'")
          expect(f"TEST_E_{engine_name.upper()}", "finalizado" not in page.locator("#mxmed-session-lock").inner_text().lower())
          page.close()

          page = open_authenticated(browser, base); Handler.reset(plan=[(.3, "expired"), (0, "valid")])
          page.evaluate("window.__oldRequest = fetch('/api/test/unauthorized').then(r => r.status); true")
          page.wait_for_timeout(50)
          page.evaluate("dispatchEvent(new StorageEvent('storage',{key:'mxmed.session.signal',newValue:JSON.stringify({type:'authenticated',at:Date.now()})}))")
          page.wait_for_timeout(450)
          expect(f"TEST_F_{engine_name.upper()}", Handler.session_count == 2
                 and page.evaluate("mxmedPrivateSession.state") == "authenticated")
          page.close()

          page = open_authenticated(browser, base); Handler.reset("valid")
          page.evaluate("dispatchEvent(new StorageEvent('storage',{key:'mxmed.session.signal',newValue:JSON.stringify({type:'expired',at:Date.now()-10000})}))")
          page.wait_for_timeout(150)
          expect(f"TEST_G_{engine_name.upper()}", Handler.session_count == 1
                 and page.evaluate("mxmedPrivateSession.state") == "authenticated")
          page.close()

          page = open_authenticated(browser, base); page.reload(); page.wait_for_function("mxmedPrivateSession.state === 'authenticated'")
          expect(f"TEST_I_{engine_name.upper()}", page.evaluate("mxmedStore.doctor_id") == "qa-doctor")
          Handler.reset(); result = page.evaluate("fetch('/api/patients/index.php?q=synthetic').then(r=>r.json())")
          expect(f"TEST_J_{engine_name.upper()}", result["ok"] is True and Handler.session_count == 0)
          page.close()

          page = open_authenticated(browser, base); Handler.reset("malformed")
          page.evaluate("fetch('/api/test/unauthorized')")
          page.wait_for_function("mxmedPrivateSession.state === 'unavailable'")
          expect(f"TEST_N_{engine_name.upper()}", page.evaluate("mxmedPrivateSession.state") == "unavailable")
          page.close()

          context = browser.new_context(); Handler.reset(); Handler.logout_count = 0
          one, two = context.new_page(), context.new_page()
          one.goto(base + "/fixture.html"); two.goto(base + "/fixture.html")
          one.wait_for_function("mxmedPrivateSession.state==='authenticated'"); two.wait_for_function("mxmedPrivateSession.state==='authenticated'")
          one.evaluate("mxmedPrivateSession.logout()")
          two.wait_for_function("mxmedPrivateSession.state==='locked'")
          expect(f"TEST_H_{engine_name.upper()}", Handler.logout_count == 1 and two.locator("#mxmed-session-lock").is_visible())
          context.close()

          page = open_authenticated(browser, base); Handler.reset("expired")
          page.evaluate("fetch('/api/test/unauthorized')")
          page.wait_for_function("mxmedPrivateSession.state === 'locked'")
          expect(f"TEST_C_{engine_name.upper()}", "finalizado" in page.locator("#mxmed-session-lock").inner_text().lower())
          page.close(); browser.close()
    finally:
      server.shutdown(); server.server_close()

print("PRIVATE_SESSION_BROWSER_QA=PASS_MOCK_CANONICAL_API")
