"""Read-only clean Director visual and keyboard-focus check."""
from playwright.sync_api import sync_playwright,expect
from pathlib import Path
import json,os
out=Path(os.environ['VIS32_ARTIFACTS']);out.mkdir(parents=True,exist_ok=True)
with sync_playwright() as p:
 b=p.chromium.launch();page=b.new_page();errors=[];page.on('pageerror',lambda e:errors.append(e.stack))
 page.goto('http://127.0.0.1:18143/index.html?review_patient=plan02ux&qa_tools=hide',wait_until='commit')
 expect(page.locator('[data-vis02-action="consulta"]')).to_have_text('VOLVER A CONSULTA',timeout=55000)
 for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
  page.set_viewport_size({'width':w,'height':h});page.wait_for_timeout(800);page.evaluate('scrollTo(0,0)')
  expect(page.locator('#t-resumen-longitudinal')).to_be_visible()
  page.screenshot(path=str(out/f'expediente-{w}.png'))
  page.locator('[data-vis02-action="consulta"]').click();expect(page.locator('[data-m7-body]')).to_have_attribute('data-encounter-id','1016')
  page.wait_for_function("document.body.classList.contains('mx-consultation-mode')");page.wait_for_timeout(400)
  assert page.locator('.ne-rx-ch-patient-name').first.bounding_box()['y']>=0
  for step in ['reason','plan','documents']:
   page.locator('[data-m7-section="'+step+'"]').click();page.wait_for_timeout(400);page.evaluate('scrollTo(0,0)')
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
   page.screenshot(path=str(out/f'dedicated-{step}-{w}.png'))
  page.locator('[data-m7-exit]').click();page.wait_for_function("!document.body.classList.contains('mx-consultation-mode')")
  page.wait_for_timeout(400)
  assert page.evaluate("document.activeElement.matches('#p-expediente [data-exp-tabs] .nav-link.active')")
  print('PASS final visuals/focus',w,flush=True)
 assert not errors
 (out/'final-visual-results.json').write_text(json.dumps({'viewports':[1440,1366,820,390],'identity_on_entry':'PASS','exit_focus':'PASS','overflow':'NONE','browser_errors':errors},indent=2))
 b.close()
