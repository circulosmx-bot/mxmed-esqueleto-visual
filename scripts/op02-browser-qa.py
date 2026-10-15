#!/usr/bin/env python3
"""Real Chromium/WebKit UI QA. Explicitly resets ONLY OP02 synthetic fixtures."""
import argparse, datetime, importlib.util, json, pathlib, subprocess
from playwright.sync_api import sync_playwright, expect
ROOT=pathlib.Path(__file__).resolve().parents[1];SUPPORT=pathlib.Path.home()/'Library/Application Support/MXMED/op02-admin';BASE='https://127.0.0.1:8445'
CREDS=json.loads((SUPPORT/'credentials.json').read_text());EVIDENCE=SUPPORT/'evidence';EVIDENCE.mkdir(mode=0o700,exist_ok=True)
ALL=['agenda.appointments.read','agenda.appointments.create','agenda.appointments.reschedule','agenda.appointments.cancel','agenda.availability.read']
spec=importlib.util.spec_from_file_location('op02env',ROOT/'scripts/op02-env.py');envmod=importlib.util.module_from_spec(spec);spec.loader.exec_module(envmod)

def login(page,role):
    page.goto(BASE+'/public/identity/acceso.php');page.locator('[name=email]').fill(CREDS[role]['email']);page.locator('[name=password]').fill(CREDS[role]['password']);page.locator('[data-submit]').click();page.wait_for_url('**/private-app.php*',timeout=15000);expect(page.locator('#op02-status')).to_have_text('Sesión y permisos vigentes.',timeout=15000)
def select(page,keys):
    for key in ALL:page.locator('[name="'+key+'"]').set_checked(key in keys)
def invite(page,role,keys,technical='collaborator'):
    page.locator('#ag_ops_add_btn').click();expect(page.locator('#op02-grants input:checked')).to_have_count(0);page.locator('#op02-email').fill(CREDS[role]['email']);page.locator('#op02-role').select_option(technical);select(page,keys);page.locator('#op02-save').click();expect(page.locator('#op02-form-status')).to_have_text('Cambio guardado y confirmado.',timeout=15000)
def accept(page):
    page.locator('button[data-panel="op02-inbox"]').click();page.get_by_role('button',name='Abrir invitación',exact=True).click();expect(page.locator('#op02-invitation')).to_be_visible();expect(page.locator('#op02-offer')).to_contain_text('Dra. Aurora Entorno Aislado');page.locator('#op02-accept').click();expect(page.locator('#op02-status')).to_have_text('Invitación aceptada. Tus permisos están disponibles.',timeout=15000);expect(page.locator('#op02-invitation')).not_to_be_visible()
def edit(page,alias,keys):
    page.reload();expect(page.locator('#op02-status')).to_have_text('Sesión y permisos vigentes.',timeout=15000)
    card=page.locator('#ag_ops_list .op02-card').filter(has=page.get_by_role('heading',name=alias,exact=True));card.get_by_role('button',name='Editar permisos',exact=True).click();select(page,keys);page.locator('#op02-save').click();expect(page.locator('#op02-form-status')).to_have_text('Cambio guardado y confirmado.',timeout=15000)
def fit(page):
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth+2'), 'horizontal overflow'

