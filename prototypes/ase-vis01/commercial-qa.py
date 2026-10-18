#!/usr/bin/env python3
"""ASE-COM01 synthetic static browser/contract checks; never touches product services."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'prototypes/ase-vis01/evidence/com01/browser'
BASE='http://127.0.0.1:8140/'
ROUTES={'/','/style.css','/app.js','/catalog.js','/commercial.js','/commercial.css','/assets/css/media-review-inbox.css'}
WARNING="Refused to apply a stylesheet because its hash, its nonce, or 'unsafe-inline' does not appear in the style-src directive of the Content Security Policy."

def run(browser,engine,width,height,before):
    context=browser.new_context(viewport={'width':width,'height':height},locale='es-MX',reduced_motion='reduce')
    page=context.new_page();checks=[];errors=[];requests=[];forbidden=[];shots=[];diagnostics=[];capture=False;warnings=[]
    def check(name,ok,detail=None):
        checks.append({'name':name,'pass':bool(ok),**({'detail':detail} if detail is not None else {})})
        if not ok:raise AssertionError(name+': '+str(detail))
    def route(r):
        p=urlsplit(r.request.url);entry={'url':r.request.url,'method':r.request.method};requests.append(entry)
        if (p.scheme,p.netloc)!=('http','127.0.0.1:8140') or p.path not in ROUTES or r.request.method!='GET':forbidden.append(entry);r.abort()
        else:r.continue_()
    context.route('**/*',route)
    context.add_init_script("""(()=>{window.__writes=[];window.__styles=[];
      for(const method of ['setItem','removeItem','clear'])Storage.prototype[method]=function(){window.__writes.push(method);throw Error('Unexpected storage write');};
      if(window.indexedDB)indexedDB.open=()=>{window.__writes.push('indexedDB');throw Error('Unexpected indexedDB');};
      if(navigator.serviceWorker)navigator.serviceWorker.register=()=>{window.__writes.push('serviceWorker');throw Error('Unexpected service worker');};
      new MutationObserver(rs=>rs.forEach(r=>r.addedNodes.forEach(n=>{if(n.nodeName==='STYLE')window.__styles.push(n.textContent);}))).observe(document,{childList:true,subtree:true});})();""")
    page.on('pageerror',lambda e:errors.append(str(e)))
    def console(m):
        if m.type=='error':
            if capture and engine=='webkit' and m.text==WARNING:warnings.append(m.text)
            else:errors.append(m.text)
    page.on('console',console)
    def shot(name,modal=False):
        nonlocal capture
        start=page.evaluate('window.__styles.length');n=len(warnings);capture=True
        path=OUT/f'{engine}-{width}x{height}-{name}.png'
        try:page.screenshot(path=str(path),full_page=not modal)
        finally:capture=False
        styles=page.evaluate('(n)=>window.__styles.slice(n)',start);new=warnings[n:]
        if new:
            check('Exact known WebKit screenshot injection only',styles==['body {}'] and new==[WARNING],{'styles':styles,'warnings':new})
            diagnostics.append({'screenshot':name,'style':'body {}','csp_rejected':True,'warning':WARNING})
        shots.append(str(path.relative_to(ROOT)))
    def layout(name):
        metrics=page.evaluate("""()=>({overflow:Math.max(document.body.scrollWidth,document.documentElement.scrollWidth)>innerWidth,small:[...document.querySelectorAll('#commercial-proposal button,#commercial-proposal label,#commercial-conditions summary,#preview-own-activity,#commercial-activity button')].filter(e=>e.getClientRects().length && !e.closest('dialog:not([open])') && e.getBoundingClientRect().height<43.9).map(e=>({id:e.id,height:e.getBoundingClientRect().height})),modalOverflow:(()=>{const d=document.querySelector('#commercial-activity');return d.open&&d.scrollWidth>d.clientWidth;})()})""")
        check(name+' fits viewport/44px targets',not metrics['overflow'] and not metrics['small'] and not metrics['modalOverflow'],metrics)
    def member(id):
        if width<640:page.locator('#show-directory').click()
        page.locator(f'[data-member="{id}"]').click()
    def own_view():
        page.locator('#commercial-conditions').evaluate('e=>e.open=true');page.locator('#preview-own-activity').click()
    result='FAIL';error=None
    try:
        response=page.goto(BASE,wait_until='networkidle')
        check('Static CSP prevents financial/API connections',response.status==200 and "connect-src 'none'" in response.headers['content-security-policy'])
        if width<640:page.locator('#show-detail').click()
        check('R2 catalog unchanged, no commercial executable key',page.evaluate('window.ASE_VIS01_CATALOG')==before['catalog'])
        for item in before['layout']:
            actual=page.locator(item['selector']).evaluate('e=>{const r=e.getBoundingClientRect();return {html:e.outerHTML,box:{x:r.x,y:r.y,width:r.width,height:r.height}};}')
            check('Approved R2 DOM preserved '+item['selector'],actual['html']==item['html'])
            check('Approved R2 geometry preserved '+item['selector'],actual['box']==item['box'],{'before':item['box'],'after':actual['box']})
        check('Five operational families preserved plus separate proposed family',page.locator('#operational-matrix [data-family]').count()==5 and page.locator('#commercial-proposal').count()==1)
        check('Commercial family initially collapsed',not page.locator('#commercial-proposals-body').is_visible())
        page.locator('#commercial-expand').focus();page.keyboard.press('Enter')
        check('Keyboard expands proposed activities, separate from grant selector',page.locator('#commercial-expand').get_attribute('aria-expanded')=='true' and page.locator('#commercial-proposal input:disabled').count()==4 and page.locator('#commercial-proposal [data-capability],#commercial-proposal [data-family-select]').count()==0)
        check('Proposals cannot change canonical draft',page.locator('#unsaved').inner_text()=='Sin cambios pendientes' and page.locator('#save-permissions').is_disabled())
        check('No broad financial power or inherited Master authority','delegación comercial expresa' in page.locator('#commercial-proposal').inner_text())
        page.locator('#commercial-conditions').evaluate('e=>e.open=true')
        terms=page.locator('#commercial-conditions').inner_text()
        check('Rate/basis pending; historical15 is customer discount',terms.count('PENDIENTE DE DEFINICIÓN')==2 and '15% de descuento al cliente' in terms and 'no aplicado' in terms and 'no puede cambiar sus condiciones' in terms)
        check('Paid monthly/annual renewals retained without code reentry','mensuales y anuales' in terms and 'No requiere reingresar el código' in terms)
        layout('Commercial detail');shot('commercial-detail')
        own_view();modal=page.locator('#commercial-activity');own=modal.inner_text()
        check('Own seller view clearly fictional/proposed','PROPUESTO · SIN CONECTAR' in own and 'Aurora Naranjo' in own and 'NO VÁLIDO' in own)
        check('Only own fictional customers, no other advisor selector',modal.locator('[data-own-customer="aurora"]').count()==2 and modal.locator('[data-own-advisor="aurora"]').count()==5 and modal.locator('select').count()==0 and 'Alba del Prado' not in own and 'Bruno Lago' not in own)
        check('Monthly renewals and annual second year included',modal.locator('[data-period="m2"],[data-period="m3"],[data-period="a2"]').count()==3)
        check('FREE alone excluded; paid versus activation unresolved','FREE activo sin pago' in own and 'activación pendiente → elegibilidad por resolver' in own)
        check('No fake balances or commission defaults','Sin calcular' in own and 'Sin determinar' in own and '$' not in own and 'MXN' not in own and '15% no es la tasa' in own)
        check('Modal opens at heading and foreground only',page.locator('#commercial-activity-title').evaluate('e=>e===document.activeElement') and modal.evaluate('e=>e.open'))
        layout('Own activity');shot('own-activity',True)
        page.locator('#commercial-replay').click()
        check('Same notice and different notice same payment stay five conceptual results',modal.locator('[data-conceptual-result]').count()==5 and page.locator('#commercial-replay-status').inner_text()=='7 avisos ficticios → 5 resultados conceptuales únicos.')
        page.locator('#commercial-replay').click()
        check('Repeated presentation replay adds no result/balance',modal.locator('[data-conceptual-result]').count()==5 and 'no existe saldo financiero' in modal.inner_text())
        check('Visual dedupe never claims financial idempotency','No prueba idempotencia ni concurrencia financiera' in modal.inner_text())
        page.keyboard.press('Tab');check('Modal focus stays within view',page.locator('#close-commercial').evaluate('e=>e===document.activeElement'))
        page.keyboard.press('Tab');check('Modal focus wraps',page.locator('#commercial-replay').evaluate('e=>e===document.activeElement'))
        page.keyboard.press('Shift+Tab');check('Modal reverse wrap',page.locator('#close-commercial').evaluate('e=>e===document.activeElement'))
        shot('conceptual-replay',True);page.keyboard.press('Escape')
        page.wait_for_function('()=>document.activeElement===document.querySelector("#preview-own-activity")')
        check('Escape returns focus to entry',not modal.evaluate('e=>e.open'))
        member('bruno');own_view();own=modal.inner_text()
        check('Next own view contains only next advisor customers','Bruno Lago' in own and 'Alba del Prado' in own and 'Irene del Bosque' not in own and modal.locator('[data-own-advisor="bruno"]').count()==5)
        page.keyboard.press('Escape');member('elisa');own_view()
        check('Suspension preserves history, no economic-rights invention','no borra historia ni decide por sí sola' in modal.inner_text() and modal.locator('[data-conceptual-result]').count()==5)
        page.keyboard.press('Escape')
        if width<640:page.locator('#show-directory').click()
        page.locator('#filter-masters').click()
        if width<640:page.locator('#show-detail').click()
        page.locator('#commercial-conditions').evaluate('e=>e.open=true')
        check('Master has no automatic code/customers/earnings',page.locator('#preview-own-activity').count()==0 and 'Sin código comercial propuesto' in page.locator('#commercial-conditions').inner_text())
        page.locator('.review-controls').evaluate('e=>e.open=true');page.locator('#authority-view').select_option('master')
        if width<640:page.locator('#show-detail').click()
        page.locator('#commercial-expand').click()
        check('Operationally delegated Master cannot grant commercial proposal',page.locator('#commercial-proposal input:disabled').count()==4 and page.locator('#filter-masters').is_disabled())
        check('No storage/cookies/service worker',not page.evaluate('window.__writes') and context.storage_state()=={'cookies':[],'origins':[]})
        check('Only allowlisted static GETs and no errors',bool(requests) and not forbidden and not errors,{'forbidden':forbidden,'errors':errors})
        result='PASS'
    except Exception as e:error=str(e)
    finally:context.close()
    return {'engine':engine,'viewport':f'{width}x{height}','result':result,'error':error,'checks':checks,'requests':requests,'forbidden_requests':forbidden,'application_errors':errors,'capture_tool_diagnostics':diagnostics,'screenshots':shots}

def main():
    branch=subprocess.check_output(['git','branch','--show-current'],cwd=ROOT,text=True).strip()
    assert branch=='prototype/ase-vis01-advisor-capability-matrix'
    OUT.mkdir(parents=True,exist_ok=True);before=json.loads((OUT.parent/'r2-before.json').read_text());cases=[]
    with sync_playwright() as p:
        for engine in ['chromium','webkit']:
            b=getattr(p,engine).launch()
            for width,height in [(1440,900),(1366,768),(820,1180),(390,844)]:
                case=run(b,engine,width,height,next(v for v in before if (v['engine'],v['width'])==(engine,width)));cases.append(case);print(engine,case['viewport'],case['result'],case['error'],flush=True)
            b.close()
    paths=['prototypes/ase-vis01/'+p for p in ['index.html','app.js','style.css','catalog.js','commercial.js','commercial.css','commercial-qa.py','qa.py','serve.py']]+['modules/identity/services/InternalCapabilityCatalog.php']
    report={'task':'ASE-COM01','scope':'Static presentation/contract only; no financial acceptance','result':'PASS' if all(c['result']=='PASS' for c in cases) else 'FAIL','commit_at_test':subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip(),'source_sha256':{p:hashlib.sha256((ROOT/p).read_bytes()).hexdigest() for p in paths},'physical_safari':'NOT_RUN','mobile':'INTERIM_MOBILE_SMOKE_ONLY','new_visual_approval_pending':True,'check_count':sum(len(c['checks']) for c in cases),'cases':cases}
    (OUT/'summary.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');print(report['result'],report['check_count'],flush=True)
    return 0 if report['result']=='PASS' else 1
if __name__=='__main__':sys.exit(main())
