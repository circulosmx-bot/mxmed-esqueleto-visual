<?php
declare(strict_types=1);

if (PHP_SAPI!=='cli') exit(1);
require_once __DIR__.'/../../identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../services/HealthcareOrganizationClaimService.php';
require_once __DIR__.'/../services/HealthcareOrganizationTeamService.php';

use Agenda\Services\HealthcareOrganizationClaimService;
use Agenda\Services\HealthcareOrganizationManagementAuthorizationService as Policy;
use Agenda\Services\HealthcareOrganizationTeamException;
use Agenda\Services\HealthcareOrganizationTeamService;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\SessionId;
use Identity\Contracts\SessionPrincipal;
use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionTokenDigest;

$db=getenv('PROV04C_QA_DB'); $tmp=getenv('PROV04C_QA_TMP');
if (!$db || !$tmp || !preg_match('/^prov04c_qa_[0-9a-f]{12}$/D',$db)) throw new RuntimeException('disposable_db_required');
function db(): PDO {
    return new PDO('mysql:host=localhost;dbname='.getenv('PROV04C_QA_DB').';charset=utf8mb4','root','',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false]);
}
function actor(string $id): AuthenticatedAccessContext {
    $now=new DateTimeImmutable('now');
    $principal=new SessionPrincipal($id,1,'active',$now->format('Y-m-d H:i:s'));
    $session=new SessionRecord(SessionId::generate(),new SessionTokenDigest(hash('sha256',$id.random_bytes(8))),
        $principal,$now,$now,$now->modify('+3600 seconds'),$now->modify('+43200 seconds'));
    return new AuthenticatedAccessContext($principal,$session);
}
function check(bool $ok,string $label): void { if (!$ok) throw new RuntimeException('FAIL '.$label); echo $label."=PASS\n"; }
function deny(callable $call,string $reason,string $label): void {
    try { $call(); } catch (HealthcareOrganizationTeamException $e) { check($e->reason===$reason,$label); return; }
    throw new RuntimeException('FAIL '.$label.' unexpectedly_allowed');
}
function countRows(PDO $p,string $table): int { return (int)$p->query('SELECT COUNT(*) FROM '.$table)->fetchColumn(); }

if (($argv[1]??'')==='accept_worker') {
    $id=$argv[2]; $worker=$argv[3];
    while (!is_file($tmp.'/start')) usleep(10000);
    try { $result=(new HealthcareOrganizationTeamService(db()))->accept(actor('race'),$id)['status']; }
    catch (HealthcareOrganizationTeamException $e) { $result=$e->reason; }
    file_put_contents($tmp.'/worker_'.$worker,$result);
    exit(0);
}

$p=db();
check(countRows($p,'healthcare_organization_membership_invitations')===0
    && countRows($p,'healthcare_organization_membership_invitation_events')===0
    && countRows($p,'healthcare_organization_member_action_events')===0,'DISPOSABLE_MIGRATION_REHEARSAL');
foreach (['owner','admin','collab','wrong','reviewer','expire','revoke','race','target','other_owner'] as $id) {
    $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at)
        VALUES(?,?,?,'active',NOW())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
}
$p->exec("INSERT INTO internal_staff(account_id,governance_class,status) VALUES ('reviewer','ADVISOR','ACTIVE')");
$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status)
    VALUES(UUID(),'reviewer','provider_claim_review','ACTIVE')");
