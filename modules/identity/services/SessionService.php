<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Adapters\SessionStoreUnavailableException;
use Identity\Contracts\AccountStatus;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\AuthenticationPrincipalCandidate;
use Identity\Contracts\Clock;
use Identity\Contracts\ReasonCode;
use Identity\Contracts\SessionCookieDescriptor;
use Identity\Contracts\SessionCreationDecision;
use Identity\Contracts\SessionId;
use Identity\Contracts\SessionPolicy;
use Identity\Contracts\SessionPrincipal;
use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionRevocationDecision;
use Identity\Contracts\SessionRotationDecision;
use Identity\Contracts\SessionState;
use Identity\Contracts\SessionStorePort;
use Identity\Contracts\SessionToken;
use Identity\Contracts\SessionValidationDecision;

final class SessionService
{
    public function __construct(private \Identity\Contracts\SessionStorePort $store, private SessionTokenCodec $tokens, private Clock $clock, private SessionPolicy $policy = new SessionPolicy(), private ?\Identity\Contracts\SessionAccountStatePort $accounts = null) {}

    public function create(AuthenticationPrincipalCandidate $candidate, array $metadata = []): SessionCreationDecision
    {
        if ($candidate->accountStatus() !== AccountStatus::ACTIVE) return new SessionCreationDecision(false, ReasonCode::ACCOUNT_NOT_ACTIVE);
        $health = $this->store->healthCheck();
        if (!$health->healthy()) return new SessionCreationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE);
        $now = $this->clock->now();
        $token = $this->tokens->issue();
        $digest = $this->tokens->digest($token);
        $absolute = $now->modify('+' . $this->policy->absoluteTtlSeconds() . ' seconds');
        $expires = $now->modify('+' . $this->policy->idleTtlSeconds() . ' seconds');
        if ($expires > $absolute) $expires = $absolute;
        $record = new SessionRecord(SessionId::generate(), $digest, SessionPrincipal::fromCandidate($candidate), $now, $now, $expires, $absolute, SessionState::ACTIVE, $this->label($metadata['device_label'] ?? ''), $this->dimensionHash($metadata['user_agent'] ?? $metadata['user_agent_hash'] ?? ''), $this->dimensionHash($metadata['ip'] ?? $metadata['ip_dimension_hash'] ?? ''));
        try { $this->store->create($record, $this->policy->maximumActiveSessions()); } catch (\Throwable) { return new SessionCreationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); }
        return new SessionCreationDecision(true, ReasonCode::ALLOWED, $record, $token, SessionCookieDescriptor::forToken($token, $this->policy->idleTtlSeconds()));
    }

    public function validate(?string $rawToken): SessionValidationDecision
    {
        if ($rawToken === null || $rawToken === '') return new SessionValidationDecision(false, ReasonCode::SESSION_MISSING);
        try { $digest = $this->tokens->digest($rawToken); $health = $this->store->healthCheck(); if (!$health->healthy()) return new SessionValidationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); $record = $this->store->read($digest); } catch (\Throwable) { return new SessionValidationDecision(false, ReasonCode::SESSION_INVALID); }
        if ($record === null) return new SessionValidationDecision(false, ReasonCode::SESSION_INVALID);
        $now = $this->clock->now();
        if ($record->state() !== SessionState::ACTIVE) return new SessionValidationDecision(false, $record->state() === SessionState::SUPERSEDED ? ReasonCode::SESSION_SUPERSEDED : ReasonCode::SESSION_REVOKED);
        if ($now >= $record->absoluteExpiresAt()) { $this->safeRevoke($digest, SessionState::ABSOLUTE_EXPIRED); return new SessionValidationDecision(false, ReasonCode::SESSION_ABSOLUTE_EXPIRED); }
        if ($now >= $record->expiresAt()) { $this->safeRevoke($digest, SessionState::IDLE_EXPIRED); return new SessionValidationDecision(false, ReasonCode::SESSION_IDLE_EXPIRED); }
        if ($record->principal()->accountStatus() !== AccountStatus::ACTIVE) return new SessionValidationDecision(false, ReasonCode::ACCOUNT_NOT_ACTIVE);
        if ($this->accounts !== null) { $state = $this->accounts->current($record->principal()->accountId()); if (!is_array($state) || (string)($state['status'] ?? '') !== AccountStatus::ACTIVE) return new SessionValidationDecision(false, ReasonCode::ACCOUNT_NOT_ACTIVE); if ((int)($state['credential_version'] ?? 0) !== $record->principal()->credentialVersion()) { $this->safeRevoke($digest, ReasonCode::CREDENTIAL_VERSION_MISMATCH); return new SessionValidationDecision(false, ReasonCode::CREDENTIAL_VERSION_MISMATCH); } }
        if ($now->getTimestamp() - $record->lastSeenAt()->getTimestamp() >= $this->policy->touchIntervalSeconds()) { try { $record = $this->store->touch($digest, $now, $this->policy) ?? $record; } catch (\Throwable) { return new SessionValidationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); } }
        return new SessionValidationDecision(true, ReasonCode::ALLOWED, $record->principal(), $record, new AuthenticatedAccessContext($record->principal(), $record));
    }

    public function rotate(string $rawToken): SessionRotationDecision
    {
        $validated = $this->validate($rawToken); if (!$validated->allowed() || $validated->record() === null) return new SessionRotationDecision(false, $validated->reasonCode());
        $old = $validated->record(); $token = $this->tokens->issue(); $now = $this->clock->now(); $expires = $old->expiresAt(); $record = new SessionRecord(\Identity\Contracts\SessionId::generate(), $this->tokens->digest($token), $old->principal(), $old->createdAt(), $now, $expires, $old->absoluteExpiresAt(), SessionState::ACTIVE, $old->deviceLabel(), $old->userAgentHash(), $old->ipDimensionHash());
        try { if (!$this->store->rotate($old->tokenDigest(), $record)) return new SessionRotationDecision(false, ReasonCode::SESSION_INVALID); } catch (\Throwable) { return new SessionRotationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); }
        return new SessionRotationDecision(true, ReasonCode::ALLOWED, $record, $token, SessionCookieDescriptor::forToken($token, max(1, $expires->getTimestamp() - $now->getTimestamp())));
    }

    public function logout(?string $rawToken): SessionRevocationDecision
    {
        if ($rawToken === null || $rawToken === '') return new SessionRevocationDecision(true, ReasonCode::ALLOWED, SessionCookieDescriptor::deletion(), 0);
        try { $digest = $this->tokens->digest($rawToken); $this->store->revoke($digest, 'logged_out', $this->clock->now()); return new SessionRevocationDecision(true, ReasonCode::ALLOWED, SessionCookieDescriptor::deletion(), 1); } catch (\Throwable) { return new SessionRevocationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); }
    }
    public function revokeAll(string $accountId): SessionRevocationDecision { try { $count = $this->store->revokeAllForAccount($accountId, ReasonCode::SESSION_REVOKED, $this->clock->now()); return new SessionRevocationDecision(true, ReasonCode::ALLOWED, null, $count); } catch (\Throwable) { return new SessionRevocationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); } }
    private function safeRevoke(\Identity\Contracts\SessionTokenDigest $digest, string $reason): void { try { $this->store->revoke($digest, $reason, $this->clock->now()); } catch (\Throwable) {} }
    private function label(mixed $value): string { $label = trim((string)$value); $label = preg_replace('/[^A-Za-z0-9 ._-]/', '', $label) ?? ''; return substr($label, 0, 80); }
    private function dimensionHash(mixed $value): string { $value = trim((string)$value); return $value === '' ? '' : hash('sha256', $value); }
}
