<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../adapters/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../services/*.php') as $file) require_once $file;
require_once __DIR__ . '/../http/CsrfTokenService.php';
require_once __DIR__ . '/../http/ProductiveIdentityHttpConfiguration.php';

use Identity\Adapters\ProductiveValkeyClient;
use Identity\Adapters\SessionStoreUnavailableException;
use Identity\Adapters\ValkeySessionStoreAdapter;
use Identity\Contracts\AccountStatus;
use Identity\Contracts\AuthenticationPrincipalCandidate;
use Identity\Contracts\Clock;
use Identity\Contracts\ReasonCode;
use Identity\Contracts\SessionAccountStatePort;
use Identity\Contracts\SessionPolicy;
use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionState;
use Identity\Contracts\TransactionalValkeySessionClientPort;
use Identity\Http\CsrfTokenService;
use Identity\Http\ProductiveIdentityHttpConfiguration;
use Identity\Services\SessionService;
use Identity\Services\SessionTokenCodec;

final class C3Clock implements Clock
{
    public function __construct(private DateTimeImmutable $at) {}
    public function now(): DateTimeImmutable { return $this->at; }
    public function advance(string $modifier): void { $this->at = $this->at->modify($modifier); }
}

final class C3AccountState implements SessionAccountStatePort
{
    public function __construct(public string $status = AccountStatus::ACTIVE, public int $credentialVersion = 1, public bool $unavailable = false) {}
    public function current(string $accountId): ?array { if ($this->unavailable) throw new RuntimeException('fixture_unavailable'); return ['status'=>$this->status,'credential_version'=>$this->credentialVersion]; }
}

/** Deterministic Redis transaction model; it never opens a socket. */
final class C3TransactionalClient implements TransactionalValkeySessionClientPort
{
    /** @var array<string,string> */ public array $values = [];
    /** @var array<string,int> */ public array $ttls = [];
    /** @var array<string,int> */ private array $versions = [];
    /** @var array<string,int> */ private array $watched = [];
    /** @var list<array{op:string,key:string,value?:string,ttl?:int}> */ private array $queue = [];
    private bool $multi = false;
    public int $conflictsRemaining = 0;
    public bool $available = true;

    public function ping(): bool { if (!$this->available) throw new SessionStoreUnavailableException(); return true; }
    public function get(string $key): ?string { if (!$this->available) throw new SessionStoreUnavailableException(); return $this->values[$key] ?? null; }
    public function set(string $key, string $value, int $ttlSeconds): bool { if (!$this->available) throw new SessionStoreUnavailableException(); if ($this->multi) { $this->queue[]=['op'=>'set','key'=>$key,'value'=>$value,'ttl'=>$ttlSeconds]; return true; } $this->applySet($key,$value,$ttlSeconds); return true; }
    public function delete(string $key): bool { if (!$this->available) throw new SessionStoreUnavailableException(); if ($this->multi) { $this->queue[]=['op'=>'del','key'=>$key]; return true; } $existed=isset($this->values[$key]); unset($this->values[$key],$this->ttls[$key]); $this->versions[$key]=($this->versions[$key]??0)+1; return $existed; }
    public function watch(array $keys): bool { foreach ($keys as $key) $this->watched[$key]=$this->versions[$key]??0; return true; }
    public function unwatch(): void { $this->watched=[]; if (!$this->multi) $this->queue=[]; }
    public function multi(): bool { $this->multi=true; $this->queue=[]; return true; }
    public function exec(): array|false
    {
        if ($this->conflictsRemaining > 0) { $this->conflictsRemaining--; $this->multi=false; $this->queue=[]; $this->watched=[]; return false; }
        foreach ($this->watched as $key=>$version) if (($this->versions[$key]??0)!==$version) { $this->multi=false; $this->queue=[]; $this->watched=[]; return false; }
        $results=[];
        foreach ($this->queue as $command) {
            if ($command['op']==='set') { $this->applySet($command['key'],(string)$command['value'],(int)$command['ttl']); $results[]=true; }
            else { $existed=isset($this->values[$command['key']]); unset($this->values[$command['key']],$this->ttls[$command['key']]); $this->versions[$command['key']]=($this->versions[$command['key']]??0)+1; $results[]=$existed?1:0; }
        }
        $this->multi=false; $this->queue=[]; $this->watched=[];
        return $results;
    }
    public function ttl(string $key): int { return $this->ttls[$key]??-2; }
    private function applySet(string $key,string $value,int $ttl):void{$this->values[$key]=$value;$this->ttls[$key]=$ttl;$this->versions[$key]=($this->versions[$key]??0)+1;}
}

