#!/usr/bin/env python3
"""Original private-app Operators + Agenda, actual isolated Identity/SQL; never resets fixtures."""
import os,pathlib,json
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
SUPPORT=pathlib.Path.home()/'Library/Application Support/MXMED/op-act01-rehearsal';OUT=pathlib.Path.home()/'Library/Application Support/MXMED/op-act01-evidence'
BASE='https://127.0.0.1:8448';CREDS=json.loads((SUPPORT/'credentials.json').read_text());REVIEW=json.loads((OUT/'browser-review.json').read_text());RESULTS=[]
def login(page,role):
 page.goto(BASE+'/public/identity/acceso.php');page.locator('[name=email]').fill(CREDS[role]['email']);page.locator('[name=password]').fill(CREDS[role]['password']);page.locator('[data-submit]').click();page.wait_for_url('**/private-app.php*');page.wait_for_function("window.mxmedPrivateSession?.state==='authenticated'",timeout=90000)
 page.wait_for_timeout(1500)
def band(page,name):return page.locator('#ag_ops_list .mx-ag-ops-operator-band').filter(has=page.locator('strong',has_text=name))
def rename(page,current,next):
 card=band(page,current);expect(card).to_have_count(1)
 if card.locator('.mx-ag-ops-band-head').get_attribute('aria-expanded')!='true':card.locator('.mx-ag-ops-band-head').click()
 card.get_by_role('button',name='Editar alias',exact=True).click();expect(page.locator('#ag_ops_alias')).to_be_enabled();assert all(e.is_disabled() for e in page.locator('[data-operator-grant]').all())
 page.locator('#ag_ops_alias').fill(next);page.locator('#ag_ops_save_btn').click();expect(page.locator('#ag_ops_modal_error')).to_have_text('Cambio guardado y confirmado.',timeout=15000);expect(band(page,next)).to_have_count(1)
def main():
 os.umask(0o077)
 with sync_playwright() as p:
  for engine in ['chromium','webkit']:
   browser=getattr(p,engine).launch(**({'args':['--ignore-certificate-errors']} if engine=='chromium' else {}))
   for width,height in [(1440,900),(1366,768),(820,1180)]:
    errors=[];ctx=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height},timezone_id='America/Mexico_City');page=ctx.new_page();page.set_default_navigation_timeout(30000);page.on('pageerror',lambda e:errors.append(str(e)));login(page,'owner')
    page.evaluate("window.jumpTo('p-ag-operadores')")
    expect(band(page,'Lupita')).to_have_count(1)
    assert page.locator('#mmSidebar').count()==page.locator('#ag_calendar').count()==page.locator('#p-ag-operadores').count()==1
    assert not page.locator('#ag_ops_login,#ag_ops_temp_password').count()
    expect(page.locator('[data-operator-grant]')).to_have_count(5)
    page.locator('#ag_ops_identity_readiness').evaluate('e=>e.open=true');expect(page.locator('#ag_ops_photo')).to_be_disabled();expect(page.get_by_role('button',name='Usar cámara móvil',exact=True)).to_be_disabled()
    assert page.evaluate('!window.mxmedCanonicalOperators && !!window.mxmedOperatorPresentationRoster')
    page.screenshot(path=str(OUT/f'{engine}-{width}x{height}-operators.png'),full_page=True)
    page.locator('#p-ag-operadores [data-ag-work-panel=p-ag-admin]').click();expect(page.locator('#ag_custom_week_root')).to_be_visible(timeout=20000)
    expect(page.locator('#ag_origin_catalog .mx-ag-origin-dot--operator-02').first.locator('..')).to_have_text('Operador Lupita')
    assert not page.evaluate('document.documentElement.scrollWidth>innerWidth+2')
    page.screenshot(path=str(OUT/f'{engine}-{width}x{height}-original-agenda.png'),full_page=True)
    assert not errors,errors
    print('ACT01_BROWSER_'+engine+'_'+str(width)+'=PASS',flush=True)
    RESULTS.append({'engine':engine,'viewport':[width,height],'original_shell':'PASS','normal_owner_panel':'PASS','full_agenda_preserved':'PASS','photo_qr_disabled':'PASS'});ctx.close()
   # A granted administrator remains a non-medical operator, never owner.
   ctx=browser.new_context(ignore_https_errors=True);page=ctx.new_page();login(page,'operador-b');assert not page.locator('#p-ag-operadores,#p-expediente,#p-pac-recetas').count();assert page.evaluate("async()=> (await window.mxmedCanonicalOperators.request('/api/profile-operators/index.php/overview')).status")==403;ctx.close();browser.close()
 (OUT/'browser.json').write_text(json.dumps(RESULTS,indent=2)+'\n');print('ACT01_ORIGINAL_UI_BROWSER='+str(len(RESULTS))+' PASS',flush=True)
