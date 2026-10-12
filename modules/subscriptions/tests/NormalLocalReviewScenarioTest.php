<?php
declare(strict_types=1);
require_once __DIR__.'/../services/NormalLocalReviewScenario.php';
use Subscriptions\Services\NormalLocalReviewScenario as Scenario;
function reviewCheck(bool $ok,string $name): void {if(!$ok)throw new RuntimeException($name);echo $name." PASS\n";}
$keys=['APP_ENV','MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT','MXMED_DB_NAME','MXMED_DB_HOST','MXMED_ENVIRONMENT','MXMED_PREVIEW_EXPLICIT'];
$old=[];foreach($keys as $key)$old[$key]=getenv($key);
try {
 foreach(['APP_ENV'=>'local','MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT'=>'1','MXMED_DB_NAME'=>'mxmed','MXMED_DB_HOST'=>'127.0.0.1','MXMED_ENVIRONMENT'=>'local','MXMED_PREVIEW_EXPLICIT'=>'0'] as $key=>$value)putenv($key.'='.$value);
 reviewCheck(Scenario::environmentAllowed(),'Q01 explicit local');
 foreach([['APP_ENV','production'],['APP_ENV','staging'],['MXMED_PREVIEW_EXPLICIT','1'],['MXMED_DB_NAME','mxmed_gate4d_preview_test'],['MXMED_DB_NAME','other'],['MXMED_DB_HOST','db.example'],['MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT','0'],['MXMED_ENVIRONMENT','production']] as [$key,$value]) {
  $previous=getenv($key);putenv($key.'='.$value);reviewCheck(!Scenario::environmentAllowed(),'reject '.$key.'='.$value);
  try {Scenario::read();throw new LogicException('storage guard bypass');}catch(RuntimeException $e){reviewCheck($e->getMessage()==='local_review_forbidden','storage denies forbidden environment');}
  putenv($key.'='.$previous);
 }
 try{Scenario::select('free',str_repeat('a',64));throw new LogicException('invalid plan accepted');}catch(InvalidArgumentException $e){reviewCheck($e->getMessage()==='unsupported_plan','Q07 unsupported plan');}
} finally {
 foreach($old as $key=>$value)putenv($value===false?$key:$key.'='.$value);
}
