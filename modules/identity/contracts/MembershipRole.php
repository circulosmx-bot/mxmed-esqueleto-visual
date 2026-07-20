<?php
declare(strict_types=1);

namespace Identity\Contracts;

/**
 * Domain membership authority codes approved in C1.
 * This is an adapter contract, not a new operational role catalog or seed.
 */
final class MembershipRole
{
    public const OWNER = 'owner';
    public const ADMINISTRATOR = 'administrator';
    public const COLLABORATOR = 'collaborator';

    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) {
            throw new \InvalidArgumentException('unknown_membership_role');
        }
        return $value;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [self::OWNER, self::ADMINISTRATOR, self::COLLABORATOR];
    }
}
