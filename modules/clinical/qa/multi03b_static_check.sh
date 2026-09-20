#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$repo_root"
php <<'PHP'
<?php
require 'api/_lib/clinical_multipart_document_service.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function code(string $class, string $method): string {
    $r = new ReflectionMethod($class, $method);
    $lines = file($r->getFileName());
    $source = "<?php\n" . implode('', array_slice($lines, $r->getStartLine()-1, $r->getEndLine()-$r->getStartLine()+1));
    return implode('', array_map(static fn($t) => is_array($t) ? (in_array($t[0], [T_COMMENT,T_DOC_COMMENT],true) ? '' : $t[1]) : $t, token_get_all($source)));
}
function ordered(string $source, array $calls): void {
    $offset = 0;
    foreach ($calls as $call) {
        $at = strpos($source, $call, $offset);
        check($at !== false, "Missing/out-of-order call: $call");
        $offset = $at + strlen($call);
    }
}
$c = 'ClinicalMultipartDocumentService';
$execute = code($c, 'execute');
ordered($execute, ['clinical_multipart_storage_assert_schema_ready(', '->stageFile(', '->insertStaged(', '$this->commit();', '$this->coordinate(']);
$main = code($c, 'coordinate');
ordered($main, ['->claim(', '->bindRequest(', '$createResource(', '->buildFinalKey(', '->finalizeCreateOnly(', '->insertOriginal(', '->finalize(', '->complete(', '$this->commit();', '$this->cleanupCommittedStaging(']);
check(!str_contains($main, '->deleteUncommitted('), 'Direct cleanup inside transaction');
ordered(code($c,'cleanupCommittedStaging'), ['->deleteUncommitted(', "'MULTIPART_POSTCOMMIT_CLEANUP_FAILED'"]);
ordered(code($c,'replay'), ['->replay(', '$this->commit();', '$this->cleanupRedundantStaging(']);
check(str_contains($main, '$this->rollbackConfirmed()') && str_contains($main, '->quarantine('), 'Missing rollback/quarantine');
check(str_contains($main, 'if ($rollbackConfirmed)'), 'Quarantine lacks rollback proof');
$source = file_get_contents('api/_lib/clinical_multipart_document_service.php');
check(str_contains($source, 'new ClinicalIdempotencyRepository($pdo)'), 'Canonical repository missing');
foreach (['clinical_idempotency_key_validate(', 'clinical_idempotency_operation_validate(', 'clinical_idempotency_request_hash('] as $helper) check(str_contains($source,$helper), 'Canonical helper missing');
check(str_contains($execute, "'idempotency_key_digest' => hash('sha256', \$key)"), 'Digest missing');
$insert = code('ClinicalMultipartCoordinationRepository','insertStaged');
check(!preg_match('/(?<![a-z_])idempotency_key(?![a-z_])/i',$insert), 'Raw key in coordination');
check(!preg_match('/(?:UPDATE|DELETE\s+FROM)\s+clinical_document_binaries/i',$source), 'Mutable manifest');
check(str_contains($source,"'ORIGINAL',1"), 'Original variant missing');
check(!preg_match('/CREATE\s+TABLE|https?:\/\/|\$_(?:POST|GET|REQUEST|FILES|SERVER|COOKIE)|new\s+PDO\s*\(/i',$source), 'Forbidden authority/HTTP/connection');
check(!preg_match('/INSERT\s+INTO\s+.*idempotency/i',$source), 'Second ledger implementation');
$delete = code('ClinicalMultipartCoordinationRepository','deleteRedundantStaged');
foreach (["storage_state='STAGED'",'document_id IS NULL','idempotency_request_id IS NULL'] as $guard) check(str_contains($delete,$guard),'Unsafe delete');
echo "MULTI03B_STATIC_TRANSACTION_ORDER=PASS\nMULTI03B_STATIC_AUTHORITIES=PASS\n";
PHP
protected_paths=(api/clinical/index.php api/clinical-documents.php api/evolution-note-generate.php api/_lib/clinical_idempotency.php api/_lib/clinical_encounter_integrity.php api/_lib/clinical_private_binary_storage.php api/_lib/clinical_multipart_storage_schema.php modules/clinical/db/migrations/2026_09_19_05_clinical_binary_storage.sql)
git diff --exit-code 683f99fabbd6617f58fff50eb8fb78b891b26213 -- "${protected_paths[@]}"
if rg -n 'clinical_multipart_document_service' api --glob '*.php' --glob '!clinical_multipart_document_service.php'; then
  echo 'FAIL: runtime service wiring' >&2
  exit 1
fi
rg -q 'V1_MULTIPART_STORAGE_NOT_READY' api/clinical/index.php
test -f modules/clinical/db/migrations/2026_09_19_05_clinical_binary_storage.sql
echo 'MULTI03B_STATIC_QA=PASS'
echo 'ANY_DATABASE_CONNECTED=false'
