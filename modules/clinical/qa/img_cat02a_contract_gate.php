<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/clinical_study_contract.php';
ob_start();

function check(bool $condition, string $case): void
{
    if (!$condition) throw new RuntimeException('FAIL ' . $case);
    echo 'PASS ', $case, PHP_EOL;
}
function rejects(callable $call, string $case): void
{
    try { $call(); } catch (InvalidArgumentException) { check(true, $case); return; }
    check(false, $case);
}
$a = clinical_imaging_authority();
check($a['version'] === 1 && count($a['rules']) === 55, 'authority version and exact study map');
check(count(array_filter($a['rules'], static fn($r) => $r['status'] === 'FUTURE_INACTIVE')) === 15, 'future identities mapped but inactive');
foreach ($a['value_labels'] as $group => $values) check(count($values) === count(array_unique(array_keys($values))), 'unique ' . $group . ' values');
foreach ($a['rules'] as $key => $rule) {
    foreach ($rule['allowed'] as $field => $choices) {
        $group = ['contrast_routes'=>'contrast_route','xray_views'=>'xray_view','dxa_sites'=>'dxa_site'][$field] ?? $field;
        check(isset($a['field_labels'][$field]) && count($choices) === count(array_unique($choices))
            && !array_diff($choices, array_keys($a['value_labels'][$group] ?? [])), 'valid applicability ' . $key . '/' . $field);
    }
    foreach ($rule['required'] as $field) check(isset($rule['allowed'][$field]), 'required field allowed ' . $key . '/' . $field);
}
$current = array_filter($a['rules'], static fn($r) => $r['status'] === 'ACTIVE');
check(count($current) === 34, '34 active general imaging keys');
foreach (array_keys($current) as $key) check(clinical_imaging_validate($key, null) === null, 'existing absent parameters ' . $key);
$valid = [
 'rx_hip'=>['laterality'=>'LEFT','xray_view_preset'=>'JOINT_STANDARD'],
 'ct_sinuses'=>['contrast_intent'=>'WITHOUT_CONTRAST'],
 'mr_lumbar_spine'=>['contrast_intent'=>'WITH_IV_CONTRAST','contrast_routes'=>['IV']],
 'rx_foot'=>['laterality'=>'RIGHT','weight_bearing'=>'YES'],
 'rx_wrist'=>['laterality'=>'BILATERAL'],
 'rx_elbow'=>['laterality'=>'LEFT'],
 'mr_cervical_spine'=>['contrast_intent'=>'WITHOUT_CONTRAST'],
 'ct_neck'=>['contrast_intent'=>'WITH_IV_CONTRAST'],
 'cta_head_neck'=>['contrast_intent'=>'WITH_IV_CONTRAST','vascular_territory'=>'NECK'],
 'mra_brain'=>['contrast_intent'=>'WITHOUT_CONTRAST','vascular_territory'=>'HEAD'],
 'mr_pelvis'=>['contrast_intent'=>'WITH_AND_WITHOUT_IV_CONTRAST'],
 'breast_tomosynthesis'=>['laterality'=>'BILATERAL','breast_purpose'=>'SCREENING','breast_tomosynthesis_relation'=>'WITH_2D_MAMMOGRAPHY'],
 'rx_tspine'=>['xray_view_preset'=>'SPINE_STANDARD'],
 'nm_renal_scan'=>['physician_protocol'=>'DYNAMIC'],
 'nm_myocardial_perfusion'=>['physician_protocol'=>'STRESS_REST'],
];
check(count($valid) === 15, '15 future scenario fixtures');
foreach ($valid as $key => $data) {
    $snapshot = clinical_imaging_validate($key, ['version'=>1, ...$data]);
    check($snapshot['version'] === 1 && clinical_imaging_summary($snapshot) !== '', 'future parameter rule ' . $key);
}
rejects(fn() => clinical_imaging_validate('ct_head', ['version'=>1,'laterality'=>'LEFT']), 'laterality on CT head');
rejects(fn() => clinical_imaging_validate('ct_head', ['version'=>1,'contrast_intent'=>'WITH_IV_CONTRAST','contrast_routes'=>['ORAL']]), 'wrong contrast route');
rejects(fn() => clinical_imaging_validate('rx_chest', ['version'=>1,'vascular_territory'=>'CAROTID']), 'vascular territory on chest RX');
rejects(fn() => clinical_imaging_validate('rx_chest', ['version'=>1,'xray_views'=>['UNKNOWN']]), 'unknown projection');
rejects(fn() => clinical_imaging_validate('rx_chest', ['version'=>1,'weight_bearing'=>'YES']), 'unsupported weight bearing');
rejects(fn() => clinical_imaging_validate('pet_ct', ['version'=>1,'radiotracer'=>'UNLISTED']), 'unknown tracer');
rejects(fn() => clinical_imaging_validate('ct_head', ['version'=>1,'scanner_model'=>'X']), 'unknown parameter key');
rejects(fn() => clinical_imaging_validate('ct_head', ['version'=>2,'contrast_intent'=>'WITHOUT_CONTRAST']), 'wrong authority version');
rejects(fn() => clinical_imaging_validate('rx_hip', ['version'=>1,'laterality'=>'LEFT','xray_views'=>['AP'],'xray_view_preset'=>'JOINT_STANDARD']), 'views and preset conflict');
rejects(fn() => clinical_imaging_validate('cta_head_neck', ['version'=>1,'contrast_intent'=>'WITH_IV_CONTRAST','vascular_territory'=>'RENAL']), 'CTA territory constraint');
rejects(fn() => clinical_imaging_validate('breast_tomosynthesis', ['version'=>1,'laterality'=>'LEFT']), 'breast purpose required');
rejects(fn() => clinical_imaging_validate('cbc', ['version'=>1,'laterality'=>'LEFT']), 'laboratory rejects imaging parameters');
rejects(fn() => clinical_imaging_validate('dexa', ['version'=>1,'dxa_sites'=>['WHOLE_BODY','HIP_LEFT']]), 'DXA whole body/site conflict');
rejects(fn() => clinical_imaging_validate('lower_ext_venous_doppler', ['version'=>1,'flow_type'=>'ARTERIAL']), 'Doppler fixed flow contradiction');
rejects(fn() => clinical_imaging_validate('rx_knee', ['version'=>1,'xray_views'=>['AP','AP']]), 'duplicate projection');
check(clinical_imaging_validate('ct_abdomen_pelvis', ['version'=>1,'contrast_intent'=>'WITH_IV_AND_ORAL_CONTRAST','contrast_routes'=>['IV','ORAL']])['version']===1, 'CT abdomen oral and IV intent');
foreach (['cbc','histopath_biopsy','ecg_12lead','anoscopy_base','dental_panoramic_xray'] as $key) {
    check(clinical_imaging_validate($key, null) === null, 'other authority unaffected ' . $key);
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE clinical_study_types (study_type_id INTEGER PRIMARY KEY, study_type_key TEXT, display_name_es TEXT, category_key TEXT, is_active INTEGER)');
$pdo->exec("INSERT INTO clinical_study_types VALUES (1,'ct_head','TAC Cráneo','IMAGEN',1),(2,'cbc','Biometría hemática','LABORATORIO',1),(3,'cyto_pap','Citología cervical','PATOLOGIA',1),(4,'ecg_12lead','Electrocardiograma','CARDIOVASCULAR',1),(5,'anoscopy_base','Anoscopia','ENDOSCOPIA',1),(6,'dental_panoramic_xray','Radiografía panorámica dental','IMAGEN',1)");
$input = ['study_type_key'=>'ct_head','imaging_order_parameters'=>['version'=>1,'contrast_intent'=>'WITHOUT_CONTRAST']];
$item = clinical_study_order_snapshot($pdo, $input, 1);
check($item['imaging_order_parameters']['version'] === 1 && str_contains($item['imaging_order_parameters_label'],'Sin contraste'), 'server snapshot and Spanish label');
$order = clinical_study_normalize_order_payload($pdo, 'imaging_order', ['order_items'=>[$input]]);
check($order['order_items'][0]['imaging_order_parameters'] === $item['imaging_order_parameters'], 'order write normalization');
$unchanged = clinical_study_normalize_order_payload($pdo, 'imaging_order', ['order_items'=>[$order['order_items'][0]]], $order);
check($unchanged['order_items'][0]['order_item_id'] === $order['order_items'][0]['order_item_id'], 'successor preserves exact item snapshot');
$tampered = $order['order_items'][0];
$tampered['imaging_order_parameters']['contrast_intent'] = 'WITH_IV_CONTRAST';
rejects(fn() => clinical_study_normalize_order_payload($pdo, 'imaging_order', ['order_items'=>[$tampered]], $order), 'issued item parameter immutability');
foreach (['cbc'=>'laboratory','cyto_pap'=>'pathology','ecg_12lead'=>'functional','anoscopy_base'=>'procedure','dental_panoramic_xray'=>'dental'] as $key=>$domain) {
    $other = clinical_study_order_snapshot($pdo, ['study_type_key'=>$key], 1);
    check(!isset($other['imaging_order_parameters']), $domain . ' snapshot unchanged');
}
$portable = ['studies'=>[['name'=>'TAC Cráneo','imaging_context'=>$item['imaging_order_parameters_label'],'note'=>null]],'patient'=>['name'=>'Paciente QA'],'physician'=>['name'=>'Médico QA'],'issued_at'=>'2026-10-04 12:00:00','display_reference'=>'QA'];
define('MXMED_PORTABLE_ORDER_TEMPLATE_ALLOWED', true);
$order = $portable; $error='';
ob_start(); include __DIR__ . '/../ui/portable-order-template.php'; $html = ob_get_clean();
check(str_contains($html, 'Contraste: Sin contraste'), 'portable print shows snapshot label');
check(!str_contains($html, 'WITHOUT_CONTRAST'), 'portable print hides internal enum');
$pdo->exec("INSERT INTO clinical_study_types VALUES (7,'ct_sinuses','TAC de senos paranasales','IMAGEN',1)");
rejects(fn() => clinical_study_order_snapshot($pdo, ['study_type_key'=>'ct_sinuses'], 1), 'future order requires parameter contract');
$resultTaxonomy = clinical_study_validate_result_payload($pdo, 'imaging_result', ['study_type_key'=>'ct_sinuses'], 'patient-qa');
check($resultTaxonomy['study_type_key'] === 'ct_sinuses', 'standalone result taxonomy does not require order parameters');
echo 'IMG_CAT02A_GATE_PASS', PHP_EOL;
ob_end_flush();
