<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/api/_lib/clinical_prescription_v2.php';

function std03aFooterAssert(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('FAIL ' . $label);
    echo "PASS {$label}\n";
}

$body = [
    'draft_ref'=>'123e4567-e89b-42d3-a456-426614174000',
    'expected_version'=>7,
    'context'=>['patient_id'=>'patient-a'],
    'payload'=>[
        'contract_version'=>2,
        'patient_snapshot'=>['id'=>'patient-a','full_name'=>'Paciente Prueba','age'=>'42','sex'=>'F'],
        'physician_snapshot'=>['user_id'=>'user-a','doctor_id'=>'doctor-a','full_name'=>'Dra. Prueba',
            'prefix'=>'Dra.','designation'=>'Médica cirujana','specialty'=>'Medicina interna',
            'license'=>'1234567','specialty_license'=>'7654321'],
        'professional_context'=>['logo_url'=>'','consultorio'=>['name'=>'Consulta vinculada','address'=>'Dirección vinculada']],
        'diagnostic_context'=>['mode'=>'diagnostico','text'=>'Diagnóstico de prueba'],
        'prescription'=>['items'=>[['name'=>'Medicamento','presentation'=>'Tabletas','dose'=>'10 mg',
            'route'=>'Oral','frequency'=>'Cada 8 horas','duration'=>'5 días','quantity'=>'15',
            'instructions'=>'Después de alimentos','private_notes'=>'Nunca visible']], 'observaciones'=>'Observación'],
        'presentation'=>['version'=>1,'professional_header'=>'shown'],
        'encounter_reference'=>['encounter_id'=>10,'appointment_id'=>'appointment-a'],
        'issue_date'=>'2026-10-14',
        'signatures'=>['doctor'=>null],
    ],
];

$v1 = clinical_prescription_binding_projection($body);
std03aFooterAssert($v1['version'] === 1 && !isset($v1['professional_presentation']),
    'legacy fingerprint projection remains V1');
$v1Fingerprint = clinical_prescription_binding_fingerprint($body);
std03aFooterAssert($v1Fingerprint === 'b2d98a7c200c4feadbb71d27c034fea8a1a136626c4eb3110b89a6ddb071200c',
    'legacy V1 fingerprint golden vector is unchanged');

$longAddress = 'Avenida de la Atención Médica Integral 1234, interior Torre B piso 12, Col. Desarrollo Profesional, Aguascalientes, Aguascalientes, C.P. 20999';
$body['payload']['professional_presentation'] = ['version'=>2,'offices'=>[
    ['name'=>'Consultorio Principal','address'=>$longAddress,'phones'=>['449 111 2233'], 'is_primary'=>true,'display_order'=>2],
    ['name'=>'Consultorio Norte','address'=>'Calle Norte 20, Col. Centro, Aguascalientes, Aguascalientes, C.P. 20000','phones'=>[], 'is_primary'=>false,'display_order'=>1],
    ['name'=>'Consultorio Sur','address'=>'Calle Sur 30, Col. Jardines, Aguascalientes, Aguascalientes, C.P. 20100','phones'=>['449 333 4455'], 'is_primary'=>false,'display_order'=>3],
]];
$v2 = clinical_prescription_binding_projection($body);
std03aFooterAssert($v2['version'] === 2 && count($v2['professional_presentation']['offices']) === 3,
    'footer participates in explicit V2 fingerprint projection');
$v2Fingerprint = clinical_prescription_binding_fingerprint($body);
$mutated = $body;
$mutated['payload']['professional_presentation']['offices'][0]['address'] .= ' cambio';
std03aFooterAssert(!hash_equals($v2Fingerprint, clinical_prescription_binding_fingerprint($mutated)),
    'footer mutation invalidates the V2 content fingerprint');

$html = clinical_prescription_v2_render($body['payload']);
std03aFooterAssert(substr_count($html, 'class="rx-doc-professional-location"') === 3,
    'renderer emits at most three logical office rows');
std03aFooterAssert(str_contains($html, htmlspecialchars($longAddress, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
    'renderer preserves the complete long address');
std03aFooterAssert(str_contains($html, '449 111 2233') && str_contains($html, '449 333 4455'),
    'renderer emits frozen published regular phones');
std03aFooterAssert(!str_contains($html, 'Dirección vinculada') && !str_contains($html, 'Consulta vinculada'),
    'legacy appointment location cannot bypass document publication opt-in');
std03aFooterAssert(!str_contains($html, 'private_notes') && !str_contains($html, 'Nunca visible'),
    'private prescription notes stay absent');
std03aFooterAssert(!str_contains($html, '<img class="qr') && !str_contains($html, 'Perfil público')
    && !str_contains($html, 'AGENDA UNA CITA'), 'inactive public-profile QR and caption are not invented');
std03aFooterAssert(strpos($html, 'rx-doc-professional-footer') > strpos($html, 'doc-base-signature'),
    'professional footer follows the signature in canonical output');

$historical = $body['payload'];
unset($historical['professional_presentation']);
$historicalHtml = clinical_prescription_v2_render($historical);
std03aFooterAssert(!str_contains($historicalHtml, 'rx-doc-professional-footer'),
    'historical payload without a frozen footer remains footer-free');
$source = (string)file_get_contents($root . '/api/_lib/clinical_prescription_v2.php');
std03aFooterAssert(str_contains($source, "'prescription_snapshot'] = ['version'=>2"),
    'Prescription snapshot version 2 semantics remain established');
$routeSource = (string)file_get_contents($root . '/api/clinical/index.php');
$appSource = (string)file_get_contents($root . '/assets/js/app.js');
$viewerSource = (string)file_get_contents($root . '/modules/clinical/ui/viewer.php');
std03aFooterAssert(str_contains($routeSource,
    "'patient_snapshot','physician_snapshot','professional_context','professional_presentation','issue_date','encounter_reference'"),
    'final review reuses the frozen professional presentation');
std03aFooterAssert(str_contains($appSource, "rxPreviewHtml.innerHTML = data.html || ''")
    && str_contains($appSource, "getElementById('ne_rx_final_html').innerHTML=data.html||''"),
    'stage 3 and stage 5 both display canonical server rendering');
std03aFooterAssert(str_contains($viewerSource, "\$payload['prescription_snapshot']['html']")
    && str_contains($viewerSource, 'prescription-v2-viewer-snapshot'),
    'issued viewer and print use the frozen Prescription V2 snapshot');

echo 'V1_FINGERPRINT=' . $v1Fingerprint . "\n";
echo 'V2_FINGERPRINT=' . $v2Fingerprint . "\n";
echo "DOC_REC_STD03A_FOOTER=PASS\n";
