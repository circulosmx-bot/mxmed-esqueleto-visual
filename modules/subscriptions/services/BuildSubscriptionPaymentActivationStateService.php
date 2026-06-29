<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use Subscriptions\Repositories\CurrentSubscriptionRepository;
use Subscriptions\Repositories\SubscriptionCheckoutIntentRepository;
use Subscriptions\Repositories\SubscriptionContractAcceptanceRepository;
use Subscriptions\Repositories\SubscriptionPaymentEventRepository;
use Subscriptions\Repositories\SubscriptionPaymentIntentRepository;
use Throwable;

final class BuildSubscriptionPaymentActivationStateService
{
    private const CHECKOUT_STATUS_PENDING_PAYMENT = 'pending_payment';
    private const ACCEPTANCE_STATUS_PENDING_PAYMENT = 'accepted_pending_payment';
    private const PAYMENT_INTENT_STATUS_PAID = 'paid';
    private const PROVIDER_STATUS_MOCK_PAID = 'mock_paid';
    private const EVENT_TYPE_CONFIRM = 'payment_intent_confirm';
    private const EVENT_PROCESSING_STATUS_PROCESSED = 'processed';

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

        $this->evaluateState(
            $entityType,
            $entityId,
            $checkoutIntent,
            $paymentIntent,
            $paymentEvent,
            $contractAcceptance,
            $activeSubscription,
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
            'checkout_intent' => $this->checkoutIntentState($checkoutIntent),
            'payment_intent' => $this->paymentIntentState($paymentIntent),
            'payment_event' => $this->paymentEventState($paymentEvent),
            'contract_acceptance' => $this->contractAcceptanceState($contractAcceptance),
            'active_subscription' => $this->activeSubscriptionState($activeSubscription),
            'activation_eligibility' => [
                'can_activate' => $canActivate,
                'reasons' => array_values($reasons),
                'required_action' => $canActivate ? 'activate_after_payment' : 'resolve_activation_state',
            ],
            'idempotency' => [
                'key_strategy' => 'client_generated_per_activation_attempt',
                'replay_safe' => true,
            ],
            'ui' => $this->uiState($canActivate, $reasons),
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
        array &$reasons
    ): void {
        if ($checkoutIntent === null) {
            $this->addReason($reasons, 'checkout_intent_missing');
        } else {
            if ((string)($checkoutIntent['status'] ?? '') !== self::CHECKOUT_STATUS_PENDING_PAYMENT) {
                $this->addReason($reasons, 'checkout_intent_not_pending_payment');
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
            if ((string)($paymentIntent['normalized_status'] ?? '') !== self::PAYMENT_INTENT_STATUS_PAID
                || (string)($paymentIntent['provider_status'] ?? '') !== self::PROVIDER_STATUS_MOCK_PAID
            ) {
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

        if ($activeSubscription !== null) {
            $this->addReason($reasons, 'active_subscription_exists');
        }
    }

    private function checkoutIntentState(?array $checkoutIntent): ?array
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
        ];
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
        ];
    }

    private function uiState(bool $canActivate, array $reasons): array
    {
        if ($canActivate) {
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

        return [
            'recommended_label' => 'Activacion no disponible',
            'recommended_message_code' => 'payment_activation_not_ready',
            'severity' => 'warning',
            'retryable' => false,
        ];
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
