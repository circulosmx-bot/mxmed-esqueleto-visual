<?php
declare(strict_types=1);

/** IMG-CAT02A: order-time physician intent. Acquisition settings remain provider-owned. */
function clinical_imaging_authority(): array
{
    static $authority = null;
    if ($authority !== null) return $authority;
    $authority = json_decode((string)file_get_contents(__DIR__.'/../../modules/clinical/catalog/imaging_order_parameters_v1.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($authority) || ($authority['contract'] ?? null) !== 'imaging_order_parameters'
        || ($authority['version'] ?? null) !== 1
        || ($authority['authority_versions']['xray_views'] ?? null) !== 1
        || ($authority['authority_versions']['vascular_territory'] ?? null) !== 1
        || ($authority['authority_versions']['radiotracer'] ?? null) !== 1
        || !is_array($authority['rules'] ?? null) || !is_array($authority['value_labels'] ?? null)) {
        throw new RuntimeException('IMAGING_AUTHORITY_INVALID');
    }
    return $authority;
}

/** Null preserves existing order behavior except for active keys with required intent. */
function clinical_imaging_validate(?string $studyKey, mixed $raw, bool $requireOrderParameters = true): ?array
{
    $authority = clinical_imaging_authority();
    $rule = $authority['rules'][$studyKey ?? ''] ?? null;
    if ($rule === null) {
        if ($raw !== null) throw new InvalidArgumentException('IMAGING_PARAMETERS_STUDY_INCOMPATIBLE');
        return null;
    }
    if ($raw === null) {
        if ($requireOrderParameters && ($rule['status'] ?? null) === 'ACTIVE_REQUIRED') {
            throw new InvalidArgumentException('IMAGING_PARAMETERS_REQUIRED');
        }
        return null;
    }
    if (!is_array($raw) || array_is_list($raw)) throw new InvalidArgumentException('IMAGING_PARAMETERS_INVALID');
    $allowedFields = array_keys($rule['allowed'] ?? []);
    if (array_diff(array_keys($raw), ['version', ...$allowedFields])) throw new InvalidArgumentException('IMAGING_PARAMETER_FIELD_INVALID');
    if (($raw['version'] ?? null) !== 1) throw new InvalidArgumentException('IMAGING_PARAMETER_VERSION_INVALID');
    if (count($raw) === 1) throw new InvalidArgumentException('IMAGING_PARAMETERS_EMPTY');
    foreach ($rule['required'] ?? [] as $field) {
        if (!array_key_exists($field, $raw)) throw new InvalidArgumentException('IMAGING_PARAMETER_REQUIRED');
    }
    $normalized = ['version' => 1];
    foreach (array_keys($authority['field_labels']) as $field) {
        if (!array_key_exists($field, $raw)) continue;
        $value = $raw[$field];
        $choices = $rule['allowed'][$field] ?? null;
        if (!is_array($choices) || $choices === []) throw new InvalidArgumentException('IMAGING_PARAMETER_FIELD_INVALID');
        $labelGroup = match ($field) {
            'contrast_routes' => 'contrast_route', 'xray_views' => 'xray_view',
            'dxa_sites' => 'dxa_site', default => $field,
        };
        $labels = $authority['value_labels'][$labelGroup] ?? [];
        if (in_array($field, $authority['list_fields'], true)) {
            if (!is_array($value) || !array_is_list($value) || $value === [] || count($value) > 12) {
                throw new InvalidArgumentException('IMAGING_PARAMETER_VALUE_INVALID');
            }
            $seen = [];
            foreach ($value as $entry) {
                if (!is_string($entry) || !in_array($entry, $choices, true) || !isset($labels[$entry]) || isset($seen[$entry])) {
                    throw new InvalidArgumentException('IMAGING_PARAMETER_VALUE_INVALID');
                }
                $seen[$entry] = true;
            }
        } elseif (!is_string($value) || !in_array($value, $choices, true) || !isset($labels[$value])) {
            throw new InvalidArgumentException('IMAGING_PARAMETER_VALUE_INVALID');
        }
        if (isset($rule['fixed'][$field]) && $value !== $rule['fixed'][$field]) {
            throw new InvalidArgumentException('IMAGING_PARAMETER_FIXED_MISMATCH');
        }
        $normalized[$field] = $value;
    }
    if (isset($normalized['xray_views'], $normalized['xray_view_preset'])) {
        throw new InvalidArgumentException('IMAGING_VIEWS_PRESET_CONFLICT');
    }
    if (isset($normalized['xray_view_preset']) && !isset($authority['view_presets'][$normalized['xray_view_preset']])) {
        throw new InvalidArgumentException('IMAGING_VIEW_PRESET_INVALID');
    }
    $intent = $normalized['contrast_intent'] ?? null;
    $routes = $normalized['contrast_routes'] ?? null;
    if ($routes !== null && $intent === null) throw new InvalidArgumentException('IMAGING_CONTRAST_INTENT_REQUIRED');
    $expectedRoutes = match ($intent) {
        'WITHOUT_CONTRAST', null => [],
        'WITH_IV_CONTRAST', 'WITH_AND_WITHOUT_IV_CONTRAST' => ['IV'],
        'WITH_ORAL_CONTRAST' => ['ORAL'],
        'WITH_IV_AND_ORAL_CONTRAST' => ['IV', 'ORAL'],
        default => throw new InvalidArgumentException('IMAGING_CONTRAST_INTENT_INVALID'),
    };
    if ($routes !== null && ($expectedRoutes === [] || array_diff($routes, $expectedRoutes) || array_diff($expectedRoutes, $routes))) {
        throw new InvalidArgumentException('IMAGING_CONTRAST_ROUTE_INVALID');
    }
    if (isset($normalized['dxa_sites'])) {
        $sites = $normalized['dxa_sites'];
        if (in_array('WHOLE_BODY', $sites, true) && count($sites) > 1
            || in_array('BILATERAL_HIPS', $sites, true) && (in_array('HIP_LEFT', $sites, true) || in_array('HIP_RIGHT', $sites, true))) {
            throw new InvalidArgumentException('IMAGING_DXA_SITE_CONFLICT');
        }
    }
    return $normalized;
}

/** Render only from issued snapshot fields, never from a mutable catalog name. */
function clinical_imaging_summary(array $parameters): string
{
    $authority = clinical_imaging_authority();
    if (($parameters['version'] ?? null) !== 1) throw new InvalidArgumentException('IMAGING_PARAMETER_VERSION_INVALID');
    $parts = [];
    foreach ($authority['field_labels'] as $field => $label) {
        if (!array_key_exists($field, $parameters)) continue;
        $group = match ($field) {
            'contrast_routes' => 'contrast_route', 'xray_views' => 'xray_view',
            'dxa_sites' => 'dxa_site', default => $field,
        };
        $values = is_array($parameters[$field]) ? $parameters[$field] : [$parameters[$field]];
        $names = [];
        foreach ($values as $value) {
            $name = $authority['value_labels'][$group][$value] ?? null;
            if (!is_string($name) || $name === '') throw new RuntimeException('IMAGING_SNAPSHOT_LABEL_INVALID');
            $names[] = $name;
        }
        $parts[] = $label . ': ' . implode(', ', $names);
    }
    return implode(' · ', $parts);
}
