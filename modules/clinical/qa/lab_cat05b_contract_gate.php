<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';
ob_start();

$pdo = new PDO('mysql:host=localhost;dbname=mxmed_director_review_lon07c', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pass = static function(bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); echo $name."=PASS\n"; };
$reject = static function(callable $run, string $name) use ($pass): void {
    try { $run(); } catch (InvalidArgumentException|RuntimeException $error) { $pass(true, $name); return; }
    throw new RuntimeException($name);
};
$panel = clinical_lab_panel_authority();
$presets = clinical_lab_preset_authority();
$specimens = clinical_specimen_authority();
$pass($panel['version'] === 1 && array_keys($panel['panels']) === ['panel_quimica_6','arterial_blood_gas'], 'QA_PANEL_AUTHORITY_VERSION');
$pass(array_column($panel['panels']['panel_quimica_6']['components'],'semantic_key') === ['glucose','urea','creatinine','uric_acid','chol_total','triglycerides'], 'QA_QUIMICA_6_EXACT_COMPONENTS');
$pass(array_column($panel['panels']['arterial_blood_gas']['components'],'semantic_key') === ['arterial_ph','arterial_paco2','arterial_pao2','arterial_hco3','arterial_oxygen_saturation','arterial_base_excess'], 'QA_GAS_EXACT_OBSERVATIONS');
$pass($presets['version'] === 1 && count($presets['presets']) === 3, 'QA_PRESET_AUTHORITY_VERSION');
$pass($specimens['version'] === 1 && isset($specimens['specimen_types']['ARTERIAL_BLOOD']), 'QA_ARTERIAL_SPECIMEN_AUTHORITY');
foreach ($panel['panels'] as $key => $rule) {
    $snapshot = clinical_lab_panel_snapshot($pdo, $key, ['panel_definition_key'=>$key,'panel_definition_version'=>1], true);
    $pass($snapshot['panel_definition_key'] === $key && count($snapshot['components']) === count($rule['components']), 'QA_PANEL_'.strtoupper($key));
    $reject(static fn()=>clinical_lab_panel_snapshot($pdo,$key,null), 'QA_PANEL_INACTIVE_'.strtoupper($key));
}
$reject(static fn()=>clinical_lab_panel_snapshot($pdo,'panel_quimica_6',['panel_definition_key'=>'panel_quimica_6','panel_definition_version'=>2],true), 'QA_PANEL_VERSION_REJECTED');
$badPanel = $panel;
$badPanel['panels']['panel_quimica_6']['components'][1]['semantic_key'] = 'glucose';
clinical_lab_panel_authority($badPanel);
$reject(static fn()=>clinical_lab_panel_snapshot($pdo,'panel_quimica_6',null,true), 'QA_PANEL_DUPLICATE_COMPONENT_REJECTED');
$badPanel['panels']['panel_quimica_6']['components'][1]['semantic_key'] = 'nonexistent_analyte';
clinical_lab_panel_authority($badPanel);
$reject(static fn()=>clinical_lab_panel_snapshot($pdo,'panel_quimica_6',null,true), 'QA_PANEL_UNKNOWN_COMPONENT_REJECTED');
$badPanel = $panel;
$badPanel['panels']['arterial_blood_gas']['components'][0]['semantic_key'] = 'arterial_unknown_result';
clinical_lab_panel_authority($badPanel);
$reject(static fn()=>clinical_lab_panel_snapshot($pdo,'arterial_blood_gas',null,true), 'QA_GAS_UNKNOWN_OBSERVATION_REJECTED');
clinical_lab_panel_authority($panel);
$gas = clinical_lab_panel_snapshot($pdo,'arterial_blood_gas',null,true);
$pass($gas['specimen_type_key'] === 'ARTERIAL_BLOOD' && clinical_specimen_validate('arterial_blood_gas',null)['specimen_type_key'] === 'ARTERIAL_BLOOD', 'QA_ARTERIAL_GAS_SPECIMEN');
$reject(static fn()=>clinical_specimen_validate('arterial_blood_gas',['specimen_type_key'=>'VENOUS_BLOOD']), 'QA_ARTERIAL_GAS_WRONG_SPECIMEN');
$pass(clinical_lab_arterial_oxygen_validate('arterial_blood_gas',['version'=>1,'mode'=>'ROOM_AIR'])['mode'] === 'ROOM_AIR', 'QA_ROOM_AIR');
$pass(clinical_lab_arterial_oxygen_validate('arterial_blood_gas',['version'=>1,'mode'=>'SUPPLEMENTAL_OXYGEN','fio2_percent'=>35,'delivery_device'=>'NASAL_CANNULA'])['fio2_percent'] === 35, 'QA_SUPPLEMENTAL_OXYGEN');
$reject(static fn()=>clinical_lab_arterial_oxygen_validate('arterial_blood_gas',['version'=>1,'mode'=>'SUPPLEMENTAL_OXYGEN']), 'QA_OXYGEN_DETAIL_REQUIRED');
$reject(static fn()=>clinical_lab_arterial_oxygen_validate('arterial_blood_gas',['version'=>1,'mode'=>'ROOM_AIR','fio2_percent'=>30]), 'QA_ROOM_AIR_FIO2_REJECTED');
$reject(static fn()=>clinical_lab_arterial_oxygen_validate('arterial_blood_gas',['version'=>1,'mode'=>'UNKNOWN','unexpected'=>1]), 'QA_UNKNOWN_OXYGEN_FIELD_REJECTED');
$reject(static fn()=>clinical_lab_arterial_oxygen_validate('arterial_blood_gas',['version'=>1,'mode'=>'SUPPLEMENTAL_OXYGEN','fio2_percent'=>101]), 'QA_INVALID_FIO2_REJECTED');
$requests = [
    'preset_qs3_renal_v1'=>['glucose','urea','creatinine'],
    'preset_qs3_lipids_v1'=>['glucose','chol_total','triglycerides'],
    'preset_hepatic_basic_v1'=>['ast','alt','alp','ggt','total_protein','albumin','bilirubin_total','bilirubin_direct'],
];
foreach ($requests as $key=>$expected) {
    $rule = clinical_lab_preset_definition(['preset_key'=>$key,'preset_version'=>1],true);
    $pass($rule['component_study_keys'] === $expected, 'QA_PRESET_'.strtoupper($key));
    $reject(static fn()=>clinical_lab_preset_definition(['preset_key'=>$key,'preset_version'=>1]), 'QA_PRESET_INACTIVE_'.strtoupper($key));
}
$badPresets = $presets;
$badPresets['presets']['preset_qs3_renal_v1']['component_study_keys'][] = 'glucose';
clinical_lab_preset_authority($badPresets);
$reject(static fn()=>clinical_lab_preset_definition(['preset_key'=>'preset_qs3_renal_v1','preset_version'=>1],true), 'QA_PRESET_DUPLICATE_COMPONENT_REJECTED');
$badPresets['presets']['preset_qs3_renal_v1']['component_study_keys'][3] = 'nonexistent_analyte';
clinical_lab_preset_authority($badPresets);
$reject(static fn()=>clinical_lab_preset_expand($pdo,[],[['preset_key'=>'preset_qs3_renal_v1','preset_version'=>1]],'CLINICAL_LAB',true), 'QA_PRESET_UNKNOWN_COMPONENT_REJECTED');
clinical_lab_preset_authority($presets);
$application = static fn(string $key): array => ['preset_key'=>$key,'preset_version'=>1];
$expand = static fn(array $inputs,array $apps): array => clinical_lab_preset_expand($pdo,$inputs,$apps,'CLINICAL_LAB',true);
$renal = $expand([],[$application('preset_qs3_renal_v1')]);
$pass(count($renal['items']) === 3 && count($renal['preview'][0]['new_keys']) === 3, 'QA_EMPTY_PLUS_RENAL');
$one = $expand([['study_type_key'=>'glucose']],[$application('preset_qs3_renal_v1')]);
$pass(count($one['items']) === 3 && $one['preview'][0]['already_selected_keys'] === ['glucose'], 'QA_ONE_PRESELECTED');
$all = $expand(array_map(static fn($key)=>['study_type_key'=>$key],$requests['preset_qs3_renal_v1']),[$application('preset_qs3_renal_v1')]);
$pass(count($all['items']) === 3 && $all['preview'][0]['new_keys'] === [], 'QA_ALL_PRESELECTED');
$twice = $expand([],[$application('preset_qs3_renal_v1'),$application('preset_qs3_renal_v1')]);
$pass(count($twice['items']) === 3 && count($twice['applications']) === 1, 'QA_PRESET_IDEMPOTENT');
$both = $expand([],[$application('preset_qs3_renal_v1'),$application('preset_qs3_lipids_v1')]);
$pass(count($both['items']) === 5 && $both['preview'][1]['already_selected_keys'] === ['glucose'], 'QA_OVERLAPPING_PRESETS_DEDUPED');
$hepatic = $expand([['study_type_key'=>'alt']],[$application('preset_hepatic_basic_v1')]);
$pass(count($hepatic['items']) === 8 && $hepatic['preview'][0]['already_selected_keys'] === ['alt'], 'QA_HEPATIC_PRESELECTED');
$pass(count($renal['preview'][0]['specimen_requirements']) === 3, 'QA_PRESET_SPECIMEN_AGGREGATION');
$reject(static fn()=>clinical_lab_preset_expand($pdo,[],[$application('preset_qs3_renal_v1')],'IMAGING',true), 'QA_PRESET_WRONG_ROUTE');
$reject(static fn()=>clinical_lab_preset_expand($pdo,[],[$application('preset_qs3_renal_v1')],'CLINICAL_LAB'), 'QA_PRESET_INACTIVE_EXPANSION');
$ordered = [];
foreach ($renal['items'] as $index=>$input) {
    $item = clinical_study_order_snapshot($pdo,$input,$index+1);
    $item['order_item_id'] = clinical_study_uuid();
    $ordered[] = $item;
}
$prov = clinical_lab_preset_snapshot($pdo,$renal['applications'],$ordered,true);
$pass(count($prov) === 1 && count($prov[0]['components']) === 3 && count(array_unique(array_column($prov[0]['components'],'order_item_id'))) === 3, 'QA_PRESET_COMPONENT_ITEM_MAPPING');
$reject(static fn()=>clinical_lab_preset_snapshot($pdo,$renal['applications'],array_slice($ordered,0,2),true), 'QA_PRESET_MISSING_ITEM_REJECTED');
$reject(static fn()=>clinical_lab_preset_snapshot($pdo,$renal['applications'],[$ordered[0],$ordered[0],$ordered[1],$ordered[2]],true), 'QA_PRESET_DUPLICATE_ITEM_REJECTED');
$activePresets = $presets;
$activePresets['presets']['preset_qs3_renal_v1']['status'] = 'ACTIVE';
clinical_lab_preset_authority($activePresets);
$issued = clinical_study_normalize_order_payload($pdo,'lab_order',['order_items'=>$renal['items'],'lab_preset_applications'=>$renal['applications']]);
$pass(count($issued['order_items']) === 3 && count($issued['lab_preset_provenance'][0]['components']) === 3, 'QA_ISSUED_PRESET_SNAPSHOT');
$successor = clinical_study_normalize_order_payload($pdo,'lab_order',['order_items'=>$issued['order_items'],'lab_preset_provenance'=>$issued['lab_preset_provenance']],$issued);
$pass($successor['lab_preset_provenance'] === $issued['lab_preset_provenance'], 'QA_SUCCESSOR_PRESERVES_PRESET_PROVENANCE');
$changed = $issued['order_items'];
array_pop($changed);
$reject(static fn()=>clinical_study_normalize_order_payload($pdo,'lab_order',['order_items'=>$changed],$issued), 'QA_SUCCESSOR_CANNOT_REMOVE_PRESET_COMPONENT');
clinical_lab_preset_authority($presets);
$order = ['studies'=>[['name'=>'Química sanguínea de 6 elementos','panel_components'=>array_column($panel['panels']['panel_quimica_6']['components'],'label')],
    ['name'=>'Gasometría arterial','oxygen_context'=>clinical_lab_arterial_oxygen_summary(['version'=>1,'mode'=>'SUPPLEMENTAL_OXYGEN','fio2_percent'=>35])]],
    'patient'=>['name'=>'Paciente QA'],'physician'=>['name'=>'Médico QA'],'issued_at'=>'2026-10-04 12:00:00','display_reference'=>'QA'];
