<?php
declare(strict_types=1);

/*
 * MySQL integration proof. Run only through qa/public_hold_expiry_concurrency.sh,
 * which provisions an isolated disposable MySQL database.
 */

require_once __DIR__ . '/../helpers/db_helpers.php';
require_once __DIR__ . '/../contracts/OtpProviderPort.php';
require_once __DIR__ . '/../controllers/PublicAppointmentsController.php';
require_once __DIR__ . '/../controllers/AvailabilityController.php';
require_once __DIR__ . '/../controllers/PublicOtpController.php';

use Agenda\Contracts\OtpDeliveryResult;
use Agenda\Contracts\OtpProviderPort;
use Agenda\Controllers\AvailabilityController;
use Agenda\Controllers\PublicAppointmentsController;
use Agenda\Controllers\PublicOtpController;

final class Pdb07bMemoryOtpProvider implements OtpProviderPort
{
    public array $deliveries = [];

    public function providerId(): string { return 'pdb07b_memory'; }
    public function configured(): bool { return true; }
    public function deliver(string $channel, string $destination, string $secret, array $context = []): OtpDeliveryResult
    {
        $this->deliveries[] = compact('channel', 'destination', 'secret', 'context');
        return new OtpDeliveryResult(true, 'accepted', null);
    }
}

