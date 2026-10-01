#!/usr/bin/env python3
"""Disposable canonical session read stub for provider UI role QA."""
import json
import socketserver
import sys

primary=json.load(open(sys.argv[1],encoding='utf-8'))
secondary=json.load(open(sys.argv[2],encoding='utf-8'))
records=[primary,*secondary.values()]
values={record['session_key']:record['session_json'] for record in records}

class Handler(socketserver.StreamRequestHandler):
    def handle(self):
        try:
            header=self.rfile.readline()
            if not header.startswith(b'*'): return
            parts=[]
            for _ in range(int(header[1:])):
                length=int(self.rfile.readline()[1:])
                parts.append(self.rfile.read(length).decode('utf-8'))
                self.rfile.read(2)
            command=parts[0].upper()
            if command=='PING': self.wfile.write(b'+PONG\r\n')
            elif command=='GET':
                value=values.get(parts[1])
                if value is None: self.wfile.write(b'$-1\r\n')
                else:
                    data=value.encode('utf-8')
                    self.wfile.write(b'$'+str(len(data)).encode()+b'\r\n'+data+b'\r\n')
            else: self.wfile.write(b'-UNSUPPORTED\r\n')
        except (ValueError,IndexError,UnicodeDecodeError):
            self.wfile.write(b'-INVALID\r\n')

class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address=True

with Server(('127.0.0.1',6384),Handler) as server: server.serve_forever()
