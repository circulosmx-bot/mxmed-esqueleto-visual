import assert from 'node:assert/strict';
import fs from 'node:fs';

const api = fs.readFileSync('api/clinical/index.php','utf8');
const repository = fs.readFileSync('api/_lib/clinical_encounter_integrity.php','utf8');
const header = fs.readFileSync('assets/js/clinical/vis02-patient-header.js','utf8');
const workspace = fs.readFileSync('assets/js/clinical/m7-workspace.js','utf8');
const app = fs.readFileSync('assets/js/app.js','utf8');

const routeStart = api.indexOf("($segments[3] ?? '') === 'active'");
const routeEnd = api.indexOf("count($segments) === 3", routeStart);
assert.ok(routeStart > 0 && routeEnd > routeStart);
const activeRoute = api.slice(routeStart, routeEnd);
assert.match(activeRoute, /activeSnapshot/);
assert.doesNotMatch(activeRoute, /clinical_m6_patient_route_uses_v1|clinical_encounter_open_fetch/);
assert.match(activeRoute, /MULTIPLE_OPEN_ENCOUNTERS/);
assert.match(activeRoute, /'open_count'=>0/);
assert.match(activeRoute, /'open_count'=>1/);
assert.match(repository, /'encounter'=>\$count === 1 \? \$rows\[0\] : null/);
assert.match(header, /CONTINUAR CONSULTA/);
assert.match(header, /Revisión requerida/);
assert.match(header, /9 pendientes clínicos|pendientes clínicos/);
assert.match(workspace, /MULTIPLE_OPEN_ENCOUNTERS/);
assert.match(app, /if\(snapshot\.encounter\) return snapshot\.encounter/);
assert.match(app, /return \(await read\(\)\)\.encounter \|\| result/);

console.log('CONS-CTA01 canonical CTA contract: PASS');
