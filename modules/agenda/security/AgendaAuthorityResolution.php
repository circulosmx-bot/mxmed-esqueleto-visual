<?php
declare(strict_types=1);

namespace Agenda\Security;

use Agenda\Contracts\ActorAuthorityContract;
use Platform\Contracts\AuthorizationDecision;
use Platform\Contracts\TrustedAuthorizationContext;

final readonly class AgendaAuthorityResolution
{
    private function __construct(
        private bool $allowed,
        private int $httpStatus,
        private string $reasonCode,
        private ?ActorAuthorityContract $authority,
        private ?TrustedAuthorizationContext $trustedContext,
        private ?AuthorizationDecision $authorizationDecision,
        private array $clientClaimsDiagnostic
    ) {}

    /** @param array<string,bool> $clientClaimsDiagnostic */
    public static function allow(ActorAuthorityContract $authority, TrustedAuthorizationContext $trustedContext, AuthorizationDecision $decision, array $clientClaimsDiagnostic): self
    {
        return new self(true, 200, 'allowed', $authority, $trustedContext, $decision, $clientClaimsDiagnostic);
    }

    /** @param array<string,bool> $clientClaimsDiagnostic */
    public static function deny(int $httpStatus, string $reasonCode, array $clientClaimsDiagnostic = [], ?AuthorizationDecision $decision = null): self
    {
        if (!in_array($httpStatus, [401, 403, 503], true)) throw new \InvalidArgumentException('unsupported_agenda_authority_http_status');
        if (trim($reasonCode) === '') throw new \InvalidArgumentException('agenda_authority_reason_required');
        return new self(false, $httpStatus, $reasonCode, null, null, $decision, $clientClaimsDiagnostic);
    }

    public function allowed(): bool { return $this->allowed; }
    public function httpStatus(): int { return $this->httpStatus; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function authority(): ?ActorAuthorityContract { return $this->authority; }
    public function trustedContext(): ?TrustedAuthorizationContext { return $this->trustedContext; }
    public function authorizationDecision(): ?AuthorizationDecision { return $this->authorizationDecision; }
    /** @return array<string,bool> */
    public function clientClaimsDiagnostic(): array { return $this->clientClaimsDiagnostic; }
    public function claimsMismatchDetected(): bool { return (bool)($this->clientClaimsDiagnostic['attempt_detected'] ?? false); }

    /** Deliberately omits account, membership, profile, token, cookie and internal reason values. */
    /** @return array{authorized:bool,status:int,error:?string} */
    public function publicResponse(): array
    {
        return [
            'authorized' => $this->allowed,
            'status' => $this->httpStatus,
            'error' => $this->allowed ? null : ($this->httpStatus === 401 ? 'authentication_required' : ($this->httpStatus === 503 ? 'authorization_unavailable' : 'forbidden')),
        ];
    }
}
