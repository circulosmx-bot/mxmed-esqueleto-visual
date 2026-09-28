"""Frozen resolver + actual WS03 anchors, explicit writes, cancel/edit and context safety."""
import json,os,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[3]
OUT=Path(os.environ.get('VITALREF03_ARTIFACTS','/tmp/mxmed-vitalref03'));OUT.mkdir(parents=True,exist_ok=True)
references=json.loads(subprocess.check_output(['php','-r','require $argv[1];$today=new DateTimeImmutable("today",new DateTimeZone("America/Mexico_City"));echo json_encode([clinical_vital_references_resolve("1990-01-01"),clinical_vital_references_resolve($today->modify("-8 years")->format("Y-m-d")),clinical_vital_references_resolve($today->modify("-14 years")->format("Y-m-d")),clinical_vital_references_resolve($today->modify("-6 months")->format("Y-m-d")),clinical_vital_references_resolve(null)]);',str(ROOT/'api/_lib/clinical_vital_references.php')],text=True))
with sync_playwright() as pw:
    browser=pw.webkit.launch();page=browser.new_page();page.on('dialog',lambda d:d.accept());page.goto((ROOT/'index.html').as_uri(),wait_until='domcontentloaded')
    result=page.evaluate(r'''async references=>{
      // Isolate this controller from the app's initial file-preview controller.
      const original=document.querySelector('#m7-workspace'),root=original.cloneNode(true);original.replaceWith(root);
      root.querySelectorAll('.vitalref-number').forEach(wrapper=>wrapper.replaceWith(wrapper.querySelector('input')));
      let patient='adult',calls=[],pending=[],rows=[];
      const tick=async()=>{await new Promise(r=>setTimeout(r,0));await new Promise(r=>setTimeout(r,0));};
      const success=data=>({ok:true,status:200,json:async()=>({ok:true,data})});
      window.fetch=async(url,opts={})=>{
        const method=opts.method||'GET';calls.push({url:String(url),method,cache:opts.cache,body:opts.body?JSON.parse(opts.body):null});
        if(String(url).endsWith('/vital-references'))return new Promise(resolve=>pending.push({patient,resolve}));
        if(method==='POST'&&String(url).endsWith('/observations')){
          const body=JSON.parse(opts.body);rows=[{observation_id:7,encounter_id:1,code:body.code,unit:body.unit,value_numeric:body.value_numeric,source:body.source,row_version:1,invalidated_at:null,recorded_at:'2026-09-28 01:00:00',effective_at:'2026-09-28 01:00:00',effective_at_authority:'EXPLICIT_EFFECTIVE_TIME',provenance:{capture_time_mode:'SERVER_AT_SAVE'}}];return success(rows[0]);
        }
        return success({items:[],patient_id:patient,observations:rows,sections:{}});
      };
      const ws=mxmedM7WS03(root,k=>'/encounters/'+k,()=>patient,()=>{});
      const controls=['value','systolic','diastolic'].map(k=>root.querySelector('[data-m7-measurement-'+k+']'));
      const code=root.querySelector('[data-m7-measurement-code]'),source=root.querySelector('[data-m7-measurement-source]');
      const hint=root.querySelector('[data-vitalref-hint]'),tooltip=root.querySelector('[data-vitalref-tooltip]');
      const placeholders=()=>controls.map(n=>n.placeholder),empty=()=>controls.every(n=>n.value==='');
      const choose=name=>{code.value=name;code.dispatchEvent(new Event('change',{bubbles:true}));};
      const load=()=>{ws.load({patient_id:patient,encounter_id:1,observations:rows,sections:{}},patient,'open');ws.select('measurements');};
      const resolve=async data=>{pending.shift().resolve(success(data));await tick();};
      const clean=()=>!ws.isDirty()&&!ws.hasSavedDrafts()&&empty()&&source.value===''&&root.querySelectorAll('.vis-step2-chip').length===0;
      const expected={blood_pressure:['','Ref. <120','Ref. <80'],heart_rate:['Ref. 60–100','',''],respiratory_rate:['Ref. 12–18','',''],temperature:['Ref. 36.5–37.3','',''],oxygen_saturation:['Ref. 95–100','',''],pain:['Escala 0–10','',''],weight:['','',''],height:['','',''],waist:['','','']};
      load();await resolve(references[0]);const safety={};
      for(const [name,wanted] of Object.entries(expected)){
        choose(name);const storage=JSON.stringify(Object.entries(sessionStorage));
        const emptyInitially=empty(),referenceMatches=JSON.stringify(placeholders())===JSON.stringify(wanted);
        ws.remember();await ws.saveSelected();root.querySelector('[data-m7-measurements-form]').requestSubmit();await tick();
        const item=references[0].items.find(n=>n.measurement_code===name);
        safety[name]={emptyInitially,referenceMatches,noDirtyOrDraft:clean()&&storage===JSON.stringify(Object.entries(sessionStorage)),noWrite:calls.every(c=>c.method==='GET'),sourcePreserved:hint.hidden===!item.display_reference&&tooltip.textContent.split('\n')[0]===item.display_reference};
        if(wanted[0]||wanted[1]){
          const input=name==='blood_pressure'?controls[1]:controls[0];input.value='81';input.dispatchEvent(new Event('input',{bubbles:true}));
          safety[name].hiddenOnEntry=!input.matches(':placeholder-shown')&&input.value==='81';
          input.value='';input.dispatchEvent(new Event('input',{bubbles:true}));safety[name].returnsOnClear=input.matches(':placeholder-shown')&&input.value==='';
          root.querySelector('[data-m7-measurement-new]').click();
        }
      }

      const saveButton=root.querySelector('[data-m7-measurement-save]');
      const cancel=()=>root.querySelector('[data-m7-measurement-new]').click();
      const arrow=(input,direction)=>input.parentElement.querySelector(`[data-vitalref-step="${direction}"]`).click();
      const keyboard=(input,direction)=>input.dispatchEvent(new KeyboardEvent('keydown',{key:direction===1?'ArrowUp':'ArrowDown',bubbles:true,cancelable:true}));
      const spinner={};
      const cases=[['blood_pressure',1,121,119,130],['blood_pressure',2,81,79,90],['heart_rate',0,81,79,112],['respiratory_rate',0,16,14,24],['temperature',0,37,36.8,38.5],['oxygen_saturation',0,99,97,91]];
      for(const [name,index,up,down,manual] of cases){
        choose(name);const input=controls[index],storage=JSON.stringify(Object.entries(sessionStorage)),placeholder=input.placeholder;
        input.focus();input.dispatchEvent(new Event('pointerenter'));ws.remember();
        const beforeSafe=clean()&&saveButton.disabled&&storage===JSON.stringify(Object.entries(sessionStorage));
        arrow(input,1);const firstUp=Number(input.value)===up,decimalVisible=name!=='temperature'||input.value==='37.0';
        const step=Number(input.step);arrow(input,1);const continued=Math.abs(Number(input.value)-(up+step))<1e-6;
        keyboard(input,-1);const returned=Number(input.value)===up;
        const explicitDirty=ws.isDirty()&&saveButton.disabled&&ws.hasSavedDrafts();
        const draft=sessionStorage.getItem('mxmed.m7.ws03.draft:adult:measurements');
        const draftOnlyValues=!/anchor|reference|Ref\.|Escala/.test(draft);
        cancel();choose(name);arrow(input,-1);const firstDown=Number(input.value)===down;
        cancel();choose(name);keyboard(input,1);const keyboardUp=Number(input.value)===up;
        cancel();choose(name);keyboard(input,-1);const keyboardDown=Number(input.value)===down;
        cancel();choose(name);input.value=String(manual);input.dispatchEvent(new Event('input',{bubbles:true}));
        const abnormalAllowed=input.validity.valid&&input.min==='0'&&input.max==='';
        arrow(input,1);const manualUp=Math.abs(Number(input.value)-(manual+step))<1e-6;
        arrow(input,-1);const manualDown=Number(input.value)===manual;
        cancel();choose(name);
        spinner[name+':'+index]={beforeSafe,firstUp,firstDown,keyboardUp,keyboardDown,decimalVisible,continued,returned,explicitDirty,draftOnlyValues,abnormalAllowed,manualUp,manualDown,cancelSafe:clean()&&input.value===''&&input.placeholder===placeholder&&saveButton.disabled};
      }
      choose('blood_pressure');arrow(controls[1],1);source.value='direct_measurement';source.dispatchEvent(new Event('change',{bubbles:true}));
      const partialBPBlocked=saveButton.disabled;await ws.saveSelected();const partialBPNoWrite=calls.every(c=>c.method==='GET');arrow(controls[2],1);const completeBPReady=!saveButton.disabled;cancel();
      choose('pain');arrow(controls[0],1);const painUp=controls[0].value==='1';cancel();choose('pain');arrow(controls[0],-1);const painDown=controls[0].value==='0';cancel();
      const noAnthropometricAnchors={};
      for(const name of ['weight','height','waist']){
        choose(name);const input=controls[0];const initiallyNative=input.step==='any'&&input.placeholder===''&&input.parentElement.querySelectorAll('[data-vitalref-step]:not([hidden])').length===0&&clean();
        input.value='78.25';input.dispatchEvent(new Event('input',{bubbles:true}));
        noAnthropometricAnchors[name]=initiallyNative&&input.value==='78.25'&&input.validity.valid;cancel();
      }
      choose('heart_rate');source.value='direct_measurement';source.dispatchEvent(new Event('change',{bubbles:true}));
      const sourceOnlyCannotSave=saveButton.disabled&&empty();cancel();
      const noReferenceWrites=calls.every(c=>c.method==='GET');
      choose('heart_rate');arrow(controls[0],1);source.value='direct_measurement';source.dispatchEvent(new Event('change',{bubbles:true}));ws.remember();
      const draft=sessionStorage.getItem('mxmed.m7.ws03.draft:adult:measurements');
      hint.focus();hint.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));
      const tooltipPreservesDraft=tooltip.textContent.includes('MedlinePlus')&&tooltip.textContent.includes('review-2025-01-01')&&controls[0].value==='81'&&sessionStorage.getItem('mxmed.m7.ws03.draft:adult:measurements')===draft;
      hint.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
      const explicitReady=!saveButton.disabled;const saved=await ws.saveSelected();const command=calls.find(c=>c.method==='POST');
      const realValueOnly=saved&&command.body.value_numeric===81&&command.body.capture_time_mode==='SERVER_AT_SAVE'&&!JSON.stringify(command.body).match(/Ref\.|Escala|reference|placeholder|source_title/)&&!draft.match(/Ref\.|Escala|reference|placeholder/);
      root.querySelector('[aria-label^="Editar: Frecuencia cardíaca"]').click();
      const editShowsSavedValue=controls[0].value==='81'&&controls[0].placeholder==='Ref. 60–100'&&!controls[0].matches(':placeholder-shown');
      arrow(controls[0],1);const savedValueWins=controls[0].value==='82';arrow(controls[0],-1);
      controls[0].value='';controls[0].dispatchEvent(new Event('input',{bubbles:true}));
      const editClearNeverPrefills=controls[0].value===''&&controls[0].matches(':placeholder-shown')&&ws.isDirty();
      root.querySelector('[data-m7-measurement-new]').click();rows=[];choose('blood_pressure');
      patient='child';load();const clearedAtSwitch=placeholders().every(n=>!n)&&hint.hidden;const childRead=pending.shift();
      patient='adult';load();const newest=pending.shift();childRead.resolve(success(references[1]));await tick();const lateChildIgnored=placeholders().every(n=>!n)&&hint.hidden;
      newest.resolve(success(references[0]));await tick();const newestApplied=controls[1].placeholder==='Ref. <120';
      patient='child';load();await resolve(references[1]);
      const childSafe=placeholders().every(n=>!n)&&tooltip.textContent.startsWith('Referencia pediátrica: requiere edad, sexo y talla.')&&!hint.hidden;
      arrow(controls[1],1);const childNoAdultAnchor=controls[1].value==='1';cancel();
      choose('heart_rate');const childHRNeutral=placeholders().every(n=>!n)&&tooltip.textContent.includes('Referencia pediátrica no disponible');
      patient='adolescent';load();await resolve(references[2]);const adolescentBP=controls[1].placeholder==='Ref. <120'&&controls[2].placeholder==='Ref. <80'&&tooltip.textContent.includes('AAP');
      patient='infant';load();await resolve(references[3]);const infantNeutral=placeholders().every(n=>!n);choose('pain');const infantPainNeutral=placeholders().every(n=>!n)&&tooltip.textContent.includes('verificar edad');
      patient='missing';load();await resolve(references[4]);const missingNeutral=placeholders().every(n=>!n)&&tooltip.textContent.includes('fecha de nacimiento');
      patient='adult';load();const late=pending.shift();ws.reset();late.resolve(success(references[0]));await tick();const resetRejectsLate=hint.hidden&&placeholders().every(n=>!n);
      patient='failure';load();pending.shift().resolve({ok:false,status:503,json:async()=>({ok:false,error:'unavailable'})});await tick();const failureSafe=clean()&&hint.hidden&&placeholders().every(n=>!n);
      return {safety,spinner,noAnthropometricAnchors,painUp,painDown,partialBPBlocked,partialBPNoWrite,completeBPReady,sourceOnlyCannotSave,explicitReady,savedValueWins,childNoAdultAnchor,noReferenceWrites,tooltipPreservesDraft,realValueOnly,editShowsSavedValue,editClearNeverPrefills,clearedAtSwitch,lateChildIgnored,newestApplied,childSafe,childHRNeutral,adolescentBP,infantNeutral,infantPainNeutral,missingNeutral,resetRejectsLate,failureSafe,noCache:calls.filter(c=>c.url.endsWith('/vital-references')).every(c=>c.cache==='no-store')};
    }''',references)
    assert all(all(checks.values()) for group in ['safety','spinner'] for checks in result[group].values()) and all(result['noAnthropometricAnchors'].values()) and all(v for k,v in result.items() if k not in ['safety','spinner','noAnthropometricAnchors']),result
    (OUT/'logic-report.json').write_text(json.dumps(result,indent=2));print('VITALREF03_WEBKIT_LOGIC_GATE=PASS',json.dumps(result),flush=True);browser.close()
