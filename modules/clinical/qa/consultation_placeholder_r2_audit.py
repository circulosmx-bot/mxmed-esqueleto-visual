"""Read-only audit of all Consultation writable controls and action dialogs in WebKit."""
import json,hashlib,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
import os
OUT=Path(os.environ.get('PLACEHOLDER_R2_ARTIFACTS','/tmp/mxmed-placeholder-r2'));OUT.mkdir(parents=True,exist_ok=True)
URL='http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_step=reason&review_placeholders=clean'
CSS="(n)=>{const s=getComputedStyle(n,'::placeholder');return Object.fromEntries(['fontFamily','fontSize','fontWeight','lineHeight','color','opacity'].map(k=>[k,s[k]]))}"
def records():return hashlib.sha256(subprocess.check_output(['mysql','-N','mxmed_director_review_lon07c','-e','SELECT * FROM clinical_encounter_sections WHERE encounter_id=1016 ORDER BY section_type; SELECT * FROM clinical_observations WHERE encounter_id=1016 ORDER BY observation_id'])).hexdigest()
before=records();errors=[];writes=[];report={};field_map={};vital_clipping=[]
with sync_playwright() as p:
 browser=p.webkit.launch()
 for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
  print(f'AUDIT {w}x{h}',flush=True)
  page=browser.new_page(viewport={'width':w,'height':h},timezone_id='America/Mexico_City');page.on('pageerror',lambda e:errors.append(str(e)))
  def guard(route):
   r=route.request
   if r.method not in ['GET','HEAD'] and not r.url.endswith('/patient-id/resolve'):writes.append(r.url);route.abort()
   else:route.continue_()
  page.route('**/api/clinical/**',guard);page.goto(URL,wait_until='commit');expect(page.locator('[data-m7-section="reason"]')).to_have_attribute('aria-current','true',timeout=55000)
  page.locator('[data-m7-section="reason"]').click();authority=page.locator('[data-m7-editor-text]').evaluate(CSS);count=0
  def audit(selector, group=None):
   global count
   for n in page.locator(selector).all():
    if n.evaluate('n=>n.tagName==="SELECT"||["radio","checkbox","file","date","datetime-local","time"].includes(n.type)'): continue
    actual=n.evaluate(CSS);expected=authority.copy()
    if n.evaluate('(n)=>n.tagName')=='INPUT':assert actual.pop('lineHeight') in ['normal',expected.pop('lineHeight')]
    assert actual==expected,(w,h,selector,actual,expected)
    count+=1
   if group:
    field_map.setdefault(f'{w}x{h}',{})[group]=page.locator(selector.replace('[placeholder]','')).evaluate_all('''ns=>ns.filter(n=>!n.readOnly&&n.type!=="hidden").map(n=>({tag:n.tagName,type:n.type,key:n.id||[...n.attributes].filter(a=>a.name.startsWith("data-")).map(a=>a.name+"="+a.value).join(" "),value:n.value,placeholder:n.placeholder||"",visible:n.getBoundingClientRect().height>0,shownText:n.tagName==="SELECT"?n.selectedOptions[0]?.textContent:(n.value||n.placeholder||""),classification:n.value?"REAL_CLINICAL_VALUE":(n.placeholder||n.tagName==="SELECT")?"PLACEHOLDER_GUIDANCE":null,placeholderShown:n.matches(":placeholder-shown")}))''')
  for step in ['reason','measurements','exam','assessment','plan','documents','finalize']:
   page.locator(f'[data-m7-section="{step}"]').click()
   audit('#m7-workspace input:not([type=hidden]),#m7-workspace textarea,#m7-workspace select',step)
  page.locator('[data-m7-section="measurements"]').click()
  code=page.locator('[data-m7-measurement-code]')
  for code_name in ['respiratory_rate','oxygen_saturation','pain','height','weight','waist']:
   if code.locator(f'option[value="{code_name}"]:not([disabled])').count()==0: continue
   code.select_option(code_name)
   for n in page.locator('.vis29-register input[placeholder]:visible').all():
    vital_clipping.append(n.evaluate('''n=>{const s=getComputedStyle(n),p=getComputedStyle(n,"::placeholder"),c=document.createElement("canvas").getContext("2d");c.font=p.fontSize+" "+p.fontFamily;return {viewport:innerWidth,copy:n.placeholder,value:n.value,font:p.fontSize,textWidth:c.measureText(n.placeholder).width,usableWidth:n.clientWidth-parseFloat(s.paddingLeft)-parseFloat(s.paddingRight),height:n.clientHeight}}'''))
  audit('.vis29-register input,.vis29-register select','Step2 alternative numeric types')
  page.locator('[data-m7-section="plan"]').click();expect(page.locator('[data-plan02b]')).to_be_visible()
  for kind in ['orders','prescription','appointment','followup']:
   page.locator(f'[data-plan02b] [data-ns="{kind}"]').click();expect(page.locator('.plan02b-modal')).to_be_visible();audit('.plan02b-modal input,.plan02b-modal textarea,.plan02b-modal select','Plan '+kind)
   if kind=='appointment':
    page.locator('.plan02b-modal [name=ns-mode][value=new]').check();audit('.plan02b-modal input,.plan02b-modal textarea,.plan02b-modal select','Plan appointment new')
   page.locator('.plan02b-modal [data-modal-cancel]').click();expect(page.locator('.plan02b-modal')).to_have_count(0)
  page.locator('[data-m7-section="documents"]').click();page.locator('[data-docux-attach]').click();expect(page.locator('[data-docux-upload]')).to_be_visible();audit('[data-docux-upload] input:not([type=hidden])','Documents attachment');page.locator('[data-docux-upload] [data-modal-cancel]').click();expect(page.locator('[data-docux-upload]')).to_be_hidden()
  page.locator('[data-m7-section="finalize"]').click();expect(page.locator('[data-m7-terminal]')).to_be_visible();audit('[data-m7-terminal] textarea','Finalization reasons and corrections')
  report[f'{w}x{h}']={'qa':'PASS','placeholder_controls_audited':count,'plan_dialogs':'PASS','document_dialog':'PASS','finalization_panel':'PASS'};page.close()
 browser.close()
assert not errors and not writes and records()==before,(errors,writes)
assert all(n['textWidth']<=n['usableWidth']+1 and float(n['font'][:-2])<=n['height'] for n in vital_clipping),vital_clipping
(OUT/'placeholder-audit.json').write_text(json.dumps({'qa':'PASS','sizes':report,'field_map':field_map,'vitalref_clipping':vital_clipping,'javascript_errors':errors,'unexpected_writes':writes,'director_records_unchanged':True},indent=2));print('R2_ALL_WRITABLE_FIELDS_AND_DIALOGS_QA=PASS')
