<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_documents.php';
require_once __DIR__ . '/clinical_portable_order.php';
require_once __DIR__ . '/clinical_legal_document_presentation.php';
require_once __DIR__ . '/clinical_prescription_binding.php';
require_once __DIR__ . '/clinical_professional_presentation.php';

final class ClinicalPrescriptionRegimenException extends InvalidArgumentException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}

function clinical_prescription_regimen_error(string $code, int $position, string $message): never
{
    throw new ClinicalPrescriptionRegimenException($code, 'Medicamento ' . $position . ': ' . $message);
}

function clinical_prescription_regimen_exact_keys(array $value, array $allowed, int $position): void
{
    foreach (array_keys($value) as $key) {
        if (!is_string($key) || !in_array($key, $allowed, true))
            clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_UNKNOWN_FIELD', $position,
                'el régimen contiene un campo no permitido.');
    }
}

function clinical_prescription_regimen_text(mixed $value, int $limit, int $position): string
{
    try { return clinical_prescription_v2_text($value, $limit); }
    catch (InvalidArgumentException) {
        clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_TEXT_INVALID', $position,
            'el texto contiene caracteres no válidos o excede la longitud permitida.');
    }
}

function clinical_prescription_regimen_positive_number(mixed $value, int $position, string $message): string
{
    if (!is_int($value) && !is_float($value) && !is_string($value))
        clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_NUMBER_INVALID', $position, $message);
    $text = trim((string)$value);
    if (!preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $text) || (float)$text <= 0)
        clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_NUMBER_INVALID', $position, $message);
    return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
}

function clinical_prescription_regimen_interval(mixed $raw, int $position, string $message,
    array $units = ['MINUTE','HOUR','DAY']): array
{
    if (!is_array($raw) || array_is_list($raw))
        clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_INTERVAL_INVALID', $position, $message);
    clinical_prescription_regimen_exact_keys($raw, ['value','unit'], $position);
    $value = clinical_prescription_regimen_positive_number($raw['value'] ?? null, $position, $message);
    $unit = $raw['unit'] ?? null;
    if (!is_string($unit) || !in_array($unit, $units, true))
        clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_INTERVAL_INVALID', $position, $message);
    return ['value'=>$value,'unit'=>$unit];
}

function clinical_prescription_regimen_date(string $value, int $position): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value)
        clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_DATE_INVALID', $position,
            'captura una fecha de término válida.');
    return $value;
}

