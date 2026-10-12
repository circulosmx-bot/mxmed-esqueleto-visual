<?php
declare(strict_types=1);

// Run only against a disposable database: MXMED_QA_DB=... php modules/clinical/qa/rec_imp03_lifecycle.php
$db = getenv('MXMED_QA_DB') ?: '';
if (!preg_match('/^[a-zA-Z0-9_]+$/D', $db) || !str_contains($db, '_qa_')) {
    fwrite(STDERR, "Disposable QA database required\n"); exit(2);
}
$pdo = new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
function clinical_has_active_doctor_patient_link(PDO $pdo, string $doctorId, string $patientId): bool {
    $s=$pdo->prepare("SELECT 1 FROM patients_doctor_links WHERE doctor_id=? AND patient_id=? AND status='active' LIMIT 1");
    $s->execute([$doctorId,$patientId]); return (bool)$s->fetchColumn();
}
function clinical_documents_get_fetch(PDO $pdo, int $id): ?array {
    $s=$pdo->prepare('SELECT id,document_uuid,document_type,status,version,payload_json FROM clinical_documents WHERE id=?');
    $s->execute([$id]);$r=$s->fetch();if(!$r)return null;
    return ['document_db_id'=>(int)$r['id'],'document_id'=>$r['document_uuid'],
        'document_type'=>$r['document_type'],'status'=>$r['status'],'version'=>(int)$r['version'],
        'content'=>['payload'=>json_decode($r['payload_json'],true)]];
}
function clinical_v1_document_fetch(PDO $pdo, int $id): array {
    $s=$pdo->prepare('SELECT id AS document_id,document_uuid,document_type,patient_id,encounter_ref_id,status,event_datetime FROM clinical_documents WHERE id=?');
    $s->execute([$id]);$row=$s->fetch();if(!$row)throw new RuntimeException('DOCUMENT_NOT_FOUND');return $row;
}
require_once __DIR__ . '/../../../api/_lib/clinical_prescription_lifecycle.php';
require_once __DIR__ . '/../../../api/_lib/clinical_encounter_integrity.php';
require_once __DIR__ . '/../../../api/_lib/clinical_document_timeline_read.php';
function qa(bool $condition, string $name): void {
    if (!$condition) throw new RuntimeException('FAIL '.$name);
    echo "PASS $name\n";
}
function qa_source(PDO $pdo, array $doctor, string $patientId, int $contractVersion=2): array {
    $payload=['contract_version'=>$contractVersion,'status'=>'generated',
        'prescription'=>['items'=>[['item_id'=>'item_1','name'=>'MEDICAMENTO SINTÉTICO','presentation'=>'tableta',
            'dose'=>'1','route'=>'oral','frequency'=>'cada 24 horas','duration'=>'3 días','quantity'=>'3',
            'instructions'=>'SINTÉTICO','private_notes'=>'NOTA PRIVADA']], 'observaciones'=>'SINTÉTICO'],
        'diagnostic_context'=>['mode'=>'sin_diagnostico','text'=>''],
        'presentation'=>['version'=>1,'professional_header'=>'shown'],
        'signatures'=>['doctor'=>['source'=>'qa_synthetic','artifact'=>'DO_NOT_COPY']],
        'prescription_snapshot'=>['version'=>1,'html'=>'<article style="max-width:800px;margin:auto;padding:24px;border:1px solid #cadde3;background:white"><h1>Receta médica sintética</h1><p>Paciente QA</p><p>MEDICAMENTO SINTÉTICO · 1 tableta · vía oral</p><p>Indicaciones: SINTÉTICO</p></article>']];
    $doc=mxmed_build_clinical_document(['type'=>'prescription','title'=>'Receta médica',
        'context'=>['patient_id'=>$patientId,'care_setting'=>'consulta'],'payload'=>[],
        'actor'=>['user_id'=>$doctor['user_id']]]);
    $doc['status']='generated';$doc['content']['payload']=$payload;
    $doc['content']['rendered_text']='SYNTHETIC ORIGINAL SNAPSHOT';
    $doc['timestamps']['generated_at']=gmdate('Y-m-d H:i:s');
    $pdo->beginTransaction();$id=mxmed_persist_clinical_document_in_transaction($pdo,$doc);$pdo->commit();
    return ['id'=>$id,'uuid'=>$doc['document_id']];
}
$doctor=['doctor_id'=>'nm-qa-doctor','user_id'=>'rec-imp03-qa-user'];
$patientId='nm-qa-patient';
$source=qa_source($pdo,$doctor,$patientId);
$base=['reason_code'=>'error_en_dosis_o_indicaciones','reason_detail'=>'','explanatory_note'=>'SINTÉTICO'];
$failure=function(callable $fn,string $name):void {try{$fn();throw new RuntimeException('FAIL '.$name);}catch(InvalidArgumentException){echo "PASS $name\n";}};
$failure(fn()=>clinical_prescription_lifecycle_void($pdo,$doctor,$patientId,$source['uuid'],[]),'V02 reason required');
$failure(fn()=>clinical_prescription_lifecycle_void($pdo,$doctor,$patientId,$source['uuid'],['reason_code'=>'bad']),'V03 invalid reason');
$failure(fn()=>clinical_prescription_lifecycle_void($pdo,$doctor,$patientId,$source['uuid'],['reason_code'=>'otra']),'V04 other detail');
$failure(fn()=>clinical_prescription_lifecycle_void($pdo,['doctor_id'=>'nm-qa-doctor','user_id'=>'unrelated'],
    $patientId,$source['uuid'],$base),'V06 unrelated actor');
