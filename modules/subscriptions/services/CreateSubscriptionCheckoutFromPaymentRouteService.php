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
use Subscriptions\Repositories\SubscriptionPaymentRouteRepository;
use Throwable;

final class CreateSubscriptionCheckoutFromPaymentRouteException extends RuntimeException
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

final class CreateSubscriptionCheckoutFromPaymentRouteService
{
    private const PROVIDER_NONE = 'none';
    private const ROUTE_STATUS_CREATED_NO_PROVIDER = 'created_no_provider';
    private const ROUTE_STATUS_CHECKOUT_CREATED_NO_PROVIDER = 'checkout_created_no_provider';
    private const CHECKOUT_STATUS_PENDING_PAYMENT = 'pending_payment';
    private const ROUTE_UPGRADE_SUBSCRIPTION = 'upgrade_subscription';
    private const ROUTE_NEW_SUBSCRIPTION = 'new_subscription';
    private const ROUTE_RENEWAL = 'renewal';
    private const CHECKOUT_INTENT_UPGRADE = 'upgrade';
    private const CHECKOUT_INTENT_NEW_SUBSCRIPTION = 'new_subscription';
    private const CHECKOUT_SOURCE = 'payment_route_checkout';
    private const CONTRACT_VERSION = 'mxmed-subscriptions-v1';
    private const CONTRACT_HASH = 'sha256:qa-local-dev-contract-placeholder';
    private const CONTRACT_SNAPSHOT_URL = '/legal/subscriptions/mxmed-subscriptions-v1.html';
    private const CONTRACT_TITLE = 'Contrato de suscripción México Médico';
    private const PRICING_STRATEGY_PRORATED_DIFFERENCE = 'prorated_difference';
    private const DEFAULT_EXPIRES_MINUTES = 30;

    private PDO $pdo;
    private BuildSubscriptionPaymentRoutePreviewService $previewService;
    private SubscriptionPaymentRouteRepository $routeRepository;
    private SubscriptionCheckoutIntentRepository $checkoutIntentRepository;
    private CurrentSubscriptionRepository $currentSubscriptionRepository;
    private SubscriptionEntityResolverService $entityResolver;
    private CreateSubscriptionPendingPaymentAcceptanceService $acceptanceService;
    private SubscriptionWriteIdempotencyService $idempotencyService;
    private SubscriptionEntityWriteLockService $lockService;

    public function __construct(
        PDO $pdo,
        BuildSubscriptionPaymentRoutePreviewService $previewService,
        SubscriptionPaymentRouteRepository $routeRepository,
        SubscriptionCheckoutIntentRepository $checkoutIntentRepository,
        CurrentSubscriptionRepository $currentSubscriptionRepository,
        SubscriptionEntityResolverService $entityResolver,
        CreateSubscriptionPendingPaymentAcceptanceService $acceptanceService,
        SubscriptionWriteIdempotencyService $idempotencyService,
        SubscriptionEntityWriteLockService $lockService
    ) {
        $this->pdo = $pdo;
        $this->previewService = $previewService;
        $this->routeRepository = $routeRepository;
        $this->checkoutIntentRepository = $checkoutIntentRepository;
        $this->currentSubscriptionRepository = $currentSubscriptionRepository;
        $this->entityResolver = $entityResolver;
        $this->acceptanceService = $acceptanceService;
        $this->idempotencyService = $idempotencyService;
        $this->lockService = $lockService;
    }

