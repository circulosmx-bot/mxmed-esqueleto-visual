<?php
declare(strict_types=1);

/** Process override is for isolated contract QA; HTTP input cannot set it. */
function clinical_lab_panel_authority(?array $processOverride = null): array
{
    static $config;
    if ($processOverride !== null) $config = $processOverride;
    return $config ??= json_decode((string)file_get_contents(__DIR__.'/../../modules/clinical/catalog/lab_panel_definitions_v1.json'), true, 512, JSON_THROW_ON_ERROR);
}

function clinical_lab_panel_snapshot(PDO $pdo, ?string $studyKey, mixed $request, bool $allowFuture = false): ?array
{
    $authority = clinical_lab_panel_authority();
    if (($authority['contract'] ?? null) !== 'lab_panel_definitions' || ($authority['version'] ?? null) !== 1) throw new RuntimeException('LAB_PANEL_AUTHORITY_INVALID');
    $rule = $authority['panels'][$studyKey ?? ''] ?? null;
    if ($rule === null) {
        if ($request !== null) throw new InvalidArgumentException('LAB_PANEL_STUDY_INCOMPATIBLE');
        return null;
    }
    if (!$allowFuture && ($rule['status'] ?? null) !== 'ACTIVE') throw new InvalidArgumentException('LAB_PANEL_INACTIVE');
    if ($request !== null) {
        if (!is_array($request) || array_is_list($request) || array_diff(array_keys($request), ['panel_definition_key','panel_definition_version'])) throw new InvalidArgumentException('LAB_PANEL_REQUEST_INVALID');
        if (($request['panel_definition_key'] ?? null) !== $studyKey) throw new InvalidArgumentException('LAB_PANEL_DEFINITION_MISMATCH');
        if (($request['panel_definition_version'] ?? null) !== ($rule['definition_version'] ?? null)) throw new InvalidArgumentException('LAB_PANEL_VERSION_INVALID');
    }
    $components = $rule['components'] ?? null;
    if (!is_array($components) || !array_is_list($components) || !$components || count($components) > 50) throw new RuntimeException('LAB_PANEL_COMPONENTS_INVALID');
    $seen = [];
    foreach ($components as $component) {
        $key = $component['semantic_key'] ?? null;
        if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]*$/D', $key) || isset($seen[$key]) || !is_string($component['label'] ?? null) || trim($component['label']) === '') throw new RuntimeException('LAB_PANEL_COMPONENT_INVALID');
        $seen[$key] = true;
        if (($rule['component_model'] ?? null) === 'CANONICAL_ANALYTE') {
            $stmt = $pdo->prepare('SELECT is_active,category_key FROM clinical_study_types WHERE study_type_key=?');
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || (int)$row['is_active'] !== 1 || $row['category_key'] !== 'LABORATORIO') throw new RuntimeException('LAB_PANEL_COMPONENT_AUTHORITY_MISMATCH');
        } elseif (($rule['component_model'] ?? null) === 'RESULT_OBSERVATION') {
            $gasObservations = ['arterial_ph','arterial_paco2','arterial_pao2','arterial_hco3','arterial_oxygen_saturation','arterial_base_excess'];
            if ($studyKey !== 'arterial_blood_gas' || !in_array($key, $gasObservations, true)) throw new RuntimeException('LAB_PANEL_COMPONENT_AUTHORITY_MISMATCH');
        } else throw new RuntimeException('LAB_PANEL_COMPONENT_MODEL_INVALID');
    }
    if (!is_int($rule['definition_version'] ?? null) || $rule['definition_version'] < 1 || !in_array($rule['specimen_type_key'] ?? null, ['SERUM','ARTERIAL_BLOOD'], true) || ($rule['routing_group'] ?? null) !== 'CLINICAL_LAB') throw new RuntimeException('LAB_PANEL_RULE_INVALID');
    return ['authority_version'=>1,'panel_definition_key'=>$studyKey,'panel_definition_version'=>$rule['definition_version'],
        'component_model'=>$rule['component_model'],'components'=>$components,'specimen_type_key'=>$rule['specimen_type_key']];
}

function clinical_lab_arterial_oxygen_validate(?string $studyKey, mixed $raw): ?array
{
    if ($studyKey !== 'arterial_blood_gas') {
        if ($raw !== null) throw new InvalidArgumentException('ARTERIAL_OXYGEN_STUDY_INCOMPATIBLE');
        return null;
    }
    if ($raw === null) return ['version'=>1,'mode'=>'UNKNOWN'];
    if (!is_array($raw) || array_is_list($raw) || array_diff(array_keys($raw), ['version','mode','fio2_percent','delivery_device'])) throw new InvalidArgumentException('ARTERIAL_OXYGEN_CONTEXT_INVALID');
    if (($raw['version'] ?? null) !== 1) throw new InvalidArgumentException('ARTERIAL_OXYGEN_VERSION_INVALID');
    $mode = $raw['mode'] ?? null;
    if (!in_array($mode, ['ROOM_AIR','SUPPLEMENTAL_OXYGEN','UNKNOWN'], true)) throw new InvalidArgumentException('ARTERIAL_OXYGEN_MODE_INVALID');
    $fio2 = $raw['fio2_percent'] ?? null;
    $device = $raw['delivery_device'] ?? null;
    if ($mode !== 'SUPPLEMENTAL_OXYGEN' && ($fio2 !== null || $device !== null)) throw new InvalidArgumentException('ARTERIAL_OXYGEN_DETAIL_INCOMPATIBLE');
    if ($fio2 !== null && (!is_int($fio2) && !is_float($fio2) || $fio2 <= 21 || $fio2 > 100)) throw new InvalidArgumentException('ARTERIAL_FIO2_INVALID');
    if ($device !== null && !in_array($device, ['NASAL_CANNULA','SIMPLE_MASK','NON_REBREATHER_MASK','HIGH_FLOW','VENTILATOR','OTHER_CONTROLLED'], true)) throw new InvalidArgumentException('ARTERIAL_OXYGEN_DEVICE_INVALID');
    if ($mode === 'SUPPLEMENTAL_OXYGEN' && $fio2 === null && $device === null) throw new InvalidArgumentException('ARTERIAL_OXYGEN_DETAIL_REQUIRED');
    return array_filter(['version'=>1,'mode'=>$mode,'fio2_percent'=>$fio2,'delivery_device'=>$device], static fn($value)=>$value!==null);
}

function clinical_lab_arterial_oxygen_summary(?array $context): ?string
{
    if ($context === null) return null;
    return match ($context['mode']) {
        'ROOM_AIR' => 'Oxígeno: aire ambiente',
        'UNKNOWN' => 'Oxígeno: contexto no conocido',
        default => 'Oxígeno suplementario'.(isset($context['fio2_percent']) ? ' · FiO₂: '.$context['fio2_percent'].'%' : '')
            .(isset($context['delivery_device']) ? ' · Dispositivo: '.(['NASAL_CANNULA'=>'cánula nasal','SIMPLE_MASK'=>'mascarilla simple','NON_REBREATHER_MASK'=>'mascarilla con reservorio','HIGH_FLOW'=>'alto flujo','VENTILATOR'=>'ventilador','OTHER_CONTROLLED'=>'otro dispositivo'] [$context['delivery_device']] ?? '') : ''),
    };
}
