<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class CanonicalSourceRecord
{
    private string $domain;
    private string $entity;
    private string $classification;
    private ?string $writerAuthority;
    private ?string $readerAuthority;
    private string $sourceReference;
    private string $migrationStatus;
    private bool $reconciliationRequired;
    private bool $rollbackRequired;
    private string $status;

    public function __construct(
        string $domain,
        string $entity,
        string $classification,
        ?string $writerAuthority,
        ?string $readerAuthority,
        string $sourceReference,
        string $migrationStatus,
        bool $reconciliationRequired,
        bool $rollbackRequired,
        string $status = 'active'
    ) {
        $this->domain = (new SafeIdentifier($domain))->value();
        $this->entity = (new SafeIdentifier($entity))->value();
        $this->classification = SourceClassification::assertValid($classification);
        $this->writerAuthority = $writerAuthority === null ? null : (new SafeIdentifier($writerAuthority))->value();
        $this->readerAuthority = $readerAuthority === null ? null : (new SafeIdentifier($readerAuthority))->value();
        $this->sourceReference = (new SafeIdentifier($sourceReference))->value();
        $this->migrationStatus = (new SafeIdentifier($migrationStatus))->value();
        $this->reconciliationRequired = $reconciliationRequired;
        $this->rollbackRequired = $rollbackRequired;
        $this->status = (new SafeIdentifier($status))->value();
        if ($this->classification === SourceClassification::CANONICAL_WRITE && $this->writerAuthority === null) throw new \InvalidArgumentException('canonical_writer_required');
        if ($this->classification !== SourceClassification::CANONICAL_WRITE && $this->writerAuthority !== null) throw new \InvalidArgumentException('noncanonical_writer_forbidden');
        if ($this->classification === SourceClassification::UNRESOLVED && $this->status === 'active') throw new \InvalidArgumentException('unresolved_source_not_selectable');
        if ($this->classification === SourceClassification::CANONICAL_WRITE && (!$reconciliationRequired || !$rollbackRequired)) throw new \InvalidArgumentException('canonical_reconciliation_and_rollback_required');
    }

    public function domain(): string { return $this->domain; }
    public function entity(): string { return $this->entity; }
    public function classification(): string { return $this->classification; }
    public function writerAuthority(): ?string { return $this->writerAuthority; }
    public function readerAuthority(): ?string { return $this->readerAuthority; }
    public function sourceReference(): string { return $this->sourceReference; }
    public function migrationStatus(): string { return $this->migrationStatus; }
    public function reconciliationRequired(): bool { return $this->reconciliationRequired; }
    public function rollbackRequired(): bool { return $this->rollbackRequired; }
    public function status(): string { return $this->status; }
    public function canAuthorizeWrites(): bool { return $this->classification === SourceClassification::CANONICAL_WRITE && $this->status === 'active'; }
    public function key(): string { return $this->domain . ':' . $this->entity; }
}
