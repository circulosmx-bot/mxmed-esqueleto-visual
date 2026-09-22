"""Authenticated browser review of real LON07C/LON02 markup, JS and API."""
import json
import os
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

base=os.environ['LON07C_QA_BASE'];root=Path(os.environ['LON07C_QA_ROOT'])
source=(root/'index.html').read_text()
start=source.index('<div class="tab-pane fade" id="t-resumen-longitudinal">')
end=source.index('<div class="tab-pane fade" id="t-antecedentes-longitudinal">',start)
panes=source[start:end]
artifacts=Path.home()/'.codex'/'artifacts'/'lon07c';artifacts.mkdir(parents=True,exist_ok=True)
fixture=f'''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="{base}/assets/css/style.css">
<link rel="stylesheet" href="{base}/assets/css/expediente-paciente-visual-normalization.css">
<style>body{{margin:0;background:#eef8fa;color:#173b4d;font:16px Arial,sans-serif}}#p-expediente{{max-width:1400px;margin:auto;padding:18px}}.d-none{{display:none!important}}.tab-pane{{display:none}}.tab-pane.active{{display:block}}.qa-tabs{{display:flex;gap:8px;flex-wrap:wrap}}.btn{{border:1px solid #06aeb8;border-radius:9px;background:#fff;color:#06536e;padding:8px 13px;cursor:pointer}}.btn-primary{{background:#06aeb8;color:#fff}}button{{font:inherit}}</style></head><body>
<div id="p-expediente" data-patient-id="p_a"><nav class="qa-tabs"><button type="button" class="btn" data-bs-target="#t-resumen-longitudinal">Resumen</button><button type="button" class="btn" data-bs-target="#t-mediciones-longitudinal">Mediciones y tendencias</button></nav>{panes}</div>
<script>window.bootstrap={{Tab:{{getOrCreateInstance(button){{return {{show(){{button.click()}}}}}}}}}};document.querySelectorAll('.qa-tabs button').forEach(button=>button.addEventListener('click',()=>{{document.querySelectorAll('.tab-pane').forEach(pane=>pane.classList.remove('active'));document.querySelector(button.dataset.bsTarget).classList.add('active');button.dispatchEvent(new Event('shown.bs.tab'))}}));</script>
<script src="{base}/assets/js/clinical/lon02-summary.js"></script><script src="{base}/assets/js/clinical/lon07c-measurements.js"></script></body></html>'''
def check(value,name):
    assert value,name
    print('PASS',name,flush=True)
def screenshot(page,name):
    path=artifacts/name;page.screenshot(path=str(path),full_page=True);print('SCREENSHOT',path,flush=True)
