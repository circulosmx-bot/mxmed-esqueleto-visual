<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Subscriptions\Repositories\SubscriptionCheckoutIntentRepository;
use Subscriptions\Repositories\SubscriptionPaymentEventRepository;
use Subscriptions\Repositories\SubscriptionPaymentIntentRepository;
use Throwable;

final class ConfirmSubscriptionPaymentIntentMockException extends RuntimeException
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

final class ConfirmSubscriptionPaymentIntentMockService
{
    private const CHECKOUT_STATUS_PENDING_PAYMENT = 'pending_payment';
    private const PROVIDER_MOCK = 'mxmed_mock';
    private const STATUS_CREATED = 'created';
    private const STATUS_PENDING_PROVIDER = 'pending_provider';
    private const STATUS_PAID = 'paid';
    private const PROVIDER_STATUS_MOCK_PAID = 'mock_paid';
    private const EVENT_TYPE_CONFIRM = 'payment_intent_confirm';
    private const EVENT_PROCESSING_STATUS_PROCESSED = 'processed';
    private const SOURCE_CONFIRM_MOCK = 'payment_intent_confirm_mock';

    private PDO $pdo;
    private SubscriptionCheckoutIntentRepository $checkoutIntentRepository;
    private SubscriptionPaymentIntentRepository $paymentIntentRepository;
    private SubscriptionPaymentEventRepository $paymentEventRepository;
    private SubscriptionWriteIdempotencyService $idempotencyService;
    private SubscriptionEntityWriteLockService $lockService;

    public function __construct(
        PDO $pdo,
        SubscriptionCheckoutIntentRepository $checkoutIntentRepository,
        SubscriptionPaymentIntentRepository $paymentIntentRepository,
        SubscriptionPaymentEventRepository $paymentEventRepository,
        SubscriptionWriteIdempotencyService $idempotencyService,
        SubscriptionEntityWriteLockService $lockService
    ) {
        $this->pdo = $pdo;
        $this->checkoutIntentRepository = $checkoutIntentRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->paymentEventRepository = $paymentEventRepository;
        $this->idempotencyService = $idempotencyService;
        $this->lockService = $lockService;
    }

