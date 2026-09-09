<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../../api/_lib/db.php';
require_once __DIR__.'/../services/MediaReviewBatchService.php';
try {
    if($argc>2||isset($argv[1])&&!preg_match('/^[0-9]{1,3}$/D',$argv[1]))throw new RuntimeException('invalid_arguments');
    $result=(new Media\Services\MediaReviewBatchService(mxmed_pdo()))->submitInactive(isset($argv[1])?(int)$argv[1]:100);
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){fwrite(STDERR,"review_batch_executor_failed\n");exit(1);}
