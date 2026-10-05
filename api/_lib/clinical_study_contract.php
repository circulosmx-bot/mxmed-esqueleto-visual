<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_dental_location.php';
require_once __DIR__ . '/clinical_specimen_requirements.php';
require_once __DIR__ . '/clinical_pathology_parameters.php';

/** TAX03A: document-payload study identity. No legacy payload is rewritten. */
function clinical_study_categories(): array
{
    return ['LABORATORIO','IMAGEN','CARDIOVASCULAR','OFTALMOLOGIA','NEUROFISIOLOGIA',
        'FUNCION_PULMONAR','AUDIOLOGIA','DENTAL','PATOLOGIA','ENDOSCOPIA','SUENO','GENETICA','OTROS'];
}

function clinical_study_category_labels_es(): array
{
    return [
        'LABORATORIO'=>'Laboratorio', 'IMAGEN'=>'Imagenología', 'CARDIOVASCULAR'=>'Cardiovascular',
        'OFTALMOLOGIA'=>'Oftalmología', 'NEUROFISIOLOGIA'=>'Neurofisiología',
        'FUNCION_PULMONAR'=>'Función pulmonar', 'AUDIOLOGIA'=>'Audiología', 'DENTAL'=>'Dental',
        'PATOLOGIA'=>'Patología', 'ENDOSCOPIA'=>'Endoscopía', 'SUENO'=>'Medicina del sueño',
        'GENETICA'=>'Genética', 'OTROS'=>'Otros',
    ];
}

function clinical_study_order_type(string $type): bool
{
    return in_array(strtolower(trim($type)), ['lab_order','imaging_order','orders','order','orden_estudio'], true);
}

function clinical_study_result_type(string $type): bool
{
    return in_array(strtolower(trim($type)), ['lab_result','lab_pdf','imaging_result','result','external_result','external_report'], true);
}

function clinical_study_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function clinical_study_valid_uuid(string $id): bool
{
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $id) === 1;
}

function clinical_study_text($value, int $max, string $error): string
{
    if (!is_string($value)) throw new InvalidArgumentException($error);
    $text = trim($value);
    if ($text === '' || mb_strlen($text) > $max) throw new InvalidArgumentException($error);
    return $text;
}

function clinical_study_optional_text($value, int $max, string $error): ?string
{
    if ($value === null || $value === '') return null;
    return clinical_study_text($value, $max, $error);
}

