<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class ReasonCode
{
    public const ALLOWED = 'allowed';
    public const INVALID_INPUT = 'invalid_input';
    public const INVALID_CREDENTIALS = 'invalid_credentials';
    public const ACCOUNT_NOT_ACTIVE = 'account_not_active';
    public const ACCOUNT_BLOCKED = 'account_blocked';
    public const ACCOUNT_DISABLED = 'account_disabled';
    public const CONSENT_MISSING = 'consent_missing';
    public const DUPLICATE_ACCOUNT = 'duplicate_account';
    public const VERIFICATION_REQUIRED = 'verification_required';
    public const TOKEN_INVALID = 'token_invalid';
    public const TOKEN_EXPIRED = 'token_expired';
    public const TOKEN_CONSUMED = 'token_consumed';
    public const TOKEN_INVALIDATED = 'token_invalidated';
    public const RATE_LIMITED = 'rate_limited';
    public const STORAGE_UNAVAILABLE = 'storage_unavailable';
    public const NOTIFICATION_UNAVAILABLE = 'notification_unavailable';
    public const UNSUPPORTED_OPERATION = 'unsupported_operation';

    public static function isKnown(string $value): bool
    {
        return in_array($value, [
            self::ALLOWED, self::INVALID_INPUT, self::INVALID_CREDENTIALS,
            self::ACCOUNT_NOT_ACTIVE, self::ACCOUNT_BLOCKED, self::ACCOUNT_DISABLED,
            self::CONSENT_MISSING, self::DUPLICATE_ACCOUNT, self::VERIFICATION_REQUIRED,
            self::TOKEN_INVALID, self::TOKEN_EXPIRED, self::TOKEN_CONSUMED,
            self::TOKEN_INVALIDATED, self::RATE_LIMITED, self::STORAGE_UNAVAILABLE,
            self::NOTIFICATION_UNAVAILABLE, self::UNSUPPORTED_OPERATION,
        ], true);
    }
}
