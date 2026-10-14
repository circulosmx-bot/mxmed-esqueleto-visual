import assert from 'node:assert/strict';
import fs from 'node:fs';

const html=fs.readFileSync('private-app.php','utf8');
const app=fs.readFileSync('assets/js/app.js','utf8');
const experience=fs.readFileSync('assets/js/clinical-document-signature-experience.js','utf8');
const css=fs.readFileSync('assets/css/style.css','utf8');

const signatureBlock=html.match(/<div id="ne_rx_sign"[\s\S]*?<div id="ne_rx_final"/)?.[0]||'';
assert.match(signatureBlock,/class="mx-signature-pad"/);
assert.match(signatureBlock,/id="ne_rx_signature_canvas" class="mx-signature-pad__canvas"/);
const expected=['ne_rx_apply_signature','ne_rx_registered_signature','ne_rx_remote_signature','ne_rx_clear_signature'];
assert.deepEqual([...signatureBlock.matchAll(/id="(ne_rx_(?:apply|registered|remote|clear)_signature)"/g)].map(m=>m[1]),expected);
for(const copy of ['FIRMAR AQUÍ','USAR FIRMA REGISTRADA','FIRMAR EN OTRO DISPOSITIVO','LIMPIAR DIBUJO'])
  assert.match(signatureBlock,new RegExp(copy));
assert.equal((html.match(/id="modalDocumentSignatureQr"/g)||[]).length,1);
assert.doesNotMatch(html,/modalResponsivaSignatureQr|rm-qr-title/);
assert.match(app,/rxExperience\?\.decorate\(rxSignPanel \|\| document\)/);
assert.match(app,/rxExperience\.bindPointer\(rxCanvas/);
assert.match(app,/rxExperience\.observeResize\(rxCanvas,\{preserve:true/);
assert.match(app,/document\.getElementById\('modalDocumentSignatureQr'\)/);
assert.doesNotMatch(app,/modalResponsivaSignatureQr/);
assert.match(experience,/#ne_rx_remote_signature/);
assert.match(experience,/#ne_rx_apply_signature/);
assert.match(experience,/#ne_rx_registered_signature/);
assert.match(experience,/#ne_rx_clear_signature/);
assert.match(css,/\[data-signature-input="direct-touch"\] button\.mx-signature-direct/);
assert.match(css,/\[data-signature-input="desktop-pointer"\] button\.mx-signature-registered/);
assert.match(css,/\.mx-signature-actions \.btn\{flex:1 1 100%;width:100%\}/);
assert.match(app,/prescription-qr-sessions/);
assert.match(app,/role:'doctor'/);
assert.match(app,/rxFinalButton\.disabled = !rxSignature/);
console.log('DOC-REC-STD02 prescription SIGN-UX02 presentation contract: PASS');
