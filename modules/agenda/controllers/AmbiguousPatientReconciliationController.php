<?php
declare(strict_types=1);

namespace Agenda\Controllers;

use Agenda\Services\AmbiguousPatientReconciliationService;
use Agenda\Services\ReconciliationException;
use PDO;

require_once __DIR__ . '/../services/AmbiguousPatientReconciliationService.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

final class AmbiguousPatientReconciliationController
{
    private ?AmbiguousPatientReconciliationService $service = null;
    private array $actorContext = [];

    public function __construct(?PDO $pdo = null)
    {
        try {
            $this->service = new AmbiguousPatientReconciliationService($pdo ?? mxmed_pdo());
        } catch (\Throwable) {
            $this->service = null;
        }
    }

    public function setActorContext(array $context = []): void
    {
        $this->actorContext = $context;
    }

    public function show(string $appointmentId): array
    {
        return $this->run(fn() => $this->serviceOrFail()->review($appointmentId, $this->actorContext));
    }

    public function resolve(string $appointmentId, array $payload): array
    {
        return $this->run(fn() => $this->serviceOrFail()->resolve($appointmentId, $payload, $this->actorContext));
    }

    private function serviceOrFail(): AmbiguousPatientReconciliationService
    {
        if ($this->service === null) {
            throw new ReconciliationException('db_not_ready', 'Conciliación no disponible.');
        }
        return $this->service;
    }

    private function run(callable $operation): array
    {
        try {
            return ['ok' => true, 'error' => null, 'message' => '', 'data' => $operation(), 'meta' => (object)['visibility' => 'private_admin']];
        } catch (ReconciliationException $error) {
            return [
                'ok' => false,
                'error' => $error->codeName,
                'message' => $error->getMessage(),
                'data' => null,
                'meta' => (object)['visibility' => 'private_admin'],
            ];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'db_error', 'message' => 'Conciliación no disponible.', 'data' => null, 'meta' => (object)['visibility' => 'private_admin']];
        }
    }
}
