import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const root = new URL('../../../', import.meta.url);
const read = path => readFileSync(new URL(path, root), 'utf8');
const activeSurfaces = [
  ['public booking', read('profiles/doctor.php')],
  ['admin product', read('index.html')],
  ['static product mirror', read('docs/index.html')]
];
const legacyVisibleTerms = /apellido\s+(paterno|materno)/i;

for (const [name, source] of activeSurfaces) {
  assert.equal(legacyVisibleTerms.test(source), false, `${name} has no legacy visible surname label`);
}

const publicBooking = activeSurfaces[0][1];
assert.match(publicBooking, /<label>Primer apellido<input[^>]*name="last_name"[^>]*required/);
assert.match(publicBooking, /<label>Segundo apellido <span>\(opcional\)<\/span><input[^>]*name="second_last_name"/);
assert.match(publicBooking, /Completa nombre\(s\) y primer apellido\./);

const admin = activeSurfaces[1][1];
assert.match(admin, /id="dp-apellido-paterno" placeholder="Primer apellido"/);
assert.match(admin, /id="dp-apellido-materno" placeholder="Segundo apellido"/);
assert.match(admin, /data-pac-apellido-paterno placeholder="Primer apellido"/);
assert.match(admin, /data-pac-apellido-materno placeholder="Segundo apellido"/);

console.log('UserFacingSurnameTerminologyContractTest PASS');
