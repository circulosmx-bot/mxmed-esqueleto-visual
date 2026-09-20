<?php
declare(strict_types=1);

/**
 * Repository schema expectation for future multipart storage. The runtime does
 * not call this authority until a later chapter explicitly activates multipart.
 */
function clinical_multipart_storage_required_schema(): array
{
    return [
        'clinical_binary_uploads' => [
            'columns' => [
                'upload_id' => ['type' => 'char(36)', 'nullable' => 'NO', 'extra' => ''],
                'operation_type' => ['type' => 'varchar(64)', 'nullable' => 'NO', 'extra' => ''],
                'doctor_id' => ['type' => 'varchar(64)', 'nullable' => 'NO', 'extra' => ''],
                'context_type' => ['type' => 'varchar(16)', 'nullable' => 'NO', 'extra' => ''],
                'context_id' => ['type' => 'varchar(128)', 'nullable' => 'NO', 'extra' => ''],
                'idempotency_key_digest' => ['type' => 'char(64)', 'nullable' => 'NO', 'extra' => ''],
                'idempotency_request_id' => ['type' => 'bigint unsigned', 'nullable' => 'YES', 'extra' => ''],
                'semantic_request_hash' => ['type' => 'char(64)', 'nullable' => 'NO', 'extra' => ''],
                'binary_sha256' => ['type' => 'char(64)', 'nullable' => 'NO', 'extra' => ''],
                'byte_length' => ['type' => 'bigint unsigned', 'nullable' => 'NO', 'extra' => ''],
                'mime_type' => ['type' => 'varchar(100)', 'nullable' => 'NO', 'extra' => ''],
                'staging_key' => ['type' => 'varchar(512)', 'nullable' => 'NO', 'extra' => ''],
                'planned_final_prefix' => ['type' => 'varchar(512)', 'nullable' => 'NO', 'extra' => ''],
                'storage_state' => ['type' => 'varchar(32)', 'nullable' => 'NO', 'extra' => ''],
                'document_id' => ['type' => 'bigint unsigned', 'nullable' => 'YES', 'extra' => ''],
                'created_at' => ['type' => 'datetime', 'nullable' => 'NO', 'extra' => ''],
                'updated_at' => ['type' => 'datetime', 'nullable' => 'NO', 'extra' => ''],
                'expires_at' => ['type' => 'datetime', 'nullable' => 'NO', 'extra' => ''],
                'lease_until' => ['type' => 'datetime', 'nullable' => 'YES', 'extra' => ''],
                'last_error_code' => ['type' => 'varchar(64)', 'nullable' => 'YES', 'extra' => ''],
            ],
            'indexes' => [
                'PRIMARY' => ['unique' => true, 'columns' => ['upload_id']],
                'uq_binary_upload_staging_key' => ['unique' => true, 'columns' => ['staging_key']],
                'idx_binary_upload_state_expiry' => ['unique' => false, 'columns' => ['storage_state', 'expires_at']],
                'idx_binary_upload_idempotency' => ['unique' => false, 'columns' => ['idempotency_request_id']],
                'idx_binary_upload_document' => ['unique' => false, 'columns' => ['document_id']],
                'idx_binary_upload_sha256' => ['unique' => false, 'columns' => ['binary_sha256']],
            ],
            'foreign_keys' => [
                'fk_binary_upload_idempotency' => ['column' => 'idempotency_request_id', 'table' => 'clinical_idempotency_requests', 'referenced_column' => 'request_id', 'update' => 'RESTRICT', 'delete' => 'RESTRICT'],
                'fk_binary_upload_document' => ['column' => 'document_id', 'table' => 'clinical_documents', 'referenced_column' => 'id', 'update' => 'RESTRICT', 'delete' => 'RESTRICT'],
            ],
            'checks' => [
                'chk_binary_upload_state_v1' => ['storage_state', 'staged', 'finalized', 'orphaned', 'reconciliation_required'],
                'chk_binary_upload_hashes_v1' => ['idempotency_key_digest', 'semantic_request_hash', 'binary_sha256', 'regexp'],
                'chk_binary_upload_integrity_v1' => ['byte_length', 'mime_type', 'staging_key', 'planned_final_prefix'],
            ],
        ],
        'clinical_document_binaries' => [
            'columns' => [
                'binary_id' => ['type' => 'bigint unsigned', 'nullable' => 'NO', 'extra' => 'auto_increment'],
                'binary_uuid' => ['type' => 'char(36)', 'nullable' => 'NO', 'extra' => ''],
                'document_id' => ['type' => 'bigint unsigned', 'nullable' => 'NO', 'extra' => ''],
                'variant_role' => ['type' => 'varchar(16)', 'nullable' => 'NO', 'extra' => ''],
                'variant_version' => ['type' => 'smallint unsigned', 'nullable' => 'NO', 'extra' => ''],
                'storage_key' => ['type' => 'varchar(512)', 'nullable' => 'NO', 'extra' => ''],
                'sha256' => ['type' => 'char(64)', 'nullable' => 'NO', 'extra' => ''],
                'byte_length' => ['type' => 'bigint unsigned', 'nullable' => 'NO', 'extra' => ''],
                'mime_type' => ['type' => 'varchar(100)', 'nullable' => 'NO', 'extra' => ''],
                'width_px' => ['type' => 'int unsigned', 'nullable' => 'YES', 'extra' => ''],
                'height_px' => ['type' => 'int unsigned', 'nullable' => 'YES', 'extra' => ''],
                'source_filename' => ['type' => 'varchar(255)', 'nullable' => 'YES', 'extra' => ''],
                'created_at' => ['type' => 'datetime', 'nullable' => 'NO', 'extra' => ''],
                'finalized_at' => ['type' => 'datetime', 'nullable' => 'NO', 'extra' => ''],
            ],
            'indexes' => [
                'PRIMARY' => ['unique' => true, 'columns' => ['binary_id']],
                'uq_document_binary_uuid' => ['unique' => true, 'columns' => ['binary_uuid']],
                'uq_document_binary_storage_key' => ['unique' => true, 'columns' => ['storage_key']],
                'uq_document_binary_variant' => ['unique' => true, 'columns' => ['document_id', 'variant_role', 'variant_version']],
            ],
            'foreign_keys' => [
                'fk_document_binary_document' => ['column' => 'document_id', 'table' => 'clinical_documents', 'referenced_column' => 'id', 'update' => 'RESTRICT', 'delete' => 'RESTRICT'],
            ],
            'checks' => [
                'chk_document_binary_role_v1' => ['variant_role', 'original', 'display', 'thumbnail'],
                'chk_document_binary_hash_v1' => ['sha256', 'regexp'],
                'chk_document_binary_integrity_v1' => ['variant_version', 'byte_length', 'mime_type', 'storage_key'],
                'chk_document_binary_dimensions_v1' => ['width_px', 'height_px', 'is null', 'is not null'],
            ],
        ],
    ];
}

