<?php
declare(strict_types=1);

/** Versioned, declarative physician request parameters. Collection events belong elsewhere. */
function clinical_specimen_authority(?array $processOverride = null): array
{
    static $config;
    if ($processOverride !== null) {
        // In-process dependency injection for disposable QA; no request input can set this.
        $config = $processOverride;
    }
    return $config ??= json_decode((string)file_get_contents(__DIR__.'/../../modules/clinical/catalog/study_specimen_requirements_v1.json'), true, 512, JSON_THROW_ON_ERROR);
}

function clinical_specimen_validate(?string $studyKey, mixed $raw, ?array $ruleOverride = null): ?array
{
    $authority = clinical_specimen_authority();
    $rule = $ruleOverride ?? ($authority['studies'][$studyKey ?? ''] ?? null);
    if ($rule === null || ($rule['specimen_mode'] ?? 'NONE') === 'NONE') {
        if ($raw !== null) throw new InvalidArgumentException('SPECIMEN_STUDY_INCOMPATIBLE');
        return null;
    }
    if (($rule['specimen_mode'] ?? null) === 'OPTIONAL_SELECTION' && $raw === null) return null;
    if ($raw !== null && (!is_array($raw) || array_is_list($raw))) throw new InvalidArgumentException('SPECIMEN_REQUIREMENTS_INVALID');
    $raw ??= [];
    $allowed = ['version','specimen_type_key','source_site_key','source_site_text','collection_mode','requested_duration_minutes','paired_specimen'];
    if (array_diff(array_keys($raw), $allowed)) throw new InvalidArgumentException('SPECIMEN_REQUIREMENTS_FIELD_INVALID');
    if (isset($raw['version']) && $raw['version'] !== 1) throw new InvalidArgumentException('SPECIMEN_REQUIREMENTS_VERSION_INVALID');
    $mode = $rule['specimen_mode'];
    $specimen = $rule['fixed_specimen_type_key'] ?? ($raw['specimen_type_key'] ?? null);
    if (isset($rule['fixed_specimen_type_key']) && isset($raw['specimen_type_key']) && $raw['specimen_type_key'] !== $specimen) throw new InvalidArgumentException('SPECIMEN_FIXED_MISMATCH');
    if (!is_string($specimen) || !isset($authority['specimen_types'][$specimen])) throw new InvalidArgumentException('SPECIMEN_TYPE_REQUIRED');
    if ($mode === 'REQUIRED_SELECTION' && !in_array($specimen, $rule['allowed_specimen_type_keys'] ?? [], true)) throw new InvalidArgumentException('SPECIMEN_TYPE_INVALID');
    if ($mode === 'OPTIONAL_SELECTION' && isset($raw['specimen_type_key']) && !in_array($specimen, $rule['allowed_specimen_type_keys'] ?? [], true)) throw new InvalidArgumentException('SPECIMEN_TYPE_INVALID');
    $collection = $rule['fixed_collection_mode'] ?? ($raw['collection_mode'] ?? null);
    if (isset($rule['fixed_collection_mode']) && isset($raw['collection_mode']) && $raw['collection_mode'] !== $collection) throw new InvalidArgumentException('SPECIMEN_COLLECTION_FIXED_MISMATCH');
    if ($collection !== null) {
        if (!isset($authority['collection_modes'][$collection])) throw new InvalidArgumentException('SPECIMEN_COLLECTION_MODE_INVALID');
        if (!isset($rule['fixed_collection_mode']) && !in_array($collection, $rule['allowed_collection_modes'] ?? [], true)) throw new InvalidArgumentException('SPECIMEN_COLLECTION_MODE_INVALID');
    } elseif (($rule['collection_mode'] ?? null) === 'REQUIRED_SELECTION') throw new InvalidArgumentException('SPECIMEN_COLLECTION_MODE_REQUIRED');
    $duration = $raw['requested_duration_minutes'] ?? ($rule['fixed_requested_duration_minutes'] ?? null);
    if ($collection === 'TIMED') {
        if (!is_int($duration) || !in_array($duration, $rule['allowed_duration_minutes'] ?? [], true)) throw new InvalidArgumentException('SPECIMEN_DURATION_INVALID');
    } elseif ($duration !== null) throw new InvalidArgumentException('SPECIMEN_DURATION_INCOMPATIBLE');
    $site = $raw['source_site_key'] ?? null;
    $siteMode = $rule['source_site'] ?? 'NONE';
    if ($siteMode === 'REQUIRED_SELECTION' && $site === null) throw new InvalidArgumentException('SPECIMEN_SOURCE_SITE_REQUIRED');
    if ($site !== null && ($siteMode === 'NONE' || !in_array($site, $rule['allowed_source_site_keys'] ?? [], true) || !isset($authority['source_sites'][$site]))) throw new InvalidArgumentException('SPECIMEN_SOURCE_SITE_INVALID');
    $siteText = $raw['source_site_text'] ?? null;
    if ($siteText !== null && ($site === null || !is_string($siteText) || trim($siteText) === '' || mb_strlen($siteText) > 120)) throw new InvalidArgumentException('SPECIMEN_SOURCE_SITE_TEXT_INVALID');
    if ($site !== null && in_array($site, $rule['source_site_text_required_keys'] ?? [], true) && $siteText === null) throw new InvalidArgumentException('SPECIMEN_SOURCE_SITE_TEXT_REQUIRED');
    $pairedRule = $rule['paired_specimen'] ?? null;
    $paired = $raw['paired_specimen'] ?? null;
    if ($pairedRule === null && $paired !== null) throw new InvalidArgumentException('SPECIMEN_PAIRED_INCOMPATIBLE');
    if ($pairedRule !== null) {
        if (!is_array($pairedRule) || ($pairedRule['required'] ?? null) !== true || !isset($authority['specimen_types'][$pairedRule['counterpart_specimen_type_key'] ?? '']) || !isset($authority['paired_relationships'][$pairedRule['relationship_key'] ?? ''])) throw new RuntimeException('SPECIMEN_PAIRED_RULE_INVALID');
        if ($paired !== null && $paired !== $pairedRule) throw new InvalidArgumentException('SPECIMEN_PAIRED_MISMATCH');
        $paired = $pairedRule;
    }
    return array_filter(['version'=>1,'specimen_type_key'=>$specimen,'source_site_key'=>$site,'source_site_text'=>$siteText===null?null:trim($siteText),'collection_mode'=>$collection,'requested_duration_minutes'=>$duration,'paired_specimen'=>$paired], static fn($v)=>$v!==null);
}

