<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateTimeImmutable;
use DateTimeZone;
use Profiles\Services\PublicProfilePlanCapabilities;
use Subscriptions\Policy\MxmedPlanCapabilityPolicy;
use Subscriptions\Repositories\CurrentSubscriptionRepository;
use Subscriptions\Repositories\SubscriptionCheckoutIntentRepository;
use Subscriptions\Repositories\SubscriptionContractAcceptanceRepository;
use Subscriptions\Repositories\SubscriptionPaymentEventRepository;
use Subscriptions\Repositories\SubscriptionPaymentIntentRepository;
use Throwable;

require_once __DIR__ . '/../../profiles/services/PublicProfilePlanCapabilities.php';
require_once __DIR__ . '/../policy/MxmedPlanCapabilityPolicy.php';

final class BuildSubscriptionPaymentActivationStateService
{
    private const CHECKOUT_STATUS_PENDING_PAYMENT = 'pending_payment';
    private const ACCEPTANCE_STATUS_PENDING_PAYMENT = 'accepted_pending_payment';
    private const PAYMENT_INTENT_STATUS_PAID = 'paid';
    private const PROVIDER_MXMED_MOCK = 'mxmed_mock';
    private const PROVIDER_STRIPE = 'stripe';
    private const PROVIDER_STATUS_MOCK_PAID = 'mock_paid';
    private const PROVIDER_STATUS_PAID = 'paid';
    private const PROVIDER_STATUS_STRIPE_SUCCEEDED = 'succeeded';
    private const EVENT_TYPE_CONFIRM = 'payment_intent_confirm';
    private const EVENT_PROCESSING_STATUS_PROCESSED = 'processed';
    private const INTENT_TYPE_NEW_SUBSCRIPTION = 'new_subscription';
    private const INTENT_TYPE_UPGRADE = 'upgrade';
    private const PRICING_STRATEGY_PRORATED_DIFFERENCE = 'prorated_difference';
    private const SOURCE_UPGRADE_ACTIVATION = 'mxmed_payment_intent_upgrade_activation_v1';
    private const MONTHLY_MARKUP_FACTOR = 1.25;
    private const MONTHLY_MARKUP_PERCENT = 25;
    private SubscriptionCheckoutIntentRepository $checkoutIntentRepository;
    private SubscriptionPaymentIntentRepository $paymentIntentRepository;
    private SubscriptionPaymentEventRepository $paymentEventRepository;
    private SubscriptionContractAcceptanceRepository $contractAcceptanceRepository;
    private CurrentSubscriptionRepository $currentSubscriptionRepository;

    public function __construct(
        SubscriptionCheckoutIntentRepository $checkoutIntentRepository,
        SubscriptionPaymentIntentRepository $paymentIntentRepository,
        SubscriptionPaymentEventRepository $paymentEventRepository,
        SubscriptionContractAcceptanceRepository $contractAcceptanceRepository,
        CurrentSubscriptionRepository $currentSubscriptionRepository
    ) {
        $this->checkoutIntentRepository = $checkoutIntentRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->paymentEventRepository = $paymentEventRepository;
        $this->contractAcceptanceRepository = $contractAcceptanceRepository;
        $this->currentSubscriptionRepository = $currentSubscriptionRepository;
    }

