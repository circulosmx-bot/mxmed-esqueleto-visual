<?php
declare(strict_types=1);

/** PATH-CAT02A: physician request authority. This is never a laboratory accession record. */
function clinical_pathology_authority(): array
{
    static $authority = null;
    if ($authority !== null) return $authority;
    $authority = json_decode((string)file_get_contents(__DIR__.'/../../modules/clinical/catalog/pathology_order_parameters_v1.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($authority) || ($authority['version'] ?? null) !== 1
        || ($authority['contract'] ?? null) !== 'pathology_order_parameters'
        || ($authority['ihc_marker_authority']['version'] ?? null) !== 1
        || ($authority['special_stain_authority']['version'] ?? null) !== 1
        || ($authority['breast_ihc_profile']['version'] ?? null) !== 1) {
        throw new RuntimeException('PATHOLOGY_AUTHORITY_INVALID');
    }
    return $authority;
}

function clinical_pathology_text(mixed $value, int $max, string $error): string
{
    if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $max
        || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
        throw new InvalidArgumentException($error);
    }
    return trim($value);
}

/** A null result leaves all existing studies, including current cytology, unchanged. */
function clinical_pathology_validate(?string $studyKey, mixed $raw): ?array
{
    $authority = clinical_pathology_authority();
    $rule = $authority['rules'][$studyKey ?? ''] ?? null;
    if ($rule === null) {
        if ($raw !== null) throw new InvalidArgumentException('PATHOLOGY_PARAMETERS_STUDY_INCOMPATIBLE');
        return null;
    }
    if (!is_array($raw) || array_is_list($raw)) throw new InvalidArgumentException('PATHOLOGY_PARAMETERS_REQUIRED');
    $allowed = ['version','specimens','marker_key','stain_key','profile_version','source_institution_name','prior_report_status'];
    if (array_diff(array_keys($raw), $allowed)) throw new InvalidArgumentException('PATHOLOGY_PARAMETER_FIELD_INVALID');
    if (($raw['version'] ?? null) !== 1) throw new InvalidArgumentException('PATHOLOGY_PARAMETER_VERSION_INVALID');

    $specimens = $raw['specimens'] ?? null;
    if (!is_array($specimens) || !array_is_list($specimens) || $specimens === []
        || count($specimens) > (int)$rule['max_specimens']) {
        throw new InvalidArgumentException('PATHOLOGY_SPECIMENS_INVALID');
    }
    $canonicalSpecimens = [];
    $seen = [];
    foreach ($specimens as $specimen) {
        if (!is_array($specimen) || array_is_list($specimen)) throw new InvalidArgumentException('PATHOLOGY_SPECIMEN_INVALID');
        $specimenAllowed = ['material_key','anatomic_site_key','anatomic_site_text','laterality','laterality_unspecified_reason','specimen_context','description'];
        if (array_diff(array_keys($specimen), $specimenAllowed)) throw new InvalidArgumentException('PATHOLOGY_SPECIMEN_FIELD_INVALID');
        $material = $specimen['material_key'] ?? null;
        if (!is_string($material) || !isset($authority['material_classes'][$material])
            || !in_array($material, $rule['materials'], true)) throw new InvalidArgumentException('PATHOLOGY_MATERIAL_INVALID');
        $context = $specimen['specimen_context'] ?? $rule['context'];
        if ($context !== $rule['context'] || !isset($authority['specimen_contexts'][$context])) {
            throw new InvalidArgumentException('PATHOLOGY_CONTEXT_INVALID');
        }
        $siteKey = $specimen['anatomic_site_key'] ?? ($rule['fixed_site'] ?? null);
        if ($siteKey !== null && (!is_string($siteKey) || !isset($authority['anatomic_sites'][$siteKey]))) {
            throw new InvalidArgumentException('PATHOLOGY_SITE_KEY_INVALID');
        }
        if (isset($rule['fixed_site']) && $siteKey !== $rule['fixed_site']) {
            throw new InvalidArgumentException('PATHOLOGY_SITE_FIXED_MISMATCH');
        }
        $siteText = array_key_exists('anatomic_site_text', $specimen)
            ? clinical_pathology_text($specimen['anatomic_site_text'], 120, 'PATHOLOGY_SITE_TEXT_INVALID') : null;
        if (($rule['site_required'] ?? false) && $siteText === null) throw new InvalidArgumentException('PATHOLOGY_SITE_REQUIRED');
        if ($siteKey === 'OTHER_ANATOMICAL_SITE' && $siteText === null) throw new InvalidArgumentException('PATHOLOGY_SITE_REQUIRED');
        $laterality = $specimen['laterality'] ?? null;
        if ($laterality !== null && (!is_string($laterality) || !isset($authority['laterality'][$laterality]))) {
            throw new InvalidArgumentException('PATHOLOGY_LATERALITY_INVALID');
        }
        if (in_array($siteKey, $authority['lateralized_site_keys'], true)
            && in_array($laterality, ['MIDLINE','NOT_APPLICABLE'], true)) {
            throw new InvalidArgumentException('PATHOLOGY_LATERALITY_SITE_CONFLICT');
        }
        $unspecifiedReason = $specimen['laterality_unspecified_reason'] ?? null;
        if ($laterality === 'UNSPECIFIED_IF_ALLOWED') {
            $unspecifiedReason = clinical_pathology_text($unspecifiedReason, 120, 'PATHOLOGY_LATERALITY_REASON_REQUIRED');
        } elseif ($unspecifiedReason !== null) {
            throw new InvalidArgumentException('PATHOLOGY_LATERALITY_REASON_INCOMPATIBLE');
        }
        $description = array_key_exists('description', $specimen)
            ? clinical_pathology_text($specimen['description'], 190, 'PATHOLOGY_DESCRIPTION_INVALID') : null;
        if (($rule['description_required'] ?? false) && $description === null) {
            throw new InvalidArgumentException('PATHOLOGY_DESCRIPTION_REQUIRED');
        }
        $item = array_filter([
            'material_key'=>$material, 'anatomic_site_key'=>$siteKey, 'anatomic_site_text'=>$siteText,
            'laterality'=>$laterality, 'laterality_unspecified_reason'=>$unspecifiedReason,
            'specimen_context'=>$context, 'description'=>$description,
        ], static fn(mixed $value): bool => $value !== null);
        $signature = json_encode($item, JSON_THROW_ON_ERROR);
        if (isset($seen[$signature])) throw new InvalidArgumentException('PATHOLOGY_SPECIMEN_DUPLICATE');
        $seen[$signature] = true;
        $canonicalSpecimens[] = $item;
    }

    $marker = $raw['marker_key'] ?? null;
    $stain = $raw['stain_key'] ?? null;
    $profile = $raw['profile_version'] ?? null;
    $institution = $raw['source_institution_name'] ?? null;
    $prior = $raw['prior_report_status'] ?? null;
    $parameter = $rule['parameter'] ?? null;
    if ($parameter === 'marker_key') {
        if (!is_string($marker) || !isset($authority['ihc_marker_authority']['markers'][$marker])) {
            throw new InvalidArgumentException('PATHOLOGY_MARKER_INVALID');
        }
    } elseif ($marker !== null) throw new InvalidArgumentException('PATHOLOGY_MARKER_INCOMPATIBLE');
    if ($parameter === 'stain_key') {
        if (!is_string($stain) || !isset($authority['special_stain_authority']['stains'][$stain])) {
            throw new InvalidArgumentException('PATHOLOGY_STAIN_INVALID');
        }
    } elseif ($stain !== null) throw new InvalidArgumentException('PATHOLOGY_STAIN_INCOMPATIBLE');
    if ($parameter === 'profile_version') {
        if ($profile !== $authority['breast_ihc_profile']['version']) {
            throw new InvalidArgumentException('PATHOLOGY_PROFILE_VERSION_INVALID');
        }
    } elseif ($profile !== null) throw new InvalidArgumentException('PATHOLOGY_PROFILE_INCOMPATIBLE');
    if ($parameter === 'outside_review') {
        $institution = clinical_pathology_text($institution, 190, 'PATHOLOGY_SOURCE_INSTITUTION_REQUIRED');
        if (!is_string($prior) || !isset($authority['prior_report_statuses'][$prior])) {
            throw new InvalidArgumentException('PATHOLOGY_PRIOR_REPORT_STATUS_INVALID');
        }
    } elseif ($institution !== null || $prior !== null) throw new InvalidArgumentException('PATHOLOGY_OUTSIDE_REVIEW_INCOMPATIBLE');

    return array_filter([
        'version'=>1, 'specimens'=>$canonicalSpecimens, 'marker_key'=>$marker, 'stain_key'=>$stain,
        'profile_version'=>$profile, 'source_institution_name'=>$institution, 'prior_report_status'=>$prior,
    ], static fn(mixed $value): bool => $value !== null);
}

