<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\PrivilegedAccessReason;
use Platform\Contracts\SupportAccessState;

/** Pure theoretical lifecycle planner; it never persists or executes a transition. */
final class SupportAccessLifecyclePlanner
{
    /** @return array<string,mixed> */
    public function plan(string $fromState, string $toState): array
    {
        try { SupportAccessState::assertValid($fromState); SupportAccessState::assertValid($toState); } catch (\Throwable) {
            return $this->result($fromState, $toState, false, PrivilegedAccessReason::INVALID_STATE);
        }
        if ($fromState === SupportAccessState::CLOSED) return $this->result($fromState, $toState, false, PrivilegedAccessReason::INVALID_STATE);
        if ($fromState === SupportAccessState::REQUESTED && $toState === SupportAccessState::ACTIVE) return $this->result($fromState, $toState, false, PrivilegedAccessReason::INVALID_STATE);
        if (in_array($fromState, [SupportAccessState::EXPIRED, SupportAccessState::REVOKED], true) && $toState === SupportAccessState::ACTIVE) return $this->result($fromState, $toState, false, PrivilegedAccessReason::INVALID_STATE);
        $allowed = [
            SupportAccessState::REQUESTED => [SupportAccessState::PENDING_APPROVAL, SupportAccessState::DENIED],
            SupportAccessState::PENDING_APPROVAL => [SupportAccessState::APPROVED, SupportAccessState::DENIED, SupportAccessState::EXPIRED, SupportAccessState::REVOKED],
            SupportAccessState::APPROVED => [SupportAccessState::EXPIRED, SupportAccessState::REVOKED, SupportAccessState::ACTIVE],
            SupportAccessState::ACTIVE => [SupportAccessState::EXPIRED, SupportAccessState::REVOKED, SupportAccessState::UNDER_REVIEW],
            SupportAccessState::EXPIRED => [SupportAccessState::UNDER_REVIEW],
            SupportAccessState::REVOKED => [SupportAccessState::UNDER_REVIEW],
            SupportAccessState::UNDER_REVIEW => [SupportAccessState::CLOSED],
            SupportAccessState::DENIED => [SupportAccessState::CLOSED],
        ];
        return $this->result($fromState, $toState, in_array($toState, $allowed[$fromState] ?? [], true), in_array($toState, $allowed[$fromState] ?? [], true) ? PrivilegedAccessReason::RUNTIME_ACTIVATION_DISABLED : PrivilegedAccessReason::INVALID_STATE);
    }

    /** @return array<string,mixed> */
    public function transition(string $fromState, string $toState): array { return $this->plan($fromState, $toState); }

    /** @return array<string,mixed> */
    private function result(string $from, string $to, bool $allowed, string $reason): array
    {
        return ['allowed' => $allowed, 'from_state' => $from, 'to_state' => $to, 'transition_real' => false, 'executable' => false, 'reason_code' => $reason];
    }
}