function clinical_prescription_regimen_normalize(array $raw, int $position, ?string $issueDate): array
{
    clinical_prescription_regimen_exact_keys($raw, ['administration','duration'], $position);
    $administration = $raw['administration'] ?? null;
    if (!is_array($administration) || array_is_list($administration))
        clinical_prescription_regimen_error('PRESCRIPTION_ADMINISTRATION_REQUIRED', $position,
            'selecciona cómo debe administrarse.');
    $pattern = $administration['pattern'] ?? null;
    if (!is_string($pattern) || !in_array($pattern, ['SCHEDULED','AS_NEEDED','PHYSICIAN_DEFINED'], true))
        clinical_prescription_regimen_error('PRESCRIPTION_ADMINISTRATION_REQUIRED', $position,
            'selecciona cómo debe administrarse.');
    $normalizedAdministration = ['pattern'=>$pattern];
    if ($pattern === 'SCHEDULED') {
        clinical_prescription_regimen_exact_keys($administration, ['pattern','instruction'], $position);
        $instruction = clinical_prescription_regimen_text($administration['instruction'] ?? '', 500, $position);
        if ($instruction === '') clinical_prescription_regimen_error('PRESCRIPTION_SCHEDULE_REQUIRED', $position,
            'especifica el horario o intervalo de administración.');
        $normalizedAdministration['instruction'] = $instruction;
    } elseif ($pattern === 'PHYSICIAN_DEFINED') {
        clinical_prescription_regimen_exact_keys($administration, ['pattern','instruction'], $position);
        $instruction = clinical_prescription_regimen_text($administration['instruction'] ?? '', 1000, $position);
        if ($instruction === '') clinical_prescription_regimen_error('PRESCRIPTION_PHYSICIAN_REGIMEN_REQUIRED', $position,
            'describe cómo debe administrarse el medicamento.');
        $normalizedAdministration['instruction'] = $instruction;
    } else {
        clinical_prescription_regimen_exact_keys($administration, ['pattern','indication','repeat_mode',
            'minimum_interval','maximum_dose_per_period','maximum_administrations_per_period'], $position);
        $indication = clinical_prescription_regimen_text($administration['indication'] ?? '', 500, $position);
        if ($indication === '') clinical_prescription_regimen_error('PRESCRIPTION_PRN_INDICATION_REQUIRED', $position,
            'indica cuándo debe usarse según necesidad.');
        $repeat = $administration['repeat_mode'] ?? null;
        if (!is_string($repeat) || !in_array($repeat, ['SINGLE_ADMINISTRATION','REPEATABLE'], true))
            clinical_prescription_regimen_error('PRESCRIPTION_PRN_REPEAT_MODE_REQUIRED', $position,
                'indica si es una administración única o repetible.');
        $normalizedAdministration += ['indication'=>$indication,'repeat_mode'=>$repeat];
        $minimum = $administration['minimum_interval'] ?? null;
        $maxDose = $administration['maximum_dose_per_period'] ?? null;
        $maxAdministrations = $administration['maximum_administrations_per_period'] ?? null;
        if ($repeat === 'REPEATABLE') {
            if ($minimum === null) clinical_prescription_regimen_error('PRESCRIPTION_PRN_INTERVAL_REQUIRED', $position,
                'especifica el intervalo mínimo entre administraciones o marca administración única.');
            $normalizedAdministration['minimum_interval'] = clinical_prescription_regimen_interval($minimum,
                $position, 'captura un intervalo mínimo válido.');
        } elseif ($minimum !== null || $maxDose !== null || $maxAdministrations !== null) {
            clinical_prescription_regimen_error('PRESCRIPTION_PRN_LIMIT_CONTRADICTION', $position,
                'una administración única no puede incluir límites de repetición.');
        }
        if ($maxDose !== null) {
            if (!is_array($maxDose) || array_is_list($maxDose)) clinical_prescription_regimen_error(
                'PRESCRIPTION_PRN_MAXIMUM_INVALID', $position, 'captura un máximo de dosis válido.');
            clinical_prescription_regimen_exact_keys($maxDose, ['value','unit','period'], $position);
            $unit = clinical_prescription_regimen_text($maxDose['unit'] ?? '', 80, $position);
            if ($unit === '') clinical_prescription_regimen_error('PRESCRIPTION_PRN_MAXIMUM_INVALID', $position,
                'captura la unidad exacta del máximo de dosis.');
            $normalizedAdministration['maximum_dose_per_period'] = [
                'value'=>clinical_prescription_regimen_positive_number($maxDose['value'] ?? null, $position,
                    'captura un máximo de dosis válido.'),
                'unit'=>$unit,
                'period'=>clinical_prescription_regimen_interval($maxDose['period'] ?? null, $position,
                    'captura el periodo del máximo de dosis.', ['HOUR','DAY']),
            ];
        }
        if ($maxAdministrations !== null) {
            if (!is_array($maxAdministrations) || array_is_list($maxAdministrations)) clinical_prescription_regimen_error(
                'PRESCRIPTION_PRN_MAXIMUM_INVALID', $position, 'captura un máximo de administraciones válido.');
            clinical_prescription_regimen_exact_keys($maxAdministrations, ['count','period'], $position);
            $count = $maxAdministrations['count'] ?? null;
            if (!is_int($count) || $count < 1) clinical_prescription_regimen_error(
                'PRESCRIPTION_PRN_MAXIMUM_INVALID', $position, 'captura un número máximo de administraciones válido.');
            $normalizedAdministration['maximum_administrations_per_period'] = ['count'=>$count,
                'period'=>clinical_prescription_regimen_interval($maxAdministrations['period'] ?? null, $position,
                    'captura el periodo del máximo de administraciones.', ['HOUR','DAY'])];
        }
    }

    $duration = $raw['duration'] ?? null;
    if (!is_array($duration) || array_is_list($duration)) clinical_prescription_regimen_error(
        'PRESCRIPTION_DURATION_REQUIRED', $position, 'selecciona cómo termina el tratamiento.');
    $mode = $duration['mode'] ?? null;
    if (!is_string($mode) || !in_array($mode, ['DEFINED_DURATION','CONTINUOUS','UNTIL_REASSESSMENT',
        'UNTIL_DATE','PHYSICIAN_DEFINED'], true)) clinical_prescription_regimen_error(
            'PRESCRIPTION_DURATION_REQUIRED', $position, 'selecciona cómo termina el tratamiento.');
    $normalizedDuration = ['mode'=>$mode];
    if ($mode === 'DEFINED_DURATION') {
        clinical_prescription_regimen_exact_keys($duration, ['mode','value','unit'], $position);
        $normalizedDuration['value'] = clinical_prescription_regimen_positive_number($duration['value'] ?? null,
            $position, 'captura una duración positiva.');
        if (!is_string($duration['unit'] ?? null) || !in_array($duration['unit'], ['DAY','WEEK','MONTH'], true))
            clinical_prescription_regimen_error('PRESCRIPTION_DURATION_UNIT_INVALID', $position,
                'selecciona una unidad de duración válida.');
        $normalizedDuration['unit'] = $duration['unit'];
    } elseif ($mode === 'UNTIL_DATE') {
        clinical_prescription_regimen_exact_keys($duration, ['mode','date'], $position);
        $date = clinical_prescription_regimen_date((string)($duration['date'] ?? ''), $position);
        if ($issueDate !== null && $date < $issueDate) clinical_prescription_regimen_error('PRESCRIPTION_DURATION_DATE_PRECEDES_ISSUE',
            $position, 'la fecha de término no puede ser anterior a la fecha de emisión.');
        $normalizedDuration['date'] = $date;
    } elseif ($mode === 'PHYSICIAN_DEFINED') {
        clinical_prescription_regimen_exact_keys($duration, ['mode','instruction'], $position);
        $instruction = clinical_prescription_regimen_text($duration['instruction'] ?? '', 1000, $position);
        if ($instruction === '') clinical_prescription_regimen_error('PRESCRIPTION_DURATION_INSTRUCTION_REQUIRED',
            $position, 'describe la condición de término del tratamiento.');
        $normalizedDuration['instruction'] = $instruction;
    } else {
        clinical_prescription_regimen_exact_keys($duration, ['mode'], $position);
    }
    return ['administration'=>$normalizedAdministration,'duration'=>$normalizedDuration];
}

