<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class AuditEventReference
{
    private string $eventName;
    private string $riskLevel;
    private ?ActorReference $realActor;
    private ?ActorReference $effectiveActor;
    private ?SubjectReference $affectedSubject;
    private ?string $correlationId;
    private ?string $requestId;
    private string $result;
    /** @var array<string,string|int|bool|null> */
    private array $metadata;

    /** @param array<string,string|int|bool|null> $metadata */
    public function __construct(string $eventName, string $riskLevel, ?ActorReference $realActor, ?ActorReference $effectiveActor, ?SubjectReference $affectedSubject, ?string $correlationId, ?string $requestId, string $result, array $metadata = [])
    {
        $this->eventName = (new SafeIdentifier($eventName))->value();
        $this->riskLevel = RiskLevel::assertValid($riskLevel);
        $this->realActor = $realActor;
        $this->effectiveActor = $effectiveActor;
        $this->affectedSubject = $affectedSubject;
        $this->correlationId = self::optional($correlationId);
        $this->requestId = self::optional($requestId);
        $this->result = AuditWriteResult::assertValid($result);
        $clean = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key) || preg_match('/(password|cookie|secret|token|payload|clinical|client_secret)/i', $key) === 1) throw new \InvalidArgumentException('sensitive_audit_metadata_key');
            if (!is_scalar($value) && $value !== null) throw new \InvalidArgumentException('non_scalar_audit_metadata');
            $clean[(new SafeIdentifier($key))->value()] = $value;
        }
        ksort($clean, SORT_STRING);
        $this->metadata = $clean;
    }
    public function eventName(): string { return $this->eventName; }
    public function riskLevel(): string { return $this->riskLevel; }
    public function realActor(): ?ActorReference { return $this->realActor; }
    public function effectiveActor(): ?ActorReference { return $this->effectiveActor; }
    public function affectedSubject(): ?SubjectReference { return $this->affectedSubject; }
    public function correlationId(): ?string { return $this->correlationId; }
    public function requestId(): ?string { return $this->requestId; }
    public function result(): string { return $this->result; }
    /** @return array<string,string|int|bool|null> */
    public function metadata(): array { return $this->metadata; }
    private static function optional(?string $value): ?string { return $value === null ? null : (new SafeIdentifier($value))->value(); }
}
