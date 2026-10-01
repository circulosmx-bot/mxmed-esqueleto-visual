<?php
declare(strict_types=1);

/** Optional, read-only projection for the general Orders and Results tab. */
function clinical_or_order_types(): array
{
    return ['order', 'orders', 'lab_order', 'imaging_order', 'orden_estudio'];
}

function clinical_or_result_types(): array
{
    return ['lab_result', 'lab_pdf', 'imaging_result', 'external_result', 'external_report', 'result'];
}

function clinical_or_cursor_encode(string $at, int $id): string
{
    return base64_encode((string)json_encode(['at' => $at, 'id' => $id], JSON_THROW_ON_ERROR));
}

function clinical_or_cursor_decode(string $raw): ?array
{
    if ($raw === '' || strlen($raw) > 512) return null;
    $decoded = base64_decode($raw, true);
    $value = $decoded === false ? null : json_decode($decoded, true);
    if (!is_array($value) || !is_string($value['at'] ?? null)
        || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value['at'])
        || !is_int($value['id'] ?? null) || $value['id'] < 1) return null;
    return ['at' => $value['at'], 'id' => $value['id']];
}

function clinical_or_schema_guard(PDO $pdo): void
{
    $columns = $pdo->query('SHOW COLUMNS FROM clinical_documents')->fetchAll(PDO::FETCH_COLUMN);
    $required = ['id','document_uuid','document_type','title','summary','version','status','patient_id',
        'encounter_id','encounter_ref_id','appointment_id','hospital_stay_id','payload_json',
        'event_datetime','created_at','generated_at'];
    if (array_diff($required, $columns)) throw new RuntimeException('ORDER_RESULT_READER_SCHEMA_NOT_READY');
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('clinical_document_revisions','clinical_document_binaries')")->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff(['clinical_document_revisions','clinical_document_binaries'], $tables)) throw new RuntimeException('ORDER_RESULT_READER_SCHEMA_NOT_READY');
}

function clinical_or_type_sql(array $types): string
{
    return implode(',', array_map(static fn(string $type): string => "'" . $type . "'", $types));
}

function clinical_or_cte(PDO $pdo, string $patientId): string
{
    $patient = $pdo->quote($patientId);
    $orders = clinical_or_type_sql(clinical_or_order_types());
    $results = clinical_or_type_sql(clinical_or_result_types());
    $successor = "NOT EXISTS (SELECT 1 FROM clinical_document_revisions s WHERE s.supersedes_document_id=d.id OR (s.original_document_id=d.id AND s.supersedes_document_id IS NULL))";
    $refs = [
        '$.related_order_document_id', '$.related_order_document_uuid',
        '$.related_document_id', '$.related_document_uuid', '$.related_order_id',
        '$.context.related_order_document_id', '$.context.related_order_document_uuid',
        '$.context.related_document_id', '$.context.related_document_uuid',
    ];
    $refValues = implode(',', array_map(static fn(string $path): string => "JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'{$path}'))", $refs));
    return "WITH order_versions AS (
        SELECT d.id,d.document_uuid,COALESCE(v.original_document_id,d.id) AS root_id
        FROM clinical_documents d LEFT JOIN clinical_document_revisions v ON v.new_document_id=d.id
        WHERE d.patient_id={$patient} AND d.document_type IN ({$orders})
    ), current_orders AS (
        SELECT d.id,COALESCE(v.original_document_id,d.id) AS root_id,
          CASE WHEN d.generated_at IS NOT NULL AND d.generated_at>'1000-01-01 00:00:00' THEN d.generated_at ELSE d.created_at END AS sort_at
        FROM clinical_documents d LEFT JOIN clinical_document_revisions v ON v.new_document_id=d.id
        WHERE d.patient_id={$patient} AND d.document_type IN ({$orders}) AND {$successor}
    ), current_results AS (
        SELECT d.id,d.payload_json,d.created_at AS sort_at
        FROM clinical_documents d WHERE d.patient_id={$patient} AND d.document_type IN ({$results}) AND {$successor}
    ), result_refs AS (
        SELECT r.id AS result_id,j.ref
        FROM current_results r JOIN JSON_TABLE(JSON_ARRAY({$refValues}), '$[*]' COLUMNS (ref VARCHAR(128) PATH '$')) j
        WHERE j.ref IS NOT NULL AND j.ref<>'' AND j.ref<>'null'
    ), result_links AS (
        SELECT rr.result_id,CASE WHEN COUNT(DISTINCT ov.root_id)=1 THEN MIN(ov.root_id) ELSE NULL END AS root_id
        FROM result_refs rr JOIN order_versions ov ON rr.ref COLLATE utf8mb4_unicode_ci=CAST(ov.id AS CHAR) COLLATE utf8mb4_unicode_ci OR rr.ref COLLATE utf8mb4_unicode_ci=ov.document_uuid COLLATE utf8mb4_unicode_ci
        GROUP BY rr.result_id
    ), result_relation AS (
        SELECT r.id,r.sort_at,COUNT(rr.ref) AS ref_count,MIN(rl.root_id) AS root_id,MIN(co.id) AS order_id
        FROM current_results r LEFT JOIN result_refs rr ON rr.result_id=r.id
        LEFT JOIN result_links rl ON rl.result_id=r.id
        LEFT JOIN current_orders co ON co.root_id=rl.root_id
        GROUP BY r.id,r.sort_at
    )";
}

