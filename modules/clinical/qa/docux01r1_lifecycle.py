"""Focused network-error and hidden-view lifecycle QA. Synthetic Director only."""
import json, os, subprocess, time
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
import pymysql
BASE='http://127.0.0.1:18143';DB='mxmed_director_review_lon07c'
OUT=Path(os.environ['DOCUX01R1_ARTIFACTS']);OUT.mkdir(parents=True,exist_ok=True)
assert 'MXMED_DB_NAME='+DB in subprocess.check_output(['ps','eww','-p','4024'],text=True)
conn=pymysql.connect(unix_socket='/tmp/mysql.sock',user='root',database=DB,autocommit=True)
TOKENS=[];REPORT={}
def state(t):
 with conn.cursor() as c:c.execute('SELECT status FROM clinical_note_capture_tokens WHERE token=%s',(t,));return c.fetchone()[0]
with sync_playwright() as p:
 b=p.chromium.launch(headless=True);ctx=b.new_context();page=ctx.new_page();polls=[]
 page.on('request',lambda r:polls.append(time.monotonic()) if r.method=='GET' and '/note-capture-tokens/' in r.url else None)
 try:
  page.goto(BASE+'/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide',wait_until='commit')
  page.wait_for_function('document.querySelector("#m7-workspace [data-m7-body]")?.dataset.encounterId==="1016"',timeout=55000)
  page.locator('[data-m7-section="documents"]').click()
  def fail(route):route.fulfill(status=500,content_type='application/json',body='{"ok":false,"message":"RAW ERROR"}')
  page.route('**/note-capture-tokens',fail);page.locator('[data-m7-capture-start]').click()
  expect(page.locator('[data-m7-capture-state]')).to_contain_text('No se pudo confirmar el inicio')
  expect(page.locator('[data-docux-capture-close]')).to_be_visible();assert 'RAW ERROR' not in page.locator('[data-docux-capture]').inner_text()
  page.locator('[data-docux-capture-close]').click();expect(page.locator('[data-docux-capture]')).not_to_be_visible();page.unroute('**/note-capture-tokens',fail);REPORT['issuance failure truthful close']='PASS'
  with page.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/note-capture-tokens')) as res:page.locator('[data-m7-capture-start]').click()
  token=res.value.json()['data']['token'];TOKENS.append(token);expect(page.locator('[data-docux-qr-content]')).to_be_visible()
  page.route('**/note-capture-tokens/*/cancel',fail);page.locator('[data-docux-capture] [data-modal-close]').click()
  expect(page.locator('[data-m7-capture-state]')).to_contain_text('No se pudo cancelar')
  expect(page.locator('[data-docux-capture]')).to_be_visible();expect(page.locator('[data-docux-qr-content]')).not_to_be_visible();assert state(token)=='pending'
  page.unroute('**/note-capture-tokens/*/cancel',fail);page.locator('[data-m7-capture-cancel]').click();expect(page.locator('[data-m7-capture-state]')).to_have_text('Captura cancelada');assert state(token)=='cancelled';page.locator('[data-docux-capture-close]').click();REPORT['failed cancel stays visible then retry']='PASS'
  with page.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/note-capture-tokens')) as res:page.locator('[data-m7-capture-start]').click()
  token=res.value.json()['data']['token'];TOKENS.append(token);expect(page.locator('[data-docux-qr-content]')).to_be_visible()
  page.evaluate("Object.defineProperty(document,'hidden',{configurable:true,value:true});document.dispatchEvent(new Event('visibilitychange'))")
  n=len(polls);page.wait_for_timeout(2800);assert len(polls)==n
  page.evaluate("delete document.hidden;document.dispatchEvent(new Event('visibilitychange'))")
  page.wait_for_timeout(2800);assert len(polls)>n;REPORT['visibility stops and resumes bounded polling']='PASS'
  page.evaluate("document.querySelector('#p-expediente').classList.add('d-none')")
  expect(page.locator('[data-docux-capture]')).not_to_be_visible()
  for _ in range(100):
   if state(token)!='pending':break
   page.wait_for_timeout(50)
  assert state(token)=='cancelled';REPORT['application context loss cancels']='PASS'
 finally:
  client=p.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=director-lon07c-review'})
  for token in TOKENS:client.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+token+'/cancel',data={})
  client.dispose();b.close()
  log=Path('/Users/circulodigital/.codex/artifacts/director-expediente-review/launch-error.log');text=log.read_text()
  for token in TOKENS:text=text.replace(token,'[DOCUX01_REDACTED]'.ljust(len(token),'_'))
  log.write_text(text);conn.close();(OUT/'lifecycle-results.json').write_text(json.dumps(REPORT,indent=2)+'\n')
print('PASS lifecycle: issuance failure, cancellation retry, visibility, application loss')
