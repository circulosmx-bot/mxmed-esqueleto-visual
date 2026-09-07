import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const context = {window: {}};
vm.runInNewContext(fs.readFileSync(new URL('../../../assets/js/public-profile-birth-date.js', import.meta.url), 'utf8'), context);
const birthDate = context.window.MxmedPublicBirthDate;
const today = new Date(2026, 8, 6);

assert.deepEqual([...birthDate.months], ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']);
assert.equal(birthDate.compose({day: '5', month: '9', year: '1962'}, today).value, '1962-09-05');
assert.equal(birthDate.compose({day: '1', month: '1', year: '1940'}, today).value, '1940-01-01', 'older patients can enter a direct year');
assert.equal(birthDate.compose({day: '29', month: '2', year: '2000'}, today).value, '2000-02-29');
for (const parts of [
  {day: '29', month: '2', year: '2001'},
  {day: '31', month: '2', year: '2000'},
  {day: '31', month: '4', year: '2000'}
]) assert.equal(birthDate.compose(parts, today).ok, false, JSON.stringify(parts) + ' is impossible');
const future = birthDate.compose({day: '7', month: '9', year: '2026'}, today);
assert.equal(future.ok, false);
assert.match(future.message, /futuro/);
assert.deepEqual({...birthDate.decompose('1940-02-29')}, {day: '29', month: '2', year: '1940'});
assert.deepEqual({...birthDate.decompose('invalid')}, {day: '', month: '', year: ''});
console.log('PublicBirthDateSelectorContractTest PASS: Spanish months, canonical composition, prefill, impossible dates, future dates, and older-patient entry');
