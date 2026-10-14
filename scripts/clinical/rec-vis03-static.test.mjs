import assert from 'node:assert/strict';
import fs from 'node:fs';

const html=fs.readFileSync('index.html','utf8');
const app=fs.readFileSync('assets/js/app.js','utf8');
const host=fs.readFileSync('assets/js/clinical/prescription-editor-host.js','utf8');
const css=fs.readFileSync('assets/css/style.css','utf8');
const legacyPlan=fs.readFileSync('assets/js/clinical/plan02b-next-steps.js','utf8');

assert.equal((html.match(/data-rx-host-step=/g)||[]).length,5);
assert.match(css,/--rx-primary:#008fa4/);
assert.match(css,/--rx-primary-action:#007f92/);
assert.match(css,/\.rx-editor-stepper::before/);
assert.match(css,/border-radius:9px/);
assert.match(css,/\.rx-editor-embedded-body \.ne-rx-field/);
assert.match(css,/:has\(\.ne-rx-row\[data-ne-rx-state="expanded"\]\) \.ne-rx-add-row/);
for(const label of ['Medicamento','Presentación','Dosis','Vía','Frecuencia','Duración','Cantidad','Indicaciones al paciente','Notas médicas privadas']) assert.match(app,new RegExp(`>${label}(?:<| )`));
assert.match(host,/Etapa \$\{stage\} de 5/);
assert.match(host,/Continuar →/);
assert.match(html,/Contexto clínico/);
assert.match(legacyPlan,/Preparar receta/);
assert.doesNotMatch(host,/fetch\(/);
console.log('REC-VIS03 consultation visual parity contract: PASS');
