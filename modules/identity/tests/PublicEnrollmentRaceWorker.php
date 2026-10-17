<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('PUB_ENR02_QA')!=='1')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../media/storage/LocalPersistentPrivateMediaStorage.php';
require_once __DIR__.'/../../agenda/contracts/OtpProviderPort.php';
require_once __DIR__.'/support/C4EvidenceHarness.php';
$p=evConnect();$db=$p->query('SELECT DATABASE()')->fetchColumn();
if(!preg_match('/^mxmed_gate4d_preview_c4ui_([a-f0-9]{10})$/D',$db,$m)||$p->query('SELECT CURRENT_USER()')->fetchColumn()!=='gov03b_'.$m[1].'@127.0.0.1')exit(1);
$s=evStack($p,new EvClock(new DateTimeImmutable('now',new DateTimeZone('UTC'))));
$svc=new Identity\Accreditation\PublicEnrollmentService($s['authority'],$s['cipher'],str_repeat('q',64));
if(($argv[1]??'')==='missing-source'){
    evDeny(fn()=>$svc->claim('enr_profile_0'),'enrollment_claim_unavailable','D_missing_ownership_source_fails_closed');exit;
}
$fixture=json_decode(file_get_contents((string)getenv('GOV03B_QA_EVIDENCE').'/enrollment-race.json'),true,16,JSON_THROW_ON_ERROR);
$decision=$s['sessions']->validate($fixture['session_token']);if(!$decision->allowed())throw new RuntimeException('owned_parallel_session_invalid');$actor=$decision->context();
try{
 if(($argv[1]??'')==='bind')$result=$svc->bind($actor,$fixture['application_id'],$fixture['handle'],$fixture['browser']);
 else $result=$svc->save($actor,$fixture['application_id'],(int)($argv[2]??'0'),($argv[1]??'')==='retry'?$fixture['command']:$s['authority']->uuid(),$fixture['intake']);
 echo json_encode(['ok'=>true,'version'=>$result['version'],'state'=>$result['state'],'replayed'=>$result['replayed']??false],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(RuntimeException $e){if($e->getMessage()!=='enrollment_version_conflict')throw $e;echo json_encode(['ok'=>false,'conflict'=>true]).PHP_EOL;}
