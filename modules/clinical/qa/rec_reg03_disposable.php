<?php
declare(strict_types=1);

$db=getenv('MXMED_QA_DB')?:'';
if(!preg_match('/^rec_reg03_qa_[a-f0-9]{12}$/D',$db)){fwrite(STDERR,"Disposable REC-REG03 database required\n");exit(2);}
$pdo=new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

function clinical_has_active_doctor_patient_link(PDO $pdo,string $doctorId,string $patientId):bool{
    $s=$pdo->prepare("SELECT 1 FROM patients_doctor_links WHERE doctor_id=? AND patient_id=? AND status='active' LIMIT 1");
    $s->execute([$doctorId,$patientId]);return(bool)$s->fetchColumn();
}
function clinical_resolve_encounter_key(PDO $pdo,string $key):array{
    if(!preg_match('/^enc:(\d+)$/D',$key,$m))return['ok'=>false,'row'=>null];
    $s=$pdo->prepare('SELECT * FROM clinical_encounters WHERE encounter_id=? LIMIT 1');$s->execute([(int)$m[1]]);
    $row=$s->fetch();return['ok'=>(bool)$row,'row'=>$row?:null];
}
function clinical_v1_document_fetch(PDO $pdo,int $id):array{
    $s=$pdo->prepare('SELECT id AS document_id,document_uuid,document_type,patient_id,encounter_ref_id,status,version,event_datetime,payload_json FROM clinical_documents WHERE id=?');
    $s->execute([$id]);return(array)$s->fetch();
}
function clinical_documents_get_fetch(PDO $pdo,int $id):?array{
    $s=$pdo->prepare('SELECT id,document_uuid,document_type,status,version,payload_json FROM clinical_documents WHERE id=?');
    $s->execute([$id]);$r=$s->fetch();if(!$r)return null;
    return['document_db_id'=>(int)$r['id'],'document_id'=>$r['document_uuid'],'document_type'=>$r['document_type'],
        'status'=>$r['status'],'version'=>(int)$r['version'],'content'=>['payload'=>json_decode($r['payload_json'],true,512,JSON_THROW_ON_ERROR)]];
}
require_once __DIR__.'/../../../api/_lib/clinical_encounter_integrity.php';
require_once __DIR__.'/../../../api/_lib/clinical_prescription_v2.php';
require_once __DIR__.'/../../../api/_lib/clinical_prescription_qr.php';
require_once __DIR__.'/../../../api/_lib/clinical_prescription_lifecycle.php';

function qa(bool $ok,string $case):void{if(!$ok)throw new RuntimeException('FAIL '.$case);echo $case."=PASS\n";}
function fetchDoc(PDO $pdo,string $uuid):array{$s=$pdo->prepare('SELECT * FROM clinical_documents WHERE document_uuid=?');$s->execute([$uuid]);$r=(array)$s->fetch();$r['payload']=json_decode($r['payload_json'],true,512,JSON_THROW_ON_ERROR);return$r;}
function body(string $patient,array $items,?string $encounter=null):array{return['document_type'=>'prescription','intent'=>'draft',
    'context'=>array_filter(['patient_id'=>$patient,'encounter_key'=>$encounter],fn($v)=>$v!==null),
    'payload'=>['contract_version'=>2,'prescription'=>['medication_schema_version'=>2,'items'=>$items,'observaciones'=>''],
        'diagnostic_context'=>['mode'=>'sin_diagnostico','text'=>''],'presentation'=>['version'=>2,'professional_header'=>'data_only']]];}
function item(string $id='reg03_1'):array{return['item_id'=>$id,'name'=>'Medicamento estructurado QA','presentation'=>'Tabletas 10 mg',
    'dose'=>'1 tableta','route'=>'Oral','quantity'=>'14 tabletas','instructions'=>'Tomar con agua','private_notes'=>'privada QA',
    'regimen'=>['administration'=>['pattern'=>'AS_NEEDED','indication'=>'En caso de dolor','repeat_mode'=>'REPEATABLE',
        'minimum_interval'=>['value'=>6,'unit'=>'HOUR'],'maximum_dose_per_period'=>['value'=>3,'unit'=>'tabletas','period'=>['value'=>24,'unit'=>'HOUR']],
        'maximum_administrations_per_period'=>['count'=>3,'period'=>['value'=>24,'unit'=>'HOUR']]],
        'duration'=>['mode'=>'UNTIL_REASSESSMENT']]];}

$doctor=['doctor_id'=>'1','user_id'=>'review-user'];$patient='review-patient';
$before=(int)$pdo->query('SELECT COUNT(*) FROM clinical_documents')->fetchColumn();
$incomplete=item();$incomplete['name']='';$incomplete['quantity']='';
$incomplete['regimen']['administration']['indication']='';$incomplete['regimen']['administration']['minimum_interval']['value']='';
$create=clinical_prescription_v2_write($pdo,$doctor,$patient,body($patient,[$incomplete]),'reg03-incomplete-'.bin2hex(random_bytes(5)));
$draft=fetchDoc($pdo,(string)$create['document_uuid']);
qa($draft['status']==='draft'&&$draft['payload']['prescription']['items'][0]['regimen']['administration']['pattern']==='AS_NEEDED'
    &&$draft['payload']['prescription']['items'][0]['frequency']==='','REG03_07_INCOMPLETE_DRAFT_ROUNDTRIP');

