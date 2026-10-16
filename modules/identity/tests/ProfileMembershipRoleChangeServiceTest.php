<?php
declare(strict_types=1);

// Disposable database and synthetic authorities only. Approval/step-up ports below are contract-test fakes.
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/support/SyntheticOwnershipEligibility.php';

use Identity\Adapters\{RejectingProfileMembershipRoleChangeAuditAdapter,RejectingProfileOwnershipApprovalAdapter,RejectingProfileOwnershipStepUpAdapter};
use Identity\Contracts\{AuthenticatedAccessContext,Clock,ProfileMembershipRoleChangeAuditPort,ProfileMembershipRoleChangeAuditRecord,
    ProfileMembershipRoleChangeRequest,ProfileOwnershipApprovalEvidence,ProfileOwnershipApprovalPort,
    ProfileOwnershipStepUpEvidence,ProfileOwnershipStepUpPort,SessionId,SessionPrincipal,SessionRecord,SessionTokenDigest};
use Identity\Repositories\AccountMembershipRepository;
use Identity\Services\ProfileMembershipRoleChangeService;

function ownCheck(bool $condition,string $label):void{if(!$condition)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
final class OwnershipFixedClock implements Clock { public function now():DateTimeImmutable{return new DateTimeImmutable('2026-10-14T18:00:00+00:00');} }

final class DisposableApprovalPort implements ProfileOwnershipApprovalPort
{
    public function __construct(private PDO $pdo){}
    public function consume(PDO $transaction,ProfileMembershipRoleChangeRequest $request,string $executorAccountId):ProfileOwnershipApprovalEvidence
    {
        if($transaction!==$this->pdo||!$transaction->inTransaction())throw new RuntimeException('approval_transaction_required');
        $s=$this->pdo->prepare('SELECT * FROM qa_ownership_approvals WHERE approval_reference=? FOR UPDATE');$s->execute([$request->approvalReference]);$r=$s->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['status']!=='APPROVED'||$r['consumed_operation_id']!==null)throw new RuntimeException('approval_denied');
        if($r['approver_account_id']===$r['target_account_id'])throw new RuntimeException('approval_self_denied');
        $u=$this->pdo->prepare("UPDATE qa_ownership_approvals SET consumed_operation_id=?,status='CONSUMED' WHERE approval_reference=? AND status='APPROVED' AND consumed_operation_id IS NULL");
        $u->execute([$request->operationId,$request->approvalReference]);if($u->rowCount()!==1)throw new RuntimeException('approval_replay');
        return new ProfileOwnershipApprovalEvidence($r['approval_reference'],$r['case_reference'],$r['decision_reference'],$r['approver_account_id'],
            [$r['approver_account_id'],'qa_approver_2'],$r['executor_account_id'],$r['operation_id'],$r['membership_id'],$r['target_account_id'],$r['profile_doctor_id'],$r['expected_role'],'profile',$r['new_role'],
            new DateTimeImmutable($r['approved_at'],new DateTimeZone('UTC')),new DateTimeImmutable($r['expires_at'],new DateTimeZone('UTC')));
    }
}

final class DisposableStepUpPort implements ProfileOwnershipStepUpPort
{
    public function __construct(private PDO $pdo){}
    public function consume(PDO $transaction,ProfileMembershipRoleChangeRequest $request,AuthenticatedAccessContext $actor):ProfileOwnershipStepUpEvidence
    {
        if($transaction!==$this->pdo||!$transaction->inTransaction())throw new RuntimeException('step_up_transaction_required');
        $s=$this->pdo->prepare('SELECT * FROM qa_ownership_step_up WHERE step_up_reference=? FOR UPDATE');$s->execute([$request->stepUpReference]);$r=$s->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['status']!=='VERIFIED'||$r['consumed_operation_id']!==null)throw new RuntimeException('step_up_denied');
        $u=$this->pdo->prepare("UPDATE qa_ownership_step_up SET consumed_operation_id=?,status='CONSUMED' WHERE step_up_reference=? AND status='VERIFIED' AND consumed_operation_id IS NULL");
        $u->execute([$request->operationId,$request->stepUpReference]);if($u->rowCount()!==1)throw new RuntimeException('step_up_replay');
        return new ProfileOwnershipStepUpEvidence($r['step_up_reference'],'stepup-audit-0001',$request->approvalReference,$r['operation_id'],$r['account_id'],$r['session_id'],(int)$r['credential_version'],
            $r['membership_id'],$r['profile_doctor_id'],$request->expectedRole,$request->expectedScope,$r['new_role'],new DateTimeImmutable($r['verified_at'],new DateTimeZone('UTC')),
            new DateTimeImmutable($r['expires_at'],new DateTimeZone('UTC')));
    }
}