function clinical_prescription_regimen_draft_number(mixed $value, int $position, string $message): string
{
    if ($value === null || $value === '') return '';
    return clinical_prescription_regimen_positive_number($value, $position, $message);
}

function clinical_prescription_regimen_draft_interval(mixed $raw, int $position, string $message,
    array $units = ['MINUTE','HOUR','DAY']): array
{
    if ($raw === null) return ['value'=>'','unit'=>''];
    if (!is_array($raw) || array_is_list($raw))
        clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_INTERVAL_INVALID', $position, $message);
    clinical_prescription_regimen_exact_keys($raw, ['value','unit'], $position);
    $value = clinical_prescription_regimen_draft_number($raw['value'] ?? '', $position, $message);
    $unit = $raw['unit'] ?? '';
    if (!is_string($unit) || ($unit !== '' && !in_array($unit, $units, true)))
        clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_INTERVAL_INVALID', $position, $message);
    return ['value'=>$value,'unit'=>$unit];
}

/** Preserve a known, possibly incomplete V2 shape for editable drafts without making it issuable. */
function clinical_prescription_regimen_draft_normalize(array $raw, int $position): array
{
    clinical_prescription_regimen_exact_keys($raw, ['administration','duration'], $position);
    $administration = $raw['administration'] ?? [];
    $duration = $raw['duration'] ?? [];
    if (!is_array($administration) || array_is_list($administration)
        || !is_array($duration) || array_is_list($duration))
        clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_INVALID', $position,
            'la pauta estructurada no tiene un formato válido.');
    $pattern = $administration['pattern'] ?? '';
    if (!is_string($pattern) || ($pattern !== ''
        && !in_array($pattern, ['SCHEDULED','AS_NEEDED','PHYSICIAN_DEFINED'], true)))
        clinical_prescription_regimen_error('PRESCRIPTION_ADMINISTRATION_REQUIRED', $position,
            'selecciona cómo debe administrarse.');
    $a = ['pattern'=>$pattern];
    if ($pattern === 'SCHEDULED' || $pattern === 'PHYSICIAN_DEFINED' || $pattern === '') {
        clinical_prescription_regimen_exact_keys($administration, ['pattern','instruction'], $position);
        $a['instruction'] = clinical_prescription_regimen_text($administration['instruction'] ?? '',
            $pattern === 'PHYSICIAN_DEFINED' ? 1000 : 500, $position);
    } else {
        clinical_prescription_regimen_exact_keys($administration, ['pattern','indication','repeat_mode',
            'minimum_interval','maximum_dose_per_period','maximum_administrations_per_period'], $position);
        $a['indication'] = clinical_prescription_regimen_text($administration['indication'] ?? '', 500, $position);
        $repeat = $administration['repeat_mode'] ?? '';
        if (!is_string($repeat) || ($repeat !== '' && !in_array($repeat,
            ['SINGLE_ADMINISTRATION','REPEATABLE'], true))) clinical_prescription_regimen_error(
                'PRESCRIPTION_PRN_REPEAT_MODE_REQUIRED', $position,
                'indica si es una administración única o repetible.');
        $a['repeat_mode'] = $repeat;
        $hasMinimum = array_key_exists('minimum_interval', $administration);
        $hasMaxDose = array_key_exists('maximum_dose_per_period', $administration);
        $hasMaxAdministrations = array_key_exists('maximum_administrations_per_period', $administration);
        if ($repeat === 'SINGLE_ADMINISTRATION' && ($hasMinimum || $hasMaxDose || $hasMaxAdministrations))
            clinical_prescription_regimen_error('PRESCRIPTION_PRN_LIMIT_CONTRADICTION', $position,
                'una administración única no puede incluir límites de repetición.');
        if ($repeat === 'REPEATABLE' || $hasMinimum) $a['minimum_interval'] =
            clinical_prescription_regimen_draft_interval($administration['minimum_interval'] ?? null,
                $position, 'captura un intervalo mínimo válido.');
        if ($hasMaxDose) {
            $maximum = $administration['maximum_dose_per_period'];
            if (!is_array($maximum) || array_is_list($maximum)) clinical_prescription_regimen_error(
                'PRESCRIPTION_PRN_MAXIMUM_INVALID', $position, 'captura un máximo de dosis válido.');
            clinical_prescription_regimen_exact_keys($maximum, ['value','unit','period'], $position);
            $a['maximum_dose_per_period'] = [
                'value'=>clinical_prescription_regimen_draft_number($maximum['value'] ?? '', $position,
                    'captura un máximo de dosis válido.'),
                'unit'=>clinical_prescription_regimen_text($maximum['unit'] ?? '', 80, $position),
                'period'=>clinical_prescription_regimen_draft_interval($maximum['period'] ?? null, $position,
                    'captura el periodo del máximo de dosis.', ['HOUR','DAY'])];
        }
        if ($hasMaxAdministrations) {
            $maximum = $administration['maximum_administrations_per_period'];
            if (!is_array($maximum) || array_is_list($maximum)) clinical_prescription_regimen_error(
                'PRESCRIPTION_PRN_MAXIMUM_INVALID', $position, 'captura un máximo de administraciones válido.');
            clinical_prescription_regimen_exact_keys($maximum, ['count','period'], $position);
            $count = $maximum['count'] ?? null;
            if ($count !== null && $count !== '' && (!is_int($count) || $count < 1))
                clinical_prescription_regimen_error('PRESCRIPTION_PRN_MAXIMUM_INVALID', $position,
                    'captura un número máximo de administraciones válido.');
            $a['maximum_administrations_per_period'] = ['count'=>$count === '' || $count === null ? 0 : $count,
                'period'=>clinical_prescription_regimen_draft_interval($maximum['period'] ?? null, $position,
                    'captura el periodo del máximo de administraciones.', ['HOUR','DAY'])];
        }
    }
    $mode = $duration['mode'] ?? '';
    if (!is_string($mode) || ($mode !== '' && !in_array($mode, ['DEFINED_DURATION','CONTINUOUS',
        'UNTIL_REASSESSMENT','UNTIL_DATE','PHYSICIAN_DEFINED'], true))) clinical_prescription_regimen_error(
            'PRESCRIPTION_DURATION_REQUIRED', $position, 'selecciona cómo termina el tratamiento.');
    $d = ['mode'=>$mode];
    if ($mode === 'DEFINED_DURATION' || $mode === '') {
        clinical_prescription_regimen_exact_keys($duration, ['mode','value','unit'], $position);
        $d['value'] = clinical_prescription_regimen_draft_number($duration['value'] ?? '', $position,
            'captura una duración positiva.');
        $unit = $duration['unit'] ?? '';
        if (!is_string($unit) || ($unit !== '' && !in_array($unit, ['DAY','WEEK','MONTH'], true)))
            clinical_prescription_regimen_error('PRESCRIPTION_DURATION_UNIT_INVALID', $position,
                'selecciona una unidad de duración válida.');
        $d['unit'] = $unit;
    } elseif ($mode === 'UNTIL_DATE') {
        clinical_prescription_regimen_exact_keys($duration, ['mode','date'], $position);
        $date = $duration['date'] ?? '';
        if (!is_string($date)) clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_DATE_INVALID', $position,
            'captura una fecha de término válida.');
        $d['date'] = $date === '' ? '' : clinical_prescription_regimen_date($date, $position);
    } elseif ($mode === 'PHYSICIAN_DEFINED') {
        clinical_prescription_regimen_exact_keys($duration, ['mode','instruction'], $position);
        $d['instruction'] = clinical_prescription_regimen_text($duration['instruction'] ?? '', 1000, $position);
    } else clinical_prescription_regimen_exact_keys($duration, ['mode'], $position);
    return ['administration'=>$a,'duration'=>$d];
}