$checks=[];
$check=static function(string $name,bool $condition)use(&$checks):void{if(!$condition)throw new RuntimeException($name);$checks[]=$name;};
$rejects=static function(callable $operation):bool{try{$operation();return false;}catch(Throwable){return true;}};
$clock=new C3Clock(new DateTimeImmutable('2026-08-24T12:00:00+00:00'));
$accounts=new C3AccountState();
$client=new C3TransactionalClient();
$store=new ValkeySessionStoreAdapter($client,'mxmed:prd:session:',$clock,3,0);
$service=new SessionService($store,new SessionTokenCodec(str_repeat('s',32)),$clock,new SessionPolicy(),$accounts);
$candidate=new AuthenticationPrincipalCandidate('account_c3_primary',1,AccountStatus::ACTIVE,'2026-08-24 12:00:00');

$created=[];
for($index=1;$index<=5;$index++){$created[]=$service->create($candidate,['device_label'=>'Browser '.$index,'user_agent'=>'fixture-agent-'.$index,'ip'=>'198.51.100.'.$index]);}
$check('five_sessions_created',count(array_filter($created,fn($item)=>$item->allowed()))===5&&count($store->listActiveForAccount('account_c3_primary'))===5);
$preSixIds=array_map(fn($item)=>(string)$item->record()?->sessionId(),$created);sort($preSixIds,SORT_STRING);
$sixth=$service->create($candidate,['device_label'=>'Browser 6']);
$superseded=$service->lastSupersededSession();
$check('sixth_login_allowed_and_oldest_tie_break_superseded',$sixth->allowed()&&$superseded?->state()===SessionState::SUPERSEDED&&(string)$superseded->sessionId()===$preSixIds[0]);
$check('active_count_never_exceeds_five',count($store->listActiveForAccount('account_c3_primary'))===5);
$check('safe_limit_disclosure_once',$service->consumeSessionLimitAction()==='OLDEST_REVOKED'&&$service->consumeSessionLimitAction()===null);
$allSerialized=implode("\n",$client->values);
$check('raw_tokens_never_persisted',array_reduce(array_merge($created,[$sixth]),fn($ok,$decision)=>$ok&&!str_contains($allSerialized,(string)$decision->token()),true));
$check('serialized_records_are_versioned',str_contains($allSerialized,'"serialization_version":1'));

$serialized=json_encode($sixth->record()?->toArray(),JSON_THROW_ON_ERROR);
$decoded=SessionRecord::fromSerialized($serialized);
$check('strict_round_trip',(string)$decoded->sessionId()===(string)$sixth->record()?->sessionId());
$row=json_decode($serialized,true,32,JSON_THROW_ON_ERROR);$row['extra']='forbidden';
$check('extra_serialization_field_rejected',$rejects(fn()=>SessionRecord::fromSerialized(json_encode($row,JSON_THROW_ON_ERROR))));
$row=json_decode($serialized,true,32,JSON_THROW_ON_ERROR);$row['serialization_version']=2;
$check('unknown_serialization_version_rejected',$rejects(fn()=>SessionRecord::fromSerialized(json_encode($row,JSON_THROW_ON_ERROR))));
$row=json_decode($serialized,true,32,JSON_THROW_ON_ERROR);unset($row['state']);
$check('missing_serialization_field_rejected',$rejects(fn()=>SessionRecord::fromSerialized(json_encode($row,JSON_THROW_ON_ERROR))));
$row=json_decode($serialized,true,32,JSON_THROW_ON_ERROR);$row['credential_version']='1';
$check('invalid_serialization_type_rejected',$rejects(fn()=>SessionRecord::fromSerialized(json_encode($row,JSON_THROW_ON_ERROR))));
$row=json_decode($serialized,true,32,JSON_THROW_ON_ERROR);$row['state']='invented';
$check('invalid_serialization_enum_rejected',$rejects(fn()=>SessionRecord::fromSerialized(json_encode($row,JSON_THROW_ON_ERROR))));
$row=json_decode($serialized,true,32,JSON_THROW_ON_ERROR);$row['expires_at']='2026-08-24T11:59:59+00:00';
$check('impossible_serialization_times_rejected',$rejects(fn()=>SessionRecord::fromSerialized(json_encode($row,JSON_THROW_ON_ERROR))));
$check('oversized_serialization_rejected',$rejects(fn()=>SessionRecord::fromSerialized(str_repeat('x',SessionRecord::MAX_SERIALIZED_BYTES+1))));

