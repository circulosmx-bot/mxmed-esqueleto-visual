import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const root = new URL('../../../', import.meta.url);
const read = path => readFileSync(new URL(path, root), 'utf8');
const markup = read('profiles/doctor.php');
const css = read('assets/css/public-profile.css');

assert.match(markup, /type="radio" name="patient_type" value="first_time"[^>]*required/);
assert.match(markup, /type="radio" name="patient_type" value="follow_up"/);
assert.equal((markup.match(/checked\s*\/?/g) || []).length, 0, 'patient type keeps its explicit no-default selection');
assert.match(css, /background:\s*rgba\(1, 175, 183, 0\.50\)/, 'unselected state uses background-only 50% turquoise');
assert.match(css, /\.mxpp-booking-patient-type__option:hover[^\{]*\{[\s\S]*rgba\(1, 175, 183, 0\.70\)/, 'hover strengthens only the turquoise background');
assert.match(css, /\.mxpp-booking-patient-type__input:checked \+ span[\s\S]*background:\s*#01AFB7[\s\S]*color:\s*#fff/, 'checked radio owns solid selected state');
assert.match(css, /\.mxpp-booking-patient-type__input:checked \+ span::before[\s\S]*opacity:\s*1/, 'checked radio reveals a white Material Symbols check');
assert.match(css, /width:\s*1\.15em[\s\S]*opacity:\s*0/, 'icon width is reserved before selection to prevent layout shift');

console.log('PublicBookingPatientTypeVisualContractTest PASS');
