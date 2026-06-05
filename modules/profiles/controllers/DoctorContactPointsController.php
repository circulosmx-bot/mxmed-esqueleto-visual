<?php
declare(strict_types=1);

namespace Profiles\Controllers;

use Profiles\Repositories\DoctorContactPointsRepository;
use RuntimeException;

require_once __DIR__ . '/../repositories/DoctorContactPointsRepository.php';

final class DoctorContactPointsController
{
    private DoctorContactPointsRepository $repository;

    public function __construct(DoctorContactPointsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(string $doctorId, string $authMode = 'transitional_open'): array
    {
        $doctorId = trim($doctorId);
        if (!$this->isValidDoctorId($doctorId)) {
            return $this->error('invalid_doctor_id', 'doctor_id invalid', $authMode);
        }

        try {
            $items = $this->repository->listByDoctor($doctorId);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'doctor_contact_points table not ready') {
                return $this->error('db_not_ready', 'doctor_contact_points table not ready', $authMode, [
                    'schema_executed' => false,
                ]);
            }
            return $this->error('profile_contact_points_unavailable', 'contact points unavailable', $authMode);
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'items' => $items,
            ],
            'meta' => $this->meta($authMode, [
                'schema_executed' => true,
                'count' => count($items),
            ]),
        ];
    }

    private function error(string $code, string $message, string $authMode, array $metaExtra = []): array
    {
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => $this->meta($authMode, $metaExtra),
        ];
    }

    private function meta(string $authMode, array $extra = []): array
    {
        return array_merge([
            'contract' => 'doctor_contact_points_private',
            'version' => 'SYS-Data-01P',
            'generated_at' => gmdate('c'),
            'auth_mode' => $authMode,
            'source' => 'doctor_contact_points',
        ], $extra);
    }

    private function isValidDoctorId(string $doctorId): bool
    {
        if ($doctorId === '' || strlen($doctorId) > 64) {
            return false;
        }
        return preg_match('/^[A-Za-z0-9._:-]+$/', $doctorId) === 1;
    }
}
