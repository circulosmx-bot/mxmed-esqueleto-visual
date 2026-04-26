<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class MedicalGroupsRepository
{
    private PDO $pdo;

    private const STATUSES = ['pending', 'verified', 'rejected', 'merged'];
    private const SOURCES = ['user_submitted', 'operator_created', 'imported'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function ensureTable(): void
    {
        if (!$this->tableExists('medical_groups')) {
            $this->createTableIfMissing();
        }
        if (!$this->tableExists('medical_groups')) {
            throw new RuntimeException('medical_groups table not ready');
        }
    }

    public function upsertGroup(array $payload): array
    {
        $this->ensureTable();

        $groupId = trim((string)($payload['group_id'] ?? ''));
        if ($groupId === '') {
            $groupId = $this->generateGroupId();
        }

        $displayName = trim((string)($payload['display_name'] ?? ''));
        if ($displayName === '') {
            throw new RuntimeException('display_name required');
        }

        $canonicalName = trim((string)($payload['canonical_name'] ?? ''));
        if ($canonicalName === '') {
            $canonicalName = $this->normalizeCanonicalName($displayName);
        }

        $status = strtolower(trim((string)($payload['status'] ?? 'pending')));
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'pending';
        }

        $source = strtolower(trim((string)($payload['source'] ?? 'user_submitted')));
        if (!in_array($source, self::SOURCES, true)) {
            $source = 'user_submitted';
        }

        $sql = 'INSERT INTO medical_groups (
            group_id, canonical_name, display_name, logo_url_original, logo_url_approved,
            status, source, created_by_user_id, reviewed_by_user_id, reviewed_at,
            rejection_reason, merged_into_group_id, updated_at
        ) VALUES (
            :group_id, :canonical_name, :display_name, :logo_url_original, :logo_url_approved,
            :status, :source, :created_by_user_id, :reviewed_by_user_id, :reviewed_at,
            :rejection_reason, :merged_into_group_id, NOW()
        )
        ON DUPLICATE KEY UPDATE
            canonical_name = VALUES(canonical_name),
            display_name = VALUES(display_name),
            logo_url_original = VALUES(logo_url_original),
            logo_url_approved = VALUES(logo_url_approved),
            status = VALUES(status),
            source = VALUES(source),
            reviewed_by_user_id = VALUES(reviewed_by_user_id),
            reviewed_at = VALUES(reviewed_at),
            rejection_reason = VALUES(rejection_reason),
            merged_into_group_id = VALUES(merged_into_group_id),
            updated_at = NOW()';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'group_id' => $groupId,
            'canonical_name' => $canonicalName,
            'display_name' => $displayName,
            'logo_url_original' => $this->nullableText($payload['logo_url_original'] ?? null),
            'logo_url_approved' => $this->nullableText($payload['logo_url_approved'] ?? null),
            'status' => $status,
            'source' => $source,
            'created_by_user_id' => $this->nullableText($payload['created_by_user_id'] ?? null),
            'reviewed_by_user_id' => $this->nullableText($payload['reviewed_by_user_id'] ?? null),
            'reviewed_at' => $this->nullableText($payload['reviewed_at'] ?? null),
            'rejection_reason' => $this->nullableText($payload['rejection_reason'] ?? null),
            'merged_into_group_id' => $this->nullableText($payload['merged_into_group_id'] ?? null),
        ]);

        $saved = $this->findById($groupId);
        if (!$saved) {
            throw new RuntimeException('medical group upsert failed');
        }

        return $saved;
    }

    public function findById(string $groupId): ?array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('SELECT * FROM medical_groups WHERE group_id = :group_id LIMIT 1');
        $stmt->execute(['group_id' => trim($groupId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function listPending(int $limit = 100): array
    {
        $this->ensureTable();
        $limit = max(1, min(500, $limit));
        $sql = "SELECT * FROM medical_groups WHERE status = 'pending' ORDER BY updated_at ASC LIMIT {$limit}";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function searchVerified(string $term = '', int $limit = 20): array
    {
        $this->ensureTable();
        $limit = max(1, min(100, $limit));
        $needle = trim($term);
        if ($needle === '') {
            $sql = "SELECT * FROM medical_groups WHERE status = 'verified' ORDER BY display_name ASC LIMIT {$limit}";
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT *
               FROM medical_groups
              WHERE status = 'verified'
                AND (
                  display_name LIKE :q_display
                  OR canonical_name LIKE :q_canonical
                )
              ORDER BY display_name ASC
              LIMIT {$limit}"
        );
        $stmt->execute([
            'q_display' => '%' . $needle . '%',
            'q_canonical' => '%' . $this->normalizeCanonicalName($needle) . '%',
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function searchVerifiedByContext(string $term = '', string $cp = '', string $colonia = '', int $limit = 20): array
    {
        $this->ensureTable();
        $limit = max(1, min(100, $limit));
        $needle = trim($term);
        $cp = trim($cp);
        $colonia = trim($colonia);

        if (($cp !== '' || $colonia !== '')
            && (!$this->tableExists('medical_group_memberships') || !$this->tableExists('consultorios'))) {
            return [];
        }

        $conditions = ["mg.status = 'verified'"];
        $params = [];

        if ($needle !== '') {
            $conditions[] = '(mg.display_name LIKE :q_display OR mg.canonical_name LIKE :q_canonical)';
            $params['q_display'] = '%' . $needle . '%';
            $params['q_canonical'] = '%' . $this->normalizeCanonicalName($needle) . '%';
        }

        if ($cp !== '' || $colonia !== '') {
            $filters = [];
            if ($cp !== '') {
                $filters[] = 'c.cp = :cp';
                $params['cp'] = $cp;
            }
            if ($colonia !== '') {
                $filters[] = 'LOWER(c.colonia) LIKE :colonia';
                $params['colonia'] = '%' . strtolower($colonia) . '%';
            }
            $conditions[] = 'EXISTS (
                SELECT 1
                  FROM medical_group_memberships m
                  JOIN consultorios c
                    ON c.doctor_id = m.doctor_id
                   AND c.consultorio_id = m.consultorio_id
                 WHERE m.group_id = mg.group_id
                   AND (' . implode(' AND ', $filters) . ')
            )';
        }

        $sql = 'SELECT mg.group_id, mg.canonical_name, mg.display_name, mg.logo_url_approved, mg.status
                  FROM medical_groups mg
                 WHERE ' . implode(' AND ', $conditions) . '
                 ORDER BY mg.display_name ASC
                 LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function createTableIfMissing(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS medical_groups (
            group_id VARCHAR(64) NOT NULL,
            canonical_name VARCHAR(190) NOT NULL,
            display_name VARCHAR(190) NOT NULL,
            logo_url_original TEXT DEFAULT NULL,
            logo_url_approved TEXT DEFAULT NULL,
            status ENUM('pending','verified','rejected','merged') NOT NULL DEFAULT 'pending',
            source ENUM('user_submitted','operator_created','imported') NOT NULL DEFAULT 'user_submitted',
            created_by_user_id VARCHAR(64) DEFAULT NULL,
            reviewed_by_user_id VARCHAR(64) DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            rejection_reason TEXT DEFAULT NULL,
            merged_into_group_id VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (group_id),
            KEY idx_medical_groups_status (status),
            KEY idx_medical_groups_canonical_name (canonical_name),
            KEY idx_medical_groups_display_name (display_name),
            KEY idx_medical_groups_merged_into (merged_into_group_id),
            CONSTRAINT fk_medical_groups_merged_into
              FOREIGN KEY (merged_into_group_id) REFERENCES medical_groups(group_id)
              ON UPDATE CASCADE ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function generateGroupId(): string
    {
        return 'mg_' . str_replace('.', '', uniqid('', true));
    }

    private function nullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function normalizeCanonicalName(string $value): string
    {
        $text = strtolower(trim($value));
        $text = preg_replace('/\s+/', ' ', $text);
        return trim((string)$text);
    }
}
