"""H09 read-only browser gate for the already-loaded mixed Historial chronology."""
from pathlib import Path
from playwright.sync_api import sync_playwright

repo = Path(__file__).resolve().parents[3]
source = (repo / 'index.html').read_text()
start = source.index('<section class="lon01-history" id="lon01-history"')
module = source[start:source.index('<div class="clinical-panel mt-2 p-2">', start)]
html = ('<html><head><style>.d-none{display:none!important}body{margin:0;font-family:Arial}'
        '#p-expediente{max-width:1440px;margin:auto}</style></head><body>'
        '<div id="p-expediente" data-patient-id="P1"><div id="t-historial-atencion" class="active">'
        '<button data-bs-target="#t-historial-atencion">Historial</button>' + module +
        '</div></div><footer class="mm-footer" style="display:none"></footer></body></html>')


def document(number, kind, at, scope='PATIENT', eligible=True):
    return {'id':number, 'document_uuid':f'doc-{number}', 'document_type':kind,
            'status':'generated', 'patient_id':'P1', 'title':f'Documento {number}',
            'summary':f'Registro {number}', 'timeline_at':at,
            'timeline_scope':scope, 'timeline_eligible':eligible}


encounters = [
    {'encounter_key':'enc:40', 'encounter_dt':'2026-09-29 08:31:00', 'status':'closed'},
    {'encounter_key':'enc:39', 'encounter_dt':'2026-09-25 09:12:00', 'status':'voided'},
    {'encounter_key':'enc:38', 'encounter_dt':'2026-09-21 09:12:00', 'status':'closed'},
]
documents = [
    document(1,'prescription','2026-09-30 20:00:00'),
    document(2,'lab_order','2026-09-28 15:00:00'),
    document(3,'imaging_order','2026-09-24 15:00:00'),
    document(4,'prescription','2026-09-20 15:00:00'),
    document(5,'prescription','2026-09-30 21:00:00','ENCOUNTER'),
    document(6,'lab_order','2026-09-30 22:00:00','ENCOUNTER'),
    document(7,'lab_result','2026-09-30 23:00:00'),
]
mock = r'''({encounters,documents,hasOpen})=>{
  window.mxmedStore={doctor_id:'D1'};window.qaCalls=[];window.resumeCalls=[];
  window.mxmedM7OpenFromHeader=async(id,intent)=>window.resumeCalls.push({id,intent});
  window.fetch=async(url,opts={})=>{
    const s=String(url);window.qaCalls.push({url:s,method:opts.method||'GET'});
    const json=data=>({ok:true,json:async()=>({ok:true,data})});
    if(s.includes('longitudinal-history')){
      const offset=Number(new URL(s,'http://local').searchParams.get('offset')||0);
      return json({canonical:encounters.slice(offset,offset+25),legacy:[],has_more:encounters.length>offset+25});
    }
    if(s.endsWith('/encounters/active'))return json(hasOpen?{encounter_key:'enc:41',patient_id:'P1',doctor_id:'D1',status:'open'}:null);
    if(s.includes('timeline_mode=1')){
      const offset=Number(new URL(s,'http://local').searchParams.get('cursor')||0);
      return json({items:documents.slice(offset,offset+10),has_more:documents.length>offset+10,
        cursor_next:documents.length>offset+10?String(offset+10):null});
    }
    if(s.includes('/documents/doc-')){
      const id=Number(s.split('doc-').pop()),row=documents.find(item=>item.id===id);
      if(!row)throw Error('Unknown document');
      return json({document:{document_id:row.document_uuid,document_type:row.document_type,
        title:row.title,status:'generated',context:{patient_id:'P1'},content:{summary:row.summary,payload:{}}}});
    }
    if(s.includes('/documents?limit=200'))return json({items:[]});
    if(s.includes('/encounters/')){
      const key=decodeURIComponent(s.split('/').pop()),row=encounters.find(item=>item.encounter_key===key);
      if(!row)throw Error('Unknown encounter');
      return json({patient_id:'P1',doctor_id:'D1',encounter_id:key.split(':')[1],status:row.status,
        event_datetime:row.encounter_dt,closed_at:'2026-09-30 05:38:00',sections:{},
        observations:[],documents:[],amendments:[]});
    }
    throw Error(s);
  };
}'''