$failure(fn()=>clinical_prescription_lifecycle_void($pdo,$doctor,'unrelated-qa-patient',
    $source['uuid'],$base),'V07 wrong patient scope');
$failure(fn()=>clinical_prescription_lifecycle_reissue($pdo,$doctor,$patientId,
    '00000000-0000-4000-8000-000000000000',$base),'S04 wrong UUID rejected');
$voided=clinical_prescription_lifecycle_void($pdo,$doctor,$patientId,$source['uuid'],$base);
qa($voided['state']==='voided','V01/V11 void state');
qa(clinical_prescription_lifecycle_void($pdo,$doctor,$patientId,$source['uuid'],$base)['state']==='voided',
    'V05 idempotent replay');
$row=$pdo->query('SELECT status,document_uuid,payload_json,rendered_text FROM clinical_documents WHERE id='.(int)$source['id'])->fetch();
qa($row['document_uuid']===$source['uuid'] && $row['rendered_text']==='SYNTHETIC ORIGINAL SNAPSHOT'
    && str_contains($row['payload_json'],'DO_NOT_COPY'),'V08-V10 immutable content/signature/UUID');
qa($voided['reason_code']===$base['reason_code'] && $voided['actor_user_id']===$doctor['user_id']
    && $voided['voided_at']!==null,'V12 audit metadata');
$original=qa_source($pdo,$doctor,$patientId);
$replacement=clinical_prescription_lifecycle_reissue($pdo,$doctor,$patientId,$original['uuid'],$base);
$new=$replacement['document'];
qa($new['document_id']!==$original['uuid'] && $new['status']==='draft','R01/R02 new draft');
$newPayload=$new['content']['payload'];
$initialFingerprint=clinical_prescription_binding_fingerprint([
    'draft_ref'=>$new['document_id'],'expected_version'=>$new['version'],'payload'=>$newPayload]);
qa(($newPayload['prescription']['items'][0]['name']??'')==='MEDICAMENTO SINTÉTICO'
    && ($newPayload['prescription']['items'][0]['private_notes']??null)===''
    && empty($newPayload['signatures']['doctor']),'R03/R04/R07 public copy, private/signature excluded');
$again=clinical_prescription_lifecycle_reissue($pdo,$doctor,$patientId,$original['uuid'],$base);
qa($again['document']['document_id']===$new['document_id'],'R14 idempotent replacement');
$drafts=clinical_prescription_v2_drafts($pdo,$doctor,$patientId);
qa(count(array_filter($drafts,fn(array $item):bool=>$item['document_uuid']===$new['document_id']
    && $item['is_reissue']===true))===1,'R02 replacement discoverable as resumable draft');
$s=$pdo->prepare('SELECT status FROM clinical_documents WHERE id=?');$s->execute([$original['id']]);
qa($s->fetchColumn()==='generated','R13 pending replacement preserves original');
qa($replacement['lifecycle']['replacement_document_uuid']===$new['document_id']
    && clinical_prescription_lifecycle_projection($pdo,(int)$new['document_db_id'])['original_document_uuid']===$original['uuid'],
    'R11/R12 bidirectional lineage');
$editedPayload=$newPayload;
$editedPayload['prescription']['items'][0]['dose']='2 tabletas';
$editedPayload['diagnostic_context']=['mode'=>'diagnostico','text'=>'DIAGNÓSTICO SINTÉTICO'];
clinical_prescription_v2_write($pdo,$doctor,$patientId,[
    'intent'=>'draft','document_type'=>'prescription','draft_ref'=>$new['document_id'],
    'expected_version'=>$new['version'],'context'=>['patient_id'=>$patientId],
    'payload'=>$editedPayload], 'rec-imp03-edit-'.bin2hex(random_bytes(6)));
$new=clinical_documents_get_fetch($pdo,(int)$new['document_db_id']);
$newPayload=$new['content']['payload'];
qa($new['version']===2 && $newPayload['prescription']['items'][0]['dose']==='2 tabletas',
    'R05 medication edit in replacement');
qa($newPayload['diagnostic_context']['text']==='DIAGNÓSTICO SINTÉTICO',
    'R06 diagnostic edit in replacement');
