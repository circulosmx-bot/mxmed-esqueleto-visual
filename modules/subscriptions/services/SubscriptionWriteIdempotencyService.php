<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use RuntimeException;
use Subscriptions\Repositories\SubscriptionWriteIdempotencyRepository;

final class SubscriptionWriteIdempotencyDecision
{
    private bool $enabled;
    private ?string $status;
    private ?int $httpStatus;
    private ?string $errorCode;
    private ?string $message;
    private ?array $response;
    private ?array $record;

    private function __construct(
        bool $enabled,
        ?string $status = null,
        ?int $httpStatus = null,
        ?string $errorCode = null,
        ?string $message = null,
        ?array $response = null,
        ?array $record = null
    ) {
        $this->enabled = $enabled;
        $this->status = $status;
        $this->httpStatus = $httpStatus;
        $this->errorCode = $errorCode;
        $this->message = $message;
        $this->response = $response;
        $this->record = $record;
    }

    public static function disabled(): self
    {
        return new self(false);
    }

    public static function proceed(array $record): self
    {
        return new self(true, 'proceed', null, null, null, null, $record);
    }

    public static function reject(int $httpStatus, string $errorCode, string $message): self
    {
        return new self(true, 'reject', $httpStatus, $errorCode, $message);
    }

    public static function replay(int $httpStatus, array $response): self
    {
        return new self(true, 'replay', $httpStatus, null, null, $response);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function shouldProceed(): bool
    {
        return $this->status === 'proceed';
    }

    public function shouldReject(): bool
    {
        return $this->status === 'reject';
    }

    public function shouldReplay(): bool
    {
        return $this->status === 'replay';
    }

    public function httpStatus(): int
    {
        return $this->httpStatus ?? 500;
    }

    public function errorCode(): string
    {
        return $this->errorCode ?? 'idempotency_error';
    }

    public function message(): string
    {
        return $this->message ?? 'idempotency error';
    }

    public function response(): array
    {
        return $this->response ?? [];
    }

    public function record(): ?array
    {
        return $this->record;
    }
}

final class SubscriptionWriteIdempotencyService
{
    public const OPERATION = 'subscriptions.create_with_contract_acceptance';
    public const CHECKOUT_OPERATION = 'subscriptions.checkout_intent.create';
    public const PAYMENT_ROUTE_OPERATION = 'subscriptions.payment_route.create';
    public const PAYMENT_ROUTE_CHECKOUT_OPERATION = 'subscriptions.payment_route.checkout.create';
    public const PAYMENT_INTENT_OPERATION = 'subscriptions.payment_intent.create';
    public const PAYMENT_INTENT_CONFIRM_MOCK_OPERATION = 'subscriptions.payment_intent.confirm_mock';
    public const PAYMENT_INTENT_ACTIVATE_AFTER_PAYMENT_OPERATION = 'subscriptions.payment_intent.activate_after_payment';

    private SubscriptionWriteIdempotencyRepository $repository;

    public function __construct(SubscriptionWriteIdempotencyRepository $repository)
    {
        $this->repository = $repository;
    }

    public function begin(?string $headerValue, array $scope, array $payload): SubscriptionWriteIdempotencyDecision
    {
        return $this->beginOperation($headerValue, self::OPERATION, $scope, $payload);
    }

    public function beginCheckoutIntent(
        ?string $headerValue,
        array $scope,
        array $payload
    ): SubscriptionWriteIdempotencyDecision {
        return $this->beginOperation($headerValue, self::CHECKOUT_OPERATION, $scope, $payload);
    }

    public function beginPaymentRoute(
        ?string $headerValue,
        array $scope,
        array $payload
    ): SubscriptionWriteIdempotencyDecision {
        return $this->beginOperation($headerValue, self::PAYMENT_ROUTE_OPERATION, $scope, $payload);
    }

    public function beginPaymentRouteCheckout(
        ?string $headerValue,
        array $scope,
        array $payload
    ): SubscriptionWriteIdempotencyDecision {
        return $this->beginOperation($headerValue, self::PAYMENT_ROUTE_CHECKOUT_OPERATION, $scope, $payload);
    }

