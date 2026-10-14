#!/usr/bin/env python3
"""Dedicated localhost HTTPS front door. No trust bypass, origin rewriting or auth."""
import argparse
import http.client
import http.server
import ssl

HOP = {'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization',
       'te', 'trailer', 'transfer-encoding', 'upgrade'}


class Proxy(http.server.BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'
    server_version = 'MXMEDLocalTLS'

    def log_message(self, *_):
        pass  # URLs may contain recovery tokens; never access-log them.

    def do_request(self):
        if self.headers.get_all('Host') != ['localhost:8443']:
            self.fail(421)
            return
        if not self.path.startswith('/') or self.path.startswith('//'):
            self.fail(400)
            return
        lengths = self.headers.get_all('Content-Length') or []
        if self.headers.get('Transfer-Encoding') or len(lengths) > 1:
            self.fail(400)
            return
        try:
            length = int(lengths[0]) if lengths else 0
            if length < 0 or length > 16 * 1024 * 1024:
                raise ValueError()
        except ValueError:
            self.fail(413)
            return
        self.connection.settimeout(30)
        backend_port = (self.server.views_port
                        if self.path.split('?', 1)[0] in {
                            '/modules/clinical/ui/document.php',
                            '/modules/clinical/ui/viewer.php',
                        }
                        else self.server.backend_port)
        connection = http.client.HTTPConnection('127.0.0.1', backend_port, timeout=30)
        try:
            body = self.rfile.read(length) if length else None
            if body is not None and len(body) != length:
                self.fail(400)
                return
            excluded = HOP | {v.strip().lower() for v in self.headers.get('Connection', '').split(',')}
            headers = {k: v for k, v in self.headers.items()
                       if k.lower() not in excluded and not k.lower().startswith('x-forwarded-')
                       and k.lower() not in {'forwarded', 'host', 'content-length'}}
            headers.update({'Host': 'localhost:8443', 'Connection': 'close'})
            # Origin, Referer, Cookie and CSRF headers are preserved, never invented.
            connection.request(self.command, self.path, body=body, headers=headers)
            response = connection.getresponse()
            payload = response.read()
            self.send_response(response.status)
            excluded = HOP | {'content-length', 'server', 'date'}
            excluded |= {v.strip().lower() for v in (response.getheader('Connection') or '').split(',')}
            for key, value in response.getheaders():
                if key.lower() not in excluded:
                    self.send_header(key, value)  # preserves each Set-Cookie independently
            self.send_header('Content-Length', str(len(payload)) if self.command != 'HEAD'
                             else (response.getheader('Content-Length') or '0'))
            self.send_header('Connection', 'close')
            self.end_headers()
            if self.command != 'HEAD':
                self.wfile.write(payload)
            self.close_connection = True
        except (OSError, http.client.HTTPException):
            self.fail(502)
        finally:
            connection.close()

    def fail(self, status):
        self.close_connection = True
        self.send_response(status)
        self.send_header('Content-Length', '0')
        self.send_header('Cache-Control', 'no-store')
        self.send_header('Connection', 'close')
        self.end_headers()

    do_GET = do_HEAD = do_POST = do_PUT = do_PATCH = do_DELETE = do_OPTIONS = do_request


def make_server(cert, key, port=8443, backend_port=8091, views_port=8092):
    server = http.server.ThreadingHTTPServer(('127.0.0.1', port), Proxy)
    server.daemon_threads = True
    server.backend_port = backend_port
    server.views_port = views_port
    context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    context.minimum_version = ssl.TLSVersion.TLSv1_2
    context.load_cert_chain(cert, key)
    server.socket = context.wrap_socket(server.socket, server_side=True)
    return server


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--cert', required=True)
    parser.add_argument('--key', required=True)
    args = parser.parse_args()
    with make_server(args.cert, args.key) as server:
        server.serve_forever()
