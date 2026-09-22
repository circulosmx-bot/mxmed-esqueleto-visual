"""Disposable physical HTTP proof for the read-only LON07B projection."""
import json
import os
import subprocess
from urllib.parse import urlencode
from playwright.sync_api import sync_playwright

base=os.environ['LON07B_QA_BASE']; db=os.environ['LON07B_QA_DB']
def sql(statement):
    return subprocess.check_output(['mysql','--batch','--skip-column-names',db,'-e',statement]).decode().strip()
def check(condition,name):
    assert condition,name
    print('PASS',name,flush=True)

values=[]
for i in range(120):
    values.append(f"(1,'weight',{70+i/10:.1f},'kg',UTC_TIMESTAMP() - INTERVAL {120-i} MINUTE,UTC_TIMESTAMP(),'u_a','direct_measurement','{{}}','EXPLICIT_EFFECTIVE_TIME')")
sql('INSERT INTO clinical_observations (encounter_id,code,value_numeric,unit,effective_at,recorded_at,recorded_by_user_id,source,provenance_json,effective_at_authority) VALUES '+','.join(values))
sql("""INSERT INTO clinical_observations (encounter_id,code,value_numeric,unit,systolic_mm_hg,diastolic_mm_hg,effective_at,recorded_at,recorded_by_user_id,source,provenance_json,effective_at_authority) VALUES
(1,'heart_rate',70,'bpm',NULL,NULL,UTC_TIMESTAMP() - INTERVAL 3 DAY,UTC_TIMESTAMP(),'u_a','direct_measurement','{}','EXPLICIT_EFFECTIVE_TIME'),
(1,'weight',999,'kg',NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP(),'u_a','direct_measurement','{}','CAPTURE_TIME_FALLBACK'),
(1,'weight',888,'kg',NULL,NULL,UTC_TIMESTAMP() - INTERVAL 1 SECOND,UTC_TIMESTAMP(),'u_a','direct_measurement','{}','UNKNOWN_LEGACY'),
(1,'weight',72,'kg',NULL,NULL,UTC_TIMESTAMP() - INTERVAL 4 DAY,UTC_TIMESTAMP(),'u_a','patient_report','{}','EXPLICIT_EFFECTIVE_TIME'),
(1,'weight',154,'lb',NULL,NULL,UTC_TIMESTAMP() - INTERVAL 4 DAY,UTC_TIMESTAMP(),'u_a','import','{}','EXPLICIT_EFFECTIVE_TIME'),
(1,'weight',11,'stone',NULL,NULL,UTC_TIMESTAMP() - INTERVAL 4 DAY,UTC_TIMESTAMP(),'u_a','import','{}','EXPLICIT_EFFECTIVE_TIME'),
(1,'pain',4,'score',NULL,NULL,UTC_TIMESTAMP() - INTERVAL 2 DAY,UTC_TIMESTAMP(),'u_a','direct_measurement','{}','EXPLICIT_EFFECTIVE_TIME'),
(1,'blood_pressure',NULL,'mmHg',120,80,UTC_TIMESTAMP() - INTERVAL 2 DAY,UTC_TIMESTAMP(),'u_a','direct_measurement','{}','EXPLICIT_EFFECTIVE_TIME'),
(1,'blood_pressure',NULL,'mmHg',125,82,UTC_TIMESTAMP() - INTERVAL 1 DAY,UTC_TIMESTAMP(),'u_a','direct_measurement','{}','EXPLICIT_EFFECTIVE_TIME'),
(2,'weight',333,'kg',NULL,NULL,UTC_TIMESTAMP() - INTERVAL 1 DAY,UTC_TIMESTAMP(),'u_b','direct_measurement','{}','EXPLICIT_EFFECTIVE_TIME')""")
sql("INSERT INTO clinical_encounter_amendments (encounter_id,target_type,target_id,reason,author_user_id,amended_at,correction_payload_json) VALUES (1,'encounter',NULL,'Corrección narrativa','u_a',UTC_TIMESTAMP(),'{\"text\":\"Peso 777 kg\"}')")
before=sql('SELECT COUNT(*) FROM clinical_observations')
plan=sql("EXPLAIN SELECT o.observation_id FROM clinical_observations o JOIN clinical_encounters e ON e.encounter_id=o.encounter_id WHERE e.doctor_id='d_a' AND e.patient_id='p_a' AND o.effective_at>=UTC_TIMESTAMP()-INTERVAL 12 MONTH ORDER BY o.effective_at DESC,o.observation_id DESC LIMIT 51")
check('idx_observation_encounter_code_effective' in plan and 'PRIMARY' in plan,'disposable query plan uses current encounter/observation indexes')

