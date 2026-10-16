#!/usr/bin/env python3
"""Disposable rendering of the original Operators partial/controller; no DB/reset/auth fixtures.
No product page or listener is created. All commands are intercepted in the browser.
"""
import copy,json,os,pathlib,re,subprocess
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=pathlib.Path.home()/'Library/Application Support/MXMED/op02-admin/evidence/op-ux06'
CAPS=['agenda.appointments.read','agenda.appointments.create','agenda.appointments.reschedule','agenda.appointments.cancel','agenda.availability.read']

def render(ready=True,owner=True):
    panel={'can_configure_agenda':not ready,'service_ready':ready,'can_manage':owner and ready}
    prefix="<?php $privateAccess=(object)['allowed'=>true];$operatorPanel=json_decode("+repr(json.dumps(panel))+",true); ?>"
    return subprocess.check_output(['php'],input=prefix+(ROOT/'partials/private-app/operators.php').read_text(),text=True)

def member(id='a',alias='Mary',status='active'):
    return {'membership_id':'membership-'+id,'account_id':'account-'+id,'alias':alias,'role_code':'collaborator','status':status,'capabilities':CAPS[:2],'effective_capabilities':CAPS[:1] if status=='active' else [],'revision':1,'contact_verified':True,'account_status':'active','invited_at':'2026-10-15 10:00:00','accepted_at':'2026-10-15 11:00:00'}

def invitation(status='pending'):
    return {'invitation_id':'invitation-a','alias':'private-contact','email_normalized':'private-contact@example.invalid','status':status,'role_code':'administrator','capabilities':CAPS[2:4],'created_at':'2026-10-15 10:00:00','expires_at':'2026-10-22 10:00:00'}

def view(members=None,invitations=None):
    members=members or [];invitations=invitations or []
    occupied=sum(m['status']!='revoked' for m in members);reserved=sum(i['status']=='pending' for i in invitations)
    return {'limit':2,'occupied':occupied,'reserved':reserved,'available':2-occupied-reserved,'members':members,'invitations':invitations}

CASES={'empty':view(),'one':view([member()]),'two':view([member(),member('b','Tere')]),'pending':view(invitations=[invitation()]),'expired':view(invitations=[invitation('expired')]),'suspended':view([member(status='suspended')]),'revoked':view([member(status='revoked')]),'alias_collision_unavailable':view([member(),member('b','Mary')]),'unicode_initials':view([member(alias='Ñu')]),'photo_unavailable':view([{**member(),'photo_url':'https://untrusted.example.invalid/avatar.svg'}])}

