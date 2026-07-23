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
        // Legacy development compatibility only; this is not a real OTP provider.
        error_log(sprintf(
            '[agenda-public-otp] channel=%s delivery_mode=dev_compatibility secret_logged=false',
            in_array($channel, ['sms', 'email'], true) ? $channel : 'unsupported'
        ));

        return true;
    }
}