function clinical_prescription_regimen_period(array $period): string
{
    $value = (string)$period['value'];
    $singular = (float)$value === 1.0;
    $unit = match ($period['unit']) {
        'MINUTE' => $singular ? 'minuto' : 'minutos',
        'HOUR' => $singular ? 'hora' : 'horas',
        'DAY' => $singular ? 'día' : 'días',
        'WEEK' => $singular ? 'semana' : 'semanas',
        'MONTH' => $singular ? 'mes' : 'meses',
    };
    return $value . ' ' . $unit;
}

/** Deterministic patient-facing compatibility projection for schema V2. */
function clinical_prescription_regimen_format(array $regimen): array
{
    $administration = $regimen['administration'];
    if ($administration['pattern'] === 'SCHEDULED') $frequency = $administration['instruction'];
    elseif ($administration['pattern'] === 'PHYSICIAN_DEFINED')
        $frequency = 'Régimen indicado por el médico: ' . $administration['instruction'];
    else {
        $parts = ['Según necesidad: ' . $administration['indication']];
        if ($administration['repeat_mode'] === 'SINGLE_ADMINISTRATION') $parts[] = 'Administración única';
        else {
            $parts[] = 'Intervalo mínimo: ' . clinical_prescription_regimen_period($administration['minimum_interval']);
            if (isset($administration['maximum_dose_per_period'])) {
                $maximum = $administration['maximum_dose_per_period'];
                $parts[] = 'Máximo: ' . $maximum['value'] . ' ' . $maximum['unit'] . ' por '
                    . clinical_prescription_regimen_period($maximum['period']);
            }
            if (isset($administration['maximum_administrations_per_period'])) {
                $maximum = $administration['maximum_administrations_per_period'];
                $parts[] = 'Máximo: ' . $maximum['count'] . ' administraciones por '
                    . clinical_prescription_regimen_period($maximum['period']);
            }
        }
        $frequency = implode(' · ', $parts);
    }
    $duration = $regimen['duration'];
    $durationText = match ($duration['mode']) {
        'DEFINED_DURATION' => 'Durante ' . clinical_prescription_regimen_period($duration),
        'CONTINUOUS' => 'Tratamiento continuo',
        'UNTIL_REASSESSMENT' => 'Hasta nueva valoración médica',
        'UNTIL_DATE' => 'Hasta el ' . $duration['date'],
        'PHYSICIAN_DEFINED' => $duration['instruction'],
    };
    return ['frequency'=>$frequency,'duration'=>$durationText];
}

