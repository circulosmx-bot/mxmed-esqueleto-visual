<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use PDO;

/** Internal issuer port. No browser claims, membership writes or signing authority. */
interface ProtectedPhysicianEvidencePort
{
    /** Caller owns this exact PDO transaction. Revalidate under locks on every use.
     * Returns minimized, current authority facts; never bytes, contact data or storage keys.
     * The admission/binding writer must append its own audit and reference request/version.
     */
    public function requireConfirmed(PDO $transaction, string $requestId, int $version,
        string $accountId, string $doctorId): array;
}
