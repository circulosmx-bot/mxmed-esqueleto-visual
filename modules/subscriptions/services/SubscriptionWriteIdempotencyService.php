<?php
declare(strict_types=1);

namespace Subscriptions\Services;

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

    private SubscriptionWriteIdempotencyRepository $repository;

    public function __construct(SubscriptionWriteIdempotencyRepository $repository)
    {
        $this->repository = $repository;
    }

    public function begin(?string $headerValue, array $scope, array $payload): SubscriptionWriteIdempotencyDecision
    {
        $key = $this->normalizeKey($headerValue);
        if ($key === null) {
            return SubscriptionWriteIdempotencyDecision::disabled();
        }

        if (!$this->isValidKey($key)) {
            return SubscriptionWriteIdempotencyDecision::reject(
                422,
                'idempotency_key_invalid',
                'Idempotency-Key is invalid'
            );
        }

        $keyHash = hash('sha256', $key);
        $requestHash = $this->requestHash($scope, $payload);
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
            'operation' => self::OPERATION,
        ];

        if ($this->repository->insertProcessing($record)) {
            return SubscriptionWriteIdempotencyDecision::proceed($record);
        }

        $existing = $this->repository->findByScope(
            $keyHash,
            $record['user_id'],
            $record['entity_type'],
            $record['entity_id'],
            self::OPERATION
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
            return SubscriptionWriteIdempotencyDecision::replay(
                $this->completedReplayStatus($existing),
                $this->completedReplayResponse($existing)
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

    public function markFailed(array $record, int $httpStatus): void
    {
        $this->repository->markFailed((string)$record['uuid'], $httpStatus);
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

    private function completedReplayResponse(array $record): array
    {
        $body = trim((string)($record['response_body_text'] ?? ''));
        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $decoded['meta'] = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];
                $decoded['meta']['idempotent_replay'] = true;
                return $decoded;
            }
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
