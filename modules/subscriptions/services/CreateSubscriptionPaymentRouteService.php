<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Subscriptions\Repositories\SubscriptionPaymentRouteRepository;
use Throwable;

final class CreateSubscriptionPaymentRouteException extends RuntimeException
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

final class CreateSubscriptionPaymentRouteService
{
    private const STATUS_CREATED_NO_PROVIDER = 'created_no_provider';
    private const PROVIDER_STATUS_NOT_CREATED = 'not_created';
    private const NEXT_ACTION_STRIPE_CHECKOUT_PENDING = 'stripe_checkout_sandbox_pending';
    private const DEFAULT_EXPIRES_MINUTES = 30;

    private PDO $pdo;
    private BuildSubscriptionPaymentRoutePreviewService $previewService;
    private SubscriptionPaymentRouteRepository $routeRepository;
    private SubscriptionWriteIdempotencyService $idempotencyService;
    private SubscriptionEntityWriteLockService $lockService;

    public function __construct(
        PDO $pdo,
        BuildSubscriptionPaymentRoutePreviewService $previewService,
        SubscriptionPaymentRouteRepository $routeRepository,
        SubscriptionWriteIdempotencyService $idempotencyService,
        SubscriptionEntityWriteLockService $lockService
    ) {
        $this->pdo = $pdo;
        $this->previewService = $previewService;
        $this->routeRepository = $routeRepository;
        $this->idempotencyService = $idempotencyService;
        $this->lockService = $lockService;
    }

    public function createPaymentRoute(array $input): array
    {
        $entityType = strtolower($this->requiredText($input['entity_type'] ?? null, 'validation_error', 'entity_type is required', 64));
        $entityId = $this->requiredText($input['entity_id'] ?? null, 'validation_error', 'entity_id is required', 64);
        $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
        $idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw new CreateSubscriptionPaymentRouteException(
                422,
                'missing_idempotency_key',
                'Idempotency-Key is required'
            );
        }