function clinical_study_order_snapshot(PDO $pdo, array $input, int $sequence): array
{
    if (array_key_exists('sequence', $input) && (int)$input['sequence'] !== $sequence) {
        throw new InvalidArgumentException('ORDER_ITEM_SEQUENCE_INVALID');
    }
    $typeId = $input['study_type_id'] ?? null;
    $requestedKey = $input['study_type_key'] ?? null;
    $key = null;
    $category = null;
    $name = null;
    if (($typeId !== null && $typeId !== '') || ($requestedKey !== null && $requestedKey !== '')) {
        if ($typeId !== null && $typeId !== '' && !is_int($typeId) && !(is_string($typeId) && ctype_digit($typeId))) {
            throw new InvalidArgumentException('STUDY_TYPE_INVALID');
        }
        if ($requestedKey !== null && $requestedKey !== '' && !is_string($requestedKey)) {
            throw new InvalidArgumentException('STUDY_TYPE_INVALID');
        }
        $byId = $typeId !== null && $typeId !== '';
        $stmt = $pdo->prepare('SELECT study_type_id,study_type_key,display_name_es,category_key,is_active FROM clinical_study_types WHERE '.($byId?'study_type_id=?':'study_type_key=?'));
        $stmt->execute([$byId ? (int)$typeId : $requestedKey]);
        $catalog = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($catalog) || (int)$catalog['is_active'] !== 1) throw new InvalidArgumentException('STUDY_TYPE_INVALID');
        $typeId = (int)$catalog['study_type_id'];
        $key = (string)$catalog['study_type_key'];
        $category = (string)$catalog['category_key'];
        $name = (string)$catalog['display_name_es'];
        if ($requestedKey !== null && $requestedKey !== '' && $requestedKey !== $key) throw new InvalidArgumentException('STUDY_TYPE_MISMATCH');
    } else {
        $typeId = null;
        $category = clinical_study_text($input['study_category'] ?? null, 32, 'STUDY_CATEGORY_REQUIRED');
        $name = clinical_study_text($input['study_display_name'] ?? null, 255, 'STUDY_NAME_REQUIRED');
    }
    if (!in_array($category, clinical_study_categories(), true)) throw new InvalidArgumentException('STUDY_CATEGORY_INVALID');
    $note = clinical_study_optional_text($input['note'] ?? null, 1000, 'STUDY_NOTE_INVALID');
    $system = clinical_study_optional_text($input['external_code_system'] ?? null, 190, 'STUDY_EXTERNAL_CODE_INVALID');
    $code = clinical_study_optional_text($input['external_code'] ?? null, 128, 'STUDY_EXTERNAL_CODE_INVALID');
    $version = clinical_study_optional_text($input['external_code_version'] ?? null, 80, 'STUDY_EXTERNAL_CODE_INVALID');
    if (($system === null) !== ($code === null) || ($version !== null && $code === null)) {
        throw new InvalidArgumentException('STUDY_EXTERNAL_CODE_INVALID');
    }
    if ($code !== null) {
        if ($typeId === null) throw new InvalidArgumentException('STUDY_EXTERNAL_CODE_UNVERIFIED');
        $stmt = $pdo->prepare('SELECT mapping_id FROM clinical_study_type_external_codes WHERE study_type_id=? AND code_system=? AND code=? AND code_version_norm=? AND verified_at IS NOT NULL');
        $stmt->execute([$typeId,$system,$code,$version ?? '']);
        if ($stmt->fetchColumn() === false) throw new InvalidArgumentException('STUDY_EXTERNAL_CODE_UNVERIFIED');
    }
    $dentalLocation = clinical_dental_validate_location($key, $input['dental_location'] ?? null);
    $specimenRequirements = clinical_specimen_validate($key, $input['specimen_collection_requirements'] ?? null);
    $pathologyParameters = clinical_pathology_validate($key, $input['pathology_order_parameters'] ?? null);
    return [
        'order_item_id' => null, 'sequence' => $sequence,
        'study_type_id' => $typeId, 'study_type_key' => $key,
        'study_category' => $category, 'study_display_name' => $name,
        'external_code_system' => $system, 'external_code' => $code,
        'external_code_version' => $version, 'note' => $note,
        ...($dentalLocation === null ? [] : [
            'dental_location' => $dentalLocation,
            'dental_location_label' => clinical_dental_location_summary($dentalLocation),
        ]),
        ...($specimenRequirements === null ? [] : ['specimen_collection_requirements' => $specimenRequirements]),
        ...($pathologyParameters === null ? [] : [
            'pathology_order_parameters' => $pathologyParameters,
            'pathology_order_parameters_label' => clinical_pathology_summary($pathologyParameters, $key),
        ]),
    ];
}

