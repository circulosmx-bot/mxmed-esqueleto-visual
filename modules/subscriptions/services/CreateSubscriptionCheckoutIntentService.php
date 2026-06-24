<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
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
        $planCode = strtolower($this->requiredText($input['plan_code'] ?? null, 'invalid_checkout_intent_payload', 'plan_code is required', 64));
        $billingPeriod = strtolower($this->requiredText($input['billing_period'] ?? null, 'invalid_checkout_intent_payload', 'billing_period is required', 32));
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

            if ($this->currentSubscriptionRepository->activeSubscriptionExists($entityType, $entityId)) {
                throw new CreateSubscriptionCheckoutIntentException(409, 'active_subscription_exists', 'active subscription already exists');
            }

            if ($this->checkoutIntentRepository->findPendingByEntityPlanAndBilling($entityType, $entityId, $planCode, $billingPeriod) !== null) {
                throw new CreateSubscriptionCheckoutIntentException(409, 'checkout_intent_already_pending', 'checkout intent is already pending');
            }
            if ($this->checkoutIntentRepository->findPendingByEntity($entityType, $entityId) !== null) {
                throw new CreateSubscriptionCheckoutIntentException(409, 'checkout_intent_already_pending', 'checkout intent is already pending');
            }

            $price = $this->priceResolver->resolveForCheckout($entityType, $entityId, $planCode, $billingPeriod);
            $now = $this->now();
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
                'notes' => 'Created by checkout intent service; awaiting payment initialization.',
            ]);

            $response = $this->response($checkoutIntent, $entity, $price, $acceptance, false);
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

    private function response(array $checkoutIntent, array $entity, array $price, array $acceptance, bool $idempotentReplay): array
    {
        return [
            'ok' => true,
            'data' => [
                'checkout_intent_uuid' => (string)($checkoutIntent['uuid'] ?? ''),
                'status' => self::STATUS_PENDING_PAYMENT,
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
            ],
            'meta' => [
                'source' => 'subscriptions_checkout_intent_service_v1',
            ],
        ];
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
        foreach (['amount_cents', 'currency', 'price_source', 'price_version'] as $field) {
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
