<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class DispositionPlan
{
    private function __construct(private bool $allowedForSimulation, private bool $executable, private string $reasonCode, private string $riskLevel, private array $requiredSteps, private array $blockers, private bool $reconciliationRequired, private bool $rollbackRequired, private bool $auditRequired, private string $idempotencyReference, private ?string $targetState, private string $dispositionMode) {}
    /** @param list<string> $requiredSteps @param list<string> $blockers */
    public static function allowSimulation(DispositionRequest $request, array $requiredSteps): self
    {
        return new self(true, false, DispositionReason::ALLOWED_FOR_SIMULATION, $request->riskLevel(), $requiredSteps, [], $request->reconciliationRequired(), $request->rollbackRequired(), $request->auditRequired(), $request->idempotencyKey(), $request->requestedTargetState(), $request->action());
    }
    /** @param list<string> $blockers */
    public static function deny(DispositionRequest $request, string $reason, array $blockers): self
    {
        return new self(false, false, DispositionReason::assertValid($reason), $request->riskLevel(), [], $blockers, $request->reconciliationRequired(), $request->rollbackRequired(), $request->auditRequired(), $request->idempotencyKey(), $request->requestedTargetState(), $request->action());
    }
    public function allowedForSimulation(): bool { return $this->allowedForSimulation; }
    public function executable(): bool { return $this->executable; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function riskLevel(): string { return $this->riskLevel; }
    public function requiredSteps(): array { return $this->requiredSteps; }
    public function blockers(): array { return $this->blockers; }
    public function reconciliationRequired(): bool { return $this->reconciliationRequired; }
    public function rollbackRequired(): bool { return $this->rollbackRequired; }
    public function auditRequired(): bool { return $this->auditRequired; }
    public function idempotencyReference(): string { return $this->idempotencyReference; }
    public function targetState(): ?string { return $this->targetState; }
    public function dispositionMode(): string { return $this->dispositionMode; }
    /** @return array<string,mixed> */
    public function toArray(): array { return ['allowed_for_simulation' => $this->allowedForSimulation, 'executable' => $this->executable, 'reason_code' => $this->reasonCode, 'risk_level' => $this->riskLevel, 'required_steps' => $this->requiredSteps, 'blockers' => $this->blockers, 'reconciliation_required' => $this->reconciliationRequired, 'rollback_required' => $this->rollbackRequired, 'audit_required' => $this->auditRequired, 'idempotency_reference' => $this->idempotencyReference, 'target_state' => $this->targetState, 'disposition_mode' => $this->dispositionMode]; }
}
