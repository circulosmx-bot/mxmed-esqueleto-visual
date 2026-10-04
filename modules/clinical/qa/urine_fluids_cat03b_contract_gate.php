<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';

$database=getenv('LAB_CAT02A_QA_DB');
if (!is_string($database) || preg_match('/^ordcomp01_qa_[a-f0-9]{10}$/D',$database)!==1) throw new RuntimeException('Disposable DB required');
$pdo=new PDO('mysql:host=localhost;dbname='.$database,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$check=static function(bool $pass,string $name):void {if(!$pass)throw new RuntimeException($name);echo $name."=PASS\n";};
$reject=static function(callable $action,string $code) use($check):void {try{$action();}catch(InvalidArgumentException $e){$check($e->getMessage()===$code,'QA_REJECT_'.$code);return;}throw new RuntimeException('Expected '.$code);};
$config=clinical_specimen_authority();
$urine=array_merge(...array_map(static fn($g)=>$g['keys'],json_decode(file_get_contents(__DIR__.'/../catalog/study_order_routing_v1.json'),true)['catalog']['urine']['groups']));
$check(count($urine)===16 && count($config['studies'])===16 && array_diff($urine,array_keys($config['studies']))===[],'QA_ALL_ACTIVE_FLUID_STUDIES_CONFIGURED');
foreach($urine as $key){
    $snapshot=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[['study_type_key'=>$key]]]);
    $item=$snapshot['order_items'][0];
    $check(($item['specimen_collection_requirements']['version']??null)===1 && is_string($item['specimen_collection_requirements']['specimen_type_key']??null),'QA_FIXED_'.strtoupper($key));
    $same=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[$item]],$snapshot);
    $check($same['order_items'][0]['order_item_id']===$item['order_item_id'],'QA_IMMUTABLE_'.strtoupper($key));
    $changed=$item;$changed['specimen_collection_requirements']['specimen_type_key']='SERUM';
    $reject(static fn()=>clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[$changed]],$snapshot),'ORDER_ITEM_ID_MEANING_CHANGED');
}
$spot=clinical_specimen_validate('urine_creatinine_spot',null);
$check($spot['collection_mode']==='SPOT' && !isset($spot['requested_duration_minutes']),'QA_FIXED_SPOT_NO_PROMPT');
$reject(static fn()=>clinical_specimen_validate('synovial_crystals',['version'=>1,'source_site_key'=>'JOINT']),'SPECIMEN_SOURCE_SITE_TEXT_REQUIRED');
$joint=clinical_specimen_validate('synovial_crystals',['version'=>1,'source_site_key'=>'JOINT','source_site_text'=>'Rodilla derecha']);
$check($joint['source_site_text']==='Rodilla derecha','QA_OPTIONAL_JOINT_SITE');
$reject(static fn()=>clinical_specimen_validate('urine_creatinine_spot',['version'=>1,'collection_mode'=>'TIMED','requested_duration_minutes'=>1440]),'SPECIMEN_COLLECTION_FIXED_MISMATCH');
$reject(static fn()=>clinical_specimen_validate('csf_glucose',['version'=>1,'actual_collected_volume_ml'=>10]),'SPECIMEN_REQUIREMENTS_FIELD_INVALID');
$reject(static fn()=>clinical_specimen_validate('csf_glucose',['version'=>1,'container'=>'tube']), 'SPECIMEN_REQUIREMENTS_FIELD_INVALID');
$timed=['specimen_mode'=>'FIXED','fixed_specimen_type_key'=>'URINE','collection_mode'=>'REQUIRED_SELECTION','allowed_collection_modes'=>['SPOT','TIMED'],'allowed_duration_minutes'=>[120,720,1440]];
$reject(static fn()=>clinical_specimen_validate('qa_synthetic_timed',null,$timed),'SPECIMEN_COLLECTION_MODE_REQUIRED');
$reject(static fn()=>clinical_specimen_validate('qa_synthetic_timed',['version'=>1,'collection_mode'=>'TIMED'],$timed),'SPECIMEN_DURATION_INVALID');
$complete=clinical_specimen_validate('qa_synthetic_timed',['version'=>1,'collection_mode'=>'TIMED','requested_duration_minutes'=>1440],$timed);
$check($complete['specimen_type_key']==='URINE' && $complete['requested_duration_minutes']===1440 && str_contains(clinical_specimen_print_context($complete,'Calcio urinario'),'24 horas'),'QA_SYNTHETIC_24H_SNAPSHOT_AND_PRINT');
$paired=['specimen_mode'=>'FIXED','fixed_specimen_type_key'=>'CSF','paired_specimen'=>['required'=>true,'counterpart_specimen_type_key'=>'SERUM','relationship_key'=>'SAME_COLLECTION_EPISODE']];
$pair=clinical_specimen_validate('qa_synthetic_paired',null,$paired);
$check($pair['paired_specimen']['counterpart_specimen_type_key']==='SERUM' && str_contains(clinical_specimen_print_context($pair,'Glucosa en LCR'),'Suero'),'QA_SYNTHETIC_PAIRED');
$legacy=clinical_study_normalize_order_payload($pdo,'lab_order',['requested_studies'=>['Prueba histórica']]);
$check(!isset($legacy['order_items'][0]['specimen_collection_requirements']),'QA_LEGACY_ORDER_NO_SPECIMEN');
$groups=json_decode(file_get_contents(__DIR__.'/../catalog/study_order_routing_v1.json'),true)['catalog']['urine']['groups'];
$check(array_search('Semen',array_column($groups,'label'),true)===array_search('Microbiología urinaria',array_column($groups,'label'),true)+1,'QA_SEMEN_GROUP_ORDER');