$touchCandidate=new AuthenticationPrincipalCandidate('account_c3_touch',1,AccountStatus::ACTIVE,'2026-08-24 12:00:00');
$touch=$service->create($touchCandidate,['device_label'=>'Touch']);$touchToken=(string)$touch->token();$initialSeen=$touch->record()?->lastSeenAt();
$clock->advance('+299 seconds');$before=$service->validate($touchToken);
$check('touch_valid_before_boundary',$before->allowed());
$check('touch_not_before_boundary',$before->record()?->lastSeenAt()==$initialSeen);
$clock->advance('+1 second');$atBoundary=$service->validate($touchToken);
$check('touch_at_boundary',$atBoundary->allowed()&&$atBoundary->record()?->lastSeenAt()===$clock->now());
$check('browser_session_cookie_has_no_max_age',$touch->cookie()?->maxAge()===null&&$touch->cookie()?->domain()===null&&$touch->cookie()?->secure()&&$touch->cookie()?->httpOnly());

$rotation=$service->rotate($touchToken);
$check('rotation_atomic_and_cookie_replaced',$rotation->allowed()&&$rotation->cookie()?->maxAge()===null&&$service->validate($touchToken)->reasonCode()===ReasonCode::SESSION_SUPERSEDED&&$service->validate((string)$rotation->token())->allowed());
$logout=$service->logout((string)$rotation->token());
$check('logout_revokes_and_deletes_cookie',$logout->allowed()&&$logout->cookie()?->maxAge()===-1&&$service->validate((string)$rotation->token())->reasonCode()===ReasonCode::SESSION_REVOKED);

$listToken=(string)$sixth->token();$listed=$service->listOwnSessions($listToken);
$encodedProjection=json_encode($listed['sessions'],JSON_THROW_ON_ERROR);
$check('own_session_listing_safe',$listed['allowed']&&!str_contains($encodedProjection,'token')&&!str_contains($encodedProjection,'user_agent')&&!str_contains($encodedProjection,'ip_dimension')&&!str_contains($encodedProjection,'valkey'));
$target=array_values(array_filter($listed['sessions'],fn($item)=>!$item['current_session']))[0]??null;
$revoked=$service->revokeOwnSession($listToken,(string)($target['session_id']??''));
$check('own_session_revoke_allowed',$revoked['allowed']&&!$revoked['current_revoked']);
$check('cross_account_or_unknown_session_rejected',!$service->revokeOwnSession($listToken,str_repeat('Z',32))['allowed']);

$accounts->status=AccountStatus::BLOCKED;$check('blocked_distinct',$service->validate($listToken)->reasonCode()===ReasonCode::ACCOUNT_BLOCKED);
$accounts->status=AccountStatus::ACTIVE;
$newActive=$service->create($candidate);$accounts->status=AccountStatus::DISABLED;$check('disabled_distinct',$service->validate((string)$newActive->token())->reasonCode()===ReasonCode::ACCOUNT_DISABLED);
$accounts->status=AccountStatus::ACTIVE;
$credential=$service->create($candidate);$accounts->credentialVersion=2;$check('credential_version_invalidates',$service->validate((string)$credential->token())->reasonCode()===ReasonCode::CREDENTIAL_VERSION_MISMATCH);$accounts->credentialVersion=1;
$dependency=$service->create($candidate);$accounts->unavailable=true;$check('account_dependency_fails_closed',$service->validate((string)$dependency->token())->reasonCode()===ReasonCode::SESSION_STORE_UNAVAILABLE);$accounts->unavailable=false;

