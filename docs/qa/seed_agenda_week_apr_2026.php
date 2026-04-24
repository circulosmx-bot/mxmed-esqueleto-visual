<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/_lib/db.php';

date_default_timezone_set('America/Mexico_City');

const APPOINTMENTS_TABLE = 'agenda_appointments';
const EVENTS_TABLE = 'agenda_appointment_events';
const SEED_CHANNEL_ORIGIN = 'qa_seed_apr_2026';
const SEED_ACTOR_ROLE = 'operator';
const SEED_ACTOR_ID = 'qa_seed_apr2026';

$doctorId = '1';
$consultorioId = '1';

$seedRows = [
    ['suffix' => '0420_0900_tent', 'patient_id' => 'p_seed_apr_001', 'start_at' => '2026-04-20 09:00:00', 'end_at' => '2026-04-20 09:30:00', 'status' => 'tentative', 'modality' => 'seguimiento'],
    ['suffix' => '0420_1000_conf', 'patient_id' => 'p_seed_apr_002', 'start_at' => '2026-04-20 10:00:00', 'end_at' => '2026-04-20 10:30:00', 'status' => 'confirmed', 'modality' => 'primera_vez'],
    ['suffix' => '0420_1130_canc', 'patient_id' => 'p_seed_apr_003', 'start_at' => '2026-04-20 11:30:00', 'end_at' => '2026-04-20 12:00:00', 'status' => 'canceled', 'modality' => 'control'],
    ['suffix' => '0421_0930_nosh', 'patient_id' => 'p_seed_apr_004', 'start_at' => '2026-04-21 09:30:00', 'end_at' => '2026-04-21 10:00:00', 'status' => 'no_show', 'modality' => 'seguimiento'],
    ['suffix' => '0421_1030_conf', 'patient_id' => 'p_seed_apr_005', 'start_at' => '2026-04-21 10:30:00', 'end_at' => '2026-04-21 11:00:00', 'status' => 'confirmed', 'modality' => 'valoracion'],
    ['suffix' => '0422_0900_tent', 'patient_id' => 'p_seed_apr_006', 'start_at' => '2026-04-22 09:00:00', 'end_at' => '2026-04-22 09:30:00', 'status' => 'tentative', 'modality' => 'seguimiento'],
    ['suffix' => '0422_1200_canc', 'patient_id' => 'p_seed_apr_007', 'start_at' => '2026-04-22 12:00:00', 'end_at' => '2026-04-22 12:30:00', 'status' => 'canceled', 'modality' => 'control'],
    ['suffix' => '0423_0830_conf', 'patient_id' => 'p_seed_apr_008', 'start_at' => '2026-04-23 08:30:00', 'end_at' => '2026-04-23 09:00:00', 'status' => 'confirmed', 'modality' => 'primera_vez'],
    ['suffix' => '0423_1100_nosh', 'patient_id' => 'p_seed_apr_009', 'start_at' => '2026-04-23 11:00:00', 'end_at' => '2026-04-23 11:30:00', 'status' => 'no_show', 'modality' => 'seguimiento'],
    ['suffix' => '0424_0900_tent', 'patient_id' => 'p_seed_apr_010', 'start_at' => '2026-04-24 09:00:00', 'end_at' => '2026-04-24 09:30:00', 'status' => 'tentative', 'modality' => 'control'],
    ['suffix' => '0424_1000_conf', 'patient_id' => 'p_seed_apr_011', 'start_at' => '2026-04-24 10:00:00', 'end_at' => '2026-04-24 10:30:00', 'status' => 'confirmed', 'modality' => 'seguimiento'],
    ['suffix' => '0425_0930_tent', 'patient_id' => 'p_seed_apr_012', 'start_at' => '2026-04-25 09:30:00', 'end_at' => '2026-04-25 10:00:00', 'status' => 'tentative', 'modality' => 'primera_vez'],
];

function fail(string $message): void
{
    fwrite(STDERR, "[seed][error] {$message}\n");
    exit(1);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $pdo = mxmed_pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    fail('No fue posible conectar a la BD: ' . $e->getMessage());
}