/** $source is the exact order version being replaced, if any. */
function clinical_study_normalize_order_payload(PDO $pdo, string $documentType, array $payload, ?array $source = null): array
{
    if (!clinical_study_order_type($documentType)) return $payload;
    $structured = array_key_exists('order_items', $payload);
    $legacy = array_key_exists('requested_studies', $payload);
    if ($source !== null && (int)($source['order_payload_version'] ?? 0) === 2 && !$structured && !$legacy) {
        throw new InvalidArgumentException('ORDER_ITEMS_REQUIRED');
    }
    if (!$structured && !$legacy) return $payload; // Other historical order shapes remain untouched.
    $inputs = $structured ? $payload['order_items'] : $payload['requested_studies'];
    if (!is_array($inputs) || !array_is_list($inputs) || $inputs === [] || count($inputs) > 100) {
        throw new InvalidArgumentException('ORDER_ITEMS_INVALID');
    }
    $fallbackCategory = match (strtolower($documentType)) {
        'lab_order' => 'LABORATORIO', 'imaging_order' => 'IMAGEN', default => null,
    };
    $prior = [];
    foreach ((array)($source['order_items'] ?? []) as $old) {
        if (is_array($old) && is_string($old['order_item_id'] ?? null)) $prior[$old['order_item_id']] = $old;
    }
    $seen = [];
    $items = [];
    foreach ($inputs as $index => $raw) {
        if ($structured) {
            if (!is_array($raw)) throw new InvalidArgumentException('ORDER_ITEMS_INVALID');
            $claimed = $raw['order_item_id'] ?? null;
            if ($claimed !== null && is_string($claimed) && isset($prior[$claimed])) {
                // Preserve the issued snapshot even when the current catalog has changed or become inactive.
                $old = $prior[$claimed];
                $item = $old;
                $item['sequence'] = $index + 1;
                foreach (['study_type_id','study_type_key','study_category','study_display_name','external_code_system','external_code','external_code_version','note','dental_location','dental_location_label','specimen_collection_requirements','pathology_order_parameters','pathology_order_parameters_label'] as $field) {
                    if (($raw[$field] ?? null) !== ($old[$field] ?? null)) throw new InvalidArgumentException('ORDER_ITEM_ID_MEANING_CHANGED');
                }
            } else {
                $item = clinical_study_order_snapshot($pdo, $raw, $index + 1);
            }
        } else {
            $name = clinical_study_text($raw, 255, 'STUDY_NAME_REQUIRED');
            if ($fallbackCategory === null) throw new InvalidArgumentException('STUDY_CATEGORY_REQUIRED');
            $item = clinical_study_order_snapshot($pdo, [
                'study_category' => $fallbackCategory, 'study_display_name' => $name,
            ], $index + 1);
            $claimed = null;
        }
        if ($claimed !== null) {
            if (!is_string($claimed) || !clinical_study_valid_uuid($claimed)) throw new InvalidArgumentException('ORDER_ITEM_ID_INVALID');
            if (isset($seen[$claimed])) throw new InvalidArgumentException('ORDER_ITEM_ID_DUPLICATE');
            if (!isset($prior[$claimed])) throw new InvalidArgumentException('ORDER_ITEM_ID_UNAUTHORIZED');
            $item['order_item_id'] = $claimed;
            $seen[$claimed] = true;
        } else {
            do { $item['order_item_id'] = clinical_study_uuid(); } while (isset($seen[$item['order_item_id']]));
            $seen[$item['order_item_id']] = true;
        }
        $items[] = $item;
    }
    $payload['order_payload_version'] = 2;
    $payload['order_items'] = $items;
    $payload['requested_studies'] = array_column($items, 'study_display_name');
    $payload['selection_count'] = count($items);
    return $payload;
}

