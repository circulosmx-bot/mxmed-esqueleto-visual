<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Subscriptions\Repositories\CurrentSubscriptionRepository;
use Subscriptions\Repositories\SubscriptionContractAcceptanceRepository;
use Throwable;

final class SubscriptionWriteException extends RuntimeException
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

final class CreateSubscriptionWithAcceptanceService
{
    private const ACCEPTANCE_SOURCE = 'mxmed_contract_acceptance_v1';
    private const SUBSCRIPTION_SOURCE = 'mxmed_subscription_write_v1';
    private const FREE_PLAN_CODE = 'free';
    private const ACTIVE_STATUS = 'active';
    private const ACCEPTED_STATUS = 'accepted';
    private const ALLOWED_ACCEPTANCE_SOURCES = [
        'panel_subscription',
        'admin_panel',
        'checkout',
        'migration',
        'system',
    ];

    private PDO $pdo;
    private CurrentSubscriptionRepository $currentRepository;
    private CurrentSubscriptionReadModelService $readModelService;
    private SubscriptionContractAcceptanceRepository $acceptanceRepository;

    public function __construct(
        PDO $pdo,
        CurrentSubscriptionRepository $currentRepository,
        CurrentSubscriptionReadModelService $readModelService,
        SubscriptionContractAcceptanceRepository $acceptanceRepository
    ) {
        $this->pdo = $pdo;
        $this->currentRepository = $currentRepository;
        $this->readModelService = $readModelService;
        $this->acceptanceRepository = $acceptanceRepository;
    }