final class DisposableRoleAuditPort implements ProfileMembershipRoleChangeAuditPort
{
    public bool $fail=false;
    public function __construct(private PDO $pdo){}
    public function append(PDO $transaction,ProfileMembershipRoleChangeAuditRecord $record):void
    {
        if($transaction!==$this->pdo||!$transaction->inTransaction())throw new RuntimeException('audit_transaction_required');
        if($this->fail)throw new RuntimeException('synthetic_audit_failure');
        $payload=json_encode(get_object_vars($record),JSON_THROW_ON_ERROR);
        $s=$this->pdo->prepare('INSERT INTO qa_role_audit(operation_id,event_type,target_reference,evidence_json) VALUES(?,?,?,?)');
        foreach([['ROLE_REVOKED',$record->membershipId],['ROLE_ASSIGNED',$record->membershipId],['PROFILE_OWNERSHIP_ASSIGNED',$record->profileDoctorId]] as [$type,$target])$s->execute([$record->operationId,$type,$target,$payload]);
    }
}

function ownershipActor(string $account='qa_target',int $version=1,string $status='active'):AuthenticatedAccessContext
{
    $principal=new SessionPrincipal($account,$version,$status,'2026-10-14 17:50:00');
    $record=new SessionRecord(new SessionId(str_pad('session-'.$account,32,'x')),new SessionTokenDigest(str_repeat('a',64)),$principal,
        new DateTimeImmutable('2026-10-14T10:00:00+00:00'),new DateTimeImmutable('2026-10-14T17:50:00+00:00'),
        new DateTimeImmutable('2026-10-14T18:20:00+00:00'),new DateTimeImmutable('2026-10-14T22:00:00+00:00'));
    return new AuthenticatedAccessContext($principal,$record);
}