$complete=item();$update=body($patient,[$complete]);$update['draft_ref']=$draft['document_uuid'];$update['expected_version']=(int)$draft['version'];
clinical_prescription_v2_write($pdo,$doctor,$patient,$update,'reg03-complete-'.bin2hex(random_bytes(5)));
$saved=fetchDoc($pdo,$draft['document_uuid']);$savedItem=$saved['payload']['prescription']['items'][0];
$canonical=clinical_prescription_regimen_normalize($complete['regimen'],1,$saved['payload']['issue_date']);
qa($savedItem['regimen']==$canonical,'REG03_08_COMPLETE_REGIMEN_RESUME');
qa($savedItem['quantity']==='14 tabletas'&&$savedItem['instructions']==='Tomar con agua'
    &&$savedItem['private_notes']==='privada QA','REG03_08_COMPLETE_FIELDS_RESUME');

$edited=$complete;$edited['regimen']['administration']['indication']='En caso de dolor intenso';
$edited['regimen']['administration']['minimum_interval']['value']=8;
$duplicate=$edited;$duplicate['item_id']='reg03_2';
$editBody=body($patient,[$edited,$duplicate]);$editBody['draft_ref']=$saved['document_uuid'];$editBody['expected_version']=(int)$saved['version'];
clinical_prescription_v2_write($pdo,$doctor,$patient,$editBody,'reg03-edit-'.bin2hex(random_bytes(5)));
$two=fetchDoc($pdo,$saved['document_uuid']);
qa(count($two['payload']['prescription']['items'])===2&&$two['payload']['prescription']['items'][1]['regimen']==$two['payload']['prescription']['items'][0]['regimen'],'REG03_09_10_EDIT_DUPLICATE');
$deleteBody=body($patient,[$edited]);$deleteBody['draft_ref']=$two['document_uuid'];$deleteBody['expected_version']=(int)$two['version'];
clinical_prescription_v2_write($pdo,$doctor,$patient,$deleteBody,'reg03-delete-'.bin2hex(random_bytes(5)));
$one=fetchDoc($pdo,$two['document_uuid']);qa(count($one['payload']['prescription']['items'])===1,'REG03_11_DELETE');
qa($one['encounter_ref_id']===null,'REG03_27_PATIENT_LEVEL_SCOPE');

$enc=clinical_prescription_v2_write($pdo,$doctor,$patient,body($patient,[item('enc_1')],'enc:1001'),'reg03-enc-'.bin2hex(random_bytes(5)));
$encDoc=fetchDoc($pdo,(string)$enc['document_uuid']);qa((int)$encDoc['encounter_ref_id']===1001,'REG03_28_EXACT_ENCOUNTER');
try{clinical_prescription_v2_write($pdo,['doctor_id'=>'999','user_id'=>'other'],$patient,body($patient,[item()]),'reg03-cross-'.bin2hex(random_bytes(5)));$denied=false;}
catch(InvalidArgumentException $e){$denied=$e->getMessage()==='PRESCRIPTION_PATIENT_SCOPE_INVALID';}
qa($denied,'REG03_29_CROSS_PHYSICIAN_DENIED');

// Reissue an isolated structured source inside the disposable clone.
$pdo->prepare("UPDATE clinical_documents SET status='generated',generated_at=UTC_TIMESTAMP() WHERE id=?")->execute([(int)$one['id']]);
$originalHash=hash('sha256',$one['payload_json']);
$reissue=clinical_prescription_lifecycle_reissue($pdo,$doctor,$patient,$one['document_uuid'],[
    'reason_code'=>'error_en_dosis_o_indicaciones','reason_detail'=>'','explanatory_note'=>'QA estructurada']);
$replacement=fetchDoc($pdo,(string)$reissue['document']['document_id']);
qa($replacement['document_uuid']!==$one['document_uuid']&&$replacement['status']==='draft'
    &&$replacement['payload']['prescription']['medication_schema_version']===2
    &&$replacement['payload']['prescription']['items'][0]['regimen']==$one['payload']['prescription']['items'][0]['regimen']
    &&$replacement['payload']['prescription']['items'][0]['private_notes']===''
    &&empty($replacement['payload']['signatures']['doctor']),'REG03_25_STRUCTURED_REISSUE');
$original=fetchDoc($pdo,$one['document_uuid']);qa(hash_equals($originalHash,hash('sha256',$original['payload_json']))&&$original['status']==='generated','REG03_26_HISTORICAL_UNCHANGED');
qa((int)$pdo->query('SELECT COUNT(*) FROM clinical_documents')->fetchColumn()>$before,'REG03_DISPOSABLE_ONLY');
echo "REC_REG03_DISPOSABLE=PASS\n";
