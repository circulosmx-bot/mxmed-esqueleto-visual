<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();

use Identity\Adapters\{PdoProfileOwnershipApproverAuthorityAdapter,RejectingFirstDirectorBootstrapAuthorizationAdapter};
use Identity\Contracts\{Clock,FirstDirectorBootstrapAuditPort,FirstDirectorBootstrapAuthorization,
    FirstDirectorBootstrapAuthorizationPort};
use Identity\Services\{FirstDirectorBootstrapService,InternalCapabilityCatalog,ProfileGovernancePolicy};
function qa02e(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo "PASS $label\n";}
function qa02eDenied(Closure $call,string $label):void{try{$call();throw new LogicException('unexpected_success '.$label);}catch(RuntimeException|InvalidArgumentException){echo "PASS $label\n";}}
final class Qa02eClock implements Clock{public function now():DateTimeImmutable{return new DateTimeImmutable('2026-10-14T20:00:00+00:00');}}
final class Qa02eTrustAnchor implements FirstDirectorBootstrapAuthorizationPort
{
    public bool $consumed=false;
    public function __construct(private FirstDirectorBootstrapAuthorization $evidence){}
    public function consume(PDO $transaction,string $operationId,string $targetAccountId):FirstDirectorBootstrapAuthorization
    {
        if(!$transaction->inTransaction()||$this->consumed)throw new RuntimeException('synthetic_trust_anchor_denied');
        if($operationId!==$this->evidence->operationId||$targetAccountId!==$this->evidence->targetAccountId)
            throw new RuntimeException('synthetic_trust_anchor_mismatch');
        $this->consumed=true;return $this->evidence;
    }
}
final class Qa02eAudit implements FirstDirectorBootstrapAuditPort
{
    public bool $fail=false;
    public function append(PDO $transaction,FirstDirectorBootstrapAuthorization $authorization):void
    {
        if($this->fail)throw new RuntimeException('synthetic_audit_failure');
        $s=$transaction->prepare('INSERT INTO qa02e_audit(operation_id,target_account_id,case_reference) VALUES(?,?,?)');
        $s->execute([$authorization->operationId,$authorization->targetAccountId,$authorization->caseReference]);
    }
}
function qa02eEvidence(string $target='staff_candidate',?DateTimeImmutable $expires=null):FirstDirectorBootstrapAuthorization
{
    return new FirstDirectorBootstrapAuthorization('123e4567-e89b-42d3-a456-426614174111',$target,
        'case-governance-0001','deployment-ratification-0001','ratifier-session-0001',['ratifier_alpha','ratifier_bravo'],
        ['ratifier_alpha'=>'approval-alpha-0001','ratifier_bravo'=>'approval-bravo-0001'],true,true,new DateTimeImmutable('2026-10-14T19:55:00+00:00'),
        $expires??new DateTimeImmutable('2026-10-14T20:05:00+00:00'));
}

