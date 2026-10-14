#!/usr/bin/env python3
from pathlib import Path
import asyncio
import os
import subprocess
from playwright.async_api import async_playwright

ROOT = Path(__file__).resolve().parents[3]
FIXTURE = ROOT / "modules/clinical/qa/doc_rec_std03a_render_fixture.php"
CSS = ROOT / "assets/css/clinical-doc-base.css"
EVIDENCE = Path(os.environ.get("STD03A_EVIDENCE_DIR", "/tmp/doc-rec-std03a-browser"))
CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"

def check(condition: bool, label: str) -> None:
    if not condition:
        raise RuntimeError("FAIL " + label)
    print("PASS " + label)

def rendered(long: bool = False) -> str:
    command = ["php", str(FIXTURE)] + (["long"] if long else [])
    return subprocess.check_output(command, cwd=ROOT, text=True)

async def inspect(page, width: int, height: int) -> None:
    await page.set_viewport_size({"width": width, "height": height})
    await page.set_content(rendered(), wait_until="load")
    await page.add_style_tag(path=str(CSS))
    await page.screenshot(path=str(EVIDENCE / f"footer-{width}x{height}.png"), full_page=True)
    geometry = await page.evaluate("""() => {
      const sheet=document.querySelector('.rx-doc-sheet');
      const signature=document.querySelector('.doc-base-signature');
      const footer=document.querySelector('.rx-doc-professional-footer');
      const locations=document.querySelector('.rx-doc-professional-locations');
      const sr=sheet.getBoundingClientRect(), gr=signature.getBoundingClientRect(), fr=footer.getBoundingClientRect(), lr=locations.getBoundingClientRect();
      return {overflow:sheet.scrollWidth-sheet.clientWidth, signatureBottom:gr.bottom, footerTop:fr.top,
        footerBottom:fr.bottom, sheetBottom:sr.bottom, locationRight:lr.right, sheetRight:sr.right,
        rows:document.querySelectorAll('.rx-doc-professional-location').length,
        qr:document.querySelectorAll('img[src*="qr" i],canvas[data-qr],.qr-code').length,
        text:footer.innerText};
    }""")
    check(geometry["overflow"] <= 1, f"{width}x{height} has no horizontal document clipping")
    check(geometry["signatureBottom"] <= geometry["footerTop"] + 1, f"{width}x{height} footer does not overlap signature")
    check(geometry["footerBottom"] <= geometry["sheetBottom"] + 1, f"{width}x{height} footer stays inside rendered sheet")
    check(geometry["rows"] == 3, f"{width}x{height} renders three locations")
    check(geometry["qr"] == 0 and "Perfil público" not in geometry["text"] and "AGENDA UNA CITA" not in geometry["text"],
          f"{width}x{height} emits no inactive QR or caption")
    check("Avenida de la Atención Médica Integral 1234" in geometry["text"] and "C.P. 20999" in geometry["text"],
          f"{width}x{height} preserves wrapped full address")
    if width > 900:
        check(geometry["locationRight"] < geometry["sheetRight"] - 100, f"{width}x{height} reserves lower-right layout capacity")

async def main() -> None:
    EVIDENCE.mkdir(parents=True, exist_ok=True)
    async with async_playwright() as tools:
        browser = await tools.chromium.launch(executable_path=CHROME, headless=True)
        page = await browser.new_page()
        for width, height in [(1440, 900), (1366, 768), (820, 1180)]:
            await inspect(page, width, height)
        await page.set_viewport_size({"width": 1440, "height": 900})
        await page.set_content(rendered(True), wait_until="load")
        await page.add_style_tag(path=str(CSS))
        long_geometry = await page.evaluate("""() => {
          const signature=document.querySelector('.doc-base-signature').getBoundingClientRect();
          const footer=document.querySelector('.rx-doc-professional-footer').getBoundingClientRect();
          return {signatureBottom:signature.bottom,footerTop:footer.top,rows:document.querySelectorAll('.rx-doc-item').length};
        }""")
        check(long_geometry["rows"] == 24 and long_geometry["signatureBottom"] <= long_geometry["footerTop"] + 1,
              "long multi-page content keeps footer after clinical body and signature")
        await page.pdf(path=str(EVIDENCE / "footer-long-letter.pdf"), format="Letter", print_background=True,
                       prefer_css_page_size=True)
        await browser.close()
    check((EVIDENCE / "footer-long-letter.pdf").stat().st_size > 10000, "browser print produced a readable Letter PDF")
    print("DOC_REC_STD03A_BROWSER=PASS")

asyncio.run(main())
