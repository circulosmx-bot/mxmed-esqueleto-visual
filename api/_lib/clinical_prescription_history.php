<?php
declare(strict_types=1);

/** Patient-level read model shared by Recetas history and future recent-prescription cards. */
function clinical_prescription_history_fetch(PDO $pdo, string $patientId, string $actorUserId,
    string $filter = 'all', int $page = 1, int $limit = 20): array
{
    if (!in_array($filter, ['all','issued','voided'], true) || $page < 1 || $page > 10000
        || $limit < 1 || $limit > 50) throw new InvalidArgumentException('PRESCRIPTION_HISTORY_PARAMS_INVALID');
    $where = "d.patient_id=:patient
        AND d.document_type IN ('prescription','receta','rx') AND d.status IN ('generated','signed','voided')";
    if ($filter === 'issued') $where .= " AND d.status IN ('generated','signed')";
    if ($filter === 'voided') $where .= " AND d.status='voided'";
    $count = $pdo->prepare("SELECT COUNT(*) FROM clinical_documents d WHERE $where");
    $count->execute([':patient'=>$patientId]);
    $total = (int)$count->fetchColumn();
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare("SELECT d.id,d.document_uuid,d.document_type,d.version,d.status,d.event_datetime,
        d.generated_at,d.created_at,d.created_by_user_id,d.payload_json,d.summary,
        l.relation_type,l.lifecycle_state,l.reason_code,l.voided_at,
        source.document_uuid AS corrects_prescription_uuid,source.event_datetime AS source_event_datetime,
        replacement.document_uuid AS replaced_by_prescription_uuid,
        replacement.event_datetime AS replacement_event_datetime
        FROM clinical_documents d
        LEFT JOIN clinical_prescription_lifecycle l ON l.original_document_id=d.id
        LEFT JOIN clinical_documents replacement ON replacement.id=l.replacement_document_id
        LEFT JOIN clinical_prescription_lifecycle incoming ON incoming.replacement_document_id=d.id
        LEFT JOIN clinical_documents source ON source.id=incoming.original_document_id
        WHERE $where ORDER BY COALESCE(d.generated_at,d.event_datetime,d.created_at) DESC,d.id DESC
        LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':patient',$patientId,PDO::PARAM_STR);
    $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
    $stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
    $stmt->execute();
    $items=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload=json_decode((string)$row['payload_json'],true);
        if (!is_array($payload)) $payload=[];
        $names=[];
        foreach ((array)($payload['prescription']['items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $name=trim((string)($item['name'] ?? $item['nombre'] ?? ''));
            if ($name !== '') $names[]=$name;
        }
        $names=array_values(array_unique($names));
        $summary=$names === [] ? 'Medicamentos no detallados' : implode(' · ',array_slice($names,0,2));
        if (count($names)>2) $summary .= ' + '.(count($names)-2).' más';
        $isLegacy=(int)($payload['contract_version'] ?? 0) !== 2;
        $items[]=[
            'document_uuid'=>(string)$row['document_uuid'],
            'version'=>(int)$row['version'],
            'status'=>(string)$row['status'],
            'can_manage'=>(string)$row['created_by_user_id'] === $actorUserId,
            'lifecycle_state'=>(string)($row['lifecycle_state'] ?? ''),
            'relation_type'=>$row['relation_type'],
            'is_legacy'=>$isLegacy,
            'reissue_eligible'=>(string)$row['created_by_user_id'] === $actorUserId
                && is_array($payload['prescription']['items'] ?? null)
                && count($payload['prescription']['items']) > 0,
            'event_datetime'=>(string)($row['generated_at'] ?: $row['event_datetime'] ?: $row['created_at']),
            'medication_summary'=>$summary,
            'physician_name'=>(string)($payload['physician_snapshot']['full_name'] ?? $payload['snapshot']['medico']['nombre_completo'] ?? ''),
            'corrects_prescription_uuid'=>$row['corrects_prescription_uuid'],
            'source_event_datetime'=>$row['source_event_datetime'],
            'replaced_by_prescription_uuid'=>$row['replaced_by_prescription_uuid'],
            'replacement_event_datetime'=>$row['replacement_event_datetime'],
            'reason_code'=>$row['reason_code'],
            'voided_at'=>$row['voided_at'],
        ];
    }
    return ['items'=>$items,'page'=>$page,'page_size'=>$limit,'total'=>$total,
        'has_next'=>$offset+count($items)<$total,'filter'=>$filter];
}

function clinical_prescription_recent_fetch(PDO $pdo, string $patientId, string $actorUserId,
    int $limit = 5): array
{
    return clinical_prescription_history_fetch($pdo,$patientId,$actorUserId,'all',1,$limit)['items'];
}
