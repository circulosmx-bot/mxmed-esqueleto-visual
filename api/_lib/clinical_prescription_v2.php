<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_documents.php';
require_once __DIR__ . '/clinical_portable_order.php';
require_once __DIR__ . '/clinical_legal_document_presentation.php';
require_once __DIR__ . '/clinical_prescription_binding.php';

/** The V2 payload is the only authoring and rendering authority for new prescriptions. */
function clinical_prescription_v2_text(mixed $value, int $limit = 4000): string
{
    if (!is_string($value) || !preg_match('//u', $value)) throw new InvalidArgumentException('PRESCRIPTION_TEXT_INVALID');
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    if (mb_strlen($value, 'UTF-8') > $limit || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value))
        throw new InvalidArgumentException('PRESCRIPTION_TEXT_INVALID');
    return $value;
}

function clinical_prescription_v2_encounter(PDO $pdo, string $key, string $patientId, string $doctorId): ?array
{
    if ($key === '') return null;
    $resolved = clinical_resolve_encounter_key($pdo, $key);
    $row = is_array($resolved['row'] ?? null) ? $resolved['row'] : null;
    if (($resolved['ok'] ?? false) !== true || $row === null || (string)$row['patient_id'] !== $patientId
        || (string)$row['doctor_id'] !== $doctorId || (string)$row['status'] !== 'open')
        throw new InvalidArgumentException('PRESCRIPTION_ENCOUNTER_SCOPE_INVALID');
    return $row;
}

