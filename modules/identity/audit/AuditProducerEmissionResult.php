<?php
declare(strict_types=1);

namespace Identity\Audit;

final readonly class AuditProducerEmissionResult
{
    public const WRITTEN = 'WRITTEN';
    public const AUDIT_FAILED_SIGNALLED = 'AUDIT_FAILED_SIGNALLED';
    public const AUDIT_AND_SIGNAL_FAILED = 'AUDIT_AND_SIGNAL_FAILED';

    private function __construct(public string $status, public bool $domainOutcomePreserved, public bool $auditSucceeded, public bool $hardFailureSignalSucceeded) {}

    public static function written(): self { return new self(self::WRITTEN, true, true, true); }
    public static function auditFailedSignalled(): self { return new self(self::AUDIT_FAILED_SIGNALLED, true, false, true); }
    public static function auditAndSignalFailed(): self { return new self(self::AUDIT_AND_SIGNAL_FAILED, true, false, false); }
}
