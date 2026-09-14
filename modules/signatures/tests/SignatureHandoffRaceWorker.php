<?php
declare(strict_types=1);
require_once __DIR__.'/../SignatureHandoffService.php';require_once __DIR__.'/../../media/storage/LocalPersistentPrivateMediaStorage.php';
if(getenv('MXMED_SIG03A_DISPOSABLE')!=='1'||!getenv('MXMED_SIG03A_DISPOSABLE_PORT'))exit(2);
$input=json_decode(fgets(STDIN),true);echo "READY\n";flush();fgets(STDIN);
$pdo=new PDO('mysql:host=127.0.0.1;port='.getenv('MXMED_SIG03A_DISPOSABLE_PORT').';dbname=mxmed;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$storage=new \Media\Storage\LocalPersistentPrivateMediaStorage(getenv('MXMED_SIG03A_PRIVATE_TEST_ROOT'));
$service=new \Signatures\SignatureHandoffService($pdo,new \Signatures\PhysicianSignatureService($pdo,$storage));
try{$service->complete($input['token'],$input['image']);echo 'SUCCESS';}catch(RuntimeException $e){if($e->getMessage()!=='handoff_denied')exit(3);echo 'DENIED';}
