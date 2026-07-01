<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Subscriptions\Repositories\CurrentSubscriptionRepository;
use Subscriptions\Repositories\ProfileSubscriptionRepository;
use Subscriptions\Repositories\SubscriptionCheckoutIntentRepository;
use Subscriptions\Repositories\SubscriptionContractAcceptanceRepository;
use Subscriptions\Repositories\SubscriptionPaymentEventRepository;
use Subscriptions\Repositories\SubscriptionPaymentIntentRepository;
use Throwable;

final class ActivateSubscriptionAfterPaymentException extends RuntimeException
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

final class ActivateSubscriptionAfterPaymentService
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
    private const SOURCE_ACTIVATION = 'mxmed_payment_intent_activation_v1';
    private const SOURCE_UPGRADE_ACTIVATION = 'mxmed_payment_intent_upgrade_activation_v1';
    private const INTENT_TYPE_UPGRADE = 'upgrade';
    private const PRICING_STRATEGY_PRORATED_DIFFERENCE = 'prorated_difference';
    private const PLAN_RANKS = [
        'basic' => 1,
        'standard' => 2,
        'optimum' => 3,
        'professional' => 4,
    ];

    private PDO $pdo;
    private SubscriptionWriteIdempotencyService $idempotencyService;
    private SubscriptionEntityWriteLockService $lockService;
    private SubscriptionCheckoutIntentRepository $checkoutIntentRepository;
    private SubscriptionPaymentIntentRepository $paymentIntentRepository;
    private SubscriptionPaymentEventRepository $paymentEventRepository;
    private SubscriptionContractAcceptanceRepository $contractAcceptanceRepository;
    private ProfileSubscriptionRepository $profileSubscriptionRepository;
    private CurrentSubscriptionRepository $currentSubscriptionRepository;

    public function __construct(
        PDO $pdo,
        SubscriptionWriteIdempotencyService $idempotencyService,
        SubscriptionEntityWriteLockService $lockService,
        SubscriptionCheckoutIntentRepository $checkoutIntentRepository,
        SubscriptionPaymentIntentRepository $paymentIntentRepository,
        SubscriptionPaymentEventRepository $paymentEventRepository,
        SubscriptionContractAcceptanceRepository $contractAcceptanceRepository,
        ProfileSubscriptionRepository $profileSubscriptionRepository,
        CurrentSubscriptionRepository $currentSubscriptionRepository
    ) {
        $this->pdo = $pdo;
        $this->idempotencyService = $idempotencyService;
        $this->lockService = $lockService;
        $this->checkoutIntentRepository = $checkoutIntentRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->paymentEventRepository = $paymentEventRepository;
        $this->contractAcceptanceRepository = $contractAcceptanceRepository;
        $this->profileSubscriptionRepository = $profileSubscriptionRepository;
        $this->currentSubscriptionRepository = $currentSubscriptionRepository;
    }

    public function activateAfterPayment(array $input): array
    {
        $entityType = strtolower($this->requiredText(
            $input['entity_type'] ?? null,
            'invalid_payment_intent_activation_payload',
            'entity_type is required',
            64
        ));
        $entityId = $this->requiredText(
            $input['entity_id'] ?? null,
            'invalid_payment_intent_activation_payload',
            'entity_id is required',
            64
        );
        $checkoutIntentUuid = $this->requiredText(
            $input['checkout_intent_uuid'] ?? null,
            'invalid_payment_intent_activation_payload',
            'checkout_intent_uuid is required',
            36
        );
        $paymentIntentUuid = $this->requiredText(
            $input['payment_intent_uuid'] ?? null,
            'invalid_payment_intent_activation_payload',
            'payment_intent_uuid is required',
            36
        );
        $paymentEventUuid = $this->requiredText(
            $input['payment_event_uuid'] ?? null,
            'invalid_payment_intent_activation_payload',
            'payment_event_uuid is required',
            36
        );
        $idempotencyKey = $this->requiredText(
            $input['idempotency_key'] ?? null,
            'idempotency_key_invalid',
            'Idempotency-Key is required',
            128
        );
        $userId = $this->optionalText($input['user_id'] ?? null, 64);
        $actorRole = $this->optionalText($input['actor_role'] ?? null, 32);

        $scope = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'doctor_id' => $entityType === 'doctor' ? $entityId : '',
            'profile_id' => null,
            'user_id' => $userId ?? '',
            'actor_role' => $actorRole ?? '',
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'payment_intent_uuid' => $paymentIntentUuid,
            'payment_event_uuid' => $paymentEventUuid,
        ];
        $payload = [
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'payment_intent_uuid' => $paymentIntentUuid,
            'payment_event_uuid' => $paymentEventUuid,
            'provider' => 'mxmed_mock',
            'normalized_status' => self::PAYMENT_INTENT_STATUS_PAID,
            'provider_status' => self::PROVIDER_STATUS_MOCK_PAID,
            'event_type' => self::EVENT_TYPE_CONFIRM,
            'processing_status' => self::EVENT_PROCESSING_STATUS_PROCESSED,
            'source' => self::SOURCE_ACTIVATION,
        ];
        $requestHash = $this->idempotencyService->buildPaymentIntentActivateAfterPaymentRequestHash($scope, $payload);
        $idempotencyDecision = $this->idempotencyService->beginPaymentIntentActivateAfterPayment(
            $idempotencyKey,
            $scope,
            $payload
        );

        if ($idempotencyDecision->shouldReplay()) {
            return $this->normalizeActivationReplayResponse($idempotencyDecision->response());
        }
        if ($idempotencyDecision->shouldReject()) {
            throw new ActivateSubscriptionAfterPaymentException(
                $idempotencyDecision->httpStatus(),
                $idempotencyDecision->errorCode(),
                $idempotencyDecision->message()
            );
        }

        $idempotencyRecord = $idempotencyDecision->record();
        $lockName = null;
        $transactionOpen = false;

        try {
            $lockName = $this->lockService->acquirePaymentIntentActivateSubscription($paymentIntentUuid, 2);
            if ($lockName === null) {
                throw new ActivateSubscriptionAfterPaymentException(
                    409,
                    SubscriptionEntityWriteLockService::ERROR_PAYMENT_INTENT_ACTIVATE_SUBSCRIPTION_LOCK_TIMEOUT,
                    'payment intent activate subscription lock timeout'
                );
            }

            $this->pdo->beginTransaction();
            $transactionOpen = true;

            $paymentIntent = $this->paymentIntentRepository->findByUuid($paymentIntentUuid);
            if ($paymentIntent === null) {
                throw new ActivateSubscriptionAfterPaymentException(
                    404,
                    'payment_intent_not_found',
                    'payment intent was not found'
                );
            }

            $checkoutIntent = $this->checkoutIntentRepository->findByUuid($checkoutIntentUuid);
            if ($checkoutIntent === null) {
                throw new ActivateSubscriptionAfterPaymentException(
                    404,
                    'checkout_intent_not_found',
                    'checkout intent was not found'
                );
            }

            $paymentEvent = $this->paymentEventRepository->findByUuid($paymentEventUuid);
            if ($paymentEvent === null) {
                throw new ActivateSubscriptionAfterPaymentException(
                    404,
                    'payment_event_not_found',
                    'payment event was not found'
                );
            }

            $this->assertActivationGuards($paymentIntent, $checkoutIntent, $paymentEvent, $entityType, $entityId);

            $contractAcceptanceUuid = $this->nullableText($checkoutIntent['contract_acceptance_uuid'] ?? null);
            if ($contractAcceptanceUuid === null || strlen($contractAcceptanceUuid) > 36) {
                throw new ActivateSubscriptionAfterPaymentException(
                    404,
                    'contract_acceptance_not_found',
                    'contract acceptance was not found'
                );
            }
            $acceptance = $this->contractAcceptanceRepository->findByUuid($contractAcceptanceUuid);
            if ($acceptance === null) {
                throw new ActivateSubscriptionAfterPaymentException(
                    404,
                    'contract_acceptance_not_found',
                    'contract acceptance was not found'
                );
            }
            $this->assertAcceptanceReady($acceptance);
            $this->assertAcceptanceMatchesCheckout($acceptance, $checkoutIntent);

            $isUpgradeCheckout = $this->checkoutIntentDeclaresUpgrade($checkoutIntent);
            $upgradeContext = $this->upgradeContext($checkoutIntent, $isUpgradeCheckout);
            $previousSubscription = null;

            if ($isUpgradeCheckout) {
                $previousSubscription = $this->currentSubscriptionRepository->findActiveByEntity($entityType, $entityId);
                if ($previousSubscription === null) {
                    throw new ActivateSubscriptionAfterPaymentException(
                        409,
                        'active_subscription_required_for_upgrade',
                        'active subscription is required for upgrade activation'
                    );
                }

                $this->assertUpgradeReady($checkoutIntent, $paymentIntent, $previousSubscription, $upgradeContext);
                $subscription = $this->profileSubscriptionRepository->createActiveFromPaidCheckout(
                    $this->upgradeSubscriptionSnapshot(
                        $checkoutIntent,
                        $paymentIntent,
                        $paymentEvent,
                        $acceptance,
                        $previousSubscription,
                        $upgradeContext
                    )
                );
            } else {
                if ($this->currentSubscriptionRepository->activeSubscriptionExists($entityType, $entityId)) {
                    throw new ActivateSubscriptionAfterPaymentException(
                        409,
                        'active_subscription_exists',
                        'active subscription already exists for entity'
                    );
                }

                $subscription = $this->profileSubscriptionRepository->createActiveFromPaidCheckout(
                    $this->subscriptionSnapshot($checkoutIntent, $paymentIntent, $paymentEvent, $acceptance)
                );
            }
            $subscriptionId = (string)($subscription['subscription_id'] ?? '');
            if ($subscriptionId === '') {
                throw new ActivateSubscriptionAfterPaymentException(
                    500,
                    'profile_subscription_create_failed',
                    'profile subscription was not created'
                );
            }

            if ($isUpgradeCheckout && $previousSubscription !== null) {
                $previousSubscription = $this->profileSubscriptionRepository->markRenewedTo(
                    (string)($previousSubscription['subscription_id'] ?? ''),
                    $subscriptionId,
                    [
                        'notes' => 'replaced by upgrade checkout '
                            . $checkoutIntentUuid
                            . ', payment intent '
                            . $paymentIntentUuid,
                    ]
                );
                if ($previousSubscription === null) {
                    throw new ActivateSubscriptionAfterPaymentException(
                        409,
                        'profile_subscription_upgrade_link_failed',
                        'previous subscription could not be linked to upgrade'
                    );
                }
            }

            $acceptance = $this->contractAcceptanceRepository->linkSubscriptionId($contractAcceptanceUuid, $subscriptionId);
            if ($acceptance === null) {
                throw new ActivateSubscriptionAfterPaymentException(
                    409,
                    'contract_acceptance_subscription_link_failed',
                    'contract acceptance could not be linked to subscription'
                );
            }

            $checkoutIntent = $this->checkoutIntentRepository->markActivatedAfterPayment($checkoutIntentUuid, $subscriptionId, [
                'source' => $isUpgradeCheckout ? self::SOURCE_UPGRADE_ACTIVATION : self::SOURCE_ACTIVATION,
                'notes' => $this->checkoutActivationNotes($isUpgradeCheckout, $paymentIntentUuid, $upgradeContext),
            ]);
            if ($checkoutIntent === null) {
                throw new ActivateSubscriptionAfterPaymentException(
                    409,
                    'checkout_activation_transition_failed',
                    'checkout intent could not be activated'
                );
            }

            $response = $this->response(
                $subscription,
                $checkoutIntent,
                $paymentIntent,
                $paymentEvent,
                $acceptance,
                $requestHash,
                false,
                $upgradeContext,
                $previousSubscription
            );

            if ($idempotencyRecord !== null) {
                $this->idempotencyService->markPaymentIntentActivateAfterPaymentCompleted($idempotencyRecord, $response, 200);
            }

            $this->pdo->commit();
            $transactionOpen = false;

            return $response;
        } catch (Throwable $e) {
            if ($transactionOpen && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($idempotencyRecord !== null) {
                $this->markIdempotencyFailed($idempotencyRecord, $this->statusForThrowable($e));
            }

            throw $this->asActivationException($e);
        } finally {
            $this->lockService->release($lockName);
        }
    }

    private function assertActivationGuards(
        array $paymentIntent,
        array $checkoutIntent,
        array $paymentEvent,
        string $entityType,
        string $entityId
    ): void {
        if ((string)($paymentIntent['checkout_intent_uuid'] ?? '') !== (string)($checkoutIntent['uuid'] ?? '')) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'payment_intent_checkout_mismatch',
                'payment intent does not belong to checkout intent'
            );
        }

        if ((string)($checkoutIntent['entity_type'] ?? '') !== $entityType
            || (string)($checkoutIntent['entity_id'] ?? '') !== $entityId
        ) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'checkout_intent_entity_mismatch',
                'checkout intent does not belong to entity'
            );
        }

        if (!$this->paymentIntentIsConfirmed($paymentIntent)) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'payment_intent_not_paid',
                'payment intent is not paid'
            );
        }

        if ((string)($paymentEvent['payment_intent_uuid'] ?? '') !== (string)($paymentIntent['uuid'] ?? '')) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'payment_event_payment_intent_mismatch',
                'payment event does not belong to payment intent'
            );
        }

        if ((string)($paymentEvent['event_type'] ?? '') !== self::EVENT_TYPE_CONFIRM) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'payment_event_not_processed',
                'payment event is not a payment intent confirmation'
            );
        }

        if ((string)($paymentEvent['processing_status'] ?? '') !== self::EVENT_PROCESSING_STATUS_PROCESSED) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'payment_event_not_processed',
                'payment event is not processed'
            );
        }

        if ((string)($checkoutIntent['status'] ?? '') !== self::CHECKOUT_STATUS_PENDING_PAYMENT) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'checkout_intent_not_pending_payment',
                'checkout intent is not pending payment'
            );
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

    private function assertAcceptanceReady(array $acceptance): void
    {
        if ((string)($acceptance['status'] ?? '') !== self::ACCEPTANCE_STATUS_PENDING_PAYMENT
            || $this->nullableText($acceptance['subscription_id'] ?? null) !== null
        ) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'contract_acceptance_not_pending_payment',
                'contract acceptance is not pending payment'
            );
        }
    }

    private function assertAcceptanceMatchesCheckout(array $acceptance, array $checkoutIntent): void
    {
        if ((string)($acceptance['entity_type'] ?? '') !== (string)($checkoutIntent['entity_type'] ?? '')
            || (string)($acceptance['entity_id'] ?? '') !== (string)($checkoutIntent['entity_id'] ?? '')
            || (string)($acceptance['plan_code'] ?? '') !== (string)($checkoutIntent['plan_code'] ?? '')
            || (string)($acceptance['billing_period'] ?? '') !== (string)($checkoutIntent['billing_period'] ?? '')
        ) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'contract_acceptance_not_pending_payment',
                'contract acceptance does not match checkout intent'
            );
        }
    }

    private function assertUpgradeReady(array $checkoutIntent, array $paymentIntent, array $activeSubscription, array $upgradeContext): void
    {
        $activeSubscriptionId = (string)($activeSubscription['subscription_id'] ?? '');
        $sourceSubscriptionId = (string)($upgradeContext['source_subscription_id'] ?? '');
        if ($activeSubscriptionId === '' || ($sourceSubscriptionId !== '' && $sourceSubscriptionId !== $activeSubscriptionId)) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'upgrade_active_subscription_mismatch',
                'active subscription does not match upgrade checkout'
            );
        }

        if ((string)($activeSubscription['plan_code'] ?? '') !== (string)$upgradeContext['current_plan_code']
            || (string)($checkoutIntent['plan_code'] ?? '') !== (string)$upgradeContext['target_plan_code']
        ) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'upgrade_plan_mismatch',
                'upgrade checkout plan does not match active subscription'
            );
        }

        if ((string)($activeSubscription['billing_period'] ?? '') !== (string)$upgradeContext['current_billing_period']
            || (string)($checkoutIntent['billing_period'] ?? '') !== (string)$upgradeContext['target_billing_period']
            || (string)$upgradeContext['current_billing_period'] !== (string)$upgradeContext['target_billing_period']
        ) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'upgrade_billing_period_mismatch',
                'upgrade billing period does not match active subscription'
            );
        }

        if ($this->planRank((string)$upgradeContext['target_plan_code']) <= $this->planRank((string)$upgradeContext['current_plan_code'])) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'upgrade_target_plan_not_higher',
                'target plan must be higher than current plan'
            );
        }

        $adjustmentAmount = (int)($upgradeContext['adjustment_amount_cents'] ?? 0);
        $currency = (string)($upgradeContext['currency'] ?? '');
        if ((int)($checkoutIntent['amount_cents'] ?? 0) !== $adjustmentAmount
            || (int)($paymentIntent['amount_cents'] ?? 0) !== $adjustmentAmount
            || (string)($checkoutIntent['currency'] ?? '') !== $currency
            || (string)($paymentIntent['currency'] ?? '') !== $currency
        ) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'upgrade_adjustment_mismatch',
                'upgrade adjustment does not match paid payment intent'
            );
        }

        $expiresAt = $this->nullableText($activeSubscription['expires_at'] ?? null);
        if ($expiresAt === null) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'upgrade_period_invalid',
                'active subscription period is invalid for upgrade activation'
            );
        }
    }

    private function subscriptionSnapshot(
        array $checkoutIntent,
        array $paymentIntent,
        array $paymentEvent,
        array $acceptance
    ): array {
        $now = $this->now();
        $durationDays = $this->durationDays($acceptance);
        $plan = $this->currentSubscriptionRepository->findPlanByCodeAndPeriod(
            (string)($checkoutIntent['plan_code'] ?? ''),
            (string)($checkoutIntent['billing_period'] ?? '')
        );
        if ($durationDays <= 0 && is_array($plan)) {
            $durationDays = (int)($plan['duration_days'] ?? 0);
        }
        if ($durationDays <= 0) {
            $durationDays = 365;
        }

        return [
            'entity_type' => (string)($checkoutIntent['entity_type'] ?? ''),
            'entity_id' => (string)($checkoutIntent['entity_id'] ?? ''),
            'doctor_id' => $this->nullableText($checkoutIntent['doctor_id'] ?? null),
            'profile_id' => $this->nullableText($checkoutIntent['profile_id'] ?? null),
            'plan_code' => (string)($checkoutIntent['plan_code'] ?? ''),
            'plan_label' => is_array($plan) ? $this->nullableText($plan['plan_label'] ?? null) : null,
            'billing_period' => (string)($checkoutIntent['billing_period'] ?? ''),
            'duration_days' => $durationDays,
            'contracted_plan_code' => (string)($checkoutIntent['plan_code'] ?? ''),
            'effective_plan_code' => (string)($checkoutIntent['plan_code'] ?? ''),
            'contract_version' => $this->nullableText($acceptance['contract_version'] ?? $checkoutIntent['contract_version'] ?? null),
            'contract_accepted_at' => $this->nullableText($acceptance['accepted_at'] ?? null),
            'contract_accepted_by_user_id' => $this->nullableText($acceptance['accepted_by_user_id'] ?? null),
            'contract_acceptance_source' => $this->nullableText($acceptance['acceptance_source'] ?? null),
            'contract_acceptance_ip' => $this->nullableText($acceptance['ip_address'] ?? null),
            'contract_acceptance_user_agent' => $this->nullableText($acceptance['user_agent'] ?? null),
            'starts_at' => $now,
            'expires_at' => $this->expiresAt($now, $durationDays),
            'status' => 'active',
            'auto_renew' => 0,
            'source' => self::SOURCE_ACTIVATION,
            'notes' => 'activated from checkout '
                . (string)($checkoutIntent['uuid'] ?? '')
                . ', payment intent '
                . (string)($paymentIntent['uuid'] ?? '')
                . ', payment event '
                . (string)($paymentEvent['uuid'] ?? ''),
            ];
    }

    private function upgradeSubscriptionSnapshot(
        array $checkoutIntent,
        array $paymentIntent,
        array $paymentEvent,
        array $acceptance,
        array $activeSubscription,
        array $upgradeContext
    ): array {
        $snapshot = $this->subscriptionSnapshot($checkoutIntent, $paymentIntent, $paymentEvent, $acceptance);
        $now = $this->now();
        $expiresAt = $this->nullableText($activeSubscription['expires_at'] ?? null);
        if ($expiresAt === null) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'upgrade_period_invalid',
                'active subscription period is invalid for upgrade activation'
            );
        }

        $snapshot['starts_at'] = $now;
        $snapshot['expires_at'] = $expiresAt;
        $snapshot['duration_days'] = $this->remainingDurationDays($now, $expiresAt);
        $snapshot['contracted_plan_code'] = (string)$upgradeContext['target_plan_code'];
        $snapshot['effective_plan_code'] = (string)$upgradeContext['target_plan_code'];
        $snapshot['renewed_from_subscription_id'] = (string)($activeSubscription['subscription_id'] ?? '');
        $snapshot['source'] = self::SOURCE_UPGRADE_ACTIVATION;
        $snapshot['notes'] = $this->upgradeNotes($checkoutIntent, $paymentIntent, $paymentEvent, $activeSubscription, $upgradeContext);

        return $snapshot;
    }

    private function upgradeNotes(
        array $checkoutIntent,
        array $paymentIntent,
        array $paymentEvent,
        array $activeSubscription,
        array $upgradeContext
    ): string {
        $notes = [
            'intent_type' => self::INTENT_TYPE_UPGRADE,
            'source_subscription_id' => (string)($activeSubscription['subscription_id'] ?? ''),
            'current_plan_code' => (string)$upgradeContext['current_plan_code'],
            'target_plan_code' => (string)$upgradeContext['target_plan_code'],
            'current_billing_period' => (string)$upgradeContext['current_billing_period'],
            'target_billing_period' => (string)$upgradeContext['target_billing_period'],
            'adjustment_amount_cents' => (int)$upgradeContext['adjustment_amount_cents'],
            'currency' => (string)$upgradeContext['currency'],
            'pricing_strategy' => (string)$upgradeContext['pricing_strategy'],
            'checkout_intent_uuid' => (string)($checkoutIntent['uuid'] ?? ''),
            'payment_intent_uuid' => (string)($paymentIntent['uuid'] ?? ''),
            'payment_event_uuid' => (string)($paymentEvent['uuid'] ?? ''),
        ];
        $encoded = json_encode($notes, JSON_UNESCAPED_SLASHES);

        return is_string($encoded) && $encoded !== ''
            ? $encoded
            : 'upgrade activated from checkout ' . (string)($checkoutIntent['uuid'] ?? '');
    }

    private function remainingDurationDays(string $startsAt, string $expiresAt): int
    {
        try {
            $startsAtDate = new DateTimeImmutable($startsAt, new DateTimeZone('UTC'));
            $expiresAtDate = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'upgrade_period_invalid',
                'active subscription period is invalid for upgrade activation',
                $e
            );
        }

        $seconds = $expiresAtDate->getTimestamp() - $startsAtDate->getTimestamp();
        if ($seconds <= 0) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'upgrade_period_invalid',
                'active subscription period is invalid for upgrade activation'
            );
        }

        return max(1, (int)ceil($seconds / 86400));
    }

    private function durationDays(array $acceptance): int
    {
        return (int)($acceptance['duration_days'] ?? 0);
    }

    private function expiresAt(string $startsAt, int $durationDays): string
    {
        return (new DateTimeImmutable($startsAt, new DateTimeZone('UTC')))
            ->add(new DateInterval('P' . $durationDays . 'D'))
            ->format('Y-m-d H:i:s');
    }

    private function response(
        array $subscription,
        array $checkoutIntent,
        array $paymentIntent,
        array $paymentEvent,
        array $acceptance,
        string $requestHash,
        bool $idempotentReplay,
        ?array $upgradeContext = null,
        ?array $previousSubscription = null
    ): array {
        $data = [
            'subscription_id' => (string)($subscription['subscription_id'] ?? ''),
            'contract_acceptance_uuid' => (string)($acceptance['uuid'] ?? ''),
            'profile_subscription' => [
                'subscription_id' => (string)($subscription['subscription_id'] ?? ''),
                'entity_type' => (string)($subscription['entity_type'] ?? ''),
                'entity_id' => (string)($subscription['entity_id'] ?? ''),
                'plan_code' => (string)($subscription['plan_code'] ?? ''),
                'billing_period' => (string)($subscription['billing_period'] ?? ''),
                'status' => (string)($subscription['status'] ?? ''),
                'starts_at' => $subscription['starts_at'] ?? null,
                'expires_at' => $subscription['expires_at'] ?? null,
                'renewed_from_subscription_id' => $subscription['renewed_from_subscription_id'] ?? null,
                'renewed_to_subscription_id' => $subscription['renewed_to_subscription_id'] ?? null,
            ],
            'checkout_intent' => [
                'uuid' => (string)($checkoutIntent['uuid'] ?? ''),
                'status' => (string)($checkoutIntent['status'] ?? ''),
                'subscription_id' => $checkoutIntent['subscription_id'] ?? null,
                'activated_at' => $checkoutIntent['activated_at'] ?? null,
            ],
            'payment_intent' => [
                'uuid' => (string)($paymentIntent['uuid'] ?? ''),
                'checkout_intent_uuid' => (string)($paymentIntent['checkout_intent_uuid'] ?? ''),
                'normalized_status' => (string)($paymentIntent['normalized_status'] ?? ''),
                'provider_status' => $paymentIntent['provider_status'] ?? null,
                'paid_at' => $paymentIntent['paid_at'] ?? null,
            ],
            'payment_event' => [
                'uuid' => (string)($paymentEvent['uuid'] ?? ''),
                'payment_intent_uuid' => (string)($paymentEvent['payment_intent_uuid'] ?? ''),
                'event_type' => (string)($paymentEvent['event_type'] ?? ''),
                'processing_status' => (string)($paymentEvent['processing_status'] ?? ''),
                'processed_at' => $paymentEvent['processed_at'] ?? null,
            ],
            'idempotency' => [
                'operation' => SubscriptionWriteIdempotencyService::PAYMENT_INTENT_ACTIVATE_AFTER_PAYMENT_OPERATION,
                'request_hash' => $requestHash,
                'idempotent_replay' => $idempotentReplay,
            ],
        ];

        if ($upgradeContext !== null) {
            $data['upgrade'] = [
                'intent_type' => self::INTENT_TYPE_UPGRADE,
                'previous_subscription_id' => is_array($previousSubscription)
                    ? ($previousSubscription['subscription_id'] ?? null)
                    : ($subscription['renewed_from_subscription_id'] ?? null),
                'new_subscription_id' => (string)($subscription['subscription_id'] ?? ''),
                'current_plan_code' => (string)$upgradeContext['current_plan_code'],
                'target_plan_code' => (string)$upgradeContext['target_plan_code'],
                'current_billing_period' => (string)$upgradeContext['current_billing_period'],
                'target_billing_period' => (string)$upgradeContext['target_billing_period'],
                'adjustment_amount_cents' => (int)$upgradeContext['adjustment_amount_cents'],
                'currency' => (string)$upgradeContext['currency'],
                'pricing_strategy' => (string)$upgradeContext['pricing_strategy'],
            ];
        }

        return [
            'ok' => true,
            'data' => $data,
            'meta' => [
                'source' => 'subscriptions_payment_intent_activate_after_payment_service_v1',
                'idempotent_replay' => $idempotentReplay,
            ],
        ];
    }

    private function normalizeActivationReplayResponse(array $response): array
    {
        $response['meta'] = is_array($response['meta'] ?? null) ? $response['meta'] : [];
        $response['meta']['idempotent_replay'] = true;

        if (is_array($response['data'] ?? null)) {
            $response['data']['idempotency'] = is_array($response['data']['idempotency'] ?? null)
                ? $response['data']['idempotency']
                : [];
            $response['data']['idempotency']['idempotent_replay'] = true;
        }

        return $response;
    }

    private function checkoutIntentDeclaresUpgrade(array $checkoutIntent): bool
    {
        $notes = $this->nullableText($checkoutIntent['notes'] ?? null);
        if ($notes === null) {
            return false;
        }

        $decoded = json_decode($notes, true);
        if (!is_array($decoded)) {
            return false;
        }

        return strtolower(trim((string)($decoded['intent_type'] ?? ''))) === self::INTENT_TYPE_UPGRADE;
    }

    private function checkoutActivationNotes(bool $isUpgradeCheckout, string $paymentIntentUuid, ?array $upgradeContext): string
    {
        if (!$isUpgradeCheckout || $upgradeContext === null) {
            return 'activated after paid payment intent ' . $paymentIntentUuid;
        }

        $notes = [
            'intent_type' => self::INTENT_TYPE_UPGRADE,
            'pricing_strategy' => self::PRICING_STRATEGY_PRORATED_DIFFERENCE,
            'upgrade_context' => [
                'current_plan_code' => (string)$upgradeContext['current_plan_code'],
                'target_plan_code' => (string)$upgradeContext['target_plan_code'],
                'current_billing_period' => (string)$upgradeContext['current_billing_period'],
                'target_billing_period' => (string)$upgradeContext['target_billing_period'],
                'adjustment_amount_cents' => (int)$upgradeContext['adjustment_amount_cents'],
                'currency' => (string)$upgradeContext['currency'],
            ],
            'activation' => [
                'source' => self::SOURCE_UPGRADE_ACTIVATION,
                'payment_intent_uuid' => $paymentIntentUuid,
            ],
        ];
        $json = json_encode($notes, JSON_UNESCAPED_SLASHES);

        return is_string($json) && $json !== ''
            ? $json
            : 'upgrade activated after paid payment intent ' . $paymentIntentUuid;
    }

    private function upgradeContext(array $checkoutIntent, bool $isUpgradeCheckout): ?array
    {
        if (!$isUpgradeCheckout) {
            return null;
        }

        $notes = $this->nullableText($checkoutIntent['notes'] ?? null);
        $decoded = is_string($notes) ? json_decode($notes, true) : null;
        if (!is_array($decoded) || !is_array($decoded['upgrade_context'] ?? null)) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'upgrade_context_invalid',
                'upgrade context is invalid'
            );
        }

        $context = $decoded['upgrade_context'];
        $currentPlanCode = $this->canonicalPlanCode($context['current_plan_code'] ?? null);
        $targetPlanCode = $this->canonicalPlanCode($context['target_plan_code'] ?? null);
        $currentBillingPeriod = strtolower($this->requiredUpgradeText($context['current_billing_period'] ?? null, 'current_billing_period'));
        $targetBillingPeriod = strtolower($this->requiredUpgradeText($context['target_billing_period'] ?? null, 'target_billing_period'));
        $sourceSubscriptionId = $this->nullableText($context['source_subscription_id'] ?? null);
        $adjustmentAmountCents = $this->positiveAmount($context['adjustment_amount_cents'] ?? null);
        $currency = strtoupper($this->requiredUpgradeText($context['currency'] ?? null, 'currency'));
        $pricingStrategy = strtolower($this->requiredUpgradeText($decoded['pricing_strategy'] ?? ($context['pricing_strategy'] ?? null), 'pricing_strategy'));

        if ($currentPlanCode === '' || $targetPlanCode === ''
            || $adjustmentAmountCents <= 0
            || $pricingStrategy !== self::PRICING_STRATEGY_PRORATED_DIFFERENCE
            || $this->planRank($targetPlanCode) <= $this->planRank($currentPlanCode)
            || $currentBillingPeriod !== $targetBillingPeriod
        ) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'upgrade_context_invalid',
                'upgrade context is invalid'
            );
        }

        return [
            'source_subscription_id' => $sourceSubscriptionId,
            'current_plan_code' => $currentPlanCode,
            'target_plan_code' => $targetPlanCode,
            'current_billing_period' => $currentBillingPeriod,
            'target_billing_period' => $targetBillingPeriod,
            'adjustment_amount_cents' => $adjustmentAmountCents,
            'currency' => $currency,
            'pricing_strategy' => $pricingStrategy,
        ];
    }

    private function requiredText($value, string $code, string $message, int $maxLength): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || strlen($text) > $maxLength) {
            throw new ActivateSubscriptionAfterPaymentException(422, $code, $message);
        }

        return $text;
    }

    private function optionalText($value, int $maxLength): ?string
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

    private function nullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text !== '' ? $text : null;
    }

    private function requiredUpgradeText($value, string $field): string
    {
        $text = $this->nullableText($value);
        if ($text === null) {
            throw new ActivateSubscriptionAfterPaymentException(
                409,
                'upgrade_context_invalid',
                'upgrade context field is required: ' . $field
            );
        }

        return $text;
    }

    private function canonicalPlanCode($value): string
    {
        $planCode = strtolower($this->nullableText($value) ?? '');
        $map = [
            'basico' => 'basic',
            'básico' => 'basic',
            'basic' => 'basic',
            'estandar' => 'standard',
            'estándar' => 'standard',
            'standard' => 'standard',
            'optimo' => 'optimum',
            'óptimo' => 'optimum',
            'optimum' => 'optimum',
            'profesional' => 'professional',
            'professional' => 'professional',
        ];

        return $map[$planCode] ?? $planCode;
    }

    private function planRank(string $planCode): int
    {
        return self::PLAN_RANKS[$planCode] ?? 0;
    }

    private function positiveAmount($value): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : 0;
        }

        $text = trim((string)($value ?? ''));
        return ctype_digit($text) ? max(0, (int)$text) : 0;
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    private function markIdempotencyFailed(array $record, int $status): void
    {
        try {
            $this->idempotencyService->markOperationFailed($record, $status);
        } catch (Throwable $ignored) {
        }
    }

    private function statusForThrowable(Throwable $e): int
    {
        if ($e instanceof ActivateSubscriptionAfterPaymentException) {
            return $e->status();
        }
        if ($e instanceof InvalidArgumentException) {
            return 422;
        }

        return 500;
    }

    private function asActivationException(Throwable $e): Throwable
    {
        if ($e instanceof ActivateSubscriptionAfterPaymentException) {
            return $e;
        }
        if ($e instanceof InvalidArgumentException) {
            return new ActivateSubscriptionAfterPaymentException(
                422,
                $this->argumentErrorCode($e),
                $e->getMessage(),
                $e
            );
        }
        if ($e instanceof RuntimeException) {
            return new ActivateSubscriptionAfterPaymentException(
                500,
                $this->runtimeErrorCode($e),
                $e->getMessage() !== '' ? $e->getMessage() : 'payment intent activation is unavailable',
                $e
            );
        }

        return new ActivateSubscriptionAfterPaymentException(
            500,
            'payment_intent_activation_unavailable',
            'payment intent activation is unavailable',
            $e
        );
    }

    private function argumentErrorCode(InvalidArgumentException $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return 'invalid_payment_intent_activation_payload';
        }
        $parts = explode(':', $message, 2);

        return trim($parts[0]) !== '' ? trim($parts[0]) : 'invalid_payment_intent_activation_payload';
    }

    private function runtimeErrorCode(RuntimeException $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return 'payment_intent_activation_unavailable';
        }
        $parts = explode(':', $message, 2);
        $code = trim($parts[0]);

        return $code !== '' ? $code : 'payment_intent_activation_unavailable';
    }
}
