<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../api/_lib/clinical_dental_location.php';

$check = static function (bool $ok, string $name): void {
    if (!$ok) throw new RuntimeException($name);
    echo $name."=PASS\n";
};
$reject = static function (callable $action, string $name) use ($check): void {
    try {$action();} catch (InvalidArgumentException) {$check(true, $name);return;}
    throw new RuntimeException($name);
};
$teeth=clinical_dental_teeth();
$check(count(array_filter($teeth,static fn(array $t):bool=>$t['dentition']==='PERMANENT'))===32,'QA_PERMANENT_32');
$check(count(array_filter($teeth,static fn(array $t):bool=>$t['dentition']==='DECIDUOUS'))===20,'QA_PRIMARY_20');
$base=['contract_version'=>2,'location_type'=>'TOOTH_LOCATION','selection_mode'=>'MULTIPLE_TEETH','numbering_system'=>'FDI_ISO_3950'];
$tooth=static fn(array $codes,string $dentition='PERMANENT'):array=>$base+['dentition_mode'=>$dentition,'tooth_fdi_codes'=>$codes,'coverage'=>'LOCALIZED'];
$validate=static fn(array $value):?array=>clinical_dental_validate_location('dental_cbct',$value);
$check($validate($tooth(['17','16']))['tooth_fdi_codes']===['16','17'],'QA_SORTED_UNIQUE_FDI');
$check($validate($tooth(['16','54'],'MIXED'))['dentition_mode']==='MIXED','QA_MIXED_FDI');
$check($validate($tooth(['54'],'PRIMARY'))['tooth_fdi_codes']===['54'],'QA_PRIMARY_FDI');
$check($validate(array_replace($tooth(['16']),['selection_mode'=>'SINGLE_TOOTH']))['tooth_fdi_codes']===['16'],'QA_SINGLE_PERMANENT_16');
$check($validate($tooth(['18','16','17']))['tooth_fdi_codes']===['16','17','18'],'QA_MULTIPLE_PERMANENT_16_17_18');
$check($validate(array_replace($tooth(['54'],'PRIMARY'),['selection_mode'=>'SINGLE_TOOTH']))['tooth_fdi_codes']===['54'],'QA_SINGLE_PRIMARY_54');
$check($validate($tooth(['55','54'],'PRIMARY'))['tooth_fdi_codes']===['54','55'],'QA_MULTIPLE_PRIMARY_54_55');
$reject(fn()=> $validate($tooth(['54'])),'QA_REJECT_PRIMARY_IN_PERMANENT');
$reject(fn()=> $validate($tooth(['16'],'PRIMARY')),'QA_REJECT_PERMANENT_IN_PRIMARY');
$reject(fn()=> $validate($tooth(['19'])),'QA_REJECT_UNKNOWN_FDI');
$reject(fn()=> $validate($tooth([])),'QA_REJECT_ZERO_TEETH');
$reject(fn()=> $validate($tooth(['16','16'])),'QA_REJECT_DUPLICATE_FDI');
$reject(fn()=> $validate($tooth(array_fill(0,53,'16'),'MIXED')),'QA_REJECT_TOO_MANY_TEETH');
$reject(fn()=> $validate($tooth(['16'])+['debug'=>true]),'QA_REJECT_UNKNOWN_FIELD');
$reject(fn()=> $validate(array_replace($tooth(['16']),['contract_version'=>3])),'QA_REJECT_VERSION');
$quadrant=['contract_version'=>2,'location_type'=>'QUADRANT_LOCATION','selection_mode'=>'QUADRANT','dentition_mode'=>'MIXED','quadrant_key'=>'UPPER_RIGHT','coverage'=>'LOCALIZED'];
$check($validate($quadrant)['quadrant_key']==='UPPER_RIGHT','QA_QUADRANT');
$arch=['contract_version'=>2,'location_type'=>'ARCH_LOCATION','selection_mode'=>'ARCH','arch_key'=>'BOTH_ARCHES','coverage'=>'BOTH_ARCHES'];
$check($validate($arch)['arch_key']==='BOTH_ARCHES','QA_ARCH');
$region=['contract_version'=>2,'location_type'=>'REGION_LOCATION','selection_mode'=>'REGION','region_key'=>'POSTERIOR','arch_key'=>'MAXILLARY','side_key'=>'RIGHT','coverage'=>'LOCALIZED'];
$check($validate($region)['region_key']==='POSTERIOR','QA_REGION');
$bilateral=array_replace($region,['selection_mode'=>'BILATERAL_REGION','side_key'=>'BILATERAL']);
$check($validate($bilateral)['side_key']==='BILATERAL','QA_BILATERAL_REGION');
$reject(fn()=>clinical_dental_validate_location('dental_intraoral_scan',$quadrant),'QA_REJECT_QUADRANT_FOR_SCAN');
$reject(fn()=>clinical_dental_validate_location('tmj_comparative_xray',$arch),'QA_REJECT_ARCH_FOR_TMJ');
$reject(fn()=>clinical_dental_validate_location('tmj_comparative_xray',$tooth(['16'])),'QA_REJECT_TOOTH_FOR_TMJ');
$tmj=['contract_version'=>2,'location_type'=>'TMJ_LOCATION','selection_mode'=>'TMJ_REGION','tmj_side'=>'BILATERAL','projection'=>'PA'];
foreach (['LEFT','RIGHT','BILATERAL'] as $side) $check(clinical_dental_validate_location('tmj_comparative_xray',array_replace($tmj,['tmj_side'=>$side]))['tmj_side']===$side,'QA_TMJ_'.$side);
$check(clinical_dental_validate_location('dental_cbct',array_diff_key($tmj,['projection'=>true])+['coverage'=>'TMJ'])['coverage']==='TMJ','QA_CBCT_TMJ_ADDITIVE');
$check(clinical_dental_validate_location('dental_intraoral_scan',array_diff_key($arch,['coverage'=>true]))['arch_key']==='BOTH_ARCHES','QA_SCAN');
$check(clinical_dental_validate_location('dental_study_model',null)===null,'QA_MODEL_OPTIONAL');
$reject(fn()=>clinical_dental_validate_location('dental_panoramic_xray',$arch),'QA_PANORAMIC_NO_SELECTOR');
$legacy=['contract_version'=>1,'numbering_system'=>'FDI_ISO_3950','dentition_mode'=>'DECIDUOUS','coverage'=>'LOCALIZED','selected_teeth'=>['54']];
$check(clinical_dental_validate_location('dental_cbct',$legacy)['selected_teeth']===['54'],'QA_V1_STILL_READABLE');
$check(str_contains((string)clinical_dental_location_summary($validate($tooth(['16']))),'Piezas 16'),'QA_V2_READ_SUMMARY');
