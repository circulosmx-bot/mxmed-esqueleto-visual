<?php
declare(strict_types=1);

namespace Agenda\Security;

use Agenda\Contracts\ActorReference;
use Identity\Contracts\AccountMembership;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\CanonicalProfileReference;

/**
 * Immutable Agenda context assembled only from already validated backend values.
 * It is intentionally independent of HTTP, storage and runtime adapters.
 */
final readonly class AgendaServerAuthorityContext
{
    public const SOURCE_BACKEND_RESOLVER = 'backend_resolver';

    public function __construct(
        private AuthenticatedAccessContext $identityContext,
        private AccountMembership $membership,
        private CanonicalProfileReference $profile,
        private ActorReference $realActor,
        private ActorReference $effectiveActor,
        private string $ownership,
        private string $scope,
        private string $correlationId,
        private string $requestId,
        private string $source = self::SOURCE_BACKEND_RESOLVER
    ) {
        if ($this->source !== self::SOURCE_BACKEND_RESOLVER) {
            throw new \InvalidArgumentException('agenda_authority_source_must_be_backend');
        }
        if (trim($this->profile->targetType()) === '' || trim($this->profile->targetId()) === '') {
            throw new \InvalidArgumentException('agenda_profile_reference_required');
        }
        if (!in_array($this->ownership, ['owner', 'member'], true)) {
            throw new \InvalidArgumentException('agenda_ownership_invalid');
        }
        foreach ([$this->scope, $this->correlationId, $this->requestId] as $value) {
            if (trim($value) === '' || preg_match('/^[A-Za-z0-9_.:-]{1,128}$/', $value) !== 1) {
                throw new \InvalidArgumentException('agenda_authority_context_value_invalid');
            }
        }
    }

    public function identityContext(): AuthenticatedAccessContext { return $this->identityContext; }
    public function membership(): AccountMembership { return $this->membership; }
    public function profile(): CanonicalProfileReference { return $this->profile; }
    public function realActor(): ActorReference { return $this->realActor; }
    public function effectiveActor(): ActorReference { return $this->effectiveActor; }
    public function accountId(): string { return $this->identityContext->principal()->accountId(); }
    public function membershipId(): string { return $this->membership->membershipId(); }
    public function profileId(): string { return $this->profile->targetId(); }
    public function role(): string { return $this->membership->role(); }
    public function ownership(): string { return $this->ownership; }
    public function scope(): string { return $this->scope; }
    public function correlationId(): string { return $this->correlationId; }
    public function requestId(): string { return $this->requestId; }
    public function source(): string { return $this->source; }
}
