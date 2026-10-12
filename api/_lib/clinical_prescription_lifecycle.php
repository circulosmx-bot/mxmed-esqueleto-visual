<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_prescription_v2.php';

function clinical_prescription_lifecycle_reason(array $body): array
{
    $code = (string)($body['reason_code'] ?? '');
    $allowed = ['error_en_medicamento','error_en_dosis_o_indicaciones',
        'cambio_en_plan_terapeutico','duplicada','emitida_por_error','otra'];
    if (!in_array($code, $allowed, true)) throw new InvalidArgumentException('PRESCRIPTION_VOID_REASON_INVALID');
    $detail = clinical_prescription_v2_text($body['reason_detail'] ?? '', 1000);
    $note = clinical_prescription_v2_text($body['explanatory_note'] ?? '', 1000);
    if ($code === 'otra' && $detail === '') throw new InvalidArgumentException('PRESCRIPTION_VOID_REASON_DETAIL_REQUIRED');
    return [$code, $detail, $note];
}

function clinical_prescription_lifecycle_row(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT l.*, o.document_uuid AS original_document_uuid,
        r.document_uuid AS replacement_document_uuid, r.status AS replacement_status,
        r.generated_at AS replacement_generated_at
        FROM clinical_prescription_lifecycle l
        JOIN clinical_documents o ON o.id=l.original_document_id
        LEFT JOIN clinical_documents r ON r.id=l.replacement_document_id
        WHERE l.original_document_id=? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function clinical_prescription_lifecycle_projection(PDO $pdo, int $id): array
{
    $row = clinical_prescription_lifecycle_row($pdo, $id);
    $incomingStmt = $pdo->prepare('SELECT o.document_uuid AS original_document_uuid
        FROM clinical_prescription_lifecycle l JOIN clinical_documents o ON o.id=l.original_document_id
        WHERE l.replacement_document_id=? LIMIT 1');
    $incomingStmt->execute([$id]);
    $incoming = $incomingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $statusStmt = $pdo->prepare('SELECT status FROM clinical_documents WHERE id=?');
    $statusStmt->execute([$id]);
    $status = (string)($statusStmt->fetchColumn() ?: '');
    if (!$row && !$incoming) return ['state'=>$status ?: 'active','relation'=>null];
    return [
        'state'=>$row && $row['lifecycle_state'] === 'completed'
            ? ($row['relation_type'] === 'corrected_reissue' ? 'replaced' : 'voided') : $status,
        'relation'=>$row['relation_type'] ?? ($incoming ? 'corrected_reissue' : null),
        'lifecycle_state'=>$row['lifecycle_state'] ?? null,
        'original_document_uuid'=>$incoming['original_document_uuid'] ?? ($row['original_document_uuid'] ?? null),
        'replacement_document_uuid'=>$row['replacement_document_uuid'] ?? null,
        'reason_code'=>$row['reason_code'] ?? null,
        'reason_detail'=>$row['reason_detail'] ?? null,
        'explanatory_note'=>$row['explanatory_note'] ?? null,
        'actor_user_id'=>$row['actor_user_id'] ?? null,
        'created_at'=>$row['created_at'] ?? null,
        'voided_at'=>$row['voided_at'] ?? null,
        'previous_status'=>$row['previous_status'] ?? null,
        'replacement_generated_at'=>$row['replacement_generated_at'] ?? null,
    ];
}

function clinical_prescription_lifecycle_source(PDO $pdo, array $doctor, string $patientId,
    string $uuid): array
{
    if (!preg_match('/^[0-9a-f-]{36}$/iD', $uuid)) throw new InvalidArgumentException('PRESCRIPTION_UUID_INVALID');
    if (!clinical_has_active_doctor_patient_link($pdo, (string)$doctor['doctor_id'], $patientId))
        throw new InvalidArgumentException('PRESCRIPTION_PATIENT_SCOPE_INVALID');
    $stmt = $pdo->prepare('SELECT id,document_uuid,document_type,status,patient_id,created_by_user_id,
        payload_json,rendered_text,version,generated_at,encounter_ref_id
        FROM clinical_documents WHERE document_uuid=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$uuid]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$source || !in_array((string)$source['document_type'], ['prescription','receta','rx'], true)
        || (string)$source['patient_id'] !== $patientId
        || (string)$source['created_by_user_id'] !== (string)$doctor['user_id'])
        throw new InvalidArgumentException('PRESCRIPTION_SOURCE_SCOPE_INVALID');
    return $source;
}

function clinical_prescription_lifecycle_void(PDO $pdo, array $doctor, string $patientId,
    string $uuid, array $body): array
{
    [$code,$detail,$note] = clinical_prescription_lifecycle_reason($body);
    $pdo->beginTransaction();
    try {
        $source = clinical_prescription_lifecycle_source($pdo,$doctor,$patientId,$uuid);
        $id = (int)$source['id'];
        $existing = clinical_prescription_lifecycle_row($pdo,$id);
        if ($existing) {
            if ($existing['relation_type'] === 'simple_void' && $existing['lifecycle_state'] === 'completed'
                && $existing['reason_code'] === $code && (string)$existing['reason_detail'] === $detail
                && (string)$existing['explanatory_note'] === $note) {
                $pdo->commit();
                return clinical_prescription_lifecycle_projection($pdo,$id);
            }
            throw new InvalidArgumentException('PRESCRIPTION_ALREADY_VOIDED_OR_REISSUING');
        }
        if (!in_array((string)$source['status'], ['generated','signed'], true))
            throw new InvalidArgumentException('PRESCRIPTION_VOID_STATUS_INVALID');
        $insert = $pdo->prepare("INSERT INTO clinical_prescription_lifecycle
            (original_document_id,relation_type,lifecycle_state,reason_code,reason_detail,
             explanatory_note,actor_user_id,created_at,voided_at,previous_status)
            VALUES (?,'simple_void','completed',?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),?)");
        $insert->execute([$id,$code,$detail ?: null,$note ?: null,(string)$doctor['user_id'],(string)$source['status']]);
        $update = $pdo->prepare("UPDATE clinical_documents SET status='voided',updated_at=UTC_TIMESTAMP(),
            updated_by_user_id=? WHERE id=? AND status IN ('generated','signed')");
        $update->execute([(string)$doctor['user_id'],$id]);
        if ($update->rowCount() !== 1) throw new RuntimeException('PRESCRIPTION_VOID_CONFLICT');
        $pdo->commit();
        return clinical_prescription_lifecycle_projection($pdo,$id);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function clinical_prescription_lifecycle_reissue(PDO $pdo, array $doctor, string $patientId,
    string $uuid, array $body): array
{
    [$code,$detail,$note] = clinical_prescription_lifecycle_reason($body);
    $pdo->beginTransaction();
    try {
        $source = clinical_prescription_lifecycle_source($pdo,$doctor,$patientId,$uuid);
        $id = (int)$source['id'];
        $existing = clinical_prescription_lifecycle_row($pdo,$id);
        if ($existing) {
            if ($existing['relation_type'] === 'corrected_reissue'
                && $existing['lifecycle_state'] === 'pending'
                && $existing['reason_code'] === $code && (string)$existing['reason_detail'] === $detail
                && (string)$existing['explanatory_note'] === $note) {
                $replacement = clinical_documents_get_fetch($pdo,(int)$existing['replacement_document_id']);
                $pdo->commit();
                return ['document'=>$replacement,'lifecycle'=>clinical_prescription_lifecycle_projection($pdo,$id)];
            }
            throw new InvalidArgumentException('PRESCRIPTION_ALREADY_VOIDED_OR_REISSUING');
        }
        if (!in_array((string)$source['status'], ['generated','signed'], true))
            throw new InvalidArgumentException('PRESCRIPTION_REISSUE_STATUS_INVALID');
        $sourcePayload = json_decode((string)$source['payload_json'],true);
        if (!is_array($sourcePayload) || !is_array($sourcePayload['prescription']['items'] ?? null)
            || !array_is_list($sourcePayload['prescription']['items'])
            || $sourcePayload['prescription']['items'] === [])
            throw new InvalidArgumentException('PRESCRIPTION_REISSUE_SOURCE_UNSTRUCTURED');
        $items = (array)($sourcePayload['prescription']['items'] ?? []);
        foreach ($items as &$item) if (is_array($item)) $item['private_notes'] = '';
        unset($item);
        $copyBody = ['document_type'=>'prescription','context'=>['patient_id'=>$patientId],
            'payload'=>['contract_version'=>2,
                'prescription'=>['items'=>$items,
                    'observaciones'=>$sourcePayload['prescription']['observaciones'] ?? ''],
                'diagnostic_context'=>$sourcePayload['diagnostic_context'] ?? ['mode'=>'sin_diagnostico','text'=>''],
                'presentation'=>$sourcePayload['presentation'] ?? ['version'=>1,'professional_header'=>'shown']]];
        $payload = clinical_prescription_v2_normalize($pdo,$doctor,$patientId,$copyBody,false);
        $html = clinical_prescription_v2_render($payload);
        $payload['prescription_snapshot'] = ['version'=>1,'html'=>$html];
        $doc = mxmed_build_clinical_document(['type'=>'prescription','title'=>'Receta médica',
            'context'=>['patient_id'=>$patientId,'care_setting'=>'consulta'],
            'payload'=>[],'actor'=>['user_id'=>(string)$doctor['user_id']]]);
        $doc['content']['payload'] = $payload;
        $doc['content']['rendered_text'] = $html;
        $doc['content']['summary'] = 'Borrador de receta corregida';
        $doc['status'] = 'draft';
        $newId = mxmed_persist_clinical_document_in_transaction($pdo,$doc);
        $insert = $pdo->prepare("INSERT INTO clinical_prescription_lifecycle
            (original_document_id,replacement_document_id,relation_type,lifecycle_state,reason_code,
             reason_detail,explanatory_note,actor_user_id,created_at,previous_status)
            VALUES (?,?,'corrected_reissue','pending',?,?,?,?,UTC_TIMESTAMP(),?)");
        $insert->execute([$id,$newId,$code,$detail ?: null,$note ?: null,(string)$doctor['user_id'],(string)$source['status']]);
        $pdo->commit();
        return ['document'=>clinical_documents_get_fetch($pdo,$newId),
            'lifecycle'=>clinical_prescription_lifecycle_projection($pdo,$id)];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Called inside the V2 finalization transaction, after the new row becomes generated. */
function clinical_prescription_lifecycle_complete_reissue(PDO $pdo, int $replacementId, array $doctor): void
{
    $stmt = $pdo->prepare("SELECT l.original_document_id,l.previous_status FROM clinical_prescription_lifecycle l
        WHERE l.replacement_document_id=? AND l.relation_type='corrected_reissue'
        AND l.lifecycle_state='pending' FOR UPDATE");
    $stmt->execute([$replacementId]);
    $lineage = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $originalId = (int)($lineage['original_document_id'] ?? 0);
    if ($originalId === 0) return;
    $update = $pdo->prepare("UPDATE clinical_documents SET status='voided',updated_at=UTC_TIMESTAMP(),
        updated_by_user_id=? WHERE id=? AND status=?");
    $update->execute([(string)$doctor['user_id'],$originalId,(string)$lineage['previous_status']]);
    if ($update->rowCount() !== 1) throw new RuntimeException('PRESCRIPTION_REISSUE_ORIGINAL_CONFLICT');
    $complete = $pdo->prepare("UPDATE clinical_prescription_lifecycle SET lifecycle_state='completed',
        voided_at=UTC_TIMESTAMP() WHERE replacement_document_id=? AND lifecycle_state='pending'");
    $complete->execute([$replacementId]);
    if ($complete->rowCount() !== 1) throw new RuntimeException('PRESCRIPTION_REISSUE_LINEAGE_CONFLICT');
}
