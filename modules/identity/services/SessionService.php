<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Adapters\SessionStoreUnavailableException;
use Identity\Contracts\AccountStatus;
use Identity\Contracts\AtomicSessionStorePort;
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
    private ?SessionRecord $lastSupersededSession = null;

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
        $this->lastSupersededSession = null;
        try {
            if ($this->store instanceof AtomicSessionStorePort) $this->lastSupersededSession = $this->store->createAuthoritatively($record, $this->policy->maximumActiveSessions());
            else $this->store->create($record, $this->policy->maximumActiveSessions());
        } catch (\Throwable) { return new SessionCreationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); }
        return new SessionCreationDecision(true, ReasonCode::ALLOWED, $record, $token, SessionCookieDescriptor::forToken($token));
    }

    public function validate(?string $rawToken): SessionValidationDecision
    {
        if ($rawToken === null || $rawToken === '') return new SessionValidationDecision(false, ReasonCode::SESSION_MISSING);
        try { $token = new SessionToken($rawToken); $digest = $this->tokens->digest($token); }
        catch (\Throwable) { return new SessionValidationDecision(false, ReasonCode::SESSION_INVALID); }
        try { $health = $this->store->healthCheck(); if (!$health->healthy()) return new SessionValidationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); $record = $this->store->read($digest); }
        catch (SessionStoreUnavailableException) { return new SessionValidationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); }
        catch (\Throwable) { return new SessionValidationDecision(false, ReasonCode::SESSION_INVALID); }
        if ($record === null) return new SessionValidationDecision(false, ReasonCode::SESSION_INVALID);
        $now = $this->clock->now();
        if ($record->state() !== SessionState::ACTIVE) return new SessionValidationDecision(false, $this->terminalReason($record->state()));
        if ($now >= $record->absoluteExpiresAt()) { $this->safeRevoke($digest, SessionState::ABSOLUTE_EXPIRED); return new SessionValidationDecision(false, ReasonCode::SESSION_ABSOLUTE_EXPIRED); }
        if ($now >= $record->expiresAt()) { $this->safeRevoke($digest, SessionState::IDLE_EXPIRED); return new SessionValidationDecision(false, ReasonCode::SESSION_IDLE_EXPIRED); }
        if ($record->principal()->accountStatus() !== AccountStatus::ACTIVE) return new SessionValidationDecision(false, $this->accountReason($record->principal()->accountStatus()));
        if ($this->accounts !== null) {
            try { $state = $this->accounts->current($record->principal()->accountId()); }
            catch (\Throwable) { return new SessionValidationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); }
            if (!is_array($state)) { $this->safeRevoke($digest, ReasonCode::SESSION_REVOKED); return new SessionValidationDecision(false, ReasonCode::SESSION_REVOKED); }
            $status = (string)($state['status'] ?? '');
            if ($status !== AccountStatus::ACTIVE) { $reason = $this->accountReason($status); $this->safeRevoke($digest, $reason); return new SessionValidationDecision(false, $reason); }
            if ((int)($state['credential_version'] ?? 0) !== $record->principal()->credentialVersion()) { $this->safeRevoke($digest, ReasonCode::CREDENTIAL_VERSION_MISMATCH); return new SessionValidationDecision(false, ReasonCode::CREDENTIAL_VERSION_MISMATCH); }
        }
        if ($now->getTimestamp() - $record->lastSeenAt()->getTimestamp() >= $this->policy->touchIntervalSeconds()) { try { $record = $this->store->touch($digest, $now, $this->policy) ?? $record; } catch (\Throwable) { return new SessionValidationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); } }
        return new SessionValidationDecision(true, ReasonCode::ALLOWED, $record->principal(), $record, new AuthenticatedAccessContext($record->principal(), $record));
    }

    public function rotate(string $rawToken): SessionRotationDecision
    {
        $validated = $this->validate($rawToken); if (!$validated->allowed() || $validated->record() === null) return new SessionRotationDecision(false, $validated->reasonCode());
        $old = $validated->record(); $token = $this->tokens->issue(); $now = $this->clock->now(); $expires = $now->modify('+' . $this->policy->idleTtlSeconds() . ' seconds'); if ($expires > $old->absoluteExpiresAt()) $expires = $old->absoluteExpiresAt(); $record = new SessionRecord(\Identity\Contracts\SessionId::generate(), $this->tokens->digest($token), $old->principal(), $old->createdAt(), $now, $expires, $old->absoluteExpiresAt(), SessionState::ACTIVE, $old->deviceLabel(), $old->userAgentHash(), $old->ipDimensionHash());
        try { if (!$this->store->rotate($old->tokenDigest(), $record)) return new SessionRotationDecision(false, ReasonCode::SESSION_INVALID); } catch (\Throwable) { return new SessionRotationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); }
        return new SessionRotationDecision(true, ReasonCode::ALLOWED, $record, $token, SessionCookieDescriptor::forToken($token));
    }

    public function logout(?string $rawToken): SessionRevocationDecision
    {
        if ($rawToken === null || $rawToken === '') return new SessionRevocationDecision(true, ReasonCode::ALLOWED, SessionCookieDescriptor::deletion(), 0);
        try { $digest = $this->tokens->digest(new SessionToken($rawToken)); $this->store->revoke($digest, 'logged_out', $this->clock->now()); return new SessionRevocationDecision(true, ReasonCode::ALLOWED, SessionCookieDescriptor::deletion(), 1); } catch (\Throwable) { return new SessionRevocationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE, SessionCookieDescriptor::deletion()); }
    }
    public function revokeAll(string $accountId): SessionRevocationDecision { try { $count = $this->store->revokeAllForAccount($accountId, ReasonCode::SESSION_REVOKED, $this->clock->now()); return new SessionRevocationDecision(true, ReasonCode::ALLOWED, null, $count); } catch (\Throwable) { return new SessionRevocationDecision(false, ReasonCode::SESSION_STORE_UNAVAILABLE); } }
    public function lastSupersededSession(): ?SessionRecord { return $this->lastSupersededSession; }
    public function consumeSessionLimitAction(): ?string { $action = $this->lastSupersededSession === null ? null : 'OLDEST_REVOKED'; $this->lastSupersededSession = null; return $action; }

    /** @return array{allowed:bool,reason_code:string,sessions:list<array<string,mixed>>,current:?SessionRecord} */
    public function listOwnSessions(?string $rawToken): array
    {
        $validated = $this->validate($rawToken);
        if (!$validated->allowed() || $validated->record() === null) return ['allowed'=>false,'reason_code'=>$validated->reasonCode(),'sessions'=>[],'current'=>null];
        try { $records = $this->store->listActiveForAccount($validated->record()->principal()->accountId()); }
        catch (\Throwable) { return ['allowed'=>false,'reason_code'=>ReasonCode::SESSION_STORE_UNAVAILABLE,'sessions'=>[],'current'=>$validated->record()]; }
        $currentId = (string)$validated->record()->sessionId();
        $sessions = array_map(static fn(SessionRecord $record): array => [
            'session_id'=>(string)$record->sessionId(),
            'device_label'=>$record->deviceLabel(),
            'created_at'=>$record->createdAt()->format(DATE_ATOM),
            'last_seen_at'=>$record->lastSeenAt()->format(DATE_ATOM),
            'idle_expires_at'=>$record->expiresAt()->format(DATE_ATOM),
            'absolute_expires_at'=>$record->absoluteExpiresAt()->format(DATE_ATOM),
            'current_session'=>(string)$record->sessionId() === $currentId,
            'state'=>$record->state(),
        ], $records);
        usort($sessions, static fn(array $left, array $right): int => strcmp((string)$right['last_seen_at'], (string)$left['last_seen_at']) ?: strcmp((string)$left['session_id'], (string)$right['session_id']));
        return ['allowed'=>true,'reason_code'=>ReasonCode::ALLOWED,'sessions'=>$sessions,'current'=>$validated->record()];
    }

    /** @return array{allowed:bool,reason_code:string,revoked_session:?SessionRecord,current_revoked:bool,current:?SessionRecord} */
    public function revokeOwnSession(?string $rawToken, string $sessionId): array
    {
        $validated = $this->validate($rawToken);
        if (!$validated->allowed() || $validated->record() === null) return ['allowed'=>false,'reason_code'=>$validated->reasonCode(),'revoked_session'=>null,'current_revoked'=>false,'current'=>null];
        try { $targetId = new SessionId($sessionId); }
        catch (\Throwable) { return ['allowed'=>false,'reason_code'=>ReasonCode::SESSION_INVALID,'revoked_session'=>null,'current_revoked'=>false,'current'=>$validated->record()]; }
        if ($this->store instanceof AtomicSessionStorePort) {
            try { $record = $this->store->revokeOwnedAuthoritatively($validated->record()->tokenDigest(), $targetId, ReasonCode::SESSION_REVOKED, $this->clock->now()); }
            catch (\Throwable) { return ['allowed'=>false,'reason_code'=>ReasonCode::SESSION_STORE_UNAVAILABLE,'revoked_session'=>null,'current_revoked'=>false,'current'=>$validated->record()]; }
            if ($record === null) return ['allowed'=>false,'reason_code'=>ReasonCode::SESSION_INVALID,'revoked_session'=>null,'current_revoked'=>false,'current'=>$validated->record()];
            return ['allowed'=>true,'reason_code'=>ReasonCode::ALLOWED,'revoked_session'=>$record,'current_revoked'=>(string)$record->sessionId()===(string)$validated->record()->sessionId(),'current'=>$validated->record()];
        }
        try { $records = $this->store->listActiveForAccount($validated->record()->principal()->accountId()); }
        catch (\Throwable) { return ['allowed'=>false,'reason_code'=>ReasonCode::SESSION_STORE_UNAVAILABLE,'revoked_session'=>null,'current_revoked'=>false,'current'=>$validated->record()]; }
        foreach ($records as $record) {
            if ((string)$record->sessionId() !== (string)$targetId) continue;
            try { $revoked = $this->store->revoke($record->tokenDigest(), ReasonCode::SESSION_REVOKED, $this->clock->now()); }
            catch (\Throwable) { return ['allowed'=>false,'reason_code'=>ReasonCode::SESSION_STORE_UNAVAILABLE,'revoked_session'=>null,'current_revoked'=>false,'current'=>$validated->record()]; }
            return ['allowed'=>$revoked,'reason_code'=>$revoked?ReasonCode::ALLOWED:ReasonCode::SESSION_INVALID,'revoked_session'=>$revoked?$record:null,'current_revoked'=>$revoked&&(string)$record->sessionId()===(string)$validated->record()->sessionId(),'current'=>$validated->record()];
        }
        return ['allowed'=>false,'reason_code'=>ReasonCode::SESSION_INVALID,'revoked_session'=>null,'current_revoked'=>false,'current'=>$validated->record()];
    }
    private function safeRevoke(\Identity\Contracts\SessionTokenDigest $digest, string $reason): void { try { $this->store->revoke($digest, $reason, $this->clock->now()); } catch (\Throwable) {} }
    private function accountReason(string $status): string { return match ($status) { AccountStatus::BLOCKED => ReasonCode::ACCOUNT_BLOCKED, AccountStatus::DISABLED => ReasonCode::ACCOUNT_DISABLED, default => ReasonCode::ACCOUNT_NOT_ACTIVE }; }
    private function terminalReason(string $state): string { return match ($state) { SessionState::SUPERSEDED => ReasonCode::SESSION_SUPERSEDED, SessionState::IDLE_EXPIRED => ReasonCode::SESSION_IDLE_EXPIRED, SessionState::ABSOLUTE_EXPIRED => ReasonCode::SESSION_ABSOLUTE_EXPIRED, SessionState::ACCOUNT_LOCKED => ReasonCode::ACCOUNT_BLOCKED, SessionState::ACCOUNT_DISABLED => ReasonCode::ACCOUNT_DISABLED, SessionState::CREDENTIAL_CHANGED => ReasonCode::CREDENTIAL_VERSION_MISMATCH, default => ReasonCode::SESSION_REVOKED }; }
    private function label(mixed $value): string { $label = trim((string)$value); $label = preg_replace('/[^A-Za-z0-9 ._-]/', '', $label) ?? ''; return substr($label, 0, 80); }
    private function dimensionHash(mixed $value): string { $value = trim((string)$value); return $value === '' ? '' : hash('sha256', $value); }
}