with sync_playwright() as p:
    browser=p.chromium.launch(channel='chrome',headless=True)
    anonymous=browser.new_context()
    check(anonymous.request.get(base+'/api/clinical/index.php?route=patients/p_a/longitudinal/measurements').status in (401,403),'anonymous read denied')
    anonymous.close()
    context=browser.new_context();context.add_cookies([{'name':'PHPSESSID','value':'lon07b-qa','url':base}]);req=context.request
    endpoint=base+'/api/clinical/index.php?route=patients/p_a/longitudinal/measurements'
    def get(path=endpoint,**args):
        separator='&' if '?' in path else '?'
        response=req.get(path+(separator+urlencode(args) if args else ''))
        return response,response.json()
    response,data=get()
    check(response.status==200 and data['ok'] is True,'canonical authenticated read route')
    series=data['data']['series'];by_key={s['series_key']:s for s in series}
    weight=by_key['weight|kg|direct_measurement']['latest_comparable_observation']
    check(float(weight['value_numeric'])==81.9 and weight['effective_at_authority']=='EXPLICIT_EFFECTIVE_TIME','latest comparable excludes newer fallback and legacy time')
    check('heart_rate|bpm|direct_measurement' in by_key,'latest per series independent of global first 100')
    check('weight|kg|patient_report' in by_key and 'weight|lb|import' in by_key and 'weight|stone|import' not in by_key,'source and accepted alternate unit remain separate')
    _,lb_points=get(view='points',code='weight',unit='lb',source='import')
    check(len(lb_points['data']['items'])==1 and float(lb_points['data']['items'][0]['value_numeric'])==154,'different accepted units produce distinct series without conversion')
    check('pain|score|direct_measurement' not in by_key,'pain excluded from numeric trend')
    bp=[s for s in series if s['code']=='blood_pressure']
    check(len(bp)==2 and {s['component'] for s in bp}=={'systolic','diastolic'} and len({s['latest_comparable_observation']['observation_id'] for s in bp})==1,'blood pressure component series share observation identity')
    response,data=get(view='history',code='weight',limit=100)
    items=data['data']['items'];check(response.status==200 and any(i['classification']=='HISTORY_ONLY' and i['effective_at_authority']=='CAPTURE_TIME_FALLBACK' for i in items),'fallback visible in history only')
    check(any(i['classification']=='HISTORY_ONLY' and i['effective_at_authority']=='UNKNOWN_LEGACY' for i in items),'unknown legacy visible in history only')
    _,alt=get(view='history',code='weight',unit='stone')
    check(len(alt['data']['items'])==1 and alt['data']['items'][0]['classification']=='HISTORY_ONLY','unrecognized unit retained as history without conversion')
    check(any(i['encounter_has_amendment'] for i in items) and all(str(i['value_numeric'])!='777.000000' for i in items),'amendment context without derived replacement')
    response,data=get(view='history',code='pain');check(response.status==200 and len(data['data']['items'])==1 and data['data']['items'][0]['classification']=='HISTORY_ONLY','pain history only')
    response,data=get(view='points',code='weight',unit='kg',source='direct_measurement',limit=50)
    seen=[]
    while True:
        page=data['data'];seen.extend(i['observation_id'] for i in page['items'])
        if not page['has_more']:break
        response,data=get(view='points',code='weight',unit='kg',source='direct_measurement',limit=50,cursor=page['next_cursor'])
        check(response.status==200,'keyset next page succeeds')
    check(len(seen)==120 and len(set(seen))==120,'stable bounded keyset pagination preserves all repeated readings')
    response,data=get(view='points',code='blood_pressure',unit='mmHg',source='direct_measurement',component='systolic')
    check(response.status==200 and len(data['data']['items'])==2 and all(i['component']=='systolic' for i in data['data']['items']),'blood pressure systolic points separate')
    response,data=get(view='points',code='blood_pressure',unit='mmHg',source='direct_measurement',component='diastolic')
    check(response.status==200 and len(data['data']['items'])==2 and all(i['component']=='diastolic' for i in data['data']['items']),'blood pressure diastolic points separate')
    response,data=get(base+'/api/clinical/index.php?route=patients/p_empty/longitudinal/measurements')
    check(response.status==200 and data['data']['series']==[],'no-data is deterministic and does not imply normal')
    response,data=get(base+'/api/clinical/index.php?route=patients/p_empty/longitudinal/measurements',view='history')
    check(response.status==200 and data['data']['items']==[] and data['data']['has_more'] is False,'no-data history is empty')
    response,data=get(base+'/api/clinical/index.php?route=patients/p_b/longitudinal/measurements')
    check(response.status in (403,404) and '333' not in response.text(),'foreign patient does not disclose measurement')
    for params in ({'limit':101},{'from':'bad'},{'from':'2000-01-01 00:00:00'},{'cursor':'bad'}):
        response,_=get(**params);check(response.status==400,'invalid bounded query rejected')
    response,_=get();check(response.status==200,'read endpoint remains available after validation errors')
    check(req.post(endpoint,data={}).status==404,'measurement route has no write method')
    context.close();browser.close()
check(before==sql('SELECT COUNT(*) FROM clinical_observations'),'read API did not mutate observations')
print('LON07B_DISPOSABLE_HTTP_GATE=PASS')