    public function beginPaymentIntent(
        ?string $headerValue,
        array $scope,
        array $payload
    ): SubscriptionWriteIdempotencyDecision {
        return $this->beginOperation($headerValue, self::PAYMENT_INTENT_OPERATION, $scope, $payload);
    }

    public function completedPaymentIntentReplay(
        ?string $headerValue,
        array $scope,
        array $payload
    ): ?SubscriptionWriteIdempotencyDecision {
        return $this->completedOperationReplay(
            $headerValue,
            self::PAYMENT_INTENT_OPERATION,
            $scope,
            $payload
        );
    }

    public function beginPaymentIntentConfirmMock(
        ?string $headerValue,
        array $scope,
        array $payload
    ): SubscriptionWriteIdempotencyDecision {
        return $this->beginOperation($headerValue, self::PAYMENT_INTENT_CONFIRM_MOCK_OPERATION, $scope, $payload);
    }

    public function beginPaymentIntentActivateAfterPayment(
        ?string $headerValue,
        array $scope,
        array $payload
    ): SubscriptionWriteIdempotencyDecision {
        return $this->beginOperation(
            $headerValue,
            self::PAYMENT_INTENT_ACTIVATE_AFTER_PAYMENT_OPERATION,
            $scope,
            $payload
        );
    }

    public function beginOperation(
        ?string $headerValue,
        string $operation,
        array $scope,
        array $payload
    ): SubscriptionWriteIdempotencyDecision
    {
        $key = $this->normalizeKey($headerValue);
        if ($key === null) {
            return SubscriptionWriteIdempotencyDecision::disabled();
        }

        $operation = trim($operation);
        if (!$this->isAllowedOperation($operation)) {
            return SubscriptionWriteIdempotencyDecision::reject(
                422,
                'idempotency_operation_invalid',
                'idempotency operation is invalid'
            );
        }

        if (!$this->isValidKey($key)) {
            return SubscriptionWriteIdempotencyDecision::reject(
                422,
                'idempotency_key_invalid',
                'Idempotency-Key is invalid'
            );
        }

        $keyHash = hash('sha256', $key);
        $requestHash = $this->requestHashForOperation($operation, $scope, $payload);
        $record = [
            'uuid' => $this->uuidV4(),
            'idempotency_key_hash' => $keyHash,
            'request_hash' => $requestHash,
            'entity_type' => (string)($scope['entity_type'] ?? ''),
            'entity_id' => (string)($scope['entity_id'] ?? ''),
            'doctor_id' => (string)($scope['doctor_id'] ?? ''),
            'profile_id' => $this->nullableText($scope['profile_id'] ?? null),
            'user_id' => (string)($scope['user_id'] ?? ''),
            'actor_role' => (string)($scope['actor_role'] ?? ''),
            'operation' => $operation,
        ];

        if ($this->repository->insertProcessing($record)) {
            return SubscriptionWriteIdempotencyDecision::proceed($record);
        }

        $existing = $this->repository->findByScope(
            $keyHash,
            $record['user_id'],
            $record['entity_type'],
            $record['entity_id'],
            $operation
        );

        if ($existing === null) {
            return SubscriptionWriteIdempotencyDecision::reject(
                409,
                'request_already_processing',
                'idempotent request is already processing'
            );
        }

        if ((string)($existing['request_hash'] ?? '') !== $requestHash) {
            return SubscriptionWriteIdempotencyDecision::reject(
                409,
                'idempotency_key_reused_with_different_payload',
                'Idempotency-Key was reused with a different payload'
            );
        }

        $status = strtolower(trim((string)($existing['status'] ?? '')));
        if ($status === 'processing') {
            return SubscriptionWriteIdempotencyDecision::reject(
                409,
                'request_already_processing',
                'idempotent request is already processing'
            );
        }

        if ($status === 'completed') {
            $response = $this->completedReplayResponse($existing, $this->requiresStoredReplayResponse($operation));
            if ($response === null) {
                return SubscriptionWriteIdempotencyDecision::reject(
                    409,
                    'idempotency_result_unavailable',
                    'idempotency result is unavailable'
                );
            }

            return SubscriptionWriteIdempotencyDecision::replay(
                $this->completedReplayStatus($existing),
                $response
            );
        }

        return SubscriptionWriteIdempotencyDecision::reject(
            409,
            'idempotency_key_not_reusable',
            'Idempotency-Key is not reusable'
        );
    }

