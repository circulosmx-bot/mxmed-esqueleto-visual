"""Focused staff integration, owned catalog reuse, and canonical browser checks."""
import importlib.util,pathlib,json,hashlib,subprocess,time,threading,traceback,os

def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def setup(r,check):
 root,owned,sql,db,env,source=[r[k] for k in ['ROOT','owned','sql','database','env','source_sql']]
 module('staff04_existing_setup',root/'scripts/ase-advisor-workspace03-checks.py').setup(r,check)
 source((root/'modules/identity/db/migrations/2026_10_19_01_staff_capability_intentions.sql').read_text())
 for table in ['auth_staff_capability_intentions','auth_staff_intention_activations']:sql(f"GRANT SELECT,INSERT ON `{db}`.`{table}` TO 'ase_writer'@'127.0.0.1'")
 source((root/'modules/catalog/db/catalog_cp_colonias_schema.sql').read_text())
 # The repository's existing geographical source; never reads the original DB.
 source((root/'db_backups/sepomex_20251115_114415.sql').read_text(encoding='utf-8-sig'))
 def norm(col):return f"CASE WHEN TRIM(COALESCE({col}, '')) REGEXP '[ÃÂ├┬]' THEN CONVERT(BINARY CONVERT(TRIM(COALESCE({col}, '')) USING cp850) USING utf8mb4) ELSE TRIM(COALESCE({col}, '')) END"
 a,b,c=map(norm,['d_asenta','d_mnpio','d_estado'])
 sql(f"INSERT INTO catalog_cp_colonias(cp,colonia,municipio,estado,is_active) SELECT TRIM(d_codigo),{a},{b},{c},1 FROM sepomex WHERE d_codigo REGEXP '^[0-9]{{5}}$' AND {a}<>'' GROUP BY TRIM(d_codigo),{a},{b},{c} ON DUPLICATE KEY UPDATE municipio=VALUES(municipio),estado=VALUES(estado),is_active=1")
 sql(f"GRANT SELECT ON `{db}`.catalog_cp_colonias TO 'ase_writer'@'127.0.0.1'")
 states=sql('SELECT estado,COUNT(DISTINCT municipio) FROM catalog_cp_colonias WHERE is_active=1 GROUP BY estado ORDER BY estado')
 out=r.get('OUT',root/'docs/evidence/staff-control04');out.mkdir(parents=True,exist_ok=True)
 (out/'geography-source.json').write_text(json.dumps({'source':'db_backups/sepomex_20251115_114415.sql','sha256':hashlib.sha256((root/'db_backups/sepomex_20251115_114415.sql').read_bytes()).hexdigest(),'states':[{'state':v[0],'municipalities':v[1]} for v in states],'identifier_contract':'Existing natural keys estado/municipio; colony row id is not a city id.'},ensure_ascii=False,indent=2))
 env['MXMED_STAFF_CONTROL04']='1'
 approval=json.loads((owned/'workflow-approval.json').read_text())
 for pattern in ['scripts/staff-control04*','modules/identity/db/migrations/2026_10_19_01_staff_capability_intentions.sql','internal/development/*.php','assets/js/staff-workflow.js','assets/js/internal-team.js','assets/js/staff-workspace.js','assets/css/staff-development-index.css','public/identity/acceso.php','public/identity/identity-access.js']:
  for p in root.glob(pattern):approval['source_hashes'][str(p.relative_to(root))]=hashlib.sha256(p.read_bytes()).hexdigest()
 (owned/'workflow-approval.json').write_text(json.dumps(approval))
 check('Existing Consultorio geographic catalog imported into owned DB only',len(states)>1)

def run(r,check,races):
 root,owned,env=[r[k] for k in ['ROOT','owned','env']];r.setdefault('OUT',root/'docs/evidence/staff-control04')
 def cli(file,action='',body=None):
  p=subprocess.run(['php',file]+([action] if action else []),cwd=root,env=env,input=json.dumps(body or {}),text=True,capture_output=True)
  if p.returncode:(owned/'staff04-fixture-error.log').write_text(p.stderr);raise RuntimeError('owned_fixture_failed')
  v=json.loads(p.stdout)
  if not v.get('ok',True):raise RuntimeError('owned_fixture_rejected')
  return v.get('data',v)
 cli('modules/identity/tests/StaffWorkflowFixture.php','enroll')
 (owned/'workspace-media.json').write_text(json.dumps(cli('scripts/ase-advisor-workspace-fixture.php')))
 php="$im=imagecreatetruecolor(64,48);imagefilledrectangle($im,0,0,63,47,imagecolorallocate($im,40,140,180));ob_start();imagepng($im);$b=ob_get_clean();$out=substr($b,0,8);for($i=8;$i<strlen($b);){$n=unpack('N',substr($b,$i,4))[1];if(substr($b,$i+4,4)!=='pHYs')$out.=substr($b,$i,$n+12);$i+=$n+12;}echo $out;"
 (owned/'synthetic.png').write_bytes(subprocess.check_output(['php','-r',php]))
 (owned/'workspace-runtime.json').write_text(json.dumps({'origin':r['origin'],'expiry_unix':r['review_deadline']}))
 (owned/'workspace-head').write_text(subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip())
 # Delivery metadata allows the exact documented credential command during logged-out QA.
 (owned/'review-info.json').write_text(json.dumps({'url':r['origin']+'/internal/media-review/index.php?section=equipo','expiry_unix':r['review_deadline'],'owner_pid':os.getpid()}))
 worker=module('staff04_worker',root/'scripts/staff-control04-simulation.py')
 threading.Thread(target=worker.run,args=(root,owned,env,r['origin'],r['review_deadline']),daemon=True).start()
 print('STAFF_CONTROL04_READY',owned,r['origin'],flush=True)
 while True:
  results=[]
  def focused(name,ok):
   results.append({'name':name,'pass':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
   if not ok:raise AssertionError(name)
  try:
   module('staff04_browser_'+str(time.time_ns()),root/r.get('BROWSER','scripts/staff-control04-browser.py')).run(r,focused,cli)
   for x in results:check(x['name'],x['pass'])
   break
  except Exception as e:
   (owned/'staff04-browser-error.log').write_text(traceback.format_exc());print('STAFF04_CHECK_FAILED',type(e).__name__,str(e)[:180],flush=True)
   marker=owned/'retry-staff04'
   while not marker.exists() and time.time()<r['review_deadline'] and not (owned/'stop-review').exists():time.sleep(1)
   if not marker.exists():raise RuntimeError('owned_review_stopped_or_expired')
   marker.unlink()