function clinical_prescription_regimen_quantity(string $quantity, int $position): void
{
    $folded = mb_strtolower($quantity, 'UTF-8');
    if ($quantity === '' || !preg_match('/(?:^|\D)[1-9][0-9]*(?:\.\d+)?(?:\D|$)/u', $quantity)
        || in_array($folded, ['ilimitado','infinito','indefinido','sin límite','sin limite','unlimited','infinite','undefined'], true))
        clinical_prescription_regimen_error('PRESCRIPTION_QUANTITY_REQUIRED', $position,
            'captura una cantidad finita a dispensar.');
}

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

/**
 * Resolve the physician logo from the canonical immutable Media publication record.
 * A profile reference alone is insufficient: the referenced asset must still be the
 * physician's PUBLIC/READY personal logo.
 */
function clinical_prescription_v2_published_logo(PDO $pdo, string $doctorId): string
{
    try {
        $stmt = $pdo->prepare(
            "SELECT a.public_url
               FROM profiles_doctors p
               JOIN media_assets a ON a.public_url = p.logo_url
              WHERE p.doctor_id = ?
                AND a.owner_type = 'PHYSICIAN'
                AND a.owner_id = p.doctor_id
                AND a.purpose = 'PHYSICIAN_PERSONAL_LOGO'
                AND a.classification = 'PUBLIC'
                AND a.status = 'READY'
                AND a.mime_type = 'image/webp'
              LIMIT 1"
        );
        $stmt->execute([$doctorId]);
        $url = trim((string)($stmt->fetchColumn() ?: ''));
    } catch (Throwable) {
        return '';
    }

    return preg_match('#^/api/media/index\.php/public/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$#iD', $url)
        ? $url : '';
}

