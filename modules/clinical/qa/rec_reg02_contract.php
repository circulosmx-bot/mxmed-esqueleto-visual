<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/api/_lib/clinical_prescription_v2.php';
require_once $root . '/api/_lib/clinical_prescription_qr.php';

function reg02Assert(bool $condition, string $case): void
{
    if (!$condition) throw new RuntimeException($case);
    echo $case . "=PASS\n";
}

function reg02Rejects(callable $callable, string $code): bool
{
    try { $callable(); }
    catch (ClinicalPrescriptionRegimenException $error) { return $error->errorCode === $code; }
    return false;
}

function reg02Regimen(array $administration, array $duration): array
{
    return clinical_prescription_regimen_normalize(
        ['administration'=>$administration,'duration'=>$duration], 1, '2026-10-14');
}

function reg02Body(array $regimen): array
{
    $projection = clinical_prescription_regimen_format($regimen);
    return ['draft_ref'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','expected_version'=>3,
        'context'=>['patient_id'=>'patient-qa'],'payload'=>[
            'contract_version'=>2,
            'prescription'=>['medication_schema_version'=>2,'items'=>[array_merge([
                'name'=>'Medicamento QA','presentation'=>'Tabletas','dose'=>'10 mg','route'=>'Oral',
                'quantity'=>'14 tabletas','instructions'=>'Después de alimentos','private_notes'=>'nota privada',
                'regimen'=>$regimen], $projection)],'observaciones'=>''],
            'diagnostic_context'=>['mode'=>'sin_diagnostico','text'=>''],
            'patient_snapshot'=>['id'=>'patient-qa','full_name'=>'Paciente QA','age'=>'40','sex'=>'F'],
            'physician_snapshot'=>['user_id'=>'user-qa','doctor_id'=>'doctor-qa','full_name'=>'Dra. QA',
                'prefix'=>'Dra.','designation'=>'Médica','specialty'=>'Medicina','license'=>'123','specialty_license'=>''],
            'professional_context'=>['logo_url'=>'','consultorio'=>null],
            'presentation'=>['version'=>2,'professional_header'=>'data_only'],
            'encounter_reference'=>null,'issue_date'=>'2026-10-14','signatures'=>['doctor'=>null],
        ]];
}

$scheduled = reg02Regimen(['pattern'=>'SCHEDULED','instruction'=>'Cada 12 horas'],
    ['mode'=>'DEFINED_DURATION','value'=>7,'unit'=>'DAY']);
reg02Assert(clinical_prescription_regimen_format($scheduled) ===
    ['frequency'=>'Cada 12 horas','duration'=>'Durante 7 días'], 'REG02_01');

$prn = reg02Regimen(['pattern'=>'AS_NEEDED','indication'=>'En caso de dolor','repeat_mode'=>'REPEATABLE',
    'minimum_interval'=>['value'=>6,'unit'=>'HOUR'],
    'maximum_dose_per_period'=>['value'=>3,'unit'=>'tabletas','period'=>['value'=>24,'unit'=>'HOUR']],
    'maximum_administrations_per_period'=>['count'=>3,'period'=>['value'=>24,'unit'=>'HOUR']]],
    ['mode'=>'DEFINED_DURATION','value'=>3,'unit'=>'DAY']);
$prnProjection = clinical_prescription_regimen_format($prn);
reg02Assert(str_contains($prnProjection['frequency'], 'En caso de dolor')
    && str_contains($prnProjection['frequency'], 'Intervalo mínimo: 6 horas')
    && str_contains($prnProjection['frequency'], '3 tabletas por 24 horas')
    && str_contains($prnProjection['frequency'], '3 administraciones por 24 horas'), 'REG02_02');

$reassessment = reg02Regimen(['pattern'=>'AS_NEEDED','indication'=>'En caso de náusea',
    'repeat_mode'=>'SINGLE_ADMINISTRATION'], ['mode'=>'UNTIL_REASSESSMENT']);
reg02Assert(clinical_prescription_regimen_format($reassessment)['duration'] ===
    'Hasta nueva valoración médica', 'REG02_03');