function clinical_prescription_v2_normalize(PDO $pdo, array $doctor, string $patientId, array $body,
    bool $final = false): array
{
    $source = is_array($body['payload'] ?? null) ? $body['payload'] : [];
    if (($body['document_type'] ?? 'prescription') !== 'prescription'
        || (int)($source['contract_version'] ?? 0) !== 2
        || !clinical_legal_document_presentation_valid($source))
        throw new InvalidArgumentException('PRESCRIPTION_V2_CONTRACT_INVALID');
    $context = is_array($body['context'] ?? null) ? $body['context'] : [];
    if (isset($context['patient_id']) && (string)$context['patient_id'] !== $patientId)
        throw new InvalidArgumentException('PRESCRIPTION_PATIENT_SCOPE_INVALID');
    if (isset($body['actor']['user_id']) && (string)$body['actor']['user_id'] !== (string)$doctor['user_id'])
        throw new InvalidArgumentException('PRESCRIPTION_ACTOR_SCOPE_INVALID');
    $encounterKey = clinical_prescription_v2_text($context['encounter_key'] ?? '', 180);
    if ($encounterKey === '' && (!empty($context['encounter_id']) || !empty($context['appointment_id'])))
        throw new InvalidArgumentException('PRESCRIPTION_EXPLICIT_ENCOUNTER_REQUIRED');
    $encounter = clinical_prescription_v2_encounter($pdo, $encounterKey, $patientId, (string)$doctor['doctor_id']);
    if ($encounter !== null && isset($context['encounter_id'])
        && (string)$context['encounter_id'] !== (string)$encounter['encounter_id'])
        throw new InvalidArgumentException('PRESCRIPTION_ENCOUNTER_SCOPE_INVALID');
    $raw = $source['prescription']['items'] ?? null;
    if (!is_array($raw) || !array_is_list($raw) || count($raw) > 100)
        throw new InvalidArgumentException('PRESCRIPTION_ITEMS_INVALID');
    $items = []; $ids = [];
    foreach ($raw as $index => $entry) {
        if (!is_array($entry)) throw new InvalidArgumentException('PRESCRIPTION_ITEM_INVALID');
        $item = [];
        $item['item_id'] = clinical_prescription_v2_text($entry['item_id'] ?? ('item_' . ($index + 1)), 80);
        if ($item['item_id'] === '' || !preg_match('/^[a-zA-Z0-9_-]+$/D', $item['item_id']) || isset($ids[$item['item_id']]))
            throw new InvalidArgumentException('PRESCRIPTION_ITEM_ID_INVALID');
        $ids[$item['item_id']] = true;
        foreach (['name','presentation','dose','route','frequency','duration','quantity','instructions','private_notes'] as $field)
            $item[$field] = clinical_prescription_v2_text($entry[$field] ?? '', $field === 'instructions' || $field === 'private_notes' ? 4000 : 500);
        if ($final && $item['name'] === '') throw new InvalidArgumentException('PRESCRIPTION_MEDICATION_NAME_REQUIRED');
        $items[] = $item;
    }
    if ($final && $items === []) throw new InvalidArgumentException('PRESCRIPTION_MEDICATION_REQUIRED');
    $diagnosis = is_array($source['diagnostic_context'] ?? null) ? $source['diagnostic_context'] : [];
    $mode = (string)($diagnosis['mode'] ?? 'sin_diagnostico');
    if (!in_array($mode, ['diagnostico','prediagnostico','sin_diagnostico'], true))
        throw new InvalidArgumentException('PRESCRIPTION_DIAGNOSIS_MODE_INVALID');
    $diagnosisText = clinical_prescription_v2_text($diagnosis['text'] ?? '', 2000);
    if ($final && $mode !== 'sin_diagnostico' && $diagnosisText === '')
        throw new InvalidArgumentException('PRESCRIPTION_DIAGNOSIS_REQUIRED');
    $appointmentId = $encounter === null ? null : (string)($encounter['appointment_id'] ?? '');
    $identity = clinical_portable_identity($pdo, (string)$doctor['doctor_id'], $patientId,
        $appointmentId ?: null, $final);
    $profile = $pdo->prepare('SELECT logo_url FROM profiles_doctors WHERE doctor_id=? LIMIT 1');
    $profile->execute([(string)$doctor['doctor_id']]);
    $logo = (string)($profile->fetchColumn() ?: '');
    if (!preg_match('#^/storage/clinical_uploads/branding/[^/]+/logo-opt\.webp$#D', $logo)) $logo = '';
    $patient = (array)$identity['patient'];
    $physician = (array)$identity['physician'];
    $age = '';
    if (!empty($patient['birthdate'])) {
        try { $age = (string)(new DateTimeImmutable($patient['birthdate']))->diff(new DateTimeImmutable('today'))->y; }
        catch (Throwable) { $age = ''; }
    }
    $sexStmt = $pdo->prepare('SELECT sex FROM patients_patients WHERE patient_id=? LIMIT 1');
    $sexStmt->execute([$patientId]);
    $sex = (string)($sexStmt->fetchColumn() ?: '');
    return [
        'contract_version'=>2,
        'status'=>$final ? 'generated' : 'draft',
        'prescription'=>['items'=>$items,
            'observaciones'=>clinical_prescription_v2_text($source['prescription']['observaciones'] ?? '', 4000)],
        'diagnostic_context'=>['mode'=>$mode,'text'=>$diagnosisText],
        'patient_snapshot'=>['id'=>$patientId,'full_name'=>(string)$patient['name'],'age'=>$age,'sex'=>$sex],
        'physician_snapshot'=>['user_id'=>(string)$doctor['user_id'],'doctor_id'=>(string)$doctor['doctor_id'],
            'full_name'=>(string)$physician['name'],'prefix'=>(string)($physician['prefix'] ?? ''),
            'designation'=>(string)($physician['designation'] ?? ''),'specialty'=>(string)($physician['specialty'] ?? ''),
            'license'=>(string)($physician['professional_license'] ?? ''),
            'specialty_license'=>(string)($physician['specialty_license'] ?? '')],
        'professional_context'=>['consultorio'=>$identity['consultorio'] ?? null,'logo_url'=>$logo],
        'presentation'=>['version'=>1,'professional_header'=>
            ($source['presentation']['professional_header'] ?? 'shown') === 'hidden' ? 'hidden' : 'shown'],
        'encounter_reference'=>$encounter === null ? null : ['encounter_id'=>(int)$encounter['encounter_id'],
            'appointment_id'=>$appointmentId ?: null],
        'issue_date'=>(new DateTimeImmutable('now',new DateTimeZone('America/Mexico_City')))->format('Y-m-d'),
        'signatures'=>['doctor'=>null],
    ];
}

