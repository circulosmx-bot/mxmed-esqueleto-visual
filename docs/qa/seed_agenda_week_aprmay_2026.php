<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/_lib/db.php';

date_default_timezone_set('America/Mexico_City');

const APPOINTMENTS_TABLE = 'agenda_appointments';
const EVENTS_TABLE = 'agenda_appointment_events';

const SEED_CHANNEL_ORIGIN = 'qa_seed_aprmay_2026';
const SEED_ACTOR_ROLE = 'operator';
const SEED_ACTOR_ID = 'qa_seed_aprmay2026';
const SEED_DOCTOR_ID = '1';
const SEED_CONSULTORIO_ID = '1';

$seedRows = [
    ['suffix' => '0427_0900_tent', 'patient_id' => 'p_seed_aprmay_001', 'start_at' => '2026-04-27 09:00:00', 'end_at' => '2026-04-27 09:30:00', 'status' => 'tentative', 'modality' => 'seguimiento'],
    ['suffix' => '0427_1000_conf', 'patient_id' => 'p_seed_aprmay_002', 'start_at' => '2026-04-27 10:00:00', 'end_at' => '2026-04-27 10:30:00', 'status' => 'confirmed', 'modality' => 'primera_vez'],
    ['suffix' => '0427_1130_canc', 'patient_id' => 'p_seed_aprmay_003', 'start_at' => '2026-04-27 11:30:00', 'end_at' => '2026-04-27 12:00:00', 'status' => 'canceled', 'modality' => 'control'],
    ['suffix' => '0428_0930_nosh', 'patient_id' => 'p_seed_aprmay_004', 'start_at' => '2026-04-28 09:30:00', 'end_at' => '2026-04-28 10:00:00', 'status' => 'confirmed', 'modality' => 'seguimiento'],
    ['suffix' => '0428_1030_conf', 'patient_id' => 'p_seed_aprmay_005', 'start_at' => '2026-04-28 10:30:00', 'end_at' => '2026-04-28 11:00:00', 'status' => 'confirmed', 'modality' => 'valoracion'],
    ['suffix' => '0428_1200_tent', 'patient_id' => 'p_seed_aprmay_006', 'start_at' => '2026-04-28 12:00:00', 'end_at' => '2026-04-28 12:30:00', 'status' => 'tentative', 'modality' => 'seguimiento'],
    ['suffix' => '0429_0830_conf', 'patient_id' => 'p_seed_aprmay_007', 'start_at' => '2026-04-29 08:30:00', 'end_at' => '2026-04-29 09:00:00', 'status' => 'confirmed', 'modality' => 'primera_vez'],
    ['suffix' => '0429_0930_canc', 'patient_id' => 'p_seed_aprmay_008', 'start_at' => '2026-04-29 09:30:00', 'end_at' => '2026-04-29 10:00:00', 'status' => 'canceled', 'modality' => 'control'],
    ['suffix' => '0429_1100_nosh', 'patient_id' => 'p_seed_aprmay_009', 'start_at' => '2026-04-29 11:00:00', 'end_at' => '2026-04-29 11:30:00', 'status' => 'confirmed', 'modality' => 'seguimiento'],
    ['suffix' => '0430_0900_tent', 'patient_id' => 'p_seed_aprmay_010', 'start_at' => '2026-04-30 09:00:00', 'end_at' => '2026-04-30 09:30:00', 'status' => 'tentative', 'modality' => 'control'],
    ['suffix' => '0430_1000_conf', 'patient_id' => 'p_seed_aprmay_011', 'start_at' => '2026-04-30 10:00:00', 'end_at' => '2026-04-30 10:30:00', 'status' => 'confirmed', 'modality' => 'seguimiento'],
    ['suffix' => '0430_1130_canc', 'patient_id' => 'p_seed_aprmay_012', 'start_at' => '2026-04-30 11:30:00', 'end_at' => '2026-04-30 12:00:00', 'status' => 'canceled', 'modality' => 'control'],
    ['suffix' => '0501_0930_conf', 'patient_id' => 'p_seed_aprmay_013', 'start_at' => '2026-05-01 09:30:00', 'end_at' => '2026-05-01 10:00:00', 'status' => 'confirmed', 'modality' => 'primera_vez'],
    ['suffix' => '0501_1030_tent', 'patient_id' => 'p_seed_aprmay_014', 'start_at' => '2026-05-01 10:30:00', 'end_at' => '2026-05-01 11:00:00', 'status' => 'tentative', 'modality' => 'seguimiento'],
    ['suffix' => '0501_1130_nosh', 'patient_id' => 'p_seed_aprmay_015', 'start_at' => '2026-05-01 11:30:00', 'end_at' => '2026-05-01 12:00:00', 'status' => 'confirmed', 'modality' => 'control'],
    ['suffix' => '0502_0900_tent', 'patient_id' => 'p_seed_aprmay_016', 'start_at' => '2026-05-02 09:00:00', 'end_at' => '2026-05-02 09:30:00', 'status' => 'tentative', 'modality' => 'seguimiento'],
    ['suffix' => '0502_1000_conf', 'patient_id' => 'p_seed_aprmay_017', 'start_at' => '2026-05-02 10:00:00', 'end_at' => '2026-05-02 10:30:00', 'status' => 'confirmed', 'modality' => 'valoracion'],
    ['suffix' => '0502_1100_canc', 'patient_id' => 'p_seed_aprmay_018', 'start_at' => '2026-05-02 11:00:00', 'end_at' => '2026-05-02 11:30:00', 'status' => 'canceled', 'modality' => 'control'],
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

function buildEventId(string $appointmentId, string $eventType): string
{
    return 'qaev_' . substr(sha1($appointmentId . ':' . $eventType), 0, 28);
}

try {
    $pdo = mxmed_pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    fail('No fue posible conectar a la BD: ' . $e->getMessage());
}

foreach ([APPOINTMENTS_TABLE, EVENTS_TABLE] as $tableName) {
    if (!tableExists($pdo, $tableName)) {
        fail("No existe tabla {$tableName}");
    }
}

$selectAppointmentStmt = $pdo->prepare(
    'SELECT appointment_id FROM ' . APPOINTMENTS_TABLE . ' WHERE appointment_id = :appointment_id LIMIT 1'
);
$insertAppointmentStmt = $pdo->prepare(
    'INSERT INTO ' . APPOINTMENTS_TABLE . '
    (appointment_id, doctor_id, consultorio_id, patient_id, start_at, end_at, modality, status, channel_origin, created_by_role, created_by_id, created_at)
    VALUES
    (:appointment_id, :doctor_id, :consultorio_id, :patient_id, :start_at, :end_at, :modality, :status, :channel_origin, :created_by_role, :created_by_id, :created_at)'
);

$selectEventStmt = $pdo->prepare(
    'SELECT event_id FROM ' . EVENTS_TABLE . ' WHERE appointment_id = :appointment_id AND event_type = :event_type LIMIT 1'
);
$insertEventStmt = $pdo->prepare(
    'INSERT INTO ' . EVENTS_TABLE . '
    (event_id, appointment_id, event_type, timestamp, from_datetime, to_datetime, from_start_at, from_end_at, to_start_at, to_end_at, observed_at, motivo_code, motivo_text, notify_patient, contact_method, actor_role, actor_id, channel_origin, notes)
    VALUES
    (:event_id, :appointment_id, :event_type, :timestamp, :from_datetime, :to_datetime, :from_start_at, :from_end_at, :to_start_at, :to_end_at, :observed_at, :motivo_code, :motivo_text, :notify_patient, :contact_method, :actor_role, :actor_id, :channel_origin, :notes)'
);

$appointmentsInserted = 0;
$appointmentsSkipped = 0;
$eventsInserted = 0;
$eventsSkipped = 0;

foreach ($seedRows as $row) {
    $appointmentId = 'qa_aprmay26_d1c1_' . $row['suffix'];
    $status = strtolower(trim((string)$row['status']));
    $startAt = (string)$row['start_at'];
    $endAt = (string)$row['end_at'];
    $startDate = new DateTime($startAt);
    $now = new DateTime('now');
    if ($startDate > $now && in_array($status, ['no_show', 'finished', 'finalizada'], true)) {
        $status = 'confirmed';
    }
    $createdAt = (new DateTime($startAt))->modify('-10 days')->format('Y-m-d H:i:s');
    $cancelledAt = (new DateTime($startAt))->modify('-1 day')->format('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $selectAppointmentStmt->execute(['appointment_id' => $appointmentId]);
        $appointmentExists = (bool)$selectAppointmentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$appointmentExists) {
            $insertAppointmentStmt->execute([
                'appointment_id' => $appointmentId,
                'doctor_id' => SEED_DOCTOR_ID,
                'consultorio_id' => SEED_CONSULTORIO_ID,
                'patient_id' => (string)$row['patient_id'],
                'start_at' => $startAt,
                'end_at' => $endAt,
                'modality' => (string)$row['modality'],
                'status' => $status,
                'channel_origin' => SEED_CHANNEL_ORIGIN,
                'created_by_role' => SEED_ACTOR_ROLE,
                'created_by_id' => SEED_ACTOR_ID,
                'created_at' => $createdAt,
            ]);
            $appointmentsInserted++;
        } else {
            $appointmentsSkipped++;
        }

        $eventsToEnsure = [
            [
                'event_type' => 'appointment_created',
                'timestamp' => $createdAt,
                'from_datetime' => $startAt,
                'to_datetime' => $endAt,
                'from_start_at' => $startAt,
                'from_end_at' => null,
                'to_start_at' => null,
                'to_end_at' => $endAt,
                'observed_at' => null,
                'motivo_code' => null,
                'motivo_text' => null,
                'notify_patient' => 0,
                'contact_method' => 'none',
                'notes' => 'QA seed abril/mayo 2026',
            ],
        ];

        if ($status === 'canceled') {
            $eventsToEnsure[] = [
                'event_type' => 'appointment_canceled',
                'timestamp' => $cancelledAt,
                'from_datetime' => $startAt,
                'to_datetime' => null,
                'from_start_at' => $startAt,
                'from_end_at' => $endAt,
                'to_start_at' => null,
                'to_end_at' => null,
                'observed_at' => null,
                'motivo_code' => 'qa_seed_cancel',
                'motivo_text' => 'Cancelación seed QA',
                'notify_patient' => 0,
                'contact_method' => 'none',
                'notes' => 'QA seed abril/mayo 2026',
            ];
        }

        foreach ($eventsToEnsure as $event) {
            $selectEventStmt->execute([
                'appointment_id' => $appointmentId,
                'event_type' => $event['event_type'],
            ]);
            $eventExists = (bool)$selectEventStmt->fetch(PDO::FETCH_ASSOC);
            if ($eventExists) {
                $eventsSkipped++;
                continue;
            }

            $insertEventStmt->execute([
                'event_id' => buildEventId($appointmentId, (string)$event['event_type']),
                'appointment_id' => $appointmentId,
                'event_type' => $event['event_type'],
                'timestamp' => $event['timestamp'],
                'from_datetime' => $event['from_datetime'],
                'to_datetime' => $event['to_datetime'],
                'from_start_at' => $event['from_start_at'],
                'from_end_at' => $event['from_end_at'],
                'to_start_at' => $event['to_start_at'],
                'to_end_at' => $event['to_end_at'],
                'observed_at' => $event['observed_at'],
                'motivo_code' => $event['motivo_code'],
                'motivo_text' => $event['motivo_text'],
                'notify_patient' => $event['notify_patient'],
                'contact_method' => $event['contact_method'],
                'actor_role' => SEED_ACTOR_ROLE,
                'actor_id' => SEED_ACTOR_ID,
                'channel_origin' => SEED_CHANNEL_ORIGIN,
                'notes' => $event['notes'],
            ]);
            $eventsInserted++;
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
echo "[seed] doctor_id=" . SEED_DOCTOR_ID . " consultorio_id=" . SEED_CONSULTORIO_ID . "\n";
echo "[seed] range=2026-04-27..2026-05-02\n";
echo "[seed] appointments inserted={$appointmentsInserted} skipped={$appointmentsSkipped}\n";
echo "[seed] events inserted={$eventsInserted} skipped={$eventsSkipped}\n";