    public function create(array $input): array
    {
        $entityType = $this->requiredText($input['entity_type'] ?? null, 'invalid_entity', 'invalid entity');
        $entityId = $this->requiredText($input['entity_id'] ?? null, 'invalid_entity', 'invalid entity');
        $doctorId = $this->requiredText($input['doctor_id'] ?? null, 'forbidden', 'doctor scope required', 403);
        $profileId = $this->optionalText($input['profile_id'] ?? null);
        $actorUserId = $this->requiredNumericText($input['actor_user_id'] ?? null, 'unauthorized', 'session authentication required', 401);
        $actorRole = $this->requiredText($input['actor_role'] ?? null, 'forbidden', 'actor scope required', 403);
        $operatorId = $this->optionalNumericText($input['operator_id'] ?? null);
        $payload = $this->payload($input['payload'] ?? null);
        $ipAddress = $this->optionalText($input['ip_address'] ?? null, 45);
        $userAgent = $this->optionalText($input['user_agent'] ?? null, 512);

        if ($entityType !== 'doctor' || $entityId !== $doctorId) {
            throw new SubscriptionWriteException(403, 'forbidden', 'doctor scope mismatch');
        }
        if ($actorRole !== 'doctor' || $operatorId !== null) {
            throw new SubscriptionWriteException(403, 'forbidden', 'operator subscription writes are not enabled');
        }

        $planCode = strtolower($this->requiredText($payload['plan_code'] ?? null, 'invalid_payload', 'plan_code is required', 400, 64));
        $billingPeriod = strtolower($this->requiredText($payload['billing_period'] ?? null, 'invalid_payload', 'billing_period is required', 400, 32));
        if ($planCode === self::FREE_PLAN_CODE) {
            throw new SubscriptionWriteException(422, 'plan_not_contractable', 'free plan cannot be contracted');
        }

        $contract = $this->payload($payload['contract'] ?? null, 'contract is required', 422, 'contract_invalid');
        $contractVersion = $this->requiredText($contract['version'] ?? null, 'contract_invalid', 'contract version is required', 422, 64);
        $contractHash = $this->requiredText($contract['hash'] ?? null, 'contract_invalid', 'contract hash is required', 422, 128);
        if (strpos($contractHash, 'sha256:') !== 0) {
            throw new SubscriptionWriteException(422, 'contract_invalid', 'contract hash must use sha256');
        }
        $contractSnapshotUrl = $this->requiredText(
            $contract['snapshot_url'] ?? null,
            'contract_invalid',
            'contract snapshot is required',
            422,
            512
        );
        $contractTitle = $this->optionalText($contract['title'] ?? null, 255);

        $acceptance = $this->payload($payload['acceptance'] ?? null, 'acceptance is required', 422, 'acceptance_source_invalid');
        $acceptanceSource = $this->requiredText(
            $acceptance['source'] ?? null,
            'acceptance_source_invalid',
            'acceptance source is required',
            422,
            64
        );
        if (!in_array($acceptanceSource, self::ALLOWED_ACCEPTANCE_SOURCES, true)) {
            throw new SubscriptionWriteException(422, 'acceptance_source_invalid', 'acceptance source is invalid');
        }

        $plan = $this->currentRepository->findPlanByCodeAndPeriod($planCode, $billingPeriod);
        if ($plan === null) {
            throw new SubscriptionWriteException(404, 'plan_not_found', 'subscription plan not found');
        }

        $catalogPlanCode = strtolower($this->requiredText($plan['plan_code'] ?? null, 'plan_not_found', 'subscription plan not found', 404));
        $catalogBillingPeriod = strtolower($this->requiredText($plan['billing_period'] ?? null, 'plan_not_found', 'subscription plan not found', 404));
        $durationDays = max(0, (int)($plan['duration_days'] ?? 0));
        $isActive = (int)($plan['is_active'] ?? 0) === 1;
        if (!$isActive || $catalogPlanCode === self::FREE_PLAN_CODE || $durationDays <= 0) {
            throw new SubscriptionWriteException(422, 'plan_not_contractable', 'subscription plan is not contractable');
        }
        if ($catalogBillingPeriod !== $billingPeriod) {
            throw new SubscriptionWriteException(422, 'billing_period_invalid', 'billing period does not match plan catalog');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $acceptedAt = $now->format('Y-m-d H:i:s');
        $startsAt = $acceptedAt;
        $expiresAt = $now->modify('+' . $durationDays . ' days')->format('Y-m-d H:i:s');

        if ($this->activeSubscriptionExists($entityType, $entityId, $acceptedAt)) {
            throw new SubscriptionWriteException(409, 'active_subscription_exists', 'active subscription already exists');
        }

        $subscriptionId = $this->uuidV4();
        $acceptanceUuid = $this->uuidV4();
        $planLabel = $this->optionalText($plan['plan_label'] ?? null, 120);

        try {
            $this->pdo->beginTransaction();

            if ($this->activeSubscriptionExists($entityType, $entityId, $acceptedAt)) {
                throw new SubscriptionWriteException(409, 'active_subscription_exists', 'active subscription already exists');
            }

            $this->acceptanceRepository->insert([
                'uuid' => $acceptanceUuid,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'doctor_id' => $doctorId,
                'profile_id' => $profileId,
                'subscription_id' => $subscriptionId,
                'plan_code' => $catalogPlanCode,
                'billing_period' => $catalogBillingPeriod,
                'duration_days' => $durationDays,
                'contract_version' => $contractVersion,
                'contract_hash' => $contractHash,
                'contract_snapshot_url' => $contractSnapshotUrl,
                'contract_title' => $contractTitle,
                'accepted_at' => $acceptedAt,
                'accepted_by_user_id' => (int)$actorUserId,
                'accepted_by_actor_role' => $actorRole,
                'accepted_by_operator_id' => $operatorId !== null ? (int)$operatorId : null,
                'acceptance_source' => $acceptanceSource,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'status' => self::ACCEPTED_STATUS,
                'source' => self::ACCEPTANCE_SOURCE,
                'notes' => 'Created by subscriptions_write_v1; no payments or capabilities connected.',
            ]);

            $this->insertProfileSubscription([
                'subscription_id' => $subscriptionId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'doctor_id' => $doctorId,
                'profile_id' => $profileId,
                'plan_code' => $catalogPlanCode,
                'plan_label' => $planLabel,
                'billing_period' => $catalogBillingPeriod,
                'duration_days' => $durationDays,
                'contracted_plan_code' => $catalogPlanCode,
                'effective_plan_code' => $catalogPlanCode,
                'contract_version' => $contractVersion,
                'contract_accepted_at' => $acceptedAt,
                'contract_accepted_by_user_id' => $actorUserId,
                'contract_acceptance_source' => $acceptanceSource,
                'contract_acceptance_ip' => $ipAddress,
                'contract_acceptance_user_agent' => $this->optionalText($userAgent, 255),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => self::ACTIVE_STATUS,
                'auto_renew' => 0,
                'source' => self::SUBSCRIPTION_SOURCE,
                'notes' => 'Created by subscriptions_write_v1; no payments or capabilities connected.',
            ]);

            $currentSubscription = $this->readModelService->resolveForEntity($entityType, $entityId);
            $this->pdo->commit();
        } catch (SubscriptionWriteException $e) {
            $this->rollbackIfNeeded();
            throw $e;
        } catch (Throwable $e) {
            $this->rollbackIfNeeded();
            throw new SubscriptionWriteException(500, 'subscription_write_failed', 'internal error', $e);
        }

        return [
            'subscription_id' => $subscriptionId,
            'contract_acceptance_uuid' => $acceptanceUuid,
            'current_subscription' => $currentSubscription,
        ];
    }

    private function payload(
        $value,
        string $message = 'payload is required',
        int $status = 400,
        string $code = 'invalid_payload'
    ): array
    {
        if (!is_array($value) || $value === []) {
            throw new SubscriptionWriteException($status, $code, $message);
        }
        return $value;
    }

    private function requiredText(
        $value,
        string $code,
        string $message,
        int $status = 422,
        int $maxLength = 255
    ): string {
        $text = trim((string)($value ?? ''));
        if ($text === '' || strlen($text) > $maxLength) {
            throw new SubscriptionWriteException($status, $code, $message);
        }
        return $text;
    }

    private function requiredNumericText($value, string $code, string $message, int $status): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || !ctype_digit($text)) {
            throw new SubscriptionWriteException($status, $code, $message);
        }
        return $text;
    }

    private function optionalText($value, int $maxLength = 255): ?string
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
            throw new SubscriptionWriteException(403, 'forbidden', 'operator scope invalid');
        }
        return $text;
    }

    private function activeSubscriptionExists(string $entityType, string $entityId, string $now): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM profile_subscriptions
             WHERE entity_type = :entity_type
               AND entity_id = :entity_id
               AND deleted_at IS NULL
               AND status IN (\'active\', \'expiring_soon\', \'grace_period\')
               AND (starts_at IS NULL OR starts_at <= :now)
               AND (expires_at IS NULL OR expires_at >= :now)'
        );
        $stmt->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'now' => $now,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0) > 0;
    }

    private function insertProfileSubscription(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO profile_subscriptions (
                subscription_id,
                entity_type,
                entity_id,
                doctor_id,
                profile_id,
                plan_code,
                plan_label,
                billing_period,
                duration_days,
                contracted_plan_code,
                effective_plan_code,
                contract_version,
                contract_accepted_at,
                contract_accepted_by_user_id,
                contract_acceptance_source,
                contract_acceptance_ip,
                contract_acceptance_user_agent,
                starts_at,
                expires_at,
                status,
                auto_renew,
                source,
                notes,
                deleted_at
            ) VALUES (
                :subscription_id,
                :entity_type,
                :entity_id,
                :doctor_id,
                :profile_id,
                :plan_code,
                :plan_label,
                :billing_period,
                :duration_days,
                :contracted_plan_code,
                :effective_plan_code,
                :contract_version,
                :contract_accepted_at,
                :contract_accepted_by_user_id,
                :contract_acceptance_source,
                :contract_acceptance_ip,
                :contract_acceptance_user_agent,
                :starts_at,
                :expires_at,
                :status,
                :auto_renew,
                :source,
                :notes,
                NULL
            )'
        );

        $stmt->execute($data);
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
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