if (!tableExists($pdo, APPOINTMENTS_TABLE)) {
    fail('No existe tabla agenda_appointments');
}
if (!tableExists($pdo, EVENTS_TABLE)) {
    fail('No existe tabla agenda_appointment_events');
}

$selectAppointmentStmt = $pdo->prepare('SELECT appointment_id FROM ' . APPOINTMENTS_TABLE . ' WHERE appointment_id = :appointment_id LIMIT 1');
$insertAppointmentStmt = $pdo->prepare(
    'INSERT INTO ' . APPOINTMENTS_TABLE . '
    (appointment_id, doctor_id, consultorio_id, patient_id, start_at, end_at, modality, status, channel_origin, created_by_role, created_by_id, created_at)
    VALUES
    (:appointment_id, :doctor_id, :consultorio_id, :patient_id, :start_at, :end_at, :modality, :status, :channel_origin, :created_by_role, :created_by_id, :created_at)'
);

$selectCreatedEventStmt = $pdo->prepare(
    'SELECT event_id FROM ' . EVENTS_TABLE . '
    WHERE appointment_id = :appointment_id AND event_type = :event_type
    LIMIT 1'
);
$insertCreatedEventStmt = $pdo->prepare(
    'INSERT INTO ' . EVENTS_TABLE . '
    (event_id, appointment_id, event_type, timestamp, from_datetime, to_datetime, from_start_at, to_end_at, actor_role, actor_id, channel_origin, notes)
    VALUES
    (:event_id, :appointment_id, :event_type, :timestamp, :from_datetime, :to_datetime, :from_start_at, :to_end_at, :actor_role, :actor_id, :channel_origin, :notes)'
);

$appointmentsInserted = 0;
$appointmentsSkipped = 0;
$eventsInserted = 0;
$eventsSkipped = 0;

foreach ($seedRows as $row) {
    $appointmentId = 'qa_apr26_d1c1_' . $row['suffix'];
    $eventId = 'qaev_' . substr(sha1($appointmentId . ':appointment_created'), 0, 28);
    $createdAt = (new DateTime($row['start_at']))->modify('-7 days')->format('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $selectAppointmentStmt->execute(['appointment_id' => $appointmentId]);
        $appointmentExists = (bool)$selectAppointmentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$appointmentExists) {
            $insertAppointmentStmt->execute([
                'appointment_id' => $appointmentId,
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'patient_id' => $row['patient_id'],
                'start_at' => $row['start_at'],
                'end_at' => $row['end_at'],
                'modality' => $row['modality'],
                'status' => $row['status'],
                'channel_origin' => SEED_CHANNEL_ORIGIN,
                'created_by_role' => SEED_ACTOR_ROLE,
                'created_by_id' => SEED_ACTOR_ID,
                'created_at' => $createdAt,
            ]);
            $appointmentsInserted++;
        } else {
            $appointmentsSkipped++;
        }

        $selectCreatedEventStmt->execute([
            'appointment_id' => $appointmentId,
            'event_type' => 'appointment_created',
        ]);
        $createdEventExists = (bool)$selectCreatedEventStmt->fetch(PDO::FETCH_ASSOC);

        if (!$createdEventExists) {
            $insertCreatedEventStmt->execute([
                'event_id' => $eventId,
                'appointment_id' => $appointmentId,
                'event_type' => 'appointment_created',
                'timestamp' => $createdAt,
                'from_datetime' => $row['start_at'],
                'to_datetime' => $row['end_at'],
                'from_start_at' => $row['start_at'],
                'to_end_at' => $row['end_at'],
                'actor_role' => SEED_ACTOR_ROLE,
                'actor_id' => SEED_ACTOR_ID,
                'channel_origin' => SEED_CHANNEL_ORIGIN,
                'notes' => 'QA seed abril 2026',
            ]);
            $eventsInserted++;
        } else {
            $eventsSkipped++;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fail('Fallo en appointment_id=' . $appointmentId . ': ' . $e->getMessage());
    }
}

echo "[seed] OK\n";
echo "[seed] doctor_id={$doctorId} consultorio_id={$consultorioId}\n";
echo "[seed] appointments inserted={$appointmentsInserted} skipped={$appointmentsSkipped}\n";
echo "[seed] appointment_created events inserted={$eventsInserted} skipped={$eventsSkipped}\n";
