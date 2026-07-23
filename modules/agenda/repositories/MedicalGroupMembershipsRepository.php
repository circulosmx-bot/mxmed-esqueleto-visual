<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class MedicalGroupMembershipsRepository
{
    private PDO $pdo;

    private const STATUSES = ['pending', 'verified', 'rejected', 'unlinked'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function ensureTable(): void
    {
        if (!$this->tableExists('medical_group_memberships')) {
            throw new RuntimeException('schema_not_ready');
        }
    }

    public function upsertMembership(array $payload): array
    {
        $this->ensureTable();

        $doctorId = trim((string)($payload['doctor_id'] ?? ''));
        $consultorioId = trim((string)($payload['consultorio_id'] ?? ''));
        $groupId = trim((string)($payload['group_id'] ?? ''));
        if ($doctorId === '' || $consultorioId === '' || $groupId === '') {
            throw new RuntimeException('doctor_id, consultorio_id and group_id are required');
        }

        $status = strtolower(trim((string)($payload['status'] ?? 'pending')));
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'pending';
        }

        $sql = 'INSERT INTO medical_group_memberships (
            doctor_id, consultorio_id, group_id, status,
            submitted_group_name, submitted_logo_url, display_name_override, updated_at
        ) VALUES (
            :doctor_id, :consultorio_id, :group_id, :status,
            :submitted_group_name, :submitted_logo_url, :display_name_override, NOW()
        )
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            submitted_group_name = VALUES(submitted_group_name),
            submitted_logo_url = VALUES(submitted_logo_url),
            display_name_override = VALUES(display_name_override),
            updated_at = NOW()';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'group_id' => $groupId,
            'status' => $status,
            'submitted_group_name' => $this->nullableText($payload['submitted_group_name'] ?? null),
            'submitted_logo_url' => $this->nullableText($payload['submitted_logo_url'] ?? null),
            'display_name_override' => $this->nullableText($payload['display_name_override'] ?? null),
        ]);

        $saved = $this->findByScope($doctorId, $consultorioId, $groupId);
        if (!$saved) {
            throw new RuntimeException('medical group membership upsert failed');
        }

        return $saved;
    }

    public function findByScope(string $doctorId, string $consultorioId, string $groupId): ?array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(
            'SELECT * FROM medical_group_memberships
              WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id AND group_id = :group_id
              LIMIT 1'
        );
        $stmt->execute([
            'doctor_id' => trim($doctorId),
            'consultorio_id' => trim($consultorioId),
            'group_id' => trim($groupId),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function listByConsultorio(string $doctorId, string $consultorioId): array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(
            'SELECT * FROM medical_group_memberships
              WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id
              ORDER BY updated_at DESC'
        );
        $stmt->execute([
            'doctor_id' => trim($doctorId),
            'consultorio_id' => trim($consultorioId),
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listByGroup(string $groupId, int $limit = 1000): array
    {
        $this->ensureTable();
        $groupId = trim($groupId);
        if ($groupId === '') {
            return [];
        }
        $limit = max(1, min(5000, $limit));
        $sql = "SELECT * FROM medical_group_memberships
                 WHERE group_id = :group_id
                 ORDER BY id ASC
                 LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['group_id' => $groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function bulkSetStatusByGroup(string $groupId, string $status, bool $excludeUnlinked = true): int
    {
        $this->ensureTable();
        $groupId = trim($groupId);
        $status = strtolower(trim($status));
        if ($groupId === '') {
            return 0;
        }
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('membership status is invalid');
        }

        $sql = 'UPDATE medical_group_memberships
                   SET status = :status,
                       updated_at = NOW()
                 WHERE group_id = :group_id';
        if ($excludeUnlinked) {
            $sql .= " AND status <> 'unlinked'";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'group_id' => $groupId,
            'status' => $status,
        ]);
        return $stmt->rowCount();
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function nullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    }
}