/** Human-readable labels are persisted beside selected values, never regenerated from a later authority. */
function clinical_pathology_summary(?array $parameters, ?string $studyKey = null): ?string
{
    if ($parameters === null) return null;
    $authority = clinical_pathology_authority();
    $parts = [];
    foreach ($parameters['specimens'] as $index => $specimen) {
        $siteName = $authority['anatomic_sites'][$specimen['anatomic_site_key'] ?? ''] ?? null;
        $siteText = $specimen['anatomic_site_text'] ?? null;
        $site = $siteName === null ? ($siteText ?? 'Sitio no indicado')
            : ($siteText === null ? $siteName : $siteName.' — '.$siteText);
        $detail = [$authority['material_classes'][$specimen['material_key']], $site,
            $authority['specimen_contexts'][$specimen['specimen_context']]];
        if (isset($specimen['laterality'])) $detail[] = $authority['laterality'][$specimen['laterality']];
        if (isset($specimen['laterality_unspecified_reason'])) $detail[] = $specimen['laterality_unspecified_reason'];
        if (isset($specimen['description'])) $detail[] = $specimen['description'];
        $parts[] = 'Muestra '.($index + 1).': '.implode(' · ', $detail);
    }
    if (isset($parameters['marker_key'])) $parts[] = 'Marcador: '.$authority['ihc_marker_authority']['markers'][$parameters['marker_key']];
    if (isset($parameters['stain_key'])) $parts[] = 'Tinción: '.$authority['special_stain_authority']['stains'][$parameters['stain_key']];
    if (isset($parameters['profile_version'])) {
        $parts[] = 'Perfil mamario v'.$parameters['profile_version'].': '.implode(', ', $authority['breast_ihc_profile']['components']);
    }
    if (isset($parameters['source_institution_name'])) $parts[] = 'Institución de origen: '.$parameters['source_institution_name'];
    if (isset($parameters['prior_report_status'])) $parts[] = 'Informe previo: '.$authority['prior_report_statuses'][$parameters['prior_report_status']];
    if (isset($authority['rules'][$studyKey ?? '']['handling_notice'])) {
        $parts[] = $authority['rules'][$studyKey]['handling_notice'];
    }
    return implode(' · ', $parts);
}
