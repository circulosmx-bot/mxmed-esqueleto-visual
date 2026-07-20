<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Contracts\Clock;
use Identity\Contracts\RateLimitDecision;
use Identity\Contracts\RateLimitKeyHasher;
use Identity\Contracts\RateLimitOperation;
use Identity\Contracts\ReasonCode;
use Identity\Repositories\RateLimitBucketRepository;
use PDO;

final class RateLimitService
{
    private const DIMENSIONS = ['identity', 'ip', 'device'];

    public function __construct(private PDO $pdo, private RateLimitKeyHasher $hasher, private Clock $clock) {}

    /** @param array<string, string|null> $dimensions */
    public function consume(string $operation, array $dimensions): RateLimitDecision
    {
        [$maximum, $windowSeconds] = $this->policy($operation);
        $dimensions = array_filter($dimensions, static fn($value, $key) => in_array($key, self::DIMENSIONS, true) && is_string($value) && trim($value) !== '', ARRAY_FILTER_USE_BOTH);
        if ($dimensions === []) return new RateLimitDecision(false, ReasonCode::INVALID_INPUT);
        $repository = new RateLimitBucketRepository($this->pdo);
        $now = $this->clock->now();
        $window = $this->windowStart($now, $windowSeconds);
        $nowString = $now->format('Y-m-d H:i:s');
        $windowString = $window->format('Y-m-d H:i:s');
        try {
            $this->pdo->beginTransaction();
            $retryAfter = 0;
            foreach ($dimensions as $dimension => $rawValue) {
                $hash = $this->hasher->hash(trim((string)$rawValue));
                $row = $repository->findForUpdate($operation, $dimension, $hash, $windowString);
                if ($row === null) {
                    $repository->create('rl_' . substr(hash('sha256', $operation . '|' . $dimension . '|' . $hash . '|' . $windowString), 0, 60), $operation, $dimension, $hash, $windowString, $nowString);
                    $row = ['attempts_count' => 0, 'blocked_until' => null];
                }
                if ($row['blocked_until'] !== null && $row['blocked_until'] > $nowString) {
                    $retryAfter = max($retryAfter, max(1, strtotime($row['blocked_until']) - $now->getTimestamp()));
                    continue;
                }
                $attempts = $row['attempts_count'] + 1;
                $blockedUntil = null;
                if ($attempts > $maximum) {
                    $delay = min($windowSeconds, 5 * (2 ** min(8, $attempts - $maximum)));
                    $blockedUntil = $now->modify('+' . $delay . ' seconds')->format('Y-m-d H:i:s');
                    $retryAfter = max($retryAfter, $delay);
                }
                $repository->update($operation, $dimension, $hash, $windowString, $attempts, $blockedUntil);
            }
            $this->pdo->commit();
            return $retryAfter > 0 ? new RateLimitDecision(false, ReasonCode::RATE_LIMITED, $retryAfter) : new RateLimitDecision(true, ReasonCode::ALLOWED);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return new RateLimitDecision(false, ReasonCode::STORAGE_UNAVAILABLE);
        }
    }

    /** @param array<string, string|null> $dimensions */
    public function clear(string $operation, array $dimensions): void
    {
        [$unusedMaximum, $windowSeconds] = $this->policy($operation);
        unset($unusedMaximum);
        $repository = new RateLimitBucketRepository($this->pdo);
        $windowString = $this->windowStart($this->clock->now(), $windowSeconds)->format('Y-m-d H:i:s');
        try {
            $this->pdo->beginTransaction();
            foreach (array_filter($dimensions, static fn($value, $key) => in_array($key, self::DIMENSIONS, true) && is_string($value) && trim($value) !== '', ARRAY_FILTER_USE_BOTH) as $dimension => $rawValue) {
                $repository->reset($operation, $dimension, $this->hasher->hash(trim((string)$rawValue)), $windowString);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new \RuntimeException('rate_limit_storage_unavailable', 0, $e);
        }
    }

    /** @return array{int,int} */
    private function policy(string $operation): array
    {
        return match ($operation) {
            RateLimitOperation::CREDENTIAL_CHECK => [5, 900],
            RateLimitOperation::RECOVERY_REQUEST, RateLimitOperation::EMAIL_VERIFICATION_RESEND => [3, 3600],
            RateLimitOperation::REGISTRATION => [3, 3600],
            RateLimitOperation::TOKEN_CONSUME, RateLimitOperation::PASSWORD_RESET => [5, 900],
            default => throw new \InvalidArgumentException('unsupported_rate_limit_operation'),
        };
    }

    private function windowStart(\DateTimeImmutable $now, int $windowSeconds): \DateTimeImmutable
    {
        $timestamp = intdiv($now->getTimestamp(), $windowSeconds) * $windowSeconds;
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('UTC'));
    }
}
