#!/usr/bin/env python3
"""Chromium/WebKit responsive QA using real QA05 Identity sessions and application UI."""
import json
import pathlib
from playwright.sync_api import sync_playwright

ORIGIN='https://127.0.0.1:8444'
SUPPORT=pathlib.Path.home()/'Library/Application Support/MXMED/qa05-admin'
CREDS=json.loads((SUPPORT/'credentials.json').read_text())
EVIDENCE=SUPPORT/'evidence';EVIDENCE.mkdir(mode=0o700,parents=True,exist_ok=True)
VIEWPORTS=[(1440,900),(1366,768),(820,1180)]

def login(page,role):
    entry=CREDS[role]
    page.goto(ORIGIN+'/public/identity/acceso.php',wait_until='domcontentloaded')
    page.locator('[name="email"]').fill(entry['email']);page.locator('[name="password"]').fill(entry['password']);page.locator('[data-submit]').click()
    page.wait_for_url('**/private-app.php',timeout=15000);page.locator('#mxmed-session-lock').wait_for(state='hidden',timeout=15000)
    actual=page.evaluate("window.mxmedStore?.activeProfessionalContext?.role")
    assert actual==role

def app_checks(page,role):
    page.evaluate("document.querySelector('button.menu-main[data-group=\"perfil\"]')?.click()")
    page.evaluate("document.querySelector('button[data-panel=\"p-consultorio\"]')?.click()")
    page.locator('#p-consultorio').wait_for(state='visible')
    page.wait_for_timeout(900)
    assert page.locator('[data-consultorio-tab-item]').count() >= 1
    assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 2')
    page.evaluate("document.querySelector('button.menu-main[data-group=\"agenda\"]')?.click()")
    page.evaluate("document.querySelector('button[data-panel=\"p-ag-ajustes\"]')?.click()")
    page.locator('#p-ag-ajustes').wait_for(state='visible')
    page.wait_for_timeout(700)
    result=page.evaluate("""async ({role}) => {
      const response=await fetch('/api/agenda/index.php/consultorios',{method:'PUT',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify({doctor_id:'qa05-doctor',consultorio_id:'1',accessible:1})});
      const body=await response.json();return {status:response.status,ok:body.ok===true,message:String(body.message||'')};
    }""",{'role':role})
    if role=='collaborator': assert result['status']==403 and not result['ok'] and result['message']
    else: assert result['status']==200 and result['ok']
    page.screenshot(path=str(EVIDENCE/f'{page.viewport_size["width"]}x{page.viewport_size["height"]}-{role}.png'),full_page=False)
    page.evaluate('window.mxmedPrivateSession.logout()');page.wait_for_url('**/public/identity/acceso.php?state=logged-out',timeout=15000)

with sync_playwright() as playwright:
    for engine_name in ['chromium','webkit']:
        browser=getattr(playwright,engine_name).launch(headless=True)
        for width,height in VIEWPORTS:
            for role in ['owner','administrator','collaborator']:
                context=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height})
                page=context.new_page();login(page,role);app_checks(page,role);context.close()
            print(f'QA_{engine_name.upper()}_{width}_{height}=PASS')
        browser.close()
print('QA05_BROWSER_QA=PASS')
