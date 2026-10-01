<?php
declare(strict_types=1);

namespace Identity\Repositories;

use Identity\Contracts\OrganizationTypeLookupPort;
use PDO;

final class MedicalGroupTypeRepository implements OrganizationTypeLookupPort
{
    public function __construct(private PDO $pdo) {}

    public function typeForGroup(string $groupId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT organization_type_key FROM medical_groups WHERE group_id = :group_id LIMIT 1');
        $stmt->execute(['group_id' => $groupId]);
        $value = $stmt->fetchColumn();
        return is_string($value) ? $value : null;
    }
}
