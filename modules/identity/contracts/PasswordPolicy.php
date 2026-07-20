<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 128;

    public static function assertValid(string $password, string $emailNormalized): void
    {
        $length = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('invalid_password_length');
        }
        if (strpos($password, "\0") !== false) {
            throw new \InvalidArgumentException('invalid_password_input');
        }
        $normalizedPassword = function_exists('mb_strtolower') ? mb_strtolower($password, 'UTF-8') : strtolower($password);
        if ($normalizedPassword === $emailNormalized) {
            throw new \InvalidArgumentException('password_matches_email');
        }
    }
}