foreach (['org_a'=>'QA Provider A','org_b'=>'QA Provider B'] as $id=>$name) {
    $p->prepare("INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source)
        VALUES(?,'LABORATORY',?,?, 'pending','operator_created')")
        ->execute([$id,mb_strtolower($name),$name]);
}
$claim=new HealthcareOrganizationClaimService($p);
$a=$claim->submitExisting(actor('owner'),'org_a','prov04c-claim-a');
$claim->decide(actor('reviewer'),$a['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED');
$b=$claim->submitExisting(actor('other_owner'),'org_b','prov04c-claim-b');
$claim->decide(actor('reviewer'),$b['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED');
$svc=new HealthcareOrganizationTeamService($p);
$policy=new Policy($p);
$admin=$svc->invite(actor('owner'),'org_a','admin','administrator','prov04c-admin-01');
check($admin['status']==='PENDING' && $svc->invite(actor('owner'),'org_a','admin','administrator','prov04c-admin-01')['invitation_uuid']===$admin['invitation_uuid'],'QA_OWNER_INVITES_ADMIN');
deny(fn()=>$svc->invite(actor('owner'),'org_a','admin','collaborator','prov04c-admin-01'),
    'idempotency_conflict','QA_INVITE_KEY_CONFLICT');
deny(fn()=>$svc->accept(actor('wrong'),$admin['invitation_uuid']),'invitation_not_found','QA_WRONG_INVITEE');
deny(fn()=>$svc->accept(null,$admin['invitation_uuid']),'authentication_required','QA_UUID_ALONE_DENIED');
$adminAccepted=$svc->accept(actor('admin'),$admin['invitation_uuid']);
check($adminAccepted['status']==='ACCEPTED' && $svc->accept(actor('admin'),$admin['invitation_uuid'])['membership_id']===$adminAccepted['membership_id']
    && $svc->readOwnMembership(actor('admin'),'org_a')['role_code']==='administrator','QA_OWNER_INVITES_ADMIN_ACCEPT');
$collab=$svc->invite(actor('owner'),'org_a','collab','collaborator','prov04c-collab-01');
check($collab['status']==='PENDING' && $svc->accept(actor('collab'),$collab['invitation_uuid'])['status']==='ACCEPTED'
    && $svc->readOwnMembership(actor('collab'),'org_a')['role_code']==='collaborator','QA_OWNER_INVITES_COLLABORATOR');
deny(fn()=>$svc->invite(actor('owner'),'org_a','wrong','owner','prov04c-owner-01'),
    'invalid_invitation_role','QA_OWNER_ROLE_INVITATION_REJECTED');
deny(fn()=>$svc->invite(actor('admin'),'org_a','wrong','collaborator','prov04c-admin-deny'),
    'team_management_denied','QA_ADMIN_INVITE_DENIED');
deny(fn()=>$svc->invite(actor('collab'),'org_a','wrong','collaborator','prov04c-collab-deny'),
    'team_management_denied','QA_COLLABORATOR_INVITE_DENIED');
$expired=$svc->invite(actor('owner'),'org_a','expire','collaborator','prov04c-expire-01');
$p->prepare('UPDATE healthcare_organization_membership_invitations SET issued_at=DATE_SUB(NOW(6),INTERVAL 8 DAY),expires_at=DATE_SUB(NOW(6),INTERVAL 1 SECOND) WHERE invitation_uuid=?')
    ->execute([$expired['invitation_uuid']]);
deny(fn()=>$svc->accept(actor('expire'),$expired['invitation_uuid']),'invitation_expired','QA_EXPIRED_INVITATION');
check($svc->readOwnInvitation(actor('expire'),$expired['invitation_uuid'])['status']==='EXPIRED'
    && $svc->readOwnMembership(actor('expire'),'org_a')===null,'QA_EXPIRED_NO_MEMBERSHIP');
$revoked=$svc->invite(actor('owner'),'org_a','revoke','collaborator','prov04c-revoke-01');
check($svc->revokeInvitation(actor('owner'),$revoked['invitation_uuid'])['status']==='REVOKED','QA_REVOKED_INVITATION');
deny(fn()=>$svc->accept(actor('revoke'),$revoked['invitation_uuid']),'invitation_terminal','QA_REVOKED_ACCEPT_DENIED');
$raced=$svc->invite(actor('owner'),'org_a','race','collaborator','prov04c-race-01');
$workers=[];
foreach ([1,2] as $i) {
    $proc=proc_open([PHP_BINARY,__FILE__,'accept_worker',$raced['invitation_uuid'],(string)$i],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,
        ['PROV04C_QA_DB'=>$db,'PROV04C_QA_TMP'=>$tmp]);
    if (!is_resource($proc)) throw new RuntimeException('worker_start_failed');
    fclose($pipes[0]); $workers[]=[$proc,$pipes];
}
file_put_contents($tmp.'/start','go');
foreach ($workers as [$proc,$pipes]) {
    $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($proc)===0,'QA_ACCEPT_WORKER');
    if ($stderr!=='') throw new RuntimeException('worker_stderr '.$stderr.' '.$stdout);
}
check(trim(file_get_contents($tmp.'/worker_1'))==='ACCEPTED'
    && trim(file_get_contents($tmp.'/worker_2'))==='ACCEPTED'
    && (int)$p->query("SELECT COUNT(*) FROM auth_account_memberships WHERE entity_group_id='org_a' AND account_id='race' AND status='active'")->fetchColumn()===1,
    'QA_CONCURRENT_ACCEPTANCE');
$atomic=$svc->invite(actor('owner'),'org_a','target','collaborator','prov04c-atomic-01');
$p->exec("CREATE TRIGGER prov04c_fail_accept_audit BEFORE INSERT ON healthcare_organization_membership_invitation_events
    FOR EACH ROW BEGIN IF NEW.action='ACCEPTED' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'; END IF; END");
try {
    try { $svc->accept(actor('target'),$atomic['invitation_uuid']); throw new RuntimeException('FAIL QA_ACCEPT_ATOMICITY unexpectedly_allowed'); }
    catch (PDOException) {}
} finally { $p->exec('DROP TRIGGER prov04c_fail_accept_audit'); }
check($svc->readOwnInvitation(actor('target'),$atomic['invitation_uuid'])['status']==='PENDING'
    && $svc->readOwnMembership(actor('target'),'org_a')===null,'QA_ACCEPT_ATOMICITY');
check($svc->accept(actor('target'),$atomic['invitation_uuid'])['status']==='ACCEPTED','QA_ACCEPT_AFTER_ROLLBACK');
$actions=[Policy::TEAM,Policy::PROFILE,Policy::LOCATIONS,Policy::OFFERINGS,Policy::SERVICE_AREAS];
foreach (['owner'=>true,'admin'=>false,'collab'=>false] as $role=>$teamAllowed) {
    foreach ($actions as $action) {
        $result=$policy->evaluate(actor($role),'org_a',$action);
        $expected=$role!=='collab' && ($action!==Policy::TEAM || $teamAllowed);
        check($result['eligible']===$expected && $result['allowed']===($action===Policy::TEAM && $teamAllowed),
            'QA_'.strtoupper($role).'_ACTION_'.strtoupper($action));
    }
}
$p->exec("INSERT INTO clinical_study_types(study_type_key,display_name_es,category_key,aliases_json)
    VALUES('prov04c_disposable','Estudio QA','LABORATORIO','[]')");
$study=(int)$p->lastInsertId();
$p->exec("INSERT INTO healthcare_organization_locations(location_uuid,group_id,branch_name)
    VALUES('00000000-0000-4000-8000-00000000000b','org_b','Branch B')");
$location=(int)$p->lastInsertId();
$p->prepare('INSERT INTO healthcare_organization_location_study_offerings(location_id,study_type_id) VALUES(?,?)')->execute([$location,$study]);
$offering=(int)$p->lastInsertId();
$p->prepare("INSERT INTO healthcare_organization_location_study_service_areas(offering_id,scope_type,region_key)
    VALUES(?,'POSTAL_CODE','MX|CP|01000')")->execute([$offering]);
$area=(int)$p->lastInsertId();
foreach (['owner','admin'] as $who) {
    foreach ([['location','00000000-0000-4000-8000-00000000000b',Policy::LOCATIONS],
        ['offering',$offering,Policy::OFFERINGS],['service_area',$area,Policy::SERVICE_AREAS]] as [$type,$id,$action]) {
        check(!$policy->evaluate(actor($who),'org_a',$action,$type,$id)['eligible'],'QA_CROSS_ORGANIZATION_'.strtoupper($who).'_'.strtoupper($type));
    }
}
$clinical=new \Identity\Services\OrganizationCapabilityPolicy();
foreach (['owner','administrator','collaborator'] as $role) {
    foreach (['patients','clinical_record','prescriptions','agenda_appointments'] as $cap) {
        check(!$clinical->allows('LABORATORY',$role,$cap),'QA_'.strtoupper($role).'_CLINICAL_DENY_'.strtoupper($cap));
    }
}
foreach (['owner','admin','collab'] as $who) {
    check(!$policy->evaluate(actor($who),'org_a','provider_verify')['allowed'],'QA_SELF_VERIFICATION_DENY_'.strtoupper($who));
}
deny(fn()=>$svc->revokeMember(actor('owner'),'org_a',
    (string)$p->query("SELECT membership_id FROM auth_account_memberships WHERE entity_group_id='org_a' AND role_code='owner'")->fetchColumn()),
    'member_not_manageable','QA_SOLE_OWNER_PROTECTED');
check($svc->suspendMember(actor('owner'),'org_a',$adminAccepted['membership_id'])['status']==='suspended'
    && !$policy->evaluate(actor('admin'),'org_a',Policy::PROFILE)['eligible'],'QA_MEMBER_SUSPEND');
check($svc->revokeMember(actor('owner'),'org_a',$adminAccepted['membership_id'])['status']==='revoked'
    && countRows($p,'healthcare_organization_member_action_events')===2,'QA_MEMBER_REVOKE_AUDIT');
check(countRows($p,'healthcare_organization_membership_invitation_events')>=10,'QA_INVITATION_AUDIT');
echo "PROV04C_DISPOSABLE_GATE=PASS\n";
