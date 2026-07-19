<?php
declare(strict_types=1);

namespace Subscriptions\Services;

require_once __DIR__ . '/../policy/MxmedPlanCapabilityPolicy.php';

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Subscriptions\Policy\MxmedPlanCapabilityPolicy;
use Subscriptions\Repositories\CurrentSubscriptionRepository;
use Subscriptions\Repositories\SubscriptionCheckoutIntentRepository;
use Throwable;

final class CreateSubscriptionCheckoutIntentException extends RuntimeException
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

final class CreateSubscriptionCheckoutIntentService
{
    private const STATUS_PENDING_PAYMENT = 'pending_payment';
    private const ACCEPTANCE_STATUS_PENDING_PAYMENT = 'accepted_pending_payment';
    private const SOURCE_CHECKOUT_INTENT = 'checkout_intent';
    private const DEFAULT_EXPIRES_MINUTES = 30;
    private const BILLING_PERIOD_ANNUAL = 'annual';
    private const INTENT_TYPE_NEW_SUBSCRIPTION = 'new_subscription';
    private const INTENT_TYPE_UPGRADE = 'upgrade';
    private const PRICING_STRATEGY_PRORATED_DIFFERENCE = 'prorated_difference';
    private const LOGICAL_UPGRADE_OPERATION = 'subscriptions.checkout_intent.upgrade';
    private PDO $pdo;
    private SubscriptionEntityResolverService $entityResolver;
    private CurrentSubscriptionRepository $currentSubscriptionRepository;
    private SubscriptionWriteIdempotencyService $idempotencyService;
    private SubscriptionEntityWriteLockService $lockService;
    private SubscriptionPlanPriceResolverService $priceResolver;
    private CreateSubscriptionPendingPaymentAcceptanceService $acceptanceService;
    private SubscriptionCheckoutIntentRepository $checkoutIntentRepository;

    public function __construct(
        PDO $pdo,
        SubscriptionEntityResolverService $entityResolver,
        CurrentSubscriptionRepository $currentSubscriptionRepository,
        SubscriptionWriteIdempotencyService $idempotencyService,
        SubscriptionEntityWriteLockService $lockService,
        SubscriptionPlanPriceResolverService $priceResolver,
        CreateSubscriptionPendingPaymentAcceptanceService $acceptanceService,
        SubscriptionCheckoutIntentRepository $checkoutIntentRepository
    ) {
        $this->pdo = $pdo;
        $this->entityResolver = $entityResolver;
        $this->currentSubscriptionRepository = $currentSubscriptionRepository;
        $this->idempotencyService = $idempotencyService;
        $this->lockService = $lockService;
        $this->priceResolver = $priceResolver;
        $this->acceptanceService = $acceptanceService;
        $this->checkoutIntentRepository = $checkoutIntentRepository;
    }

