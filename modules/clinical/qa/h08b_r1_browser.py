"""H08B-R1 Historial browser gate with synthetic, read-only API responses."""
from pathlib import Path
from playwright.sync_api import sync_playwright

repo = Path(__file__).resolve().parents[3]
source = (repo / 'index.html').read_text()
start = source.index('<section class="lon01-history" id="lon01-history"')
module = source[start:source.index('<div class="clinical-panel mt-2 p-2">', start)]
html = ('<html><head><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet">'
        '<style>.d-none{display:none!important}body{margin:0;padding:250px 12px 0;font-family:Arial}'
        '#p-expediente{max-width:1440px;margin:auto}</style></head><body><div id="p-expediente" data-patient-id="P1">'
        '<div id="t-historial-atencion" class="active"><button data-bs-target="#t-historial-atencion">Historial</button>'
        + module + '</div></div><footer class="mm-footer" style="display:none"></footer></body></html>')

closed = [
    {'encounter_key':'enc:8','encounter_dt':'2026-09-29 08:31:00','status':'closed','appointment_id':'A8'},
    {'encounter_key':'enc:7','encounter_dt':'2026-09-25 09:12:00','status':'closed','appointment_id':None},
]
def doc(id, kind, at, eligible=True, scope='PATIENT', count=None, summary='Descripción canónica'):
    return {'id':id,'document_uuid':f'doc-{id}','document_type':kind,'status':'generated','patient_id':'P1',
        'title':'Receta médica' if kind=='prescription' else 'Orden de laboratorio','summary':summary,
        'timeline_at':at,'timeline_at_source':'generated_at','timeline_scope':scope,'timeline_eligible':eligible,
        'prescription_item_count':count}

documents = [
    doc(11,'prescription','2026-09-29 20:00:00',count=1,summary='Omeprazol 20 mg'),
    doc(12,'lab_order','2026-09-27 15:00:00',summary='Biometría hemática'),
    doc(13,'prescription','2026-09-21 02:00:00',count=3),
    doc(14,'prescription','2026-09-30 18:00:00',eligible=True,scope='ENCOUNTER'),
    doc(15,'lab_order','2026-09-30 18:00:00',eligible=False,scope='ENCOUNTER'),
    doc(16,'lab_order','2026-09-30 18:00:00',eligible=False,scope='APPOINTMENT'),
    doc(17,'prescription','2026-09-30 18:00:00',eligible=False,scope='HOSPITAL_STAY'),
    doc(18,'lab_result','2026-09-30 18:00:00'),
    doc(19,'pdf','2026-09-30 18:00:00'),
    doc(20,'lab_order','2026-09-30 18:00:00',eligible=False,scope='PATIENT'),
]

mock = r'''({encounters,documents,hasOpen})=>{
  window.mxmedStore={doctor_id:'D1'};window.qaCalls=[];window.resumeCalls=[];
  window.mxmedM7OpenFromHeader=async(id,intent)=>window.resumeCalls.push({id,intent});
  window.fetch=async(url,opts={})=>{
    const s=String(url);window.qaCalls.push({url:s,method:opts.method||'GET'});
    const json=data=>({ok:true,json:async()=>({ok:true,data})});
    if(s.includes('longitudinal-history')){
      const offset=Number(new URL(s,'http://local').searchParams.get('offset')||0);
      return json({canonical:encounters.slice(offset,offset+25),legacy:offset===0?[{entry_id:3,note_type:'historia_clinica',status:'draft',payload:{},subjective:'Antecedente'}]:[],has_more:encounters.length>offset+25});
    }
    if(s.endsWith('/encounters/active'))return json(hasOpen?{encounter_key:'enc:9',patient_id:'P1',doctor_id:'D1',status:'open'}:null);
    if(s.includes('timeline_mode=1')){
      const offset=Number(new URL(s,'http://local').searchParams.get('cursor')||0);
      return json({items:documents.slice(offset,offset+10),has_more:documents.length>offset+10,cursor_next:documents.length>offset+10?String(offset+10):null,limit:100});
    }
    if(s.includes('/appointments/A8'))return json({appointment_id:'A8',patient_id:'P1',doctor_id:'D1',consultorio_id:'C1'});
    if(s.includes('/consultorios?'))return json([{doctor_id:'D1',consultorio_id:'C1',name:'Clínica Central'}]);
    if(s.includes('/documents/doc-')){
      const id=Number(s.split('doc-').pop());const row=documents.find(x=>x.id===id);
      if(!row)throw Error('Unknown document');
      return json({document:{document_id:row.document_uuid,document_type:row.document_type,title:row.title,status:'generated',
        context:{patient_id:'P1'},content:{summary:row.summary,rendered_text:row.document_type==='prescription'?'Receta canónica de sólo lectura':null,
        payload:row.document_type==='lab_order'?{requested_studies:['Biometría hemática'],indication:'Control'}:{prescription:{items:[{medicamento:'Omeprazol'}]}}}}});
    }
    if(s.includes('/documents?limit=200'))return json({items:[]});
    if(s.includes('/encounters/')){
      const key=decodeURIComponent(s.split('/').pop());const row=encounters.find(x=>x.encounter_key===key);
      if(!row)throw Error('Unknown encounter');
      return json({patient_id:'P1',doctor_id:'D1',encounter_id:key.split(':')[1],status:row.status,event_datetime:row.encounter_dt,
        closed_at:'2026-09-30 05:38:00',sections:{reason_evolution:{narrative_text:'Nota clínica'}},observations:[],documents:[],amendments:[]});
    }
    throw Error(s);
  };
}'''

