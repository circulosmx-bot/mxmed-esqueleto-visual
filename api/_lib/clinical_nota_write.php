<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_nota_binding.php';
require_once __DIR__ . '/clinical_nota_render.php';
require_once __DIR__ . '/clinical_nota_qr.php';
require_once __DIR__ . '/clinical_nota_encounter_source.php';

/** Preserve an existing QR artifact exactly, regardless of JSON object key order. */
function clinical_nota_qr_entry_unchanged($previous, $incoming): bool
{
    if (is_array($previous) || is_array($incoming)) {
        if (!is_array($previous) || !is_array($incoming) || count($previous) !== count($incoming)) return false;
        foreach ($previous as $key => $value) {
            if (!array_key_exists($key, $incoming)
                || !clinical_nota_qr_entry_unchanged($value, $incoming[$key])) return false;
        }
        return true;
    }
    return $previous === $incoming;
}

/** Canonical create/update/finalize command for Nota. Caller supplies authenticated doctor scope. */
function clinical_nota_write(PDO $pdo, array $doctor, string $patientId, array $body, string $idempotencyKey): array
{
    if (($body['document_type'] ?? '') !== 'nota_medica'
        || ($body['type'] ?? '') !== 'nota_medica'
        || !is_array($body['payload'] ?? null)
        || (int)($body['payload']['contract_version'] ?? 0) !== 2)
        throw new InvalidArgumentException('NOTA_DOCUMENT_INVALID');
    $payload = $body['payload'];
    if (!clinical_legal_document_presentation_valid($payload))
        throw new InvalidArgumentException('NOTA_PRESENTATION_INVALID');
    $intent = (string)($payload['status'] ?? '');
    if (!in_array($intent, ['draft', 'issued'], true))
        throw new InvalidArgumentException('NOTA_INTENT_INVALID');
    $types = ['consulta_inicial', 'nota_evolucion', 'nota_subsecuente'];
    if (!in_array((string)($payload['note']['type'] ?? ''), $types, true))
        throw new InvalidArgumentException('NOTA_TYPE_INVALID');
    if ($intent === 'issued') {
        foreach (['motivo_consulta', 'padecimiento_actual', 'exploracion_fisica',
            'impresion_diagnostica', 'tratamiento_indicaciones'] as $field) {
            if (trim((string)($payload['content'][$field] ?? '')) === '')
                throw new InvalidArgumentException('NOTA_FIELD_REQUIRED_' . strtoupper($field));
        }
    }
    $draftRef = trim((string)($body['draft_ref'] ?? ''));
    $expectedVersion = (int)($body['expected_version'] ?? 0);
    if ($draftRef !== '' && (preg_match('/^[0-9a-f-]{36}$/i', $draftRef) !== 1 || $expectedVersion < 1))
        throw new InvalidArgumentException('NOTA_DRAFT_IDENTITY_INVALID');
    if (trim((string)($body['actor']['user_id'] ?? '')) !== (string)$doctor['user_id']
        || trim((string)($payload['actor_snapshot']['user_id'] ?? '')) !== (string)$doctor['user_id'])
        throw new InvalidArgumentException('NOTA_ACTOR_MISMATCH');
    if (trim((string)($body['context']['patient_id'] ?? '')) !== $patientId)
        throw new InvalidArgumentException('NOTA_PATIENT_MISMATCH');
    $source = $payload['encounter_source'] ?? null;
    if ($source !== null && !is_array($source))
        throw new InvalidArgumentException('NOTA_ENCOUNTER_SOURCE_INVALID');
    $sourceId = is_array($source) ? (int)($source['encounter_id'] ?? 0) : 0;
    if ($source !== null && ($sourceId < 1 || trim((string)($source['confirmed_at'] ?? '')) === ''))
        throw new InvalidArgumentException('NOTA_ENCOUNTER_CONFIRMATION_REQUIRED');
    $requestEncounterId = trim((string)($body['context']['encounter_id'] ?? ''));
    if ($requestEncounterId !== ($sourceId > 0 ? (string)$sourceId : ''))
        throw new InvalidArgumentException('NOTA_ENCOUNTER_CONTEXT_MISMATCH');
    if (isset($payload['encounter_imports']) && !is_array($payload['encounter_imports']))
        throw new InvalidArgumentException('NOTA_IMPORTS_INVALID');
    $allowedDestinations = ['motivo_consulta','padecimiento_actual','signos_vitales',
        'exploracion_fisica','impresion_diagnostica','tratamiento_indicaciones','estudios_sugeridos'];
    $allowedSources = ['reason_evolution','vital_observation','physical_exam','assessment','plan','diagnostic_order'];
    foreach ((array)($payload['encounter_imports'] ?? []) as $import) {
        if (!is_array($import) || !in_array((string)($import['destination_key'] ?? ''), $allowedDestinations, true)
            || !in_array((string)($import['source_type'] ?? ''), $allowedSources, true)
            || (int)($import['encounter_id'] ?? 0) < 1
            || trim((string)($import['source_record_id'] ?? '')) === ''
            || !is_string($import['imported_snapshot'] ?? null))
            throw new InvalidArgumentException('NOTA_IMPORT_PROVENANCE_INVALID');
    }
    $key = clinical_idempotency_key_validate($idempotencyKey);
    clinical_encounter_integrity_assert_schema_ready($pdo);
    $semantic = clinical_document_semantic_request($body, null) + [
        'patient_id' => $patientId, 'operation' => 'CREATE_ENCOUNTER_DOCUMENT',
        'nota_intent' => $intent, 'draft_ref' => $draftRef ?: null,
        'expected_version' => $draftRef !== '' ? $expectedVersion : null,
    ];
    $service = new ClinicalEncounterIntegrityService($pdo);
    return $service->idempotentCreate('CREATE_ENCOUNTER_DOCUMENT', (string)$doctor['doctor_id'],
        'PATIENT', $patientId, $key, $semantic, 'document_id', (string)$doctor['user_id'],
        function () use ($pdo, $doctor, $patientId, $body, $payload, $intent, $draftRef, $expectedVersion, $sourceId): int {
            if (!clinical_has_active_doctor_patient_link($pdo, (string)$doctor['doctor_id'], $patientId))
                throw new InvalidArgumentException('NOTA_PATIENT_SCOPE_INVALID');
            $existing = null;
            if ($draftRef !== '') {
                $stmt = $pdo->prepare('SELECT id,document_uuid,document_type,patient_id,status,version,
                    created_by_user_id,payload_json,generated_at,signed_at FROM clinical_documents
                    WHERE document_uuid=? LIMIT 1 FOR UPDATE');
                $stmt->execute([$draftRef]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!is_array($existing) || (string)$existing['patient_id'] !== $patientId
                    || (string)$existing['document_type'] !== 'nota_medica'
                    || (string)$existing['created_by_user_id'] !== (string)$doctor['user_id'])
                    throw new InvalidArgumentException('NOTA_DRAFT_SCOPE_INVALID');
                if ((string)$existing['status'] !== 'draft')
                    throw new ClinicalIdempotencyException('NOTA_DRAFT_FINAL', 'La nota ya no es borrador.', 409);
                if ((int)$existing['version'] !== $expectedVersion)
                    throw new ClinicalIdempotencyException('NOTA_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            }
            $sourceRow = $sourceId > 0
                ? clinical_nota_source_encounter($pdo, $sourceId, $patientId, (string)$doctor['doctor_id']) : null;
            foreach ((array)($payload['encounter_imports'] ?? []) as $import) {
                $importId = (int)$import['encounter_id'];
                if ($importId === $sourceId) continue;
                $check = $pdo->prepare('SELECT 1 FROM clinical_encounters WHERE encounter_id=? AND patient_id=? AND doctor_id=?');
                $check->execute([$importId, $patientId, (string)$doctor['doctor_id']]);
                if (!$check->fetchColumn()) throw new InvalidArgumentException('NOTA_IMPORT_SCOPE_INVALID');
            }
            $writeContext = $sourceRow !== null ? [
                'patient_id' => $patientId, 'care_setting' => 'consulta',
                'encounter_id' => (string)$sourceId,
                'appointment_id' => $sourceRow['appointment_id'] ?? null,
            ] : ['patient_id' => $patientId, 'care_setting' => 'consulta'];
            $previousPayload = $existing !== null ? json_decode((string)$existing['payload_json'], true) : null;
            $priorImports = (array)($previousPayload['encounter_imports'] ?? []);
            $projectionCache = [];
            foreach ((array)($payload['encounter_imports'] ?? []) as $import) {
                $retained = false;
                foreach ($priorImports as $prior) {
                    if (clinical_nota_qr_entry_unchanged($prior, $import)) { $retained = true; break; }
                }
                if ($retained) continue;
                $importId = (int)$import['encounter_id'];
                if (!isset($projectionCache[$importId])) {
                    $importRow = clinical_nota_source_encounter($pdo, $importId, $patientId, (string)$doctor['doctor_id']);
                    $projectionCache[$importId] = clinical_nota_source_projection($pdo, $importRow);
                }
                $matched = false;
                foreach ($projectionCache[$importId]['candidates'] as $candidate) {
                    $snapshot = static fn(string $text): string => trim(str_replace(["\r\n", "\r"], "\n", $text));
                    if ($candidate['source_type'] === $import['source_type']
                        && $candidate['source_record_id'] === $import['source_record_id']
                        && $candidate['source_version_or_updated_at'] === ($import['source_version_or_updated_at'] ?? null)
                        && in_array($import['destination_key'], $candidate['destinations'], true)
                        && $snapshot((string)$candidate['text']) === $snapshot((string)$import['imported_snapshot'])) {
                        $matched = true; break;
                    }
                }
                if (!$matched) throw new InvalidArgumentException('NOTA_IMPORT_SOURCE_CHANGED');
            }
            $canonical = $body;
            $canonical['context'] = $writeContext;
            $canonical['actor'] = ['user_id' => (string)$doctor['user_id']];
            $canonical['payload'] = $payload;
            if ($sourceRow !== null) {
                $priorSource = (array)($previousPayload['encounter_source'] ?? []);
                $sameSource = (int)($priorSource['encounter_id'] ?? 0) === $sourceId;
                $canonical['payload']['encounter_source'] = clinical_nota_source_display($sourceRow) + [
                    'patient_id' => $patientId,
                    'doctor_id' => (string)$doctor['doctor_id'],
                    'confirmed_at' => $sameSource ? (string)($priorSource['confirmed_at'] ?? gmdate('c')) : gmdate('c'),
                    'status_at_confirmation' => $sameSource
                        ? (string)($priorSource['status_at_confirmation'] ?? $sourceRow['status'])
                        : (string)$sourceRow['status'],
                ];
            } else {
                $canonical['payload']['encounter_source'] = null;
            }
            $canonical['payload']['rendered_text'] = clinical_nota_canonical_text($canonical['payload']);
            if (isset($payload['form_snapshot']['final_text'])
                && trim((string)$payload['form_snapshot']['final_text']) !== '')
                throw new InvalidArgumentException('NOTA_FINAL_TEXT_NOT_CANONICAL');
            $uploadedQrRoles = [];
            foreach (['doctor'] as $role) {
                $canonical['payload']['signature_binding_status'][$role] =
                    clinical_nota_binding_classify($canonical, (string)$doctor['doctor_id'], $pdo);
                $entry = (array)($payload['signatures'][$role] ?? []);
                if (($entry['source'] ?? '') !== 'remote_qr') continue;
                $qr = clinical_nota_qr_row($pdo, (string)($entry['token'] ?? ''));
                $previousEntry = is_array($previousPayload)
                    ? (array)($previousPayload['signatures'][$role] ?? []) : [];
                if ($canonical['payload']['signature_binding_status'][$role] !== 'valid_bound_signature'
                    && ($existing === null || !clinical_nota_qr_entry_unchanged($previousEntry, $entry)))
                    throw new InvalidArgumentException('NOTA_QR_UNVERIFIED');
                if ($qr !== null && (string)$qr['status'] === 'uploaded') {
                    if ($existing === null || $canonical['payload']['signature_binding_status'][$role] !== 'valid_bound_signature')
                        throw new InvalidArgumentException('NOTA_QR_STALE');
                    $uploadedQrRoles[] = $role;
                }
            }
            if ($intent === 'issued') {
                foreach (['doctor' => 'NOTA_PHYSICIAN_SIGNATURE'] as $role => $errorPrefix) {
                    $classification = $canonical['payload']['signature_binding_status'][$role];
                    if ($classification === 'absent')
                        throw new InvalidArgumentException($errorPrefix . '_REQUIRED');
                    if ($classification !== 'valid_bound_signature')
                        throw new InvalidArgumentException($errorPrefix . '_INVALID_CURRENT_VERSION');
                }
            }
            $canonical['payload']['nota_snapshot'] = [
                'version' => 1,
                'html' => clinical_nota_render_html($canonical['payload']),
            ];
            $nextStatus = $intent === 'draft' ? 'draft' : 'generated';
            if ($existing === null) {
                $id = clinical_v1_document_insert($pdo, $writeContext, $canonical, (string)$doctor['user_id']);
                if ($nextStatus === 'draft') {
                    $mark = $pdo->prepare("UPDATE clinical_documents SET status='draft',generated_at=NULL WHERE id=? AND status='generated'");
                    $mark->execute([$id]);
                    if ($mark->rowCount() !== 1) throw new RuntimeException('NOTA_DRAFT_CREATE_FAILED');
                }
                return $id;
            }
            $id = (int)$existing['id'];
            $encoded = json_encode($canonical['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $update = $pdo->prepare('UPDATE clinical_documents SET title=?,summary=?,payload_json=?,rendered_text=?,
                encounter_ref_id=?,encounter_id=?,appointment_id=?,
                event_datetime=?,status=?,version=version+1,updated_at=UTC_TIMESTAMP(),updated_by_user_id=?,
                edited_flag=1,generated_at=CASE WHEN ?=\'generated\' THEN UTC_TIMESTAMP() ELSE NULL END
                WHERE id=? AND version=? AND status=\'draft\'');
            $update->execute([(string)($body['title'] ?? 'Nota médica'), (string)($body['summary'] ?? ''),
                $encoded, (string)$canonical['payload']['rendered_text'],
                $sourceId > 0 ? $sourceId : null, $sourceId > 0 ? (string)$sourceId : null,
                $sourceRow['appointment_id'] ?? null,
                (string)($body['event_datetime'] ?? gmdate('Y-m-d H:i:s')),
                $nextStatus, (string)$doctor['user_id'], $nextStatus, $id, $expectedVersion]);
            if ($update->rowCount() !== 1)
                throw new ClinicalIdempotencyException('NOTA_DRAFT_VERSION_CONFLICT', 'El borrador cambió. Vuelve a abrirlo.', 409);
            foreach ($uploadedQrRoles as $role) {
                clinical_nota_qr_claim($pdo, $canonical, (array)$payload['signatures'][$role], $role,
                    (string)$doctor['doctor_id'], $draftRef, $expectedVersion);
            }
            return $id;
        }, static fn(int $id): array => clinical_v1_document_fetch($pdo, $id));
}
