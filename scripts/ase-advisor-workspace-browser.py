"""Targeted R2 end-to-end checks. All reads/writes use the new owned instance."""
import json,time,pathlib,importlib.util
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
review=module('r2_review',ROOT/'scripts/ase-advisor-workspace-review.py')
old=module('r2_admission',ROOT/'scripts/ase-adm02-oper09-browser.py')
CAPS=['media_review_read','media_review_source_download','media_review_corrected_upload','media_review_approve','media_review_improve','media_review_request_replacement']

def read(page,path):
 return page.evaluate('''async path=>{const r=await fetch(path,{cache:'no-store'});return {status:r.status,body:await r.json().catch(()=>null)};}''',path)

def post(page,path,body):
 return page.evaluate('''async ([path,body])=>{const r=await fetch(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});return {status:r.status,body:await r.json().catch(()=>null)};}''',[path,body])

def search(page,name):
 page.locator('#physician-search').fill(name);page.locator('#physician-search-form button').click()
 expect(page.locator('#discovery-list [data-doctor-id]')).to_have_count(1)
 page.locator('#discovery-list [data-doctor-id]').click();expect(page.locator('#physician-name')).to_contain_text(name)

def tab(page,name):
 direct=page.get_by_role('tab',name=name,exact=True)
 if direct.count() and direct.is_visible():direct.click()
 else:
  page.locator('#more-toggle').click();page.locator('#more-menu').get_by_role('menuitemradio',name=name,exact=True).click()

def item(page,sid):
 tab(page,'Imágenes');page.locator('#media-items [data-submission-id="'+sid+'"]').click()
 expect(page.locator('#review-image')).to_be_visible()
 expect(page.locator('#review-image')).to_have_js_property('complete',True)

