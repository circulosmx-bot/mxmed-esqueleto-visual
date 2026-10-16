#!/usr/bin/env python3
"""Loopback-only TLS front door for the persistent OP02 environment."""
import os
import argparse
import http.client
import http.server
import ssl

HOP = {'connection','keep-alive','proxy-authenticate','proxy-authorization','te','trailer','transfer-encoding','upgrade'}
CANONICAL_SESSION_COOKIE = '__Host-mxmed_session'
IMP07 = os.environ.get('MXMED_OP_IMP07_REHEARSAL') == '1'
REHEARSAL = os.environ.get('MXMED_OP_UI03_REHEARSAL') == '1'
QA_SESSION_COOKIE = '__Host-mxmed_opimp07_session' if IMP07 else '__Host-mxmed_opui03_session' if REHEARSAL else '__Host-mxmed_op02_session'
HOST = '127.0.0.1:8447' if IMP07 else '127.0.0.1:8446' if REHEARSAL else '127.0.0.1:8445'

def backend_cookie_header(raw):
    mapped=[]
    for item in raw.split(';'):
        name,separator,value=item.strip().partition('=')
        if not separator or name != QA_SESSION_COOKIE: continue
        mapped.append((CANONICAL_SESSION_COOKIE if name == QA_SESSION_COOKIE else name)+'='+value)
    return '; '.join(mapped)

class Handler(http.server.BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'
    server_version = 'MXMEDOP02TLS'
    def log_message(self, *_): pass
    def run(self):
        if self.headers.get_all('Host') != [HOST]:
            self.send_error(421); return
        lengths = self.headers.get_all('Content-Length') or []
        if self.headers.get('Transfer-Encoding') or len(lengths) > 1:
            self.send_error(400); return
        try:
            length = int(lengths[0]) if lengths else 0
            if length < 0 or length > 16 * 1024 * 1024: raise ValueError()
        except ValueError:
            self.send_error(413); return
        body = self.rfile.read(length) if length else None
        connection = http.client.HTTPConnection('127.0.0.1', self.server.backend_port, timeout=30)
        excluded = HOP | {v.strip().lower() for v in self.headers.get('Connection','').split(',')}
        headers = {k:v for k,v in self.headers.items() if k.lower() not in excluded and k.lower() not in {'host','content-length'} and not k.lower().startswith('x-forwarded-')}
        # HTTP header names are case-insensitive (including HTTP/2 browser tooling).
        cookie_keys=[key for key in headers if key.lower()=='cookie']
        if cookie_keys:
            raw='; '.join(headers.pop(key) for key in cookie_keys)
            headers['Cookie']=backend_cookie_header(raw)
        headers.update({'Host':HOST,'Connection':'close'})
        try:
            connection.request(self.command,self.path,body=body,headers=headers)
            response=connection.getresponse(); payload=response.read()
            self.send_response(response.status)
            for key,value in response.getheaders():
                if key.lower() == 'set-cookie' and value.startswith(CANONICAL_SESSION_COOKIE+'='):
                    value=QA_SESSION_COOKIE+value[len(CANONICAL_SESSION_COOKIE):]
                if key.lower() not in HOP and key.lower() not in {'content-length','server','date'}: self.send_header(key,value)
            self.send_header('Content-Length',str(len(payload)))
            self.send_header('X-Robots-Tag','noindex, nofollow, noarchive')
            self.send_header('Cache-Control','no-store' if self.path.startswith('/api/') else 'no-cache')
            self.end_headers()
            if self.command != 'HEAD': self.wfile.write(payload)
        except (OSError,http.client.HTTPException): self.send_error(502)
        finally: connection.close()
    do_GET=do_HEAD=do_POST=do_PUT=do_PATCH=do_DELETE=run

class Server(http.server.ThreadingHTTPServer):
    allow_reuse_address=True
    def __init__(self,address,handler,port): super().__init__(address,handler); self.backend_port=port

if __name__=='__main__':
    parser=argparse.ArgumentParser(); parser.add_argument('--cert',required=True);parser.add_argument('--key',required=True);parser.add_argument('--backend-port',type=int,default=8094)
    args=parser.parse_args(); server=Server(('127.0.0.1',8447 if IMP07 else 8446 if REHEARSAL else 8445),Handler,args.backend_port)
    context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.minimum_version=ssl.TLSVersion.TLSv1_2;context.load_cert_chain(args.cert,args.key)
    server.socket=context.wrap_socket(server.socket,server_side=True);server.serve_forever()
