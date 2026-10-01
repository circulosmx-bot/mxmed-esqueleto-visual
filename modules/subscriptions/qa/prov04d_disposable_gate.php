<?php
declare(strict_types=1);

if (PHP_SAPI!=='cli') exit(1);
require_once __DIR__.'/../../identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../agenda/services/HealthcareOrganizationClaimService.php';
require_once __DIR__.'/../../agenda/services/HealthcareOrganizationTeamService.php';
require_once __DIR__.'/../services/ProviderCommercialEntitlementService.php';
require_once __DIR__.'/../services/ProviderOrganizationSubscriptionService.php';
require_once __DIR__.'/../repositories/CurrentSubscriptionRepository.php';
require_once __DIR__.'/../repositories/SubscriptionContractAcceptanceRepository.php';
require_once __DIR__.'/../services/CurrentSubscriptionReadModelService.php';
require_once __DIR__.'/../services/CreateSubscriptionWithAcceptanceService.php';

use Agenda\Services\HealthcareOrganizationClaimService;
use Agenda\Services\HealthcareOrganizationManagementAuthorizationService as Policy;
use Agenda\Services\HealthcareOrganizationTeamService;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\SessionId;
use Identity\Contracts\SessionPrincipal;
use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionTokenDigest;
use Subscriptions\Repositories\CurrentSubscriptionRepository;
use Subscriptions\Repositories\SubscriptionContractAcceptanceRepository;
use Subscriptions\Services\CreateSubscriptionWithAcceptanceService;
use Subscriptions\Services\CurrentSubscriptionReadModelService;
use Subscriptions\Services\ProviderCommercialEntitlementService;
use Subscriptions\Services\ProviderOrganizationSubscriptionException;
use Subscriptions\Services\ProviderOrganizationSubscriptionService;
use Subscriptions\Services\SubscriptionWriteException;

$db=getenv('PROV04D_QA_DB');
if (!$db || !preg_match('/^prov04d_qa_[0-9a-f]{12}$/D',$db)) throw new RuntimeException('disposable_db_required');
$p=new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4','root','',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false]);
function actor(string $id): AuthenticatedAccessContext {
    $now=new DateTimeImmutable('now');
    $principal=new SessionPrincipal($id,1,'active',$now->format('Y-m-d H:i:s'));
    $session=new SessionRecord(SessionId::generate(),new SessionTokenDigest(hash('sha256',$id.random_bytes(8))),
        $principal,$now,$now,$now->modify('+3600 seconds'),$now->modify('+43200 seconds'));
    return new AuthenticatedAccessContext($principal,$session);
}
function check(bool $ok,string $label): void { if (!$ok) throw new RuntimeException('FAIL '.$label); echo $label."=PASS\n"; }
function denied(callable $call,string $reason,string $label): void {
    try { $call(); } catch (ProviderOrganizationSubscriptionException $e) { check($e->reason===$reason,$label); return; }
    throw new RuntimeException('FAIL '.$label.' unexpectedly_allowed');
}
function rowCount(PDO $p,string $table): int { return (int)$p->query('SELECT COUNT(*) FROM '.$table)->fetchColumn(); }

check(rowCount($p,'profile_subscriptions')===0 && rowCount($p,'subscription_plans')===0
    && rowCount($p,'provider_subscription_plan_capabilities')===0,'DISPOSABLE_MIGRATION_REHEARSAL');
