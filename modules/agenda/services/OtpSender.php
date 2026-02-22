<?php
namespace Agenda\Services;

interface OtpSender
{
    public function send(string $channel, string $to, string $otp, array $context = []): bool;
}

class DevOtpSender implements OtpSender
{
    public function send(string $channel, string $to, string $otp, array $context = []): bool
    {
        $maskedTo = $this->maskRecipient($to);
        $requestId = (string)($context['request_id'] ?? '');
        $doctorId = (string)($context['doctor_id'] ?? '');
        $consultorioId = (string)($context['consultorio_id'] ?? '');

        error_log(sprintf(
            '[agenda-public-otp] channel=%s to=%s otp=%s request_id=%s doctor_id=%s consultorio_id=%s',
            $channel,
            $maskedTo,
            $otp,
            $requestId,
            $doctorId,
            $consultorioId
        ));

        return true;
    }

    private function maskRecipient(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        $length = strlen($raw);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($raw, -4);
    }
}
