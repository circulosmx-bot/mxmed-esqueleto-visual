<?php
declare(strict_types=1);

/**
 * Endpoint: POST api/evolution-note-generate.php
 * Creates nota_evolucion clinical document (status generated).
 * Input: {context, payload, actor} JSON.
 * Calls mxmed_build_clinical_document + validation.
 * Persists to clinical_documents and participants.
 * Returns {ok, document_id, document_uuid}.
 * Requires clinical docs schema.
 * Uses MySQL via mxmed_pdo().
 * Stores payload_json, rendered_text, summary.
 * For list, use clinical-documents.php?action=list.
 * patient_id should be URL-encoded in frontend.
 */

require_once __DIR__ . '/_lib/http.php';
require_once __DIR__ . '/_lib/db.php';
require_once __DIR__ . '/_lib/clinical_documents.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    mxmed_json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
}

try {
    $pdo = mxmed_pdo();
    mxmed_ensure_clinical_docs_schema($pdo);
} catch (Throwable $e) {
    mxmed_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}

function mxmed_evolution_note_is_uuid_v4(string $value): bool {
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
}

function mxmed_evolution_note_is_canonical_patient_id(string $value): bool {
    if (mxmed_evolution_note_is_uuid_v4($value)) {
        return true;
    }

    if (preg_match('/^p_[a-f0-9]{12}$/i', $value) === 1) {
        return true;
    }

    return preg_match('/^p_[A-Za-z0-9_-]{6,62}$/', $value) === 1;
}

function mxmed_evolution_note_patient_exists(PDO $pdo, string $patientId): bool {
    $stmt = $pdo->prepare("SELECT patient_id FROM patients_patients WHERE patient_id = :patient_id LIMIT 1");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) && trim((string)($row['patient_id'] ?? '')) !== '';
}

function mxmed_evolution_note_validate_patient_id(PDO $pdo, array $body): string {
    $context = $body['context'] ?? null;
    if (!is_array($context)) {
        mxmed_json_response([
            'ok' => false,
            'error' => 'invalid_params',
            'message' => 'patient_id must be canonical',
            'data' => null,
        ], 400);
    }

    $rawPatientId = $context['patient_id'] ?? null;
    if (!is_scalar($rawPatientId)) {
        mxmed_json_response([
            'ok' => false,
            'error' => 'invalid_params',
            'message' => 'patient_id must be canonical',
            'data' => null,
        ], 400);
    }

    $patientId = trim((string)$rawPatientId);
    if ($patientId === '' || !mxmed_evolution_note_is_canonical_patient_id($patientId)) {
        mxmed_json_response([
            'ok' => false,
            'error' => 'invalid_params',
            'message' => 'patient_id must be canonical',
            'data' => null,
        ], 400);
    }

    if (!mxmed_evolution_note_patient_exists($pdo, $patientId)) {
        mxmed_json_response([
            'ok' => false,
            'error' => 'not_found',
            'message' => 'patient not found',
            'data' => null,
        ], 404);
    }

    return $patientId;
}

$body = mxmed_read_json_body();
$body['context']['patient_id'] = mxmed_evolution_note_validate_patient_id($pdo, $body);
$context = is_array($body['context'] ?? null) ? $body['context'] : [];
$payload = is_array($body['payload'] ?? null) ? $body['payload'] : [];
$actor = is_array($body['actor'] ?? null) ? $body['actor'] : [];

try {
    $doc = mxmed_build_clinical_document([
        'type' => 'nota_evolucion',
        'context' => $context,
        'payload' => $payload,
        'actor' => $actor,
    ]);
} catch (Throwable $e) {
    mxmed_json_response(['ok' => false, 'error' => $e->getMessage()], 400);
}

$errs = mxmed_evolution_note_validate_to_generate($doc['content']['payload']);
if (count($errs)) {
    mxmed_json_response(['ok' => false, 'errors' => $errs], 422);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO clinical_documents (
            document_uuid, document_type, title, version, status,
            patient_id, encounter_id, hospital_stay_id, care_setting, service,
            payload_json, rendered_text, summary, edited_flag,
            event_datetime, widget_group, printable,
            created_at, updated_at, generated_at, signed_at,
            created_by_user_id, updated_by_user_id
        ) VALUES (
            :uuid, :type, :title, :version, :status,
            :patient_id, :encounter_id, :hospital_stay_id, :care_setting, :service,
            :payload_json, :rendered_text, :summary, :edited_flag,
            :event_datetime, :widget_group, :printable,
            :created_at, :updated_at, :generated_at, :signed_at,
            :created_by_user_id, :updated_by_user_id
        )
    ");

    $payloadJson = json_encode($doc['content']['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt->execute([
        ':uuid' => $doc['document_id'],
        ':type' => $doc['document_type'],
        ':title' => $doc['title'],
        ':version' => (int)$doc['version'],
        ':status' => 'generated',
        ':patient_id' => $doc['context']['patient_id'],
        ':encounter_id' => $doc['context']['encounter_id'],
        ':hospital_stay_id' => $doc['context']['hospital_stay_id'],
        ':care_setting' => $doc['context']['care_setting'],
        ':service' => $doc['context']['service'],
        ':payload_json' => $payloadJson,
        ':rendered_text' => $doc['content']['rendered_text'],
        ':summary' => $doc['content']['summary'],
        ':edited_flag' => (int)($doc['content']['edited_flag'] ?? 0),
        ':event_datetime' => $doc['ui']['event_datetime'],
        ':widget_group' => $doc['ui']['widget_group'],
        ':printable' => $doc['ui']['printable'] ? 1 : 0,
        ':created_at' => $doc['timestamps']['created_at'],
        ':updated_at' => $doc['timestamps']['updated_at'],
        ':generated_at' => $doc['timestamps']['generated_at'],
        ':signed_at' => $doc['timestamps']['signed_at'],
        ':created_by_user_id' => $doc['audit']['created_by_user_id'],
        ':updated_by_user_id' => $doc['audit']['updated_by_user_id'],
    ]);

    $docId = (int)$pdo->lastInsertId();

    $pstmt = $pdo->prepare("
        INSERT INTO clinical_document_participants (
            clinical_document_id, user_id, role, participation_type, signed_at, created_at
        ) VALUES (
            :doc_id, :user_id, :role, :ptype, :signed_at, :created_at
        )
    ");

    foreach ($doc['participants'] as $p) {
        $pstmt->execute([
            ':doc_id' => $docId,
            ':user_id' => (string)$p['user_id'],
            ':role' => (string)$p['role'],
            ':ptype' => (string)$p['participation_type'],
            ':signed_at' => $p['signed_at'],
            ':created_at' => $doc['timestamps']['created_at'],
        ]);
    }

    $pdo->commit();

    mxmed_json_response([
        'ok' => true,
        'document_id' => $docId,
        'document_uuid' => $doc['document_id'],
    ], 201);
} catch (Throwable $e) {
    try { $pdo->rollBack(); } catch (Throwable $e2) { }
    mxmed_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}
