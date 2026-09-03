<?php
declare(strict_types=1);

require_once __DIR__ . '/../adapters/SesPublicAgendaOtpAdapter.php';

use Agenda\Adapters\SesPublicAgendaOtpAdapter;

final class Pdb03FakeSesClient
{
    public array $requests = [];
    public mixed $result = ['MessageId' => 'internal-message-id'];
    public ?\Throwable $failure = null;

    public function sendEmail(array $request): mixed
    {
        $this->requests[] = $request;
        if ($this->failure !== null) throw $this->failure;
        return $this->result;
    }
}

function pdb03SesAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$client = new Pdb03FakeSesClient();
$adapter = new SesPublicAgendaOtpAdapter(
    $client,
    'us-east-1',
    'no-reply@mexicomedico.com',
    'México Médico'
);
$result = $adapter->deliver('email', 'recipient@example.test', '654321');
pdb03SesAssert($result->accepted() && $result->providerReference() === null, 'delivery accepted without exposing provider reference');
pdb03SesAssert($adapter->providerId() === 'amazon_ses_v2' && $adapter->configured(), 'SES v2 provider identity');
$request = $client->requests[0] ?? [];
pdb03SesAssert(($request['Destination']['ToAddresses'] ?? []) === ['recipient@example.test'], 'single exact recipient');
pdb03SesAssert(($request['FromEmailAddress'] ?? '') === '=?UTF-8?B?' . base64_encode('México Médico') . '?= <no-reply@mexicomedico.com>', 'exact sender');
pdb03SesAssert(!isset($request['ReplyToAddresses']) && !isset($request['EmailTags']), 'no reply-to or SES tags');
pdb03SesAssert(isset($request['Content']['Simple']) && !isset($request['Content']['Raw']), 'SendEmail simple content, not raw email');
$subject = $request['Content']['Simple']['Subject']['Data'] ?? '';
$text = $request['Content']['Simple']['Body']['Text']['Data'] ?? '';
$html = $request['Content']['Simple']['Body']['Html']['Data'] ?? '';
pdb03SesAssert($subject === 'Tu código para confirmar tu cita — México Médico', 'subject exact');
pdb03SesAssert(str_contains($text, '654321') && str_contains($html, '654321'), 'transient OTP in text and HTML bodies');
pdb03SesAssert(str_contains($text, '10 minutos') && str_contains($html, '10 minutos'), 'expiry communicated');
foreach (['diagnóstico', 'historial', 'fecha de nacimiento', 'SendRawEmail', 'smtp'] as $forbidden) {
    pdb03SesAssert(!str_contains(strtolower(json_encode($request, JSON_THROW_ON_ERROR)), strtolower($forbidden)), 'email excludes ' . $forbidden);
}

$client->failure = new RuntimeException('AWS detail recipient@example.test 654321');
$failed = $adapter->deliver('email', 'recipient@example.test', '654321');
pdb03SesAssert(!$failed->accepted() && $failed->reason() === 'delivery_unavailable', 'provider failure is generic');
pdb03SesAssert(!str_contains(json_encode($failed->toArray(), JSON_THROW_ON_ERROR), '654321'), 'failure excludes OTP');
pdb03SesAssert(!$adapter->deliver('sms', 'recipient@example.test', '654321')->accepted(), 'SMS rejected');
pdb03SesAssert(!$adapter->deliver('email', 'invalid', '654321')->accepted(), 'invalid recipient rejected');
pdb03SesAssert(!$adapter->deliver('email', 'recipient@example.test', 'not-six')->accepted(), 'invalid OTP rejected');

$source = file_get_contents(__DIR__ . '/../composition/PublicAgendaOtpComposition.php');
pdb03SesAssert(is_string($source) && str_contains($source, 'new \\Aws\\SesV2\\SesV2Client'), 'productive composition constructs SES v2 client');
foreach (['access_key', 'secret_key', 'credentials', 'smtp', 'SendRawEmail'] as $forbidden) {
    pdb03SesAssert(!str_contains($source, $forbidden), 'productive composition excludes ' . $forbidden);
}

echo "SesPublicAgendaOtpAdapterTest PASS\n";
