<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../api/_lib/clinical_study_contract.php';
require_once __DIR__ . '/../../../api/_lib/clinical_order_result_read.php';

$db = getenv('TAX03A_QA_DB');
if (!is_string($db) || !preg_match('/^tax03a_qa_[a-f0-9]{12}$/D', $db)) throw new RuntimeException('Disposable DB required');
$pdo = new PDO('mysql:host=localhost;dbname='.$db, 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE patients_patients(patient_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE patients_doctor_links(doctor_id VARCHAR(64),patient_id VARCHAR(64),status VARCHAR(32)) ENGINE=InnoDB");
$pdo->exec("INSERT INTO patients_patients VALUES('p_tax03a'),('p_other')");
$pdo->exec("INSERT INTO patients_doctor_links VALUES('d_tax03a','p_tax03a','active')");
$pdo->exec("CREATE TABLE clinical_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,document_uuid CHAR(36) NOT NULL UNIQUE,
 document_type VARCHAR(64) NOT NULL,title VARCHAR(128) NOT NULL,summary VARCHAR(512),version INT NOT NULL DEFAULT 1,
 status VARCHAR(20) NOT NULL DEFAULT 'generated',patient_id VARCHAR(128) NOT NULL,
 encounter_id VARCHAR(128),encounter_ref_id BIGINT UNSIGNED,appointment_id VARCHAR(64),hospital_stay_id VARCHAR(128),
 care_setting VARCHAR(32) NOT NULL DEFAULT 'consulta',service VARCHAR(128),payload_json JSON NOT NULL,
 rendered_text LONGTEXT,edited_flag TINYINT NOT NULL DEFAULT 0,widget_group VARCHAR(64) NOT NULL DEFAULT 'documentos_clinicos',
 printable TINYINT NOT NULL DEFAULT 1,event_datetime DATETIME NOT NULL,created_at DATETIME NOT NULL,
 updated_at DATETIME,signed_at DATETIME,created_by_user_id VARCHAR(128),updated_by_user_id VARCHAR(128),generated_at DATETIME
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE clinical_document_participants(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,clinical_document_id BIGINT UNSIGNED,user_id VARCHAR(64),role VARCHAR(32),participation_type VARCHAR(32),signed_at DATETIME,created_at DATETIME) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE clinical_document_revisions (revision_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 original_document_id BIGINT UNSIGNED NOT NULL,supersedes_document_id BIGINT UNSIGNED,new_document_id BIGINT UNSIGNED NOT NULL UNIQUE) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE clinical_document_binaries (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 document_id BIGINT UNSIGNED NOT NULL,variant_role VARCHAR(20) NOT NULL,variant_version INT NOT NULL) ENGINE=InnoDB");
$check = static function (bool $yes,string $name): void { if (!$yes) throw new RuntimeException($name); echo $name."=PASS\n"; };
$reject = static function (callable $run,string $name) use ($check): void {
    try { $run(); } catch (InvalidArgumentException) { $check(true,$name); return; }
    $check(false,$name);
};
$insert = $pdo->prepare("INSERT INTO clinical_documents(document_uuid,document_type,title,summary,patient_id,encounter_id,encounter_ref_id,appointment_id,payload_json,event_datetime,created_at,generated_at)
 VALUES(?,?,?,?,?,?,?,?,?,'2026-09-30 12:00:00','2026-09-30 12:00:00','2026-09-30 12:00:00')");
$add = static function (string $type,array $payload,string $patient='p_tax03a',?string $encounter=null) use ($pdo,$insert): array {
    $uuid=clinical_study_uuid();
    $insert->execute([$uuid,$type,$type,null,$patient,$encounter,$encounter===null?null:(int)$encounter,null,json_encode($payload,JSON_THROW_ON_ERROR)]);
    return ['id'=>(int)$pdo->lastInsertId(),'uuid'=>$uuid];
};
$pdo->exec("INSERT INTO clinical_study_types(study_type_key,display_name_es,category_key,aliases_json) VALUES('ecg_12_lead','Electrocardiograma de 12 derivaciones','CARDIOVASCULAR',JSON_ARRAY('ECG'))");
$typeId=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO clinical_study_type_external_codes(study_type_id,code_system,code,provenance,verified_at) VALUES(?,?,?,?,NOW())")
    ->execute([$typeId,'urn:loinc.org','TEST-CODE','TAX03A disposable QA']);
$lab=clinical_study_normalize_order_payload($pdo,'lab_order',['requested_studies'=>['CBC','Glucosa','HbA1c']]);
$check($lab['order_payload_version']===2 && count($lab['order_items'])===3
    && $lab['order_items'][0]['study_category']==='LABORATORIO'
    && $lab['requested_studies']===['CBC','Glucosa','HbA1c'],'QA_CURRENT_LAB_PAYLOAD');
$check(count(array_unique(array_column($lab['order_items'],'order_item_id')))===3
    && array_column($lab['order_items'],'sequence')===[1,2,3],'QA_MULTIPLE_ITEMS');
$img=clinical_study_normalize_order_payload($pdo,'imaging_order',['requested_studies'=>['Radiografía de tórax']]);
$check($img['order_items'][0]['study_category']==='IMAGEN' && $img['order_items'][0]['study_type_id']===null,'QA_CURRENT_IMAGING_PAYLOAD');
$free=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[['study_category'=>'DENTAL','study_display_name'=>'CBCT dental']]]);
$check($free['order_items'][0]['study_type_id']===null && $free['order_items'][0]['study_type_key']===null,'QA_FREE_TEXT_ITEM');
$catalog=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[['study_type_id'=>$typeId,'external_code_system'=>'urn:loinc.org','external_code'=>'TEST-CODE']]]);
$check($catalog['order_items'][0]['study_type_key']==='ecg_12_lead'
    && $catalog['order_items'][0]['study_category']==='CARDIOVASCULAR'
    && $catalog['order_items'][0]['study_display_name']==='Electrocardiograma de 12 derivaciones','QA_CATALOG_ITEM');
