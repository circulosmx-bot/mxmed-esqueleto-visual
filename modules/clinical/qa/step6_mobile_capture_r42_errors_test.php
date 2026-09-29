<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../api/_lib/clinical_capture_upload_errors.php';
foreach ([1=>'UPLOAD_TOO_LARGE',2=>'UPLOAD_TOO_LARGE',3=>'UPLOAD_PARTIAL',4=>'UPLOAD_NO_FILE',6=>'UPLOAD_NO_TMP_DIR',7=>'UPLOAD_CANT_WRITE',8=>'UPLOAD_EXTENSION_BLOCKED'] as $error=>$code) {
    $result=clinical_capture_upload_failure(['error'=>$error,'size'=>0],100,'40M');
    if ($result['code']!==$code || $result['upload_error']!==$error) throw new RuntimeException('Wrong PHP upload error mapping');
}
if (clinical_capture_upload_failure(['error'=>0,'size'=>26214400],26215000,'40M')!==null) throw new RuntimeException('25 MB boundary rejected');
if (clinical_capture_upload_failure(['error'=>0,'size'=>26214401],26215001,'40M')['code']!=='UPLOAD_TOO_LARGE') throw new RuntimeException('Over product limit accepted');
$missing=clinical_capture_upload_failure(null,41943041,'40M');
if (!$missing['post_max_size_exceeded'] || $missing['upload_error']!==null || $missing['code']!=='UPLOAD_TOO_LARGE') throw new RuntimeException('Missing post envelope detection');
if (clinical_capture_upload_failure(null,0,'0')['code']!=='UPLOAD_NO_FILE') throw new RuntimeException('Unlimited post size misclassified');
echo "R42_UPLOAD_ERROR_MAPPING=PASS\n";
