<?php
declare(strict_types=1);

// No database connection or writes. Exercise the production writer and BOTH
// production PDO adapters; the PDO driver boundary alone is a recording double.
foreach (glob(__DIR__.'/../contracts/*.php') as $f) require_once $f;
foreach (glob(__DIR__.'/../repositories/*.php') as $f) require_once $f;
foreach (glob(__DIR__.'/../services/*.php') as $f) require_once $f;
require_once __DIR__.'/../audit/SensitiveAdminActionCatalog.php';

use Platform\Contracts\{CanonicalAuditEventInput,CanonicalAuditEventType,TrustedAuditContext};
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper,CorrelatableOperationCatalog,SourceModuleCatalog};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
use Platform\Audit\SensitiveAdminActionCatalog;

function check(bool $ok, string $name): void {
    if (!$ok) throw new RuntimeException($name);
}
function denied(callable $probe, string $message): void {
    try { $probe(); } catch (Throwable $e) {
        check($e->getMessage() === $message, 'unexpected rejection: '.$e->getMessage());
        return;
    }
    throw new RuntimeException('expected rejection: '.$message);
}

final class CredentialAuditPdoProbe extends PDO {
    public bool $active = false;
    public bool $failInsert = false;
    public bool $failCas = false;
    public int $commits = 0;
    public int $rollbacks = 0;
    public array $calls = [];
    public array $eventRows = [];
    public function __construct() {}
    public function beginTransaction(): bool { $this->active=true; return true; }
    public function inTransaction(): bool { return $this->active; }
    public function commit(): bool { $this->commits++; $this->active=false; return true; }
    public function rollBack(): bool { $this->rollbacks++; $this->active=false; return true; }
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        check($this->active, 'SQL outside caller transaction');
        $this->calls[]=$query;
        return new CredentialAuditStatementProbe($this, $query);
    }
}
final class CredentialAuditStatementProbe extends PDOStatement {
    private bool $fetched=false;
    public function __construct(private CredentialAuditPdoProbe $owner, private string $sql) {}
    public function execute(?array $params=null): bool {
        if (str_starts_with($this->sql,'INSERT INTO platform_audit_events')) {
            if ($this->owner->failInsert) throw new RuntimeException('synthetic_insert_failure');
            $this->owner->eventRows[]=$params;
        }
        if (str_contains($this->sql,'advance_stream_head') && $this->owner->failCas) {
            throw new PDOException('synthetic_cas_failure',45000);
        }
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT, int $cursorOrientation=PDO::FETCH_ORI_NEXT, int $cursorOffset=0): mixed {
        if (!str_contains($this->sql,'lock_stream_head') || $this->fetched) return false;
        $this->fetched=true;
        return ['last_sequence_number'=>0,'last_event_hash'=>CanonicalAuditWriter::GENESIS_HASH,'hash_version'=>'sha256-hex-v1','updated_at'=>null];
    }
    public function nextRowset(): bool { return false; }
    public function closeCursor(): bool { return true; }
    public function rowCount(): int { return 1; }
}
function writer(CredentialAuditPdoProbe $pdo): CanonicalAuditWriter {
    return new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),
        new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),
        new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),
        new JoinedPdoCanonicalAuditTransactionAdapter($pdo),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
}
$metadata=[
    'PHYSICIAN_CREDENTIAL_PROVISIONED'=>['credential_type'=>'SPECIALTY','new_verification_status'=>'PENDING_REVIEW','new_lifecycle_status'=>'ACTIVE'],
    'PHYSICIAN_CREDENTIAL_VERIFIED'=>['credential_type'=>'SPECIALTY','previous_verification_status'=>'PENDING_REVIEW','new_verification_status'=>'VERIFIED'],
    'PHYSICIAN_CREDENTIAL_REJECTED'=>['credential_type'=>'SPECIALTY','previous_verification_status'=>'PENDING_REVIEW','new_verification_status'=>'REJECTED'],
    'PHYSICIAN_CREDENTIAL_INACTIVATED'=>['credential_type'=>'SPECIALTY','previous_lifecycle_status'=>'ACTIVE','new_lifecycle_status'=>'INACTIVE'],
    'PHYSICIAN_CREDENTIAL_REVOKED'=>['credential_type'=>'SPECIALTY','previous_lifecycle_status'=>'ACTIVE','new_lifecycle_status'=>'REVOKED'],
];
$rows=CanonicalAuditPolicyRegistry::canonicalRows();
check(count($rows)===43 && count(CanonicalAuditEventType::all())===43,'43 events/policies');
check(array_column($rows,'event_type')===CanonicalAuditEventType::all(),'catalog order');
check(array_slice(CanonicalAuditEventType::all(),38)===array_keys($metadata),'exactly five new events');
check(hash('sha256',json_encode(array_slice($rows,0,38),JSON_UNESCAPED_SLASHES))==='aa7327ccd64e9e4537cc9da4e86cb7216d0a66684f0da9e4122704390f64d958','existing 38 policies unchanged');
$catalog=new SensitiveAdminActionCatalog();
check($catalog->all()===[],'generic catalog remains empty');
denied(fn()=>$catalog->definition('UNRATIFIED_ACTION'),'unknown_sensitive_admin_action_key');
$context=TrustedAuditContext::fromServer('synthetic-advisor','account','ADVISOR','profile',
    'synthetic-request','synthetic-correlation','synthetic-session',null,null,'PROFILE','trusted-service');