$byKey=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[['study_type_key'=>'ecg_12_lead']]]);
$check($byKey['order_items'][0]['study_type_id']===$typeId,'QA_CATALOG_KEY_LOOKUP');
$reject(fn()=>clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[['study_category'=>'DENTAL','study_display_name'=>'CBCT','order_item_id'=>$lab['order_items'][0]['order_item_id']]]]),'QA_CLIENT_ITEM_ID_REJECTED');
$source=$add('lab_order',$lab);
$foreign=$add('lab_order',clinical_study_normalize_order_payload($pdo,'lab_order',['requested_studies'=>['Otro']]),'p_other');
$refs=['related_order_document_id'=>(string)$source['id'],'related_order_document_uuid'=>$source['uuid']];
$one=clinical_study_validate_result_payload($pdo,'lab_result',$refs+['related_order_item_ids'=>[$lab['order_items'][0]['order_item_id']]],'p_tax03a');
$check(count($one['related_order_item_ids'])===1,'QA_RESULT_ONE_ITEM');
$many=clinical_study_validate_result_payload($pdo,'lab_result',$refs+['related_order_item_ids'=>array_column($lab['order_items'],'order_item_id')],'p_tax03a');
$check(count($many['related_order_item_ids'])===3,'QA_RESULT_MULTIPLE_ITEMS');
$reject(fn()=>clinical_study_validate_result_payload($pdo,'lab_result',$refs+['related_order_item_ids'=>[$lab['order_items'][0]['order_item_id'],$lab['order_items'][0]['order_item_id']]],'p_tax03a'),'QA_RESULT_DUPLICATE_IDS');
$reject(fn()=>clinical_study_validate_result_payload($pdo,'lab_result',$refs+['related_order_item_ids'=>[clinical_study_uuid()]],'p_tax03a'),'QA_RESULT_UNKNOWN_ITEM');
$otherOrder=clinical_study_normalize_order_payload($pdo,'lab_order',['requested_studies'=>['Otro pedido']]);
$other=$add('lab_order',$otherOrder);
$reject(fn()=>clinical_study_validate_result_payload($pdo,'lab_result',$refs+['related_order_item_ids'=>[$otherOrder['order_items'][0]['order_item_id']]],'p_tax03a'),'QA_RESULT_CROSS_ORDER');
$foreignPayload=json_decode((string)$pdo->query('SELECT payload_json FROM clinical_documents WHERE id='.$foreign['id'])->fetchColumn(),true);
$reject(fn()=>clinical_study_validate_result_payload($pdo,'lab_result',$refs+['related_order_item_ids'=>[$foreignPayload['order_items'][0]['order_item_id']]],'p_tax03a'),'QA_RESULT_CROSS_PATIENT');
$legacy=clinical_study_validate_result_payload($pdo,'lab_result',$refs,'p_tax03a');
$check(!array_key_exists('related_order_item_ids',$legacy),'QA_LEGACY_RESULT_NO_ITEM_IDS');
$add('lab_result',$many+['requested_studies'=>['CBC','Glucosa','HbA1c']]);
$view=clinical_or_list_fetch($pdo,'p_tax03a',20,'all','',null);
$found=null;
foreach ($view['items'] as $item) if (($item['order']['id']??null)===$source['id']) $found=$item;
$check(is_array($found) && $found['result_count']===1 && $found['order']['covered_item_count']===3
    && $found['order']['total_item_count']===3 && $found['order']['coverage_state']==='ALL_ITEMS_HAVE_RESULTS','QA_RESULT_COVERAGE_PROJECTION');
