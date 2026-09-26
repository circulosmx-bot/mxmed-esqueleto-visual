"""Opt-in R1 real HTTP/DB concurrency on an isolated copy of the synthetic Director.
No production guard bypass: this independent deployment has the normal non-cohort
legacy policy; Director's cohort and guard are untouched. No bearer logs/artifacts.
"""
import concurrent.futures as cf
import hashlib, io, json, os, re, shutil, signal, socket, subprocess, tarfile, tempfile, time, threading
import urllib.request, urllib.error
from pathlib import Path
import pymysql

ROOT=Path(__file__).resolve().parents[3]
OUT=Path(os.environ['DOCSEC01R1_ARTIFACTS']); OUT.mkdir(parents=True,exist_ok=True)
SOURCE_DB='mxmed_director_review_lon07c'
DB='docsec01r1_qa_'+str(os.getpid())
assert re.fullmatch(r'docsec01r1_qa_\d+',DB)
REPORT={}; TOKENS=[]; APIS=[]; LOCAL=threading.local()
conn=pymysql.connect(unix_socket='/tmp/mysql.sock',user='root',autocommit=True)
def sql(q,args=()):
    with conn.cursor() as c: c.execute(q,args); return c.fetchall()
def count(): return sql('SELECT COUNT(*) FROM clinical_documents')[0][0]
def state(t): return sql('SELECT status,document_id,signature_image_data IS NOT NULL FROM clinical_note_capture_tokens WHERE token=%s',(t,))[0]
def request(path='',action='GET',body=None,auth=True):
    headers={'Accept':'application/json'}
    if auth: headers['Cookie']='PHPSESSID=docsec01r1-owner'
    data=None
    if body is not None: data=json.dumps(body).encode();headers['Content-Type']='application/json'
    req=urllib.request.Request(getattr(LOCAL,'api',API)+path,data=data,headers=headers,method=action)
    try:r=urllib.request.urlopen(req,timeout=25)
    except urllib.error.HTTPError as e:r=e
    return r.status,json.loads(r.read())
def issue(signature=False,encounter=None):
    s,b=request('note-capture-tokens','POST',dict(patient_id='p_plan02_review',encounter_key=encounter,note_context='consentimiento_firma_remota:patient' if signature else 'nota_clinica_modal'))
    assert s==201, 'isolated issuance failed'
    t=b['data']['token'];TOKENS.append(t);return t
