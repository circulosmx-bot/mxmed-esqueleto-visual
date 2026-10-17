#!/usr/bin/env python3
"""Read-only renderer/preview/print acceptance, without clinical runtime or records.

Original stage-3 markup, renderer and styles; intercepted synthetic browser origin.
No server, credentials, session, database, signing or issuance is composed.
"""
import argparse
import hashlib
import json
import mimetypes
import os
from pathlib import Path
import re
import subprocess
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[1]
ORIGIN = 'https://ux-align01-prescription.example.invalid'
BASELINE = 'e927dcfa86e3bf1fb7cf4e972b4155051d2e51c0'
PROTECTED = ('api/_lib/clinical_prescription_v2.php', 'api/_lib/clinical_prescription_binding.php',
    'api/_lib/clinical_prescription_qr.php', 'api/_lib/clinical_prescription_lifecycle.php',
    'api/_lib/clinical_professional_authority.php', 'api/_lib/clinical_professional_presentation.php',
    'modules/clinical/ui/viewer.php', 'assets/css/clinical-doc-base.css', 'assets/css/style.css',
    'assets/js/app.js', 'assets/js/clinical/prescription-signature-binding.js',
    'assets/js/clinical/prescription-document-presentation.js')
SIZES = ((1440, 900), (1366, 768), (820, 1180))


def manifest():
    return {name: hashlib.sha256((ROOT / name).read_bytes()).hexdigest() for name in PROTECTED}