    public function createCheckoutIntent(array $input): array
    {
        $entityType = strtolower($this->requiredText($input['entity_type'] ?? null, 'invalid_checkout_intent_payload', 'entity_type is required', 64));
        $entityId = $this->requiredText($input['entity_id'] ?? null, 'invalid_checkout_intent_payload', 'entity_id is required', 64);
        $intentType = $this->intentType($input['intent_type'] ?? self::INTENT_TYPE_NEW_SUBSCRIPTION);
        $planCode = $this->canonicalPlanCode($this->requiredText($input['plan_code'] ?? ($input['target_plan_code'] ?? null), 'invalid_checkout_intent_payload', 'plan_code is required', 64));
        $billingPeriod = strtolower($this->requiredText($input['billing_period'] ?? null, 'invalid_checkout_intent_payload', 'billing_period is required', 32));
        if ($intentType === self::INTENT_TYPE_NEW_SUBSCRIPTION && $billingPeriod !== self::BILLING_PERIOD_ANNUAL) {
            throw new CreateSubscriptionCheckoutIntentException(422, 'billing_period_invalid', 'billing period is invalid for checkout pricing');
        }
        $contractVersion = $this->requiredText($input['contract_version'] ?? null, 'contract_invalid', 'contract_version is required', 64);
        $contractHash = $this->requiredText($input['contract_hash'] ?? null, 'contract_invalid', 'contract_hash is required', 128);
        $contractSnapshotUrl = $this->requiredText($input['contract_snapshot_url'] ?? null, 'contract_invalid', 'contract_snapshot_url is required', 255);
        $idempotencyKey = $this->requiredText($input['idempotency_key'] ?? null, 'idempotency_key_invalid', 'Idempotency-Key is required', 128);
        $actorUserId = $this->requiredNumericText($input['actor_user_id'] ?? ($input['user_id'] ?? null), 'invalid_checkout_intent_payload', 'actor_user_id is required');
        $doctorId = $this->optionalText($input['doctor_id'] ?? ($entityType === 'doctor' ? $entityId : null), 64);
        $profileId = $this->optionalText($input['profile_id'] ?? null, 64);
        $actorRole = $this->optionalText($input['actor_role'] ?? 'doctor', 32);
        $operatorId = $this->optionalNumericText($input['operator_id'] ?? null);
        $source = $this->forcedSource($input['source'] ?? self::SOURCE_CHECKOUT_INTENT);
        $contractTitle = $this->optionalText($input['contract_title'] ?? null, 255);
        $ipAddress = $this->optionalText($input['ip_address'] ?? null, 45);
        $userAgent = $this->optionalText($input['user_agent'] ?? null, 512);

        $this->assertClientDidNotSendCanonicalPrice($input);

        $scope = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'doctor_id' => $doctorId ?? '',
            'profile_id' => $profileId,
            'user_id' => $actorUserId,
            'actor_role' => $actorRole ?? '',
        ];
        $payload = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'intent_type' => $intentType,
            'plan_code' => $planCode,
            'billing_period' => $billingPeriod,
            'contract_version' => $contractVersion,
            'contract_hash' => $contractHash,
            'contract_snapshot_url' => $contractSnapshotUrl,
            'contract' => [
                'version' => $contractVersion,
                'hash' => $contractHash,
                'snapshot_url' => $contractSnapshotUrl,
            ],
            'acceptance' => [
                'source' => self::SOURCE_CHECKOUT_INTENT,
            ],
            'source' => $source,
        ];
        $requestHash = $this->idempotencyService->buildCheckoutRequestHash($scope, $payload);
        $idempotencyDecision = $this->idempotencyService->beginCheckoutIntent($idempotencyKey, $scope, $payload);

        if ($idempotencyDecision->shouldReplay()) {
            return $idempotencyDecision->response();
        }
        if ($idempotencyDecision->shouldReject()) {
            throw new CreateSubscriptionCheckoutIntentException(
                $idempotencyDecision->httpStatus(),
                $idempotencyDecision->errorCode(),
                $idempotencyDecision->message()
            );
        }

        $idempotencyRecord = $idempotencyDecision->record();
        $lockName = null;
        $transactionOpen = false;

        try {
            $lockName = $this->lockService->acquireCheckoutCreate($entityType, $entityId, 2);
            if ($lockName === null) {
                throw new CreateSubscriptionCheckoutIntentException(
                    409,
                    SubscriptionEntityWriteLockService::ERROR_CHECKOUT_LOCK_TIMEOUT,
                    'subscription checkout lock timeout'
                );
            }

            $entity = $this->resolvedEntity($entityType, $entityId);
            $activeSubscription = $this->currentSubscriptionRepository->findActiveByEntity($entityType, $entityId);

            if ($intentType === self::INTENT_TYPE_NEW_SUBSCRIPTION && $activeSubscription !== null) {
                throw new CreateSubscriptionCheckoutIntentException(409, 'active_subscription_exists', 'active subscription already exists');
            }
            if ($intentType === self::INTENT_TYPE_UPGRADE && $activeSubscription === null) {
                throw new CreateSubscriptionCheckoutIntentException(409, 'active_subscription_required', 'active subscription is required for upgrade checkout');
            }

            if ($this->checkoutIntentRepository->findPendingByEntityPlanAndBilling($entityType, $entityId, $planCode, $billingPeriod) !== null) {
                throw new CreateSubscriptionCheckoutIntentException(409, 'checkout_intent_already_pending', 'checkout intent is already pending');
            }
            if ($this->checkoutIntentRepository->findPendingByEntity($entityType, $entityId) !== null) {
                throw new CreateSubscriptionCheckoutIntentException(409, 'checkout_intent_already_pending', 'checkout intent is already pending');
            }

            $now = $this->now();
            $price = $intentType === self::INTENT_TYPE_UPGRADE
                ? $this->upgradePrice($entityType, $entityId, $activeSubscription ?? [], $planCode, $billingPeriod, $now)
                : $this->priceResolver->resolveForCheckout($entityType, $entityId, $planCode, $billingPeriod);
            $expiresAt = $this->expiresAt($input['expires_at'] ?? null, $now);

            $this->pdo->beginTransaction();
            $transactionOpen = true;

            $acceptance = $this->acceptanceService->createPendingPaymentAcceptance([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'doctor_id' => $doctorId,
                'profile_id' => $profileId,
                'actor_user_id' => $actorUserId,
                'actor_role' => $actorRole,
                'operator_id' => $operatorId,
                'plan_code' => (string)($price['plan_code'] ?? $planCode),
                'billing_period' => (string)($price['billing_period'] ?? $billingPeriod),
                'contract_version' => $contractVersion,
                'contract_hash' => $contractHash,
                'contract_snapshot_url' => $contractSnapshotUrl,
                'contract_title' => $contractTitle,
                'acceptance_source' => self::SOURCE_CHECKOUT_INTENT,
                'source' => self::SOURCE_CHECKOUT_INTENT,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'accepted_at' => $now,
            ]);

            $checkoutUuid = $this->generateUuidV4();
            $checkoutIntent = $this->checkoutIntentRepository->create([
                'uuid' => $checkoutUuid,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'doctor_id' => $doctorId,
                'profile_id' => $profileId,
                'user_id' => $actorUserId,
                'actor_role' => $actorRole,
                'plan_code' => (string)($price['plan_code'] ?? $planCode),
                'billing_period' => (string)($price['billing_period'] ?? $billingPeriod),
                'amount_cents' => (int)($price['amount_cents'] ?? 0),
                'currency' => (string)($price['currency'] ?? ''),
                'price_source' => (string)($price['price_source'] ?? ''),
                'price_version' => (string)($price['price_version'] ?? ''),
                'contract_acceptance_uuid' => (string)($acceptance['contract_acceptance_uuid'] ?? ''),
                'contract_version' => $contractVersion,
                'contract_hash' => $contractHash,
                'contract_snapshot_url' => $contractSnapshotUrl,
                'status' => self::STATUS_PENDING_PAYMENT,
                'source' => self::SOURCE_CHECKOUT_INTENT,
                'idempotency_key_hash' => isset($idempotencyRecord['idempotency_key_hash'])
                    ? (string)$idempotencyRecord['idempotency_key_hash']
                    : hash('sha256', $idempotencyKey),
                'request_hash' => $requestHash,
                'expires_at' => $expiresAt,
                'notes' => $this->checkoutNotes($intentType, $price),
            ]);

            $response = $this->response($checkoutIntent, $entity, $price, $acceptance, $intentType, false);
            $this->pdo->commit();
            $transactionOpen = false;

            if ($idempotencyRecord !== null) {
                $this->idempotencyService->markCheckoutIntentCompleted($idempotencyRecord, $response, 201);
            }

            return $response;
        } catch (Throwable $e) {
            if ($transactionOpen && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($idempotencyRecord !== null) {
                $this->markIdempotencyFailed($idempotencyRecord, $this->statusForThrowable($e));
            }

            throw $this->asCheckoutException($e);
        } finally {
            $this->lockService->release($lockName);
        }
    }

    private function resolvedEntity(string $entityType, string $entityId): array
    {
        $entity = $this->entityResolver->resolveForCheckout($entityType, $entityId);
        if (!($entity['entity_exists'] ?? false)) {
            throw new CreateSubscriptionCheckoutIntentException(
                404,
                (string)($entity['error'] ?? 'entity_not_found'),
                'entity was not found'
            );
        }
        if (!($entity['entity_is_contractable'] ?? false)) {
            throw new CreateSubscriptionCheckoutIntentException(
                422,
                (string)($entity['error'] ?? 'entity_not_contractable'),
                'entity is not contractable'
            );
        }

        return $entity;
    }

    private function upgradePrice(
        string $entityType,
        string $entityId,
        array $activeSubscription,
        string $targetPlanCode,
        string $targetBillingPeriod,
        string $now
    ): array {
        $currentPlanCode = $this->canonicalPlanCode((string)($activeSubscription['plan_code'] ?? ''));
        $currentBillingPeriod = strtolower(trim((string)($activeSubscription['billing_period'] ?? '')));
        $currentRank = $this->planRank($currentPlanCode);
        $targetRank = $this->planRank($targetPlanCode);
        if ($currentRank <= 0) {
            throw new CreateSubscriptionCheckoutIntentException(409, 'upgrade_current_plan_unsupported', 'current subscription plan is not supported for upgrade');
        }
        if ($targetRank <= 0) {
            throw new CreateSubscriptionCheckoutIntentException(422, 'invalid_checkout_intent_payload', 'target plan is not supported for upgrade');
        }
        if ($targetRank <= $currentRank) {
            throw new CreateSubscriptionCheckoutIntentException(409, 'upgrade_target_plan_not_higher', 'target plan must be higher than current plan');
        }
        if ($targetBillingPeriod !== $currentBillingPeriod) {
            throw new CreateSubscriptionCheckoutIntentException(409, 'upgrade_billing_period_change_not_supported', 'billing period change is not supported for upgrade checkout');
        }

        $currentPrice = $this->upgradeResolvedPrice($entityType, $entityId, $currentPlanCode, $currentBillingPeriod);
        $targetPrice = $this->upgradeResolvedPrice($entityType, $entityId, $targetPlanCode, $targetBillingPeriod);
        $currentAmount = (int)($currentPrice['amount_cents'] ?? 0);
        $targetAmount = (int)($targetPrice['amount_cents'] ?? 0);
        $priceDifference = $targetAmount - $currentAmount;
        if ($currentAmount <= 0 || $targetAmount <= 0 || $priceDifference <= 0) {
            throw new CreateSubscriptionCheckoutIntentException(409, 'upgrade_adjustment_not_positive', 'upgrade adjustment is not positive');
        }

        $period = $this->activeSubscriptionPeriod($activeSubscription, $now);
        $adjustmentAmount = (int)round($priceDifference * ((int)$period['remaining_days'] / (int)$period['period_days']));
        if ($adjustmentAmount <= 0) {
            throw new CreateSubscriptionCheckoutIntentException(409, 'upgrade_adjustment_not_positive', 'upgrade adjustment is not positive');
        }

        $price = $targetPrice;
        $price['amount_cents'] = $adjustmentAmount;
        $price['price_source'] = 'upgrade_prorated_difference';
        $price['price_version'] = substr((string)($targetPrice['price_version'] ?? 'target') . ':upgrade-prorated', 0, 64);
        $price['upgrade_context'] = [
            'intent_type' => self::INTENT_TYPE_UPGRADE,
            'source_subscription_id' => (string)($activeSubscription['subscription_id'] ?? ''),
            'current_plan_code' => $currentPlanCode,
            'target_plan_code' => $targetPlanCode,
            'current_billing_period' => $currentBillingPeriod,
            'target_billing_period' => $targetBillingPeriod,
            'current_price_period_cents' => $currentAmount,
            'target_price_period_cents' => $targetAmount,
            'price_difference_cents' => $priceDifference,
            'remaining_days' => (int)$period['remaining_days'],
            'period_days' => (int)$period['period_days'],
            'adjustment_amount_cents' => $adjustmentAmount,
            'currency' => (string)($targetPrice['currency'] ?? ''),
            'pricing_strategy' => self::PRICING_STRATEGY_PRORATED_DIFFERENCE,
            'current_price_snapshot' => $this->compactPriceSnapshot($currentPrice),
            'target_price_snapshot' => $this->compactPriceSnapshot($targetPrice),
        ];
        if (isset($currentPrice['monthly_markup_percent']) || isset($targetPrice['monthly_markup_percent'])) {
            $price['upgrade_context']['monthly_markup_percent'] = 25;
        }

        return $price;
    }

    private function upgradeResolvedPrice(string $entityType, string $entityId, string $planCode, string $billingPeriod): array
    {
        try {
            return $this->priceResolver->resolveForCheckout($entityType, $entityId, $planCode, $billingPeriod);
        } catch (SubscriptionPlanPriceResolverException $e) {
            $status = $e->status() === 503 ? 503 : 422;
            throw new CreateSubscriptionCheckoutIntentException($status, 'upgrade_price_unavailable', 'upgrade price is unavailable', $e);
        }
    }

    private function activeSubscriptionPeriod(array $activeSubscription, string $now): array
    {
        $startsAt = trim((string)($activeSubscription['starts_at'] ?? ''));
        $expiresAt = trim((string)($activeSubscription['expires_at'] ?? ''));
        if ($startsAt === '' || $expiresAt === '') {
            throw new CreateSubscriptionCheckoutIntentException(409, 'upgrade_period_invalid', 'active subscription period is invalid for upgrade checkout');
        }

        try {
            $nowDate = new DateTimeImmutable($now, new DateTimeZone('UTC'));
            $startsAtDate = new DateTimeImmutable($startsAt, new DateTimeZone('UTC'));
            $expiresAtDate = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            throw new CreateSubscriptionCheckoutIntentException(409, 'upgrade_period_invalid', 'active subscription period is invalid for upgrade checkout', $e);
        }

        $periodSeconds = $expiresAtDate->getTimestamp() - $startsAtDate->getTimestamp();
        $remainingSeconds = $expiresAtDate->getTimestamp() - $nowDate->getTimestamp();
        if ($periodSeconds <= 0 || $remainingSeconds <= 0) {
            throw new CreateSubscriptionCheckoutIntentException(409, 'upgrade_period_invalid', 'active subscription period is invalid for upgrade checkout');
        }

        $periodDays = max(1, (int)ceil($periodSeconds / 86400));
        $remainingDays = min($periodDays, max(1, (int)ceil($remainingSeconds / 86400)));

        return [
            'period_days' => $periodDays,
            'remaining_days' => $remainingDays,
        ];
    }

    private function compactPriceSnapshot(array $price): array
    {
        return [
            'plan_code' => (string)($price['plan_code'] ?? ''),
            'billing_period' => (string)($price['billing_period'] ?? ''),
            'amount_cents' => (int)($price['amount_cents'] ?? 0),
            'currency' => (string)($price['currency'] ?? ''),
            'price_source' => (string)($price['price_source'] ?? ''),
            'price_version' => (string)($price['price_version'] ?? ''),
            'price_uuid' => (string)($price['price_uuid'] ?? ''),
            'derived_from_billing_period' => $price['derived_from_billing_period'] ?? null,
            'monthly_markup_percent' => $price['monthly_markup_percent'] ?? null,
        ];
    }

    private function checkoutNotes(string $intentType, array $price): string
    {
        if ($intentType !== self::INTENT_TYPE_UPGRADE) {
            return 'Created by checkout intent service; awaiting payment initialization.';
        }

        $notes = [
            'intent_type' => self::INTENT_TYPE_UPGRADE,
            'pricing_strategy' => self::PRICING_STRATEGY_PRORATED_DIFFERENCE,
            'upgrade_context' => $price['upgrade_context'] ?? [],
        ];
        $json = json_encode($notes, JSON_UNESCAPED_SLASHES);

        return is_string($json) && $json !== ''
            ? $json
            : 'Created by checkout intent service for upgrade; awaiting payment initialization.';
    }

    private function response(array $checkoutIntent, array $entity, array $price, array $acceptance, string $intentType, bool $idempotentReplay): array
    {
        $data = [
            'checkout_intent_uuid' => (string)($checkoutIntent['uuid'] ?? ''),
            'status' => self::STATUS_PENDING_PAYMENT,
            'intent_type' => $intentType,
            'entity' => $entity,
            'plan_code' => (string)($checkoutIntent['plan_code'] ?? $price['plan_code'] ?? ''),
            'billing_period' => (string)($checkoutIntent['billing_period'] ?? $price['billing_period'] ?? ''),
            'price' => [
                'amount_cents' => (int)($checkoutIntent['amount_cents'] ?? $price['amount_cents'] ?? 0),
                'currency' => (string)($checkoutIntent['currency'] ?? $price['currency'] ?? ''),
                'price_source' => (string)($checkoutIntent['price_source'] ?? $price['price_source'] ?? ''),
                'price_version' => (string)($checkoutIntent['price_version'] ?? $price['price_version'] ?? ''),
                'price_uuid' => (string)($price['price_uuid'] ?? ''),
                'valid_from' => (string)($price['valid_from'] ?? ''),
                'valid_until' => $price['valid_until'] ?? null,
                'source' => (string)($price['source'] ?? ''),
            ],
            'contract_acceptance_uuid' => (string)($acceptance['contract_acceptance_uuid'] ?? ''),
            'contract' => [
                'version' => (string)($acceptance['contract_version'] ?? $checkoutIntent['contract_version'] ?? ''),
                'hash' => (string)($acceptance['contract_hash'] ?? $checkoutIntent['contract_hash'] ?? ''),
                'snapshot_url' => (string)($acceptance['contract_snapshot_url'] ?? $checkoutIntent['contract_snapshot_url'] ?? ''),
                'acceptance_status' => self::ACCEPTANCE_STATUS_PENDING_PAYMENT,
            ],
            'expires_at' => (string)($checkoutIntent['expires_at'] ?? ''),
            'created_at' => (string)($checkoutIntent['created_at'] ?? ''),
            'source' => self::SOURCE_CHECKOUT_INTENT,
            'idempotency' => [
                'operation' => SubscriptionWriteIdempotencyService::CHECKOUT_OPERATION,
                'idempotent_replay' => $idempotentReplay,
            ],
        ];

        if ($intentType === self::INTENT_TYPE_UPGRADE) {
            $context = is_array($price['upgrade_context'] ?? null) ? $price['upgrade_context'] : [];
            $data['current_plan_code'] = (string)($context['current_plan_code'] ?? '');
            $data['target_plan_code'] = (string)($context['target_plan_code'] ?? $data['plan_code']);
            $data['current_billing_period'] = (string)($context['current_billing_period'] ?? '');
            $data['target_billing_period'] = (string)($context['target_billing_period'] ?? $data['billing_period']);
            $data['adjustment_amount_cents'] = (int)($context['adjustment_amount_cents'] ?? $data['price']['amount_cents']);
            $data['currency'] = (string)($context['currency'] ?? $data['price']['currency']);
            $data['pricing_strategy'] = self::PRICING_STRATEGY_PRORATED_DIFFERENCE;
            $data['remaining_days'] = (int)($context['remaining_days'] ?? 0);
            $data['period_days'] = (int)($context['period_days'] ?? 0);
            $data['next_step'] = 'create_payment_intent';
            $data['idempotency']['logical_operation'] = self::LOGICAL_UPGRADE_OPERATION;
        }

        return [
            'ok' => true,
            'data' => $data,
            'meta' => [
                'source' => 'subscriptions_checkout_intent_service_v1',
            ],
        ];
    }

    private function intentType($value): string
    {
        $intentType = strtolower(trim((string)($value ?? self::INTENT_TYPE_NEW_SUBSCRIPTION)));
        if ($intentType === '') {
            $intentType = self::INTENT_TYPE_NEW_SUBSCRIPTION;
        }
        if (!in_array($intentType, [self::INTENT_TYPE_NEW_SUBSCRIPTION, self::INTENT_TYPE_UPGRADE], true)) {
            throw new CreateSubscriptionCheckoutIntentException(422, 'checkout_intent_type_invalid', 'checkout intent type is invalid');
        }

        return $intentType;
    }

    private function canonicalPlanCode(string $planCode): string
    {
        return MxmedPlanCapabilityPolicy::normalizePlanCode($planCode)
            ?? strtolower(trim($planCode));
    }

    private function planRank(string $planCode): int
    {
        return MxmedPlanCapabilityPolicy::planRank($this->canonicalPlanCode($planCode)) ?? 0;
    }

    private function requiredText($value, string $code, string $message, int $maxLength): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || strlen($text) > $maxLength) {
            throw new CreateSubscriptionCheckoutIntentException(422, $code, $message);
        }

        return $text;
    }

    private function requiredNumericText($value, string $code, string $message): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || !ctype_digit($text)) {
            throw new CreateSubscriptionCheckoutIntentException(422, $code, $message);
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

    private function optionalNumericText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (!ctype_digit($text)) {
            throw new CreateSubscriptionCheckoutIntentException(422, 'invalid_checkout_intent_payload', 'operator_id is invalid');
        }

        return $text;
    }

    private function forcedSource($value): string
    {
        $source = strtolower(trim((string)$value));
        if ($source !== self::SOURCE_CHECKOUT_INTENT) {
            throw new CreateSubscriptionCheckoutIntentException(422, 'acceptance_source_invalid', 'source must be checkout_intent');
        }

        return self::SOURCE_CHECKOUT_INTENT;
    }

    private function assertClientDidNotSendCanonicalPrice(array $input): void
    {
        foreach ([
            'amount_cents',
            'currency',
            'price_source',
            'price_version',
            'adjustment_amount_cents',
            'current_price_period_cents',
            'target_price_period_cents',
            'price_difference_cents',
            'remaining_days',
            'period_days',
            'pricing_strategy',
            'next_step',
            'current_subscription_id',
        ] as $field) {
            if (array_key_exists($field, $input)) {
                throw new CreateSubscriptionCheckoutIntentException(
                    422,
                    'invalid_checkout_intent_payload',
                    'price snapshot must be resolved server-side'
                );
            }
        }
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    private function expiresAt($value, string $now): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return (new DateTimeImmutable($now, new DateTimeZone('UTC')))
                ->modify('+' . self::DEFAULT_EXPIRES_MINUTES . ' minutes')
                ->format('Y-m-d H:i:s');
        }

        try {
            return (new DateTimeImmutable($text, new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            throw new CreateSubscriptionCheckoutIntentException(
                422,
                'invalid_checkout_intent_payload',
                'expires_at is invalid',
                $e
            );
        }
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
        if ($e instanceof CreateSubscriptionCheckoutIntentException) {
            return $e->status();
        }
        if ($e instanceof SubscriptionPlanPriceResolverException) {
            return $e->status();
        }

        return 500;
    }

    private function asCheckoutException(Throwable $e): Throwable
    {
        if ($e instanceof CreateSubscriptionCheckoutIntentException) {
            return $e;
        }
        if ($e instanceof SubscriptionPlanPriceResolverException) {
            return new CreateSubscriptionCheckoutIntentException($e->status(), $e->errorCode(), $e->getMessage(), $e);
        }
        if ($e instanceof SubscriptionPendingPaymentAcceptanceException) {
            return new CreateSubscriptionCheckoutIntentException(500, $e->errorCode(), $e->getMessage(), $e);
        }
        if ($e instanceof InvalidArgumentException) {
            return new CreateSubscriptionCheckoutIntentException(422, $this->argumentErrorCode($e), $e->getMessage(), $e);
        }

        return new CreateSubscriptionCheckoutIntentException(500, 'checkout_intent_unavailable', 'checkout intent is unavailable', $e);
    }

    private function argumentErrorCode(InvalidArgumentException $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return 'invalid_checkout_intent_payload';
        }
        $parts = explode(':', $message, 2);

        return trim($parts[0]) !== '' ? trim($parts[0]) : 'invalid_checkout_intent_payload';
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
