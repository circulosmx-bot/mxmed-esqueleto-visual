#!/usr/bin/env python3
"""Owned QA01 fixture setup only; browser QA is separately resumable. No historical suite."""
import importlib.util,pathlib,json,subprocess,os,hashlib,threading
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(n,p):
 s=importlib.util.spec_from_file_location(n,p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
previous=module('full_previous',ROOT/'scripts/cross-surface-ux05-qa.py');base=previous.base
base.EXPECTED_BRANCH='feature/mxmed-advisor-full-grants-qa01';base.BASE='42d1e9e97e01ba29ad213c2732aa5a0d81ed637b';base.OUT=pathlib.Path('/tmp/mxmed-full-grants-qa01-setup');base.REVIEW_SECONDS=86400
base.EXTRA_PROTECTED.append('mxmed-cross-surface-ux05')
def setup(r,check):
 previous.setup(r,check)
 p=r['owned']/'workflow-approval.json';v=json.loads(p.read_text())
 for f in ROOT.glob('scripts/advisor-full-grants-qa01*'):v['source_hashes'][str(f.relative_to(ROOT))]=hashlib.sha256(f.read_bytes()).hexdigest()
 p.write_text(json.dumps(v))
def run(r,check,races):
 o=r['owned'];env=r['env']
 def cli(file,action=''):
  x=subprocess.run(['php',file]+([action] if action else []),cwd=ROOT,env=env,input='{}',text=True,capture_output=True)
  if x.returncode:(o/'full-fixture-error.log').write_text(x.stderr);raise RuntimeError('owned_fixture_failed')
  v=json.loads(x.stdout);assert v.get('ok',True),v.get('error');return v.get('data',v)
 cli('modules/identity/tests/StaffWorkflowFixture.php','enroll')
 (o/'workspace-media.json').write_text(json.dumps(cli('scripts/ase-advisor-workspace-fixture.php')))
 (o/'workspace-runtime.json').write_text(json.dumps({'origin':r['origin'],'expiry_unix':r['review_deadline']}))
 (o/'workspace-head').write_text(subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip())
 (o/'review-info.json').write_text(json.dumps({'url':r['origin']+'/internal/media-review/index.php?section=equipo','expiry_unix':r['review_deadline'],'owner_pid':os.getpid()}))
 (o/'review-env.json').write_text(json.dumps(env));(o/'support-ready').write_text('ready')
 # Existing artificial raster generator; no real identity image.
 php="$im=imagecreatetruecolor(64,48);imagefilledrectangle($im,0,0,63,47,imagecolorallocate($im,40,140,180));ob_start();imagepng($im);$b=ob_get_clean();$out=substr($b,0,8);for($i=8;$i<strlen($b);){$n=unpack('N',substr($b,$i,4))[1];if(substr($b,$i+4,4)!=='pHYs')$out.=substr($b,$i,$n+12);$i+=$n+12;}echo $out;"
 (o/'synthetic.png').write_bytes(subprocess.check_output(['php','-r',php]))
 cli('scripts/ase-advisor-workspace02-fixture.php','prepare')
 (o/'full-grants-protected-before.json').write_text(json.dumps(base.protected()))
 worker=module('full_worker',ROOT/'scripts/staff-control04-simulation.py')
 threading.Thread(target=worker.run,args=(ROOT,o,env,r['origin'],r['review_deadline']),daemon=True).start()
 print('FULL_GRANTS_READY',o,r['origin'],flush=True)
 check('QA01 fresh setup only; functional results recorded separately',True)
base.SETUP_HOOK=setup;base.HOOK=run
if __name__=='__main__':os.environ['MXMED_KEEP_OWNED_WORKFLOW']='1';base.main()