function clinical_specimen_print_context(?array $request, string $studyName): ?string
{
    if ($request === null) return null;
    $authority = clinical_specimen_authority();
    $parts = [];
    $specimen = $request['specimen_type_key'] ?? null;
    $fixedInName = $specimen !== null && match ($specimen) {
        'URINE' => preg_match('/orina|urinari/iu', $studyName) === 1,
        'CSF' => preg_match('/LCR|cefalorraquídeo/iu', $studyName) === 1,
        'SEMEN' => preg_match('/semen|espermato/iu', $studyName) === 1,
        'SYNOVIAL_FLUID' => preg_match('/sinovial/iu', $studyName) === 1,
        default => false,
    };
    if (!$fixedInName && isset($authority['specimen_types'][$specimen])) $parts[] = 'Muestra: '.$authority['specimen_types'][$specimen];
    if (($request['collection_mode'] ?? null) === 'TIMED') $parts[] = 'Recolección: '.((int)$request['requested_duration_minutes'] / 60).' horas';
    elseif (isset($request['collection_mode']) && !str_contains(mb_strtolower($studyName), 'muestra aislada')) $parts[] = 'Recolección: '.$authority['collection_modes'][$request['collection_mode']];
    if (isset($request['source_site_key'])) $parts[] = 'Sitio: '.($request['source_site_text'] ?? $authority['source_sites'][$request['source_site_key']]);
    if (isset($request['paired_specimen'])) $parts[] = 'Muestra complementaria: '.$authority['specimen_types'][$request['paired_specimen']['counterpart_specimen_type_key']];
    return $parts ? implode(' · ', $parts) : null;
}
