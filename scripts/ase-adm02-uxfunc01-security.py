#!/usr/bin/env python3
"""Additional removal checks against the exact owned, running UXFUNC disposable instance."""
import pathlib,json,os,re,time,subprocess,concurrent.futures,hashlib,sys
import pymysql
root=pathlib.Path(__file__).resolve().parents[1]
owned=pathlib.Path(sys.argv[1]).resolve()
assert re.fullmatch(r'/private/tmp/mxmed-ase-adm02imp01-[A-Za-z0-9_]+',str(owned))
assert owned.stat().st_uid==os.getuid() and owned.stat().st_mode&0o777==0o700
approval=json.loads((owned/'workflow-approval.json').read_text())
assert pathlib.Path(approval['root']).resolve()==root and approval['expires']>time.time()
assert all(hashlib.sha256((root/f).read_bytes()).hexdigest()==h for f,h in approval['source_hashes'].items())
env=json.loads((owned/'review-env.json').read_text())
assert env['MXMED_DB_NAME']==approval['database']
p=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='root',database=approval['database'],autocommit=True)
def sql(q,args=()):
    with p.cursor() as c:c.execute(q,args);return c.fetchall()
assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==owned/'mysql'
php=r'''
require getcwd().'/modules/identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
$i=\Identity\Http\IdentityHttpComposition::fromProcessEnvironment();
$r=new \Identity\Http\CanonicalHttpSessionResolver($i->sessions());
$t=json_decode(file_get_contents(getenv('ASE_INT02A_OWNED').'/sessions.json'),true);
$b=json_decode(stream_get_contents(STDIN),true);
try {
 if($b['operation']==='consumer'){
  $a=$r->resolve(['__Host-mxmed_session'=>$t['target']]);
  $g=(new \Identity\Repositories\InternalOperatorGrantRepository($i->pdo()))->activeCapabilities('qa_target');
  echo json_encode(['account_session_valid'=>$a!==null,'staff_permission'=>$g->contains('media_review_read')]);
 }else{
  $s=new \Identity\Services\InternalGovernanceService($i->pdo(),$r);
  $v=$s->execute(['__Host-mxmed_session'=>$t['director']],$b['operation'],$b['target'],$b['capability']??null,$b['operation']==='remove_advisor'?\Identity\Services\InternalGovernanceService::ADVISOR_REMOVAL:null);
  echo json_encode(['ok'=>true,'status'=>$v['status']]);
 }
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
'''
def call(operation,target='qa_target',capability=None):
    v=subprocess.run(['php','-r',php],cwd=root,env=env,input=json.dumps(dict(operation=operation,target=target,capability=capability)),text=True,capture_output=True)
    assert v.returncode==0,'fixture failed';return json.loads(v.stdout)
checks=[]
def check(name,value):
    assert value,name;checks.append({'name':name,'pass':True});print('PASS',name)
for op,cap in [('reactivate',None),('grant','media_review_read'),('register',None),('appoint_master',None)]:
    check('Canonical direct writer rejects '+op+' for removed staff',call(op,capability=cap).get('error')=='governance_target_ineligible')
check('Identity session survives; canonical staff consumer denies permission',call('consumer')=={'account_session_valid':True,'staff_permission':False})
try:
    sql("UPDATE internal_staff SET status='ACTIVE' WHERE account_id='qa_target'");raise AssertionError('terminal trigger missing')
except pymysql.MySQLError as e:check('Terminal trigger blocks status restoration',e.args[0]==1644)
with concurrent.futures.ThreadPoolExecutor(2) as pool:results=list(pool.map(lambda _:call('remove_advisor','qa_advisor'),range(2)))
check('Two independent writer connections: one removal only',sum(x.get('ok') is True for x in results)==1 and sum(x.get('error')=='governance_target_ineligible' for x in results)==1)
check('Concurrent removal has one audit event',sql("SELECT COUNT(*) FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operation'))='remove_advisor' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.target_account_id'))='qa_advisor'")[0][0]==1)
p.close()
out=root/'docs/evidence/ase-adm02-uxfunc01/security.json';out.write_text(json.dumps({'result':'PASS','checks':checks,'independent_connections':True,'real_data_used':False},indent=2)+'\n')
