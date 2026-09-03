<?php
declare(strict_types=1);

require_once __DIR__ . '/../contracts/OtpProviderPort.php';
require_once __DIR__ . '/../controllers/PublicOtpController.php';
require_once __DIR__ . '/../controllers/PublicAppointmentsController.php';

use Agenda\Contracts\OtpDeliveryResult;
use Agenda\Contracts\OtpProviderPort;
use Agenda\Controllers\PublicOtpController;
use Agenda\Controllers\PublicAppointmentsController;

final class Pdb03MemoryOtpProvider implements OtpProviderPort
{
    public array $deliveries = [];
    public bool $accept = true;
    public bool $throw = false;

    public function providerId(): string { return 'test_memory'; }
    public function configured(): bool { return true; }
    public function deliver(string $channel, string $destination, string $secret, array $context = []): OtpDeliveryResult
    {
        $this->deliveries[] = compact('channel', 'destination', 'secret', 'context');
        if ($this->throw) throw new RuntimeException('provider detail ' . $destination . ' ' . $secret);
        return new OtpDeliveryResult($this->accept, $this->accept ? 'accepted' : 'provider secret failure', null);
    }
}

function pdb03Assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE agenda_public_otps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    doctor_id TEXT NOT NULL,
    contact_type TEXT NOT NULL,
    contact_value TEXT NOT NULL,
    code_hash TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    verified INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
)');
$pdo->exec('CREATE TABLE agenda_public_appointment_flows (
    flow_id INTEGER PRIMARY KEY AUTOINCREMENT,
    appointment_id TEXT UNIQUE NOT NULL,
    doctor_id TEXT NOT NULL,
    status TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    otp_id INTEGER NULL,
    otp_channel TEXT NULL,
    otp_external_id TEXT NULL,
    otp_verified_at TEXT NULL,
    updated_at TEXT NULL
)');
$pdo->exec('CREATE TABLE agenda_appointments (
    appointment_id TEXT PRIMARY KEY,
    status TEXT NOT NULL,
    channel_origin TEXT NOT NULL,
    created_at TEXT NOT NULL
)');

