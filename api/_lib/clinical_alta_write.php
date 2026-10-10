<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_alta_render.php';

/** Canonical Alta draft and finalization; signature binding is reserved for DOC-CLOSE02C. */
function clinical_alta_write(PDO $pdo, array $doctor, string $patientId, array $body, string $idempotencyKey): array
{
    if (($body['document_type'] ?? '') !== 'alta_medica'
        || ($body['type'] ?? '') !== 'alta_medica'
        || !is_array($body['payload'] ?? null)
        || ($body['payload']['contract_version'] ?? null) !== 2)
        throw new InvalidArgumentException('ALTA_DOCUMENT_INVALID');
    $payload = $body['payload'];
    $intent = (string)($payload['status'] ?? '');
    if (!in_array($intent, ['draft', 'issued'], true)) throw new InvalidArgumentException('ALTA_INTENT_INVALID');
    if (!in_array((string)($payload['alta']['type'] ?? ''),
        ['mejoria', 'voluntaria', 'administrativa', 'referencia'], true))
        throw new InvalidArgumentException('ALTA_TYPE_INVALID');
    if (!is_array($payload['content'] ?? null)) throw new InvalidArgumentException('ALTA_CONTENT_INVALID');
    if (trim((string)($payload['form_snapshot']['final_text'] ?? '')) !== ''
        || trim((string)($payload['final_text'] ?? '')) !== '')
        throw new InvalidArgumentException('ALTA_FINAL_TEXT_NOT_CANONICAL');
    if ($intent === 'issued') {
        foreach (['motivo_egreso', 'resumen_evolucion', 'diagnostico_final',
            'estado_paciente', 'tratamiento'] as $field) {
            if (trim((string)($payload['content'][$field] ?? '')) === '')
                throw new InvalidArgumentException('ALTA_FIELD_REQUIRED_' . strtoupper($field));
        }
        // Interim presence check only. Exact UUID/version/fingerprint binding belongs to DOC-CLOSE02C.
        $signatureImage = (string)($payload['signatures']['doctor']['image_data'] ?? '');
        if (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#D', $signatureImage, $match) !== 1)
            throw new InvalidArgumentException('ALTA_PHYSICIAN_SIGNATURE_REQUIRED');
        $signatureBytes = base64_decode($match[1], true);
        $signatureInfo = false;
        if (is_string($signatureBytes) && strlen($signatureBytes) >= 50) {
            try { $signatureInfo = @getimagesizefromstring($signatureBytes); }
            catch (Throwable $ignored) { $signatureInfo = false; }
        }
        if ($signatureInfo === false || ($signatureInfo['mime'] ?? '') !== 'image/png'
            || (int)($signatureInfo[0] ?? 0) < 2 || (int)($signatureInfo[1] ?? 0) < 2)
            throw new InvalidArgumentException('ALTA_PHYSICIAN_SIGNATURE_INVALID');
    }
    $draftRef = trim((string)($body['draft_ref'] ?? ''));
    $expectedVersion = (int)($body['expected_version'] ?? 0);
    if ($draftRef !== '' && (preg_match('/^[0-9a-f-]{36}$/i', $draftRef) !== 1 || $expectedVersion < 1))
        throw new InvalidArgumentException('ALTA_DRAFT_IDENTITY_INVALID');
    if (trim((string)($body['actor']['user_id'] ?? '')) !== (string)$doctor['user_id']
        || trim((string)($payload['actor_snapshot']['user_id'] ?? '')) !== (string)$doctor['user_id'])
        throw new InvalidArgumentException('ALTA_ACTOR_MISMATCH');
    if (trim((string)($body['context']['patient_id'] ?? '')) !== $patientId
        || trim((string)($body['context']['encounter_id'] ?? '')) !== '')
        throw new InvalidArgumentException('ALTA_PATIENT_CONTEXT_INVALID');
    $key = clinical_idempotency_key_validate($idempotencyKey);
    clinical_encounter_integrity_assert_schema_ready($pdo);
    $semantic = clinical_document_semantic_request($body, null) + [
        'patient_id' => $patientId, 'operation' => 'CREATE_ENCOUNTER_DOCUMENT',
        'alta_intent' => $intent, 'draft_ref' => $draftRef ?: null,
        'expected_version' => $draftRef !== '' ? $expectedVersion : null,
    ];
    $service = new ClinicalEncounterIntegrityService($pdo);
    return $service->idempotentCreate('CREATE_ENCOUNTER_DOCUMENT', (string)$doctor['doctor_id'],
        'PATIENT', $patientId, $key, $semantic, 'document_id', (string)$doctor['user_id'],
        function () use ($pdo, $doctor, $patientId, $body, $payload, $intent, $draftRef, $expectedVersion): int {
            if (!clinical_has_active_doctor_patient_link($pdo, (string)$doctor['doctor_id'], $patientId))
                throw new InvalidArgumentException('ALTA_PATIENT_SCOPE_INVALID');
            $existing = null;
            if ($draftRef !== '') {
                $stmt = $pdo->prepare('SELECT id,patient_id,document_type,status,version,created_by_user_id
                    FROM clinical_documents WHERE document_uuid=? LIMIT 1 FOR UPDATE');
                $stmt->execute([$draftRef]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!is_array($existing) || (string)$existing['patient_id'] !== $patientId
                    || (string)$existing['document_type'] !== 'alta_medica'
                    || (string)$existing['created_by_user_id'] !== (string)$doctor['user_id'])
                    throw new InvalidArgumentException('ALTA_DRAFT_SCOPE_INVALID');
                if ((string)$existing['status'] !== 'draft')
                    throw new ClinicalIdempotencyException('ALTA_DRAFT_FINAL', 'El alta ya no es borrador.', 409);
                if ((int)$existing['version'] !== $expectedVersion)
                    throw new ClinicalIdempotencyException('ALTA_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            }
            $canonical = $body;
            $canonical['context'] = ['patient_id' => $patientId, 'care_setting' => 'consulta'];
            $canonical['actor'] = ['user_id' => (string)$doctor['user_id']];
            $canonical['payload'] = $payload;
            unset($canonical['payload']['final_text'], $canonical['payload']['form_snapshot']['final_text']);
            $html = clinical_alta_render_html($canonical['payload']);
            $canonical['payload']['alta_snapshot'] = ['version' => 1, 'html' => $html];
            $canonical['payload']['rendered_text'] = trim(preg_replace('/\s+/u', ' ',
                html_entity_decode(strip_tags(str_replace('<br>', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            $nextStatus = $intent === 'draft' ? 'draft' : 'generated';
            if ($existing === null) {
                if ($intent !== 'draft') throw new InvalidArgumentException('ALTA_DRAFT_REQUIRED');
                $id = clinical_v1_document_insert($pdo, $canonical['context'], $canonical, (string)$doctor['user_id']);
                $mark = $pdo->prepare("UPDATE clinical_documents SET status='draft',generated_at=NULL WHERE id=? AND status='generated'");
                $mark->execute([$id]);
                if ($mark->rowCount() !== 1) throw new RuntimeException('ALTA_DRAFT_CREATE_FAILED');
                return $id;
            }
            $encoded = json_encode($canonical['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $update = $pdo->prepare('UPDATE clinical_documents SET title=?,summary=?,payload_json=?,rendered_text=?,
                event_datetime=?,status=?,version=version+1,updated_at=UTC_TIMESTAMP(),updated_by_user_id=?,
                edited_flag=1,generated_at=CASE WHEN ?=\'generated\' THEN UTC_TIMESTAMP() ELSE NULL END
                WHERE id=? AND version=? AND status=\'draft\'');
            $update->execute([(string)($body['title'] ?? 'Alta médica'), (string)($body['summary'] ?? ''),
                $encoded, (string)$canonical['payload']['rendered_text'],
                (string)($body['event_datetime'] ?? gmdate('Y-m-d H:i:s')), $nextStatus,
                (string)$doctor['user_id'], $nextStatus, (int)$existing['id'], $expectedVersion]);
            if ($update->rowCount() !== 1)
                throw new ClinicalIdempotencyException('ALTA_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            return (int)$existing['id'];
        }, static fn(int $id): array => clinical_v1_document_fetch($pdo, $id));
}
