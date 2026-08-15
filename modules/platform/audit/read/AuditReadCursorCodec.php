<?php
declare(strict_types=1);

namespace Platform\Audit\Read;

use DateTimeImmutable;
use DateTimeZone;

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

/** Opaque HMAC-protected keyset cursor; secret material is injected and never returned. */
final class AuditReadCursorCodec
{
    public function __construct(private string $secret)
    {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('audit_read_cursor_secret_too_short');
        }
    }

    public function encode(AuditReadCursor $cursor): string
    {
        $body = json_encode(['v' => 1, 'created_at' => $cursor->createdAt, 'event_id' => $cursor->eventId], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return self::base64UrlEncode($body) . '.' . self::base64UrlEncode(hash_hmac('sha256', $body, $this->secret, true));
    }

    public function decode(string $opaque): AuditReadCursor
    {
        if ($opaque === '' || strlen($opaque) > 1024 || substr_count($opaque, '.') !== 1) {
            throw new \InvalidArgumentException('invalid_audit_read_cursor');
        }
        [$encodedBody, $encodedMac] = explode('.', $opaque, 2);
        $body = self::base64UrlDecode($encodedBody);
        $mac = self::base64UrlDecode($encodedMac);
        if (strlen($mac) !== 32 || !hash_equals(hash_hmac('sha256', $body, $this->secret, true), $mac)) {
            throw new \InvalidArgumentException('audit_read_cursor_tampered');
        }
        $decoded = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_keys($decoded) !== ['v', 'created_at', 'event_id'] || $decoded['v'] !== 1
            || !is_string($decoded['created_at']) || !is_string($decoded['event_id'])) {
            throw new \InvalidArgumentException('invalid_audit_read_cursor_payload');
        }
        return new AuditReadCursor($decoded['created_at'], $decoded['event_id']);
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
