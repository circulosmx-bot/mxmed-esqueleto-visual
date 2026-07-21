<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class DispositionReason
{
    public const ALLOWED_FOR_SIMULATION = 'allowed_for_simulation';
    public const POLICY_UNRESOLVED = 'policy_unresolved';
    public const SOURCE_UNRESOLVED = 'source_unresolved';
    public const SOURCE_CONFLICT = 'source_conflict';
    public const RETENTION_UNRESOLVED = 'retention_unresolved';
    public const ANONYMIZATION_UNRESOLVED = 'anonymization_unresolved';
    public const LEGAL_HOLD = 'legal_hold';
    public const RISK_REQUIRED = 'risk_requirement_unsatisfied';
    public const AUTHORIZATION_DENIED = 'authorization_denied';
    public const APPROVAL_REQUIRED = 'approval_required';
    public const AUDIT_UNAVAILABLE = 'audit_unavailable';
    public const RECONCILIATION_REQUIRED = 'reconciliation_required';
    public const ROLLBACK_REQUIRED = 'rollback_required';
    public const CURRENT_STATE_MISMATCH = 'current_state_mismatch';
    public const EXPIRATION_REQUIRED = 'expiration_required';
    public const SIMULATION_ONLY = 'simulation_only';

    /** @return list<string> */
    public static function all(): array { return [self::ALLOWED_FOR_SIMULATION, self::POLICY_UNRESOLVED, self::SOURCE_UNRESOLVED, self::SOURCE_CONFLICT, self::RETENTION_UNRESOLVED, self::ANONYMIZATION_UNRESOLVED, self::LEGAL_HOLD, self::RISK_REQUIRED, self::AUTHORIZATION_DENIED, self::APPROVAL_REQUIRED, self::AUDIT_UNAVAILABLE, self::RECONCILIATION_REQUIRED, self::ROLLBACK_REQUIRED, self::CURRENT_STATE_MISMATCH, self::EXPIRATION_REQUIRED, self::SIMULATION_ONLY]; }
    public static function assertValid(string $value): string { if (!in_array($value, self::all(), true)) throw new \InvalidArgumentException('unknown_disposition_reason'); return $value; }
}
