<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/clinical_professional_presentation.php';
require_once __DIR__ . '/../../../api/_lib/clinical_prescription_v2.php';

function preview01(bool $ok,string $case):void{if(!$ok)throw new RuntimeException('FAIL '.$case);echo $case."=PASS\n";}

$previous=getenv('MXMED_PUBLIC_PROFILE_ORIGIN');
putenv('MXMED_PUBLIC_PROFILE_ORIGIN=https://mxmed.example.test');
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE profiles_doctors (doctor_id TEXT PRIMARY KEY,profile_status TEXT,is_public_candidate INTEGER)');
$pdo->exec('CREATE TABLE public_profile_seo_routes (entity_type TEXT,entity_id TEXT,canonical_path TEXT,status TEXT,route_enabled INTEGER,canonical_enabled INTEGER)');
$pdo->exec("INSERT INTO profiles_doctors VALUES ('doctor-qa','active',1)");
$pdo->exec("INSERT INTO public_profile_seo_routes VALUES ('doctor','doctor-qa','/medicos/doctora-qa','candidate',0,0)");
$current=clinical_professional_presentation_public_profile($pdo,'doctor-qa');
preview01($current===['url'=>'https://mxmed.example.test/profiles/doctor.php?doctor_id=doctor-qa',
    'source'=>'published_profile_current_url'],'REC_PREVIEW01_PUBLISHED_CURRENT_URL');
$pdoWithoutSeo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdoWithoutSeo->exec('CREATE TABLE profiles_doctors (doctor_id TEXT PRIMARY KEY,profile_status TEXT,is_public_candidate INTEGER)');
$pdoWithoutSeo->exec("INSERT INTO profiles_doctors VALUES ('doctor-qa','active',1)");
preview01(clinical_professional_presentation_public_profile($pdoWithoutSeo,'doctor-qa')===$current,
    'REC_PREVIEW01_PUBLISHED_URL_WITHOUT_SEO_READ_MODEL');
$pdo->exec("UPDATE public_profile_seo_routes SET status='active',route_enabled=1,canonical_enabled=1");
$canonical=clinical_professional_presentation_public_profile($pdo,'doctor-qa');
preview01($canonical===['url'=>'https://mxmed.example.test/medicos/doctora-qa',
    'source'=>'active_canonical_profile_route'],'REC_PREVIEW01_ACTIVE_CANONICAL_URL');
$pdo->exec("UPDATE profiles_doctors SET profile_status='hidden'");
preview01(clinical_professional_presentation_public_profile($pdo,'doctor-qa')===null,
    'REC_PREVIEW01_HIDDEN_PROFILE_NO_QR');
if($previous===false)putenv('MXMED_PUBLIC_PROFILE_ORIGIN');else putenv('MXMED_PUBLIC_PROFILE_ORIGIN='.$previous);

$base=[
 'patient_snapshot'=>['full_name'=>'Paciente QA','age'=>'42','sex'=>'F'],
 'physician_snapshot'=>['full_name'=>'Dra. Profesional QA','designation'=>'Médica cirujana','specialty'=>'Endocrinología',
   'license'=>'1234567','specialty_license'=>'7654321'],
 'professional_context'=>['logo_url'=>'/api/media/index.php/public/a1e44098-a0ef-403f-b51e-8bee88100ef8','consultorio'=>null],
 'professional_presentation'=>['version'=>2,'offices'=>[['name'=>'Consultorio autorizado','address'=>'Calle QA 1','phones'=>['449 111 2233'],
   'is_primary'=>true,'display_order'=>1]],'public_profile'=>$canonical],
 'diagnostic_context'=>['mode'=>'sin_diagnostico','text'=>''],
 'prescription'=>['items'=>[['name'=>'Medicamento QA','quantity'=>'10 tabletas']],'observaciones'=>''],
 'issue_date'=>'2026-10-14','signatures'=>['doctor'=>null],
];
foreach(['logo_and_data','data_only'] as $mode){
 $payload=$base;$payload['presentation']=['version'=>2,'professional_header'=>$mode];$html=clinical_prescription_v2_render($payload);
 preview01(str_contains($html,'Dra. Profesional QA')&&str_contains($html,'Consultorio autorizado')
   &&str_contains($html,'AGENDA UNA CITA')&&str_contains($html,'data-rx-public-profile-qr'),
   'REC_PREVIEW01_'.strtoupper($mode).'_IDENTITY');
 preview01(($mode==='logo_and_data')===str_contains($html,'alt="Logo médico"'),
   'REC_PREVIEW01_'.strtoupper($mode).'_LOGO_POLICY');
}
$hidden=$base;$hidden['presentation']=['version'=>2,'professional_header'=>'hidden'];$hiddenHtml=clinical_prescription_v2_render($hidden);
foreach(['Dra. Profesional QA','Endocrinología','1234567','7654321','Consultorio autorizado','449 111 2233','AGENDA UNA CITA','data-rx-public-profile-qr','doc-base-signature'] as $forbidden)
 preview01(!str_contains($hiddenHtml,$forbidden),'REC_PREVIEW01_HIDDEN_'.strtoupper(preg_replace('/\W+/','_',$forbidden)));
preview01(str_contains($hiddenHtml,'Paciente QA')&&str_contains($hiddenHtml,'Medicamento QA'),
 'REC_PREVIEW01_HIDDEN_CLINICAL_BODY');

$css=(string)file_get_contents(__DIR__.'/../../../assets/css/clinical-doc-base.css');
preview01(str_contains($css,'.rx-doc-patient-meta')&&str_contains($css,'.rx-doc-physician-name')
 &&str_contains($css,'max-width: 128px')&&str_contains($css,'font-family: "Asap"')
 &&str_contains($css,'asap-latin.woff2'),
 'REC_PREVIEW01_PRESENTATION_STYLES');
$presentationScript='assets/js/clinical/prescription-document-presentation.js';
foreach(['private-app.php','modules/clinical/ui/viewer.php','modules/clinical/ui/document.php'] as $surface)
 preview01(str_contains((string)file_get_contents(__DIR__.'/../../../'.$surface),$presentationScript),
   'REC_PREVIEW01_QR_HYDRATION_'.strtoupper(str_replace(['.php','/'],['','_'],$surface)));
echo "REC_PREVIEW01_CONTRACT=PASS\n";
