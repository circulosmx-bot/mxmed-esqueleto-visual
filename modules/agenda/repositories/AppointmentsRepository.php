<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class AppointmentsRepository
{
    private PDO $pdo;
    private string $table;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $table = trim((string)($config['appointments_table'] ?? ''));
        $this->table = $table !== '' ? $this->sanitizeIdentifier($table) : 'agenda_appointments';
    }

    private function ensureTable(): void
    {
        if (!$this->tableExists($this->table)) {
            throw new RuntimeException('appointments table not ready');
        }
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function listByRange(string $from, string $to, ?string $doctorId = null, ?string $consultorioId = null, int $limit = 200): array
    {
        $this->ensureTable();
        $sql = sprintf(
            'SELECT appointment_id, doctor_id, consultorio_id, patient_id, start_at, end_at, modality, status, channel_origin FROM %s WHERE start_at >= :from AND end_at <= :to',
            $this->table
        );
        $params = ['from' => $from, 'to' => $to];

        if ($doctorId) {
            $sql .= ' AND doctor_id = :doctor_id';
            $params['doctor_id'] = $doctorId;
        }
        if ($consultorioId) {
            $sql .= ' AND consultorio_id = :consultorio_id';
            $params['consultorio_id'] = $consultorioId;
        }

        $sql .= ' ORDER BY start_at ASC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(string $id): ?array
    {
        $this->ensureTable();
        $sql = sprintf('SELECT * FROM %s WHERE appointment_id = :id LIMIT 1', $this->table);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function sanitizeIdentifier(?string $value): string
    {
        if (!$value) {
            return '';
        }
        return preg_replace('/[^a-zA-Z0-9_]/', '', $value) ?: '';
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