qa(clinical_prescription_binding_fingerprint([
    'draft_ref'=>$new['document_id'],'expected_version'=>$new['version'],'payload'=>$newPayload])!==$initialFingerprint,
    'R09 replacement edit produces independent fingerprint');
$draftRef=$new['document_id'];$version=(int)$new['version'];
$writeBody=['intent'=>'finalize','document_type'=>'prescription','draft_ref'=>$draftRef,
    'expected_version'=>$version,'context'=>['patient_id'=>$patientId],'payload'=>$newPayload];
try {
    clinical_prescription_v2_write($pdo,$doctor,$patientId,$writeBody,'rec-imp03-invalid-final-'.bin2hex(random_bytes(6)));
    throw new RuntimeException('FAIL R08 missing signature accepted');
} catch (InvalidArgumentException $e) { qa($e->getMessage()==='PRESCRIPTION_SIGNATURE_REQUIRED_OR_STALE','R08 signature required'); }
$s->execute([$original['id']]);qa($s->fetchColumn()==='generated','R13 failed finalization preserves original');
$image=imagecreatetruecolor(70,30);$white=imagecolorallocate($image,255,255,255);
$black=imagecolorallocate($image,0,0,0);imagefill($image,0,0,$white);
imageline($image,4,22,25,5,$black);imageline($image,25,5,55,22,$black);
ob_start();imagepng($image);$png=ob_get_clean();
$imageData='data:image/png;base64,'.base64_encode($png);
$bindingBody=['draft_ref'=>$draftRef,'expected_version'=>$version,
    'context'=>['patient_id'=>$patientId],'payload'=>$newPayload];
$signature=['source'=>'local_canvas','role'=>'doctor',
    'signer_name'=>$newPayload['physician_snapshot']['full_name'],'image_data'=>$imageData,
    'binding'=>['version'=>1,'role'=>'doctor','source'=>'local_canvas','document_type'=>'prescription',
        'document_uuid'=>$draftRef,'document_version'=>$version,
        'authority'=>clinical_prescription_binding_authority($bindingBody,$doctor['doctor_id']),
        'content_fingerprint'=>clinical_prescription_binding_fingerprint($bindingBody),
        'artifact_digest'=>clinical_consent_binding_artifact_digest($imageData)]];
qa($signature['binding']['artifact_digest']!=='','R09 independent signature artifact');
$applyBody=$writeBody;$applyBody['intent']='apply_signature';$applyBody['signature']=$signature;
clinical_prescription_v2_write($pdo,$doctor,$patientId,$applyBody,'rec-imp03-sign-'.bin2hex(random_bytes(6)));
$issued=clinical_prescription_v2_write($pdo,$doctor,$patientId,$writeBody,
    'rec-imp03-final-'.bin2hex(random_bytes(6)));
qa((string)$issued['document_uuid']===$draftRef,'R10 replacement issued');
$s->execute([$original['id']]);qa($s->fetchColumn()==='voided','R11 original atomically voided');
$originalState=$pdo->query('SELECT document_uuid,payload_json,rendered_text FROM clinical_documents WHERE id='.(int)$original['id'])->fetch();
qa($originalState['document_uuid']===$original['uuid']
    && $originalState['rendered_text']==='SYNTHETIC ORIGINAL SNAPSHOT'
    && str_contains($originalState['payload_json'],'DO_NOT_COPY'),
    'R11 original signed content preserved after replacement');
qa(clinical_prescription_lifecycle_projection($pdo,(int)$new['document_db_id'])['original_document_uuid']===$original['uuid'],
    'R12 issued replacement source');
$timeline=clinical_document_timeline_list_fetch($pdo,$patientId,200);
$byUuid=[];foreach($timeline['items'] as $item)$byUuid[$item['document_uuid']]=$item;
qa(isset($byUuid[$original['uuid']]) && isset($byUuid[$new['document_id']]),
    'H05/H06 original and replacement remain in history');
qa($byUuid[$original['uuid']]['prescription_lifecycle_state']==='replaced'
    && $byUuid[$original['uuid']]['replaced_by_prescription_uuid']===$new['document_id']
    && $byUuid[$new['document_id']]['corrects_prescription_uuid']===$original['uuid'],
    'H03/H04 lineage projected in history');
$legacy=qa_source($pdo,$doctor,$patientId,1);
$legacyReplacement=clinical_prescription_lifecycle_reissue($pdo,$doctor,$patientId,$legacy['uuid'],$base);
qa($legacyReplacement['document']['status']==='draft'
    && ($legacyReplacement['document']['content']['payload']['contract_version']??null)===2
    && empty($legacyReplacement['document']['content']['payload']['signatures']['doctor']),
    'H08/V1 structured historical source creates unsigned V2 draft');
$legacyVoid=qa_source($pdo,$doctor,$patientId,1);
qa(clinical_prescription_lifecycle_void($pdo,$doctor,$patientId,$legacyVoid['uuid'],$base)['state']==='voided',
    'V1 historical void supported');
echo "REC_IMP03_CORE_QA=PASS\n";
