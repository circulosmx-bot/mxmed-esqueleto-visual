<?php
declare(strict_types=1);

namespace Subscriptions\Repositories;

use PDO;
use PDOException;

final class CurrentSubscriptionRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findPlanByCodeAndPeriod(string $planCode, string $billingPeriod): ?array
    {
        $planCode = trim($planCode);
        $billingPeriod = trim($billingPeriod);
        if ($planCode === '' || $billingPeriod === '') {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT
                    plan_code,
                    plan_label,
                    billing_period,
                    duration_days,
                    is_active,
                    sort_order,
                    source
                 FROM subscription_plans
                 WHERE plan_code = :plan_code
                   AND billing_period = :billing_period
                 LIMIT 1'
            );
            $stmt->execute([
                'plan_code' => $planCode,
                'billing_period' => $billingPeriod,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }

        return is_array($row) ? $row : null;
    }

    public function findFallbackFreePlan(): ?array
    {
        return $this->findPlanByCodeAndPeriod('free', 'lifetime');
    }

    public function findCurrentCandidateForEntity(string $entityType, string $entityId): ?array
    {
        $entityType = trim($entityType);
        $entityId = trim($entityId);
        if ($entityType === '' || $entityId === '') {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT
                    subscription_id,
                    entity_type,
                    entity_id,
                    doctor_id,
                    profile_id,
                    plan_code,
                    plan_label,
                    billing_period,
                    duration_days,
                    contracted_plan_code,
                    effective_plan_code,
                    contract_version,
                    contract_accepted_at,
                    contract_accepted_by_user_id,
                    contract_acceptance_source,
                    starts_at,
                    expires_at,
                    grace_starts_at,
                    grace_ends_at,
                    status,
                    auto_renew,
                    cancelled_at,
                    renewed_from_subscription_id,
                    renewed_to_subscription_id,
                    source,
                    created_at,
                    updated_at
                 FROM profile_subscriptions
                 WHERE entity_type = :entity_type
                   AND entity_id = :entity_id
                   AND deleted_at IS NULL
                 ORDER BY
                    CASE status
                        WHEN "active" THEN 0
                        WHEN "grace_period" THEN 1
                        WHEN "expired" THEN 2
                        WHEN "inactive" THEN 3
                        WHEN "renewed" THEN 4
                        WHEN "cancelled" THEN 5
                        ELSE 6
                    END ASC,
                    starts_at DESC,
                    created_at DESC
                 LIMIT 1'
            );
            $stmt->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }

        return is_array($row) ? $row : null;
    }
}