    public function build(array $input): array
    {
        $reasons = [];
        $entityType = $this->cleanText($input['entity_type'] ?? null, 64);
        $entityType = $entityType !== null ? strtolower($entityType) : null;
        $entityId = $this->cleanText($input['entity_id'] ?? null, 64);
        $checkoutIntentUuid = $this->cleanText($input['checkout_intent_uuid'] ?? null, 36);
        $paymentIntentUuid = $this->cleanText($input['payment_intent_uuid'] ?? null, 36);
        $audience = $this->normalizeAudience($input['audience'] ?? null);

        $scopeValid = $entityType !== null && $entityId !== null;
        if (!$scopeValid) {
            $this->addReason($reasons, 'entity_scope_invalid');
        }

        $checkoutIntent = $this->lookupCheckoutIntent($checkoutIntentUuid, $entityType, $entityId, $reasons);
        $paymentIntent = $this->lookupPaymentIntent($paymentIntentUuid, $checkoutIntent, $reasons);
        if ($checkoutIntent === null && $paymentIntent !== null) {
            $paymentCheckoutIntentUuid = $this->cleanText($paymentIntent['checkout_intent_uuid'] ?? null, 36);
            if ($paymentCheckoutIntentUuid !== null) {
                $checkoutIntent = $this->lookupCheckoutIntent($paymentCheckoutIntentUuid, $entityType, $entityId, $reasons);
            }
        }
        $paymentEvent = $this->lookupPaymentEvent($paymentIntent, $checkoutIntent, $reasons);
        $contractAcceptance = $this->lookupContractAcceptance($checkoutIntent, $entityType, $entityId, $reasons);
        $activeSubscription = $this->lookupActiveSubscription($entityType, $entityId, $reasons);
        $checkoutDeclaresUpgrade = $this->checkoutIntentDeclaresUpgrade($checkoutIntent);
        $isUpgradeCheckout = $checkoutDeclaresUpgrade || $this->checkoutIntentHasSafeUpgradeActivationTrace($checkoutIntent);
        $upgradeContext = $this->upgradeContext($checkoutIntent, $checkoutDeclaresUpgrade, $reasons);

        $this->evaluateState(
            $entityType,
            $entityId,
            $checkoutIntent,
            $paymentIntent,
            $paymentEvent,
            $contractAcceptance,
            $activeSubscription,
            $isUpgradeCheckout,
            $upgradeContext,
            $reasons
        );

        $canActivate = $reasons === [];

        return [
            'ok' => true,
            'entity' => [
                'entity_type' => $entityType,
                'entity_id' => $this->entityIdValue($entityId),
                'scope_valid' => $scopeValid && !in_array('entity_scope_invalid', $reasons, true),
                'audience' => $audience,
            ],
            'checkout_intent' => $this->checkoutIntentState($checkoutIntent, $isUpgradeCheckout),
            'payment_intent' => $this->paymentIntentState($paymentIntent),
            'payment_event' => $this->paymentEventState($paymentEvent),
            'contract_acceptance' => $this->contractAcceptanceState($contractAcceptance),
            'active_subscription' => $this->activeSubscriptionState($activeSubscription),
            'upgrade' => $this->upgradeState($upgradeContext),
            'upgrade_explanation' => $this->upgradeExplanation($upgradeContext, $activeSubscription),
            'activation_eligibility' => [
                'can_activate' => $canActivate,
                'reasons' => array_values($reasons),
                'required_action' => $canActivate ? 'activate_after_payment' : 'resolve_activation_state',
            ],
            'idempotency' => [
                'key_strategy' => 'client_generated_per_activation_attempt',
                'replay_safe' => true,
            ],
            'ui' => $this->uiState($canActivate, $reasons, $upgradeContext !== null),
        ];
    }

    private function lookupCheckoutIntent(?string $checkoutIntentUuid, ?string $entityType, ?string $entityId, array &$reasons): ?array
    {
        if ($checkoutIntentUuid !== null) {
            return $this->safeLookup(
                fn() => $this->checkoutIntentRepository->findByUuid($checkoutIntentUuid),
                $reasons
            );
        }

        $entityIdInt = $this->positiveInt($entityId);
        if ($entityType === null || $entityIdInt === null) {
            return null;
        }

        return $this->safeLookup(
            fn() => $this->checkoutIntentRepository->findLatestPendingPaymentByEntity($entityType, $entityIdInt),
            $reasons
        );
    }

    private function lookupPaymentIntent(?string $paymentIntentUuid, ?array $checkoutIntent, array &$reasons): ?array
    {
        if ($paymentIntentUuid !== null) {
            return $this->safeLookup(
                fn() => $this->paymentIntentRepository->findByUuid($paymentIntentUuid),
                $reasons
            );
        }

        $checkoutIntentUuid = $this->cleanText($checkoutIntent['uuid'] ?? null, 36);
        if ($checkoutIntentUuid === null) {
            return null;
        }

        return $this->safeLookup(
            fn() => $this->paymentIntentRepository->findByCheckoutIntentUuid($checkoutIntentUuid),
            $reasons
        );
    }

    private function lookupPaymentEvent(?array $paymentIntent, ?array $checkoutIntent, array &$reasons): ?array
    {
        $paymentIntentUuid = $this->cleanText($paymentIntent['uuid'] ?? null, 36);
        if ($paymentIntentUuid !== null) {
            return $this->safeLookup(
                fn() => $this->paymentEventRepository->findProcessedConfirmByPaymentIntentUuid($paymentIntentUuid),
                $reasons
            );
        }

        $checkoutIntentUuid = $this->cleanText($checkoutIntent['uuid'] ?? null, 36);
        if ($checkoutIntentUuid === null) {
            return null;
        }

        return $this->safeLookup(
            fn() => $this->paymentEventRepository->findProcessedConfirmByCheckoutIntentUuid($checkoutIntentUuid),
            $reasons
        );
    }

    private function lookupContractAcceptance(?array $checkoutIntent, ?string $entityType, ?string $entityId, array &$reasons): ?array
    {
        $contractAcceptanceUuid = $this->cleanText($checkoutIntent['contract_acceptance_uuid'] ?? null, 36);
        if ($contractAcceptanceUuid !== null) {
            return $this->safeLookup(
                fn() => $this->contractAcceptanceRepository->findPendingPaymentByUuid($contractAcceptanceUuid),
                $reasons
            );
        }

        $entityIdInt = $this->positiveInt($entityId);
        if ($entityType === null || $entityIdInt === null) {
            return null;
        }

        return $this->safeLookup(
            fn() => $this->contractAcceptanceRepository->findPendingPaymentByEntity($entityType, $entityIdInt),
            $reasons
        );
    }