def offer_and_operator():
 with sync_playwright() as p:
  for engine in ['chromium','webkit']:
   browser=getattr(p,engine).launch(**({'args':['--ignore-certificate-errors']} if engine=='chromium' else {}))
   if engine=='chromium':
    ctx=browser.new_context(ignore_https_errors=True);page=ctx.new_page()
    page.goto(BASE+'/public/identity/acceso.php#operator-invitation='+REVIEW['raw'])
    page.locator('[name=email]').fill(CREDS['operador-c']['email']);page.locator('[name=password]').fill(CREDS['operador-c']['password']);page.locator('[data-submit]').click()
    expect(page.locator('#operator-offer-modal')).to_be_visible(timeout=30000)
    expect(page.locator('#operator-offer-alias')).to_have_value('Tere');expect(page.locator('#operator-offer-alias')).to_be_disabled()
    assert 'operator-invitation=' not in page.url
    page.locator('#operator-offer-accept').click();page.wait_for_url('**/private-app.php?workspace=agenda#p-ag-admin');page.wait_for_function("window.mxmedCanonicalOperators?.state.personal_alias==='Tere'")
    assert page.evaluate('window.mxmedCanonicalOperators.state.owner') is False
    print('ACT01_CANONICAL_EMAIL_FRAGMENT_OFFER_ACCEPTANCE=PASS',flush=True);ctx.close()
   for width,height in [(1440,900),(1366,768),(820,1180)]:
    ctx=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height},timezone_id='America/Mexico_City');page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));login(page,'operador-b');page.evaluate('window.mxmedCanonicalOperators.whenReady')
    page.evaluate("window.jumpTo('p-ag-admin')");expect(page.locator('#ag_custom_week_root')).to_be_visible(timeout=20000)
    assert not page.locator('#p-ag-operadores,#p-expediente,#p-pac-recetas').count()
    facts=page.evaluate("""async ({id,day})=>{const r=await window.mxmedCanonicalOperators.request('/api/agenda/index.php/appointments?from='+day+'%2000:00:00&to='+day+'%2023:59:59');if(!r.ok)throw Error('reader');const row=r.json.data.find(a=>a.appointment_id===id);const meta=window.mxmedAgendaAppointmentOriginMeta(row),neutral=window.mxmedAgendaAppointmentOriginMeta({created_by_role:'operator',channel_origin:'operator_workspace'});return {label:meta.label,visual:meta.visualKey,neutral:neutral.label,alias:row.creator_snapshot.alias,overflow:document.documentElement.scrollWidth>innerWidth+2};}""",{'id':REVIEW['appointment_id'],'day':REVIEW['day']})
    assert facts=={'label':'Operador Mary','visual':'operator-01','neutral':'Operador (histórico sin alias confirmado)','alias':'Mary','overflow':False},facts
    page.screenshot(path=str(OUT/f'{engine}-{width}x{height}-bounded-agenda.png'),full_page=True);assert not errors,errors
    print('ACT01_BOUNDED_ORIGINAL_AGENDA_'+engine+'_'+str(width)+'=PASS',flush=True);ctx.close()
   browser.close()
 (OUT/'offer-and-operator.json').write_text(json.dumps({'exact_email_offer':'PASS','explicit_acceptance':'PASS','original_bounded_agenda_six_viewports':'PASS','historical_snapshot_renderer':'PASS','no_clinical_html':'PASS'},indent=2)+'\n')
def legacy():
 with sync_playwright() as p:
  browser=p.chromium.launch(args=['--ignore-certificate-errors']);ctx=browser.new_context(ignore_https_errors=True,viewport={'width':1440,'height':900});page=ctx.new_page()
  cached={'operators':[{'operator_id':'act01-legacy-'+str(n),'alias':'ARCHIVO'+str(n+1),'status':status} for n,status in enumerate(['active','paused','archived'])]}
  ctx.add_init_script('localStorage.setItem("mxmed.agenda.operators.state.v1",'+json.dumps(json.dumps(cached))+');')
  login(page,'owner');page.evaluate("window.jumpTo('p-ag-operadores')");expect(page.locator('#ag_ops_deployed_legacy')).to_have_count(1)
  expect(page.locator('#ag_ops_deployed_legacy .mx-ag-ops-operator-band')).to_have_count(3);expect(page.locator('#ag_ops_browser_list li')).to_have_count(3)
  assert page.locator('#ag_ops_deployed_legacy').get_attribute('open') is None and page.locator('#ag_ops_browser_history').get_attribute('open') is None
  page.locator('#ag_ops_deployed_legacy > summary').click();record=page.locator('#ag_ops_deployed_legacy .mx-ag-ops-operator-band').first;record.locator('.mx-ag-ops-band-head').click()
  expect(record.get_by_role('button')).to_have_count(2);record.get_by_role('button',name='Ver historial').click();expect(page.locator('#ag_ops_history_count')).to_have_text('3 acciones');expect(page.locator('#ag_ops_history_list p')).to_have_count(3)
  assert json.loads(page.evaluate('localStorage.getItem("mxmed.agenda.operators.state.v1")'))==cached
  page.screenshot(path=str(OUT/'active-panel-legacy-history.png'),full_page=True);(OUT/'legacy-browser.json').write_text(json.dumps({'server_legacy_3':'PASS','events_9_scoped_3':'PASS','uncertain_browser_3_separate_collapsed':'PASS','no_merge_or_storage_changes':'PASS'},indent=2)+'\n');print('ACT01_ACTIVE_LEGACY_HISTORY_AND_UNCERTAIN_REFERENCES=PASS',flush=True);browser.close()
if __name__=='__main__':
 import sys
 legacy() if '--legacy' in sys.argv else (offer_and_operator() if '--offer-and-operator' in sys.argv else main())
