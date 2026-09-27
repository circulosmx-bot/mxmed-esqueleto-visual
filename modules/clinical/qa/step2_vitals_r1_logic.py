"""Isolated WebKit checks for Step 2 create, edit, void and encounter scoping."""
from pathlib import Path

from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parents[3]

with sync_playwright() as playwright:
    browser = playwright.webkit.launch(headless=True)
    page = browser.new_page()
    page.goto((root / 'index.html').as_uri(), wait_until='domcontentloaded')
    result = page.evaluate('''async () => {
      const root=document.querySelector('#m7-workspace');
      let patient='p1',rows=[],fail=true,calls=[];
      const stamp='2026-09-27 07:17:00';
      const success=data=>({ok:true,status:200,json:async()=>({ok:true,data})});
      window.fetch=async(url,opts={})=>{
        const method=opts.method||'GET';calls.push({url:String(url),method,payload:opts.body?JSON.parse(opts.body):null});
        if(String(url).includes('/longitudinal/measurements'))return success({items:[]});
        if(method==='POST'&&String(url).endsWith('/observations')){
          if(fail)return {ok:false,status:422,json:async()=>({ok:false,error:{code:'TEST_FAILURE'}})};
          const payload=JSON.parse(opts.body);
          rows=[{observation_id:7,code:payload.code,value_numeric:payload.value_numeric,unit:payload.unit,source:payload.source,
            effective_at:stamp,recorded_at:stamp,effective_at_authority:'EXPLICIT_EFFECTIVE_TIME',row_version:1,invalidated_at:null}];
          return success(rows[0]);
        }
        if(method==='PATCH'){
          const payload=JSON.parse(opts.body);rows=[{...rows[0],value_numeric:payload.value_numeric,row_version:2}];return success(rows[0]);
        }
        if(method==='POST'&&String(url).endsWith('/void')){
          const removed={...rows[0],invalidated_at:stamp,row_version:3};rows=[];return success(removed);
        }
        return success({patient_id:patient,observations:rows,sections:{}});
      };
      const ws=window.mxmedM7WS03(root,key=>'/api/clinical/index.php/encounters/'+key,()=>patient,()=>{});
      const code=root.querySelector('[data-m7-measurement-code]');
      const values=()=>[...code.options].map(option=>option.value);
      const list=()=>root.querySelector('[data-m7-measurements-list]');
      ws.load({patient_id:patient,encounter_id:1,observations:rows,sections:{}},'one','open');ws.select('measurements');
      const initiallyAvailable=values().includes('temperature');
      code.value='temperature';code.dispatchEvent(new Event('change',{bubbles:true}));
      const input=root.querySelector('[data-m7-measurement-value]');input.value='36.5';input.dispatchEvent(new Event('input',{bubbles:true}));
      const source=root.querySelector('[data-m7-measurement-source]');source.value='direct_measurement';source.dispatchEvent(new Event('change',{bubbles:true}));
      const failed=await ws.saveSelected();
      const failureKeepsOption=values().includes('temperature')&&input.value==='36.5'&&list().querySelectorAll('.vis-step2-chip').length===0;
      fail=false;
      const created=await ws.saveSelected();
      const addCall=calls.find(call=>call.method==='POST'&&call.url.endsWith('/observations')&&!('effective_at' in call.payload));
      const successFilters=!values().includes('temperature')&&list().querySelectorAll('.vis-step2-chip').length===1;
      ws.load({patient_id:patient,encounter_id:1,observations:rows,sections:{}},'one','open');ws.select('measurements');
      const reloadFilters=!values().includes('temperature');
      list().querySelector('[aria-label^="Editar: Temperatura"]').click();
      input.value='36.6';input.dispatchEvent(new Event('input',{bubbles:true}));
      const edited=await ws.saveSelected();
      const editKeepsOne=edited&&list().querySelectorAll('.vis-step2-chip').length===1&&list().textContent.includes('36.6 °C')&&!values().includes('temperature');
      list().querySelector('[aria-label^="Eliminar: Temperatura"]').click();
      const dialog=root.querySelector('[data-meas01-confirm]');dialog.close('confirm');
      await new Promise(resolve=>setTimeout(resolve,0));
      await new Promise(resolve=>setTimeout(resolve,0));
      const voidRestores=rows.length===0&&values().includes('temperature')&&list().querySelectorAll('.vis-step2-chip').length===0;
      patient='p2';ws.load({patient_id:patient,encounter_id:2,observations:[],sections:{}},'two','open');ws.select('measurements');
      const otherEncounterRestores=values().includes('temperature');
      return {initiallyAvailable,failed:failed===false,failureKeepsOption,created,serverModeSent:addCall?.payload.capture_time_mode==='SERVER_AT_SAVE',
        noClientTime:!('effective_at' in (addCall?.payload||{})),successFilters,reloadFilters,editKeepsOne,voidRestores,otherEncounterRestores};
    }''')
    assert all(result.values()), result
    print('STEP2_VITALS_R1_LOGIC_GATE=PASS', result, flush=True)
    browser.close()
