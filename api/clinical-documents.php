<?php
declare(strict_types=1);

require_once __DIR__ . '/_lib/http.php';
require_once __DIR__ . '/_lib/db.php';
require_once __DIR__ . '/_lib/clinical_documents.php';

$action = $_GET['action'] ?? '';

try {
    $pdo = mxmed_pdo();
    mxmed_ensure_clinical_docs_schema($pdo);
} catch (Throwable $e) {
    mxmed_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}

if ($action === 'save') {
    $body = mxmed_read_json_body();
    try {
        $doc = mxmed_build_clinical_document($body);
    } catch (Throwable $e) {
        mxmed_json_response(['ok' => false, 'error' => $e->getMessage()], 400);
    }

    if ($doc['document_type'] === 'nota_evolucion') {
        $errs = mxmed_evolution_note_validate_to_generate($doc['content']['payload']);
        if (count($errs)) mxmed_json_response(['ok' => false, 'errors' => $errs], 422);
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
            ':status' => $doc['status'],
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

        $doc['document_db_id'] = $docId;
        mxmed_json_response(['ok' => true, 'document' => $doc], 201);
    } catch (Throwable $e) {
        try { $pdo->rollBack(); } catch (Throwable $e2) { }
        mxmed_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'list') {
    $patientId = trim((string)($_GET['patient_id'] ?? ''));
    if ($patientId === '') mxmed_json_response(['ok' => false, 'error' => 'patient_id requerido'], 400);
    $type = trim((string)($_GET['document_type'] ?? ''));
    $limit = (int)($_GET['limit'] ?? 30);
    if ($limit <= 0) $limit = 30;
    if ($limit > 200) $limit = 200;

    $sql = "
        SELECT
            id,
            title,
            document_type,
            summary,
            event_datetime,
            printable,
            JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.snapshot.medico.nombre_completo')) AS doctor_name
        FROM clinical_documents
        WHERE patient_id = :patient_id
    ";
    $params = [':patient_id' => $patientId];
    if ($type !== '') {
        $sql .= " AND document_type = :type";
        $params[':type'] = $type;
    }
    $sql .= " ORDER BY event_datetime DESC, id DESC LIMIT {$limit}";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        mxmed_json_response(['ok' => true, 'items' => $items]);
    } catch (Throwable $e) {
        mxmed_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) mxmed_json_response(['ok' => false, 'error' => 'id requerido'], 400);
    try {
        $stmt = $pdo->prepare("SELECT * FROM clinical_documents WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) mxmed_json_response(['ok' => false, 'error' => 'Documento no encontrado'], 404);

        $pstmt = $pdo->prepare("SELECT user_id, role, participation_type, signed_at FROM clinical_document_participants WHERE clinical_document_id = :id ORDER BY id ASC");
        $pstmt->execute([':id' => $id]);
        $participants = $pstmt->fetchAll();

        $payload = json_decode((string)$row['payload_json'], true);
        if (!is_array($payload)) $payload = [];

        mxmed_json_response([
            'ok' => true,
            'document' => [
                'document_db_id' => (int)$row['id'],
                'document_id' => $row['document_uuid'],
                'document_type' => $row['document_type'],
                'title' => $row['title'],
                'version' => (int)$row['version'],
                'context' => [
                    'patient_id' => $row['patient_id'],
                    'encounter_id' => $row['encounter_id'],
                    'hospital_stay_id' => $row['hospital_stay_id'],
                    'care_setting' => $row['care_setting'],
                    'service' => $row['service'],
                ],
                'status' => $row['status'],
                'timestamps' => [
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                    'generated_at' => $row['generated_at'],
                    'signed_at' => $row['signed_at'],
                ],
                'audit' => [
                    'created_by_user_id' => $row['created_by_user_id'],
                    'updated_by_user_id' => $row['updated_by_user_id'],
                ],
                'participants' => $participants,
                'content' => [
                    'payload' => $payload,
                    'rendered_text' => $row['rendered_text'],
                    'summary' => $row['summary'],
                    'edited_flag' => (int)$row['edited_flag'],
                ],
                'ui' => [
                    'event_datetime' => $row['event_datetime'],
                    'widget_group' => $row['widget_group'],
                    'printable' => (bool)$row['printable'],
                ],
            ],
        ]);
    } catch (Throwable $e) {
        mxmed_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

mxmed_json_response(['ok' => false, 'error' => 'Acción no soportada'], 404);
