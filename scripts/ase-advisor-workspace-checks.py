"""Own infrastructure only, canonical admission and targeted R2 browser operations."""
import json,pathlib,subprocess,importlib.util,hashlib,time,traceback,os
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def setup(r,check):
 root,owned,sql,db,env=[r[k] for k in ['ROOT','owned','sql','database','env']]
 module('oper09_setup',root/'scripts/ase-adm02-oper09-checks.py').setup(r,check)
 r['source_sql']((root/'modules/agenda/db/consultorios_schema.sql').read_text())
 for file in ['2026_09_03_01_create_media_assets.sql','2026_09_08_doctor_gallery.sql','2026_09_08_doctor_profile_photo.sql','2026_09_15_doctor_gallery_display_order.sql']:
  r['source_sql']((root/'modules/media/db/migrations'/file).read_text())
 for table,rights in {'consultorios':'SELECT','media_assets':'SELECT,INSERT,UPDATE','media_review_submissions':'UPDATE','media_review_files':'INSERT,DELETE','profiles_doctors':'UPDATE'}.items():
  sql(f"GRANT {rights} ON `{db}`.`{table}` TO 'ase_writer'@'127.0.0.1'")
 env['MXMED_ADVISOR_WORKSPACE_REVIEW']='1'
 pin_sources(root,owned)
 check('Workspace uses canonical Media schema, owned roots and limited HTTP principal',True)

def pin_sources(root,owned):
 file=owned/'workflow-approval.json';approval=json.loads(file.read_text());assert approval['root']==str(root)
 assert all(hashlib.sha256((root/f).read_bytes()).hexdigest()==h for f,h in approval['source_hashes'].items())
 files=[*root.glob('api/internal/workspace/*.php'),*root.glob('internal/staff-workspace/*.php'),root/'api/media/index.php',root/'assets/js/staff-workspace.js',root/'assets/css/staff-workspace.css',root/'public/identity/identity-access.js',root/'public/identity/acceso.php']
 approval['source_hashes'].update({str(f.relative_to(root)):hashlib.sha256(f.read_bytes()).hexdigest() for f in files})
 file.write_text(json.dumps(approval))

def run(r,check,races):
 root,owned,env=[r[k] for k in ['ROOT','owned','env']]
 r['OUT']=root/'docs/evidence/ase-advisor-workspace01-r2'
 def cli(file,action='',body=None):
  result=subprocess.run(['php',file]+([action] if action else []),cwd=root,env=env,input=json.dumps(body or {}),text=True,capture_output=True)
  if result.returncode:
   (owned/'workspace-cli-error.log').write_text(result.stderr);raise RuntimeError('owned_fixture_failed')
  value=json.loads(result.stdout);assert value.get('ok',True);return value.get('data',value)
 cli('modules/identity/tests/StaffWorkflowFixture.php','enroll')
 media=cli('scripts/ase-advisor-workspace-fixture.php');(owned/'workspace-media.json').write_text(json.dumps(media))
 php="$im=imagecreatetruecolor(64,48);imagefilledrectangle($im,0,0,63,47,imagecolorallocate($im,40,140,180));ob_start();imagepng($im);$b=ob_get_clean();$out=substr($b,0,8);for($i=8;$i<strlen($b);){$n=unpack('N',substr($b,$i,4))[1];if(substr($b,$i+4,4)!=='pHYs')$out.=substr($b,$i,$n+12);$i+=$n+12;}echo $out;"
 (owned/'synthetic.png').write_bytes(subprocess.check_output(['php','-r',php]))
 (owned/'workspace-runtime.json').write_text(json.dumps({'origin':r['origin'],'expiry_unix':r['review_deadline']}))
 print('WORKSPACE_READY',owned,r['origin'],flush=True)
 for stage in ['admission','workspace']:
  while True:
   results=[]
   def focused(name,ok):
    results.append({'name':name,'pass':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
    if not ok:raise AssertionError(name)
   try:
    if stage=='admission':module('admission_'+str(time.time_ns()),root/'scripts/ase-adm02-oper09-browser.py').run(stage,r,focused,cli)
    else:module('workspace_'+str(time.time_ns()),root/'scripts/ase-advisor-workspace-browser.py').run(r,focused)
    for item in results:check(item['name'],item['pass'])
    break
   except Exception as e:
    (owned/(stage+'-failure.log')).write_text(traceback.format_exc())
    print('STAGE_FAILED',stage,type(e).__name__,str(e)[:250],flush=True)
    if os.environ.get('MXMED_WORKSPACE_RECOVER')!='1':raise
    marker=owned/('retry-'+stage)
    while not marker.exists() and time.time()<r['review_deadline'] and not (owned/'stop-review').exists():time.sleep(1)
    if not marker.exists():raise RuntimeError('owned_review_stopped_or_expired')
    marker.unlink()
