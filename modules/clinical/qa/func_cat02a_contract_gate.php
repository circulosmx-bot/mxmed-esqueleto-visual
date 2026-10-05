<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';
require_once __DIR__.'/../../../api/_lib/clinical_study_search.php';
require_once __DIR__.'/../../../api/_lib/clinical_portable_order.php';
ob_start();

function check(bool $ok, string $case): void { if (!$ok) throw new RuntimeException('FAIL '.$case); echo 'PASS '.$case.PHP_EOL; }
function reject(callable $call, string $case): void {
    try { $call(); } catch (InvalidArgumentException) { check(true,$case); return; }
    check(false,$case);
}
$a=clinical_functional_authority();
check($a['version']===1 && count($a['rules'])===37, 'AUTHORITY_VERSION_AND_COUNT');
$nav=json_decode((string)file_get_contents(__DIR__.'/../catalog/study_featured_navigation_v1.json'),true,512,JSON_THROW_ON_ERROR)['leaves'];
$leaves=['cardiovascular'=>'CARDIOVASCULAR','neurophysiology'=>'NEUROFISIOLOGIA','pulmonary'=>'FUNCION_PULMONAR','sleep'=>'SUENO','audiovestibular'=>'AUDIOLOGIA'];
$current=[];
foreach ($leaves as $leaf=>$category) foreach ($nav[$leaf]['study_keys'] as $key) {
    check(!isset($current[$key]),'UNIQUE_KEY_'.$key);
    $current[$key]=$category;
    check(isset($a['rules'][$key]) && $a['rules'][$key]['status']==='ACTIVE_OPTIONAL','CURRENT_RULE_'.$key);
}
check(count($current)===35,'CURRENT_35_KEYS');
check(array_keys($a['rules'])===array_keys($current+['esophageal_manometry'=>true,'esophageal_ph_monitoring'=>true]),'NO_EXTRA_OR_MISSING_RULES');
foreach (['esophageal_manometry','esophageal_ph_monitoring'] as $key) check($a['rules'][$key]['status']==='FUTURE_REQUIRED','DORMANT_GI_'.$key);
foreach ($a['value_labels'] as $field=>$labels) check(count($labels)===count(array_unique(array_keys($labels))),'UNIQUE_ENUM_'.$field);
foreach ($a['rules'] as $key=>$rule) {
    foreach ($rule['allowed'] as $field=>$choices) {
        check(isset($a['field_labels'][$field],$a['value_labels'][$field]) && count($choices)===count(array_unique($choices)) && !array_diff($choices,array_keys($a['value_labels'][$field])),'APPLICABILITY_'.$key.'_'.$field);
    }
    check(!array_diff($rule['required'],array_keys($rule['allowed'])),'REQUIRED_SUBSET_'.$key);
}
$valid=[
    'holter'=>[['duration'=>'24_HOURS'],['duration'=>'48_HOURS'],['duration'=>'72_HOURS']],
    'video_eeg'=>[['duration'=>'24_HOURS'],['duration'=>'72_HOURS']],
    'emg_ncs'=>[['body_site'=>'UPPER_EXTREMITY','side'=>'RIGHT'],['body_site'=>'LOWER_EXTREMITY','side'=>'BILATERAL']],
    'evoked_ssep'=>[['body_site'=>'UPPER_EXTREMITY','side'=>'LEFT']],
    'evoked_auditory_baep'=>[['side'=>'BILATERAL']],
    'evoked_visual'=>[['side'=>'LEFT']],
    'spirometry'=>[['spirometry_protocol'=>'BASELINE'],['spirometry_protocol'=>'PRE_POST_BRONCHODILATOR']],
    'audiometry_tonal'=>[['side'=>'RIGHT']],
    'vng'=>[['caloric_intent'=>'WITH_CALORIC'],['caloric_intent'=>'WITHOUT_CALORIC']],
    'esophageal_manometry'=>[['gi_technique'=>'CONVENTIONAL'],['gi_technique'=>'HIGH_RESOLUTION']],
    'esophageal_ph_monitoring'=>[['gi_technique'=>'PH_ONLY','duration'=>'24_HOURS','acid_suppression'=>'OFF_THERAPY'],['gi_technique'=>'PH_IMPEDANCE','duration'=>'24_HOURS','acid_suppression'=>'ON_THERAPY']],
];
foreach ($valid as $key=>$cases) foreach ($cases as $case) {
    $p=clinical_functional_validate($key,['version'=>1,...$case]);
    check(count($p)===count($case)+1 && ($p['version'] ?? null)===1 && !array_diff_assoc($case,$p) && clinical_functional_summary($p)!=='','VALID_'.$key.'_'.implode('_',$case));
}
foreach ($current as $key=>$category) check(clinical_functional_validate($key,null)===null,'OMITTED_CURRENT_'.$key);
foreach ([
    ['holter',['version'=>1,'duration'=>'96_HOURS']],
    ['holter',['version'=>1,'duration'=>'24h']],
    ['holter',['version'=>1,'side'=>'LEFT']],
    ['holter',['version'=>2,'duration'=>'24_HOURS']],
    ['holter',['version'=>1]],
    ['abpm_mapa',['version'=>1,'duration'=>'24_HOURS']],
    ['stress_test',['version'=>1,'duration'=>'24_HOURS']],
    ['emg_ncs',['version'=>1,'body_site'=>'UPPER_EXTREMITY']],
    ['emg_ncs',['version'=>1,'side'=>'RIGHT']],
    ['emg_ncs',['version'=>1,'body_site'=>'UPPER_EXTREMITY','side'=>'NOT_APPLICABLE']],
    ['spirometry',['version'=>1,'spirometry_protocol'=>'WITH_BRAND']],
    ['esophageal_manometry',['version'=>1,'gi_technique'=>'PH_ONLY']],
    ['esophageal_ph_monitoring',['version'=>1,'gi_technique'=>'PH_IMPEDANCE','duration'=>'48_HOURS','acid_suppression'=>'ON_THERAPY']],
    ['esophageal_ph_monitoring',['version'=>1,'gi_technique'=>'PH_ONLY','duration'=>'24_HOURS']],
    ['cbc',['version'=>1,'duration'=>'24_HOURS']],
] as $i=>$case) reject(static fn()=>clinical_functional_validate($case[0],$case[1]),'REJECT_'.$i);
foreach (['esophageal_manometry','esophageal_ph_monitoring'] as $key) reject(static fn()=>clinical_functional_validate($key,null),'FUTURE_REQUIRED_'.$key);
check(clinical_functional_validate('esophageal_manometry',null,false)===null,'FUTURE_RESULT_TAXONOMY_CAN_OMIT');

