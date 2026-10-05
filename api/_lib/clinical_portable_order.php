<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_study_contract.php';

final class ClinicalPortableOrderException extends InvalidArgumentException
{
    public function __construct(public readonly string $reason, public readonly int $httpStatus = 409)
    {
        parent::__construct($reason);
    }
}

/** Shared session authority for the clinical API and the private print page. */
function clinical_portable_doctor_context(): ?array
{
    if (session_status() === PHP_SESSION_NONE) session_start(['read_and_close' => true]);
    $doctorId = trim((string)($_SESSION['doctor_id'] ?? $_SESSION['active_doctor_id'] ?? $_SESSION['mxmed_doctor_id'] ?? ''));
    $userId = trim((string)($_SESSION['user_id'] ?? $_SESSION['mxmed_user_id'] ?? $_SESSION['auth_user_id'] ?? $_SESSION['actor_user_id'] ?? ''));
    return $doctorId !== '' && $userId !== '' ? ['doctor_id' => $doctorId, 'user_id' => $userId] : null;
}

function clinical_portable_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function clinical_portable_text(mixed $value): string
{
    return is_scalar($value) ? trim((string)$value) : '';
}

/** Only server-resolved identity is admitted into an issued order. */
function clinical_portable_identity(PDO $pdo, string $doctorId, string $patientId,
    ?string $appointmentId = null, bool $requirePrintableIdentity = true): array
{
    $stmt = $pdo->prepare('SELECT display_name,birthdate FROM patients_patients WHERE patient_id=? LIMIT 1');
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($patient)) {
        throw new ClinicalPortableOrderException('No se pudo identificar al paciente.', 422);
    }
    if ($requirePrintableIdentity && clinical_portable_text($patient['display_name'] ?? '') === '') {
        throw new ClinicalPortableOrderException('No se pudo identificar al paciente.', 422);
    }
    $profile = null;
    if (clinical_portable_table_exists($pdo, 'profiles_doctors')) {
        $column = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles_doctors' AND COLUMN_NAME='primary_specialty_credential_id'");
        $column->execute();
        $primaryColumn = (int)$column->fetchColumn() > 0 ? 'primary_specialty_credential_id' : 'NULL AS primary_specialty_credential_id';
        $stmt = $pdo->prepare('SELECT display_name,prefix,professional_designation,specialty_primary,professional_license,specialty_license,'
            . $primaryColumn . ' FROM profiles_doctors WHERE doctor_id=? LIMIT 1');
        $stmt->execute([$doctorId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $name = clinical_portable_text($profile['display_name'] ?? '');
    $nameSource = 'profile';
    if (clinical_portable_table_exists($pdo, 'profiles_verified_identities')) {
        $stmt = $pdo->prepare('SELECT given_names,first_surname,second_surname FROM profiles_verified_identities WHERE doctor_id=? LIMIT 1');
        $stmt->execute([$doctorId]);
        $verified = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($verified)) {
            $name = trim(implode(' ', array_filter(array_map('clinical_portable_text', [
                $verified['given_names'], $verified['first_surname'], $verified['second_surname'] ?? null,
            ]))));
            $nameSource = 'verified_identity';
        }
    }
    if ($name === '' && $requirePrintableIdentity) throw new ClinicalPortableOrderException('No se pudo identificar al médico solicitante.', 422);

    $professional = clinical_portable_text($profile['professional_license'] ?? '');
    $specialtyLicense = clinical_portable_text($profile['specialty_license'] ?? '');
    $primarySpecialtyId = (int)($profile['primary_specialty_credential_id'] ?? 0);
    $professionalSource = $professional === '' ? null : 'profile_recorded';
    $specialtySource = $specialtyLicense === '' ? null : 'profile_recorded';
    if (clinical_portable_table_exists($pdo, 'profiles_doctor_credentials')) {
        $stmt = $pdo->prepare("SELECT credential_id,credential_type,license_number FROM profiles_doctor_credentials WHERE doctor_id=? AND verification_status='VERIFIED' AND lifecycle_status='ACTIVE' ORDER BY credential_id");
        $stmt->execute([$doctorId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $credential) {
            if ($credential['credential_type'] === 'PROFESSIONAL') {
                $professional = clinical_portable_text($credential['license_number']);
                $professionalSource = 'verified_credential';
            } elseif ($credential['credential_type'] === 'SPECIALTY' && $primarySpecialtyId > 0
                && (int)$credential['credential_id'] === $primarySpecialtyId) {
                $specialtyLicense = clinical_portable_text($credential['license_number']);
                $specialtySource = 'verified_credential';
            }
        }
    }
    $consultorio = null;
    if ($appointmentId !== null && $appointmentId !== '' && clinical_portable_table_exists($pdo, 'agenda_appointments')
        && clinical_portable_table_exists($pdo, 'consultorios')) {
        $stmt = $pdo->prepare('SELECT c.titulo,c.grupo_nombre,c.calle,c.num_ext,c.num_int,c.colonia,c.municipio,c.estado,c.cp FROM agenda_appointments a JOIN consultorios c ON c.doctor_id=a.doctor_id AND c.consultorio_id=a.consultorio_id WHERE a.appointment_id=? AND a.doctor_id=? AND a.patient_id=? LIMIT 1');
        $stmt->execute([$appointmentId, $doctorId, $patientId]);
        $place = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($place)) {
            $address = trim(implode(', ', array_filter(array_map('clinical_portable_text', [
                trim(implode(' ', array_filter([$place['calle'], $place['num_ext'], $place['num_int']]))),
                $place['colonia'], $place['municipio'], $place['estado'], $place['cp'],
            ]))));
            $consultorio = ['name' => clinical_portable_text($place['titulo'] ?: $place['grupo_nombre']), 'address' => $address];
            if ($consultorio['name'] === '' && $consultorio['address'] === '') $consultorio = null;
        }
    }
    return [
        'patient' => ['name' => clinical_portable_text($patient['display_name']), 'birthdate' => clinical_portable_text($patient['birthdate'] ?? '') ?: null],
        'physician' => [
            'doctor_id' => $doctorId, 'name' => $name, 'name_source' => $nameSource,
            'prefix' => clinical_portable_text($profile['prefix'] ?? '') ?: null,
            'designation' => clinical_portable_text($profile['professional_designation'] ?? '') ?: null,
            'specialty' => clinical_portable_text($profile['specialty_primary'] ?? '') ?: null,
            'professional_license' => $professional ?: null, 'professional_license_source' => $professionalSource,
            'specialty_license' => $specialtyLicense ?: null, 'specialty_license_source' => $specialtySource,
        ],
        'consultorio' => $consultorio,
    ];
}

function clinical_portable_issue_payload(PDO $pdo, array $payload, string $documentType, string $doctorId,
    string $patientId, ?string $appointmentId, string $issuedAt): array
{
    // A submitted snapshot is never an authority, including on replacement.
    unset($payload['portable_order_snapshot'], $payload['portable_order_snapshot_version']);
    if (!clinical_study_order_type($documentType)) return $payload;
    $identity = clinical_portable_identity($pdo, $doctorId, $patientId, $appointmentId, false);
    $payload['portable_order_snapshot_version'] = 1;
    $payload['portable_order_snapshot'] = $identity + ['captured_at' => $issuedAt];
    return $payload;
}

function clinical_portable_legacy_doctor(PDO $pdo, array $order, string $sessionDoctorId, string $sessionUserId): ?string
{
    if (clinical_portable_table_exists($pdo, 'clinical_idempotency_requests')) {
        $stmt = $pdo->prepare('SELECT doctor_id FROM clinical_idempotency_requests WHERE document_id=? AND committed_at IS NOT NULL ORDER BY request_id DESC LIMIT 1');
        $stmt->execute([(int)$order['id']]);
        $owner = clinical_portable_text($stmt->fetchColumn());
        if ($owner !== '') return $owner;
    }
    $encounterId = (int)($order['encounter_ref_id'] ?: $order['encounter_id']);
    if ($encounterId > 0 && clinical_portable_table_exists($pdo, 'clinical_encounters')) {
        $stmt = $pdo->prepare('SELECT doctor_id FROM clinical_encounters WHERE encounter_id=? AND patient_id=? LIMIT 1');
        $stmt->execute([$encounterId, $order['patient_id']]);
        $owner = clinical_portable_text($stmt->fetchColumn());
        if ($owner !== '') return $owner;
    }
    if (clinical_portable_text($order['appointment_id'] ?? '') !== '' && clinical_portable_table_exists($pdo, 'agenda_appointments')) {
        $stmt = $pdo->prepare('SELECT doctor_id FROM agenda_appointments WHERE appointment_id=? AND patient_id=? LIMIT 1');
        $stmt->execute([$order['appointment_id'], $order['patient_id']]);
        $owner = clinical_portable_text($stmt->fetchColumn());
        if ($owner !== '') return $owner;
    }
    if ($sessionUserId !== '' && hash_equals($sessionUserId, clinical_portable_text($order['created_by_user_id'])) && $sessionDoctorId !== '') {
        return $sessionDoctorId;
    }
    return null;
}

/** An exact, minimal, doctor-authorized projection; no result or chart joins. */
function clinical_portable_order_read(PDO $pdo, string $uuid, string $doctorId, string $userId): array
{
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $uuid) !== 1) {
        throw new ClinicalPortableOrderException('Orden no encontrada.', 404);
    }
    $stmt = $pdo->prepare('SELECT id,document_uuid,document_type,version,status,patient_id,appointment_id,encounter_id,encounter_ref_id,created_by_user_id,generated_at,payload_json FROM clinical_documents WHERE document_uuid=? LIMIT 1');
    $stmt->execute([$uuid]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($order) || !clinical_study_order_type((string)$order['document_type'])) {
        throw new ClinicalPortableOrderException('Orden no encontrada.', 404);
    }
    $scope = $pdo->prepare("SELECT COUNT(*) FROM patients_doctor_links WHERE doctor_id=? AND patient_id=? AND status='active'");
    $scope->execute([$doctorId, $order['patient_id']]);
    if ((int)$scope->fetchColumn() === 0) throw new ClinicalPortableOrderException('Orden no encontrada.', 404);
    $payload = json_decode((string)$order['payload_json'], true);
    $payload = is_array($payload) ? $payload : [];
    $parent = $pdo->prepare('SELECT original_document_id FROM clinical_document_revisions WHERE new_document_id=? LIMIT 1');
    $parent->execute([(int)$order['id']]);
    $rootId = (int)($parent->fetchColumn() ?: $order['id']);
    $ordinal = $pdo->prepare('SELECT COUNT(*) FROM clinical_document_revisions WHERE original_document_id=? AND new_document_id<=?');
    $ordinal->execute([$rootId, (int)$order['id']]);
    $lineageOrdinal = 1 + (int)$ordinal->fetchColumn();
    $revision = $pdo->prepare('SELECT COUNT(*) FROM clinical_document_revisions WHERE supersedes_document_id=? OR (original_document_id=? AND supersedes_document_id IS NULL)');
    $revision->execute([(int)$order['id'], (int)$order['id']]);
    if ((int)$revision->fetchColumn() > 0 || strtolower(clinical_portable_text($payload['status'] ?? '')) === 'replaced'
        || clinical_portable_text($payload['replaced_by_document_uuid'] ?? '') !== ''
        || clinical_portable_text($payload['replaced_by_document_id'] ?? '') !== '') {
        throw new ClinicalPortableOrderException('Esta versión fue reemplazada y no puede imprimirse como orden vigente.');
    }
    if ($order['status'] === 'voided' || strtolower(clinical_portable_text($payload['status'] ?? '')) === 'voided') {
        throw new ClinicalPortableOrderException('Esta orden fue anulada y no puede imprimirse.');
    }
    $issuedAt = clinical_portable_text($order['generated_at']);
    if (!in_array($order['status'], ['generated', 'signed'], true)
        || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $issuedAt) !== 1
        || strtotime($issuedAt . ' UTC') === false || str_starts_with($issuedAt, '0000-')) {
        throw new ClinicalPortableOrderException('Esta orden todavía no está emitida para impresión.');
    }
    $items = [];
    if ((int)($payload['order_payload_version'] ?? 0) === 2) {
        if (!is_array($payload['order_items'] ?? null)) throw new ClinicalPortableOrderException('Esta orden contiene estudios incompletos.');
        if (!array_is_list($payload['order_items'])) throw new ClinicalPortableOrderException('Esta orden contiene estudios incompletos.');
        foreach ($payload['order_items'] as $item) {
            if (!is_array($item) || !is_string($item['study_display_name'] ?? null)
                || clinical_portable_text($item['study_display_name']) === ''
                || !is_int($item['sequence'] ?? null) && !(is_string($item['sequence'] ?? null) && ctype_digit($item['sequence']))
                || ($item['note'] ?? null) !== null && !is_string($item['note'])) {
                throw new ClinicalPortableOrderException('Esta orden contiene estudios incompletos.');
            }
            if (isset($item['pathology_order_parameters'])
                && (!is_array($item['pathology_order_parameters'])
                    || clinical_portable_text($item['pathology_order_parameters_label'] ?? '') === '')) {
                throw new ClinicalPortableOrderException('Esta orden contiene parámetros de patología incompletos.');
            }
            $items[] = ['sequence' => (int)$item['sequence'], 'name' => clinical_portable_text($item['study_display_name']),
                'note' => clinical_portable_text($item['note'] ?? '') ?: null,
                'dental_context' => clinical_portable_text($item['dental_location_label'] ?? '') ?: null,
                'specimen_context' => clinical_specimen_print_context(is_array($item['specimen_collection_requirements'] ?? null) ? $item['specimen_collection_requirements'] : null, (string)$item['study_display_name']),
                'pathology_context' => clinical_portable_text($item['pathology_order_parameters_label'] ?? '') ?: null];
        }
        usort($items, static fn(array $a, array $b): int => $a['sequence'] <=> $b['sequence']);
        foreach ($items as $index => $item) {
            if ($item['sequence'] !== $index + 1) throw new ClinicalPortableOrderException('Esta orden contiene estudios fuera de secuencia.');
        }
    } elseif (is_array($payload['requested_studies'] ?? null)) {
        if (!array_is_list($payload['requested_studies'])) throw new ClinicalPortableOrderException('Esta orden contiene estudios incompletos.');
        foreach ($payload['requested_studies'] as $study) {
            if (!is_string($study) || trim($study) === '') throw new ClinicalPortableOrderException('Esta orden contiene estudios incompletos.');
            $items[] = ['sequence' => count($items) + 1, 'name' => trim($study), 'note' => null, 'dental_context' => null, 'specimen_context' => null, 'pathology_context' => null];
        }
    }
    if ($items === []) throw new ClinicalPortableOrderException('Esta orden no contiene estudios legibles para imprimir.');
    $snapshot = $payload['portable_order_snapshot'] ?? null;
    $snapshotSource = 'ISSUANCE_SNAPSHOT';
    if ((int)($payload['portable_order_snapshot_version'] ?? 0) !== 1 || !is_array($snapshot)) {
        $owner = clinical_portable_legacy_doctor($pdo, $order, $doctorId, $userId);
        if ($owner === null) throw new ClinicalPortableOrderException('No se pudo identificar al médico que emitió esta orden histórica.');
        $snapshot = clinical_portable_identity($pdo, $owner, (string)$order['patient_id'], clinical_portable_text($order['appointment_id'] ?? '') ?: null);
        $snapshotSource = 'LEGACY_RECONSTRUCTED';
    }
    $patient = is_array($snapshot['patient'] ?? null) ? $snapshot['patient'] : [];
    $physician = is_array($snapshot['physician'] ?? null) ? $snapshot['physician'] : [];
    if (clinical_portable_text($patient['name'] ?? '') === '' || clinical_portable_text($physician['name'] ?? '') === '') {
        throw new ClinicalPortableOrderException('Falta la identificación del paciente o del médico solicitante.');
    }
    $priority = clinical_portable_text($payload['priority'] ?? '');
    $priority = ['priority_routine' => 'Rutinario', 'priority_urgent' => 'Urgente',
        'priority_stat' => 'Prioridad (STAT)'][strtolower($priority)] ?? $priority;
    if ($priority === '' && is_array($payload['flags'] ?? null)) {
        foreach (['priority_stat' => 'Prioridad (STAT)', 'priority_urgent' => 'Urgente', 'priority_routine' => 'Rutinario'] as $flag => $label) {
            if (in_array($flag, $payload['flags'], true)) { $priority = $label; break; }
        }
    }
    $reference = strtoupper(substr(hash('sha256', strtolower($uuid)), 0, 12));
    return [
        'document_uuid' => $order['document_uuid'], 'document_version' => (int)$order['version'],
        'lineage_revision' => $lineageOrdinal, 'status' => $order['status'],
        'issued_at' => $issuedAt, 'identity_snapshot_source' => $snapshotSource,
        'patient' => ['name' => $patient['name'], 'birthdate' => $patient['birthdate'] ?? null],
        'physician' => array_diff_key($physician, ['doctor_id' => true]),
        'consultorio' => $snapshot['consultorio'] ?? null,
        'studies' => array_map(static fn(array $item): array => ['name' => $item['name'], 'note' => $item['note'], 'dental_context' => $item['dental_context'], 'specimen_context' => $item['specimen_context'] ?? null, 'pathology_context' => $item['pathology_context'] ?? null], $items),
        'priority' => $priority ?: null, 'indication' => clinical_portable_text($payload['indication'] ?? '') ?: null,
        'display_reference' => $reference,
    ];
}
