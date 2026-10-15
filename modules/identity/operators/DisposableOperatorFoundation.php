<?php
declare(strict_types=1);
namespace Identity\Operators;

/** OP-IMP01 compatibility factory. Both compositions share one authority service. */
final class DisposableOperatorFoundation
{
    public static function compose(\PDO $pdo, \Identity\Services\SessionService $sessions, \Identity\Http\CsrfTokenService $csrf,
        \Platform\Services\CanonicalAuditWriter $audit, \Identity\Adapters\InMemoryIdentityNotificationAdapter $notifications,
        \Identity\Contracts\Clock $clock): ProfileOperatorService
    { return ProfileOperatorService::compose($pdo,$sessions,$csrf,$audit,$notifications,$clock); }
}