function ownershipReset(PDO $p):void
{
    foreach(['qa_role_audit','qa_ownership_step_up','qa_ownership_approvals','auth_account_memberships','auth_account_credentials','auth_accounts','profiles_doctors'] as $table)$p->exec('DELETE FROM '.$table);
    $p->exec("INSERT INTO profiles_doctors(doctor_id,display_name) VALUES('qa_profile','Synthetic Profile'),('qa_other_profile','Synthetic Other')");
    foreach(['qa_target','qa_approver','qa_contender'] as $id){$s=$p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active','2026-10-14 10:00:00')");$s->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);$p->prepare("INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,'synthetic-not-a-real-hash','2026-10-14 10:00:00',1)")->execute([$id]);}
    $p->exec("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status,assignment_source,updated_at)
        VALUES('qa_membership','qa_target','qa_profile','collaborator','profile','active','synthetic_contract_test','2026-10-14 17:00:00')");
}

function ownershipRequest(PDO $p,array $replace=[]):ProfileMembershipRoleChangeRequest
{
    $updated=(string)$p->query("SELECT updated_at FROM auth_account_memberships WHERE membership_id='qa_membership'")->fetchColumn();
    $v=array_replace(['operation_id'=>'123e4567-e89b-42d3-a456-426614174000','membership_id'=>'qa_membership','target_account_id'=>'qa_target',
        'profile_doctor_id'=>'qa_profile','expected_role'=>'collaborator','expected_scope'=>'profile','expected_status'=>'active','expected_updated_at'=>$updated,
        'new_role'=>'owner','approval_reference'=>'approval-qa-001','step_up_reference'=>'stepup-qa-0001'],$replace);
    return new ProfileMembershipRoleChangeRequest($v['operation_id'],$v['membership_id'],$v['target_account_id'],$v['profile_doctor_id'],$v['expected_role'],
        $v['expected_scope'],$v['expected_status'],$v['expected_updated_at'],$v['new_role'],$v['approval_reference'],$v['step_up_reference']);
}

function ownershipEvidence(PDO $p,ProfileMembershipRoleChangeRequest $r,?string $stepExpiry=null,?string $stepVerified=null,string $approver='qa_approver'):void
{
    $p->prepare("INSERT INTO qa_ownership_approvals VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,NULL,'APPROVED')")->execute([
        $r->approvalReference,'case-qa-0001','decision-qa-0001',$approver,'qa_target',$r->operationId,$r->membershipId,$r->targetAccountId,
        $r->profileDoctorId,$r->expectedRole,$r->newRole,'2026-10-14 17:58:00','2026-10-14 18:05:00']);
    $p->prepare("INSERT INTO qa_ownership_step_up VALUES(?,?,?,?,?,?,?,?,?,?,NULL,NULL,'VERIFIED')")->execute([
        $r->stepUpReference,$r->operationId,$r->targetAccountId,(string)ownershipActor()->session()->sessionId(),1,$r->membershipId,$r->profileDoctorId,$r->newRole,
        $stepVerified??'2026-10-14 17:59:00',$stepExpiry??'2026-10-14 18:03:00']);
}

function ownershipService(PDO $p,?ProfileOwnershipApprovalPort $approval=null,?ProfileOwnershipStepUpPort $step=null,?ProfileMembershipRoleChangeAuditPort $audit=null):array
{
    $audit??=new DisposableRoleAuditPort($p);
    return [new ProfileMembershipRoleChangeService($p,new AccountMembershipRepository($p),$approval??new DisposableApprovalPort($p),$step??new DisposableStepUpPort($p),$audit,new OwnershipFixedClock(),eligibility:new SyntheticOwnershipEligibility()),$audit];
}

function ownershipDenied(PDO $p,Closure $operation,string $label):void
{
    $before=$p->query('SELECT membership_id,account_id,profile_doctor_id,role_code,scope_code,status FROM auth_account_memberships ORDER BY membership_id')->fetchAll(PDO::FETCH_ASSOC);
    try{$operation();throw new LogicException('unexpected_success '.$label);}catch(RuntimeException){}
    ownCheck($before===$p->query('SELECT membership_id,account_id,profile_doctor_id,role_code,scope_code,status FROM auth_account_memberships ORDER BY membership_id')->fetchAll(PDO::FETCH_ASSOC),$label);
}

$root=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db='mxmed_identity_qa02a_'.bin2hex(random_bytes(5));$root->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try{
    $p=new PDO("mysql:host=127.0.0.1;port=3306;dbname=$db;charset=utf8mb4",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_07_19_01_create_auth_accounts.sql'));
    $p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_07_20_04_create_auth_account_credentials.sql'));
    $p->exec(file_get_contents(__DIR__.'/../../profiles/db/profiles_doctors_schema.sql'));
    $p->exec("CREATE TABLE medical_groups(group_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB");
    $p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_07_19_03_create_auth_account_memberships.sql'));
    $p->exec("CREATE TABLE qa_ownership_approvals(approval_reference VARCHAR(128) PRIMARY KEY,case_reference VARCHAR(128),decision_reference VARCHAR(128),approver_account_id VARCHAR(64),executor_account_id VARCHAR(64),operation_id CHAR(36),membership_id VARCHAR(64),target_account_id VARCHAR(64),profile_doctor_id VARCHAR(64),expected_role VARCHAR(32),new_role VARCHAR(32),approved_at DATETIME,expires_at DATETIME,unused VARCHAR(1) NULL,consumed_operation_id CHAR(36) NULL,status VARCHAR(16)) ENGINE=InnoDB");
    $p->exec("CREATE TABLE qa_ownership_step_up(step_up_reference VARCHAR(128) PRIMARY KEY,operation_id CHAR(36),account_id VARCHAR(64),session_id VARCHAR(128),credential_version INT,membership_id VARCHAR(64),profile_doctor_id VARCHAR(64),new_role VARCHAR(32),verified_at DATETIME,expires_at DATETIME,unused VARCHAR(1) NULL,consumed_operation_id CHAR(36) NULL,status VARCHAR(16)) ENGINE=InnoDB");
    $p->exec("CREATE TABLE qa_role_audit(id BIGINT AUTO_INCREMENT PRIMARY KEY,operation_id CHAR(36),event_type VARCHAR(64),target_reference VARCHAR(64),evidence_json JSON,UNIQUE KEY uq_event(operation_id,event_type)) ENGINE=InnoDB");

    ownershipReset($p);$r=ownershipRequest($p);ownershipEvidence($p,$r);[$s]=ownershipService($p);$out=$s->assignInitialOwner(ownershipActor(),$r);
    ownCheck($out['role_code']==='owner','B_authorized_transition');
    ownCheck((string)$p->query("SELECT membership_id FROM auth_account_memberships")->fetchColumn()==='qa_membership','C_same_membership_identity');
    ownCheck((int)$p->query('SELECT COUNT(*) FROM auth_account_memberships')->fetchColumn()===1,'D_no_second_membership');
    ownCheck((int)$p->query('SELECT COUNT(*) FROM qa_role_audit')->fetchColumn()===3,'audit_three_events');
    ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'Q_replay_denied_no_mutation');
    ownCheck((int)$p->query('SELECT COUNT(*) FROM qa_role_audit')->fetchColumn()===3,'Q_replay_no_duplicate_audit');

    ownershipReset($p);$r=ownershipRequest($p);[$s]=ownershipService($p,new RejectingProfileOwnershipApprovalAdapter());ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'E_missing_approval_denied');
    ownershipReset($p);$r=ownershipRequest($p,['approval_reference'=>'forged-approval']);ownershipEvidence($p,ownershipRequest($p));[$s]=ownershipService($p);ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'F_forged_approval_denied');
    ownershipReset($p);$r=ownershipRequest($p);ownershipEvidence($p,$r);[$s]=ownershipService($p,null,new RejectingProfileOwnershipStepUpAdapter());ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'G_missing_step_up_denied');
    ownershipReset($p);$r=ownershipRequest($p);ownershipEvidence($p,$r,'2026-10-14 17:59:30');[$s]=ownershipService($p);ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'H_stale_step_up_denied');
    ownershipReset($p);$r=ownershipRequest($p);ownershipEvidence($p,$r,'2026-10-14 18:03:00','2026-10-14 17:40:00');[$s]=ownershipService($p);ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'H_old_step_up_denied');
    ownershipReset($p);$r=ownershipRequest($p);ownershipEvidence($p,$r,null,null,'qa_target');[$s]=ownershipService($p);ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'F_self_approval_denied');
    ownershipReset($p);$r=ownershipRequest($p,['profile_doctor_id'=>'qa_other_profile']);ownershipEvidence($p,$r);[$s]=ownershipService($p);ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'I_cross_profile_denied');
    ownershipReset($p);$p->exec("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status) VALUES('qa_ambiguous','qa_target','qa_other_profile','collaborator','profile','active')");$r=ownershipRequest($p);ownershipEvidence($p,$r);[$s]=ownershipService($p);ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'J_ambiguous_membership_denied');
    ownershipReset($p);$p->exec("UPDATE auth_accounts SET status='disabled' WHERE account_id='qa_target'");$r=ownershipRequest($p);ownershipEvidence($p,$r);[$s]=ownershipService($p);ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'K_disabled_account_denied');
    ownershipReset($p);$p->exec("UPDATE auth_account_memberships SET status='revoked' WHERE membership_id='qa_membership'");$r=ownershipRequest($p);ownershipEvidence($p,$r);[$s]=ownershipService($p);ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'K_revoked_membership_denied');
    ownershipReset($p);$r=ownershipRequest($p,['expected_role'=>'administrator']);ownershipEvidence($p,$r);[$s]=ownershipService($p);ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'L_expected_role_mismatch_denied');
    ownershipReset($p);$p->exec("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status) VALUES('qa_existing_owner','qa_approver','qa_profile','owner','profile','active')");$r=ownershipRequest($p);ownershipEvidence($p,$r);[$s]=ownershipService($p);ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'N_duplicate_owner_denied');

    ownershipReset($p);$r=ownershipRequest($p);ownershipEvidence($p,$r);$audit=new DisposableRoleAuditPort($p);$audit->fail=true;[$s]=ownershipService($p,null,null,$audit);ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'O_audit_failure_rolls_back_role');
    ownCheck((int)$p->query('SELECT COUNT(*) FROM qa_role_audit')->fetchColumn()===0&&(int)$p->query("SELECT COUNT(*) FROM qa_ownership_approvals WHERE status='APPROVED'")->fetchColumn()===1,'O_evidence_consumption_rolled_back');
    ownershipReset($p);$r=ownershipRequest($p);ownershipEvidence($p,$r);[$s]=ownershipService($p,null,null,new RejectingProfileMembershipRoleChangeAuditAdapter());ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'O_runtime_default_audit_rejects');
    ownershipReset($p);$r=ownershipRequest($p);ownershipEvidence($p,$r);$p->exec("CREATE TRIGGER qa_fail_role_update BEFORE UPDATE ON auth_account_memberships FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic role update failure'");[$s]=ownershipService($p);ownershipDenied($p,fn()=>$s->assignInitialOwner(ownershipActor(),$r),'P_role_failure_rolls_back_evidence');$p->exec('DROP TRIGGER qa_fail_role_update');
    ownCheck((int)$p->query('SELECT COUNT(*) FROM qa_role_audit')->fetchColumn()===0,'P_role_failure_no_audit');

    ownershipReset($p);$p->exec("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status,updated_at) VALUES('qa_contender_membership','qa_contender','qa_profile','collaborator','profile','active','2026-10-14 17:00:00')");
    $first=ownershipRequest($p);ownershipEvidence($p,$first);[$s]=ownershipService($p);$s->assignInitialOwner(ownershipActor(),$first);
    $second=new ProfileMembershipRoleChangeRequest('123e4567-e89b-42d3-a456-426614174001','qa_contender_membership','qa_contender','qa_profile','collaborator','profile','active','2026-10-14 17:00:00','owner','approval-qa-002','stepup-qa-0002');
    ownershipDenied($p,fn()=>(ownershipService($p))[0]->assignInitialOwner(ownershipActor('qa_contender'),$second),'M_serialized_contender_denied');
    ownCheck((int)$p->query("SELECT COUNT(*) FROM auth_account_memberships WHERE profile_doctor_id='qa_profile' AND role_code='owner' AND status='active'")->fetchColumn()===1,'M_single_owner_invariant');

    $cap=new Subscriptions\Services\ExistingCapabilityAuthorityService();
    ownCheck(!$cap->resolve('patients',['plan_code'=>'standard','status'=>'active','is_active'=>true])->available(),'U_standard_clinical_denied');
    ownCheck($cap->resolve('patients',['plan_code'=>'optimum','status'=>'active','is_active'=>true])->available(),'U_optimum_clinical_allowed');
    $widget=file_get_contents(__DIR__.'/../../../api/local-review/index.php');
    ownCheck(is_string($widget)&&str_contains($widget,"!== '1'")&&str_contains($widget,"['real','standard','optimum','professional']"),'V_widget_scope_and_scenarios_preserved');
    echo "IDENTITY_QA02A_DISPOSABLE_CONTRACT_TEST=PASS\n";
}finally{$root->exec("DROP DATABASE IF EXISTS `$db`");}
