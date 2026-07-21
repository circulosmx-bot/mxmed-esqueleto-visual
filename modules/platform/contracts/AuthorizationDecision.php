<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class AuthorizationDecision
{
    private function __construct(
        private bool $allowed,
        private string $reasonCode,
        private string $riskLevel,
        private array $evaluatedRequirements,
        private array $missingRequirements,
        private ?string $correlationId,
        private ?string $satisfiedRule
    ) {}

    /** @param list<string> $evaluatedRequirements */
    public static function allow(string $riskLevel, string $satisfiedRule, array $evaluatedRequirements = [], ?string $correlationId = null): self
    {
        RiskLevel::assertValid($riskLevel);
        if (trim($satisfiedRule) === '') throw new \InvalidArgumentException('allow_rule_required');
        return new self(true, ReasonCode::ALLOWED, $riskLevel, self::normalize($evaluatedRequirements), [], self::optional($correlationId), $satisfiedRule);
    }

    /** @param list<string> $evaluatedRequirements @param list<string> $missingRequirements */
    public static function deny(string $riskLevel, string $reasonCode, array $evaluatedRequirements = [], array $missingRequirements = [], ?string $correlationId = null): self
    {
        RiskLevel::assertValid($riskLevel);
        ReasonCode::assertValid($reasonCode);
        if ($reasonCode === ReasonCode::ALLOWED) throw new \InvalidArgumentException('deny_reason_required');
        return new self(false, $reasonCode, $riskLevel, self::normalize($evaluatedRequirements), self::normalize($missingRequirements), self::optional($correlationId), null);
    }

    public static function uninitialized(string $riskLevel = RiskLevel::R0): self
    {
        return self::deny($riskLevel, ReasonCode::DECISION_UNINITIALIZED);
    }

    public function allowed(): bool { return $this->allowed; }
    public function isAllowed(): bool { return $this->allowed; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function riskLevel(): string { return $this->riskLevel; }
    /** @return list<string> */
    public function evaluatedRequirements(): array { return $this->evaluatedRequirements; }
    /** @return list<string> */
    public function missingRequirements(): array { return $this->missingRequirements; }
    public function correlationId(): ?string { return $this->correlationId; }
    public function satisfiedRule(): ?string { return $this->satisfiedRule; }
    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['allowed' => $this->allowed, 'reason_code' => $this->reasonCode, 'risk_level' => $this->riskLevel, 'evaluated_requirements' => $this->evaluatedRequirements, 'missing_requirements' => $this->missingRequirements, 'correlation_id' => $this->correlationId, 'satisfied_rule' => $this->satisfiedRule];
    }

    /** @param list<string> $values @return list<string> */
    private static function normalize(array $values): array
    {
        $result = [];
        foreach ($values as $value) $result[] = (new SafeIdentifier($value))->value();
        $result = array_values(array_unique($result, SORT_STRING));
        sort($result, SORT_STRING);
        return $result;
    }
    private static function optional(?string $value): ?string { return $value === null ? null : (new SafeIdentifier($value))->value(); }
}
