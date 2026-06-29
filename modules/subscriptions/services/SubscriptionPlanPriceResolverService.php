<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use RuntimeException;
use Subscriptions\Repositories\SubscriptionPlanPriceRepository;
use Throwable;

final class SubscriptionPlanPriceResolverException extends RuntimeException
{
    private int $status;
    private string $errorCode;

    public function __construct(int $status, string $errorCode, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->status = $status;
        $this->errorCode = $errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}

final class SubscriptionPlanPriceResolverService
{
    private const DEFAULT_CURRENCY = 'MXN';
    private const FREE_PLAN_CODE = 'free';
    private const BILLING_PERIOD_ANNUAL = 'annual';
    private const BILLING_PERIOD_MONTHLY = 'monthly';
    private const MONTHLY_MARKUP_FACTOR = 1.25;
    private const MONTHLY_MARKUP_PERCENT = 25;

    private SubscriptionPlanPriceRepository $repository;

    public function __construct(SubscriptionPlanPriceRepository $repository)
    {
        $this->repository = $repository;
    }

    public function resolveForCheckout(
        string $entityType,
        string $entityId,
        string $planCode,
        string $billingPeriod,
        ?string $currency = self::DEFAULT_CURRENCY,
        ?DateTimeImmutable $now = null
    ): array {
        $this->normalizeContext($entityType, $entityId);
        $planCode = strtolower(trim($planCode));
        $billingPeriod = strtolower(trim($billingPeriod));
        $currency = strtoupper(trim((string)($currency ?? self::DEFAULT_CURRENCY)));
        if ($currency === '') {
            $currency = self::DEFAULT_CURRENCY;
        }
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($planCode === self::FREE_PLAN_CODE) {
            throw new SubscriptionPlanPriceResolverException(
                422,
                'plan_not_contractable',
                'free plan cannot be contracted through paid checkout'
            );
        }

        if (!in_array($billingPeriod, [self::BILLING_PERIOD_ANNUAL, self::BILLING_PERIOD_MONTHLY], true)) {
            throw new SubscriptionPlanPriceResolverException(
                422,
                'billing_period_invalid',
                'billing period is invalid for checkout pricing'
            );
        }

        try {
            $candidates = $this->repository->findActiveCandidates($planCode, $billingPeriod, $currency, $now);
            if ($billingPeriod === self::BILLING_PERIOD_MONTHLY && count($candidates) === 0) {
                $annualCandidates = $this->repository->findActiveCandidates($planCode, self::BILLING_PERIOD_ANNUAL, $currency, $now);

                return $this->derivedMonthlySnapshot($this->singleCandidate($annualCandidates));
            }
        } catch (PDOException $e) {
            throw new SubscriptionPlanPriceResolverException(
                503,
                'pricing_source_unavailable',
                'pricing source is unavailable',
                $e
            );
        }

        return $this->snapshot($this->singleCandidate($candidates));
    }

    private function normalizeContext(string $entityType, string $entityId): void
    {
        trim($entityType);
        trim($entityId);
    }

    private function singleCandidate(array $candidates): array
    {
        $count = count($candidates);
        if ($count === 0) {
            throw new SubscriptionPlanPriceResolverException(
                422,
                'plan_price_not_configured',
                'subscription plan price is not configured'
            );
        }

        if ($count > 1) {
            throw new SubscriptionPlanPriceResolverException(
                500,
                'pricing_configuration_conflict',
                'more than one active subscription plan price is configured'
            );
        }

        return $candidates[0];
    }

    private function snapshot(array $price): array
    {
        return [
            'plan_code' => (string)($price['plan_code'] ?? ''),
            'billing_period' => (string)($price['billing_period'] ?? ''),
            'amount_cents' => (int)($price['amount_cents'] ?? 0),
            'currency' => (string)($price['currency'] ?? ''),
            'price_source' => (string)($price['price_source'] ?? ''),
            'price_version' => (string)($price['price_version'] ?? ''),
            'valid_from' => (string)($price['valid_from'] ?? ''),
            'valid_until' => $price['valid_until'] ?? null,
            'price_uuid' => (string)($price['uuid'] ?? ''),
            'source' => (string)($price['source'] ?? ''),
        ];
    }

    private function derivedMonthlySnapshot(array $annualPrice): array
    {
        $annualSnapshot = $this->snapshot($annualPrice);
        $annualAmountCents = (int)($annualSnapshot['amount_cents'] ?? 0);
        $monthlyAmountCents = (int)round(($annualAmountCents / 12) * self::MONTHLY_MARKUP_FACTOR);
        if ($monthlyAmountCents <= 0) {
            throw new SubscriptionPlanPriceResolverException(
                422,
                'plan_price_not_configured',
                'subscription plan price is not configured'
            );
        }

        $version = trim((string)($annualSnapshot['price_version'] ?? ''));
        if ($version === '') {
            $version = 'annual';
        }

        return [
            'plan_code' => (string)($annualSnapshot['plan_code'] ?? ''),
            'billing_period' => self::BILLING_PERIOD_MONTHLY,
            'amount_cents' => $monthlyAmountCents,
            'currency' => (string)($annualSnapshot['currency'] ?? self::DEFAULT_CURRENCY),
            'price_source' => 'derived_monthly_markup_25',
            'price_version' => substr($version . ':monthly25', 0, 64),
            'valid_from' => (string)($annualSnapshot['valid_from'] ?? ''),
            'valid_until' => $annualSnapshot['valid_until'] ?? null,
            'price_uuid' => '',
            'source' => 'derived_from_annual_price',
            'derived_from_billing_period' => self::BILLING_PERIOD_ANNUAL,
            'derived_from_amount_cents' => $annualAmountCents,
            'derived_from_price_uuid' => (string)($annualSnapshot['price_uuid'] ?? ''),
            'monthly_markup_percent' => self::MONTHLY_MARKUP_PERCENT,
        ];
    }
}
