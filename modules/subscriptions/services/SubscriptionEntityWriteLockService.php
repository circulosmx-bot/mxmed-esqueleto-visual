<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use InvalidArgumentException;
use PDO;

final class SubscriptionEntityWriteLockService
{
    private const LOCK_PREFIX = 'mxmed:subscriptions';
    private const LOCK_OPERATION = 'create';
    private const CHECKOUT_CREATE_OPERATION = 'checkout_create';
    private const PAYMENT_INTENT_CREATE_OPERATION = 'payment_intent_create';
    private const PAYMENT_INTENT_CONFIRM_OPERATION = 'payment_intent_confirm';
    private const PAYMENT_INTENT_ACTIVATE_SUBSCRIPTION_OPERATION = 'payment_intent_activate_subscription';
    private const CHECKOUT_INTENTS_SCOPE = 'checkout_intents';
    private const PAYMENT_INTENTS_SCOPE = 'payment_intents';
    private const MAX_LOCK_NAME_LENGTH = 64;
    public const ERROR_CHECKOUT_LOCK_TIMEOUT = 'subscription_checkout_lock_timeout';
    public const ERROR_PAYMENT_INTENT_LOCK_TIMEOUT = 'payment_intent_lock_timeout';
    public const ERROR_PAYMENT_INTENT_CONFIRM_LOCK_TIMEOUT = 'payment_intent_confirm_lock_timeout';
    public const ERROR_PAYMENT_INTENT_ACTIVATE_SUBSCRIPTION_LOCK_TIMEOUT = 'payment_intent_activate_subscription_lock_timeout';
    public const ERROR_LOCK_PURPOSE_INVALID = 'subscription_lock_purpose_invalid';
    public const ERROR_LOCK_ACQUIRE_FAILED = 'subscription_lock_acquire_failed';
    public const ERROR_LOCK_RELEASE_FAILED = 'subscription_lock_release_failed';
    private const ALLOWED_OPERATIONS = [
        self::LOCK_OPERATION,
        self::CHECKOUT_CREATE_OPERATION,
        self::PAYMENT_INTENT_CREATE_OPERATION,
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function acquire(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string
    {
        return $this->acquireForPurpose($entityType, $entityId, self::LOCK_OPERATION, $timeoutSeconds);
    }

    public function acquireCheckoutCreate(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string
    {
        return $this->acquireForPurpose($entityType, $entityId, self::CHECKOUT_CREATE_OPERATION, $timeoutSeconds);
    }

    // Lock scope: checkout_intent_uuid.
    public function acquirePaymentIntentCreate(string $checkoutIntentUuid, int $timeoutSeconds = 2): ?string
    {
        return $this->acquireLockName(
            $this->buildPaymentIntentCreateLockName($checkoutIntentUuid),
            $timeoutSeconds
        );
    }

    // Lock scope: payment_intent_uuid.
    public function acquirePaymentIntentConfirm(string $paymentIntentUuid, int $timeoutSeconds = 2): ?string
    {
        return $this->acquireLockName(
            $this->buildPaymentIntentConfirmLockName($paymentIntentUuid),
            $timeoutSeconds
        );
    }

    // Lock scope: payment_intent_uuid.
    public function acquirePaymentIntentActivateSubscription(string $paymentIntentUuid, int $timeoutSeconds = 2): ?string
    {
        return $this->acquireLockName(
            $this->buildPaymentIntentActivateSubscriptionLockName($paymentIntentUuid),
            $timeoutSeconds
        );
    }

    public function acquireForPurpose(
        string $entityType,
        string $entityId,
        string $purpose,
        int $timeoutSeconds = 2
    ): ?string
    {
        $lockName = $this->buildLockName($entityType, $entityId, $purpose);
        return $this->acquireLockName($lockName, $timeoutSeconds);
    }

    private function acquireLockName(string $lockName, int $timeoutSeconds = 2): ?string
    {
        $stmt = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, :timeout_seconds) AS lock_result');
        $stmt->execute([
            'lock_name' => $lockName,
            'timeout_seconds' => max(0, $timeoutSeconds),
        ]);
        $result = $stmt->fetchColumn();

        return (string)$result === '1' ? $lockName : null;
    }

    public function release(?string $lockName): void
    {
        if ($lockName === null || $lockName === '') {
            return;
        }

        $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute(['lock_name' => $lockName]);
    }

    public function buildLockName(string $entityType, string $entityId, string $purpose): string
    {
        $type = strtolower(trim($entityType));
        $id = trim($entityId);
        if ($type === '' || $id === '' || !preg_match('/^[A-Za-z0-9._:-]+$/', $type . ':' . $id)) {
            throw new InvalidArgumentException('invalid subscription write lock scope');
        }

        $operation = trim($purpose);
        if (!in_array($operation, self::ALLOWED_OPERATIONS, true)) {
            throw new InvalidArgumentException(self::ERROR_LOCK_PURPOSE_INVALID);
        }

        $lockName = self::LOCK_PREFIX . ':' . $type . ':' . $id . ':' . $operation;
        if (strlen($lockName) <= self::MAX_LOCK_NAME_LENGTH) {
            return $lockName;
        }

        return self::LOCK_PREFIX . ':' . $type . ':' . substr(hash('sha256', $id), 0, 24) . ':' . $operation;
    }

    public function buildPaymentIntentCreateLockName(string $checkoutIntentUuid): string
    {
        $uuid = trim($checkoutIntentUuid);
        if ($uuid === '' || !preg_match('/^[A-Za-z0-9._:-]+$/', $uuid)) {
            throw new InvalidArgumentException('invalid payment intent lock scope');
        }

        $lockName = self::LOCK_PREFIX
            . ':' . self::CHECKOUT_INTENTS_SCOPE
            . ':' . $uuid
            . ':' . self::PAYMENT_INTENT_CREATE_OPERATION;
        if (strlen($lockName) <= self::MAX_LOCK_NAME_LENGTH) {
            return $lockName;
        }

        return self::LOCK_PREFIX
            . ':pi:'
            . substr(hash('sha256', $uuid), 0, 19)
            . ':' . self::PAYMENT_INTENT_CREATE_OPERATION;
    }

    public function buildPaymentIntentConfirmLockName(string $paymentIntentUuid): string
    {
        $uuid = trim($paymentIntentUuid);
        if ($uuid === '' || !preg_match('/^[A-Za-z0-9._:-]+$/', $uuid)) {
            throw new InvalidArgumentException('invalid payment intent confirm lock scope');
        }

        $lockName = self::LOCK_PREFIX
            . ':' . self::PAYMENT_INTENTS_SCOPE
            . ':' . $uuid
            . ':' . self::PAYMENT_INTENT_CONFIRM_OPERATION;
        if (strlen($lockName) <= self::MAX_LOCK_NAME_LENGTH) {
            return $lockName;
        }

        return 'mxmed:sub:pi:'
            . substr(hash('sha256', $uuid), 0, 12)
            . ':confirm';
    }

    public function buildPaymentIntentActivateSubscriptionLockName(string $paymentIntentUuid): string
    {
        $uuid = trim($paymentIntentUuid);
        if ($uuid === '' || !preg_match('/^[A-Za-z0-9._:-]+$/', $uuid)) {
            throw new InvalidArgumentException('invalid payment intent activate subscription lock scope');
        }

        $lockName = 'mxmed:sub:pi:'
            . substr(hash('sha256', $uuid), 0, 12)
            . ':activate';
        if (strlen($lockName) > self::MAX_LOCK_NAME_LENGTH) {
            throw new InvalidArgumentException('payment intent activate subscription lock name too long');
        }

        return $lockName;
    }
}
