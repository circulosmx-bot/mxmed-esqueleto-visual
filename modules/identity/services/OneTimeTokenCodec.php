<?php
declare(strict_types=1);

namespace Identity\Services;

final class OneTimeTokenCodec
{
    public static function issue(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        if (preg_match('/^[A-Za-z0-9_-]{40,64}$/', $token) !== 1) throw new \InvalidArgumentException('invalid_one_time_token');
        return hash('sha256', $token);
    }

    public static function id(string $prefix): string
    {
        return $prefix . bin2hex(random_bytes(12));
    }
}