with sync_playwright() as p:
    browser=p.chromium.launch(channel='chrome',headless=True)
    errors=[];starts=[]
    for width,height in [(1440,900),(1366,768),(820,1180),(390,844)]:
        context=browser.new_context(viewport={'width':width,'height':height});context.add_cookies([{'name':'PHPSESSID','value':'lon07b-qa','url':base}]);page=context.new_page()
        page.on('pageerror',lambda error:errors.append(str(error)))
        page.on('console',lambda message:errors.append(message.text) if message.type=='error' else None)
        page.on('request',lambda request:starts.append(request.url) if request.method=='POST' and '/encounters' in request.url else None)
        for resource in ('antecedents','allergies','problems','medications','tasks'):
            page.route('**/longitudinal/'+resource,lambda route:route.fulfill(status=200,content_type='application/json',body=json.dumps({'ok':True,'data':{'items':[]}})))
        page.goto(base+'/modules/clinical/README.md');page.set_content(fixture,wait_until='load')
        page.locator('[data-bs-target="#t-resumen-longitudinal"]').click()
        expect(page.locator('[data-lon02-measurements]')).to_contain_text('Frecuencia cardiaca')
        expect(page.locator('[data-lon02-measurements]')).to_contain_text('81.9')
        check('999' not in page.locator('[data-lon02-measurements]').inner_text(),'LON02 excludes fallback from latest comparable')
        screenshot(page,f'resumen-{width}x{height}.png')
        page.locator('[data-lon02-trends]').click()
        expect(page.locator('[data-lon07c-status]')).to_contain_text('Lecturas comparables')
        options=page.locator('[data-lon07c-series] option').all_text_contents()
        check(any('Presión arterial' in option for option in options) and any('Peso' in option for option in options),'series selector uses canonical available series')
        check(any('Peso' in option and 'lb' in option and 'Importación' in option for option in options) and any('Peso' in option and 'kg' in option and 'Referido' in option for option in options),'different units and sources stay distinct in selector')
        page.locator('[data-lon07c-series]').select_option(label=next(option for option in options if 'Peso' in option and 'lb' in option))
        expect(page.locator('[data-lon07c-points-body]')).to_contain_text('154 lb')
        check(page.locator('[data-lon07c-chart] circle').count()==1,'alternate unit has its own chart without kg overlay')
        page.locator('[data-lon07c-series]').select_option(label=next(option for option in options if 'Peso' in option and 'Referido' in option))
        expect(page.locator('[data-lon07c-points-body]')).to_contain_text('72 kg')
        check(page.locator('[data-lon07c-chart] circle').count()==1,'patient-reported source has its own chart')
        page.locator('[data-lon07c-series]').select_option(label=next(option for option in options if 'Presión arterial' in option))
        expect(page.locator('[data-lon07c-points-body]')).to_contain_text('Sistólica')
        expect(page.locator('[data-lon07c-points-body]')).to_contain_text('Diastólica')
        check(page.locator('[data-lon07c-chart] circle').count()==4,'blood pressure has four actual component points')
        screenshot(page,f'tendencias-presion-{width}x{height}.png')
        weight=next(option for option in options if option.startswith('Peso') and 'kg' in option and 'Medición directa' in option)
        page.locator('[data-lon07c-series]').select_option(label=weight)
        page_size=20 if width==390 else 50
        expect(page.locator('[data-lon07c-points-body] tr')).to_have_count(page_size)
        check(page.locator('[data-lon07c-chart] circle').count()==page_size,'single-value chart matches first canonical page')
        check('Hora de medición no confirmada' in page.locator('[data-lon07c-history-body]').inner_text(),'fallback visible only in history')
        check('Hora de medición desconocida' in page.locator('[data-lon07c-history-body]').inner_text(),'unknown legacy visible only in history')
        check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'),'no horizontal page overflow')
        if width==390:check(page.locator('[data-lon07c-history-body] tr').first.evaluate("el=>getComputedStyle(el).display==='block'"),'mobile history uses readable cards')
        screenshot(page,f'tendencias-peso-{width}x{height}.png')
        if width==1440:
            page.locator('[data-lon07c-more-points]').click();expect(page.locator('[data-lon07c-points-body] tr')).to_have_count(100)
            page.locator('[data-lon07c-more-points]').click();expect(page.locator('[data-lon07c-points-body] tr')).to_have_count(120)
            check(page.locator('[data-lon07c-chart] circle').count()==120,'same-day repeated readings retained after pagination')
            page.locator('[data-lon07c-more-history]').click();expect(page.locator('[data-lon07c-history-body] tr')).to_have_count(100)
            page.locator('[data-lon07c-more-history]').click();expect(page.locator('[data-lon07c-history-body] tr')).to_have_count(129)
            expect(page.locator('[data-lon07c-history-body]')).to_contain_text('Dolor')
            check('Dolor' not in '\n'.join(options),'pain not offered as chart series')
            screenshot(page,'tendencias-historial-1440x900.png')
            page.locator('[data-lon07c-series]').focus();check(page.evaluate('document.activeElement.matches("[data-lon07c-series]")'),'series selector keyboard focusable')
            page.locator('[data-lon07c-from]').focus();check(page.evaluate('document.activeElement.matches("[data-lon07c-from]")'),'date control keyboard focusable')
            page.locator('[data-lon07c-from]').fill('2025-09-01');page.locator('[data-lon07c-to]').fill('2025-09-02');page.locator('[data-lon07c-apply]').click()
            expect(page.locator('[data-lon07c-status]')).to_contain_text('Sin mediciones comparables')
            check(page.locator('[data-lon07c-chart] circle').count()==0,'date range delegates filtering to canonical read model')
            page.locator('#p-expediente').evaluate("el=>el.dataset.patientId='p_empty'")
            expect(page.locator('[data-lon07c-status]')).to_contain_text('Sin mediciones comparables')
            screenshot(page,'tendencias-sin-datos-1440x900.png')
            page.locator('#p-expediente').evaluate("el=>el.dataset.patientId='p_fallback'")
            page.locator('[data-bs-target="#t-resumen-longitudinal"]').click()
            expect(page.locator('[data-lon02-measurements]')).to_contain_text('Sin mediciones comparables en los últimos 12 meses')
            page.locator('[data-lon02-trends]').click()
            expect(page.locator('[data-lon07c-history-body]')).to_contain_text('91 kg')
            check(page.locator('[data-lon07c-chart] circle').count()==0,'fallback-only patient remains historical without chart')
            screenshot(page,'tendencias-solo-historial-1440x900.png')
        context.close()
    check(not starts,'no encounter START request from summary or trends')
    check(not errors,'no browser console or page errors: '+repr(errors))
    browser.close()
print('LON07C_DISPOSABLE_BROWSER_GATE=PASS')
