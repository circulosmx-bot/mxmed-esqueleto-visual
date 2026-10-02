<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../api/_lib/clinical_study_contract.php';

$database = getenv('DENTAL_CAT02_QA_DB');
if (!is_string($database) || preg_match('/^dental_cat02_qa_[a-f0-9]{12}$/D', $database) !== 1) throw new RuntimeException('Disposable DB required');
$pdo = new PDO('mysql:host=localhost;dbname='.$database, 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$check = static function (bool $ok,string $name): void {if (!$ok) throw new RuntimeException($name);echo $name."=PASS\n";};
$reject = static function (callable $action,string $error) use ($check): void {
    try {$action();} catch (InvalidArgumentException $e) {$check($e->getMessage()===$error,'QA_REJECT_'.$error);return;}
    throw new RuntimeException('Expected '.$error);
};
$rows=$pdo->query("SELECT study_type_key,category_key,aliases_json FROM clinical_study_types WHERE study_type_key LIKE 'dental_%' OR study_type_key='tmj_comparative_xray'")->fetchAll(PDO::FETCH_ASSOC);
$byKey=array_column($rows,null,'study_type_key');
$check(count($rows)===7 && count($byKey)===7 && (int)$pdo->query('SELECT COUNT(*) FROM clinical_study_types')->fetchColumn()===190,'QA_CATALOG_SEVEN_IDEMPOTENT');
$check(count(array_filter($rows,static fn(array $r):bool=>$r['category_key']==='IMAGEN'))===4
    && count(array_filter($rows,static fn(array $r):bool=>$r['category_key']==='DENTAL'))===3,'QA_DENTAL_CATEGORY_OWNERSHIP');
$check(str_contains($byKey['dental_panoramic_xray']['aliases_json'],'Ortopantomografía')
    && !isset($byKey['dental_carpal_xray']) && !isset($byKey['craniofacial_projection_xray']),'QA_ALIASES_AND_DEFERRED');
$teeth=clinical_dental_teeth();
$permanent=array_keys(array_filter($teeth,static fn(array $t):bool=>$t['dentition']==='PERMANENT'));
$primary=array_keys(array_filter($teeth,static fn(array $t):bool=>$t['dentition']==='DECIDUOUS'));
$check(count($permanent)===32 && count($primary)===20 && count(array_unique(array_keys($teeth)))===52,'QA_FDI_EXACT_ALLOWLIST');
foreach ([11=>'incisivo central superior derecho',16=>'primer molar superior derecho',21=>'incisivo central superior izquierdo',
    26=>'primer molar superior izquierdo',31=>'incisivo central inferior izquierdo',36=>'primer molar inferior izquierdo',
    41=>'incisivo central inferior derecho',46=>'primer molar inferior derecho',51=>'incisivo central superior derecho',
    55=>'segundo molar superior derecho',61=>'incisivo central superior izquierdo',65=>'segundo molar superior izquierdo',
    71=>'incisivo central inferior izquierdo',75=>'segundo molar inferior izquierdo',81=>'incisivo central inferior derecho',
    85=>'segundo molar inferior derecho'] as $code=>$name) {
    $check($teeth[(string)$code]['name_es']===$name,'QA_FDI_NAME_'.$code);
}
$base=['contract_version'=>1,'numbering_system'=>'FDI_ISO_3950'];
$local=static fn(array $codes,string $mode='PERMANENT'):array=>$base+['dentition_mode'=>$mode,'coverage'=>'LOCALIZED','selected_teeth'=>$codes];
foreach ($permanent as $code) clinical_dental_validate_location('dental_cbct',$local([(string)$code]));
foreach ($primary as $code) clinical_dental_validate_location('dental_cbct',$local([(string)$code],'DECIDUOUS'));
$check(true,'QA_FDI_ALL_PERMANENT_AND_DECIDUOUS');
$mixed=clinical_dental_validate_location('dental_cbct',$local(['16','55'],'MIXED'));
$check($mixed['selected_teeth']===['16','55'] && $mixed['arch']==='MAXILLARY','QA_FDI_MIXED');
foreach (['19','49','56','60','86','00','1','abc'] as $bad) $reject(fn()=>clinical_dental_validate_location('dental_cbct',$local([$bad])),'DENTAL_TOOTH_CODE_INVALID');
$reject(fn()=>clinical_dental_validate_location('dental_cbct',$local(['16','16'])),'DENTAL_TOOTH_DUPLICATE');
$reject(fn()=>clinical_dental_validate_location('dental_cbct',$local(['55'])),'DENTAL_DENTITION_TOOTH_CONFLICT');
$reject(fn()=>clinical_dental_validate_location('dental_cbct',$local([])),'DENTAL_LOCALIZED_TARGET_REQUIRED');
$reject(fn()=>clinical_dental_validate_location('dental_cbct',$base+['coverage'=>'MAXILLOFACIAL','selected_teeth'=>['16'],'dentition_mode'=>'PERMANENT']),'DENTAL_BROAD_TOOTH_CONFLICT');
$reject(fn()=>clinical_dental_validate_location('dental_cbct',$local(['16'])+['fov_cm'=>'100x100']),'DENTAL_FOV_INVALID');
$reject(fn()=>clinical_dental_validate_location('dental_cbct',$local(['16'])+['viewer'=>'Ez3D']),'DENTAL_LOCATION_FIELD_INVALID');
$reject(fn()=>clinical_dental_validate_location('dental_panoramic_xray',$local(['16'])),'DENTAL_LOCATION_STUDY_INCOMPATIBLE');
$reject(fn()=>clinical_dental_validate_location('glucose',$local(['16'])),'DENTAL_LOCATION_STUDY_INCOMPATIBLE');
foreach (['MAXILLARY_ARCH','MANDIBULAR_ARCH','BOTH_ARCHES','MAXILLOFACIAL'] as $coverage) {
    $snapshot=clinical_dental_validate_location('dental_cbct',$base+['coverage'=>$coverage,'selected_teeth'=>[]]);
    $check($snapshot['coverage']===$coverage && !isset($snapshot['anatomical_region']),'QA_CBCT_'.$coverage);
}
$check(clinical_dental_validate_location('dental_cbct',$local([])+['anatomical_region'=>'Seno maxilar derecho'])['anatomical_region']==='Seno maxilar derecho','QA_LOCALIZED_REGION');
$check(clinical_dental_validate_location('tmj_comparative_xray',$base+['projection'=>'PA'])['projection']==='PA','QA_TMJ_VIEW');
$check(clinical_dental_validate_location('dental_intraoral_scan',$base+['arch'=>'BOTH'])['arch']==='BOTH','QA_SCAN_ARCH');
$check(clinical_dental_validate_location('dental_clinical_photographs',$base+['photograph_scope'=>'BOTH'])['photograph_scope']==='BOTH','QA_PHOTO_SCOPE');
$check(clinical_dental_validate_location('dental_study_model',null)===null,'QA_MODEL_OPTIONAL_ARCH');
$reject(fn()=>clinical_dental_validate_location('dental_intraoral_scan',$base+['projection'=>'PA']),'DENTAL_ARCH_REQUIRED');
$reject(fn()=>clinical_dental_validate_location('tmj_comparative_xray',$base+['arch'=>'BOTH']),'DENTAL_PROJECTION_INVALID');
$items=[['study_type_key'=>'dental_cbct','dental_location'=>$local(['16','17'])],['study_type_key'=>'glucose']];
$order=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>$items]);
$check(count($order['order_items'])===2 && $order['order_items'][0]['dental_location']['selected_teeth']===['16','17']
    && str_contains($order['order_items'][0]['dental_location_label'],'Piezas 16, 17')
    && !isset($order['order_items'][1]['dental_location']),'QA_MIXED_ORDER_EXACT_SNAPSHOT');
$same=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>$order['order_items']],$order);
$check($same['order_items'][0]['order_item_id']===$order['order_items'][0]['order_item_id']
    && $same['order_items'][0]['dental_location']===$order['order_items'][0]['dental_location'],'QA_REPLACEMENT_PRESERVES_LOCATION');
$changed=$order['order_items'];$changed[0]['dental_location']['selected_teeth']=['16'];
$reject(fn()=>clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>$changed],$order),'ORDER_ITEM_ID_MEANING_CHANGED');
$check(clinical_study_normalize_order_payload($pdo,'lab_order',['requested_studies'=>['Glucosa']])['order_items'][0]['study_type_id']===null,'QA_LEGACY_ORDER_UNCHANGED');
