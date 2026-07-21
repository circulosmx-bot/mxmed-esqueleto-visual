<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class AuditEventContract
{
    private const FORBIDDEN_KEYS = ['otp', 'token', 'secret', 'password', 'payload'];
    public function __construct(private ActorReference $realActor, private ActorReference $effectiveActor, private string $subject, private string $action, private string $reason, private string $correlationId, private string $requestId, private string $previousState, private string $nextState, private string $result, private array $metadata = [])
    {
        foreach ([$subject, $action, $reason, $correlationId, $requestId, $previousState, $nextState, $result] as $value) if (trim($value) === '') throw new \InvalidArgumentException('audit event field required');
        foreach (array_keys($metadata) as $key) if (in_array(strtolower((string)$key), self::FORBIDDEN_KEYS, true)) throw new \InvalidArgumentException('sensitive audit metadata rejected');
    }
    public function appendOnly(): bool { return true; }
    public function realActor(): ActorReference { return $this->realActor; }
    public function effectiveActor(): ActorReference { return $this->effectiveActor; }
    public function toArray(): array { return ['real_actor' => $this->realActor->toArray(), 'effective_actor' => $this->effectiveActor->toArray(), 'subject' => $this->subject, 'action' => $this->action, 'reason' => $this->reason, 'correlation_id' => $this->correlationId, 'request_id' => $this->requestId, 'previous_state' => $this->previousState, 'next_state' => $this->nextState, 'result' => $this->result, 'metadata' => $this->metadata]; }
}
