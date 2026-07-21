<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class AppointmentLifecycleContract
{
    public const TENTATIVE = 'tentative';
    public const PENDING_OTP = 'pending_otp';
    public const PENDING = 'pending';
    public const SCHEDULED = 'scheduled';
    public const CONFIRMED = 'confirmed';
    public const CANCELED = 'canceled';
    public const NO_SHOW = 'no_show';

    private const STATES = [self::TENTATIVE, self::PENDING_OTP, self::PENDING, self::SCHEDULED, self::CONFIRMED, self::CANCELED, self::NO_SHOW];
    private const TRANSITIONS = [
        self::TENTATIVE => [self::CONFIRMED, self::CANCELED],
        self::PENDING_OTP => [self::CONFIRMED, self::CANCELED],
        self::PENDING => [self::CONFIRMED, self::CANCELED],
        self::SCHEDULED => [self::CONFIRMED, self::CANCELED],
        self::CONFIRMED => [self::TENTATIVE, self::CANCELED, self::NO_SHOW],
        self::CANCELED => [],
        self::NO_SHOW => [],
    ];

    public static function states(): array { return self::STATES; }
    public static function transition(string $from, string $to): AppointmentTransitionResult
    {
        self::assertState($from);
        self::assertState($to);
        $allowed = in_array($to, self::TRANSITIONS[$from], true);
        return new AppointmentTransitionResult($allowed, $allowed ? 'allowed' : 'invalid_transition', $from, $to);
    }
    public static function assertState(string $state): string
    {
        if (!in_array($state, self::STATES, true)) throw new \InvalidArgumentException('unknown appointment state');
        return $state;
    }
    public static function agendaAppointmentIsClinicalEncounter(): bool { return false; }
}

final class AppointmentTransitionResult
{
    public function __construct(private bool $allowed, private string $reason, private string $from, private string $to) {}
    public function allowed(): bool { return $this->allowed; }
    public function reason(): string { return $this->reason; }
    public function httpStatus(): int { return $this->allowed ? 200 : 409; }
    public function from(): string { return $this->from; }
    public function to(): string { return $this->to; }
    public function toArray(): array { return ['allowed' => $this->allowed, 'reason' => $this->reason, 'from' => $this->from, 'to' => $this->to, 'http_status' => $this->httpStatus()]; }
}

final class AgendaAppointmentReference
{
    public function __construct(private string $id) { if (trim($id) === '') throw new \InvalidArgumentException('appointment id required'); }
    public function id(): string { return $this->id; }
    public function entityType(): string { return 'agenda_appointment'; }
}

final class ClinicalEncounterReference
{
    public function __construct(private string $id) { if (trim($id) === '') throw new \InvalidArgumentException('encounter id required'); }
    public function id(): string { return $this->id; }
    public function entityType(): string { return 'clinical_encounter'; }
}
