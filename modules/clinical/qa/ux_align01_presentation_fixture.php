<?php
declare(strict_types=1);

/** Renderer-only synthetic snapshots. Never composes a DB, session, writer or signing service. */
if (PHP_SAPI !== 'cli') exit(1);
require_once dirname(__DIR__,3).'/api/_lib/clinical_prescription_v2.php';

$medications=[];
foreach (['A','B'] as $name) $medications[]=[
    'name'=>'Medicamento sintético '.$name,'presentation'=>'Presentación sintética de revisión',
    'dose'=>'Cantidad sintética '.$name,'route'=>'Vía sintética','frequency'=>'Periodicidad sintética',
    'duration'=>'Duración sintética','quantity'=>'Cantidad para revisión',
    'instructions'=>'Texto sintético de pauta '.$name.'. No constituye una indicación médica.',
    'private_notes'=>'PRIVATE_NOTE_SENTINEL_NEVER_DISPLAY',
];
$base=[
    'contract_version'=>2,'status'=>'draft',
    'patient_snapshot'=>['id'=>'synthetic-patient','full_name'=>'Paciente sintético UX-ALIGN01','age'=>'42','sex'=>'F'],
    'physician_snapshot'=>['user_id'=>'synthetic-person','doctor_id'=>'synthetic-profile',
        'full_name'=>'Dra. Profesional Sintética','prefix'=>'Dra.','designation'=>'Médica de revisión sintética',
        'specialty'=>'Especialidad sintética','license'=>'QA-CEDULA-NO-VALIDA','specialty_license'=>'QA-ESPECIALIDAD-NO-VALIDA',
        'personal_mobile'=>'PERSONAL_MOBILE_SENTINEL_NEVER_DISPLAY','emergency_phone'=>'EMERGENCY_SENTINEL_NEVER_DISPLAY'],
    'professional_context'=>['logo_url'=>'','consultorio'=>['name'=>'UNPUBLISHED_LOCATION_SENTINEL','address'=>'UNPUBLISHED_ADDRESS_SENTINEL']],
    'diagnostic_context'=>['mode'=>'diagnostico','text'=>'Contexto clínico sintético para revisar jerarquía visual.'],
    'prescription'=>['items'=>$medications,'observaciones'=>'Observaciones sintéticas de legibilidad; sin validez clínica.'],
    'presentation'=>['version'=>2,'professional_header'=>'data_only'],
    'issue_date'=>'2026-10-16','signatures'=>['doctor'=>null],
    // Same frozen, opted-in office projection shape used by the canonical renderer.
    // These strings are synthetic; this fixture does not assert real publication authority.
    'professional_presentation'=>['version'=>2,'offices'=>[
        ['name'=>'Consultorio sintético principal','address'=>'Domicilio sintético extenso para evaluar saltos de línea, edificio de revisión visual, piso de prueba, municipio sintético, C.P. 00000',
            'phones'=>['TEL-SINTETICO-01'],'is_primary'=>true,'display_order'=>1],
        ['name'=>'Consultorio sintético secundario','address'=>'Domicilio sintético secundario',
            'phones'=>['TEL-SINTETICO-02'],'is_primary'=>false,'display_order'=>2],
    ]],
];
$cases=['data_only'=>$base];
$cases['logo_and_data']=$base;
$cases['logo_and_data']['presentation']['professional_header']='logo_and_data';
// No invented public logo is required: absent approved media keeps the original fallback.
$cases['hidden']=$base;$cases['hidden']['presentation']['professional_header']='hidden';
$cases['missing_optional']=$base;
foreach (['designation','specialty','license','specialty_license'] as $key) unset($cases['missing_optional']['physician_snapshot'][$key]);
$cases['missing_optional']['professional_presentation']=['version'=>2,'offices'=>[]];
$cases['missing_optional']['diagnostic_context']=['mode'=>'sin_diagnostico','text'=>''];
$cases['missing_optional']['prescription']['observaciones']='';
$cases['legacy']=$base;unset($cases['legacy']['professional_presentation']);
$cases['legacy']['professional_context']['consultorio']=null;
$cases['legacy']['presentation']=['version'=>1,'professional_header'=>'shown'];
$cases['long']=$base;$cases['long']['prescription']['items']=[];
for ($i=1;$i<=24;$i++)$cases['long']['prescription']['items'][]=array_replace($medications[0],['name'=>'Medicamento sintético '.str_pad((string)$i,2,'0',STR_PAD_LEFT)]);
$out=[];
foreach ($cases as $name=>$payload) {
    $before=json_encode($payload,JSON_THROW_ON_ERROR);
    $html=clinical_prescription_v2_render($payload);
    if (json_encode($payload,JSON_THROW_ON_ERROR)!==$before)throw new RuntimeException('renderer_mutated_fixture');
    foreach (['PRIVATE_NOTE_SENTINEL','PERSONAL_MOBILE_SENTINEL','EMERGENCY_SENTINEL','UNPUBLISHED_LOCATION_SENTINEL','UNPUBLISHED_ADDRESS_SENTINEL'] as $sentinel)
        if(str_contains($html,$sentinel))throw new RuntimeException('private_or_unpublished_field_rendered');
    $body=['draft_ref'=>'00000000-0000-4000-8000-000000000001','expected_version'=>1,'context'=>['patient_id'=>'synthetic-patient'],'payload'=>$payload];
    $out[$name]=['html'=>$html,'html_sha256'=>hash('sha256',$html),'payload_sha256'=>hash('sha256',$before),
        'content_fingerprint'=>clinical_prescription_binding_fingerprint($body),'medication_count'=>count($payload['prescription']['items']),
        'header_expected'=>$name!=='hidden','footer_expected'=>!in_array($name,['hidden','missing_optional','legacy'],true)];
}
echo json_encode(['result'=>'PASS','source'=>'EXPLICIT_SYNTHETIC_FROZEN_SNAPSHOT_RENDER_ONLY','cases'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
