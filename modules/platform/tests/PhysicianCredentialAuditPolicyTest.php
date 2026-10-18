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
    public array $targetQueries = [];
    public array $eventRows = [];
    public string $database = 'mxmed_gate4d_preview_act04_audit';
    public bool $changeTarget = false;
    public bool $routineSecurityDrift = false;
    public bool $routineBodyDrift = false;
    public bool $routineSignatureDrift = false;
    public function __construct() {}
    public function beginTransaction(): bool { $this->active=true; return true; }
    public function inTransaction(): bool { return $this->active; }
    public function commit(): bool { $this->commits++; $this->active=false; return true; }
    public function rollBack(): bool { $this->rollbacks++; $this->active=false; return true; }
    public function query(string $query, ?int $fetchMode=null, mixed ...$fetchModeArgs): PDOStatement|false {
        check($this->active && $query==='SELECT DATABASE()', 'only transaction-scoped database inspection');
        $this->targetQueries[]=$query;
        return new CredentialAuditStatementProbe($this, $query);
    }
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        check($this->active, 'SQL outside caller transaction');
        $this->calls[]=$query;
        return new CredentialAuditStatementProbe($this, $query);
    }
}
final class CredentialAuditStatementProbe extends PDOStatement {
    private bool $fetched=false;
    private array $params=[];
    public function __construct(private CredentialAuditPdoProbe $owner, private string $sql) {}
    public function execute(?array $params=null): bool {
        $this->params=$params??[];
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
        if (str_contains($this->sql,'information_schema.ROUTINES')) {
            $name=$this->params[1]??'';
            check(($this->params[0]??'')===$this->owner->database, 'metadata uses the connected synthetic target');
            $file=match($name){
                'audit_mp01c_lock_stream_head_v1'=>'audit_mp01c_r2_lock.sql',
                'audit_mp01c_advance_stream_head_cas_v1'=>'audit_mp01c_r2_cas.sql',
                default=>throw new RuntimeException('unexpected routine lookup'),
            };
            // Driver fixture reports the shipped routine body; no SQL is executed.
            $source=(string)file_get_contents(__DIR__.'/../db/routines/'.$file);
            $body=substr($source,strpos($source,"\nBEGIN")+1);
            $body=(string)preg_replace('/END\s*\$\$.*\z/s','END',$body);
            $body=str_replace('\\\\','\\',$body);
            $body=str_replace('`mxmed`.`platform_audit_stream_heads`',
                '`'.$this->owner->database.'`.`platform_audit_stream_heads`',$body);
            return ['ROUTINE_DEFINITION'=>$body.($this->owner->routineBodyDrift?' DRIFT':''),
                'SECURITY_TYPE'=>$this->owner->routineSecurityDrift?'INVOKER':'DEFINER',
                'SQL_DATA_ACCESS'=>$name==='audit_mp01c_lock_stream_head_v1'?'READS SQL DATA':'MODIFIES SQL DATA'];
        }
        if (!str_contains($this->sql,'lock_stream_head') || $this->fetched) return false;
        $this->fetched=true;
        return ['last_sequence_number'=>0,'last_event_hash'=>CanonicalAuditWriter::GENESIS_HASH,'hash_version'=>'sha256-hex-v1','updated_at'=>null];
    }
    public function fetchColumn(int $column=0): mixed {
        check($this->sql==='SELECT DATABASE()' && $column===0, 'expected target read');
        return $this->owner->changeTarget && count($this->owner->targetQueries)>1
            ? 'mxmed_gate4d_preview_changed' : $this->owner->database;
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT, mixed ...$args): array {
        check(str_contains($this->sql,'information_schema.PARAMETERS') && $mode===PDO::FETCH_NUM, 'expected routine signature inspection');
        check(($this->params[0]??'')===$this->owner->database, 'signature uses the connected synthetic target');
        $signature=[['IN','p_stream_key','varchar(191)','utf8mb4','utf8mb4_unicode_ci']];
        if (($this->params[1]??'')==='audit_mp01c_advance_stream_head_cas_v1') {
            foreach (['p_expected_sequence'=>'bigint','p_expected_hash'=>'char(64)',
                'p_expected_hash_version'=>'varchar(32)','p_expected_updated_at'=>'varchar(27)',
                'p_new_sequence'=>'bigint','p_new_hash'=>'char(64)',
                'p_new_hash_version'=>'varchar(32)','p_new_updated_at'=>'varchar(27)'] as $name=>$type) {
                $signature[]=['IN',$name,$type,$type==='bigint'?null:'utf8mb4',$type==='bigint'?null:'utf8mb4_unicode_ci'];
            }
        } else check(($this->params[1]??'')==='audit_mp01c_lock_stream_head_v1', 'expected lock signature');
        if ($this->owner->routineSignatureDrift) $signature[0][2]='varchar(190)';
        return $signature;
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
$laterEvents=['PHYSICIAN_EVIDENCE_CHANGED','PHYSICIAN_AUTHORITY_CHANGED',
    'PUBLIC_PROFILE_CORRECTION_RECEIVED','PUBLIC_PROFILE_CORRECTION_REVIEWED','PUBLIC_PROFILE_CORRECTION_APPLICATION','STAFF_ADMISSION_CASE_CHANGED','STAFF_ADMISSION_CASE_READ','STAFF_MEDIA_CHANGED','STAFF_MEDIA_READ'];
check(count($rows)===61 && count(CanonicalAuditEventType::all())===61,'61 reviewed events/policies through ASE-ADM02-IMP03');
check(array_column($rows,'event_type')===CanonicalAuditEventType::all(),'catalog order');
// The reviewed OP-IMP07 catalog at d195328b had 52 rows. Later C4 and
// public-correction events are append-only, not changes to credential policy.
check(hash('sha256',json_encode(array_slice($rows,0,52),JSON_UNESCAPED_SLASHES))
    ==='2444b5ba944db0bc0a29390a073699e1b85efe4e061376bf6375c781df6db581','published 52-policy prefix unchanged');
check(array_slice(CanonicalAuditEventType::all(),52)===$laterEvents,'exact reviewed C4, correction and admission extensions');
check(array_slice(CanonicalAuditEventType::all(),38,5)===array_keys($metadata),'exactly five new events');
// OP-IMP01 and OP-IMP07 extend only invitation metadata in these historical
// rows. Keep the published a18cc367 digest after removing those exact additions.
// The older QA02B digest was stale already at the published starting HEAD.
$historical=array_slice($rows,0,38);
foreach ($historical as &$row) {
    if (in_array($row['event_type'],['INVITATION_CREATED','INVITATION_ACCEPTED','INVITATION_REVOKED'],true)) {
        check($row['allowed_producer_metadata']===['operator_action','actor_membership_id','profile_doctor_id','membership_id','invitation_id','grant_revision','owner_membership_id','capabilities','operation_reason','presentation_version','presentation_id','personal_alias','seat_position','presentation_revision','presentation_released'],'OP-IMP01/07 exact invitation metadata extensions');
        $row['allowed_producer_metadata']=[];
    }
}
unset($row);
check(hash('sha256',json_encode($historical,JSON_UNESCAPED_SLASHES))==='b1fb43367b584e9ae569774d1be823c443b6649d5c51de589b5222a53d25960a','reviewed published a18cc367 policy baseline (without OP-IMP01 metadata)');
$catalog=new SensitiveAdminActionCatalog();
check($catalog->all()===[],'generic catalog remains empty');
denied(fn()=>$catalog->definition('PROFILE_OPERATOR_DELEGATION_CHANGED'),'specific_canonical_event_must_not_be_duplicated');
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
        check(count($pdo->eventRows)===1 && count($pdo->calls)===8
            && count($pdo->targetQueries)===4,'SQL adapter protocol includes target/body/security/signature validation');
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
// Exercise the current production target/routine validation through the driver
// boundary, rather than bypassing it to make the historical probe pass.
$input=new CanonicalAuditEventInput('PHYSICIAN_CREDENTIAL_VERIFIED','SUCCESS','ADMIN_DECISION',
    'doctor','synthetic-doctor','PHYSICIAN_CREDENTIAL','123',$metadata['PHYSICIAN_CREDENTIAL_VERIFIED']);
foreach (['database'=>'audit_database_target_denied','changeTarget'=>'audit_database_target_changed',
    'routineSecurityDrift'=>'audit_routine_security_drift','routineBodyDrift'=>'audit_routine_drift',
    'routineSignatureDrift'=>'audit_routine_signature_drift'] as $flag=>$error) {
    $pdo=new CredentialAuditPdoProbe();
    $pdo->$flag=$flag==='database'?'unapproved_database':true;
    $pdo->beginTransaction();
    denied(fn()=>writer($pdo)->append($input,$context),$error);
    check($pdo->eventRows===[] && $pdo->rollbacks===1 && $pdo->commits===0,'invalid database/routine denied before event insert and rolled back');
}
echo "AUDIT_TARGET_AND_ROUTINE_DRIFT_REJECTIONS=5_PASS\n";
echo "AUDCRD01_POLICY_AND_WRITER=PASS\nQA02B_AUTHORIZED_OWNERSHIP_POLICIES_ENRICHED=3\n";
echo "JOINED_PDO_TRANSACTION_PROTOCOL=PASS\nPHYSICAL_DATABASE_TEST=NOT_RUN\nDB_WRITES=0\n";
