<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Repositories\InternalOperatorGrantRepository;
use PDO;

/** ASE-ALIGN01 V2: exact routine operations, never a staff-role permission bundle. */
final class StaffRoutineOperationPolicy
{
    public const OPERATION_CAPABILITIES = [
        'PHYSICIAN_ENROLLMENT_REVIEW' => 'physician_accreditation_review',
        'PHYSICIAN_EVIDENCE_REVIEW' => 'physician_accreditation_review',
        'PROFESSIONAL_SOURCE_PROVISION' => 'professional_source_provision',
    ];

    public static function capability(string $operation): ?string
    {
        return self::OPERATION_CAPABILITIES[$operation] ?? null;
    }

    public static function allowAdvisor(PDO $pdo, string $accountId, string $operation): bool
    {
        $capability = self::capability($operation);
        if ($capability === null || !self::activeAdvisor($pdo, $accountId)) return false;
        $definition = InternalCapabilityCatalog::require($capability);
        return $definition['kind'] === 'operational' && $definition['delegable'] === true
            && (new InternalOperatorGrantRepository($pdo))->activeCapabilities($accountId,$pdo->inTransaction())->contains($capability);
    }

    public static function eligibleAdvisorSubject(PDO $pdo, string $accountId): bool
    {
        if (!self::activeAdvisor($pdo, $accountId)) return false;
        $grants = (new InternalOperatorGrantRepository($pdo))->activeCapabilities($accountId,$pdo->inTransaction());
        foreach (array_unique(self::OPERATION_CAPABILITIES) as $capability) {
            $definition = InternalCapabilityCatalog::require($capability);
            if ($definition['kind'] === 'operational' && $definition['delegable'] === true && $grants->contains($capability)) return true;
        }
        return false;
    }

    private static function activeAdvisor(PDO $pdo, string $accountId): bool
    {
        $q = $pdo->prepare("SELECT 1 FROM internal_staff s JOIN auth_accounts a ON a.account_id=s.account_id
            JOIN auth_account_credentials c ON c.account_id=a.account_id WHERE s.account_id=?
            AND s.status='ACTIVE' AND s.governance_class='ADVISOR' AND a.status='active'
            AND a.email_verified_at IS NOT NULL AND c.credential_version>=1".($pdo->inTransaction()?' FOR UPDATE':''));
        $q->execute([$accountId]);
        return $q->fetchColumn() !== false;
    }
}
