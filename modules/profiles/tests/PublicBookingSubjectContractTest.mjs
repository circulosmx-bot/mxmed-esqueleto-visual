import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {execFileSync} from 'node:child_process';

const context = {window: {}};
vm.runInNewContext(fs.readFileSync(new URL('../../../assets/js/public-profile-booking-subject.js', import.meta.url), 'utf8'), context);
const subject = context.window.MxmedPublicBookingSubject;
const state = {doctorId: '1', selectedSlot: {consultorio_id: '2', start_at: '2026-09-07 16:00:00', end_at: '2026-09-07 16:30:00'}};
const patient = {full_name: 'Paciente Sintético', mobile_phone: '5550000011', email: 'patient@example.test', birth_date: '2000-01-01', gender: 'F', reason: ''};
const booker = {name: 'Persona Sintética', phone: '5550000022', email: 'booker@example.test', relationship: 'madre'};
assert.equal(subject.prepare(state, patient, booker).ok, false);
subject.choose(state, false);
for (const field of ['name', 'phone', 'email', 'relationship']) {
  assert.equal(subject.prepare(state, patient, {...booker, [field]: ''}).ok, false, field + ' required');
}
const other = subject.prepare(state, patient, booker).payload;
assert.equal(other.booker_is_patient, false);
assert.equal(other.booker.relationship, 'madre');
assert.notEqual(other.patient.email, other.booker.email);
state.preparedPayload = other;
subject.choose(state, true);
assert.equal(state.preparedPayload, null);
const self = subject.prepare(state, patient, booker).payload;
assert.equal(self.booker_is_patient, true);
assert.equal(self.booker.name, self.patient.name);
assert.equal(self.booker.email, self.patient.email);
assert.equal(Object.hasOwn(self.booker, 'relationship'), false);
assert.equal(JSON.stringify(self).includes(booker.email), false);
assert.equal(self.patient_type, 'first_time');
assert.equal(self.end_at, state.selectedSlot.end_at);
// Validate the exact prepared shapes against the authoritative pure validator.
// Bypass construction: no PDO connection, reservation, or OTP operation occurs.
const controller = new URL('../../agenda/controllers/PublicAppointmentsController.php', import.meta.url).pathname;
const php = `require $argv[1]; $r=new ReflectionClass(Agenda\\Controllers\\PublicAppointmentsController::class); $c=$r->newInstanceWithoutConstructor(); $m=$r->getMethod('validateReservePayload'); foreach(json_decode(stream_get_contents(STDIN),true) as $payload){ $v=$m->invoke($c,$payload); if($v['errors']){fwrite(STDERR,json_encode(array_keys($v['errors'])));exit(1);} } echo "Backend validator PASS (no DB/network)\\n";`;
process.stdout.write(execFileSync('php', ['-r', php, controller], {input: JSON.stringify([self, other]), encoding: 'utf8'}));
console.log('PublicBookingSubjectContractTest PASS: self/other/required relationship/serialization/stale cleanup');
