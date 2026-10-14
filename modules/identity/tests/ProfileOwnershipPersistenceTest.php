<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();

use Identity\Adapters\{CanonicalProfileMembershipRoleChangeAuditAdapter,PdoProfileOwnershipApprovalAdapter,
    PdoProfileOwnershipApproverAuthorityAdapter,PdoProfileOwnershipStepUpAdapter};
use Identity\Contracts\{AuthenticatedAccessContext,Clock,PasswordHash,ProfileMembershipRoleChangeRequest,RateLimitKeyHasher,
    SessionId,SessionPrincipal,SessionRecord,SessionTokenDigest};
use Identity\Repositories\{AccountCredentialRepository,AccountMembershipRepository,IdentityAccountRepository,ProfileOwnershipStepUpRepository};
use Identity\Services\{CredentialAuthenticationService,ProfileMembershipRoleChangeService,ProfileOwnershipStepUpIssuanceService,RateLimitService};
use Platform\Repositories\CanonicalAuditTransactionPort;
use Platform\Services\{AuditV1PhysicalMapper,CanonicalAuditMetadataSanitizer,CanonicalAuditPolicyRegistry,CanonicalAuditSealer,
    CanonicalAuditSerializer,CanonicalAuditWriter,CoarseAuditUserAgentSummarizer,EnvironmentAuditSecretProvider,
    HmacSha256AuditIpHasher,RandomAuditUuidProvider,SystemAuditUtcClock,TrustedAuditContextValidator};

function qa02b(bool $condition,string $label):void{if(!$condition)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
function qa02bDenied(Closure $call,string $label):void{try{$call();throw new LogicException('unexpected_success '.$label);}catch(RuntimeException){echo 'PASS '.$label.PHP_EOL;}}
final class Qa02bClock implements Clock { public function now():DateTimeImmutable{return new DateTimeImmutable('2026-10-14T18:00:00+00:00');} }

/** Disposable-only canonical writer transaction; production continues to require the controlled stored routines. */
final class Qa02bAuditTransaction implements CanonicalAuditTransactionPort
{
    public bool $failInsert=false;
    public function __construct(private PDO $pdo){}
    public function begin():void{if(!$this->pdo->inTransaction())throw new RuntimeException('outer_transaction_required');}
    public function commit():void{if(!$this->pdo->inTransaction())throw new RuntimeException('outer_transaction_required');}
    public function rollBack():void{if($this->pdo->inTransaction())$this->pdo->rollBack();}
    public function ensureHead(string $streamKey,string $genesisHash,string $hashVersion):void
    {$s=$this->pdo->prepare('INSERT IGNORE INTO platform_audit_stream_heads VALUES(?,0,?,?,NULL)');$s->execute([$streamKey,$genesisHash,$hashVersion]);}
    public function lockHead(string $streamKey):array
    {$s=$this->pdo->prepare('SELECT last_sequence_number,last_event_hash,hash_version,updated_at FROM platform_audit_stream_heads WHERE stream_key=? FOR UPDATE');$s->execute([$streamKey]);$r=$s->fetch(PDO::FETCH_ASSOC);return ['last_sequence_number'=>(int)$r['last_sequence_number'],'last_event_hash'=>$r['last_event_hash'],'hash_version'=>$r['hash_version'],'updated_at'=>$r['updated_at']===null?null:str_replace(' ','T',$r['updated_at']).'Z'];}
    public function assertLegacyHeadMatchesLatest(string $streamKey,int $sequenceNumber,string $eventHash):void{throw new RuntimeException('legacy_not_used');}
    public function insertEvent(array $row):int
    {if($this->failInsert)throw new RuntimeException('synthetic_audit_write_failure');$c=array_keys($row);$s=$this->pdo->prepare('INSERT INTO platform_audit_events(`'.implode('`,`',$c).'`) VALUES(:'.implode(',:',$c).')');$s->execute($row);return $s->rowCount();}
    public function updateHead(string $streamKey,int $expectedSequence,string $expectedHash,?string $expectedHashVersion,?string $expectedUpdatedAt,int $newSequence,string $newHash,string $newHashVersion,string $newUpdatedAt):int
    {$s=$this->pdo->prepare('UPDATE platform_audit_stream_heads SET last_sequence_number=?,last_event_hash=?,hash_version=?,updated_at=? WHERE stream_key=? AND last_sequence_number=? AND last_event_hash=?');$s->execute([$newSequence,$newHash,$newHashVersion,str_replace(['T','Z'],[' ', ''],$newUpdatedAt),$streamKey,$expectedSequence,$expectedHash]);return $s->rowCount();}
}

function qa02bWriter(PDO $pdo,Qa02bAuditTransaction $tx):CanonicalAuditWriter{return new CanonicalAuditWriter(
    CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),new TrustedAuditContextValidator(),
    new RandomAuditUuidProvider(),new SystemAuditUtcClock(),new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),
    new CoarseAuditUserAgentSummarizer(),$tx,new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());}
