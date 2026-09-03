<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/db.php';

function mediaMigrationAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

if (getenv('MXMED_MEDIA_MIGRATION_VERIFY') !== '1') {
    echo "MediaMigrationDatabaseTest SKIP (set MXMED_MEDIA_MIGRATION_VERIFY=1 for local schema readback)\n";
    exit(0);
}

$pdo = mxmed_pdo();
$create = $pdo->query('SHOW CREATE TABLE media_assets')->fetch(PDO::FETCH_NUM);
mediaMigrationAssert(is_array($create), 'media_assets exists after clean migration apply');
$sql = (string)($create[1] ?? '');
foreach (['media_id', 'owner_type', 'owner_id', 'purpose', 'classification', 'storage_key', 'public_url', 'mime_type', 'width', 'height', 'byte_size', 'checksum_sha256', 'alt_text', 'status'] as $field) {
    mediaMigrationAssert(str_contains($sql, '`' . $field . '`'), 'database field present: ' . $field);
}
mediaMigrationAssert(str_contains($sql, "enum('PHYSICIAN_PERSONAL_LOGO','CONSULTORIO_GROUP_LOGO')"), 'database purpose enum exact');
mediaMigrationAssert(str_contains($sql, "enum('PUBLIC')"), 'database classification enum exact');
mediaMigrationAssert(!preg_match('/\\b(?:tiny|medium|long)?blob\\b/i', $sql), 'database table has no binary BLOB');
mediaMigrationAssert(str_contains($sql, 'chk_media_assets_width'), 'database width constraint active');
mediaMigrationAssert(str_contains($sql, 'chk_media_assets_height'), 'database height constraint active');
mediaMigrationAssert(str_contains($sql, 'chk_media_assets_bytes'), 'database byte constraint active');
mediaMigrationAssert((int)$pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn() === 0, 'clean apply contains no fixture rows');

echo "MediaMigrationDatabaseTest PASS\n";
