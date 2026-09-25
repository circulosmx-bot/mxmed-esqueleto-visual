"""MEAS01 real HTTP/browser contract test. Requires an isolated synthetic review clone.
MEAS01_QA_DB must start mxmed_meas01_qa_; never points at a working database.
"""
import json,os,subprocess,uuid
from pathlib import Path
from playwright.sync_api import sync_playwright
DB=os.environ['MEAS01_QA_DB'];BASE=os.environ['MEAS01_QA_BASE'];ART=Path(os.environ['MEAS01_QA_ARTIFACTS'])
assert DB.startswith('mxmed_meas01_qa_') and BASE.startswith('http://127.0.0.1:')
def sql(q):return subprocess.check_output(['mysql','-uroot','-BN',DB,'-e',q],text=True).strip()
def check(v,n):
 assert v,n
 print('PASS '+str(n),flush=True)
def endpoint(route):return BASE+'/api/clinical/index.php?route='+route
with sync_playwright() as pw:
 b=pw.chromium.launch(channel='chrome',headless=True);c=b.new_context(viewport={'width':1440,'height':900});c.add_cookies([{'name':'PHPSESSID','value':'director-lon07c-review','url':BASE}]);r=c.request
 def get(route):
  response=r.get(endpoint(route));assert response.ok,(response.status,response.text());return response.json()['data']
 def void(oid,version,enc=1001,patient='review-patient'):
  return r.post(endpoint(f'encounters/enc:{enc}/observations/{oid}/void'),data={'row_version':version,'patient_id':patient})
 def add(value):
  resp=r.post(endpoint('encounters/enc:1001/observations'),headers={'Idempotency-Key':str(uuid.uuid4())},data={'code':'weight','value_numeric':value,'unit':'kg','effective_at':'2026-09-24 20:00:00','source':'direct_measurement'})
  assert resp.ok,(resp.status,resp.text());return resp.json()['data']
 prior_route='patients/review-patient/longitudinal/measurements&view=prior&exclude_encounter_id=1001'
 prior_before=get(prior_route)
 row=add(68.123);oid=int(row['observation_id']);version=int(row['row_version'])
 edited=r.patch(endpoint(f'encounters/enc:1001/observations/{oid}'),data={'row_version':version,'code':'weight','value_numeric':68.124,'unit':'kg','source':'direct_measurement'})
 check(edited.status==200,'active PATCH remains supported');version=int(edited.json()['data']['row_version'])
 original=sql(f'SELECT code,value_numeric,effective_at,recorded_at,recorded_by_user_id,provenance_json FROM clinical_observations WHERE observation_id={oid}')
 check(void(oid,version,patient='review-history').status==403,'wrong patient denied')
 other=c.request.post(endpoint(f'encounters/enc:1014/observations/{oid}/void'),data={'row_version':version,'patient_id':'review-history'})
 check(other.status==409,'cross encounter observation denied')
 check(void(oid,version+1).status==409,'stale version rejected')
 check(any(x['observation_id']==oid for x in get('encounters/enc:1001')['observations']),'conflict preserves active row')
 check(void(2,1,enc=1002).status==409,'closed encounter denied')
 check(void(oid,version,enc=1003).status==409,'voided encounter denied')
 denied=b.new_context();denied.add_cookies([{'name':'PHPSESSID','value':'meas01-wrong-doctor','url':BASE}]);resp=denied.request.post(endpoint(f'encounters/enc:1001/observations/{oid}/void'),data={'row_version':version,'patient_id':'review-patient'})
 check(resp.status in (401,403,404),'wrong doctor denied');denied.close()
 check(r.post(endpoint(f'encounters/enc:1001/observations/{oid}/void'),data={'patient_id':'review-patient'}).status==400,'version mandatory')
 result=void(oid,version);check(result.status==200,'open invalidation succeeds');saved=result.json()['data']
 check(saved['invalidated_at'] and saved['invalidated_by_user_id']=='review-user' and saved['invalidation_reason']=='Captura errónea' and int(saved['row_version'])==version+1,'audit actor reason time and version')
 detail=get('encounters/enc:1001');check(not any(x['observation_id']==oid for x in detail['observations']),'invalidated excluded current')
 check(any(x['observation_id']==oid for x in detail['invalidated_observations']),'encounter audit read retains invalidated')
 check(sql(f'SELECT code,value_numeric,effective_at,recorded_at,recorded_by_user_id,provenance_json FROM clinical_observations WHERE observation_id={oid}')==original,'original observation remains stored unchanged')
 check(void(oid,version+1).status==409,'repeat invalidation cannot overwrite audit')
 patch=r.patch(endpoint(f'encounters/enc:1001/observations/{oid}'),data={'row_version':version+1,'code':'weight','value_numeric':1,'unit':'kg','source':'direct_measurement'})
 check(patch.status==409,'PATCH cannot revive invalidated row')
 check(get(prior_route)==prior_before,'prior historical values unchanged')
 # Excluding another encounter makes current observations eligible prior sources for this read.
 prior=get('patients/review-patient/longitudinal/measurements&view=prior&exclude_encounter_id=1002')['items']
 check(not any(x['observation_id']==oid for x in prior) and any(x['code']=='weight' for x in prior),'latest prior falls back after invalidation')
 history=get('patients/review-patient/longitudinal/measurements&view=history&from=2026-09-01%2000:00:00&to=2026-09-25%2023:59:59')['items'];audit=next(x for x in history if x['observation_id']==oid)
 check(audit['invalidated_at'] and audit['ineligibility_reason']=='INVALIDATED' and not audit['trend_eligible'],'longitudinal history exposes invalidation')
 for view in ('latest','points'):
  data=get(f'patients/review-patient/longitudinal/measurements&view={view}&code=weight&unit=kg&source=direct_measurement&from=2026-09-01%2000:00:00&to=2026-09-25%2023:59:59')
  rows=data.get('items',[]) or [x['latest_comparable_observation'] for x in data.get('series',[])]
  check(not any(x['observation_id']==oid for x in rows),view+' excludes invalidated')
 # UI against real canonical API, including failure and canceled confirmation.
 row=add(68.321);oid=int(row['observation_id']);p=c.new_page();writes=[]
 p.on('request',lambda req:writes.append(req.url) if '/void' in req.url and req.method=='POST' else None)
 p.goto(BASE+'/index.html?review_encounter=open&qa_tools=hide',wait_until='domcontentloaded');p.locator('[data-m7-section=measurements]').click()
 current=p.locator('[data-m7-measurements-list]');line=current.locator('.vis29-reading').filter(has_text='68.32 kg');line.wait_for()
 prior_text=p.locator('[data-vis29-prior]').inner_text();p.locator('[data-vis29-prior] .vis29-reading').filter(has_text='Peso').get_by_role('button').click();capture=p.locator('[data-m7-measurement-value]').input_value();line.get_by_role('button',name='Eliminar:',exact=False).click();p.locator('[data-meas01-confirm] button[value=cancel]').click();check(not writes,'cancel sends no request')
 line.get_by_role('button',name='Eliminar:',exact=False).click();p.locator('[data-meas01-confirm] button[value=confirm]').click();p.wait_for_function("document.querySelector('[data-m7-measurements-state]').textContent==='Medición eliminada'")
 check(line.count()==0 and p.locator('[data-vis29-prior]').inner_text()==prior_text,'canonical UI refresh removes only current row')
 check(p.locator('[data-m7-measurement-value]').input_value()==capture and p.locator('.vis29-prior-selected').count()==1,'unrelated pending capture preserved')
 check(len(writes)==1 and sql(f'SELECT invalidated_at IS NOT NULL FROM clinical_observations WHERE observation_id={oid}')=='1','UI invokes real canonical invalidation')
 # Dirty unrelated capture survives removal failure and success.
 edit=current.locator('.vis29-reading').filter(has_text='67.8 kg');edit.get_by_role('button',name='Eliminar:',exact=False).click()
 p.screenshot(path=str(ART/'MEAS01_DELETE_CONFIRMATION_1440.png'));p.locator('[data-meas01-confirm] button[value=cancel]').click()
 async_fail='**/observations/*/void'
 p.route(async_fail,lambda rt:rt.fulfill(status=409,json={'ok':False,'error':{'code':'VERSION_CONFLICT'}}))
 edit.get_by_role('button',name='Eliminar:',exact=False).click();p.locator('[data-meas01-confirm] button[value=confirm]').click();p.wait_for_function("document.querySelector('[data-m7-measurements-state]').textContent.includes('El valor cambió')")
 check(edit.count()==1,'UI stale version keeps row and shows conflict');p.unroute(async_fail)
 p.route(async_fail,lambda rt:rt.fulfill(status=503,json={'ok':False,'error':{'code':'UNAVAILABLE'}}))
 edit.get_by_role('button',name='Eliminar:',exact=False).click();p.locator('[data-meas01-confirm] button[value=confirm]').click();p.wait_for_function("document.querySelector('[data-m7-measurements-state]').textContent.includes('No se confirmó')")
 check(edit.count()==1,'UI failure keeps row');p.unroute(async_fail)
 for w,h in ((1440,900),(1366,768),(820,1180),(390,844)):
  p.set_viewport_size({'width':w,'height':h});current.scroll_into_view_if_needed();check(p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),f'responsive {w}');p.screenshot(path=str(ART/f'MEAS01_COLLECTOR_{w}.png'))
 c.close();b.close()
