"""OR02C visual and read-only behavior QA using synthetic projection responses."""
import json
from pathlib import Path
from urllib.parse import parse_qs, urlsplit
from playwright.sync_api import sync_playwright, expect

root = Path(__file__).resolve().parents[3]
shots = Path('/tmp/or02c-qa-screenshots')
shots.mkdir(exist_ok=True)
calls = []
state = {'scenario': 'empty'}

def doc(id, title, kind, scope='PATIENT', origin=None):
    created = f'2026-09-{30-id%8:02d} 12:00:00'
    return {'id': id, 'document_uuid': f'00000000-0000-4000-8000-{id:012d}',
            'document_type': kind, 'title': title, 'summary': f'Resumen registrado de {title}',
            'status': 'generated', 'version': 1, 'source_scope': scope,
            'encounter_id': 'e1' if scope == 'ENCOUNTER' else None, 'encounter_ref_id': 1 if scope == 'ENCOUNTER' else None,
            'appointment_id': None, 'created_at': created, 'generated_at': created if kind.endswith('order') else None,
            'chronology_at': created, 'event_datetime': None, 'has_private_binary': 0,
            'requested_studies': ['Biometría hemática completa'] if id == 1 else [],
            'result_origin': origin, 'related_order_document_id': 1 if id in (11, 12, 13) else None,
            'versions': []}

order_a = doc(1, 'Biometría hemática', 'lab_order', 'ENCOUNTER')
order_b = doc(2, 'Ultrasonido abdominal', 'imaging_order')
order_c = doc(3, 'Estudio de control', 'order')
result_a = doc(11, 'Biometría hemática completa', 'lab_result')
result_b = doc(12, 'Reporte complementario', 'lab_result')
result_c = doc(13, 'Valores de referencia', 'result')
standalone = doc(14, 'Perfil tiroideo', 'result', origin='sin_orden')
groups = [
    {'kind': 'ORDER', 'order': order_a, 'result_count': 3, 'results': [result_a,result_b,result_c]},
    {'kind': 'ORDER', 'order': order_b, 'result_count': 0, 'results': []},
    {'kind': 'ORDER', 'order': order_c, 'result_count': 0, 'results': []},
    {'kind': 'STANDALONE_RESULT', 'result': standalone},
]
bulk = [{'kind': 'ORDER', 'order': doc(100+i, f'Orden de prueba {i}', 'order'), 'result_count': 0, 'results': []} for i in range(30)]

def data_for(query):
    scenario=state['scenario'];filter_value=query.get('filter',['all'])[0];search=query.get('search',[''])[0];cursor=query.get('cursor')
    if scenario=='empty':items=[]
    elif scenario=='orders_only':items=[{'kind':'ORDER','order': row['order'],'result_count':0,'results':[]} for row in groups[:3]]
    elif scenario=='one_result':items=[{'kind':'ORDER','order': order_a,'result_count':1,'results':[result_a]}]
    elif scenario=='bulk':items=bulk
    else:items=groups
    if filter_value=='orders':items=[item for item in items if item['kind']=='ORDER']
    if filter_value=='results':
        results=[]
        for item in items:
            if item['kind']=='ORDER':results += [{'kind':'RESULT','result':r} for r in item['results']]
            elif item['kind']=='STANDALONE_RESULT':results.append({'kind':'RESULT','result':item['result']})
        items=results
    if search:items=[item for item in items if search.lower() in (item.get('order') or item.get('result'))['title'].lower()]
    if scenario=='bulk' and filter_value!='results' and not search:
        items=items[25:] if cursor else items[:25]
        more=not bool(cursor)
    else:more=False
    return {'items':items,'has_more':more,'cursor_next':'next-page' if more else None,
            'order_types':['order','orders','lab_order','imaging_order','orden_estudio'],
            'result_types':['lab_result','lab_pdf','imaging_result','external_result','external_report','result']}

def box(page, selector):
    return page.locator(selector).bounding_box()

def geometry(page):
    left=box(page,'#t-estudios .vis06-orders-index');right=box(page,'#t-estudios .vis06-detail')
    return tuple(round(value,2) for value in (left['x'],left['width'],right['x'],right['width']))