def main():
    os.umask(0o077);OUT.mkdir(parents=True,exist_ok=True);results=[]
    controller=(ROOT/'assets/js/agenda/operators-panel.js').read_text();css=(ROOT/'assets/css/style.css').read_text()
    # Original Bootstrap and styles, no app/backend API. Synthetic test origin is intercepted.
    fonts=''.join(re.findall(r'<link[^>]*href="https://fonts.googleapis.com/[^"]+"[^>]*>',(ROOT/'private-app.php').read_text()))
    markup='<html><head>'+fonts+'<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>'+css+'</style></head><body><main style="padding:16px">'
    with sync_playwright() as p:
        for engine in ['chromium','webkit']:
            browser=getattr(p,engine).launch();context=browser.new_context();page=context.new_page()
            context.route('https://op-ux06.example.invalid/**',lambda r:r.fulfill(status=200,body='<html></html>',content_type='text/html'))
            page.goto('https://op-ux06.example.invalid/')
            def fixture(snapshot,ready=True,owner=True,error=False):
                page.set_content(markup+render(ready,owner)+'</main></body></html>')
                page.evaluate('document.fonts.ready')
                page.locator('#p-ag-operadores').evaluate("e=>e.classList.remove('d-none')")
                page.evaluate("""()=>{window.mxmedOperatorPanel=null;window.commands=[];window.storageWrites=0;window.bootstrap={Modal:{getOrCreateInstance:()=>({show(){document.getElementById('ag_ops_history_modal').classList.add('show')}})}};Object.defineProperty(window,'localStorage',{configurable:true,value:{getItem:()=>null,setItem(){window.storageWrites++}}});}""")
                page.add_script_tag(content=controller)
                page.evaluate("""async ({snapshot,ready,owner,error})=>{
                  window.snapshot=snapshot;
                  const service={data:async path=>{if(error)throw Error('Servicio no disponible');return path.endsWith('/audit')?[{metadata_json:JSON.stringify({producer_metadata:{membership_id:'membership-a'}}),occurred_at_utc:'2026-10-15 12:00:00',real_actor_reference:'account:original-person',action:'PROFILE_OPERATOR_COMMAND',outcome:'SUCCESS'}]:window.snapshot},command:async(action,input)=>{window.commands.push({action,input});throw Error('Cambio rechazado por el servidor');},refresh:async()=>window.mxmedOperatorPanel.refresh()};
                  window.mxmedOperatorPanel=window.mxmedInitializeOperatorsPanel({service_ready:ready,can_manage:owner,legacy:null},service);
                  if(ready&&owner)await window.mxmedOperatorPanel.refresh().catch(()=>{});
                }""",{'snapshot':snapshot,'ready':ready,'owner':owner,'error':error})
            for width,height in [(1440,900),(1366,768),(820,1180)]:
                page.set_viewport_size({'width':width,'height':height})
                for name,snapshot in CASES.items():
                    fixture(copy.deepcopy(snapshot));expect(page.locator('[data-operator-grant]')).to_have_count(5)
                    assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+2'),(engine,width,name)
                    expect(page.locator('#ag_ops_active_value')).to_have_text(str(snapshot['occupied']+snapshot['reserved'])+'/2')
                    if name in ['empty','revoked','expired']:
                        expect(page.locator('#ag_ops_empty_team')).to_be_visible();expect(page.locator('#ag_ops_empty_invite')).to_be_visible();expect(page.locator('#ag_ops_empty_invite')).to_be_enabled()
                        page.locator('#ag_ops_empty_invite').click();expect(page.locator('#ag_ops_email')).to_be_focused()
                    else:expect(page.locator('#ag_ops_empty_team')).not_to_be_visible()
                    cards=page.locator('#ag_ops_list .mx-ag-ops-operator-band')
                    if name=='revoked':
                        expect(cards).to_have_count(0);page.locator('#ag_ops_archived_toggle').click();cards=page.locator('#ag_ops_archived_list .mx-ag-ops-operator-band')
                    for card in cards.all():
                        card.locator('.mx-ag-ops-band-head').click();expect(card.locator('.mx-ag-ops-band-panel')).to_be_visible();assert card.locator('.mx-ag-ops-band-head').get_attribute('aria-controls')==card.locator('.mx-ag-ops-band-panel').get_attribute('id')
                    if name=='pending':
                        expect(cards.first).to_contain_text('p•••@example.invalid');assert 'private-contact' not in cards.first.inner_text();expect(cards.first).to_contain_text('Permisos ofrecidos: Reprogramar citas · Cancelar citas');expect(cards.first).to_contain_text('Reserva una plaza');assert 'No disponible' not in cards.first.locator('dd').all_text_contents()[6:8]
                        assert not cards.first.get_by_role('button',name='Editar permisos',exact=True).count();expect(cards.first.get_by_role('button',name='Cancelar invitación',exact=True)).to_be_visible()
                    if name=='suspended':
                        expect(cards.first).to_contain_text('Conserva su plaza');expect(cards.first.get_by_role('button',name='Reactivar',exact=True)).to_be_visible()
                    if name=='revoked':assert cards.first.get_by_role('button').count()==2 # expand + history only
                    if name=='expired':assert cards.first.get_by_role('button').count()==2
                    if name=='unicode_initials':expect(cards.first.locator('.mx-ag-ops-avatar')).to_have_text('ÑU')
                    if name=='photo_unavailable':assert not cards.first.locator('img').count();expect(cards.first.locator('.mx-ag-ops-avatar')).to_have_text('MA')
                    if name=='alias_collision_unavailable':expect(cards).to_have_count(2);assert len(set(cards.evaluate_all('es=>es.map(e=>e.dataset.operatorId)')))==2
                    expect(page.locator('#ag_ops_alias')).to_be_disabled();expect(page.locator('#ag_ops_photo')).to_be_disabled()
                    page.locator('#ag_ops_identity_readiness').evaluate('e=>e.open=true')
                    colors=page.evaluate("""()=>[...document.querySelectorAll('#ag_ops_identity_readiness .mx-ag-origin-dot')].map(e=>getComputedStyle(e).backgroundColor)""")
                    assert colors==['rgb(255, 0, 127)','rgb(255, 102, 204)'],colors
                    assert page.evaluate('window.storageWrites')==0
                    results.append({'engine':engine,'viewport':[width,height],'state':name,'result':'PASS'})
                    if name in ['empty','two','pending','suspended'] and width==1440:page.screenshot(path=str(OUT/(engine+'-'+name+'.png')),full_page=True)
                # Explicit absence of service is never a fake empty team or authority.
                fixture(view(),ready=False,owner=False);expect(page.locator('#ag_ops_empty_team')).not_to_be_visible();expect(page.locator('#ag_ops_save_btn')).to_be_disabled();expect(page.locator('#ag_ops_active_value')).to_have_text('No disponible')
                fixture(view(),owner=False);expect(page.locator('#ag_ops_save_btn')).to_be_disabled();assert page.evaluate('window.commands.length')==0
                fixture(view(),error=True);expect(page.locator('#ag_ops_empty_team')).not_to_be_visible();expect(page.locator('#ag_ops_active_value')).to_have_text('No disponible')
                results.append({'engine':engine,'viewport':[width,height],'service_error_inactive_nonowner':'PASS'})
            fixture(view([member()]));card=page.locator('#ag_ops_list .mx-ag-ops-operator-band').first;card.locator('.mx-ag-ops-band-head').click();card.get_by_role('button',name='Ver historial',exact=True).click();expect(page.locator('#ag_ops_history_list')).to_contain_text('account:original-person')
            # Failed authoritative command cannot produce success or enable future actions.
            card.get_by_role('button',name='Suspender',exact=True).click();expect(page.locator('#ag_ops_modal_error')).to_have_text('Cambio rechazado por el servidor')
            card.locator('details').evaluate('e=>e.open=true')
            for b in page.locator('[data-ops-unavailable]').all():expect(b).to_be_disabled()
            assert page.evaluate("window.commands.length===1 && window.commands[0].input.membership_id==='membership-a'")
            # Replacing a displayed operator never edits appointment creator snapshots or history.
            old={'created_by_id':'original-person','creator_membership_id':'old-membership','operator_alias':'Mary','operator_slot':1,'origin_visual_key':'operator-01'}
            page.evaluate('value=>{window.appointment=value;window.snapshot.members=[{...window.snapshot.members[0],membership_id:"new-membership",account_id:"new-person",alias:"Tere"}]}',old)
            page.evaluate('window.mxmedOperatorPanel.refresh()');assert page.evaluate('window.appointment')==old
            expect(page.locator('#ag_ops_list strong')).to_have_text('Tere')
            # No alias writer or upload endpoint exists behind the disabled controls.
            expect(page.locator('#ag_ops_alias')).to_be_disabled();expect(page.locator('#ag_ops_photo')).to_be_disabled()
            results.append({'engine':engine,'failed_command':'PASS','future_actions_stay_disabled':'PASS','historical_creator_after_replacement':'PASS','alias_collision_rename_persistence':'NOT_IMPLEMENTED_DISABLED','photo_mobile_storage':'NOT_IMPLEMENTED_DISABLED'})
            context.close();browser.close()
    (OUT/'presentation.json').write_text(json.dumps(results,indent=2)+'\n');print('OP_UX06_PRESENTATION_CASES='+str(len(results))+' PASS (persistence boundaries explicitly disabled)')
if __name__=='__main__':main()