def run(r,check):
 owned,origin,sql,out=[r[k] for k in ['owned','origin','sql','OUT']];(out/'screens').mkdir(exist_ok=True)
 record=json.loads((owned/'oper09-advisor.json').read_text());fixture=json.loads((owned/'workspace-media.json').read_text())
 def sid(doctor,purpose,index=0):return sorted(x['submission_id'] for x in fixture['items'] if x['owner_id']==doctor and x['purpose']==purpose)[index]
 aurora,bruno='workspace_aurora','workspace_bruno';photo=sid(aurora,'DOCTOR_PROFILE_PHOTO');logo=sid(bruno,'PHYSICIAN_PERSONAL_LOGO');gallery=sid(aurora,'DOCTOR_GALLERY');replacement=sid(aurora,'DOCTOR_GALLERY',1)
 endpoint='/api/internal/workspace/read.php';media='/api/internal/workspace/media.php'
 with sync_playwright() as pw:
  browser,dc,d=review.native_browser(pw);review.login(d,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
  old.selected(d,record)
  def grants(caps):
   if not d.locator('#use-media').is_visible():d.locator('[data-expand="use-media"]').click()
   changed=False
   for cap in CAPS:
    box=d.locator('[data-capability="'+cap+'"]');enabled=cap in caps
    if box.is_checked()!=enabled:box.set_checked(enabled);changed=True
   if changed:
    d.locator('#save-permissions').click();d.locator('#accept-confirmation').click();expect(d.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000)
   d.reload();expect(d.locator('#add-member')).to_be_enabled();old.selected(d,record)
   actual={x[0] for x in sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE'",(record['account'],))}
   check('Director grants/revokes exact capabilities through canonical UI',actual==set(caps))
  grants(['media_review_read'])
  check('Anonymous and non-staff Identity accounts cannot read workspace',r['request'](None,endpoint,'GET')[0]==401 and r['request']('outsider',endpoint,'GET')[0]==403)
  ab,ac,a=review.native_browser(pw);page_errors=[];a.on('pageerror',lambda error:page_errors.append(str(error)))
  review.login(a,origin,record['email'],(owned/'applicant-password').read_text().strip())
  expect(a.locator('#staff-name')).to_contain_text('Irene');expect(a.locator('#staff-role')).to_contain_text('Asesor')
  snap=read(a,endpoint);check('Legitimate Advisor login exposes real role and accessible pending count',snap['status']==200 and snap['body']['data']['actor']['access_level']=='ADVISOR' and snap['body']['data']['count']>0)
  check('No initial editing context before physician selection',not a.locator('#physician-workspace').is_visible())
  check('Search excludes unrelated canonical profile',read(a,endpoint+'?query=Oculto')['body']['data']['physicians']==[])
  check('Direct out-of-scope physician lookup denies metadata',read(a,endpoint+'?doctor_id=workspace_out_of_scope')['status']==404)
  search(a,'Aurora');check('Search selects exact canonical physician with source specialty and location',a.locator('#physician-description').inner_text().find('Medicina general')>=0 and a.locator('#physician-description').inner_text().find('Ciudad Ficticia Norte')>=0)
  item(a,photo if sql('SELECT review_status FROM media_review_submissions WHERE submission_id=%s',(photo,))[0][0]=='PENDING_REVIEW' else sid(aurora,'PHYSICIAN_PERSONAL_LOGO'))
  check('Read-only grant loads real REVIEW and hides mutation/original controls',a.locator('#review-image').evaluate('(e)=>e.complete&&e.naturalWidth>0') and not a.locator('#download-original').is_visible() and not a.locator('#approve-image').is_visible())
  source=media+'?doctor_id='+bruno+'&submission_id='+logo+'&mode=source'
  check('Ungranted SOURCE is denied server-side',old.fetch_status(a,source)==403)
  denied=post(a,media+'?doctor_id='+bruno+'&mode=replacement',{'submission_id':logo,'reason_code':'OTHER','feedback':'Synthetic negative','csrf':'invalid'})
  check('Ungranted mutation is denied server-side',denied['status']==403)
  check('Cross-physician exact resource binding rejects read',old.fetch_status(a,media+'?doctor_id='+aurora+'&submission_id='+logo+'&mode=review')==404)
  check('Cross-physician original source URL rejects mismatched resource',old.fetch_status(a,media+'?doctor_id='+aurora+'&submission_id='+logo+'&mode=source')==404)
  check('Advisor cannot read Director staff directory',old.fetch_status(a,'/api/internal/team/read.php')==403)
  grants(CAPS)
  a.reload();search(a,'Bruno');item(a,logo)
  with a.expect_download() as download:a.locator('#download-original').click()
  check('Granted SOURCE download uses actual protected consumer',download.value.failure() is None)
  options=read(a,media+'?doctor_id='+bruno+'&mode=options')['body']
  check('Cross-physician mutation denied with otherwise valid CSRF',post(a,media+'?doctor_id='+aurora+'&mode=replacement',{'submission_id':logo,'reason_code':'OTHER','feedback':'Wrong physician negative','csrf':options['csrf']})['status']==404)
  check('Canonical mutation rejects invalid CSRF',post(a,media+'?doctor_id='+bruno+'&mode=replacement',{'submission_id':logo,'reason_code':'OTHER','csrf':'invalid'})['status']==403)
  check('Canonical consumer rejects additional context fields in payload',post(a,media+'?doctor_id='+bruno+'&mode=replacement',{'submission_id':logo,'reason_code':'OTHER','csrf':options['csrf'],'doctor_id':aurora})['status']==400)
  a.locator('#request-replacement').click();a.locator('#replacement-feedback').fill('Borrador sólo del médico Bruno')
  a.once('dialog',lambda dialog:dialog.dismiss());tab(a,'Resumen')
  check('Unsaved draft cancellation retains physician and edited section',a.locator('#replacement-feedback').input_value()=='Borrador sólo del médico Bruno' and a.locator('#images-panel').is_visible())
  a.once('dialog',lambda dialog:dialog.accept());tab(a,'Resumen')
  expect(a.locator('#summary-panel')).to_be_visible();check('Confirmed tab change retains exact physician and discards draft',a.locator('#physician-name').inner_text().startswith('Bruno'))
  search(a,'Aurora');item(a,gallery)
  check('Draft never follows physician switch',a.locator('#replacement-feedback').input_value()=='')
  # Delay a real response after server authorization; UI must ignore it after selection B.
  delayed=[]
  def delay(route):delayed.append((route,route.fetch()))
  a.route('**/api/internal/workspace/media.php?**mode=review**',delay)
  a.locator('#media-items [data-submission-id="'+sid(aurora,'PHYSICIAN_PERSONAL_LOGO')+'"]').click()
  a.wait_for_timeout(250);search(a,'Bruno')
  for route,response in delayed:
   try:route.fulfill(response=response)
   except Exception:pass
  a.unroute('**/api/internal/workspace/media.php?**mode=review**',delay)
  a.wait_for_timeout(200)
  check('Delayed physician A response cannot populate selected physician B',len(delayed)>0 and a.locator('#physician-name').inner_text().startswith('Bruno') and not a.locator('#media-detail').is_visible())
  a.locator('#pending-toggle').click();a.locator('#discovery-list [data-submission-id="'+logo+'"]').click();expect(a.locator('#review-image')).to_be_visible()
  check('Pending item opens exact physician, Images tab and actual item',a.locator('#physician-name').inner_text().startswith('Bruno') and a.locator('#images-panel').is_visible() and a.locator('#media-items [data-submission-id="'+logo+'"]').get_attribute('aria-pressed')=='true')
  if sql("SELECT COUNT(*) FROM platform_audit_events WHERE action='MEDIA_LOGO_IMPROVEMENT_ACCEPTED' AND resource_reference=%s",(logo,))[0][0]:
   # A resumed targeted run preserves its records. Reuse the actual correction
   # consumer to supply fresh opaque synthetic input, never reset domain SQL.
   a.locator('#corrected-file').set_input_files(str(owned/'synthetic-logo.png'));a.locator('#save-corrected').click();expect(a.locator('#corrected-file')).to_have_value('')
  # Existing GD improvement pipeline, no external provider or image generation.
  a.locator('#generate-improvement').click();expect(a.locator('#proposal-panel')).to_be_visible(timeout=20000)
  a.locator('#discard-improvement').click();expect(a.locator('#proposal-panel')).not_to_be_visible()
  a.locator('#generate-improvement').click();expect(a.locator('#proposal-panel')).to_be_visible(timeout=20000)
  a.locator('#accept-improvement').click();expect(a.locator('#proposal-panel')).not_to_be_visible()
  check('Canonical logo improvement generates, discards and accepts actual derivative',sql("SELECT COUNT(*) FROM media_review_files WHERE submission_id=%s AND role='AUTO_PROPOSAL'",(logo,))[0][0]==0)
  search(a,'Aurora');item(a,gallery);a.locator('#corrected-file').set_input_files(str(owned/'synthetic.png'));a.locator('#save-corrected').click()
  expect(a.locator('#corrected-file')).to_have_value('')
  check('Canonical corrected upload replaces review derivative with audit',sql("SELECT COUNT(*) FROM media_review_files WHERE submission_id=%s AND role='CORRECTED'",(gallery,))[0][0]==1)
  if sql('SELECT review_status FROM media_review_submissions WHERE submission_id=%s',(replacement,))[0][0]=='PENDING_REVIEW':
   item(a,replacement);a.locator('#request-replacement').click();a.locator('#replacement-reason').select_option('QUALITY_INSUFFICIENT');a.locator('#replacement-feedback').fill('Imagen sintética: enviar una versión más clara.')
   a.locator('#send-replacement').click();expect(a.locator('#media-items [data-submission-id="'+replacement+'"]')).not_to_be_visible()
  check('Replacement writer records NEEDS_WORK for selected item only',sql('SELECT review_status FROM media_review_submissions WHERE submission_id=%s',(replacement,))[0][0]=='NEEDS_WORK')
  if sql('SELECT review_status FROM media_review_submissions WHERE submission_id=%s',(photo,))[0][0]=='PENDING_REVIEW':
   item(a,photo);a.once('dialog',lambda dialog:dialog.accept());a.locator('#approve-image').click();expect(a.locator('#media-items [data-submission-id="'+photo+'"]')).not_to_be_visible()
  expect(a.locator('#published-list img')).to_be_visible()
  check('Approval publishes canonical derivative distinct from pending submissions',a.locator('#published-list img').first.evaluate('(e)=>e.complete&&e.naturalWidth>0') and sql('SELECT review_status FROM media_review_submissions WHERE submission_id=%s',(photo,))[0][0]=='APPROVED')
  expected_count=sql("SELECT COUNT(*) FROM media_review_submissions WHERE review_status='PENDING_REVIEW'")[0][0]
  expect(a.locator('#pending-count')).to_have_text(str(expected_count));check('Completed tasks removed from global pending badge',True)
  director_history=read(d,'/api/internal/team/read.php?category=ADVISOR&target='+record['account'])
  check('Director history remains valid JSON after real Media audit events',director_history['status']==200 and isinstance(director_history['body'],dict) and len(director_history['body'].get('items',[]))==1)
  cookies=ac.cookies();grants([cap for cap in CAPS if cap!='media_review_read'])
  revoked=read(a,endpoint);check('Read revocation removes all accessible metadata on existing session',revoked['body']['data']['count']==0 and revoked['body']['data']['physicians']==[] and revoked['body']['data']['sections']==[] and old.fetch_status(a,source)==403)
  if a.locator('#physician-workspace').is_visible():a.locator('#refresh-images').click()
  expect(a.locator('#physician-workspace')).not_to_be_visible()
  grants(CAPS);a.reload();search(a,'Bruno');item(a,logo)
  check('Explicit regrant restores context through current authority',old.fetch_status(a,source)==200)
  a.locator('#staff-logout').click();a.wait_for_url('**/public/identity/acceso.php?**')
  ac.add_cookies(cookies);check('Logged-out session replay denied; independent Director retained',read(a,endpoint)['status']==401 and old.fetch_status(d,'/api/internal/team/read.php')==200)
  ac.clear_cookies();review.login(a,origin,record['email'],(owned/'applicant-password').read_text().strip());search(a,'Bruno');item(a,logo)
  check('Legitimate canonical re-login restores Advisor workspace',True)
  native=a.evaluate('({width:innerWidth,height:innerHeight,outerHeight,availableHeight:screen.availHeight,scrollWidth:document.documentElement.scrollWidth})')
  check('Delivered native Chromium window fits actual screen and content width',native['outerHeight']<=native['availableHeight'] and native['scrollWidth']<=native['width'])
  a.locator('#request-replacement').click();a.locator('#send-replacement').scroll_into_view_if_needed()
  check('Native contextual action is reachable by real viewport hit test',a.locator('#send-replacement').evaluate('(e)=>{const b=e.getBoundingClientRect();return b.top>=0&&b.bottom<=innerHeight&&e.contains(document.elementFromPoint(b.left+b.width/2,b.top+b.height/2));}'))
  a.locator('#cancel-replacement').click();a.screenshot(path=str(out/'screens/native-workspace.png'))
  # One focused Director regression; accepted modal shell and footer are unchanged.
  old.personal(d,record);d.locator('#save-staff-profile').scroll_into_view_if_needed()
  check('Director panel and accepted modal footer remain reachable',d.locator('#save-staff-profile').is_visible())
  d.locator('#cancel-staff-profile').click()
  responsive=[]
  def sizes(page,engine):
   for width,height in [(1440,900),(1366,768),(390,844)]:
    page.set_viewport_size({'width':width,'height':height});page.wait_for_timeout(200)
    search(page,'Bruno');item(page,logo)
    if width==390:
     page.locator('#more-toggle').click();expect(page.locator('#more-menu')).to_be_visible();page.screenshot(path=str(out/'screens'/f'{engine}-{width}-more.png'));page.keyboard.press('Escape')
     tab(page,'Resumen');tab(page,'Imágenes');item(page,logo)
    page.locator('#request-replacement').click();page.locator('#send-replacement').scroll_into_view_if_needed()
    proof=page.evaluate('({width:innerWidth,height:innerHeight,scrollWidth:document.documentElement.scrollWidth,context:document.querySelector("#physician-name").textContent})')
    proof.update(engine=engine);responsive.append(proof)
    hit=page.locator('#send-replacement').evaluate('(e)=>{const b=e.getBoundingClientRect();return b.top>=0&&b.bottom<=innerHeight&&e.contains(document.elementFromPoint(b.left+b.width/2,b.top+b.height/2));}')
    check(f'{engine} {width}x{height}: top tabs, correct physician, no overflow, reachable action',proof['scrollWidth']<=width and proof['context'].startswith('Bruno') and hit)
    page.locator('#cancel-replacement').click();page.locator('#physician-name').scroll_into_view_if_needed();page.screenshot(path=str(out/'screens'/f'{engine}-{width}.png'))
  sizes(a,'chromium')
  wb=pw.webkit.launch();wc=wb.new_context(ignore_https_errors=True);w=wc.new_page();review.login(w,origin,record['email'],(owned/'applicant-password').read_text().strip());sizes(w,'webkit');wb.close()
  (out/'window-evidence.json').write_text(json.dumps({'native':native,'responsive':responsive,'physical_safari':'NOT_RUN','mobile_final':'INTERIM_MOBILE_SMOKE_ONLY'},indent=2))
  audit=sql("SELECT COUNT(*) FROM platform_audit_events WHERE action LIKE 'MEDIA%%'")[0][0]
  check('Media authorized mutations retain canonical audit events',audit>0)
  check('Workspace operations and canonical logout have no browser exceptions',not page_errors)
  (out/'workspace-result.json').write_text(json.dumps({'synthetic':True,'sections':['summary','images'],'capabilities':CAPS,'physicians':2,'pending_count':expected_count,'media_audit_count':audit,'authority':'separate_canonical_ADVISOR','source_head':r['BASE']},indent=2))
  ab.close();browser.close()
