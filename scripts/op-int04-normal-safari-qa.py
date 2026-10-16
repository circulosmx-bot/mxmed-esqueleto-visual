#!/usr/bin/env python3
"""Read-only normal-local UI QA using an existing Safari session; never reads cookies/credentials.
The screen is 1440x900. Same-origin temporary frames give the actual application
its requested layout viewport; window screenshots show the visible portion only.
"""
import datetime,json,os,pathlib,subprocess,time
os.umask(0o077)
EVIDENCE=pathlib.Path.home()/'Library/Application Support/MXMED/op02-admin/evidence/op-int04'
EVIDENCE.mkdir(parents=True,exist_ok=True)
URL='https://localhost:8443/private-app.php#p-ag-operadores'
def apple(code):return subprocess.check_output(['osascript','-e',code],text=True).strip()
def quoted(v):return json.dumps(v,ensure_ascii=False)
def main():
 available=apple('''tell application "Safari"
 repeat with w in windows
  repeat with t in tabs of w
   if URL of t starts with "https://localhost:8443/private-app.php" then
    if (do JavaScript "window.mxmedPrivateSession?.state || ''" in t) is "authenticated" then return "AVAILABLE"
   end if
  end repeat
 end repeat
 return "NOT_RUN"
end tell''')
 if available!='AVAILABLE':raise RuntimeError('NOT_RUN: no existing authenticated Safari session')
 # A new review tab only; original Director tabs are left intact.
 state=apple('''tell application "Safari"
 set w to front window
 set b to bounds of w
 set n to count of tabs of w
 set t to make new tab at end of tabs of w with properties {URL:"'''+URL+'''"}
 set current tab of w to t
 activate
 return (id of w as text) & "|" & (n + 1 as text) & "|" & (item 1 of b as text) & "|" & (item 2 of b as text) & "|" & (item 3 of b as text) & "|" & (item 4 of b as text)
end tell''').split('|')
 wid,index=map(int,state[:2]);bounds=list(map(int,state[2:]));results=[]
 def evaluate(js):return apple('tell application "Safari" to do JavaScript '+quoted(js)+' in tab '+str(index)+' of window id '+str(wid))
 def wait(js,seconds=35):
  until=time.monotonic()+seconds
  while time.monotonic()<until:
   try:
    if evaluate('String(Boolean('+js+'))')=='true':return
   except subprocess.CalledProcessError:pass
   time.sleep(.35)
  raise AssertionError('Read-only Safari readiness timed out')
 def frame(js):return evaluate("(()=>{const f=document.getElementById('mxmedOpInt04Viewport');const w=f.contentWindow,d=f.contentDocument;"+js+'})()')
 def shot(name):
  path=EVIDENCE/name
  r=subprocess.run(['screencapture','-x','-l',str(wid),str(path)],capture_output=True,text=True,timeout=10)
  return str(path) if r.returncode==0 else 'NOT_RUN_SCREEN_CAPTURE_UNAVAILABLE'
 try:
  wait("window.mxmedPrivateSession?.state==='authenticated' && !!window.mxmedOperatorPanel")
  for width,height in [(1440,900),(1366,768),(820,1180)]:
   apple('tell application "Safari" to set bounds of window id '+str(wid)+' to {0,0,'+str(width)+',900}')
   evaluate("(()=>{const f=document.createElement('iframe');f.id='mxmedOpInt04Viewport';f.title='Read-only normal-local viewport QA';f.src="+quoted(URL)+";f.style.cssText='display:block;border:0;width:"+str(width)+"px;height:"+str(height)+"px';document.body.replaceChildren(f);document.body.style.cssText='margin:0;padding:0;overflow:auto;display:block;min-width:0;height:auto';document.documentElement.style.cssText='overflow:auto;height:auto';window.scrollTo(0,0);})()")
   wait("document.getElementById('mxmedOpInt04Viewport')?.contentWindow.mxmedPrivateSession?.state==='authenticated' && !!document.getElementById('mxmedOpInt04Viewport')?.contentWindow.mxmedOperatorPanel && document.getElementById('mxmedOpInt04Viewport').contentDocument.readyState==='complete' && typeof document.getElementById('mxmedOpInt04Viewport').contentWindow.jumpTo==='function'")
   time.sleep(.7)
   frame("w.jumpTo('p-ag-operadores');")
   wait("!document.getElementById('mxmedOpInt04Viewport').contentDocument.querySelector('#p-ag-operadores').classList.contains('d-none')")
   facts=json.loads(frame("const b=JSON.parse(d.getElementById('mxmed-private-bootstrap').textContent);return JSON.stringify({viewport:[w.innerWidth,w.innerHeight],session:w.mxmedPrivateSession.state,doctor:b.professional.doctor_id,role:b.professional.role,service_ready:b.operators_ui.service_ready,schema_ready:b.operators_ui.schema_ready,can_manage:b.operators_ui.can_manage,legacy_counts:b.operators_ui.legacy.counts,sidebars:d.querySelectorAll('#mmSidebar').length,panels:d.querySelectorAll('#p-ag-operadores').length,calendars:d.querySelectorAll('#ag_calendar').length,controller:d.querySelectorAll('script[src*=\"operators-panel.js\"]').length,old_credentials:d.querySelectorAll('#ag_ops_login,#ag_ops_temp_password').length,save_disabled:d.getElementById('ag_ops_save_btn').disabled,inputs_disabled:[...d.querySelectorAll('#p-ag-operadores input,#p-ag-operadores select')].every(e=>e.disabled),grants:d.querySelectorAll('[data-operator-grant]').length,quota:d.getElementById('ag_ops_active_value').textContent,canonical_agenda_adapter:!!w.mxmedCanonicalOperators,readiness_visible:!!d.getElementById('ag_ops_readiness'),overflow:d.documentElement.scrollWidth>w.innerWidth+2,legacy_records:d.querySelectorAll('#ag_ops_list .mx-ag-ops-operator-band').length,archive_records:Number(d.getElementById('ag_ops_archived_count').textContent)});") )
   assert facts['viewport']==[width,height],facts
   assert facts['session']=='authenticated' and facts['doctor']=='1' and facts['role']=='collaborator',facts
   assert not facts['service_ready'] and not facts['schema_ready'] and not facts['can_manage'],facts
   assert facts['legacy_counts']=={'active':1,'paused':1,'pending':0,'archived':1},facts
   assert facts['sidebars']==facts['panels']==facts['calendars']==facts['controller']==1,facts
   assert facts['save_disabled'] and facts['inputs_disabled'] and facts['grants']==5 and facts['old_credentials']==0,facts
   assert facts['quota']=='No disponible' and facts['readiness_visible'] and not facts['canonical_agenda_adapter'] and not facts['overflow'],facts
   assert facts['legacy_records']>=2 and facts['archive_records']>=1,facts
   screenshots=[shot(f'normal-safari-frame-{width}x{height}-operators-visible.png')]
   # Original accordions, archive and history remain usable, with no mutation handlers.
   frame("d.querySelector('[data-ops-accordion-toggle=permissions]').click();d.getElementById('ag_ops_archived_toggle').click();")
   assert frame("return String(!d.getElementById('ag_ops_archived_panel').classList.contains('d-none'));")=='true'
   frame("[...d.querySelectorAll('#p-ag-operadores button')].find(e=>e.textContent==='Historial del equipo').click();")
   wait("document.getElementById('mxmedOpInt04Viewport').contentDocument.querySelector('#ag_ops_history_modal').classList.contains('show')")
   assert int(frame("return String(d.querySelectorAll('#ag_ops_history_list p').length);"))==9
   frame("w.bootstrap.Modal.getInstance(d.getElementById('ag_ops_history_modal')).hide();w.jumpTo('p-ag-admin');")
   wait("!document.getElementById('mxmedOpInt04Viewport').contentDocument.querySelector('#p-ag-admin').classList.contains('d-none') && document.getElementById('mxmedOpInt04Viewport').contentDocument.querySelectorAll('.mx-ag-slot-card').length>0")
   screenshots.append(shot(f'normal-safari-frame-{width}x{height}-agenda-visible.png'))
   # Open and close the actual appointment form without saving.
   tomorrow=(datetime.date.today()+datetime.timedelta(days=1)).isoformat()
   frame("d.querySelector('.mx-ag-custom-week-col[data-day-key=\""+tomorrow+"\"] [data-slot-kind=available]')?.click();")
   wait("!document.getElementById('mxmedOpInt04Viewport').contentDocument.querySelector('#ag_custom_slot_menu').classList.contains('d-none')")
   frame("d.getElementById('ag_custom_slot_new_btn').click();")
   wait("document.getElementById('mxmedOpInt04Viewport').contentDocument.querySelector('#ag_new_appointment_modal').classList.contains('show')")
   screenshots.append(shot(f'normal-safari-frame-{width}x{height}-appointment-visible.png'))
   frame("w.bootstrap.Modal.getInstance(d.getElementById('ag_new_appointment_modal')).hide();w.jumpTo('p-info');")
   wait("!document.getElementById('mxmedOpInt04Viewport').contentDocument.querySelector('#p-info').classList.contains('d-none')")
   facts.update({'original_agenda':'PASS','original_appointment_form_read_only':'PASS','unrelated_navigation':'PASS','legacy_history_read':'PASS','result':'PASS','screenshots':screenshots,'screenshot_scope':'Visible portion of real Safari frame; frame viewport verified separately'})
   results.append(facts);(EVIDENCE/'normal-safari.json').write_text(json.dumps(results,indent=2)+'\n');print(f'NORMAL_SAFARI_FRAME_{width}_{height}=PASS',flush=True)
 finally:
  # Restore this new review tab to the actual application and the original window bounds.
  apple('tell application "Safari" to set URL of tab '+str(index)+' of window id '+str(wid)+' to '+quoted(URL))
  apple('tell application "Safari" to set bounds of window id '+str(wid)+' to {'+','.join(map(str,bounds))+'}')
if __name__=='__main__':main()