$search=clinical_or_list_fetch($pdo,'p_tax03a',20,'orders','LABORATORIO',null);
$check(count($search['items'])>=1,'QA_V2_CATEGORY_SEARCH');
$check(clinical_study_valid_uuid($lab['order_items'][0]['order_item_id']),'QA_SERVER_UUID_FORMAT');

$same=clinical_study_normalize_order_payload($pdo,'lab_order',['order_items'=>$lab['order_items']],$lab);
$check($same['order_items'][0]['order_item_id']===$lab['order_items'][0]['order_item_id'],'QA_ORDER_REPLACEMENT_SAME_ITEM');
$changed=$lab['order_items'];
$changed[0]['study_display_name']='CBC distinto';
$reject(fn()=>clinical_study_normalize_order_payload($pdo,'lab_order',['order_items'=>$changed],$lab),'QA_ORDER_REPLACEMENT_MEANING_GUARD');
unset($changed[0]['order_item_id']);
$new=clinical_study_normalize_order_payload($pdo,'lab_order',['order_items'=>$changed],$lab);
$check($new['order_items'][0]['order_item_id']!==$lab['order_items'][0]['order_item_id'],'QA_ORDER_REPLACEMENT_NEW_ITEM');
$duplicate=$lab['order_items'];
$duplicate[1]['order_item_id']=$duplicate[0]['order_item_id'];
$reject(fn()=>clinical_study_normalize_order_payload($pdo,'lab_order',['order_items'=>$duplicate],$lab),'QA_ORDER_REPLACEMENT_DUPLICATE_ID');
$corrected=clinical_study_validate_result_payload($pdo,'lab_result',$refs,'p_tax03a',null,$many);
$check($corrected['related_order_item_ids']===$many['related_order_item_ids'],'QA_RESULT_CORRECTION_PRESERVES_IDS');
$reject(fn()=>clinical_study_validate_result_payload($pdo,'lab_result',$refs+['related_order_item_ids'=>[$lab['order_items'][0]['order_item_id']]],'p_tax03a',null,$many),'QA_RESULT_CORRECTION_REJECTS_CHANGED_IDS');
$reject(fn()=>clinical_study_validate_result_payload($pdo,'lab_result',
    ['related_order_document_id'=>(string)$other['id'],'related_order_document_uuid'=>$other['uuid']],
    'p_tax03a',null,$many),'QA_RESULT_CORRECTION_REJECTS_ORDER_CHANGE');

$legacyOrder=$add('lab_order',['requested_studies'=>['Histórico libre']]);
$legacyResult=$add('lab_result',['related_order_document_uuid'=>$legacyOrder['uuid']]);
$legacyView=clinical_or_list_fetch($pdo,'p_tax03a',30,'orders','Histórico libre',null);
$check(count($legacyView['items'])===1 && $legacyView['items'][0]['order']['coverage_state']==='UNKNOWN_LEGACY'
    && $legacyView['items'][0]['order']['order_items']===[],'QA_V1_BACKWARD_COMPATIBILITY');
$unknownOrder=clinical_study_normalize_order_payload($pdo,'lab_order',['requested_studies'=>['Sin atribución']]);
$unknownDoc=$add('lab_order',$unknownOrder);
$add('lab_result',['related_order_document_uuid'=>$unknownDoc['uuid']]);
$unknownView=clinical_or_list_fetch($pdo,'p_tax03a',30,'orders','Sin atribución',null);
$check(count($unknownView['items'])===1 && $unknownView['items'][0]['result_count']===1
    && $unknownView['items'][0]['order']['coverage_state']==='UNKNOWN_COVERAGE'
    && $unknownView['items'][0]['order']['order_items'][0]['coverage_state']==='UNKNOWN','QA_UNKNOWN_COVERAGE_NOT_FABRICATED');
