<?php
declare(strict_types=1);
/** Disposable DB only: V3 draft, physician signatures, stale edit, emission, snapshot. */
require_once dirname(__DIR__, 3).'/api/_lib/db.php';
$database=(string)(mxmed_load_db_config()['mysql']['dbname']??'');
if(!preg_match('/^mxmed_cert_ext02b_qa_[0-9]{8}$/D',$database)) {
    fwrite(STDERR,"Refusing non-disposable database\n"); exit(2);
}
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['PATH_INFO']='/__cert_ext02b';
ob_start(); require dirname(__DIR__,3).'/api/clinical/index.php'; ob_end_clean();
require_once dirname(__DIR__,3).'/api/_lib/clinical_certificado_write.php';
$pdo=mxmed_pdo();
$doctor=['doctor_id'=>'ix-qa-doctor','user_id'=>'ix-qa-user'];
$patientId='ix-qa-patient';
$canvas=imagecreatetruecolor(180,90);
$white=imagecolorallocate($canvas,255,255,255);
$black=imagecolorallocate($canvas,0,0,0);
imagefilledrectangle($canvas,0,0,179,89,$white);
imagesetthickness($canvas,4);
imageline($canvas,12,20,130,60,$black);
imageline($canvas,30,62,160,18,$black);
ob_start(); imagepng($canvas); $image='data:image/png;base64,'.base64_encode(ob_get_clean());
$digest=clinical_consent_binding_artifact_digest($image);
if($digest==='') throw new RuntimeException('Synthetic signature invalid');

function preopQaBody(): array {
    $preop=['planned_procedure'=>'Resección electiva sintética de lesión cutánea',
        'planned_date'=>'','requester'=>'','assessment_scope'=>'Consulta y exploración general',
        'current_findings'=>'Valoración sintética estable, con seguimiento de presión arterial.',
        'history_state'=>'','history_detail'=>'','medications_state'=>'','medications_detail'=>'',
        'allergies_state'=>'','allergies_detail'=>'','studies_state'=>'','studies_detail'=>'',
        'clinical_considerations'=>'Comunicar al equipo solicitante el seguimiento de presión arterial.',
        'conclusion'=>'consideraciones_documentadas','recommendations'=>'Continuar seguimiento clínico.'];
    return ['type'=>'certificado_medico','document_type'=>'certificado_medico',
        'title'=>'Constancia de valoración médica preoperatoria','summary'=>'CERT-EXT02B caso sintético QA',
        'event_datetime'=>gmdate('Y-m-d H:i:s'),
        'context'=>['patient_id'=>'ix-qa-patient','care_setting'=>'consulta'],
        'actor'=>['user_id'=>'ix-qa-user'],
        'payload'=>['contract_version'=>2,'status'=>'draft',
            'presentation'=>['version'=>1,'professional_header'=>'shown'],
            'report'=>['emission_date'=>gmdate('Y-m-d'),'issued_at'=>gmdate('Y-m-d H:i:s')],
            'patient_snapshot'=>['full_name'=>'Paciente sintético QA','age'=>'42',
                'sex'=>'Femenino','identifier'=>'ix-qa-patient'],
            'actor_snapshot'=>['user_id'=>'ix-qa-user','full_name'=>'Dra. Sintética QA',
                'license'=>'QA123','specialty'=>'QA','specialty_license'=>'',
                'place'=>'QA','institution'=>'QA','facility'=>'QA'],
            'branding'=>['logo_url_resolved'=>'','facility_visible'=>'QA','location_line_visible'=>'QA',
                'group_name'=>'QA','address_line'=>'QA','consultorio_phone'=>'QA'],
            'certificate'=>['type'=>'valoracion_preoperatoria',
                'type_label'=>'Constancia de valoración médica preoperatoria',
                'purpose'=>'A quien corresponda','purpose_category'=>'A quien corresponda',
                'usage_type'=>'','usage_custom'=>''],
            'content'=>['declaration_text'=>'Se documenta una valoración médica preoperatoria para el procedimiento previsto. No autoriza cirugía ni anestesia.',
                'care_date'=>'','rest_days'=>'','rest_start_date'=>'','rest_end_date'=>'',
                'return_note'=>'','general_condition'=>'','general_condition_label'=>'',
                'observations_mode'=>'','observations'=>'','validity_term'=>'',
                'usage_note_type'=>'','usage_note_custom'=>'','usage_note'=>'',
                'recipient_header'=>'','validity_note'=>'','closing_statement'=>'','preop'=>$preop],
            'form_snapshot'=>['type'=>'valoracion_preoperatoria','preop'=>$preop],
            'signatures'=>['doctor'=>null]]];
}
function preopQaWrite(PDO $pdo,array $doctor,string $patientId,array $body):array {
    return clinical_certificado_write($pdo,$doctor,$patientId,$body,'cert-ext02b:'.bin2hex(random_bytes(12)));
}
foreach(['local_canvas','registered_profile'] as $source) {
    $body=preopQaBody();
    $created=preopQaWrite($pdo,$doctor,$patientId,$body);
    if(($created['status']??'')!=='draft') throw new RuntimeException('Draft failed');
    $body['draft_ref']=$created['document_uuid'];
    $body['expected_version']=1;
    if($source==='registered_profile') {
        $pdo->prepare('INSERT INTO physician_signatures
            (doctor_id,asset_id,storage_key,checksum_sha256,byte_size,width,height) VALUES
            (?,UUID(),?,?,?,180,90) ON DUPLICATE KEY UPDATE checksum_sha256=VALUES(checksum_sha256)')
            ->execute([$doctor['doctor_id'],'qa-only-signature',$digest,strlen($image)]);
    }
    $body['payload']['signatures']['doctor']=['type'=>'drawn','role'=>'doctor',
        'image_data'=>$image,'signed_at'=>gmdate('Y-m-d H:i:s'),'signer_name'=>'Dra. Sintética QA',
        'source'=>$source,'binding'=>['version'=>2,'document_type'=>'certificado_medico',
            'document_uuid'=>$created['document_uuid'],'role'=>'doctor','source'=>$source,
            'authority'=>'ix-qa-doctor|ix-qa-user','artifact_digest'=>$digest,
            'content_fingerprint'=>clinical_certificado_binding_fingerprint($body),
            'applied_at'=>gmdate('c')]];
    if(clinical_certificado_binding_classify($body,$doctor['doctor_id'],$pdo)!=='valid_bound_signature')
        throw new RuntimeException("$source signature invalid");
    $edited=$body;
    $edited['payload']['content']['preop']['planned_procedure'].=' cambiada';
    if(clinical_certificado_binding_classify($edited,$doctor['doctor_id'],$pdo)!=='stale_or_unverified_signature')
        throw new RuntimeException("$source edit did not stale");
    $body['payload']['status']='issued';
    $issued=preopQaWrite($pdo,$doctor,$patientId,$body);
    if(($issued['status']??'')!=='generated'||$issued['document_uuid']!==$created['document_uuid'])
        throw new RuntimeException("$source emission failed");
    $payload=json_decode((string)$pdo->query('SELECT payload_json FROM clinical_documents WHERE document_uuid='
        .$pdo->quote($created['document_uuid']))->fetchColumn(),true);
    if(($payload['signature_binding_status']['doctor']??'')!=='valid_bound_signature'
        || !str_contains((string)($payload['certificado_snapshot']['html']??''),
            $body['payload']['content']['preop']['planned_procedure']))
        throw new RuntimeException("$source snapshot mismatch");
    echo "$source draft/signature/stale/emission/snapshot PASS\n";
}
