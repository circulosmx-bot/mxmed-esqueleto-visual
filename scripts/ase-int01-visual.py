#!/usr/bin/env python3
"""Read-only comparison against the untouched approved prototype; no prototype writes."""
import hashlib,json,pathlib,subprocess
from playwright.sync_api import sync_playwright
ROOT=pathlib.Path(__file__).resolve().parents[1];OUT=ROOT/'docs/evidence/ase-int01';BASE='312ee388f93c892e6caf7e845b11e2b42f9c3565'
cfg=json.loads(pathlib.Path(json.loads(pathlib.Path('/tmp/mxmed-ase-int01-owned.json').read_text())['configuration']).read_text());assert cfg['root']==str(ROOT)
token=json.loads((pathlib.Path(cfg['owned'])/'sessions.json').read_text())['tokens']['qa_director']
assets=[]
for old,new in [('style.css','internal-team.css'),('commercial.css','internal-team-commercial.css'),('admission.css','internal-team-admission.css')]:
    approved=subprocess.check_output(['git','show',BASE+':prototypes/ase-vis01/'+old],cwd=ROOT);candidate=(ROOT/'assets/css'/new).read_bytes();assert approved==candidate
    assets.append({'approved':'prototypes/ase-vis01/'+old,'integrated':'assets/css/'+new,'bytes_identical':True,'sha256':hashlib.sha256(candidate).hexdigest()})
measure="""() => Object.fromEntries(['.advisor-workspace','.workspace-columns','.directory','.detail','h1','.panel'].map(selector=>{const e=document.querySelector(selector),s=getComputedStyle(e),r=e.getBoundingClientRect();return [selector,{width:Math.round(r.width*100)/100,font:s.fontFamily,fontSize:s.fontSize,color:s.color,background:s.backgroundColor,radius:s.borderRadius,gap:s.gap,columns:s.gridTemplateColumns,display:s.display}]}))"""
cases=[]
with sync_playwright() as p:
    for engine in ['chromium','webkit']:
        browser=getattr(p,engine).launch()
        for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
            ctx=browser.new_context(ignore_https_errors=True,viewport={'width':w,'height':h});ctx.add_cookies([{'name':'__Host-mxmed_session','value':token,'domain':'127.0.0.1','path':'/','secure':True,'httpOnly':True,'sameSite':'Strict'}])
            a=ctx.new_page();a.goto('http://127.0.0.1:8140/');a.wait_for_selector('#directory-content button');assert BASE in a.locator('#preview-commit').text_content()
            b=ctx.new_page();b.goto('https://127.0.0.1:8141/internal/media-review/index.php?section=equipo');b.wait_for_selector('[data-member]')
            original=a.evaluate(measure);integrated=b.evaluate(measure);differences=[]
            for sel,values in original.items():
                for key,value in values.items():
                    if integrated[sel][key]!=value:differences.append({'selector':sel,'property':key,'approved':value,'integrated':integrated[sel][key]})
            assert not differences, differences
            cases.append({'engine':engine,'viewport':f'{w}x{h}','shared_geometry_and_tokens':'PASS','differences':differences,'approved':original,'integrated':integrated});ctx.close()
        browser.close()
report={'result':'PASS','approved_visual_commit':BASE,'assets':assets,'cases':cases,'comparison':'Shared widths, grid columns/gap, fonts, colors, panel radius. Content/vertical heights deliberately differ; no claim of zero pixel difference.','required_differences':['Existing staff navigation and read-only badge replace prototype actor controls.','Bounded reference search/paging replaces fictitious personal-name search.','Neutral portraits and unavailable personal/contact/evidence/history replace fabricated data.','Real account/staff/grant states remain distinct from unknown identity review.','Master sees exact delegated scope only; hidden grants are unknown, not unassigned.','Admission/commercial commands disabled, no balances or codes; no fictional histories.'],'physical_safari':'NOT_RUN','mobile':'INTERIM_SMOKE_ONLY'}
(OUT/'visual-comparison.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps({'result':'PASS','cases':len(cases),'approved_css_identical':len(assets)}))
