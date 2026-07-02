<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Subscriptions\Repositories\SubscriptionCheckoutIntentRepository;
use Subscriptions\Repositories\SubscriptionPaymentIntentRepository;
use Throwable;

final class CreateSubscriptionPaymentIntentException extends RuntimeException
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

final class CreateSubscriptionPaymentIntentService
{
    private const CHECKOUT_STATUS_PENDING_PAYMENT = 'pending_payment';
    private const PROVIDER_MOCK = 'mxmed_mock';
    private const PROVIDER_STRIPE = 'stripe';
    private const STATUS_CREATED = 'created';
    private const STATUS_PENDING_PROVIDER = 'pending_provider';
    private const SOURCE_PAYMENT_INTENT = 'payment_intent';

    private SubscriptionCheckoutIntentRepository $checkoutIntentRepository;
    private SubscriptionPaymentIntentRepository $paymentIntentRepository;
    private SubscriptionWriteIdempotencyService $idempotencyService;
    private SubscriptionEntityWriteLockService $lockService;
    private SubscriptionPaymentIntentMockProvider $mockProvider;
    private ?StripePaymentIntentProviderService $stripeProvider;

    public function __construct(
        SubscriptionCheckoutIntentRepository $checkoutIntentRepository,
        SubscriptionPaymentIntentRepository $paymentIntentRepository,
        SubscriptionWriteIdempotencyService $idempotencyService,
        SubscriptionEntityWriteLockService $lockService,
        SubscriptionPaymentIntentMockProvider $mockProvider,
        ?StripePaymentIntentProviderService $stripeProvider = null
    ) {
        $this->checkoutIntentRepository = $checkoutIntentRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->idempotencyService = $idempotencyService;
        $this->lockService = $lockService;
        $this->mockProvider = $mockProvider;
        $this->stripeProvider = $stripeProvider;
    }

    public function createPaymentIntent(array $input): array
    {
        $checkoutIntentUuid = $this->requiredText(
            $input['checkout_intent_uuid'] ?? null,
            'checkout_intent_uuid_required',
            'checkout_intent_uuid is required',
            36
        );
        $idempotencyKey = $this->requiredText(
            $input['idempotency_key'] ?? null,
            'idempotency_key_invalid',
            'Idempotency-Key is required',
            128
        );
        $provider = $this->provider($input['provider'] ?? self::PROVIDER_MOCK);
        $source = $this->optionalText($input['source'] ?? self::SOURCE_PAYMENT_INTENT, 128) ?? self::SOURCE_PAYMENT_INTENT;
        $notes = $this->optionalNotes($input);
        $normalizedStatus = $this->initialStatus($input['normalized_status'] ?? self::STATUS_CREATED);

        $checkoutIntent = $this->checkoutIntentRepository->findByUuid($checkoutIntentUuid);
        if ($checkoutIntent === null) {
            throw new CreateSubscriptionPaymentIntentException(
                404,
                'checkout_intent_not_found',
                'checkout intent was not found'
            );
        }

        $amountCents = (int)($checkoutIntent['amount_cents'] ?? 0);
        $currency = strtoupper(trim((string)($checkoutIntent['currency'] ?? '')));
        $scope = $this->idempotencyScope($checkoutIntent);
        $payload = [
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'provider' => $provider,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'source' => $source,
        ];
        $requestHash = $this->idempotencyService->buildPaymentIntentRequestHash($scope, $payload);
        $completedReplay = $this->idempotencyService->completedPaymentIntentReplay($idempotencyKey, $scope, $payload);
        if ($completedReplay !== null) {
            if ($completedReplay->shouldReplay()) {
                return $completedReplay->response();
            }
            if ($completedReplay->shouldReject()) {
                throw new CreateSubscriptionPaymentIntentException(
                    $completedReplay->httpStatus(),
                    $completedReplay->errorCode(),
                    $completedReplay->message()
                );
            }
        }

        $this->assertCheckoutReadyForPayment($checkoutIntent);

        $idempotencyDecision = $this->idempotencyService->beginPaymentIntent($idempotencyKey, $scope, $payload);

        if ($idempotencyDecision->shouldReplay()) {
            return $idempotencyDecision->response();
        }
        if ($idempotencyDecision->shouldReject()) {
            throw new CreateSubscriptionPaymentIntentException(
                $idempotencyDecision->httpStatus(),
                $idempotencyDecision->errorCode(),
                $idempotencyDecision->message()
            );
        }

        $idempotencyRecord = $idempotencyDecision->record();
        $lockName = null;

        try {
            $lockName = $this->lockService->acquirePaymentIntentCreate($checkoutIntentUuid, 2);
            if ($lockName === null) {
                throw new CreateSubscriptionPaymentIntentException(
                    409,
                    SubscriptionEntityWriteLockService::ERROR_PAYMENT_INTENT_LOCK_TIMEOUT,
                    'payment intent lock timeout'
                );
            }

            if ($this->paymentIntentRepository->findActiveByCheckoutIntentUuid($checkoutIntentUuid) !== null) {
                throw new CreateSubscriptionPaymentIntentException(
                    409,
                    'payment_intent_already_exists',
                    'payment intent already exists for checkout intent'
                );
            }

            $paymentIntentUuid = $this->generateUuidV4();
            $providerResult = $this->createProviderPaymentIntent([
                'checkout_intent_uuid' => $checkoutIntentUuid,
                'payment_intent_uuid' => $paymentIntentUuid,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'provider' => $provider,
                'normalized_status' => $normalizedStatus,
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'provider_idempotency_key' => $this->providerIdempotencyKey($idempotencyKey, $requestHash),
                'entity_type' => (string)($checkoutIntent['entity_type'] ?? ''),
                'entity_id' => (string)($checkoutIntent['entity_id'] ?? ''),
                'plan_code' => (string)($checkoutIntent['plan_code'] ?? ''),
                'billing_period' => (string)($checkoutIntent['billing_period'] ?? ''),
            ]);

            $paymentIntent = $this->paymentIntentRepository->create([
                'uuid' => $paymentIntentUuid,
                'checkout_intent_uuid' => $checkoutIntentUuid,
                'provider' => (string)($providerResult['provider'] ?? $provider),
                'provider_payment_id' => (string)($providerResult['provider_payment_id'] ?? ''),
                'provider_checkout_id' => $providerResult['provider_checkout_id'] ?? null,
                'normalized_status' => (string)($providerResult['normalized_status'] ?? self::STATUS_CREATED),
                'provider_status' => $providerResult['provider_status'] ?? null,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'created_at_provider' => $providerResult['created_at_provider'] ?? $this->now(),
                'expires_at' => $this->nullableText($checkoutIntent['expires_at'] ?? null),
                'paid_at' => null,
                'failed_at' => null,
                'cancelled_at' => null,
                'source' => $source,
                'notes' => $notes,
            ]);

            $response = $this->response($paymentIntent, $checkoutIntent, $requestHash, false);
            if ($idempotencyRecord !== null) {
                $this->idempotencyService->markPaymentIntentCompleted($idempotencyRecord, $response, 201);
            }

            return $response;
        } catch (Throwable $e) {
            if ($idempotencyRecord !== null) {
                $this->markIdempotencyFailed($idempotencyRecord, $this->statusForThrowable($e));
            }

            throw $this->asPaymentIntentException($e);
        } finally {
            $this->lockService->release($lockName);
        }
    }

