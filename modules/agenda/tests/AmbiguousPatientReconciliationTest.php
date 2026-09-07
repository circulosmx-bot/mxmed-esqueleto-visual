<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/AmbiguousPatientReconciliationService.php';
require_once __DIR__ . '/../controllers/AmbiguousPatientReconciliationController.php';

use Agenda\Controllers\AmbiguousPatientReconciliationController;
use Agenda\Services\AmbiguousPatientReconciliationService;
use Agenda\Services\ReconciliationException;

function pdb08dAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function pdb08dDatabase(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE agenda_appointments (appointment_id TEXT PRIMARY KEY, doctor_id TEXT, consultorio_id TEXT, patient_id TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE agenda_public_appointment_flows (flow_id INTEGER PRIMARY KEY AUTOINCREMENT, appointment_id TEXT UNIQUE, payload_json TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE agenda_appointment_events (event_id TEXT PRIMARY KEY, appointment_id TEXT, event_type TEXT, timestamp TEXT, actor_role TEXT, actor_id TEXT, channel_origin TEXT, notes TEXT)');
    $pdo->exec('CREATE TABLE patients_patients (patient_id TEXT PRIMARY KEY, display_name TEXT, birthdate TEXT, sex TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE patients_contacts (contact_id TEXT PRIMARY KEY, patient_id TEXT, phone TEXT, email TEXT)');
    $pdo->exec('CREATE TABLE patients_doctor_links (link_id TEXT PRIMARY KEY, doctor_id TEXT, patient_id TEXT, status TEXT, ended_at TEXT)');
    return $pdo;
}

function pdb08dPatient(PDO $pdo, string $id, string $doctorId, string $name = 'Elena Mora', string $birthdate = '1988-08-08', string $phone = '4490000008', string $email = 'elena@example.test'): void
{
    $pdo->prepare('INSERT INTO patients_patients (patient_id, display_name, birthdate, sex, status) VALUES (?, ?, ?, "F", "active")')->execute([$id, $name, $birthdate]);
    $pdo->prepare('INSERT INTO patients_contacts (contact_id, patient_id, phone, email) VALUES (?, ?, ?, ?)')->execute(['c_' . $id, $id, $phone, $email]);
    $pdo->prepare('INSERT INTO patients_doctor_links (link_id, doctor_id, patient_id, status, ended_at) VALUES (?, ?, ?, "active", NULL)')->execute(['l_' . $id, $doctorId, $id]);
}

function pdb08dSeedCase(PDO $pdo, string $appointmentId = 'apt_ambiguous'): void
{
    pdb08dPatient($pdo, 'p_new', 'doctor_a');
    pdb08dPatient($pdo, 'p_candidate_a', 'doctor_a');
    pdb08dPatient($pdo, 'p_candidate_b', 'doctor_a');
    pdb08dPatient($pdo, 'p_cross_doctor', 'doctor_b');
    $pdo->prepare('INSERT INTO agenda_appointments (appointment_id, doctor_id, consultorio_id, patient_id, status) VALUES (?, "doctor_a", "consultorio_a", "p_new", "confirmed")')->execute([$appointmentId]);
    $payload = [
        'patient_type' => 'follow_up',
        'patient' => ['name' => 'Elena Mora', 'dob' => '1988-08-08', 'gender' => 'F', 'phone' => '4490000008', 'email' => 'elena@example.test'],
        'patient_identity_resolution' => ['status' => 'ambiguous', 'match_tier' => 'ambiguous'],
    ];
    $pdo->prepare('INSERT INTO agenda_public_appointment_flows (appointment_id, payload_json, updated_at) VALUES (?, ?, "2026-09-06 00:00:00")')->execute([$appointmentId, json_encode($payload)]);
}

function pdb08dActor(string $doctorId = 'doctor_a', string $role = 'operator', bool $authoritative = true): array
{
    return ['doctor_id' => $doctorId, 'actor_role' => $role, 'actor_id' => 'operator_01', 'user_id' => 'operator_01', 'is_authoritative' => $authoritative];
}

function pdb08dFlow(PDO $pdo, string $appointmentId = 'apt_ambiguous'): array
{
    return json_decode((string)$pdo->query("SELECT payload_json FROM agenda_public_appointment_flows WHERE appointment_id = '" . $appointmentId . "'")->fetchColumn(), true);
}

// Candidate display is derived privately, uses the PDB08B authority, and masks contacts.
$pdo = pdb08dDatabase(); pdb08dSeedCase($pdo);
$service = new AmbiguousPatientReconciliationService($pdo);
$review = $service->review('apt_ambiguous', pdb08dActor());
pdb08dAssert($review['requires_review'] === true && $review['patient_type'] === 'Ya ha consultado antes', 'review separates declaration from ambiguous identity');
pdb08dAssert(count($review['candidates']) === 2 && $review['candidates'][0]['phone_masked'] === '*** *** 0008' && $review['candidates'][0]['email_masked'] === 'e***@example.test', 'private candidate list masks contacts and excludes the created patient');

// Keep-new leaves the appointment untouched and its exact retry is idempotent.
$kept = $service->resolve('apt_ambiguous', ['action' => 'keep_new'], pdb08dActor());
$keptRetry = $service->resolve('apt_ambiguous', ['action' => 'keep_new'], pdb08dActor());
pdb08dAssert($kept['resulting_patient_id'] === 'p_new' && $kept['idempotent'] === false && $keptRetry['idempotent'] === true, 'keep-new is safe and idempotent');
pdb08dAssert($pdo->query("SELECT patient_id FROM agenda_appointments WHERE appointment_id = 'apt_ambiguous'")->fetchColumn() === 'p_new', 'keep-new retains appointment patient');
$keptFlow = pdb08dFlow($pdo);
pdb08dAssert(($keptFlow['patient_identity_resolution']['status'] ?? '') === 'ambiguous' && ($keptFlow['patient_identity_resolution']['admin_reconciliation']['status'] ?? '') === 'kept_new', 'original ambiguity and bounded resolution are both retained');
pdb08dAssert((int)$pdo->query("SELECT COUNT(*) FROM agenda_appointment_events WHERE appointment_id = 'apt_ambiguous'")->fetchColumn() === 1, 'keep-new writes one audit event');

// Relink changes only the appointment patient_id, preserves the new patient, and rejects a later divergent action.
$pdo = pdb08dDatabase(); pdb08dSeedCase($pdo);
$service = new AmbiguousPatientReconciliationService($pdo);
$linked = $service->resolve('apt_ambiguous', ['action' => 'relink_existing', 'candidate_patient_id' => 'p_candidate_a'], pdb08dActor());
$linkedRetry = $service->resolve('apt_ambiguous', ['action' => 'relink_existing', 'candidate_patient_id' => 'p_candidate_a'], pdb08dActor());
pdb08dAssert($linked['previous_patient_id'] === 'p_new' && $linked['resulting_patient_id'] === 'p_candidate_a' && $linkedRetry['idempotent'] === true, 'relink changes the appointment once and is idempotent');
pdb08dAssert($pdo->query("SELECT patient_id FROM agenda_appointments WHERE appointment_id = 'apt_ambiguous'")->fetchColumn() === 'p_candidate_a' && (int)$pdo->query("SELECT COUNT(*) FROM patients_patients WHERE patient_id = 'p_new'")->fetchColumn() === 1, 'relink does not delete or merge the created patient');
try { $service->resolve('apt_ambiguous', ['action' => 'relink_existing', 'candidate_patient_id' => 'p_candidate_b'], pdb08dActor()); throw new RuntimeException('different relink accepted'); }
catch (ReconciliationException $e) { pdb08dAssert($e->codeName === 'conflict', 'different patient after resolution is blocked'); }

// Cross-doctor submissions are never trusted from the browser.
$pdo = pdb08dDatabase(); pdb08dSeedCase($pdo);
$service = new AmbiguousPatientReconciliationService($pdo);
try { $service->resolve('apt_ambiguous', ['action' => 'relink_existing', 'candidate_patient_id' => 'p_cross_doctor'], pdb08dActor()); throw new RuntimeException('cross-doctor relink accepted'); }
catch (ReconciliationException $e) { pdb08dAssert($e->codeName === 'forbidden', 'cross-doctor candidate is server-side rejected'); }

// Patient-owned clinical data blocks a simple relink, including a confirmed appointment.
$pdo->exec('CREATE TABLE clinical_encounters (encounter_id INTEGER PRIMARY KEY, patient_id TEXT)');
$pdo->exec("INSERT INTO clinical_encounters (patient_id) VALUES ('p_new')");
try { $service->resolve('apt_ambiguous', ['action' => 'relink_existing', 'candidate_patient_id' => 'p_candidate_a'], pdb08dActor()); throw new RuntimeException('clinical relink accepted'); }
catch (ReconciliationException $e) { pdb08dAssert($e->codeName === 'clinical_data_exists', 'clinical data blocks relink'); }
pdb08dAssert($pdo->query("SELECT patient_id FROM agenda_appointments WHERE appointment_id = 'apt_ambiguous'")->fetchColumn() === 'p_new', 'clinical block preserves appointment patient');

// A stale second operator cannot overwrite a completed reconciliation.
$pdo = pdb08dDatabase(); pdb08dSeedCase($pdo);
$first = new AmbiguousPatientReconciliationService($pdo);
$second = new AmbiguousPatientReconciliationService($pdo);
$first->resolve('apt_ambiguous', ['action' => 'relink_existing', 'candidate_patient_id' => 'p_candidate_a'], pdb08dActor());
try { $second->resolve('apt_ambiguous', ['action' => 'relink_existing', 'candidate_patient_id' => 'p_candidate_b'], pdb08dActor()); throw new RuntimeException('stale operator overwrote reconciliation'); }
catch (ReconciliationException $e) { pdb08dAssert($e->codeName === 'conflict', 'stale concurrent reconciliation is blocked'); }

// Controller boundary requires the authoritative private doctor scope.
$pdo = pdb08dDatabase(); pdb08dSeedCase($pdo);
$controller = new AmbiguousPatientReconciliationController($pdo);
$controller->setActorContext(pdb08dActor('doctor_a', 'operator', false));
pdb08dAssert(($controller->show('apt_ambiguous')['error'] ?? '') === 'unauthorized', 'unauthenticated private context rejected');
$controller->setActorContext(pdb08dActor('doctor_b'));
pdb08dAssert(($controller->show('apt_ambiguous')['error'] ?? '') === 'forbidden', 'cross-doctor appointment review rejected');
$controller->setActorContext(pdb08dActor());
pdb08dAssert(($controller->show('apt_ambiguous')['ok'] ?? false) === true, 'authorized doctor/operator scope accepted');

function pdb08eCleanRelinkedCase(): array
{
    $pdo = pdb08dDatabase(); pdb08dSeedCase($pdo);
    // A single historical candidate makes resolver-exclusion assertion deterministic.
    $pdo->exec("DELETE FROM patients_contacts WHERE patient_id = 'p_candidate_b'");
    $pdo->exec("DELETE FROM patients_doctor_links WHERE patient_id = 'p_candidate_b'");
    $pdo->exec("DELETE FROM patients_patients WHERE patient_id = 'p_candidate_b'");
    $service = new AmbiguousPatientReconciliationService($pdo);
    $service->resolve('apt_ambiguous', ['action' => 'relink_existing', 'candidate_patient_id' => 'p_candidate_a'], pdb08dActor());
    return [$pdo, $service];
}

// PDB08E-B happy path preserves the source/contact/audit trail while deactivating the exact duplicate.
[$pdo, $service] = pdb08eCleanRelinkedCase();
$eligible = $service->review('apt_ambiguous', pdb08dActor());
pdb08dAssert(($eligible['cleanup']['eligible'] ?? false) === true, 'only a completed relinked preclinical orphan is offered cleanup');
$cleanup = $service->cleanup('apt_ambiguous', ['confirmed' => true], pdb08dActor());
pdb08dAssert($cleanup['idempotent'] === false && $pdo->query("SELECT status FROM patients_patients WHERE patient_id = 'p_new'")->fetchColumn() === 'inactive', 'safe orphan patient is made inactive');
pdb08dAssert($pdo->query("SELECT status FROM patients_doctor_links WHERE patient_id = 'p_new'")->fetchColumn() === 'inactive' && $pdo->query("SELECT ended_at FROM patients_doctor_links WHERE patient_id = 'p_new'")->fetchColumn() !== null, 'original doctor link is ended instead of deleted');
pdb08dAssert((int)$pdo->query("SELECT COUNT(*) FROM patients_contacts WHERE patient_id = 'p_new'")->fetchColumn() === 1 && $pdo->query("SELECT patient_id FROM agenda_appointments WHERE appointment_id = 'apt_ambiguous'")->fetchColumn() === 'p_candidate_a', 'contacts remain and appointment retains canonical patient');
pdb08dAssert((int)$pdo->query("SELECT COUNT(*) FROM agenda_appointment_events WHERE event_type = 'preclinical_duplicate_deactivated'")->fetchColumn() === 1, 'one immutable cleanup audit event exists');
pdb08dAssert(($service->review('apt_ambiguous', pdb08dActor())['cleanup']['status'] ?? '') === 'Registro duplicado desactivado', 'resolved cleanup status is read only');
$resolver = new \Patients\Services\PublicBookingPatientIdentityResolver($pdo);
$candidates = $resolver->eligibleReviewCandidates('doctor_a', ['name' => 'Elena Mora', 'dob' => '1988-08-08', 'gender' => 'F', 'phone' => '4490000008', 'email' => 'elena@example.test']);
pdb08dAssert(count($candidates) === 1 && $candidates[0]['patient_id'] === 'p_candidate_a', 'inactive duplicate is excluded from future identity resolution');

// Repeat and concurrent-like retries become a successful no-op with no duplicate event.
$retry = $service->cleanup('apt_ambiguous', ['confirmed' => true], pdb08dActor());
$secondService = new AmbiguousPatientReconciliationService($pdo);
$concurrentRetry = $secondService->cleanup('apt_ambiguous', ['confirmed' => true], pdb08dActor());
pdb08dAssert($retry['idempotent'] === true && $concurrentRetry['idempotent'] === true && (int)$pdo->query("SELECT COUNT(*) FROM agenda_appointment_events WHERE event_type = 'preclinical_duplicate_deactivated'")->fetchColumn() === 1, 'cleanup is idempotent and concurrent-safe');

// A newly-owned appointment or clinical record appearing after the UI review blocks the transactional recheck.
[$pdo, $service] = pdb08eCleanRelinkedCase();
$pdo->exec("INSERT INTO agenda_appointments (appointment_id, doctor_id, consultorio_id, patient_id, status) VALUES ('apt_race', 'doctor_a', 'consultorio_a', 'p_new', 'confirmed')");
try { $service->cleanup('apt_ambiguous', ['confirmed' => true], pdb08dActor()); throw new RuntimeException('new reference cleanup accepted'); }
catch (ReconciliationException $e) { pdb08dAssert($e->codeName === 'unsafe_orphan', 'new appointment blocks write-time orphan predicate'); }
pdb08dAssert($pdo->query("SELECT status FROM patients_patients WHERE patient_id = 'p_new'")->fetchColumn() === 'active', 'race block leaves source active');

[$pdo, $service] = pdb08eCleanRelinkedCase();
$pdo->exec('CREATE TABLE clinical_encounters (encounter_id INTEGER PRIMARY KEY, patient_id TEXT)');
$pdo->exec("INSERT INTO clinical_encounters (patient_id) VALUES ('p_new')");
try { $service->cleanup('apt_ambiguous', ['confirmed' => true], pdb08dActor()); throw new RuntimeException('clinical cleanup accepted'); }
catch (ReconciliationException $e) { pdb08dAssert($e->codeName === 'unsafe_orphan', 'clinical dependency blocks cleanup'); }

[$pdo, $service] = pdb08eCleanRelinkedCase();
$pdo->exec("INSERT INTO patients_doctor_links (link_id, doctor_id, patient_id, status, ended_at) VALUES ('l_extra', 'doctor_b', 'p_new', 'active', NULL)");
try { $service->cleanup('apt_ambiguous', ['confirmed' => true], pdb08dActor()); throw new RuntimeException('extra-link cleanup accepted'); }
catch (ReconciliationException $e) { pdb08dAssert($e->codeName === 'unsafe_orphan', 'additional doctor link blocks cleanup'); }

[$pdo, $service] = pdb08eCleanRelinkedCase();
try { $service->cleanup('apt_ambiguous', ['confirmed' => true, 'source_patient_id' => 'p_candidate_a'], pdb08dActor()); throw new RuntimeException('arbitrary source accepted'); }
catch (ReconciliationException $e) { pdb08dAssert($e->codeName === 'forbidden', 'arbitrary source id is rejected'); }
try { $service->cleanup('apt_ambiguous', ['confirmed' => true], pdb08dActor('doctor_b')); throw new RuntimeException('cross doctor cleanup accepted'); }
catch (ReconciliationException $e) { pdb08dAssert($e->codeName === 'forbidden', 'cleanup enforces doctor scope'); }
try { $service->cleanup('apt_ambiguous', ['confirmed' => false], pdb08dActor()); throw new RuntimeException('unconfirmed cleanup accepted'); }
catch (ReconciliationException $e) { pdb08dAssert($e->codeName === 'confirmation_required', 'cleanup requires explicit admin confirmation'); }

echo "AmbiguousPatientReconciliationTest PASS\n";
