<?php
declare(strict_types=1);

namespace Subscriptions\Contracts;

final class ExistingCapabilityDecision
{
    private string $capabilityId;
    private bool $available;
    private string $reasonCode;
    private string $source;
    private ?string $planCode;
    private string $operationalState;

    public function __construct(
        string $capabilityId,
        bool $available,
        string $reasonCode,
        string $source,
        ?string $planCode,
        string $operationalState
    ) {
        $this->capabilityId = trim($capabilityId);
        $this->available = $available;
        $this->reasonCode = trim($reasonCode);
        $this->source = trim($source);
        $this->planCode = $planCode !== null ? trim($planCode) : null;
        $this->operationalState = trim($operationalState);
    }

    public function capabilityId(): string
    {
        return $this->capabilityId;
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function planCode(): ?string
    {
        return $this->planCode;
    }

    public function operationalState(): string
    {
        return $this->operationalState;
    }

    /**
     * Internal contract representation. Reason codes are intentionally kept
     * out of the public read-model by using publicArray() there.
     */
    public function toArray(bool $includeReasonCode = true): array
    {
        $result = [
            'capability_id' => $this->capabilityId,
            'available' => $this->available,
            'source' => $this->source,
            'plan_code' => $this->planCode,
            'operational_state' => $this->operationalState,
        ];

        if ($includeReasonCode) {
            $result['reason_code'] = $this->reasonCode;
        }

        return $result;
    }

    public function publicArray(): array
    {
        return $this->toArray(false);
    }
}
