<?php
declare(strict_types=1);
require __DIR__.'/run_gate4c_session_authorization_test.php';
require_once __DIR__.'/../http/CanonicalHttpSessionResolver.php';

use Identity\Contracts\{SessionStorePort,SessionRecord,SessionTokenDigest,SessionPolicy,SessionStoreHealthDecision};
use Identity\Adapters\InMemorySessionStoreAdapter;
use Identity\Services\{SessionService,SessionTokenCodec};

/** Simulates the real read/touch race without clocks or sessions outside this test. */
final class Sim02RaceStore implements SessionStorePort
{
    public function __construct(private InMemorySessionStoreAdapter $inner=new InMemorySessionStoreAdapter()){}
    public function create(SessionRecord $r,?int $m=null):void{$this->inner->create($r,$m);}
    public function read(SessionTokenDigest $d):?SessionRecord{return $this->inner->read($d);}
    public function touch(SessionTokenDigest $d,DateTimeImmutable $at,SessionPolicy $p):?SessionRecord{$this->inner->revoke($d,'revoked',$at);return null;}
    public function rotate(SessionTokenDigest $d,SessionRecord $r):bool{return $this->inner->rotate($d,$r);}
    public function revoke(SessionTokenDigest $d,string $r,DateTimeImmutable $at):bool{return $this->inner->revoke($d,$r,$at);}
    public function revokeAllForAccount(string $a,string $r,DateTimeImmutable $at):int{return $this->inner->revokeAllForAccount($a,$r,$at);}
    public function listActiveForAccount(string $a):array{return $this->inner->listActiveForAccount($a);}
    public function enforceMaximumActiveSessions(string $a,int $m,DateTimeImmutable $at):?SessionRecord{return $this->inner->enforceMaximumActiveSessions($a,$m,$at);}
    public function compareAndUpdate(SessionTokenDigest $d,callable $f):?SessionRecord{return $this->inner->compareAndUpdate($d,$f);}
    public function healthCheck():SessionStoreHealthDecision{return $this->inner->healthCheck();}
}

function sim02SessionCheck(bool $ok,string $name):void{gate4cAssert($ok,$name);echo $name."=PASS\n";}
function sim02SessionStack(?SessionStorePort $store=null):array
{
    $clock=new Gate4CTestClock(new DateTimeImmutable('2026-10-16T00:00:00Z'));
    $state=new Gate4CTestAccountState();
    $service=new SessionService($store??new InMemorySessionStoreAdapter(),new SessionTokenCodec(str_repeat('q',32)),$clock,new SessionPolicy(),$state);
    $candidate=new \Identity\Contracts\AuthenticationPrincipalCandidate('sim02-account',1,'active',$clock->now()->format(DATE_ATOM));
    $created=$service->create($candidate);
    return [$clock,$state,$service,(string)$created->token(),$created->record()];
}
[$clock,$state,$service,$token]=sim02SessionStack();$clock->advance('+3600 seconds');
sim02SessionCheck(!$service->validate($token)->allowed(),'SIM02_idle_exact_3600_denied');
[$clock,$state,$service,$token,$record]=sim02SessionStack();$absolute=$record->absoluteExpiresAt();
for($i=1;$i<144;$i++){
    $clock->advance('+300 seconds');$decision=$service->validate($token);
    gate4cAssert($decision->allowed()&&$decision->record()->absoluteExpiresAt()==$absolute,'active use preserves absolute deadline');
    if($i%12===0){$rotation=$service->rotate($token);gate4cAssert($rotation->allowed()&&$rotation->record()->absoluteExpiresAt()==$absolute,'rotation preserves absolute deadline');$token=(string)$rotation->token();}
}
$clock->advance('+300 seconds');
sim02SessionCheck(!$service->validate($token)->allowed(),'SIM02_active_use_and_rotations_cannot_extend_43200');
[$clock,$state,$service,$token]=sim02SessionStack(new Sim02RaceStore());$clock->advance('+300 seconds');
sim02SessionCheck(!$service->validate($token)->allowed(),'SIM02_concurrent_revoke_during_touch_denied');
[$clock,$state,$unused,$unusedToken]=sim02SessionStack();
$observer=new SessionService(new InMemorySessionStoreAdapter(),new SessionTokenCodec(str_repeat('q',32)),$clock,new SessionPolicy(),$state,touchActivity:false);
$created=$observer->create(new \Identity\Contracts\AuthenticationPrincipalCandidate('sim02-observer',1,'active',$clock->now()->format(DATE_ATOM)));
$token=(string)$created->token();$seen=$created->record()->lastSeenAt();
for($i=1;$i<12;$i++){$clock->advance('+300 seconds');$d=$observer->validate($token);gate4cAssert($d->allowed()&&$d->record()->lastSeenAt()==$seen,'observation is not activity');}
$clock->advance('+300 seconds');
sim02SessionCheck(!$observer->validate($token)->allowed(),'SIM02_session_observation_cannot_keep_idle_alive');
[$clock,$state,$service,$token]=sim02SessionStack();$state->credentialVersion++;
sim02SessionCheck(!$service->validate($token)->allowed(),'SIM02_credential_rotation_denies_session');
[$clock,$state,$service,$token]=sim02SessionStack();$state->status='disabled';
sim02SessionCheck(!$service->validate($token)->allowed(),'SIM02_disabled_account_denies_session');
[$clock,$state,$service,$token]=sim02SessionStack();$service->revokeAll('sim02-account');
sim02SessionCheck(!$service->validate($token)->allowed(),'SIM02_session_revocation_denied');
$resolver=new \Identity\Http\CanonicalHttpSessionResolver($service);
sim02SessionCheck($resolver->resolve(['PHPSESSID'=>'legacy-only','user_id'=>'sim02-account'])===null,'SIM02_legacy_cookie_not_canonical_auth');
echo "GovSim02SessionTest=PASS\n";
