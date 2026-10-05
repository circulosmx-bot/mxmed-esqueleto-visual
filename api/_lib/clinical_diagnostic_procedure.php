<?php
declare(strict_types=1);

/** Exact PROC-CAT02B scope authority; unknown procedure identities fail closed. */
function clinical_diagnostic_procedure_policy(string $key): ?array
{
    static $studies = null;
    if ($studies === null) {
        $data = json_decode((string)file_get_contents(__DIR__.'/../../modules/clinical/catalog/diagnostic_procedure_scope_v1.json'), true, 512, JSON_THROW_ON_ERROR);
        if (($data['version'] ?? null) !== 1 || !is_array($data['studies'] ?? null)
            || array_keys($data['studies']) !== ['colposcopy_diagnostic','hysteroscopy_diagnostic','cystoscopy_diagnostic']) {
            throw new RuntimeException('DIAGNOSTIC_PROCEDURE_SCOPE_INVALID');
        }
        foreach ($data['studies'] as $policy) {
            if (($policy['scope'] ?? null) !== 'DIAGNOSTIC_ONLY'
                || ($policy['allowed_service_modes'] ?? null) !== ['ON_SITE']
                || ($policy['optional_biopsy_intent'] ?? null) !== 'NONE_V1') {
                throw new RuntimeException('DIAGNOSTIC_PROCEDURE_SCOPE_INVALID');
            }
        }
        $studies = $data['studies'];
    }
    return $studies[$key] ?? null;
}

function clinical_diagnostic_procedure_assert_order(?string $key, string $category, array $input, bool $resultTaxonomy = false): void
{
    if ($category !== 'PROCEDIMIENTOS_DIAGNOSTICOS') return;
    if ($key === null || clinical_diagnostic_procedure_policy($key) === null) {
        throw new InvalidArgumentException('DIAGNOSTIC_PROCEDURE_SCOPE_INVALID');
    }
    // No client-owned scope, biopsy, therapeutic or preparation fields are part of V1.
    if (!$resultTaxonomy && array_diff(array_keys($input), ['study_type_id','study_type_key','study_category','study_display_name','sequence','note','external_code_system','external_code','external_code_version'])) {
        throw new InvalidArgumentException('DIAGNOSTIC_PROCEDURE_PARAMETERS_UNSUPPORTED');
    }
}

function clinical_diagnostic_procedure_service_mode(?string $key, string $mode, ?string $category = null): bool
{
    if ($key === null) return true;
    $policy = clinical_diagnostic_procedure_policy($key);
    if ($category === 'PROCEDIMIENTOS_DIAGNOSTICOS' && $policy === null) return false;
    return $policy === null || in_array($mode, $policy['allowed_service_modes'], true);
}
