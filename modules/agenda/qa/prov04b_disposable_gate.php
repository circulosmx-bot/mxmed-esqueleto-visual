<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__.'/../../identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../services/HealthcareOrganizationClaimService.php';

use Agenda\Services\HealthcareOrganizationClaimException;
use Agenda\Services\HealthcareOrganizationClaimService;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\SessionId;
use Identity\Contracts\SessionPrincipal;
use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionTokenDigest;

$db = getenv('PROV04B_QA_DB');
$tmp = getenv('PROV04B_QA_TMP');
if (!$db || !$tmp || !preg_match('/^prov04b_qa_[0-9a-f]{12}$/D',$db)) throw new RuntimeException('disposable_db_required');
function pdo(): PDO {
    return new PDO('mysql:host=localhost;dbname='.getenv('PROV04B_QA_DB').';charset=utf8mb4','root','',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false]);
}
function actor(string $id): AuthenticatedAccessContext {
    $now = new DateTimeImmutable('now');
    $principal = new SessionPrincipal($id,1,'active',$now->format('Y-m-d H:i:s'));
    $session = new SessionRecord(SessionId::generate(),new SessionTokenDigest(hash('sha256',$id.random_bytes(8))),
        $principal,$now,$now,$now->modify('+3600 seconds'),$now->modify('+43200 seconds'));
    return new AuthenticatedAccessContext($principal,$session);
}
function check(bool $ok,string $label): void { if (!$ok) throw new RuntimeException('FAIL '.$label); echo $label."=PASS\n"; }
function denied(callable $call,string $reason,string $label): void {
    try { $call(); } catch (HealthcareOrganizationClaimException $e) {
        check($e->reason===$reason,$label); return;
    }
    throw new RuntimeException('FAIL '.$label.' unexpected_allow');
}
function countRows(PDO $p,string $table): int { return (int)$p->query('SELECT COUNT(*) FROM '.$table)->fetchColumn(); }
function group(PDO $p,string $id,string $name,string $type='LABORATORY',string $status='pending'): void {
    $p->prepare('INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source)
        VALUES(?,?,?,?,?,\'operator_created\')')->execute([$id,$type,mb_strtolower($name),$name,$status]);
}

if (($argv[1] ?? '') === 'approve_worker') {
    [$script,$command,$claim,$reviewer,$index] = $argv;
    while (!is_file($tmp.'/start')) usleep(10000);
    try {
        $result = (new HealthcareOrganizationClaimService(pdo()))->decide(actor($reviewer),$claim,'APPROVED','EVIDENCE_ACCEPTED');
        file_put_contents($tmp.'/result_'.$index,$result['status']);
    } catch (HealthcareOrganizationClaimException $e) {
        file_put_contents($tmp.'/result_'.$index,$e->reason);
    }
    exit(0);
}

$p = pdo();
check(countRows($p,'healthcare_organization_claims')===0 && countRows($p,'healthcare_organization_claim_events')===0,
    'DISPOSABLE_MIGRATION_REHEARSAL');
foreach (['claimant_a','claimant_b','reviewer','other_reviewer','unrelated','owner_nominal'] as $id) {
    $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at)
        VALUES(?,?,?,'active',NOW())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
}
$p->exec("INSERT INTO internal_staff(account_id,governance_class,status) VALUES
    ('reviewer','ADVISOR','ACTIVE'),('other_reviewer','ADVISOR','ACTIVE'),('claimant_a','ADVISOR','ACTIVE')");
foreach (['reviewer','other_reviewer','claimant_a'] as $id) {
    $p->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status)
        VALUES(UUID(),?,'provider_claim_review','ACTIVE')")->execute([$id]);
}
$svc = new HealthcareOrganizationClaimService($p);
group($p,'org_existing','Existing Lab');
$first = $svc->submitExisting(actor('claimant_a'),'org_existing','existing-claim-01','BUSINESS_EMAIL');
check($first['status']==='PENDING' && $first['requested_role']==='owner' && countRows($p,'auth_account_memberships')===0,
    'QA_EXISTING_ORGANIZATION_CLAIM');
check($svc->submitExisting(actor('claimant_a'),'org_existing','existing-claim-01','BUSINESS_EMAIL')['claim_uuid']
    ===$first['claim_uuid'],'QA_SUBMISSION_IDEMPOTENT_REPLAY');
denied(fn()=>$svc->submitExisting(actor('claimant_a'),'org_existing','existing-claim-01','PHONE'),
    'idempotency_conflict','QA_IDEMPOTENCY_CONFLICT');
denied(fn()=>$svc->submitExisting(actor('claimant_a'),'org_existing','existing-claim-02'),
    'claim_pending_exists','QA_DUPLICATE_PENDING_SAME_ACCOUNT');
