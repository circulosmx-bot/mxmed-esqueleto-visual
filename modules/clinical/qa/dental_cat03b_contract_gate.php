<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';

$pass = static function(bool $ok,string $name):void {if(!$ok)throw new RuntimeException($name);echo $name."=PASS\n";};
$reject = static function(callable $run,string $name)use($pass):void {
    try {$run();}catch(InvalidArgumentException){$pass(true,$name);return;}
    throw new RuntimeException($name);
};
$pass(clinical_dental_v2_authority()['contract_version']===2,'QA_LOCATION_V2');
$pass(clinical_dental_study_policy_authority()['contract_version']===1,'QA_STUDY_POLICY_V1');
$pass(clinical_dental_acquisition_authority()['contract_version']===1,'QA_ACQUISITION_V1');
$tooth=static fn(array $codes,string $dentition='PERMANENT'):array=>[
    'contract_version'=>2,'location_type'=>'TOOTH_LOCATION','selection_mode'=>count($codes)===1?'SINGLE_TOOTH':'MULTIPLE_TEETH',
    'numbering_system'=>'FDI_ISO_3950','dentition_mode'=>$dentition,'tooth_fdi_codes'=>$codes];
$valid=static fn(string $key,array $value):array=>clinical_dental_validate_location($key,$value);
$pass($valid('dental_periapical_xray',$tooth(['16']))['tooth_fdi_codes']===['16'],'QA_PERIAPICAL_ONE');
$eight=['11','12','13','14','15','16','17','18'];
$pass(count($valid('dental_periapical_xray',$tooth($eight))['tooth_fdi_codes'])===8,'QA_PERIAPICAL_EIGHT');
$pass(count($valid('dental_periapical_xray',$tooth(['16','54'],'MIXED'))['tooth_fdi_codes'])===2,'QA_PERIAPICAL_MIXED');
$pass($valid('dental_periapical_xray',$tooth(['54'],'PRIMARY'))['dentition_mode']==='PRIMARY','QA_PERIAPICAL_PRIMARY');
$reject(fn()=> $valid('dental_periapical_xray',$tooth([])),'QA_PERIAPICAL_REJECT_ZERO');
$reject(fn()=> $valid('dental_periapical_xray',$tooth([...$eight,'21'])),'QA_PERIAPICAL_REJECT_NINE');
$reject(fn()=> $valid('dental_periapical_xray',$tooth(['54'])),'QA_PERIAPICAL_REJECT_DENTITION');
$reject(fn()=> $valid('dental_periapical_xray',['contract_version'=>2,'location_type'=>'REGION_LOCATION','selection_mode'=>'REGION','region_key'=>'POSTERIOR','arch_key'=>'BOTH_ARCHES','side_key'=>'RIGHT']),'QA_PERIAPICAL_REJECT_REGION');
$reject(fn()=>clinical_dental_validate_location('dental_periapical_xray',null),'QA_PERIAPICAL_REJECT_NULL');
$reject(fn()=>clinical_dental_validate_location('dental_periapical_xray',['contract_version'=>1]),'QA_PERIAPICAL_REJECT_V1');
$bite=static fn(string $side,string $mode='REGION',string $region='POSTERIOR',string $arch='BOTH_ARCHES'):array=>[
    'contract_version'=>2,'location_type'=>'REGION_LOCATION','selection_mode'=>$mode,'dentition_mode'=>'MIXED',
    'region_key'=>$region,'arch_key'=>$arch,'side_key'=>$side];
