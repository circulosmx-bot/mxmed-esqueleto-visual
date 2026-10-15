#!/usr/bin/env python3
"""Read-only source/render preservation checks against the accepted OP02 baseline."""
import hashlib,html.parser,json,pathlib,subprocess
ROOT=pathlib.Path(__file__).resolve().parents[1]
BASELINE='4e3e4db0c1cacd3236a57483f5f6e46ef2309635'
class Dom(html.parser.HTMLParser):
 def __init__(self):super().__init__();self.nodes=[]
 def handle_starttag(self,t,a):self.nodes.append(('start',t,sorted(a)))
 def handle_startendtag(self,t,a):self.handle_starttag(t,a)
 def handle_endtag(self,t):self.nodes.append(('end',t))
 def handle_data(self,d):
  v=' '.join(d.split())
  if v:self.nodes.append(('text',v))
def parse(v):p=Dom();p.feed(v);return p.nodes
def render(v):
 prefix="<?php $privateAccess=(object)['allowed'=>true];$operatorUi03=false;$operatorOwner=false;$professional=['doctor_id'=>'reference','display_name'=>'Referencia'];$account=['id'=>'reference'];$html=static fn($v)=>htmlspecialchars($v,ENT_QUOTES,'UTF-8'); ?>"
 return subprocess.check_output(['php'],input=prefix+v,cwd=ROOT,text=True)
def run():
 old=subprocess.check_output(['git','show',BASELINE+':private-app.php'],cwd=ROOT,text=True);new=(ROOT/'private-app.php').read_text();results={}
 for label,start,end in [('operators','        <section class="mm-card d-none" id="p-ag-operadores">','        <!-- AGENDA · ADMINISTRACIÓN -->'),('agenda','        <section class="mm-card d-none" id="p-ag-admin">','        <!-- PACIENTES · ARCHIVO -->')]:
  before=old[old.index(start):old.index(end,old.index(start))]
  after=render("<?php require 'partials/private-app/"+label+".php'; ?>")
  assert parse(before)==parse(after),label+' normal DOM changed'
  results[label]={'normal_rendered_dom':'IDENTICAL','nodes':len(parse(after))}
 start='<body ';end='<div id="viewport">'
 a=render(old[old.index(start):old.index(end)]);b=render(new[new.index(start):new.index(end)])
 assert parse(a)==parse(b),'normal header/sidebar DOM changed';results['header_sidebar']={'normal_rendered_dom':'IDENTICAL','nodes':len(parse(b))}
 shared=(ROOT/'assets/js/agenda/agenda-workspace.js').read_text();app=(ROOT/'assets/js/app.js').read_text()
 assert shared.count('// Agenda Frontend v1 (FullCalendar + backend appointments)')==1
 assert '// Agenda Frontend v1 (FullCalendar + backend appointments)' not in app
 assert new.index('<script src="assets/js/agenda/agenda-workspace.js') < new.index('<script src="assets/js/app.js')
 assert 'window.mxmedInitializeAgenda();' in app
 old_app=subprocess.check_output(['git','show',BASELINE+':assets/js/app.js'],cwd=ROOT,text=True)
 old_prefix=old_app.split('// Agenda Frontend v1 (FullCalendar + backend appointments)')[0]
 sidebar=old_app[old_app.index('// VIS25: explicit pin preference plus transient hover/focus expansion.'):old_app.index('// Active professional context bridge')]
 assert sidebar in (ROOT/'assets/js/core/sidebar.js').read_text(),'sidebar implementation changed'
 restored=app.replace('// Shared original sidebar: assets/js/core/sidebar.js\nwindow.mxmedInitializeSidebar();\n\n',sidebar)
 assert restored.startswith(old_prefix),'pre-Agenda initialization order changed'
 assert "require $root.'/private-app.php'" in (ROOT/'scripts/op02-router.php').read_text()
 assert not (ROOT/'modules/identity/operators/operator-workspace.php').exists()
 for f in ['api/agenda/index.php','api/_lib/clinical_professional_authority.php','api/_lib/clinical_identity.php']:
  if (ROOT/f).exists():
   assert not subprocess.check_output(['git','diff',BASELINE,'--',f],cwd=ROOT),f+' authority changed'
 suffix=old_app[old_app.index('// Agenda · Operadores'):].replace("  if(!panel) return;","  if(!panel || window.mxmedCanonicalOperators) return;",1)
 assert app.endswith(suffix),'post-Agenda application/clinical implementation changed'
 results['application_suffix_unchanged']='PASS';results['shared_source']='PASS';results['clinical_dispatch_unchanged']='PASS'
 evidence=pathlib.Path.home()/'Library/Application Support/MXMED/op02-admin/evidence/op-ui03';evidence.mkdir(mode=0o700,parents=True,exist_ok=True);(evidence/'normal-component-parity.json').write_text(json.dumps(results,indent=2))
 print(json.dumps(results,indent=2))
if __name__=='__main__':run()
