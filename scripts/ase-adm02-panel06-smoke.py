#!/usr/bin/env python3
"""Post-run focused privacy/receipt checks; requires exact owned, pinned PANEL06 preview."""
import pathlib,json,sys,os,time,hashlib,uuid,base64,http.client,ssl
import pymysql
from playwright.sync_api import sync_playwright,expect
root=pathlib.Path(__file__).resolve().parents[1];owned=pathlib.Path(sys.argv[1]).resolve()
assert owned.parent==pathlib.Path('/private/tmp') and owned.name.startswith('mxmed-ase-adm02imp01-')
assert owned.stat().st_uid==os.getuid() and owned.stat().st_mode&0o777==0o700
a=json.loads((owned/'workflow-approval.json').read_text());assert a['root']==str(root) and a['expires']>time.time()
assert all(hashlib.sha256((root/f).read_bytes()).hexdigest()==h for f,h in a['source_hashes'].items())
env=json.loads((owned/'review-env.json').read_text());assert env['MXMED_DB_HOST']=='127.0.0.1' and env['MXMED_DB_NAME']==a['database']
db=pymysql.connect(host='127.0.0.1',port=int(env['MXMED_DB_PORT']),user=env['MXMED_DB_USER'],password=env['MXMED_DB_PASS'],database=env['MXMED_DB_NAME'],autocommit=True)
def sql(q,args=()):
 with db.cursor() as c:c.execute(q,args);return c.fetchall()
assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==owned/'mysql'
tokens=json.loads((owned/'sessions.json').read_text());origin=env['MXMED_PREVIEW_ORIGIN'];records=json.loads((owned/'uxfunc02-records.json').read_text());results=[];csrf={}
def check(label,ok):assert ok,label;results.append({'name':label,'pass':True});print('PASS '+label)
def api(action,input=None,actor='director',token=True):
 conn=http.client.HTTPSConnection('127.0.0.1',int(origin.rsplit(':',1)[1]),context=ssl.create_default_context(cafile=str(owned/'cert.pem')))
 h={'Content-Type':'application/json','Origin':origin,'Sec-Fetch-Site':'same-origin','Cookie':'__Host-mxmed_session='+tokens[actor]}
 if token:h['X-CSRF-Token']=csrf.get(actor,'')
 try:
  conn.request('POST','/api/internal/staff-admission/workflow.php',json.dumps({'action':action,'input':input or {}}),h);r=conn.getresponse();return r.status,json.loads(r.read())
 finally:conn.close()
