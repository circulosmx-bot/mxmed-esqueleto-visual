<?php
declare(strict_types=1);
namespace Platform\Contracts;
final readonly class TrustedAuditContext
{
    private function __construct(
        public string $actorIdentityId, public string $actorType, public string $actorRole,
        public string $actorScope, public string $requestId, public string $correlationId,
        public ?string $sessionId, public ?string $trustedClientIp, public ?string $trustedRawUserAgent,
        public string $sourceModule, public string $sourceRoute,
    ) {}
    public static function fromServer(
        string $actorIdentityId, string $actorType, string $actorRole, string $actorScope,
        string $requestId, string $correlationId, ?string $sessionId, ?string $trustedClientIp,
        ?string $trustedRawUserAgent, string $sourceModule, string $sourceRoute,
    ): self { return new self($actorIdentityId,$actorType,$actorRole,$actorScope,$requestId,$correlationId,$sessionId,$trustedClientIp,$trustedRawUserAgent,$sourceModule,$sourceRoute); }
}
