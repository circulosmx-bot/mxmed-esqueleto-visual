<?php
declare(strict_types=1);

/** FUNC-CAT02A: bounded physician intent; technical acquisition remains provider-owned. */
function clinical_functional_authority(): array
{
    static $authority = null;
    if ($authority !== null) return $authority;
    $path = __DIR__.'/../../modules/clinical/catalog/functional_order_parameters_v1.json';
    $decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || ($decoded['version'] ?? null) !== 1
        || ($decoded['contract'] ?? null) !== 'functional_order_parameters'
        || !is_array($decoded['field_labels'] ?? null)
        || !is_array($decoded['value_labels'] ?? null)
        || !is_array($decoded['rules'] ?? null) || count($decoded['rules']) !== 37) {
        throw new RuntimeException('FUNCTIONAL_AUTHORITY_INVALID');
    }
    foreach ($decoded['rules'] as $key => $rule) {
        if (!is_string($key) || !is_array($rule)
            || !in_array($rule['status'] ?? null, ['ACTIVE_OPTIONAL','FUTURE_REQUIRED'], true)
            || !is_array($rule['allowed'] ?? null) || !is_array($rule['required'] ?? null)) {
            throw new RuntimeException('FUNCTIONAL_AUTHORITY_INVALID');
        }
        foreach ($rule['allowed'] as $field => $choices) {
            if (!isset($decoded['field_labels'][$field], $decoded['value_labels'][$field])
                || !is_array($choices) || $choices === [] || !array_is_list($choices)
                || count($choices) !== count(array_unique($choices))
                || array_diff($choices, array_keys($decoded['value_labels'][$field]))) {
                throw new RuntimeException('FUNCTIONAL_AUTHORITY_INVALID');
            }
        }
        if (array_diff($rule['required'], array_keys($rule['allowed']))) {
            throw new RuntimeException('FUNCTIONAL_AUTHORITY_INVALID');
        }
    }
    return $authority = $decoded;
}

/** Null preserves every existing functional order; future GI keys require intent at activation. */
function clinical_functional_validate(?string $studyKey, mixed $raw, bool $requireOrderParameters = true): ?array
{
    $rule = clinical_functional_authority()['rules'][$studyKey ?? ''] ?? null;
    if ($rule === null || ($rule['allowed'] ?? []) === []) {
        if ($raw !== null) throw new InvalidArgumentException('FUNCTIONAL_PARAMETERS_STUDY_INCOMPATIBLE');
        return null;
    }
    if ($raw === null) {
        if ($requireOrderParameters && $rule['status'] === 'FUTURE_REQUIRED') {
            throw new InvalidArgumentException('FUNCTIONAL_PARAMETERS_REQUIRED');
        }
        return null;
    }
    if (!is_array($raw) || array_is_list($raw)) throw new InvalidArgumentException('FUNCTIONAL_PARAMETERS_INVALID');
    if (array_diff(array_keys($raw), ['version', ...array_keys($rule['allowed'])])) {
        throw new InvalidArgumentException('FUNCTIONAL_PARAMETER_FIELD_INVALID');
    }
    if (($raw['version'] ?? null) !== 1) throw new InvalidArgumentException('FUNCTIONAL_PARAMETER_VERSION_INVALID');
    if (count($raw) === 1) throw new InvalidArgumentException('FUNCTIONAL_PARAMETERS_EMPTY');
    foreach ($rule['required'] as $field) {
        if (!array_key_exists($field, $raw)) throw new InvalidArgumentException('FUNCTIONAL_PARAMETER_REQUIRED');
    }
    $normalized = ['version' => 1];
    foreach (array_keys(clinical_functional_authority()['field_labels']) as $field) {
        if (!array_key_exists($field, $raw)) continue;
        $value = $raw[$field];
        if (!is_string($value) || !in_array($value, $rule['allowed'][$field] ?? [], true)) {
            throw new InvalidArgumentException('FUNCTIONAL_PARAMETER_VALUE_INVALID');
        }
        $normalized[$field] = $value;
    }
    // A region without a side, or a side without its region, would leave an EMG/SSEP request ambiguous.
    if (in_array($studyKey, ['emg_ncs','evoked_ssep'], true)
        && isset($normalized['body_site']) !== isset($normalized['side'])) {
        throw new InvalidArgumentException('FUNCTIONAL_SITE_SIDE_PAIR_REQUIRED');
    }
    return $normalized;
}

/** Persist this Spanish label with the issued item; never regenerate historical labels. */
function clinical_functional_summary(array $parameters): string
{
    $authority = clinical_functional_authority();
    if (($parameters['version'] ?? null) !== 1) throw new InvalidArgumentException('FUNCTIONAL_PARAMETER_VERSION_INVALID');
    $parts = [];
    foreach ($authority['field_labels'] as $field => $label) {
        if (!isset($parameters[$field])) continue;
        $name = $authority['value_labels'][$field][$parameters[$field]] ?? null;
        if (!is_string($name) || $name === '') throw new RuntimeException('FUNCTIONAL_SNAPSHOT_LABEL_INVALID');
        $parts[] = $label.': '.$name;
    }
    return implode(' · ', $parts);
}