def enter(page, rows=encounters, docs=documents, has_open=True):
    page.set_content(html)
    page.add_style_tag(path=str(repo / 'assets/css/expediente-paciente-visual-normalization.css'))
    # The isolated fixture has no icon-font stylesheet; clip literal ligature text.
    page.add_style_tag(content='.material-symbols-rounded{overflow:hidden!important;white-space:nowrap!important}')
    page.evaluate(mock, {'encounters':rows, 'documents':docs, 'hasOpen':has_open})
    page.add_script_tag(path=str(repo / 'assets/js/clinical/lon01-history.js'))
    page.wait_for_selector('[data-lon01-filters]:not(.d-none)')
    if rows or docs or has_open:
        page.wait_for_function("document.querySelectorAll('[data-lon01-kind]').length >= 3")
    if page.viewport_size['width'] <= 700 and page.locator('[data-lon01-detail]').is_visible():
        page.locator('[data-lon01-close]').click()


def visible_kinds(page):
    return page.locator('[data-lon01-kind]').evaluate_all(
        '(items)=>items.filter(item=>!item.hidden).map(item=>item.dataset.lon01Kind)')


def choose(page, key):
    page.locator(f'[data-lon01-filter="{key}"]').click()
    assert page.locator(f'[data-lon01-filter="{key}"]').get_attribute('aria-pressed') == 'true'
    assert page.locator('[data-lon01-filter][aria-pressed="true"]').count() == 1


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    for width, height in [(1440,810),(1440,900),(1366,768),(390,844),(320,700)]:
        page = browser.new_page(viewport={'width':width,'height':height}, timezone_id='America/Mexico_City')
        enter(page)
        assert visible_kinds(page) == ['consultations','prescriptions','consultations','studies','consultations','studies','consultations','prescriptions']
        assert page.locator('[data-lon01-filter="all"]').get_attribute('aria-pressed') == 'true'
        assert page.locator('.lon01-card.is-open-consultation').is_visible()
        assert page.locator('.lon01-event-row').count() == 4
        baseline_height = page.locator('.lon01-card.is-closed').first.bounding_box()['height']
        row_height = page.locator('.lon01-event-row').first.bounding_box()['height']
        page_y = page.evaluate('scrollY')
        timeline_reads = lambda: sum('longitudinal-history' in x['url'] or 'timeline_mode=1' in x['url'] for x in page.evaluate('qaCalls'))
        reads = timeline_reads()
        choose(page,'consultations')
        assert visible_kinds(page) == ['consultations']*4
        assert page.locator('.lon01-card.is-open-consultation').get_attribute('hidden') is None
        choose(page,'prescriptions')
        assert visible_kinds(page) == ['prescriptions']*2
        assert page.locator('.lon01-card.is-open-consultation').get_attribute('hidden') is not None
        assert page.locator('[data-lon01-detail-title]').inner_text() == 'Documento 1'
        choose(page,'studies')
        assert visible_kinds(page) == ['studies']*2
        assert page.locator('.lon01-card.is-open-consultation').get_attribute('hidden') is not None
        choose(page,'all')
        assert len(visible_kinds(page)) == 8
        if width <= 700: page.locator('[data-lon01-close]').click()
        assert timeline_reads() == reads
        assert page.evaluate('scrollY') == page_y
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        assert page.locator('.lon01-card.is-closed').first.bounding_box()['height'] == baseline_height
        assert page.locator('.lon01-event-row').first.bounding_box()['height'] == row_height
        assert all(x['method']=='GET' for x in page.evaluate('qaCalls'))
        if width > 700:
            assert page.locator('.vis05-timeline').evaluate('(e)=>getComputedStyle(e).scrollbarGutter') == 'stable'
            assert page.evaluate('document.documentElement.scrollHeight <= innerHeight')
        print(f'H09 {width}x{height}: PASS')
        page.close()

    page = browser.new_page(viewport={'width':1440,'height':810})
    enter(page)
    page.locator('.lon01-card.is-open-consultation').click()
    assert page.evaluate('resumeCalls') == [{'id':'P1','intent':'resume'}]
    page.locator('[data-lon01-filter="all"]').focus()
    page.keyboard.press('ArrowRight')
    assert page.locator('[data-lon01-filter="consultations"]').get_attribute('aria-pressed') == 'true'
    page.keyboard.press('Home')
    assert page.locator('[data-lon01-filter="all"]').get_attribute('aria-pressed') == 'true'
    page.locator('.lon01-event-row').last.click()
    page.wait_for_function("document.querySelector('[data-lon01-detail-title]')?.textContent === 'Documento 4'")
    choose(page,'prescriptions')
    assert page.locator('.lon01-event-row[aria-pressed="true"] .lon01-event-copy strong').inner_text() == 'RECETA'
    assert page.locator('[data-lon01-detail-title]').inner_text() == 'Documento 4'
    choose(page,'studies')
    page.wait_for_function("document.querySelector('[data-lon01-detail-title]')?.textContent === 'Documento 2'")
    assert page.locator('.lon01-event-row[aria-pressed="true"]').count() == 1
    page.close()

    page = browser.new_page(viewport={'width':1440,'height':810})
    enter(page, rows=[], docs=[], has_open=False)
    for key,label in [('consultations','Sin consultas registradas.'),
                      ('prescriptions','Sin recetas independientes registradas.'),
                      ('studies','Sin estudios independientes registrados.')]:
        choose(page,key)
        assert page.locator('[data-lon01-filter-empty]').inner_text() == label
        assert page.locator('[data-vis05-empty]').inner_text() == label
        assert page.locator('[data-lon01-detail]').is_hidden()
    page.close()

    long_rows = encounters + [{'encounter_key':f'enc:{number}',
        'encounter_dt':f'2026-08-{number-49:02d} 09:00:00','status':'closed'} for number in range(50,74)]
    long_docs = documents + [document(100+number,'prescription',f'2026-08-10 {number:02d}:00:00') for number in range(16)]
    page = browser.new_page(viewport={'width':1440,'height':810})
    enter(page, rows=long_rows, docs=long_docs)
    assert sum('longitudinal-history' in x['url'] for x in page.evaluate('qaCalls')) == 2
    assert sum('timeline_mode=1' in x['url'] for x in page.evaluate('qaCalls')) == 3
    assert page.locator('.vis05-timeline').evaluate('(e)=>e.scrollHeight > e.clientHeight')
    assert page.locator('.vis05-timeline').evaluate('(e)=>getComputedStyle(e).scrollbarGutter') == 'stable'
    page.locator('.vis05-timeline').evaluate('(e)=>e.scrollTop=e.scrollHeight')
    reads = len(page.evaluate('qaCalls'))
    choose(page,'prescriptions')
    assert visible_kinds(page).count('prescriptions') == 18
    assert page.locator('.vis05-timeline').evaluate('(e)=>e.scrollTop') == 0
    assert page.locator('.vis05-timeline').evaluate('(e)=>e.classList.contains("is-scrollable")')
    assert len(page.evaluate('qaCalls')) == reads + 1  # replacement detail read only
    assert page.evaluate('document.documentElement.scrollHeight <= innerHeight')
    assert page.evaluate('scrollY') == 0
    print('H09 empty, selection and complete paginated filter: PASS')
    page.close()
    browser.close()