function clinical_prescription_v2_published_logo_url(string $url): bool
{
    return preg_match('#^/api/media/index\.php/public/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$#iD', trim($url)) === 1;
}

/** Prescription-only presentation v2. V1 shown/hidden remains byte-compatible for stored documents. */
function clinical_prescription_v2_presentation(array $payload): array
{
    $presentation = is_array($payload['presentation'] ?? null) ? $payload['presentation'] : [];
    if (($presentation['version'] ?? null) === 2
        && in_array($presentation['professional_header'] ?? null, ['hidden','data_only','logo_and_data'], true)) {
        return ['version'=>2,'professional_header'=>$presentation['professional_header']];
    }
    if (clinical_legal_document_presentation_valid($payload)) {
        return ['version'=>1,'professional_header'=>(($presentation['professional_header'] ?? 'shown') === 'hidden' ? 'hidden' : 'shown')];
    }
    throw new InvalidArgumentException('PRESCRIPTION_V2_CONTRACT_INVALID');
}

function clinical_prescription_v2_presentation_mode(array $payload): string
{
    $presentation = clinical_prescription_v2_presentation($payload);
    if ($presentation['version'] === 1) return $presentation['professional_header'] === 'hidden' ? 'hidden' : 'logo_and_data';
    return $presentation['professional_header'];
}

