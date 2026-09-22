"""Disposable canonical HTTP and SQL proof for LON07A time authority."""
import os, subprocess
from playwright.sync_api import sync_playwright
base=os.environ['LON07A_QA_BASE'];db=os.environ['LON07A_QA_DB']
def check(value,name):
    assert value,name
    print('PASS',name,flush=True)
def sql(query):
    return subprocess.check_output(['mysql','--batch','--skip-column-names',db,'-e',query]).decode().strip()
def observation(observation_id):
    return sql(f'SELECT effective_at,effective_at_authority,value_numeric FROM clinical_observations WHERE observation_id={int(observation_id)}').split('\t')
check(observation(1)==['2024-01-02 03:04:05','UNKNOWN_LEGACY','71.500000'],'old row time unchanged and UNKNOWN_LEGACY')
check(sql('SELECT COUNT(*) FROM clinical_observations')=='1','migration rerun preserved row count')
constraint=subprocess.run(['mysql',db,'-e',"UPDATE clinical_observations SET effective_at_authority='INVENTED' WHERE observation_id=1"],capture_output=True)
check(constraint.returncode!=0 and observation(1)[1]=='UNKNOWN_LEGACY','three-state database CHECK enforced')
with sync_playwright() as playwright:
    browser=playwright.chromium.launch(channel='chrome',headless=True);context=browser.new_context();context.add_cookies([{'name':'PHPSESSID','value':'lon07a-qa','url':base}]);page=context.new_page();page.goto(base+'/modules/clinical/README.md');req=context.request
    endpoint=base+'/api/clinical/index.php?route=encounters/enc:1/'
    explicit={'code':'weight','value_numeric':72.25,'unit':'kg','source':'direct_measurement','effective_at':'2025-03-04 05:06:07'}
    created=req.post(endpoint+'observations',data=explicit,headers={'Content-Type':'application/json','Idempotency-Key':'lon07a-explicit'})
    check(created.status==201 and created.json().get('ok') is True,'explicit canonical HTTP create')
    row=created.json()['data'];explicit_id=int(row['observation_id'])
    check(observation(explicit_id)==['2025-03-04 05:06:07','EXPLICIT_EFFECTIVE_TIME','72.250000'],'explicit accepted time and authority persisted')
    replay=req.post(endpoint+'observations',data=explicit,headers={'Content-Type':'application/json','Idempotency-Key':'lon07a-explicit'})
    check(replay.status==200 and int(replay.json()['data']['observation_id'])==explicit_id,'WS03 create idempotency preserved')
    fallback={'code':'heart_rate','value_numeric':80,'unit':'bpm','source':'direct_measurement'}
    created=req.post(endpoint+'observations',data=fallback,headers={'Content-Type':'application/json','Idempotency-Key':'lon07a-fallback'})
    check(created.status==201 and created.json().get('ok') is True,'omitted-time canonical HTTP create')
    fallback_row=created.json()['data'];fallback_id=int(fallback_row['observation_id']);before=observation(fallback_id)
    check(before[1]=='CAPTURE_TIME_FALLBACK' and before[0]==sql(f'SELECT recorded_at FROM clinical_observations WHERE observation_id={fallback_id}'),'capture time marked and same as recorded time')
    check(fallback_row['effective_at_authority']=='CAPTURE_TIME_FALLBACK','fallback authority returned to reader')
    changed=req.patch(endpoint+f'observations/{fallback_id}',data={**fallback,'value_numeric':82,'row_version':int(fallback_row['row_version'])},headers={'Content-Type':'application/json'})
    check(changed.status==200 and changed.json().get('ok') is True,'PATCH without effective time accepted')
    check(observation(fallback_id)==[before[0],'CAPTURE_TIME_FALLBACK','82.000000'],'omitted-time PATCH preserves date and authority')
    stale=req.patch(endpoint+f'observations/{fallback_id}',data={**fallback,'value_numeric':83,'row_version':int(fallback_row['row_version'])},headers={'Content-Type':'application/json'})
    check(stale.status==409,'WS03 row-version conflict preserved')
    changed=req.patch(endpoint+f'observations/{fallback_id}',data={**fallback,'effective_at':'2025-05-06 07:08:09','row_version':int(changed.json()['data']['row_version'])},headers={'Content-Type':'application/json'})
    check(changed.status==200 and observation(fallback_id)[:2]==['2025-05-06 07:08:09','EXPLICIT_EFFECTIVE_TIME'],'explicit PATCH promotes time authority with version guard')
    lied=req.post(endpoint+'observations',data={**explicit,'effective_at_authority':'EXPLICIT_EFFECTIVE_TIME'},headers={'Content-Type':'application/json','Idempotency-Key':'lon07a-client-lie'})
    check(lied.status==400 and sql('SELECT COUNT(*) FROM clinical_observations')=='3','client cannot assign time authority')
    detail=req.get(endpoint[:-1]);check(detail.status==200 and 'effective_at_authority' in detail.text(),'encounter read includes time authority')
    final=req.post(endpoint+'finalize',data={},headers={'Content-Type':'application/json'})
    check(final.status==200 and final.json().get('ok') is True,'canonical M7 finalize')
    original=observation(explicit_id)
    amendment=req.post(endpoint+'amendments',data={'target':{'type':'observation','id':str(explicit_id)},'reason':'Corrección narrativa posterior','correction':{'text':'Peso 999 kg; texto no estructurado'}},headers={'Content-Type':'application/json','Idempotency-Key':'lon07a-amendment'})
    check(amendment.status==201 and amendment.json().get('ok') is True,'append-only encounter amendment')
    check(observation(explicit_id)==original and sql('SELECT COUNT(*) FROM clinical_observations')=='3','amendment leaves original value, time and authority unchanged')
    check(sql('SELECT COUNT(*) FROM clinical_encounter_amendments')=='1','amendment retained separately')
    check(sql("SELECT COUNT(*) FROM clinical_observations WHERE value_numeric=999")=='0','amendment text creates no synthetic observation')
    context.close();browser.close()
pure=subprocess.check_output(['php','-r','require $argv[1];foreach(["EXPLICIT_EFFECTIVE_TIME","CAPTURE_TIME_FALLBACK","UNKNOWN_LEGACY"] as $v)echo clinical_observation_time_trend_eligible($v)?"1":"0";',os.path.join(os.path.dirname(__file__),'../../../api/_lib/clinical_observations.php')]).decode()
check(pure=='100','only explicit effective time passes pure trend-time gate')
print('LON07A_DISPOSABLE_HTTP_GATE=PASS')