foreach (['owner','reviewer','operator','admin','collab','outsider'] as $id) {
    $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at)
        VALUES(?,?,?,'active',NOW())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
}
$p->exec("INSERT INTO internal_staff(account_id,governance_class,status) VALUES
    ('reviewer','ADVISOR','ACTIVE'),('operator','ADVISOR','ACTIVE')");
$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES
    (UUID(),'reviewer','provider_claim_review','ACTIVE'),
    (UUID(),'operator','provider_subscription_activate','ACTIVE')");
$p->exec("INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source)
    VALUES('provider_a','LABORATORY','provider a','Provider A','pending','operator_created'),
          ('provider_no_owner','HOSPITAL','provider no owner','Provider No Owner','pending','operator_created')");
$claim=new HealthcareOrganizationClaimService($p);
$request=$claim->submitExisting(actor('owner'),'provider_a','prov04d-claim-01');
$claim->decide(actor('reviewer'),$request['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED');
$claimCount=rowCount($p,'healthcare_organization_claims');
$memberCount=rowCount($p,'auth_account_memberships');
$p->exec("INSERT INTO subscription_plans(plan_code,plan_label,billing_period,duration_days,product_family,source)
    VALUES('provider_qa_internal','Provider QA','annual',30,'PROVIDER_ORGANIZATION','disposable_qa'),
          ('doctor_qa','Doctor QA','annual',30,'DOCTOR','disposable_qa')");
foreach (['provider_profile_manage','provider_locations_manage','provider_offerings_manage',
    'provider_service_areas_manage','provider_matching_participation','provider_public_profile_publish'] as $cap) {
    $p->prepare("INSERT INTO provider_subscription_plan_capabilities(plan_code,billing_period,capability)
        VALUES('provider_qa_internal','annual',?)")->execute([$cap]);
}
$entitlement=new ProviderCommercialEntitlementService($p);
$policy=new Policy($p,$entitlement);
$writer=new ProviderOrganizationSubscriptionService($p);
$productActions=[Policy::PROFILE,Policy::LOCATIONS,Policy::OFFERINGS,Policy::SERVICE_AREAS];
check(!$entitlement->hasCapability('provider_a',ProviderCommercialEntitlementService::MATCHING)
    && !$policy->evaluate(actor('owner'),'provider_a',Policy::PROFILE)['allowed']
    && $policy->evaluate(actor('owner'),'provider_a',Policy::TEAM)['allowed'],
    'QA_NO_SUBSCRIPTION');
check($policy->evaluate(actor('owner'),'provider_a',Policy::SUBSCRIPTION)['allowed']
    && !$policy->evaluate(actor('admin'),'provider_a',Policy::SUBSCRIPTION)['allowed'],
    'QA_OWNER_SUBSCRIPTION_MANAGEMENT');
denied(fn()=>$writer->activateInternal(actor('owner'),'provider_a','provider_qa_internal','annual'),
    'internal_governance_required','QA_OWNER_CANNOT_SELF_ACTIVATE');
denied(fn()=>$writer->activateInternal(actor('operator'),'provider_a','doctor_qa','annual'),
    'provider_plan_incompatible','QA_DOCTOR_PLAN_PROVIDER_REJECTED');
denied(fn()=>$writer->activateInternal(actor('operator'),'provider_no_owner','provider_qa_internal','annual'),
    'governed_owner_required','QA_CLAIM_CANNOT_BE_BYPASSED');
denied(fn()=>$writer->activateInternal(actor('operator'),'provider_a','unknown_plan','annual'),
    'provider_plan_incompatible','QA_UNKNOWN_PLAN_REJECTED');
$activated=$writer->activateInternal(actor('operator'),'provider_a','provider_qa_internal','annual');
$subscription=$p->prepare('SELECT * FROM profile_subscriptions WHERE subscription_id=?');
$subscription->execute([$activated['subscription_id']]);$row=$subscription->fetch();
check($activated['status']==='active' && $row['entity_type']==='provider_organization'
    && $row['entity_id']==='provider_a' && $row['doctor_id']===null && $row['profile_id']===null
    && $entitlement->hasCapability('provider_a',ProviderCommercialEntitlementService::MATCHING)
    && $entitlement->hasCapability('provider_a',ProviderCommercialEntitlementService::PUBLIC_PROFILE),
    'QA_ACTIVE_PROVIDER_ENTITLEMENT');
try {
    $p->prepare("INSERT INTO profile_subscriptions(subscription_id,entity_type,entity_id,plan_code,
        contracted_plan_code,effective_plan_code,billing_period,starts_at,expires_at,status)
        VALUES(UUID(),'provider_organization','provider_a','provider_qa_internal',
          'provider_qa_internal','provider_qa_internal','annual',UTC_TIMESTAMP(),
          DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),'active')")->execute();
    throw new RuntimeException('FAIL QA_DATABASE_ACTIVE_UNIQUENESS unexpectedly_allowed');
} catch (PDOException $e) { check($e->getCode()==='23000','QA_DATABASE_ACTIVE_UNIQUENESS'); }
foreach ($productActions as $action) check($policy->evaluate(actor('owner'),'provider_a',$action)['allowed'],
    'QA_OWNER_ENTITLED_'.strtoupper($action));
denied(fn()=>$writer->activateInternal(actor('operator'),'provider_a','provider_qa_internal','annual'),
    'active_provider_subscription_exists','QA_SIMULTANEOUS_ACTIVE_PREVENTED');
$team=new HealthcareOrganizationTeamService($p);
foreach (['admin'=>'administrator','collab'=>'collaborator'] as $account=>$role) {
    $invite=$team->invite(actor('owner'),'provider_a',$account,$role,'prov04d-'.$account.'-01');
    $team->accept(actor($account),$invite['invitation_uuid']);
}
foreach ($productActions as $action) {
    check($policy->evaluate(actor('admin'),'provider_a',$action)['allowed'],
        'QA_ADMIN_ENTITLED_'.strtoupper($action));
    check(!$policy->evaluate(actor('collab'),'provider_a',$action)['allowed'],
        'QA_COLLABORATOR_DENIED_'.strtoupper($action));
}
check(!$policy->evaluate(actor('admin'),'provider_a',Policy::TEAM)['allowed']
    && !$policy->evaluate(actor('admin'),'provider_a',Policy::SUBSCRIPTION)['allowed'],
    'QA_ADMIN_SUBSCRIPTION_MANAGEMENT_DENY');
check(!$policy->evaluate(actor('collab'),'provider_a',Policy::SUBSCRIPTION)['allowed'],
    'QA_COLLABORATOR_SUBSCRIPTION_MANAGEMENT_DENY');
$clinical=new \Identity\Services\OrganizationCapabilityPolicy();
foreach (['patients','clinical_record','prescriptions','agenda_appointments','provider_referrals_read',
    'provider_referral_respond','provider_results_write'] as $cap) {
    check(!$clinical->allows('LABORATORY','owner',$cap),'QA_PROVIDER_CLINICAL_DENY_'.strtoupper($cap));
}
check(rowCount($p,'healthcare_organization_provider_status')===0
    && rowCount($p,'healthcare_organization_claims')===$claimCount
    && rowCount($p,'auth_account_memberships')===$memberCount+2,
    'QA_NO_VERIFICATION_OR_CLAIM_SIDE_EFFECT');

$p->prepare("UPDATE profile_subscriptions SET status='grace_period',
    starts_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 DAY),
    expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),
    grace_starts_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),
    grace_ends_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE subscription_id=?")
    ->execute([$activated['subscription_id']]);
check($entitlement->hasCapability('provider_a',ProviderCommercialEntitlementService::MATCHING),
    'QA_BOUNDED_GRACE_ENTITLEMENT');

// The clock, not a destructive lifecycle write, removes expired participation.
$p->prepare("UPDATE profile_subscriptions SET grace_ends_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)
    WHERE subscription_id=?")
    ->execute([$activated['subscription_id']]);
check(!$entitlement->hasCapability('provider_a',ProviderCommercialEntitlementService::MATCHING)
    && !$policy->evaluate(actor('owner'),'provider_a',Policy::PROFILE)['allowed']
    && $policy->evaluate(actor('owner'),'provider_a',Policy::TEAM)['allowed']
    && rowCount($p,'healthcare_organization_provider_status')===0,
    'QA_EXPIRED_SUBSCRIPTION');
$renewed=$writer->activateInternal(actor('operator'),'provider_a','provider_qa_internal','annual');
check($renewed['subscription_id']!==$activated['subscription_id']
    && $entitlement->hasCapability('provider_a',ProviderCommercialEntitlementService::MATCHING)
    && rowCount($p,'profile_subscriptions')===2
    && (string)$p->query("SELECT status FROM profile_subscriptions WHERE subscription_id='".$activated['subscription_id']."'")->fetchColumn()==='expired',
    'QA_EXPIRED_HISTORY_PRESERVED_AND_RESTORED');
check($writer->cancelInternal(actor('operator'),'provider_a',$renewed['subscription_id'])['status']==='cancelled'
    && !$entitlement->hasCapability('provider_a',ProviderCommercialEntitlementService::MATCHING)
    && rowCount($p,'profile_subscriptions')===2
    && rowCount($p,'provider_subscription_governance_events')===3,'QA_CANCEL_PRESERVES_HISTORY');
$p->exec("INSERT INTO subscription_plans(plan_code,plan_label,billing_period,duration_days,product_family,source)
    VALUES('provider_qa_partial','Provider Partial QA','annual',30,'PROVIDER_ORGANIZATION','disposable_qa')");
$p->exec("INSERT INTO provider_subscription_plan_capabilities(plan_code,billing_period,capability)
    VALUES('provider_qa_partial','annual','provider_matching_participation')");
$partial=$writer->activateInternal(actor('operator'),'provider_a','provider_qa_partial','annual');
check($entitlement->hasCapability('provider_a',ProviderCommercialEntitlementService::MATCHING)
    && !$entitlement->hasCapability('provider_a',Policy::PROFILE)
    && !$policy->evaluate(actor('owner'),'provider_a',Policy::PROFILE)['allowed'],
    'QA_CAPABILITY_SPECIFIC_NOT_GLOBAL_PAID');
$writer->cancelInternal(actor('operator'),'provider_a',$partial['subscription_id']);
$subscriptionCountBefore=rowCount($p,'profile_subscriptions');
$eventCountBefore=rowCount($p,'provider_subscription_governance_events');
$p->exec("CREATE TRIGGER prov04d_fail_activation_audit BEFORE INSERT ON provider_subscription_governance_events
    FOR EACH ROW BEGIN IF NEW.action='ACTIVATED' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'; END IF; END");
try {
    try { $writer->activateInternal(actor('operator'),'provider_a','provider_qa_internal','annual');
        throw new RuntimeException('FAIL QA_ACTIVATION_ATOMICITY unexpectedly_allowed'); }
    catch (PDOException) {}
} finally { $p->exec('DROP TRIGGER prov04d_fail_activation_audit'); }
check(rowCount($p,'profile_subscriptions')===$subscriptionCountBefore
    && rowCount($p,'provider_subscription_governance_events')===$eventCountBefore,
    'QA_ACTIVATION_ATOMICITY');

$doctorWriter=new CreateSubscriptionWithAcceptanceService($p,new CurrentSubscriptionRepository($p),
    new CurrentSubscriptionReadModelService(new CurrentSubscriptionRepository($p)),
    new SubscriptionContractAcceptanceRepository($p));
$p->exec("INSERT INTO profiles_doctors(doctor_id,display_name) VALUES('doctor_qa','Doctor QA')");
$doctorInput=['entity_type'=>'doctor','entity_id'=>'doctor_qa','doctor_id'=>'doctor_qa',
    'actor_user_id'=>'1','actor_role'=>'doctor','payload'=>[
        'plan_code'=>'provider_qa_internal','billing_period'=>'annual',
        'contract'=>['version'=>'qa','hash'=>'sha256:'.str_repeat('a',64),'snapshot_url'=>'https://example.invalid/qa'],
        'acceptance'=>['source'=>'system'],
    ]];
try {
    $doctorWriter->create($doctorInput);
    throw new RuntimeException('FAIL QA_PROVIDER_PLAN_DOCTOR_REJECTED unexpectedly_allowed');
} catch (SubscriptionWriteException $e) {
    check($e->errorCode()==='plan_entity_incompatible','QA_PROVIDER_PLAN_DOCTOR_REJECTED');
}
$doctorInput['payload']['plan_code']='doctor_qa';
$doctorCreated=$doctorWriter->create($doctorInput);
check(($doctorCreated['current_subscription']['effective_plan_code']??null)==='doctor_qa'
    && (int)$p->query("SELECT COUNT(*) FROM profile_subscriptions WHERE entity_type='doctor' AND entity_id='doctor_qa'")->fetchColumn()===1,
    'QA_DOCTOR_SUBSCRIPTION_REGRESSION');
echo "PROV04D_DISPOSABLE_GATE=PASS\n";
