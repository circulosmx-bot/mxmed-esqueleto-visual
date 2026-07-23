<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class MedicalGroupReviewLogRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function ensureTable(): void
    {
        if (!$this->tableExists('medical_group_review_log')) {
            throw new RuntimeException('schema_not_ready');
        }
    }

    public function append(array $payload): array
    {
        $this->ensureTable();

        $groupId = trim((string)($payload['group_id'] ?? ''));
        $action = trim((string)($payload['action'] ?? ''));
        if ($groupId === '' || $action === '') {
            throw new RuntimeException('group_id and action are required');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO medical_group_review_log (group_id, action, notes, actor_user_id)
             VALUES (:group_id, :action, :notes, :actor_user_id)'
        );
        $stmt->execute([
            'group_id' => $groupId,
            'action' => $action,
            'notes' => $this->nullableText($payload['notes'] ?? null),
            'actor_user_id' => $this->nullableText($payload['actor_user_id'] ?? null),
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $saved = $this->findById($id);
        if (!$saved) {
            throw new RuntimeException('medical group review log append failed');
        }
        return $saved;
    }

    public function findById(int $id): ?array
    {
        $this->ensureTable();
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM medical_group_review_log WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function listByGroup(string $groupId, int $limit = 100): array
    {
        $this->ensureTable();
        $groupId = trim($groupId);
        if ($groupId === '') {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $sql = "SELECT * FROM medical_group_review_log WHERE group_id = :group_id ORDER BY created_at DESC LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['group_id' => $groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
