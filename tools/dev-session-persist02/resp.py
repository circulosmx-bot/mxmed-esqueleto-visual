import socket

def command(port,*args):
 data=b'*'+str(len(args)).encode()+b'\r\n'
 for x in args:
  b=str(x).encode();data+=b'$'+str(len(b)).encode()+b'\r\n'+b+b'\r\n'
 with socket.create_connection(('127.0.0.1',port),timeout=5) as s:
  s.sendall(data);f=s.makefile('rb')
  def read():
   line=f.readline();typ=line[:1];v=line[1:-2]
   if typ==b'-':raise RuntimeError('Owned Valkey command rejected')
   if typ==b'+':return v.decode()
   if typ==b':':return int(v)
   if typ==b'$':
    n=int(v)
    if n<0:return None
    out=f.read(n);f.read(2);return out.decode()
   if typ==b'*':return [read() for _ in range(int(v))]
   raise RuntimeError('Invalid owned Valkey response')
  return read()