def upload(t):
    boundary='docsec01r1'
    body=(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="synthetic.pdf"\r\nContent-Type: application/pdf\r\n\r\n%PDF-1.4\n% synthetic R1 capture only\n%%EOF\n\r\n--{boundary}--\r\n').encode()
    req=urllib.request.Request(getattr(LOCAL,'api',API)+'note-capture-tokens/'+t+'/upload',data=body,headers={'Content-Type':'multipart/form-data; boundary='+boundary},method='POST')
    try:r=urllib.request.urlopen(req,timeout=25)
    except urllib.error.HTTPError as e:r=e
    return r.status,json.loads(r.read())
def operation(t,op):
    if op=='upload':return upload(t)
    if op=='signature':return request('note-capture-tokens/'+t+'/signature','POST',{'signature_data':'data:image/png;base64,c3ludGhldGlj'},False)
    return request('note-capture-tokens/'+t+'/cancel','POST',{})
def on_worker(index,func,*args):
    LOCAL.api=APIS[index]
    try:return func(*args)
    finally:del LOCAL.api
def lockname(t):
    identity='row:'+str(sql('SELECT id FROM clinical_note_capture_tokens WHERE token=%s',(t,))[0][0])
    return 'capture:'+hashlib.sha256((DB+'|'+identity).encode()).hexdigest()[:56]
def waiters(n,pattern='SELECT GET_LOCK%'):
    end=time.monotonic()+8
    while time.monotonic()<end:
        rows=sql('SELECT ID FROM information_schema.PROCESSLIST WHERE DB=%s AND INFO LIKE %s',(DB,pattern))
        if len(rows)>=n:return [x[0] for x in rows]
        time.sleep(.01)
    raise AssertionError('independent workers did not reach DB barrier')
def race(name,ops,signature=False,encounter=None,aliases=False):
    t=issue(signature,encounter);before=count();key=lockname(t)
    assert sql('SELECT GET_LOCK(%s,5)',(key,))[0][0]==1
    with cf.ThreadPoolExecutor(len(ops)) as pool:
        futures=[pool.submit(on_worker,i,operation,t.upper() if aliases and i else t,op) for i,op in enumerate(ops)]
        try: ids=waiters(len(ops))
        finally:sql('SELECT RELEASE_LOCK(%s)',(key,))
        results=[f.result() for f in futures]
    statuses=[r[0] for r in results];current=state(t);delta=count()-before
    assert sum(s in (200,201) for s in statuses)==1,(name,statuses)
    assert delta==(1 if current[1] else 0) and not(current[1] and current[2]),name
    if current[0]=='cancelled':assert delta==0 and not current[2]
    REPORT[name]={'connection_ids':ids,'http':statuses,'state':current[0],'documents':delta,'signature':bool(current[2]),'winner':ops[next(i for i,s in enumerate(statuses) if s in (200,201))]}
    print('PASS '+name,flush=True);return t

servers=[];tmp=Path(tempfile.mkdtemp(prefix='docsec01r1-'))
try:
    # Clone only the explicitly synthetic review database; stream SQL, never save tokens.
    assert sql('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=%s',(SOURCE_DB,))
    sql('CREATE DATABASE `'+DB+'`');conn.select_db(DB)
    dump=subprocess.Popen(['mysqldump','-u','root','-h','localhost','--single-transaction','--skip-lock-tables','--set-gtid-purged=OFF',SOURCE_DB],stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    load=subprocess.run(['mysql','-u','root','-h','localhost',DB],stdin=dump.stdout,capture_output=True);dump.stdout.close();dump.wait()
    assert load.returncode==dump.returncode==0,'disposable clone failed'
    sql('DELETE FROM clinical_note_capture_tokens')
    archive=subprocess.check_output(['git','archive','HEAD'],cwd=ROOT)
    with tarfile.open(fileobj=io.BytesIO(archive)) as tar:
        assert all(not Path(m.name).is_absolute() and '..' not in Path(m.name).parts for m in tar.getmembers())
        tar.extractall(tmp)
    # Overlay the actual combined uncommitted patch, without touching its source.
    paths=subprocess.check_output(['git','ls-files','--modified','--others','--exclude-standard'],cwd=ROOT,text=True).splitlines()
    for rel in paths:
        target=tmp/rel;target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(ROOT/rel,target)
    assert not (tmp/'api/mxmed-db.config.php').exists()
    sessions=tmp/'qa-sessions';sessions.mkdir()
    subprocess.run(['php','-r', 'session_save_path($argv[1]); session_id("docsec01r1-owner"); session_start(); $_SESSION=["doctor_id"=>"1","user_id"=>"docsec01r1"];session_write_close();',str(sessions)],check=True,capture_output=True)
    env={k:v for k,v in os.environ.items() if not k.startswith('MXMED_') and k!='PHP_CLI_SERVER_WORKERS'}
    env.update(MXMED_DB_HOST='localhost',MXMED_DB_NAME=DB,MXMED_DB_USER='root')
    # Dedicated ports guarantee independent workers; prefork accept scheduling can
    # otherwise queue both test requests at one blocked worker and fake a timeout.
    for _ in range(4):
        with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
        APIS.append(f'http://127.0.0.1:{port}/api/clinical/index.php/')
        server=subprocess.Popen(['php','-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','-t',str(tmp)],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,start_new_session=True)
        servers.append(server)
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1',port),timeout=.1):break
            except OSError:time.sleep(.03)
    API=APIS[0];REPORT['independent_http_worker_pids']=[p.pid for p in servers]
    t=race('patient upload vs upload',['upload','upload'])
    assert REPORT['patient upload vs upload']['documents']==1
    before=count();assert upload(t)[0]==409 and count()==before;REPORT['second independent upload']='PASS'
    race('guarded legacy encounter upload vs upload',['upload','upload'],encounter='enc:1015')
    race('upload vs cancel',['upload','cancel'])
    race('collation alias upload vs cancel',['upload','cancel'],aliases=True)
    race('upload vs signature',['upload','signature'],True)
    race('signature vs cancel',['signature','cancel'],True)
    for kind,code in [('cancelled',409),('expired',410),('unknown',404)]:
        t='unknown-r1' if kind=='unknown' else issue()
        if kind=='cancelled':assert operation(t,'cancel')[0]==200
        if kind=='expired':sql('UPDATE clinical_note_capture_tokens SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE token=%s',(t,))
        before=count();assert upload(t)[0]==code and count()==before;REPORT[kind+' upload']='PASS'
    # Force upload to own authority first, then queue BOTH competing terminal actions.
    t=issue(True);before=count();sql('LOCK TABLES clinical_documents WRITE')
    with cf.ThreadPoolExecutor(3) as pool:
        first=pool.submit(on_worker,0,upload,t)
        try:
            writer_ids=waiters(1,'INSERT INTO clinical_documents%')
            rivals=[pool.submit(on_worker,i+1,operation,t,op) for i,op in enumerate(['cancel','signature'])]
            rival_ids=waiters(2)
        finally:sql('UNLOCK TABLES')
        statuses=[first.result()[0]]+[f.result()[0] for f in rivals]
    assert statuses==[201,409,409] and count()==before+1 and state(t)[0]=='uploaded' and not state(t)[2]
    REPORT['claimed upload vs cancel and signature']={'connection_ids':writer_ids+rival_ids,'http':statuses,'documents':1,'winner':'upload'}
    # Expiry before authority: both workers demonstrably wait at the named lock.
    t=issue(True);key=lockname(t);sql('SELECT GET_LOCK(%s,5)',(key,));before=count()
    with cf.ThreadPoolExecutor(2) as pool:
        fs=[pool.submit(on_worker,i,operation,t,op) for i,op in enumerate(['upload','signature'])]
        try:
            ids=waiters(2);sql('UPDATE clinical_note_capture_tokens SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE token=%s',(t,))
        finally:sql('SELECT RELEASE_LOCK(%s)',(key,))
        assert [f.result()[0] for f in fs]==[410,410]
    assert state(t)[0]=='expired' and count()==before
    REPORT['expiry before claim']={'connection_ids':ids,'result':'PASS'}
    # Hold document INSERT after the valid token claim until wall-clock expiry.
    t=issue();sql('UPDATE clinical_note_capture_tokens SET expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 SECOND) WHERE token=%s',(t,))
    sql('LOCK TABLES clinical_documents WRITE')
    with cf.ThreadPoolExecutor(1) as pool:
        f=pool.submit(on_worker,0,upload,t)
        try:
            ids=waiters(1,'INSERT INTO clinical_documents%')
            time.sleep(2.1) # Only crossing expiry; concurrency proof is the DB barrier above.
        finally:sql('UNLOCK TABLES')
        assert f.result()[0]==201
    assert state(t)[0]=='uploaded';REPORT['expiry after claim']={'connection_ids':ids,'result':'PASS'}
    t=issue(True)
    status,_=request('note-capture-tokens/'+t+'/signature','POST',{'signature_data':'data:image/png;base64,c3ludGhldGlj','signer_name':'x'*300},False)
    assert status==500 and state(t)==('pending',None,0)
    assert operation(t,'signature')[0]==200
    REPORT['signature SQL failure then retry']='PASS'
    # Fault injection uses actual transaction helper on this isolated DB only.
    fault=subprocess.run(['php',str(ROOT/'modules/clinical/qa/docsec01r1_failure_test.php'),DB,str(tmp/'qa-private')],capture_output=True,text=True)
    assert fault.returncode==0,'isolated rollback/ambiguous commit test failed: '+fault.stdout+fault.stderr
    REPORT['failure_and_ambiguous_commit']=json.loads(fault.stdout)
finally:
    for server in servers:
        os.killpg(server.pid,signal.SIGTERM);server.wait(timeout=5)
    conn.select_db('information_schema');sql('DROP DATABASE IF EXISTS `'+DB+'`');conn.close()
    shutil.rmtree(tmp)
    (OUT/'isolated-races.json').write_text(json.dumps(REPORT,indent=2)+'\n')
print('PASS isolated patient-level concurrency and terminal matrix',flush=True)
