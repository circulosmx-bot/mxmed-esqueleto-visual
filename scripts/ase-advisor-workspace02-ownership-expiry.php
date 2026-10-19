<?php
declare(strict_types=1);
/** Owned-only regression: injected clock, no SQL expiry edits or replacement ownership writer. */
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/ase-advisor-workspace02-runtime.php';
$s=workspace02OwnedServices();$owned=realpath((string)getenv('ASE_INT02A_OWNED'));
$key=$argv[1]??'';if($key!=='claim_approve')throw new RuntimeException('synthetic_test_case_required');
$records=json_decode(file_get_contents($owned.'/workspace02-fixture.json'),true,64,JSON_THROW_ON_ERROR);$record=$records[$key];
$advisor=json_decode(file_get_contents($owned.'/oper09-advisor.json'),true,32,JSON_THROW_ON_ERROR);
$i=$s['identity'];$p=$i->pdo();
$d=$i->authentication()->authenticate($advisor['email'],trim(file_get_contents($owned.'/applicant-password')),['identity'=>$advisor['email']]);
if(!$d->isAllowed())throw new RuntimeException('synthetic_login_denied');$session=$i->sessions()->create($d->candidate());
if(!$session->allowed())throw new RuntimeException('synthetic_session_denied');$actor=$i->sessions()->validate($session->token()->value())->context();
$q=$p->prepare('SELECT l.link_version,l.case_id,c.status,c.expires_at FROM auth_physician_profile_request_ownership l JOIN auth_governance_cases c ON c.case_id=l.case_id WHERE l.origin_id=? ORDER BY l.link_version');
$q->execute([$record['application_id']]);$before=$q->fetchAll(PDO::FETCH_ASSOC);
$make=function(Identity\Contracts\Clock $clock)use($s,$record){return new Identity\PhysicianRequests\RequestOwnershipService($s['identity'],$s['security'],$s['mfa'],$s['composition']->evidence,$record['application_id'],clock:$clock);};
try{
 if(!$before)$make(new Identity\Contracts\SystemClock())->begin($actor);
 // Advance only the first expiry observation, then restore real time before
 // C4 eligibility and the replacement-case writer. Neither TTL is edited.
 $expiryObservation=new class implements Identity\Contracts\Clock{
  private bool $first=true;
  public function now():DateTimeImmutable{$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));if($this->first){$this->first=false;return $now->modify('+31 minutes');}return $now;}
 };
 if(count($before)<2)$now=$make($expiryObservation)->begin($actor);
 else $now=$make(new Identity\Contracts\SystemClock())->status($actor);
 $q->execute([$record['application_id']]);$links=$q->fetchAll(PDO::FETCH_ASSOC);
 if(count($links)!==2||(int)$links[0]['link_version']!==1||$links[0]['status']!=='REVOKED'||(int)$links[1]['link_version']!==2||$links[1]['status']!=='OPEN'||$links[0]['case_id']===$links[1]['case_id'])throw new RuntimeException('append_only_case_recovery_failed');
 $ttl=(new DateTimeImmutable($links[1]['expires_at'],new DateTimeZone('UTC')))->getTimestamp()-time();
 if($ttl<=0||$ttl>1800)throw new RuntimeException('fresh_case_ttl_invalid');
 $result=['result'=>'PASS','clock'=>'injected plus 31 minutes for expiry observation only; replacement writer and system clock use real time','prior_case_retained'=>true,'prior_case_revoked'=>true,'links'=>2,'fresh_case_ttl_seconds'=>$ttl,'owner_written'=>false];
 file_put_contents($owned.'/workspace02-ownership-expiry.json',json_encode($result,JSON_THROW_ON_ERROR));
 echo json_encode($result,JSON_THROW_ON_ERROR);
}catch(Throwable $e){file_put_contents($owned.'/workspace02-ownership-expiry-error.log',(string)$e);echo json_encode(['result'=>'FAIL','error'=>$e->getMessage()],JSON_THROW_ON_ERROR);exit(1);}