denied(fn()=>$svc->decide(actor('claimant_a'),$first['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED'),
    'self_review_denied','QA_CLAIMANT_SELF_APPROVAL');
group($p,'org_owner_role','Owner Role Lab');
$p->exec("INSERT INTO auth_account_memberships(membership_id,account_id,entity_group_id,role_code,scope_code,status,assignment_source)
    VALUES(UUID(),'owner_nominal','org_owner_role','owner','organization','active','manual_review')");
denied(fn()=>$svc->decide(actor('owner_nominal'),$first['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED'),
    'review_forbidden','QA_PROVIDER_OWNER_SELF_APPROVAL');
denied(fn()=>$svc->decide(actor('unrelated'),$first['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED'),
    'review_forbidden','QA_UNRELATED_ACCOUNT');
denied(fn()=>$svc->readOwn(actor('unrelated'),$first['claim_uuid']),
    'claim_not_found','QA_CLAIM_READ_PRIVACY');
denied(fn()=>$svc->readOwn(null,$first['claim_uuid']),
    'authentication_required','QA_NO_SESSION');
$review = $svc->decide(actor('reviewer'),$first['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED');
$member = $p->query("SELECT * FROM auth_account_memberships WHERE entity_group_id='org_existing'")->fetchAll();
check($review['status']==='APPROVED' && count($member)===1 && $member[0]['account_id']==='claimant_a'
    && $member[0]['role_code']==='owner' && $member[0]['status']==='active'
    && $member[0]['assignment_source']==='provider_claim_approval','QA_APPROVAL_OWNER_MEMBERSHIP');
check(countRows($p,'healthcare_organization_claim_events')===2
    && $review['events'][1]['previous_status']==='PENDING'
    && $review['events'][1]['new_status']==='APPROVED','QA_CLAIM_AUDIT');
check(!array_key_exists('events',$svc->readOwn(actor('claimant_a'),$first['claim_uuid']))
    && !array_key_exists('decision_reason_code',$svc->readOwn(actor('claimant_a'),$first['claim_uuid'])),
    'QA_INTERNAL_NOTES_PRIVATE');
check(countRows($p,'healthcare_organization_provider_status')===0
    && countRows($p,'profile_subscriptions')===0,'QA_NO_VERIFICATION_OR_SUBSCRIPTION');
denied(fn()=>$svc->decide(actor('reviewer'),$first['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED'),
    'claim_terminal','QA_TERMINAL_REOPEN');
$second = $svc->submitExisting(actor('claimant_b'),'org_existing','second-claim-01');
denied(fn()=>$svc->decide(actor('reviewer'),$second['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED'),
    'active_owner_exists','QA_ACTIVE_OWNER');
check($svc->readOwn(actor('claimant_b'),$second['claim_uuid'])['status']==='PENDING','QA_ACTIVE_OWNER_FAIL_CLOSED');
group($p,'org_reject','Reject Lab');
$reject = $svc->submitExisting(actor('claimant_b'),'org_reject','reject-claim-01');
check($svc->decide(actor('reviewer'),$reject['claim_uuid'],'REJECTED','EVIDENCE_INSUFFICIENT')['status']==='REJECTED'
    && (int)$p->query("SELECT COUNT(*) FROM auth_account_memberships WHERE entity_group_id='org_reject'")->fetchColumn()===0,
    'QA_REJECTION');
group($p,'org_cancel','Cancel Lab');
$cancel = $svc->submitExisting(actor('claimant_b'),'org_cancel','cancel-claim-01');
check($svc->cancel(actor('claimant_b'),$cancel['claim_uuid'])['status']==='CANCELED'
    && (int)$p->query("SELECT COUNT(*) FROM auth_account_memberships WHERE entity_group_id='org_cancel'")->fetchColumn()===0,
    'QA_CANCEL');
denied(fn()=>$svc->decide(actor('reviewer'),$cancel['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED'),
    'claim_terminal','QA_CANCEL_TERMINAL');
$new = $svc->submitNewCandidate(actor('claimant_b'),'DIAGNOSTIC_CENTER','New Diagnostic Center','candidate-claim-01');
$created = $p->prepare('SELECT organization_type_key,status FROM medical_groups WHERE group_id=?');
$created->execute([$new['group_id']]);$createdRow=$created->fetch();
check($new['status']==='PENDING' && $createdRow['organization_type_key']==='DIAGNOSTIC_CENTER'
    && $createdRow['status']==='pending' && (int)$p->query("SELECT COUNT(*) FROM auth_account_memberships WHERE entity_group_id='".$new['group_id']."'")->fetchColumn()===0,
    'QA_NEW_ORGANIZATION_CANDIDATE');
check($svc->submitNewCandidate(actor('claimant_b'),'DIAGNOSTIC_CENTER','New Diagnostic Center','candidate-claim-01')['claim_uuid']
    ===$new['claim_uuid'],'QA_CANDIDATE_IDEMPOTENT_REPLAY');
denied(fn()=>$svc->submitNewCandidate(actor('claimant_a'),'DIAGNOSTIC_CENTER','New Diagnostic Center','candidate-claim-02'),
    'duplicate_review_required','QA_DUPLICATE_ORGANIZATION');
group($p,'org_merged_alias','Former Diagnostic Name','DIAGNOSTIC_CENTER','merged');
$p->exec("UPDATE medical_groups SET merged_into_group_id='org_existing' WHERE group_id='org_merged_alias'");
try {
    $svc->submitNewCandidate(actor('claimant_b'),'DIAGNOSTIC_CENTER','Former Diagnostic Name','candidate-claim-merged');
    throw new RuntimeException('FAIL QA_MERGED_IDENTITY_DUPLICATE unexpected_allow');
} catch (HealthcareOrganizationClaimException $e) {
    check($e->reason==='duplicate_review_required' && ($e->details['existing_group_id']??null)==='org_existing',
        'QA_MERGED_IDENTITY_DUPLICATE');
}
denied(fn()=>$svc->submitNewCandidate(actor('claimant_b'),'UNKNOWN','Bad Candidate','candidate-claim-03'),
    'invalid_provider_organization_type','QA_INVALID_ORGANIZATION_TYPE');
check($svc->decide(actor('reviewer'),$new['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED')['status']==='APPROVED',
    'QA_NEW_CANDIDATE_APPROVAL');

group($p,'org_race','Race Lab');
$raceA = $svc->submitExisting(actor('claimant_a'),'org_race','race-claim-01');
$raceB = $svc->submitExisting(actor('claimant_b'),'org_race','race-claim-02');
check($raceA['status']==='PENDING' && $raceB['status']==='PENDING','QA_COMPETING_CLAIMS');
$workers=[];
foreach ([[$raceA['claim_uuid'],'reviewer',1],[$raceB['claim_uuid'],'other_reviewer',2]] as [$id,$reviewer,$index]) {
    $command = [PHP_BINARY,__FILE__,'approve_worker',$id,$reviewer,(string)$index];
    $proc = proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,
        ['PROV04B_QA_DB'=>$db,'PROV04B_QA_TMP'=>$tmp]);
    if (!is_resource($proc)) throw new RuntimeException('worker_start_failed');
    fclose($pipes[0]);$workers[]=[$proc,$pipes];
}
file_put_contents($tmp.'/start','go');
foreach ($workers as [$proc,$pipes]) {
    $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    if (proc_close($proc)!==0) throw new RuntimeException('worker_failed '.$stdout.' '.$stderr);
}
$results=[trim(file_get_contents($tmp.'/result_1')),trim(file_get_contents($tmp.'/result_2'))];sort($results);
echo 'QA_CONCURRENT_RESULTS='.implode(',',$results)."\n";
check($results===['APPROVED','active_owner_exists']
    && (int)$p->query("SELECT COUNT(*) FROM auth_account_memberships WHERE entity_group_id='org_race' AND role_code='owner' AND status='active'")->fetchColumn()===1,
    'QA_CONCURRENT_APPROVAL_SINGLE_OWNER');

$policy = new \Identity\Services\OrganizationCapabilityPolicy();
foreach (['patients','clinical_record','prescriptions','agenda_appointments','provider_referrals_read','provider_results_write'] as $cap) {
    check(!$policy->allows('LABORATORY','owner',$cap),'QA_OWNER_CLINICAL_DENY_'.strtoupper($cap));
}
check(countRows($p,'profile_subscriptions')===0 && countRows($p,'healthcare_organization_provider_status')===0,
    'QA_NO_COMMERCIAL_OR_PROVIDER_VERIFICATION');
group($p,'org_atomic','Atomic Lab');
$atomic = $svc->submitExisting(actor('claimant_a'),'org_atomic','atomic-claim-01');
$p->exec("CREATE TRIGGER prov04b_fail_approved_audit BEFORE INSERT ON healthcare_organization_claim_events
    FOR EACH ROW BEGIN IF NEW.action='APPROVED' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'; END IF; END");
try {
    try { $svc->decide(actor('reviewer'),$atomic['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED');
        throw new RuntimeException('FAIL QA_APPROVAL_ATOMICITY unexpected_allow');
    } catch (PDOException) {}
} finally { $p->exec('DROP TRIGGER prov04b_fail_approved_audit'); }
$atomicState=$p->prepare('SELECT status FROM healthcare_organization_claims WHERE claim_uuid=?');
$atomicState->execute([$atomic['claim_uuid']]);
check($atomicState->fetchColumn()==='PENDING'
    && (int)$p->query("SELECT COUNT(*) FROM auth_account_memberships WHERE entity_group_id='org_atomic'")->fetchColumn()===0
    && (int)$p->query("SELECT COUNT(*) FROM healthcare_organization_claim_events e
        JOIN healthcare_organization_claims c ON c.claim_uuid=e.claim_uuid WHERE c.group_id='org_atomic'")->fetchColumn()===1,
    'QA_APPROVAL_ATOMICITY');
echo "PROV04B_DISPOSABLE_GATE=PASS\n";
