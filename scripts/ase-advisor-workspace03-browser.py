"""Focused WORKSPACE03 browser + persisted-source checks on a fresh owned runtime."""
import importlib.util,json,pathlib,time
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
ws=module('ws03_ws02',ROOT/'scripts/ase-advisor-workspace02-browser.py')
review,old=ws.review,ws.old
SUMMARY='physician_pending_summary_read'
CAPS=ws.CAPS+ws.MEDIA+[SUMMARY]
def tab(page,id):page.locator('#tab-'+id).click()
def pending(page,doctor=None,application=None):
 path=ws.API+'?mode=pending'
 if doctor:path+='&doctor_id='+doctor
 if application:path+='&request_id='+application
 r=ws.previous.read(page,path);assert r['status']==200,r;return r['body']['data']
def open_request(page,id):
 data=ws.snapshot(page,id)['request']
 if page.locator('#search-toggle').is_visible():page.locator('#search-toggle').click()
 page.locator('#physician-search').fill(data['label']);page.locator('#physician-search-form button').click()
 expect(page.locator('#discovery-list')).to_have_attribute('aria-busy','false')
 if data['doctor_id']:
  page.locator('#discovery-list [data-doctor-id="'+data['doctor_id']+'"]').click();tab(page,'requests')
  page.locator('#request-list [data-request-id="'+id+'"]').click()
 else:page.locator('#discovery-list [data-request-id="'+id+'"]').click()
 expect(page.locator('#request-detail')).to_have_attribute('data-request-id',id)
