<?php
declare(strict_types=1);

require_once __DIR__ . '/../contracts/OtpProviderPort.php';
require_once __DIR__ . '/../contracts/OtpRateLimitPolicy.php';
require_once __DIR__ . '/../adapters/CanonicalPublicAgendaAdapter.php';

use Agenda\Adapters\CanonicalPublicAgendaAdapter;

function cut01cErrorAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__, 3);
$adapter = new CanonicalPublicAgendaAdapter();
$causes = ['otp_not_found', 'otp_expired', 'otp_invalid', 'otp_locked', 'otp_mismatch', 'challenge_replay'];
$results = [];
foreach ($causes as $cause) {
    $results[$cause] = $adapter->homogeneousError('public_otp_verify', 'corr-cut01c');
}
$first = reset($results);
foreach ($results as $cause => $result) {
    cut01cErrorAssert($result === $first, 'sensitive cause is non-enumerating: ' . $cause);
}
cut01cErrorAssert($first['ok'] === false, 'error is not ok');
cut01cErrorAssert($first['error'] === 'verification_unavailable', 'error code homogeneous');
cut01cErrorAssert($first['message'] === 'verification could not be completed', 'message homogeneous');
cut01cErrorAssert($first['data'] === null && $first['http_status'] === 409, 'data and status homogeneous');
cut01cErrorAssert(array_keys($first['meta']) === ['route', 'correlation_reference'], 'metadata keys are minimal');
cut01cErrorAssert($first['meta'] === ['route' => 'public_otp_verify', 'correlation_reference' => 'corr-cut01c'], 'metadata is opaque');
cut01cErrorAssert($first === $adapter->homogeneousError('public_otp_verify', 'corr-cut01c'), 'error deterministic');
$unsafe = $adapter->homogeneousError('phone=+525500000000', 'person@example.test');
cut01cErrorAssert($unsafe['meta'] === ['route' => 'public_verification', 'correlation_reference' => ''], 'unsafe metadata fails closed');

$forbiddenKeys = ['otp_id', 'request_id', 'appointment_id', 'doctor_id', 'consultorio_id', 'phone', 'email', 'contact', 'recipient', 'attempts', 'attempts_remaining', 'table', 'sql'];
foreach ($forbiddenKeys as $key) {
    cut01cErrorAssert(!array_key_exists($key, $first['meta']) && !array_key_exists($key, $first), 'forbidden public key absent: ' . $key);
}

$appointmentsSource = file_get_contents($root . '/modules/agenda/controllers/PublicAppointmentsController.php');
cut01cErrorAssert(str_contains($appointmentsSource, 'CanonicalPublicAgendaAdapter::canonicalPublicAgendaEnabled'), 'alternate adapter flag remains dormant');
cut01cErrorAssert(!str_contains($appointmentsSource, 'new CanonicalPublicAgendaAdapter'), 'alternate adapter not instantiated');
cut01cErrorAssert(!str_contains($appointmentsSource, '->homogeneousError(') && !str_contains($appointmentsSource, '->readiness('), 'alternate adapter not executed');
$primaryOtpSource = file_get_contents($root . '/modules/agenda/controllers/PublicOtpController.php');
cut01cErrorAssert(str_contains($primaryOtpSource, 'PublicAgendaOtpComposition'), 'primary OTP delivery uses productive composition');
cut01cErrorAssert(!str_contains($primaryOtpSource, 'DevOtpSender'), 'primary OTP delivery has no development fallback');
cut01cErrorAssert(hash_file('sha256', $root . '/api/agenda/index.php') === '7b8eecc5b8eb1ee677394702a70b5e9c126898f1b0e4840f3110b5b1f3a884bd', 'Agenda routes unchanged');
cut01cErrorAssert(hash_file('sha256', $root . '/api/patients/index.php') === '4283afd8b138cd1abadcb7ff4024b6e501504f4de3b07a6cc7ecff300d130337', 'Patients routes unchanged');

echo "Cut01CErrorContractAntiEnumerationTest PASS\n";