$registry=CanonicalAuditPolicyRegistry::canonical();
foreach ($metadata as $event=>$fields) {
    check(CanonicalAuditEventType::assertKnown($event)===$event,'known event');
    $policy=$registry->policyFor($event);
    check($policy['producer']==='Profiles' && $policy['retention_class']==='OWNERSHIP','profile governance');
    check($policy['actor_required'] && $policy['session_required'] && $policy['target_required'],'authority requirements');
    check($policy['allowed_producer_metadata']===array_keys($fields),'event-specific metadata');
    check((new CorrelatableOperationCatalog())->operationForEvent($event)==='PHYSICIAN_CREDENTIAL_LIFECYCLE','operation mapping');
    check((new SourceModuleCatalog())->moduleForEvent($event)==='PROFILE','module mapping');
    denied(fn()=>$catalog->definition($event),'specific_canonical_event_must_not_be_duplicated');
    foreach ([['SUCCESS','ADMIN_DECISION'],['FAILURE','STATE_CONFLICT'],['FAILURE','INTERNAL_ERROR'],['DENIED','POLICY_DENIED']] as [$result,$reason]) {
        $pdo=new CredentialAuditPdoProbe(); $pdo->beginTransaction();
        // Doctor ownership uses existing effective entity fields, not metadata.
        $input=new CanonicalAuditEventInput($event,$result,$reason,'doctor','synthetic-doctor','PHYSICIAN_CREDENTIAL','123',$fields);
        $envelope=writer($pdo)->append($input,$context);
        check($pdo->active && $pdo->commits===0,'writer does not commit caller transaction');
        check(count($pdo->eventRows)===1 && count($pdo->calls)===4,'existing SQL adapter protocol');
        check($envelope->value('target_id')==='123' && $envelope->value('target_type')==='PHYSICIAN_CREDENTIAL','credential target');
        check($envelope->value('effective_entity_id')==='synthetic-doctor','doctor context');
        check($envelope->value('session_id')==='synthetic-session','session retained');
        check($envelope->value('sequence_number')===1 && strlen($envelope->value('event_hash'))===64,'canonical sealed chain');
        $pdo->rollBack();
        check($pdo->rollbacks===1 && !$pdo->active,'caller can roll back after append');
    }
    foreach (['unknown','action_code','license_number','institution_name','professional_area_label','source_reference','uploaded_documents','raw_evidence','notes','doctor_id','previous_unrelated_status'] as $key) {
        $pdo=new CredentialAuditPdoProbe();$pdo->beginTransaction();
        denied(fn()=>writer($pdo)->append(new CanonicalAuditEventInput($event,'SUCCESS','ADMIN_DECISION','doctor','synthetic-doctor','PHYSICIAN_CREDENTIAL','123',$fields+[$key=>'forbidden']),$context),'unknown_metadata');
        check($pdo->calls===[],'forbidden metadata rejected before SQL');$pdo->rollBack();
    }
    foreach ([['SUCCESS','POLICY_DENIED'],['FAILURE','ADMIN_DECISION'],['DENIED','ADMIN_DECISION'],['PARTIAL','STATE_CONFLICT'],['SUCCESS','SYSTEM_POLICY']] as [$result,$reason]) {
        denied(fn()=>$registry->assertAllowed($event,$result,$reason),'result_reason_pair_not_allowed');
    }
    $input=new CanonicalAuditEventInput($event,'SUCCESS','ADMIN_DECISION','doctor','synthetic-doctor','PHYSICIAN_CREDENTIAL','123',$fields);
    denied(fn()=>new CanonicalAuditEventInput($event,'SUCCESS','ADMIN_DECISION','doctor','synthetic-doctor','PHYSICIAN_CREDENTIAL','',$fields),'missing_audit_target');
    $missingActor=TrustedAuditContext::fromServer('','account','ADVISOR','profile','request','correlation','session',null,null,'PROFILE','trusted-service');
    denied(fn()=>writer(new CredentialAuditPdoProbe())->append($input,$missingActor),'invalid_trusted_context:actorIdentityId');
    $pdo=new CredentialAuditPdoProbe();
    denied(fn()=>writer($pdo)->append($input,$context),'audit_outer_transaction_required');
    foreach (['failInsert'=>'synthetic_insert_failure','failCas'=>'head_update_failed'] as $flag=>$error) {
        $pdo=new CredentialAuditPdoProbe();$pdo->$flag=true;$pdo->beginTransaction();
        denied(fn()=>writer($pdo)->append($input,$context),$error);
        check($pdo->rollbacks===1 && !$pdo->active && $pdo->commits===0,'audit failure rolls back caller transaction');
    }
    $pdo=new CredentialAuditPdoProbe();$pdo->beginTransaction();writer($pdo)->append($input,$context);
    check($pdo->commits===0,'only caller commits');$pdo->commit();
    check($pdo->commits===1 && !$pdo->active,'caller commit accepted');
    echo $event."_ACCEPTED=true\n";
}
echo "AUDCRD01_POLICY_AND_WRITER=PASS\nEXISTING_38_EVENT_POLICIES_CHANGED=0\n";
echo "JOINED_PDO_TRANSACTION_PROTOCOL=PASS\nPHYSICAL_DATABASE_TEST=NOT_RUN\nDB_WRITES=0\n";