function qa02bActor():AuthenticatedAccessContext{
    $principal=new SessionPrincipal('qa_target',1,'active','2026-10-14 17:50:00');
    return new AuthenticatedAccessContext($principal,new SessionRecord(new SessionId(str_pad('qa02b-session',32,'x')),
        new SessionTokenDigest(str_repeat('a',64)),$principal,new DateTimeImmutable('2026-10-14T10:00:00+00:00'),
        new DateTimeImmutable('2026-10-14T17:50:00+00:00'),new DateTimeImmutable('2026-10-14T18:20:00+00:00'),
        new DateTimeImmutable('2026-10-14T22:00:00+00:00')));
}
function qa02bRequest(PDO $pdo,string $operation='123e4567-e89b-42d3-a456-426614174000',string $approval='approval-qa02b-0001',string $proof='pending-proof-reference'):ProfileMembershipRoleChangeRequest{
    $updated=(string)$pdo->query("SELECT updated_at FROM auth_account_memberships WHERE membership_id='qa_membership'")->fetchColumn();
    return new ProfileMembershipRoleChangeRequest($operation,'qa_membership','qa_target','qa_profile','collaborator','profile','active',$updated,'owner',$approval,$proof);
}
function qa02bApproval(PDO $pdo,ProfileMembershipRoleChangeRequest $r,string $expires='2026-10-14 18:30:00',string $approver='qa_approver'):void{
    $s=$pdo->prepare("INSERT INTO auth_profile_ownership_approvals
      (approval_reference,operation_id,case_reference,decision_reference,approver_account_id,executor_account_id,target_account_id,
       membership_id,profile_doctor_id,expected_role,expected_scope,new_role,status,approved_at,expires_at)
      VALUES(?,?,?,?,?,?,?,?,?,?,?,?,'APPROVED','2026-10-14 17:55:00',?)");
    $s->execute([$r->approvalReference,$r->operationId,'case-qa02b-0001','decision-'.$r->operationId,$approver,'qa_target','qa_target',
        $r->membershipId,$r->profileDoctorId,$r->expectedRole,$r->expectedScope,$r->newRole,$expires]);
    foreach([[1,$approver,'endorsement-primary-0001','mfa-primary-0001'],[2,'qa_approver_2','endorsement-second-0001','mfa-second-0001']] as [$slot,$account,$endorsement,$mfa]){
        $s=$pdo->prepare("INSERT INTO auth_profile_ownership_approval_endorsements
          (endorsement_id,approval_reference,endorsement_slot,operation_id,case_reference,approver_account_id,target_account_id,
           membership_id,profile_doctor_id,capability,staff_session_id,credential_version,mfa_evidence_reference,status,approved_at,expires_at)
          VALUES(?,?,?,?,?,?,?,?,?,'profile_initial_ownership_approve',?,1,?,'APPROVED','2026-10-14 17:55:00',?)");
        $s->execute([$endorsement,$r->approvalReference,$slot,$r->operationId,'case-qa02b-0001',$account,'qa_target',
            $r->membershipId,$r->profileDoctorId,'staff-session-'.$slot,$mfa,$expires]);
    }
}
function qa02bReset(PDO $pdo):void{
    $pdo->exec('TRUNCATE TABLE platform_audit_events');$pdo->exec('TRUNCATE TABLE platform_audit_stream_heads');
    foreach(['auth_profile_ownership_step_up_proofs','auth_profile_ownership_approval_endorsements','auth_profile_ownership_approvals','auth_rate_limit_buckets','internal_capability_delegations','internal_operator_grants','internal_staff','auth_account_memberships','auth_account_credentials','auth_accounts','profiles_doctors'] as $table)$pdo->exec('DELETE FROM '.$table);
    $pdo->exec("INSERT INTO profiles_doctors(doctor_id,display_name) VALUES('qa_profile','Synthetic Profile'),('qa_other','Other')");
    $hash=PasswordHash::hash('Synthetic-Password-123!');
    foreach(['qa_target','qa_approver','qa_approver_2'] as $id){$pdo->prepare("INSERT INTO auth_accounts VALUES(?,?,?,'active','2026-10-14 10:00:00',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);$pdo->prepare("INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,'2026-10-14 10:00:00',1)")->execute([$id,$hash]);}
    foreach(['qa_approver','qa_approver_2'] as $id){$pdo->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,'MASTER_ADMIN','ACTIVE')")->execute([$id]);$pdo->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),?,'profile_initial_ownership_approve','ACTIVE')")->execute([$id]);}
    $pdo->exec("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status,assignment_source,updated_at) VALUES('qa_membership','qa_target','qa_profile','collaborator','profile','active','synthetic','2026-10-14 17:00:00')");
}

$root=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db='mxmed_identity_qa02b_'.bin2hex(random_bytes(5));$root->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try{
 $p=new PDO("mysql:host=127.0.0.1;port=3306;dbname=$db;charset=utf8mb4",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $liveComposition=file_get_contents(__DIR__.'/../http/IdentityHttpComposition.php');
 qa02b(is_string($liveComposition)&&!str_contains($liveComposition,'PdoProfileOwnershipApprovalAdapter')
    &&!str_contains($liveComposition,'PdoProfileOwnershipStepUpAdapter')
    &&!str_contains($liveComposition,'ProfileOwnershipStepUpIssuanceService'),'live_runtime_and_issuer_remain_disabled');
 $p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_07_19_01_create_auth_accounts.sql'));
 $p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_07_20_04_create_auth_account_credentials.sql'));
 $p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_07_20_06_create_auth_rate_limit_buckets.sql'));
 $p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_09_08_07_create_internal_operator_grants.sql'));
 $p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_09_10_01_internal_governance.sql'));
 $p->exec(file_get_contents(__DIR__.'/../../profiles/db/profiles_doctors_schema.sql'));
 $p->exec('CREATE TABLE medical_groups(group_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB');
 $p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_07_19_03_create_auth_account_memberships.sql'));
 $migration=file_get_contents(__DIR__.'/../db/migrations/2026_10_14_01_create_profile_ownership_governance.sql');
 $p->exec($migration);$p->exec($migration);
 $r3Migration=file_get_contents(__DIR__.'/../db/migrations/2026_10_14_02_profile_ownership_r3_endorsements.sql');
 $p->exec($r3Migration);$p->exec($r3Migration);
 qa02b((int)$p->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'auth_profile_ownership_%'")->fetchColumn()===3,'forward_and_repeat_migration');
 qa02b((int)$p->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE() AND table_name LIKE 'auth_profile_ownership_%'")->fetchColumn()===14,'foreign_keys_present');
 $p->exec("CREATE TABLE platform_audit_stream_heads(stream_key VARCHAR(191) PRIMARY KEY,last_sequence_number BIGINT NOT NULL,last_event_hash CHAR(64) NOT NULL,hash_version VARCHAR(32),updated_at DATETIME(6)) ENGINE=InnoDB");
 $p->exec("CREATE TABLE platform_audit_events(stream_key VARCHAR(191),sequence_number BIGINT,event_id CHAR(64),schema_version VARCHAR(32),occurred_at_utc DATETIME(6),action VARCHAR(128),risk_level VARCHAR(2),outcome VARCHAR(16),reason_code VARCHAR(128),real_actor_reference VARCHAR(191),effective_actor_reference VARCHAR(191),affected_subject_reference VARCHAR(191),correlation_id VARCHAR(191),request_id VARCHAR(191),case_reference VARCHAR(191),resource_type VARCHAR(128),resource_reference VARCHAR(191),metadata_json JSON,previous_hash CHAR(64),event_hash CHAR(64),created_at_utc DATETIME(6),PRIMARY KEY(stream_key,sequence_number),UNIQUE(event_id),UNIQUE(event_hash)) ENGINE=InnoDB");
 $p->exec("CREATE TRIGGER qa02b_audit_no_update BEFORE UPDATE ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit_update_forbidden'");
 $p->exec("CREATE TRIGGER qa02b_audit_no_delete BEFORE DELETE ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit_delete_forbidden'");

 qa02bReset($p);$request=qa02bRequest($p);qa02bApproval($p,$request);
 $clock=new Qa02bClock();$stepRepo=new ProfileOwnershipStepUpRepository($p);
 $auth=new CredentialAuthenticationService(new IdentityAccountRepository($p),new AccountCredentialRepository($p),
    new RateLimitService($p,new RateLimitKeyHasher('synthetic-pepper'),$clock),$clock);
 $issuer=new ProfileOwnershipStepUpIssuanceService($p,$auth,$stepRepo,$clock);
 $issuance=$issuer->issue(qa02bActor(),$request,'qa_target@example.invalid','Synthetic-Password-123!',['ip'=>'qa02b-ip']);
 qa02b((int)$p->query("SELECT COUNT(*) FROM auth_profile_ownership_step_up_proofs WHERE proof_hash=SHA2(".$p->quote($issuance->proof).",256) AND status='VERIFIED'")->fetchColumn()===1,'credential_backed_hashed_step_up_issued');
 $request=qa02bRequest($p,$request->operationId,$request->approvalReference,$issuance->proof);
 $tx=new Qa02bAuditTransaction($p);$service=new ProfileMembershipRoleChangeService($p,new AccountMembershipRepository($p),
    new PdoProfileOwnershipApprovalAdapter($p,new PdoProfileOwnershipApproverAuthorityAdapter($p),$clock),new PdoProfileOwnershipStepUpAdapter($p,$stepRepo,$clock),
    new CanonicalProfileMembershipRoleChangeAuditAdapter($p,qa02bWriter($p,$tx)),$clock);
 $result=$service->assignInitialOwner(qa02bActor(),$request);
 qa02b($result['role_code']==='owner'&&(int)$p->query('SELECT COUNT(*) FROM auth_account_memberships')->fetchColumn()===1,'same_membership_promoted_once');
 qa02b((int)$p->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn()===3,'three_canonical_audit_events_atomic');
 $metadata=(string)$p->query("SELECT metadata_json FROM platform_audit_events WHERE action='PROFILE_OWNERSHIP_ASSIGNED'")->fetchColumn();
 qa02b(str_contains($metadata,'case-qa02b-0001')&&str_contains($metadata,'qa_approver_2')&&str_contains($metadata,$issuance->auditReference)&&!str_contains($metadata,$issuance->proof),'audit_evidence_without_step_up_secret');
 qa02bDenied(fn()=>$service->assignInitialOwner(qa02bActor(),$request),'approval_and_step_up_replay_denied');

 qa02bReset($p);$expired=qa02bRequest($p);qa02bApproval($p,$expired,'2026-10-14 17:59:59');
 qa02bDenied(fn()=>$issuer->issue(qa02bActor(),$expired,'qa_target@example.invalid','Synthetic-Password-123!',['ip'=>'qa02b-expired']),'expired_approval_denied');
 qa02bReset($p);$self=qa02bRequest($p);
 qa02bDenied(fn()=>qa02bApproval($p,$self,'2026-10-14 18:30:00','qa_target'),'self_approval_constraint_denied');

 qa02bReset($p);$single=qa02bRequest($p);qa02bApproval($p,$single);$p->exec("DELETE FROM auth_profile_ownership_approval_endorsements WHERE endorsement_slot=2");
 qa02bDenied(fn()=>$issuer->issue(qa02bActor(),$single,'qa_target@example.invalid','Synthetic-Password-123!',['ip'=>'qa02b-single']),'missing_second_approver_denied');

 qa02bReset($p);$staleStaff=qa02bRequest($p);qa02bApproval($p,$staleStaff);$issuance=$issuer->issue(qa02bActor(),$staleStaff,'qa_target@example.invalid','Synthetic-Password-123!',['ip'=>'qa02b-stale-staff']);
 $staleStaff=qa02bRequest($p,$staleStaff->operationId,$staleStaff->approvalReference,$issuance->proof);
 $p->exec("UPDATE auth_account_credentials SET credential_version=2 WHERE account_id='qa_approver_2'");
 qa02bDenied(fn()=>$service->assignInitialOwner(qa02bActor(),$staleStaff),'stale_approver_credential_denied_at_consumption');

 qa02bReset($p);$revoked=qa02bRequest($p);qa02bApproval($p,$revoked);$issuance=$issuer->issue(qa02bActor(),$revoked,'qa_target@example.invalid','Synthetic-Password-123!',['ip'=>'qa02b-revoked']);
 $revoked=qa02bRequest($p,$revoked->operationId,$revoked->approvalReference,$issuance->proof);
 $p->exec("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='qa_approver_2' AND capability='profile_initial_ownership_approve'");
 qa02bDenied(fn()=>$service->assignInitialOwner(qa02bActor(),$revoked),'revoked_secondary_approver_denied_at_consumption');
 qa02b((string)$p->query("SELECT role_code FROM auth_account_memberships WHERE membership_id='qa_membership'")->fetchColumn()==='collaborator'
    &&(string)$p->query("SELECT status FROM auth_profile_ownership_approvals")->fetchColumn()==='APPROVED','revoked_approver_no_evidence_consumption');

 qa02bReset($p);$request=qa02bRequest($p);qa02bApproval($p,$request);$issuance=$issuer->issue(qa02bActor(),$request,'qa_target@example.invalid','Synthetic-Password-123!',['ip'=>'qa02b-rollback']);$request=qa02bRequest($p,$request->operationId,$request->approvalReference,$issuance->proof);
 $failingTx=new Qa02bAuditTransaction($p);$failingTx->failInsert=true;$failing=new ProfileMembershipRoleChangeService($p,new AccountMembershipRepository($p),new PdoProfileOwnershipApprovalAdapter($p,new PdoProfileOwnershipApproverAuthorityAdapter($p),$clock),new PdoProfileOwnershipStepUpAdapter($p,$stepRepo,$clock),new CanonicalProfileMembershipRoleChangeAuditAdapter($p,qa02bWriter($p,$failingTx)),$clock);
 qa02bDenied(fn()=>$failing->assignInitialOwner(qa02bActor(),$request),'audit_failure_denied');
 qa02b((string)$p->query("SELECT role_code FROM auth_account_memberships WHERE membership_id='qa_membership'")->fetchColumn()==='collaborator'&&(string)$p->query("SELECT status FROM auth_profile_ownership_approvals")->fetchColumn()==='APPROVED','audit_failure_rolls_back_role_and_evidence');

 $p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_10_14_02_rollback_profile_ownership_r3_endorsements.sql'));
 $p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_10_14_01_rollback_profile_ownership_governance.sql'));
 qa02b((int)$p->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'auth_profile_ownership_%'")->fetchColumn()===0,'rollback_removes_only_qa02b_tables');
 qa02b((int)$p->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('auth_accounts','auth_account_memberships','auth_account_credentials')")->fetchColumn()===3,'rollback_preserves_identity_baseline');
 echo "IDENTITY_QA02B_DISPOSABLE_PERSISTENCE_TEST=PASS\n";
}finally{if(!str_starts_with($db,'mxmed_identity_qa02b_'))throw new RuntimeException('unsafe_cleanup_prefix');$root->exec("DROP DATABASE IF EXISTS `$db`");}