$reject(fn()=>clinical_study_validate_result_payload($pdo,'lab_result',
    ['related_order_document_uuid'=>$legacyOrder['uuid'],'related_order_item_ids'=>[$lab['order_items'][0]['order_item_id']]],
    'p_tax03a'),'QA_V1_ITEM_LINK_REJECTED');
$scoped=clinical_study_normalize_order_payload($pdo,'lab_order',['requested_studies'=>['Prueba en encuentro']]);
$scopedDoc=$add('lab_order',$scoped,'p_tax03a','44');
$reject(fn()=>clinical_study_validate_result_payload($pdo,'lab_result',
    ['related_order_document_uuid'=>$scopedDoc['uuid'],'related_order_item_ids'=>[$scoped['order_items'][0]['order_item_id']]],
    'p_tax03a'),'QA_CROSS_SCOPE_REJECTED');
$validScoped=clinical_study_validate_result_payload($pdo,'lab_result',
    ['related_order_document_uuid'=>$scopedDoc['uuid'],'related_order_item_ids'=>[$scoped['order_items'][0]['order_item_id']]],
    'p_tax03a','44');
$check(count($validScoped['related_order_item_ids'])===1,'QA_ENCOUNTER_SCOPE_LINK');
$reject(fn()=>clinical_study_validate_result_payload($pdo,'lab_result',
    ['related_order_document_id'=>(string)$source['id'],'related_order_document_uuid'=>$other['uuid'],
      'related_order_item_ids'=>[$lab['order_items'][0]['order_item_id']]],'p_tax03a'),'QA_EXACT_ORDER_VERSION_REQUIRED');

$partialOrder=clinical_study_normalize_order_payload($pdo,'lab_order',['requested_studies'=>['A','B','C']]);
$partialDoc=$add('lab_order',$partialOrder);
$partialRefs=['related_order_document_uuid'=>$partialDoc['uuid']];
$add('lab_result',$partialRefs+['related_order_item_ids'=>[$partialOrder['order_items'][0]['order_item_id']]]);
$partialRead=clinical_or_list_fetch($pdo,'p_tax03a',30,'orders','A',null);
$partialRow=null;
foreach ($partialRead['items'] as $entry) if (($entry['order']['id']??null)===$partialDoc['id']) $partialRow=$entry;
$check(is_array($partialRow) && $partialRow['result_count']===1
    && $partialRow['order']['coverage_state']==='PARTIAL_RESULTS'
    && $partialRow['order']['covered_item_count']===1
    && $partialRow['order']['order_items'][1]['coverage_state']==='NO_RESULT','QA_PARTIAL_RESULT_STATE');
$voided=$add('lab_result',$partialRefs+['related_order_item_ids'=>[$partialOrder['order_items'][1]['order_item_id']]]);
$pdo->exec("UPDATE clinical_documents SET status='voided' WHERE id={$voided['id']}");
$partialRead=clinical_or_list_fetch($pdo,'p_tax03a',30,'orders','A',null);
foreach ($partialRead['items'] as $entry) if (($entry['order']['id']??null)===$partialDoc['id']) $partialRow=$entry;
$check($partialRow['result_count']===1 && $partialRow['order']['covered_item_count']===1,'QA_VOIDED_RESULT_NOT_COUNTED');

$pdo->exec("UPDATE clinical_study_types SET display_name_es='Electrocardiograma actualizado',is_active=0 WHERE study_type_id={$typeId}");
$preserved=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>$catalog['order_items']],$catalog);
$check($preserved['order_items'][0]['study_display_name']==='Electrocardiograma de 12 derivaciones'
    && $preserved['order_items'][0]['order_item_id']===$catalog['order_items'][0]['order_item_id'],'QA_CATALOG_CHANGE_DOES_NOT_REWRITE_SNAPSHOT');
$reject(fn()=>clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[['study_type_id'=>$typeId]]]),'QA_INACTIVE_CATALOG_TYPE_REJECTED');
try { $pdo->exec("UPDATE clinical_study_types SET study_type_key='different_key' WHERE study_type_id={$typeId}"); $check(false,'QA_KEY_IMMUTABLE'); }
catch (PDOException) { $check(true,'QA_KEY_IMMUTABLE'); }
$pdo->exec("UPDATE clinical_study_types SET is_active=1 WHERE study_type_id={$typeId}");
