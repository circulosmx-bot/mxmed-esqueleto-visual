<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';
$db=getenv('TAX03B_AMEND_DB');
if(!is_string($db)||!preg_match('/^tax03b_amend_[a-f0-9]{12}$/D',$db))throw new RuntimeException('Disposable DB required');
$pdo=new PDO('mysql:host=localhost;dbname='.$db,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("INSERT INTO patients_patients(patient_id,display_name) VALUES('p_tax03b_amend','QA patient')");
$pdo->exec("INSERT INTO patients_doctor_links(link_id,doctor_id,patient_id,status) VALUES('link_tax03b_amend','d_tax03b_amend','p_tax03b_amend','active')");
$insert=$pdo->prepare("INSERT INTO clinical_documents(document_uuid,document_type,title,patient_id,payload_json,event_datetime,created_at,generated_at,created_by_user_id)
 VALUES(?,?,?,'p_tax03b_amend',?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP(),'u_tax03b_amend')");
$add=static function(string $type,string $title,array $payload) use($insert,$pdo):array{
 $uuid=clinical_study_uuid();$insert->execute([$uuid,$type,$title,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
 return ['id'=>(int)$pdo->lastInsertId(),'uuid'=>$uuid,'payload'=>$payload];
};
$main=$add('lab_order','Orden V2',clinical_study_normalize_order_payload($pdo,'lab_order',['requested_studies'=>['Glucosa','HbA1c']]));
$other=$add('lab_order','Otra orden V2',clinical_study_normalize_order_payload($pdo,'lab_order',['requested_studies'=>['Creatinina']]));
$resultPayload=clinical_study_validate_result_payload($pdo,'lab_result',[
 'related_order_document_id'=>(string)$main['id'],'related_order_document_uuid'=>$main['uuid'],
 'related_order_item_ids'=>[$main['payload']['order_items'][0]['order_item_id']],
 'result_summary'=>'Resultado original'], 'p_tax03b_amend');
$result=$add('lab_result','Resultado V2',$resultPayload);
echo json_encode(['main'=>$main,'other'=>$other,'result'=>$result],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
