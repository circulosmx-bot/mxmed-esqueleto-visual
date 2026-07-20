<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class OneTimeTokenPurpose
{
    public const EMAIL_VERIFICATION = 'email_verification';
    public const PASSWORD_RECOVERY = 'password_recovery';

    public static function assertValid(string $purpose): string
    {
        if (!in_array($purpose, [self::EMAIL_VERIFICATION, self::PASSWORD_RECOVERY], true)) {
            throw new \InvalidArgumentException('unsupported_token_purpose');
        }
        return $purpose;
    }
}
