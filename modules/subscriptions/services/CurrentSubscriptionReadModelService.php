<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Subscriptions\Contracts\ExistingCapabilityDecision;
use Subscriptions\Repositories\CurrentSubscriptionRepository;

require_once __DIR__ . '/../contracts/ExistingCapabilityDecision.php';
require_once __DIR__ . '/ExistingCapabilityAuthorityService.php';

final class CurrentSubscriptionReadModelService
{
    private const VERSION = 'current-subscription-readmodel-v1';
    private const FREE_PLAN_CODE = 'free';
    private const FREE_BILLING_PERIOD = 'lifetime';
    private const EXISTING_CAPABILITY_IDS = [
        'profile_directory_basic',
        'public_contact',
        'gallery',
        'agenda_appointments',
        'patients',
        'clinical_record',
        'prescriptions',
    ];

    private CurrentSubscriptionRepository $repository;
    private ExistingCapabilityAuthorityService $capabilityAuthority;
    private ?DateTimeImmutable $now;

    public function __construct(
        CurrentSubscriptionRepository $repository,
        ?DateTimeImmutable $now = null,
        ?ExistingCapabilityAuthorityService $capabilityAuthority = null
    ) {
        $this->repository = $repository;
        $this->now = $now;
        $this->capabilityAuthority = $capabilityAuthority ?? new ExistingCapabilityAuthorityService();
    }

    public function resolveForEntity(string $entityType, string $entityId): array
    {
        $entityType = trim($entityType);
        $entityId = trim($entityId);
        if ($entityType === '' || $entityId === '') {
            throw new InvalidArgumentException('entity_type and entity_id are required');
        }

        $subscription = $this->repository->findCurrentCandidateForEntity($entityType, $entityId);
        if ($subscription === null) {
            return $this->buildFreeDefault($entityType, $entityId, false);
        }

        return $this->buildFromSubscription($entityType, $entityId, $subscription);
    }

    private function buildFromSubscription(string $entityType, string $entityId, array $subscription): array
    {
        $status = $this->normalizeStatus($subscription['status'] ?? null);
        $contractedPlanCode = $this->normalizePlanCode(
            $subscription['contracted_plan_code'] ?? ($subscription['plan_code'] ?? null)
        );
        $storedEffectivePlanCode = $this->normalizePlanCode($subscription['effective_plan_code'] ?? null);
        $planCode = $this->normalizePlanCode($subscription['plan_code'] ?? null);
        $billingPeriod = $this->toNullableText($subscription['billing_period'] ?? null) ?? 'annual';
        $startsAt = $this->parseDateTime($subscription['starts_at'] ?? null);
        $expiresAt = $this->parseDateTime($subscription['expires_at'] ?? null);
        $graceStartsAt = $this->parseDateTime($subscription['grace_starts_at'] ?? null);
        $graceEndsAt = $this->parseDateTime($subscription['grace_ends_at'] ?? null);
        $now = $this->now();

        $isExpired = ($expiresAt !== null && $expiresAt < $now);
        $isInGrace = $this->isInGraceWindow($status, $now, $expiresAt, $graceStartsAt, $graceEndsAt);
        $isActive = (
            $status === 'active'
            && ($startsAt === null || $startsAt <= $now)
            && ($expiresAt === null || $expiresAt >= $now)
        );

        if ($isExpired && !$isInGrace) {
            return $this->buildExpiredFreeFallback($entityType, $entityId, $subscription, $contractedPlanCode);
        }

        $effectivePlanCode = $contractedPlanCode ?? $planCode ?? $storedEffectivePlanCode ?? self::FREE_PLAN_CODE;
        $plan = $this->repository->findPlanByCodeAndPeriod($effectivePlanCode, $billingPeriod);
        if ($plan === null && $effectivePlanCode === self::FREE_PLAN_CODE) {
            $plan = $this->repository->findFallbackFreePlan();
        }

        return $this->buildModel([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'contracted_plan_code' => $contractedPlanCode,
            'effective_plan_code' => $effectivePlanCode,
            'plan_label' => $this->resolvePlanLabel($plan, $subscription, $effectivePlanCode),
            'billing_period' => $this->resolveBillingPeriod($plan, $billingPeriod),
            'duration_days' => $this->resolveDurationDays($plan, $subscription),
            'status' => $status,
            'contract_accepted_at' => $this->formatDateTime($this->parseDateTime($subscription['contract_accepted_at'] ?? null)),
            'starts_at' => $this->formatDateTime($startsAt),
            'expires_at' => $this->formatDateTime($expiresAt),
            'grace_starts_at' => $this->formatDateTime($graceStartsAt),
            'grace_ends_at' => $this->formatDateTime($graceEndsAt),
            'grace_status' => $isInGrace ? 'active' : null,
            'is_free_fallback' => false,
            'is_paid_plan' => $effectivePlanCode !== self::FREE_PLAN_CODE,
            'is_active' => $isActive || $isInGrace,
            'is_expired' => $isExpired,
            'is_in_grace' => $isInGrace,
            'days_until_expiration' => $this->daysUntil($expiresAt, $now),
            'source' => 'profile_subscriptions.current_candidate',
            'version' => self::VERSION,
            'feature_access' => $this->featureAccess($effectivePlanCode, $status, $isActive || $isInGrace, false),
        ]);
    }

