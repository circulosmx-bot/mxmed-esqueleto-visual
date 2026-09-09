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
        $s = $this->pdo->prepare("SELECT g.capability FROM internal_operator_grants g JOIN auth_accounts a ON a.account_id=g.account_id WHERE g.account_id=? AND g.status='ACTIVE' AND g.revoked_at IS NULL AND a.status='active' ORDER BY g.capability");
        $s->execute([$accountId]);
        // No cache: a revoked grant stops authorizing the next request.
        return new CapabilitySet($s->fetchAll(PDO::FETCH_COLUMN));
    }
}
