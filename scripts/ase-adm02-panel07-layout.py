#!/usr/bin/env python3
"""Final layout/expanded-popup smoke after restoring the directory/detail DOM boundary."""
import pathlib,json,sys,os,hashlib,time
from playwright.sync_api import sync_playwright,expect
root=pathlib.Path(__file__).resolve().parents[1];owned=pathlib.Path(sys.argv[1]).resolve();assert owned.parent==pathlib.Path('/private/tmp') and owned.name.startswith('mxmed-ase-adm02imp01-') and owned.stat().st_uid==os.getuid() and owned.stat().st_mode&0o777==0o700
approval=json.loads((owned/'workflow-approval.json').read_text());assert approval['root']==str(root) and approval['expires']>time.time();assert all(hashlib.sha256((root/f).read_bytes()).hexdigest()==h for f,h in approval['source_hashes'].items())
env=json.loads((owned/'review-env.json').read_text());origin=env['MXMED_PREVIEW_ORIGIN'];records=json.loads((owned/'uxfunc02-records.json').read_text());out=root/'docs/evidence/ase-adm02-panel07';checks=[]
def check(label,ok):assert ok,label;checks.append({'name':label,'pass':True});print('PASS '+label,flush=True)
with sync_playwright() as pw:
 for engine in ['chromium','webkit']:
  b=getattr(pw,engine).launch();ctx=b.new_context(ignore_https_errors=True,viewport={'width':1366,'height':768});p=ctx.new_page();p.goto(origin+'/public/identity/acceso.php?next=%2Finternal%2Fmedia-review%2Findex.php%3Fsection%3Dequipo');check(engine+' login fields visible with compact expandable credential guide',p.locator('#login-password').bounding_box()['y']<768);p.locator('#staff-synthetic-login-guide summary').click();expect(p.locator('#staff-synthetic-login-guide')).to_contain_text('credentials');p.locator('#staff-synthetic-login-guide summary').click();p.locator('#login-email').fill('qa_director@example.test');p.locator('#login-password').fill((owned/'fixture-password').read_text().strip());p.locator('[data-submit]').click();p.wait_for_url('**/index.php?section=equipo');expect(p.locator('#add-member')).to_be_enabled()
  for w,h in [(1440,900),(1366,768),(390,844),(900,400)]:
   p.set_viewport_size({'width':w,'height':h});label=f'{engine}-{w}-{h}'
   if w<600:p.locator('#show-directory').click()
   p.locator('[data-member="'+records['bruno']['account']+'"]').click();p.locator('#personal-data').evaluate('(e)=>e.open=true');p.screenshot(path=str(out/'screens'/('final-team-'+label+'.png')),full_page=True)
   if w>=600:
    a=p.locator('#team-directory').bounding_box();d=p.locator('#advisor-detail').bounding_box();check(label+' directory and selected detail preserve approved side-by-side layout',d['x']>a['x']+a['width']-1 and abs(d['y']-a['y'])<2)
   else:check(label+' mobile selected detail reachable',p.locator('#member-edit').is_visible())
   check(label+' exact approved heading and no horizontal overflow',p.locator('h1').inner_text()=='EQUIPO INTERNO Y PERMISOS' and p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
   def popup(id,parent):
    trigger=p.locator('#'+id);trigger.click();pop=p.locator('.team-select-options:popover-open');expect(pop).to_be_visible();r=pop.bounding_box();check(label+' '+id+' expanded bounds',r['x']>=0 and r['y']>=0 and r['x']+r['width']<=w+1 and r['y']+r['height']<=h+1);p.keyboard.press('End');p.keyboard.press('Escape');expect(trigger).to_be_focused();expect(p.locator('#'+parent)).to_be_visible()
   p.locator('#member-edit').click();expect(p.locator('#personal-gender-trigger')).to_be_enabled()
   for kind in ['gender','avatar','presentation']:popup('personal-'+kind+'-trigger','staff-profile')
   p.locator('#save-staff-profile').scroll_into_view_if_needed();check(label+' profile footer reachable',p.locator('#save-staff-profile').bounding_box()['y']<h);p.keyboard.press('Escape')
   if w<600:p.locator('#show-directory').click()
   p.locator('#add-member').click();popup('new-channel-trigger','onboarding');p.keyboard.press('Escape')
  b.close()
(out/'layout.json').write_text(json.dumps({'result':'PASS','checks':checks,'check_count':len(checks)},indent=2)+'\n')
