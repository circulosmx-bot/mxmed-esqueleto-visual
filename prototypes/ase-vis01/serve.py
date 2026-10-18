#!/usr/bin/env python3
"""Loopback-only, expiring, allowlisted static UI-3 preview. No product composition."""
import argparse
import datetime as dt
import http.server
import json
from pathlib import Path
import subprocess
import threading

ROOT = Path(__file__).resolve().parents[2]
HERE = Path(__file__).resolve().parent
ROUTES = {
    '/': (HERE / 'index.html', 'text/html; charset=utf-8'),
    '/style.css': (HERE / 'style.css', 'text/css; charset=utf-8'),
    '/catalog.js': (HERE / 'catalog.js', 'text/javascript; charset=utf-8'),
    '/app.js': (HERE / 'app.js', 'text/javascript; charset=utf-8'),
    '/assets/css/media-review-inbox.css': (ROOT / 'assets/css/media-review-inbox.css', 'text/css; charset=utf-8'),
}

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--port', type=int, default=8140)
    parser.add_argument('--expires', help='UTC ISO time; default seven days from startup')
    args = parser.parse_args()
    if not 8140 <= args.port <= 65535:
        parser.error('Preview port must be >= 8140.')
    now = dt.datetime.now(dt.timezone.utc)
    expires = dt.datetime.fromisoformat(args.expires.replace('Z', '+00:00')) if args.expires else now + dt.timedelta(days=7)
    if expires.tzinfo is None or expires <= now:
        parser.error('Expiry must be a future time with timezone.')
    branch = subprocess.check_output(['git', 'branch', '--show-current'], cwd=ROOT, text=True).strip()
    if branch != 'prototype/ase-vis01-advisor-capability-matrix':
        parser.error('Run only from the isolated ASE-VIS01 prototype branch.')
    commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
    class Handler(http.server.BaseHTTPRequestHandler):
        def do_GET(self):
            self.respond(False)
        def do_HEAD(self):
            self.respond(True)
        def do_POST(self):
            self.send_error(405, 'Static preview; writes unavailable')
        do_PUT = do_POST
        do_PATCH = do_POST
        do_DELETE = do_POST
        def respond(self, head):
            if dt.datetime.now(dt.timezone.utc) >= expires:
                self.send_error(410, 'Preview expired')
                return
            route = ROUTES.get(self.path.split('?', 1)[0])
            if not route:
                self.send_error(404)
                return
            path, mime = route
            content = path.read_bytes()
            if path.name == 'index.html':
                content = content.replace(b'__PREVIEW_COMMIT__', commit.encode()).replace(b'__PREVIEW_EXPIRY__', expires.isoformat().encode())
            self.send_response(200)
            self.send_header('Content-Type', mime)
            self.send_header('Content-Length', str(len(content)))
            self.send_header('Cache-Control', 'no-store')
            self.send_header('X-Content-Type-Options', 'nosniff')
            self.send_header('Content-Security-Policy', "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'none'; form-action 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'")
            self.send_header('Referrer-Policy', 'no-referrer')
            self.end_headers()
            if not head:
                self.wfile.write(content)
        def log_message(self, *args):
            pass
    server = http.server.ThreadingHTTPServer(('127.0.0.1', args.port), Handler)
    timer = threading.Timer((expires - now).total_seconds(), server.shutdown)
    timer.daemon = True
    timer.start()
    print(json.dumps({'url':f'http://127.0.0.1:{args.port}/', 'branch':branch, 'commit':commit, 'expires':expires.isoformat()}), flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        timer.cancel()
        server.server_close()

if __name__ == '__main__':
    main()