for actor in ['director','master']:status,b=api('bootstrap',actor=actor);assert status==200;csrf[actor]=b['csrf_token']
cid=records['bruno']['case'];status,c=api('case',{'case_id':cid});assert status==200
body=dict(case_id=cid,expected_version=c['version'],operation_id=str(uuid.uuid4()),changes={'given_names':''})
check('Changed required name rejected by server',api('update',body)[0]==400)
body['changes']={'contact_phone':'+12025550106'}
check('Profile update requires CSRF',api('update',body,token=False)[0]==403)
check('Stale personal version cannot overwrite current',api('update',{**body,'expected_version':1})[0]==409)
check('Rejected updates create no personal version',api('case',{'case_id':cid})[1]['version']==c['version'])
purpose='INTERNAL_PRESENTATION_PORTRAIT';status,m=api('media_status',dict(case_id=cid,purpose=purpose));assert status==200
portrait=dict(case_id=cid,purpose=purpose,asset_id=m['asset_id'],asset_version=m['version'],expected_case_version=c['version'])
other=api('case',{'case_id':records['alma']['case']})[1]
check('Private asset cannot be read under a different authorized case',api('portrait',{**portrait,'case_id':other['case_id'],'expected_case_version':other['version']})[0]==403)
metadata=dict(operation_id=str(uuid.uuid4()),case_id=cid,expected_case_version=c['version'],expected_asset_version=m['version'],purpose=purpose,declared_mime='image/png')
image=base64.b64encode((owned/'synthetic.png').read_bytes()).decode()
check('Master cannot write another member portrait',api('upload',dict(metadata=metadata,base64=image),actor='master')[0]==403)
removed=api('case',{'case_id':records['celia']['case']})[1]
check('Director cannot upload portrait to removed staff',api('upload',dict(metadata={**metadata,'case_id':removed['case_id'],'expected_case_version':removed['version'],'expected_asset_version':0},base64=image))[0]==403)
intent=sql('SELECT intent_id,case_id FROM auth_staff_review_intents WHERE consumed_at IS NOT NULL ORDER BY expires_at DESC LIMIT 1')[0]
receipt=dict(case_id=intent[1],intent_id=intent[0]);status,value=api('review_result',receipt)
check('Exact review receipt returns committed metadata without proofs or bytes',status==200 and value['committed'] is True and set(value)=={'intent_id','case_id','action','committed','outcome','expired'})
check('Master cannot inspect Director review receipts',api('review_result',receipt,actor='master')[0]==403)
check('Review intent cannot be reconciled under another case',api('review_result',{**receipt,'case_id':cid if cid!=intent[1] else records['alma']['case']})[0]==403)
with sync_playwright() as pw:
 for engine in ['chromium','webkit']:
  prior_version=api('media_status',dict(case_id=cid,purpose=purpose))[1]['version'];b=getattr(pw,engine).launch();ctx=b.new_context(ignore_https_errors=True);ctx.add_cookies([dict(name='__Host-mxmed_session',value=tokens['director'],url=origin,secure=True,httpOnly=True,sameSite='Strict')]);p=ctx.new_page();p.goto(origin+'/internal/media-review/index.php?section=equipo');expect(p.locator('#add-member')).to_be_enabled();p.locator('[data-member="'+records['bruno']['account']+'"]').click();expect(p.locator('#detail-title')).to_have_text(records['bruno']['name']);p.locator('#personal-data').evaluate('(e)=>e.open=true');p.locator('#member-edit').click();expect(p.locator('#staff-profile')).to_be_visible();lost=[]
  def intercept(route):
   if route.request.post_data_json['action']=='upload':
    response=route.fetch(headers={**route.request.all_headers(),'Sec-Fetch-Site':'same-origin'});assert response.status==200;lost.append(response.json());route.abort('failed')
   else:route.continue_()
  p.route('**/api/internal/staff-admission/workflow.php',intercept);p.locator('#portrait-file').set_input_files(str(owned/'synthetic.png'));expect(p.locator('#staff-profile-status')).to_contain_text('Consulta el resultado');p.keyboard.press('Escape');expect(p.locator('#staff-profile')).to_be_hidden();p.unroute('**/api/internal/staff-admission/workflow.php',intercept);p.reload();expect(p.locator('#add-member')).to_be_enabled();p.locator('[data-member="'+records['bruno']['account']+'"]').click();expect(p.locator('#detail-title')).to_have_text(records['bruno']['name']);p.locator('#personal-data').evaluate('(e)=>e.open=true');p.locator('#member-edit').click();expect(p.locator('#profile-reconcile')).to_be_visible();p.locator('#profile-reconcile').click();expect(p.locator('#staff-profile-status')).to_contain_text('Operación guardada y confirmada')
  check(engine+' lost portrait ACK reconciles exact operation after Escape/reload; no second upload',len(lost)==1 and api('media_status',dict(case_id=cid,purpose=purpose))[1]['version']==prior_version+1 and sql('SELECT COUNT(*) FROM auth_staff_media_operations WHERE operation_id=%s',(lost[0]['operation_id'],))[0][0]==1)
  p.keyboard.press('Escape');expect(p.locator('#staff-profile')).to_be_hidden();expect(p.locator('#member-edit')).to_be_focused();expect(p.locator('#detail-title')).to_have_text(records['bruno']['name']);check(engine+' focus returns to contextual edit after canonical rerender',True)
  b.close()
db.close();(root/'docs/evidence/ase-adm02-panel06/smoke.json').write_text(json.dumps({'result':'PASS','checks':results,'check_count':len(results)},indent=2)+'\n')
