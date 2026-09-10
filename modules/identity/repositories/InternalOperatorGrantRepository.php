<?php
declare(strict_types=1);
namespace Identity\Repositories;
use PDO;
use Platform\Contracts\CapabilitySet;

final class InternalOperatorGrantRepository
{
    public function __construct(private PDO $pdo) {}
    public function activeCapabilities(string $accountId): CapabilitySet
    {
        $s = $this->pdo->prepare("SELECT g.capability FROM internal_operator_grants g JOIN auth_accounts a ON a.account_id=g.account_id JOIN internal_staff s ON s.account_id=g.account_id AND s.status='ACTIVE' WHERE g.account_id=? AND g.status='ACTIVE' AND g.revoked_at IS NULL AND a.status='active' ORDER BY g.capability");
        $s->execute([$accountId]);
        // No cache: a revoked grant stops authorizing the next request.
        return new CapabilitySet(array_values(array_filter($s->fetchAll(PDO::FETCH_COLUMN), \Identity\Services\InternalCapabilityCatalog::known(...))));
    }
}
