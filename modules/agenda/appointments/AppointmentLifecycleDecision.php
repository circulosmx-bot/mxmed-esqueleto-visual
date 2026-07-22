<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final readonly class AppointmentLifecycleDecision
{
    private function __construct(
        private bool $allowed,
        private string $reason,
        private int $httpStatus,
        private string $idempotencyStatus,
        private bool $replay,
        private bool $mutationEffective,
        private ?int $aggregateVersionResult,
        private ?string $resultDigest,
        private ?AppointmentSnapshot $nextSnapshot,
        private ?AppointmentLifecycleEvent $event,
        private ?AppointmentSlotClaim $slotClaim,
        private ?AppointmentMutationPlan $mutationPlan,
        private ?AppointmentIdempotencyRecord $idempotencyRecord,
        private ?AppointmentConcurrencyDecision $concurrencyDecision
    ) {}

    public static function failure(
        string $reason,
        string $idempotencyStatus = AppointmentIdempotencyDecision::NEW_OPERATION,
        ?AppointmentConcurrencyDecision $concurrencyDecision = null
    ): self {
        return new self(false, $reason, 409, $idempotencyStatus, false, false, null, null, null, null, null, null, null, $concurrencyDecision);
    }

    public static function replay(AppointmentIdempotencyRecord $record): self
    {
        return new self(
            true,
            AppointmentIdempotencyDecision::REPLAY,
            $record->originalHttpStatus(),
            AppointmentIdempotencyDecision::REPLAY,
            true,
            false,
            $record->aggregateVersionResult(),
            $record->resultDigest(),
            null,
            null,
            null,
            null,
            $record,
            null
        );
    }

    public static function success(
        AppointmentSnapshot $nextSnapshot,
        AppointmentLifecycleEvent $event,
        AppointmentSlotClaim $slotClaim,
        AppointmentMutationPlan $mutationPlan,
        AppointmentIdempotencyRecord $idempotencyRecord,
        AppointmentConcurrencyDecision $concurrencyDecision
    ): self {
        return new self(
            true,
            'transition_applied',
            200,
            AppointmentIdempotencyDecision::NEW_OPERATION,
            false,
            true,
            $nextSnapshot->aggregateVersion(),
            $idempotencyRecord->resultDigest(),
            $nextSnapshot,
            $event,
            $slotClaim,
            $mutationPlan,
            $idempotencyRecord,
            $concurrencyDecision
        );
    }

    public function allowed(): bool { return $this->allowed; }
    public function reason(): string { return $this->reason; }
    public function httpStatus(): int { return $this->httpStatus; }
    public function idempotencyStatus(): string { return $this->idempotencyStatus; }
    public function replayed(): bool { return $this->replay; }
    public function mutationEffective(): bool { return $this->mutationEffective; }
    public function aggregateVersionResult(): ?int { return $this->aggregateVersionResult; }
    public function resultDigest(): ?string { return $this->resultDigest; }
    public function nextSnapshot(): ?AppointmentSnapshot { return $this->nextSnapshot; }
    public function event(): ?AppointmentLifecycleEvent { return $this->event; }
    public function slotClaim(): ?AppointmentSlotClaim { return $this->slotClaim; }
    public function mutationPlan(): ?AppointmentMutationPlan { return $this->mutationPlan; }
    public function idempotencyRecord(): ?AppointmentIdempotencyRecord { return $this->idempotencyRecord; }
    public function concurrencyDecision(): ?AppointmentConcurrencyDecision { return $this->concurrencyDecision; }
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'http_status' => $this->httpStatus,
            'idempotency_status' => $this->idempotencyStatus,
            'replay' => $this->replay,
            'mutation_effective' => $this->mutationEffective,
            'aggregate_version_result' => $this->aggregateVersionResult,
            'result_digest' => $this->resultDigest,
            'next_snapshot' => $this->nextSnapshot?->toArray(),
            'event' => $this->event?->toArray(),
            'slot_claim' => $this->slotClaim?->toArray(),
            'mutation_plan' => $this->mutationPlan?->toArray(),
            'idempotency_record' => $this->idempotencyRecord?->toArray(),
            'concurrency' => $this->concurrencyDecision?->toArray(),
        ];
    }
}
