"""Actual Director controller: auto recovery, discard anchors and frozen geometry."""
import hashlib,json,os,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
ROOT=Path(__file__).resolve().parents[3]
OUT=Path(os.environ.get('STEP2_DRAFT_R2_ARTIFACTS','/tmp/mxmed-step2-draft-r2'));OUT.mkdir(parents=True,exist_ok=True)
BASE=os.environ.get('STEP2_DRAFT_R2_REVIEW_BASE','http://127.0.0.1:18148')
URL=BASE+'/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_vitalref=adult&review_step2_visual=r4&review_current_values=0'
START='be2e819b393140d26d2a1f4e6905c12a1818b050'
INSTRUMENT=''';(()=>{const make=window.mxmedM7WS03;window.__recoveryQA=[];window.mxmedM7WS03=(...args)=>{const ws=make(...args);window.__recoveryQA.push(ws);return ws;};})();'''
METRICS='''()=>{const rect=s=>{const r=document.querySelector(s).getBoundingClientRect();return [r.x,r.y+scrollY,r.width,r.height]};return Object.fromEntries(['#p-expediente>.head>.exp-hdr','.m7-workspace-head','.m7-workspace-sections','[data-m7-step-title]','.vis29-register','.m7-measurement-fields','.vis29-current','.vis04-progression'].map(s=>[s,rect(s)]).concat([['pageHeight',document.documentElement.scrollHeight],['horizontalOverflow',document.documentElement.scrollWidth>innerWidth+1]]));}'''
checks={};geometry={};errors=[];writes=[]
def check(name,condition=True):
 assert condition,name
 checks[name]='PASS';print('PASS '+name,flush=True)
def snapshot():return hashlib.sha256(subprocess.check_output(['mysql','-N','mxmed_director_review_lon07c','-e','SELECT * FROM clinical_observations WHERE encounter_id=1016 ORDER BY observation_id'])).hexdigest()
def settle(page):page.evaluate('async()=>{await document.fonts.ready;document.activeElement?.blur();scrollTo({top:0,left:0,behavior:"instant"});await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));}')
def ready(page):
 expect(page.locator('[data-m7-step-title]')).to_have_text('Signos vitales y otros valores clínicos',timeout=55000)
 expect(page.locator('[data-vitalref-hint]')).to_be_visible()
def capture(page,code):
 page.locator('[data-m7-measurement-code]').select_option(code)
 if code=='blood_pressure':
  page.locator('[data-m7-measurement-systolic]').fill('128');page.locator('[data-m7-measurement-diastolic]').fill('76')
 else:page.locator('[data-m7-measurement-value]').fill('37.2' if code=='temperature' else '72')
 page.locator('[data-m7-measurement-source]').select_option('direct_measurement')
