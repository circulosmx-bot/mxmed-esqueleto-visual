<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/api/_lib/clinical_prescription_v2.php';

$long = ($argv[1] ?? '') === 'long';
$items = [];
$total = $long ? 24 : 2;
for ($index = 1; $index <= $total; $index++) {
    $items[] = [
        'name'=>'Medicamento de prueba ' . $index,
        'presentation'=>'Tabletas de liberación prolongada',
        'dose'=>'10 mg', 'route'=>'Oral', 'frequency'=>'Cada 8 horas',
        'duration'=>'7 días', 'quantity'=>'21 tabletas',
        'instructions'=>'Tomar después de alimentos y mantener hidratación adecuada.',
    ];
}
$payload = [
    'patient_snapshot'=>['full_name'=>'Paciente de revisión visual','age'=>'42','sex'=>'F'],
    'physician_snapshot'=>['full_name'=>'Dra. Leticia Muñoz Romo','designation'=>'Médica cirujana',
        'specialty'=>'Medicina interna','license'=>'1234567','specialty_license'=>'7654321'],
    'professional_context'=>['logo_url'=>'','consultorio'=>null],
    'diagnostic_context'=>['mode'=>'diagnostico','text'=>'Contexto clínico de revisión visual.'],
    'prescription'=>['items'=>$items,'observaciones'=>'Mantener vigilancia clínica y acudir a revisión según indicación médica.'],
    'presentation'=>['version'=>1,'professional_header'=>'shown'],
    'issue_date'=>'2026-10-14','signatures'=>['doctor'=>null],
    'professional_presentation'=>['version'=>2,'offices'=>[
        ['name'=>'Consultorio Principal','address'=>'Avenida de la Atención Médica Integral 1234, interior Torre B piso 12, Col. Desarrollo Profesional, Aguascalientes, Aguascalientes, C.P. 20999','phones'=>['449 111 2233'],'is_primary'=>true,'display_order'=>2],
        ['name'=>'Consultorio Norte','address'=>'Calle Norte 20, Col. Centro, Aguascalientes, Aguascalientes, C.P. 20000','phones'=>[],'is_primary'=>false,'display_order'=>1],
        ['name'=>'Consultorio Sur','address'=>'Calle Sur 30, Col. Jardines, Aguascalientes, Aguascalientes, C.P. 20100','phones'=>['449 333 4455'],'is_primary'=>false,'display_order'=>3],
    ]],
];

echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<style>html,body{margin:0;background:#eef2f5}.doc-base-sheet-frame{box-sizing:border-box}</style></head><body>'
    . '<main class="doc-base-sheet-frame">' . clinical_prescription_v2_render($payload) . '</main></body></html>';
