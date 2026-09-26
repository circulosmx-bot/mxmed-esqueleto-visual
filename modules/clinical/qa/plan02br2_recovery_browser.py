"""Separate real collision and persisted-but-lost-response scenarios."""
from plan02br2_browser import *

def recovery(browser):
    ctx,page=boot(browser);order(page,'PLAN02BR2 collision order A');order(page,'PLAN02BR2 collision order B');appointment(page,os.environ['PLAN02BR2_COLLISION_DATE']);followup(page,'PLAN02BR2 collision followup');collector(page);count(page,4)
    draft=saved(page);slot=draft['appointment']['selection'];payload=dict(doctor_id='1',consultorio_id='1',patient_id='p_plan02_review',start_at=slot['start_at'],end_at=slot['end_at'],modality='in_person',channel_origin='doctor',created_by_role='doctor',created_by_id='1')
    r=ctx.request.post(BASE+'/api/agenda/index.php/appointments',data=payload,headers={'Idempotency-Key':str(uuid.uuid4())});assert r.json()['ok'];writes=[];capture(page,writes)
    confirm(page);s=saved(page);assert s['orders'][0]['state']=='SUCCESS' and s['appointment']['state']=='FAILED' and s['followup']['state']=='BLOCKED_BY_DEPENDENCY';assert len(writes)==3;count(page,2);expect(page.locator('[data-prepared]')).to_have_count(2)
    page.screenshot(path=str(OUT/'partial-failure.png'));oldkey=s['appointment']['key'];orderid=s['orders'][0]['result']['document_id'];page.locator('[data-review="'+s['appointment']['id']+'"]').click();expect(page.locator('[data-ns-slot]').first).to_be_visible();page.locator('[data-ns-slot]').first.click();page.locator('[data-modal-add]').click();assert saved(page)['appointment']['key']!=oldkey
    confirm(page);s=saved(page);assert s['orders'][0]['result']['document_id']==orderid and sum('/documents' in r['url'] for r in writes)==2;assert s['followup']['result']['item']['appointment_id']==s['appointment']['result']['appointment_id'];count(page,0);expect(page.locator('[data-prepared]')).to_have_count(0)
    (OUT/'collision-results.json').write_text(json.dumps({'state':s,'writes':writes},indent=2));ctx.close();print('PASS real collision and dependent retry',flush=True)
    ctx,page=boot(browser);order(page,'PLAN02BR2 recovery order A');order(page,'PLAN02BR2 recovery order B');appointment(page,os.environ['PLAN02BR2_RECOVERY_DATE']);followup(page,'PLAN02BR2 recovery followup');collector(page);lost={};attempts=[]
    def intercept(route):
        req=route.request
        if req.method!='POST':route.continue_();return
        kind='order' if req.url.endswith('/documents') else 'appointment' if req.url.endswith('/appointments') else 'followup' if req.url.endswith('/longitudinal/tasks') else None
        if not kind:route.continue_();return
        payload=req.post_data_json;key=req.headers['idempotency-key'];attempts.append({'kind':kind,'key':key,'payload':payload})
        if kind not in lost and (kind!='order' or payload['title'].endswith('B')):
            response=route.fetch();body=response.json();assert body['ok'],body;lost[kind]={'key':key,'payload':payload,'body':body};route.abort('failed')
        else:route.continue_()
    page.route('**/api/**',intercept)
    confirm(page);s=saved(page);assert s['appointment']['uncertain'];assert s['followup']['state']=='BLOCKED_BY_DEPENDENCY'
    item=page.locator('[data-prepared="'+s['appointment']['id']+'"]');expect(item.locator('[data-remove]')).to_have_count(0);item.locator('[data-review]').click();expect(page.locator('[data-modal-add]')).not_to_be_visible();page.locator('[data-modal-cancel]').click();assert saved(page)['appointment']['key']==s['appointment']['key']
    page.locator('[data-m7-section="finalize"]').click();page.locator('[data-m7-finalize]').click();expect(page.locator('.plan02b-leave')).to_contain_text('resultado pendiente');expect(page.locator('[data-discard]')).to_have_count(0);page.locator('[data-stay]').click();collector(page)
    # Restore the prior version's interrupted-state shape, preserving key and payload.
    snapshot=saved(page);snapshot['appointment']['state']='IN_PROGRESS';snapshot['appointment'].pop('uncertain',None)
    page.evaluate('(s)=>sessionStorage.setItem("mxmed-plan02b:1:p_plan02_review:1015",JSON.stringify(s))',snapshot)
    page.once('dialog',lambda d:d.accept());page.reload(wait_until='commit');ready(page);collector(page);restored=saved(page);assert restored['appointment']['key']==snapshot['appointment']['key'] and restored['appointment']['payload']==snapshot['appointment']['payload'];assert restored['appointment']['uncertain'];count(page,3)
    confirm(page);assert saved(page)['followup']['uncertain'];confirm(page);s=saved(page);assert all(a['state']=='SUCCESS' for a in s['orders']+[s['appointment'],s['followup']]);assert len(attempts)==7
    for kind,v in lost.items():
        matching=[a for a in attempts if a['key']==v['key']];assert len(matching)==2 and matching[0]['payload']==matching[1]['payload']
    assert s['orders'][1]['result']['document_id']==lost['order']['body']['data']['document_id'];assert s['appointment']['result']['appointment_id']==lost['appointment']['body']['data']['appointment_id'];assert s['followup']['result']['item']['task_id']==lost['followup']['body']['data']['item']['task_id'];count(page,0);expect(page.locator('[data-prepared]')).to_have_count(0)
    (OUT/'recovery-results.json').write_text(json.dumps({'state':s,'attempts':attempts,'lost':lost},indent=2));ctx.close();print('PASS all writers lost response, frozen attempts, legacy restoration and stable count',flush=True)

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True);recovery(browser);browser.close()
