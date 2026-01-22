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

$body = mxmed_read_json_body();
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
