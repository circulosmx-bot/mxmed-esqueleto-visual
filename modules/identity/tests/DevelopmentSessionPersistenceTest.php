<?php
declare(strict_types=1);
spl_autoload_register(static function(string $name):void {
 if(!str_starts_with($name,'Identity\\'))return;
 $parts=explode('\\',substr($name,9));$parts[0]=strtolower($parts[0]);
 $path=realpath(__DIR__.'/../'.implode('/',$parts).'.php');if($path)require_once $path;
});
require __DIR__.'/run_gate4c_session_authorization_test.php';
use Identity\Contracts\DevelopmentSessionCookiePolicy;
use Identity\Contracts\{AuthenticationPrincipalCandidate,SessionPolicy};
use Identity\Adapters\InMemorySessionStoreAdapter;
use Identity\Services\{SessionService,SessionTokenCodec};
function checkPersistence(bool $value,string $label):void {gate4cAssert($value,$label);echo $label."=PASS\n";}
putenv('MXMED_DEV_PERSISTENT_COOKIE');
checkPersistence(DevelopmentSessionCookiePolicy::fromEnvironment('production','https://example.test')===null,'Default unchanged');
foreach(['MXMED_DEV_PERSISTENT_COOKIE'=>'1','APP_ENV'=>'local','MXMED_PREVIEW_EXPLICIT'=>'1','MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT'=>'0','MXMED_DB_HOST'=>'127.0.0.1','MXMED_DB_PORT'=>'55551','MXMED_DB_NAME'=>'mxmed_gate4d_preview_persist_test'] as $k=>$v)putenv("$k=$v");
$policy=DevelopmentSessionCookiePolicy::fromEnvironment('local','https://127.0.0.1:55552');
foreach([['production','https://127.0.0.1:55552'],['staging','https://127.0.0.1:55552'],['local','https://example.test:55552']] as [$e,$o]){
 try{DevelopmentSessionCookiePolicy::fromEnvironment($e,$o);throw new LogicException('guard failed');}catch(RuntimeException $x){checkPersistence(true,'Non-isolated context denied '.$e.' '.$o);}
}
putenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT=1');
try{DevelopmentSessionCookiePolicy::fromEnvironment('local','https://127.0.0.1:55552');throw new LogicException('guard failed');}catch(RuntimeException $x){checkPersistence(true,'Original normal-local cannot enable flag');}
putenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT=0');
$clock=new Gate4CTestClock(new DateTimeImmutable('2026-10-22T00:00:00Z'));
$service=new SessionService(new InMemorySessionStoreAdapter(),new SessionTokenCodec(str_repeat('q',32)),$clock,new SessionPolicy(),new Gate4CTestAccountState(),developmentCookie:$policy);
$c=$service->create(new AuthenticationPrincipalCandidate('synthetic-persistence',1,'active',$clock->now()->format(DATE_ATOM)));
checkPersistence($c->cookie()->maxAge()===43200,'Persistent cookie bounded at 12 hours');
$token=(string)$c->token();
$clock->advance('+1800 seconds');$rotated=$service->rotate($token);
checkPersistence($rotated->cookie()->maxAge()===41400,'Rotation cannot extend cookie absolute deadline');
checkPersistence(!$service->validate($token)->allowed(),'Superseded token denied');
$token=(string)$rotated->token();$clock->advance('+3600 seconds');
checkPersistence(!$service->validate($token)->allowed(),'Persistent cookie cannot bypass idle expiration');
$c=$service->create(new AuthenticationPrincipalCandidate('synthetic-persistence',1,'active',$clock->now()->format(DATE_ATOM)));$token=(string)$c->token();
$service->logout($token);checkPersistence(!$service->validate($token)->allowed(),'Explicit logout remains authoritative');
checkPersistence(!$service->validate('invalid')->allowed(),'Invalid token denied');
$c=$service->create(new AuthenticationPrincipalCandidate('synthetic-persistence',1,'active',$clock->now()->format(DATE_ATOM)));$token=(string)$c->token();
$access=$service->validate($token)->context();
$membershipRows=new Gate4CTestMemberships([['membership_id'=>'synthetic-membership','profile_doctor_id'=>'synthetic-doctor','role_code'=>'owner','scope_code'=>'profile','status'=>'active']]);
$authority=new \Identity\Services\FailClosedAuthorizationService($membershipRows,new Gate4CTestCapabilityAuthority());
checkPersistence($authority->authorize($access,'profile_doctor','synthetic-doctor','agenda_appointments')->allowed(),'Current membership authorized');
$membershipRows->rows=[];
checkPersistence(!$authority->authorize($access,'profile_doctor','synthetic-doctor','agenda_appointments')->allowed() && $service->validate($token)->allowed(),'Removed membership denied without logout');
for($n=0;$n<143;$n++){$clock->advance('+300 seconds');checkPersistence($service->validate($token)->allowed(),'Active bounded session step '.$n);}
$clock->advance('+300 seconds');checkPersistence(!$service->validate($token)->allowed(),'Absolute 12 hour expiry enforced despite activity');
echo "DevelopmentSessionPersistenceTest=PASS\n";
