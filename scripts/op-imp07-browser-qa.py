#!/usr/bin/env python3
"""Original private-app Operators + Agenda, actual isolated Identity/SQL; never resets fixtures."""
import os,pathlib,json
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
SUPPORT=pathlib.Path.home()/'Library/Application Support/MXMED/opimp07-rehearsal';OUT=pathlib.Path.home()/'Library/Application Support/MXMED/op02-admin/evidence/op-imp07'
BASE='https://127.0.0.1:8447';CREDS=json.loads((SUPPORT/'credentials.json').read_text());REVIEW=json.loads((OUT/'review-state.json').read_text());RESULTS=[]
def login(page,role):
 page.goto(BASE+'/public/identity/acceso.php');page.locator('[name=email]').fill(CREDS[role]['email']);page.locator('[name=password]').fill(CREDS[role]['password']);page.locator('[data-submit]').click();page.wait_for_url('**/private-app.php*');page.wait_for_function("window.mxmedPrivateSession?.state==='authenticated' && !!window.mxmedCanonicalOperators")
 page.evaluate('window.mxmedCanonicalOperators.whenReady');page.wait_for_timeout(500)
def band(page,name):return page.locator('#ag_ops_list .mx-ag-ops-operator-band').filter(has=page.locator('strong',has_text=name))
def rename(page,current,next):
 card=band(page,current);expect(card).to_have_count(1)
 if card.locator('.mx-ag-ops-band-head').get_attribute('aria-expanded')!='true':card.locator('.mx-ag-ops-band-head').click()
 card.get_by_role('button',name='Editar alias',exact=True).click();expect(page.locator('#ag_ops_alias')).to_be_enabled();assert all(e.is_disabled() for e in page.locator('[data-operator-grant]').all())
 page.locator('#ag_ops_alias').fill(next);page.locator('#ag_ops_save_btn').click();expect(page.locator('#ag_ops_modal_error')).to_have_text('Cambio guardado y confirmado.',timeout=15000);expect(band(page,next)).to_have_count(1)
def offers():
 # This composition owns only synthetic accounts. Release/reinvite through the
 # canonical lifecycle; never reset an existing database or insert memberships.
 import importlib.util,os
 os.environ['MXMED_OP_IMP07_REHEARSAL']='1'
 spec=importlib.util.spec_from_file_location('op02',ROOT/'scripts/op02-http-qa.py');q=importlib.util.module_from_spec(spec);spec.loader.exec_module(q)
 owner=q.Client().login('owner')
 def revoke(role):
  member=next(r for r in owner.overview()['members'] if r['account_id']=='op02-'+role and r['status']=='active')
  owner.cmd('revoke',{'membership_id':member['membership_id'],'expected_revision':member['revision']})
 revoke('operador-c')
 with sync_playwright() as p:
  browser=p.chromium.launch();contexts=[]
  def fresh(role):
   ctx=browser.new_context(ignore_https_errors=True,viewport={'width':1440,'height':900});contexts.append(ctx);pg=ctx.new_page();login(pg,role);return pg
  try:
   pg=fresh('owner');pg.evaluate("window.jumpTo('p-ag-operadores')")
   for role,ownerAlias,acceptedAlias in [('operador-a','Mary','Mary'),('operador-c','','Tere')]:
    pg.bring_to_front();pg.locator('#ag_ops_refresh').click();expect(pg.locator('#ag_ops_available_value')).to_have_text('1')
    pg.locator('#ag_ops_form_cancel_btn').click();pg.locator('#ag_ops_email').fill(CREDS[role]['email']);pg.locator('#ag_ops_identity_readiness').evaluate('e=>e.open=true');pg.locator('#ag_ops_alias').fill(ownerAlias)
    pg.locator('[data-ops-accordion-toggle=permissions]').click()
    for grant in pg.locator('[data-operator-grant]').all():grant.check()
    pg.locator('#ag_ops_save_btn').click();expect(pg.locator('#ag_ops_modal_error')).to_have_text('Cambio guardado y confirmado.',timeout=15000)
    invitee=fresh(role);nav=invitee.locator('#mmSidebar [data-panel=p-operator-inbox]')
    if not nav.is_visible():invitee.locator('[data-action=sidebar-toggle-proxy]').click()
    nav.click();invitee.get_by_role('button',name='Abrir invitación',exact=True).click();expect(invitee.locator('#operator-offer-modal')).to_be_visible()
    alias=invitee.locator('#operator-offer-alias');expect(alias).to_have_value(ownerAlias)
    if ownerAlias:expect(alias).to_be_disabled()
    else:expect(alias).to_be_editable();alias.fill(acceptedAlias)
    invitee.locator('#operator-offer-accept').click();invitee.wait_for_url('**/private-app.php?workspace=agenda#p-ag-admin');invitee.wait_for_function("window.mxmedCanonicalOperators?.state.presentation_ready===true")
    assert invitee.evaluate('window.mxmedCanonicalOperators.state.personal_alias')==acceptedAlias
    pg.bring_to_front();pg.locator('#ag_ops_refresh').click();expect(band(pg,acceptedAlias)).to_have_count(1)
    print('OP07_ORIGINAL_OFFER_'+role+'=PASS',flush=True)
    if role=='operador-a':revoke(role)
   (OUT/'offer-ui.json').write_text(json.dumps({'owner_alias_original_form':'PASS','owner_alias_exact_readonly_offer':'PASS','invitee_alias_original_acceptance':'PASS','canonical_confirmation_only':'PASS'},indent=2)+'\n')
  finally:
   for ctx in contexts:ctx.close()
   browser.close()

