"""WebKit safety checks using the actual PHP registry; no Director mutations."""
import json,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[3]
references=json.loads(subprocess.check_output(['php','-r','require $argv[1]; echo json_encode([clinical_vital_references_resolve("1990-01-01"),clinical_vital_references_resolve((new DateTimeImmutable("today"))->modify("-8 years")->format("Y-m-d"))]);',str(ROOT/'api/_lib/clinical_vital_references.php')],text=True))
with sync_playwright() as pw:
 browser=pw.webkit.launch();page=browser.new_page();page.goto((ROOT/'index.html').as_uri(),wait_until='domcontentloaded')
 result=page.evaluate('''async references=>{
  const root=document.querySelector('#m7-workspace');let patient='adult',calls=[],pending=[];
  const tick=()=>new Promise(r=>setTimeout(r,0));
  const success=data=>({ok:true,status:200,json:async()=>({ok:true,data})});
  window.fetch=async(url,opts={})=>{
    calls.push({url:String(url),method:opts.method||'GET',cache:opts.cache});
    if(String(url).endsWith('/vital-references'))return new Promise(resolve=>pending.push({patient,resolve}));
    return success({items:[],patient_id:patient,observations:[],sections:{}});
  };
  const ws=window.mxmedM7WS03(root,k=>'/encounters/'+k,()=>patient,()=>{});
  const load=()=>{ws.load({patient_id:patient,encounter_id:1,observations:[],sections:{}},patient,'open');ws.select('measurements');};
  const code=root.querySelector('[data-m7-measurement-code]'),hint=root.querySelector('[data-vitalref-hint]'),tooltip=root.querySelector('[data-vitalref-tooltip]');
  const inputs=['value','systolic','diastolic'].map(k=>root.querySelector('[data-m7-measurement-'+k+']'));
  const clean=()=>!ws.isDirty()&&!ws.hasSavedDrafts()&&inputs.every(n=>n.value==='')&&root.querySelector('[data-m7-measurement-source]').value===''&&root.querySelectorAll('.vis-step2-chip').length===0;
  load();const adultRead=pending.shift();adultRead.resolve(success(references[0]));await tick();await tick();
  const safety={};
  for(const item of references[0].items){
    code.value=item.measurement_code;code.dispatchEvent(new Event('change',{bubbles:true}));
    const before=JSON.stringify(Object.entries(sessionStorage));ws.remember();await ws.saveSelected();
    safety[item.measurement_code]=clean()&&hint.hidden===!item.display_reference&&tooltip.textContent.split('\\n')[0]===item.display_reference&&before===JSON.stringify(Object.entries(sessionStorage))&&!root.querySelector('[data-m7-measurements-form]').checkValidity();
  }
  code.value='heart_rate';code.dispatchEvent(new Event('change',{bubbles:true}));
  inputs[0].value='81';inputs[0].dispatchEvent(new Event('input',{bubbles:true}));ws.remember();
  const manualDraft=sessionStorage.getItem('mxmed.m7.ws03.draft:adult:measurements');
  hint.focus();hint.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape'}));
  const tooltipDoesNotTouchDraft=inputs[0].value==='81'&&sessionStorage.getItem('mxmed.m7.ws03.draft:adult:measurements')===manualDraft;
  sessionStorage.removeItem('mxmed.m7.ws03.draft:adult:measurements');
  patient='child';load();const clearedAtSwitch=hint.hidden;const childRead=pending.shift();
  patient='adult';load();const newestRead=pending.shift();
  childRead.resolve(success(references[1]));await tick();await tick();const lateChildIgnored=hint.hidden;
  newestRead.resolve(success(references[0]));await tick();await tick();const newestApplied=tooltip.textContent.includes('Referencia adulta:');
  patient='child';load();pending.shift().resolve(success(references[1]));await tick();await tick();
  const childNoAdult=tooltip.textContent.split('\\n')[0]==='Referencia pediátrica: requiere edad, sexo y talla.';
  patient='adult';load();const late=pending.shift();ws.reset();late.resolve(success(references[0]));await tick();await tick();
  const resetRejectsLate=hint.hidden;
  patient='unavailable';load();pending.shift().resolve({ok:false,status:503,json:async()=>({ok:false,error:'unavailable'})});await tick();await tick();
  const failureSafe=hint.hidden&&clean();
  return {safety,tooltipDoesNotTouchDraft,clearedAtSwitch,lateChildIgnored,newestApplied,childNoAdult,resetRejectsLate,failureSafe,noWrites:calls.every(c=>c.method==='GET'),noCache:calls.filter(c=>c.url.endsWith('/vital-references')).every(c=>c.cache==='no-store')};
 }''',references)
 assert all(result['safety'].values()) and all(v for k,v in result.items() if k!='safety'),result
 print('VITALREF01_WEBKIT_SAFETY_GATE=PASS',json.dumps(result),flush=True);browser.close()
