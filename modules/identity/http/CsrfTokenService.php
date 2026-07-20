<?php
declare(strict_types=1);

namespace Identity\Http;

final class CsrfTokenService
{
    public function __construct(private string $pepper, private int $ttlSeconds = 900)
    {
        if ($this->pepper === '' || $this->ttlSeconds < 60) throw new \InvalidArgumentException('csrf_pepper_required');
    }

    public function issue(): string
    {
        $payload = time() . '.' . self::base64Url(random_bytes(24));
        return $payload . '.' . hash_hmac('sha256', $payload, $this->pepper);
    }

    public function valid(string $token): bool
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3 || !ctype_digit($parts[0]) || strlen($parts[2]) !== 64) return false;
        $timestamp = (int)$parts[0];
        if ($timestamp < time() - $this->ttlSeconds || $timestamp > time() + 60) return false;
        $payload = $parts[0] . '.' . $parts[1];
        return hash_equals($parts[2], hash_hmac('sha256', $payload, $this->pepper));
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
