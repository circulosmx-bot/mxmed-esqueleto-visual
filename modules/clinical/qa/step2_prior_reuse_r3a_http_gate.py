"""Canonical reuse HTTP/SQL gate; runs only in the disposable R1/R3A database."""
import json,os,subprocess
from playwright.sync_api import sync_playwright
BASE=os.environ['STEP2_QA_BASE'];DB=os.environ['STEP2_QA_DB']
assert DB.startswith('step2_qa_')
def sql(query):return subprocess.check_output(['mysql','--batch','--skip-column-names',DB,'-e',query],text=True).strip()
def check(condition,name):
 assert condition,name
 print('PASS '+name,flush=True)
sql("UPDATE clinical_observations SET invalidated_at=UTC_TIMESTAMP(),invalidated_by_user_id='u_a',invalidation_reason='Retired QA setup' WHERE code='temperature'")
sql("INSERT INTO clinical_observations (encounter_id,code,value_numeric,unit,effective_at,effective_at_authority,recorded_at,recorded_by_user_id,source,provenance_json) VALUES (1,'temperature',36.5,'°C','2025-09-25 15:42:00','EXPLICIT_EFFECTIVE_TIME','2025-09-25 15:43:00','u_a','direct_measurement','{\"clinical_context\":\"historical synthetic baseline\"}')")
src=int(sql("SELECT MAX(observation_id) FROM clinical_observations"));source_before=sql(f'SELECT JSON_OBJECT("id",observation_id,"encounter",encounter_id,"value",value_numeric,"time",effective_at,"recorded",recorded_at,"source",source,"provenance",provenance_json,"invalidated",invalidated_at) FROM clinical_observations WHERE observation_id={src}')
sql("INSERT INTO patients_patients VALUES ('p_b'); INSERT INTO patients_doctor_links (doctor_id,patient_id,status) VALUES ('d_a','p_b','active'); INSERT INTO clinical_encounters (doctor_id,patient_id,encounter_dt,status) VALUES ('d_a','p_b','2025-01-01','open'),('d_b','p_a','2025-01-01','open')")
other_ids=sql("SELECT encounter_id FROM clinical_encounters WHERE encounter_id>2 ORDER BY encounter_id").splitlines()
for enc in other_ids:sql(f"INSERT INTO clinical_observations (encounter_id,code,value_numeric,unit,effective_at,effective_at_authority,recorded_at,recorded_by_user_id,source,provenance_json) VALUES ({enc},'oxygen_saturation',98,'%','2025-09-25 15:42:00','EXPLICIT_EFFECTIVE_TIME','2025-09-25 15:43:00','u_a','patient_report','{{}}')")
wrong_patient,wrong_doctor=map(int,sql("SELECT observation_id FROM clinical_observations WHERE code='oxygen_saturation' ORDER BY observation_id").splitlines())
fallback=int(sql("SELECT observation_id FROM clinical_observations WHERE code='heart_rate' AND effective_at_authority='CAPTURE_TIME_FALLBACK' LIMIT 1"))
with sync_playwright() as p:
 b=p.chromium.launch();ctx=b.new_context();ctx.add_cookies([{'name':'PHPSESSID','value':'step2-qa','url':BASE}]);req=ctx.request
 def reuse(source,key='reuse-temperature',enc=2,extra=None):
  payload={'source_observation_id':source};payload.update(extra or {})
  return req.post(BASE+f'/api/clinical/index.php?route=encounters/enc:{enc}/observations/reuse',data=payload,headers={'Idempotency-Key':key})
 def prior(enc=2):return req.get(BASE+f'/api/clinical/index.php?route=patients/p_a/longitudinal/measurements&view=prior&exclude_encounter_id={enc}').json()['data']['items']
 check(src in [int(r['observation_id']) for r in prior()],'source appears in exact canonical prior projection')
 tamper=reuse(src,'reuse-tamper',extra={'value_numeric':999,'effective_at':'2026-01-01 00:00:00','source':'import'})
 check(tamper.status==400,'client historical fields rejected')
 response=reuse(src);check(response.status==201,'canonical reuse succeeds');row=response.json()['data'];new_id=int(row['observation_id']);prov=json.loads(row['provenance_json'])
 check(int(row['encounter_id'])==2 and float(row['value_numeric'])==36.5 and row['unit']=='°C' and row['source']=='direct_measurement','canonical source value origin and current encounter copied')
 check(row['effective_at']=='2025-09-25 15:42:00' and row['recorded_at']!=row['effective_at'],'measurement time retained; incorporation uses server now')
 check(prov['reuse_mode']=='PRIOR_OBSERVATION' and prov['source_observation_id']==src and prov['reused_at']==row['recorded_at'] and prov['source_provenance']['clinical_context']=='historical synthetic baseline' and 'capture_time_mode' not in prov,'server lineage differs from direct capture and preserves original provenance')
 source_after=sql(f'SELECT JSON_OBJECT("id",observation_id,"encounter",encounter_id,"value",value_numeric,"time",effective_at,"recorded",recorded_at,"source",source,"provenance",provenance_json,"invalidated",invalidated_at) FROM clinical_observations WHERE observation_id={src}')
 check(source_before==source_after,'source observation immutable')
 replay=reuse(src);check(replay.status==200 and int(replay.json()['data']['observation_id'])==new_id,'lost response replay returns same observation')
 duplicate=reuse(src,'reuse-second-key');check(duplicate.status==409 and duplicate.json()['error']['code']=='MEASUREMENT_TYPE_ALREADY_PRESENT','duplicate active type rejected')
 weight=next(int(r['observation_id']) for r in prior() if r['code']=='height')
 changed=reuse(weight);check(changed.status==409 and changed.json()['error']['code']=='IDEMPOTENCY_KEY_REUSED','different source with same key conflicts')
 for name,source in [('cross patient',wrong_patient),('cross doctor',wrong_doctor),('fallback',fallback),('unknown',99999999)]:
  r=reuse(source,'reuse-'+name.replace(' ','-'));check(r.status==409 and r.json()['error']['code']=='PRIOR_OBSERVATION_NOT_REUSABLE',name+' source rejected without existence disclosure')
 check(sql("SELECT COUNT(*) FROM clinical_observations WHERE encounter_id=2 AND code='temperature' AND invalidated_at IS NULL")=='1','all rejected commands leave exactly one active value')
 edit=req.patch(BASE+f'/api/clinical/index.php?route=encounters/enc:2/observations/{new_id}',data={'code':'temperature','value_numeric':36.6,'unit':'°C','source':'direct_measurement','row_version':1})
 check(edit.status==200 and json.loads(edit.json()['data']['provenance_json'])==prov,'editing retains server lineage and measurement time')
 # Canonically void an OPEN source after reading it, then use a later OPEN encounter.
 created=req.post(BASE+'/api/clinical/index.php?route=encounters/enc:2/observations',data={'code':'respiratory_rate','value_numeric':18,'unit':'rpm','source':'patient_report','capture_time_mode':'SERVER_AT_SAVE'},headers={'Idempotency-Key':'stale-source'})
 check(created.status==201,'stale source created canonically');stale=created.json()['data'];sid=int(stale['observation_id'])
 check(sid in [int(r['observation_id']) for r in prior(999)],'stale candidate initially visible')
 void=req.post(BASE+f'/api/clinical/index.php?route=encounters/enc:2/observations/{sid}/void',data={'patient_id':'p_a','row_version':1})
 check(void.status==200,'stale source canonically invalidated')
 sql("INSERT INTO clinical_encounter_sections (encounter_id,section_type,payload_schema_version,payload_json,narrative_text,created_by_user_id,updated_by_user_id) VALUES (2,'plan',1,'{\"follow_up\":\"Control\"}','Control','u_a','u_a')")
 finalized=req.post(BASE+'/api/clinical/index.php?route=encounters/enc:2/finalize',data={});check(finalized.status==200,'current encounter finalized canonically')
 check(reuse(weight,'closed-reuse').status==409,'closed current encounter rejected')
 sql("INSERT INTO clinical_encounters (doctor_id,patient_id,encounter_dt,status,opened_by_user_id) VALUES ('d_a','p_a',UTC_TIMESTAMP(),'open','u_a')")
 later=int(sql("SELECT MAX(encounter_id) FROM clinical_encounters"))
 check(reuse(sid,'stale-reuse',later).status==409,'stale invalidated source revalidated and rejected')
 chain=reuse(new_id,'lineage-next',later);check(chain.status==201,'later reuse succeeds');chain_prov=json.loads(chain.json()['data']['provenance_json'])
 check(chain_prov['source_observation_id']==new_id and chain_prov['source_provenance']['source_observation_id']==src,'future reuse preserves direct source and ancestor lineage')
 check(reuse(src,'superseded-source',later).status==409,'superseded source excluded by latest-per-type reader is rejected')
 # A paired historical value uses the same canonical copy path.
 sql("INSERT INTO clinical_observations (encounter_id,code,unit,systolic_mm_hg,diastolic_mm_hg,effective_at,effective_at_authority,recorded_at,recorded_by_user_id,source,provenance_json) VALUES (1,'blood_pressure','mmHg',120,80,'2025-09-25 15:42:00','EXPLICIT_EFFECTIVE_TIME','2025-09-25 15:43:00','u_a','patient_report','{}')")
 paired=int(sql('SELECT MAX(observation_id) FROM clinical_observations'))
 pressure=reuse(paired,'paired-reuse',later);check(pressure.status==201 and float(pressure.json()['data']['systolic_mm_hg'])==120 and float(pressure.json()['data']['diastolic_mm_hg'])==80 and pressure.json()['data']['source']=='patient_report','pressure components and original reported origin copied')
 sql("INSERT INTO clinical_observations (encounter_id,code,value_numeric,unit,effective_at,effective_at_authority,recorded_at,recorded_by_user_id,source,provenance_json) VALUES (1,'oxygen_saturation',98,'%','2099-01-01 00:00:00','EXPLICIT_EFFECTIVE_TIME',UTC_TIMESTAMP(),'u_a','direct_measurement','{}'),(1,'pain',3,'wrong-unit','2025-09-25 15:42:00','EXPLICIT_EFFECTIVE_TIME',UTC_TIMESTAMP(),'u_a','direct_measurement','{}')")
 future,noncanonical=map(int,sql("SELECT observation_id FROM clinical_observations WHERE (encounter_id=1 AND code='oxygen_saturation') OR unit='wrong-unit' ORDER BY observation_id").splitlines())
 check(reuse(future,'future-reuse',later).status==409 and reuse(noncanonical,'noncanonical-reuse',later).status==409,'future and noncanonical sources rejected')
 forged=req.post(BASE+f'/api/clinical/index.php?route=encounters/enc:{later}/observations',data={'code':'weight','unit':'kg','value_numeric':70,'source':'direct_measurement','provenance':{'reuse_mode':'PRIOR_OBSERVATION','source_observation_id':src}},headers={'Idempotency-Key':'forged-lineage'})
 check(forged.status==400,'ordinary create cannot forge reuse authority')
 anon=p.request.new_context();unauth=anon.post(BASE+f'/api/clinical/index.php?route=encounters/enc:{later}/observations/reuse',data={'source_observation_id':src},headers={'Idempotency-Key':'unauthorized'});check(unauth.status==401,'reuse requires authenticated physician');anon.dispose()
 # Void the new current encounter through existing lifecycle authority.
 voided=req.post(BASE+f'/api/clinical/index.php?route=encounters/enc:{later}/void',data={'reason':'Disposable QA closeout'})
 check(voided.status==200 and reuse(weight,'voided-reuse',later).status==409,'voided current encounter rejected')
 check(sql(f"SELECT COUNT(*) FROM clinical_idempotency_requests WHERE observation_id={new_id}")=='1','reuse uses existing idempotency storage without schema expansion')
 b.close()
print('STEP2_PRIOR_REUSE_R3A_HTTP_GATE=PASS',flush=True)
