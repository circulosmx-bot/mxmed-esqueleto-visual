<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class RetentionPolicy
{
    private string $dataDomain;
    private string $dataClass;
    private string $purpose;
    private string $retentionTrigger;
    private ?int $retentionPeriodSeconds;
    private bool $retentionUnresolved;
    private string $currentState;
    private string $archiveState;
    private string $dispositionMethod;
    private bool $legalHold;
    private string $approvalLevel;
    private string $policyOwner;
    private string $implementationStatus;

    public function __construct(string $dataDomain, string $dataClass, string $purpose, string $retentionTrigger, ?int $retentionPeriodSeconds, bool $retentionUnresolved, string $currentState, string $archiveState, string $dispositionMethod, bool $legalHold, string $approvalLevel, string $policyOwner, string $implementationStatus)
    {
        $this->dataDomain = (new SafeIdentifier($dataDomain))->value();
        $this->dataClass = (new SafeIdentifier($dataClass))->value();
        $this->purpose = self::text($purpose, 'purpose');
        $this->retentionTrigger = self::text($retentionTrigger, 'retention_trigger');
        if ($retentionUnresolved === ($retentionPeriodSeconds !== null)) throw new \InvalidArgumentException('retention_period_or_unresolved_required');
        if ($retentionPeriodSeconds !== null && $retentionPeriodSeconds < 1) throw new \InvalidArgumentException('invalid_retention_period');
        $this->retentionPeriodSeconds = $retentionPeriodSeconds;
        $this->retentionUnresolved = $retentionUnresolved;
        $this->currentState = RetentionState::assertValid($currentState);
        $this->archiveState = RetentionState::assertValid($archiveState);
        $this->dispositionMethod = self::text($dispositionMethod, 'disposition_method');
        $this->legalHold = $legalHold;
        $this->approvalLevel = (new SafeIdentifier($approvalLevel))->value();
        $this->policyOwner = (new SafeIdentifier($policyOwner))->value();
        $this->implementationStatus = (new SafeIdentifier($implementationStatus))->value();
    }
    public function dataDomain(): string { return $this->dataDomain; }
    public function dataClass(): string { return $this->dataClass; }
    public function purpose(): string { return $this->purpose; }
    public function retentionTrigger(): string { return $this->retentionTrigger; }
    public function retentionPeriodSeconds(): ?int { return $this->retentionPeriodSeconds; }
    public function retentionUnresolved(): bool { return $this->retentionUnresolved; }
    public function currentState(): string { return $this->currentState; }
    public function archiveState(): string { return $this->archiveState; }
    public function dispositionMethod(): string { return $this->dispositionMethod; }
    public function legalHold(): bool { return $this->legalHold; }
    public function approvalLevel(): string { return $this->approvalLevel; }
    public function policyOwner(): string { return $this->policyOwner; }
    public function implementationStatus(): string { return $this->implementationStatus; }
    public function automaticDeletionAllowed(): bool
    {
        return !$this->retentionUnresolved && !$this->legalHold && $this->currentState === RetentionState::ARCHIVED && $this->implementationStatus === 'approved';
    }
    public function allowsTransition(string $targetState): bool
    {
        $targetState = RetentionState::assertValid($targetState);
        if ($this->legalHold && in_array($targetState, [RetentionState::ANONYMIZED, RetentionState::DELETED], true)) return false;
        if (in_array($this->currentState, [RetentionState::ACTIVE, RetentionState::INACTIVE], true) && $targetState === RetentionState::DELETED) return false;
        return true;
    }
    private static function text(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 256 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) throw new \InvalidArgumentException('invalid_' . $field);
        return $value;
    }
}