def pdf_inspect(path):
    """PDFKit reads our generated synthetic PDF without launching a UI/application."""
    script = """ObjC.import('PDFKit');
function run(argv) {
  const document=$.PDFDocument.alloc.initWithURL($.NSURL.fileURLWithPath(argv[0]));
  if (!document) throw Error('synthetic_pdf_unreadable');
  return JSON.stringify({pages:Number(document.pageCount),text:ObjC.unwrap(document.string)});
}"""
    return json.loads(subprocess.check_output(['/usr/bin/osascript', '-l', 'JavaScript', '-e', script, str(path)], text=True))


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--evidence', type=Path, default=Path.home() /
        'Library/Application Support/MXMED/ux-align01/20261016-review/prescription')
    args = parser.parse_args()
    os.umask(0o077)
    args.evidence.mkdir(parents=True, exist_ok=True)
    pinned = manifest()
    for name in PROTECTED:
        before = subprocess.check_output(['git', 'show', BASELINE + ':' + name], cwd=ROOT)
        assert hashlib.sha256(before).hexdigest() == pinned[name], name + ' changed since preflight baseline'
    fixture = json.loads(subprocess.check_output(['php', str(ROOT /
        'modules/clinical/qa/ux_align01_presentation_fixture.php')], cwd=ROOT, text=True))
    assert fixture['result'] == 'PASS'
    cases = fixture['cases']
    app = (ROOT / 'private-app.php').read_text()
    stage = re.search(r'<div id="ne_rx_review" class="d-none">([\s\S]*?)<div id="ne_rx_sign"', app)
    assert stage, 'Original stage3 markup not found'
    stage = '<div id="ne_rx_review">' + stage.group(1)
    bootstrap = re.search(r'<link href="https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.3/dist/css/bootstrap\.min\.css"[^>]*>', app).group(0)
    fonts = ''.join(re.findall(r'<link[^>]*href="https://fonts\.googleapis\.com/[^>]+>', app))
    results, pdfs = [], []
    with sync_playwright() as p:
        for engine in ('chromium', 'webkit'):
            browser = getattr(p, engine).launch()
            for width, height in SIZES:
                context = browser.new_context(viewport={'width': width, 'height': height})
                page = context.new_page()
                errors, unexpected = [], []
                page.on('pageerror', lambda e: errors.append(str(e)))
                selected = {'case': 'data_only', 'view': 'preview'}

                def markup():
                    rendered = cases[selected['case']]['html']
                    if selected['view'] == 'preview':
                        # Exact original preview controls; clinical behavior remains outside
                        # this renderer acceptance. Nothing is sent/saved/signed.
                        content = stage.replace('<div id="ne_rx_preview_html" class="doc-base-sheet-frame"></div>',
                            '<div id="ne_rx_preview_html" class="doc-base-sheet-frame">' + rendered + '</div>')
                        content = '<main class="rx-editor-embedded-body">' + content + '</main>'
                    else:
                        # The existing viewer wraps the already frozen HTML in this frame.
                        content = '<div class="doc-base-sheet-frame prescription-v2-viewer-snapshot mb-3">' + rendered + '</div>'
                    return ('<!doctype html><html lang="es"><head><meta charset="utf-8">'
                        '<meta name="viewport" content="width=device-width,initial-scale=1">' + bootstrap + fonts +
                        ('<link rel="stylesheet" href="/assets/css/style.css">' if selected['view'] == 'preview' else '') +
                        '<link rel="stylesheet" href="/assets/css/clinical-doc-base.css"></head><body>' + content + '</body></html>')

                def route_request(route):
                    request = route.request
                    parsed = urlsplit(request.url)
                    if request.method != 'GET':
                        unexpected.append({'method': request.method, 'path': parsed.path}); route.abort(); return
                    if request.url == 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' or parsed.netloc in ('fonts.googleapis.com', 'fonts.gstatic.com'):
                        route.continue_(); return
                    if parsed.netloc == urlsplit(ORIGIN).netloc:
                        if parsed.path == '/original-component-fixture':
                            route.fulfill(status=200, content_type='text/html', body=markup()); return
                        if parsed.path.startswith('/assets/'):
                            asset = (ROOT / parsed.path.lstrip('/')).resolve()
                            if asset.is_relative_to(ROOT / 'assets') and asset.is_file():
                                route.fulfill(status=200, content_type=mimetypes.guess_type(asset)[0] or 'application/octet-stream', body=asset.read_bytes()); return
                    unexpected.append({'method': request.method, 'path': parsed.path})
                    route.abort()

                context.route('**/*', route_request)
                for name, case in cases.items():
                    selected['case'], selected['view'] = name, 'preview'
                    page.emulate_media(media='screen')
                    page.goto(ORIGIN + '/original-component-fixture', wait_until='load')
                    page.evaluate('document.fonts.ready')
                    # Align original selector to the native case, without invoking any writer.
                    mode = name if name in ('hidden', 'logo_and_data') else 'data_only'
                    page.locator('input[name=ne_rx_professional_header][value=' + mode + ']').check(force=True)
                    expect(page.locator('.rx-doc-item')).to_have_count(case['medication_count'])
                    expect(page.locator('.doc-base-title')).to_have_text('RECETA MÉDICA')
                    expect(page.locator('.rx-doc-patient-name')).to_contain_text('Paciente sintético UX-ALIGN01')
                    expect(page.locator('.rx-doc-patient-meta')).to_contain_text('42')
                    expect(page.locator('.rx-doc-patient-meta')).to_contain_text('2026-10-16')
                    if case['header_expected']:
                        expect(page.locator('.rx-doc-physician-name')).to_have_text('Dra. Profesional Sintética')
                        assert page.locator('.clinical-doc-head').count() == 1
                    else:
                        assert not page.locator('.clinical-doc-head,.doc-base-signature,.rx-doc-professional-footer').count()
                        assert 'Dra. Profesional Sintética' not in page.locator('.rx-doc-sheet').inner_text()
                    if name == 'missing_optional':
                        assert not page.locator('.rx-doc-physician-professional,.rx-doc-physician-license,.rx-doc-professional-footer').count()
                        assert 'Contexto clínico' not in page.locator('.rx-doc-sheet').inner_text()
                    elif case['header_expected']:
                        expect(page.locator('.rx-doc-sheet')).to_contain_text('QA-CEDULA-NO-VALIDA')
                        expect(page.locator('.rx-doc-sheet')).to_contain_text('Especialidad sintética')
                    expect(page.locator('.rx-doc-item').first).to_contain_text('No constituye una indicación médica.')
                    for field in ('Presentación:', 'Dosis:', 'Vía:', 'Frecuencia:', 'Duración:', 'Cantidad:', 'Indicaciones:'):
                        expect(page.locator('.rx-doc-item').first).to_contain_text(field)
                    assert bool(page.locator('.rx-doc-professional-footer').count()) == case['footer_expected']
                    if case['footer_expected']:
                        expect(page.locator('.rx-doc-professional-footer')).to_contain_text('TEL-SINTETICO-01')
                        expect(page.locator('.rx-doc-professional-footer')).to_contain_text('Domicilio sintético extenso')
                    assert not page.locator('.rx-doc-sheet img,.rx-doc-public-profile').count(), 'Absent approved media stays absent'
                    assert all(s not in page.locator('.rx-doc-sheet').inner_text() for s in ('SENTINEL', 'binding', 'issuer', 'role_code'))
                    geometry = page.evaluate("""() => {
                      const sheet=document.querySelector('.rx-doc-sheet'), s=sheet.getBoundingClientRect();
                      const items=[...sheet.querySelectorAll('.rx-doc-item')];
                      const signature=sheet.querySelector('.doc-base-signature')?.getBoundingClientRect();
                      const footer=sheet.querySelector('.rx-doc-professional-footer')?.getBoundingClientRect();
                      return {overflow:document.documentElement.scrollWidth-innerWidth,
                        sheetOverflow:sheet.scrollWidth-sheet.clientWidth,
                        itemContained:items.every(e=>{const r=e.getBoundingClientRect();return r.right<=s.right+1&&r.left>=s.left-1&&r.height>30}),
                        minInstructionFont:Math.min(...[...sheet.querySelectorAll('.rx-doc-item-meta')].map(e=>parseFloat(getComputedStyle(e).fontSize))),
                        footerAfterSignature:!footer||!signature||footer.top>=signature.bottom-1,
                        professionalSize:sheet.querySelector('.rx-doc-physician-name')?parseFloat(getComputedStyle(sheet.querySelector('.rx-doc-physician-name')).fontSize):null,
                        licenseSize:sheet.querySelector('.rx-doc-physician-license')?parseFloat(getComputedStyle(sheet.querySelector('.rx-doc-physician-license')).fontSize):null};
                    }""")
                    assert geometry['overflow'] <= 2 and geometry['sheetOverflow'] <= 1 and geometry['itemContained'] and geometry['footerAfterSignature'], (engine, width, name, geometry)
                    assert geometry['minInstructionFont'] >= 12
                    if geometry['licenseSize'] is not None:
                        assert geometry['professionalSize'] > geometry['licenseSize']
                    if name == 'data_only':
                        page.screenshot(path=str(args.evidence / f'{engine}-{width}x{height}-original-preview.png'), full_page=True)
                    results.append({'engine': engine, 'viewport': [width, height], 'case': name, 'screen': 'PASS', 'geometry': geometry})

                    # Read-only frozen viewer sheet + exact current print media on both engines.
                    selected['view'] = 'snapshot'
                    page.goto(ORIGIN + '/original-component-fixture', wait_until='load')
                    page.evaluate('document.fonts.ready')
                    page.emulate_media(media='print')
                    print_geometry = page.evaluate("""() => {
                      const s=document.querySelector('.rx-doc-sheet'), css=getComputedStyle(s);
                      const footer=s.querySelector('.rx-doc-professional-footer')?.getBoundingClientRect();
                      const signature=s.querySelector('.doc-base-signature')?.getBoundingClientRect();
                      return {width:s.getBoundingClientRect().width,shadow:css.boxShadow,
                        items:s.querySelectorAll('.rx-doc-item').length,
                        breakInside:getComputedStyle(s.querySelector('.rx-doc-item')).breakInside,
                        footerAfterSignature:!footer||!signature||footer.top>=signature.bottom-1,
                        text:s.innerText};
                    }""")
                    assert abs(print_geometry['width'] - 816) <= 1 and print_geometry['shadow'] == 'none', print_geometry
                    assert print_geometry['items'] == case['medication_count'] and print_geometry['footerAfterSignature']
                    assert print_geometry['breakInside'] == 'avoid'
                    assert 'Paciente sintético UX-ALIGN01' in print_geometry.pop('text')
                    results[-1]['print_media'] = 'PASS'
                    results[-1]['print_geometry'] = print_geometry
                    if name == 'data_only':
                        page.screenshot(path=str(args.evidence / f'{engine}-{width}x{height}-print-media.png'), full_page=True)
                    if engine == 'chromium' and width == 1440 and name in ('data_only', 'long'):
                        path = args.evidence / f'{name}-letter.pdf'
                        page.pdf(path=str(path), format='Letter', print_background=True, prefer_css_page_size=True)
                        facts = pdf_inspect(path)
                        assert path.stat().st_size > 10000 and facts['pages'] >= 1
                        assert 'Paciente sintético UX-ALIGN01' in facts['text'] and 'QA-CEDULA-NO-VALIDA' in facts['text']
                        if name == 'long':
                            assert facts['pages'] > 1
                            assert all('Medicamento sintético ' + str(i).zfill(2) in facts['text'] for i in range(1,25))
                        else:
                            assert all('Medicamento sintético ' + i in facts['text'] for i in ('A','B'))
                        assert 'TEL-SINTETICO-01' in facts['text'] and 'SENTINEL' not in facts['text']
                        pdfs.append({'file': path.name, 'pages': facts['pages'], 'bytes': path.stat().st_size,
                            'all_medications_text_readable': True, 'pdfkit_reader': 'PASS'})
                assert not errors and not unexpected, (errors, unexpected)
                context.close()
                print(f'UX_ALIGN01_PRESCRIPTION_{engine}_{width}x{height}=PASS', flush=True)
            browser.close()
    assert manifest() == pinned, 'Protected source changed during acceptance'
    output = {'result': 'PASS', 'scope': 'ORIGINAL_NATIVE_RENDERER_PREVIEW_COMPONENT_PRINT_MEDIA_ONLY',
        'baseline': BASELINE, 'source_manifest': pinned, 'source_byte_identical_to_baseline': True,
        'cases': results, 'pdfs': pdfs, 'webkit_pdf_export': 'UNSUPPORTED_NOT_CLAIMED',
        'normal_local_accessed': False, 'records_written': 0, 'signatures_applied': 0,
        'native_fixture': {name: {k:v for k,v in case.items() if k != 'html'} for name,case in cases.items()}}
    (args.evidence / 'result.json').write_text(json.dumps(output, indent=2, ensure_ascii=False)+'\n')
    print('UX_ALIGN01_PRESCRIPTION_CASES=' + str(len(results)))
    print('UX_ALIGN01_PRESCRIPTION=PASS_RENDERER_PREVIEW_PRINT_COMPONENTS_ONLY')


if __name__ == '__main__':
    main()