    private function lookupActiveSubscription(?string $entityType, ?string $entityId, array &$reasons): ?array
    {
        if ($entityType === null || $entityId === null) {
            return null;
        }

        return $this->safeLookup(
            fn() => $this->currentSubscriptionRepository->findActiveByEntity($entityType, $entityId),
            $reasons
        );
    }

    private function evaluateState(
        ?string $entityType,
        ?string $entityId,
        ?array $checkoutIntent,
        ?array $paymentIntent,
        ?array $paymentEvent,
        ?array $contractAcceptance,
        ?array $activeSubscription,
        bool $isUpgradeCheckout,
        ?array $upgradeContext,
        array &$reasons
    ): void {
        if ($checkoutIntent === null) {
            $this->addReason($reasons, 'checkout_intent_missing');
        } else {
            if ((string)($checkoutIntent['status'] ?? '') !== self::CHECKOUT_STATUS_PENDING_PAYMENT) {
                $this->addReason($reasons, 'checkout_intent_not_pending_payment');
            }
            if ($this->checkoutIntentIsExpired($checkoutIntent)
                && !$this->paidBeforeCheckoutExpiry($checkoutIntent, $paymentIntent, $paymentEvent)
            ) {
                $this->addReason($reasons, 'checkout_intent_expired');
            }
            if ($entityType !== null
                && $entityId !== null
                && ((string)($checkoutIntent['entity_type'] ?? '') !== $entityType
                    || (string)($checkoutIntent['entity_id'] ?? '') !== $entityId)
            ) {
                $this->addReason($reasons, 'entity_scope_invalid');
            }
            if ($this->cleanText($checkoutIntent['subscription_id'] ?? null, 36) !== null
                || $this->cleanText($checkoutIntent['activated_at'] ?? null, 32) !== null
            ) {
                $this->addReason($reasons, 'activation_already_done');
            }
        }

        if ($paymentIntent === null) {
            $this->addReason($reasons, 'payment_intent_missing');
        } else {
            if (!$this->paymentIntentIsConfirmed($paymentIntent)) {
                $this->addReason($reasons, 'payment_intent_not_paid');
            }
            if ($checkoutIntent !== null
                && (string)($paymentIntent['checkout_intent_uuid'] ?? '') !== (string)($checkoutIntent['uuid'] ?? '')
            ) {
                $this->addReason($reasons, 'checkout_payment_mismatch');
            }
        }

        if ($paymentEvent === null) {
            $this->addReason($reasons, 'payment_event_missing');
        } else {
            if ((string)($paymentEvent['event_type'] ?? '') !== self::EVENT_TYPE_CONFIRM
                || (string)($paymentEvent['processing_status'] ?? '') !== self::EVENT_PROCESSING_STATUS_PROCESSED
            ) {
                $this->addReason($reasons, 'payment_event_not_processed');
            }
            if ($this->cleanText($paymentEvent['uuid'] ?? null, 36) === null) {
                $this->addReason($reasons, 'payment_event_missing');
            }
            if ($paymentIntent !== null
                && (string)($paymentEvent['payment_intent_uuid'] ?? '') !== (string)($paymentIntent['uuid'] ?? '')
            ) {
                $this->addReason($reasons, 'payment_event_payment_intent_mismatch');
            }
            if ($checkoutIntent !== null
                && $this->cleanText($paymentEvent['checkout_intent_uuid'] ?? null, 36) !== null
                && (string)($paymentEvent['checkout_intent_uuid'] ?? '') !== (string)($checkoutIntent['uuid'] ?? '')
            ) {
                $this->addReason($reasons, 'checkout_payment_mismatch');
            }
        }

        if ($contractAcceptance === null) {
            $this->addReason($reasons, 'contract_acceptance_missing');
        } else {
            if ((string)($contractAcceptance['status'] ?? '') !== self::ACCEPTANCE_STATUS_PENDING_PAYMENT) {
                $this->addReason($reasons, 'contract_acceptance_not_pending_payment');
            }
            if ($this->cleanText($contractAcceptance['subscription_id'] ?? null, 36) !== null) {
                $this->addReason($reasons, 'activation_already_done');
            }
            if ($checkoutIntent !== null
                && ((string)($contractAcceptance['entity_type'] ?? '') !== (string)($checkoutIntent['entity_type'] ?? '')
                    || (string)($contractAcceptance['entity_id'] ?? '') !== (string)($checkoutIntent['entity_id'] ?? '')
                    || (string)($contractAcceptance['plan_code'] ?? '') !== (string)($checkoutIntent['plan_code'] ?? '')
                    || (string)($contractAcceptance['billing_period'] ?? '') !== (string)($checkoutIntent['billing_period'] ?? ''))
            ) {
                $this->addReason($reasons, 'contract_acceptance_not_pending_payment');
            }
        }

        if ($isUpgradeCheckout) {
            if (in_array('activation_already_done', $reasons, true)) {
                return;
            }
            if ($upgradeContext === null) {
                return;
            }
            $this->evaluateUpgradeState($checkoutIntent, $activeSubscription, $upgradeContext, $reasons);
            return;
        }

        if ($activeSubscription !== null) {
            $this->addReason($reasons, 'active_subscription_exists');
        }
    }

