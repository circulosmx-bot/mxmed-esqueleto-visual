"""Patient/encounter-scoped UI type, fresh reference guards, drafts and used-type authority."""
import json,os,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[3]
OUT=Path(os.environ.get('VITALREF03_R1_ARTIFACTS','/tmp/mxmed-vitalref03-r1'));OUT.mkdir(parents=True,exist_ok=True)
references=json.loads(subprocess.check_output(['php','-r','require $argv[1];echo json_encode([clinical_vital_references_resolve("1990-01-01"),clinical_vital_references_resolve((new DateTimeImmutable("today",new DateTimeZone("America/Mexico_City")))->modify("-8 years")->format("Y-m-d"))]);',str(ROOT/'api/_lib/clinical_vital_references.php')],text=True))
with sync_playwright() as p:
 browser=p.webkit.launch();page=browser.new_page();page.on('dialog',lambda d:d.accept());page.goto((ROOT/'index.html').as_uri(),wait_until='domcontentloaded')
 result=page.evaluate(r'''async references=>{
  const original=document.querySelector('#m7-workspace'),root=original.cloneNode(true);original.replaceWith(root);root.querySelectorAll('.vitalref-number').forEach(w=>w.replaceWith(w.querySelector('input')));
  let patient='adult',key='A1',rows=[],pending=[],calls=[];
  const success=data=>({ok:true,status:200,json:async()=>({ok:true,data})}),tick=async()=>{await new Promise(r=>setTimeout(r,0));await new Promise(r=>setTimeout(r,0));};
  window.fetch=async(url,opts={})=>{
   const method=opts.method||'GET';calls.push({url:String(url),method,body:opts.body?JSON.parse(opts.body):null});
   if(String(url).endsWith('/vital-references'))return new Promise(resolve=>pending.push(resolve));
   if(method==='POST'&&String(url).endsWith('/observations')){const body=JSON.parse(opts.body);rows=[{...body,observation_id:9,encounter_id:1,row_version:1,invalidated_at:null,recorded_at:'2026-09-28 01:00:00',effective_at:'2026-09-28 01:00:00',effective_at_authority:'EXPLICIT_EFFECTIVE_TIME',provenance:{capture_time_mode:'SERVER_AT_SAVE'}}];return success(rows[0]);}
   return success({items:[],patient_id:patient,observations:rows,sections:{}});
  };
  const ws=mxmedM7WS03(root,k=>'/encounters/'+k,()=>patient,()=>{}),code=root.querySelector('[data-m7-measurement-code]'),value=root.querySelector('[data-m7-measurement-value]'),sys=root.querySelector('[data-m7-measurement-systolic]');
  const choose=name=>{code.value=name;code.dispatchEvent(new Event('change',{bubbles:true}));},up=input=>input.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowUp',bubbles:true,cancelable:true}));
  const load=()=>{ws.load({patient_id:patient,encounter_id:1,observations:rows,sections:{}},key,'open');ws.select('measurements');};
  const resolve=async data=>{pending.shift()(success(data));await tick();},cancel=()=>root.querySelector('[data-m7-measurement-new]').click();
  const clean=()=>!ws.isDirty()&&!ws.hasSavedDrafts()&&[value,sys,root.querySelector('[data-m7-measurement-diastolic]')].every(n=>n.value==='');
  load();await resolve(references[0]);choose('heart_rate');
  const onlyUITypeStored=clean()&&sessionStorage.getItem('mxmed.m7.ws03.entry-type:adult:A1')==='heart_rate'&&!Object.keys(sessionStorage).some(k=>k.startsWith('mxmed.m7.ws03.draft:'));
  code.replaceChildren();load();await resolve(references[0]);const reenterWithoutToggle=code.value==='heart_rate'&&value.placeholder==='Ref. 60–100'&&clean();up(value);const reenteredArrow=value.value==='81';cancel();
  choose('temperature');patient='child';key='B1';load();const clearedAtPatientSwitch=[value,sys].every(n=>n.placeholder==='');await resolve(references[1]);
  const childDoesNotInheritAdultType=code.value==='blood_pressure'&&sys.placeholder===''&&clean();up(sys);const childDoesNotInheritAdultAnchor=sys.value==='1';cancel();choose('oxygen_saturation');
  patient='adult';key='A1';load();await resolve(references[0]);const returnPatientType=code.value==='temperature'&&value.placeholder==='Ref. 36.5–37.3'&&clean();up(value);const returnPatientAnchor=value.value==='37.0';cancel();
  key='A2';load();await resolve(references[0]);const encounterScoped=code.value==='blood_pressure'&&clean();
  key='A1';load();const old=pending.shift();patient='child';key='B1';load();const newest=pending.shift();old(success(references[0]));await tick();const staleReferenceRejected=value.placeholder==='';newest(success(references[1]));await tick();const newestReferenceApplied=code.value==='oxygen_saturation'&&value.placeholder==='Ref. 95–100'&&clean();up(value);const newestAnchor=value.value==='99';cancel();
  patient='adult';key='A1';load();await resolve(references[0]);choose('heart_rate');value.value='112';value.dispatchEvent(new Event('input',{bubbles:true}));const raw=sessionStorage.getItem('mxmed.m7.ws03.draft:A1:measurements');
  load();await resolve(references[0]);const realDraftAutoRestored=!value.disabled&&value.value==='112'&&ws.isDirty()&&sessionStorage.getItem('mxmed.m7.ws03.draft:A1:measurements')===raw;
  up(value);const recoveredActualValueWins=value.value==='113';cancel();
  rows=[{observation_id:7,encounter_id:1,code:'heart_rate',unit:'bpm',value_numeric:68,source:'direct_measurement',row_version:1,invalidated_at:null,provenance:{reuse_mode:'PRIOR_OBSERVATION',source_observation_id:6}}];load();await resolve(references[0]);
  const usedTypeFiltersPreference=!Array.from(code.options).some(n=>n.value==='heart_rate')&&code.value!=='heart_rate';root.querySelector('[aria-label^="Editar: Frecuencia cardíaca"]').click();up(value);const reusedActualValueWins=value.value==='69';cancel();rows=[];
  load();await resolve(references[0]);choose('heart_rate');up(value);const source=root.querySelector('[data-m7-measurement-source]');source.value='direct_measurement';source.dispatchEvent(new Event('change',{bubbles:true}));const saved=await ws.saveSelected(),command=calls.find(c=>c.method==='POST');
  const serverAtSave=saved&&command.body.value_numeric===81&&command.body.capture_time_mode==='SERVER_AT_SAVE'&&!JSON.stringify(command.body).match(/entry-type|entry_anchor|reference|effective_at/);
  sessionStorage.setItem('mxmed.m7.ws03.entry-type:adult:A2','not-a-measurement');key='A2';rows=[];load();await resolve(references[0]);const invalidChoiceSafe=code.value==='blood_pressure'&&clean();
  return {onlyUITypeStored,reenterWithoutToggle,reenteredArrow,clearedAtPatientSwitch,childDoesNotInheritAdultType,childDoesNotInheritAdultAnchor,returnPatientType,returnPatientAnchor,encounterScoped,staleReferenceRejected,newestReferenceApplied,newestAnchor,realDraftAutoRestored,recoveredActualValueWins,usedTypeFiltersPreference,reusedActualValueWins,serverAtSave,invalidChoiceSafe};
 }''',references)
 assert all(result.values()),result
 (OUT/'logic-report.json').write_text(json.dumps(result,indent=2));print('VITALREF03_R1_LOGIC_GATE=PASS',json.dumps(result),flush=True);browser.close()
