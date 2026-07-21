<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class DispositionRequest
{
    private string $requestId;
    private string $idempotencyKey;
    private string $domain;
    private string $dataClass;
    private string $purpose;
    private string $policyReference;
    private string $sourceReference;
    private string $requestedBy;
    private string $riskLevel;
    private string $action;
    private SubjectReference $subject;
    private ?string $expectedCurrentState;
    private ?string $requestedTargetState;
    private ?string $caseReference;
    private ?int $expirationSeconds;

    public function __construct(
        string $requestId,
        string $idempotencyKey,
        string $domain,
        string $dataClass,
        SubjectReference $subject,
        string $action,
        string $purpose,
        string $requestedBy,
        string $riskLevel,
        string $policyReference,
        string $sourceReference,
        ?string $expectedCurrentState,
        ?string $requestedTargetState,
        private bool $simulationOnly,
        ?string $caseReference,
        private ApprovalReferenceSet $approvalReferences,
        private bool $legalHoldKnown,
        private bool $auditRequired,
        private bool $reconciliationRequired,
        private bool $rollbackRequired,
        ?int $expirationSeconds
    ) {
        $this->requestId = (new SafeIdentifier($requestId))->value();
        $this->idempotencyKey = (new SafeIdentifier($idempotencyKey))->value();
        $this->domain = (new SafeIdentifier($domain))->value();
        $this->dataClass = (new SafeIdentifier($dataClass))->value();
        $this->subject = $subject;
        $this->action = DispositionAction::assertValid($action);
        $this->purpose = self::text($purpose, 'purpose');
        $this->requestedBy = (new SafeIdentifier($requestedBy))->value();
        $this->riskLevel = RiskLevel::assertValid($riskLevel);
        $this->policyReference = (new SafeIdentifier($policyReference))->value();
        $this->sourceReference = (new SafeIdentifier($sourceReference))->value();
        $this->expectedCurrentState = $expectedCurrentState === null ? null : RetentionState::assertValid($expectedCurrentState);
        $this->requestedTargetState = $requestedTargetState === null ? null : RetentionState::assertValid($requestedTargetState);
        $this->caseReference = $caseReference === null ? null : (new SafeIdentifier($caseReference))->value();
        if ($expirationSeconds !== null && $expirationSeconds < 1) throw new \InvalidArgumentException('invalid_disposition_expiration');
        $this->expirationSeconds = $expirationSeconds;
    }
    public function requestId(): string { return $this->requestId; }
    public function idempotencyKey(): string { return $this->idempotencyKey; }
    public function domain(): string { return $this->domain; }
    public function dataClass(): string { return $this->dataClass; }
    public function subject(): SubjectReference { return $this->subject; }
    public function action(): string { return $this->action; }
    public function purpose(): string { return $this->purpose; }
    public function requestedBy(): string { return $this->requestedBy; }
    public function riskLevel(): string { return $this->riskLevel; }
    public function policyReference(): string { return $this->policyReference; }
    public function sourceReference(): string { return $this->sourceReference; }
    public function expectedCurrentState(): ?string { return $this->expectedCurrentState; }
    public function requestedTargetState(): ?string { return $this->requestedTargetState; }
    public function simulationOnly(): bool { return $this->simulationOnly; }
    public function caseReference(): ?string { return $this->caseReference; }
    public function approvalReferences(): ApprovalReferenceSet { return $this->approvalReferences; }
    public function legalHoldKnown(): bool { return $this->legalHoldKnown; }
    public function auditRequired(): bool { return $this->auditRequired; }
    public function reconciliationRequired(): bool { return $this->reconciliationRequired; }
    public function rollbackRequired(): bool { return $this->rollbackRequired; }
    public function expirationSeconds(): ?int { return $this->expirationSeconds; }
    private static function text(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 256 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) throw new \InvalidArgumentException('invalid_' . $field);
        return $value;
    }
}
