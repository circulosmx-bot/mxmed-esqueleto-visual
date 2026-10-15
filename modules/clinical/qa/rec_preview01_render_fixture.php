<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_prescription_v2.php';
$mode=in_array($argv[1]??'', ['logo_and_data','data_only','hidden'],true)?$argv[1]:'logo_and_data';
$payload=[
 'patient_snapshot'=>['full_name'=>'Paciente de revisión visual','age'=>'42','sex'=>'F'],
 'physician_snapshot'=>['full_name'=>'Dra. Leticia Muñoz Romo','designation'=>'Médica cirujana','specialty'=>'Endocrinología',
  'license'=>'1234567','specialty_license'=>'7654321'],
 'professional_context'=>['logo_url'=>'/api/media/index.php/public/a1e44098-a0ef-403f-b51e-8bee88100ef8','consultorio'=>null],
 'professional_presentation'=>['version'=>2,'offices'=>[
  ['name'=>'Consultorio Principal','address'=>'Avenida de la Atención Médica 123, Aguascalientes, C.P. 20000','phones'=>['449 111 2233'],'is_primary'=>true,'display_order'=>1],
 ],'public_profile'=>['url'=>'https://mxmed.example.test/profiles/doctor.php?doctor_id=1','source'=>'published_profile_current_url']],
 'diagnostic_context'=>['mode'=>'diagnostico','text'=>'Contexto clínico de revisión.'],
 'prescription'=>['items'=>[['name'=>'Medicamento de prueba','presentation'=>'Tabletas','dose'=>'10 mg','route'=>'Oral',
  'frequency'=>'Cada 8 horas','duration'=>'7 días','quantity'=>'21 tabletas','instructions'=>'Después de alimentos.']],'observaciones'=>'Vigilar respuesta.'],
 'presentation'=>['version'=>2,'professional_header'=>$mode],
 'issue_date'=>'2026-10-14','signatures'=>['doctor'=>null],
];
echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main class="doc-base-sheet-frame">'.clinical_prescription_v2_render($payload).'</main></body></html>';
