#!/usr/bin/env python3
"""Disposable local RESP subset for the canonical preview session reader only."""
import json
import socketserver
import sys

with open(sys.argv[1], encoding='utf-8') as handle:
    fixture = json.load(handle)
values = {fixture['session_key']: fixture['session_json']}

class Handler(socketserver.StreamRequestHandler):
    def handle(self):
        try:
            head = self.rfile.readline()
            if not head.startswith(b'*'):
                return
            parts = []
            for _ in range(int(head[1:])):
                length = int(self.rfile.readline()[1:])
                parts.append(self.rfile.read(length).decode('utf-8'))
                self.rfile.read(2)
            cmd = parts[0].upper()
            if cmd == 'PING':
                self.wfile.write(b'+PONG\r\n')
            elif cmd == 'GET':
                value = values.get(parts[1])
                if value is None:
                    self.wfile.write(b'$-1\r\n')
                else:
                    data = value.encode('utf-8')
                    self.wfile.write(b'$' + str(len(data)).encode() + b'\r\n' + data + b'\r\n')
            else:
                self.wfile.write(b'-UNSUPPORTED\r\n')
        except (ValueError, IndexError, UnicodeDecodeError):
            self.wfile.write(b'-INVALID\r\n')

class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True

with Server(('127.0.0.1', 6384), Handler) as server:
    server.serve_forever()
