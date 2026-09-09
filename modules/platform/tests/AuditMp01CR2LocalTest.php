<?php
declare(strict_types=1);
// Invoked only by the disposable-container harness; no application DB config.
$port=getenv('MXMED_R2_QA_PORT');if(!$port||!ctype_digit($port))throw new RuntimeException('disposable_port_required');
function db(string $user): PDO {global $port;return new PDO("mysql:host=127.0.0.1;port=$port;dbname=mxmed;charset=utf8mb4",$user,'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);}
function ok(bool $v,string $label):void {if(!$v)throw new RuntimeException($label);echo "PASS $label\n";}
function denied(callable $f,string $state, ?int $native=null):void {try{$f();}catch(PDOException $e){ok((string)$e->getCode()===$state&&($native===null||(int)$e->errorInfo[1]===$native),'denied_'.$state);return;}throw new RuntimeException('unexpected_allow');}
foreach(['contracts','repositories','services'] as $dir)foreach(glob(__DIR__.'/../'.$dir.'/*.php') as $f)require_once $f;
use Identity\Audit\AuditProducerEmissionResult;
use Identity\Audit\AuditProducerFailureSignal;
use Identity\Audit\BoundedBestEffortAuditEmitter;
use Identity\Audit\CanonicalAuditWriterAdapter;
use Identity\Audit\CanonicalIdentityAuditProducer;
use Identity\Audit\HmacSha256AuthIdentifierAuditHasher;
use Identity\Audit\IdentityAuditReasonResolver;
use Identity\Audit\Mp01eEventScopePolicy;
use Identity\Audit\TrustedIdentityId;
use Identity\Audit\Contracts\AuditProducerFailureSignalPort;
use Identity\Audit\Contracts\AuthIdentifierAuditSecretProvider;
use Platform\Contracts\AuditIpHasher;
use Platform\Contracts\CanonicalAuditEnvelope;
use Platform\Contracts\CanonicalAuditEventInput;
use Platform\Contracts\TrustedActorContext;
use Platform\Contracts\TrustedRequestContext;
use Platform\Repositories\CanonicalAuditTransactionPort;
use Platform\Repositories\PdoCanonicalAuditTransactionAdapter;
use Platform\Services\AuditV1PhysicalMapper;
use Platform\Services\AuditWriterContextBridge;
use Platform\Services\CanonicalAuditMetadataSanitizer;
use Platform\Services\CanonicalAuditPolicyRegistry;
use Platform\Services\CanonicalAuditSealer;
use Platform\Services\CanonicalAuditSerializer;
use Platform\Services\CanonicalAuditWriter;
use Platform\Services\CoarseAuditUserAgentSummarizer;
use Platform\Services\RandomAuditUuidProvider;
use Platform\Services\SystemAuditUtcClock;
use Platform\Services\TrustedAuditContextValidator;
use Platform\Services\UuidV4ContextIdPolicy;


spl_autoload_register(static function(string $class):void { $base=dirname(__DIR__,2); if(str_starts_with($class,'Identity\\Audit\\Contracts\\'))$p=$base.'/identity/audit/contracts/'.substr($class,25).'.php'; elseif(str_starts_with($class,'Identity\\Audit\\'))$p=$base.'/identity/audit/'.substr($class,15).'.php'; else return; if(is_file($p))require_once $p; });
$r=db('root');$w=db('r2writer');$d=db('r2definer');
$actual=[];
foreach(['TABLE_PRIVILEGES','COLUMN_PRIVILEGES'] as $view){
 foreach($r->query("SELECT * FROM information_schema.$view WHERE TABLE_SCHEMA='mxmed'")->fetchAll(PDO::FETCH_ASSOC) as $row){
  if(!str_contains($row['GRANTEE'],'r2writer')&&!str_contains($row['GRANTEE'],'r2definer'))continue;
  ok($row['IS_GRANTABLE']==='NO','no_grant_option');
  $actual[]=implode('|',[str_contains($row['GRANTEE'],'r2writer')?'writer':'definer',$row['PRIVILEGE_TYPE'],$row['TABLE_NAME'],$row['COLUMN_NAME']??'']);
 }
}
foreach($r->query("SELECT User,Proc_priv,Routine_name FROM mysql.procs_priv WHERE Db='mxmed' AND User IN ('r2writer','r2definer')")->fetchAll(PDO::FETCH_ASSOC) as $row){ok(strtolower($row['Proc_priv'])==='execute','execute_only');$actual[]=($row['User']==='r2writer'?'writer':'definer').'|EXECUTE|'.$row['Routine_name'].'|';}
$expected=[];foreach(['platform_audit_events','platform_audit_stream_heads'] as $t)foreach(['SELECT','INSERT'] as $p)$expected[]="writer|$p|$t|";
foreach(['stream_key','last_sequence_number','last_event_hash','hash_version','updated_at'] as $c)$expected[]="definer|SELECT|platform_audit_stream_heads|$c";
foreach(['last_sequence_number','last_event_hash','hash_version','updated_at'] as $c)$expected[]="definer|UPDATE|platform_audit_stream_heads|$c";
foreach(['writer','definer'] as $u)foreach(['audit_mp01c_lock_stream_head_v1','audit_mp01c_advance_stream_head_cas_v1'] as $p)$expected[]="$u|EXECUTE|$p|";
sort($expected);sort($actual);ok($actual===$expected&&count($actual)===17,'17_of_17_zero_extras');
foreach(['r2writer','r2definer'] as $u){
 ok((int)$r->query("SELECT COUNT(*) FROM information_schema.USER_PRIVILEGES WHERE GRANTEE=CONCAT(CHAR(39),'$u',CHAR(39),'@',CHAR(39),'%',CHAR(39)) AND PRIVILEGE_TYPE<>'USAGE'")->fetchColumn()===0,'no_global_privileges');
 ok((int)$r->query("SELECT COUNT(*) FROM mysql.db WHERE User='$u'")->fetchColumn()===0,'no_schema_privileges');
 ok((int)$r->query("SELECT COUNT(*) FROM mysql.role_edges WHERE TO_USER='$u'")->fetchColumn()===0,'no_roles');
 $grants=implode('\n',db($u)->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN));
 ok(!preg_match('/SUPER|GRANT OPTION|ALL PRIVILEGES|CREATE|ALTER|DROP/',$grants),'grant_review');
 ok((int)$r->query("SELECT COUNT(*) FROM mysql.global_grants WHERE USER='$u'")->fetchColumn()===0,'no_dynamic_privileges');
 denied(fn()=>db($u)->exec('CREATE TABLE forbidden(id INT)'),'42000');
 denied(fn()=>db($u)->exec("GRANT SELECT ON mxmed.platform_audit_events TO 'r2writer'@'%'"),'42000');
}
denied(fn()=>$d->query('SELECT * FROM platform_audit_events'),'42000');
denied(fn()=>$w->exec('UPDATE platform_audit_stream_heads SET last_sequence_number=9'),'42000');
$a=new PdoCanonicalAuditTransactionAdapter($w);$a->begin();$a->ensureHead('lock',str_repeat('0',64),'sha256-hex-v1');$a->commit();
foreach(['commit','rollBack'] as $end){
 $a->begin();$head=$a->lockHead('lock');ok(array_keys($head)===['last_sequence_number','last_event_hash','hash_version','updated_at'],'lock_columns');
 $b=db('r2definer');$b->exec('SET SESSION innodb_lock_wait_timeout=1');
 denied(fn()=>$b->exec("UPDATE platform_audit_stream_heads SET last_sequence_number=0 WHERE stream_key='lock'"),'HY000',1205);
 $a->$end();$b->exec("UPDATE platform_audit_stream_heads SET last_sequence_number=0 WHERE stream_key='lock'");
}
$t='2026-08-23T04:42:11.123456Z';$h=str_repeat('a',64);
$a->begin();$a->lockHead('lock');$a->updateHead('lock',0,str_repeat('0',64),'sha256-hex-v1',null,1,$h,'sha256-hex-v1',$t);$a->commit();
$baseline=$w->query("SELECT * FROM platform_audit_stream_heads WHERE stream_key='lock'")->fetch(PDO::FETCH_ASSOC);
ok($baseline['last_sequence_number']===1&&$baseline['last_event_hash']===$h&&$baseline['updated_at']==='2026-08-23 04:42:11.123456','cas_exact_success');
$good=['lock',1,$h,'sha256-hex-v1',$t,2,str_repeat('b',64),'sha256-hex-v1','2026-08-23T04:42:12.123456Z'];
foreach([0=> 'missing',1=>0,2=>str_repeat('c',64),3=>null,4=>'2026-08-23T04:42:10.123456Z',5=>7] as $i=>$v){$args=$good;$args[$i]=$v;denied(function()use($w,$args){$s=$w->prepare('CALL audit_mp01c_advance_stream_head_cas_v1(?,?,?,?,?,?,?,?,?)');try{$s->execute($args);}finally{$s->closeCursor();}},'45000');ok($baseline===$w->query("SELECT * FROM platform_audit_stream_heads WHERE stream_key='lock'")->fetch(PDO::FETCH_ASSOC),'failed_cas_no_mutation');}
foreach($r->query("SELECT SECURITY_TYPE,DEFINER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='mxmed'") as $row)ok($row['SECURITY_TYPE']==='DEFINER'&&$row['DEFINER']==='r2definer@%','restricted_definer');
echo "R2_SQL_QA=PASS\n";

final class ProofFailureSignal implements AuditProducerFailureSignalPort
{
    public array $signals = [];
    public function signal(AuditProducerFailureSignal $signal): void
    {
        $this->signals[] = $signal->safePayload();
    }
}

final class UnusedProofAuthIdentifierSecret implements AuthIdentifierAuditSecretProvider
{
    public function currentAuthIdentifierAuditKey(): array
    {
        return [
            'namespace' => HmacSha256AuthIdentifierAuditHasher::REQUIRED_NAMESPACE,
            'version' => 'unused-proof-v1',
            'secret' => 'unused-proof-secret-32-bytes-minimum',
        ];
    }
}

final class UnusedProofIpHasher implements AuditIpHasher
{
    public function hashTrustedNetworkAddress(string $trustedNetworkAddress): array
    {
        throw new RuntimeException('unexpected_ip_hash_invocation');
    }
}

final class NegativeRollbackAfterInsert extends RuntimeException {}

final class FailAfterInsertTransaction implements CanonicalAuditTransactionPort
{
    public bool $insertObserved = false;
    public bool $rollbackObserved = false;

    public function __construct(private CanonicalAuditTransactionPort $delegate, private bool $failAfterCas=false) {}
    public function begin(): void { $this->delegate->begin(); }
    public function ensureHead(string $streamKey, string $genesisHash, string $hashVersion): void
    {
        $this->delegate->ensureHead($streamKey, $genesisHash, $hashVersion);
    }
    public function lockHead(string $streamKey): array { return $this->delegate->lockHead($streamKey); }
    public function assertLegacyHeadMatchesLatest(string $streamKey, int $sequenceNumber, string $eventHash): void
    {
        $this->delegate->assertLegacyHeadMatchesLatest($streamKey, $sequenceNumber, $eventHash);
    }
    public function insertEvent(array $row): int
    {
        $count = $this->delegate->insertEvent($row);
        $this->insertObserved = $count === 1;
        if (!$this->failAfterCas) throw new NegativeRollbackAfterInsert('negative_force_rollback_after_event_insert');
        return $count;
    }
    public function updateHead(string $streamKey, int $expectedSequence, string $expectedHash, ?string $expectedHashVersion, ?string $expectedUpdatedAt, int $newSequence, string $newHash, string $newHashVersion, string $newUpdatedAt): int
    {
        $count = $this->delegate->updateHead($streamKey, $expectedSequence, $expectedHash, $expectedHashVersion, $expectedUpdatedAt, $newSequence, $newHash, $newHashVersion, $newUpdatedAt);
        if ($this->failAfterCas) throw new NegativeRollbackAfterInsert('negative_force_rollback_after_cas');
        return $count;
    }
    public function commit(): void { $this->delegate->commit(); }
    public function rollBack(): void
    {
        $this->delegate->rollBack();
        $this->rollbackObserved = true;
    }
}


function canonicalTime(mixed $value): ?string
{
    if ($value === null) return null;
    $text = (string)$value;
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/D', $text) !== 1) {
        throw new RuntimeException('invalid_physical_datetime6');
    }
    return str_replace(' ', 'T', $text) . 'Z';
}

