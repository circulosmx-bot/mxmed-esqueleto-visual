<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class ReadOperationDecision
{
    private function __construct(private bool $allowed, private string $reasonCode, private string $operation) {}
    public static function allowRead(ReadOperationContract $contract): self { return new self(true, CanonicalSourceReason::ALLOWED, $contract->operation()); }
    public static function denied(string $operation, string $reasonCode): self { return new self(false, CanonicalSourceReason::assertValid($reasonCode), (new SafeIdentifier($operation))->value()); }
    public function allowed(): bool { return $this->allowed; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function operation(): string { return $this->operation; }
}