    private function completedOperationReplay(
        ?string $headerValue,
        string $operation,
        array $scope,
        array $payload
    ): ?SubscriptionWriteIdempotencyDecision {
        $key = $this->normalizeKey($headerValue);
        if ($key === null) {
            return null;
        }

        $operation = trim($operation);
        if (!$this->isAllowedOperation($operation)) {
            return SubscriptionWriteIdempotencyDecision::reject(
                422,
                'idempotency_operation_invalid',
                'idempotency operation is invalid'
            );
        }

        if (!$this->isValidKey($key)) {
            return SubscriptionWriteIdempotencyDecision::reject(
                422,
                'idempotency_key_invalid',
                'Idempotency-Key is invalid'
            );
        }

        $keyHash = hash('sha256', $key);
        $requestHash = $this->requestHashForOperation($operation, $scope, $payload);
        $existing = $this->repository->findByScope(
            $keyHash,
            (string)($scope['user_id'] ?? ''),
            (string)($scope['entity_type'] ?? ''),
            (string)($scope['entity_id'] ?? ''),
            $operation
        );
        if ($existing === null) {
            return null;
        }

        if ((string)($existing['request_hash'] ?? '') !== $requestHash) {
            return SubscriptionWriteIdempotencyDecision::reject(
                409,
                'idempotency_key_reused_with_different_payload',
                'Idempotency-Key was reused with a different payload'
            );
        }

        $status = strtolower(trim((string)($existing['status'] ?? '')));
        if ($status === 'processing') {
            return SubscriptionWriteIdempotencyDecision::reject(
                409,
                'request_already_processing',
                'idempotent request is already processing'
            );
        }

        if ($status === 'completed') {
            $response = $this->completedReplayResponse($existing, $this->requiresStoredReplayResponse($operation));
            if ($response === null) {
                return SubscriptionWriteIdempotencyDecision::reject(
                    409,
                    'idempotency_result_unavailable',
                    'idempotency result is unavailable'
                );
            }

            return SubscriptionWriteIdempotencyDecision::replay(
                $this->completedReplayStatus($existing),
                $response
            );
        }

        return SubscriptionWriteIdempotencyDecision::reject(
            409,
            'idempotency_key_not_reusable',
            'Idempotency-Key is not reusable'
        );
    }

    public function markCompleted(array $record, array $response, int $httpStatus): void
    {
        $subscriptionId = (string)($response['data']['subscription_id'] ?? '');
        $acceptanceUuid = (string)($response['data']['contract_acceptance_uuid'] ?? '');
        if ($subscriptionId === '' || $acceptanceUuid === '') {
            return;
        }

        $body = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->repository->markCompleted(
            (string)$record['uuid'],
            $subscriptionId,
            $acceptanceUuid,
            $httpStatus,
            $body !== false ? $body : null
        );
    }

    public function markCheckoutIntentCompleted(array $record, array $response, int $httpStatus): void
    {
        $data = is_array($response['data'] ?? null) ? $response['data'] : $response;
        $checkoutIntentUuid = (string)($data['checkout_intent_uuid'] ?? '');
        if ($checkoutIntentUuid === '') {
            throw new RuntimeException('checkout_idempotency_complete_failed: checkout_intent_uuid is required');
        }

        $body = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('checkout_idempotency_complete_failed: response payload is not serializable');
        }

        $acceptanceUuid = $this->nullableText($data['contract_acceptance_uuid'] ?? null);
        $this->repository->markCompletedWithResponse(
            (string)$record['uuid'],
            null,
            $acceptanceUuid,
            $httpStatus,
            $body
        );
    }