function clinical_or_search_sql(PDO $pdo, string $search, string $alias, bool $study = false): string
{
    if ($search === '') return '1=1';
    $escaped = str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $search);
    $pattern = $pdo->quote('%' . $escaped . '%');
    $base = "({$alias}.title LIKE {$pattern} ESCAPE '\\\\' OR {$alias}.summary LIKE {$pattern} ESCAPE '\\\\' OR {$alias}.document_type LIKE {$pattern} ESCAPE '\\\\'";
    $labels = $study
        ? " OR CASE {$alias}.document_type WHEN 'lab_order' THEN 'Orden de laboratorio' WHEN 'imaging_order' THEN 'Orden de imagen' ELSE 'Orden de estudios' END LIKE {$pattern} ESCAPE '\\\\'"
        : " OR CASE {$alias}.document_type WHEN 'lab_result' THEN 'Resultado de laboratorio' WHEN 'imaging_result' THEN 'Resultado de imagen' ELSE 'Resultado de estudio' END LIKE {$pattern} ESCAPE '\\\\'";
    $studies = $study ? " OR JSON_SEARCH({$alias}.payload_json,'one',{$pattern},'\\\\','$.requested_studies[*]') IS NOT NULL" : '';
    return $base . $labels . $studies . ')';
}

function clinical_or_document(array $row): array
{
    $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
    $payload = is_array($payload) ? $payload : [];
    $scope = trim((string)($row['encounter_ref_id'] ?? '')) !== '' || trim((string)($row['encounter_id'] ?? '')) !== ''
        ? 'ENCOUNTER' : (trim((string)($row['appointment_id'] ?? '')) !== '' ? 'APPOINTMENT'
            : (trim((string)($row['hospital_stay_id'] ?? '')) !== '' ? 'HOSPITAL_STAY' : 'PATIENT'));
    $isOrder = in_array($row['document_type'], clinical_or_order_types(), true);
    return [
        'id' => (int)$row['id'], 'document_uuid' => $row['document_uuid'],
        'document_type' => $row['document_type'], 'title' => $row['title'], 'summary' => $row['summary'],
        'version' => (int)$row['version'], 'status' => $row['status'],
        'lineage_root_id' => (int)$row['lineage_root_id'], 'has_successor' => 0,
        'encounter_id' => $row['encounter_id'], 'encounter_ref_id' => $row['encounter_ref_id'],
        'appointment_id' => $row['appointment_id'], 'hospital_stay_id' => $row['hospital_stay_id'],
        'source_scope' => $scope, 'created_at' => $row['created_at'], 'generated_at' => $row['generated_at'],
        'event_datetime' => $row['event_datetime'], 'has_private_binary' => (int)$row['has_private_binary'],
        'chronology_at' => $isOrder && $row['generated_at'] !== null && $row['generated_at'] > '1000-01-01 00:00:00'
            ? $row['generated_at'] : $row['created_at'],
        'chronology_source' => $isOrder && $row['generated_at'] !== null && $row['generated_at'] > '1000-01-01 00:00:00'
            ? 'generated_at' : 'created_at',
        'requested_studies' => $isOrder && is_array($payload['requested_studies'] ?? null)
            ? array_values(array_filter($payload['requested_studies'], 'is_string')) : [],
        'result_origin' => !$isOrder && is_string($payload['result_origin'] ?? null) ? $payload['result_origin'] : null,
        'related_order_document_id' => null,
    ];
}

