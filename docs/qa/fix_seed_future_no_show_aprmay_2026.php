<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/_lib/db.php';

date_default_timezone_set('America/Mexico_City');

const APPOINTMENTS_TABLE = 'agenda_appointments';
const EVENTS_TABLE = 'agenda_appointment_events';
const FLAGS_TABLE = 'agenda_patient_flags';
const INCIDENTS_TABLE = 'agenda_patient_incidents';
const APPOINTMENT_PREFIX = 'qa_aprmay26_d1c1_%';
const RANGE_FROM = '2026-04-27 00:00:00';
const RANGE_TO = '2026-05-03 00:00:00';

function fail(string $message): void
{
    fwrite(STDERR, "[fix][error] {$message}\n");
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

foreach ([APPOINTMENTS_TABLE, EVENTS_TABLE, FLAGS_TABLE, INCIDENTS_TABLE] as $tableName) {
    if (!tableExists($pdo, $tableName)) {
        fail("No existe tabla {$tableName}");
    }
}

$params = [
    ':prefix' => APPOINTMENT_PREFIX,
    ':from' => RANGE_FROM,
    ':to' => RANGE_TO,
];

$sqlUpdateAppointments = <<<SQL
UPDATE agenda_appointments
SET status = 'confirmed'
WHERE appointment_id LIKE :prefix
  AND start_at >= :from
  AND start_at < :to
  AND status IN ('no_show', 'finished', 'finalizada')
SQL;

$sqlDeleteEvents = <<<SQL
DELETE e
FROM agenda_appointment_events e
INNER JOIN agenda_appointments a
  ON a.appointment_id = e.appointment_id
WHERE a.appointment_id LIKE :prefix
  AND a.start_at >= :from
  AND a.start_at < :to
  AND e.event_type = 'appointment_no_show'
SQL;

$sqlDeleteFlags = <<<SQL
DELETE f
FROM agenda_patient_flags f
INNER JOIN agenda_appointments a
  ON a.appointment_id = f.source_appointment_id
WHERE a.appointment_id LIKE :prefix
  AND a.start_at >= :from
  AND a.start_at < :to
  AND f.flag_type = 'black'
  AND f.reason_code = 'no_show'
SQL;

$sqlDeleteIncidents = <<<SQL
DELETE i
FROM agenda_patient_incidents i
INNER JOIN agenda_appointments a
  ON a.appointment_id = i.appointment_id
WHERE a.appointment_id LIKE :prefix
  AND a.start_at >= :from
  AND a.start_at < :to
  AND i.incident_type = 'no_show'
SQL;

$sqlCountBadStatus = <<<SQL
SELECT COUNT(*) AS c
FROM agenda_appointments
WHERE appointment_id LIKE :prefix
  AND start_at >= :from
  AND start_at < :to
  AND status IN ('no_show', 'finished', 'finalizada')
SQL;

$sqlCountNoShowEvents = <<<SQL
SELECT COUNT(*) AS c
FROM agenda_appointment_events e
INNER JOIN agenda_appointments a
  ON a.appointment_id = e.appointment_id
WHERE a.appointment_id LIKE :prefix
  AND a.start_at >= :from
  AND a.start_at < :to
  AND e.event_type = 'appointment_no_show'
SQL;

$sqlCountNoShowFlags = <<<SQL
SELECT COUNT(*) AS c
FROM agenda_patient_flags f
INNER JOIN agenda_appointments a
  ON a.appointment_id = f.source_appointment_id
WHERE a.appointment_id LIKE :prefix
  AND a.start_at >= :from
  AND a.start_at < :to
  AND f.flag_type = 'black'
  AND f.reason_code = 'no_show'
SQL;

$sqlCountNoShowIncidents = <<<SQL
SELECT COUNT(*) AS c
FROM agenda_patient_incidents i
INNER JOIN agenda_appointments a
  ON a.appointment_id = i.appointment_id
WHERE a.appointment_id LIKE :prefix
  AND a.start_at >= :from
  AND a.start_at < :to
  AND i.incident_type = 'no_show'
SQL;

try {
    $pdo->beginTransaction();

    $stmtUpdate = $pdo->prepare($sqlUpdateAppointments);
    $stmtUpdate->execute($params);
    $updatedAppointments = $stmtUpdate->rowCount();

    $stmtDeleteEvents = $pdo->prepare($sqlDeleteEvents);
    $stmtDeleteEvents->execute($params);
    $deletedEvents = $stmtDeleteEvents->rowCount();

    $stmtDeleteFlags = $pdo->prepare($sqlDeleteFlags);
    $stmtDeleteFlags->execute($params);
    $deletedFlags = $stmtDeleteFlags->rowCount();

    $stmtDeleteIncidents = $pdo->prepare($sqlDeleteIncidents);
    $stmtDeleteIncidents->execute($params);
    $deletedIncidents = $stmtDeleteIncidents->rowCount();

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail('No fue posible aplicar corrección: ' . $e->getMessage());
}

$stmtCountBadStatus = $pdo->prepare($sqlCountBadStatus);
$stmtCountBadStatus->execute($params);
$remainingBadStatus = (int)$stmtCountBadStatus->fetchColumn();

$stmtCountEvents = $pdo->prepare($sqlCountNoShowEvents);
$stmtCountEvents->execute($params);
$remainingEvents = (int)$stmtCountEvents->fetchColumn();

$stmtCountFlags = $pdo->prepare($sqlCountNoShowFlags);
$stmtCountFlags->execute($params);
$remainingFlags = (int)$stmtCountFlags->fetchColumn();

$stmtCountIncidents = $pdo->prepare($sqlCountNoShowIncidents);
$stmtCountIncidents->execute($params);
$remainingIncidents = (int)$stmtCountIncidents->fetchColumn();

echo "[fix] OK\n";
echo "[fix] scope=qa_aprmay26_d1c1_* range=2026-04-27..2026-05-02\n";
echo "[fix] appointments status corrected={$updatedAppointments}\n";
echo "[fix] appointment_no_show events deleted={$deletedEvents}\n";
echo "[fix] no_show flags deleted={$deletedFlags}\n";
echo "[fix] no_show incidents deleted={$deletedIncidents}\n";
echo "[fix] remaining invalid statuses={$remainingBadStatus}\n";
echo "[fix] remaining no_show events={$remainingEvents}\n";
echo "[fix] remaining no_show flags={$remainingFlags}\n";
echo "[fix] remaining no_show incidents={$remainingIncidents}\n";
