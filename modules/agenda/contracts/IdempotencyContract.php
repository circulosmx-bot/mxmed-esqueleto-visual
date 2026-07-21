<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class IdempotencyRecord
{
    public function __construct(private string $operation, private string $key, private string $slotIdentity, private string $fingerprint, private array $originalResult)
    {
        foreach ([$operation, $key, $slotIdentity, $fingerprint] as $value) if (trim($value) === '') throw new \InvalidArgumentException('idempotency field required');
        $this->originalResult = $originalResult;
    }
    public function operation(): string { return $this->operation; }
    public function key(): string { return $this->key; }
    public function slotIdentity(): string { return $this->slotIdentity; }
    public function fingerprint(): string { return $this->fingerprint; }
    public function originalResult(): array { return $this->originalResult; }
}

final class IdempotencyEvaluation
{
    public function __construct(private string $status, private bool $mutationEffective, private int $httpStatus, private array $result = []) {}
    public function status(): string { return $this->status; }
    public function mutationEffective(): bool { return $this->mutationEffective; }
    public function httpStatus(): int { return $this->httpStatus; }
    public function result(): array { return $this->result; }
}

final class IdempotencyContract
{
    public const REPLAY = 'replay';
    public const CONFLICT = 'collision_conflict';
    public const NEW = 'new';

    public static function evaluate(?IdempotencyRecord $record, string $key, string $fingerprint): IdempotencyEvaluation
    {
        if (trim($key) === '' || trim($fingerprint) === '') throw new \InvalidArgumentException('idempotency key and fingerprint required');
        if ($record === null) return new IdempotencyEvaluation(self::NEW, true, 201);
        if ($record->key() !== $key || $record->fingerprint() !== $fingerprint) return new IdempotencyEvaluation(self::CONFLICT, false, 409);
        return new IdempotencyEvaluation(self::REPLAY, false, 200, $record->originalResult());
    }
}
