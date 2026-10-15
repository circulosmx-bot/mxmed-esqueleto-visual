from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[3]
SCRIPT = ROOT / "assets/js/perfil/consultorio/administrative-feedback.js"
MESSAGE = "No tienes permisos para modificar este consultorio. Esta acción requiere acceso de propietario o administrador."

with sync_playwright() as p:
    for engine_name, engine in (("CHROMIUM", p.chromium), ("WEBKIT", p.webkit)):
        browser = engine.launch(headless=True)
        for width, height in ((1440, 900), (1366, 768), (820, 1180)):
            page = browser.new_page(viewport={"width": width, "height": height})
            page.set_content('<main><input id="draft" value="sin guardar"><div class="alert d-none" data-cons-admin-feedback role="alert" aria-live="assertive"></div></main>')
            page.add_script_tag(path=str(SCRIPT))
            page.evaluate("window.dispatchEvent(new CustomEvent('mxmed:authorization-denied',{detail:{input:'/api/agenda/index.php/consultorios'}}))")
            alert = page.locator("[data-cons-admin-feedback]")
            assert alert.is_visible() and alert.inner_text() == MESSAGE
            assert page.locator("#draft").input_value() == "sin guardar"
            assert page.evaluate("document.documentElement.scrollWidth <= document.documentElement.clientWidth")
            print(f"QA_{engine_name}_{width}_{height}=PASS")
            page.evaluate("window.mxmedConsultorioAuthorizationFeedback.clear()")
            page.evaluate("window.dispatchEvent(new CustomEvent('mxmed:authorization-denied',{detail:{input:'/api/agenda/index.php/consultorios/emergency-contacts'}}))")
            assert alert.is_visible() and alert.inner_text() == MESSAGE
            page.close()
        browser.close()
print("GOV03A_33_CONSULTORIO_403_VISIBLE=PASS")
print("GOV03A_34_EMERGENCY_403_VISIBLE=PASS")
