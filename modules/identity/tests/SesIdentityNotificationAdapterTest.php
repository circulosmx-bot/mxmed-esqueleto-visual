<?php
declare(strict_types=1);

require_once __DIR__ . '/../contracts/OneTimeTokenPurpose.php';
require_once __DIR__ . '/../contracts/NotificationMessage.php';
require_once __DIR__ . '/../contracts/IdentityNotificationPort.php';
require_once __DIR__ . '/../services/OneTimeTokenCodec.php';
require_once __DIR__ . '/../adapters/SesIdentityNotificationAdapter.php';

use Identity\Adapters\SesIdentityNotificationAdapter;
use Identity\Contracts\NotificationMessage;
use Identity\Contracts\OneTimeTokenPurpose;
use Identity\Services\OneTimeTokenCodec;

final class Eotp02FakeSesClient
{
    public array $requests = [];
    public mixed $result = ['MessageId' => 'safe-message-reference'];
    public ?\Throwable $failure = null;

    public function sendEmail(array $request): mixed
    {
        $this->requests[] = $request;
        if ($this->failure !== null) throw $this->failure;
        return $this->result;
    }
}

function eotp02Assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function eotp02ThrowsSafe(callable $operation, array $secrets): void
{
    try { $operation(); throw new RuntimeException('expected_failure'); }
    catch (Throwable $exception) {
        eotp02Assert($exception->getMessage() === 'notification_unavailable', 'failure boundary exact');
        foreach ($secrets as $secret) eotp02Assert(!str_contains($exception->getMessage(), $secret), 'failure excludes sensitive input');
    }
}

$client = new Eotp02FakeSesClient();
$adapter = new SesIdentityNotificationAdapter(
    $client,
    'us-east-1',
    'no-reply@mexicomedico.com',
    'México Médico',
    'https://mexicomedico.com'
);
$token = OneTimeTokenCodec::issue();
eotp02Assert(strlen($token) === 43 && preg_match('/^[A-Za-z0-9_-]{43}$/D', $token) === 1, 'high entropy token unchanged');

$adapter->send(new NotificationMessage(OneTimeTokenPurpose::EMAIL_VERIFICATION, 'recipient@example.test', $token, '2099-01-01 00:00:00'));
$verification = $client->requests[0] ?? [];
eotp02Assert(array_keys($verification['Destination']) === ['ToAddresses'] && $verification['Destination']['ToAddresses'] === ['recipient@example.test'], 'single exact recipient');
eotp02Assert($verification['FromEmailAddress'] === '=?UTF-8?B?' . base64_encode('México Médico') . '?= <no-reply@mexicomedico.com>', 'exact encoded from identity');
eotp02Assert(!isset($verification['ReplyToAddresses']) && !isset($verification['EmailTags']), 'reply-to and tags absent');
$verificationText = $verification['Content']['Simple']['Body']['Text']['Data'] ?? '';
$verificationHtml = $verification['Content']['Simple']['Body']['Html']['Data'] ?? '';
eotp02Assert(str_contains($verificationText, 'https://mexicomedico.com/public/identity/verificar-correo.php?token=' . $token), 'verification URL exact');
eotp02Assert(str_contains($verificationText, '24 horas') && str_contains($verificationHtml, '24 horas'), 'verification text and html');
eotp02Assert(($verification['Content']['Simple']['Subject']['Charset'] ?? '') === 'UTF-8', 'UTF-8 subject');

$recoveryToken = OneTimeTokenCodec::issue();
$adapter->send(new NotificationMessage(OneTimeTokenPurpose::PASSWORD_RECOVERY, 'recovery@example.test', $recoveryToken, '2099-01-01 00:00:00'));
$recovery = $client->requests[1] ?? [];
$recoveryText = $recovery['Content']['Simple']['Body']['Text']['Data'] ?? '';
$recoveryHtml = $recovery['Content']['Simple']['Body']['Html']['Data'] ?? '';
eotp02Assert(str_contains($recoveryText, 'https://mexicomedico.com/public/identity/restablecer-acceso.php?token=' . $recoveryToken), 'recovery URL exact');
eotp02Assert(str_contains($recoveryText, '30 minutos') && str_contains($recoveryHtml, '30 minutos'), 'recovery text and html');

$client->result = ['MessageId' => ''];
eotp02ThrowsSafe(fn() => $adapter->send(new NotificationMessage(OneTimeTokenPurpose::EMAIL_VERIFICATION, 'missing-id@example.test', $token, '2099-01-01 00:00:00')), ['missing-id@example.test', $token]);
$client->failure = new RuntimeException('timeout recipient@example.test ' . $token);
eotp02ThrowsSafe(fn() => $adapter->send(new NotificationMessage(OneTimeTokenPurpose::EMAIL_VERIFICATION, 'recipient@example.test', $token, '2099-01-01 00:00:00')), ['recipient@example.test', $token]);

$unsupported = new NotificationMessage(OneTimeTokenPurpose::EMAIL_VERIFICATION, 'recipient@example.test', $token, '2099-01-01 00:00:00');
(new ReflectionProperty(NotificationMessage::class, 'purpose'))->setValue($unsupported, 'unsupported');
$client->failure = null;
$client->result = ['MessageId' => 'safe-message-reference'];
eotp02ThrowsSafe(fn() => $adapter->send($unsupported), ['recipient@example.test', $token]);

foreach ([
    ['mx-central-1', 'no-reply@mexicomedico.com', 'México Médico'],
    ['us-east-1', 'no-reply@other.example', 'México Médico'],
    ['us-east-1', 'no-reply@mexicomedico.com', ''],
] as [$region, $from, $name]) {
    try {
        new SesIdentityNotificationAdapter(new Eotp02FakeSesClient(), $region, $from, $name, 'https://mexicomedico.com');
        throw new RuntimeException('invalid_configuration_accepted');
    } catch (Throwable $exception) {
        eotp02Assert($exception->getMessage() === 'notification_configuration_unavailable', 'invalid adapter configuration rejected');
    }
}

echo "SesIdentityNotificationAdapterTest PASS\n";
