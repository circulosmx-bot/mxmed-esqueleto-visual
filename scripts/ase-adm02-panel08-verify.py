#!/usr/bin/env python3
"""Static verification of the isolated PANEL08 diff and materially affected docs. No SQL/network."""
import pathlib,subprocess,hashlib,json,re,urllib.parse
root=pathlib.Path(__file__).resolve().parents[1]
def git(*a):return subprocess.check_output(['git',*a],cwd=root,text=True).splitlines()
files=set(git('diff','--name-only','ec8024dfd4c16237877f92a25f4351e2ec0bdc16'))|set(git('ls-files','--others','--exclude-standard'))
files={f for f in files if (root/f).is_file() and not f.startswith('docs/evidence/') and '__pycache__' not in f}
checks=[]
for f in sorted(files):
 ext=(root/f).suffix
 if ext=='.php':cmd=['php','-l',f]
 elif ext=='.js':cmd=['node','--check',f]
 elif ext=='.py':cmd=['python3','-m','py_compile',f]
 else:continue
 subprocess.run(cmd,cwd=root,check=True,capture_output=True);checks.append({'file':f,'syntax':'PASS'})
links=0
for f in sorted(files):
 if not f.endswith('.md'):continue
 p=root/f
 for target in re.findall(r'\[[^\]]*\]\(([^)\s]+)\)',p.read_text()):
  if '://' in target or target.startswith(('mailto:','#')):continue
  path=urllib.parse.unquote(target.split('#')[0]);assert (p.parent/path).exists(),(f,target);links+=1
subprocess.run(['git','diff','--check'],cwd=root,check=True,capture_output=True)
out=root/'docs/evidence/ase-adm02-panel08';out.mkdir(parents=True,exist_ok=True)
(out/'verification.json').write_text(json.dumps({'result':'PASS','syntax':checks,'relative_document_links':links,'diff_check':'PASS','source_sha256':{f:hashlib.sha256((root/f).read_bytes()).hexdigest() for f in sorted(files) if not f.endswith('.md')},'no_database_connections':True},indent=2)+'\n')
print('PASS',len(checks),'syntax checks;',links,'document paths; diff whitespace clean')