    private function buildExpiredFreeFallback(
        string $entityType,
        string $entityId,
        array $subscription,
        ?string $contractedPlanCode
    ): array {
        $freePlan = $this->repository->findFallbackFreePlan();
        $expiresAt = $this->parseDateTime($subscription['expires_at'] ?? null);

        return $this->buildModel([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'contracted_plan_code' => $contractedPlanCode,
            'effective_plan_code' => self::FREE_PLAN_CODE,
            'plan_label' => $this->resolvePlanLabel($freePlan, null, self::FREE_PLAN_CODE),
            'billing_period' => $this->resolveBillingPeriod($freePlan, self::FREE_BILLING_PERIOD),
            'duration_days' => $this->resolveDurationDays($freePlan, null),
            'status' => 'expired',
            'contract_accepted_at' => $this->formatDateTime($this->parseDateTime($subscription['contract_accepted_at'] ?? null)),
            'starts_at' => $this->formatDateTime($this->parseDateTime($subscription['starts_at'] ?? null)),
            'expires_at' => $this->formatDateTime($expiresAt),
            'grace_starts_at' => $this->formatDateTime($this->parseDateTime($subscription['grace_starts_at'] ?? null)),
            'grace_ends_at' => $this->formatDateTime($this->parseDateTime($subscription['grace_ends_at'] ?? null)),
            'grace_status' => null,
            'is_free_fallback' => true,
            'is_paid_plan' => false,
            'is_active' => true,
            'is_expired' => true,
            'is_in_grace' => false,
            'days_until_expiration' => null,
            'source' => 'profile_subscriptions.expired_free_fallback',
            'version' => self::VERSION,
            'feature_access' => $this->featureAccess(self::FREE_PLAN_CODE, 'free_default', true, true),
        ]);
    }

    private function buildFreeDefault(string $entityType, string $entityId, bool $isFallback): array
    {
        $freePlan = $this->repository->findFallbackFreePlan();

        return $this->buildModel([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'contracted_plan_code' => null,
            'effective_plan_code' => self::FREE_PLAN_CODE,
            'plan_label' => $this->resolvePlanLabel($freePlan, null, self::FREE_PLAN_CODE),
            'billing_period' => $this->resolveBillingPeriod($freePlan, self::FREE_BILLING_PERIOD),
            'duration_days' => $this->resolveDurationDays($freePlan, null),
            'status' => 'free_default',
            'contract_accepted_at' => null,
            'starts_at' => null,
            'expires_at' => null,
            'grace_starts_at' => null,
            'grace_ends_at' => null,
            'grace_status' => null,
            'is_free_fallback' => $isFallback,
            'is_paid_plan' => false,
            'is_active' => true,
            'is_expired' => false,
            'is_in_grace' => false,
            'days_until_expiration' => null,
            'source' => $freePlan !== null ? 'subscription_plans.default_free' : 'code.default_free',
            'version' => self::VERSION,
            'feature_access' => $this->featureAccess(self::FREE_PLAN_CODE, 'free_default', true, $isFallback),
        ]);
    }

    private function buildModel(array $data): array
    {
        return [
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'contracted_plan_code' => $data['contracted_plan_code'],
            'effective_plan_code' => $data['effective_plan_code'],
            'plan_label' => $data['plan_label'],
            'billing_period' => $data['billing_period'],
            'duration_days' => $data['duration_days'],
            'status' => $data['status'],
            'contract_accepted_at' => $data['contract_accepted_at'],
            'starts_at' => $data['starts_at'],
            'expires_at' => $data['expires_at'],
            'grace_starts_at' => $data['grace_starts_at'],
            'grace_ends_at' => $data['grace_ends_at'],
            'grace_status' => $data['grace_status'],
            'is_free_fallback' => $data['is_free_fallback'],
            'is_paid_plan' => $data['is_paid_plan'],
            'is_active' => $data['is_active'],
            'is_expired' => $data['is_expired'],
            'is_in_grace' => $data['is_in_grace'],
            'days_until_expiration' => $data['days_until_expiration'],
            'source' => $data['source'],
            'version' => $data['version'],
            'feature_access' => $data['feature_access'],
        ];
    }