function makeWriter(CanonicalAuditTransactionPort $transaction): CanonicalAuditWriter
{
    return new CanonicalAuditWriter(
        CanonicalAuditPolicyRegistry::canonical(),
        new CanonicalAuditMetadataSanitizer(),
        new TrustedAuditContextValidator(),
        new RandomAuditUuidProvider(),
        new SystemAuditUtcClock(),
        new UnusedProofIpHasher(),
        new CoarseAuditUserAgentSummarizer(),
        $transaction,
        new CanonicalAuditSealer(new CanonicalAuditSerializer()),
        new AuditV1PhysicalMapper(),
    );
}

function proofRequest(string $requestId, string $correlationId, string $sessionId): TrustedRequestContext
{
    return TrustedRequestContext::fromTrustedBoundary(
        $requestId,
        $correlationId,
        'AUTH_LOGIN',
        $sessionId,
        'AUTH',
        'POST /auth/login',
        null,
        null,
        'HTTP',
        'a1_g1_attempt2_final_real_producer_proof',
        new UuidV4ContextIdPolicy(),
    );
}

function proofActor(string $targetId): TrustedActorContext
{
    return TrustedActorContext::fromTrustedBackend([
        'authenticated_identity_id' => $targetId,
        'real_actor_type' => 'ACCOUNT',
        'real_actor_id' => $targetId,
        'actor_role' => 'ACCOUNT_OWNER',
        'actor_scope' => 'SELF',
        'target_type' => 'ACCOUNT',
        'target_id' => $targetId,
        'authorization_provenance' => 'a1_g1_final_controlled_physical_proof',
        'trust_source' => 'backend_trusted',
    ]);
}


