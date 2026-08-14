<?php
declare(strict_types=1);

namespace Platform\Contracts;

/** Shared fail-closed scope boundary for canonical audit producers. */
interface AuditEventScopePolicy
{
    public function assertRequestMatches(
        string $eventType,
        TrustedRequestContext $request,
    ): void;
}