    /**
     * Keep internal reason codes inside the authority service only. The
     * additive read-model exposes stable decisions without technical details
     * or commercial copy, so old clients can continue consuming current data.
     */
    private function featureAccess(string $planCode, string $status, bool $isActive, bool $isFreeFallback): array
    {
        $context = [
            'plan_code' => $planCode,
            'subscription_status' => $status,
            'is_active' => $isActive,
            'is_free_fallback' => $isFreeFallback,
        ];
        $decisions = $this->capabilityAuthority->resolveMany(self::EXISTING_CAPABILITY_IDS, $context);
        $public = [];
        foreach ($decisions as $capabilityId => $decision) {
            if (!$decision instanceof ExistingCapabilityDecision) {
                continue;
            }
            $public[$capabilityId] = $decision->publicArray();
        }
        return $public;
    }

    private function isInGraceWindow(
        string $status,
        DateTimeImmutable $now,
        ?DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $graceStartsAt,
        ?DateTimeImmutable $graceEndsAt
    ): bool {
        if ($graceEndsAt === null || $now > $graceEndsAt) {
            return false;
        }

        $startsAt = $graceStartsAt ?? $expiresAt;
        if ($startsAt !== null && $now < $startsAt) {
            return false;
        }

        return $status === 'grace_period' || ($expiresAt !== null && $expiresAt < $now);
    }

    private function daysUntil(?DateTimeImmutable $target, DateTimeImmutable $now): ?int
    {
        if ($target === null || $target < $now) {
            return null;
        }

        $seconds = $target->getTimestamp() - $now->getTimestamp();
        return (int)ceil($seconds / 86400);
    }

    private function resolvePlanLabel(?array $plan, ?array $subscription, string $planCode): string
    {
        $label = $this->toNullableText($plan['plan_label'] ?? null)
            ?? $this->toNullableText($subscription['plan_label'] ?? null);
        if ($label !== null) {
            return $label;
        }

        $labels = [
            'free' => 'Gratuito',
            'basic' => 'Básico',
            'standard' => 'Estándar',
            'optimum' => 'Óptimo',
            'professional' => 'Profesional',
        ];
        return $labels[$planCode] ?? $planCode;
    }

    private function resolveBillingPeriod(?array $plan, string $fallback): string
    {
        return $this->toNullableText($plan['billing_period'] ?? null) ?? $fallback;
    }

    private function resolveDurationDays(?array $plan, ?array $subscription): int
    {
        if (isset($plan['duration_days'])) {
            return max(0, (int)$plan['duration_days']);
        }
        if (isset($subscription['duration_days'])) {
            return max(0, (int)$subscription['duration_days']);
        }
        return 0;
    }

    private function normalizeStatus($status): string
    {
        $status = strtolower(trim((string)($status ?? '')));
        $allowed = [
            'draft',
            'active',
            'expiring_soon',
            'grace_period',
            'expired',
            'inactive',
            'cancelled',
            'renewed',
        ];
        return in_array($status, $allowed, true) ? $status : 'draft';
    }

    private function normalizePlanCode($planCode): ?string
    {
        $code = strtolower(trim((string)($planCode ?? '')));
        if ($code === '') {
            return null;
        }

        $aliases = [
            'gratuito' => 'free',
            'basico' => 'basic',
            'básico' => 'basic',
            'estandar' => 'standard',
            'estándar' => 'standard',
            'optimo' => 'optimum',
            'óptimo' => 'optimum',
            'profesional' => 'professional',
        ];

        return $aliases[$code] ?? $code;
    }

    private function parseDateTime($value): ?DateTimeImmutable
    {
        $value = $this->toNullableText($value);
        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function formatDateTime(?DateTimeInterface $dateTime): ?string
    {
        return $dateTime?->format('Y-m-d H:i:s');
    }

    private function now(): DateTimeImmutable
    {
        return $this->now ?? new DateTimeImmutable('now');
    }

    private function toNullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    }
}
