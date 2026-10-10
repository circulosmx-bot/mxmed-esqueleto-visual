<?php
declare(strict_types=1);

/** An exact, physician-owned Consulta is the only source accepted by Nota. */
function clinical_nota_source_encounter(PDO $pdo, int $encounterId, string $patientId, string $doctorId): array
{
    if ($encounterId < 1 || $patientId === '' || $doctorId === '')
        throw new InvalidArgumentException('NOTA_ENCOUNTER_INVALID');
    $stmt = $pdo->prepare('SELECT encounter_id, patient_id, doctor_id, appointment_id, encounter_dt,
        encounter_type, status, created_at, updated_at FROM clinical_encounters WHERE encounter_id=? LIMIT 1');
    $stmt->execute([$encounterId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || (string)$row['patient_id'] !== $patientId || (string)$row['doctor_id'] !== $doctorId)
        throw new InvalidArgumentException('NOTA_ENCOUNTER_SCOPE_INVALID');
    if (!in_array(strtolower((string)$row['status']), ['open', 'closed'], true))
        throw new InvalidArgumentException('NOTA_ENCOUNTER_STATUS_INVALID');
    if (!clinical_has_active_doctor_patient_link($pdo, $doctorId, $patientId))
        throw new InvalidArgumentException('NOTA_PATIENT_SCOPE_INVALID');
    if (!clinical_m6_v1_route_enabled_for_pair(clinical_encounter_integrity_v1_enabled(), $doctorId, $patientId))
        throw new InvalidArgumentException('NOTA_M6_CANONICAL_REQUIRED');
    return $row;
}

function clinical_nota_source_display(array $row): array
{
    $id = (int)$row['encounter_id'];
    $appointment = trim((string)($row['appointment_id'] ?? ''));
    return [
        'encounter_id' => $id,
        'display_key' => $appointment !== '' ? 'appt:' . $appointment . '#enc:' . $id : 'enc:' . $id,
        'encounter_dt' => (string)$row['encounter_dt'],
        'status' => strtolower((string)$row['status']),
        'origin' => (string)($row['encounter_type'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

/** Read-only import candidates. Patient-level and appointment-inferred documents are excluded. */
function clinical_nota_source_projection(PDO $pdo, array $row): array
{
    $id = (int)$row['encounter_id'];
    $patientId = (string)$row['patient_id'];
    $candidates = [];
    $sections = $pdo->prepare("SELECT section_type, narrative_text, payload_json, row_version, updated_at
        FROM clinical_encounter_sections WHERE encounter_id=?
        AND section_type IN ('reason_evolution','assessment','plan','physical_exam')");
    $sections->execute([$id]);
    foreach ($sections->fetchAll(PDO::FETCH_ASSOC) as $section) {
        $type = (string)$section['section_type'];
        $text = trim((string)($section['narrative_text'] ?? ''));
        $destinations = [
            'reason_evolution' => ['motivo_consulta', 'padecimiento_actual'],
            'assessment' => ['impresion_diagnostica'],
            'plan' => ['tratamiento_indicaciones'],
            'physical_exam' => ['exploracion_fisica'],
        ][$type];
        if ($type === 'physical_exam') {
            $payload = json_decode((string)$section['payload_json'], true) ?: [];
            $names = ['general'=>'Estado general','head_neck'=>'Cabeza y cuello','cardiovascular'=>'Cardiovascular',
                'respiratory'=>'Respiratorio','abdomen'=>'Abdomen','extremities'=>'Extremidades',
                'neurological'=>'Neurológico','skin'=>'Piel'];
            $lines = [];
            foreach ((array)($payload['systems'] ?? []) as $system => $finding) {
                if (!is_array($finding)) continue;
                $state = (string)($finding['state'] ?? '');
                if ($state === 'NORMAL') $lines[] = ($names[$system] ?? $system) . ': normal';
                if ($state === 'ABNORMAL' && trim((string)($finding['finding'] ?? '')) !== '')
                    $lines[] = ($names[$system] ?? $system) . ': ' . trim((string)$finding['finding']);
            }
            $text = implode("\n", $lines);
        }
        if ($text === '') continue;
        $candidates[] = ['source_type' => $type, 'source_record_id' => 'section:' . $id . ':' . $type,
            'source_version_or_updated_at' => (string)$section['row_version'] . '@' . (string)$section['updated_at'],
            'updated_at' => (string)$section['updated_at'], 'text' => $text, 'destinations' => $destinations];
    }
    $vitals = $pdo->prepare('SELECT observation_id, code, value_numeric, unit, systolic_mm_hg, diastolic_mm_hg,
        effective_at, row_version, updated_at FROM clinical_observations
        WHERE encounter_id=? AND invalidated_at IS NULL ORDER BY effective_at, observation_id');
    $vitals->execute([$id]);
    $vitalNames = ['blood_pressure'=>'Presión arterial','heart_rate'=>'Frecuencia cardiaca',
        'respiratory_rate'=>'Frecuencia respiratoria','temperature'=>'Temperatura',
        'oxygen_saturation'=>'Saturación de oxígeno','pain'=>'Dolor',
        'weight'=>'Peso','height'=>'Talla','waist'=>'Cintura'];
    foreach ($vitals->fetchAll(PDO::FETCH_ASSOC) as $vital) {
        $code = (string)$vital['code'];
        $value = $vital['systolic_mm_hg'] !== null && $vital['diastolic_mm_hg'] !== null
            ? (string)$vital['systolic_mm_hg'] . '/' . (string)$vital['diastolic_mm_hg']
            : (string)($vital['value_numeric'] ?? '');
        if ($value === '') continue;
        $candidates[] = ['source_type' => 'vital_observation',
            'source_record_id' => 'observation:' . (int)$vital['observation_id'],
            'source_version_or_updated_at' => (string)$vital['row_version'] . '@' . (string)$vital['updated_at'],
            'updated_at' => (string)$vital['updated_at'],
            'text' => trim(($vitalNames[$code] ?? $code) . ' (' . $code . '): ' . $value . ' ' . (string)$vital['unit'])
                . ' · ' . (string)$vital['effective_at'], 'destinations' => ['signos_vitales']];
    }
    $orders = $pdo->prepare("SELECT id, document_uuid, document_type, title, version, updated_at
        FROM clinical_documents WHERE patient_id=? AND encounter_ref_id=?
        AND document_type IN ('lab_order','imaging_order','orders','order','orden_estudio')
        AND status='generated' ORDER BY id");
    $orders->execute([$patientId, $id]);
    foreach ($orders->fetchAll(PDO::FETCH_ASSOC) as $order) {
        $candidates[] = ['source_type' => 'diagnostic_order',
            'source_record_id' => 'document:' . (int)$order['id'] . ':' . (string)$order['document_uuid'],
            'source_version_or_updated_at' => (string)$order['version'] . '@' . (string)$order['updated_at'],
            'updated_at' => (string)$order['updated_at'],
            'text' => 'Orden de estudios: ' . trim((string)$order['title']) . ' · ' . (string)$order['document_uuid'],
            'destinations' => ['estudios_sugeridos']];
    }
    return ['encounter' => clinical_nota_source_display($row), 'candidates' => $candidates];
}
