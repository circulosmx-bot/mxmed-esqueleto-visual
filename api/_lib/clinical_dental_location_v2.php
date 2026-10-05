<?php
declare(strict_types=1);

/** Additive location authority. V1 snapshots remain owned by clinical_dental_location.php. */
function clinical_dental_v2_authority(): array
{
    static $authority = null;
    if ($authority !== null) return $authority;
    $path = __DIR__ . '/../../assets/data/clinical/dental-location-authority-v2.json';
    $source = json_decode((string)file_get_contents($path), true);
    if (!is_array($source) || ($source['authority'] ?? null) !== 'DENTAL_LOCATION_V2'
        || ($source['contract_version'] ?? null) !== 2 || ($source['numbering_system'] ?? null) !== 'FDI_ISO_3950') {
        throw new RuntimeException('DENTAL_V2_AUTHORITY_UNAVAILABLE');
    }
    return $authority = $source;
}

function clinical_dental_v2_study_policy(?string $studyKey): ?array
{
    return clinical_dental_v2_authority()['study_policies'][$studyKey ?? ''] ?? null;
}

/** Also accepts a caller-supplied policy for isolated contract tests and future procedure adapters. */
function clinical_dental_v2_validate(array $raw, array $policy): array
{
    $authority = clinical_dental_v2_authority();
    if (array_is_list($raw)) throw new InvalidArgumentException('DENTAL_V2_LOCATION_INVALID');
    if (($raw['contract_version'] ?? null) !== 2) throw new InvalidArgumentException('DENTAL_V2_VERSION_INVALID');
    $mode = $raw['selection_mode'] ?? null;
    $type = $raw['location_type'] ?? null;
    $typeForMode = [
        'SINGLE_TOOTH'=>'TOOTH_LOCATION', 'MULTIPLE_TEETH'=>'TOOTH_LOCATION',
        'QUADRANT'=>'QUADRANT_LOCATION', 'ARCH'=>'ARCH_LOCATION',
        'REGION'=>'REGION_LOCATION', 'BILATERAL_REGION'=>'REGION_LOCATION',
        'TMJ_REGION'=>'TMJ_LOCATION',
    ];
    if (!is_string($mode) || !isset($typeForMode[$mode]) || $type !== $typeForMode[$mode]) {
        throw new InvalidArgumentException('DENTAL_V2_TYPE_INVALID');
    }
    if (!in_array($mode, $policy['allowed_location_modes'] ?? [], true)) {
        throw new InvalidArgumentException('DENTAL_V2_MODE_FORBIDDEN');
    }
    $base = ['contract_version','location_type','selection_mode'];
    $specific = match ($type) {
        'TOOTH_LOCATION'=>['numbering_system','dentition_mode','tooth_fdi_codes'],
        'QUADRANT_LOCATION'=>['dentition_mode','quadrant_key'],
        'ARCH_LOCATION'=>['dentition_mode','arch_key'],
        'REGION_LOCATION'=>['dentition_mode','region_key','arch_key','side_key','region_detail'],
        'TMJ_LOCATION'=>['tmj_side'],
    };
    $study = ($policy['study_kind'] ?? null) === 'CBCT' ? ['coverage','fov_cm']
        : (($policy['study_kind'] ?? null) === 'TMJ' ? ['projection'] : []);
    if (array_diff(array_keys($raw), [...$base,...$specific,...$study])) {
        throw new InvalidArgumentException('DENTAL_V2_FIELD_INVALID');
    }
    $dentition = $raw['dentition_mode'] ?? null;
    if ($dentition !== null && (!is_string($dentition) || !in_array($dentition, $authority['dentition_modes'], true)
        || !in_array($dentition, $policy['allowed_dentition_modes'] ?? [], true))) {
        throw new InvalidArgumentException('DENTAL_V2_DENTITION_INVALID');
    }
    $normalized = ['contract_version'=>2,'location_type'=>$type,'selection_mode'=>$mode];
    if ($type === 'TOOTH_LOCATION') {
        if (($raw['numbering_system'] ?? null) !== 'FDI_ISO_3950' || $dentition === null) {
            throw new InvalidArgumentException('DENTAL_V2_TOOTH_AUTHORITY_INVALID');
        }
        $codes = $raw['tooth_fdi_codes'] ?? null;
        if (!is_array($codes) || !array_is_list($codes)) throw new InvalidArgumentException('DENTAL_V2_TEETH_INVALID');
        $count = count($codes);
        if ($count < max(1, (int)($policy['min_selection'] ?? 1)) || $count > (int)($policy['max_selection'] ?? 52)
            || ($mode === 'SINGLE_TOOTH' && $count !== 1)) throw new InvalidArgumentException('DENTAL_V2_TEETH_COUNT_INVALID');
        $teeth = clinical_dental_teeth();
        foreach ($codes as $code) {
            if (!is_string($code) || !isset($teeth[$code])) throw new InvalidArgumentException('DENTAL_V2_TOOTH_CODE_INVALID');
            $toothMode = $teeth[$code]['dentition'] === 'DECIDUOUS' ? 'PRIMARY' : 'PERMANENT';
            if ($dentition !== 'MIXED' && $dentition !== $toothMode) {
                throw new InvalidArgumentException('DENTAL_V2_DENTITION_TOOTH_CONFLICT');
            }
        }
        if (count(array_unique($codes)) !== $count) throw new InvalidArgumentException('DENTAL_V2_TOOTH_DUPLICATE');
        usort($codes, static fn(string $a,string $b): int => (int)$a <=> (int)$b);
        $normalized += ['numbering_system'=>'FDI_ISO_3950','dentition_mode'=>$dentition,'tooth_fdi_codes'=>$codes];
    } elseif ($type === 'QUADRANT_LOCATION') {
        if ($dentition === null || !in_array($raw['quadrant_key'] ?? null, $policy['allowed_quadrants'] ?? $authority['quadrants'], true)) {
            throw new InvalidArgumentException('DENTAL_V2_QUADRANT_INVALID');
        }
        $normalized += ['dentition_mode'=>$dentition,'quadrant_key'=>$raw['quadrant_key']];
    } elseif ($type === 'ARCH_LOCATION') {
        if (!in_array($raw['arch_key'] ?? null, $policy['allowed_arches'] ?? $authority['arches'], true)) throw new InvalidArgumentException('DENTAL_V2_ARCH_INVALID');
        if ($dentition !== null) $normalized['dentition_mode'] = $dentition;
        $normalized['arch_key'] = $raw['arch_key'];
    } elseif ($type === 'REGION_LOCATION') {
        $region = $raw['region_key'] ?? null;
        $arch = $raw['arch_key'] ?? null;
        $side = $raw['side_key'] ?? null;
        $detail = $raw['region_detail'] ?? null;
        if (!in_array($region, $policy['allowed_regions'] ?? $authority['regions'], true)
            || ($arch !== null && !in_array($arch, $policy['allowed_arches'] ?? $authority['arches'], true))
            || ($side !== null && !in_array($side, $policy['allowed_sides'] ?? $authority['sides'], true))) {
            throw new InvalidArgumentException('DENTAL_V2_REGION_INVALID');
        }
        if ($mode === 'BILATERAL_REGION' ? $side !== 'BILATERAL' : $side === 'BILATERAL') {
            throw new InvalidArgumentException('DENTAL_V2_REGION_SIDE_INVALID');
        }
        if ($region === 'MAXILLOFACIAL' && ($arch !== null || $side !== null)) throw new InvalidArgumentException('DENTAL_V2_REGION_INVALID');
        if (in_array($region, ['ANTERIOR','POSTERIOR'], true) && ($arch === null || $side === null)) {
            throw new InvalidArgumentException('DENTAL_V2_REGION_INVALID');
        }
        if ($region === 'OTHER_SPECIFIED') {
            if (!is_string($detail) || trim($detail) === '' || mb_strlen(trim($detail)) > 120) {
                throw new InvalidArgumentException('DENTAL_V2_REGION_DETAIL_REQUIRED');
            }
            $detail = trim($detail);
        } elseif ($detail !== null) throw new InvalidArgumentException('DENTAL_V2_REGION_DETAIL_FORBIDDEN');
        if ($dentition !== null) $normalized['dentition_mode'] = $dentition;
        $normalized['region_key'] = $region;
        if ($arch !== null) $normalized['arch_key'] = $arch;
        if ($side !== null) $normalized['side_key'] = $side;
        if ($detail !== null) $normalized['region_detail'] = $detail;
    } else {
        if (!in_array($raw['tmj_side'] ?? null, $policy['allowed_tmj_sides'] ?? $authority['tmj_sides'], true)) {
            throw new InvalidArgumentException('DENTAL_V2_TMJ_SIDE_INVALID');
        }
        $normalized['tmj_side'] = $raw['tmj_side'];
    }
    if (($policy['study_kind'] ?? null) === 'CBCT') {
        $coverage = $raw['coverage'] ?? null;
        $expected = match ($type) {
            'TOOTH_LOCATION','QUADRANT_LOCATION'=>'LOCALIZED',
            'ARCH_LOCATION'=>match ($normalized['arch_key']) {
                'MAXILLARY'=>'MAXILLARY_ARCH','MANDIBULAR'=>'MANDIBULAR_ARCH','BOTH_ARCHES'=>'BOTH_ARCHES',
            },
            'REGION_LOCATION'=>$normalized['region_key'] === 'MAXILLOFACIAL' ? 'MAXILLOFACIAL' : 'LOCALIZED',
            default=>null,
        };
        if ($coverage !== $expected) throw new InvalidArgumentException('DENTAL_V2_COVERAGE_CONFLICT');
        $normalized['coverage'] = $coverage;
        if (array_key_exists('fov_cm', $raw)) {
            $fov = $raw['fov_cm'];
            if (!is_string($fov) || preg_match('/^(?:[1-9]|[12][0-9]|30)(?:\.[0-9])?x(?:[1-9]|[12][0-9]|30)(?:\.[0-9])?$/D', $fov) !== 1) {
                throw new InvalidArgumentException('DENTAL_V2_FOV_INVALID');
            }
            $normalized['fov_cm'] = $fov;
        }
    } elseif (($policy['study_kind'] ?? null) === 'TMJ') {
        if (!in_array($raw['projection'] ?? null, ['LATERAL','PA'], true)) {
            throw new InvalidArgumentException('DENTAL_V2_PROJECTION_INVALID');
        }
        $normalized['projection'] = $raw['projection'];
    }
    return $normalized;
}

