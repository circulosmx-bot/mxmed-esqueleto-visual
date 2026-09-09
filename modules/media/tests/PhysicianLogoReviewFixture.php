<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/MediaReviewInterventionFixture.php';
require_once __DIR__.'/../services/PhysicianLogoReviewCandidateService.php';
require_once __DIR__.'/../services/PhysicianLogoApprovalService.php';
require_once __DIR__.'/../services/PublicLogoMediaService.php';
function mr7Candidate(PDO $p,string $label='Logotipo sintético MR7',int $width=900,int $height=300):array {
    [$private,$public]=mr5Storage();$doctor='mr7_'.bin2hex(random_bytes(6));
    $p->prepare('INSERT INTO profiles_doctors(doctor_id,display_name) VALUES(?,?)')->execute([$doctor,$label]);
    $old=(new Media\Services\PublicLogoMediaService($p,$public,new Media\Services\GdPublicLogoProcessor(),new Media\Repositories\MediaAssetsRepository($p),new Media\Repositories\LogoReferenceRepository($p)))->replacePhysicianLogo($doctor,mr6File('png',240,160));
    $upload=mr6File('png',$width,$height);$sourceHash=hash_file('sha256',$upload['tmp_name']);
    $service=new Media\Services\PhysicianLogoReviewCandidateService($p,$private);
    try{$service->upload($doctor,$upload);}finally{unlink($upload['tmp_name']);}
    return ['doctor'=>$doctor,'id'=>$service->current($doctor)['submission_id'],'old'=>$old,'source_hash'=>$sourceHash];
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')==='candidate')echo json_encode(mr7Candidate(mr5Pdo(),$argv[2]??'Logotipo sintético MR7',(int)($argv[3]??900),(int)($argv[4]??300)));
}
