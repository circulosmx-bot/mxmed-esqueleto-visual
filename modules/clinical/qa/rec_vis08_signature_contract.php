<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/clinical_prescription_binding.php';

function recVis08Assert(bool $condition, string $name): void
{
    if (!$condition) throw new RuntimeException($name);
    echo $name."=PASS\n";
}

$image = imagecreatetruecolor(40, 18);
$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 20, 35, 50);
imagefill($image, 0, 0, $white);
imagesetthickness($image, 3);
imageline($image, 3, 13, 35, 4, $black);
ob_start(); imagepng($image); $bytes = (string)ob_get_clean();
$dataUrl = 'data:image/png;base64,'.base64_encode($bytes);
$digest = clinical_consent_binding_artifact_digest($dataUrl);
recVis08Assert($digest !== '', 'SIGNATURE_FIXTURE_VALID');

$body = [
    'draft_ref'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'expected_version'=>4,
    'context'=>['patient_id'=>'patient-1'],
    'payload'=>[
        'contract_version'=>2,
        'prescription'=>['items'=>[['name'=>'Losartán','presentation'=>'50 mg','dose'=>'1','route'=>'Oral',
            'frequency'=>'Cada 24 horas','duration'=>'30 días','quantity'=>'30','instructions'=>'Tomar diariamente']],
            'observaciones'=>''],
        'diagnostic_context'=>['mode'=>'diagnostico','text'=>'Hipertensión'],
        'patient_snapshot'=>['id'=>'patient-1','full_name'=>'Paciente QA','age'=>'42','sex'=>'F'],
        'physician_snapshot'=>['user_id'=>'user-1','doctor_id'=>'1','full_name'=>'Dra. Leticia Muñoz Romo',
            'prefix'=>'Dra.','designation'=>'Médica cirujana','specialty'=>'Medicina interna','license'=>'123','specialty_license'=>'456'],
        'professional_context'=>['logo_url'=>'/api/media/index.php/public/11111111-1111-4111-8111-111111111111','consultorio'=>null],
        'professional_presentation'=>['version'=>2,'offices'=>[]],
        'presentation'=>['version'=>2,'professional_header'=>'logo_and_data'],
        'encounter_reference'=>null,'issue_date'=>'2026-10-14','signatures'=>['doctor'=>null],
    ],
];
$fingerprint = clinical_prescription_binding_fingerprint($body);
$signature = [
    'type'=>'drawn','role'=>'doctor','source'=>'local_canvas','image_data'=>$dataUrl,
    'signer_name'=>'Dra. Leticia Muñoz Romo','signed_at'=>'2026-10-14T12:00:00-06:00',
    'binding'=>[
        'version'=>1,'document_type'=>'prescription','document_uuid'=>$body['draft_ref'],
        'document_version'=>4,'role'=>'doctor','source'=>'local_canvas',
        'authority'=>clinical_prescription_binding_authority($body,'1'),'artifact_digest'=>$digest,
        'content_fingerprint'=>$fingerprint,'applied_at'=>'2026-10-14T12:00:00-06:00',
    ],
];
$body['payload']['signatures']['doctor']=$signature;
recVis08Assert(clinical_prescription_binding_classify($body,'1') === 'valid_bound_signature', 'CURRENT_PRESENTATION_SIGNATURE_VALID');
$body['payload']['presentation']=['version'=>2,'professional_header'=>'data_only'];
recVis08Assert(clinical_prescription_binding_classify($body,'1') === 'stale_or_unverified_signature', 'PRESENTATION_CHANGE_REJECTS_STALE_SIGNATURE');
echo "REC_VIS08_SIGNATURE_CONTRACT=PASS\n";