function pdb07bAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pdb07bPdo(): PDO
{
    $dsn = trim((string)getenv('MXMED_PDB07B_TEST_DSN'));
    $user = (string)getenv('MXMED_PDB07B_TEST_USER');
    $pass = (string)getenv('MXMED_PDB07B_TEST_PASS');
    if ($dsn === '' || $user === '') {
        throw new RuntimeException('disposable MySQL test environment required');
    }
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function pdb07bPayload(string $startAt = '2030-01-07 10:00:00'): array
{
    $start = new DateTimeImmutable($startAt, new DateTimeZone('America/Mexico_City'));
    return [
        'doctor_id' => '1',
        'consultorio_id' => '1',
        'start_at' => $start->format('Y-m-d H:i:s'),
        'end_at' => $start->modify('+30 minutes')->format('Y-m-d H:i:s'),
        'visit_kind' => 'presencial',
        'patient_type' => 'first_time',
        'booker_is_patient' => true,
        'patient' => [
            'name' => 'Paciente sintético',
            'phone' => '5550000001',
            'email' => 'patient@example.test',
            'dob' => '1990-01-01',
            'gender' => 'F',
            'reason' => '',
        ],
        'booker' => [],
        'payment_mode' => 'none',
    ];
}

function pdb07bOtherPersonPayload(string $startAt = '2030-01-07 11:00:00'): array
{
    $payload = pdb07bPayload($startAt);
    $payload['booker_is_patient'] = false;
    $payload['patient'] = [
        'name' => 'Paciente alternativa',
        'phone' => '5550000002',
        'email' => 'other.patient@example.test',
        'dob' => '1992-02-02',
        'gender' => 'F',
        'reason' => '',
    ];
    $payload['booker'] = [
        'name' => 'Persona agenda',
        'phone' => '5550000003',
        'email' => 'booker.profile@example.test',
        'relationship' => 'madre',
    ];
    return $payload;
}

function pdb07bReserve(PDO $pdo, array $payload): array
{
    return (new PublicAppointmentsController(null, $pdo))->reserve($payload);
}

function pdb07bSetup(PDO $pdo): void
{
    foreach ([
        'agenda_public_appointment_flows',
        'agenda_public_otps',
        'agenda_appointment_events',
        'agenda_appointments',
        'agenda_availability_overrides',
        'consultorio_schedule',
        'patients_doctor_links',
        'patients_contacts',
        'patients_patients',
        'doctor_identity_map',
    ] as $table) {
        $pdo->exec('DROP TABLE IF EXISTS ' . $table);
    }

    $pdo->exec("CREATE TABLE agenda_appointments (
        appointment_id VARCHAR(64) NOT NULL PRIMARY KEY,
        doctor_id VARCHAR(64) NOT NULL,
        consultorio_id VARCHAR(64) NOT NULL,
        patient_id VARCHAR(64) NULL,
        start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL,
        modality VARCHAR(32) NOT NULL,
        status VARCHAR(32) NOT NULL,
        channel_origin VARCHAR(64) NOT NULL,
        created_by_role VARCHAR(32) NOT NULL,
        created_by_id VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL,
        active_slot_key VARCHAR(255) GENERATED ALWAYS AS (
            CASE WHEN status IN ('pending_otp','confirmed','pending','scheduled')
            THEN CONCAT(doctor_id, '|', consultorio_id, '|', DATE_FORMAT(start_at, '%Y-%m-%d %H:%i:%s'))
            ELSE NULL END
        ) STORED,
        UNIQUE KEY uniq_active_slot (active_slot_key),
        KEY idx_appointments_doctor_start (doctor_id, start_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE agenda_appointment_events (
        event_id VARCHAR(64) NOT NULL PRIMARY KEY,
        appointment_id VARCHAR(64) NOT NULL,
        event_type VARCHAR(64) NOT NULL,
        timestamp DATETIME NOT NULL,
        actor_role VARCHAR(32) NULL,
        actor_id VARCHAR(64) NULL,
        channel_origin VARCHAR(64) NULL,
        from_datetime DATETIME NULL,
        to_datetime DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE agenda_public_appointment_flows (
        flow_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        appointment_id VARCHAR(64) NOT NULL,
        doctor_id VARCHAR(64) NOT NULL,
        consultorio_id VARCHAR(64) NOT NULL,
        start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'pending_otp',
        otp_id BIGINT UNSIGNED NULL,
        otp_channel VARCHAR(16) NULL,
        otp_external_id VARCHAR(64) NULL,
        otp_verified_at DATETIME NULL,
        expires_at DATETIME NOT NULL,
        cancel_token VARCHAR(64) NOT NULL,
        payload_json JSON NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uniq_public_flow_appointment (appointment_id),
        KEY idx_public_flow_status_expires (status, expires_at),
        KEY idx_public_flow_slot (doctor_id, consultorio_id, start_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE agenda_public_otps (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        doctor_id VARCHAR(64) NOT NULL,
        contact_type VARCHAR(32) NOT NULL,
        contact_value VARCHAR(191) NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        verified TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE agenda_availability_overrides (
        override_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        doctor_id VARCHAR(64) NOT NULL,
        consultorio_id VARCHAR(64) NOT NULL,
        date_ymd DATE NOT NULL,
        type ENUM('open','close') NOT NULL,
        start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        KEY idx_override_doctor_consultorio_date (doctor_id, consultorio_id, date_ymd)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE consultorio_schedule (
        doctor_id VARCHAR(64) NOT NULL,
        consultorio_id VARCHAR(64) NOT NULL,
        weekday TINYINT NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE patients_patients (
        patient_id VARCHAR(64) NOT NULL PRIMARY KEY,
        status VARCHAR(32) NOT NULL,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE patients_contacts (
        contact_id VARCHAR(64) NOT NULL PRIMARY KEY,
        patient_id VARCHAR(64) NOT NULL,
        phone VARCHAR(32) NULL,
        email VARCHAR(191) NULL,
        is_primary TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE patients_doctor_links (
        doctor_id VARCHAR(64) NOT NULL,
        patient_id VARCHAR(64) NOT NULL,
        status VARCHAR(32) NOT NULL,
        ended_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE doctor_identity_map (
        legacy_doctor_id VARCHAR(64) NOT NULL PRIMARY KEY,
        canonical_doctor_id VARCHAR(64) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("INSERT INTO consultorio_schedule VALUES ('1','1',1,'09:00:00','12:00:00',1)");
    $pdo->exec("INSERT INTO patients_patients VALUES ('p_pdb07bpatient','active','2030-01-01 00:00:00')");
    $pdo->exec("INSERT INTO patients_contacts VALUES ('c_pdb07bpatient','p_pdb07bpatient','5550000001','patient@example.test',1,'2030-01-01 00:00:00')");
    $pdo->exec("INSERT INTO patients_doctor_links VALUES ('1','p_pdb07bpatient','active',NULL)");
    $pdo->exec("INSERT INTO patients_patients VALUES ('p_pdb07bother','active','2030-01-01 00:00:00')");
    $pdo->exec("INSERT INTO patients_contacts VALUES ('c_pdb07bother','p_pdb07bother','5550000002','other.patient@example.test',1,'2030-01-01 00:00:00')");
    $pdo->exec("INSERT INTO patients_doctor_links VALUES ('1','p_pdb07bother','active',NULL)");
}

function pdb07bAvailabilityContains(PDO $pdo, string $startAt): bool
{
    $result = (new AvailabilityController($pdo))->publicAvailability([
        'doctor_id' => '1',
        'consultorio_id' => '1',
        'mode' => 'next',
        'start_date' => '2030-01-07',
        'days' => 1,
        'limit_per_day' => 20,
        'slot_minutes' => 30,
    ]);
    pdb07bAssert(($result['ok'] ?? false) === true, 'public availability must respond in disposable DB');
    foreach (($result['data']['days'] ?? []) as $day) {
        foreach (($day['slots'] ?? []) as $slot) {
            if (($slot['start_at'] ?? '') === $startAt) {
                return true;
            }
        }
    }
    return false;
}

function pdb07bExpiryProof(PDO $pdo): array
{
    pdb07bSetup($pdo);
    $payload = pdb07bPayload();
    $first = pdb07bReserve($pdo, $payload);
    pdb07bAssert(($first['ok'] ?? false) === true, 'unexpired public reserve succeeds');
    $appointmentId = (string)($first['data']['appointment_id'] ?? '');
    pdb07bAssert($appointmentId !== '', 'unexpired reserve returns appointment id');
    pdb07bAssert(!pdb07bAvailabilityContains($pdo, $payload['start_at']), 'unexpired pending OTP blocks public availability');
    $second = pdb07bReserve($pdo, $payload);
    pdb07bAssert(
        ($second['ok'] ?? false) !== true,
        'unexpired pending OTP blocks a second reserve: ' . json_encode($second, JSON_THROW_ON_ERROR)
    );

    $pdo->prepare("UPDATE agenda_public_appointment_flows SET expires_at = '2000-01-01 00:00:00' WHERE appointment_id = :id")
        ->execute(['id' => $appointmentId]);
    $pdo->prepare("UPDATE agenda_appointments SET created_at = '2000-01-01 00:00:00' WHERE appointment_id = :id")
        ->execute(['id' => $appointmentId]);

    pdb07bAssert(pdb07bAvailabilityContains($pdo, $payload['start_at']), 'expired pending OTP no longer hides public availability without maintenance');
    $readOnlyState = $pdo->prepare("SELECT a.status AS appointment_status, f.status AS flow_status
        FROM agenda_appointments a
        INNER JOIN agenda_public_appointment_flows f ON f.appointment_id = a.appointment_id
        WHERE a.appointment_id = :id");
    $readOnlyState->execute(['id' => $appointmentId]);
    $readOnlyState = $readOnlyState->fetch();
    pdb07bAssert(
        ($readOnlyState['appointment_status'] ?? null) === 'pending_otp'
            && ($readOnlyState['flow_status'] ?? null) === 'pending_otp',
        'public availability is read-only and does not expire the pending hold itself'
    );
    $expiredConfirm = (new PublicAppointmentsController(null, $pdo))->confirm([
        'appointment_id' => $appointmentId,
        'otp_id' => '1',
        'code' => '123456',
    ]);
    pdb07bAssert(($expiredConfirm['ok'] ?? false) !== true, 'expired pending OTP cannot confirm');
    $reclaimed = pdb07bReserve($pdo, $payload);
    pdb07bAssert(($reclaimed['ok'] ?? false) === true, 'expired slot is physically reclaimed through reserve');
    return ['expired_appointment_id' => $appointmentId, 'reclaimed_appointment_id' => (string)$reclaimed['data']['appointment_id']];
}

function pdb07bProfileActivationProof(PDO $pdo): array
{
    pdb07bSetup($pdo);
    $provider = new Pdb07bMemoryOtpProvider();
    $selfReserve = (new PublicAppointmentsController(null, $pdo, $provider))->reserve(pdb07bPayload());
    pdb07bAssert(($selfReserve['ok'] ?? false) === true, 'profile self reserve creates a pending OTP appointment');
    $selfAppointmentId = (string)($selfReserve['data']['appointment_id'] ?? '');
    $selfOtp = (new PublicOtpController($pdo, $provider))->request(['appointment_id' => $selfAppointmentId]);
    pdb07bAssert(($selfOtp['ok'] ?? false) === true, 'profile self OTP request is appointment-bound');
    $selfOtpId = (string)($selfOtp['data']['otp_id'] ?? '');
    $selfCode = (string)($provider->deliveries[0]['secret'] ?? '');
    $selfConfirm = (new PublicAppointmentsController(null, $pdo, $provider))->confirm([
        'appointment_id' => $selfAppointmentId,
        'otp_id' => $selfOtpId,
        'code' => $selfCode,
    ]);
    pdb07bAssert(($selfConfirm['data']['status'] ?? '') === 'confirmed', 'profile self confirmation transitions the same appointment');
    $selfReplay = (new PublicAppointmentsController(null, $pdo, $provider))->confirm([
        'appointment_id' => $selfAppointmentId,
        'otp_id' => $selfOtpId,
        'code' => $selfCode,
    ]);
    pdb07bAssert(((array)($selfReplay['meta'] ?? []))['idempotent'] ?? false, 'profile confirmation replay remains idempotent');

    $otherReserve = (new PublicAppointmentsController(null, $pdo, $provider))->reserve(pdb07bOtherPersonPayload());
    pdb07bAssert(($otherReserve['ok'] ?? false) === true, 'profile other-person reserve creates a pending OTP appointment');
    $otherAppointmentId = (string)($otherReserve['data']['appointment_id'] ?? '');
    $flowPayload = $pdo->prepare('SELECT payload_json FROM agenda_public_appointment_flows WHERE appointment_id = :id');
    $flowPayload->execute(['id' => $otherAppointmentId]);
    $otherFlowPayload = json_decode((string)$flowPayload->fetchColumn(), true);
    pdb07bAssert(
        ($otherFlowPayload['booker_is_patient'] ?? null) === false
            && ($otherFlowPayload['booker']['relationship'] ?? '') === 'madre',
        'profile other-person relationship is serialized in the authoritative reserve flow'
    );
    $otherOtp = (new PublicOtpController($pdo, $provider))->request(['appointment_id' => $otherAppointmentId]);
    pdb07bAssert(($otherOtp['ok'] ?? false) === true, 'profile other-person OTP request is appointment-bound');
    $otherOtpId = (string)($otherOtp['data']['otp_id'] ?? '');
    $otherCode = (string)($provider->deliveries[1]['secret'] ?? '');
    $otherConfirm = (new PublicAppointmentsController(null, $pdo, $provider))->confirm([
        'appointment_id' => $otherAppointmentId,
        'otp_id' => $otherOtpId,
        'code' => $otherCode,
    ]);
    pdb07bAssert(($otherConfirm['data']['status'] ?? '') === 'confirmed', 'profile other-person confirmation transitions the same appointment');

    $activeRows = (int)$pdo->query("SELECT COUNT(*) FROM agenda_appointments WHERE status = 'confirmed' AND start_at IN ('2030-01-07 10:00:00', '2030-01-07 11:00:00')")->fetchColumn();
    pdb07bAssert($activeRows === 2, 'one confirmed active row exists for each profile test slot');
    return [
        'self_status' => 'confirmed',
        'other_status' => 'confirmed',
        'confirmed_active_rows' => $activeRows,
        'otp_delivery_count' => count($provider->deliveries),
    ];
}

function pdb07bConcurrentAttempt(string $readyFile, string $gateFile, string $resultFile, array $payload): never
{
    file_put_contents($readyFile, 'ready');
    $deadline = microtime(true) + 10;
    while (!is_file($gateFile)) {
        if (microtime(true) > $deadline) {
            file_put_contents($resultFile, json_encode(['ok' => false, 'error' => 'barrier_timeout']));
            exit(1);
        }
        usleep(1000);
    }
    $result = pdb07bReserve(pdb07bPdo(), $payload);
    file_put_contents($resultFile, json_encode([
        'ok' => (bool)($result['ok'] ?? false),
        'error' => $result['error'] ?? null,
        'appointment_id' => $result['data']['appointment_id'] ?? null,
    ], JSON_THROW_ON_ERROR));
    exit(($result['ok'] ?? false) === true || ($result['error'] ?? '') === 'slot_taken' ? 0 : 1);
}

function pdb07bConcurrencyRun(PDO $pdo, int $run): array
{
    pdb07bSetup($pdo);
    $temp = sys_get_temp_dir() . '/mxmed-pdb07b-' . bin2hex(random_bytes(6));
    mkdir($temp, 0700, true);
    $payload = pdb07bPayload('2030-01-07 11:00:00');
    $children = [];
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $pid = pcntl_fork();
        pdb07bAssert($pid !== -1, 'fork is available for parallel reserve proof');
        if ($pid === 0) {
            pdb07bConcurrentAttempt($temp . '/ready-' . $attempt, $temp . '/gate', $temp . '/result-' . $attempt, $payload);
        }
        $children[] = $pid;
    }
    $deadline = microtime(true) + 10;
    while (count(glob($temp . '/ready-*')) !== 2) {
        pdb07bAssert(microtime(true) <= $deadline, 'both reserve attempts reach the barrier');
        usleep(1000);
    }
    touch($temp . '/gate');
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        pdb07bAssert(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'parallel reserve process settles with expected result');
    }
    $results = [];
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $results[] = json_decode((string)file_get_contents($temp . '/result-' . $attempt), true, 512, JSON_THROW_ON_ERROR);
    }
    foreach (glob($temp . '/*') as $file) {
        unlink($file);
    }
    rmdir($temp);
    $successCount = count(array_filter($results, static fn(array $result): bool => $result['ok'] === true));
    $conflictCount = count(array_filter($results, static fn(array $result): bool => $result['error'] === 'slot_taken'));
    // A PDO handle inherited by a forked child may be closed when the child exits.
    // Reconnect for the parent-side ledger so this proof does not reuse that handle.
    $verificationPdo = pdb07bPdo();
    $activeCount = (int)$verificationPdo->query("SELECT COUNT(*) FROM agenda_appointments WHERE doctor_id='1' AND consultorio_id='1' AND start_at='2030-01-07 11:00:00' AND status IN ('pending_otp','confirmed','pending','scheduled')")->fetchColumn();
    $status = (string)$verificationPdo->query("SELECT status FROM agenda_appointments WHERE doctor_id='1' AND consultorio_id='1' AND start_at='2030-01-07 11:00:00' LIMIT 1")->fetchColumn();
    pdb07bAssert($successCount === 1 && $conflictCount === 1, 'parallel exact-slot reserve yields one success and one slot_taken');
    pdb07bAssert($activeCount === 1 && $status === 'pending_otp', 'one active pending_otp row remains after parallel reserve');
    return compact('run', 'successCount', 'conflictCount', 'activeCount', 'status');
}

$expiry = pdb07bExpiryProof(pdb07bPdo());
$profileActivation = pdb07bProfileActivationProof(pdb07bPdo());
$runs = [];
for ($run = 1; $run <= 5; $run++) {
    $runs[] = pdb07bConcurrencyRun(pdb07bPdo(), $run);
}

echo json_encode([
    'database_mode' => 'disposable',
    'expiry' => $expiry,
    'profile_activation' => $profileActivation,
    'concurrency_runs' => $runs,
    'concurrency_otp_request_count' => 0,
    'profile_test_fake_otp_delivery_count' => $profileActivation['otp_delivery_count'],
    'ses_send_call_count' => 0,
    'realtime_expiry_sleep_seconds' => 0,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