$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE clinical_study_types (study_type_id INTEGER PRIMARY KEY, study_type_key TEXT, display_name_es TEXT, category_key TEXT, is_active INTEGER)');
$insert=$pdo->prepare('INSERT INTO clinical_study_types(study_type_key,display_name_es,category_key,is_active) VALUES (?,?,?,1)');
foreach ($current as $key=>$category) $insert->execute([$key,$key,$category]);
foreach (['cbc'=>'LABORATORIO','cyto_pap'=>'PATOLOGIA','echo_tte'=>'CARDIOVASCULAR','anoscopy_base'=>'ENDOSCOPIA','dental_panoramic_xray'=>'IMAGEN'] as $key=>$category) $insert->execute([$key,$key,$category]);
$input=['study_type_key'=>'holter','functional_order_parameters'=>['version'=>1,'duration'=>'48_HOURS']];
$item=clinical_study_order_snapshot($pdo,$input,1);
check($item['functional_order_parameters_label']==='Duración: 48 horas','SNAPSHOT_SPANISH_LABEL');
$order=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[$input]]);
check($order['order_items'][0]['functional_order_parameters']===$item['functional_order_parameters'],'WRITE_NORMALIZATION');
$same=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>$order['order_items']],$order);
check($same['order_items'][0]['order_item_id']===$order['order_items'][0]['order_item_id'],'SUCCESSOR_PRESERVES_ITEM');
$tampered=$order['order_items'][0];$tampered['functional_order_parameters']['duration']='72_HOURS';
reject(static fn()=>clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[$tampered]],$order),'SUCCESSOR_REJECTS_REINTERPRETATION');
$tampered=$order['order_items'][0];$tampered['functional_order_parameters_label']='Duración: 72 horas';
reject(static fn()=>clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[$tampered]],$order),'SUCCESSOR_REJECTS_LABEL_TAMPER');
foreach (['cbc'=>'LAB','cyto_pap'=>'PATHOLOGY','echo_tte'=>'IMAGING','anoscopy_base'=>'PROCEDURE','dental_panoramic_xray'=>'DENTAL'] as $key=>$domain) {
    $other=clinical_study_order_snapshot($pdo,['study_type_key'=>$key],1);
    check(!isset($other['functional_order_parameters']),'REGRESSION_'.$domain);
}
foreach ($current as $key=>$category) {
    $legacy=clinical_study_order_snapshot($pdo,['study_type_key'=>$key],1);
    check(!isset($legacy['functional_order_parameters']),'CURRENT_WRITER_OMISSION_'.$key);
}
$search=clinical_study_search_authority();
$full=['study_type_key'=>'full_pft','display_name_es'=>'Pruebas funcionales respiratorias completas','aliases_json'=>'[]'];
$spiro=['study_type_key'=>'spirometry','display_name_es'=>'Espirometría','aliases_json'=>'[]'];
$ranked=clinical_study_search_ranked_rows([$spiro,$full],'PFT',$search);
check($ranked[0]['study_type_key']==='full_pft','PFT_EXACT_ALIAS_WINS_OVER_SPIROMETRY_DISCOVERY');
check(clinical_study_search_ranked_rows([$spiro,$full],'PFP',$search)===[],'PFP_FUTURE_SEARCH_TERM_NOT_YET_ACTIVE');
check(clinical_study_search_ranked_rows([$spiro,$full],'pruebas de función pulmonar',$search)[0]['study_type_key']==='full_pft','FULL_PHRASE_CURRENT_PREFIX_DISCOVERY');
$pdo->exec('CREATE TABLE clinical_documents (id INTEGER PRIMARY KEY, document_uuid TEXT, document_type TEXT, version INTEGER, status TEXT, patient_id TEXT, appointment_id TEXT, encounter_id TEXT, encounter_ref_id TEXT, created_by_user_id TEXT, generated_at TEXT, payload_json TEXT)');
$pdo->exec('CREATE TABLE patients_doctor_links (doctor_id TEXT, patient_id TEXT, status TEXT)');
$pdo->exec('CREATE TABLE clinical_document_revisions (original_document_id INTEGER, new_document_id INTEGER, supersedes_document_id INTEGER)');
$pdo->exec("INSERT INTO patients_doctor_links VALUES ('doctor-qa','patient-qa','active')");
$uuid='123e4567-e89b-42d3-a456-426614174000';
$readPayload=['order_payload_version'=>2,'order_items'=>[$item],
    'portable_order_snapshot_version'=>1,'portable_order_snapshot'=>[
        'patient'=>['name'=>'Paciente QA'],'physician'=>['name'=>'Médico QA']]];
