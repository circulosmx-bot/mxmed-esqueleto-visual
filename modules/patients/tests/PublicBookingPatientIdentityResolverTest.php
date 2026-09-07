<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/PublicBookingPatientIdentityResolver.php';

use Patients\Services\PublicBookingPatientIdentityResolver;

function pdb08bAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function pdb08bDatabase(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE patients_patients (patient_id TEXT PRIMARY KEY, display_name TEXT NOT NULL, birthdate TEXT NULL, sex TEXT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE patients_contacts (contact_id TEXT PRIMARY KEY, patient_id TEXT NOT NULL, phone TEXT NULL, email TEXT NULL)');
    $pdo->exec('CREATE TABLE patients_doctor_links (link_id TEXT PRIMARY KEY, doctor_id TEXT NOT NULL, patient_id TEXT NOT NULL, status TEXT NOT NULL, ended_at TEXT NULL)');
    return $pdo;
}

function pdb08bPatient(PDO $pdo, string $id, string $doctorId, string $name, string $birthdate, string $phone, string $email, string $sex = 'F'): void
{
    $pdo->prepare('INSERT INTO patients_patients (patient_id, display_name, birthdate, sex, status) VALUES (?, ?, ?, ?, "active")')
        ->execute([$id, $name, $birthdate, $sex]);
    $pdo->prepare('INSERT INTO patients_contacts (contact_id, patient_id, phone, email) VALUES (?, ?, ?, ?)')
        ->execute(['c_' . $id, $id, $phone, $email]);
    $pdo->prepare('INSERT INTO patients_doctor_links (link_id, doctor_id, patient_id, status, ended_at) VALUES (?, ?, ?, "active", NULL)')
        ->execute(['l_' . $id, $doctorId, $id]);
}

function pdb08bPatientInput(string $name, string $birthdate, string $phone, string $email): array
{
    return ['name' => $name, 'dob' => $birthdate, 'gender' => 'F', 'phone' => $phone, 'email' => $email];
}

// Unique strong match remains reusable even when the declaration says first-time.
$pdo = pdb08bDatabase();
pdb08bPatient($pdo, 'p_unique', 'doctor-a', 'María López', '1990-01-01', '+52 449 123 4567', 'maria@example.test');
$resolver = new PublicBookingPatientIdentityResolver($pdo);
$unique = $resolver->resolve('doctor-a', pdb08bPatientInput('MARIA LOPEZ', '1990-01-01', '4491234567', 'MARIA@EXAMPLE.TEST') + ['patient_type' => 'first_time']);
pdb08bAssert($unique['status'] === 'matched' && $unique['patient_id'] === 'p_unique', 'wrong first_time declaration keeps unique strong patient reuse');

// A contact without the same full name and birth date cannot be reused.
$contactOnly = $resolver->resolve('doctor-a', pdb08bPatientInput('Otra Persona', '1995-05-05', '4491234567', 'otra@example.test'));
pdb08bAssert($contactOnly['status'] === 'not_found' && $contactOnly['patient_id'] === null, 'contact-only reuse is blocked');

// A shared household phone selects the patient whose complete evidence is unique.
$pdo = pdb08bDatabase();
pdb08bPatient($pdo, 'p_phone_a', 'doctor-a', 'Andrea Casas', '1980-01-01', '4490000000', 'andrea@example.test');
pdb08bPatient($pdo, 'p_phone_b', 'doctor-a', 'Beatriz Ríos', '1992-02-02', '4490000000', 'beatriz@example.test');
$sharedPhone = (new PublicBookingPatientIdentityResolver($pdo))->resolve('doctor-a', pdb08bPatientInput('BEATRIZ RIOS', '1992-02-02', '449 000 0000', 'beatriz@example.test'));
pdb08bAssert($sharedPhone['status'] === 'matched' && $sharedPhone['patient_id'] === 'p_phone_b', 'shared phone cannot choose the first patient');

// A shared family email follows the same contract.
$pdo = pdb08bDatabase();
pdb08bPatient($pdo, 'p_email_a', 'doctor-a', 'Carla Núñez', '1981-03-03', '4490000001', 'familia@example.test');
pdb08bPatient($pdo, 'p_email_b', 'doctor-a', 'Daniela Paz', '1993-04-04', '4490000002', 'familia@example.test');
$sharedEmail = (new PublicBookingPatientIdentityResolver($pdo))->resolve('doctor-a', pdb08bPatientInput('DANIELA PAZ', '1993-04-04', '4490000002', 'FAMILIA@example.test'));
pdb08bAssert($sharedEmail['status'] === 'matched' && $sharedEmail['patient_id'] === 'p_email_b', 'shared email cannot choose the first patient');

// Duplicate complete evidence is ambiguous and never emits a patient id.
$pdo = pdb08bDatabase();
pdb08bPatient($pdo, 'p_ambiguous_a', 'doctor-a', 'Elena Mora', '1988-08-08', '4490000008', 'elena@example.test');
pdb08bPatient($pdo, 'p_ambiguous_b', 'doctor-a', 'Elena Mora', '1988-08-08', '4490000008', 'elena@example.test');
$ambiguous = (new PublicBookingPatientIdentityResolver($pdo))->resolve('doctor-a', pdb08bPatientInput('ELENA MORA', '1988-08-08', '4490000008', 'elena@example.test'));
pdb08bAssert($ambiguous['status'] === 'ambiguous' && $ambiguous['patient_id'] === null, 'multiple strong candidates are ambiguous');

// A returning declaration does not attach an unrelated historical patient.
$noMatch = $resolver->resolve('doctor-a', pdb08bPatientInput('Paciente Nuevo', '2001-01-01', '4491111111', 'nuevo@example.test') + ['patient_type' => 'follow_up']);
pdb08bAssert($noMatch['status'] === 'not_found' && $noMatch['patient_id'] === null, 'wrong follow_up declaration does not create a false link');

// Booker fields are intentionally absent from this boundary; only patient fields are read.
$pdo = pdb08bDatabase();
pdb08bPatient($pdo, 'p_other_person', 'doctor-a', 'Fernanda Soto', '1994-09-09', '4490000009', 'fernanda@example.test');
$otherPerson = new PublicBookingPatientIdentityResolver($pdo);
$otherPersonResult = $otherPerson->resolve('doctor-a', pdb08bPatientInput('Fernanda Soto', '1994-09-09', '4490000009', 'fernanda@example.test') + [
    'booker' => ['name' => 'Solicitante distinto', 'phone' => '4499999999', 'email' => 'booker@example.test', 'relationship' => 'madre'],
]);
pdb08bAssert($otherPersonResult['status'] === 'matched' && $otherPersonResult['patient_id'] === 'p_other_person', 'booker fields do not drive patient resolution');

echo "PublicBookingPatientIdentityResolverTest PASS\n";