    private function assertCheckoutReadyForPayment(array $checkoutIntent): void
    {
        if ($this->nullableText($checkoutIntent['deleted_at'] ?? null) !== null) {
            throw new CreateSubscriptionPaymentIntentException(
                404,
                'checkout_intent_not_found',
                'checkout intent was not found'
            );
        }

        if ((string)($checkoutIntent['status'] ?? '') !== self::CHECKOUT_STATUS_PENDING_PAYMENT) {
            throw new CreateSubscriptionPaymentIntentException(
                409,
                'checkout_intent_not_pending_payment',
                'checkout intent is not pending payment'
            );
        }

        $this->assertCheckoutNotExpired($checkoutIntent);

        $amountCents = (int)($checkoutIntent['amount_cents'] ?? 0);
        $currency = trim((string)($checkoutIntent['currency'] ?? ''));
        if ($amountCents <= 0 || $currency === '') {
            throw new CreateSubscriptionPaymentIntentException(
                422,
                'payment_intent_invalid_checkout_snapshot',
                'checkout intent payment snapshot is invalid'
            );
        }
    }

    private function assertCheckoutNotExpired(array $checkoutIntent): void
    {
        $expiresAt = $this->nullableText($checkoutIntent['expires_at'] ?? null);
        if ($expiresAt === null) {
            return;
        }

        try {
            $expiresAtDate = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));
            $nowDate = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            throw new CreateSubscriptionPaymentIntentException(
                422,
                'payment_intent_invalid_checkout_snapshot',
                'checkout intent expiry is invalid',
                $e
            );
        }

        if ($expiresAtDate < $nowDate) {
            throw new CreateSubscriptionPaymentIntentException(
                409,
                'checkout_intent_expired',
                'checkout intent is expired'
            );
        }
    }

    private function idempotencyScope(array $checkoutIntent): array
    {
        return [
            'entity_type' => (string)($checkoutIntent['entity_type'] ?? ''),
            'entity_id' => (string)($checkoutIntent['entity_id'] ?? ''),
            'doctor_id' => (string)($checkoutIntent['doctor_id'] ?? ''),
            'profile_id' => $this->nullableText($checkoutIntent['profile_id'] ?? null),
            'user_id' => (string)($checkoutIntent['user_id'] ?? ''),
            'actor_role' => (string)($checkoutIntent['actor_role'] ?? ''),
            'checkout_intent_uuid' => (string)($checkoutIntent['uuid'] ?? ''),
        ];
    }

    private function response(
        array $paymentIntent,
        array $checkoutIntent,
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
                    'created_at_provider' => $paymentIntent['created_at_provider'] ?? null,
                    'expires_at' => $paymentIntent['expires_at'] ?? null,
                    'created_at' => (string)($paymentIntent['created_at'] ?? ''),
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
                    'operation' => SubscriptionWriteIdempotencyService::PAYMENT_INTENT_OPERATION,
                    'request_hash' => $requestHash,
                    'idempotent_replay' => $idempotentReplay,
                ],
            ],
            'meta' => [
                'source' => 'subscriptions_payment_intent_service_v1',
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
        if (!in_array($provider, [self::PROVIDER_MOCK, self::PROVIDER_STRIPE], true)) {
            throw new CreateSubscriptionPaymentIntentException(
                422,
                'payment_intent_provider_invalid',
                'provider is invalid'
            );
        }

        return $provider;
    }

    private function createProviderPaymentIntent(array $input): array
    {
        $provider = (string)($input['provider'] ?? self::PROVIDER_MOCK);
        if ($provider === self::PROVIDER_MOCK) {
            return $this->mockProvider->create($input);
        }
        if ($provider === self::PROVIDER_STRIPE) {
            if ($this->stripeProvider === null) {
                throw new CreateSubscriptionPaymentIntentException(
                    503,
                    'payment_intent_provider_unavailable',
                    'payment intent provider is unavailable'
                );
            }

            return $this->stripeProvider->create($input);
        }

        throw new CreateSubscriptionPaymentIntentException(
            422,
            'payment_intent_provider_invalid',
            'provider is invalid'
        );
    }

    private function providerIdempotencyKey(string $idempotencyKey, string $requestHash): string
    {
        return 'mxmed-sub-payment-intent-' . substr(hash('sha256', $idempotencyKey . ':' . $requestHash), 0, 48);
    }

    private function initialStatus($value): string
    {
        $status = $this->requiredText(
            $value,
            'invalid_payment_intent_payload',
            'normalized_status is invalid',
            32
        );
        if ($status === 'paid') {
            throw new CreateSubscriptionPaymentIntentException(
                422,
                'invalid_payment_intent_payload',
                'initial payment intent status cannot be paid'
            );
        }
        if (!in_array($status, [self::STATUS_CREATED, self::STATUS_PENDING_PROVIDER], true)) {
            throw new CreateSubscriptionPaymentIntentException(
                422,
                'invalid_payment_intent_payload',
                'normalized_status is invalid'
            );
        }

        return $status;
    }

    private function requiredText($value, string $code, string $message, int $maxLength): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || strlen($text) > $maxLength) {
            throw new CreateSubscriptionPaymentIntentException(422, $code, $message);
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
        if ($e instanceof CreateSubscriptionPaymentIntentException) {
            return $e->status();
        }
        if ($e instanceof InvalidArgumentException) {
            return 422;
        }

        return 500;
    }

    private function asPaymentIntentException(Throwable $e): Throwable
    {
        if ($e instanceof CreateSubscriptionPaymentIntentException) {
            return $e;
        }
        if ($e instanceof InvalidArgumentException) {
            return new CreateSubscriptionPaymentIntentException(
                422,
                $this->argumentErrorCode($e),
                $e->getMessage(),
                $e
            );
        }
        if ($e instanceof RuntimeException) {
            return new CreateSubscriptionPaymentIntentException(
                500,
                $this->runtimeErrorCode($e),
                $e->getMessage() !== '' ? $e->getMessage() : 'payment intent is unavailable',
                $e
            );
        }

        return new CreateSubscriptionPaymentIntentException(
            500,
            'payment_intent_unavailable',
            'payment intent is unavailable',
            $e
        );
    }

    private function argumentErrorCode(InvalidArgumentException $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return 'invalid_payment_intent_payload';
        }
        $parts = explode(':', $message, 2);

        return trim($parts[0]) !== '' ? trim($parts[0]) : 'invalid_payment_intent_payload';
    }

    private function runtimeErrorCode(RuntimeException $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return 'payment_intent_unavailable';
        }
        $parts = explode(':', $message, 2);
        $code = trim($parts[0]);

        return $code !== '' ? $code : 'payment_intent_unavailable';
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
