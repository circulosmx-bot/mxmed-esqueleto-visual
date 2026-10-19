<?php
declare(strict_types=1);
// CLI-only consumer input. No staff admission, grant or session mutation here.
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
$doctor='oper09_fictional_profile';
$p->prepare('INSERT INTO profiles_doctors(doctor_id,display_name) VALUES(?,?)')->execute([$doctor,'Perfil ficticio · imagen artificial']);
$upload=candidateFixture();
try{$id=(new Media\Services\ProfilePhotoReviewCandidateService($p,mxmed_private_media_storage()))->upload($doctor,$upload);}finally{unlink($upload['tmp_name']);}
// Model an aged, entirely synthetic media batch; the existing inactivity executor
// submits and audits it. Advisor authorization is NEVER manufactured by this fixture.
$p->prepare('UPDATE media_review_batches SET last_activity_at=DATE_SUB(CURRENT_TIMESTAMP(6),INTERVAL 31 MINUTE) WHERE owner_id=?')->execute([$doctor]);
$result=(new Media\Services\MediaReviewBatchService($p))->submitInactive();
if($result['submitted']!==1)throw new RuntimeException('synthetic_media_not_submitted');
echo json_encode(['submission_id'=>$id]);