def run(r,check,cli,races):
 owned,origin,sql,out=[r[k] for k in ['owned','origin','sql','OUT']]
 out.mkdir(parents=True,exist_ok=True);(out/'screens').mkdir(exist_ok=True)
 record=json.loads((owned/'oper09-advisor.json').read_text());fixture=json.loads((owned/'workspace02-fixture.json').read_text());password=(owned/'applicant-password').read_text().strip()
 results={'checks':[],'responsive':[],'page_errors':[]}
 def observed(name,value):
  check(name,value);results['checks'].append({'name':name,'pass':bool(value)});(out/'browser-result.json').write_text(json.dumps(results,indent=2)+'\n')
 with sync_playwright() as pw:
  db,dc,d=review.native_browser(pw);ab,ac,a=review.native_browser(pw)
  a.on('pageerror',lambda e:results['page_errors'].append(str(e)))
  def grants(desired):
   review.login(d,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR);old.selected(d,record)
   for group in ['requests','media']:
    if not d.locator('#use-'+group).is_visible():d.locator('[data-expand="use-'+group+'"]').click()
   changed=False
   for cap in CAPS:
    box=d.locator('[data-capability="'+cap+'"]');value=cap in desired
    if box.is_checked()!=value:box.set_checked(value);changed=True
   if changed:
    d.locator('#save-permissions').click();d.locator('#accept-confirmation').click();expect(d.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=25000)
   current={x[0] for x in sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE'",(record['account'],))}
   assert current==set(desired),current
  grants(CAPS)
  observed('Director delegates summary and operational grants through canonical UI',True)
  if not sql('SELECT factor_id FROM auth_staff_mfa_factors WHERE account_id=%s',(record['account'],)):cli('scripts/ase-advisor-workspace02-fixture.php','enroll_advisor')
  review.login(a,origin,record['email'],password);expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false',timeout=20000)
  initial=pending(a,'workspace_aurora');aurora=initial['context']
  observed('Canonical physician grouping joins media and claim exactly once',aurora['visible_count']==int(sql("SELECT COUNT(*) FROM media_review_submissions WHERE owner_id='workspace_aurora' AND review_status='PENDING_REVIEW'")[0][0])+1 and a.locator('#discovery-list [data-doctor-id="workspace_aurora"]').count()==1)
  apps=[g['application_id'] for g in initial['groups'] if g['application_id']]
  observed('Unmaterialized applications retain separate canonical identities',len(apps)==len(set(apps)) and fixture['new_ready']['application_id'] in apps and fixture['evidence_stale']['application_id'] in apps)
  observed('Global badge counts actionable work items, not physician cards',a.locator('#pending-count').inner_text()==str(initial['actionable_count']) and initial['actionable_count']>initial['group_count'])
  a.locator('#discovery-list [data-doctor-id="workspace_aurora"]').click();expect(a.locator('#physician-name')).to_contain_text('Aurora')
  observed('Selection collapses inbox and preserves exact profile identity',not a.locator('#discovery').is_visible() and a.locator('#work-panel').get_attribute('data-doctor-id')=='workspace_aurora')
  observed('All eleven top tabs exist in one nonwrapping row',a.locator('#section-tabs [role=tab]').count()==11 and a.locator('#section-tabs').evaluate('(e)=>new Set([...e.children].map(c=>c.offsetTop)).size===1'))
  tab(a,'seo');observed('Unsupported tab is informational and grants no operation',a.locator('#restricted-panel').is_visible() and a.locator('#restricted-panel input, #restricted-panel form').count()==0)
  # Resolve one real image, retaining three Media items plus the claim for review.
  media_marker=owned/'workspace03-media-transition.json'
  if not media_marker.exists():
   before=pending(a,'workspace_aurora');tab(a,'images')
   photo=sql("SELECT submission_id FROM media_review_submissions WHERE owner_id='workspace_aurora' AND purpose='DOCTOR_PROFILE_PHOTO' AND review_status='PENDING_REVIEW'")[0][0]
   a.locator('#media-items [data-submission-id="'+photo+'"]').click();expect(a.locator('#approve-image')).to_be_enabled();a.once('dialog',lambda dialog:dialog.accept());a.locator('#approve-image').click()
   expect(a.locator('#media-items [data-submission-id="'+photo+'"]')).to_have_count(0,timeout=20000)
   after=pending(a,'workspace_aurora');media_marker.write_text(json.dumps({'before':before['actionable_count'],'after':after['actionable_count'],'before_profile':before['context']['visible_count'],'after_profile':after['context']['visible_count'],'submission_id':photo}))
  m=json.loads(media_marker.read_text());after=pending(a,'workspace_aurora')
  observed('Committed Media approval decreases global, header and tab counts once',m['before']-m['after']==1 and m['before_profile']-m['after_profile']==1 and after['context']['visible_count']==4 and after['context']['sections']['images']['visible_count']==3)
  observed('Committed counts are rendered in header, tab and red global badge', '4 pendientes' in a.locator('#physician-statuses').inner_text() and a.locator('#tab-images .tab-count').inner_text()=='3' and a.locator('#pending-count').inner_text()==str(after['actionable_count']))
  a.reload();expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false');card=a.locator('#discovery-list [data-doctor-id="workspace_aurora"]');expect(card).to_have_count(1)
  observed('Four pending actions render one physician card with four visible items','4 pendientes visibles' in card.inner_text());card.click()
  tab(a,'history');expect(a.locator('.history-entry')).not_to_have_count(0)
  observed('Completed Media action remains in canonical history',m['submission_id'] in json.dumps(ws.previous.read(a,ws.API+'?mode=history&doctor_id=workspace_aurora')['body']))
  tab(a,'summary');before=pending(a,'workspace_aurora');tab(a,'images');tab(a,'summary')
  observed('Navigation does not decrement pending counters',pending(a,'workspace_aurora')['context']['visible_count']==before['context']['visible_count'])
  grants(ws.MEDIA+[SUMMARY]);a.reload();expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false')
  summary=pending(a,'workspace_aurora');encoded=json.dumps(summary)
  observed('Summary-only request count stays bounded to existing Media follow-up profiles',summary['context']['sections']['requests']=={'visible_count':1,'actionable_count':0,'summary_only':True} and summary['group_count']==2 and summary['actionable_count']==7)
  observed('Summary discloses no applicant, request IDs, evidence, reasons or new applications',all(v['application_id'] not in encoded and v['account_id'] not in encoded for v in fixture.values()) and not any(k in encoded for k in ['evidence_request','information_history','professional_license','resolution_id']))
  a.locator('#discovery-list [data-doctor-id="workspace_aurora"]').click();tab(a,'requests')
  observed('Restricted tab displays authorized aggregate without protected panel',a.locator('#restricted-panel').is_visible() and '1 pendiente' in a.locator('#restricted-summary').inner_text() and not a.locator('#request-detail').is_visible())
  observed('Summary permission does not authorize request detail or evidence',ws.previous.read(a,ws.API+'?mode=read&request_id='+fixture['claim_ready']['application_id'])['status']==403)
  observed('Summary rejects unrelated physician and mismatched application context',ws.previous.read(a,ws.API+'?mode=pending&doctor_id=workspace_out_of_scope')['status']==403 and ws.previous.read(a,ws.API+'?mode=pending&doctor_id=workspace_bruno&request_id='+fixture['claim_ready']['application_id'])['status']==403)
  a.screenshot(path=str(out/'screens/summary-only-request.png'))
  grants(ws.MEDIA);a.locator('#pending-toggle').click();expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false')
  revoked=pending(a,'workspace_aurora');observed('Summary revocation immediately removes restricted counts server-side','requests' not in revoked['context']['sections'] and revoked['context']['visible_count']==3)
  a.reload();expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false');a.locator('#discovery-list [data-doctor-id="workspace_aurora"]').click();tab(a,'requests')
  observed('Unauthorized UI has no restricted request badge or count',a.locator('#tab-requests .tab-count').count()==0 and 'No hay un resumen' in a.locator('#restricted-summary').inner_text())
  grants([SUMMARY]);empty=pending(a);observed('Summary grant alone supplies no global physician visibility',empty['groups']==[] and empty['awareness_groups']==[] and empty['actionable_count']==0)
  grants(ws.CAPS+[SUMMARY]);only_requests=pending(a,'workspace_aurora')
  observed('Request follow-up permits scoped Media summary without Media read',only_requests['context']['sections']['images']['summary_only'] and only_requests['context']['sections']['images']['visible_count']==3 and not any(g['doctor_id']=='workspace_bruno' for g in only_requests['groups']))
  observed('Summary cannot fetch protected Media or Director administration',ws.previous.read(a,'/api/internal/workspace/media.php?mode=options&doctor_id=workspace_aurora')['status']==403 and ws.previous.read(a,'/api/internal/team/read.php')['status']==403)
  nb,nc,n=review.native_browser(pw);review.login(n,origin,'qa_advisor@example.test',(owned/'fixture-password').read_text().strip())
  negative=pending(n,'workspace_aurora');observed('Independent Advisor without summary cannot infer restricted Media counts','images' not in negative['context']['sections'] and negative['actionable_count']==0)
  nb.close()
  grants(CAPS);a.reload();expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false')
  def resolve(key,decision):
   id=fixture[key]['application_id'];before=ws.snapshot(a,id)['request'];open_request(a,id)
   if before['state']!=decision:
    if decision=='APPROVED':ws.inspect_evidence(a)
    code=ws.fresh_mfa(a,owned,sql,record['account'])
    a.locator('#'+{'APPROVED':'approve-request','NEEDS_INFORMATION':'request-information','REJECTED':'reject-request'}[decision]).click()
    a.locator('#request-reason').fill('Verificación ficticia WORKSPACE03');a.locator('#request-password').fill(password)
    a.locator('#request-mfa').fill(code);a.locator('#request-confirmation').check();a.locator('#confirm-request-decision').click()
    expect(a.locator('#request-status')).to_have_text({'APPROVED':'Aprobada','NEEDS_INFORMATION':'Información solicitada','REJECTED':'Rechazada'}[decision],timeout=30000)
   value=ws.snapshot(a,id)['request'];assert value['state']==decision;return value
  claim_id=fixture['claim_approve']['doctor_id'];profile_before=sql('SELECT doctor_id,display_name,profile_status,plan_code FROM profiles_doctors WHERE doctor_id=%s',(claim_id,))[0]
  claim=resolve('claim_approve','APPROVED');observed('Claim regression preserves original canonical free profile',claim['doctor_id']==claim_id and profile_before==sql('SELECT doctor_id,display_name,profile_status,plan_code FROM profiles_doctors WHERE doctor_id=%s',(claim_id,))[0])
  new=resolve('new_approve','APPROVED');new_id=fixture['new_approve']['application_id'];new_context=pending(a,new['doctor_id'],new_id)['context']
  observed('New-profile regression materializes exactly one hidden free profile',sql('SELECT COUNT(*) FROM auth_physician_profile_request_profiles WHERE origin_id=%s',(new_id,))[0][0]==1 and sql('SELECT profile_status,plan_code FROM profiles_doctors WHERE doctor_id=%s',(new['doctor_id'],))[0]==('hidden','free'))
  observed('Materialized application maintains exact association without duplicate pending group',new_context['key']=='physician:'+new['doctor_id'] and new_context['visible_count']==0 and all(g['application_id']!=new_id and g['doctor_id']!=new['doctor_id'] for g in pending(a)['groups']))
  tab(a,'history');expect(a.locator('.history-entry')).not_to_have_count(0)
  observed('Application history survives materialization with canonical actor and reference',new_id in a.locator('#history-list').inner_text())
  information=resolve('information','NEEDS_INFORMATION');info_id=fixture['information']['application_id'];info=pending(a,application=info_id)
  waiting=owned/'workspace03-waiting-observation.json'
  if information['waiting_applicant']:
   waiting.write_text(json.dumps({'request_id':info_id,'state':information['state'],'actionable':information['actionable'],'context_visible':info['context']['visible_count'],'context_actionable':info['context']['actionable_count'],'in_actionable_queue':any(g['application_id']==info_id for g in info['groups'])}))
  w=json.loads(waiting.read_text())
  observed('Information request is unresolved but leaves Advisor actionable inbox',w['request_id']==info_id and not w['actionable'] and w['context_visible']==1 and w['context_actionable']==0 and not w['in_actionable_queue'])
  # Actual authenticated applicant response restores one review item, no new profile.
  ib,ic,app=review.native_browser(pw);review.login(app,origin,fixture['information']['email'],password,'/public/identity/perfil-medico.php?application='+info_id)
  data=ws.previous.read(app,ws.API+'?mode=applicant&request_id='+info_id)['body']['data']
  if not data.get('response'):
   # Reuse original applicant interface when a response has not yet been submitted.
   box=app.locator('textarea');
   if box.count() and box.is_visible():
    box.fill('Respuesta ficticia para revisión de conteos.');app.locator('form').filter(has=box).locator('button[type=submit]').click();app.wait_for_timeout(600)
  ib.close()
  answered=ws.snapshot(a,info_id)['request'];observed('Applicant response restores actionability without duplicate item',answered['actionable'] and pending(a,application=info_id)['context']['actionable_count']==1)
  # Leave a separate waiting case for visual review.
  resolve('new_reject','NEEDS_INFORMATION')
  open_request(a,fixture['claim_ready']['application_id']);exact=ws.previous.read(a,'/api/internal/workspace/read.php?doctor_id=workspace_bruno')['body']['data']['pending_items'][0]['submission_id']
  observed('Cross-physician protected Media binding remains denied',old.fetch_status(a,'/api/internal/workspace/media.php?mode=review&doctor_id=workspace_aurora&submission_id='+exact)==404)
  observed('Selected request cannot claim another physician context',ws.previous.read(a,ws.API+'?mode=pending&doctor_id=workspace_bruno&request_id='+fixture['claim_ready']['application_id'])['status']==409)
  for engine in ['chromium','webkit']:
   b=getattr(pw,engine).launch(headless=True)
   for width,height in [(1440,900),(1366,768),(390,844)]:
    c=b.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});page=c.new_page();page.on('pageerror',lambda e:results['page_errors'].append(str(e)))
    review.login(page,origin,record['email'],password);expect(page.locator('#discovery-list')).to_have_attribute('aria-busy','false')
    page.locator('#discovery-list [data-doctor-id="workspace_aurora"]').click();expect(page.locator('#section-tabs [role=tab]')).to_have_count(11)
    page.locator('#more-toggle').click();expect(page.locator('#more-menu button')).to_have_count(11);page.locator('#more-menu [data-section=seo]').click();expect(page.locator('#restricted-panel')).to_be_visible()
    page.locator('#tab-seo').press('End');expect(page.locator('#tab-history')).to_be_focused();expect(page.locator('#history-panel')).to_be_visible()
    page.locator('#tab-history').press('Home');expect(page.locator('#tab-summary')).to_be_focused()
    geometry=page.evaluate('({width:innerWidth,scroll:document.documentElement.scrollWidth,tabRows:new Set([...document.querySelectorAll("#section-tabs button")].map(e=>e.offsetTop)).size})')
    observed(f'{engine} {width}x{height}: complete overflow navigation, keyboard and counters',geometry['width']>=geometry['scroll'] and geometry['tabRows']==1 and '4 pendientes' in page.locator('#physician-statuses').inner_text())
    page.screenshot(path=str(out/'screens'/f'{engine}-{width}.png'));results['responsive'].append({'engine':engine,'width':width,'height':height,'geometry':geometry});c.close()
   b.close()
  # Final live review: operations remain delegated, request details deliberately restricted.
  grants(ws.MEDIA+[SUMMARY]);review.login(a,origin,record['email'],password);expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false');a.locator('#discovery-list [data-doctor-id="workspace_aurora"]').click();tab(a,'requests')
  geometry=a.evaluate('({width:innerWidth,height:innerHeight,outerHeight,availableHeight:screen.availHeight,scroll:document.documentElement.scrollWidth})')
  observed('Native delivered viewport remains within physical screen',geometry['outerHeight']<=geometry['availableHeight'] and geometry['scroll']<=geometry['width'])
  observed('No browser JavaScript errors',results['page_errors']==[])
  a.screenshot(path=str(out/'screens/native-summary.png'));d.screenshot(path=str(out/'screens/director-grants.png'))
  results['native']=geometry;results['result']='PASS';(out/'browser-result.json').write_text(json.dumps(results,indent=2)+'\n')
  ab.close();db.close()