foreach(['LEFT','RIGHT'] as $side)$pass($valid('dental_bitewing_xray',$bite($side))['side_key']===$side,'QA_BITEWING_'.$side);
$pass($valid('dental_bitewing_xray',$bite('BILATERAL','BILATERAL_REGION'))['side_key']==='BILATERAL','QA_BITEWING_BILATERAL');
$pass($valid('dental_bitewing_xray',array_replace($bite('LEFT'),['dentition_mode'=>'PRIMARY']))['dentition_mode']==='PRIMARY','QA_BITEWING_PRIMARY');
$pass($valid('dental_bitewing_xray',array_replace($bite('LEFT'),['dentition_mode'=>'PERMANENT']))['dentition_mode']==='PERMANENT','QA_BITEWING_PERMANENT');
$reject(fn()=> $valid('dental_bitewing_xray',$bite('RIGHT','REGION','ANTERIOR')),'QA_BITEWING_REJECT_ANTERIOR');
$reject(fn()=> $valid('dental_bitewing_xray',$bite('RIGHT','REGION','POSTERIOR','MAXILLARY')),'QA_BITEWING_REJECT_ARCH');
$reject(fn()=> $valid('dental_bitewing_xray',array_diff_key($bite('RIGHT'),['side_key'=>true])),'QA_BITEWING_REJECT_NO_SIDE');
$reject(fn()=> $valid('dental_bitewing_xray',$tooth(['16'])),'QA_BITEWING_REJECT_TOOTH');
$reject(fn()=> $valid('dental_bitewing_xray',$bite('RIGHT')+['debug'=>true]),'QA_BITEWING_REJECT_UNKNOWN_FIELD');
$occlusal=static fn(string $arch):array=>['contract_version'=>2,'location_type'=>'ARCH_LOCATION','selection_mode'=>'ARCH','dentition_mode'=>'PRIMARY','arch_key'=>$arch];
foreach(['MAXILLARY','MANDIBULAR'] as $arch)$pass($valid('dental_occlusal_xray',$occlusal($arch))['arch_key']===$arch,'QA_OCCLUSAL_'.$arch);
$pass($valid('dental_occlusal_xray',array_replace($occlusal('MAXILLARY'),['dentition_mode'=>'PERMANENT']))['dentition_mode']==='PERMANENT','QA_OCCLUSAL_PERMANENT');
$pass($valid('dental_occlusal_xray',array_replace($occlusal('MANDIBULAR'),['dentition_mode'=>'MIXED']))['dentition_mode']==='MIXED','QA_OCCLUSAL_MIXED');
$reject(fn()=> $valid('dental_occlusal_xray',$occlusal('BOTH_ARCHES')),'QA_OCCLUSAL_REJECT_BOTH');
$reject(fn()=> $valid('dental_occlusal_xray',array_diff_key($occlusal('MAXILLARY'),['arch_key'=>true])),'QA_OCCLUSAL_REJECT_NO_ARCH');
$reject(fn()=> $valid('dental_occlusal_xray',array_diff_key($occlusal('MAXILLARY'),['dentition_mode'=>true])),'QA_OCCLUSAL_REJECT_NO_DENTITION');
$protocol=static fn(string $key,string $dentition='PERMANENT'):array=>['contract_version'=>1,'protocol_key'=>$key,'dentition_mode'=>$dentition];
foreach([14,16,18] as $count){$snapshot=clinical_dental_acquisition_validate('dental_full_periapical_series',$protocol('FULL_MOUTH_'.$count));
    $pass($snapshot['nominal_image_count']===$count&&$snapshot['scope']==='FULL_MOUTH','QA_SERIES_'.$count);}
