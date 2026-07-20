<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class SessionPolicy
{
    public const IDLE_TTL_SECONDS = 3600;
    public const ABSOLUTE_TTL_SECONDS = 43200;
    public const TOUCH_INTERVAL_SECONDS = 300;
    public const MAX_ACTIVE_SESSIONS = 5;

    public function __construct(
        private int $idleTtlSeconds = self::IDLE_TTL_SECONDS,
        private int $absoluteTtlSeconds = self::ABSOLUTE_TTL_SECONDS,
        private int $touchIntervalSeconds = self::TOUCH_INTERVAL_SECONDS,
        private int $maximumActiveSessions = self::MAX_ACTIVE_SESSIONS
    ) {
        if ($this->idleTtlSeconds !== 3600 || $this->absoluteTtlSeconds !== 43200 || $this->touchIntervalSeconds !== 300 || $this->maximumActiveSessions !== 5) {
            throw new \InvalidArgumentException('invalid_session_policy');
        }
    }

    public function idleTtlSeconds(): int { return $this->idleTtlSeconds; }
    public function absoluteTtlSeconds(): int { return $this->absoluteTtlSeconds; }
    public function touchIntervalSeconds(): int { return $this->touchIntervalSeconds; }
    public function maximumActiveSessions(): int { return $this->maximumActiveSessions; }
}
