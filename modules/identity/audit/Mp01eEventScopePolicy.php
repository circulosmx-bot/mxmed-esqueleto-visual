<?php
declare(strict_types=1);

namespace Identity\Audit;

use Platform\Contracts\AuditEventScopePolicy;
use Platform\Contracts\TrustedRequestContext;

final class Mp01eEventScopePolicy implements AuditEventScopePolicy
{
    private const MAP = [
        'AUTH_REGISTRATION_REQUESTED'=>['AUTH_REGISTRATION','AUTH'],
        'AUTH_EMAIL_VERIFICATION_SENT'=>['AUTH_EMAIL_VERIFICATION','AUTH'],
        'AUTH_EMAIL_VERIFIED'=>['AUTH_EMAIL_VERIFICATION','AUTH'],
        'AUTH_LOGIN_SUCCEEDED'=>['AUTH_LOGIN','AUTH'],
        'AUTH_LOGIN_FAILED'=>['AUTH_LOGIN','AUTH'],
        'AUTH_PASSWORD_RECOVERY_REQUESTED'=>['AUTH_PASSWORD_RECOVERY','AUTH'],
        'AUTH_PASSWORD_RESET_SUCCEEDED'=>['AUTH_PASSWORD_RECOVERY','AUTH'],
        'AUTH_PASSWORD_CHANGED'=>['AUTH_PASSWORD_CHANGE','AUTH'],
        'AUTH_SESSION_CREATED'=>['AUTH_SESSION_LIFECYCLE','SESSION'],
        'AUTH_SESSION_ROTATED'=>['AUTH_SESSION_LIFECYCLE','SESSION'],
        'AUTH_SESSION_REVOKED'=>['AUTH_SESSION_LIFECYCLE','SESSION'],
        'AUTH_LOGOUT'=>['AUTH_SESSION_LIFECYCLE','SESSION'],
        'AUTH_LOGOUT_ALL'=>['AUTH_SESSION_LIFECYCLE','SESSION'],
    ];

    public function assertRequestMatches(string $eventType, TrustedRequestContext $request): void
    {
        $expected = self::MAP[$eventType] ?? null;
        if ($expected === null) throw new \InvalidArgumentException('event_outside_mp01e_scope');
        if ($request->operationKey !== $expected[0]) throw new \InvalidArgumentException('mp01e_operation_mismatch');
        if ($request->sourceModule !== $expected[1]) throw new \InvalidArgumentException('mp01e_source_module_mismatch');
    }

    /** @return array<string,array{0:string,1:string}> */
    public function map(): array { return self::MAP; }
}