foreach([13,15,17] as $count)$reject(fn()=>clinical_dental_acquisition_validate('dental_full_periapical_series',$protocol('FULL_MOUTH_'.$count)),'QA_SERIES_REJECT_'.$count);
$reject(fn()=>clinical_dental_acquisition_validate('dental_full_periapical_series',null),'QA_SERIES_REJECT_MISSING');
$reject(fn()=>clinical_dental_acquisition_validate('dental_full_periapical_series',$protocol('FULL_MOUTH_16','PRIMARY')),'QA_SERIES_REJECT_PRIMARY');
$reject(fn()=>clinical_dental_acquisition_validate('dental_full_periapical_series',$protocol('FULL_MOUTH_16','MIXED')),'QA_SERIES_REJECT_MIXED');
$reject(fn()=>clinical_dental_acquisition_validate('dental_full_periapical_series',['contract_version'=>2,'protocol_key'=>'FULL_MOUTH_14','dentition_mode'=>'PERMANENT']),'QA_SERIES_REJECT_VERSION');
$reject(fn()=>clinical_dental_acquisition_validate('dental_full_periapical_series',$protocol('FULL_MOUTH_14')+['debug'=>1]),'QA_SERIES_REJECT_FIELD');
$reject(fn()=>clinical_dental_acquisition_validate('dental_cbct',$protocol('FULL_MOUTH_14')),'QA_SERIES_REJECT_OTHER_STUDY');
$pass(clinical_dental_validate_location('dental_full_periapical_series',null)===null,'QA_SERIES_NO_LOCATION');
$tmj=static fn(string $side):array=>['contract_version'=>2,'location_type'=>'TMJ_LOCATION','selection_mode'=>'TMJ_REGION','tmj_side'=>$side,'coverage'=>'TMJ'];
foreach(['LEFT','RIGHT','BILATERAL'] as $side)$pass($valid('dental_cbct',$tmj($side))['tmj_side']===$side,'QA_CBCT_TMJ_'.$side);
$reject(fn()=> $valid('dental_cbct',array_diff_key($tmj('LEFT'),['tmj_side'=>true])),'QA_CBCT_TMJ_REJECT_NO_SIDE');
$reject(fn()=> $valid('dental_cbct',array_replace($tmj('LEFT'),['tmj_side'=>'MIDLINE'])),'QA_CBCT_TMJ_REJECT_INVALID_SIDE');
$reject(fn()=> $valid('dental_cbct',$tmj('LEFT')+['tooth_fdi_codes'=>['16']]),'QA_CBCT_TMJ_REJECT_TOOTH');
$reject(fn()=> $valid('dental_cbct',array_replace($tmj('LEFT'),['coverage'=>'LOCALIZED'])),'QA_CBCT_TMJ_REJECT_COVERAGE');
$pass($valid('dental_cbct',$tooth(['16'])+['coverage'=>'LOCALIZED'])['coverage']==='LOCALIZED','QA_CBCT_EXISTING_TOOTH');
$pass($valid('dental_cbct',['contract_version'=>2,'location_type'=>'QUADRANT_LOCATION','selection_mode'=>'QUADRANT','dentition_mode'=>'PERMANENT','quadrant_key'=>'UPPER_RIGHT','coverage'=>'LOCALIZED'])['quadrant_key']==='UPPER_RIGHT','QA_CBCT_EXISTING_QUADRANT');
$pass($valid('dental_cbct',['contract_version'=>2,'location_type'=>'ARCH_LOCATION','selection_mode'=>'ARCH','arch_key'=>'MAXILLARY','coverage'=>'MAXILLARY_ARCH'])['arch_key']==='MAXILLARY','QA_CBCT_EXISTING_ARCH');
$pass($valid('dental_cbct',['contract_version'=>2,'location_type'=>'REGION_LOCATION','selection_mode'=>'REGION','region_key'=>'POSTERIOR','arch_key'=>'MAXILLARY','side_key'=>'RIGHT','coverage'=>'LOCALIZED'])['region_key']==='POSTERIOR','QA_CBCT_EXISTING_REGION');
$pass(str_contains((string)clinical_dental_location_summary($tmj('BILATERAL')),'Región: ATM bilateral'),'QA_CBCT_TMJ_PRINT_LABEL');

