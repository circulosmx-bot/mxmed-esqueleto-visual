#!/usr/bin/env python3
"""Open only the owned synthetic browser, or request its runner's graceful disposal."""
import argparse,pathlib,json,os,time,re
p=argparse.ArgumentParser();p.add_argument('owned');p.add_argument('--stop',action='store_true');a=p.parse_args()
root=pathlib.Path(a.owned).resolve()
assert re.fullmatch(r'/private/tmp/mxmed-ase-adm02imp01-[A-Za-z0-9_]+',str(root))
assert root.stat().st_uid==os.getuid() and root.stat().st_mode&0o777==0o700
info=json.loads((root/'review-info.json').read_text());approval=json.loads((root/'workflow-approval.json').read_text())
assert info['expiry_unix']>time.time() and approval['expires']>time.time()
assert pathlib.Path(approval['root']).resolve()==pathlib.Path(__file__).resolve().parents[1]
if a.stop:
    (root/'stop-review').touch(mode=0o600);print('Cierre solicitado al runner propietario.');raise SystemExit
from playwright.sync_api import sync_playwright
origin=info['url'].split('/internal/')[0];tokens=json.loads((root/'sessions.json').read_text())
with sync_playwright() as pw:
    browser=pw.chromium.launch(headless=False)
    ctx=browser.new_context(ignore_https_errors=True,viewport={'width':1366,'height':900})
    ctx.add_cookies([{'name':'__Host-mxmed_session','value':tokens['director'],'url':origin,'secure':True,'httpOnly':True,'sameSite':'Strict'}])
    page=ctx.new_page();page.goto(info['url'])
    while browser.is_connected() and time.time()<info['expiry_unix'] and not (root/'stop-review').exists():
        try:page.wait_for_timeout(1000)
        except Exception:break
    if browser.is_connected():browser.close()
