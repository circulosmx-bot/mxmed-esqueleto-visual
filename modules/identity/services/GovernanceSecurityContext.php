<?php
declare(strict_types=1);
namespace Identity\Services;
use Identity\Contracts\{AuthenticatedAccessContext,Clock,SystemClock};

/** Reuses canonical account authentication and session state. No new session authority. */
final class GovernanceSecurityContext
{
    public function __construct(private SessionService $sessions, private CredentialAuthenticationService $credentials,
        private Clock $clock = new SystemClock()) {}

    public function binding(AuthenticatedAccessContext $actor): array
    {
        $value = ['account_id'=>$actor->accountId(),'session_id'=>(string)$actor->session()->sessionId(),
            'credential_version'=>$actor->principal()->credentialVersion(),
            'token_digest'=>(string)$actor->session()->tokenDigest()];
        $this->assertLive($value);
        return $value;
    }

    public function reauthenticate(AuthenticatedAccessContext $actor, string $email, string $password, array $dimensions): array
    {
        $binding = $this->binding($actor);
        $decision = $this->credentials->authenticate($email, $password, $dimensions);
        $candidate = $decision->candidate();
        if (!$decision->isAllowed() || $candidate === null || $candidate->accountId() !== $actor->accountId()
            || $candidate->credentialVersion() !== $binding['credential_version']) throw new \RuntimeException('governance_reauthentication_denied');
        $this->assertLive($binding);
        return $binding + ['reauthenticated_at'=>$this->clock->now()->format(DATE_ATOM)];
    }

    public function assertLive(array $binding, bool $recent = false): void
    {
        if (!$this->sessions->bindingIsCurrent($binding)) throw new \RuntimeException('governance_session_invalidated');
        if ($recent) {
            $at = new \DateTimeImmutable((string)($binding['reauthenticated_at'] ?? '1970-01-01T00:00:00Z'));
            if ($at > $this->clock->now() || $at <= $this->clock->now()->modify('-5 minutes')) throw new \RuntimeException('governance_reauthentication_expired');
        }
    }
    public function assertUnexpired(string $expiresAt):void
    {
        if(new \DateTimeImmutable($expiresAt,new \DateTimeZone('UTC'))<=$this->clock->now())throw new \RuntimeException('governance_case_expired');
    }
}