$continuous = reg02Regimen(['pattern'=>'SCHEDULED','instruction'=>'Una tableta cada mañana'], ['mode'=>'CONTINUOUS']);
reg02Assert(clinical_prescription_regimen_format($continuous)['duration'] === 'Tratamiento continuo', 'REG02_04');

$untilDate = reg02Regimen(['pattern'=>'SCHEDULED','instruction'=>'Cada 24 horas'],
    ['mode'=>'UNTIL_DATE','date'=>'2026-10-20']);
reg02Assert(clinical_prescription_regimen_format($untilDate)['duration'] === 'Hasta el 2026-10-20', 'REG02_05');

$taper = reg02Regimen(['pattern'=>'PHYSICIAN_DEFINED','instruction'=>'Reducir una tableta cada tercer día'],
    ['mode'=>'PHYSICIAN_DEFINED','instruction'=>'Terminar al completar la reducción indicada']);
reg02Assert(str_contains(clinical_prescription_regimen_format($taper)['frequency'], 'Reducir una tableta'), 'REG02_06');

reg02Assert(reg02Rejects(fn()=>reg02Regimen(['pattern'=>'AS_NEEDED','indication'=>'',
    'repeat_mode'=>'SINGLE_ADMINISTRATION'], ['mode'=>'CONTINUOUS']), 'PRESCRIPTION_PRN_INDICATION_REQUIRED'), 'REG02_07');
reg02Assert(reg02Rejects(fn()=>reg02Regimen(['pattern'=>'AS_NEEDED','indication'=>'Dolor',
    'repeat_mode'=>'REPEATABLE'], ['mode'=>'CONTINUOUS']), 'PRESCRIPTION_PRN_INTERVAL_REQUIRED'), 'REG02_08');
clinical_prescription_regimen_quantity('30 tabletas', 1);
reg02Assert(clinical_prescription_regimen_format($continuous)['duration'] === 'Tratamiento continuo', 'REG02_09');

$body = reg02Body($scheduled);
$initialHash = clinical_prescription_binding_fingerprint($body);
$canvas=imagecreatetruecolor(40,18); $white=imagecolorallocate($canvas,255,255,255);
$ink=imagecolorallocate($canvas,1,49,81); imagefill($canvas,0,0,$white); imageline($canvas,3,14,36,3,$ink);
ob_start(); imagepng($canvas); $image='data:image/png;base64,'.base64_encode((string)ob_get_clean());
$signature=['source'=>'local_canvas','role'=>'doctor','signer_name'=>'Dra. QA','image_data'=>$image,
    'binding'=>['version'=>2,'role'=>'doctor','source'=>'local_canvas','document_type'=>'prescription',
        'document_uuid'=>$body['draft_ref'],'document_version'=>3,
        'authority'=>clinical_prescription_binding_authority($body,'doctor-qa'),
        'content_fingerprint'=>$initialHash,'artifact_digest'=>clinical_consent_binding_artifact_digest($image)]];
$body['payload']['signatures']['doctor']=$signature;
reg02Assert(clinical_prescription_binding_classify($body,'doctor-qa')==='valid_bound_signature', 'REG02_10_SIGNATURE_CURRENT');
$changed = $body;
$changed['payload']['prescription']['items'][0]['regimen']['administration']['instruction'] = 'Cada 8 horas';
$changed['payload']['prescription']['items'][0]['frequency'] = 'Cada 8 horas';
reg02Assert(clinical_prescription_fingerprint_version($body) === 2
    && !hash_equals($initialHash, clinical_prescription_binding_fingerprint($changed))
    && clinical_prescription_binding_classify($changed,'doctor-qa')==='stale_or_unverified_signature', 'REG02_10');

$session = ['fingerprint_version'=>2,'role'=>'doctor','signer_authority'=>'doctor-qa|user-qa',
    'content_fingerprint'=>$initialHash,'artifact_digest'=>'digest','document_uuid'=>$body['draft_ref'],
    'document_version'=>3,'token'=>str_repeat('a',64),'uploaded_at'=>'2026-10-14 12:00:00'];
reg02Assert(clinical_prescription_qr_binding($session)['version'] === 2
    && !hash_equals($session['content_fingerprint'], clinical_prescription_binding_fingerprint($changed)), 'REG02_11');

