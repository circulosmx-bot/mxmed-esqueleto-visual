#!/usr/bin/env python3
"""Reuses the existing normal TLS proxy, unchanged headers/canonical Identity cookie."""
import importlib.util,pathlib
p=pathlib.Path(__file__).with_name('normal-local-https-proxy.py');s=importlib.util.spec_from_file_location('normal_tls',p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
class Rehearsal(m.Proxy):
 def do_request(self):
  # Same fixed loopback proxy contract; no Origin/Cookie/CSRF rewriting.
  if self.headers.get_all('Host')!=['127.0.0.1:8448']:self.fail(421);return
  self.headers.replace_header('Host','localhost:8443')
  return super().do_request()
 do_GET=do_HEAD=do_POST=do_PUT=do_PATCH=do_DELETE=do_OPTIONS=do_request
# The inherited proxy sends the fixed normal Host. This fixture keeps it on the
# loopback backend; the canonical origin itself remains 127.0.0.1:8448.
if __name__=='__main__':
 m.http.server.ThreadingHTTPServer.request_queue_size=128
 tls=pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-tls';server=m.make_server(str(tls/'localhost.crt'),str(tls/'localhost.key'),8448,8097,8097);server.RequestHandlerClass=Rehearsal;server.serve_forever()