    public function markPaymentIntentCompleted(array $record, array $response, int $httpStatus): void
    {
        $body = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('payment_intent_idempotency_complete_failed: response payload is not serializable');
        }

        $this->repository->markCompletedWithResponse(
            (string)$record['uuid'],
            null,
            null,
            $httpStatus,
            $body
        );
    }

    public function markPaymentRouteCompleted(array $record, array $response, int $httpStatus): void
    {
        $body = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('payment_route_idempotency_complete_failed: response payload is not serializable');
        }

        $this->repository->markCompletedWithResponse(
            (string)$record['uuid'],
            null,
            null,
            $httpStatus,
            $body
        );
    }

    public function markPaymentIntentConfirmMockCompleted(array $record, array $response, int $httpStatus): void
    {
        $body = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('payment_intent_confirm_mock_idempotency_complete_failed: response payload is not serializable');
        }

        $this->repository->markCompletedWithResponse(
            (string)$record['uuid'],
            null,
            null,
            $httpStatus,
            $body
        );
    }

    public function markPaymentIntentActivateAfterPaymentCompleted(array $record, array $response, int $httpStatus): void
    {
        $body = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('payment_intent_activate_after_payment_idempotency_complete_failed: response payload is not serializable');
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : $response;
        $subscriptionId = $this->nullableText($data['subscription_id'] ?? null);
        $acceptanceUuid = $this->nullableText($data['contract_acceptance_uuid'] ?? null);

        $this->repository->markCompletedWithResponse(
            (string)$record['uuid'],
            $subscriptionId,
            $acceptanceUuid,
            $httpStatus,
            $body
        );
    }

    public function markOperationCompleted(array $record, array $response, int $httpStatus): void
    {
        $operation = (string)($record['operation'] ?? '');
        if ($operation === self::CHECKOUT_OPERATION) {
            $this->markCheckoutIntentCompleted($record, $response, $httpStatus);
            return;
        }

        if ($operation === self::PAYMENT_INTENT_OPERATION) {
            $this->markPaymentIntentCompleted($record, $response, $httpStatus);
            return;
        }

        if ($operation === self::PAYMENT_ROUTE_OPERATION) {
            $this->markPaymentRouteCompleted($record, $response, $httpStatus);
            return;
        }

        if ($operation === self::PAYMENT_ROUTE_CHECKOUT_OPERATION) {
            $this->markPaymentRouteCompleted($record, $response, $httpStatus);
            return;
        }

        if ($operation === self::PAYMENT_INTENT_CONFIRM_MOCK_OPERATION) {
            $this->markPaymentIntentConfirmMockCompleted($record, $response, $httpStatus);
            return;
        }

        if ($operation === self::PAYMENT_INTENT_ACTIVATE_AFTER_PAYMENT_OPERATION) {
            $this->markPaymentIntentActivateAfterPaymentCompleted($record, $response, $httpStatus);
            return;
        }

        if ($operation === self::OPERATION || $operation === '') {
            $this->markCompleted($record, $response, $httpStatus);
            return;
        }

        throw new RuntimeException('idempotency_operation_invalid: idempotency operation is invalid');
    }

    public function markFailed(array $record, int $httpStatus): void
    {
        $this->repository->markFailed((string)$record['uuid'], $httpStatus);
    }

    public function markOperationFailed(array $record, int $httpStatus): void
    {
        $this->markFailed($record, $httpStatus);
    }

    private function normalizeKey(?string $headerValue): ?string
    {
        if ($headerValue === null) {
            return null;
        }

        $key = trim($headerValue);
        return $key === '' ? '' : $key;
    }

    private function isValidKey(string $key): bool
    {
        $length = strlen($key);
        return $length >= 8
            && $length <= 128
            && preg_match('/^[A-Za-z0-9._:-]+$/', $key) === 1;
    }

    private function isAllowedOperation(string $operation): bool
    {
        return in_array(
            $operation,
            [
                self::OPERATION,
                self::CHECKOUT_OPERATION,
                self::PAYMENT_ROUTE_OPERATION,
                self::PAYMENT_ROUTE_CHECKOUT_OPERATION,
                self::PAYMENT_INTENT_OPERATION,
                self::PAYMENT_INTENT_CONFIRM_MOCK_OPERATION,
                self::PAYMENT_INTENT_ACTIVATE_AFTER_PAYMENT_OPERATION,
            ],
            true
        );
    }

    private function requestHashForOperation(string $operation, array $scope, array $payload): string
    {
        if ($operation === self::CHECKOUT_OPERATION) {
            return $this->buildCheckoutRequestHash($scope, $payload);
        }
        if ($operation === self::PAYMENT_INTENT_OPERATION) {
            return $this->buildPaymentIntentRequestHash($scope, $payload);
        }
        if ($operation === self::PAYMENT_ROUTE_OPERATION) {
            return $this->buildPaymentRouteRequestHash($scope, $payload);
        }
        if ($operation === self::PAYMENT_ROUTE_CHECKOUT_OPERATION) {
            return $this->buildPaymentRouteCheckoutRequestHash($scope, $payload);
        }
        if ($operation === self::PAYMENT_INTENT_CONFIRM_MOCK_OPERATION) {
            return $this->buildPaymentIntentConfirmMockRequestHash($scope, $payload);
        }
        if ($operation === self::PAYMENT_INTENT_ACTIVATE_AFTER_PAYMENT_OPERATION) {
            return $this->buildPaymentIntentActivateAfterPaymentRequestHash($scope, $payload);
        }

        return $this->requestHash($scope, $payload);
    }

    public function buildCheckoutRequestHash(array $scope, array $payload): string
    {
        $contract = is_array($payload['contract'] ?? null) ? $payload['contract'] : [];
        $acceptance = is_array($payload['acceptance'] ?? null) ? $payload['acceptance'] : [];
        $canonical = [
            'operation' => self::CHECKOUT_OPERATION,
            'entity_type' => (string)($scope['entity_type'] ?? $payload['entity_type'] ?? ''),
            'entity_id' => (string)($scope['entity_id'] ?? $payload['entity_id'] ?? ''),
            'plan_code' => (string)($payload['plan_code'] ?? ''),
            'billing_period' => (string)($payload['billing_period'] ?? ''),
            'contract' => [
                'version' => (string)($contract['version'] ?? $payload['contract_version'] ?? ''),
                'hash' => (string)($contract['hash'] ?? $payload['contract_hash'] ?? ''),
                'snapshot_url' => (string)($contract['snapshot_url'] ?? $payload['contract_snapshot_url'] ?? ''),
            ],
            'acceptance' => [
                'source' => (string)($acceptance['source'] ?? $payload['acceptance_source'] ?? ''),
            ],
            'source' => (string)($payload['source'] ?? ''),
        ];

        return hash('sha256', $this->canonicalJson($canonical));
    }

    public function buildPaymentIntentRequestHash(array $scope, array $payload): string
    {
        $canonical = [
            'operation' => self::PAYMENT_INTENT_OPERATION,
            'entity_type' => (string)($scope['entity_type'] ?? ''),
            'entity_id' => (string)($scope['entity_id'] ?? ''),
            'user_id' => (string)($scope['user_id'] ?? ''),
            'checkout_intent_uuid' => (string)($payload['checkout_intent_uuid'] ?? $scope['checkout_intent_uuid'] ?? ''),
            'provider' => (string)($payload['provider'] ?? ''),
            'provider_payment_id' => (string)($payload['provider_payment_id'] ?? ''),
            'provider_checkout_id' => (string)($payload['provider_checkout_id'] ?? ''),
            'amount_cents' => isset($payload['amount_cents']) ? (int)$payload['amount_cents'] : null,
            'currency' => (string)($payload['currency'] ?? ''),
            'source' => (string)($payload['source'] ?? ''),
        ];

        return hash('sha256', $this->canonicalJson($canonical));
    }

    public function buildPaymentRouteRequestHash(array $scope, array $payload): string
    {
        $canonical = [
            'operation' => self::PAYMENT_ROUTE_OPERATION,
            'route_type' => (string)($payload['route_type'] ?? ''),
            'entity_type' => (string)($scope['entity_type'] ?? $payload['entity_type'] ?? ''),
            'entity_id' => (string)($scope['entity_id'] ?? $payload['entity_id'] ?? ''),
            'current_plan_code' => (string)($payload['current_plan_code'] ?? ''),
            'target_plan_code' => (string)($payload['target_plan_code'] ?? $payload['plan_code'] ?? ''),
            'billing_period' => (string)($payload['billing_period'] ?? ''),
            'payment_method_family' => (string)($payload['payment_method_family'] ?? ''),
            'auto_renew_requested' => (bool)($payload['auto_renew_requested'] ?? false),
        ];

        return hash('sha256', $this->canonicalJson($canonical));
    }

    public function buildPaymentRouteCheckoutRequestHash(array $scope, array $payload): string
    {
        $canonical = [
            'operation' => self::PAYMENT_ROUTE_CHECKOUT_OPERATION,
            'entity_type' => (string)($scope['entity_type'] ?? $payload['entity_type'] ?? ''),
            'entity_id' => (string)($scope['entity_id'] ?? $payload['entity_id'] ?? ''),
            'payment_route_uuid' => (string)($payload['payment_route_uuid'] ?? ''),
            'provider' => (string)($payload['provider'] ?? 'none'),
            'route_type' => (string)($payload['route_type'] ?? ''),
            'current_plan_code' => (string)($payload['current_plan_code'] ?? ''),
            'target_plan_code' => (string)($payload['target_plan_code'] ?? ''),
            'billing_period' => (string)($payload['billing_period'] ?? ''),
            'amount_cents' => isset($payload['amount_cents']) ? (int)$payload['amount_cents'] : null,
            'currency' => (string)($payload['currency'] ?? ''),
            'request_payload_hash' => (string)($payload['request_payload_hash'] ?? ''),
        ];

        return hash('sha256', $this->canonicalJson($canonical));
    }

    public function buildPaymentIntentConfirmMockRequestHash(array $scope, array $payload): string
    {
        $canonical = [
            'operation' => self::PAYMENT_INTENT_CONFIRM_MOCK_OPERATION,
            'entity_type' => (string)($scope['entity_type'] ?? ''),
            'entity_id' => (string)($scope['entity_id'] ?? ''),
            'user_id' => (string)($scope['user_id'] ?? ''),
            'checkout_intent_uuid' => (string)($payload['checkout_intent_uuid'] ?? $scope['checkout_intent_uuid'] ?? ''),
            'payment_intent_uuid' => (string)($payload['payment_intent_uuid'] ?? $scope['payment_intent_uuid'] ?? ''),
            'provider' => (string)($payload['provider'] ?? 'mxmed_mock'),
            'action' => 'confirm_mock',
            'source' => (string)($payload['source'] ?? ''),
        ];

        return hash('sha256', $this->canonicalJson($canonical));
    }

    public function buildPaymentIntentActivateAfterPaymentRequestHash(array $scope, array $payload): string
    {
        $canonical = [
            'operation' => self::PAYMENT_INTENT_ACTIVATE_AFTER_PAYMENT_OPERATION,
            'entity_type' => (string)($scope['entity_type'] ?? ''),
            'entity_id' => (string)($scope['entity_id'] ?? ''),
            'user_id' => (string)($scope['user_id'] ?? ''),
            'checkout_intent_uuid' => (string)($payload['checkout_intent_uuid'] ?? $scope['checkout_intent_uuid'] ?? ''),
            'payment_intent_uuid' => (string)($payload['payment_intent_uuid'] ?? $scope['payment_intent_uuid'] ?? ''),
            'payment_event_uuid' => (string)($payload['payment_event_uuid'] ?? $scope['payment_event_uuid'] ?? ''),
            'provider' => (string)($payload['provider'] ?? ''),
            'normalized_status' => (string)($payload['normalized_status'] ?? $payload['payment_intent_status'] ?? ''),
            'provider_status' => (string)($payload['provider_status'] ?? ''),
            'event_type' => (string)($payload['event_type'] ?? ''),
            'processing_status' => (string)($payload['processing_status'] ?? ''),
            'plan_code' => (string)($payload['plan_code'] ?? ''),
            'billing_period' => (string)($payload['billing_period'] ?? ''),
            'amount_cents' => isset($payload['amount_cents']) ? (int)$payload['amount_cents'] : null,
            'currency' => (string)($payload['currency'] ?? ''),
            'contract_acceptance_uuid' => (string)($payload['contract_acceptance_uuid'] ?? $scope['contract_acceptance_uuid'] ?? ''),
        ];

        return hash('sha256', $this->canonicalJson($canonical));
    }

    private function requestHash(array $scope, array $payload): string
    {
        $contract = is_array($payload['contract'] ?? null) ? $payload['contract'] : [];
        $acceptance = is_array($payload['acceptance'] ?? null) ? $payload['acceptance'] : [];
        $canonical = [
            'operation' => self::OPERATION,
            'entity_type' => (string)($scope['entity_type'] ?? ''),
            'entity_id' => (string)($scope['entity_id'] ?? ''),
            'doctor_id' => (string)($scope['doctor_id'] ?? ''),
            'user_id' => (string)($scope['user_id'] ?? ''),
            'actor_role' => (string)($scope['actor_role'] ?? ''),
            'plan_code' => (string)($payload['plan_code'] ?? ''),
            'billing_period' => (string)($payload['billing_period'] ?? ''),
            'contract' => [
                'version' => (string)($contract['version'] ?? ''),
                'hash' => (string)($contract['hash'] ?? ''),
                'snapshot_url' => (string)($contract['snapshot_url'] ?? ''),
            ],
            'acceptance' => [
                'source' => (string)($acceptance['source'] ?? ''),
            ],
        ];

        return hash('sha256', $this->canonicalJson($canonical));
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

    private function completedReplayStatus(array $record): int
    {
        $status = (int)($record['response_http_status'] ?? 0);
        return $status >= 200 && $status < 600 ? $status : 200;
    }

    private function requiresStoredReplayResponse(string $operation): bool
    {
        return in_array(
            $operation,
            [
                self::CHECKOUT_OPERATION,
                self::PAYMENT_ROUTE_OPERATION,
                self::PAYMENT_ROUTE_CHECKOUT_OPERATION,
                self::PAYMENT_INTENT_OPERATION,
                self::PAYMENT_INTENT_ACTIVATE_AFTER_PAYMENT_OPERATION,
            ],
            true
        );
    }

    private function completedReplayResponse(array $record, bool $requireStoredResponse = false): ?array
    {
        $body = trim((string)($record['response_body_text'] ?? ''));
        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $decoded['meta'] = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];
                $decoded['meta']['idempotent_replay'] = true;
                $operation = trim((string)($record['operation'] ?? ''));
                if ($this->normalizesNestedIdempotencyReplay($operation) && is_array($decoded['data'] ?? null)) {
                    $decoded['data']['idempotency'] = is_array($decoded['data']['idempotency'] ?? null)
                        ? $decoded['data']['idempotency']
                        : [];
                    $decoded['data']['idempotency']['idempotent_replay'] = true;
                }
                return $decoded;
            }
        }

        if ($requireStoredResponse) {
            return null;
        }

        return [
            'ok' => true,
            'data' => [
                'subscription_id' => (string)($record['subscription_id'] ?? ''),
                'contract_acceptance_uuid' => (string)($record['contract_acceptance_uuid'] ?? ''),
            ],
            'meta' => [
                'source' => 'subscriptions_write_idempotency_v1',
                'idempotent_replay' => true,
            ],
        ];
    }

    private function normalizesNestedIdempotencyReplay(string $operation): bool
    {
        return in_array(
            $operation,
            [
                self::CHECKOUT_OPERATION,
                self::PAYMENT_ROUTE_OPERATION,
                self::PAYMENT_ROUTE_CHECKOUT_OPERATION,
                self::PAYMENT_INTENT_OPERATION,
            ],
            true
        );
    }

    private function nullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text !== '' ? $text : null;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
