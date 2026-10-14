<?php
declare(strict_types=1);

// Run only against a disposable clone of normal-local mxmed.
$db = getenv('MXMED_QA_DB') ?: '';
if (!preg_match('/^rec_vis06_qa_[a-f0-9]+$/D', $db)) {
    fwrite(STDERR, "Disposable REC-VIS06 database required\n");
    exit(2);
}
$pdo = new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function clinical_has_active_doctor_patient_link(PDO $pdo, string $doctorId, string $patientId): bool
{
    $stmt=$pdo->prepare("SELECT 1 FROM patients_doctor_links WHERE doctor_id=? AND patient_id=? AND status='active' LIMIT 1");
    $stmt->execute([$doctorId,$patientId]);
    return (bool)$stmt->fetchColumn();
}

function clinical_resolve_encounter_key(PDO $pdo, string $encounterKey): array
{
    if (preg_match('/^enc:(\d+)$/D', $encounterKey, $match) !== 1) return ['ok'=>false,'row'=>null];
    $stmt=$pdo->prepare('SELECT * FROM clinical_encounters WHERE encounter_id=? LIMIT 1');
    $stmt->execute([(int)$match[1]]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return ['ok'=>is_array($row),'row'=>is_array($row)?$row:null,'encounter_key'=>$encounterKey];
}

function clinical_v1_document_fetch(PDO $pdo, int $id): array
{
    $stmt=$pdo->prepare('SELECT id AS document_id,document_uuid,document_type,patient_id,encounter_ref_id,status,version,payload_json FROM clinical_documents WHERE id=?');
    $stmt->execute([$id]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new RuntimeException('DOCUMENT_NOT_FOUND');
    return $row;
}

require_once __DIR__.'/../../../api/_lib/clinical_encounter_integrity.php';
require_once __DIR__.'/../../../api/_lib/clinical_prescription_v2.php';

function check(bool $condition, string $name): void
{
    if (!$condition) throw new RuntimeException('FAIL '.$name);
    echo "PASS $name\n";
}

function fetch_document(PDO $pdo, string $uuid): array
{
    $stmt=$pdo->prepare('SELECT id,document_uuid,version,status,encounter_ref_id,payload_json FROM clinical_documents WHERE document_uuid=? LIMIT 1');
    $stmt->execute([$uuid]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new RuntimeException('DOCUMENT_NOT_FOUND');
    $row['payload']=json_decode((string)$row['payload_json'],true,512,JSON_THROW_ON_ERROR);
    return $row;
}

function base_body(string $patientId, ?string $encounterKey=null): array
{
    $context=['patient_id'=>$patientId];
    if ($encounterKey !== null) $context['encounter_key']=$encounterKey;
    return ['document_type'=>'prescription','intent'=>'draft','context'=>$context,'payload'=>[
        'contract_version'=>2,
        'prescription'=>['items'=>[['item_id'=>'item_1','name'=>'REC-VIS06 QA','presentation'=>'tableta',
            'dose'=>'1 tableta','route'=>'oral','frequency'=>'cada 24 horas','duration'=>'1 día',
            'quantity'=>'1','instructions'=>'QA','private_notes'=>'']], 'observaciones'=>''],
        'diagnostic_context'=>['mode'=>'sin_diagnostico','text'=>''],
        'presentation'=>['version'=>1,'professional_header'=>'shown'],
    ]];
}

function signature_for(array $document, array $doctor, string $source='local_canvas'): array
{
    $canvas=imagecreatetruecolor(80,32);
    $white=imagecolorallocate($canvas,255,255,255);$ink=imagecolorallocate($canvas,1,49,81);
    imagefill($canvas,0,0,$white);imageline($canvas,4,25,30,6,$ink);imageline($canvas,30,6,70,24,$ink);
    ob_start();imagepng($canvas);$bytes=(string)ob_get_clean();
    $image='data:image/png;base64,'.base64_encode($bytes);
    $bindingBody=['draft_ref'=>$document['document_uuid'],'expected_version'=>(int)$document['version'],
        'context'=>['patient_id'=>'review-patient'],'payload'=>$document['payload']];
    return ['source'=>$source,'role'=>'doctor','signer_name'=>$document['payload']['physician_snapshot']['full_name'],
        'image_data'=>$image,'signed_at'=>gmdate(DATE_ATOM),'binding'=>[
            'version'=>1,'role'=>'doctor','source'=>$source,'document_type'=>'prescription',
            'document_uuid'=>$document['document_uuid'],'document_version'=>(int)$document['version'],
            'authority'=>clinical_prescription_binding_authority($bindingBody,$doctor['doctor_id']),
            'content_fingerprint'=>clinical_prescription_binding_fingerprint($bindingBody),
            'artifact_digest'=>clinical_consent_binding_artifact_digest($image),
        ]];
}

putenv('MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1');
$doctor=['doctor_id'=>'1','user_id'=>'review-user'];

$patientBody=base_body('review-patient');
$patientCreated=clinical_prescription_v2_write($pdo,$doctor,'review-patient',$patientBody,'rec-vis06-patient-'.bin2hex(random_bytes(5)));
$patient=fetch_document($pdo,(string)$patientCreated['document_uuid']);
check($patient['encounter_ref_id']===null && $patient['payload']['encounter_reference']===null,
    'patient-level draft has no implicit encounter');

$linkedBody=base_body('review-patient','enc:1001');
$linkedCreated=clinical_prescription_v2_write($pdo,$doctor,'review-patient',$linkedBody,'rec-vis06-linked-'.bin2hex(random_bytes(5)));
$linked=fetch_document($pdo,(string)$linkedCreated['document_uuid']);
check((int)$linked['encounter_ref_id']===1001 && (int)$linked['payload']['encounter_reference']['encounter_id']===1001,
    'explicit and consultation encounter remains exact');

$cross=base_body('review-no-open','enc:1001');
try {
    clinical_prescription_v2_normalize($pdo,$doctor,'review-no-open',$cross,false);
    throw new RuntimeException('FAIL cross-patient encounter accepted');
} catch (InvalidArgumentException $error) {
    check($error->getMessage()==='PRESCRIPTION_ENCOUNTER_SCOPE_INVALID','cross-patient encounter denied');
}

foreach ([['patient-level',$patient,$patientBody,'local_canvas'],['encounter-linked',$linked,$linkedBody,'registered_profile']] as [$label,$document,$body,$source]) {
    $unsigned=$body;
    $unsigned['intent']='finalize';$unsigned['draft_ref']=$document['document_uuid'];$unsigned['expected_version']=(int)$document['version'];
    $unsigned['payload']=$document['payload'];
    try {
        clinical_prescription_v2_write($pdo,$doctor,'review-patient',$unsigned,'rec-vis06-unsigned-'.bin2hex(random_bytes(5)));
        throw new RuntimeException('FAIL unsigned emission accepted');
    } catch (InvalidArgumentException $error) {
        check($error->getMessage()==='PRESCRIPTION_SIGNATURE_REQUIRED_OR_STALE',$label.' unsigned emission denied');
    }
    $signature=signature_for($document,$doctor,$source);
    if ($source==='registered_profile') {
        $stmt=$pdo->prepare('INSERT INTO physician_signatures
            (doctor_id,asset_id,storage_key,checksum_sha256,byte_size,width,height) VALUES
            (?,\'66666666-6666-4666-8666-666666666666\',\'qa/rec-vis06-signature.png\',?,1,80,32)
            ON DUPLICATE KEY UPDATE checksum_sha256=VALUES(checksum_sha256)');
        $stmt->execute([$doctor['doctor_id'],$signature['binding']['artifact_digest']]);
        check((int)$pdo->query("SELECT COUNT(*) FROM physician_signatures WHERE doctor_id='1'")->fetchColumn()===1,
            'registered signature authority prepared');
    }
    $apply=$unsigned;$apply['intent']='apply_signature';$apply['signature']=$signature;
    clinical_prescription_v2_write($pdo,$doctor,'review-patient',$apply,'rec-vis06-sign-'.bin2hex(random_bytes(5)));
    $signed=fetch_document($pdo,$document['document_uuid']);
    check(clinical_prescription_binding_classify(['draft_ref'=>$signed['document_uuid'],
        'expected_version'=>(int)$signed['version'],'context'=>['patient_id'=>'review-patient'],'payload'=>$signed['payload']],
        '1',$pdo)==='valid_bound_signature',$label.' signature preparation');
    $issued=clinical_prescription_v2_write($pdo,$doctor,'review-patient',$unsigned,'rec-vis06-final-'.bin2hex(random_bytes(5)));
    check((string)$issued['document_uuid']===(string)$document['document_uuid'],$label.' immutable UUID emission');
}
check((string)$pdo->query('SELECT status FROM clinical_encounters WHERE encounter_id=1001')->fetchColumn()==='open',
    'prescription emission does not close consultation');

echo "REC_VIS06_SIGNATURE_INTEGRITY=PASS\n";
