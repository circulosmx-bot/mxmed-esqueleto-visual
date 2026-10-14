import assert from 'node:assert/strict';
import fs from 'node:fs';

const html=fs.readFileSync('index.html','utf8');
const app=fs.readFileSync('assets/js/app.js','utf8');
const host=fs.readFileSync('assets/js/clinical/prescription-editor-host.js','utf8');
const globalEntry=fs.readFileSync('assets/js/clinical/rec-ux01.js','utf8');
const css=fs.readFileSync('assets/css/style.css','utf8');

assert.match(html,/data-rx-global-workspace-mount/);
assert.match(html,/data-rx-host-patient-context/);
assert.doesNotMatch(html,/id="ne_open_rx"[^>]+data-bs-target="#modalReceta"/);
assert.match(app,/caller:'global'/);
assert.match(app,/workspaceMount:quickRxPanel\.querySelector/);
assert.match(app,/caller=runtime\?\.encounter_key\?'consultation':'activity'/);
assert.match(html,/Vinculada a esta consulta/);
assert.match(app,/prescription-host-request-change-patient/);
assert.match(globalEntry,/mxmedSetQuickRxPatientSnapshot/);
assert.match(host,/workspaceMount\.appendChild\(workspace\)/);
assert.match(host,/workspaceAnchor\.after\(workspace\)/);
assert.match(css,/rx-clinical-prescription-active/);
assert.match(css,/@media\(max-width:850px\)[\s\S]+grid-template-columns:repeat\(5/);
assert.doesNotMatch(host,/fetch\(/);
console.log('REC-VIS02 host normalization contract: PASS');
