#!/usr/bin/env python3
"""Single owned synthetic SQL connection: discard the acknowledged COMMIT reply.
The runner supplies disposable credentials; this helper never reads credentials or SQL.
"""
import json,pathlib,select,socket,sys
out=pathlib.Path(sys.argv[1]);assert out.name=='act03-commit-loss.json'
def packet(conn):
    def exact(n):
        data=b''
        while len(data)<n:
            chunk=conn.recv(n-len(data))
            if not chunk:raise EOFError()
            data+=chunk
        return data
    head=exact(4);return head+exact(int.from_bytes(head[:3],'little'))
with socket.socket() as listener:
    listener.bind(('127.0.0.1',0));listener.listen(1);listener.settimeout(20)
    print(listener.getsockname()[1],flush=True)
    with listener.accept()[0] as front,socket.create_connection(('127.0.0.1',3306),timeout=20) as back:
        commit=False
        while True:
            ready,_,_=select.select([front,back],[],[],20)
            if not ready:raise RuntimeError('owned proxy timeout')
            for source in ready:
                data=packet(source)
                if source is front:
                    commit=data[4:5]==b'\x03' and data[5:].upper().strip().rstrip(b';').strip().endswith(b'COMMIT')
                    back.sendall(data)
                else:
                    if commit:
                        assert data[4:5]==b'\x00','server did not acknowledge COMMIT'
                        out.write_text(json.dumps({'server_commit_acknowledged':True,'client_ack_discarded':True,'normal_runtime_touched':False}))
                        front.shutdown(socket.SHUT_RDWR)
                        sys.exit(0)
                    front.sendall(data)
