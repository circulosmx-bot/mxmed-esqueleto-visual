<?php
declare(strict_types=1);
// Fixture writes only synthetic Media inputs; no grant/session/admission shortcuts.
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/ase-adm02-workflow-runtime.php';
staffWorkflowOwnedServices();
$owned=realpath(getenv('ASE_INT02A_OWNED'));
$p=new PDO('mysql:unix_socket='.$owned.'/mysql.sock;dbname='.getenv('MXMED_DB_NAME').';charset=utf8mb4','ase_fixture',getenv('ASE_INT02A_FIXTURE_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(realpath($p->query('SELECT @@datadir')->fetchColumn())!==$owned.'/mysql')throw new RuntimeException('owned_datadir_required');
foreach(['MXMED_PRIVATE_MEDIA_ROOT','MXMED_PUBLIC_MEDIA_ROOT'] as $key)if(dirname(realpath(getenv($key)))!==$owned)throw new RuntimeException('owned_media_root_required');
require_once __DIR__.'/../modules/media/private-bootstrap.php';
require_once __DIR__.'/../modules/media/tests/ReviewCandidateFixture.php';
require_once __DIR__.'/../modules/media/services/MediaReviewBatchService.php';
require_once __DIR__.'/../modules/media/services/PhysicianLogoReviewCandidateService.php';
require_once __DIR__.'/../modules/media/services/GalleryReviewCandidateService.php';
$records=[];
foreach([
 ['workspace_aurora','Aurora Ejemplo del Valle','Medicina general','Ciudad Ficticia Norte','Estado de Ensayo'],
 ['workspace_bruno','Bruno Ejemplo del Río','Pediatría','Ciudad Ficticia Sur','Estado de Ensayo']
] as [$doctor,$name,$specialty,$city,$state]){
 $p->prepare('INSERT INTO profiles_doctors(doctor_id,display_name,specialty_primary,profile_status) VALUES(?,?,?,?)')->execute([$doctor,$name,$specialty,'hidden']);
 $p->prepare('INSERT INTO consultorios(doctor_id,consultorio_id,titulo,municipio,estado) VALUES(?,?,?,?,?)')->execute([$doctor,$doctor.'_office','Consultorio ficticio',$city,$state]);
 foreach(['photo','logo','gallery','gallery'] as $i=>$kind){
  $upload=candidateFixture('png',240+$i*20,120+$i*20);
  if($kind==='logo'){
   // Opaque synthetic geometry makes the existing safe background-removal
   // consumer demonstrable. It depicts no person, brand or external reference.
   $im=imagecreatetruecolor(600,300);imagefill($im,0,0,imagecolorallocate($im,255,255,255));
   imagefilledrectangle($im,120,60,480,240,imagecolorallocate($im,20,60,140));
   imagefilledrectangle($im,210,120,390,180,imagecolorallocate($im,255,255,255));
   imagepng($im,$upload['tmp_name']);$im=null;
   copy($upload['tmp_name'],$owned.'/synthetic-logo.png');
  }
  try{
   $service=match($kind){'logo'=>new Media\Services\PhysicianLogoReviewCandidateService($p,mxmed_private_media_storage()),'gallery'=>new Media\Services\GalleryReviewCandidateService($p,mxmed_private_media_storage()),default=>new Media\Services\ProfilePhotoReviewCandidateService($p,mxmed_private_media_storage())};
   $service->upload($doctor,$upload);
  }finally{unlink($upload['tmp_name']);}
 }
 $p->prepare('UPDATE media_review_batches SET last_activity_at=DATE_SUB(CURRENT_TIMESTAMP(6),INTERVAL 31 MINUTE) WHERE owner_id=?')->execute([$doctor]);
 $records[]=['doctor_id'=>$doctor,'name'=>$name];
}
// A canonical profile with no reviewable work must not appear in staff search.
$p->prepare('INSERT INTO profiles_doctors(doctor_id,display_name) VALUES(?,?)')->execute(['workspace_out_of_scope','Oculto Fuera de Ámbito']);
$result=(new Media\Services\MediaReviewBatchService($p))->submitInactive();
if($result['submitted']!==2)throw new RuntimeException('synthetic_media_not_submitted');
$items=$p->query('SELECT submission_id,owner_id,purpose FROM media_review_submissions ORDER BY owner_id,purpose,submission_id')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['physicians'=>$records,'items'=>$items],JSON_THROW_ON_ERROR);
