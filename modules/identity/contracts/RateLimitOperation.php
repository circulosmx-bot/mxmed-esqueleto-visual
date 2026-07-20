<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class RateLimitOperation
{
    public const REGISTRATION = 'registration';
    public const EMAIL_VERIFICATION_RESEND = 'email_verification_resend';
    public const CREDENTIAL_CHECK = 'credential_check';
    public const RECOVERY_REQUEST = 'recovery_request';
    public const TOKEN_CONSUME = 'token_consume';
    public const PASSWORD_RESET = 'password_reset';
}