/** Optional explicit coverage; absence retains the legacy whole-order relationship. */
function clinical_study_validate_result_payload(PDO $pdo, string $documentType, array $payload,
    string $patientId, ?string $encounterId = null, ?array $previous = null): array
{
    if (!clinical_study_result_type($documentType)) {
        if (array_key_exists('related_order_item_ids', $payload)) throw new InvalidArgumentException('RESULT_ITEM_IDS_NOT_ALLOWED');
        return $payload;
    }
    if (is_array($previous) && array_key_exists('related_order_item_ids', $previous)) {
        if (!array_key_exists('related_order_item_ids', $payload)) $payload['related_order_item_ids'] = $previous['related_order_item_ids'];
        if ($payload['related_order_item_ids'] !== $previous['related_order_item_ids']) {
            throw new InvalidArgumentException('RESULT_ITEM_LINK_IMMUTABLE');
        }
        foreach (['related_order_document_id','related_order_document_uuid','related_document_id','related_document_uuid','related_order_id'] as $field) {
            $originalRef = trim((string)($previous[$field] ?? ''));
            if ($originalRef === '') continue;
            if (!array_key_exists($field, $payload)) $payload[$field] = $previous[$field];
            if (trim((string)($payload[$field] ?? '')) !== $originalRef) {
                throw new InvalidArgumentException('RESULT_ORDER_VERSION_IMMUTABLE');
            }
        }
    }
    if (!array_key_exists('related_order_item_ids', $payload)) {
        // A standalone result can carry a single optional taxonomy snapshot.
        $hasTaxonomy = array_key_exists('study_category', $payload)
            || array_key_exists('study_type_id', $payload)
            || array_key_exists('study_type_key', $payload)
            || array_key_exists('study_display_name', $payload);
        if ($hasTaxonomy) {
            $snapshot = clinical_study_order_snapshot($pdo, $payload, 1);
            foreach (['study_type_id','study_type_key','study_category','study_display_name'] as $field) {
                $payload[$field] = $snapshot[$field];
            }
        }
        return $payload;
    }
    $ids = $payload['related_order_item_ids'];
    if (!is_array($ids) || !array_is_list($ids) || $ids === [] || count($ids) > 100) {
        throw new InvalidArgumentException('RESULT_ITEM_IDS_INVALID');
    }
    $seen = [];
    foreach ($ids as $id) {
        if (!is_string($id) || !clinical_study_valid_uuid($id)) throw new InvalidArgumentException('RESULT_ITEM_ID_INVALID');
        if (isset($seen[$id])) throw new InvalidArgumentException('RESULT_ITEM_ID_DUPLICATE');
        $seen[$id] = true;
    }
    $refs = [];
    foreach (['related_order_document_id','related_order_document_uuid','related_document_id','related_document_uuid','related_order_id'] as $field) {
        $value = $payload[$field] ?? null;
        if ($value !== null && trim((string)$value) !== '') $refs[] = trim((string)$value);
    }
    $refs = array_values(array_unique($refs));
    if ($refs === []) throw new InvalidArgumentException('RESULT_ORDER_REQUIRED');
    $order = null;
    foreach ($refs as $ref) {
        $stmt = $pdo->prepare('SELECT id,document_uuid,document_type,patient_id,encounter_id,encounter_ref_id,payload_json FROM clinical_documents WHERE id=? OR document_uuid=? LIMIT 1');
        $stmt->execute([ctype_digit($ref) ? (int)$ref : -1,$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !clinical_study_order_type((string)$row['document_type'])
            || ($order !== null && (int)$row['id'] !== (int)$order['id'])) {
            throw new InvalidArgumentException('RESULT_ORDER_VERSION_INVALID');
        }
        $order = $row;
    }
    if ((string)$order['patient_id'] !== $patientId) throw new InvalidArgumentException('RESULT_ORDER_PATIENT_MISMATCH');
    $orderEncounter = trim((string)($order['encounter_ref_id'] ?? $order['encounter_id'] ?? ''));
    if ($orderEncounter !== trim((string)$encounterId)) throw new InvalidArgumentException('RESULT_ORDER_SCOPE_MISMATCH');
    $orderPayload = json_decode((string)$order['payload_json'], true);
    if (!is_array($orderPayload) || (int)($orderPayload['order_payload_version'] ?? 0) !== 2) {
        throw new InvalidArgumentException('RESULT_ORDER_ITEMS_UNAVAILABLE');
    }
    $available = [];
    foreach ((array)($orderPayload['order_items'] ?? []) as $item) {
        if (is_array($item) && is_string($item['order_item_id'] ?? null)) $available[$item['order_item_id']] = true;
    }
    foreach ($ids as $id) if (!isset($available[$id])) throw new InvalidArgumentException('RESULT_ITEM_NOT_IN_ORDER');
    return $payload;
}