with sync_playwright() as playwright:
    browser=playwright.chromium.launch()
    page=browser.new_page(viewport={'width':1440,'height':900})
    errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
    def handler(route):
        url=route.request.url;calls.append(url);query=parse_qs(urlsplit(url).query)
        if '/encounters/active' in url:data={'doctor_id':'d_or02c'}
        elif 'orders_results_mode=1' in url:data=data_for(query)
        elif '/documents/' in url:
            token=urlsplit(url).path.rsplit('/',1)[-1]
            all_docs=[order_a,order_b,order_c,result_a,result_b,result_c,standalone]+[item['order'] for item in bulk]
            row=next((item for item in all_docs if item['document_uuid']==token or str(item['id'])==token),None)
            data={'title':row['title'] if row else '', 'content':{'payload':{'indication':'Control de rutina' if row and row['id']==1 else '',
                  'text':'Contenido registrado' if row and row['id'] in (11,12,13,14) else ''},'summary':row['summary'] if row else ''}}
        else:data={'items':[]}
        route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':data}))
    page.route('**/api/clinical/index.php/**',handler)
    page.route('http://mxmed.test/',lambda route:route.fulfill(status=200,content_type='text/html',body='<html><body></body></html>'))
    page.goto('http://mxmed.test/')
    page.set_content('''<div style="height:230px"></div><div id="p-expediente" data-patient-id="p_or02c">
      <div id="t-estudios" class="active"></div><div id="t-consent" style="display:none"></div><div id="t-tratamiento" style="display:none"></div></div>
      <div class="mm-footer" style="height:30px"></div>''')
    page.add_style_tag(path=str(root/'assets/css/expediente-paciente-visual-normalization.css'))
    page.add_style_tag(content='body{margin:0}')
    page.add_script_tag(path=str(root/'assets/js/clinical/vis06-modules.js'))
    expect(page.locator('#t-estudios .vis06-orders-empty')).to_be_visible()
    expect(page.locator('#t-estudios .vis06-detail-placeholder')).to_be_visible()
    assert page.locator('#t-estudios .vis06-index-card').count()==0
    assert page.locator('#t-estudios .vis06-create').count()==1
    assert page.locator('#t-estudios .vis06-segments button[aria-pressed="true"]').inner_text()=='TODOS'
    page.screenshot(path=str(shots/'empty-1440x900.png'))
    print('QA_EMPTY=PASS')

    state['scenario']='orders_only';page.locator('#t-estudios .vis06-refresh').click()
    expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(3)
    expect(page.locator('#t-estudios .vis06-count',has_text='Sin resultados')).to_have_count(4)
    expect(page.locator('#t-estudios .vis06-no-results')).to_contain_text('Aún no se han recibido resultados')
    expect(page.locator('#t-estudios .vis06-order-print')).to_have_count(2)
    page.evaluate('window.__portableOpened="";window.__portableDownloaded="";window.open=(url)=>{window.__portableOpened=url;};HTMLAnchorElement.prototype.click=function(){window.__portableDownloaded=this.href;}')
    page.locator('#t-estudios .vis06-order-print',has_text='Imprimir').click()
    assert '/modules/clinical/ui/portable-order.php?' in page.evaluate('window.__portableOpened')
    assert 'uuid=' in page.evaluate('window.__portableOpened')
    page.locator('#t-estudios .vis06-order-print',has_text='Descargar PDF').click()
    assert '/modules/clinical/ui/portable-order-pdf.php?' in page.evaluate('window.__portableDownloaded')
    assert 'uuid=' in page.evaluate('window.__portableDownloaded')
    page.screenshot(path=str(shots/'orders-only-1440x900.png'))
    print('QA_ORDERS_ONLY=PASS; QA_GENERAL_PRINT_AND_PDF_ENTRIES=PASS')

    state['scenario']='one_result';page.locator('#t-estudios .vis06-refresh').click()
    expect(page.locator('#t-estudios .vis06-result-entry')).to_have_count(1)
    print('QA_ONE_RESULT=PASS')

    state['scenario']='mixed';page.locator('#t-estudios .vis06-refresh').click()
    expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(4)
    expect(page.locator('#t-estudios .vis06-result-entry')).to_have_count(3)
    assert '3 resultados' in page.locator('#t-estudios .vis06-projected-head').inner_text()
    before=geometry(page);scroll_before=page.evaluate('window.scrollY')
    page.locator('#t-estudios .vis06-result-entry',has_text='Reporte complementario').click()
    expect(page.locator('#t-estudios .vis06-projected-intro h4')).to_have_text('Reporte complementario')
    page.locator('#t-estudios .vis06-index-card',has_text='Ultrasonido abdominal').click()
    expect(page.locator('#t-estudios .vis06-no-results')).to_be_visible()
    page.locator('#t-estudios .vis06-index-card',has_text='Biometría hemática').first.click()
    expect(page.locator('#t-estudios .vis06-result-entry')).to_have_count(3)
    assert geometry(page)==before and page.evaluate('window.scrollY')==scroll_before
    page.screenshot(path=str(shots/'mixed-1440x900.png'))
    print('QA_MULTIPLE_RESULTS=PASS; QA_MULTI_ORDER=PASS; QA_CROSS_SELECTION=PASS')

    page.locator('#t-estudios .vis06-index-card',has_text='Perfil tiroideo').click()
    expect(page.locator('#t-estudios .vis06-projected-section',has_text='Origen')).to_contain_text('Sin orden previa')
    assert 'RESULTADO SIN ORDEN PREVIA' in page.locator('#t-estudios .vis06-index-card',has_text='Perfil tiroideo').inner_text()
    print('QA_STANDALONE_RESULT=PASS')

    page.locator('#t-estudios .vis06-segments button[data-filter="results"]').click()
    expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(4)
    assert all('Registrado' in text for text in page.locator('#t-estudios .vis06-index-card').all_text_contents())
    assert 'filter=results' in calls[-2] or any('filter=results' in call for call in calls)
    page.locator('#t-estudios .vis06-index-card',has_text='Biometría hemática completa').click()
    expect(page.locator('#t-estudios .vis06-projected-section',has_text='Origen')).to_contain_text('Biometría hemática')
    page.screenshot(path=str(shots/'results-1440x900.png'))
    print('QA_RESULTS_FILTER=PASS')

    page.locator('#t-estudios .vis06-search-wrap input').fill('Perfil')
    expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(1)
    assert any('search=Perfil' in call for call in calls)
    print('QA_SEARCH=PASS')
    page.locator('#t-estudios .vis06-search-wrap input').fill('')
    page.locator('#t-estudios .vis06-segments button[data-filter="all"]').click()

    state['scenario']='bulk';page.locator('#t-estudios .vis06-refresh').click()
    expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(25)
    assert page.locator('#t-estudios .vis06-list').evaluate('(element)=>element.scrollHeight>element.clientHeight')
    page.locator('#t-estudios .vis06-more').click()
    expect(page.locator('#t-estudios .vis06-index-card')).to_have_count(30)
    ids=page.locator('#t-estudios .vis06-index-card').evaluate_all('(items)=>items.map(item=>item.dataset.orListId)')
    assert len(ids)==len(set(ids))==30
    print('QA_PAGINATION=PASS; QA_LEFT_INTERNAL_SCROLL=PASS')

    state['scenario']='mixed';page.locator('#t-estudios .vis06-refresh').click()
    for width,height in [(1440,810),(1440,900),(1366,768)]:
        page.set_viewport_size({'width':width,'height':height})
        page.wait_for_timeout(100)
        left=box(page,'#t-estudios .vis06-orders-index');right=box(page,'#t-estudios .vis06-detail')
        assert left['x']+left['width']<right['x'] and right['width']>left['width'],(width,height,left,right)
        assert page.evaluate('document.documentElement.scrollWidth<=window.innerWidth'),(width,height,'horizontal overflow',page.evaluate('''()=>({width:document.documentElement.scrollWidth,offenders:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).slice(0,10).map(e=>[e.tagName,e.className,e.getBoundingClientRect().right])})'''))
        assert page.evaluate('document.documentElement.scrollHeight<=window.innerHeight+2'),(width,height,'page scroll',page.evaluate('''()=>({height:document.documentElement.scrollHeight,workspace:document.querySelector('.vis06-orders-workspace').getBoundingClientRect().toJSON(),footer:document.querySelector('.mm-footer').getBoundingClientRect().toJSON()})'''))
        page.screenshot(path=str(shots/f'mixed-{width}x{height}.png'))
        print(f'QA_{width}x{height}=PASS')

    page.set_viewport_size({'width':390,'height':844})
    page.locator('#t-estudios .vis06-index-card').first.click()
    expect(page.locator('#t-estudios .vis06-orders-index')).to_be_hidden()
    expect(page.locator('#t-estudios .vis06-detail')).to_be_visible()
    expect(page.locator('#t-estudios .vis06-order-print',has_text='Descargar PDF')).to_be_visible()
    page.locator('#t-estudios .vis06-mobile-back').click()
    expect(page.locator('#t-estudios .vis06-orders-index')).to_be_visible()
    assert page.evaluate('document.documentElement.scrollWidth<=window.innerWidth'),page.evaluate('''()=>({width:document.documentElement.scrollWidth,offenders:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1||e.scrollWidth>e.clientWidth+1).slice(0,30).map(e=>[e.tagName,e.className,e.getBoundingClientRect().right,e.clientWidth,e.scrollWidth])})''')
    print('QA_MOBILE=PASS')
    assert not errors,errors
    print('QA_JS_ERRORS=NONE')
    browser.close()