define('MXMED_PORTABLE_ORDER_TEMPLATE_ALLOWED',true);
ob_start(); include __DIR__.'/../ui/portable-order-template.php'; $html=ob_get_clean();
$pass(str_contains($html,'Incluye: Glucosa, Urea, Creatinina') && str_contains($html,'FiO₂: 35%') && !str_contains($html,'preset_qs3'), 'QA_PORTABLE_STORED_PANEL_AND_OXYGEN');
$pass(!isset($specimens['studies']['panel_quimica_6']) && !isset($presets['presets']['panel_quimica_6']), 'QA_NO_PSEUDO_PRESET_PANEL');
foreach (['cbc','ogtt','urine_albumin_creatinine_panel','urine_protein_creatinine_panel','csf_meningitis_encephalitis_panel'] as $key) {
    $current = clinical_study_order_snapshot($pdo,['study_type_key'=>$key],1);
    $pass($current['study_type_key'] === $key && !isset($current['lab_panel_definition']), 'QA_EXISTING_PANEL_'.strtoupper($key));
}
foreach (['glucose'=>'LABORATORIO','urinalysis'=>'LABORATORIO','ecg_12lead'=>'CARDIOVASCULAR','anoscopy_base'=>'ENDOSCOPIA','dental_panoramic_xray'=>'IMAGEN'] as $key=>$category) {
    $current = clinical_study_order_snapshot($pdo,['study_type_key'=>$key],1);
    $pass($current['study_category'] === $category && !isset($current['lab_panel_definition']) && !isset($current['lab_arterial_oxygen_context']), 'QA_OTHER_STUDY_'.strtoupper($key));
}
echo "LAB_CAT05B_CONTRACT_GATE=PASS\n";
ob_end_flush();