$insertOrder=$pdo->prepare('INSERT INTO clinical_documents VALUES (1,?,\'orders\',1,\'generated\',\'patient-qa\',NULL,NULL,NULL,\'user-qa\',\'2026-10-05 12:00:00\',?)');
$insertOrder->execute([$uuid,json_encode($readPayload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
$read=clinical_portable_order_read($pdo,$uuid,'doctor-qa','user-qa');
check($read['studies'][0]['functional_context']==='Duración: 48 horas','PORTABLE_READER_STORED_FUNCTIONAL_LABEL');
$bad=$readPayload;$bad['order_items'][0]['functional_order_parameters_label']='';
$pdo->prepare('UPDATE clinical_documents SET payload_json=? WHERE id=1')->execute([json_encode($bad,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
reject(static fn()=>clinical_portable_order_read($pdo,$uuid,'doctor-qa','user-qa'),'PORTABLE_READER_REJECTS_INCOMPLETE_FUNCTIONAL_LABEL');
$portable=['studies'=>[['name'=>'Holter','functional_context'=>$item['functional_order_parameters_label'],'note'=>null]],'patient'=>['name'=>'Paciente QA'],'physician'=>['name'=>'Médico QA'],'issued_at'=>'2026-10-05 12:00:00','display_reference'=>'QA'];
define('MXMED_PORTABLE_ORDER_TEMPLATE_ALLOWED',true);
$order=$portable;$error='';ob_start();include __DIR__.'/../ui/portable-order-template.php';$html=ob_get_clean();
check(str_contains($html,'Duración: 48 horas'),'PORTABLE_LABEL');
check(!str_contains($html,'48_HOURS'),'PORTABLE_HIDES_ENUM');
echo 'FUNC_CAT02A_GATE_PASS',PHP_EOL;
ob_end_flush();
