"""Generate a review-only patch; never writes protected source or runtime config."""
import pathlib,subprocess,difflib,json,hashlib
ROOT=pathlib.Path(__file__).resolve().parents[2];ORIGINAL=ROOT.parent/'mxmed-step2-vitals-r1';BASE='f92f37f1958af5140a96c04cb52c5c1ce7af8e01'
assert subprocess.check_output(['git','rev-parse','HEAD'],cwd=ORIGINAL,text=True).strip()==BASE
files=['modules/identity/contracts/DevelopmentSessionCookiePolicy.php','modules/identity/services/SessionService.php','modules/identity/http/IdentityHttpComposition.php','modules/identity/http/NormalLocalIdentityConfiguration.php','scripts/normal-local-identity.py']
patch='';manifest=[]
for file in files:
 p=ORIGINAL/file;before=p.read_text() if p.exists() else '';after=(ROOT/file).read_text()
 if file.endswith('/SessionService.php'):
  # Only cookie hunks; no unrelated observeOnly changes from staff history.
  after=before.replace('private bool $touchActivity = true) {}','private bool $touchActivity = true, private ?\\Identity\\Contracts\\DevelopmentSessionCookiePolicy $developmentCookie = null) {}').replace('SessionCookieDescriptor::forToken($token)', 'SessionCookieDescriptor::forToken($token, $this->developmentCookie?->remainingSeconds($record, $this->clock))')
 if p.exists():assert before==subprocess.check_output(['git','show',BASE+':'+file],cwd=ORIGINAL,text=True)
 patch+=''.join(difflib.unified_diff(before.splitlines(True),after.splitlines(True),fromfile='a/'+file if p.exists() else '/dev/null',tofile='b/'+file))
 manifest.append({'file':file,'before':hashlib.sha256(before.encode()).hexdigest() if p.exists() else None,'after':hashlib.sha256(after.encode()).hexdigest()})
out=ROOT/'tools/dev-session-persist02';(out/'integration.patch').write_text(patch);(out/'integration-files.json').write_text(json.dumps(manifest,indent=2)+'\n')
subprocess.run(['git','apply','--check',str(out/'integration.patch')],cwd=ORIGINAL,check=True)
print('PATCH_SHA256='+hashlib.sha256(patch.encode()).hexdigest())
