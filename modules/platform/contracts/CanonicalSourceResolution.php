<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class CanonicalSourceResolution
{
    private function __construct(private bool $allowed, private string $reasonCode, private string $operation, private ?CanonicalSourceRecord $source) {}
    public static function allowResolution(string $operation, CanonicalSourceRecord $source): self { return new self(true, CanonicalSourceReason::ALLOWED, (new SafeIdentifier($operation))->value(), $source); }
    public static function denied(string $operation, string $reasonCode): self { return new self(false, CanonicalSourceReason::assertValid($reasonCode), (new SafeIdentifier($operation))->value(), null); }
    public function allowed(): bool { return $this->allowed; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function operation(): string { return $this->operation; }
    public function source(): ?CanonicalSourceRecord { return $this->source; }
    /** @return array<string,mixed> */
    public function toArray(): array { return ['allowed' => $this->allowed, 'reason_code' => $this->reasonCode, 'operation' => $this->operation, 'source' => $this->source === null ? null : ['domain' => $this->source->domain(), 'entity' => $this->source->entity(), 'classification' => $this->source->classification(), 'reader_authority' => $this->source->readerAuthority(), 'writer_authority' => $this->source->writerAuthority(), 'source_reference' => $this->source->sourceReference(), 'migration_status' => $this->source->migrationStatus(), 'reconciliation_required' => $this->source->reconciliationRequired(), 'rollback_required' => $this->source->rollbackRequired(), 'status' => $this->source->status()]]; }
}
