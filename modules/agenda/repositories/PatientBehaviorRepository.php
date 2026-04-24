<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class PatientBehaviorRepository
{
    private PDO $pdo;
    private ?string $table = null;
    private bool $enabled = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $table = trim((string)($config['patient_incidents_table'] ?? ''));
        $this->enabled = $table !== '';
        if ($this->enabled) {
            $this->table = $table;
        }
    }

    public function getIncidentsByPatient(string $patientId, string $doctorId): array
    {
        $this->ensureTable();
        $sql = sprintf(
            'SELECT * FROM %s WHERE patient_id = :patient_id AND doctor_id = :doctor_id ORDER BY created_at DESC',
            $this->table
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countNoShow(string $patientId, string $doctorId): int
    {
        return $this->countByType($patientId, $doctorId, 'no_show');
    }

    public function countLateCancel(string $patientId, string $doctorId): int
    {
        return $this->countByType($patientId, $doctorId, 'late_cancel');
    }

    public function getRecentIncidents(string $patientId, string $doctorId, int $limit = 20): array
    {
        $this->ensureTable();
        $safeLimit = max(1, min(500, (int)$limit));
        $sql = sprintf(
            'SELECT * FROM %s WHERE patient_id = :patient_id AND doctor_id = :doctor_id ORDER BY created_at DESC LIMIT :limit',
            $this->table
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':patient_id', $patientId);
        $stmt->bindValue(':doctor_id', $doctorId);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function countByType(string $patientId, string $doctorId, string $incidentType): int
    {
        $this->ensureTable();
        $sql = sprintf(
            'SELECT COUNT(*) FROM %s WHERE patient_id = :patient_id AND doctor_id = :doctor_id AND incident_type = :incident_type',
            $this->table
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'incident_type' => $incidentType,
        ]);
        return (int)$stmt->fetchColumn();
    }

    private function ensureTable(): void
    {
        if (!$this->enabled || !$this->table || !$this->tableExists($this->table)) {
            throw new RuntimeException('patient incidents not ready');
        }
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function loadConfig(): array
    {
        $path = __DIR__ . '/../config/agenda.php';
        if (!is_file($path)) {
            return [];
        }
        $config = require $path;
        return is_array($config) ? $config : [];
    }
}
