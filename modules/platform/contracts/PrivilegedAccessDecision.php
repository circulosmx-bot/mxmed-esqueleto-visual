<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class PrivilegedAccessDecision
{
    private string $mode;
    private string $state;
    private bool $policySatisfied;
    private string $reasonCode;
    private string $riskLevel;
    /** @var list<string> */
    private array $requiredControls;
    /** @var list<string> */
    private array $blockers;
    private ScopeSet $scopes;
    private ?string $expiresAtUtc;
    private bool $visibilityRequired;
    private bool $postReviewRequired;
    private string $auditStatus;
    private string $correlationId;
    private ?string $satisfiedRule;

    /** @param list<string> $requiredControls @param list<string> $blockers */
    public function __construct(
        string $mode,
        string $state,
        bool $policySatisfied,
        bool $activatable,
        string $reasonCode,
        string $riskLevel,
        array $requiredControls,
        array $blockers,
        ScopeSet $scopes,
        ?string $expiresAtUtc,
        bool $visibilityRequired,
        bool $postReviewRequired,
        string $auditStatus,
        string $correlationId,
        ?string $satisfiedRule = null
    ) {
        $this->mode = PrivilegedAccessMode::assertValid($mode);
        $this->state = SupportAccessState::assertValid($state);
        $this->policySatisfied = $policySatisfied;
        if ($activatable) throw new \InvalidArgumentException('privileged_access_activation_forbidden');
        $this->reasonCode = PrivilegedAccessReason::assertValid($reasonCode);
        $this->riskLevel = RiskLevel::assertValid($riskLevel);
        $this->auditStatus = AuditWriteResult::assertValid($auditStatus);
        $this->correlationId = (new SafeIdentifier($correlationId))->value();
        $this->requiredControls = self::normalize($requiredControls);
        $this->blockers = self::normalize($blockers);
        $this->scopes = $scopes;
        $this->expiresAtUtc = $expiresAtUtc;
        $this->visibilityRequired = $visibilityRequired;
        $this->postReviewRequired = $postReviewRequired;
        $this->satisfiedRule = $satisfiedRule === null ? null : (new SafeIdentifier($satisfiedRule))->value();
    }
    public function mode(): string { return $this->mode; }
    public function state(): string { return $this->state; }
    public function policySatisfied(): bool { return $this->policySatisfied; }
    public function activatable(): bool { return false; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function riskLevel(): string { return $this->riskLevel; }
    /** @return list<string> */
    public function requiredControls(): array { return $this->requiredControls; }
    /** @return list<string> */
    public function blockers(): array { return $this->blockers; }
    public function scopes(): ScopeSet { return $this->scopes; }
    public function expiresAtUtc(): ?string { return $this->expiresAtUtc; }
    public function visibilityRequired(): bool { return $this->visibilityRequired; }
    public function postReviewRequired(): bool { return $this->postReviewRequired; }
    public function auditStatus(): string { return $this->auditStatus; }
    public function correlationId(): string { return $this->correlationId; }
    public function satisfiedRule(): ?string { return $this->satisfiedRule; }
    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['mode' => $this->mode, 'state' => $this->state, 'policy_satisfied' => $this->policySatisfied, 'activatable' => false, 'reason_code' => $this->reasonCode, 'risk_level' => $this->riskLevel, 'required_controls' => $this->requiredControls, 'blockers' => $this->blockers, 'scopes' => $this->scopes->values(), 'expires_at_utc' => $this->expiresAtUtc, 'visibility_required' => $this->visibilityRequired, 'post_review_required' => $this->postReviewRequired, 'audit_status' => $this->auditStatus, 'correlation_id' => $this->correlationId, 'satisfied_rule' => $this->satisfiedRule];
    }
    /** @param list<string> $values @return list<string> */
    private static function normalize(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) throw new \InvalidArgumentException('invalid_privileged_access_control');
            $normalized[] = $value;
        }
        $normalized = array_values(array_unique($normalized, SORT_STRING));
        sort($normalized, SORT_STRING);
        return $normalized;
    }
}
