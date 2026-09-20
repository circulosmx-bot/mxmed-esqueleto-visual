<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/clinical_multipart_storage_schema.php';

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$catalog = clinical_multipart_storage_required_schema();
expect_true(array_keys($catalog) === ['clinical_binary_uploads', 'clinical_document_binaries'], 'table manifest drift');
expect_true(count($catalog['clinical_binary_uploads']['columns']) === 20, 'upload column manifest drift');
expect_true(count($catalog['clinical_document_binaries']['columns']) === 14, 'binary column manifest drift');
expect_true(array_keys($catalog['clinical_binary_uploads']['columns']) === [
    'upload_id', 'operation_type', 'doctor_id', 'context_type', 'context_id',
    'idempotency_key_digest', 'idempotency_request_id', 'semantic_request_hash',
    'binary_sha256', 'byte_length', 'mime_type', 'staging_key', 'planned_final_prefix',
    'storage_state', 'document_id', 'created_at', 'updated_at', 'expires_at',
    'lease_until', 'last_error_code',
], 'upload columns drift');
expect_true(array_keys($catalog['clinical_document_binaries']['columns']) === [
    'binary_id', 'binary_uuid', 'document_id', 'variant_role', 'variant_version',
    'storage_key', 'sha256', 'byte_length', 'mime_type', 'width_px', 'height_px',
    'source_filename', 'created_at', 'finalized_at',
], 'binary columns drift');
expect_true($catalog['clinical_binary_uploads']['columns']['idempotency_request_id'] === ['type' => 'bigint unsigned', 'nullable' => 'YES', 'extra' => ''], 'idempotency correlation column drift');
expect_true($catalog['clinical_binary_uploads']['columns']['document_id'] === ['type' => 'bigint unsigned', 'nullable' => 'YES', 'extra' => ''], 'upload document column drift');
expect_true($catalog['clinical_document_binaries']['columns']['binary_id']['extra'] === 'auto_increment', 'binary identity drift');
expect_true($catalog['clinical_document_binaries']['columns']['document_id']['nullable'] === 'NO', 'binary document authority drift');

$uploadIndexes = $catalog['clinical_binary_uploads']['indexes'];
expect_true($uploadIndexes['uq_binary_upload_staging_key'] === ['unique' => true, 'columns' => ['staging_key']], 'staging unique drift');
expect_true($uploadIndexes['idx_binary_upload_state_expiry']['columns'] === ['storage_state', 'expires_at'], 'state/expiry index drift');
expect_true(isset($uploadIndexes['idx_binary_upload_idempotency'], $uploadIndexes['idx_binary_upload_document'], $uploadIndexes['idx_binary_upload_sha256']), 'upload reconciliation index missing');

$binaryIndexes = $catalog['clinical_document_binaries']['indexes'];
expect_true($binaryIndexes['uq_document_binary_uuid']['unique'] === true, 'binary UUID unique drift');
expect_true($binaryIndexes['uq_document_binary_storage_key']['unique'] === true, 'storage key unique drift');
expect_true($binaryIndexes['uq_document_binary_variant']['columns'] === ['document_id', 'variant_role', 'variant_version'], 'variant unique drift');

$uploadFks = $catalog['clinical_binary_uploads']['foreign_keys'];
expect_true($uploadFks['fk_binary_upload_idempotency']['table'] === 'clinical_idempotency_requests', 'idempotency FK target drift');
expect_true($uploadFks['fk_binary_upload_idempotency']['update'] === 'RESTRICT' && $uploadFks['fk_binary_upload_idempotency']['delete'] === 'RESTRICT', 'idempotency FK rule drift');
expect_true($uploadFks['fk_binary_upload_document']['table'] === 'clinical_documents', 'upload document FK target drift');
expect_true($catalog['clinical_document_binaries']['foreign_keys']['fk_document_binary_document']['table'] === 'clinical_documents', 'binary document FK target drift');

foreach (['fk_binary_upload_document', 'fk_binary_upload_idempotency'] as $name) {
    expect_true($uploadFks[$name]['update'] === 'RESTRICT' && $uploadFks[$name]['delete'] === 'RESTRICT', $name . ' must restrict');
}
$binaryFk = $catalog['clinical_document_binaries']['foreign_keys']['fk_document_binary_document'];
expect_true($binaryFk['update'] === 'RESTRICT' && $binaryFk['delete'] === 'RESTRICT', 'binary document FK must restrict');

expect_true(array_keys($catalog['clinical_binary_uploads']['checks']) === [
    'chk_binary_upload_state_v1',
    'chk_binary_upload_hashes_v1',
    'chk_binary_upload_integrity_v1',
], 'upload check manifest drift');
expect_true(array_keys($catalog['clinical_document_binaries']['checks']) === [
    'chk_document_binary_role_v1',
    'chk_document_binary_hash_v1',
    'chk_document_binary_integrity_v1',
    'chk_document_binary_dimensions_v1',
], 'binary check manifest drift');
expect_true(in_array('reconciliation_required', $catalog['clinical_binary_uploads']['checks']['chk_binary_upload_state_v1'], true), 'upload state check drift');
expect_true(in_array('thumbnail', $catalog['clinical_document_binaries']['checks']['chk_document_binary_role_v1'], true), 'variant role check drift');

echo "MULTI02A_READINESS_PURE_PASS\n";
