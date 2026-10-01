<?php
declare(strict_types=1);

// Physical, disposable MySQL check of both accepted idempotency generations.
require_once __DIR__ . '/../../../api/_lib/clinical_encounter_integrity.php';

$host = getenv('MXMED_DB_HOST') ?: 'localhost';
$user = getenv('MXMED_DB_USER') ?: 'root';
$pass = getenv('MXMED_DB_PASS') ?: '';
$pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$database = 'trt04gate_' . bin2hex(random_bytes(6));
$pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $pdo->exec("USE `$database`");
    $pdo->exec("CREATE TABLE clinical_idempotency_requests (
        operation_type VARCHAR(64) NOT NULL,
        committed_at DATETIME NULL,
        observation_id BIGINT NULL,
        document_id BIGINT NULL,
        encounter_amendment_id BIGINT NULL,
        document_revision_id BIGINT NULL
    ) ENGINE=InnoDB");
    $v1Operation = "CHECK (operation_type IN ('CREATE_OBSERVATION','CREATE_ENCOUNTER_DOCUMENT','CREATE_POST_ENCOUNTER_RESULT','CREATE_ENCOUNTER_AMENDMENT','CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT'))";
    $v1Result = "CHECK (
        (committed_at IS NULL AND observation_id IS NULL AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL)
        OR (committed_at IS NOT NULL AND (
            (operation_type='CREATE_OBSERVATION' AND observation_id IS NOT NULL AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL)
            OR (operation_type IN ('CREATE_ENCOUNTER_DOCUMENT','CREATE_POST_ENCOUNTER_RESULT') AND observation_id IS NULL AND document_id IS NOT NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL)
            OR (operation_type='CREATE_ENCOUNTER_AMENDMENT' AND observation_id IS NULL AND document_id IS NULL AND encounter_amendment_id IS NOT NULL AND document_revision_id IS NULL)
            OR (operation_type='CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT' AND observation_id IS NULL AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NOT NULL)
        )))";
    $trtOperation = "CHECK (operation_type IN ('CREATE_OBSERVATION','CREATE_ENCOUNTER_DOCUMENT','CREATE_POST_ENCOUNTER_RESULT','CREATE_ENCOUNTER_AMENDMENT','CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT','CREATE_TREATMENT_PLAN','TRANSITION_TREATMENT_PLAN','CREATE_TREATMENT_SESSION','COMPLETE_TREATMENT_SESSION','VOID_TREATMENT_SESSION','CORRECT_TREATMENT_SESSION'))";
    $trtResult = "CHECK (
        (committed_at IS NULL AND observation_id IS NULL AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL AND treatment_plan_id IS NULL AND treatment_plan_event_id IS NULL AND treatment_session_id IS NULL AND treatment_result_json IS NULL)
        OR (committed_at IS NOT NULL AND (
            (operation_type='CREATE_OBSERVATION' AND observation_id IS NOT NULL AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL AND treatment_plan_id IS NULL AND treatment_plan_event_id IS NULL AND treatment_session_id IS NULL)
            OR (operation_type IN ('CREATE_ENCOUNTER_DOCUMENT','CREATE_POST_ENCOUNTER_RESULT') AND observation_id IS NULL AND document_id IS NOT NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL AND treatment_plan_id IS NULL AND treatment_plan_event_id IS NULL AND treatment_session_id IS NULL)
            OR (operation_type='CREATE_ENCOUNTER_AMENDMENT' AND observation_id IS NULL AND document_id IS NULL AND encounter_amendment_id IS NOT NULL AND document_revision_id IS NULL AND treatment_plan_id IS NULL AND treatment_plan_event_id IS NULL AND treatment_session_id IS NULL)
            OR (operation_type='CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT' AND observation_id IS NULL AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NOT NULL AND treatment_plan_id IS NULL AND treatment_plan_event_id IS NULL AND treatment_session_id IS NULL)
            OR (operation_type='CREATE_TREATMENT_PLAN' AND treatment_plan_id IS NOT NULL AND treatment_result_json IS NOT NULL)
            OR (operation_type='TRANSITION_TREATMENT_PLAN' AND treatment_plan_event_id IS NOT NULL AND treatment_result_json IS NOT NULL)
            OR (operation_type IN ('CREATE_TREATMENT_SESSION','COMPLETE_TREATMENT_SESSION','VOID_TREATMENT_SESSION','CORRECT_TREATMENT_SESSION') AND treatment_session_id IS NOT NULL AND treatment_result_json IS NOT NULL)
        )))";
    $assert = static function (PDO $db, string $label, bool $ready): void {
        $actual = clinical_encounter_integrity_idempotency_constraint_drift($db) === [];
        if ($actual !== $ready) throw new RuntimeException("$label expected " . ($ready ? 'READY' : 'NOT_READY'));
        echo "$label=PASS\n";
    };
    $pdo->exec("ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_operation_v1 $v1Operation");
    $pdo->exec("ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_committed_result_v1 $v1Result");
    $assert($pdo, 'QA_V1_SCHEMA', true);

    $pdo->exec('ALTER TABLE clinical_idempotency_requests DROP CONSTRAINT chk_idempotency_operation_v1, DROP CONSTRAINT chk_idempotency_committed_result_v1');
    $pdo->exec('ALTER TABLE clinical_idempotency_requests ADD COLUMN treatment_plan_id BIGINT NULL, ADD COLUMN treatment_plan_event_id BIGINT NULL, ADD COLUMN treatment_session_id BIGINT NULL, ADD COLUMN treatment_result_json JSON NULL');
    $pdo->exec("ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_operation_trt04 $trtOperation");
    $pdo->exec("ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_committed_result_trt04 $trtResult");
    $assert($pdo, 'QA_TRT04_SCHEMA', true);
    $pdo->exec('ALTER TABLE clinical_idempotency_requests DROP CONSTRAINT chk_idempotency_operation_trt04');
    $weakOperation = str_replace(",'CORRECT_TREATMENT_SESSION'", '', $trtOperation);
    $pdo->exec("ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_operation_trt04 $weakOperation");
    $assert($pdo, 'QA_WEAK_TRT04_OPERATION_CLAUSE', false);
    $pdo->exec('ALTER TABLE clinical_idempotency_requests DROP CONSTRAINT chk_idempotency_operation_trt04');
    $pdo->exec("ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_operation_trt04 $trtOperation");

    $pdo->exec('ALTER TABLE clinical_idempotency_requests DROP CONSTRAINT chk_idempotency_operation_trt04');
    $assert($pdo, 'QA_MISSING_OPERATION', false);
    $pdo->exec("ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_operation_trt04 $trtOperation");
    $pdo->exec('ALTER TABLE clinical_idempotency_requests DROP CONSTRAINT chk_idempotency_committed_result_trt04');
    $assert($pdo, 'QA_MISSING_COMMITTED_RESULT', false);
    $pdo->exec("ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_committed_result_trt04 $trtResult");
    $pdo->exec('ALTER TABLE clinical_idempotency_requests DROP CONSTRAINT chk_idempotency_operation_trt04');
    $pdo->exec("ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_operation_v1 $v1Operation");
    $assert($pdo, 'QA_MIXED_PAIR', false);
    $pdo->exec('ALTER TABLE clinical_idempotency_requests DROP CONSTRAINT chk_idempotency_committed_result_trt04');
    $pdo->exec("ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_committed_result_v1 $v1Result");
    $assert($pdo, 'QA_V1_PAIR_WITH_TRT04_COLUMNS', false);
    $pdo->exec('ALTER TABLE clinical_idempotency_requests DROP CONSTRAINT chk_idempotency_operation_v1, DROP CONSTRAINT chk_idempotency_committed_result_v1');
    $assert($pdo, 'QA_NO_PAIR', false);
} finally {
    $pdo->exec("DROP DATABASE `$database`");
}
