#!/usr/bin/env python3
"""OP-IMP07 permits only origin presentation changes in the original Agenda module."""
import pathlib,subprocess,re,json
ROOT=pathlib.Path(__file__).resolve().parents[1]
BASELINE='2add5afa8f652c0c1d00662642e9d33cd9dd8492'
def source(name,old=False):
 return subprocess.check_output(['git','show',BASELINE+':'+name],cwd=ROOT,text=True) if old else (ROOT/name).read_text()
def agenda_business(s):
 if '  const syncCanonicalOperatorLegend=' in s:
  a=s.index('  const syncCanonicalOperatorLegend=');b=s.index('  const els =',a);s=s[:a]+s[b:]
 for start,end in [
  ('  const resolveEventActionOriginMeta =','  const syncEventActionPatientIdentityDisplay ='),
  ('  const resolveAppointmentOriginVisualKey =','  // Agenda owns the appointment-origin mapping;')]:
  a=s.index(start);b=s.index(end,a);s=s[:a]+'  /* versioned origin presentation */\n'+s[b:]
 return re.sub(r'^\s*creator_(?:snapshot|origin_status): row\?\.creator_(?:snapshot|origin_status) \|\| (?:null|\'\'),\n','',s,flags=re.M)
def main():
 name='assets/js/agenda/agenda-workspace.js'
 assert agenda_business(source(name))==agenda_business(source(name,True)), 'Agenda business logic changed outside origin presentation'
 names=['partials/private-app/agenda.php','assets/css/style.css','assets/js/core/navigation.js','assets/js/core/sidebar.js','assets/js/core/private-session.js','api/agenda/index.php','api/_lib/clinical_professional_authority.php','assets/js/app.js']
 for name in names:
  current=source(name)
  if name=='assets/js/core/private-session.js':
   # OP-ACT01 moves only a strictly validated invitation fragment outside next=.
   expected=source(name,True).replace("return '/private-app.php' + window.location.search + window.location.hash;","return '/private-app.php' + window.location.search + (location.hash.startsWith('#operator-invitation=')?'':location.hash);").replace("(state ? '&state=' + encodeURIComponent(state) : '');","(state ? '&state=' + encodeURIComponent(state) : '') + (/^#operator-invitation=[A-Za-z0-9_.%~-]{20,512}$/.test(location.hash)?location.hash:'');")
   assert current==expected,name+' changed beyond protected fragment transport'
  else:assert current==source(name,True),name
 print(json.dumps({'original_agenda_business_logic':'IDENTICAL_OUTSIDE_ORIGIN_PRESENTATION','shell_calendar_styles_navigation_clinical_dispatch_session_except_reviewed_invitation_fragment':'BYTE_IDENTICAL'},indent=2))
if __name__=='__main__':main()
