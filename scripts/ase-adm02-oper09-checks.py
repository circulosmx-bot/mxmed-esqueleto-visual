"""Focused canonical journey; no historical suite or persistent database target."""
import json,pathlib,subprocess,time,importlib.util,hashlib,traceback,os

def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def setup(r,check):
 root,owned,sql,db,env=[r[k] for k in ['ROOT','owned','sql','database','env']]
 assert not (root/'api/mxmed-db.config.php').exists()
 for file in ['2026_09_08_media_review_candidates.sql','2026_09_08_media_review_pending_logo.sql','2026_09_09_media_review_auto_proposal.sql','2026_09_10_media_review_improvement_input.sql','2026_09_11_media_review_replacement_feedback.sql','2026_09_12_media_review_batches.sql','2026_09_13_media_review_item_submission.sql']:
  r['source_sql']((root/'modules/media/db/migrations'/file).read_text())
 for table in ['profiles_doctors','media_review_submissions','media_review_files','media_review_batches','media_review_batch_ready_events']:
  sql(f"GRANT SELECT ON `{db}`.`{table}` TO 'ase_writer'@'127.0.0.1'")
 for key,name in [('MXMED_PRIVATE_MEDIA_ROOT','media-private'),('MXMED_PUBLIC_MEDIA_ROOT','media-public')]:
  (owned/name).mkdir(mode=0o700);env[key]=str(owned/name)
 env['MXMED_OPER09_MEDIA_REVIEW']='1'
 pin_sources(root,owned)
 check('Real media consumer uses only owned schema and private roots',True)

def run(r,check,races):
 root,owned,env=[r[k] for k in ['ROOT','owned','env']]
 def cli(file,action='',body=None):
  result=subprocess.run(['php',file]+([action] if action else []),cwd=root,env=env,input=json.dumps(body or {}),text=True,capture_output=True)
  if result.returncode:
   (owned/'oper09-cli-error.log').write_text(result.stderr);raise RuntimeError('owned_fixture_failed')
  value=json.loads(result.stdout);assert value.get('ok',True);return value.get('data',value)
 cli('modules/identity/tests/StaffWorkflowFixture.php','enroll')
 media=cli('scripts/ase-adm02-oper09-media-fixture.php');(owned/'oper09-media.json').write_text(json.dumps(media))
 php="$im=imagecreatetruecolor(64,48);imagefilledrectangle($im,0,0,63,47,imagecolorallocate($im,40,140,180));ob_start();imagepng($im);$b=ob_get_clean();$out=substr($b,0,8);for($i=8;$i<strlen($b);){$n=unpack('N',substr($b,$i,4))[1];if(substr($b,$i+4,4)!=='pHYs')$out.=substr($b,$i,$n+12);$i+=$n+12;}echo $out;"
 (owned/'synthetic.png').write_bytes(subprocess.check_output(['php','-r',php]))
 (owned/'oper09-runtime.json').write_text(json.dumps({'origin':r['origin'],'expiry_unix':r['review_deadline']}))
 print('OPER09_READY',owned,r['origin'],flush=True)
 # Stages are explicit, resumable and preserve the exact synthetic account on failures.
 for stage in ['admission','permissions','modals']:
  while True:
   marker=owned/('run-'+stage)
   print('WAITING_STAGE',stage,flush=True)
   if os.environ.get('MXMED_OPER09_STAGED')=='1':
    while not marker.exists() and time.time()<r['review_deadline'] and not (owned/'stop-review').exists():time.sleep(1)
    if not marker.exists():raise RuntimeError('owned_review_stopped_or_expired')
    marker.unlink()
   results=[]
   def focused(name,ok):
    results.append({'name':name,'pass':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
    if not ok:raise AssertionError(name)
   try:
    module('oper09_'+stage+str(time.time_ns()),root/'scripts/ase-adm02-oper09-browser.py').run(stage,r,focused,cli)
    for item in results:check(item['name'],item['pass'])
    break
   except Exception as e:
    # Private only: assertion traces can contain applicant input; never commit them.
    (owned/(stage+'-failure.log')).write_text(traceback.format_exc())
    print('STAGE_FAILED',stage,type(e).__name__,str(e)[:500],flush=True)
    if os.environ.get('MXMED_OPER09_STAGED')!='1':raise


def pin_sources(root,owned):
 """Extend the owned composition's exact source pins to its existing Media consumer."""
 file=owned/'workflow-approval.json';approval=json.loads(file.read_text());assert approval['root']==str(root)
 assert all(hashlib.sha256((root/f).read_bytes()).hexdigest()==h for f,h in approval['source_hashes'].items())
 files=[*root.glob('modules/media/**/*.php'),*root.glob('api/internal/media-review/*.php'),root/'internal/media-review/index.php',root/'api/_lib/db.php',*root.glob('assets/js/staff-*.js'),root/'assets/js/team-dialogs.js',root/'assets/js/team-selects.js',root/'assets/css/internal-team-admission.css',root/'assets/js/media-review-inbox.js',root/'assets/css/media-review-inbox.css']
 approval['source_hashes'].update({str(f.relative_to(root)):hashlib.sha256(f.read_bytes()).hexdigest() for f in files})
 file.write_text(json.dumps(approval))