$root=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db='mxmed_identity_qa02e_'.bin2hex(random_bytes(5));$root->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try{
    $p=new PDO("mysql:host=127.0.0.1;port=3306;dbname=$db;charset=utf8mb4",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $composition=(string)file_get_contents(__DIR__.'/../http/IdentityHttpComposition.php');
    qa02e(!str_contains($composition,'FirstDirectorBootstrapService')&&!str_contains($composition,'ProfileGovernancePolicy')
        &&!str_contains($composition,'PdoProfileOwnershipApproverAuthorityAdapter'),'governance_runtime_and_routes_disabled');
    foreach(['2026_07_19_01_create_auth_accounts.sql','2026_07_20_04_create_auth_account_credentials.sql','2026_09_08_07_create_internal_operator_grants.sql','2026_09_10_01_internal_governance.sql'] as $file)
        $p->exec(file_get_contents(__DIR__.'/../db/migrations/'.$file));
    $p->exec(file_get_contents(__DIR__.'/../../profiles/db/profiles_doctors_schema.sql'));
    $p->exec('CREATE TABLE medical_groups(group_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB');
    $p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_07_19_03_create_auth_account_memberships.sql'));
    $p->exec('CREATE TABLE qa02e_audit(operation_id CHAR(36) PRIMARY KEY,target_account_id VARCHAR(64),case_reference VARCHAR(128)) ENGINE=InnoDB');
    $password=password_hash('Synthetic-QA02E-only!',PASSWORD_DEFAULT);
    foreach(['staff_candidate','staff_candidate_2','physician_owner'] as $id){$s=$p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active','2026-10-14 19:00:00')");$s->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);$s=$p->prepare("INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,'2026-10-14 19:00:00',1)");$s->execute([$id,$password]);}
    $p->exec("INSERT INTO profiles_doctors(doctor_id,display_name) VALUES('profile_exact','Synthetic Profile')");
    $p->exec("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status) VALUES('owner_membership','physician_owner','profile_exact','owner','profile','active')");

    $clock=new Qa02eClock();$audit=new Qa02eAudit();
    $rejecting=new FirstDirectorBootstrapService($p,new RejectingFirstDirectorBootstrapAuthorizationAdapter(),$audit,$clock);
    qa02eDenied(fn()=>$rejecting->execute('123e4567-e89b-42d3-a456-426614174111','staff_candidate'),'missing_trust_anchor_denied');
    qa02e((int)$p->query('SELECT COUNT(*) FROM internal_staff')->fetchColumn()===0,'missing_trust_anchor_no_mutation');
    qa02eDenied(fn()=>new FirstDirectorBootstrapAuthorization('123e4567-e89b-42d3-a456-426614174111','staff_candidate','case-governance-0001','deployment-ratification-0001','ratifier-session-0001',['staff_candidate','ratifier_bravo'],['staff_candidate'=>'approval-alpha-0001','ratifier_bravo'=>'approval-bravo-0001'],true,true,new DateTimeImmutable('2026-10-14T19:55:00+00:00'),new DateTimeImmutable('2026-10-14T20:05:00+00:00')),'bootstrap_self_approval_denied');
    qa02eDenied(fn()=>new FirstDirectorBootstrapAuthorization('123e4567-e89b-42d3-a456-426614174111','staff_candidate','case-governance-0001','deployment-ratification-0001','ratifier-session-0001',['ratifier_alpha','ratifier_alpha'],['ratifier_alpha'=>'approval-alpha-0001','ratifier_bravo'=>'approval-bravo-0001'],true,true,new DateTimeImmutable('2026-10-14T19:55:00+00:00'),new DateTimeImmutable('2026-10-14T20:05:00+00:00')),'bootstrap_dual_approval_separation');
    qa02eDenied(fn()=>new FirstDirectorBootstrapAuthorization('123e4567-e89b-42d3-a456-426614174111','staff_candidate','case-governance-0001','deployment-ratification-0001','ratifier-session-0001',['ratifier_alpha','ratifier_bravo'],['ratifier_alpha'=>'approval-alpha-0001','ratifier_bravo'=>'approval-bravo-0001'],true,false,new DateTimeImmutable('2026-10-14T19:55:00+00:00'),new DateTimeImmutable('2026-10-14T20:05:00+00:00')),'bootstrap_mfa_required');

    $trust=new Qa02eTrustAnchor(qa02eEvidence());$service=new FirstDirectorBootstrapService($p,$trust,$audit,$clock);
    $created=$service->execute('123e4567-e89b-42d3-a456-426614174111','staff_candidate');
    qa02e($created['governance_class']==='DIRECTOR'&&(int)$p->query('SELECT COUNT(*) FROM internal_staff')->fetchColumn()===1,'first_director_created_once_disposable');
    qa02e((int)$p->query('SELECT COUNT(*) FROM qa02e_audit')->fetchColumn()===1,'bootstrap_audit_atomic');
    qa02eDenied(fn()=>$service->execute('123e4567-e89b-42d3-a456-426614174111','staff_candidate'),'repeated_bootstrap_denied');
    $other=new FirstDirectorBootstrapService($p,new Qa02eTrustAnchor(qa02eEvidence('staff_candidate_2')),$audit,$clock);
    qa02eDenied(fn()=>$other->execute('123e4567-e89b-42d3-a456-426614174111','staff_candidate_2'),'second_director_concurrency_guard');
    qa02e((int)$p->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='internal_staff' AND index_name='uniq_internal_director'")->fetchColumn()===1,'database_unique_director_guard');

    $p->exec('DELETE FROM qa02e_audit');$p->exec('DELETE FROM internal_staff');$audit->fail=true;
    $failed=new FirstDirectorBootstrapService($p,new Qa02eTrustAnchor(qa02eEvidence()),$audit,$clock);
    qa02eDenied(fn()=>$failed->execute('123e4567-e89b-42d3-a456-426614174111','staff_candidate'),'bootstrap_audit_failure_denied');
    qa02e((int)$p->query('SELECT COUNT(*) FROM internal_staff')->fetchColumn()===0,'bootstrap_audit_failure_rolls_back');

    $ownership=ProfileGovernancePolicy::initialOwnershipApproval('profile_exact');
    qa02e($ownership->riskLevel()==='R3'&&$ownership->reauthenticationRequired()&&$ownership->mfaRequired()
        &&$ownership->caseRequired()&&$ownership->approvalRequired()&&!$ownership->dualApprovalRequired()&&$ownership->entityProfileRequired()
        &&$ownership->capabilitiesRequired()->contains(InternalCapabilityCatalog::APPROVE_INITIAL_PROFILE_OWNERSHIP),'ownership_routine_single_staff_policy');
    $rootPolicy=ProfileGovernancePolicy::firstDirectorBootstrap('staff_candidate');
    qa02e($rootPolicy->dualApprovalRequired()&&$rootPolicy->mfaRequired()&&$rootPolicy->reauthenticationRequired()
        &&$rootPolicy->caseRequired()&&$rootPolicy->auditTrailRequired(),'root_r3_controls_not_simplified');
    $assisted=ProfileGovernancePolicy::assistedProfileAdministration('curate','profile_exact');
    qa02e($assisted->riskLevel()==='R2'&&!$assisted->ownershipRequired()&&!$assisted->membershipRequired()
        &&$assisted->entityProfileRequired()&&$assisted->capabilitiesRequired()->contains(InternalCapabilityCatalog::CURATE_PHYSICIAN_PROFILE),'ownerless_assisted_profile_policy');
    qa02e(!in_array('clinical_record',$assisted->capabilitiesRequired()->values(),true),'assisted_profile_no_clinical_capability');
    qa02e(!InternalCapabilityCatalog::all()[InternalCapabilityCatalog::APPROVE_INITIAL_PROFILE_OWNERSHIP]['delegable'],'ownership_approval_capability_non_delegable');

    $p->beginTransaction();$authority=new PdoProfileOwnershipApproverAuthorityAdapter($p);
    qa02eDenied(fn()=>$authority->assertAuthorized($p,'physician_owner','staff_candidate','profile_exact',1),'profile_owner_without_staff_authority_denied');
    $p->rollBack();
    $p->exec("INSERT INTO internal_staff(account_id,governance_class,status) VALUES('staff_candidate','ADVISOR','ACTIVE')");
    $p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'staff_candidate','profile_initial_ownership_approve','ACTIVE')");
    $p->beginTransaction();
    qa02eDenied(fn()=>$authority->assertAuthorized($p,'staff_candidate','physician_owner','profile_exact',1),'insufficient_staff_class_denied');
    $p->rollBack();
    qa02e((int)$p->query("SELECT COUNT(*) FROM auth_account_memberships WHERE account_id='physician_owner' AND role_code='owner'")->fetchColumn()===1,'profile_owner_membership_unchanged');
    echo "IDENTITY_QA02E_DISPOSABLE_PREPARATION_TEST=PASS\n";
}finally{if(!str_starts_with($db,'mxmed_identity_qa02e_'))throw new RuntimeException('unsafe_cleanup_prefix');$root->exec("DROP DATABASE IF EXISTS `$db`");}
