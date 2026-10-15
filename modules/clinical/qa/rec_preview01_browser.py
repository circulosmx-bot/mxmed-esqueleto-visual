#!/usr/bin/env python3
import asyncio,os,subprocess
from pathlib import Path
from playwright.async_api import async_playwright

ROOT=Path(__file__).resolve().parents[3]
OUT=Path(os.environ.get('REC_PREVIEW01_EVIDENCE','/tmp/rec-preview01'));OUT.mkdir(parents=True,exist_ok=True)
def check(ok,label):
    if not ok: raise RuntimeError('FAIL '+label)
    print(label+'=PASS',flush=True)
def render(mode): return subprocess.check_output(['php','modules/clinical/qa/rec_preview01_render_fixture.php',mode],cwd=ROOT,text=True)

async def main():
  async with async_playwright() as pw:
    browser=await pw.webkit.launch();page=await browser.new_page()
    for mode in ['logo_and_data','data_only','hidden']:
      for width,height in [(1440,900),(1366,768),(820,1180)]:
        await page.set_viewport_size({'width':width,'height':height});await page.set_content(render(mode),wait_until='domcontentloaded')
        await page.add_style_tag(path=str(ROOT/'assets/css/clinical-doc-base.css'))
        await page.add_script_tag(path=str(ROOT/'assets/vendor/qrcodejs/qrcode.min.js'))
        await page.add_script_tag(path=str(ROOT/'assets/js/clinical/prescription-document-presentation.js'))
        await page.wait_for_timeout(100)
        state=await page.evaluate("""()=>{const q=s=>document.querySelector(s),r=s=>q(s)?.getBoundingClientRect();return{
          overflow:document.documentElement.scrollWidth-innerWidth,head:!!q('.clinical-doc-head'),logo:!!q('.clinical-doc-head-logo-slot img'),
          doctor:document.body.innerText.includes('Dra. Leticia Muñoz Romo'),footer:!!q('.rx-doc-professional-footer'),
          office:document.body.innerText.includes('Consultorio Principal'),qr:!!q('[data-rx-public-profile-qr] canvas,[data-rx-public-profile-qr] img'),
          qrTarget:q('[data-rx-public-profile-qr]')?.dataset.rxPublicProfileQr||'',profileHref:q('.rx-doc-public-profile')?.href||'',
          caption:document.body.innerText.includes('AGENDA UNA CITA'),signature:!!q('.doc-base-signature'),patient:!!q('.rx-doc-patient-meta'),
          logoWidth:r('.clinical-doc-head-logo-slot img')?.width||0,logoHeight:r('.clinical-doc-head-logo-slot img')?.height||0,
          nameFamily:getComputedStyle(q('.rx-doc-physician-name')||document.body).fontFamily,
          patientWeight:getComputedStyle(q('.rx-doc-patient-name')).fontWeight};}""")
        check(state['overflow']<=1,f'REC_PREVIEW01_{mode}_{width}x{height}_NO_CLIP')
        check(state['patient'] and int(state['patientWeight'])>=600,f'REC_PREVIEW01_{mode}_{width}x{height}_PATIENT_HIERARCHY')
        if mode=='hidden':
          check(not any(state[k] for k in ['head','logo','doctor','footer','office','qr','caption','signature']),f'REC_PREVIEW01_HIDDEN_{width}x{height}_NO_IDENTITY')
        else:
          check(state['head'] and state['doctor'] and state['footer'] and state['office'] and state['qr'] and state['caption'] and state['signature'],f'REC_PREVIEW01_{mode}_{width}x{height}_COMPLETE')
          check(state['qrTarget']=='https://mxmed.example.test/profiles/doctor.php?doctor_id=1' and state['profileHref']==state['qrTarget'],f'REC_PREVIEW01_{mode}_{width}x{height}_QR_TARGET')
          check('Asap' in state['nameFamily'],f'REC_PREVIEW01_{mode}_{width}x{height}_PUBLIC_PROFILE_TYPE')
          if mode=='logo_and_data': check(state['logo'] and state['logoWidth']<=128 and state['logoHeight']<=64,f'REC_PREVIEW01_{width}x{height}_REDUCED_LOGO')
          else: check(not state['logo'],f'REC_PREVIEW01_{width}x{height}_DATA_ONLY_NO_LOGO')
        await page.screenshot(path=str(OUT/f'{mode}-{width}x{height}.png'),full_page=True)
    await browser.close()
  print('REC_PREVIEW01_BROWSER_WEBKIT=PASS')
asyncio.run(main())
