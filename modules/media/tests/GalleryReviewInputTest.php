<?php
declare(strict_types=1);
$path=tempnam(sys_get_temp_dir(),'mr10-camera-');
putenv('PHOTO_MODERN_FIXTURE='.$path);
try {
    // Exercise the same bounded decoder, including oversized-source limits.
    require __DIR__.'/ProfilePhotoInputLimitsTest.php';
    require __DIR__.'/GalleryReviewFixture.php';
    $doctor=mr10Doctor();$p=mr5Pdo();[$private]=mr5Storage();
    $hash=hash_file('sha256',$path);
    (new Media\Services\GalleryReviewCandidateService($p,$private))->upload($doctor,['tmp_name'=>$path,'name'=>'camera.jpg','type'=>'image/jpeg','error'=>0]);
    $id=$p->query("SELECT submission_id FROM media_review_submissions WHERE owner_id='$doctor' AND purpose='DOCTOR_GALLERY'")->fetchColumn();
    $files=mr6Rows($p,$id);
    checkPhoto(array_keys($files)===['SOURCE','REVIEW'],'gallery source/review only');
    checkPhoto($files['SOURCE']['checksum_sha256']===$hash,'gallery exact source retained');
    checkPhoto((int)$files['SOURCE']['width']*(int)$files['SOURCE']['height']===12000000,'gallery 12 MP intake');
    $r=$files['REVIEW'];checkPhoto(max($r['width'],$r['height'])<=800&&$r['byte_size']<=153600,'gallery output bounded');
    $object=$private->openReadStream($r['storage_key']);try{$bytes=stream_get_contents($object['stream']);}finally{fclose($object['stream']);}
    checkPhoto(!str_contains($bytes,'GPSLatitude')&&!str_contains($bytes,'Device=QA'),'gallery review metadata stripped');
    echo "MR10_CAMERA_INPUT_QA=PASS\n";
} finally {putenv('PHOTO_MODERN_FIXTURE');if(is_file($path))unlink($path);}
