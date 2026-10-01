#!/usr/bin/env python3
"""Capture the real authenticated provider portal against disposable fixtures."""
import json
import os
from pathlib import Path
import sys
from playwright.sync_api import sync_playwright

fixture = json.loads(Path(sys.argv[1]).read_text())
role_fixtures = json.loads(Path(sys.argv[3]).read_text())
output = Path(sys.argv[2])
output.mkdir(parents=True, exist_ok=True)
root = 'https://127.0.0.1:8140/provider/'
browser_path = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
issues = []

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path=browser_path, headless=True)
    context = browser.new_context(ignore_https_errors=True, viewport={'width':1440,'height':810}, device_scale_factor=1)
    context.add_cookies([{'name':'__Host-mxmed_session','value':fixture['token'],
                          'domain':'127.0.0.1','path':'/','secure':True,'httpOnly':True,'sameSite':'Lax'}])
    page = context.new_page()
    page.on('pageerror', lambda error: issues.append(str(error)))
    def capture(name, width, height):
        page.set_viewport_size({'width':width,'height':height})
        page.screenshot(path=str(output / name), full_page=True)
        overflow = page.evaluate('document.documentElement.scrollWidth > window.innerWidth')
        print(f'{name}: horizontal_overflow={overflow}')
        if overflow: issues.append(f'{name}: horizontal overflow')
    def open_module(name):
        page.get_by_role('button', name=name, exact=True).click()
        page.locator('.content-panel .loading').wait_for(state='detached', timeout=15000)
    page.goto(root, wait_until='domcontentloaded')
    page.get_by_role('heading', name='Resumen', exact=True).wait_for(timeout=20000)
    assert page.locator('#page-title').inner_text() == 'Provider A'
    capture('A-resumen-owner-1440x810.png',1440,810)
    capture('A-resumen-owner-1440x900.png',1440,900)
    capture('A-resumen-owner-1366x768.png',1366,768)
    open_module('Sucursales')
    page.get_by_text('Sucursal Centro').wait_for()
    capture('B-sucursales-1440x810.png',1440,810)
    open_module('Servicios')
    page.get_by_text('Biometría hemática',exact=True).first.wait_for()
    capture('C-servicios-1440x810.png',1440,810)
    open_module('Áreas de servicio')
    page.get_by_text('Código postal 01000').wait_for()
    capture('D-areas-1440x810.png',1440,810)
    open_module('Equipo')
    page.get_by_text('owner@example.invalid').wait_for()
    capture('E-equipo-1440x810.png',1440,810)
    open_module('Suscripción')
    page.locator('.metric strong').get_by_text('Plan de proveedor').wait_for()
    capture('F-suscripcion-1440x810.png',1440,810)
    page.locator('#organization-picker').select_option('prov_b')
    page.get_by_role('heading', name='Resumen', exact=True).wait_for()
    open_module('Sucursales')
    page.get_by_text('Se requiere un plan de proveedor activo',exact=False).wait_for()
    capture('G-sin-plan-1440x810.png',1440,810)
    page.locator('#organization-picker').select_option('prov_a')
    page.get_by_role('heading', name='Resumen', exact=True).wait_for()
    capture('H-resumen-mobile-390x844.png',390,844)
    open_module('Servicios')
    capture('I-servicios-mobile-390x844.png',390,844)
    page.locator('#study-search').fill('BH')
    page.get_by_text('Configurado',exact=True).wait_for()
    assert page.locator('#catalog-results').get_by_text('Biometría hemática').count() == 1
    page.locator('#study-category').select_option('IMAGEN')
    page.get_by_text('Sin estudios coincidentes.').wait_for()
    print('QA_STUDY_CATALOG_ALIAS_CATEGORY=PASS')
    page.set_viewport_size({'width':1440,'height':810})
    open_module('Sucursales')
    page.locator('[data-edit-location]').first.click()
    page.locator('#loc-phone').fill('55 5555 9191')
    dialogs=[]
    page.once('dialog',lambda dialog: (dialogs.append(dialog.message), dialog.dismiss()))
    page.get_by_role('button',name='Servicios',exact=True).click()
    assert 'cambios sin guardar' in dialogs[0]
    assert page.get_by_role('heading',name='Sucursales',exact=True).count() == 1
    page.locator('#location-form [type=submit]').click()
    page.get_by_text('Sucursal guardada.').wait_for()
    assert page.get_by_text('55 5555 9191').count() == 1
    assert 'Verificado' in page.locator('.list-row').first.inner_text()
    print('QA_LOCATION_PHONE_NO_REVERIFICATION=PASS')
    page.locator('[data-edit-location]').first.click()
    page.locator('#loc-street').fill('Av. Reforma nueva')
    dialogs=[]
    page.once('dialog',lambda dialog: (dialogs.append(dialog.message), dialog.accept()))
    page.locator('#location-form [type=submit]').click()
    assert 'nueva verificación' in dialogs[0]
    page.get_by_text('Av. Reforma nueva').wait_for()
    assert 'Pendiente de verificación' in page.locator('.list-row').first.inner_text()
    print('QA_LOCATION_REVERIFICATION_WARNING=PASS')
    page.get_by_role('button',name='Agregar sucursal').click()
    page.locator('#loc-branch_name').fill('Sucursal QA Nueva')
    page.locator('#location-form [type=submit]').click()
    page.get_by_text('Sucursal QA Nueva').wait_for()
    print('QA_LOCATION_CREATE=PASS')
    open_module('Servicios')
    page.locator('[data-edit-offering]').first.click()
    assert page.locator('#offering-form [name=study_type_id]').count() == 0
    page.locator('#service-mode').select_option('MOBILE')
    page.locator('#offering-form [type=submit]').click()
    page.get_by_text('Configuración guardada.').wait_for()
    print('QA_OFFERING_IDENTITY_IMMUTABLE=PASS')
    open_module('Áreas de servicio')
    page.locator('#area-postal').fill('01001')
    page.locator('#area-form [type=submit]').click()
    page.get_by_text('Código postal 01001').wait_for()
    assert 'Pendiente de verificación' in page.get_by_text('Código postal 01001').locator('..').inner_text()
    print('QA_SERVICE_AREA_CREATE=PASS')
    open_module('Equipo')
    page.locator('#invite-email').fill('eligible2@example.invalid')
    page.once('dialog',lambda dialog: dialog.accept())
    page.locator('#invite-form [type=submit]').click()
    page.get_by_text('Invitación enviada.').wait_for()
    assert page.get_by_text('eligible2@example.invalid').count() >= 1
    print('QA_TEAM_EMAIL_INVITE=PASS')
    page.locator('#organization-picker').select_option('prov_b')
    page.get_by_role('heading',name='Resumen',exact=True).wait_for()
    open_module('Equipo')
    assert page.locator('#invite-email').count() == 1
    print('QA_TEAM_WITHOUT_ENTITLEMENT=PASS')
    page.locator('#organization-picker').select_option('prov_a')
    page.get_by_role('heading',name='Resumen',exact=True).wait_for()
    assert 'MX|CP|' not in page.locator('body').inner_text()
    assert 'prov04f_bh' not in page.locator('body').inner_text()
    assert not issues, issues
    for actor in ['admin','collab','eligible']:
        role_context=browser.new_context(ignore_https_errors=True,viewport={'width':1440,'height':810})
        role_context.add_cookies([{'name':'__Host-mxmed_session','value':role_fixtures[actor]['token'],
                                   'domain':'127.0.0.1','path':'/','secure':True,'httpOnly':True,'sameSite':'Lax'}])
        role_page=role_context.new_page()
        role_page.goto(root,wait_until='domcontentloaded')
        if actor=='eligible':
            role_page.get_by_text('No tienes organizaciones de proveedor disponibles',exact=False).wait_for()
            assert role_page.locator('#module-nav').is_hidden()
            print('QA_NO_ORGANIZATIONS=PASS')
        elif actor=='admin':
            role_page.get_by_role('heading',name='Resumen',exact=True).wait_for()
            assert role_page.get_by_role('button',name='Equipo',exact=True).count()==0
            role_page.get_by_role('button',name='Sucursales',exact=True).click()
            role_page.get_by_text('Sucursal Centro').wait_for()
            status=role_page.evaluate("async () => (await fetch('/api/provider/index.php/organizations/prov_b')).status")
            assert status==404
            print('QA_ADMIN_ENTITLED_CROSS_ORGANIZATION=PASS')
        else:
            role_page.get_by_role('heading',name='Resumen',exact=True).wait_for()
            assert role_page.get_by_role('button',name='Equipo',exact=True).count()==0
            assert role_page.get_by_role('button',name='Sucursales',exact=True).count()==0
            assert role_page.get_by_role('button',name='Suscripción',exact=True).count()==1
            print('QA_COLLABORATOR_SAFE_CONTEXT=PASS')
        role_context.close()
    print('VISUAL_QA=PASS')
    browser.close()