    private function paymentIntentIsConfirmed(array $paymentIntent): bool
    {
        if ((string)($paymentIntent['normalized_status'] ?? '') !== self::PAYMENT_INTENT_STATUS_PAID) {
            return false;
        }

        $provider = strtolower((string)($paymentIntent['provider'] ?? ''));
        $providerStatus = strtolower((string)($paymentIntent['provider_status'] ?? ''));

        if ($provider === self::PROVIDER_MXMED_MOCK) {
            return in_array($providerStatus, [
                self::PROVIDER_STATUS_MOCK_PAID,
                self::PROVIDER_STATUS_PAID,
            ], true);
        }

        if ($provider === self::PROVIDER_STRIPE) {
            return in_array($providerStatus, [
                self::PROVIDER_STATUS_STRIPE_SUCCEEDED,
                self::PROVIDER_STATUS_PAID,
            ], true);
        }

        return $providerStatus === self::PROVIDER_STATUS_PAID;
    }

    private function evaluateUpgradeState(?array $checkoutIntent, ?array $activeSubscription, array $upgradeContext, array &$reasons): void
    {
        if ($activeSubscription === null) {
            $this->addReason($reasons, 'active_subscription_required_for_upgrade');
            return;
        }

        if ((string)($activeSubscription['plan_code'] ?? '') !== (string)$upgradeContext['current_plan_code']) {
            $this->addReason($reasons, 'upgrade_current_plan_mismatch');
        }
        if ((string)($activeSubscription['billing_period'] ?? '') !== (string)$upgradeContext['current_billing_period']) {
            $this->addReason($reasons, 'upgrade_billing_period_mismatch');
        }

        if ($checkoutIntent !== null) {
            if ((string)($checkoutIntent['plan_code'] ?? '') !== (string)$upgradeContext['target_plan_code']) {
                $this->addReason($reasons, 'upgrade_target_plan_mismatch');
            }
            if ((string)($checkoutIntent['billing_period'] ?? '') !== (string)$upgradeContext['target_billing_period']) {
                $this->addReason($reasons, 'upgrade_billing_period_mismatch');
            }
        }
    }

    private function checkoutIntentState(?array $checkoutIntent, bool $isUpgradeCheckout): ?array
    {
        if ($checkoutIntent === null) {
            return null;
        }

        return [
            'uuid' => (string)($checkoutIntent['uuid'] ?? ''),
            'status' => (string)($checkoutIntent['status'] ?? ''),
            'plan_code' => (string)($checkoutIntent['plan_code'] ?? ''),
            'billing_period' => (string)($checkoutIntent['billing_period'] ?? ''),
            'amount_cents' => isset($checkoutIntent['amount_cents']) ? (int)$checkoutIntent['amount_cents'] : null,
            'currency' => (string)($checkoutIntent['currency'] ?? ''),
            'expires_at' => $this->cleanText($checkoutIntent['expires_at'] ?? null, 32),
            'subscription_id' => $this->cleanText($checkoutIntent['subscription_id'] ?? null, 36),
            'contract_acceptance_uuid' => $this->cleanText($checkoutIntent['contract_acceptance_uuid'] ?? null, 36),
            'activated_at' => $this->cleanText($checkoutIntent['activated_at'] ?? null, 32),
            'intent_type' => $isUpgradeCheckout ? self::INTENT_TYPE_UPGRADE : self::INTENT_TYPE_NEW_SUBSCRIPTION,
        ];
    }

