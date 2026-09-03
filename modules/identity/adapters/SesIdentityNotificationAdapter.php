<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\IdentityNotificationPort;
use Identity\Contracts\NotificationMessage;
use Identity\Contracts\OneTimeTokenPurpose;

final class SesIdentityNotificationAdapter implements IdentityNotificationPort
{
    public const REGION = 'us-east-1';
    public const FROM_ADDRESS = 'no-reply@mexicomedico.com';
    public const FROM_NAME = 'México Médico';

    public function __construct(
        private object $client,
        private string $region,
        private string $fromAddress,
        private string $fromName,
        private string $identityOrigin,
        private ?string $replyTo = null
    ) {
        $this->identityOrigin = rtrim($this->identityOrigin, '/');
        $parts = parse_url($this->identityOrigin);
        if (
            $this->region !== self::REGION
            || $this->fromAddress !== self::FROM_ADDRESS
            || $this->fromName !== self::FROM_NAME
            || !is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || trim((string)($parts['host'] ?? '')) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '')
            || ($this->replyTo !== null && filter_var($this->replyTo, FILTER_VALIDATE_EMAIL) === false)
            || !is_callable([$this->client, 'sendEmail'])
        ) {
            throw new \RuntimeException('notification_configuration_unavailable');
        }
    }

    public function send(NotificationMessage $message): void
    {
        try {
            if (trim($message->recipient()) !== $message->recipient() || filter_var($message->recipient(), FILTER_VALIDATE_EMAIL) === false) {
                throw new \RuntimeException('invalid_notification_recipient');
            }
            [$subject, $text, $html] = $this->content($message);
            $request = [
                'FromEmailAddress' => $this->encodedFromAddress(),
                'Destination' => ['ToAddresses' => [$message->recipient()]],
                'Content' => [
                    'Simple' => [
                        'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
                        'Body' => [
                            'Text' => ['Data' => $text, 'Charset' => 'UTF-8'],
                            'Html' => ['Data' => $html, 'Charset' => 'UTF-8'],
                        ],
                    ],
                ],
            ];
            if ($this->replyTo !== null) $request['ReplyToAddresses'] = [$this->replyTo];
            $result = $this->client->sendEmail($request);
            $messageId = $this->messageId($result);
            if ($messageId === '') throw new \RuntimeException('provider_message_id_missing');
        } catch (\Throwable) {
            throw new \RuntimeException('notification_unavailable');
        }
    }

    /** @return array{string,string,string} */
    private function content(NotificationMessage $message): array
    {
        [$path, $subject, $heading, $instruction, $expiry] = match ($message->purpose()) {
            OneTimeTokenPurpose::EMAIL_VERIFICATION => [
                '/public/identity/verificar-correo.php',
                'Verifica tu correo en México Médico',
                'Verifica tu correo electrónico',
                'Activa tu cuenta de México Médico mediante el siguiente enlace:',
                'Este enlace vence en 24 horas.',
            ],
            OneTimeTokenPurpose::PASSWORD_RECOVERY => [
                '/public/identity/restablecer-acceso.php',
                'Restablece tu acceso a México Médico',
                'Restablece tu acceso',
                'Continúa con la recuperación de tu cuenta mediante el siguiente enlace:',
                'Este enlace vence en 30 minutos.',
            ],
            default => throw new \RuntimeException('unsupported_notification_purpose'),
        };
        $url = $this->identityOrigin . $path . '?token=' . rawurlencode($message->token());
        $text = $heading . "\n\n" . $instruction . "\n" . $url . "\n\n" . $expiry
            . "\n\nSi no solicitaste esta acción, puedes ignorar este mensaje.";
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeHeading = htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeInstruction = htmlspecialchars($instruction, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeExpiry = htmlspecialchars($expiry, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="es"><body>'
            . '<h1>' . $safeHeading . '</h1><p>' . $safeInstruction . '</p>'
            . '<p><a href="' . $safeUrl . '">Continuar en México Médico</a></p>'
            . '<p>' . $safeExpiry . '</p>'
            . '<p>Si no solicitaste esta acción, puedes ignorar este mensaje.</p>'
            . '</body></html>';
        return [$subject, $text, $html];
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