function clinical_multipart_storage_assert_schema_ready(PDO $pdo): void
{
    $drift = [];
    foreach (clinical_multipart_storage_required_schema() as $table => $expected) {
        $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $query->execute([$table]);
        if ((int)$query->fetchColumn() !== 1) {
            $drift[] = $table;
            continue;
        }

        $query = $pdo->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $query->execute([$table]);
        $actualColumns = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actualColumns[(string)$row['COLUMN_NAME']] = $row;
        }
        foreach ($expected['columns'] as $column => $shape) {
            $actual = $actualColumns[$column] ?? null;
            if (!is_array($actual)
                || strtolower((string)$actual['COLUMN_TYPE']) !== $shape['type']
                || strtoupper((string)$actual['IS_NULLABLE']) !== $shape['nullable']
                || strtolower((string)$actual['EXTRA']) !== $shape['extra']) {
                $drift[] = $table . '.' . $column;
            }
        }
        if (count($actualColumns) !== count($expected['columns'])) {
            $drift[] = $table . '.column_manifest';
        }

        $query = $pdo->prepare('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX');
        $query->execute([$table]);
        $actualIndexes = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = (string)$row['INDEX_NAME'];
            $actualIndexes[$name]['unique'] = ((int)$row['NON_UNIQUE']) === 0;
            $actualIndexes[$name]['columns'][] = (string)$row['COLUMN_NAME'];
        }
        foreach ($expected['indexes'] as $name => $shape) {
            if (($actualIndexes[$name] ?? null) !== $shape) {
                $drift[] = $table . '.' . $name;
            }
        }

        foreach ($expected['foreign_keys'] as $name => $shape) {
            $query = $pdo->prepare('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.CONSTRAINT_NAME=?');
            $query->execute([$table, $name]);
            $actual = $query->fetch(PDO::FETCH_ASSOC);
            if (!is_array($actual)
                || (string)$actual['COLUMN_NAME'] !== $shape['column']
                || (string)$actual['REFERENCED_TABLE_NAME'] !== $shape['table']
                || (string)$actual['REFERENCED_COLUMN_NAME'] !== $shape['referenced_column']
                || strtoupper((string)$actual['UPDATE_RULE']) !== $shape['update']
                || strtoupper((string)$actual['DELETE_RULE']) !== $shape['delete']) {
                $drift[] = $table . '.' . $name;
            }
        }

        foreach ($expected['checks'] as $name => $markers) {
            $query = $pdo->prepare('SELECT c.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS t JOIN information_schema.CHECK_CONSTRAINTS c ON c.CONSTRAINT_SCHEMA=t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME=t.CONSTRAINT_NAME WHERE t.CONSTRAINT_SCHEMA=DATABASE() AND t.TABLE_NAME=? AND t.CONSTRAINT_NAME=? AND t.CONSTRAINT_TYPE=\'CHECK\'');
            $query->execute([$table, $name]);
            $clause = strtolower((string)($query->fetchColumn() ?: ''));
            foreach ($markers as $marker) {
                if (!str_contains($clause, $marker)) {
                    $drift[] = $table . '.' . $name;
                    break;
                }
            }
        }
    }

    if ($drift !== []) {
        throw new RuntimeException('MULTIPART_STORAGE_SCHEMA_NOT_READY:' . implode(',', array_values(array_unique($drift))));
    }
}
