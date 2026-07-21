<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class SupportAccessState
{
    public const REQUESTED = 'requested';
    public const PENDING_APPROVAL = 'pending_approval';
    public const APPROVED = 'approved';
    public const ACTIVE = 'active';
    public const EXPIRED = 'expired';
    public const REVOKED = 'revoked';
    public const UNDER_REVIEW = 'under_review';
    public const CLOSED = 'closed';
    public const DENIED = 'denied';
    /** @return list<string> */
    public static function all(): array { return [self::REQUESTED, self::PENDING_APPROVAL, self::APPROVED, self::ACTIVE, self::EXPIRED, self::REVOKED, self::UNDER_REVIEW, self::CLOSED, self::DENIED]; }
    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) throw new \InvalidArgumentException('unknown_support_access_state');
        return $value;
    }
}