        try {
            $preview = $this->previewService->build([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (BuildSubscriptionPaymentRoutePreviewException $e) {
            throw new CreateSubscriptionPaymentRouteException(
                $e->status(),
                $e->errorCode(),
                $e->getMessage(),
                $e
            );
        }
        $this->assertPaymentExecutionAllowed($preview);

        $scope = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'doctor_id' => (string)($input['doctor_id'] ?? ''),
            'profile_id' => $input['profile_id'] ?? null,
            'user_id' => (string)($input['actor_user_id'] ?? ''),
            'actor_role' => (string)($input['actor_role'] ?? ''),
        ];
        $idempotencyPayload = $this->idempotencyPayload($preview);
        $requestHash = $this->idempotencyService->buildPaymentRouteRequestHash($scope, $idempotencyPayload);
        $idempotencyDecision = $this->idempotencyService->beginPaymentRoute(
            $idempotencyKey,
            $scope,
            $idempotencyPayload
        );

        if ($idempotencyDecision->shouldReplay()) {
            return $idempotencyDecision->response();
        }
        if ($idempotencyDecision->shouldReject()) {
            throw new CreateSubscriptionPaymentRouteException(
                $idempotencyDecision->httpStatus(),
                $this->idempotencyErrorCode($idempotencyDecision->errorCode()),
                $idempotencyDecision->message()
            );
        }

        $idempotencyRecord = $idempotencyDecision->record();
        $lockName = null;
        $transactionOpen = false;

        try {
            $lockName = $this->lockService->acquirePaymentRouteCreate($entityType, $entityId, 2);
            if ($lockName === null) {
                throw new CreateSubscriptionPaymentRouteException(
                    409,
                    SubscriptionEntityWriteLockService::ERROR_PAYMENT_ROUTE_LOCK_TIMEOUT,
                    'payment route create lock timeout'
                );
            }

            $this->pdo->beginTransaction();
            $transactionOpen = true;

            if ($this->routeRepository->findActiveConflict(
                $entityType,
                $entityId,
                (string)$preview['route_type'],
                $this->nullableText($preview['current_plan_code'] ?? null),
                $this->nullableText($preview['target_plan_code'] ?? null),
                (string)$preview['billing_period']
            ) !== null) {
                throw new CreateSubscriptionPaymentRouteException(
                    409,
                    'route_conflict',
                    'payment route is already active for this entity and target'
                );
            }

            $route = $this->routeRepository->create($this->routeSnapshot(
                $preview,
                $payload,
                $idempotencyKey,
                $requestHash,
                $idempotencyRecord,
                [
                    'doctor_id' => $scope['doctor_id'],
                    'profile_id' => $scope['profile_id'],
                    'user_id' => $scope['user_id'],
                    'actor_role' => $scope['actor_role'],
                ]
            ));
            $response = $this->response($route, $preview, false);

            if ($idempotencyRecord !== null) {
                $this->idempotencyService->markPaymentRouteCompleted($idempotencyRecord, $response, 201);
            }

            $this->pdo->commit();
            $transactionOpen = false;

            return $response;
        } catch (CreateSubscriptionPaymentRouteException $e) {
            if ($transactionOpen && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($idempotencyRecord !== null) {
                $this->idempotencyService->markOperationFailed($idempotencyRecord, $e->status());
            }
            throw $e;
        } catch (InvalidArgumentException $e) {
            if ($transactionOpen && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($idempotencyRecord !== null) {
                $this->idempotencyService->markOperationFailed($idempotencyRecord, 422);
            }
            throw new CreateSubscriptionPaymentRouteException(
                422,
                $this->exceptionCode($e, 'invalid_payment_route_payload'),
                'payment route payload is invalid',
                $e
            );
        } catch (Throwable $e) {
            if ($transactionOpen && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($idempotencyRecord !== null) {
                $this->idempotencyService->markOperationFailed($idempotencyRecord, 500);
            }
            throw new CreateSubscriptionPaymentRouteException(
                500,
                'payment_route_unavailable',
                'payment route is unavailable',
                $e
            );
        } finally {
            $this->lockService->release($lockName);
        }
    }

    private function idempotencyPayload(array $preview): array
    {
        return [
            'route_type' => (string)($preview['route_type'] ?? ''),
            'entity_type' => (string)($preview['entity_type'] ?? ''),
            'entity_id' => (string)($preview['entity_id'] ?? ''),
            'current_plan_code' => (string)($preview['current_plan_code'] ?? ''),
            'target_plan_code' => (string)($preview['target_plan_code'] ?? ''),
            'billing_period' => (string)($preview['billing_period'] ?? ''),
            'payment_method_family' => (string)($preview['payment_method_family'] ?? ''),
            'auto_renew_requested' => (bool)($preview['auto_renew_requested'] ?? false),
        ];
    }

    private function routeSnapshot(
        array $preview,
        array $payload,
        string $idempotencyKey,
        string $requestHash,
        ?array $idempotencyRecord,
        array $scope
    ): array {
        $idempotencyKeyHash = isset($idempotencyRecord['idempotency_key_hash'])
            ? (string)$idempotencyRecord['idempotency_key_hash']
            : hash('sha256', $idempotencyKey);
        $warnings = array_values(is_array($preview['warnings'] ?? null) ? $preview['warnings'] : []);
        $reasons = array_values(is_array($preview['reasons'] ?? null) ? $preview['reasons'] : []);

        return [
            'uuid' => $this->uuidV4(),
            'entity_type' => (string)$preview['entity_type'],
            'entity_id' => (string)$preview['entity_id'],
            'doctor_id' => $this->nullableText($scope['doctor_id'] ?? null),
            'profile_id' => $this->nullableText($scope['profile_id'] ?? null),
            'user_id' => $this->nullableText($scope['user_id'] ?? null),
            'actor_role' => $this->nullableText($scope['actor_role'] ?? null),
            'route_type' => (string)$preview['route_type'],
            'current_plan_code' => $this->nullableText($preview['current_plan_code'] ?? null),
            'target_plan_code' => $this->nullableText($preview['target_plan_code'] ?? null),
            'billing_period' => (string)$preview['billing_period'],
            'payment_method_family' => (string)$preview['payment_method_family'],
            'auto_renew_requested' => (bool)($preview['auto_renew_requested'] ?? false),
            'auto_renew_status' => (string)$preview['auto_renew_status'],
            'amount_cents' => (int)$preview['amount_cents'],
            'currency' => (string)$preview['currency'],
            'amount_source' => 'server_recalculated',
            'frontend_amount_cents' => $preview['frontend_amount_cents'] ?? null,
            'amount_mismatch' => (bool)($preview['amount_mismatch'] ?? false),
            'current_price_cents' => $preview['current_price_cents'] ?? null,
            'target_price_cents' => $preview['target_price_cents'] ?? null,
            'adjustment_amount_cents' => $preview['adjustment_amount_cents'] ?? null,
            'renewal_amount_cents' => $preview['renewal_amount_cents'] ?? null,
            'remaining_days' => $preview['remaining_days'] ?? null,
            'period_days' => $preview['period_days'] ?? null,
            'renewal_duration_days' => $preview['renewal_duration_days'] ?? null,
            'current_expires_at' => $this->nullableText($preview['current_expires_at'] ?? null),
            'estimated_next_expires_at' => $this->nullableText($preview['estimated_next_expires_at'] ?? null),
            'status' => self::STATUS_CREATED_NO_PROVIDER,
            'provider' => null,
            'provider_status' => self::PROVIDER_STATUS_NOT_CREATED,
            'next_action_type' => self::NEXT_ACTION_STRIPE_CHECKOUT_PENDING,
            'next_action_enabled' => false,
            'idempotency_key' => $idempotencyKeyHash,
            'idempotency_key_hash' => $idempotencyKeyHash,
            'request_hash' => $requestHash,
            'frontend_summary_snapshot_json' => $this->jsonOrNull(
                is_array($payload['frontend_summary_snapshot'] ?? null)
                    ? $payload['frontend_summary_snapshot']
                    : null
            ),
            'server_preview_snapshot_json' => $this->jsonOrNull($preview),
            'warnings_json' => $this->jsonOrNull($warnings),
            'reasons_json' => $this->jsonOrNull($reasons),
            'expires_at' => $this->expiresAt(),
            'source' => 'mxmed_subscription_payment_route_v1',
        ];
    }

    private function response(array $route, array $preview, bool $idempotentReplay): array
    {
        $data = [
            'mode' => 'payment_route_created_no_provider',
            'payment_route_uuid' => (string)$route['uuid'],
            'route_type' => (string)$route['route_type'],
            'entity_type' => (string)$route['entity_type'],
            'entity_id' => (string)$route['entity_id'],
            'current_plan_code' => $route['current_plan_code'] ?? null,
            'target_plan_code' => $route['target_plan_code'] ?? null,
            'billing_period' => (string)$route['billing_period'],
            'amount_cents' => (int)$route['amount_cents'],
            'currency' => (string)$route['currency'],
            'amount_source' => (string)$route['amount_source'],
            'frontend_amount_cents' => $route['frontend_amount_cents'] ?? null,
            'amount_mismatch' => (bool)$route['amount_mismatch'],
            'auto_renew_requested' => (bool)$route['auto_renew_requested'],
            'auto_renew_status' => (string)$route['auto_renew_status'],
            'payment_method_family' => (string)$route['payment_method_family'],
            'status' => (string)$route['status'],
            'provider' => null,
            'provider_status' => (string)$route['provider_status'],
            'next_action' => [
                'type' => (string)$route['next_action_type'],
                'enabled' => (bool)$route['next_action_enabled'],
            ],
            'idempotency' => [
                'required' => true,
                'received' => true,
                'persisted' => true,
                'mode' => 'write_no_provider',
                'operation' => SubscriptionWriteIdempotencyService::PAYMENT_ROUTE_OPERATION,
                'idempotent_replay' => $idempotentReplay,
            ],
            'warnings' => array_values(is_array($preview['warnings'] ?? null) ? $preview['warnings'] : []),
            'reasons' => array_values(is_array($preview['reasons'] ?? null) ? $preview['reasons'] : []),
            'expires_at' => (string)$route['expires_at'],
        ];

        foreach ([
            'current_price_cents',
            'target_price_cents',
            'adjustment_amount_cents',
            'renewal_amount_cents',
            'remaining_days',
            'period_days',
            'renewal_duration_days',
            'current_expires_at',
            'estimated_next_expires_at',
        ] as $key) {
            if (array_key_exists($key, $route) && $route[$key] !== null) {
                $data[$key] = $route[$key];
            }
        }

        return [
            'ok' => true,
            'data' => $data,
            'meta' => [
                'contract' => 'subscription_payment_route_create',
                'version' => 'SUB-PAYMENT-ROUTE-CREATE-1',
                'generated_at' => gmdate('c'),
                'source' => 'subscriptions_payment_route_create',
                'mode' => self::STATUS_CREATED_NO_PROVIDER,
                'idempotent_replay' => $idempotentReplay,
            ],
        ];
    }

    private function assertPaymentExecutionAllowed(array $preview): void
    {
        if ((string)($preview['route_type'] ?? '') !== 'new_subscription'
            || (string)($preview['billing_period'] ?? '') !== 'monthly'
        ) {
            return;
        }

        $contract = is_array($preview['pricing_contract'] ?? null) ? $preview['pricing_contract'] : [];
        if (($contract['payment_execution_enabled'] ?? null) !== false) {
            return;
        }

        throw new CreateSubscriptionPaymentRouteException(
            409,
            'monthly_recurring_not_ready',
            'monthly recurring payments are not ready'
        );
    }

    private function idempotencyErrorCode(string $code): string
    {
        if ($code === 'idempotency_key_reused_with_different_payload') {
            return 'idempotency_conflict';
        }

        return $code;
    }

    private function exceptionCode(Throwable $e, string $fallback): string
    {
        $raw = trim($e->getMessage());
        if ($raw === '') {
            return $fallback;
        }

        $parts = explode(':', $raw, 2);
        $code = trim($parts[0]);
        return $code !== '' ? $code : $fallback;
    }

    private function requiredText($value, string $code, string $message, int $maxLength): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || strlen($text) > $maxLength) {
            throw new CreateSubscriptionPaymentRouteException(422, $code, $message);
        }

        return $text;
    }

    private function nullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function jsonOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return null;
        }

        return $json;
    }

    private function expiresAt(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . self::DEFAULT_EXPIRES_MINUTES . ' minutes')
            ->format('Y-m-d H:i:s');
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