/** Private notes are intentionally absent from patient-facing output. */
function clinical_prescription_v2_render(array $payload): string
{
    $h = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $patient = (array)($payload['patient_snapshot'] ?? []);
    $doctor = (array)($payload['physician_snapshot'] ?? []);
    $professional = (array)($payload['professional_context'] ?? []);
    $logo = (string)($professional['logo_url'] ?? '');
    $html = '<article class="rx-doc-sheet document-sheet doc-base-sheet doc-base-sheet--letter doc-base-print-safe"><div class="doc-base-sheet-inner doc-base-body">';
    if (clinical_legal_document_professional_header_mode($payload) === 'shown') {
        $html .= '<header class="clinical-doc-head doc-base-medical-header doc-base-header-block doc-base-print-safe">';
        if (preg_match('#^/storage/clinical_uploads/branding/[^/]+/logo-opt\.webp$#D', $logo))
            $html .= '<div class="clinical-doc-head-logo-slot"><img src="'.$h($logo).'" alt="Logo médico"></div>';
        $html .= '<div class="clinical-doc-head-main"><div class="clinical-doc-doctor-name">'.$h($doctor['full_name'] ?? '').'</div>';
        foreach (['designation'=>'','specialty'=>'','license'=>'Cédula profesional: ',
            'specialty_license'=>'Cédula de especialidad: '] as $key=>$prefix) {
            if (!empty($doctor[$key])) $html .= '<div class="clinical-doc-doctor-site">'.$h($prefix.$doctor[$key]).'</div>';
        }
        $place = (array)($professional['consultorio'] ?? []);
        foreach (['name','address'] as $key) if (!empty($place[$key]))
            $html .= '<div class="clinical-doc-doctor-site">'.$h($place[$key]).'</div>';
        $html .= '</div></header>';
    }
    $html .= '<div class="doc-base-title-block-wrap"><section class="doc-base-title-block"><div class="doc-base-title">RECETA MÉDICA</div></section></div>';
    $html .= '<div class="doc-base-patient-block-wrap"><section class="doc-base-patient-meta"><div><strong>Paciente:</strong> '.$h($patient['full_name'] ?? '').'</div>'
        .'<div><strong>Edad:</strong> '.$h($patient['age'] ?? '').' · <strong>Sexo:</strong> '.$h($patient['sex'] ?? '').'</div>'
        .'<div><strong>Fecha:</strong> '.$h($payload['issue_date'] ?? '').'</div></section></div>';
    $diagnosis = (array)($payload['diagnostic_context'] ?? []);
    if (!empty($diagnosis['text'])) $html .= '<section class="doc-base-section"><div class="doc-base-section-title">Contexto clínico</div><div class="doc-base-text">'.$h($diagnosis['text']).'</div></section>';
    $html .= '<div class="doc-base-body-block"><section class="doc-base-section"><div class="doc-base-section-title">Medicamentos</div><ol class="rx-doc-list">';
    foreach ((array)($payload['prescription']['items'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $html .= '<li class="rx-doc-item"><strong>'.$h($item['name'] ?? '').'</strong>';
        foreach (['presentation'=>'Presentación','dose'=>'Dosis','route'=>'Vía','frequency'=>'Frecuencia',
            'duration'=>'Duración','quantity'=>'Cantidad','instructions'=>'Indicaciones'] as $key=>$label) {
            if (trim((string)($item[$key] ?? '')) !== '')
                $html .= '<div class="rx-doc-item-meta"><strong>'.$h($label).':</strong> '.nl2br($h($item[$key])).'</div>';
        }
        $html .= '</li>';
    }
    $html .= '</ol></section>';
    $observations = (string)($payload['prescription']['observaciones'] ?? '');
    if ($observations !== '') $html .= '<section class="doc-base-section"><div class="doc-base-section-title">Observaciones</div><div class="doc-base-text">'.nl2br($h($observations)).'</div></section>';
    $html .= '<section class="doc-base-signature doc-base-print-safe"><div class="doc-base-section-title">Médico responsable</div>';
    $signed = (array)($payload['signatures']['doctor'] ?? []);
    if (!empty($signed['image_data'])) $html .= '<img class="doc-base-signature-image" src="'.$h($signed['image_data']).'" alt="Firma del médico responsable">';
    else $html .= '<div class="rx-doc-sign-line"></div>';
    $html .= '<div class="doc-base-signature-meta">'.$h($doctor['full_name'] ?? '').'</div>';
    if (!empty($doctor['license'])) $html .= '<div class="doc-base-signature-meta">Cédula profesional: '.$h($doctor['license']).'</div>';
    $html .= '</section></div></div></article>';
    return $html;
}

function clinical_prescription_v2_write(PDO $pdo, array $doctor, string $patientId, array $body, string $key): array
{
    $intent = (string)($body['intent'] ?? '');
    if (!in_array($intent, ['draft','apply_signature','finalize'], true)) throw new InvalidArgumentException('PRESCRIPTION_INTENT_INVALID');
    $draftRef = trim((string)($body['draft_ref'] ?? ''));
    $expected = (int)($body['expected_version'] ?? 0);
    if ($intent !== 'draft' && $draftRef === '') throw new InvalidArgumentException('PRESCRIPTION_DRAFT_REQUIRED');
    if ($draftRef !== '' && (preg_match('/^[0-9a-f-]{36}$/iD', $draftRef) !== 1 || $expected < 1))
        throw new InvalidArgumentException('PRESCRIPTION_DRAFT_IDENTITY_INVALID');
    $idempotencyKey = clinical_idempotency_key_validate($key);
    clinical_encounter_integrity_assert_schema_ready($pdo);
    $semantic = ['patient_id'=>$patientId,'doctor_id'=>(string)$doctor['doctor_id'],
        'intent'=>$intent,'draft_ref'=>$draftRef,'expected_version'=>$expected,'body'=>$body];
    // Prescription V2 is a canonical patient-level document writer. It may carry an
    // explicitly validated encounter reference, but it is not an encounter mutation
    // and must not depend on the rollout gate for the encounter integrity service.
    return (new ClinicalIdempotentCreateExecutor($pdo))->execute(
        'CREATE_ENCOUNTER_DOCUMENT', (string)$doctor['doctor_id'],
        'PATIENT', $patientId, $idempotencyKey, $semantic, 'document_id', (string)$doctor['user_id'],
        function () use ($pdo,$doctor,$patientId,$body,$intent,$draftRef,$expected): int {
            if (!clinical_has_active_doctor_patient_link($pdo,(string)$doctor['doctor_id'],$patientId))
                throw new InvalidArgumentException('PRESCRIPTION_PATIENT_SCOPE_INVALID');
            $existing = null;
            if ($draftRef !== '') {
                $stmt = $pdo->prepare('SELECT id,document_uuid,document_type,patient_id,status,version,created_by_user_id,
                    encounter_ref_id,payload_json FROM clinical_documents WHERE document_uuid=? LIMIT 1 FOR UPDATE');
                $stmt->execute([$draftRef]); $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!is_array($existing) || (string)$existing['document_type'] !== 'prescription'
                    || (string)$existing['patient_id'] !== $patientId
                    || (string)$existing['created_by_user_id'] !== (string)$doctor['user_id']
                    || (string)$existing['status'] !== 'draft')
                    throw new InvalidArgumentException('PRESCRIPTION_DRAFT_SCOPE_INVALID');
                if ((int)$existing['version'] !== $expected)
                    throw new ClinicalIdempotencyException('PRESCRIPTION_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            }
            $payload = clinical_prescription_v2_normalize($pdo,$doctor,$patientId,$body,$intent!=='draft');
            $payload['status'] = $intent === 'finalize' ? 'generated' : 'draft';
            $encounterId = (int)($payload['encounter_reference']['encounter_id'] ?? 0);
            if ($existing !== null && (int)($existing['encounter_ref_id'] ?? 0) !== $encounterId)
                throw new InvalidArgumentException('PRESCRIPTION_DRAFT_ENCOUNTER_IMMUTABLE');
            $oldPayload = $existing === null ? null : json_decode((string)$existing['payload_json'],true);
            if ($existing !== null && is_array($oldPayload)) {
                foreach (['patient_snapshot','physician_snapshot','professional_context','issue_date','encounter_reference'] as $snapshotKey)
                    $payload[$snapshotKey] = $oldPayload[$snapshotKey] ?? $payload[$snapshotKey];
            }
            $bindingBody = ['draft_ref'=>$draftRef,'expected_version'=>$expected,
                'context'=>['patient_id'=>$patientId],'payload'=>$payload];
            if ($intent === 'apply_signature') {
                if ($existing === null) throw new InvalidArgumentException('PRESCRIPTION_DRAFT_REQUIRED');
                $oldBody = ['draft_ref'=>$draftRef,'expected_version'=>$expected,
                    'context'=>['patient_id'=>$patientId],'payload'=>$oldPayload];
                if (!hash_equals(clinical_prescription_binding_fingerprint($oldBody),
                        clinical_prescription_binding_fingerprint($bindingBody)))
                    throw new InvalidArgumentException('PRESCRIPTION_SIGNATURE_CONTENT_STALE');
                $payload['signatures']['doctor'] = $body['signature'] ?? null;
                $bindingBody['payload'] = $payload;
                if (clinical_prescription_binding_classify($bindingBody,(string)$doctor['doctor_id'],$pdo)
                    !== 'valid_bound_signature') throw new InvalidArgumentException('PRESCRIPTION_SIGNATURE_INVALID');
                if (($payload['signatures']['doctor']['source'] ?? '') === 'remote_qr') {
                    require_once __DIR__ . '/clinical_prescription_qr.php';
                    clinical_prescription_qr_claim($pdo,$bindingBody,$payload['signatures']['doctor'],
                        'doctor',(string)$doctor['doctor_id'],$draftRef,$expected);
                }
            } elseif ($intent === 'finalize') {
                $payload['signatures']['doctor'] = $oldPayload['signatures']['doctor'] ?? null;
                $bindingBody['payload'] = $payload;
                if (clinical_prescription_binding_classify($bindingBody,(string)$doctor['doctor_id'],$pdo)
                    !== 'valid_bound_signature') throw new InvalidArgumentException('PRESCRIPTION_SIGNATURE_REQUIRED_OR_STALE');
            } elseif ($existing !== null && !empty($oldPayload['signatures']['doctor'])) {
                $oldBody = ['draft_ref'=>$draftRef,'expected_version'=>$expected,
                    'context'=>['patient_id'=>$patientId],'payload'=>$oldPayload];
                if (hash_equals(clinical_prescription_binding_fingerprint($oldBody),
                        clinical_prescription_binding_fingerprint($bindingBody)))
                    $payload['signatures']['doctor'] = $oldPayload['signatures']['doctor'];
            }
            $html = clinical_prescription_v2_render($payload);
            $payload['prescription_snapshot'] = ['version'=>1,'html'=>$html];
            $status = $intent === 'finalize' ? 'generated' : 'draft';
            if ($existing === null) {
                $doc = mxmed_build_clinical_document(['type'=>'prescription','title'=>'Receta médica',
                    'context'=>['patient_id'=>$patientId,'encounter_id'=>$encounterId ?: null,
                        'appointment_id'=>$payload['encounter_reference']['appointment_id'] ?? null,
                        'care_setting'=>'consulta'],
                    'payload'=>[],'actor'=>['user_id'=>(string)$doctor['user_id']]]);
                $doc['content']['payload'] = $payload;
                $doc['content']['rendered_text'] = $html;
                $doc['content']['summary'] = $intent === 'draft' ? 'Borrador de receta' : 'Receta médica';
                $doc['status'] = $status;
                $doc['timestamps']['generated_at'] = $status === 'generated' ? gmdate('Y-m-d H:i:s') : null;
                return mxmed_persist_clinical_document_in_transaction($pdo,$doc,
                    $encounterId ? ['encounter_ref_id'=>$encounterId] : []);
            }
            $versionUnchanged = $intent === 'apply_signature' || $intent === 'finalize' || ($intent === 'draft'
                && !empty($payload['signatures']['doctor']));
            $stmt = $pdo->prepare('UPDATE clinical_documents SET payload_json=?,rendered_text=?,summary=?,
                status=?,version=version+'.($versionUnchanged?'0':'1').',updated_at=UTC_TIMESTAMP(),updated_by_user_id=?,edited_flag=1,
                generated_at=CASE WHEN ?=\'generated\' THEN UTC_TIMESTAMP() ELSE NULL END
                WHERE id=? AND version=? AND status=\'draft\'');
            $stmt->execute([json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
                $html,$status==='draft'?'Borrador de receta':'Receta médica',$status,
                (string)$doctor['user_id'],$status,(int)$existing['id'],$expected]);
            if ($stmt->rowCount() !== 1)
                throw new ClinicalIdempotencyException('PRESCRIPTION_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            if ($intent === 'finalize') {
                require_once __DIR__ . '/clinical_prescription_lifecycle.php';
                clinical_prescription_lifecycle_complete_reissue($pdo,(int)$existing['id'],$doctor);
            }
            return (int)$existing['id'];
        }, static fn(int $id): array => clinical_v1_document_fetch($pdo,$id));
}

function clinical_prescription_v2_drafts(PDO $pdo, array $doctor, string $patientId, string $encounterKey = ''): array
{
    $encounter = clinical_prescription_v2_encounter($pdo,$encounterKey,$patientId,(string)$doctor['doctor_id']);
    $encounterClause = $encounter === null ? 'encounter_ref_id IS NULL' : 'encounter_ref_id=?';
    $stmt = $pdo->prepare("SELECT d.document_uuid,d.version,d.updated_at,d.payload_json,
        source.document_uuid AS corrects_prescription_uuid,source.event_datetime AS source_event_datetime,
        CASE WHEN l.replacement_document_id IS NULL THEN 0 ELSE 1 END AS is_reissue
        FROM clinical_documents d
        LEFT JOIN clinical_prescription_lifecycle l ON l.replacement_document_id=d.id AND l.lifecycle_state='pending'
        LEFT JOIN clinical_documents source ON source.id=l.original_document_id
        WHERE d.patient_id=? AND d.created_by_user_id=? AND d.document_type='prescription' AND d.status='draft'
        AND JSON_UNQUOTE(JSON_EXTRACT(d.payload_json,'$.contract_version'))='2'
        AND (d.$encounterClause OR l.replacement_document_id IS NOT NULL)
        ORDER BY is_reissue DESC,COALESCE(d.updated_at,d.created_at) DESC,d.id DESC LIMIT 20");
    $params = [$patientId,(string)$doctor['user_id']];
    if ($encounter !== null) $params[] = (int)$encounter['encounter_id'];
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload = json_decode((string)$row['payload_json'],true);
        if (!is_array($payload)) continue;
        $rows[] = ['document_uuid'=>$row['document_uuid'],'version'=>(int)$row['version'],
            'is_reissue'=>(int)$row['is_reissue'] === 1,
            'corrects_prescription_uuid'=>$row['corrects_prescription_uuid'],
            'source_event_datetime'=>$row['source_event_datetime'],
            'updated_at'=>$row['updated_at'],'payload'=>$payload];
    }
    return $rows;
}