// In-memory catalog rows exercise the real order snapshot writer without touching the review DB.
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE clinical_study_types(study_type_id INTEGER PRIMARY KEY,study_type_key TEXT,display_name_es TEXT,category_key TEXT,is_active INTEGER)');
$insert=$pdo->prepare('INSERT INTO clinical_study_types VALUES(?,?,?,?,1)');
foreach(['dental_periapical_xray','dental_bitewing_xray','dental_occlusal_xray','dental_full_periapical_series','dental_cbct'] as $i=>$key)$insert->execute([$i+1,$key,$key,'IMAGEN']);
$item=clinical_study_order_snapshot($pdo,['study_type_key'=>'dental_periapical_xray','dental_location'=>$tooth(['16','17']),'dental_study_policy_version'=>1],1);
$pass($item['dental_location_authority_version']===2&&$item['dental_study_policy_version']===1&&$item['dental_location']['tooth_fdi_codes']===['16','17'],'QA_LOCATION_POLICY_SNAPSHOT');
$pass($item['dental_location_label']==='Piezas 16, 17','QA_LOCATION_LABEL_SNAPSHOT');
$reject(fn()=>clinical_study_order_snapshot($pdo,['study_type_key'=>'dental_periapical_xray','dental_location'=>$tooth(['16']),'dental_study_policy_version'=>2],1),'QA_REJECT_POLICY_VERSION');
$series=clinical_study_order_snapshot($pdo,['study_type_key'=>'dental_full_periapical_series','dental_acquisition_protocol'=>$protocol('FULL_MOUTH_18')],1);
$pass($series['dental_study_policy_version']===1&&$series['dental_location_authority_version']===2&&$series['dental_acquisition_protocol']['nominal_image_count']===18,'QA_SERIES_SNAPSHOT');
$pass($series['dental_acquisition_protocol_label']==='Protocolo: Serie de 18 imágenes intraorales','QA_SERIES_LABEL_SNAPSHOT');
$pass($series['dental_acquisition_protocol']['provider_confirmation_required']===true
    && $series['dental_acquisition_protocol']['nominal_count_semantics']==='PLANNED_ACQUISITION_NOT_FINAL_USABLE_IMAGE_COUNT','QA_SERIES_SEMANTICS_SNAPSHOT');
$series['order_item_id']=clinical_study_uuid();
$source=['order_payload_version'=>2,'order_items'=>[$series]];
$successor=clinical_study_normalize_order_payload($pdo,'imaging_order',['order_items'=>[$series]],$source);
$pass($successor['order_items'][0]['dental_acquisition_protocol']===$series['dental_acquisition_protocol'],'QA_SERIES_SUCCESSOR_IMMUTABLE');
$altered=$series;$altered['dental_acquisition_protocol']['protocol_key']='FULL_MOUTH_14';
$reject(fn()=>clinical_study_normalize_order_payload($pdo,'imaging_order',['order_items'=>[$altered]],$source),'QA_SERIES_SUCCESSOR_REJECTS_REINTERPRETATION');
$cbct=clinical_study_order_snapshot($pdo,['study_type_key'=>'dental_cbct','dental_location'=>$tmj('BILATERAL')],1);
$pass($cbct['dental_location']['coverage']==='TMJ'&&$cbct['dental_study_policy_version']===1,'QA_CBCT_TMJ_SNAPSHOT');
$old=clinical_study_order_snapshot($pdo,['study_type_key'=>'dental_cbct','dental_location'=>['contract_version'=>1,'numbering_system'=>'FDI_ISO_3950','dentition_mode'=>'PERMANENT','coverage'=>'LOCALIZED','selected_teeth'=>['16']]],1);
$pass(!isset($old['dental_location_authority_version'])&&$old['dental_location']['contract_version']===1,'QA_CBCT_V1_COMPATIBILITY');
$pass(clinical_dental_validate_location('dental_panoramic_xray',null)===null&&clinical_dental_validate_location('dental_cephalometric_xray',null)===null&&clinical_dental_validate_location('dental_study_model',null)===null,'QA_EXISTING_DENTAL_NULL_POLICIES');
$pass(clinical_dental_validate_location('tmj_comparative_xray',['contract_version'=>2,'location_type'=>'TMJ_LOCATION','selection_mode'=>'TMJ_REGION','tmj_side'=>'RIGHT','projection'=>'PA'])['projection']==='PA','QA_EXISTING_TMJ');
