<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;
use Identity\Http\IdentityHttpComposition;
final class InvitationComposition
{
    public static function fromIdentity(IdentityHttpComposition $identity):InvitationService
    {
        if(getenv('MXMED_STAFF_ADMISSION_INVITATIONS_ENABLED')!=='1')throw new \RuntimeException('admission_disabled');
        // Preserve foundation authorization, private approval/source pins, privacy and schema checks.
        Composition::fromIdentity($identity);
        // No ratified production TTL/channel/legal configuration or production delivery implementation.
        // A feature flag or synthetic environment variable cannot substitute for those decisions.
        // Owned CLI QA constructs real service primitives explicitly, never selects a positive HTTP adapter.
        throw new \RuntimeException('staff_invitation_production_policy_unavailable');
    }
}
