<?php
declare(strict_types=1);
require_once __DIR__ . '/clinical_dental_location_v2.php';

/** CAT02 order-location authority. The same explicit FDI map is served to the browser. */
function clinical_dental_teeth(): array
{
    static $teeth = null;
    if ($teeth !== null) return $teeth;
    $source = json_decode((string)file_get_contents(__DIR__ . '/../../assets/data/clinical/dental-fdi-iso3950-v1.json'), true);
    if (!is_array($source) || ($source['authority'] ?? null) !== 'FDI_ISO_3950' || ($source['contract_version'] ?? null) !== 1) {
        throw new RuntimeException('DENTAL_NUMBERING_AUTHORITY_UNAVAILABLE');
    }
    $teeth = [];
    foreach ($source['teeth'] as $tooth) $teeth[$tooth['code']] = $tooth;
    if (count($teeth) !== 52) throw new RuntimeException('DENTAL_NUMBERING_AUTHORITY_INCOMPLETE');
    return $teeth;
}

function clinical_dental_study_requirements(): array
{
    return [
        'dental_cbct' => 'CBCT',
        'dental_panoramic_xray' => 'NONE',
        'dental_cephalometric_xray' => 'NONE',
        'tmj_comparative_xray' => 'TMJ',
        'dental_intraoral_scan' => 'SCAN',
        'dental_clinical_photographs' => 'PHOTO',
        'dental_study_model' => 'MODEL',
    ];
}

/** Null means no dental location. No catalog or patient state is used to rebuild it later. */
function clinical_dental_validate_location(?string $studyKey, mixed $raw): ?array
{
    if (is_array($raw) && ($raw['contract_version'] ?? null) === 2) {
        return clinical_dental_v2_validate_study($studyKey, $raw);
    }
    $kind = clinical_dental_study_requirements()[$studyKey ?? ''] ?? null;
    if ($kind === null || $kind === 'NONE') {
        if ($raw !== null) throw new InvalidArgumentException('DENTAL_LOCATION_STUDY_INCOMPATIBLE');
        return null;
    }
    if ($raw === null) {
        if ($kind === 'MODEL') return null;
        throw new InvalidArgumentException('DENTAL_LOCATION_REQUIRED');
    }
    if (!is_array($raw) || array_is_list($raw)) throw new InvalidArgumentException('DENTAL_LOCATION_INVALID');
    $allowed = ['contract_version','numbering_system','dentition_mode','coverage','arch','selected_teeth',
        'anatomical_region','projection','photograph_scope','fov_cm'];
    if (array_diff(array_keys($raw), $allowed)) throw new InvalidArgumentException('DENTAL_LOCATION_FIELD_INVALID');
    if (($raw['contract_version'] ?? null) !== 1 || ($raw['numbering_system'] ?? null) !== 'FDI_ISO_3950') {
        throw new InvalidArgumentException('DENTAL_LOCATION_AUTHORITY_INVALID');
    }
    $mode = $raw['dentition_mode'] ?? null;
    if ($mode !== null && !in_array($mode, ['PERMANENT','DECIDUOUS','MIXED'], true)) throw new InvalidArgumentException('DENTAL_DENTITION_INVALID');
    $codes = $raw['selected_teeth'] ?? [];
    if (!is_array($codes) || !array_is_list($codes) || count($codes) > 52) throw new InvalidArgumentException('DENTAL_TEETH_INVALID');
    $authority = clinical_dental_teeth();
    $seen = [];
    if ($codes !== [] && $mode === null) throw new InvalidArgumentException('DENTAL_DENTITION_REQUIRED');
    foreach ($codes as $code) {
        if (!is_string($code) || !isset($authority[$code])) throw new InvalidArgumentException('DENTAL_TOOTH_CODE_INVALID');
        if (isset($seen[$code])) throw new InvalidArgumentException('DENTAL_TOOTH_DUPLICATE');
        if ($mode !== 'MIXED' && $authority[$code]['dentition'] !== $mode) throw new InvalidArgumentException('DENTAL_DENTITION_TOOTH_CONFLICT');
        $seen[$code] = true;
    }
    if ($codes !== [] && $kind !== 'CBCT') throw new InvalidArgumentException('DENTAL_LOCATION_STUDY_INCOMPATIBLE');
    $arch = $raw['arch'] ?? null;
    if ($arch !== null && !in_array($arch, ['MAXILLARY','MANDIBULAR','BOTH'], true)) throw new InvalidArgumentException('DENTAL_ARCH_INVALID');
    $coverage = $raw['coverage'] ?? null;
    $region = $raw['anatomical_region'] ?? null;
    if ($region !== null && (!is_string($region) || trim($region) === '' || mb_strlen($region) > 120)) {
        throw new InvalidArgumentException('DENTAL_REGION_INVALID');
    }
    $projection = $raw['projection'] ?? null;
    $photograph = $raw['photograph_scope'] ?? null;
    $fov = $raw['fov_cm'] ?? null;
    if ($kind === 'CBCT') {
        if (!in_array($coverage, ['LOCALIZED','MAXILLARY_ARCH','MANDIBULAR_ARCH','BOTH_ARCHES','MAXILLOFACIAL'], true)) {
            throw new InvalidArgumentException('DENTAL_COVERAGE_INVALID');
        }
        if ($coverage === 'LOCALIZED') {
            if ($codes === [] && $region === null) throw new InvalidArgumentException('DENTAL_LOCALIZED_TARGET_REQUIRED');
            $arches = array_unique(array_map(static fn(string $code): string => $authority[$code]['arch'], $codes));
            $inferred = count($arches) === 2 ? 'BOTH' : (count($arches) === 1 ? ($arches[0] === 'SUPERIOR' ? 'MAXILLARY' : 'MANDIBULAR') : null);
            if ($arch !== null && $inferred !== null && $arch !== $inferred) throw new InvalidArgumentException('DENTAL_ARCH_TOOTH_CONFLICT');
            $arch ??= $inferred;
        } else {
            // A broad request cannot silently acquire tooth-level focus in V1.
            if ($codes !== [] || $region !== null) throw new InvalidArgumentException('DENTAL_BROAD_TOOTH_CONFLICT');
            $expected = ['MAXILLARY_ARCH'=>'MAXILLARY','MANDIBULAR_ARCH'=>'MANDIBULAR','BOTH_ARCHES'=>'BOTH','MAXILLOFACIAL'=>null][$coverage];
            if ($arch !== null && $arch !== $expected) throw new InvalidArgumentException('DENTAL_ARCH_COVERAGE_CONFLICT');
            $arch = $expected;
        }
        if ($projection !== null || $photograph !== null) throw new InvalidArgumentException('DENTAL_LOCATION_STUDY_INCOMPATIBLE');
        if ($fov !== null && (!is_string($fov) || preg_match('/^(?:[1-9]|[12][0-9]|30)(?:\.[0-9])?x(?:[1-9]|[12][0-9]|30)(?:\.[0-9])?$/D', $fov) !== 1)) {
            throw new InvalidArgumentException('DENTAL_FOV_INVALID');
        }
    } else {
        if ($coverage !== null || $region !== null || $fov !== null || $mode !== null) throw new InvalidArgumentException('DENTAL_LOCATION_STUDY_INCOMPATIBLE');
        if ($kind === 'TMJ') {
            if (!in_array($projection, ['LATERAL','PA'], true) || $arch !== null || $photograph !== null) throw new InvalidArgumentException('DENTAL_PROJECTION_INVALID');
        } elseif ($kind === 'SCAN') {
            if ($arch === null || $projection !== null || $photograph !== null) throw new InvalidArgumentException('DENTAL_ARCH_REQUIRED');
        } elseif ($kind === 'PHOTO') {
            if (!in_array($photograph, ['INTRAORAL','EXTRAORAL','BOTH'], true) || $arch !== null || $projection !== null) throw new InvalidArgumentException('DENTAL_PHOTOGRAPH_SCOPE_INVALID');
        } elseif ($kind === 'MODEL' && ($projection !== null || $photograph !== null)) {
            throw new InvalidArgumentException('DENTAL_LOCATION_STUDY_INCOMPATIBLE');
        }
    }
    // Canonical field order gives stable immutable snapshots independent of input object order.
    return array_filter([
        'contract_version'=>1,'numbering_system'=>'FDI_ISO_3950','dentition_mode'=>$mode,
        'coverage'=>$coverage,'arch'=>$arch,'selected_teeth'=>$codes,
        'anatomical_region'=>$region === null ? null : trim($region),'projection'=>$projection,
        'photograph_scope'=>$photograph,'fov_cm'=>$fov,
    ], static fn(mixed $value): bool => $value !== null);
}

