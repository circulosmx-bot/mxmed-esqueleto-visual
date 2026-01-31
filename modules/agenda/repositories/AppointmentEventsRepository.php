<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class AppointmentEventsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listByAppointmentId(string $appointmentId, int $limit): array
    {
        throw new RuntimeException('appointment events not ready');
    }
}
