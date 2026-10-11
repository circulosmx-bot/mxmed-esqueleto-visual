<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_multimedia_consent_render.php';

/** MULTI-CONS02A: draft-only canonical authority; emission opens with signature binding in 02B. */
function clinical_multimedia_consent_write(PDO $pdo, array $doctor, string $patientId,
    array $body, string $idempotencyKey): array
{
    if (($body['document_type'] ?? '') !== 'consentimiento_multimedia'
        || ($body['type'] ?? '') !== 'consentimiento_multimedia'
        || !is_array($body['payload'] ?? null)
        || ($body['payload']['contract_version'] ?? null) !== 1)
        throw new InvalidArgumentException('MULTIMEDIA_DOCUMENT_INVALID');
    $payload = $body['payload'];
    if (($payload['status'] ?? '') !== 'draft')
        throw new InvalidArgumentException('MULTIMEDIA_EMISSION_NOT_AVAILABLE');
    if (!clinical_legal_document_presentation_valid($payload))
        throw new InvalidArgumentException('MULTIMEDIA_PRESENTATION_INVALID');
    if (!is_array($payload['consent'] ?? null))
        throw new InvalidArgumentException('MULTIMEDIA_CONTENT_INVALID');
    $consent = clinical_multimedia_consent_normalize($payload['consent']);
    if (array_key_exists('declaration', $payload['consent'])
        && trim((string)$payload['consent']['declaration']) !== clinical_multimedia_consent_declaration($consent))
        throw new InvalidArgumentException('MULTIMEDIA_DECLARATION_MISMATCH');
    foreach (['rendered_text', 'frozen_snapshot', 'html', 'media_files', 'media_attachments'] as $forbidden)
        if (array_key_exists($forbidden, $body) || array_key_exists($forbidden, $payload))
            throw new InvalidArgumentException('MULTIMEDIA_SECOND_AUTHORITY_FORBIDDEN');
    if (!empty($payload['signatures'])) throw new InvalidArgumentException('MULTIMEDIA_SIGNATURES_NOT_AVAILABLE');
    $signer = is_array($payload['signer'] ?? null) ? $payload['signer'] : [];
    $mode = (string)($signer['mode'] ?? '');
    if (!in_array($mode, ['patient', 'representative'], true))
        throw new InvalidArgumentException('MULTIMEDIA_SIGNER_MODE_INVALID');
    $patientSnapshot = is_array($payload['patient_snapshot'] ?? null) ? $payload['patient_snapshot'] : [];
    $patientName = trim((string)($patientSnapshot['full_name'] ?? ''));
    if ($patientName === '' || trim((string)($patientSnapshot['id'] ?? '')) !== $patientId)
        throw new InvalidArgumentException('MULTIMEDIA_PATIENT_IDENTITY_INVALID');
    $safeSigner = ['mode' => $mode, 'name' => '', 'character' => '', 'relationship' => '', 'authority' => ''];
    if ($mode === 'representative') {
        foreach (['name', 'character', 'relationship', 'authority'] as $field)
            $safeSigner[$field] = trim((string)($signer[$field] ?? ''));
        if ($safeSigner['name'] === '' || $safeSigner['character'] === ''
            || $safeSigner['relationship'] === '' || $safeSigner['authority'] === '')
            throw new InvalidArgumentException('MULTIMEDIA_REPRESENTATIVE_IDENTITY_REQUIRED');
    }
    $actor = is_array($payload['actor_snapshot'] ?? null) ? $payload['actor_snapshot'] : [];
    if (trim((string)($body['actor']['user_id'] ?? '')) !== (string)$doctor['user_id']
        || trim((string)($actor['user_id'] ?? '')) !== (string)$doctor['user_id'])
        throw new InvalidArgumentException('MULTIMEDIA_ACTOR_MISMATCH');
    if (trim((string)($body['context']['patient_id'] ?? '')) !== $patientId
        || trim((string)($body['context']['encounter_id'] ?? '')) !== ''
        || trim((string)($body['context']['appointment_id'] ?? '')) !== '')
        throw new InvalidArgumentException('MULTIMEDIA_PATIENT_CONTEXT_INVALID');
    $draftRef = trim((string)($body['draft_ref'] ?? ''));
    $expectedVersion = (int)($body['expected_version'] ?? 0);
    if ($draftRef !== '' && (preg_match('/^[0-9a-f-]{36}$/i', $draftRef) !== 1 || $expectedVersion < 1))
        throw new InvalidArgumentException('MULTIMEDIA_DRAFT_IDENTITY_INVALID');
    $key = clinical_idempotency_key_validate($idempotencyKey);
    clinical_encounter_integrity_assert_schema_ready($pdo);
    $semantic = clinical_document_semantic_request($body, null) + [
        'patient_id'=>$patientId, 'operation'=>'CREATE_ENCOUNTER_DOCUMENT',
        'multimedia_intent'=>'draft', 'draft_ref'=>$draftRef ?: null,
        'expected_version'=>$draftRef !== '' ? $expectedVersion : null,
    ];
    $service = new ClinicalEncounterIntegrityService($pdo);
    return $service->idempotentCreate('CREATE_ENCOUNTER_DOCUMENT', (string)$doctor['doctor_id'],
        'PATIENT', $patientId, $key, $semantic, 'document_id', (string)$doctor['user_id'],
        function () use ($pdo, $doctor, $patientId, $body, $payload, $consent,
            $safeSigner, $patientSnapshot, $actor, $draftRef, $expectedVersion): int {
            if (!clinical_has_active_doctor_patient_link($pdo, (string)$doctor['doctor_id'], $patientId))
                throw new InvalidArgumentException('MULTIMEDIA_PATIENT_SCOPE_INVALID');
            $existing = null;
            if ($draftRef !== '') {
                $stmt = $pdo->prepare('SELECT id,patient_id,document_type,status,version,created_by_user_id
                    FROM clinical_documents WHERE document_uuid=? LIMIT 1 FOR UPDATE');
                $stmt->execute([$draftRef]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!is_array($existing) || (string)$existing['patient_id'] !== $patientId
                    || (string)$existing['document_type'] !== 'consentimiento_multimedia'
                    || (string)$existing['created_by_user_id'] !== (string)$doctor['user_id'])
                    throw new InvalidArgumentException('MULTIMEDIA_DRAFT_SCOPE_INVALID');
                if ((string)$existing['status'] !== 'draft')
                    throw new ClinicalIdempotencyException('MULTIMEDIA_DRAFT_FINAL', 'El documento ya no es borrador.', 409);
                if ((int)$existing['version'] !== $expectedVersion)
                    throw new ClinicalIdempotencyException('MULTIMEDIA_DRAFT_VERSION_CONFLICT', 'El borrador cambió.', 409);
            }
            $canonical = [
                'document_type'=>'consentimiento_multimedia', 'type'=>'consentimiento_multimedia',
                'title'=>'Consentimiento multimedia',
                'summary'=>'Autorización de material clínico audiovisual',
                'event_datetime'=>(string)($body['event_datetime'] ?? gmdate('Y-m-d H:i:s')),
                'context'=>['patient_id'=>$patientId, 'care_setting'=>'consulta'],
                'actor'=>['user_id'=>(string)$doctor['user_id']],
                'payload'=>[
                    'contract_version'=>1, 'status'=>'draft',
                    'consent'=>$consent + ['declaration'=>clinical_multimedia_consent_declaration($consent)],
                    'signer'=>$safeSigner,
                    'patient_snapshot'=>[
                        'id'=>$patientId, 'full_name'=>trim((string)$patientSnapshot['full_name']),
                        'age'=>trim((string)($patientSnapshot['age'] ?? '')),
                        'sex'=>trim((string)($patientSnapshot['sex'] ?? '')),
                    ],
                    'actor_snapshot'=>[
                        'user_id'=>(string)$doctor['user_id'],
                        'full_name'=>trim((string)($actor['full_name'] ?? '')),
                        'specialty'=>trim((string)($actor['specialty'] ?? '')),
                        'license'=>trim((string)($actor['license'] ?? '')),
                        'facility'=>trim((string)($actor['facility'] ?? '')),
                        'place'=>trim((string)($actor['place'] ?? '')),
                    ],
                    'presentation'=>['version'=>1,
                        'professional_header'=>clinical_legal_document_professional_header_mode($payload)],
                    'document_date'=>trim((string)($payload['document_date'] ?? gmdate('Y-m-d'))),
                    'signatures'=>[],
                ],
            ];
            $html = clinical_multimedia_consent_render_html($canonical['payload']);
            $canonical['payload']['frozen_snapshot'] = ['version'=>1, 'html'=>$html];
            $canonical['payload']['rendered_text'] = trim(preg_replace('/\s+/u', ' ',
                html_entity_decode(strip_tags(str_replace('<br>', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            if ($existing === null) {
                $id = clinical_v1_document_insert($pdo, $canonical['context'], $canonical, (string)$doctor['user_id']);
                $mark = $pdo->prepare("UPDATE clinical_documents SET status='draft',generated_at=NULL WHERE id=? AND status='generated'");
                $mark->execute([$id]);
                if ($mark->rowCount() !== 1) throw new RuntimeException('MULTIMEDIA_DRAFT_CREATE_FAILED');
                return $id;
            }
            $update = $pdo->prepare("UPDATE clinical_documents SET title=?,summary=?,payload_json=?,rendered_text=?,
                event_datetime=?,version=version+1,updated_at=UTC_TIMESTAMP(),updated_by_user_id=?,edited_flag=1
                WHERE id=? AND version=? AND status='draft'");
            $update->execute([$canonical['title'], $canonical['summary'],
                json_encode($canonical['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                $canonical['payload']['rendered_text'], $canonical['event_datetime'],
                (string)$doctor['user_id'], (int)$existing['id'], $expectedVersion]);
            if ($update->rowCount() !== 1)
                throw new ClinicalIdempotencyException('MULTIMEDIA_DRAFT_VERSION_CONFLICT', 'El borrador cambió.', 409);
            return (int)$existing['id'];
        }, static fn(int $id): array => clinical_v1_document_fetch($pdo, $id));
}
