<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class SessionState
{
    public const ACTIVE = 'active';
    public const LOGGED_OUT = 'logged_out';
    public const REVOKED = 'revoked';
    public const SUPERSEDED = 'superseded';
    public const IDLE_EXPIRED = 'idle_expired';
    public const ABSOLUTE_EXPIRED = 'absolute_expired';

    public static function all(): array
    {
        return [self::ACTIVE, self::LOGGED_OUT, self::REVOKED, self::SUPERSEDED, self::IDLE_EXPIRED, self::ABSOLUTE_EXPIRED];
    }

    public static function assertValid(string $state): string
    {
        if (!in_array($state, self::all(), true)) throw new \InvalidArgumentException('unknown_session_state');
        return $state;
    }
}
