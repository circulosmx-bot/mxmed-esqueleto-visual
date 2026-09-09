#!/usr/bin/env python3
"""R2 authority QA. Only a newly created disposable MySQL container is accessed."""
import hashlib,json,pathlib,re,subprocess,time,uuid,os
ROOT=pathlib.Path(__file__).resolve().parents[2]
BASE=ROOT/'modules/platform/db/routines'
def run(args, **kw):
    p=subprocess.run(args,text=True,capture_output=True,**kw)
    if p.returncode: raise RuntimeError(p.stderr or p.stdout)
    return p.stdout
m=json.loads((BASE/'AuditMp01CR2RoutineManifest.json').read_text())
assert m['security']=='DEFINER' and len(m['routines'])==2
assert len(set(m['privileges']['required_rows']))==17
for item in m['routines']+[m['privileges']]:
    raw=(BASE/item['path']).read_bytes()
    assert hashlib.sha256(raw).hexdigest()==item['sha256']
    if item in m['routines']:
        source=raw.decode()
        assert item['signature'] in source
        assert 'SQL SECURITY DEFINER' in source and 'SQL SECURITY INVOKER' not in source
        assert 'CREATE DEFINER = {{RESTRICTED_DEFINER}}' in source
        assert not re.search(r'\b(COMMIT|ROLLBACK|START TRANSACTION)\b',source,re.I)
name='mxmed-r2-qa-'+uuid.uuid4().hex[:12]
try:
    run(['docker','run','--rm','-d','--name',name,'-p','127.0.0.1::3306','-e','MYSQL_ALLOW_EMPTY_PASSWORD=yes','mysql:8.4','--log-bin=binlog','--binlog-format=ROW','--log-bin-trust-function-creators=1'])
    for _ in range(90):
        p=subprocess.run(['docker','exec',name,'mysqladmin','--protocol=TCP','-h127.0.0.1','ping'],capture_output=True)
        if p.returncode==0:break
        time.sleep(1)
    else:raise RuntimeError('startup timeout')
    def sql(s):return run(['docker','exec','-i',name,'mysql','--batch','-uroot'],input=s)
    sql("CREATE DATABASE mxmed CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER 'r2definer'@'%'; CREATE USER 'r2writer'@'%';")
    source=(ROOT/'modules/platform/db/migrations/2026_07_20_01_create_platform_audit_events.sql').read_text()
    sql('USE mxmed;\n'+source)
    sql("USE mxmed; CREATE TABLE platform_audit_stream_heads(stream_key VARCHAR(191) PRIMARY KEY,last_sequence_number BIGINT NOT NULL,last_event_hash CHAR(64) NOT NULL,hash_version VARCHAR(32) NULL,updated_at DATETIME(6) NULL) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
    m=json.loads((BASE/'AuditMp01CR2RoutineManifest.json').read_text())
    for item in m['routines']+[m['privileges']]:
        raw=(BASE/item['path']).read_bytes();assert hashlib.sha256(raw).hexdigest()==item['sha256']
        sql(raw.decode().replace('{{RESTRICTED_DEFINER}}',"'r2definer'@'%'").replace('{{WRITER}}',"'r2writer'@'%'") )
    port=run(['docker','port',name,'3306']).strip().split(':')[-1]
    env=dict(os.environ,MXMED_R2_QA_PORT=port)
    print(run(['php',str(ROOT/'modules/platform/tests/AuditMp01CR2LocalTest.php')],env=env),flush=True)
finally:
    subprocess.run(['docker','stop',name],capture_output=True)