def state(page):return page.evaluate('''()=>({instances:window.__recoveryQA.length,dirty:window.__recoveryQA.at(-1).isDirty(),draft:sessionStorage.getItem('mxmed.m7.ws03.draft:enc:1016:measurements'),code:document.querySelector('[data-m7-measurement-code]').value,values:['value','systolic','diastolic'].map(k=>document.querySelector('[data-m7-measurement-'+k+']').value),source:document.querySelector('[data-m7-measurement-source]').value,disabled:[...document.querySelector('[data-m7-measurements-form]').elements].filter(n=>!n.closest('[data-vis30-measurement-draft]')).map(n=>n.disabled)})''')
with sync_playwright() as p:
 before=snapshot()
 for engine in [p.webkit,p.chromium]:
  browser=engine.launch()
  for w,h in ([(1440,900),(1366,768),(820,1180),(390,844)] if engine.name=='webkit' else [(1440,900)]):
   label=f'{engine.name} {w}x{h}';page=browser.new_page(viewport={'width':w,'height':h})
   page.on('dialog',lambda d:d.accept());page.on('pageerror',lambda e:errors.append(str(e)))
   page.on('request',lambda r:writes.append(r.url) if '/api/clinical/' in r.url and r.method not in ['GET','HEAD'] else None)
   def instrument(route):
    response=route.fetch();route.fulfill(response=response,body=response.text()+INSTRUMENT)
   page.route('**/assets/js/clinical/m7-ws03.js*',instrument)
   page.goto(URL,wait_until='commit');ready(page)
   # Compare current capture with accepted source, using the same review bootstrap.
   old=browser.new_page(viewport={'width':w,'height':h});old.on('dialog',lambda d:d.accept())
   old_js=subprocess.check_output(['git','show',START+':assets/js/clinical/m7-ws03.js'],cwd=ROOT)
   old_html=subprocess.check_output(['git','show',START+':index.html'],cwd=ROOT,text=True)
   old.route('**/assets/js/clinical/m7-ws03.js*',lambda r:r.fulfill(body=old_js,content_type='text/javascript'))
   def html(route):
    runtime=route.fetch().text();old_body=old_html[old_html.index('<form class="vis29-register"'):old_html.index('</form>',old_html.index('<form class="vis29-register"'))+7]
    start=runtime.index('<form class="vis29-register"');end=runtime.index('</form>',start)+7
    route.fulfill(body=runtime[:start]+old_body+runtime[end:],content_type='text/html')
   old.route('**/index.html?*',html);old.goto(URL,wait_until='commit');ready(old)
   for code,field,placeholder,anchor_up in [('heart_rate','value','Ref. 60–100','81'),('temperature','value','Ref. 36.5–37.3','37.0'),('blood_pressure','systolic','Ref. <120','121')]:
    capture(page,code);capture(old,code);settle(old);baseline=old.evaluate(METRICS);settle(page)
    check(label+' '+code+' pre-reload geometry equals accepted source',page.evaluate(METRICS)==baseline)
    saved=state(page);page.reload(wait_until='commit');ready(page)
    input=page.locator('[data-m7-measurement-'+field+']');expect(input).to_be_enabled();expect(page.locator('[data-m7-measurement-code]')).to_have_value(code)
    restored=state(page);check(label+' '+code+' auto restore exact real draft active dirty',restored['instances']==1 and restored['dirty'] and restored['values']==saved['values'] and restored['source']==saved['source'] and restored['draft']==saved['draft'] and not any(restored['disabled']))
    expect(page.locator('[data-vis30-measurement-draft]')).to_be_hidden();expect(page.locator('[data-m7-measurement-recovered-status]')).to_be_visible();expect(page.locator('[data-m7-measurement-recovered-status]')).to_have_text('Captura recuperada')
    settle(page);metrics=page.evaluate(METRICS);geometry[label+' '+code]={'accepted':baseline,'auto_restored':metrics};check(label+' '+code+' auto restore frozen geometry',metrics==baseline)
    # Opening and closing unrelated context must leave form state unchanged.
    before_context=state(page);page.locator('[data-vis29-prior-open]').click();page.locator('[data-vis29-prior-close]').first.click();check(label+' '+code+' unrelated click does not alter active draft',state(page)==before_context)
    if code=='heart_rate':input.press('ArrowUp');expect(input).to_have_value('73');input.press('ArrowDown');expect(input).to_have_value('72')
    page.locator('[data-m7-measurement-new]').click();expect(input).to_have_value('');expect(input).to_have_attribute('placeholder',placeholder);expect(input).to_be_enabled()
    check(label+' '+code+' discard clean and no recovery UI',not state(page)['dirty'] and state(page)['draft'] is None and state(page)['values']==['','',''])
    expect(page.locator('[data-m7-measurement-recovered-status]')).to_be_hidden();expect(page.locator('[data-m7-measurement-save]')).to_be_disabled()
    input.locator('..').locator('[data-vitalref-step="1"]').click();expect(input).to_have_value(anchor_up);page.locator('[data-m7-measurement-new]').click()
    check(label+' '+code+' reference anchor immediately active after discard')
    old.locator('[data-m7-measurement-new]').click()
   old.close()
   # The full requested cycle includes actual drafts before each reload.
   for cycle in range(1,6):
    capture(page,'heart_rate');page.reload(wait_until='commit');ready(page);input=page.locator('[data-m7-measurement-value]');expect(input).to_have_value('72');expect(input).to_be_enabled();input.press('ArrowUp');expect(input).to_have_value('73');input.press('ArrowDown');expect(input).to_have_value('72')
    page.locator('[data-m7-measurement-new]').click();page.locator('[data-m7-measurement-code]').select_option('temperature');page.reload(wait_until='commit');ready(page)
    expect(input).to_have_value('');expect(input).to_be_enabled();expect(input).to_have_attribute('placeholder','Ref. 36.5–37.3');expect(page.locator('[data-vis30-measurement-draft]')).to_be_hidden();expect(page.locator('[data-m7-measurement-recovered-status]')).to_be_hidden()
    check(label+' full recovery cycle '+str(cycle),state(page)['instances']==1 and not state(page)['dirty'] and state(page)['draft'] is None)
   capture(page,'temperature');page.reload(wait_until='commit');ready(page);settle(page);page.screenshot(path=str(OUT/(label.replace(' ','-')+'-auto-restored.png')),full_page=w<1200);page.close()
  browser.close()
 check('no automatic clinical writes',not writes);check('canonical observations unchanged',snapshot()==before);check('no JavaScript errors',not errors)
 (OUT/'r2-browser-report.json').write_text(json.dumps({'checks':checks,'geometry':geometry,'clinical_writes':writes,'javascript_errors':errors,'canonical_observations_sha256':before},indent=2));print('STEP2_DRAFT_R2_BROWSER_GATE=PASS')
