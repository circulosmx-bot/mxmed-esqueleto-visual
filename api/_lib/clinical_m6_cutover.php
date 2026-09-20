<?php
declare(strict_types=1);

final class ClinicalM6CohortConfigException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('M6_COHORT_CONFIG_INVALID');
    }
}

final class ClinicalM6LegacyWriteBlockedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('M6_LEGACY_WRITE_BLOCKED');
    }

    public function httpStatus(): int
    {
        return 409;
    }
}

function clinical_m6_cohort_config_invalid(): never
{
    throw new ClinicalM6CohortConfigException();
}

function clinical_m6_config_value(string $name): string
{
    $value = getenv($name);
    return $value === false ? '' : trim((string)$value);
}

function clinical_m6_emergency_off(): bool
{
    $value = strtolower(clinical_m6_config_value('MXMED_CLINICAL_M6_EMERGENCY_OFF'));
    if ($value === '' || in_array($value, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }
    if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    clinical_m6_cohort_config_invalid();
}

function clinical_m6_cohort_mode(): string
{
    $mode = strtolower(clinical_m6_config_value('MXMED_CLINICAL_M6_COHORT_MODE'));
    if ($mode === '' || $mode === 'off') {
        return 'off';
    }
    if ($mode === 'allowlist') {
        return 'allowlist';
    }

    clinical_m6_cohort_config_invalid();
}

function clinical_m6_cohort_identifier_valid(string $value): bool
{
    return $value !== '' && preg_match('/^[^\s|,;]+$/u', $value) === 1;
}

/**
 * @return list<array{doctor_id:string,patient_id:string}>
 */
function clinical_m6_cohort_pairs(): array
{
    if (clinical_m6_cohort_mode() === 'off') {
        return [];
    }

    $raw = clinical_m6_config_value('MXMED_CLINICAL_M6_COHORT_PAIRS');
    if ($raw === '') {
        clinical_m6_cohort_config_invalid();
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
    $entries = preg_split('/[\n,;]/', $normalized);
    if (!is_array($entries) || $entries === []) {
        clinical_m6_cohort_config_invalid();
    }

    $pairs = [];
    foreach ($entries as $entry) {
        $entry = trim((string)$entry);
        if ($entry === '' || substr_count($entry, '|') !== 1) {
            clinical_m6_cohort_config_invalid();
        }

        [$doctorId, $patientId] = array_map('trim', explode('|', $entry, 2));
        if (!clinical_m6_cohort_identifier_valid($doctorId)
            || !clinical_m6_cohort_identifier_valid($patientId)) {
            clinical_m6_cohort_config_invalid();
        }

        $key = $doctorId . "\0" . $patientId;
        $pairs[$key] = [
            'doctor_id' => $doctorId,
            'patient_id' => $patientId,
        ];
    }

    return array_values($pairs);
}

function clinical_m6_cohort_pair_configured(string $doctorId, string $patientId): bool
{
    $doctorId = trim($doctorId);
    $patientId = trim($patientId);
    if ($doctorId === '' || $patientId === '') {
        return false;
    }
    if (clinical_m6_cohort_mode() === 'off') {
        return false;
    }

    foreach (clinical_m6_cohort_pairs() as $pair) {
        if (hash_equals($pair['doctor_id'], $doctorId)
            && hash_equals($pair['patient_id'], $patientId)) {
            return true;
        }
    }

    return false;
}

function clinical_m6_cohort_authorized(string $doctorId, string $patientId): bool
{
    $doctorId = trim($doctorId);
    $patientId = trim($patientId);
    if ($doctorId === '' || $patientId === '') {
        return false;
    }
    if (clinical_m6_emergency_off()) {
        return false;
    }

    return clinical_m6_cohort_pair_configured($doctorId, $patientId);
}

function clinical_m6_patient_in_any_cohort(string $patientId): bool
{
    $patientId = trim($patientId);
    if ($patientId === '') {
        return false;
    }
    if (clinical_m6_cohort_mode() === 'off') {
        return false;
    }

    foreach (clinical_m6_cohort_pairs() as $pair) {
        if (hash_equals($pair['patient_id'], $patientId)) {
            return true;
        }
    }

    return false;
}

function clinical_m6_legacy_write_block_required(string $patientId): bool
{
    return clinical_m6_patient_in_any_cohort($patientId);
}

function clinical_m6_assert_legacy_write_allowed(string $patientId): void
{
    if (clinical_m6_legacy_write_block_required($patientId)) {
        throw new ClinicalM6LegacyWriteBlockedException();
    }
}
