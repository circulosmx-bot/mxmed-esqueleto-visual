import assert from 'node:assert/strict';
import fs from 'node:fs';

const html=fs.readFileSync('index.html','utf8');
const css=fs.readFileSync('assets/css/style.css','utf8');
const host=fs.readFileSync('assets/js/clinical/prescription-editor-host.js','utf8');
const app=fs.readFileSync('assets/js/app.js','utf8');

const workspace=html.slice(html.indexOf('<section class="rx-editor-workspace'),html.indexOf('<dialog class="rx-ux-dialog"'));
assert.match(workspace,/<header class="rx-editor-workspace-head">\s*<h2 id="rx-editor-title" tabindex="-1">NUEVA RECETA<\/h2>\s*<ol class="rx-editor-stepper"/);
assert.match(workspace,/<\/ol>\s*<button[^>]+data-rx-host-close/);
assert.equal((workspace.match(/data-rx-host-step=/g)||[]).length,5);
assert.doesNotMatch(workspace,/rx-editor-eyebrow|>RECETAS<|Captura las indicaciones, revisa el documento/);
assert.match(html,/ne-rx-stage-title visually-hidden[^>]+>Contexto clínico/);
assert.match(html,/ne-rx-section-title visually-hidden[^>]+>Medicamentos/);
assert.match(css,/\.rx-editor-workspace-head\{display:grid;grid-template-columns:auto minmax\(0,1fr\) auto/);
assert.match(css,/\.rx-editor-workspace-head \.rx-editor-stepper\{grid-column:1\/-1;grid-row:2\}/);
assert.match(css,/\.rx-editor-stepper li\.is-current/);
assert.match(host,/aria-current','step'/);
assert.doesNotMatch(app,/rx-editor-title[^\n]{0,100}textContent|textContent[^\n]{0,100}rx-editor-title/);

console.log('REC-VIS05 compact prescription header contract: PASS');