    public function createCheckout(array $input): array
    {
        $entityType = strtolower($this->requiredText($input['entity_type'] ?? null, 'validation_error', 'entity_type is required', 64));
        $entityId = $this->requiredText($input['entity_id'] ?? null, 'validation_error', 'entity_id is required', 64);
        $paymentRouteUuid = $this->requiredText($input['payment_route_uuid'] ?? null, 'validation_error', 'payment_route_uuid is required', 36);
        $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
        $provider = $this->provider($payload['provider'] ?? self::PROVIDER_NONE);
        $idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                422,
                'missing_idempotency_key',
                'Idempotency-Key is required'
            );
        }

        $route = $this->routeRepository->findByUuid($paymentRouteUuid);
        if ($route === null) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                404,
                'payment_route_not_found',
                'payment route was not found'
            );
        }
        $this->assertRouteEntity($route, $entityType, $entityId);

        $preview = $this->recalculatePreview($route, $idempotencyKey);
        $this->assertRouteStillMatchesPreview($route, $preview);

        $scope = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'doctor_id' => (string)($input['doctor_id'] ?? ''),
            'profile_id' => $input['profile_id'] ?? null,
            'user_id' => (string)($input['actor_user_id'] ?? ''),
            'actor_role' => (string)($input['actor_role'] ?? ''),
        ];
        $idempotencyPayload = $this->idempotencyPayload($route, $preview, $provider, $payload);
        $requestHash = $this->idempotencyService->buildPaymentRouteCheckoutRequestHash($scope, $idempotencyPayload);
        $idempotencyDecision = $this->idempotencyService->beginPaymentRouteCheckout(
            $idempotencyKey,
            $scope,
            $idempotencyPayload
        );

        if ($idempotencyDecision->shouldReplay()) {
            return $idempotencyDecision->response();
        }
        if ($idempotencyDecision->shouldReject()) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                $idempotencyDecision->httpStatus(),
                $this->idempotencyErrorCode($idempotencyDecision->errorCode()),
                $idempotencyDecision->message()
            );
        }

        $idempotencyRecord = $idempotencyDecision->record();
        $lockName = null;
        $transactionOpen = false;

        try {
            $lockName = $this->lockService->acquirePaymentRouteCheckoutCreate($entityType, $entityId, 2);
            if ($lockName === null) {
                throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                    409,
                    SubscriptionEntityWriteLockService::ERROR_PAYMENT_ROUTE_CHECKOUT_LOCK_TIMEOUT,
                    'payment route checkout lock timeout'
                );
            }

            $this->pdo->beginTransaction();
            $transactionOpen = true;

            $freshRoute = $this->routeRepository->findByUuid($paymentRouteUuid);
            if ($freshRoute === null) {
                throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                    404,
                    'payment_route_not_found',
                    'payment route was not found'
                );
            }
            $this->assertRouteEntity($freshRoute, $entityType, $entityId);
            $this->assertRouteConsumable($freshRoute);

            $existingCheckout = $this->checkoutIntentRepository->findByPaymentRouteUuid($paymentRouteUuid);
            if ($existingCheckout !== null) {
                throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                    409,
                    'route_already_consumed',
                    'payment route already has a checkout intent'
                );
            }
            if ($this->checkoutIntentRepository->findPendingByEntity($entityType, $entityId) !== null) {
                throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                    409,
                    'route_conflict',
                    'entity already has a pending checkout intent'
                );
            }

            $entity = $this->resolvedEntity($entityType, $entityId);
            $planCode = $this->checkoutPlanCode($freshRoute);
            $intentType = $this->checkoutIntentType($freshRoute);
            $activeSubscription = $this->currentSubscriptionRepository->findActiveByEntity($entityType, $entityId);
            $this->assertRouteBusinessState($freshRoute, $activeSubscription);

            $acceptance = $this->acceptanceService->createPendingPaymentAcceptance([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'doctor_id' => $scope['doctor_id'] !== '' ? $scope['doctor_id'] : ($entityType === 'doctor' ? $entityId : null),
                'profile_id' => $scope['profile_id'] ?? null,
                'actor_user_id' => $scope['user_id'],
                'actor_role' => $scope['actor_role'] !== '' ? $scope['actor_role'] : 'doctor',
                'plan_code' => $planCode,
                'billing_period' => (string)$freshRoute['billing_period'],
                'duration_days' => $this->durationDays($freshRoute),
                'contract_version' => self::CONTRACT_VERSION,
                'contract_hash' => self::CONTRACT_HASH,
                'contract_snapshot_url' => self::CONTRACT_SNAPSHOT_URL,
                'contract_title' => self::CONTRACT_TITLE,
                'acceptance_source' => 'checkout_intent',
                'source' => 'checkout_intent',
            ]);

            $checkout = $this->checkoutIntentRepository->create([
                'uuid' => $this->uuidV4(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'doctor_id' => $scope['doctor_id'] !== '' ? $scope['doctor_id'] : ($entityType === 'doctor' ? $entityId : null),
                'profile_id' => $scope['profile_id'] ?? null,
                'user_id' => $scope['user_id'],
                'actor_role' => $scope['actor_role'] !== '' ? $scope['actor_role'] : 'doctor',
                'plan_code' => $planCode,
                'billing_period' => (string)$freshRoute['billing_period'],
                'amount_cents' => (int)$freshRoute['amount_cents'],
                'currency' => (string)$freshRoute['currency'],
                'price_source' => 'payment_route_server_recalculated',
                'price_version' => 'payment-route-checkout-v1',
                'status' => self::CHECKOUT_STATUS_PENDING_PAYMENT,
                'contract_version' => self::CONTRACT_VERSION,
                'contract_hash' => self::CONTRACT_HASH,
                'contract_snapshot_url' => self::CONTRACT_SNAPSHOT_URL,
                'contract_acceptance_uuid' => (string)$acceptance['contract_acceptance_uuid'],
                'idempotency_key_hash' => isset($idempotencyRecord['idempotency_key_hash'])
                    ? (string)$idempotencyRecord['idempotency_key_hash']
                    : hash('sha256', $idempotencyKey),
                'request_hash' => $requestHash,
                'payment_route_uuid' => $paymentRouteUuid,
                'expires_at' => $this->expiresAt(),
                'source' => self::CHECKOUT_SOURCE,
                'notes' => $this->checkoutNotes($freshRoute, $activeSubscription, $intentType),
            ]);

            $updatedRoute = $this->routeRepository->markCheckoutCreated($paymentRouteUuid, (string)$checkout['uuid']);
            if ($updatedRoute === null) {
                throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                    409,
                    'route_already_consumed',
                    'payment route was already consumed'
                );
            }

            $response = $this->response($updatedRoute, $checkout, $acceptance, $entity, $intentType, false);
            if ($idempotencyRecord !== null) {
                $this->idempotencyService->markPaymentRouteCompleted($idempotencyRecord, $response, 201);
            }

            $this->pdo->commit();
            $transactionOpen = false;

            return $response;
        } catch (CreateSubscriptionCheckoutFromPaymentRouteException $e) {
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
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                422,
                $this->exceptionCode($e, 'validation_error'),
                'payment route checkout payload is invalid',
                $e
            );
        } catch (Throwable $e) {
            if ($transactionOpen && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($idempotencyRecord !== null) {
                $this->idempotencyService->markOperationFailed($idempotencyRecord, 500);
            }
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                500,
                'payment_route_checkout_unavailable',
                'payment route checkout is unavailable',
                $e
            );
        } finally {
            $this->lockService->release($lockName);
        }
    }

    private function provider($value): string
    {
        $provider = strtolower(trim((string)($value ?? self::PROVIDER_NONE)));
        if ($provider === '') {
            $provider = self::PROVIDER_NONE;
        }
        if ($provider !== self::PROVIDER_NONE) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                422,
                'unsupported_provider',
                'provider is not supported for this payment route checkout'
            );
        }

        return $provider;
    }

    private function assertRouteEntity(array $route, string $entityType, string $entityId): void
    {
        if ((string)$route['entity_type'] !== $entityType || (string)$route['entity_id'] !== $entityId) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                403,
                'entity_scope_mismatch',
                'payment route does not belong to this entity'
            );
        }
    }

    private function assertRouteConsumable(array $route): void
    {
        $checkoutIntentUuid = $this->nullableText($route['checkout_intent_uuid'] ?? null);
        if ($checkoutIntentUuid !== null) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                409,
                'route_already_consumed',
                'payment route already has a checkout intent'
            );
        }

        if ((string)($route['status'] ?? '') !== self::ROUTE_STATUS_CREATED_NO_PROVIDER) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                409,
                'route_already_consumed',
                'payment route is not available for checkout creation'
            );
        }

        $expiresAt = $this->parseDateTime($route['expires_at'] ?? null);
        if ($expiresAt === null || $expiresAt < new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                409,
                'route_expired',
                'payment route is expired'
            );
        }
    }

    private function recalculatePreview(array $route, string $idempotencyKey): array
    {
        try {
            return $this->previewService->build([
                'entity_type' => (string)$route['entity_type'],
                'entity_id' => (string)$route['entity_id'],
                'payload' => [
                    'route_type' => (string)$route['route_type'],
                    'current_plan_code' => $route['current_plan_code'] ?? null,
                    'target_plan_code' => $route['target_plan_code'] ?? null,
                    'plan_code' => $route['target_plan_code'] ?? $route['current_plan_code'] ?? null,
                    'billing_period' => (string)$route['billing_period'],
                    'payment_method_family' => (string)$route['payment_method_family'],
                    'auto_renew_requested' => (bool)$route['auto_renew_requested'],
                    'amount_cents' => (int)$route['amount_cents'],
                ],
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (BuildSubscriptionPaymentRoutePreviewException $e) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                $e->status() >= 500 ? 500 : 409,
                $e->status() >= 500 ? 'internal_error' : 'route_context_changed',
                $e->status() >= 500 ? 'payment route preview is unavailable' : 'payment route context changed',
                $e
            );
        }
    }

    private function assertRouteStillMatchesPreview(array $route, array $preview): void
    {
        foreach (['route_type', 'current_plan_code', 'target_plan_code', 'billing_period'] as $key) {
            if ((string)($route[$key] ?? '') !== (string)($preview[$key] ?? '')) {
                throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                    409,
                    'route_context_changed',
                    'payment route context changed'
                );
            }
        }

        if ((int)$route['amount_cents'] !== (int)($preview['amount_cents'] ?? -1)
            || strtoupper((string)$route['currency']) !== strtoupper((string)($preview['currency'] ?? ''))
        ) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                409,
                'route_amount_changed',
                'payment route amount changed'
            );
        }
    }

    private function idempotencyPayload(array $route, array $preview, string $provider, array $payload): array
    {
        $requestPayload = $payload;
        $requestPayload['provider'] = $provider;

        return [
            'payment_route_uuid' => (string)$route['uuid'],
            'provider' => $provider,
            'route_type' => (string)$preview['route_type'],
            'current_plan_code' => (string)($preview['current_plan_code'] ?? ''),
            'target_plan_code' => (string)($preview['target_plan_code'] ?? ''),
            'billing_period' => (string)$preview['billing_period'],
            'amount_cents' => (int)$preview['amount_cents'],
            'currency' => (string)$preview['currency'],
            'request_payload_hash' => hash('sha256', $this->canonicalJson($requestPayload)),
        ];
    }

    private function resolvedEntity(string $entityType, string $entityId): array
    {
        $entity = $this->entityResolver->resolveForCheckout($entityType, $entityId);
        if (!(bool)($entity['entity_exists'] ?? false)) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                404,
                'entity_not_found',
                'entity was not found'
            );
        }
        if (!(bool)($entity['entity_is_contractable'] ?? false)) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                409,
                'entity_not_contractable',
                'entity cannot create a checkout intent'
            );
        }

        return $entity;
    }

    private function assertRouteBusinessState(array $route, ?array $activeSubscription): void
    {
        $routeType = (string)$route['route_type'];
        if ($routeType === self::ROUTE_NEW_SUBSCRIPTION && $activeSubscription !== null) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                409,
                'active_subscription_exists',
                'active subscription already exists'
            );
        }
        if (in_array($routeType, [self::ROUTE_UPGRADE_SUBSCRIPTION, self::ROUTE_RENEWAL], true) && $activeSubscription === null) {
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                409,
                'active_subscription_required',
                'active subscription is required'
            );
        }
        if ($routeType === self::ROUTE_UPGRADE_SUBSCRIPTION && $activeSubscription !== null) {
            if ((string)($activeSubscription['plan_code'] ?? '') !== (string)($route['current_plan_code'] ?? '')
                || (string)($activeSubscription['billing_period'] ?? '') !== (string)($route['billing_period'] ?? '')
            ) {
                throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                    409,
                    'route_context_changed',
                    'payment route context changed'
                );
            }
        }
    }

    private function checkoutPlanCode(array $route): string
    {
        $routeType = (string)$route['route_type'];
        if ($routeType === self::ROUTE_UPGRADE_SUBSCRIPTION || $routeType === self::ROUTE_NEW_SUBSCRIPTION) {
            $targetPlanCode = $this->nullableText($route['target_plan_code'] ?? null);
            if ($targetPlanCode === null) {
                throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                    409,
                    'route_context_changed',
                    'payment route context changed'
                );
            }

            return $targetPlanCode;
        }
        if ($routeType === self::ROUTE_RENEWAL) {
            $currentPlanCode = $this->nullableText($route['current_plan_code'] ?? null);
            if ($currentPlanCode === null) {
                throw new CreateSubscriptionCheckoutFromPaymentRouteException(
                    409,
                    'route_context_changed',
                    'payment route context changed'
                );
            }

            return $currentPlanCode;
        }

        throw new CreateSubscriptionCheckoutFromPaymentRouteException(
            409,
            'route_context_changed',
            'payment route context changed'
        );
    }

    private function checkoutIntentType(array $route): string
    {
        $routeType = (string)$route['route_type'];
        if ($routeType === self::ROUTE_UPGRADE_SUBSCRIPTION) {
            return self::CHECKOUT_INTENT_UPGRADE;
        }
        if ($routeType === self::ROUTE_NEW_SUBSCRIPTION) {
            return self::CHECKOUT_INTENT_NEW_SUBSCRIPTION;
        }

        return self::ROUTE_RENEWAL;
    }

    private function durationDays(array $route): ?int
    {
        foreach (['period_days', 'renewal_duration_days'] as $key) {
            if (isset($route[$key]) && (int)$route[$key] > 0) {
                return (int)$route[$key];
            }
        }

        return null;
    }

    private function checkoutNotes(array $route, ?array $activeSubscription, string $intentType): string
    {
        $notes = [
            'source' => self::CHECKOUT_SOURCE,
            'payment_route_uuid' => (string)$route['uuid'],
            'route_type' => (string)$route['route_type'],
        ];

        if ($intentType === self::CHECKOUT_INTENT_UPGRADE) {
            $currentPrice = (int)($route['current_price_cents'] ?? 0);
            $targetPrice = (int)($route['target_price_cents'] ?? 0);
            $notes['intent_type'] = self::CHECKOUT_INTENT_UPGRADE;
            $notes['pricing_strategy'] = self::PRICING_STRATEGY_PRORATED_DIFFERENCE;
            $notes['upgrade_context'] = [
                'intent_type' => self::CHECKOUT_INTENT_UPGRADE,
                'source_subscription_id' => (string)($activeSubscription['subscription_id'] ?? ''),
                'current_plan_code' => (string)$route['current_plan_code'],
                'target_plan_code' => (string)$route['target_plan_code'],
                'current_billing_period' => (string)$route['billing_period'],
                'target_billing_period' => (string)$route['billing_period'],
                'current_price_period_cents' => $currentPrice,
                'target_price_period_cents' => $targetPrice,
                'price_difference_cents' => max(0, $targetPrice - $currentPrice),
                'remaining_days' => (int)($route['remaining_days'] ?? 0),
                'period_days' => (int)($route['period_days'] ?? 0),
                'adjustment_amount_cents' => (int)$route['amount_cents'],
                'currency' => (string)$route['currency'],
                'pricing_strategy' => self::PRICING_STRATEGY_PRORATED_DIFFERENCE,
            ];
        } else {
            $notes['intent_type'] = $intentType;
        }

        $json = json_encode($notes, JSON_UNESCAPED_SLASHES);
        return is_string($json) && $json !== ''
            ? $json
            : 'Created by payment route checkout bridge; awaiting payment provider initialization.';
    }

    private function response(
        array $route,
        array $checkout,
        array $acceptance,
        array $entity,
        string $intentType,
        bool $idempotentReplay
    ): array {
        return [
            'ok' => true,
            'data' => [
                'mode' => 'checkout_created_no_provider',
                'payment_route_uuid' => (string)$route['uuid'],
                'checkout_intent_uuid' => (string)$checkout['uuid'],
                'route_type' => (string)$route['route_type'],
                'intent_type' => $intentType,
                'entity_type' => (string)$route['entity_type'],
                'entity_id' => (string)$route['entity_id'],
                'entity' => $entity,
                'current_plan_code' => $route['current_plan_code'] ?? null,
                'target_plan_code' => $route['target_plan_code'] ?? null,
                'plan_code' => (string)$checkout['plan_code'],
                'billing_period' => (string)$checkout['billing_period'],
                'status' => self::ROUTE_STATUS_CHECKOUT_CREATED_NO_PROVIDER,
                'checkout_status' => (string)$checkout['status'],
                'provider' => null,
                'provider_status' => (string)$route['provider_status'],
                'amount_cents' => (int)$checkout['amount_cents'],
                'currency' => (string)$checkout['currency'],
                'amount_source' => (string)$route['amount_source'],
                'contract_acceptance_uuid' => (string)($acceptance['contract_acceptance_uuid'] ?? ''),
                'next_action' => [
                    'type' => 'payment_intent_provider_pending',
                    'enabled' => false,
                ],
                'idempotency' => [
                    'required' => true,
                    'received' => true,
                    'persisted' => true,
                    'mode' => 'payment_route_checkout_create',
                    'operation' => SubscriptionWriteIdempotencyService::PAYMENT_ROUTE_CHECKOUT_OPERATION,
                    'idempotent_replay' => $idempotentReplay,
                ],
                'warnings' => $this->jsonArray($route['warnings_json'] ?? null),
                'reasons' => $this->jsonArray($route['reasons_json'] ?? null),
                'expires_at' => (string)$checkout['expires_at'],
                'checkout_created_at' => $route['checkout_created_at'] ?? null,
            ],
            'meta' => [
                'contract' => 'subscription_payment_route_checkout',
                'version' => 'SUB-PAYMENT-ROUTE-CHECKOUT-1',
                'generated_at' => gmdate('c'),
                'source' => 'subscriptions_payment_route_checkout',
                'mode' => self::ROUTE_STATUS_CHECKOUT_CREATED_NO_PROVIDER,
                'idempotent_replay' => $idempotentReplay,
            ],
        ];
    }

    private function idempotencyErrorCode(string $code): string
    {
        return $code === 'idempotency_key_reused_with_different_payload'
            ? 'idempotency_conflict'
            : $code;
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
            throw new CreateSubscriptionCheckoutFromPaymentRouteException(422, $code, $message);
        }

        return $text;
    }

    private function nullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text !== '' ? $text : null;
    }

    private function jsonArray($value): array
    {
        $decoded = is_string($value) && trim($value) !== '' ? json_decode($value, true) : null;
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function parseDateTime($value): ?DateTimeImmutable
    {
        $text = $this->nullableText($value);
        if ($text === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($text, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return null;
        }
    }

    private function expiresAt(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . self::DEFAULT_EXPIRES_MINUTES . ' minutes')
            ->format('Y-m-d H:i:s');
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

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