def main():
 os.umask(0o077)
 with sync_playwright() as p:
  for engine in ['chromium','webkit']:
   browser=getattr(p,engine).launch()
   for width,height in [(1440,900),(1366,768),(820,1180)]:
    errors=[];ctx=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height},timezone_id='America/Mexico_City');page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)));login(page,'owner')
    page.evaluate("window.jumpTo('p-ag-operadores')")
    expect(band(page,'Tere')).to_have_count(1);expect(band(page,'Lupita')).to_have_count(1)
    assert page.locator('#mmSidebar').count()==page.locator('#ag_calendar').count()==page.locator('#p-ag-operadores').count()==1
    assert not page.locator('#ag_ops_login,#ag_ops_temp_password,#p-expediente,#p-pac-recetas').count()
    expect(page.locator('[data-operator-grant]')).to_have_count(5)
    page.locator('#ag_ops_identity_readiness').evaluate('e=>e.open=true');expect(page.locator('#ag_ops_photo')).to_be_disabled();expect(page.get_by_role('button',name='Usar cámara móvil',exact=True)).to_be_disabled()
    # Alias write travels through the existing canonical command route.
    rename(page,'Tere','Teresa');rename(page,'Teresa','Tere')
    # A rejected rename cannot overwrite another member or claim success.
    card=band(page,'Tere');card.get_by_role('button',name='Editar alias',exact=True).click();page.locator('#ag_ops_alias').fill('LUPITA');page.locator('#ag_ops_save_btn').click();expect(page.locator('#ag_ops_modal_error')).to_have_text('Ese alias ya está reservado en este equipo. Elige otro.');expect(band(page,'Tere')).to_have_count(1)
    page.locator('#ag_ops_form_cancel_btn').click()
    assert all(not e.is_disabled() for e in page.locator('[data-operator-grant]').all())
    page.screenshot(path=str(OUT/f'{engine}-{width}x{height}-operators.png'),full_page=True)
    page.locator('#p-ag-operadores [data-ag-work-panel=p-ag-admin]').click();expect(page.locator('#ag_custom_week_root')).to_be_visible(timeout=20000)
    expect(page.locator('#ag_origin_catalog .mx-ag-origin-dot--operator-01').first.locator('..')).to_have_text('Operador Tere');expect(page.locator('#ag_origin_catalog .mx-ag-origin-dot--operator-02').first.locator('..')).to_have_text('Operador Lupita')
    facts=page.evaluate("""async ({id,day})=>{
      const r=await window.mxmedCanonicalOperators.request('/api/agenda/index.php/appointments?from='+day+'%2000:00:00&to='+day+'%2023:59:59');
      if(!r.ok)throw Error('appointment reader unavailable');const old=r.json.data.find(a=>a.appointment_id===id);
      const meta=window.mxmedAgendaAppointmentOriginMeta(old);
      const neutral=window.mxmedAgendaAppointmentOriginMeta({channel_origin:'operator_workspace',created_by_role:'operator',created_by_id:'old-person'});
      const color=p=>getComputedStyle(document.querySelector('#ag_origin_catalog .mx-ag-origin-dot--operator-0'+p)).backgroundColor;
      return {label:meta.label,visual:meta.visualKey,neutral:neutral.label,snapshot:old.creator_snapshot,colors:[color(1),color(2)],overflow:document.documentElement.scrollWidth>innerWidth+2};
    }""",{'id':REVIEW['appointment_id'],'day':REVIEW['day']})
    assert facts['label']=='Operador Mary' and facts['visual']=='operator-01',facts
    assert facts['neutral']=='Operador (histórico sin alias confirmado)' and facts['colors']==['rgb(255, 0, 127)','rgb(255, 102, 204)'] and not facts['overflow'],facts
    # The actual original appointment modal must agree with the authoritative helper.
    toggle=page.get_by_role('button',name='Ver más horas',exact=True)
    if toggle.count() and toggle.first.is_visible():toggle.first.click()
    event=page.locator('[data-event-id="cancel-trace:'+REVIEW['appointment_id']+'"]')
    if not event.count():event=page.locator('[data-event-id="'+REVIEW['appointment_id']+'"]')
    expect(event.first).to_be_visible(timeout=15000)
    # Native cards render before FullCalendar finishes hydrating its event store.
    # Retry only the existing read-only detail action until that store is ready.
    for attempt in range(30):
     if page.locator('#ag_event_actions_modal').is_visible():break
     event.first.click()
     page.wait_for_timeout(500)
    expect(page.locator('#ag_event_actions_modal')).to_be_visible();expect(page.locator('#ag_event_action_origin')).to_have_text('Operador Mary')
    page.screenshot(path=str(OUT/f'{engine}-{width}x{height}-historical-detail.png'),full_page=True)
    assert not errors,errors
    print('OP07_BROWSER_'+engine+'_'+str(width)+'=PASS',flush=True)
    RESULTS.append({'engine':engine,'viewport':[width,height],'original_shell':'PASS','canonical_rename_collision':'PASS','current_alias_legend':'PASS','historical_detail':'PASS','neutral_historical_label':'PASS','two_exact_colors':'PASS','photo_qr_disabled':'PASS'});ctx.close()
   # A granted administrator remains a non-medical operator, never owner.
   ctx=browser.new_context(ignore_https_errors=True);page=ctx.new_page();login(page,'operador-b');assert not page.locator('#p-ag-operadores,#p-expediente,#p-pac-recetas').count();assert page.evaluate("async()=> (await window.mxmedCanonicalOperators.request('/api/profile-operators/index.php/overview')).status")==403;ctx.close();browser.close()
 (OUT/'browser.json').write_text(json.dumps(RESULTS,indent=2)+'\n');print('OP07_ORIGINAL_UI_BROWSER='+str(len(RESULTS))+' PASS',flush=True)
if __name__=='__main__':
 import sys
 offers() if '--offers-only' in sys.argv else main()
