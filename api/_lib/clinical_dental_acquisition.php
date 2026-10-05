<?php
declare(strict_types=1);

/** Versioned acquisition intent for a future full-mouth intraoral series. */
function clinical_dental_acquisition_authority(): array
{
    static $authority = null;
    if ($authority !== null) return $authority;
    $path = __DIR__ . '/../../assets/data/clinical/dental-acquisition-protocols-v1.json';
    $source = json_decode((string)file_get_contents($path), true);
    if (!is_array($source) || ($source['authority'] ?? null) !== 'DENTAL_ACQUISITION_PROTOCOLS_V1'
        || ($source['contract_version'] ?? null) !== 1
        || ($source['study_key'] ?? null) !== 'dental_full_periapical_series'
        || !is_array($source['variants'] ?? null)) {
        throw new RuntimeException('DENTAL_ACQUISITION_AUTHORITY_UNAVAILABLE');
    }
    return $authority = $source;
}

function clinical_dental_acquisition_validate(?string $studyKey, mixed $raw): ?array
{
    if ($studyKey !== 'dental_full_periapical_series') {
        if ($raw !== null) throw new InvalidArgumentException('DENTAL_ACQUISITION_STUDY_INCOMPATIBLE');
        return null;
    }
    if (!is_array($raw) || array_is_list($raw)) throw new InvalidArgumentException('DENTAL_ACQUISITION_REQUIRED');
    if (array_diff(array_keys($raw), ['contract_version','protocol_key','dentition_mode'])) {
        throw new InvalidArgumentException('DENTAL_ACQUISITION_FIELD_INVALID');
    }
    $authority = clinical_dental_acquisition_authority();
    if (($raw['contract_version'] ?? null) !== $authority['contract_version']) {
        throw new InvalidArgumentException('DENTAL_ACQUISITION_VERSION_INVALID');
    }
    $key = $raw['protocol_key'] ?? null;
    if (!is_string($key) || !isset($authority['variants'][$key])) {
        throw new InvalidArgumentException('DENTAL_ACQUISITION_PROTOCOL_INVALID');
    }
    $dentition = $raw['dentition_mode'] ?? null;
    if (!is_string($dentition) || !in_array($dentition, $authority['dentition_modes'], true)) {
        throw new InvalidArgumentException('DENTAL_ACQUISITION_DENTITION_INVALID');
    }
    $variant = $authority['variants'][$key];
    return [
        'contract_version' => $authority['contract_version'],
        'protocol_key' => $key,
        'dentition_mode' => $dentition,
        'label_es' => $variant['label_es'],
        'nominal_image_count' => $variant['nominal_image_count'],
        'scope' => $variant['scope'],
        'composition' => $variant['composition'],
        'bitewing_inclusion' => $variant['bitewing_inclusion'],
        'nominal_count_semantics' => $variant['nominal_count_semantics'],
        'provider_confirmation_required' => $variant['provider_confirmation_required'],
    ];
}

function clinical_dental_acquisition_summary(?array $protocol): ?string
{
    return $protocol === null ? null : 'Protocolo: '.$protocol['label_es'];
}