function clinical_prescription_v2_normalize(PDO $pdo, array $doctor, string $patientId, array $body,
    bool $final = false, ?string $authoritativeIssueDate = null): array
{
    $source = is_array($body['payload'] ?? null) ? $body['payload'] : [];
    if (($body['document_type'] ?? 'prescription') !== 'prescription'
        || (int)($source['contract_version'] ?? 0) !== 2)
        throw new InvalidArgumentException('PRESCRIPTION_V2_CONTRACT_INVALID');
    $presentation = clinical_prescription_v2_presentation($source);
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
    $prescription = is_array($source['prescription'] ?? null) ? $source['prescription'] : [];
    $schemaVersion = $prescription['medication_schema_version'] ?? 1;
    if (!is_int($schemaVersion) || !in_array($schemaVersion, [1,2], true))
        throw new ClinicalPrescriptionRegimenException('PRESCRIPTION_MEDICATION_SCHEMA_INVALID',
            'La versión del esquema de medicamentos no es válida.');
    $issueDate = $authoritativeIssueDate
        ?? (new DateTimeImmutable('now',new DateTimeZone('America/Mexico_City')))->format('Y-m-d');
    clinical_prescription_regimen_date($issueDate, 1);
    $raw = $prescription['items'] ?? null;
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
        if ($schemaVersion === 2) clinical_prescription_regimen_exact_keys($entry,
            ['item_id','name','presentation','dose','route','frequency','duration','quantity','instructions','private_notes','regimen'],
            $index + 1);
        elseif (array_key_exists('regimen', $entry)) clinical_prescription_regimen_error(
            'PRESCRIPTION_MEDICATION_SCHEMA_DOWNGRADE', $index + 1,
            'el régimen estructurado requiere medication_schema_version=2.');
        foreach (['name','presentation','dose','route','frequency','duration','quantity','instructions','private_notes'] as $field) {
            $limit = $field === 'instructions' || $field === 'private_notes' ? 4000 : 500;
            $item[$field] = $schemaVersion === 2
                ? clinical_prescription_regimen_text($entry[$field] ?? '', $limit, $index + 1)
                : clinical_prescription_v2_text($entry[$field] ?? '', $limit);
        }
        if ($schemaVersion === 2) {
            if ($final) {
                foreach (['name'=>'captura el nombre del medicamento.','presentation'=>'captura la presentación.',
                    'dose'=>'captura la dosis.','route'=>'captura la vía de administración.'] as $field=>$message) {
                    if ($item[$field] === '') clinical_prescription_regimen_error(
                        'PRESCRIPTION_MEDICATION_' . strtoupper($field) . '_REQUIRED', $index + 1, $message);
                }
            }
            if ($final || $item['quantity'] !== '') clinical_prescription_regimen_quantity($item['quantity'], $index + 1);
            if (!is_array($entry['regimen'] ?? null) || array_is_list($entry['regimen']))
                clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_REQUIRED', $index + 1,
                    'captura el régimen de administración y duración.');
            $item['regimen'] = $final
                ? clinical_prescription_regimen_normalize($entry['regimen'], $index + 1, $issueDate)
                : clinical_prescription_regimen_draft_normalize($entry['regimen'], $index + 1);
            try {
                $completeRegimen = clinical_prescription_regimen_normalize($item['regimen'], $index + 1, $issueDate);
                $projection = clinical_prescription_regimen_format($completeRegimen);
                foreach (['frequency','duration'] as $field) {
                    if ($item[$field] !== '' && $item[$field] !== $projection[$field])
                        clinical_prescription_regimen_error('PRESCRIPTION_REGIMEN_PROJECTION_CONFLICT', $index + 1,
                            'la proyección de ' . ($field === 'frequency' ? 'frecuencia' : 'duración') . ' contradice el régimen.');
                    $item[$field] = $projection[$field];
                }
                $item['regimen'] = $completeRegimen;
            } catch (ClinicalPrescriptionRegimenException $error) {
                if ($final) throw $error;
                $item['frequency'] = '';
                $item['duration'] = '';
            }
        }
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
    $logo = clinical_prescription_v2_published_logo($pdo, (string)$doctor['doctor_id']);
    $professionalPresentation = clinical_professional_presentation_resolve($pdo, (string)$doctor['doctor_id']);
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
        'prescription'=>array_merge($schemaVersion === 2 ? ['medication_schema_version'=>2] : [], ['items'=>$items,
            'observaciones'=>clinical_prescription_v2_text($prescription['observaciones'] ?? '', 4000)]),
        'diagnostic_context'=>['mode'=>$mode,'text'=>$diagnosisText],
        'patient_snapshot'=>['id'=>$patientId,'full_name'=>(string)$patient['name'],'age'=>$age,'sex'=>$sex],
        'physician_snapshot'=>['user_id'=>(string)$doctor['user_id'],'doctor_id'=>(string)$doctor['doctor_id'],
            'full_name'=>(string)$physician['name'],'prefix'=>(string)($physician['prefix'] ?? ''),
            'designation'=>(string)($physician['designation'] ?? ''),'specialty'=>(string)($physician['specialty'] ?? ''),
            'license'=>(string)($physician['professional_license'] ?? ''),
            'specialty_license'=>(string)($physician['specialty_license'] ?? '')],
        // Location publication is governed exclusively by professional_presentation V2.
        'professional_context'=>['consultorio'=>null,'logo_url'=>$logo],
        'professional_presentation'=>$professionalPresentation,
        'presentation'=>$presentation,
        'encounter_reference'=>$encounter === null ? null : ['encounter_id'=>(int)$encounter['encounter_id'],
            'appointment_id'=>$appointmentId ?: null],
        'issue_date'=>$issueDate,
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
    $presentationMode = clinical_prescription_v2_presentation_mode($payload);
    if ($presentationMode !== 'hidden') {
        $html .= '<header class="clinical-doc-head doc-base-medical-header doc-base-header-block doc-base-print-safe">';
        if ($presentationMode === 'logo_and_data' && clinical_prescription_v2_published_logo_url($logo))
            $html .= '<div class="clinical-doc-head-logo-slot"><img src="'.$h($logo).'" alt="Logo médico"></div>';
        $html .= '<div class="clinical-doc-head-main"><div class="clinical-doc-doctor-name">'.$h($doctor['full_name'] ?? '').'</div>';
        foreach (['designation'=>'','specialty'=>'','license'=>'Cédula profesional: ',
            'specialty_license'=>'Cédula de especialidad: '] as $key=>$prefix) {
            if (!empty($doctor[$key])) $html .= '<div class="clinical-doc-doctor-site">'.$h($prefix.$doctor[$key]).'</div>';
        }
        $usesAuthorizedFooter = (int)($payload['professional_presentation']['version'] ?? 0) >= 2;
        $place = $usesAuthorizedFooter ? [] : (array)($professional['consultorio'] ?? []);
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
    $html .= '</section></div>';
    $offices = (array)($payload['professional_presentation']['offices'] ?? []);
    if ($offices !== []) {
        $html .= '<div class="doc-base-flex-spacer" aria-hidden="true"></div>'
            . '<footer class="rx-doc-professional-footer doc-base-print-safe"><div class="rx-doc-professional-locations">';
        foreach (array_slice($offices, 0, 3) as $office) {
            if (!is_array($office)) continue;
            $name = trim((string)($office['name'] ?? ''));
            $address = trim((string)($office['address'] ?? ''));
            $phones = array_values(array_filter(array_map(static fn(mixed $v): string => trim((string)$v),
                (array)($office['phones'] ?? [])), static fn(string $v): bool => $v !== ''));
            if ($name === '' && $address === '' && $phones === []) continue;
            $html .= '<div class="rx-doc-professional-location">';
            if ($name !== '') $html .= '<div class="rx-doc-professional-location-name">'.$h($name).'</div>';
            if ($address !== '') $html .= '<div class="rx-doc-professional-location-address">'.$h($address).'</div>';
            if ($phones !== []) $html .= '<div class="rx-doc-professional-location-phones">Tel. '.$h(implode(' · ', $phones)).'</div>';
            $html .= '</div>';
        }
        $html .= '</div></footer>';
    }
    $html .= '</div></article>';
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
            $oldPayload = $existing === null ? null : json_decode((string)$existing['payload_json'],true);
            $oldIssueDate = is_array($oldPayload) && is_string($oldPayload['issue_date'] ?? null)
                ? $oldPayload['issue_date'] : null;
            $payload = clinical_prescription_v2_normalize($pdo,$doctor,$patientId,$body,$intent!=='draft',$oldIssueDate);
            $payload['status'] = $intent === 'finalize' ? 'generated' : 'draft';
            $encounterId = (int)($payload['encounter_reference']['encounter_id'] ?? 0);
            if ($existing !== null && (int)($existing['encounter_ref_id'] ?? 0) !== $encounterId)
                throw new InvalidArgumentException('PRESCRIPTION_DRAFT_ENCOUNTER_IMMUTABLE');
            if ($existing !== null && is_array($oldPayload)) {
                foreach (['patient_snapshot','physician_snapshot','professional_context','professional_presentation','issue_date','encounter_reference'] as $snapshotKey)
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
            $payload['prescription_snapshot'] = ['version'=>2,'html'=>$html];
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
