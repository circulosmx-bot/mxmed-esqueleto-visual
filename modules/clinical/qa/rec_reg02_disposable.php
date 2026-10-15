<?php
declare(strict_types=1);

$db = getenv('MXMED_QA_DB') ?: '';
if (!preg_match('/^rec_reg02_qa_[a-f0-9]{12}$/D', $db)) {
    fwrite(STDERR, "Disposable REC-REG02 database required\n");
    exit(2);
}
$pdo = new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);

function clinical_has_active_doctor_patient_link(PDO $pdo, string $doctorId, string $patientId): bool
{
    $stmt=$pdo->prepare("SELECT 1 FROM patients_doctor_links WHERE doctor_id=? AND patient_id=? AND status='active' LIMIT 1");
    $stmt->execute([$doctorId,$patientId]); return (bool)$stmt->fetchColumn();
}
function clinical_resolve_encounter_key(PDO $pdo, string $key): array { return ['ok'=>false,'row'=>null]; }
function clinical_v1_document_fetch(PDO $pdo, int $id): array
{
    $stmt=$pdo->prepare('SELECT id AS document_id,document_uuid,document_type,patient_id,encounter_ref_id,status,version,payload_json FROM clinical_documents WHERE id=?');
    $stmt->execute([$id]); return (array)$stmt->fetch(PDO::FETCH_ASSOC);
}

require_once __DIR__.'/../../../api/_lib/clinical_encounter_integrity.php';
require_once __DIR__.'/../../../api/_lib/clinical_prescription_v2.php';
require_once __DIR__.'/../../../api/_lib/clinical_prescription_qr.php';

function qa(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('FAIL '.$label);
    echo $label."=PASS\n";
}
function document(PDO $pdo, string $uuid): array
{
    $stmt=$pdo->prepare('SELECT * FROM clinical_documents WHERE document_uuid=? LIMIT 1');
    $stmt->execute([$uuid]); $row=(array)$stmt->fetch(PDO::FETCH_ASSOC);
    $row['payload']=json_decode((string)$row['payload_json'],true,512,JSON_THROW_ON_ERROR); return $row;
}
function body(string $patientId): array
{
    return ['document_type'=>'prescription','intent'=>'draft','context'=>['patient_id'=>$patientId],
        'payload'=>['contract_version'=>2,'prescription'=>['medication_schema_version'=>2,'items'=>[[
            'item_id'=>'reg02_1','name'=>'Medicamento estructurado QA','presentation'=>'Tabletas',
            'dose'=>'1 tableta','route'=>'Oral','quantity'=>'14 tabletas','instructions'=>'Después de alimentos',
            'private_notes'=>'nota QA privada','regimen'=>[
                'administration'=>['pattern'=>'SCHEDULED','instruction'=>'Cada 12 horas'],
                'duration'=>['mode'=>'DEFINED_DURATION','value'=>7,'unit'=>'DAY'],
            ]]],'observaciones'=>''],
            'diagnostic_context'=>['mode'=>'sin_diagnostico','text'=>''],
            'presentation'=>['version'=>1,'professional_header'=>'shown']]];
}

putenv('MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1');
$doctor=['doctor_id'=>'1','user_id'=>'review-user']; $patientId='review-patient';
$before=(int)$pdo->query('SELECT COUNT(*) FROM clinical_documents')->fetchColumn();
$invalid=body($patientId);
$invalid['payload']['prescription']['items'][0]['regimen']['administration']=[
    'pattern'=>'AS_NEEDED','indication'=>'','repeat_mode'=>'REPEATABLE',
    'minimum_interval'=>['value'=>6,'unit'=>'HOUR']];
$incomplete=clinical_prescription_v2_write($pdo,$doctor,$patientId,$invalid,
    'rec-reg02-incomplete-'.bin2hex(random_bytes(5)));
$incompleteDraft=document($pdo,(string)$incomplete['document_uuid']);
qa($incompleteDraft['status']==='draft'
    && $incompleteDraft['payload']['prescription']['items'][0]['frequency']===''
    && $incompleteDraft['payload']['prescription']['items'][0]['duration']==='',
    'REG02_DISPOSABLE_INCOMPLETE_DRAFT_ACCEPTED');
$before=(int)$pdo->query('SELECT COUNT(*) FROM clinical_documents')->fetchColumn();

