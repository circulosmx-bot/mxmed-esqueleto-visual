<?php
declare(strict_types=1);

namespace Agenda\Adapters;

use Agenda\Contracts\OtpDeliveryResult;
use Agenda\Contracts\OtpProviderPort;

require_once __DIR__ . '/../contracts/OtpProviderPort.php';

final class SesPublicAgendaOtpAdapter implements OtpProviderPort
{
    public const REGION = 'us-east-1';
    public const FROM_ADDRESS = 'no-reply@mexicomedico.com';
    public const FROM_NAME = 'México Médico';

    public function __construct(
        private object $client,
        private string $region,
        private string $fromAddress,
        private string $fromName
    ) {
        if (
            $this->region !== self::REGION
            || $this->fromAddress !== self::FROM_ADDRESS
            || $this->fromName !== self::FROM_NAME
            || !is_callable([$this->client, 'sendEmail'])
        ) {
            throw new \RuntimeException('agenda_otp_configuration_unavailable');
        }
    }

    public function providerId(): string
    {
        return 'amazon_ses_v2';
    }

    public function configured(): bool
    {
        return true;
    }

    public function deliver(string $channel, string $destination, string $secret, array $context = []): OtpDeliveryResult
    {
        if (
            $channel !== 'email'
            || trim($destination) !== $destination
            || filter_var($destination, FILTER_VALIDATE_EMAIL) === false
            || preg_match('/\A\d{6}\z/D', $secret) !== 1
        ) {
            return new OtpDeliveryResult(false, 'delivery_rejected', null);
        }

        $subject = 'Tu código para confirmar tu cita — México Médico';
        $text = "Tu solicitud de cita en México Médico está pendiente de confirmación.\n\n"
            . "Tu código es: {$secret}\n\n"
            . "El código vence en 10 minutos.\n\n"
            . 'Si no solicitaste esta cita, puedes ignorar este mensaje.';
        $safeCode = htmlspecialchars($secret, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="es"><body>'
            . '<h1>Confirma tu cita en México Médico</h1>'
            . '<p>Tu solicitud de cita está pendiente de confirmación.</p>'
            . '<p>Tu código es: <strong>' . $safeCode . '</strong></p>'
            . '<p>El código vence en 10 minutos.</p>'
            . '<p>Si no solicitaste esta cita, puedes ignorar este mensaje.</p>'
            . '</body></html>';

        try {
            $result = $this->client->sendEmail([
                'FromEmailAddress' => $this->encodedFromAddress(),
                'Destination' => ['ToAddresses' => [$destination]],
                'Content' => [
                    'Simple' => [
                        'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
                        'Body' => [
                            'Text' => ['Data' => $text, 'Charset' => 'UTF-8'],
                            'Html' => ['Data' => $html, 'Charset' => 'UTF-8'],
                        ],
                    ],
                ],
            ]);
            if ($this->messageId($result) === '') {
                return new OtpDeliveryResult(false, 'delivery_unavailable', null);
            }
            return new OtpDeliveryResult(true, 'accepted', null);
        } catch (\Throwable) {
            return new OtpDeliveryResult(false, 'delivery_unavailable', null);
        }
    }

    private function encodedFromAddress(): string
    {
        return '=?UTF-8?B?' . base64_encode($this->fromName) . '?= <' . $this->fromAddress . '>';
    }

    private function messageId(mixed $result): string
    {
        if (is_array($result)) return trim((string)($result['MessageId'] ?? ''));
        if ($result instanceof \ArrayAccess) return trim((string)($result['MessageId'] ?? ''));
        if (is_object($result) && method_exists($result, 'get')) return trim((string)$result->get('MessageId'));
        return '';
    }
}
