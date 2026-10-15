<?php
declare(strict_types=1);
namespace Identity\Services;

use Identity\Contracts\ProfileOperatorOwnershipBoundary;
use PDO;

/** A future schema installation cannot silently omit operator invalidation. */
final class ProfileOperatorOwnershipGuard
{
    public static function assertConfigured(PDO $pdo, string $profile, ?ProfileOperatorOwnershipBoundary $boundary): void
    {
        if ($boundary!==null) return;
        $s=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='auth_profile_operator_invitations'");
        if ((int)$s->fetchColumn()===0) return;
        $s=$pdo->prepare('SELECT invitation_id FROM auth_profile_operator_invitations WHERE profile_doctor_id=? LIMIT 1');
        $s->execute([$profile]);
        if ($s->fetchColumn()!==false) throw new \RuntimeException('operator_ownership_boundary_required');
    }
}
