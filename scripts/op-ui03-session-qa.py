#!/usr/bin/env python3
"""Real isolated Identity: CSRF refresh preserves reads; rotation discards stale reads."""
import asyncio,json,pathlib
from playwright.async_api import async_playwright
ROOT=pathlib.Path(__file__).resolve().parents[1]
SUPPORT=pathlib.Path.home()/'Library/Application Support/MXMED/opui03-rehearsal'
BASE='https://127.0.0.1:8446'
RESULTS={}
async def main():
 config=json.loads((SUPPORT/'environment.json').read_text());assert config['MXMED_OP_UI03_REHEARSAL']=='1' and config['MXMED_DB_NAME']=='mxmed_gate4d_preview_opui03'
 creds=json.loads((SUPPORT/'credentials.json').read_text())['owner']
 async with async_playwright() as p:
  browser=await p.chromium.launch();ctx=await browser.new_context(ignore_https_errors=True);page=await ctx.new_page()
  await page.goto(BASE+'/public/identity/acceso.php');await page.locator('[name=email]').fill(creds['email']);await page.locator('[name=password]').fill(creds['password']);await page.locator('[data-submit]').click();await page.wait_for_url('**/private-app.php*');await page.wait_for_function("window.mxmedPrivateSession?.state==='authenticated' && !!window.mxmedCanonicalOperators")
  await page.evaluate('window.mxmedCanonicalOperators.whenReady')
  stable=await page.evaluate("""async()=>{
   const get=async()=> (await fetch('/api/identity/index.php/current-session',{cache:'no-store'})).json();
   const a=await get(),b=await get();return {stable:a.application_access.context.request_session_marker===b.application_access.context.request_session_marker,new_nonce:a.csrf_token!==b.csrf_token};
  }""")
  assert stable['stable'] and stable['new_nonce'];RESULTS['S01_stable_session_new_csrf']='PASS'
  async def delay(route):
   response=await route.fetch();await asyncio.sleep(.8);await route.fulfill(response=response)
  await page.route('**/api/profile-operators/index.php/bootstrap',delay)
  result=await page.evaluate("""async()=>{
   const read=window.mxmedCanonicalOperators.request('/api/profile-operators/index.php/bootstrap');
   await new Promise(r=>setTimeout(r,100));const valid=await window.mxmedPrivateSession.validate('ui03-csrf-refresh');
   const response=await read;return {valid,status:response.status};
  }""")
  assert result['valid'] and result['status']==200,result;RESULTS['S02_csrf_refresh_keeps_inflight_read']='PASS'
  result=await page.evaluate("""async()=>{
   const old=window.mxmedPrivateSession.context.request_session_marker;
   const pending=window.mxmedCanonicalOperators.request('/api/profile-operators/index.php/bootstrap').then(r=>String(r.status)).catch(e=>e.name);
   await new Promise(r=>setTimeout(r,100));
   const rotation=await window.mxmedCanonicalOperators.request('/api/identity/index.php/session-rotate','POST',{csrf_token:window.mxmedPrivateSession.csrfToken});
   const valid=await window.mxmedPrivateSession.validate('ui03-session-rotated');
   return {rotation:rotation.status,valid,changed:old!==window.mxmedPrivateSession.context.request_session_marker,pending:await pending};
  }""")
  assert result['rotation']==200 and result['valid'] and result['changed'] and result['pending']=='AbortError',result
  RESULTS['S03_rotation_changes_marker_discards_old_read']='PASS'
  await page.unroute('**/api/profile-operators/index.php/bootstrap',delay)
  assert await page.evaluate("async()=> (await window.mxmedCanonicalOperators.request('/api/profile-operators/index.php/bootstrap')).status")==200
  RESULTS['S04_rotated_session_remains_valid']='PASS'
  await page.evaluate('window.mxmedPrivateSession.logout()');await page.wait_for_url('**/public/identity/acceso.php?state=logged-out')
  assert (await ctx.request.get(BASE+'/api/profile-operators/index.php/bootstrap')).status==401
  RESULTS['S05_logout_revocation']='PASS';await ctx.close();await browser.close()
 (SUPPORT/'evidence/op-ui03-session.json').write_text(json.dumps(RESULTS,indent=2));print(json.dumps(RESULTS,indent=2))
if __name__=='__main__':asyncio.run(main())
