#!/usr/bin/env python3
"""Loopback HTTPS bridge for disposable provider UI QA only."""
import http.client
import http.server
import ssl
import sys

class Proxy(http.server.BaseHTTPRequestHandler):
    def forward(self):
        body = self.rfile.read(int(self.headers.get('Content-Length', '0')))
        upstream = http.client.HTTPConnection('127.0.0.1', 18150, timeout=20)
        headers = {key: value for key, value in self.headers.items()
                   if key.lower() not in {'host', 'connection', 'content-length'}}
        headers['Host'] = '127.0.0.1:18150'
        upstream.request(self.command, self.path, body=body, headers=headers)
        response = upstream.getresponse()
        payload = response.read()
        self.send_response(response.status)
        for key, value in response.getheaders():
            if key.lower() not in {'connection', 'transfer-encoding', 'content-length'}:
                self.send_header(key, value)
        self.send_header('Content-Length', str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)
        upstream.close()

    do_GET = forward
    do_POST = forward
    do_PATCH = forward
    def log_message(self, *_):
        pass

server = http.server.ThreadingHTTPServer(('127.0.0.1', 8140), Proxy)
context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
context.load_cert_chain(sys.argv[1], sys.argv[2])
server.socket = context.wrap_socket(server.socket, server_side=True)
server.serve_forever()
