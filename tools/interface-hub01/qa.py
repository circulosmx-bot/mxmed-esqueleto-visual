"""Focused browser QA; no grants, memberships, domain writes or real login automation."""
import json, pathlib
async def run(windows, command, open_window, snapshot, state, origin, hub):
    checks=[]
    def check(name,ok):
        checks.append({'name':name,'pass':bool(ok)})
        if not ok:raise AssertionError(name)
    try:
        d=windows['director'][2];a=windows['advisor'][2];h=windows['hub'][2]
        check('Director actual team UI',await d.locator('#filter-advisors').is_visible())
        check('Advisor exact original physician', 'Leticia' in await a.locator('#physician-name').inner_text())
        check('Six entries present',len(snapshot()['interfaces'])==6)
        original_page=a
        for source,role in [(h,'director'),(d,'advisor'),(a,'director')]:
            await source.locator('#mxmed-review-navigation').evaluate('(e)=>e.shadowRoot.querySelector("details").open=true')
            button=source.get_by_role('listitem').filter(has_text=snapshot()['interfaces'][0 if role=='director' else 1]['title']).get_by_role('button')
            await button.click()
        check('Open/focus reuses same Advisor page',windows['advisor'][2]==original_page)
        check('Selection preserved on focus','Leticia' in await a.locator('#physician-name').inner_text())
        dc=await windows['director'][1].cookies(origin);ac=await windows['advisor'][1].cookies(origin)
        ds=[c['value'] for c in dc if c['name']=='__Host-mxmed_session'];ass=[c['value'] for c in ac if c['name']=='__Host-mxmed_session']
        check('Canonical sessions present and distinct',len(ds)==len(ass)==1 and ds!=ass)
        if 'physician' in windows:check('Medical context has no staff cookies',not await windows['physician'][1].cookies(origin))
        else:check('Unavailable original medical context not launched',not next(x for x in snapshot()['interfaces'] if x['id']=='physician')['enabled'])
        for action in ['operator','public','provider','rm -rf /','https://example.com',{'action':'director'}]:
            denied=False
            try:await command({'page':a,'frame':a.main_frame},action)
            except RuntimeError:denied=True
            check('Denied unavailable/arbitrary action '+str(action),denied)
        child=await windows['advisor'][1].new_page();await child.goto(origin+'/public/identity/acceso.php')
        denied=False
        try:await command({'page':child,'frame':child.main_frame},'director')
        except RuntimeError:denied=True
        check('Unmanaged page cannot invoke controller',denied);await child.close()
        response=await h.request.post(hub+'/',data='director');check('HTTP cannot launch processes',response.status==501)
        response=await h.request.get(hub+'/commands');check('No HTTP command route',response.status==404)
        check('Unavailable buttons disabled',await h.get_by_role('button',name='No disponible').count()==len([x for x in snapshot()['interfaces'] if not x['enabled']]))
        for width,height in [(1366,768),(390,844)]:
            await h.set_viewport_size({'width':width,'height':height})
            check('Responsive hub '+str(width),await h.evaluate('document.documentElement.scrollWidth<=innerWidth'))
            await h.screenshot(path=str(state/('hub-'+str(width)+'.png')))
        await h.set_viewport_size({'width':1280,'height':720})
        for key in ['director','advisor']:
            p=windows[key][2];await p.reload();await p.locator('#mxmed-review-navigation').wait_for()
            check('Persistent navigation survives reload '+key,await p.locator('#mxmed-review-navigation').is_visible())
        # Restore authoritative physician selection after the explicit refresh test.
        await a.locator('#physician-search').fill('Leticia');await a.locator('#physician-search-form button[type=submit]').click()
        await a.locator('#discovery-list [data-doctor-id="mxmed-local-development:1"]').click()
        await a.locator('#physician-name').filter(has_text='Leticia').wait_for()
        await a.screenshot(path=str(state/'advisor.png'));await d.screenshot(path=str(state/'director.png'))
    finally:(state/'qa.json').write_text(json.dumps(checks,indent=2))
    return checks