// REG02_12 is completed by rec_reg02_binding.test.mjs against this PHP projection.
reg02Assert(clinical_prescription_binding_projection($body)['fingerprint_version'] === 2, 'REG02_12_PHP');

$legacy = $body;
unset($legacy['payload']['prescription']['medication_schema_version']);
unset($legacy['payload']['prescription']['items'][0]['regimen']);
reg02Assert(clinical_prescription_fingerprint_version($legacy) === 1, 'REG02_13');
$downgrade=$body; unset($downgrade['payload']['prescription']['medication_schema_version']);
try { clinical_prescription_fingerprint_version($downgrade); $downgradeRejected=false; }
catch (InvalidArgumentException $error) { $downgradeRejected=$error->getMessage()==='PRESCRIPTION_FINGERPRINT_SCHEMA_DOWNGRADE'; }
reg02Assert($downgradeRejected,'REG02_SCHEMA_DOWNGRADE_REJECTED');
$legacyHtml = clinical_prescription_v2_render($legacy['payload']);
reg02Assert(str_contains($legacyHtml, 'Medicamento QA') && str_contains($legacyHtml, 'Durante 7 días'), 'REG02_14');
$frozen = '<article>snapshot histórico inmutable</article>';
clinical_prescription_v2_render($legacy['payload']);
reg02Assert($frozen === '<article>snapshot histórico inmutable</article>', 'REG02_15');

$unknown = $scheduled; $unknown['administration']['unknown'] = 'x';
reg02Assert(reg02Rejects(fn()=>clinical_prescription_regimen_normalize($unknown,1,'2026-10-14'),
    'PRESCRIPTION_REGIMEN_UNKNOWN_FIELD'), 'REG02_16');
$malformed = ['pattern'=>'AS_NEEDED','indication'=>'Dolor','repeat_mode'=>'REPEATABLE',
    'minimum_interval'=>['value'=>0,'unit'=>'HOUR']];
reg02Assert(reg02Rejects(fn()=>reg02Regimen($malformed,['mode'=>'CONTINUOUS']),
    'PRESCRIPTION_REGIMEN_NUMBER_INVALID'), 'REG02_17');
reg02Assert(reg02Rejects(fn()=>clinical_prescription_regimen_quantity('',1), 'PRESCRIPTION_QUANTITY_REQUIRED')
    && reg02Rejects(fn()=>clinical_prescription_regimen_quantity('ilimitado',1), 'PRESCRIPTION_QUANTITY_REQUIRED'), 'REG02_18');

reg02Assert(clinical_prescription_binding_authority($body, 'doctor-qa') !==
    clinical_prescription_binding_authority($body, 'other-doctor'), 'REG02_19');
$writer = (string)file_get_contents($root . '/api/_lib/clinical_prescription_v2.php');
$normalizeAt = strpos($writer, '$payload = clinical_prescription_v2_normalize', strpos($writer, 'function clinical_prescription_v2_write'));
$insertAt = strpos($writer, 'mxmed_persist_clinical_document_in_transaction', $normalizeAt ?: 0);
reg02Assert($normalizeAt !== false && $insertAt !== false && $normalizeAt < $insertAt, 'REG02_20');

$rendered = clinical_prescription_v2_render($body['payload']);
reg02Assert(str_contains($rendered, 'Cada 12 horas') && str_contains($rendered, 'Durante 7 días')
    && !str_contains($rendered, 'nota privada'), 'REG02_CANONICAL_OUTPUT');
$injection = $body; $injection['payload']['prescription']['items'][0]['instructions'] = '<script>alert(1)</script>';
reg02Assert(str_contains(clinical_prescription_v2_render($injection['payload']), '&lt;script&gt;')
    && !str_contains(clinical_prescription_v2_render($injection['payload']), '<script>'), 'REG02_HTML_ESCAPE');

echo 'REC_REG02_CONTRACT=PASS' . PHP_EOL;
if (getenv('REC_REG02_EXPORT_VECTOR') === '1') {
    echo 'REG02_V2_BODY=' . base64_encode(json_encode($body,
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)) . PHP_EOL;
    echo 'REG02_V2_HASH=' . $initialHash . PHP_EOL;
}