function clinical_dental_v2_validate_study(?string $studyKey, mixed $raw): ?array
{
    $policy = clinical_dental_v2_study_policy($studyKey);
    if ($policy === null || $policy['allowed_location_modes'] === []) throw new InvalidArgumentException('DENTAL_V2_STUDY_INCOMPATIBLE');
    if ($raw === null) {
        if (!$policy['required']) return null;
        throw new InvalidArgumentException('DENTAL_V2_LOCATION_REQUIRED');
    }
    if (!is_array($raw)) throw new InvalidArgumentException('DENTAL_V2_LOCATION_INVALID');
    $policy['study_kind'] = match ($studyKey) {'dental_cbct'=>'CBCT','tmj_comparative_xray'=>'TMJ',default=>null};
    return clinical_dental_v2_validate($raw, $policy);
}

function clinical_dental_v2_summary(array $location): string
{
    $labels = [
        'UPPER_RIGHT'=>'superior derecho','UPPER_LEFT'=>'superior izquierdo',
        'LOWER_LEFT'=>'inferior izquierdo','LOWER_RIGHT'=>'inferior derecho',
        'MAXILLARY'=>'Maxilar superior','MANDIBULAR'=>'Mandíbula','BOTH_ARCHES'=>'Ambas arcadas',
        'ANTERIOR'=>'Anterior','POSTERIOR'=>'Posterior','MAXILLOFACIAL'=>'Maxilofacial','OTHER_SPECIFIED'=>'Región especificada',
        'LEFT'=>'izquierda','RIGHT'=>'derecha','MIDLINE'=>'media','BILATERAL'=>'bilateral',
        'LOCALIZED'=>'Zona localizada','MAXILLARY_ARCH'=>'Maxilar superior',
        'MANDIBULAR_ARCH'=>'Mandíbula',
    ];
    $parts = [];
    if (isset($location['tooth_fdi_codes'])) $parts[] = 'Piezas '.implode(', ', $location['tooth_fdi_codes']);
    if (isset($location['quadrant_key'])) $parts[] = 'Cuadrante '.$labels[$location['quadrant_key']];
    if (isset($location['arch_key'])) $parts[] = $labels[$location['arch_key']];
    if (isset($location['region_key'])) $parts[] = 'Región '.$labels[$location['region_key']];
    if (isset($location['side_key'])) $parts[] = 'Lado '.$labels[$location['side_key']];
    if (isset($location['region_detail'])) $parts[] = $location['region_detail'];
    if (isset($location['tmj_side'])) $parts[] = 'ATM '.$labels[$location['tmj_side']];
    if (isset($location['coverage'])) $parts[] = 'Cobertura '.$labels[$location['coverage']];
    if (isset($location['projection'])) $parts[] = 'Vista '.($location['projection'] === 'PA' ? 'posteroanterior' : 'lateral');
    if (isset($location['fov_cm'])) $parts[] = 'Campo solicitado '.$location['fov_cm'].' cm';
    return implode(' · ', $parts);
}