    public function confirmMock(array $input): array
    {
        $entityType = strtolower($this->requiredText(
            $input['entity_type'] ?? null,
            'invalid_payment_intent_confirm_payload',
            'entity_type is required',
            64
        ));
        $entityId = $this->requiredText(
            $input['entity_id'] ?? null,
            'invalid_payment_intent_confirm_payload',
            'entity_id is required',
            64
        );
        $checkoutIntentUuid = $this->requiredText(
            $input['checkout_intent_uuid'] ?? null,
            'invalid_payment_intent_confirm_payload',
            'checkout_intent_uuid is required',
            36
        );
        $paymentIntentUuid = $this->requiredText(
            $input['payment_intent_uuid'] ?? null,
            'invalid_payment_intent_confirm_payload',
            'payment_intent_uuid is required',
            36
        );
        $idempotencyKey = $this->requiredText(
            $input['idempotency_key'] ?? null,
            'idempotency_key_invalid',
            'Idempotency-Key is required',
            128
        );
        $provider = $this->provider($input['provider'] ?? self::PROVIDER_MOCK);
        $source = $this->optionalText($input['source'] ?? self::SOURCE_CONFIRM_MOCK, 128) ?? self::SOURCE_CONFIRM_MOCK;
        $notes = $this->optionalNotes($input);

        $paymentIntent = $this->paymentIntentRepository->findByUuid($paymentIntentUuid);
        if ($paymentIntent === null) {
            throw new ConfirmSubscriptionPaymentIntentMockException(
                404,
                'payment_intent_not_found',
                'payment intent was not found'
            );
        }

        $checkoutIntent = $this->checkoutIntentRepository->findByUuid($checkoutIntentUuid);
        if ($checkoutIntent === null) {
            throw new ConfirmSubscriptionPaymentIntentMockException(
                404,
                'checkout_intent_not_found',
                'checkout intent was not found'
            );
        }

        $this->assertConfirmable($paymentIntent, $checkoutIntent, $entityType, $entityId, $provider);

        $scope = $this->idempotencyScope($checkoutIntent, $paymentIntent);
        $payload = [
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'payment_intent_uuid' => $paymentIntentUuid,
            'provider' => $provider,
            'source' => $source,
        ];
        $requestHash = $this->idempotencyService->buildPaymentIntentConfirmMockRequestHash($scope, $payload);
        $idempotencyDecision = $this->idempotencyService->beginPaymentIntentConfirmMock(
            $idempotencyKey,
            $scope,
            $payload
        );

        if ($idempotencyDecision->shouldReplay()) {
            return $idempotencyDecision->response();
        }
        if ($idempotencyDecision->shouldReject()) {
            throw new ConfirmSubscriptionPaymentIntentMockException(
                $idempotencyDecision->httpStatus(),
                $idempotencyDecision->errorCode(),
                $idempotencyDecision->message()
            );
        }

        $idempotencyRecord = $idempotencyDecision->record();
        $lockName = null;
        $transactionOpen = false;

        try {
            $lockName = $this->lockService->acquirePaymentIntentConfirm($paymentIntentUuid, 2);
            if ($lockName === null) {
                throw new ConfirmSubscriptionPaymentIntentMockException(
                    409,
                    SubscriptionEntityWriteLockService::ERROR_PAYMENT_INTENT_CONFIRM_LOCK_TIMEOUT,
                    'payment intent confirm lock timeout'
                );
            }

            $paymentIntent = $this->paymentIntentRepository->findByUuid($paymentIntentUuid);
            if ($paymentIntent === null) {
                throw new ConfirmSubscriptionPaymentIntentMockException(
                    404,
                    'payment_intent_not_found',
                    'payment intent was not found'
                );
            }
            $checkoutIntent = $this->checkoutIntentRepository->findByUuid($checkoutIntentUuid);
            if ($checkoutIntent === null) {
                throw new ConfirmSubscriptionPaymentIntentMockException(
                    404,
                    'checkout_intent_not_found',
                    'checkout intent was not found'
                );
            }
            $this->assertConfirmable($paymentIntent, $checkoutIntent, $entityType, $entityId, $provider);

            if ($this->paymentEventRepository->findConfirmMockByPaymentIntentUuid($paymentIntentUuid) !== null) {
                throw new ConfirmSubscriptionPaymentIntentMockException(
                    409,
                    'payment_intent_already_paid',
                    'payment intent was already confirmed'
                );
            }

            $now = $this->now();
            $eventHash = $this->eventHash($paymentIntent, $checkoutIntent, $now);
            $providerEventId = $this->providerEventId($paymentIntentUuid);

            $this->pdo->beginTransaction();
            $transactionOpen = true;

            $paymentEvent = $this->paymentEventRepository->create([
                'uuid' => $this->generateUuidV4(),
                'checkout_intent_uuid' => $checkoutIntentUuid,
                'payment_intent_uuid' => $paymentIntentUuid,
                'provider' => $provider,
                'provider_event_id' => $providerEventId,
                'provider_payment_id' => $this->nullableText($paymentIntent['provider_payment_id'] ?? null),
                'event_type' => self::EVENT_TYPE_CONFIRM,
                'provider_status' => self::PROVIDER_STATUS_MOCK_PAID,
                'normalized_status' => self::STATUS_PAID,
                'amount_cents' => (int)($paymentIntent['amount_cents'] ?? 0),
                'currency' => (string)($paymentIntent['currency'] ?? ''),
                'event_hash' => $eventHash,
                'signature_validated_at' => $now,
                'received_at' => $now,
                'processed_at' => $now,
                'processing_status' => self::EVENT_PROCESSING_STATUS_PROCESSED,
                'error_message' => null,
                'payload_text_sanitized' => null,
                'source' => $source,
                'notes' => $notes,
            ]);

            $paymentIntent = $this->paymentIntentRepository->markMockPaid($paymentIntentUuid, [
                'paid_at' => $now,
                'source' => $source,
                'notes' => $notes,
            ]);
            if ($paymentIntent === null) {
                throw new ConfirmSubscriptionPaymentIntentMockException(
                    404,
                    'payment_intent_not_found',
                    'payment intent was not found'
                );
            }

            $response = $this->response($paymentIntent, $checkoutIntent, $paymentEvent, $requestHash, false);
            $this->pdo->commit();
            $transactionOpen = false;

            if ($idempotencyRecord !== null) {
                $this->idempotencyService->markPaymentIntentConfirmMockCompleted($idempotencyRecord, $response, 200);
            }

            return $response;
        } catch (Throwable $e) {
            if ($transactionOpen && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($idempotencyRecord !== null) {
                $this->markIdempotencyFailed($idempotencyRecord, $this->statusForThrowable($e));
            }

            throw $this->asConfirmMockException($e);
        } finally {
            $this->lockService->release($lockName);
        }
    }

    private function assertConfirmable(
        array $paymentIntent,
        array $checkoutIntent,
        string $entityType,
        string $entityId,
        string $provider
    ): void {
        if ((string)($paymentIntent['checkout_intent_uuid'] ?? '') !== (string)($checkoutIntent['uuid'] ?? '')) {
            throw new ConfirmSubscriptionPaymentIntentMockException(
                409,
                'payment_intent_checkout_mismatch',
                'payment intent does not belong to checkout intent'
            );
        }

        if ((string)($checkoutIntent['entity_type'] ?? '') !== $entityType
            || (string)($checkoutIntent['entity_id'] ?? '') !== $entityId
        ) {
            throw new ConfirmSubscriptionPaymentIntentMockException(
                409,
                'payment_intent_checkout_mismatch',
                'checkout intent does not belong to entity'
            );
        }

        if ((string)($paymentIntent['provider'] ?? '') !== $provider || $provider !== self::PROVIDER_MOCK) {
            throw new ConfirmSubscriptionPaymentIntentMockException(
                422,
                'payment_intent_provider_invalid',
                'provider is invalid'
            );
        }

        if ((string)($checkoutIntent['status'] ?? '') !== self::CHECKOUT_STATUS_PENDING_PAYMENT) {
            throw new ConfirmSubscriptionPaymentIntentMockException(
                409,
                'checkout_intent_not_pending_payment',
                'checkout intent is not pending payment'
            );
        }

        $status = (string)($paymentIntent['normalized_status'] ?? '');
        if ($status === self::STATUS_PAID) {
            throw new ConfirmSubscriptionPaymentIntentMockException(
                409,
                'payment_intent_already_paid',
                'payment intent is already paid'
            );
        }
        if (!in_array($status, [self::STATUS_CREATED, self::STATUS_PENDING_PROVIDER], true)) {
            throw new ConfirmSubscriptionPaymentIntentMockException(
                409,
                'payment_intent_not_confirmable',
                'payment intent is not confirmable'
            );
        }
    }

    private function idempotencyScope(array $checkoutIntent, array $paymentIntent): array
    {
        return [
            'entity_type' => (string)($checkoutIntent['entity_type'] ?? ''),
            'entity_id' => (string)($checkoutIntent['entity_id'] ?? ''),
            'doctor_id' => (string)($checkoutIntent['doctor_id'] ?? ''),
            'profile_id' => $this->nullableText($checkoutIntent['profile_id'] ?? null),
            'user_id' => (string)($checkoutIntent['user_id'] ?? ''),
            'actor_role' => (string)($checkoutIntent['actor_role'] ?? ''),
            'checkout_intent_uuid' => (string)($checkoutIntent['uuid'] ?? ''),
            'payment_intent_uuid' => (string)($paymentIntent['uuid'] ?? ''),
        ];
    }

    private function response(
        array $paymentIntent,
        array $checkoutIntent,
        array $paymentEvent,
        string $requestHash,
        bool $idempotentReplay
    ): array {
        return [
            'ok' => true,
            'data' => [
                'payment_intent' => [
                    'uuid' => (string)($paymentIntent['uuid'] ?? ''),
                    'checkout_intent_uuid' => (string)($paymentIntent['checkout_intent_uuid'] ?? ''),
                    'provider' => (string)($paymentIntent['provider'] ?? ''),
                    'provider_payment_id' => (string)($paymentIntent['provider_payment_id'] ?? ''),
                    'provider_checkout_id' => $paymentIntent['provider_checkout_id'] ?? null,
                    'normalized_status' => (string)($paymentIntent['normalized_status'] ?? ''),
                    'provider_status' => $paymentIntent['provider_status'] ?? null,
                    'amount_cents' => (int)($paymentIntent['amount_cents'] ?? 0),
                    'currency' => (string)($paymentIntent['currency'] ?? ''),
                    'paid_at' => $paymentIntent['paid_at'] ?? null,
                    'updated_at' => (string)($paymentIntent['updated_at'] ?? ''),
                ],
                'payment_event' => [
                    'uuid' => (string)($paymentEvent['uuid'] ?? ''),
                    'payment_intent_uuid' => (string)($paymentEvent['payment_intent_uuid'] ?? ''),
                    'checkout_intent_uuid' => (string)($paymentEvent['checkout_intent_uuid'] ?? ''),
                    'provider' => (string)($paymentEvent['provider'] ?? ''),
                    'provider_event_id' => (string)($paymentEvent['provider_event_id'] ?? ''),
                    'event_type' => (string)($paymentEvent['event_type'] ?? ''),
                    'normalized_status' => $paymentEvent['normalized_status'] ?? null,
                    'provider_status' => $paymentEvent['provider_status'] ?? null,
                    'processing_status' => (string)($paymentEvent['processing_status'] ?? ''),
                    'processed_at' => $paymentEvent['processed_at'] ?? null,
                ],
                'checkout_intent' => [
                    'uuid' => (string)($checkoutIntent['uuid'] ?? ''),
                    'status' => (string)($checkoutIntent['status'] ?? ''),
                    'entity_type' => (string)($checkoutIntent['entity_type'] ?? ''),
                    'entity_id' => (string)($checkoutIntent['entity_id'] ?? ''),
                    'plan_code' => (string)($checkoutIntent['plan_code'] ?? ''),
                    'billing_period' => (string)($checkoutIntent['billing_period'] ?? ''),
                    'amount_cents' => (int)($checkoutIntent['amount_cents'] ?? 0),
                    'currency' => (string)($checkoutIntent['currency'] ?? ''),
                ],
                'idempotency' => [
                    'operation' => SubscriptionWriteIdempotencyService::PAYMENT_INTENT_CONFIRM_MOCK_OPERATION,
                    'request_hash' => $requestHash,
                    'idempotent_replay' => $idempotentReplay,
                ],
            ],
            'meta' => [
                'source' => 'subscriptions_payment_intent_confirm_mock_service_v1',
                'idempotent_replay' => $idempotentReplay,
            ],
        ];
    }

    private function provider($value): string
    {
        $provider = $this->requiredText(
            $value,
            'payment_intent_provider_invalid',
            'provider is invalid',
            64
        );
        if ($provider !== self::PROVIDER_MOCK) {
            throw new ConfirmSubscriptionPaymentIntentMockException(
                422,
                'payment_intent_provider_invalid',
                'provider is invalid'
            );
        }

        return $provider;
    }

    private function eventHash(array $paymentIntent, array $checkoutIntent, string $receivedAt): string
    {
        $payload = [
            'provider' => self::PROVIDER_MOCK,
            'event_type' => self::EVENT_TYPE_CONFIRM,
            'payment_intent_uuid' => (string)($paymentIntent['uuid'] ?? ''),
            'checkout_intent_uuid' => (string)($checkoutIntent['uuid'] ?? ''),
            'normalized_status' => self::STATUS_PAID,
            'provider_status' => self::PROVIDER_STATUS_MOCK_PAID,
            'received_at' => $receivedAt,
        ];

        return hash('sha256', $this->canonicalJson($payload));
    }

    private function providerEventId(string $paymentIntentUuid): string
    {
        return 'mxmed_mock_confirm:' . $paymentIntentUuid;
    }

    private function requiredText($value, string $code, string $message, int $maxLength): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || strlen($text) > $maxLength) {
            throw new ConfirmSubscriptionPaymentIntentMockException(422, $code, $message);
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

    private function optionalNotes(array $input): ?string
    {
        $notes = $this->optionalText($input['notes'] ?? null, 65535);
        if ($notes !== null) {
            return $notes;
        }

        if (!is_array($input['metadata'] ?? null)) {
            return null;
        }

        $encoded = json_encode($input['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded !== false ? $this->optionalText($encoded, 65535) : null;
    }

    private function nullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text !== '' ? $text : null;
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
        if ($e instanceof ConfirmSubscriptionPaymentIntentMockException) {
            return $e->status();
        }
        if ($e instanceof InvalidArgumentException) {
            return 422;
        }

        return 500;
    }

    private function asConfirmMockException(Throwable $e): Throwable
    {
        if ($e instanceof ConfirmSubscriptionPaymentIntentMockException) {
            return $e;
        }
        if ($e instanceof InvalidArgumentException) {
            return new ConfirmSubscriptionPaymentIntentMockException(
                422,
                $this->argumentErrorCode($e),
                $e->getMessage(),
                $e
            );
        }
        if ($e instanceof RuntimeException) {
            return new ConfirmSubscriptionPaymentIntentMockException(
                500,
                $this->runtimeErrorCode($e),
                $e->getMessage() !== '' ? $e->getMessage() : 'payment intent confirm is unavailable',
                $e
            );
        }

        return new ConfirmSubscriptionPaymentIntentMockException(
            500,
            'payment_intent_confirm_unavailable',
            'payment intent confirm is unavailable',
            $e
        );
    }

    private function argumentErrorCode(InvalidArgumentException $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return 'invalid_payment_intent_confirm_payload';
        }
        $parts = explode(':', $message, 2);

        return trim($parts[0]) !== '' ? trim($parts[0]) : 'invalid_payment_intent_confirm_payload';
    }

    private function runtimeErrorCode(RuntimeException $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return 'payment_intent_confirm_unavailable';
        }
        $parts = explode(':', $message, 2);
        $code = trim($parts[0]);

        return $code !== '' ? $code : 'payment_intent_confirm_unavailable';
    }

    private function canonicalJson(array $value): string
    {
        $this->sortRecursive($value);
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json !== false ? $json : '';
    }

    private function sortRecursive(array &$value): void
    {
        ksort($value);
        foreach ($value as &$child) {
            if (is_array($child)) {
                $this->sortRecursive($child);
            }
        }
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