$writer=makeWriter(new PdoCanonicalAuditTransactionAdapter($w));$signal=new ProofFailureSignal();
$producer=new CanonicalIdentityAuditProducer(new BoundedBestEffortAuditEmitter(new CanonicalAuditWriterAdapter($writer),new AuditWriterContextBridge(),$signal,new Mp01eEventScopePolicy()),new HmacSha256AuthIdentifierAuditHasher(new UnusedProofAuthIdentifierSecret()),new IdentityAuditReasonResolver());
$request=proofRequest('11111111-1111-4111-8111-111111111111','22222222-2222-4222-8222-222222222222','synthetic-session');
$target=TrustedIdentityId::fromAuthoritativeOutcome('q2-synthetic');$actor=proofActor('q2-synthetic');
$result=$producer->loginSucceeded($request,$actor,$target,'USER_REQUEST');ok($result->auditSucceeded&&count($signal->signals)===0,'real_producer_written');
$event=$w->query('SELECT * FROM platform_audit_events')->fetch(PDO::FETCH_ASSOC);$streamKey=$event['stream_key'];
$head=$w->query("SELECT * FROM platform_audit_stream_heads WHERE stream_key=".$w->quote($streamKey))->fetch(PDO::FETCH_ASSOC);
ok((int)$w->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn()===1&&$head['last_sequence_number']===1&&$head['last_event_hash']===$event['event_hash']&&$event['previous_hash']===str_repeat('0',64),'one_event_head_chain');
    $metadata = json_decode($event['metadata_json'],true,512,JSON_THROW_ON_ERROR);
    $internal = $metadata['_audit_v1'] ?? null;
    $producerMetadata = $metadata['producer_metadata'] ?? null;
    if (!is_array($internal) || !is_array($producerMetadata)) throw new RuntimeException('invalid_physical_metadata_projection');
    $riskToSeverity = ['R0' => 'INFO', 'R1' => 'WARN', 'R2' => 'HIGH', 'R3' => 'CRITICAL'];
    $realReference = explode(':', (string)$event['real_actor_reference'], 2);
    $effectiveReference = $event['effective_actor_reference'] === null ? null : explode(':', (string)$event['effective_actor_reference'], 2);
    $envelope = CanonicalAuditEnvelope::assembleByWriter([
        'event_id' => $internal['event_id'],
        'occurred_at' => canonicalTime($event['occurred_at_utc']),
        'event_type' => $event['action'],
        'event_version' => $event['schema_version'],
        'severity' => $riskToSeverity[$event['risk_level']] ?? null,
        'result' => $event['outcome'],
        'actor_identity_id' => $realReference[1] ?? null,
        'actor_type' => $realReference[0] ?? null,
        'actor_role' => $internal['actor_role'],
        'actor_scope' => $internal['actor_scope'],
        'effective_entity_type' => $effectiveReference[0] ?? null,
        'effective_entity_id' => $effectiveReference[1] ?? null,
        'target_type' => $event['resource_type'],
        'target_id' => $event['resource_reference'],
        'session_id' => $internal['session_id'],
        'request_id' => $event['request_id'],
        'correlation_id' => $event['correlation_id'],
        'source_module' => $internal['source_module'],
        'source_route' => $internal['source_route'],
        'reason_code' => $event['reason_code'],
        'retention_class' => $internal['retention_class'],
        'metadata_json' => $producerMetadata,
        'ip_hmac' => $internal['ip_hmac'],
        'user_agent_summary' => $internal['user_agent_summary'],
        'sequence_number' => (int)$event['sequence_number'],
        'previous_hash' => $event['previous_hash'],
        'event_hash' => $event['event_hash'],
        'created_at' => canonicalTime($event['created_at_utc']),
    ]);
    $computedHash = hash('sha256', (new CanonicalAuditSerializer())->bytes($envelope, $streamKey));

