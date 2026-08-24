<?php
declare(strict_types=1);

namespace Identity\Http;

use Identity\Contracts\Clock;
use Identity\Contracts\SessionTokenDigest;
use Identity\Contracts\SystemClock;

final class CsrfTokenService
{
    private const PRE_AUTH = 'preauth';
    private const AUTHENTICATED = 'session';

    public function __construct(
        private string $pepper,
        private int $ttlSeconds = 900,
        private Clock $clock = new SystemClock(),
        private string $trustedOrigin = 'https://localhost'
    ) {
        if ($this->pepper === '' || strlen($this->pepper) < 32 || $this->ttlSeconds < 60 || $this->ttlSeconds > 900) throw new \InvalidArgumentException('csrf_configuration_required');
        $parts = parse_url($this->trustedOrigin);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || trim((string)($parts['host'] ?? '')) === '') throw new \InvalidArgumentException('csrf_trusted_origin_required');
        $this->trustedOrigin = rtrim($this->trustedOrigin, '/');
    }

    /** Legacy preview alias; productive callers should name the purpose explicitly. */
    public function issue(): string { return $this->issuePreAuth(); }
    public function valid(string $token): bool { return $this->validPreAuth($token); }

    public function issuePreAuth(): string
    {
        return $this->issueFor(self::PRE_AUTH, hash('sha256', $this->trustedOrigin));
    }

    public function validPreAuth(string $token): bool
    {
        return $this->validFor($token, self::PRE_AUTH, hash('sha256', $this->trustedOrigin));
    }

    public function issueAuthenticated(SessionTokenDigest $session): string
    {
        return $this->issueFor(self::AUTHENTICATED, (string)$session);
    }

    public function validAuthenticated(string $token, SessionTokenDigest $session): bool
    {
        return $this->validFor($token, self::AUTHENTICATED, (string)$session);
    }

    private function issueFor(string $purpose, string $binding): string
    {
        $timestamp = (string)$this->clock->now()->getTimestamp();
        $nonce = self::base64Url(random_bytes(24));
        $public = $purpose . '.' . $timestamp . '.' . $nonce;
        return $public . '.' . hash_hmac('sha256', $public . '.' . $binding, $this->pepper);
    }

    private function validFor(string $token, string $purpose, string $binding): bool
    {
        $parts = explode('.', trim($token));
        if (
            count($parts) !== 4
            || $parts[0] !== $purpose
            || !ctype_digit($parts[1])
            || preg_match('/^[A-Za-z0-9_-]{32}$/D', $parts[2]) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $parts[3]) !== 1
        ) return false;
        $timestamp = (int)$parts[1];
        $now = $this->clock->now()->getTimestamp();
        if ($timestamp < $now - $this->ttlSeconds || $timestamp > $now + 60) return false;
        $public = $parts[0] . '.' . $parts[1] . '.' . $parts[2];
        return hash_equals($parts[3], hash_hmac('sha256', $public . '.' . $binding, $this->pepper));
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
