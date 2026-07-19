<?php
declare(strict_types=1);

namespace Subscriptions\Repositories;

use PDO;
use PDOException;
use RuntimeException;

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

    public function findPublishedPlanPrices(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT
                    plan_code,
                    billing_period,
                    amount_cents,
                    currency,
                    price_version
                 FROM subscription_plan_prices
                 WHERE is_active = 1
                   AND deleted_at IS NULL
                   AND valid_from <= UTC_TIMESTAMP()
                   AND (valid_until IS NULL OR valid_until >= UTC_TIMESTAMP())
                 ORDER BY plan_code ASC, billing_period ASC, valid_from DESC'
            );
            $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            return [];
        }

        return is_array($rows) ? $rows : [];
    }

    public function activeSubscriptionExists(string $entityType, string $entityId): bool
    {
        return $this->findActiveByEntity($entityType, $entityId) !== null;
    }

    public function findActiveByEntity(string $entityType, string $entityId): ?array
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
                    billing_period,
                    starts_at,
                    expires_at,
                    grace_starts_at,
                    grace_ends_at,
                    status,
                    source,
                    created_at,
                    updated_at
                 FROM profile_subscriptions
                 WHERE entity_type = :entity_type
                   AND entity_id = :entity_id
                   AND deleted_at IS NULL
                   AND status IN (\'active\', \'expiring_soon\', \'grace_period\')
                   AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP())
                   AND (expires_at IS NULL OR expires_at >= UTC_TIMESTAMP())
                 ORDER BY
                    CASE status
                        WHEN "active" THEN 0
                        WHEN "expiring_soon" THEN 1
                        WHEN "grace_period" THEN 2
                        ELSE 3
                    END ASC,
                    starts_at DESC,
                    expires_at DESC,
                    created_at DESC
                 LIMIT 1'
            );
            $stmt->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException('active_subscription_check_unavailable', 0, $e);
        }

        return is_array($row) ? $row : null;
    }

    public function findCurrentCandidateForEntity(string $entityType, string $entityId): ?array
    {
        $entityType = trim($entityType);
        $entityId = trim($entityId);
        if ($entityType === '' || $entityId === '') {
            return null;
        }

        try {
            $optionalColumns = $this->availableOptionalSubscriptionColumns();
            $optionalSelect = $optionalColumns !== []
                ? ",\n                    " . implode(",\n                    ", $optionalColumns)
                : '';
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
                    updated_at' . $optionalSelect . '
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

    public function cancelScheduledPlanChange(string $entityType, string $entityId): bool
    {
        $available = $this->availableOptionalSubscriptionColumns();
        foreach (['scheduled_plan_code', 'scheduled_effective_at', 'scheduled_change_status'] as $required) {
            if (!in_array($required, $available, true)) {
                throw new RuntimeException('scheduled_change_storage_unavailable');
            }
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE profile_subscriptions
                 SET scheduled_change_status = \'cancelled\',
                     updated_at = CURRENT_TIMESTAMP
                 WHERE entity_type = :entity_type
                   AND entity_id = :entity_id
                   AND deleted_at IS NULL
                   AND scheduled_plan_code IS NOT NULL
                   AND scheduled_effective_at > UTC_TIMESTAMP()
                   AND scheduled_change_status = \'scheduled\'
                 ORDER BY created_at DESC
                 LIMIT 1'
            );
            $stmt->execute(['entity_type' => trim($entityType), 'entity_id' => trim($entityId)]);
            return $stmt->rowCount() === 1;
        } catch (PDOException $e) {
            throw new RuntimeException('scheduled_change_cancel_unavailable', 0, $e);
        }
    }

    private function availableOptionalSubscriptionColumns(): array
    {
        $allowlist = [
            'policy_version',
            'original_expires_at',
            'grace_extension_type',
            'grace_extension_days',
            'grace_extension_status',
            'grace_extension_approved_at',
            'restricted_at',
            'scheduled_plan_code',
            'scheduled_effective_at',
            'scheduled_change_status',
            'active_addons_json',
            'archival_state',
        ];
        try {
            $stmt = $this->pdo->query('SHOW COLUMNS FROM profile_subscriptions');
            $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            return [];
        }
        $available = [];
        foreach ($rows as $row) {
            $field = (string)($row['Field'] ?? '');
            if (in_array($field, $allowlist, true)) {
                $available[] = $field;
            }
        }
        return $available;
    }
}