    private function checkoutIntentIsExpired(array $checkoutIntent): bool
    {
        $expiresAt = $this->cleanText($checkoutIntent['expires_at'] ?? null, 32);
        if ($expiresAt === null) {
            return false;
        }

        try {
            $expiresAtDate = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));
            $nowDate = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return true;
        }

        return $expiresAtDate < $nowDate;
    }

    private function paidBeforeCheckoutExpiry(
        array $checkoutIntent,
        ?array $paymentIntent,
        ?array $paymentEvent
    ): bool {
        $expiresAtDate = $this->parseUtcDate($checkoutIntent['expires_at'] ?? null);
        if ($expiresAtDate === null || $paymentIntent === null || $paymentEvent === null) {
            return false;
        }

        if (!$this->paymentIntentIsConfirmed($paymentIntent)) {
            return false;
        }

        if ((string)($paymentIntent['checkout_intent_uuid'] ?? '') !== (string)($checkoutIntent['uuid'] ?? '')) {
            return false;
        }

        if ((string)($paymentEvent['event_type'] ?? '') !== self::EVENT_TYPE_CONFIRM
            || (string)($paymentEvent['normalized_status'] ?? '') !== self::PAYMENT_INTENT_STATUS_PAID
            || (string)($paymentEvent['processing_status'] ?? '') !== self::EVENT_PROCESSING_STATUS_PROCESSED
        ) {
            return false;
        }

        if ((string)($paymentEvent['payment_intent_uuid'] ?? '') !== (string)($paymentIntent['uuid'] ?? '')) {
            return false;
        }

        $paymentEventCheckoutUuid = $this->cleanText($paymentEvent['checkout_intent_uuid'] ?? null, 36);
        if ($paymentEventCheckoutUuid !== null
            && $paymentEventCheckoutUuid !== (string)($checkoutIntent['uuid'] ?? '')
        ) {
            return false;
        }

        $paidAtDate = $this->parseUtcDate($paymentIntent['paid_at'] ?? null);
        if ($paidAtDate === null || $this->parseUtcDate($paymentEvent['processed_at'] ?? null) === null) {
            return false;
        }

        return $paidAtDate <= $expiresAtDate;
    }

    private function parseUtcDate($value): ?DateTimeImmutable
    {
        $text = $this->cleanText($value, 32);
        if ($text === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($text, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return null;
        }
    }

    private function paymentIntentState(?array $paymentIntent): ?array
    {
        if ($paymentIntent === null) {
            return null;
        }

        return [
            'uuid' => (string)($paymentIntent['uuid'] ?? ''),
            'checkout_intent_uuid' => (string)($paymentIntent['checkout_intent_uuid'] ?? ''),
            'provider' => (string)($paymentIntent['provider'] ?? ''),
            'normalized_status' => (string)($paymentIntent['normalized_status'] ?? ''),
            'provider_status' => $this->cleanText($paymentIntent['provider_status'] ?? null, 64),
            'paid_at' => $this->cleanText($paymentIntent['paid_at'] ?? null, 32),
        ];
    }

    private function paymentEventState(?array $paymentEvent): ?array
    {
        if ($paymentEvent === null) {
            return null;
        }

        return [
            'uuid' => (string)($paymentEvent['uuid'] ?? ''),
            'payment_intent_uuid' => $this->cleanText($paymentEvent['payment_intent_uuid'] ?? null, 36),
            'checkout_intent_uuid' => $this->cleanText($paymentEvent['checkout_intent_uuid'] ?? null, 36),
            'event_type' => (string)($paymentEvent['event_type'] ?? ''),
            'processing_status' => (string)($paymentEvent['processing_status'] ?? ''),
            'processed_at' => $this->cleanText($paymentEvent['processed_at'] ?? null, 32),
        ];
    }

    private function contractAcceptanceState(?array $contractAcceptance): ?array
    {
        if ($contractAcceptance === null) {
            return null;
        }

        return [
            'uuid' => (string)($contractAcceptance['uuid'] ?? ''),
            'status' => (string)($contractAcceptance['status'] ?? ''),
            'subscription_id' => $this->cleanText($contractAcceptance['subscription_id'] ?? null, 36),
        ];
    }

    private function activeSubscriptionState(?array $activeSubscription): array
    {
        return [
            'exists' => $activeSubscription !== null,
            'subscription_id' => $activeSubscription !== null
                ? $this->cleanText($activeSubscription['subscription_id'] ?? null, 36)
                : null,
            'status' => $activeSubscription !== null
                ? $this->cleanText($activeSubscription['status'] ?? null, 32)
                : null,
            'plan_code' => $activeSubscription !== null
                ? $this->cleanText($activeSubscription['plan_code'] ?? null, 64)
                : null,
            'billing_period' => $activeSubscription !== null
                ? $this->cleanText($activeSubscription['billing_period'] ?? null, 32)
                : null,
            'starts_at' => $activeSubscription !== null
                ? $this->cleanText($activeSubscription['starts_at'] ?? null, 32)
                : null,
            'expires_at' => $activeSubscription !== null
                ? $this->cleanText($activeSubscription['expires_at'] ?? null, 32)
                : null,
        ];
    }

    private function upgradeState(?array $upgradeContext): ?array
    {
        if ($upgradeContext === null) {
            return null;
        }

        return [
            'intent_type' => self::INTENT_TYPE_UPGRADE,
            'current_plan_code' => $upgradeContext['current_plan_code'],
            'target_plan_code' => $upgradeContext['target_plan_code'],
            'current_billing_period' => $upgradeContext['current_billing_period'],
            'target_billing_period' => $upgradeContext['target_billing_period'],
            'adjustment_amount_cents' => $upgradeContext['adjustment_amount_cents'],
            'currency' => $upgradeContext['currency'],
            'pricing_strategy' => $upgradeContext['pricing_strategy'],
        ];
    }

    private function upgradeExplanation(?array $upgradeContext, ?array $activeSubscription): ?array
    {
        if ($upgradeContext === null) {
            return null;
        }

        $currentPlanCode = (string)$upgradeContext['current_plan_code'];
        $targetPlanCode = (string)$upgradeContext['target_plan_code'];
        $targetBillingPeriod = (string)$upgradeContext['target_billing_period'];
        $currentPlanPrice = $this->nullablePositiveAmount($upgradeContext['current_price_period_cents'] ?? null);
        $targetPlanPrice = $this->nullablePositiveAmount($upgradeContext['target_price_period_cents'] ?? null);
        $differenceCents = $this->nullablePositiveAmount($upgradeContext['price_difference_cents'] ?? null);
        if ($differenceCents === null && $currentPlanPrice !== null && $targetPlanPrice !== null) {
            $calculatedDifference = $targetPlanPrice - $currentPlanPrice;
            $differenceCents = $calculatedDifference > 0 ? $calculatedDifference : null;
        }

        $totalPeriodDays = $this->nullablePositiveAmount($upgradeContext['total_period_days'] ?? null);
        $remainingDays = $this->nullablePositiveAmount($upgradeContext['remaining_days'] ?? null);
        $elapsedDays = $totalPeriodDays !== null && $remainingDays !== null
            ? max(0, $totalPeriodDays - $remainingDays)
            : null;

        $coveredUntil = $this->cleanText($activeSubscription['expires_at'] ?? null, 32);
        $targetLabel = $this->planLabel($targetPlanCode);
        $annualPrice = $targetBillingPeriod === 'annual' ? $targetPlanPrice : null;
        $monthlyPrice = $annualPrice !== null
            ? (int)round(($annualPrice / 12) * self::MONTHLY_MARKUP_FACTOR)
            : ($targetBillingPeriod === 'monthly' ? $targetPlanPrice : null);
        $benefitsSummary = $this->upgradeBenefitsSummary($currentPlanCode, $targetPlanCode);

        return [
            'current_plan' => [
                'code' => $currentPlanCode,
                'label' => $this->planLabel($currentPlanCode),
            ],
            'target_plan' => [
                'code' => $targetPlanCode,
                'label' => $targetLabel,
            ],
            'benefits_summary' => $benefitsSummary,
            'benefits_source' => 'public_profile_plan_capabilities',
            'pricing_explanation' => [
                'strategy' => (string)$upgradeContext['pricing_strategy'],
                'reason' => 'Solo pagas la diferencia proporcional por el tiempo restante de tu suscripcion actual.',
                'current_plan_price_cents' => $currentPlanPrice,
                'target_plan_price_cents' => $targetPlanPrice,
                'difference_cents' => $differenceCents,
                'elapsed_days' => $elapsedDays,
                'remaining_days' => $remainingDays,
                'total_period_days' => $totalPeriodDays,
                'adjustment_amount_cents' => (int)$upgradeContext['adjustment_amount_cents'],
                'currency' => (string)$upgradeContext['currency'],
            ],
            'coverage' => [
                'covered_until' => $coveredUntil,
                'message' => $this->coverageMessage($targetLabel, $coveredUntil),
            ],
            'renewal_after_coverage' => [
                'annual_price_cents' => $annualPrice,
                'monthly_price_cents' => $monthlyPrice,
                'monthly_price_formula' => 'annual_price / 12 * 1.25',
                'monthly_markup_percent' => self::MONTHLY_MARKUP_PERCENT,
                'message' => 'Al renovar despues de esa fecha, se aplicara el precio regular vigente del plan ' . $targetLabel . '.',
            ],
            'activation_rule' => [
                'recalculates_on_activation' => false,
                'message' => 'El monto ya fue calculado al crear el checkout y no se recalcula al activar.',
            ],
        ];
    }

    private function upgradeBenefitsSummary(string $currentPlanCode, string $targetPlanCode): array
    {
        $currentPlanCode = PublicProfilePlanCapabilities::normalizePlanCode($currentPlanCode);
        $targetPlanCode = PublicProfilePlanCapabilities::normalizePlanCode($targetPlanCode);
        if ($currentPlanCode === $targetPlanCode) {
            return [];
        }

        $currentCapabilities = $this->publicProfileCapabilities($currentPlanCode);
        $targetCapabilities = $this->publicProfileCapabilities($targetPlanCode);
        if ($currentCapabilities === [] || $targetCapabilities === []) {
            return [];
        }

        $summary = [];
        foreach ($this->upgradeBenefitCatalog() as $capabilityKey => $copy) {
            $currentIncluded = (bool)($currentCapabilities[$capabilityKey] ?? false);
            $targetIncluded = (bool)($targetCapabilities[$capabilityKey] ?? false);
            if ($currentIncluded || !$targetIncluded) {
                continue;
            }

            $summary[] = [
                'key' => (string)$copy['key'],
                'label' => (string)$copy['label'],
                'description' => (string)$copy['description'],
                'current_plan_included' => false,
                'target_plan_included' => true,
            ];
        }

        return $summary;
    }

    private function publicProfileCapabilities(string $planCode): array
    {
        $contract = PublicProfilePlanCapabilities::build($planCode, [
            'plan_source' => 'subscription_upgrade_explanation',
        ]);
        $capabilities = $contract['plan']['capabilities'] ?? [];

        return is_array($capabilities) ? $capabilities : [];
    }

    private function upgradeBenefitCatalog(): array
    {
        return [
            'show_public_agenda' => [
                'key' => 'public_agenda_visibility',
                'label' => 'Agenda publica en perfil',
                'description' => 'El plan destino habilita mostrar acceso a agenda publica en tu perfil, segun configuracion y disponibilidad.',
            ],
            'show_promotional_packages' => [
                'key' => 'promotional_packages_visibility',
                'label' => 'Promociones y paquetes visibles',
                'description' => 'El plan destino permite mostrar promociones o paquetes comerciales cuando exista informacion configurada para el perfil.',
            ],
            'allow_review_replies' => [
                'key' => 'review_replies',
                'label' => 'Gestion de respuestas a resenas',
                'description' => 'El plan destino habilita capacidades para responder resenas desde el perfil cuando el modulo correspondiente este disponible.',
            ],
            'show_gallery' => [
                'key' => 'public_profile_gallery',
                'label' => 'Galeria en perfil publico',
                'description' => 'El plan destino permite enriquecer la presentacion del perfil con galeria, segun el contenido disponible.',
            ],
            'show_insurances' => [
                'key' => 'accepted_insurances_visibility',
                'label' => 'Aseguradoras visibles',
                'description' => 'El plan destino permite mostrar aseguradoras aceptadas cuando exista informacion comercial configurada.',
            ],
            'show_consultation_details' => [
                'key' => 'consultation_details_visibility',
                'label' => 'Detalles comerciales de consulta',
                'description' => 'El plan destino permite mostrar detalles comerciales de consulta segun la configuracion vigente del perfil.',
            ],
        ];
    }

    private function uiState(bool $canActivate, array $reasons, bool $isUpgradeReady): array
    {
        if ($canActivate) {
            if ($isUpgradeReady) {
                return [
                    'recommended_label' => 'Activar mejora de plan',
                    'recommended_message_code' => 'payment_activation_upgrade_ready',
                    'severity' => 'info',
                    'retryable' => false,
                ];
            }

            return [
                'recommended_label' => 'Activar suscripcion',
                'recommended_message_code' => 'payment_activation_ready',
                'severity' => 'info',
                'retryable' => false,
            ];
        }

        if (in_array('active_subscription_exists', $reasons, true)
            || in_array('activation_already_done', $reasons, true)
        ) {
            return [
                'recommended_label' => 'Suscripcion ya activa',
                'recommended_message_code' => 'payment_activation_already_done',
                'severity' => 'warning',
                'retryable' => false,
            ];
        }

        if (in_array('active_subscription_required_for_upgrade', $reasons, true)
            || in_array('upgrade_context_invalid', $reasons, true)
            || in_array('upgrade_target_plan_not_higher', $reasons, true)
        ) {
            return [
                'recommended_label' => 'Mejora no disponible',
                'recommended_message_code' => 'payment_activation_upgrade_not_ready',
                'severity' => 'warning',
                'retryable' => false,
            ];
        }

        return [
            'recommended_label' => 'Activacion no disponible',
            'recommended_message_code' => 'payment_activation_not_ready',
            'severity' => 'warning',
            'retryable' => false,
        ];
    }

    private function checkoutIntentDeclaresUpgrade(?array $checkoutIntent): bool
    {
        $notes = $this->cleanText($checkoutIntent['notes'] ?? null, 65535);
        if ($notes === null) {
            return false;
        }

        $decoded = json_decode($notes, true);
        if (!is_array($decoded)) {
            return false;
        }

        return strtolower(trim((string)($decoded['intent_type'] ?? ''))) === self::INTENT_TYPE_UPGRADE;
    }

    private function checkoutIntentHasSafeUpgradeActivationTrace(?array $checkoutIntent): bool
    {
        if ($checkoutIntent === null) {
            return false;
        }

        $source = $this->cleanText($checkoutIntent['source'] ?? null, 128);
        if ($source !== self::SOURCE_UPGRADE_ACTIVATION) {
            return false;
        }

        return $this->cleanText($checkoutIntent['subscription_id'] ?? null, 36) !== null
            && $this->cleanText($checkoutIntent['activated_at'] ?? null, 32) !== null;
    }

    private function upgradeContext(?array $checkoutIntent, bool $isUpgradeCheckout, array &$reasons): ?array
    {
        if (!$isUpgradeCheckout) {
            return null;
        }

        $notes = $this->cleanText($checkoutIntent['notes'] ?? null, 65535);
        $decoded = is_string($notes) ? json_decode($notes, true) : null;
        if (!is_array($decoded) || !is_array($decoded['upgrade_context'] ?? null)) {
            $this->addReason($reasons, 'upgrade_context_invalid');
            return null;
        }

        $context = $decoded['upgrade_context'];
        $currentPlanCode = $this->canonicalPlanCode($context['current_plan_code'] ?? null);
        $targetPlanCode = $this->canonicalPlanCode($context['target_plan_code'] ?? null);
        $currentBillingPeriod = strtolower($this->cleanText($context['current_billing_period'] ?? null, 32) ?? '');
        $targetBillingPeriod = strtolower($this->cleanText($context['target_billing_period'] ?? null, 32) ?? '');
        $pricingStrategy = strtolower($this->cleanText($decoded['pricing_strategy'] ?? ($context['pricing_strategy'] ?? null), 64) ?? '');
        $adjustmentAmountCents = $this->positiveAmount($context['adjustment_amount_cents'] ?? null);
        $currency = strtoupper($this->cleanText($context['currency'] ?? null, 3) ?? '');

        if ($currentPlanCode === '' || $targetPlanCode === ''
            || $currentBillingPeriod === '' || $targetBillingPeriod === ''
            || $pricingStrategy !== self::PRICING_STRATEGY_PRORATED_DIFFERENCE
            || $adjustmentAmountCents <= 0
            || $currency === ''
        ) {
            $this->addReason($reasons, 'upgrade_context_invalid');
            return null;
        }

        if ($currentBillingPeriod !== $targetBillingPeriod) {
            $this->addReason($reasons, 'upgrade_billing_period_mismatch');
        }
        if ($this->planRank($targetPlanCode) <= $this->planRank($currentPlanCode)) {
            $this->addReason($reasons, 'upgrade_target_plan_not_higher');
        }

        return [
            'current_plan_code' => $currentPlanCode,
            'target_plan_code' => $targetPlanCode,
            'current_billing_period' => $currentBillingPeriod,
            'target_billing_period' => $targetBillingPeriod,
            'adjustment_amount_cents' => $adjustmentAmountCents,
            'currency' => $currency,
            'pricing_strategy' => $pricingStrategy,
            'current_price_period_cents' => $this->nullablePositiveAmount($context['current_price_period_cents'] ?? null),
            'target_price_period_cents' => $this->nullablePositiveAmount($context['target_price_period_cents'] ?? null),
            'price_difference_cents' => $this->nullablePositiveAmount($context['price_difference_cents'] ?? null),
            'remaining_days' => $this->nullablePositiveAmount($context['remaining_days'] ?? null),
            'total_period_days' => $this->nullablePositiveAmount($context['period_days'] ?? null),
        ];
    }

    private function canonicalPlanCode($value): string
    {
        $planCode = strtolower($this->cleanText($value, 64) ?? '');
        return MxmedPlanCapabilityPolicy::normalizePlanCode($planCode) ?? $planCode;
    }

    private function planRank(string $planCode): int
    {
        return MxmedPlanCapabilityPolicy::planRank($planCode) ?? 0;
    }

    private function positiveAmount($value): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : 0;
        }

        $text = trim((string)($value ?? ''));
        return ctype_digit($text) ? max(0, (int)$text) : 0;
    }

    private function nullablePositiveAmount($value): ?int
    {
        $amount = $this->positiveAmount($value);

        return $amount > 0 ? $amount : null;
    }

    private function planLabel(string $planCode): string
    {
        $labels = [
            'basic' => 'Básico',
            'standard' => 'Estándar',
            'optimum' => 'Óptimo',
            'professional' => 'Profesional',
        ];

        return $labels[$planCode] ?? $planCode;
    }

    private function coverageMessage(string $targetLabel, ?string $coveredUntil): string
    {
        $dateLabel = $this->spanishDateLabel($coveredUntil);
        if ($dateLabel === null) {
            return 'Con este pago tu perfil queda cubierto como ' . $targetLabel . ' hasta la fecha de fin de tu suscripcion vigente.';
        }

        return 'Con este pago tu perfil queda cubierto como ' . $targetLabel . ' hasta el ' . $dateLabel . '.';
    }

    private function spanishDateLabel(?string $value): ?string
    {
        $value = $this->cleanText($value, 32);
        if ($value === null) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return null;
        }

        $months = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        $month = $months[(int)$date->format('n')] ?? '';
        if ($month === '') {
            return null;
        }

        return $date->format('d') . ' de ' . $month . ' de ' . $date->format('Y');
    }

    private function safeLookup(callable $lookup, array &$reasons): ?array
    {
        try {
            $row = $lookup();
        } catch (Throwable $e) {
            $this->addReason($reasons, 'activation_state_unavailable');
            return null;
        }

        return is_array($row) ? $row : null;
    }

    private function addReason(array &$reasons, string $reason): void
    {
        if (!in_array($reason, $reasons, true)) {
            $reasons[] = $reason;
        }
    }

    private function cleanText($value, int $maxLength): ?string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength);
        }

        return $text;
    }

    private function normalizeAudience($value): string
    {
        $audience = strtolower($this->cleanText($value, 32) ?? 'user');
        $allowed = ['dev', 'support', 'admin', 'user'];

        return in_array($audience, $allowed, true) ? $audience : 'user';
    }

    private function positiveInt(?string $value): ?int
    {
        if ($value === null || !ctype_digit($value)) {
            return null;
        }
        $intValue = (int)$value;

        return $intValue > 0 ? $intValue : null;
    }

    private function entityIdValue(?string $entityId)
    {
        $entityIdInt = $this->positiveInt($entityId);

        return $entityIdInt ?? $entityId;
    }
}
