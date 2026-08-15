<?php
declare(strict_types=1);

namespace Platform\Audit\Read;

use DateTimeImmutable;
use DateTimeZone;
use Platform\Audit\Read\Contracts\AuditReadCursorSecretProvider;

final readonly class AuditReadCursor
{
    public function __construct(public string $createdAt, public string $eventId)
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $createdAt, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d\TH:i:s.u\Z') !== $createdAt) {
            throw new \InvalidArgumentException('invalid_audit_read_cursor_time');
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $eventId) !== 1) {
            throw new \InvalidArgumentException('invalid_audit_read_cursor_event');
        }
    }
}

/**
 * Opaque HMAC-protected keyset cursor.
 *
 * Provider mode is the authoritative versioned path and emits v2 only. The
 * raw-string constructor remains compatibility-only for the published v1
 * MP01G contract and must not be used by productive composition.
 */
final class AuditReadCursorCodec
{
    private ?string $legacySecret = null;
    private ?AuditReadCursorSecretProvider $provider = null;

    public function __construct(string|AuditReadCursorSecretProvider $key)
    {
        if (is_string($key)) {
            if (strlen($key) < 32) {
                throw new \InvalidArgumentException('audit_read_cursor_secret_too_short');
            }
            $this->legacySecret = $key;
            return;
        }
        $this->provider = $key;
    }

    public function encode(AuditReadCursor $cursor): string
    {
        if ($this->legacySecret !== null) {
            $body = json_encode(
                ['v' => 1, 'created_at' => $cursor->createdAt, 'event_id' => $cursor->eventId],
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            return $this->sign($body, $this->legacySecret);
        }

        $key = $this->providerKey();
        $body = json_encode(
            ['v' => 2, 'key_version' => $key['version'], 'created_at' => $cursor->createdAt, 'event_id' => $cursor->eventId],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        return $this->sign($body, $key['secret']);
    }

    public function decode(string $opaque): AuditReadCursor
    {
        [$body, $mac] = $this->transport($opaque);

        if ($this->legacySecret !== null) {
            $this->assertMac($body, $mac, $this->legacySecret);
            $decoded = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || array_keys($decoded) !== ['v', 'created_at', 'event_id'] || $decoded['v'] !== 1
                || !is_string($decoded['created_at']) || !is_string($decoded['event_id'])) {
                throw new \InvalidArgumentException('invalid_audit_read_cursor_payload');
            }
            return new AuditReadCursor($decoded['created_at'], $decoded['event_id']);
        }

        $key = $this->providerKey();
        $this->assertMac($body, $mac, $key['secret']);
        $decoded = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || ($decoded['v'] ?? null) !== 2) {
            throw new \InvalidArgumentException('audit_read_cursor_provider_requires_v2');
        }
        if (array_keys($decoded) !== ['v', 'key_version', 'created_at', 'event_id']
            || !is_string($decoded['key_version']) || !hash_equals($key['version'], $decoded['key_version'])
            || !is_string($decoded['created_at']) || !is_string($decoded['event_id'])) {
            throw new \InvalidArgumentException('invalid_audit_read_cursor_provider_payload');
        }
        return new AuditReadCursor($decoded['created_at'], $decoded['event_id']);
    }

    /** @return array{version:string,secret:string} */
    private function providerKey(): array
    {
        if ($this->provider === null) {
            throw new \LogicException('audit_read_cursor_provider_missing');
        }
        $key = $this->provider->currentAuditReadCursorKey();
        if (!isset($key['version'], $key['secret']) || !is_string($key['version']) || $key['version'] === '') {
            throw new \RuntimeException('audit_read_cursor_provider_version_invalid');
        }
        if (!is_string($key['secret']) || strlen($key['secret']) < 32) {
            throw new \RuntimeException('audit_read_cursor_provider_secret_invalid');
        }
        return ['version' => $key['version'], 'secret' => $key['secret']];
    }

    private function sign(string $body, string $secret): string
    {
        return self::base64UrlEncode($body) . '.' . self::base64UrlEncode(hash_hmac('sha256', $body, $secret, true));
    }

    /** @return array{0:string,1:string} */
    private function transport(string $opaque): array
    {
        if ($opaque === '' || strlen($opaque) > 1024 || substr_count($opaque, '.') !== 1) {
            throw new \InvalidArgumentException('invalid_audit_read_cursor');
        }
        [$encodedBody, $encodedMac] = explode('.', $opaque, 2);
        return [self::base64UrlDecode($encodedBody), self::base64UrlDecode($encodedMac)];
    }

    private function assertMac(string $body, string $mac, string $secret): void
    {
        if (strlen($mac) !== 32 || !hash_equals(hash_hmac('sha256', $body, $secret, true), $mac)) {
            throw new \InvalidArgumentException('audit_read_cursor_tampered');
        }
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            throw new \InvalidArgumentException('invalid_audit_read_cursor_encoding');
        }
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false || self::base64UrlEncode($decoded) !== $value) {
            throw new \InvalidArgumentException('invalid_audit_read_cursor_encoding');
        }
        return $decoded;
    }
}