measure = '''()=>{const rect=s=>{const r=document.querySelector(s).getBoundingClientRect();return [r.x,r.y,r.width,r.height]};
  return {left:rect('.vis05-timeline'),right:rect('[data-lon01-detail]'),scrollY:scrollY,
    listScroll:document.querySelector('.vis05-timeline').scrollTop,
    cards:[...document.querySelectorAll('.lon01-card.is-closed')].map(e=>{const r=e.getBoundingClientRect();return [r.x,r.y,r.width,r.height]})}}'''

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless=True,args=['--no-sandbox'])
    for width,height in [(1440,810),(1440,900),(1366,768),(390,844)]:
      for has_open,rows in [(True,documents),(False,[])]:
        page=browser.new_page(viewport={'width':width,'height':height},timezone_id='America/Mexico_City')
        page.set_content(html)
        page.add_style_tag(path=str(repo/'assets/css/expediente-paciente-visual-normalization.css'))
        page.evaluate(mock,{'encounters':closed,'documents':rows,'hasOpen':has_open})
        page.add_script_tag(path=str(repo/'assets/js/clinical/lon01-history.js'))
        page.wait_for_selector('[data-lon01-detail]:not(.d-none)')
        page.wait_for_function("document.querySelector('.lon01-closed-venue')?.textContent === 'Clínica Central'")
        if width<=700: page.locator('[data-lon01-close]').click()
        cards=page.locator('.lon01-card.is-closed')
        assert cards.count()==2
        assert [round(cards.nth(i).bounding_box()['height']) for i in range(2)]==[61,61]
        if has_open and width>700: assert round(page.locator('.lon01-card.is-open-consultation').bounding_box()['height'])==58
        assert page.locator('.lon01-card.is-open-consultation').count()==int(has_open)
        assert page.locator('.lon01-event-row').count()==(3 if has_open else 0)
        assert page.evaluate('document.documentElement.scrollWidth')<=width
        if width>700: assert page.evaluate('document.documentElement.scrollHeight')<=height,(width,height,page.evaluate('document.documentElement.scrollHeight'))
        assert '29 sep 2026 · 23:38' in page.locator('[data-lon01-detail-meta]').inner_text()
        if has_open:
            titles=page.locator('[data-lon01-current] strong, [data-lon01-previous] > button strong').all_inner_texts()
            assert titles==['CONSULTA EN CURSO','RECETA','CONSULTA ANTERIOR','ORDEN DE ESTUDIOS','CONSULTA ANTERIOR','RECETA'],titles
            assert '29 SEPTIEMBRE 2026 · Omeprazol 20 mg' in page.locator('.lon01-event-row').nth(0).inner_text()
            assert '3 medicamentos' in page.locator('.lon01-event-row').nth(2).inner_text()
            assert '20 SEPTIEMBRE 2026' in page.locator('.lon01-event-row').nth(2).inner_text()
            assert page.locator('.lon01-event-row .lon01-event-icon').first.evaluate('(e)=>getComputedStyle(e).fontSize')=='27px'
            assert page.locator('.lon01-event-row').first.evaluate('(e)=>getComputedStyle(e).borderRadius')=='0px'
            if width>700:
                page.locator('.lon01-card.is-open-consultation').click()
                assert page.evaluate('resumeCalls')==[{'id':'P1','intent':'resume'}]
                before=page.evaluate(measure)
                page.evaluate('''()=>{window.qaListMutations=0;window.qaDetailShell=document.querySelector('[data-lon01-detail]');
                  new MutationObserver(records=>{qaListMutations+=records.filter(r=>r.type==='childList').length})
                    .observe(document.querySelector('.vis05-timeline'),{childList:true,subtree:true})}''')
                page.locator('.lon01-event-row').nth(0).click()
                page.wait_for_function("document.querySelector('[data-lon01-detail-title]')?.textContent === 'Receta médica'")
                assert 'Receta canónica de sólo lectura' in page.locator('[data-lon01-detail-body]').inner_text()
                assert page.evaluate(measure)==before
                page.locator('.lon01-event-row').nth(1).click()
                page.wait_for_function("document.querySelector('[data-lon01-detail-title]')?.textContent === 'Orden de laboratorio'")
                assert 'Biometría hemática' in page.locator('[data-lon01-detail-body]').inner_text()
                assert page.evaluate(measure)==before
                cards.nth(1).click()
                page.wait_for_function("document.querySelector('[data-lon01-detail-title]')?.textContent === 'Consulta Finalizada'")
                assert page.evaluate(measure)==before
                assert page.locator('.lon01-event-row[aria-pressed="true"]').count()==0
                assert page.locator('.lon01-card.is-closed[aria-pressed="true"]').count()==1
                assert page.locator('[data-lon01-detail] button:visible').count()==0
                assert page.evaluate('qaListMutations')==0
                assert page.evaluate('qaDetailShell===document.querySelector("[data-lon01-detail]")')
            else:
                page.locator('.lon01-event-row').first.click()
                page.wait_for_selector('[data-lon01-detail]:not(.d-none)')
                assert 'Receta canónica de sólo lectura' in page.locator('[data-lon01-detail-body]').inner_text()
                page.locator('[data-lon01-close]').click()
                assert page.locator('.vis05-timeline').is_visible()
        else:
            assert 'Sin consulta activa.' not in page.locator('#lon01-history').inner_text()
            if width>700:
                left=page.locator('.lon01-list-column').bounding_box()['y'];right=page.locator('[data-lon01-detail]').bounding_box()['y']
                assert abs(left-right)<1,(left,right)
        assert all(call['method']=='GET' for call in page.evaluate('qaCalls'))
        print(f'{width}x{height} open={has_open}: PASS')
        if width==1440 and height==810 and has_open:page.screenshot(path='/tmp/h08b_r1_1440x810.png')
        page.close()
    long_encounters=closed+[{'encounter_key':f'enc:{id}','encounter_dt':f'2026-08-{id-19:02d} 09:00:00','status':'closed','appointment_id':None} for id in range(20,44)]
    long_documents=[doc(100+id,'lab_order',f'2026-10-01 {id:02d}:00:00',summary=f'Estudio {id}') for id in range(15,-1,-1)]
    page=browser.new_page(viewport={'width':1440,'height':810},timezone_id='America/Mexico_City')
    page.set_content(html)
    page.add_style_tag(path=str(repo/'assets/css/expediente-paciente-visual-normalization.css'))
    page.evaluate(mock,{'encounters':long_encounters,'documents':long_documents,'hasOpen':True})
    page.add_script_tag(path=str(repo/'assets/js/clinical/lon01-history.js'))
    page.wait_for_function("document.querySelectorAll('.lon01-card.is-closed').length === 26")
    page.wait_for_function("document.querySelector('.vis05-timeline').scrollTop > 0")
    assert page.locator('.lon01-event-row').count()==16
    assert page.locator('.lon01-card.is-closed[aria-pressed="true"]').count()==1
    assert page.locator('.vis05-timeline').evaluate('(e)=>e.scrollHeight>e.clientHeight')
    assert page.locator('.vis05-timeline').evaluate('(e)=>getComputedStyle(e).scrollbarGutter')=='stable'
    assert page.evaluate('document.documentElement.scrollHeight')<=810
    before=page.evaluate(measure)
    page.locator('.lon01-card.is-closed').nth(0).click()
    page.wait_for_function("document.querySelector('[data-lon01-detail]')?.getAttribute('aria-busy') !== 'true'")
    assert page.evaluate(measure)==before,(before,page.evaluate(measure))
    assert sum('timeline_mode=1' in call['url'] for call in page.evaluate('qaCalls'))==2
    assert sum('longitudinal-history' in call['url'] for call in page.evaluate('qaCalls'))==2
    assert all(call['method']=='GET' for call in page.evaluate('qaCalls'))
    print('long paginated timeline and internal scroll: PASS')
    page.close()
    browser.close()