function clinical_or_list_fetch(PDO $pdo, string $patientId, int $limit, string $filter, string $search, ?array $cursor): array
{
    clinical_or_schema_guard($pdo);
    $cte = clinical_or_cte($pdo, $patientId);
    $orderSearch = clinical_or_search_sql($pdo, $search, 'd', true);
    $resultSearch = clinical_or_search_sql($pdo, $search, 'd');
    $resultMatch = "EXISTS (SELECT 1 FROM result_relation rel JOIN clinical_documents d ON d.id=rel.id WHERE rel.order_id=o.id AND {$resultSearch})";
    $orderCandidates = "SELECT 'ORDER' AS kind,o.id,o.sort_at FROM current_orders o JOIN clinical_documents d ON d.id=o.id WHERE o.sort_at IS NOT NULL AND ({$orderSearch} OR {$resultMatch})";
    $standaloneCandidates = "SELECT CASE WHEN rel.ref_count=0 THEN 'STANDALONE_RESULT' ELSE 'UNRESOLVED_RESULT' END AS kind,rel.id,rel.sort_at
        FROM result_relation rel JOIN clinical_documents d ON d.id=rel.id
        WHERE rel.order_id IS NULL AND rel.sort_at IS NOT NULL AND {$resultSearch}";
    $resultCandidates = "SELECT 'RESULT' AS kind,rel.id,rel.sort_at FROM result_relation rel JOIN clinical_documents d ON d.id=rel.id WHERE rel.sort_at IS NOT NULL AND {$resultSearch}";
    $union = $filter === 'orders' ? $orderCandidates : ($filter === 'results' ? $resultCandidates : "{$orderCandidates} UNION ALL {$standaloneCandidates}");
    $cursorWhere = $cursor === null ? '' : 'WHERE p.sort_at<:cursor_at OR (p.sort_at=:cursor_at_tie AND p.id<:cursor_id)';
    $sql = "{$cte} SELECT p.kind,p.id,p.sort_at FROM ({$union}) p {$cursorWhere} ORDER BY p.sort_at DESC,p.id DESC LIMIT :fetch_limit";
    $stmt = $pdo->prepare($sql);
    if ($cursor !== null) {
        $stmt->bindValue(':cursor_at', $cursor['at']);
        $stmt->bindValue(':cursor_at_tie', $cursor['at']);
        $stmt->bindValue(':cursor_id', $cursor['id'], PDO::PARAM_INT);
    }
    $stmt->bindValue(':fetch_limit', $limit + 1, PDO::PARAM_INT);
    $stmt->execute();
    $page = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($page) > $limit;
    $page = array_slice($page, 0, $limit);
    if (!$page) return ['items'=>[], 'has_more'=>false, 'cursor_next'=>null, 'limit'=>$limit, 'filter'=>$filter,
        'order_types'=>clinical_or_order_types(),'result_types'=>clinical_or_result_types()];
    $orderIds = array_map('intval', array_column(array_filter($page, static fn(array $item): bool => $item['kind']==='ORDER'), 'id'));
    $childIds = [];
    if ($orderIds) {
        $in = implode(',', $orderIds);
        $childRows = $pdo->query("{$cte} SELECT rel.id,rel.order_id FROM result_relation rel WHERE rel.order_id IN ({$in}) ORDER BY rel.sort_at DESC,rel.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($childRows as $child) $childIds[(int)$child['order_id']][] = (int)$child['id'];
    }
    $ids = array_unique(array_merge(array_map('intval', array_column($page, 'id')), ...array_values($childIds ?: [[]])));
    $in = implode(',', $ids);
    $documentRows = $pdo->query("SELECT d.id,d.document_uuid,d.document_type,d.title,d.summary,d.version,d.status,d.encounter_id,d.encounter_ref_id,
        d.appointment_id,d.hospital_stay_id,d.payload_json,d.event_datetime,d.created_at,d.generated_at,
        COALESCE(v.original_document_id,d.id) AS lineage_root_id,
        EXISTS(SELECT 1 FROM clinical_document_binaries b WHERE b.document_id=d.id AND b.variant_role='ORIGINAL' AND b.variant_version=1) AS has_private_binary
        FROM clinical_documents d LEFT JOIN clinical_document_revisions v ON v.new_document_id=d.id WHERE d.id IN ({$in})")->fetchAll(PDO::FETCH_ASSOC);
    $docs = [];
    foreach ($documentRows as $row) $docs[(int)$row['id']] = clinical_or_document($row);
    $roots = array_unique(array_column($docs, 'lineage_root_id'));
    $rootIn = implode(',', array_map('intval', $roots));
    $versionRows = $pdo->query("SELECT d.id,d.document_uuid,d.title,d.version,d.status,d.event_datetime,d.created_at,
        COALESCE(v.original_document_id,d.id) AS root_id,
        EXISTS(SELECT 1 FROM clinical_document_binaries b WHERE b.document_id=d.id AND b.variant_role='ORIGINAL' AND b.variant_version=1) AS has_private_binary
        FROM clinical_documents d LEFT JOIN clinical_document_revisions v ON v.new_document_id=d.id
        WHERE d.patient_id=" . $pdo->quote($patientId) . " AND (d.id IN ({$rootIn}) OR v.original_document_id IN ({$rootIn}))
        ORDER BY d.id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $versions = [];
    foreach ($versionRows as $version) {
        $versions[(int)$version['root_id']][] = [
            'id'=>(int)$version['id'],'document_uuid'=>$version['document_uuid'],'title'=>$version['title'],
            'version'=>(int)$version['version'],'status'=>$version['status'],
            'event_datetime'=>$version['event_datetime'],'created_at'=>$version['created_at'],
            'has_private_binary'=>(int)$version['has_private_binary'],
        ];
    }
    foreach ($docs as &$doc) $doc['versions'] = $versions[$doc['lineage_root_id']] ?? [];
    unset($doc);
    $resultOrder = [];
    if ($filter === 'results') {
        $resultIds = implode(',', array_map('intval', array_column($page, 'id')));
        $links = $pdo->query("{$cte} SELECT id,order_id FROM result_relation WHERE id IN ({$resultIds})")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($links as $link) $resultOrder[(int)$link['id']] = $link['order_id'] === null ? null : (int)$link['order_id'];
    }
    $items = [];
    foreach ($page as $candidate) {
        $id = (int)$candidate['id'];
        if ($candidate['kind'] === 'ORDER') {
            $results = [];
            foreach ($childIds[$id] ?? [] as $childId) {
                $child = $docs[$childId];
                $child['related_order_document_id'] = $id;
                $results[] = $child;
            }
            $items[] = ['kind'=>'ORDER','order'=>$docs[$id],'result_count'=>count($results),'results'=>$results];
        } else {
            $result = $docs[$id];
            if ($candidate['kind'] === 'RESULT') {
                // A linked result can also be opened directly in result-filter mode.
                $result['related_order_document_id'] = $resultOrder[$id] ?? null;
            }
            $items[] = ['kind'=>$candidate['kind'],'result'=>$result];
        }
    }
    $last = $page[count($page)-1];
    return ['items'=>$items,'has_more'=>$hasMore,
        'cursor_next'=>$hasMore ? clinical_or_cursor_encode((string)$last['sort_at'], (int)$last['id']) : null,
        'limit'=>$limit,'filter'=>$filter,
        'order_types'=>clinical_or_order_types(),'result_types'=>clinical_or_result_types()];
}
