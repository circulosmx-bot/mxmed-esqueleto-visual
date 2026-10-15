#!/usr/bin/env python3
import asyncio
from pathlib import Path
from playwright.async_api import async_playwright

ROOT=Path(__file__).resolve().parents[3]
def check(ok,label):
    if not ok: raise RuntimeError('FAIL '+label)
    print(f'CONS_VIS01_{label}=PASS',flush=True)

def markup():
    items=''.join(f'''<li class="nav-item" data-consultorio-tab-item><button class="nav-link{' active' if i==2 else ''}" data-bs-target="#sede{i}"><span class="tab-ico">🏢</span><span class="tab-lbl">CONSULTORIO {i}</span></button><span class="cons-tab-drag-handle" role="button" tabindex="0">↔</span></li>''' for i in (1,2,3))
    options='<option value="">Sin especificar</option><option value="1">Sí</option><option value="0">No</option>'
    return f'''<!doctype html><html><body><section id="p-consultorio" class="mm-card"><div class="body"><ul class="nav mm-tabs-embed">{items}<li class="nav-item"><button id="btn-consul-add">Agregar</button></li></ul><div data-cons-order-status><div><strong></strong><span id="cons-order-help"></span></div><button data-cons-save-order>Guardar orden actual</button></div><div class="tab-content"><div id="sede1"></div><div id="sede2"></div><div id="sede3"></div></div><div data-map-reference-wrap-for="cons-map-frame"><label>Referencia de ubicación</label><input data-map-reference-input-for="cons-map-frame"></div><div class="ratio"><iframe id="cons-map-frame"></iframe></div><div data-cons-location-amenities><label>Estacionamiento<select id="cons-estac">{options}</select></label><label>Accesibilidad<select id="cons-acces">{options}</select></label></div></div></section></body></html>'''

async def setup(page,configured=True):
    await page.set_content(markup())
    await page.add_style_tag(path=str(ROOT/'assets/css/consultorio-visual-normalization.css'))
    await page.evaluate("""()=>{window.__requests=[];window.fetch=async(_url,options)=>{const body=JSON.parse(options.body);window.__requests.push(body);return new Response(JSON.stringify({ok:true,data:{presentation_order_configured:true,presentation_order:body.consultorio_ids,presentation_order_fingerprint:'b'.repeat(64)}}),{status:200,headers:{'Content-Type':'application/json'}})}}""")
    await page.add_script_tag(path=str(ROOT/'assets/js/perfil/consultorio/consultorio-ordering.js'))
    await page.evaluate("""configured=>document.getElementById('p-consultorio').dispatchEvent(new CustomEvent('mx:consultorios-hydrated',{detail:{doctor_id:'doctor-a',presentation_order_configured:configured,presentation_order:['1','2','3'],presentation_order_fingerprint:'a'.repeat(64)}}))""",configured)

async def main():
  async with async_playwright() as pw:
    browser=await pw.webkit.launch()
    for width,height in ((1440,900),(1366,768),(820,1180)):
      page=await browser.new_page(viewport={'width':width,'height':height});await setup(page)
      state=await page.evaluate("""()=>({overflow:document.documentElement.scrollWidth-innerWidth,handles:document.querySelectorAll('.cons-tab-drag-handle').length,primary:document.querySelector('[data-consultorio-tab-item]:first-child .cons-tab-primary-badge')?.textContent||'',active:document.querySelector('.nav-link.active')?.getAttribute('data-bs-target'),amenitiesAfterMap:!!document.querySelector('.ratio + [data-cons-location-amenities]'),triState:Array.from(document.querySelectorAll('[data-cons-location-amenities] select')).every(x=>Array.from(x.options).map(o=>o.value).join(',')===',1,0')})""")
      check(state['overflow']<=1,f'{width}x{height}_NO_PAGE_OVERFLOW');check(state['handles']==3,f'{width}x{height}_DRAG_AFFORDANCES');check(state['primary']=='Principal',f'{width}x{height}_PRIMARY_BADGE');check(state['amenitiesAfterMap'],f'{width}x{height}_AMENITIES_NEAR_MAP')
      check(state['triState'],f'{width}x{height}_AMENITIES_TRISTATE')
      await page.screenshot(path=f'/tmp/cons-vis01-{width}x{height}.png',full_page=True)
      if width==1440:
        target=page.locator('[data-bs-target="#sede2"]');box=await target.bounding_box()
        await page.locator('[data-bs-target="#sede1"] + .cons-tab-drag-handle').drag_to(target,target_position={'x':box['width']-2,'y':box['height']/2})
        await page.wait_for_function("window.__requests.length===1")
        order=await page.evaluate("mxmedConsultorioOrdering.currentOrder()")
        check(order==['2','1','3'],'DESKTOP_DRAG_REORDER');check((await page.locator('.nav-link.active').get_attribute('data-bs-target'))=='#sede2','DRAG_PRESERVES_SELECTION')
        await page.locator('[data-bs-target="#sede3"] + .cons-tab-drag-handle').press('ArrowLeft')
        await page.wait_for_function("window.__requests.length===2")
        check((await page.evaluate("mxmedConsultorioOrdering.currentOrder()"))==['2','3','1'],'KEYBOARD_REORDER')
      await page.close()

    touch=await browser.new_page(viewport={'width':820,'height':1180},has_touch=True);await setup(touch)
    await touch.evaluate("""()=>{const h=document.querySelector('[data-bs-target="#sede3"] + .cons-tab-drag-handle');const t=document.querySelector('[data-bs-target="#sede1"]').closest('.nav-item');const r=t.getBoundingClientRect();h.dispatchEvent(new PointerEvent('pointerdown',{bubbles:true,pointerId:7,pointerType:'touch',clientX:h.getBoundingClientRect().x+5,clientY:h.getBoundingClientRect().y+5}));h.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,cancelable:true,pointerId:7,pointerType:'touch',clientX:r.left+2,clientY:r.top+r.height/2}));h.dispatchEvent(new PointerEvent('pointerup',{bubbles:true,pointerId:7,pointerType:'touch',clientX:r.left+2,clientY:r.top+r.height/2}));}""")
    await touch.wait_for_function("window.__requests.length===1")
    check((await touch.evaluate("mxmedConsultorioOrdering.currentOrder()"))==['3','1','2'],'TOUCH_POINTER_REORDER');await touch.close()

    unconfigured=await browser.new_page(viewport={'width':1366,'height':768});await setup(unconfigured,False)
    check(await unconfigured.locator('[data-cons-save-order]').is_visible(),'UNCONFIGURED_EXPLICIT_ACTION')
    await unconfigured.locator('[data-cons-save-order]').click();await unconfigured.wait_for_function("window.__requests.length===1")
    check((await unconfigured.evaluate("mxmedConsultorioOrdering.currentOrder()"))==['1','2','3'],'SAVE_CURRENT_ORDER');await unconfigured.close()
    await browser.close()
  print('CONS_VIS01_BROWSER_WEBKIT=PASS')
asyncio.run(main())
