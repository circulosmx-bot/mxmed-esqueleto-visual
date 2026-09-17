<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../repositories/PatientsRepository.php';

use Patients\Repositories\PatientsRepository;

function archiveAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = mxmed_pdo();
$repo = new PatientsRepository($pdo);
$doctorId = (string)$pdo->query("SELECT l.doctor_id
    FROM patients_doctor_links l JOIN patients_patients p ON p.patient_id = l.patient_id
    WHERE l.status = 'active' AND p.status = 'active'
      AND NOT EXISTS (SELECT 1 FROM clinical_encounters e WHERE e.patient_id = l.patient_id)
      AND NOT EXISTS (SELECT 1 FROM agenda_appointments a WHERE a.patient_id = l.patient_id)
    GROUP BY l.doctor_id HAVING COUNT(*) >= 7 ORDER BY COUNT(*) DESC LIMIT 1")->fetchColumn();
archiveAssert($doctorId !== '', 'doctor with seven untouched linked patients required for synthetic fixtures');
$patientStmt = $pdo->prepare("SELECT l.patient_id
    FROM patients_doctor_links l JOIN patients_patients p ON p.patient_id = l.patient_id
    WHERE l.doctor_id = ? AND l.status = 'active' AND p.status = 'active'
      AND NOT EXISTS (SELECT 1 FROM clinical_encounters e WHERE e.patient_id = l.patient_id)
      AND NOT EXISTS (SELECT 1 FROM agenda_appointments a WHERE a.patient_id = l.patient_id)
    ORDER BY l.patient_id LIMIT 7");
$patientStmt->execute([$doctorId]);
$patientIds = $patientStmt->fetchAll(PDO::FETCH_COLUMN);
archiveAssert(count($patientIds) === 7, 'seven untouched linked patients required for synthetic fixtures');

$shared = $pdo->query("SELECT a.patient_id, a.doctor_id AS doctor_a, b.doctor_id AS doctor_b
    FROM patients_doctor_links a JOIN patients_doctor_links b
      ON b.patient_id = a.patient_id AND b.doctor_id <> a.doctor_id AND b.status = 'active'
    WHERE a.status = 'active'
      AND NOT EXISTS (SELECT 1 FROM clinical_encounters e WHERE e.patient_id = a.patient_id AND e.doctor_id IN (a.doctor_id, b.doctor_id))
      AND NOT EXISTS (SELECT 1 FROM agenda_appointments ap WHERE ap.patient_id = a.patient_id AND ap.doctor_id IN (a.doctor_id, b.doctor_id) AND ap.start_at > NOW())
    LIMIT 1")->fetch(PDO::FETCH_ASSOC);
archiveAssert(is_array($shared), 'shared patient required for cross-doctor fixture');
$foreignDoctorId = $shared['doctor_a'] === $doctorId ? $shared['doctor_b'] : $shared['doctor_a'];
$consultorioStmt = $pdo->prepare('SELECT consultorio_id FROM agenda_appointments WHERE doctor_id = ? LIMIT 1');
$consultorioStmt->execute([$doctorId]);
$consultorioId = (string)$consultorioStmt->fetchColumn();
archiveAssert($consultorioId !== '', 'doctor consultorio required for appointment fixtures');

$now = new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'));
$future = static fn(int $days): string => $now->modify("+{$days} days")->setTime(10, 0)->format('Y-m-d H:i:s');
$appointmentIdPrefix = 'patux04b_' . bin2hex(random_bytes(5));
$pdo->beginTransaction();
try {
    $encounter = $pdo->prepare('INSERT INTO clinical_encounters (patient_id, doctor_id, encounter_dt, status) VALUES (?, ?, ?, ?)');
    $addEncounter = static function (string $patientId, ?string $doctor, string $date, string $status) use ($encounter): void {
        $encounter->execute([$patientId, $doctor, $date, $status]);
    };
    $appointment = $pdo->prepare("INSERT INTO agenda_appointments
        (appointment_id, doctor_id, consultorio_id, patient_id, start_at, end_at, modality, status)
        VALUES (?, ?, ?, ?, ?, ?, 'in_person', ?)");
    $appointmentNumber = 0;
    $addAppointment = static function (string $patientId, string $doctor, string $start, string $status) use ($appointment, &$appointmentNumber, $appointmentIdPrefix, $consultorioId): void {
        $end = (new DateTimeImmutable($start))->modify('+30 minutes')->format('Y-m-d H:i:s');
        $appointment->execute([$appointmentIdPrefix . '_' . ++$appointmentNumber, $doctor, $consultorioId, $patientId, $start, $end, $status]);
    };

    $addEncounter($patientIds[1], $doctorId, '2026-01-10 09:00:00', 'closed');
    $addEncounter($patientIds[2], $doctorId, '2026-02-10 09:00:00', 'closed');
    $addEncounter($patientIds[2], $doctorId, '2026-03-10 09:00:00', ' CLOSED ');
    $addEncounter($patientIds[3], $doctorId, '2026-04-10 09:00:00', 'open');
    $addEncounter($patientIds[4], null, '2026-05-10 09:00:00', 'closed');
    $addEncounter($patientIds[5], $doctorId, '2026-06-10 09:00:00', 'closed');
    $addEncounter($patientIds[5], null, '2026-07-10 09:00:00', 'closed');
    $addEncounter($patientIds[6], $foreignDoctorId, '2026-08-10 09:00:00', 'closed');

    $addAppointment($patientIds[1], $doctorId, $future(2), 'confirmed');
    $addAppointment($patientIds[2], $doctorId, $future(4), 'scheduled');
    $addAppointment($patientIds[2], $doctorId, $future(3), 'tentative');
    $addAppointment($patientIds[2], $doctorId, $future(1), 'canceled');
    $addAppointment($patientIds[3], $doctorId, $future(1), 'pending_otp');
    $addAppointment($patientIds[4], $doctorId, $future(1), 'no_show');
    $addAppointment($patientIds[4], $doctorId, $future(2), 'completed');
    $addAppointment($patientIds[4], $doctorId, $future(3), 'late_cancel');
    $addAppointment($patientIds[4], $doctorId, $future(4), 'finished');
    $addAppointment($patientIds[5], $doctorId, $future(5), 'pending');
    $addAppointment($patientIds[6], $foreignDoctorId, $future(1), 'confirmed');

    $filters = ['sort' => 'surname_asc', 'page' => 1, 'limit' => 100];
    $allRows = static function (string $doctor, string $sort) use ($repo, $filters): array {
        $rows = [];
        for ($page = 1; ; $page++) {
            $result = $repo->browsePatientsByDoctorId($doctor, array_replace($filters, ['sort' => $sort, 'page' => $page]));
            array_push($rows, ...$result['items']);
            if (count($rows) >= $result['filtered_total']) break;
            archiveAssert($page < 100, 'pagination did not terminate');
        }
        return $rows;
    };
    $byId = static function (array $rows): array {
        $out = [];
        foreach ($rows as $row) $out[$row['patient_id']] = $row;
        return $out;
    };
    $rows = $byId($allRows($doctorId, 'surname_asc'));
    foreach ([0, 3, 6] as $i) {
        archiveAssert((int)$rows[$patientIds[$i]]['consultation_count'] === 0, "fixture {$i} must have zero attributed consultations");
        archiveAssert($rows[$patientIds[$i]]['last_consultation_at'] === null, "fixture {$i} must have no last consultation");
    }
    archiveAssert((int)$rows[$patientIds[1]]['consultation_count'] === 1, 'one closed consultation count');
    archiveAssert((int)$rows[$patientIds[2]]['consultation_count'] === 2, 'normalized closed consultation count');
    archiveAssert(str_starts_with($rows[$patientIds[2]]['last_consultation_at'], '2026-03-10'), 'latest encounter_dt authority');
    archiveAssert((int)$rows[$patientIds[4]]['has_unattributed_legacy_consultation'] === 1, 'legacy-only flag');
    archiveAssert((int)$rows[$patientIds[4]]['consultation_count'] === 0, 'legacy must not count');
    archiveAssert((int)$rows[$patientIds[5]]['consultation_count'] === 1, 'attributed + legacy counts only attributed');
    archiveAssert(str_starts_with($rows[$patientIds[5]]['last_consultation_at'], '2026-06-10'), 'legacy must not replace attributed date');
    archiveAssert($rows[$patientIds[1]]['next_appointment_at'] === $future(2), 'confirmed appointment');
    archiveAssert($rows[$patientIds[2]]['next_appointment_at'] === $future(3), 'nearest eligible appointment');
    foreach ([0, 3, 4, 6] as $i) archiveAssert($rows[$patientIds[$i]]['next_appointment_at'] === null, "fixture {$i} must have no eligible appointment");
    archiveAssert($rows[$patientIds[5]]['next_appointment_at'] === $future(5), 'pending internal appointment');

    $addEncounter($shared['patient_id'], $shared['doctor_a'], '2026-08-01 09:00:00', 'closed');
    $addEncounter($shared['patient_id'], $shared['doctor_b'], '2026-08-02 09:00:00', 'closed');
    $addAppointment($shared['patient_id'], $shared['doctor_a'], $future(6), 'confirmed');
    $addAppointment($shared['patient_id'], $shared['doctor_b'], $future(7), 'confirmed');
    $a = $byId($allRows($shared['doctor_a'], 'surname_asc'))[$shared['patient_id']];
    $b = $byId($allRows($shared['doctor_b'], 'surname_asc'))[$shared['patient_id']];
    archiveAssert((int)$a['consultation_count'] === 1 && str_starts_with($a['last_consultation_at'], '2026-08-01'), 'doctor A clinical isolation');
    archiveAssert((int)$b['consultation_count'] === 1 && str_starts_with($b['last_consultation_at'], '2026-08-02'), 'doctor B clinical isolation');
    archiveAssert($a['next_appointment_at'] === $future(6), 'doctor A appointment isolation');
    archiveAssert($b['next_appointment_at'] === $future(7), 'doctor B appointment isolation');

    foreach (['surname_asc', 'surname_desc', 'age_asc', 'age_desc', 'last_consultation_desc', 'last_consultation_asc', 'next_appointment_asc', 'next_appointment_desc', 'consultations_desc', 'consultations_asc'] as $sort) {
        $sorted = $allRows($doctorId, $sort);
        archiveAssert(count($sorted) === count($rows), "{$sort} global pagination count");
        archiveAssert(count(array_unique(array_column($sorted, 'patient_id'))) === count($sorted), "{$sort} duplicate across pages");
        if (str_starts_with($sort, 'last_consultation_') || str_starts_with($sort, 'next_appointment_')) {
            $key = str_starts_with($sort, 'last_') ? 'last_consultation_at' : 'next_appointment_at';
            $seenUnknown = false;
            $previous = null;
            foreach ($sorted as $row) {
                if ($row[$key] === null) $seenUnknown = true;
                else {
                    archiveAssert(!$seenUnknown, "{$sort} unknown must remain last");
                    if ($previous !== null) archiveAssert(str_ends_with($sort, '_asc') ? $previous <= $row[$key] : $previous >= $row[$key], "{$sort} global order");
                    $previous = $row[$key];
                }
            }
        }
        if (str_starts_with($sort, 'consultations_')) {
            $seenAmbiguous = false;
            $previous = null;
            foreach ($sorted as $row) {
                $ambiguous = (int)$row['consultation_count'] === 0 && (int)$row['has_unattributed_legacy_consultation'] === 1;
                if ($ambiguous) $seenAmbiguous = true;
                else {
                    archiveAssert(!$seenAmbiguous, "{$sort} legacy ambiguous must remain last");
                    $count = (int)$row['consultation_count'];
                    if ($previous !== null) archiveAssert(str_ends_with($sort, '_asc') ? $previous <= $count : $previous >= $count, "{$sort} global order");
                    $previous = $count;
                }
            }
        }
    }
    echo "PatientArchiveClinicalReadModelIntegrationTest PASS\n";
} finally {
    $pdo->rollBack();
}
