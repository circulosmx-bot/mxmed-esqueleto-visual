<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class BuildSubscriptionPaymentRoutePreviewException extends RuntimeException
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

final class BuildSubscriptionPaymentRoutePreviewService
{
    private const ROUTE_NEW_SUBSCRIPTION = 'new_subscription';
    private const ROUTE_UPGRADE_SUBSCRIPTION = 'upgrade_subscription';
    private const ROUTE_RENEWAL = 'renewal';
    private const DEFAULT_CURRENCY = 'MXN';
    private const STATUS_READY = 'preview_ready_for_checkout_sandbox';
    private const BILLING_PERIOD_ANNUAL = 'annual';
    private const BILLING_PERIOD_MONTHLY = 'monthly';
    private const FREE_MONTHLY_ADVANCE_CONTRACT_VERSION = 'free_monthly_advance_v1';
    private const MONTHLY_INITIAL_CYCLES = 3;

    private const PLAN_RANKS = [
        'basic' => 1,
        'standard' => 2,
        'optimum' => 3,
        'professional' => 4,
    ];

    private const PLAN_ALIASES = [
        'basic' => 'basic',
        'basico' => 'basic',
        'básico' => 'basic',
        'standard' => 'standard',
        'estandar' => 'standard',
        'estándar' => 'standard',
        'optimum' => 'optimum',
        'optimo' => 'optimum',
        'óptimo' => 'optimum',
        'professional' => 'professional',
        'profesional' => 'professional',
    ];

    private CurrentSubscriptionReadModelService $readModelService;
    private SubscriptionPlanPriceResolverService $priceResolver;
    private DateTimeImmutable $now;

    public function __construct(
        CurrentSubscriptionReadModelService $readModelService,
        SubscriptionPlanPriceResolverService $priceResolver,
        ?DateTimeImmutable $now = null
    ) {
        $this->readModelService = $readModelService;
        $this->priceResolver = $priceResolver;
        $this->now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function build(array $input): array
    {
        $entityType = trim((string)($input['entity_type'] ?? ''));
        $entityId = trim((string)($input['entity_id'] ?? ''));
        $payload = (array)($input['payload'] ?? []);
        $idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));