def case(browser,engine,width,height,index):
    subprocess.run(['python3',str(ROOT/'scripts/op02-env.py'),'reset'],check=True,stdout=subprocess.DEVNULL)
    subprocess.run(['python3',str(ROOT/'scripts/op02-env.py'),'start'],check=True,stdout=subprocess.DEVNULL)
    contexts=[];errors=[]
    try:
        ownerctx=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});contexts.append(ownerctx);owner=ownerctx.new_page();owner.on('pageerror',lambda e:errors.append(str(e)));login(owner,'owner')
        expect(owner.locator('#ag_ops_available_value')).to_have_text('2');expect(owner.locator('#p-ag-operadores')).to_be_visible();fit(owner)
        invite(owner,'operador-a',[ALL[0],ALL[1],ALL[4]]);invite(owner,'operador-b',[ALL[3]],'administrator');expect(owner.locator('#ag_ops_pending_value')).to_have_text('2');expect(owner.locator('#ag_ops_available_value')).to_have_text('0');owner.evaluate('scrollTo(0,0)');owner.screenshot(path=str(EVIDENCE/f'{engine}-{width}x{height}-owner.png'))
        actx=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});contexts.append(actx);operator=actx.new_page();operator.on('pageerror',lambda e:errors.append(str(e)));requests=[];operator.on('request',lambda r:requests.append(r.url));login(operator,'operador-a')
        operator.get_by_role('button',name='Abrir invitación',exact=True).click();expect(operator.locator('#op02-invitation')).to_be_visible();operator.screenshot(path=str(EVIDENCE/f'{engine}-{width}x{height}-offer.png'));operator.locator('#op02-accept').click();expect(operator.locator('#op02-status')).to_have_text('Invitación aceptada. Tus permisos están disponibles.',timeout=15000)
        expect(operator.locator('#op02-owner-nav')).not_to_be_visible();operator.locator('#op02-agenda-nav').click();expect(operator.locator('#op02-create')).to_be_visible()
        day=(datetime.date.today()+datetime.timedelta(days=10+index)).isoformat();operator.locator('#op02-date').fill(day);operator.locator('#op02-start').fill(day+'T10:00');operator.locator('#op02-end').fill(day+'T10:30');operator.locator('#op02-create button').click();expect(operator.locator('#op02-create-result')).to_contain_text('Cita creada:',timeout=15000)
        operator.reload();expect(operator.locator('#op02-status')).to_have_text('Sesión y permisos vigentes.',timeout=15000);operator.locator('#op02-date').fill(day);operator.locator('#op02-load').click();expect(operator.locator('#op02-appointments .op02-card')).to_have_count(1,timeout=15000);expect(operator.get_by_role('button',name='Cancelar cita',exact=True)).to_have_count(0)
        edit(owner,'op02-operador-a',ALL);operator.reload();expect(operator.locator('#op02-status')).to_have_text('Sesión y permisos vigentes.',timeout=15000);operator.locator('#op02-date').fill(day);operator.locator('#op02-load').click();operator.get_by_role('button',name='Reprogramar',exact=True).click();operator.locator('#op02-new-start').fill(day+'T11:00');operator.locator('#op02-new-end').fill(day+'T11:30');operator.get_by_role('button',name='Guardar reprogramación',exact=True).click();expect(operator.locator('#op02-status')).to_have_text('Cita reprogramada.',timeout=15000)
        operator.locator('#op02-availability').click();expect(operator.locator('#op02-availability-result')).to_contain_text('horarios disponibles',timeout=15000);fit(operator);operator.screenshot(path=str(EVIDENCE/f'{engine}-{width}x{height}-agenda.png'))
        # Revocation is enforced on the existing session and old visible controls.
        edit(owner,'op02-operador-a',[ALL[0],ALL[1],ALL[2],ALL[4]])
        operator.on('dialog',lambda d:d.accept());operator.get_by_role('button',name='Cancelar cita',exact=True).click();expect(operator.locator('#op02-status')).to_contain_text('No tienes autorización',timeout=15000);operator.screenshot(path=str(EVIDENCE/f'{engine}-{width}x{height}-denied.png'))
        operator.reload();expect(operator.locator('#op02-status')).to_have_text('Sesión y permisos vigentes.',timeout=15000);operator.locator('#op02-date').fill(day);operator.locator('#op02-load').click();expect(operator.get_by_role('button',name='Cancelar cita',exact=True)).to_have_count(0);expect(operator.locator('#op02-appointments')).to_contain_text('11:00:00')
        bctx=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});contexts.append(bctx);bp=bctx.new_page();login(bp,'operador-b');accept(bp);bp.locator('#op02-agenda-nav').click();expect(bp.locator('#op02-load')).not_to_be_visible();expect(bp.locator('#op02-action-locator')).to_be_visible();fit(bp)
        # Full module denial through real HTTP from the authenticated browser.
        assert operator.evaluate("async()=> (await fetch('/api/clinical-documents.php')).status")==403
        assert not any('/api/clinical/' in u or '/api/patients/' in u for u in requests), 'clinical background fetch'
        assert 'mxmed-private-bootstrap' not in operator.content() and 'OP02_PRIVATE_CANARY' not in operator.content()
        operator.locator('#op02-logout').click();operator.wait_for_url('**/public/identity/acceso.php?state=logged-out',timeout=15000);login(operator,'operador-a');operator.locator('#op02-agenda-nav').click();expect(operator.locator('#op02-create')).to_be_visible();fit(operator)
        assert not errors, 'browser JS errors: '+repr(errors)
        print(f'QA_{engine.upper()}_{width}_{height}=PASS',flush=True)
        return {'engine':engine,'viewport':f'{width}x{height}','result':'PASS','functions':['owner_panel','two_invites','verified_acceptance','permission_editor','operator_login','create','reschedule','availability','reload','immediate_revocation','backend_denial','bounded_navigation','logout_login']}
    finally:
        for c in contexts:c.close()

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--reset-between-cases',action='store_true',required=True);parser.parse_args()
    before=envmod.snapshot_normal();results=[]
    try:
        with sync_playwright() as p:
            for engine in ['chromium','webkit']:
                browser=getattr(p,engine).launch(headless=True)
                try:
                    for index,(width,height) in enumerate([(1440,900),(1366,768),(820,1180)]):results.append(case(browser,engine,width,height,index))
                finally:browser.close()
        assert before==envmod.snapshot_normal(),'normal-local/QA05 checksums changed'
        print('OP02_BROWSER_QA=PASS',flush=True)
    finally:(EVIDENCE/'browser-qa.json').write_text(json.dumps({'results':results,'physical_safari':'NOT_RUN'},indent=2)+'\n')
