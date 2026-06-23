<?php
declare(strict_types=1);

namespace Subscriptions\Repositories;

use DateTimeImmutable;
use PDO;

final class SubscriptionPlanPriceRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findActiveCandidates(
        string $planCode,
        string $billingPeriod,
        string $currency,
        DateTimeImmutable $now
    ): array {
        $stmt = $this->pdo->prepare(
            'SELECT
                uuid,
                plan_code,
                billing_period,
                amount_cents,
                currency,
                price_source,
                price_version,
                valid_from,
                valid_until,
                is_active,
                source
             FROM subscription_plan_prices
             WHERE plan_code = :plan_code
               AND billing_period = :billing_period
               AND currency = :currency
               AND is_active = 1
               AND deleted_at IS NULL
               AND valid_from <= :now
               AND (valid_until IS NULL OR valid_until > :now)
             ORDER BY valid_from DESC, id DESC
             LIMIT 2'
        );
        $stmt->execute([
            'plan_code' => trim($planCode),
            'billing_period' => trim($billingPeriod),
            'currency' => trim($currency),
            'now' => $now->format('Y-m-d H:i:s'),
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
}
