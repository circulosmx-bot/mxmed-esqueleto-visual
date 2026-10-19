"""Targeted canonical setup and resumable checks; never targets an existing DB."""
import hashlib,importlib.util,json,os,pathlib,secrets,subprocess,time,traceback
def module(name,path):
 spec=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m

def setup(r,check):
 module('ws03_existing_setup',r['ROOT']/'scripts/ase-advisor-workspace02-checks.py').setup(r,check)
 r['env']['MXMED_ADVISOR_WORKSPACE03_REVIEW']='1'


def pin(root,owned):
 path=owned/'workflow-approval.json';approval=json.loads(path.read_text());assert approval['root']==str(root)
 assert all(hashlib.sha256((root/f).read_bytes()).hexdigest()==h for f,h in approval['source_hashes'].items())
 patterns=['modules/identity/PhysicianRequests/*.php','modules/identity/Accreditation/*.php',
           'modules/identity/services/InternalCapabilityCatalog.php','api/internal/workspace/*.php',
           'scripts/ase-advisor-workspace02-runtime.php','scripts/ase-advisor-workspace02-router.php']
 for pattern in patterns:
  for f in root.glob(pattern):approval['source_hashes'][str(f.relative_to(root))]=hashlib.sha256(f.read_bytes()).hexdigest()
 for f in root.glob('modules/platform/**/*.php'):approval['source_hashes'][str(f.relative_to(root))]=hashlib.sha256(f.read_bytes()).hexdigest()
 path.write_text(json.dumps(approval))

def run(r,check,races):
 root,owned,env=[r[k] for k in ['ROOT','owned','env']];r['OUT']=root/'docs/evidence/ase-advisor-workspace03'
 def cli(file,action='',body=None):
  result=subprocess.run(['php',file]+([action] if action else []),cwd=root,env=env,input=json.dumps(body or {}),text=True,capture_output=True)
  if result.returncode:
   (owned/'workspace02-cli-error.log').write_text(result.stderr);raise RuntimeError('owned_fixture_failed')
  try:value=json.loads(result.stdout)
  except Exception:
   (owned/'workspace02-cli-output.log').write_text(result.stdout);raise
  if not value.get('ok',True):raise RuntimeError('owned_fixture_rejected:'+str(value.get('error')))
  return value.get('data',value)
 cli('modules/identity/tests/StaffWorkflowFixture.php','enroll')
 media=cli('scripts/ase-advisor-workspace-fixture.php');(owned/'workspace-media.json').write_text(json.dumps(media))
 php="$im=imagecreatetruecolor(64,48);imagefilledrectangle($im,0,0,63,47,imagecolorallocate($im,40,140,180));ob_start();imagepng($im);$b=ob_get_clean();$out=substr($b,0,8);for($i=8;$i<strlen($b);){$n=unpack('N',substr($b,$i,4))[1];if(substr($b,$i+4,4)!=='pHYs')$out.=substr($b,$i,$n+12);$i+=$n+12;}echo $out;"
 (owned/'synthetic.png').write_bytes(subprocess.check_output(['php','-r',php]))
 (owned/'workspace-runtime.json').write_text(json.dumps({'origin':r['origin'],'expiry_unix':r['review_deadline']}))
 (owned/'workspace-head').write_text(subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip())
 print('WORKSPACE03_READY',owned,r['origin'],flush=True)
 for stage in ['admission','requests']:
  while True:
   results=[]
   def focused(name,ok):
    results.append({'name':name,'pass':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
    if not ok:raise AssertionError(name)
   try:
    if stage=='admission':module('admission_'+str(time.time_ns()),root/'scripts/ase-adm02-oper09-browser.py').run(stage,r,focused,cli)
    else:
     for action in ['prepare','secondary_advisor','owned_conflict']:
      cli('scripts/ase-advisor-workspace02-fixture.php',action)
     module('requests_'+str(time.time_ns()),root/'scripts/ase-advisor-workspace03-browser.py').run(r,focused,cli,races)
    for item in results:check(item['name'],item['pass'])
    break
   except Exception as e:
    (owned/(stage+'-failure.log')).write_text(traceback.format_exc())
    print('STAGE_FAILED',stage,type(e).__name__,str(e)[:200],flush=True)
    if os.environ.get('MXMED_WORKSPACE02_RECOVER')!='1':raise
    marker=owned/('retry-'+stage)
    while not marker.exists() and time.time()<r['review_deadline'] and not (owned/'stop-review').exists():time.sleep(1)
    if not marker.exists():raise RuntimeError('owned_review_stopped_or_expired')
    marker.unlink()