$now = new DateTimeImmutable('2026-09-02 12:00:00', new DateTimeZone('America/Mexico_City'));
$clock = static fn(): DateTimeImmutable => $now;
$insertFlow = $pdo->prepare('INSERT INTO agenda_public_appointment_flows
    (appointment_id, doctor_id, status, expires_at, payload_json, otp_id, otp_channel, otp_external_id, otp_verified_at, updated_at)
    VALUES (:appointment_id, :doctor_id, :status, :expires_at, :payload_json, NULL, NULL, NULL, NULL, :updated_at)');
$insertAppointment = $pdo->prepare('INSERT INTO agenda_appointments VALUES (:appointment_id, :status, :channel_origin, :created_at)');
$addFlow = static function (string $id, bool $bookerIsPatient, string $patientEmail, string $bookerEmail = '') use ($insertFlow, $insertAppointment): void {
    $insertFlow->execute([
        'appointment_id' => $id,
        'doctor_id' => 'doctor-public',
        'status' => 'pending_otp',
        'expires_at' => '2026-09-02 12:10:00',
        'payload_json' => json_encode([
            'booker_is_patient' => $bookerIsPatient,
            'patient' => ['email' => $patientEmail],
            'booker' => ['email' => $bookerEmail],
        ], JSON_THROW_ON_ERROR),
        'updated_at' => '2026-09-02 12:00:00',
    ]);
    $insertAppointment->execute([
        'appointment_id' => $id,
        'status' => 'pending_otp',
        'channel_origin' => 'public_agenda',
        'created_at' => '2026-09-02 12:00:00',
    ]);
};

$addFlow('appointment-main', false, 'patient@example.test', 'BOOKER@example.test');
$provider = new Pdb03MemoryOtpProvider();
$controller = new PublicOtpController($pdo, $provider, $clock);
$response = $controller->request([
    'appointment_id' => 'appointment-main',
    'contact_type' => 'email',
    'contact_value' => 'attacker@example.test',
]);
pdb03Assert($response['ok'] === true, 'OTP request succeeds for pending booking');
pdb03Assert(count($provider->deliveries) === 1, 'delivery invoked exactly once');
$delivery = $provider->deliveries[0];
pdb03Assert($delivery['channel'] === 'email', 'email is the only delivery channel');
pdb03Assert($delivery['destination'] === 'booker@example.test', 'recipient resolved from persisted booking state');
pdb03Assert($delivery['destination'] !== 'attacker@example.test', 'caller cannot redirect delivery');
pdb03Assert(preg_match('/\A\d{6}\z/D', $delivery['secret']) === 1, 'six digit OTP delivered');
pdb03Assert(($response['data']['destination_hint'] ?? '') === 'b***@example.test', 'only masked destination returned');
$serializedResponse = json_encode($response, JSON_THROW_ON_ERROR);
pdb03Assert(!str_contains($serializedResponse, $delivery['secret']), 'plaintext OTP absent from HTTP response');
pdb03Assert(!str_contains($serializedResponse, 'booker@example.test'), 'full email absent from HTTP response');
pdb03Assert(!str_contains($serializedResponse, 'MessageId') && !str_contains($serializedResponse, 'code_hash'), 'provider and hash internals absent');

$otpId = (int)$response['data']['otp_id'];
$otpRow = $pdo->query('SELECT * FROM agenda_public_otps WHERE id = ' . $otpId)->fetch(PDO::FETCH_ASSOC);
pdb03Assert(is_array($otpRow), 'OTP hash persisted');
pdb03Assert(!array_key_exists('code', $otpRow) && !array_key_exists('otp', $otpRow), 'no plaintext OTP column');
pdb03Assert($otpRow['code_hash'] !== $delivery['secret'] && password_verify($delivery['secret'], $otpRow['code_hash']), 'hash-only persistence');
$flowOtp = (int)$pdo->query("SELECT otp_id FROM agenda_public_appointment_flows WHERE appointment_id = 'appointment-main'")->fetchColumn();
pdb03Assert($flowOtp === $otpId, 'challenge bound to exact pending booking');

$wrong = $controller->verify(['otp_id' => (string)$otpId, 'code' => '000000']);
pdb03Assert($wrong['ok'] === false && $wrong['error'] === 'invalid_code', 'wrong OTP rejected');
$correct = $controller->verify(['otp_id' => (string)$otpId, 'code' => $delivery['secret']]);
pdb03Assert($correct['ok'] === true && $correct['data']['verified'] === true, 'captured delivered OTP verifies');
$appointments = new PublicAppointmentsController(null, $pdo, $provider, $clock);
$confirmed = $appointments->confirm([
    'appointment_id' => 'appointment-main',
    'otp_id' => (string)$otpId,
    'code' => $delivery['secret'],
]);
pdb03Assert($confirmed['ok'] === true && $confirmed['data']['status'] === 'confirmed', 'flow-bound delivered OTP confirms appointment');
$appointmentStatus = $pdo->query("SELECT status FROM agenda_appointments WHERE appointment_id = 'appointment-main'")->fetchColumn();
pdb03Assert($appointmentStatus === 'confirmed', 'appointment durable status becomes confirmed');

$addFlow('appointment-rate', true, 'rate@example.test');
$rateProvider = new Pdb03MemoryOtpProvider();
$rateController = new PublicOtpController($pdo, $rateProvider, $clock);
$firstRate = $rateController->request(['appointment_id' => 'appointment-rate']);
$secondRate = $rateController->request(['appointment_id' => 'appointment-rate']);
pdb03Assert($firstRate['ok'] === true && $secondRate['error'] === 'rate_limited', '60 second resend limit enforced');
pdb03Assert(count($rateProvider->deliveries) === 1, 'rate-limited request does not deliver');
$later = $now->modify('+61 seconds');
$replacementController = new PublicOtpController($pdo, $rateProvider, static fn(): DateTimeImmutable => $later);
$replacement = $replacementController->request(['appointment_id' => 'appointment-rate']);
pdb03Assert($replacement['ok'] === true && $replacement['data']['otp_id'] !== $firstRate['data']['otp_id'], 'request after cooldown replaces flow-bound challenge');
$replacementBound = (int)$pdo->query("SELECT otp_id FROM agenda_public_appointment_flows WHERE appointment_id = 'appointment-rate'")->fetchColumn();
pdb03Assert($replacementBound === (int)$replacement['data']['otp_id'], 'only replacement challenge remains bound to booking');
$replacementAppointments = new PublicAppointmentsController(null, $pdo, $rateProvider, static fn(): DateTimeImmutable => $later);
$oldChallengeConfirm = $replacementAppointments->confirm([
    'appointment_id' => 'appointment-rate',
    'otp_id' => (string)$firstRate['data']['otp_id'],
    'code' => $rateProvider->deliveries[0]['secret'],
]);
pdb03Assert($oldChallengeConfirm['error'] === 'otp_mismatch', 'replaced challenge cannot confirm booking');

$addFlow('appointment-failure', true, 'failure@example.test');
$failureProvider = new Pdb03MemoryOtpProvider();
$failureProvider->throw = true;
$failureController = new PublicOtpController($pdo, $failureProvider, $clock);
$failed = $failureController->request(['appointment_id' => 'appointment-failure']);
pdb03Assert($failed['ok'] === false && $failed['error'] === 'otp_delivery_unavailable', 'transport failure is generic');
$failedJson = json_encode($failed, JSON_THROW_ON_ERROR);
pdb03Assert(!str_contains($failedJson, 'failure@example.test') && !preg_match('/\d{6}/', $failedJson), 'failure exposes no provider detail or OTP');
$failureFlow = $pdo->query("SELECT otp_id, status FROM agenda_public_appointment_flows WHERE appointment_id = 'appointment-failure'")->fetch(PDO::FETCH_ASSOC);
pdb03Assert($failureFlow['otp_id'] === null && $failureFlow['status'] === 'pending_otp', 'failed challenge removed and booking remains pending');
$failureAppointment = $pdo->query("SELECT status FROM agenda_appointments WHERE appointment_id = 'appointment-failure'")->fetchColumn();
pdb03Assert($failureAppointment === 'pending_otp', 'delivery failure does not confirm appointment');

$addFlow('appointment-attempts', true, 'attempts@example.test');
$attemptProvider = new Pdb03MemoryOtpProvider();
$attemptController = new PublicOtpController($pdo, $attemptProvider, $clock);
$attemptRequest = $attemptController->request(['appointment_id' => 'appointment-attempts']);
for ($attempt = 1; $attempt <= 5; $attempt++) {
    $attemptResult = $attemptController->verify(['otp_id' => (string)$attemptRequest['data']['otp_id'], 'code' => '000000']);
}
pdb03Assert($attemptResult['error'] === 'too_many_attempts' && ($attemptResult['meta']->attempts ?? 0) === 5, 'maximum five attempts preserved');

$addFlow('appointment-expired', true, 'expired@example.test');
$expiredProvider = new Pdb03MemoryOtpProvider();
$expiredController = new PublicOtpController($pdo, $expiredProvider, $clock);
$expiredRequest = $expiredController->request(['appointment_id' => 'appointment-expired']);
$pdo->exec("UPDATE agenda_public_otps SET expires_at = '2026-09-02 11:59:59' WHERE id = " . (int)$expiredRequest['data']['otp_id']);
$expired = $expiredController->verify(['otp_id' => (string)$expiredRequest['data']['otp_id'], 'code' => $expiredProvider->deliveries[0]['secret']]);
pdb03Assert($expired['error'] === 'expired', 'expired OTP rejected');

$source = file_get_contents(__DIR__ . '/../controllers/PublicAppointmentsController.php');
pdb03Assert(is_string($source) && str_contains($source, "(int)(\$flow['otp_id'] ?? 0) !== (int)\$otpId"), 'confirm requires exact flow-bound OTP');
pdb03Assert(str_contains($source, "'status' => 'confirmed'"), 'existing confirm transition preserved');
$wizard = file_get_contents(__DIR__ . '/../../../assets/js/public/agenda-wizard.js');
pdb03Assert(is_string($wizard) && str_contains($wizard, 'appointment_id: state.appointmentId'), 'wizard requests OTP by booking identifier');
pdb03Assert(!str_contains($wizard, "contact_value: getOtpContactValue"), 'wizard cannot nominate delivery destination');
$listingTest = file_get_contents(__DIR__ . '/../../profiles/tests/PublicDiscoveryPageTest.php');
pdb03Assert(is_string($listingTest) && str_contains($listingTest, '/profiles/doctor.php?doctor_id=doctor-fixture'), 'PDB-02 transition remains present');

echo "PublicAgendaEmailOtpDeliveryTest PASS\n";
