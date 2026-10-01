import json
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright, expect

root=Path(__file__).resolve().parents[3]
pdf=Path('/tmp/res02a-qa.pdf')
pdf.write_bytes(b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF')
ids=[f'00000000-0000-4000-8000-{n:012d}' for n in (1,2,3)]
order_id='00000000-0000-4000-8000-000000000100'
state={'items':[],'kind':'orders','encounter':'','writes':[],'source':'general'}

def handler(route):
    request=route.request; path=urlsplit(request.url).path
    if request.method=='GET' and '/documents/' in path:
        payload={'order_payload_version':2,'order_items':state['items'],'requested_studies':[item['study_display_name'] for item in state['items']]}
        if not state['items']: payload={'requested_studies':['Estudio legado']}
        data={'document':{'document_uuid':order_id,'document_type':state['kind'],'title':'Orden QA','status':'generated',
            'context':{'patient_id':'p_qa','encounter_id':state['encounter'] or None,'appointment_id':None},
            'content':{'payload':payload},'timestamps':{'generated_at':'2026-09-30 12:00:00'}}}
    elif request.method=='POST' and path.endswith('/documents'):
        body=request.post_data
        state['writes'].append((path,body,request.headers.get('idempotency-key')))
        data={'document_id':500}
    else:data={'items':[]}
    route.fulfill(status=201 if request.method=='POST' else 200,content_type='application/json',body=json.dumps({'ok':True,'data':data}))

def open_composer(page,general=True):
    page.evaluate('''(general) => window.mxmedLinkedResultComposer.open({patientId:'p_qa',doctorId:'d_qa',
      orderRef:general?'00000000-0000-4000-8000-000000000100':'',encounterId:general?'':'77',
      orders:general?[]:[{document_uuid:'00000000-0000-4000-8000-000000000100',title:'Orden QA'}],
      trigger:document.querySelector('#trigger'),isCurrent:()=>true})''',general)
    if not general:page.locator('.res02a-dialog [data-order-picker] select').select_option(order_id)
    expect(page.locator('.res02a-dialog [data-context] strong')).to_have_text('Orden QA')

with sync_playwright() as playwright:
    browser=playwright.chromium.launch()
    page=browser.new_page(viewport={'width':1440,'height':810})
    errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
    page.route('http://127.0.0.1:38999/',lambda route:route.fulfill(status=200,content_type='text/html',body='<html></html>'))
    page.route('**/api/clinical/index.php/**',handler)
    page.goto('http://127.0.0.1:38999/')
    page.set_content('<button id="trigger">Abrir</button>')
    page.add_style_tag(path=str(root/'assets/css/expediente-paciente-visual-normalization.css'))
    page.add_script_tag(path=str(root/'assets/js/clinical/res02a-linked-result-composer.js'))
    def items(count):
        return [{'order_item_id':ids[i],'study_display_name':['Glucosa','RX tórax','HbA1c'][i],
                 'study_category':['LABORATORIO','IMAGEN','LABORATORIO'][i]} for i in range(count)]
    def save():
        page.locator('.res02a-dialog [data-provenance]').fill('Laboratorio QA')
        page.locator('.res02a-dialog [data-file]').set_input_files(str(pdf))
        page.locator('.res02a-dialog [data-save]').click()
        expect(page.locator('.res02a-dialog')).not_to_be_visible()
        path,body,key=state['writes'][-1]
        assert key and 'name="file"' in body
        return path,body
    state['items']=items(1);state['kind']='lab_order';open_composer(page)
    assert page.locator('input[data-item]:checked').count()==1
    assert page.locator('.res02a-dialog [data-title]').input_value()=='Glucosa'
    path,body=save();assert ids[0] in body and 'lab_result' in body and '/patients/p_qa/documents' in path
    print('QA_ONE_ITEM=PASS')
    state['items']=items(3);state['kind']='orders';open_composer(page)
    assert page.locator('input[data-item]:checked').count()==0
    page.locator('input[data-item]').nth(0).focus();page.keyboard.press('Space')
    assert page.locator('input[data-item]').nth(0).is_checked()
    path,body=save();assert ids[0] in body and ids[1] not in body
    print('QA_ONE_OF_THREE=PASS')
    open_composer(page)
    for element in page.locator('input[data-item]').all():element.check()
    path,body=save();assert all(value in body for value in ids) and 'result' in body
    print('QA_ALL_THREE=PASS')
    open_composer(page);page.locator('input[data-item]').nth(0).check();page.locator('input[data-item]').nth(2).check()
    path,body=save();assert ids[0] in body and ids[2] in body and ids[1] not in body
    print('QA_ONE_AND_THREE=PASS')
    open_composer(page);page.locator('input[data-item]').nth(0).check();page.locator('input[data-general]').check();assert page.locator('input[data-item]:checked').count()==0
    path,body=save();assert 'related_order_item_ids' not in body and 'related_order_document_uuid' in body
    print('QA_AMBIGUOUS=PASS')
    state['items']=[];open_composer(page);assert page.locator('input[data-general]').is_checked()
    assert page.locator('.res02a-legacy li').inner_text()=='Estudio legado'
    path,body=save();assert 'related_order_item_ids' not in body
    print('QA_V1_ORDER=PASS')
    state['items']=items(3);state['encounter']='77';open_composer(page,False)
    page.locator('input[data-item]').nth(0).check();path,body=save();assert '/encounters/enc%3A77/documents' in path
    print('QA_CONSULTATION=PASS')
    for width,height in ((1440,810),(1440,900),(1366,768),(390,844)):
        page.set_viewport_size({'width':width,'height':height});open_composer(page,False)
        box=page.locator('.res02a-dialog').bounding_box()
        assert box['width']<=width and box['height']<=height,(width,height,box)
        assert page.locator('.res02a-dialog').evaluate('(e)=>e.scrollWidth<=e.clientWidth'),(width,height)
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(width,height)
        page.locator('.res02a-dialog [data-close]').first.click()
        print(f'QA_{width}x{height}=PASS')
    assert not errors,errors
    browser.close()