$revokeAllCandidate=new AuthenticationPrincipalCandidate('account_c3_revoke_all',1,AccountStatus::ACTIVE,$clock->now()->format('Y-m-d H:i:s'));
$revokeAllA=$service->create($revokeAllCandidate);$revokeAllB=$service->create($revokeAllCandidate);$revokeAll=$service->revokeAll('account_c3_revoke_all');
$check('security_revoke_all',$revokeAll->allowed()&&$revokeAll->revokedCount()===2&&!$service->validate((string)$revokeAllA->token())->allowed()&&!$service->validate((string)$revokeAllB->token())->allowed());
$logoutFailureCandidate=$service->create(new AuthenticationPrincipalCandidate('account_c3_logout_failure',1,AccountStatus::ACTIVE,$clock->now()->format('Y-m-d H:i:s')));$client->available=false;$logoutFailure=$service->logout((string)$logoutFailureCandidate->token());$client->available=true;
$check('logout_store_failure_clears_cookie_without_false_success',!$logoutFailure->allowed()&&$logoutFailure->cookie()?->maxAge()===-1);
$idleCandidate=$service->create(new AuthenticationPrincipalCandidate('account_c3_idle',1,AccountStatus::ACTIVE,$clock->now()->format('Y-m-d H:i:s')));$clock->advance('+3600 seconds');
$check('idle_expiry_at_boundary',$service->validate((string)$idleCandidate->token())->reasonCode()===ReasonCode::SESSION_IDLE_EXPIRED);
$absoluteCandidate=$service->create(new AuthenticationPrincipalCandidate('account_c3_absolute',1,AccountStatus::ACTIVE,$clock->now()->format('Y-m-d H:i:s')));$clock->advance('+43200 seconds');
$check('absolute_expiry_at_boundary',$service->validate((string)$absoluteCandidate->token())->reasonCode()===ReasonCode::SESSION_ABSOLUTE_EXPIRED);

$csrf=new CsrfTokenService(str_repeat('c',32),900,$clock,'https://mxmed.example.test');
$preauth=$csrf->issuePreAuth();$auth=$csrf->issueAuthenticated($sixth->record()->tokenDigest());
$check('csrf_purposes_are_separate',$csrf->validPreAuth($preauth)&&!$csrf->validPreAuth($auth)&&$csrf->validAuthenticated($auth,$sixth->record()->tokenDigest()));
$otherDigest=$created[1]->record()->tokenDigest();$check('csrf_wrong_session_rejected',!$csrf->validAuthenticated($auth,$otherDigest));
$rotatedCsrf=$csrf->issueAuthenticated($rotation->record()->tokenDigest());$check('csrf_rotation_invalidates_old_binding',!$csrf->validAuthenticated($auth,$rotation->record()->tokenDigest())&&$csrf->validAuthenticated($rotatedCsrf,$rotation->record()->tokenDigest()));

$conflictCandidate=new AuthenticationPrincipalCandidate('account_c3_conflict',1,AccountStatus::ACTIVE,'2026-08-24 12:00:00');
$client->conflictsRemaining=2;$retry=$service->create($conflictCandidate);$check('transaction_conflict_bounded_retry_succeeds',$retry->allowed());
$beforeCount=count($store->listActiveForAccount('account_c3_conflict'));$client->conflictsRemaining=3;$exhausted=$service->create($conflictCandidate);$client->conflictsRemaining=0;
$check('transaction_exhaustion_fails_closed_without_partial_authority',!$exhausted->allowed()&&$exhausted->reasonCode()===ReasonCode::SESSION_STORE_UNAVAILABLE&&count($store->listActiveForAccount('account_c3_conflict'))===$beforeCount);
$client->available=false;$check('store_outage_grants_no_authority',$service->create($candidate)->reasonCode()===ReasonCode::SESSION_STORE_UNAVAILABLE);$client->available=true;

