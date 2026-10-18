#!/usr/bin/env python3
"""ASE-VIS01 R2: synthetic static UI checks; no original app, Identity, SQL or delivery."""
import argparse
import datetime as dt
import hashlib
import json
from pathlib import Path
import subprocess
import sys
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
VIEWPORTS = [(1440,900),(1366,768),(820,1180),(390,844)]
ROUTES = {'/commercial.js','/commercial.css','/','/style.css','/catalog.js','/app.js','/assets/css/media-review-inbox.css'}
FUTURE = {'profile_create','profile_curate','profile_publication_manage','profile_support_assist'}
RESTRICTED = {'public_profile_correction_review','public_profile_correction_apply'}
DELEGATED = {'media_review_read','media_review_approve','physician_accreditation_review'}
WARNING = "Refused to apply a stylesheet because its hash, its nonce, or 'unsafe-inline' does not appear in the style-src directive of the Content Security Policy."
def git(*args):
    return subprocess.check_output(['git',*args],cwd=ROOT,text=True).strip()

def run_case(browser,engine,viewport,base,evidence,baseline):
    width,height=viewport
    context=browser.new_context(viewport={'width':width,'height':height},locale='es-MX',reduced_motion='reduce')
    page=context.new_page()
    checks=[];errors=[];requests=[];forbidden=[];screenshots=[];diagnostics=[];capture_messages=[];capturing=False
    origin=urlsplit(base)
    def route(r):
        q=r.request;p=urlsplit(q.url);item={'method':q.method,'path':p.path,'origin':f'{p.scheme}://{p.netloc}'};requests.append(item)
        if (p.scheme,p.netloc)!=(origin.scheme,origin.netloc) or p.path not in ROUTES or q.method!='GET':forbidden.append(item);r.abort()
        else:r.continue_()
    context.route('**/*',route)
    page.on('pageerror',lambda e:errors.append(str(e)))
    def console(m):
        if m.type!='error':return
        if capturing and engine=='webkit' and m.text==WARNING:capture_messages.append(m.text)
        else:errors.append(m.text)
    page.on('console',console)
    context.add_init_script("""(() => {
      window.__storageAttempts=[];window.__styles=[];window.__closes={confirmation:0,onboarding:0};
      new MutationObserver(records=>records.forEach(r=>r.addedNodes.forEach(n=>{if(n.nodeName==='STYLE')window.__styles.push(n.textContent);}))).observe(document,{childList:true,subtree:true});
      addEventListener('DOMContentLoaded',()=>['confirmation','onboarding'].forEach(id=>document.getElementById(id).addEventListener('close',()=>window.__closes[id]++)));
      for(const method of ['setItem','removeItem','clear']){const original=Storage.prototype[method];Storage.prototype[method]=function(...args){window.__storageAttempts.push(method);return original.apply(this,args);};}
      if(window.indexedDB){const original=indexedDB.open.bind(indexedDB);indexedDB.open=(...args)=>{window.__storageAttempts.push('indexedDB');return original(...args);};}
      if(navigator.serviceWorker){const original=navigator.serviceWorker.register.bind(navigator.serviceWorker);navigator.serviceWorker.register=(...args)=>{window.__storageAttempts.push('serviceWorker');return original(...args);};}
    })();""")
    def check(name,ok,detail=None):
        checks.append({'name':name,'pass':bool(ok),**({'detail':detail} if detail is not None else {})})
        if not ok:raise AssertionError(f'{engine} {width}x{height}: {name}; {detail}')
    def text(selector,fragment,name):check(name,fragment in page.locator(selector).inner_text())
    def shot(name,modal=False):
        nonlocal capturing
        if name!='collapsed' and width not in [1440,390]:return
        path=evidence/f'{engine}-{width}x{height}-{name}.png';before=page.evaluate('window.__styles.length');messages=len(capture_messages);capturing=True
        try:
            page.screenshot(path=str(path),full_page=not modal)
            styles=page.evaluate('(n)=>window.__styles.slice(n)',before);found=capture_messages[messages:]
            if engine=='webkit' and styles==['body {}'] and found==[WARNING]:diagnostics.append({'screenshot':name,'style':'body {}','csp_rejected':True,'message':WARNING,'scope':'Exact DOM mutation during screenshot only'})
            elif found:errors.extend(found)
        finally:capturing=False
        screenshots.append(str(path.relative_to(ROOT)))
    def layout(name):
        metrics=page.evaluate("""()=>({overflow:Math.max(document.body.scrollWidth,document.documentElement.scrollWidth)>innerWidth,small:[...document.querySelectorAll('button,select,input:not([type=checkbox]),summary,.family-select,.capability-row,.ack-row')].filter(e=>e.getClientRects().length && !e.closest('dialog:not([open])') && e.getBoundingClientRect().height<43.9).map(e=>({id:e.id,height:e.getBoundingClientRect().height}))})""")
        check(name+' no horizontal overflow',not metrics['overflow'],metrics)
        check(name+' controls/labels >=44px',not metrics['small'],metrics['small'])
    def close_dialog(id,action):
        old=page.evaluate('(id)=>window.__closes[id]',id);action();page.wait_for_function('([id,n])=>window.__closes[id]>n',arg=[id,old])
    def enter(selector):page.locator(selector).focus();page.keyboard.press('Enter')
    def member(id):
        if width<=640:page.locator('#show-directory').click()
        enter(f'[data-member="{id}"]')
    def mode(value):
        page.locator('.review-controls').evaluate('e=>e.open=true');page.locator('#authority-view').select_option(value)
    def root(kind='use'):return page.locator('#operational-matrix' if kind=='use' else '#delegation-matrix')
    def group(id,kind='use'):return root(kind).locator(f'[data-family-select="{id}"]')
    def expand(id,kind='use'):
        button=root(kind).locator(f'[data-family="{id}"] .family-toggle')
        if button.get_attribute('aria-expanded')=='false':button.click()
    def cap(key,kind='use'):return root(kind).locator(f'[data-capability="{key}"]')
    def selected_keys(kind='use'):return set(root(kind).locator('[data-capability]:checked').evaluate_all('nodes=>nodes.map(n=>n.dataset.capability)'))
    def save():enter('#save-permissions')
    def accept():close_dialog('confirmation',lambda:page.locator('#accept-confirmation').click())
    def focus(selector,name):
        page.wait_for_function('(s)=>document.querySelector(s)===document.activeElement',arg=selector);check(name,True)
    def fresh():page.reload(wait_until='networkidle')
    def wizard_step():page.locator('#next-onboarding').click()
    def wizard_start(scenario,category='ADVISOR'):
        if width<=640:page.locator('#show-directory').click()
        page.locator('#add-member').click();page.locator('#identity-case').select_option(scenario)
        wizard_step();page.locator('#member-category').select_option(category);wizard_step()
    result={}
    try:
        response=page.goto(base,wait_until='networkidle')
        check('Static preview HTTP 200 and no backend connections by CSP',response.status==200 and "connect-src 'none'" in response.headers['content-security-policy'] and "form-action 'none'" in response.headers['content-security-policy'])
        text('h1','EQUIPO INTERNO Y PERMISOS','R2 heading')
        text('body','PROTOTIPO — NO OPERATIVO','Explicit mock scope')
        catalog=page.evaluate('window.ASE_VIS01_CATALOG');op={k for k,v in catalog.items() if v['kind']=='operational' and v['delegable']}
        check('18 definitions and exact16 operational checkboxes',len(catalog)==18 and set(root().locator('[data-capability]').evaluate_all('ns=>ns.map(n=>n.dataset.capability)'))==op)
        check('Director sees six advisors, no editable Director root',page.locator('[data-member]').count()==6 and page.locator('[data-member="director"]').count()==0)
        check('Five families collapsed initially',root().locator('.family').count()==5 and root().locator('[data-capability]:visible').count()==0)
        check('Expand and select are separate native controls',root().locator('[data-expand]').count()==5 and root().locator('[data-family-select]').count()==5)
        if width<=640:page.locator('#show-detail').click()
        dimensions=page.evaluate('({page_height:document.documentElement.scrollHeight,matrix_height:document.querySelector("#operational-matrix").getBoundingClientRect().height,detail_height:document.querySelector("#advisor-detail").getBoundingClientRect().height})')
        old=next(v for v in baseline['viewports'] if v['engine']==engine and v['width']==width)
        check('Collapsed matrix height reduced at least40% from R1',dimensions['matrix_height']<old['matrix_height']*.6,{'r1':old,'r2':dimensions})
        layout('Collapsed matrix');shot('collapsed')
        if width<=640:check('Mobile selection-first hides directory in detail view',not page.locator('#team-directory').is_visible() and page.locator('#advisor-detail').is_visible())
        else:check('Desktop/tablet retains both columns',page.locator('#team-directory').is_visible() and page.locator('#advisor-detail').is_visible())
        check('One selected media capability has mixed semantics',group('media').evaluate('e=>e.indeterminate') and group('media').get_attribute('aria-checked')=='mixed')
        before=selected_keys();expand('media');check('Expand does not modify any grant',selected_keys()==before)
        group('media').click();check('Entire Media family selects six exact capabilities',len(selected_keys() & set(k for k in op if k.startswith('media_')))==6)
        check('All eligible selected indicator',group('media').is_checked() and not group('media').evaluate('e=>e.indeterminate'))
        cap('media_review_approve').uncheck();check('Individual override produces mixed5of6',len(selected_keys())==5 and group('media').evaluate('e=>e.indeterminate'))
        shot('expanded-partial');layout('Expanded partial')
        check('No autosave; directory still one saved permission','1 permiso asignado' in page.locator('[data-member="aurora"]').inner_text())
        cap('media_review_read').uncheck();save()
        text('#confirmation-body','Conceder uso (4)','Grouped confirmation lists individual grants')
        text('#confirmation-body','Revocar uso (1)','Grouped confirmation lists individual revocation')
        text('#confirmation-body','Descargar originales','Private source consequence remains visible')
        check('No capability IDs shown in UI',all(k not in page.locator('body').inner_text() for k in catalog))
        focus('#cancel-confirmation','Confirmation focuses Cancel first')
        for key in ['Tab','Tab','Shift+Tab']:
            page.keyboard.press(key);check('Confirmation focus remains inside '+key,page.locator('#confirmation').evaluate('e=>e.contains(document.activeElement)'))
        layout('Multigrant dialog');shot('multi-confirmation',True)
        close_dialog('confirmation',lambda:page.keyboard.press('Escape'));focus('#save-permissions','Escape returns keyboard focus')
        check('Escape preserves unsaved proposal',not page.locator('#save-permissions').is_disabled())
        page.locator('#revert').click();focus('#detail-title','Revert restores focus');check('Revert restores original one grant',selected_keys()=={'media_review_read'})
        expand('media');cap('media_review_approve').check();member('bruno')
        text('#confirmation-title','Descartar','Selection change protects unsaved draft')
        close_dialog('confirmation',lambda:page.locator('#cancel-confirmation').click())
        check('Cancel selection preserves advisor and independent draft',page.locator('#detail-title').inner_text()=='Aurora Naranjo' and selected_keys()=={'media_review_read','media_review_approve'})
        if width<=640:page.locator('#show-detail').click()
        save();accept()
        text('#feedback','Simulación guardada','Explicit simulated success');check('Save updates only page memory',selected_keys()=={'media_review_read','media_review_approve'})
        focus('#detail-title','Confirmed save focus restored')
        expand('media');cap('media_review_read').uncheck();page.locator('.review-controls').evaluate('e=>e.open=true');page.locator('#simulate-conflict').check();save();accept()
        check('Stale conflict blocks saving and preserves draft',page.locator('#conflict-banner').is_visible() and page.locator('#save-permissions').is_disabled())
        focus('#conflict-banner','Conflict focuses recovery notice');page.locator('#refresh-conflict').click();check('Explicit refresh restores saved grants',len(selected_keys())==2)
        enter('#lifecycle');text('#confirmation-description','conserva todos sus permisos','Suspension preserves history and grants');accept()
        check('Suspended advisor retains grants and blocks edits',len(selected_keys())==2 and root().locator('[data-capability]:enabled').count()==0)
        enter('#lifecycle');accept();check('Reactivated advisor retains grants',len(selected_keys())==2 and root().locator('[data-capability]:enabled').count()==10)
        member('bruno');check('Three-grant fixture',len(selected_keys())==3);member('cora');check('All16assigned fixture retained including locked grants',len(selected_keys())==16);member('diego');check('Zero-grant fixture',len(selected_keys())==0)
        # Master authority is independent from operational use and delegation.
        fresh();page.locator('#filter-masters').click()
        if width<=640:page.locator('#show-detail').click()
        check('Director Master directory has two fictional Masters',page.locator('[data-member]').count()==2 and 'Lucía' in page.locator('#detail-title').inner_text())
        check('Master operational use has exactly two grants',len(selected_keys())==2)
        page.locator('.master-governance').evaluate('e=>e.open=true')
        check('Management authority and three exact delegations separate from use',page.locator('#manage-advisors').is_checked() and selected_keys('delegate')==DELEGATED)
        check('Corrections consumer allowed only for Master targets; profiles still locked',all(cap(k).is_enabled() for k in RESTRICTED) and all(cap(k).is_disabled() for k in FUTURE))
        shot('master-administration');layout('Master administration')
        expand('media');cap('media_review_improve').check()
        check('Operational use edit does not grant delegation','media_review_improve' not in selected_keys('delegate'))
        page.locator('#revert').click();page.locator('.master-governance').evaluate('e=>e.open=true')
        expand('media','delegate');cap('media_review_source_download','delegate').check()
        check('Delegation edit does not add operational use',len(selected_keys())==2)
        page.locator('#revert').click()
        enter('#designation');check('Removing Master requires distinct acknowledgement',page.locator('#accept-confirmation').is_disabled());page.locator('#privileged-ack').check();accept()
        check('Removing designation preserves operational grants',len(selected_keys())==2 and page.locator('.master-governance').count()==0)
        text('#feedback','gestión y delegaciones retiradas','Removal reports management and delegation revocation')
        enter('#designation');check('Director designation is separately confirmed',page.locator('#accept-confirmation').is_disabled());page.locator('#privileged-ack').check();accept()
        text('.detail-header','Autorización pendiente','Master proposal is not fully activated')
        check('Appointment adds no use grants, management or delegation',len(selected_keys())==2 and not page.locator('#manage-advisors').is_checked() and not selected_keys('delegate'))
        # Delegated family selection must preserve existing grants outside scope.
        fresh();mode('master');member('aurora');group('media').click()
        check('All eligible subset selected still has truthful mixed2of6 indicator',selected_keys()=={'media_review_read','media_review_approve'} and group('media').get_attribute('aria-checked')=='mixed')
        text('#operational-matrix [data-family="media"] .family-count','2 de 2 editables','Delegated subset count is explicit')
        page.locator('#revert').click();member('cora')
        check('Master cannot select Masters, appoint or self-manage',page.locator('#filter-masters').is_disabled() and page.locator('#designation').count()==0 and page.locator('[data-member="lucia"]').count()==0)
        check('Only exact3delegated controls enabled',set(root().locator('[data-capability]:enabled').evaluate_all('ns=>ns.map(n=>n.dataset.capability)'))==DELEGATED)
        expand('media');group('media').click()
        check('Partial Master bulk revokes only2delegated Media, preserves4others',len(selected_keys() & set(k for k in op if k.startswith('media_')))==4 and 'media_review_source_download' in selected_keys())
        check('Partial group truthfully mixed, not all granted',group('media').get_attribute('aria-checked')=='mixed')
        save();text('#confirmation-body','Revocar uso (2)','Only exact delegated revocations confirmed');accept();check('Saved subset retains all14othergrants',len(selected_keys())==14)
        expand('media');group('media').click();check('Selecting eligible subset restores2without changing locked4',len(selected_keys())==16 and group('media').get_attribute('aria-checked')=='true')
        page.locator('#revert').click();mode('missing')
        if width<=640:page.locator('#show-detail').click()
        check('Master missing management cannot mutate groups or lifecycle',root().locator('[data-capability]:enabled').count()==0 and root().locator('[data-family-select]:enabled').count()==0 and page.locator('#lifecycle').is_disabled() and page.locator('#add-member').is_disabled())
        fresh();mode('master');wizard_start('existing')
        page.locator('#previous-onboarding').click()
        check('Master onboarding category excludes Master appointment',page.locator('#member-category option[value="MASTER_ADMIN"]').evaluate('e=>e.disabled'))
        close_dialog('onboarding',lambda:page.keyboard.press('Escape'))
        fresh();page.locator('#add-member').click();page.locator('#member-email').fill('not-a-fictional-domain@example.com');wizard_step()
        text('#onboarding-feedback','example.test','Prototype accepts synthetic email only')
        check('Guided dialog keeps keyboard focus inside',page.locator('#onboarding').evaluate('e=>e.contains(document.activeElement)'))
        for key in ['Tab','Shift+Tab']:
            page.keyboard.press(key);check('Guided modal focus trap '+key,page.locator('#onboarding').evaluate('e=>e.contains(document.activeElement)'))
        close_dialog('onboarding',lambda:page.keyboard.press('Escape'))
        # Unified journey scenarios. No account existence lookup, password, or delivery.
        for scenario,expected in [('existing','Autorización pendiente'),('new','Invitación pendiente'),('verification','Verificación de identidad pendiente'),('mfa','Configuración MFA pendiente'),('expired','Invitación pendiente'),('partial','Autorización pendiente')]:
            fresh();wizard_start(scenario)
            check(scenario+' collapsed initial permissions',page.locator('#onboarding [data-capability]:visible').count()==0)
            page.locator('#onboarding [data-family-select="media"][data-kind="use"]').click();wizard_step()
            text('#onboarding-content',expected,scenario+' safe pending review')
            check(scenario+' never asks admin for another person password',page.locator('#onboarding input[type=password]').count()==0)
            if scenario=='new':shot('unified-onboarding',True);layout('Onboarding review')
            close_dialog('onboarding',wizard_step)
            text('.detail-header',expected,scenario+' pending lifecycle recorded as UX only')
            check(scenario+' proposal has zero assigned operational grants',not selected_keys() and root().locator('[data-capability]:enabled').count()==0)
            text('#feedback','no se envió una invitación',scenario+' no false delivery success')
            if scenario=='partial':text('.detail-header .pending-notice','Actualizar y reconciliar', 'Partial failure requires reconciliation, no blind retry')
            if scenario=='expired':text('.detail-header .pending-notice','ha vencido','Expired invitation is not sent or activated')
        fresh();wizard_start('duplicate');wizard_step();wizard_step()
        check('Duplicate keeps workflow open without creating member',page.locator('#onboarding').evaluate('e=>e.open') and page.locator('[data-member]').count()==6)
        text('#onboarding-feedback','No se crea otra entrada','Duplicate requires safe reconciliation');close_dialog('onboarding',lambda:page.locator('#cancel-onboarding').click())
        fresh();wizard_start('existing','MASTER_ADMIN')
        page.locator('#onboarding [data-family-select="media"][data-kind="use"]').click();page.locator('.wizard-delegation').evaluate('e=>e.open=true');page.locator('#onboard-manage').check();page.locator('#onboarding [data-family-select="media"][data-kind="delegate"]').click();wizard_step()
        check('Unified Master proposal requires explicit Director confirmation',page.locator('#next-onboarding').is_disabled())
        text('#onboarding-content','seguridad siguen pendientes','Master security ceremony is explicitly future');shot('master-onboarding',True)
        page.locator('#master-confirm').check();close_dialog('onboarding',wizard_step)
        check('Master onboarding proposal creates no effective grants/delegation/manage',not selected_keys() and not selected_keys('delegate') and not page.locator('#manage-advisors').is_checked())
        text('.detail-header','Autorización pendiente','Master onboarding is not an activation')
        # Directory states, labels, focus, privacy and page memory foundations.
        fresh();page.locator('#advisor-search').fill('nobody-fictional');text('#directory-content','Sin resultados','Search no-results');page.locator('#advisor-search').fill('')
        page.locator('.review-controls').evaluate('e=>e.open=true')
        for state,word in [('empty','Aún no hay integrantes'),('loading','Cargando equipo'),('error','No pudimos consultar')]:
            page.locator('#directory-state').select_option(state);text('#directory-content',word,'Directory '+state)
        page.locator('#retry-directory').click();check('Retry restores historical fictional fixtures',page.locator('[data-member]').count()==6)
        if width<=640:page.locator('#show-detail').click()
        check('Labels/descriptions associated with individual checkboxes',root().locator('[data-capability]').evaluate_all('ns=>ns.every(n=>n.labels.length===1 && document.getElementById(n.getAttribute("aria-describedby")))'))
        check('Status feedback is live',page.locator('#feedback').get_attribute('aria-live')=='polite' and page.locator('#unsaved').get_attribute('role')=='status')
        page.locator('.skip-link').focus();page.keyboard.press('Enter');focus('#advisor-detail','Skip link supports focus and mobile detail access')
        layout('Final layout')
        storage=page.evaluate('({attempts:window.__storageAttempts,local:localStorage.length,session:sessionStorage.length,cookies:document.cookie})')
        check('No persistence attempts or cookies',not storage['attempts'] and not storage['local'] and not storage['session'] and not storage['cookies'] and context.storage_state()=={'cookies':[],'origins':[]},storage)
        check('Only allowlisted local GETs; no Identity/governance/delivery calls',not forbidden and bool(requests),requests)
        check('No application errors; exact screenshot diagnostic separated',not errors,errors)
        fresh();check('Reload discards all simulated staff/governance changes',page.locator('[data-member]').count()==6 and selected_keys()=={'media_review_read'})
        if width<=640:page.locator('#show-detail').click()
        expand('media');before_close=page.evaluate('window.__closes.confirmation')
        page.evaluate('''()=>{document.querySelector('#use-media-media_review_approve').click();document.querySelector('#save-permissions').click();document.querySelector('#cancel-confirmation').click();document.querySelector('#save-permissions').click();}''')
        page.wait_for_function('(n)=>window.__closes.confirmation>n',arg=before_close)
        check('Rapid reopen ignores old queued close event',page.locator('#confirmation').evaluate('e=>e.open'))
        accept();check('Rapidly reopened action still executes',selected_keys()=={'media_review_read','media_review_approve'})
        check('No late application error or prohibited request',not errors and not forbidden)

        result={'result':'PASS','dimensions':dimensions}
    except Exception as error:
        try:shot('failure')
        except Exception:pass
        result={'result':'FAIL','error':str(error)}
    finally:context.close()
    return {'engine':engine,'viewport':f'{width}x{height}',**result,'checks':checks,'requests':requests,'forbidden_requests':forbidden,'application_errors':errors,'capture_tool_diagnostics':diagnostics,'screenshots':screenshots}

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--url',default='http://127.0.0.1:8140/');parser.add_argument('--evidence',type=Path,default=ROOT/'prototypes/ase-vis01/evidence/r2/browser');args=parser.parse_args();p=urlsplit(args.url)
    if p.scheme!='http' or p.hostname!='127.0.0.1' or not p.port or p.port<8140 or p.path not in ['','/'] or p.query or p.fragment or p.username or p.password:parser.error('Only loopback static preview root at port>=8140')
    if git('branch','--show-current')!='prototype/ase-vis01-advisor-capability-matrix':parser.error('Isolated prototype branch required')
    evidence=args.evidence.resolve();evidence.mkdir(parents=True,exist_ok=True)
    paths=['prototypes/ase-vis01/'+n for n in ['index.html','app.js','style.css','catalog.js','commercial.js','commercial.css','commercial-qa.py','qa.py','serve.py','export-catalog.php']]+['assets/css/media-review-inbox.css','modules/identity/services/InternalCapabilityCatalog.php']
    driver=Path(__import__('playwright').__file__).resolve().parent/'driver/package/lib/server/screenshotter.js';data=driver.read_bytes()
    report={'task':'ASE-COM01 R2 regression','r2_visual_approval':'APPROVED_BY_DIRECTOR_IN_ASE_COM01','final_visual_approval_scope':'ASE-COM01 extension only','started_utc':dt.datetime.now(dt.timezone.utc).isoformat(),'branch':git('branch','--show-current'),'commit_at_test':git('rev-parse','HEAD'),'commit_binding':'Precommit candidate SHA256 must match final commit tree','source_sha256':{f:hashlib.sha256((ROOT/f).read_bytes()).hexdigest() for f in paths},'physical_safari':'NOT_RUN','mobile':'INTERIM_MOBILE_SMOKE_ONLY','final_visual_approval':False,'capture_tool_qualification':{'driver_path':str(driver),'sha256':hashlib.sha256(data).hexdigest(),'body_style_line':next(i for i,line in enumerate(data.decode().splitlines(),1) if 'style.textContent = "body {}"' in line),'rule':'Only exact WebKit body {} injection plus matching CSP denial during screenshot is qualified; other errors fatal, no driver/CSP bypass.'},'accessibility_limits':'DOM, keyboard, labels, focus, modal and target smoke; no physical screen-reader or Safari acceptance. macOS WebKit Tab-to-link preference is not changed.','cases':[]}
    baseline=json.loads((ROOT/'prototypes/ase-vis01/evidence/r2/baseline.json').read_text())
    with sync_playwright() as playwright:
        for engine in ['chromium','webkit']:
            browser=getattr(playwright,engine).launch()
            try:
                for size in VIEWPORTS:
                    case=run_case(browser,engine,size,args.url,evidence,baseline);report['cases'].append(case);print(f"{engine} {case['viewport']}: {case['result']} ({len(case['checks'])} checks) {case.get('error','')}",flush=True)
            finally:browser.close()
    report['result']='PASS' if len(report['cases'])==8 and all(c['result']=='PASS' for c in report['cases']) else 'FAIL';report['check_count']=sum(len(c['checks']) for c in report['cases']);report['finished_utc']=dt.datetime.now(dt.timezone.utc).isoformat();(evidence/'summary.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');print(json.dumps({'result':report['result'],'checks':report['check_count'],'summary':str(evidence/'summary.json')}),flush=True)
    return 0 if report['result']=='PASS' else 1
if __name__=='__main__':sys.exit(main())
