<?php
declare(strict_types=1);

/** Read-only document projection for the future Historial prescription/order timeline. */
function clinical_document_timeline_encode_cursor(string $at, int $id): string
{
    return base64_encode((string)json_encode(['at' => $at, 'id' => $id], JSON_THROW_ON_ERROR));
}

function clinical_document_timeline_decode_cursor(string $cursor): ?array
{
    if ($cursor === '' || strlen($cursor) > 512) return null;
    $decoded = base64_decode($cursor, true);
    $value = $decoded === false ? null : json_decode($decoded, true);
    if (!is_array($value) || !is_string($value['at'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value['at'])) return null;
    if (!is_int($value['id'] ?? null) || $value['id'] < 1) return null;
    return ['at' => $value['at'], 'id' => $value['id']];
}

function clinical_document_timeline_list_fetch(PDO $pdo, string $patientId, int $limit, ?array $cursor = null): array
{
    // A missing association or lineage column cannot safely mean "patient-level".
    $columns = $pdo->query('SHOW COLUMNS FROM clinical_documents')->fetchAll(PDO::FETCH_COLUMN);
    $required = ['id','document_uuid','document_type','status','patient_id','title','summary','event_datetime',
        'encounter_ref_id','encounter_id','appointment_id','hospital_stay_id','created_at','generated_at','payload_json'];
    if (array_diff($required, $columns)) throw new RuntimeException('TIMELINE_READER_SCHEMA_NOT_READY');
    $hasRevisions = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_revisions' LIMIT 1")->fetchColumn();
    if (!$hasRevisions) throw new RuntimeException('TIMELINE_READER_SCHEMA_NOT_READY');

    // These timestamps are written as UTC system times. event_datetime is intentionally excluded.
    $generatedValid = "d.generated_at IS NOT NULL AND d.generated_at > '1000-01-01 00:00:00'";
    $createdValid = "d.created_at IS NOT NULL AND d.created_at > '1000-01-01 00:00:00'";
    $at = "CASE WHEN {$generatedValid} THEN d.generated_at WHEN {$createdValid} THEN d.created_at ELSE NULL END";
    $source = "CASE WHEN {$generatedValid} THEN 'generated_at' WHEN {$createdValid} THEN 'created_at' ELSE NULL END";
    $cursorWhere = $cursor === null ? '' : ' AND (t.timeline_at < :cursor_at OR (t.timeline_at = :cursor_at AND t.id < :cursor_id))';
    $sql = "SELECT t.* FROM (
        SELECT d.id,d.document_uuid,d.document_type,d.status,d.patient_id,d.title,d.summary,d.event_datetime,
               d.encounter_ref_id,d.encounter_id,d.appointment_id,d.hospital_stay_id,d.created_at,d.generated_at,
               COALESCE(rev.original_document_id,d.id) AS lineage_root_id,
               {$at} AS timeline_at, {$source} AS timeline_at_source,
               CASE WHEN d.document_type IN ('prescription','receta')
                         AND JSON_TYPE(JSON_EXTRACT(d.payload_json,'$.prescription.items'))='ARRAY'
                    THEN JSON_LENGTH(JSON_EXTRACT(d.payload_json,'$.prescription.items')) ELSE NULL END AS prescription_item_count
        FROM clinical_documents d
        LEFT JOIN clinical_document_revisions rev ON rev.new_document_id=d.id
        WHERE d.patient_id=:patient_id
          AND d.document_type IN ('prescription','receta','order','orders','lab_order','imaging_order','orden_estudio')
          AND NOT EXISTS (SELECT 1 FROM clinical_document_revisions successor
                          WHERE successor.supersedes_document_id=d.id
                             OR (successor.original_document_id=d.id AND successor.supersedes_document_id IS NULL))
    ) t WHERE t.timeline_at IS NOT NULL{$cursorWhere}
    ORDER BY t.timeline_at DESC,t.id DESC LIMIT :fetch_limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    if ($cursor !== null) {
        $stmt->bindValue(':cursor_at', $cursor['at'], PDO::PARAM_STR);
        $stmt->bindValue(':cursor_id', $cursor['id'], PDO::PARAM_INT);
    }
    $stmt->bindValue(':fetch_limit', $limit + 1, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($rows) > $limit;
    $items = array_slice($rows, 0, $limit);
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['lineage_root_id'] = (int)$item['lineage_root_id'];
        $item['prescription_item_count'] = $item['prescription_item_count'] === null ? null : (int)$item['prescription_item_count'];
        $item['has_successor'] = 0; // Superseded versions were removed before pagination.
        $item['timeline_scope'] = trim((string)$item['encounter_ref_id']) !== '' || trim((string)$item['encounter_id']) !== ''
            ? 'ENCOUNTER' : (trim((string)$item['appointment_id']) !== '' ? 'APPOINTMENT'
                : (trim((string)$item['hospital_stay_id']) !== '' ? 'HOSPITAL_STAY' : 'PATIENT'));
        $item['timeline_eligible'] = $item['timeline_scope'] === 'PATIENT';
    }
    unset($item);
    $last = $items ? $items[count($items) - 1] : null;
    return ['items' => $items, 'has_more' => $hasMore,
        'cursor_next' => $hasMore && $last ? clinical_document_timeline_encode_cursor($last['timeline_at'], $last['id']) : null,
        'limit' => $limit];
}
