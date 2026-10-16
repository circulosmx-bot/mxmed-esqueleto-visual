<?php
declare(strict_types=1);
namespace Identity\Services;

use PDO;

/** GOV-SIM02 changes routine physician ownership only, never platform recovery. */
final class PhysicianOwnershipPolicy
{
    public const ROUTINE = 'GOV-SIM02_PHYSICIAN_INITIAL_OWNER';

    public static function routine(array $case): bool
    {
        $context = json_decode((string)($case['security_context'] ?? ''), true);
        return ($case['operation'] ?? null) === 'INITIAL_OWNER'
            && is_array($context) && ($context['version'] ?? null) === 2
            && ($context['ownership_policy'] ?? null) === self::ROUTINE;
    }

    public static function requiredApprovals(array $case): int { return self::routine($case) ? 1 : 2; }

    public static function approvalCount(PDO $pdo, string $operationId, string $caseId): int
    {
        try {
            $q=$pdo->prepare('SELECT operation,security_context FROM auth_governance_cases WHERE operation_id=? AND case_id=? FOR UPDATE');
            $q->execute([$operationId,$caseId]);$case=$q->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Historical approval fixtures/pre-v2 deployments retain two-person protection.
            if (!in_array((int)($e->errorInfo[1] ?? 0), [1054,1146], true)) throw $e;
            return 2;
        }
        return is_array($case) ? self::requiredApprovals($case) : 2;
    }
}
