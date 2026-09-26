"""Real C21 multi-worker regression; existing synthetic Director only, no bearer logs."""
import concurrent.futures as cf
import hashlib, json, os, subprocess, time, urllib.request, urllib.error
from pathlib import Path
import pymysql

DB='mxmed_director_review_lon07c';BASE='http://127.0.0.1:18143/api/clinical/index.php/'
OUT=Path(os.environ['DOCSEC01R1_ARTIFACTS']);OUT.mkdir(parents=True,exist_ok=True)
runtime=subprocess.check_output(['ps','eww','-p','4024'],text=True)
assert 'MXMED_DB_NAME='+DB in runtime and 'MXMED_DB_HOST=localhost' in runtime
conn=pymysql.connect(unix_socket='/tmp/mysql.sock',user='root',database=DB,autocommit=True)
TOKENS=[];REPORT={}
def sql(q,args=()):
    with conn.cursor() as c:c.execute(q,args);return c.fetchall()
def req(path,body=None,auth=True):
    headers={'Content-Type':'application/json'}
    if auth:headers['Cookie']='PHPSESSID=director-lon07c-review'
    r=urllib.request.Request(BASE+path,data=json.dumps(body).encode() if body is not None else None,headers=headers)
    try:res=urllib.request.urlopen(r,timeout=25)
    except urllib.error.HTTPError as e:res=e
    return res.status,json.loads(res.read())
def upload(t):
    body=b'--r1\r\nContent-Disposition: form-data; name="file"; filename="r1.pdf"\r\nContent-Type: application/pdf\r\n\r\n%PDF-1.4\n% synthetic\n%%EOF\n\r\n--r1--\r\n'
    r=urllib.request.Request(BASE+'note-capture-tokens/'+t+'/upload',data=body,headers={'Content-Type':'multipart/form-data; boundary=r1'})
    try:res=urllib.request.urlopen(r,timeout=25)
    except urllib.error.HTTPError as e:res=e
    return res.status,json.loads(res.read())
def op(t,name):
    if name=='upload':return upload(t)
    if name=='signature':return req('note-capture-tokens/'+t+'/signature',{'signature_data':'data:image/png;base64,c3ludGhldGlj'},False)
    return req('note-capture-tokens/'+t+'/cancel',{})
try:
    for name,ops in [('canonical four uploads',['upload']*4),('canonical upload vs cancel',['upload','cancel']),('canonical upload vs signature',['upload','signature']),('signature vs cancel',['signature','cancel'])]:
        status,body=req('note-capture-tokens',{'patient_id':'p_plan02_review','encounter_key':'enc:1015','note_context':'consentimiento_firma_remota:patient' if 'signature' in ops else 'nota_clinica_modal'})
        assert status==201;t=body['data']['token'];TOKENS.append(t)
        identity='row:'+str(sql('SELECT id FROM clinical_note_capture_tokens WHERE token=%s',(t,))[0][0])
        key='capture:'+hashlib.sha256((DB+'|'+identity).encode()).hexdigest()[:56]
        before=sql('SELECT COUNT(*) FROM clinical_documents')[0][0]
        assert sql('SELECT GET_LOCK(%s,5)',(key,))[0][0]==1
        with cf.ThreadPoolExecutor(len(ops)) as pool:
            fs=[pool.submit(op,t,o) for o in ops]
            try:
                end=time.monotonic()+8
                while time.monotonic()<end:
                    ids=sql("SELECT ID FROM information_schema.PROCESSLIST WHERE DB=%s AND INFO LIKE 'SELECT GET_LOCK%%'",(DB,))
                    if len(ids)>=len(ops):break
                    time.sleep(.01)
                assert len(ids)>=len(ops),'missing independent worker barrier'
            finally:sql('SELECT RELEASE_LOCK(%s)',(key,))
            statuses=[f.result()[0] for f in fs]
        row=sql('SELECT status,document_id,signature_image_data IS NOT NULL FROM clinical_note_capture_tokens WHERE token=%s',(t,))[0]
        delta=sql('SELECT COUNT(*) FROM clinical_documents')[0][0]-before
        assert sum(x in (200,201) for x in statuses)==1 and not(row[1] and row[2])
        assert delta==int(bool(row[1]))
        if name=='canonical four uploads':assert delta==1
        REPORT[name]={'connection_ids':[x[0] for x in ids],'http':statuses,'state':row[0],'documents':delta,'winner':ops[next(i for i,s in enumerate(statuses) if s in (200,201))]}
        print('PASS '+name,flush=True)
finally:
    for t in TOKENS:req('note-capture-tokens/'+t+'/cancel',{})
    log=Path('/Users/circulodigital/.codex/artifacts/director-expediente-review/launch-error.log')
    text=log.read_text()
    for t in TOKENS:text=text.replace(t,'[DOCSEC01R1_REDACTED]'.ljust(len(t),'_'))
    log.write_text(text);conn.close()
    (OUT/'director-races.json').write_text(json.dumps(REPORT,indent=2)+'\n')
