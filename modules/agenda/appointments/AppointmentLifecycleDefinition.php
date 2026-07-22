<?php
declare(strict_types=1);

namespace Agenda\Appointments;

use Agenda\Contracts\AppointmentLifecycleContract;

final readonly class AppointmentLifecycleTransitionDecision
{
    public function __construct(
        private bool $allowed,
        private string $reason,
        private string $from,
        private string $to,
        private int $httpStatus
    ) {}

    public function allowed(): bool { return $this->allowed; }
    public function reason(): string { return $this->reason; }
    public function from(): string { return $this->from; }
    public function to(): string { return $this->to; }
    public function httpStatus(): int { return $this->httpStatus; }
    public function toArray(): array
    {
        return ['allowed' => $this->allowed, 'reason' => $this->reason, 'from' => $this->from, 'to' => $this->to, 'http_status' => $this->httpStatus];
    }
}

final readonly class AppointmentLifecycleDefinition
{
    public const LIFECYCLE_ID = 'pg03-appointment-lifecycle';
    public const VERSION = 1;

    private const REQUIRED_STATES = ['tentative', 'pending_otp', 'pending', 'scheduled', 'confirmed', 'canceled', 'no_show'];
    private const TERMINAL_STATES = ['canceled', 'no_show'];
    private const SLOT_OCCUPYING_STATES = ['tentative', 'pending_otp', 'pending', 'scheduled', 'confirmed'];

    private array $states;
    private array $matrix;

    public function __construct()
    {
        $states = AppointmentLifecycleContract::states();
        if ($states !== self::REQUIRED_STATES) {
            throw new AppointmentDomainException('lifecycle_version_mismatch', 'Gate 8A state catalog diverged');
        }
        $matrix = [];
        foreach ($states as $from) {
            $matrix[$from] = [];
            foreach ($states as $to) {
                $baseDecision = AppointmentLifecycleContract::transition($from, $to);
                if ($baseDecision->allowed()) $matrix[$from][] = $to;
            }
        }
        $this->states = $states;
        $this->matrix = $matrix;
    }

    public function lifecycleId(): string { return self::LIFECYCLE_ID; }
    public function version(): int { return self::VERSION; }
    public function states(): array { return $this->states; }
    public function terminalStates(): array { return self::TERMINAL_STATES; }
    public function slotOccupyingStates(): array { return self::SLOT_OCCUPYING_STATES; }
    public function matrix(): array { return $this->matrix; }
    public function clinicalEncounter(): bool { return AppointmentLifecycleContract::agendaAppointmentIsClinicalEncounter(); }
    public function isState(string $state): bool { return in_array($state, $this->states, true); }
    public function isTerminal(string $state): bool { return in_array($state, self::TERMINAL_STATES, true); }
    public function occupiesSlot(string $state): bool { return in_array($state, self::SLOT_OCCUPYING_STATES, true); }

    public function evaluate(string $from, string $to): AppointmentLifecycleTransitionDecision
    {
        if (!$this->isState($from) || !$this->isState($to)) {
            return new AppointmentLifecycleTransitionDecision(false, 'unknown_appointment_state', $from, $to, 409);
        }
        $baseDecision = AppointmentLifecycleContract::transition($from, $to);
        return new AppointmentLifecycleTransitionDecision(
            $baseDecision->allowed(),
            $baseDecision->allowed() ? 'allowed' : 'invalid_transition',
            $from,
            $to,
            $baseDecision->httpStatus()
        );
    }

    public function toArray(): array
    {
        return [
            'lifecycle_id' => self::LIFECYCLE_ID,
            'version' => self::VERSION,
            'states' => $this->states,
            'terminal_states' => self::TERMINAL_STATES,
            'slot_occupying_states' => self::SLOT_OCCUPYING_STATES,
            'matrix' => $this->matrix,
            'clinical_encounter' => $this->clinicalEncounter(),
        ];
    }
}
