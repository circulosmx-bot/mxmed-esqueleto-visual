#!/usr/bin/env python3
"""Owned disposable MySQL/Valkey/PHP/TLS; never connects to an existing database."""
import argparse,datetime as dt,http.client,http.server,json,os,pathlib,secrets,shutil,signal,socket,ssl,subprocess,sys,tempfile,threading,time
ROOT=pathlib.Path(__file__).resolve().parents[1]
POINTER=pathlib.Path('/tmp/mxmed-ase-int01-owned.json')

def free_port():
    with socket.socket() as s:s.bind(('127.0.0.1',0));return s.getsockname()[1]

def start():
    assert subprocess.check_output(['git','branch','--show-current'],cwd=ROOT,text=True).strip()=='feature/ase-int01-internal-team-readonly'
    if POINTER.exists():raise RuntimeError('Owned preview already registered; inspect or stop that preview first')
    os.umask(0o077);owned=pathlib.Path(tempfile.mkdtemp(prefix='mxmed-ase-int01-',dir='/tmp'));data=owned/'mysql';data.mkdir()
    mysql_port,valkey_port,php_port=free_port(),free_port(),free_port()
    with socket.socket() as s:s.bind(('127.0.0.1',8141))
    mysqld=shutil.which('mysqld');mysql=shutil.which('mysql');valkey=shutil.which('valkey-server') or str(pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-runtime/valkey-server')
    assert mysqld and mysql and pathlib.Path(valkey).is_file()
    env={k:v for k,v in os.environ.items() if k in ['PATH','HOME','USER','TMPDIR','LANG','LC_ALL','TZ']};env.update(APP_ENV='local',MXMED_ENVIRONMENT='local',MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT='0',MXMED_PREVIEW_EXPLICIT='1',MXMED_PREVIEW_ORIGIN='https://127.0.0.1:8141',MXMED_PREVIEW_PEPPER=secrets.token_hex(32),MXMED_DB_HOST='127.0.0.1',MXMED_DB_PORT=str(mysql_port),MXMED_DB_NAME='mxmed_gate4d_preview_aseint01_'+secrets.token_hex(5),MXMED_DB_USER='ase_reader',MXMED_DB_PASS=secrets.token_urlsafe(32),MXMED_SESSION_STORE_PORT=str(valkey_port),MXMED_PREVIEW_NOTIFICATION_FILE=str(owned/'unused-notifications.json'))
    configuration={'owned':str(owned),'root':str(ROOT),'env':env,'socket':str(owned/'mysql.sock'),'pids':{},'expires':(dt.datetime.now(dt.timezone.utc)+dt.timedelta(hours=8)).isoformat()}
    path=owned/'runtime.json';path.write_text(json.dumps(configuration));POINTER.write_text(json.dumps({'configuration':str(path)}))
    def spawn(name,args):
        with open(owned/(name+'.log'),'ab') as log:p=subprocess.Popen(args,cwd=ROOT,env=env,stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
        configuration['pids'][name]=p.pid;path.write_text(json.dumps(configuration));return p
    try:
        with open(owned/'initialize.log','wb') as log:subprocess.run([mysqld,'--no-defaults','--initialize-insecure','--datadir='+str(data)],stdout=log,stderr=log,check=True)
        spawn('mysql',[mysqld,'--no-defaults','--datadir='+str(data),'--socket='+configuration['socket'],'--port='+str(mysql_port),'--bind-address=127.0.0.1','--skip-name-resolve','--mysqlx=0','--skip-log-bin','--pid-file='+str(owned/'mysql.pid')])
        for _ in range(100):
            if (owned/'mysql.sock').exists():
                check=subprocess.run([mysql,'--no-defaults','--protocol=SOCKET','--socket='+configuration['socket'],'-uroot','-e','SELECT 1'],capture_output=True)
                if check.returncode==0:break
            time.sleep(.2)
        else:raise RuntimeError('Owned MySQL did not start')
        spawn('valkey',[valkey,'--bind','127.0.0.1','--port',str(valkey_port),'--protected-mode','yes','--save','','--appendonly','no','--dir',str(owned)])
        for _ in range(50):
            try:
                with socket.create_connection(('127.0.0.1',valkey_port),timeout=.1):break
            except OSError:time.sleep(.1)
        env['ASE_INT01_CONFIGURATION']=str(path);configuration['env']=env;path.write_text(json.dumps(configuration))
        result=subprocess.run(['php','modules/identity/tests/TeamReadFixture.php','setup'],cwd=ROOT,env=env,capture_output=True,text=True)
        if result.returncode:
            (owned/'fixture-error.log').write_text(result.stderr);raise RuntimeError('Owned fixture failed; private log retained')
        (owned/'sessions.json').write_text(result.stdout)
        subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(owned/'key.pem'),'-out',str(owned/'cert.pem'),'-days','1','-subj','/CN=127.0.0.1','-addext','subjectAltName=IP:127.0.0.1'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,check=True)
        spawn('php',['php','-S','127.0.0.1:'+str(php_port),'-t',str(ROOT),str(ROOT/'scripts/ase-int01-router.php')])
        configuration['backend_port']=php_port;path.write_text(json.dumps(configuration))
        spawn('tls',[sys.executable,str(__file__),'serve',str(path)])
        print(json.dumps({'url':'https://127.0.0.1:8141/internal/media-review/index.php?section=equipo','owned_configuration':str(path),'expires':configuration['expires'],'isolation':'Fresh MySQL instance and private Valkey; no clone or access to existing schemas'}))
    except BaseException:
        stop();raise

def stop():
    if not POINTER.exists():return
    path=pathlib.Path(json.loads(POINTER.read_text())['configuration']);cfg=json.loads(path.read_text());owned=pathlib.Path(cfg['owned'])
    assert owned.parent==pathlib.Path('/tmp') and owned.name.startswith('mxmed-ase-int01-') and path.parent==owned
    for name,pid in reversed(list(cfg['pids'].items())):
        try:
            cmd=subprocess.check_output(['ps','-ww','-p',str(pid),'-o','command='],text=True)
            if not cmd.strip():continue
            assert str(owned) in cmd or (name=='php' and cfg['root'] in cmd) or (name=='valkey' and 'valkey-server' in cmd and cmd.strip().endswith('127.0.0.1:'+cfg['env']['MXMED_SESSION_STORE_PORT']))
            os.kill(pid,signal.SIGTERM)
        except subprocess.CalledProcessError:pass
    deadline=time.monotonic()+20
    for pid in cfg['pids'].values():
        while time.monotonic()<deadline:
            state=subprocess.run(['ps','-p',str(pid),'-o','stat='],capture_output=True,text=True).stdout.strip()
            if not state or state.startswith('Z'):break
            time.sleep(.1)
        else:raise RuntimeError('Owned process still running; private directory retained')
    shutil.rmtree(owned);POINTER.unlink()
    print(json.dumps({'stopped_owned_processes':True,'owned_directory_deleted':not owned.exists(),'original_processes_touched':False}))

def serve(path):
    cfg=json.loads(pathlib.Path(path).read_text());expires=dt.datetime.fromisoformat(cfg['expires'])
    class Handler(http.server.BaseHTTPRequestHandler):
        def log_message(self,*_):pass
        def run(self):
            if self.headers.get_all('Host')!=['127.0.0.1:8141']:self.send_error(421);return
            if dt.datetime.now(dt.timezone.utc)>=expires:self.send_error(410);return
            if self.headers.get('Transfer-Encoding') or len(self.headers.get_all('Content-Length') or [])>1:self.send_error(400);return
            try:length=int(self.headers.get('Content-Length','0'))
            except ValueError:self.send_error(400);return
            if length<0 or length>4096:self.send_error(413);return
            headers={k:v for k,v in self.headers.items() if k.lower() not in ['host','connection','content-length'] and not k.lower().startswith('x-forwarded-')};headers.update(Host='127.0.0.1:8141',Connection='close')
            connection=http.client.HTTPConnection('127.0.0.1',cfg['backend_port'],timeout=15)
            try:
                connection.request(self.command,self.path,body=self.rfile.read(length) if length else None,headers=headers);response=connection.getresponse();body=response.read();self.send_response(response.status)
                for key,value in response.getheaders():
                    if key.lower() not in ['connection','transfer-encoding','content-length','server','date']:self.send_header(key,value)
                self.send_header('Content-Length',str(len(body)));self.send_header('X-Robots-Tag','noindex, nofollow');self.end_headers()
                if self.command!='HEAD':self.wfile.write(body)
            finally:connection.close()
        do_GET=do_HEAD=do_POST=do_PUT=do_PATCH=do_DELETE=run
    server=http.server.ThreadingHTTPServer(('127.0.0.1',8141),Handler);context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.minimum_version=ssl.TLSVersion.TLSv1_2;context.load_cert_chain(pathlib.Path(cfg['owned'])/'cert.pem',pathlib.Path(cfg['owned'])/'key.pem');server.socket=context.wrap_socket(server.socket,server_side=True);server.serve_forever()

def review():
    # Fresh, temporary browser context: only an owned synthetic canonical Director.
    from playwright.sync_api import sync_playwright
    cfg=json.loads(pathlib.Path(json.loads(POINTER.read_text())['configuration']).read_text())
    if dt.datetime.now(dt.timezone.utc)>=dt.datetime.fromisoformat(cfg['expires']):raise RuntimeError('Preview expired; stop and start a fresh owned preview')
    token=json.loads(subprocess.check_output(['php','modules/identity/tests/TeamReadFixture.php','review_token'],cwd=ROOT,env=cfg['env'],text=True))['tokens']['qa_director']
    with sync_playwright() as p:
        browser=p.chromium.launch(headless=False);context=browser.new_context(ignore_https_errors=True,viewport={'width':1440,'height':900})
        context.add_cookies([{'name':'__Host-mxmed_session','value':token,'domain':'127.0.0.1','path':'/','secure':True,'httpOnly':True,'sameSite':'Strict'}])
        page=context.new_page();page.goto('https://127.0.0.1:8141/internal/media-review/index.php?section=equipo')
        print('Isolated synthetic review only. Close the browser window to finish; original Safari sessions are untouched.',flush=True)
        try:page.wait_for_event('close',timeout=0)
        finally:browser.close()

if __name__=='__main__':
    action=sys.argv[1] if len(sys.argv)>1 else ''
    if action=='start':start()
    elif action=='stop':stop()
    elif action=='review':review()
    elif action=='serve' and len(sys.argv)==3:serve(sys.argv[2])
    else:raise SystemExit('Use start, review or stop; configuration remains outside Git.')