        if ($entityType === '' || $entityId === '') {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                422,
                'validation_error',
                'entity_type and entity_id are required'
            );
        }

        if ($idempotencyKey === '') {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                422,
                'missing_idempotency_key',
                'Idempotency-Key is required for payment route preview'
            );
        }

        $routeType = $this->normalizeRouteType($payload['route_type'] ?? null);
        $paymentMethodFamily = $this->normalizePaymentMethodFamily($payload['payment_method_family'] ?? 'not_selected');
        $autoRenewRequested = $this->normalizeBoolean($payload['auto_renew_requested'] ?? false);
        $currentModel = $this->readModelService->resolveForEntity($entityType, $entityId);

        if ($routeType === self::ROUTE_NEW_SUBSCRIPTION) {
            return $this->buildNewSubscriptionPreview(
                $entityType,
                $entityId,
                $payload,
                $currentModel,
                $paymentMethodFamily,
                $autoRenewRequested
            );
        }

        if ($routeType === self::ROUTE_UPGRADE_SUBSCRIPTION) {
            return $this->buildUpgradePreview(
                $entityType,
                $entityId,
                $payload,
                $currentModel,
                $paymentMethodFamily,
                $autoRenewRequested
            );
        }

        return $this->buildRenewalPreview(
            $entityType,
            $entityId,
            $payload,
            $currentModel,
            $paymentMethodFamily,
            $autoRenewRequested
        );
    }

    private function buildNewSubscriptionPreview(
        string $entityType,
        string $entityId,
        array $payload,
        array $currentModel,
        string $paymentMethodFamily,
        bool $autoRenewRequested
    ): array {
        if ($this->hasActivePaidSubscription($currentModel)) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                409,
                'active_subscription_exists',
                'active paid subscription already exists'
            );
        }

        $targetPlanCode = $this->requireTargetPlan($payload);
        $billingPeriod = $this->normalizeBillingPeriod($payload['billing_period'] ?? null);
        $targetPrice = $this->resolvePrice($entityType, $entityId, $targetPlanCode, $billingPeriod);
        $annualPrice = $billingPeriod === self::BILLING_PERIOD_ANNUAL
            ? $targetPrice
            : $this->resolvePrice($entityType, $entityId, $targetPlanCode, self::BILLING_PERIOD_ANNUAL);
        $pricingContract = $this->newSubscriptionPricingContract($targetPlanCode, $billingPeriod, $targetPrice, $annualPrice);
        $amountCents = (int)$pricingContract['initial_amount_cents'];
        $frontendAmount = $this->frontendAmountCents($payload, self::ROUTE_NEW_SUBSCRIPTION);

        return $this->baseResponse(
            self::ROUTE_NEW_SUBSCRIPTION,
            $entityType,
            $entityId,
            null,
            $targetPlanCode,
            $billingPeriod,
            $amountCents,
            (string)($targetPrice['currency'] ?? self::DEFAULT_CURRENCY),
            $frontendAmount,
            $paymentMethodFamily,
            $autoRenewRequested
        ) + [
            'target_price_cents' => (int)$targetPrice['amount_cents'],
            'pricing_contract' => $pricingContract,
        ];
    }

    private function buildUpgradePreview(
        string $entityType,
        string $entityId,
        array $payload,
        array $currentModel,
        string $paymentMethodFamily,
        bool $autoRenewRequested
    ): array {
        if (!$this->hasActivePaidSubscription($currentModel)) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                409,
                'active_subscription_required',
                'active paid subscription is required'
            );
        }

        $currentPlanCode = $this->currentPlanCode($currentModel);
        $targetPlanCode = $this->requireTargetPlan($payload);
        if (self::PLAN_RANKS[$targetPlanCode] <= self::PLAN_RANKS[$currentPlanCode]) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                409,
                'invalid_upgrade',
                'target plan must be higher than current plan'
            );
        }

        $billingPeriod = $this->currentBillingPeriod($currentModel);
        $payloadBillingPeriod = $this->nullableBillingPeriod($payload['billing_period'] ?? null);
        if ($payloadBillingPeriod !== null && $payloadBillingPeriod !== $billingPeriod) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                422,
                'unsupported_billing_period_change',
                'billing period change is not supported for upgrade preview'
            );
        }

        $remainingDays = $this->remainingDays($currentModel);
        if ($remainingDays <= 0) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                409,
                'subscription_not_eligible_for_upgrade',
                'subscription is not eligible for upgrade preview'
            );
        }

        $periodDays = $this->periodDays($currentModel, $billingPeriod);
        $currentPrice = $this->resolvePrice($entityType, $entityId, $currentPlanCode, $billingPeriod);
        $targetPrice = $this->resolvePrice($entityType, $entityId, $targetPlanCode, $billingPeriod);
        $currentPriceCents = (int)$currentPrice['amount_cents'];
        $targetPriceCents = (int)$targetPrice['amount_cents'];
        $differenceCents = $targetPriceCents - $currentPriceCents;
        if ($differenceCents <= 0) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                409,
                'invalid_upgrade',
                'target plan price must be higher than current plan price'
            );
        }

        $adjustmentAmountCents = max(0, (int)round(($differenceCents * $remainingDays) / max(1, $periodDays)));
        $frontendAmount = $this->frontendAmountCents($payload, self::ROUTE_UPGRADE_SUBSCRIPTION);
        $warnings = $this->frontendCurrentPlanWarnings($payload, $currentPlanCode);

        return $this->baseResponse(
            self::ROUTE_UPGRADE_SUBSCRIPTION,
            $entityType,
            $entityId,
            $currentPlanCode,
            $targetPlanCode,
            $billingPeriod,
            $adjustmentAmountCents,
            (string)($targetPrice['currency'] ?? self::DEFAULT_CURRENCY),
            $frontendAmount,
            $paymentMethodFamily,
            $autoRenewRequested,
            $warnings
        ) + [
            'current_price_cents' => $currentPriceCents,
            'target_price_cents' => $targetPriceCents,
            'remaining_days' => $remainingDays,
            'period_days' => $periodDays,
            'adjustment_amount_cents' => $adjustmentAmountCents,
        ];
    }

    private function buildRenewalPreview(
        string $entityType,
        string $entityId,
        array $payload,
        array $currentModel,
        string $paymentMethodFamily,
        bool $autoRenewRequested
    ): array {
        if (!$this->hasActivePaidSubscription($currentModel)) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                409,
                'active_subscription_required',
                'active paid subscription is required'
            );
        }

        $currentPlanCode = $this->currentPlanCode($currentModel);
        $billingPeriod = $this->currentBillingPeriod($currentModel);
        $currentPrice = $this->resolvePrice($entityType, $entityId, $currentPlanCode, $billingPeriod);
        $renewalAmountCents = (int)$currentPrice['amount_cents'];
        $renewalDurationDays = $this->periodDays($currentModel, $billingPeriod);
        $currentExpiresAt = $this->nullableText($currentModel['expires_at'] ?? null);
        $estimatedNextExpiresAt = $this->estimatedNextExpiresAt($currentExpiresAt, $renewalDurationDays);
        $frontendAmount = $this->frontendAmountCents($payload, self::ROUTE_RENEWAL);
        $warnings = $this->frontendCurrentPlanWarnings($payload, $currentPlanCode);

        return $this->baseResponse(
            self::ROUTE_RENEWAL,
            $entityType,
            $entityId,
            $currentPlanCode,
            null,
            $billingPeriod,
            $renewalAmountCents,
            (string)($currentPrice['currency'] ?? self::DEFAULT_CURRENCY),
            $frontendAmount,
            $paymentMethodFamily,
            $autoRenewRequested,
            $warnings
        ) + [
            'current_expires_at' => $currentExpiresAt,
            'renewal_duration_days' => $renewalDurationDays,
            'estimated_next_expires_at' => $estimatedNextExpiresAt,
            'renewal_amount_cents' => $renewalAmountCents,
        ];
    }

    private function baseResponse(
        string $routeType,
        string $entityType,
        string $entityId,
        ?string $currentPlanCode,
        ?string $targetPlanCode,
        string $billingPeriod,
        int $amountCents,
        string $currency,
        ?int $frontendAmountCents,
        string $paymentMethodFamily,
        bool $autoRenewRequested,
        array $warnings = []
    ): array {
        return [
            'mode' => 'payment_route_preview',
            'route_type' => $routeType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'current_plan_code' => $currentPlanCode,
            'target_plan_code' => $targetPlanCode,
            'billing_period' => $billingPeriod,
            'amount_cents' => $amountCents,
            'currency' => strtoupper(trim($currency)) !== '' ? strtoupper(trim($currency)) : self::DEFAULT_CURRENCY,
            'amount_source' => 'server_recalculated',
            'frontend_amount_cents' => $frontendAmountCents,
            'amount_mismatch' => $frontendAmountCents !== null && $frontendAmountCents !== $amountCents,
            'auto_renew_requested' => $autoRenewRequested,
            'auto_renew_status' => $this->autoRenewStatus($autoRenewRequested, $paymentMethodFamily),
            'payment_method_family' => $paymentMethodFamily,
            'status' => self::STATUS_READY,
            'next_action' => [
                'type' => 'stripe_checkout_sandbox_pending',
                'enabled' => false,
            ],
            'idempotency_required' => true,
            'idempotency_key_received' => true,
            'idempotency_persisted' => false,
            'lock_required_for_write_phase' => true,
            'lock_persisted' => false,
            'idempotency' => [
                'required' => true,
                'received' => true,
                'persisted' => false,
                'mode' => 'preview_no_write',
            ],
            'lock' => [
                'required_for_write_phase' => true,
                'persisted' => false,
            ],
            'warnings' => array_values(array_unique($warnings)),
            'reasons' => [],
        ];
    }

    private function normalizeRouteType($value): string
    {
        $routeType = strtolower(trim((string)$value));
        if (!in_array($routeType, [
            self::ROUTE_NEW_SUBSCRIPTION,
            self::ROUTE_UPGRADE_SUBSCRIPTION,
            self::ROUTE_RENEWAL,
        ], true)) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                422,
                'invalid_route_type',
                'route_type is not supported for payment route preview'
            );
        }

        return $routeType;
    }

    private function normalizePaymentMethodFamily($value): string
    {
        $paymentMethodFamily = strtolower(trim((string)$value));
        if ($paymentMethodFamily === '') {
            $paymentMethodFamily = 'not_selected';
        }
        if (!in_array($paymentMethodFamily, ['card', 'spei', 'oxxo', 'not_selected'], true)) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                422,
                'invalid_payment_method_family',
                'payment_method_family is not supported for payment route preview'
            );
        }

        return $paymentMethodFamily;
    }

    private function requireTargetPlan(array $payload): string
    {
        $targetPlanCode = $this->normalizePlanCode($payload['target_plan_code'] ?? ($payload['plan_code'] ?? null));
        if ($targetPlanCode === null) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                422,
                'invalid_target_plan',
                'target_plan_code is required'
            );
        }

        return $targetPlanCode;
    }

    private function normalizePlanCode($value): ?string
    {
        $raw = strtolower(trim((string)$value));
        if ($raw === '') {
            return null;
        }

        $normalized = self::PLAN_ALIASES[$raw] ?? null;
        if ($normalized === null || !array_key_exists($normalized, self::PLAN_RANKS)) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                422,
                'invalid_target_plan',
                'plan code is not supported for payment route preview'
            );
        }

        return $normalized;
    }

    private function normalizeBillingPeriod($value): string
    {
        $billingPeriod = $this->nullableBillingPeriod($value);
        if ($billingPeriod === null) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                422,
                'validation_error',
                'billing_period is required'
            );
        }

        return $billingPeriod;
    }

    private function nullableBillingPeriod($value): ?string
    {
        $billingPeriod = strtolower(trim((string)$value));
        if ($billingPeriod === '') {
            return null;
        }
        if (!in_array($billingPeriod, [self::BILLING_PERIOD_ANNUAL, self::BILLING_PERIOD_MONTHLY], true)) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                422,
                'validation_error',
                'billing_period is invalid'
            );
        }

        return $billingPeriod;
    }

    private function currentPlanCode(array $currentModel): string
    {
        $candidate = $currentModel['effective_plan_code'] ?? ($currentModel['contracted_plan_code'] ?? null);
        $planCode = $this->normalizePlanCode($candidate);
        if ($planCode === null) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                409,
                'active_subscription_required',
                'active paid subscription is required'
            );
        }

        return $planCode;
    }

    private function currentBillingPeriod(array $currentModel): string
    {
        return $this->normalizeBillingPeriod($currentModel['billing_period'] ?? null);
    }

    private function hasActivePaidSubscription(array $currentModel): bool
    {
        return (bool)($currentModel['is_paid_plan'] ?? false)
            && (bool)($currentModel['is_active'] ?? false)
            && $this->nullableText($currentModel['effective_plan_code'] ?? null) !== 'free';
    }

    private function resolvePrice(string $entityType, string $entityId, string $planCode, string $billingPeriod): array
    {
        try {
            return $this->priceResolver->resolveForCheckout(
                $entityType,
                $entityId,
                $planCode,
                $billingPeriod,
                self::DEFAULT_CURRENCY,
                $this->now
            );
        } catch (SubscriptionPlanPriceResolverException $e) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                $e->status() >= 500 ? 500 : 409,
                $e->status() >= 500 ? 'internal_error' : 'price_not_resolved',
                $e->status() >= 500 ? 'payment route preview is unavailable' : 'price could not be resolved',
                $e
            );
        }
    }

    private function newSubscriptionPricingContract(
        string $planCode,
        string $billingPeriod,
        array $targetPrice,
        array $annualPrice
    ): array {
        $currency = strtoupper(trim((string)($targetPrice['currency'] ?? self::DEFAULT_CURRENCY)));
        if ($currency === '') {
            $currency = self::DEFAULT_CURRENCY;
        }

        $unitAmountCents = (int)($targetPrice['amount_cents'] ?? 0);
        if ($unitAmountCents <= 0) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                409,
                'price_not_resolved',
                'price could not be resolved'
            );
        }

        $annualAmountCents = (int)($annualPrice['amount_cents'] ?? 0);
        if ($annualAmountCents <= 0) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                409,
                'price_not_resolved',
                'annual price could not be resolved'
            );
        }

        if ($billingPeriod === self::BILLING_PERIOD_MONTHLY) {
            $initialAmountCents = $this->safeMultiply($unitAmountCents, self::MONTHLY_INITIAL_CYCLES);
            $monthlyAnnualizedAmountCents = $this->safeMultiply($unitAmountCents, 12);

            return [
                'contract_version' => self::FREE_MONTHLY_ADVANCE_CONTRACT_VERSION,
                'plan_code' => $planCode,
                'billing_period' => self::BILLING_PERIOD_MONTHLY,
                'currency' => $currency,
                'unit_amount_cents' => $unitAmountCents,
                'initial_cycles' => self::MONTHLY_INITIAL_CYCLES,
                'initial_amount_cents' => $initialAmountCents,
                'regular_recurring_amount_cents' => $unitAmountCents,
                'annual_amount_cents' => $annualAmountCents,
                'monthly_annualized_amount_cents' => $monthlyAnnualizedAmountCents,
                'annual_savings_amount_cents' => max(0, $monthlyAnnualizedAmountCents - $annualAmountCents),
                'is_prorated' => false,
                'payment_execution_enabled' => false,
                'payment_execution_block_reason' => 'stripe_billing_not_ready',
                'price_source' => (string)($targetPrice['price_source'] ?? ''),
                'price_version' => (string)($targetPrice['price_version'] ?? ''),
            ];
        }

        return [
            'contract_version' => self::FREE_MONTHLY_ADVANCE_CONTRACT_VERSION,
            'plan_code' => $planCode,
            'billing_period' => self::BILLING_PERIOD_ANNUAL,
            'currency' => $currency,
            'unit_amount_cents' => $unitAmountCents,
            'initial_cycles' => 1,
            'initial_amount_cents' => $unitAmountCents,
            'regular_recurring_amount_cents' => $unitAmountCents,
            'annual_amount_cents' => $annualAmountCents,
            'monthly_annualized_amount_cents' => null,
            'annual_savings_amount_cents' => 0,
            'is_prorated' => false,
            'payment_execution_enabled' => true,
            'payment_execution_block_reason' => null,
            'price_source' => (string)($targetPrice['price_source'] ?? ''),
            'price_version' => (string)($targetPrice['price_version'] ?? ''),
        ];
    }

    private function safeMultiply(int $amountCents, int $multiplier): int
    {
        if ($amountCents < 0 || $multiplier < 0 || ($multiplier > 0 && $amountCents > intdiv(PHP_INT_MAX, $multiplier))) {
            throw new BuildSubscriptionPaymentRoutePreviewException(
                500,
                'internal_error',
                'payment route preview is unavailable'
            );
        }

        return $amountCents * $multiplier;
    }

    private function remainingDays(array $currentModel): int
    {
        $expiresAt = $this->parseDateTime($currentModel['expires_at'] ?? null);
        if ($expiresAt === null || $expiresAt <= $this->now) {
            return 0;
        }

        return (int)ceil(($expiresAt->getTimestamp() - $this->now->getTimestamp()) / 86400);
    }

    private function periodDays(array $currentModel, string $billingPeriod): int
    {
        $durationDays = (int)($currentModel['duration_days'] ?? 0);
        if ($durationDays > 0) {
            return $durationDays;
        }

        $startsAt = $this->parseDateTime($currentModel['starts_at'] ?? null);
        $expiresAt = $this->parseDateTime($currentModel['expires_at'] ?? null);
        if ($startsAt !== null && $expiresAt !== null && $expiresAt > $startsAt) {
            return max(1, (int)ceil(($expiresAt->getTimestamp() - $startsAt->getTimestamp()) / 86400));
        }

        return $billingPeriod === self::BILLING_PERIOD_MONTHLY ? 30 : 365;
    }

    private function estimatedNextExpiresAt(?string $currentExpiresAt, int $renewalDurationDays): ?string
    {
        $expiresAt = $this->parseDateTime($currentExpiresAt);
        if ($expiresAt === null || $renewalDurationDays <= 0) {
            return null;
        }

        return $expiresAt->modify('+' . $renewalDurationDays . ' days')->format('Y-m-d H:i:s');
    }

    private function frontendAmountCents(array $payload, string $routeType): ?int
    {
        $keys = ['amount_cents'];
        if ($routeType === self::ROUTE_UPGRADE_SUBSCRIPTION) {
            array_unshift($keys, 'adjustment_amount_cents');
        }
        if ($routeType === self::ROUTE_RENEWAL) {
            array_unshift($keys, 'renewal_amount_cents');
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && is_numeric($payload[$key])) {
                return max(0, (int)round((float)$payload[$key]));
            }
        }

        $snapshot = $payload['frontend_summary_snapshot'] ?? null;
        if (is_array($snapshot)) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $snapshot) && is_numeric($snapshot[$key])) {
                    return max(0, (int)round((float)$snapshot[$key]));
                }
            }
        }

        return null;
    }

    private function frontendCurrentPlanWarnings(array $payload, string $currentPlanCode): array
    {
        $payloadCurrentPlan = $payload['current_plan_code'] ?? null;
        if ($payloadCurrentPlan === null || trim((string)$payloadCurrentPlan) === '') {
            return [];
        }

        try {
            $normalized = $this->normalizePlanCode($payloadCurrentPlan);
        } catch (BuildSubscriptionPaymentRoutePreviewException $e) {
            return ['frontend_current_plan_ignored'];
        }

        if ($normalized !== $currentPlanCode) {
            return ['frontend_current_plan_mismatch'];
        }

        return [];
    }

    private function autoRenewStatus(bool $requested, string $paymentMethodFamily): string
    {
        if (!$requested) {
            return 'disabled';
        }

        if ($paymentMethodFamily === 'card') {
            return 'pending_saved_payment_method';
        }

        if (in_array($paymentMethodFamily, ['spei', 'oxxo'], true)) {
            return 'not_supported_for_manual_method';
        }

        return 'pending_payment_method';
    }

    private function normalizeBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int)$value === 1;
        }

        $raw = strtolower(trim((string)$value));
        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    private function parseDateTime($value): ?DateTimeImmutable
    {
        $raw = $this->nullableText($value);
        if ($raw === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($raw, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return null;
        }
    }

    private function nullableText($value): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }
}