$validConfig=[
 'APP_ENV'=>'production','DB_HOST'=>'db.internal','DB_PORT'=>'3306','DB_NAME'=>'mxmed_productive','DB_USERNAME'=>'identity','DB_PASSWORD'=>'fixture-db',
 'SESSION_SIGNING_KEY'=>str_repeat('p',32),'MXMED_IDENTITY_ORIGIN'=>'https://mxmed.example.test','SESSION_HOST'=>'valkey.internal','SESSION_PORT'=>'6379',
 'SESSION_PREFIX'=>'mxmed:prd:session:','SESSION_IDLE_TTL'=>'3600','SESSION_ABSOLUTE_LIFETIME'=>'43200','SESSION_TOUCH_INTERVAL'=>'300','SESSION_MAX_ACTIVE'=>'5',
 'SESSION_TLS_REQUIRED'=>'true','SESSION_LOCK_ENABLED'=>'true','SESSION_LOCK_TIMEOUT_SECONDS'=>'10','SESSION_LOCK_WAIT_MICROSECONDS'=>'100000','SESSION_STORE_USERNAME'=>'mxmed_session_app','SESSION_STORE_PASSWORD'=>'fixture-store',
];
$configuration=ProductiveIdentityHttpConfiguration::fromValues('production',$validConfig);
$check('productive_config_exact',$configuration->sessionConfigured()&&$configuration->sessionPort()===6379&&$configuration->sessionPrefix()==='mxmed:prd:session:');
$check('productive_client_constructs_without_connection',(new ProductiveValkeyClient('valkey.internal',6379,'mxmed_session_app','fixture-store')) instanceof TransactionalValkeySessionClientPort);
$invalid=$validConfig;$invalid['SESSION_TLS_REQUIRED']='false';$check('productive_tls_cannot_be_disabled',$rejects(fn()=>ProductiveIdentityHttpConfiguration::fromValues('production',$invalid)));
$invalid=$validConfig;$invalid['SESSION_PREFIX']='mxmed:stg:session:';$check('cross_environment_prefix_rejected',$rejects(fn()=>ProductiveIdentityHttpConfiguration::fromValues('production',$invalid)));

$factory=file_get_contents(__DIR__.'/../services/SessionStoreFactory.php');$composition=file_get_contents(__DIR__.'/../http/IdentityHttpComposition.php');$entry=file_get_contents(dirname(__DIR__,3).'/api/identity/index.php');$productiveClient=file_get_contents(__DIR__.'/../adapters/ProductiveValkeyClient.php');
$check('no_productive_in_memory_or_preview_fallback',str_contains($factory,"['production', 'staging']")&&!str_contains(substr($composition,strpos($composition,'public static function productive'),strpos($composition,'private static function build')-strpos($composition,'public static function productive')),'PreviewValkeyClient'));
$check('no_forbidden_valkey_commands',preg_match('/->(?:keys|scan|eval|flushall|flushdb|config|acl|select)\s*\(/i',$productiveClient)!==1);
$check('request_cannot_select_session_authority',!str_contains($entry,"body['SESSION_")&&!str_contains($entry,"body['prefix'")&&!str_contains($entry,"body['environment'"));
$check('http_and_client_sources_do_not_disclose_secrets',!str_contains($entry,'getMessage()')&&!str_contains($entry,'getTrace')&&!str_contains($productiveClient,'error_log'));
$check('canonical_session_audit_is_productively_wired',str_contains($composition,'CanonicalSessionAuditProducer')&&str_contains($composition,'PdoCanonicalAuditTransactionAdapter')&&str_contains($entry,'identityHttpAuditSession')&&str_contains($entry,'identityHttpAuditLogoutAll'));
$check('terminal_http_states_clear_cookie_and_transient_does_not_grant',str_contains($entry,"identityHttpSetCookie(SessionCookieDescriptor::deletion())")&&str_contains($entry,"'TEMPORARILY_UNAVAILABLE'], 503")&&str_contains($entry,"'SESSION_REPLACED'"));

echo json_encode(['ok'=>true,'checks_total'=>count($checks),'checks'=>$checks],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
