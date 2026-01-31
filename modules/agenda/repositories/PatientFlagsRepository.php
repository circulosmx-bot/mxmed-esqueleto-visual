<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class PatientFlagsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listByPatientId(string $patientId, bool $activeOnly, int $limit): array
    {
        throw new RuntimeException('patient flags not ready');
    }
}
