#!/usr/bin/env python3
"""OP-INT04: explicit Operators replacement, byte-preserved Agenda/clinical boundaries."""
import html.parser,json,pathlib,subprocess
ROOT=pathlib.Path(__file__).resolve().parents[1];BASELINE='8e29d3fa5be7f6f75dfd7321b5cde58781f5d755'
class Dom(html.parser.HTMLParser):
 def __init__(self):super().__init__();self.nodes=[]
 def handle_starttag(self,t,a):self.nodes.append(('start',t,sorted(a)))
 def handle_endtag(self,t):self.nodes.append(('end',t))
 def handle_data(self,d):
  if d.strip():self.nodes.append(('text',' '.join(d.split())))
def parse(s):p=Dom();p.feed(s);return p.nodes
def old(name):return subprocess.check_output(['git','show',BASELINE+':'+name],cwd=ROOT,text=True)
def read(name):return (ROOT/name).read_text()
def render(s,ready=False):
 prefix="<?php $privateAccess=(object)['allowed'=>true];$operatorUi03="+('true' if ready else 'false')+";$operatorOwner="+('true' if ready else 'false')+";$operatorPanel=['can_configure_agenda'=>"+('false' if ready else 'true')+",'service_ready'=>"+('true' if ready else 'false')+",'can_manage'=>"+('true' if ready else 'false')+",'message'=>'Esquema pendiente','access_message'=>'Sin titular'];$professional=['doctor_id'=>'reference','display_name'=>'Referencia'];$account=['id'=>'reference'];$html=static fn($v)=>htmlspecialchars($v,ENT_QUOTES,'UTF-8'); ?>"
 return subprocess.check_output(['php'],input=prefix+s,cwd=ROOT,text=True)
def run():
 results={}
 for name in ['partials/private-app/agenda.php','assets/js/core/navigation.js','assets/js/core/sidebar.js','assets/js/core/private-session.js','api/_lib/clinical_professional_authority.php','api/_lib/clinical_identity.php']:
  if (ROOT/name).exists():assert read(name)==old(name),name+' changed'
 subprocess.run(['python3',str(ROOT/'scripts/op-imp07-contract-qa.py')],cwd=ROOT,check=True)
 results['agenda_and_security_boundaries']='ORIGIN_ONLY_OP_IMP07_OTHER_BOUNDARIES_IDENTICAL'
 before=old('private-app.php');after=read('private-app.php');start='<body ';end='<div id="viewport">'
 assert parse(render(before[before.index(start):before.index(end)]))==parse(render(after[after.index(start):after.index(end)]))
 results['normal_header_sidebar']='DOM_IDENTICAL'
 app=old('assets/js/app.js');a=app.index('// Agenda · Operadores (frontend UX scaffold, backend-safe)');b=app.index('// P11 single source shim',a)
 assert read('assets/js/app.js')==app[:a]+'// Operators uses one canonical panel controller; legacy records are read-only.\n\n'+app[b:]
 results['unrelated_application_and_clinical_logic']='BYTE_IDENTICAL'
 api=old('api/agenda/index.php');new=read('api/agenda/index.php');a=api.index("        case 'operators':");b=api.index('\n    }\n} catch',a);c=new.index("        case 'operators':");d=new.index('\n    }\n} catch',c)
 assert api[:a]==new[:c] and api[b:]==new[d:]
 block=new[c:d];assert "if ($method !== 'GET')" in block and not any('->'+method+'(' in block for method in ['store','migrationApply','pause','archive','restore','reactivate'])
 results['normal_legacy_http_writers']='RETIRED_OTHER_ROUTES_IDENTICAL'
 tpl=read('partials/private-app/operators.php');normal=render("<?php require 'partials/private-app/operators.php'; ?>");enabled=render("<?php require 'partials/private-app/operators.php'; ?>",True)
 for ident in ['ag_ops_shell','ag_ops_layout','ag_ops_list','ag_ops_modal_accordion','ag_ops_history_modal']:
  assert 'id="'+ident+'"' in normal and 'id="'+ident+'"' in enabled
 for ident in ['ag_ops_temp_password','ag_ops_login','ag_ops_regen_password','ag_ops_perm_billing']:assert ident not in tpl
 assert normal.count('data-operator-grant=')==5 and enabled.count('data-operator-grant=')==5
 assert 'id="ag_ops_refresh" disabled' in normal
 assert 'id="ag_ops_save_btn" disabled' in normal and 'id="ag_ops_add_btn" disabled' in normal and 'id="ag_ops_save_btn" disabled' not in enabled
 assert '3' not in ''.join(s for s in normal.splitlines() if 'ag_ops_max_allowed_value' in s)
 assert read('assets/js/agenda/operators-panel.js').count("$('ag_ops_save_btn').addEventListener")==1
 assert 'operatorComposition' not in read('assets/js/agenda/operators-panel.js') and '$operatorUi03' not in tpl
 results['single_native_panel_readiness_and_server_disabled_mutations']='PASS'
 evidence=pathlib.Path.home()/'Library/Application Support/MXMED/op02-admin/evidence/op-int04';evidence.mkdir(parents=True,exist_ok=True);(evidence/'source-contract.json').write_text(json.dumps(results,indent=2)+'\n');print(json.dumps(results,indent=2))
if __name__=='__main__':run()