function clinical_dental_location_summary(?array $location): ?string
{
    if ($location === null) return null;
    if (($location['contract_version'] ?? null) === 2) return clinical_dental_v2_summary($location);
    $parts = [];
    $coverage = ['LOCALIZED'=>'Zona localizada','MAXILLARY_ARCH'=>'Maxilar superior',
        'MANDIBULAR_ARCH'=>'Mandíbula','BOTH_ARCHES'=>'Ambos maxilares','MAXILLOFACIAL'=>'Maxilofacial'];
    $arch = ['MAXILLARY'=>'Maxilar superior','MANDIBULAR'=>'Mandíbula','BOTH'=>'Ambos maxilares'];
    $projection = ['LATERAL'=>'Vista lateral','PA'=>'Vista posteroanterior'];
    $photo = ['INTRAORAL'=>'Fotografías intraorales','EXTRAORAL'=>'Fotografías extraorales','BOTH'=>'Fotografías intraorales y extraorales'];
    if (isset($location['coverage'])) $parts[] = $coverage[$location['coverage']] ?? '';
    elseif (isset($location['arch'])) $parts[] = $arch[$location['arch']] ?? '';
    if (isset($location['projection'])) $parts[] = $projection[$location['projection']] ?? '';
    if (isset($location['photograph_scope'])) $parts[] = $photo[$location['photograph_scope']] ?? '';
    if (!empty($location['selected_teeth'])) {
        $teeth = clinical_dental_teeth();
        $parts[] = 'Piezas ' . implode(', ', $location['selected_teeth']);
        $parts[] = implode('; ', array_map(static fn(string $code): string => $code . ' · ' . $teeth[$code]['name_es'], $location['selected_teeth']));
    }
    if (!empty($location['anatomical_region'])) $parts[] = 'Región: ' . $location['anatomical_region'];
    if (!empty($location['fov_cm'])) $parts[] = 'Campo solicitado: ' . $location['fov_cm'] . ' cm';
    return implode(' · ', array_filter($parts));
}