$request=body($patientId);
$created=clinical_prescription_v2_write($pdo,$doctor,$patientId,$request,'rec-reg02-valid-'.bin2hex(random_bytes(5)));
$draft=document($pdo,(string)$created['document_uuid']);
$bindingBody=['draft_ref'=>$draft['document_uuid'],'expected_version'=>(int)$draft['version'],
    'context'=>['patient_id'=>$patientId],'payload'=>$draft['payload']];
qa((int)$draft['payload']['prescription']['medication_schema_version']===2
    && $draft['payload']['prescription']['items'][0]['frequency']==='Cada 12 horas'
    && $draft['payload']['prescription']['items'][0]['duration']==='Durante 7 días',
    'REG02_DISPOSABLE_CANONICAL_PERSISTENCE');
qa(clinical_prescription_fingerprint_version($bindingBody)===2,'REG02_DISPOSABLE_FINGERPRINT_V2');

$invalidFinal=body($patientId); $invalidFinal['intent']='finalize';
$invalidFinal['draft_ref']=$draft['document_uuid']; $invalidFinal['expected_version']=(int)$draft['version'];
$invalidFinal['payload']['prescription']['items'][0]['regimen']['administration']=[
    'pattern'=>'AS_NEEDED','indication'=>'','repeat_mode'=>'SINGLE_ADMINISTRATION'];
$beforeInvalidEmissionHash=hash('sha256',(string)$draft['payload_json']);
try {
    clinical_prescription_v2_write($pdo,$doctor,$patientId,$invalidFinal,'rec-reg02-invalid-final-'.bin2hex(random_bytes(5)));
    throw new RuntimeException('FAIL invalid emission accepted');
} catch (ClinicalPrescriptionRegimenException $error) {
    qa($error->errorCode==='PRESCRIPTION_PRN_INDICATION_REQUIRED','REG02_DISPOSABLE_INVALID_EMISSION_REJECTED');
}
$afterInvalidEmission=document($pdo,$draft['document_uuid']);
qa((int)$afterInvalidEmission['version']===(int)$draft['version']
    && (string)$afterInvalidEmission['status']==='draft'
    && hash_equals($beforeInvalidEmissionHash,hash('sha256',(string)$afterInvalidEmission['payload_json'])),
    'REG02_DISPOSABLE_INVALID_EMISSION_ATOMIC');

$token=bin2hex(random_bytes(32)); $html=clinical_prescription_qr_review_html($draft['payload'],(int)$draft['version']);
$stmt=$pdo->prepare("INSERT INTO clinical_prescription_qr_sessions
    (token,document_uuid,document_version,patient_id,doctor_id,actor_user_id,role,signer_authority,
    signer_name,signer_role_label,fingerprint_version,content_fingerprint,review_html,review_html_sha256,
    status,created_at,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 900 SECOND))");
$stmt->execute([$token,$draft['document_uuid'],$draft['version'],$patientId,'1','review-user','doctor',
    clinical_prescription_binding_authority($bindingBody,'1'),$draft['payload']['physician_snapshot']['full_name'],
    'Médico responsable',2,clinical_prescription_binding_fingerprint($bindingBody),$html,hash('sha256',$html)]);
$session=clinical_prescription_qr_row($pdo,$token);
qa(is_array($session)&&clinical_prescription_qr_current($pdo,$session),'REG02_DISPOSABLE_QR_CURRENT');

$changed=body($patientId); $changed['draft_ref']=$draft['document_uuid'];
$changed['expected_version']=(int)$draft['version'];
$changed['payload']['prescription']['items'][0]['regimen']['administration']['instruction']='Cada 8 horas';
clinical_prescription_v2_write($pdo,$doctor,$patientId,$changed,'rec-reg02-change-'.bin2hex(random_bytes(5)));
qa(!clinical_prescription_qr_current($pdo,$session),'REG02_DISPOSABLE_QR_STALE_AFTER_MUTATION');

try {
    clinical_prescription_v2_write($pdo,['doctor_id'=>'999','user_id'=>'other-user'],$patientId,body($patientId),
        'rec-reg02-cross-'.bin2hex(random_bytes(5)));
    throw new RuntimeException('FAIL cross physician write accepted');
} catch (InvalidArgumentException $error) {
    qa($error->getMessage()==='PRESCRIPTION_PATIENT_SCOPE_INVALID','REG02_DISPOSABLE_CROSS_PHYSICIAN_DENIED');
}

echo "REC_REG02_DISPOSABLE=PASS\n";