ok(hash_equals($event['event_hash'],$computedHash),'real_producer_hash_valid');

$tx=new FailAfterInsertTransaction(new PdoCanonicalAuditTransactionAdapter($w));$rollbackWriter=makeWriter($tx);
$input=new CanonicalAuditEventInput('AUTH_LOGIN_SUCCEEDED','SUCCESS','USER_REQUEST',null,null,'ACCOUNT','q2-rollback',[]);
$ctx=(new AuditWriterContextBridge())->toAuditContext($request,proofActor('q2-rollback'));
try{$rollbackWriter->append($input,$ctx);throw new RuntimeException('rollback_expected');}catch(NegativeRollbackAfterInsert){}
ok($tx->insertObserved&&$tx->rollbackObserved,'post_insert_rollback');
ok((int)$w->query("SELECT COUNT(*) FROM platform_audit_events WHERE resource_reference='q2-rollback'")->fetchColumn()===0&&(int)$w->query("SELECT COUNT(*) FROM platform_audit_stream_heads WHERE stream_key='account:q2-rollback'")->fetchColumn()===0,'zero_partial_writes');
echo "R2_REAL_PRODUCER_AND_ROLLBACK=PASS\n";

$beforeEvents=$w->query('SELECT * FROM platform_audit_events ORDER BY stream_key, sequence_number')->fetchAll(PDO::FETCH_ASSOC);
$beforeHeads=$w->query('SELECT * FROM platform_audit_stream_heads ORDER BY stream_key')->fetchAll(PDO::FETCH_ASSOC);
$tx=new FailAfterInsertTransaction(new PdoCanonicalAuditTransactionAdapter($w),true);
try{makeWriter($tx)->append(new CanonicalAuditEventInput('AUTH_LOGIN_SUCCEEDED','SUCCESS','USER_REQUEST',null,null,'ACCOUNT','q2-synthetic',[]),(new AuditWriterContextBridge())->toAuditContext($request,$actor));throw new RuntimeException('rollback_expected');}catch(NegativeRollbackAfterInsert $e){ok($e->getMessage()==='negative_force_rollback_after_cas','cas_completed_before_failure');}
ok($tx->insertObserved&&$tx->rollbackObserved,'post_cas_rollback');
ok($beforeEvents===$w->query('SELECT * FROM platform_audit_events ORDER BY stream_key, sequence_number')->fetchAll(PDO::FETCH_ASSOC)&&$beforeHeads===$w->query('SELECT * FROM platform_audit_stream_heads ORDER BY stream_key')->fetchAll(PDO::FETCH_ASSOC),'post_cas_zero_partial_writes');
