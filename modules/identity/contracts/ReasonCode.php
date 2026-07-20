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
    public const SESSION_MISSING = 'session_missing';
    public const SESSION_INVALID = 'session_invalid';
    public const SESSION_REVOKED = 'session_revoked';
    public const SESSION_SUPERSEDED = 'session_superseded';
    public const SESSION_IDLE_EXPIRED = 'session_idle_expired';
    public const SESSION_ABSOLUTE_EXPIRED = 'session_absolute_expired';
    public const SESSION_STORE_UNAVAILABLE = 'session_store_unavailable';
    public const CREDENTIAL_VERSION_MISMATCH = 'credential_version_mismatch';
    public const SESSION_LIMIT_REACHED = 'session_limit_reached';
    public const MEMBERSHIP_MISSING = 'membership_missing';
    public const MEMBERSHIP_INACTIVE = 'membership_inactive';
    public const PROFILE_MISMATCH = 'profile_mismatch';
    public const CAPABILITY_DENIED = 'capability_denied';

    public static function isKnown(string $value): bool
    {
        return in_array($value, [
            self::ALLOWED, self::INVALID_INPUT, self::INVALID_CREDENTIALS,
            self::ACCOUNT_NOT_ACTIVE, self::ACCOUNT_BLOCKED, self::ACCOUNT_DISABLED,
            self::CONSENT_MISSING, self::DUPLICATE_ACCOUNT, self::VERIFICATION_REQUIRED,
            self::TOKEN_INVALID, self::TOKEN_EXPIRED, self::TOKEN_CONSUMED,
            self::TOKEN_INVALIDATED, self::RATE_LIMITED, self::STORAGE_UNAVAILABLE,
            self::NOTIFICATION_UNAVAILABLE, self::UNSUPPORTED_OPERATION,
            self::SESSION_MISSING, self::SESSION_INVALID, self::SESSION_REVOKED,
            self::SESSION_SUPERSEDED, self::SESSION_IDLE_EXPIRED, self::SESSION_ABSOLUTE_EXPIRED,
            self::SESSION_STORE_UNAVAILABLE, self::CREDENTIAL_VERSION_MISMATCH,
            self::SESSION_LIMIT_REACHED, self::MEMBERSHIP_MISSING, self::MEMBERSHIP_INACTIVE,
            self::PROFILE_MISMATCH, self::CAPABILITY_DENIED,
        ], true);
    }
}
