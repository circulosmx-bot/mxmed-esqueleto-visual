<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'||!preg_match('/^mxmed_gate4d_preview_prov04e_[0-9a-f]{12}$/D',(string)getenv('PROV04E_QA_DB'))) exit(1);
require_once __DIR__.'/../../identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../services/HealthcareOrganizationClaimService.php';
require_once __DIR__.'/../../subscriptions/services/ProviderOrganizationSubscriptionService.php';
require_once __DIR__.'/../http/ProviderManagementApi.php';
use Agenda\Services\HealthcareOrganizationClaimService;
use Agenda\Services\HealthcareOrganizationTeamService;
use Agenda\Services\HealthcareOrganizationDirectoryService;
use Agenda\Services\HealthcareProviderCoverageService;
use Agenda\Http\ProviderManagementApi;
use Agenda\Http\ProviderManagementHttpException;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\SessionId;
use Identity\Contracts\SessionPrincipal;
use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionTokenDigest;
use Identity\Contracts\SessionToken;
use Identity\Services\SessionTokenCodec;
use Identity\Http\CsrfTokenService;
use Subscriptions\Services\ProviderOrganizationSubscriptionService;
$p=new PDO('mysql:host=localhost;dbname='.getenv('PROV04E_QA_DB').';charset=utf8mb4','root','',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
function actor(string $id): AuthenticatedAccessContext {
    $now=new DateTimeImmutable('now');$principal=new SessionPrincipal($id,1,'active',$now->format('Y-m-d H:i:s'));
    $session=new SessionRecord(SessionId::generate(),new SessionTokenDigest(hash('sha256',$id.random_bytes(8))),$principal,$now,$now,$now->modify('+3600 seconds'),$now->modify('+43200 seconds'));
    return new AuthenticatedAccessContext($principal,$session);
}
function check(bool $ok,string $name): void {if (!$ok) throw new RuntimeException('FAIL '.$name);echo $name."=PASS\n";}
foreach (['owner','owner_b','reviewer','operator','admin','collab','invitee'] as $id) $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',NOW())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
$p->exec("INSERT INTO internal_staff(account_id,governance_class,status) VALUES ('reviewer','ADVISOR','ACTIVE'),('operator','ADVISOR','ACTIVE')");
$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES (UUID(),'reviewer','provider_claim_review','ACTIVE'),(UUID(),'operator','provider_subscription_activate','ACTIVE')");
$p->exec("INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source) VALUES
    ('prov_a','LABORATORY','provider a','Provider A','pending','operator_created'),('prov_b','LABORATORY','provider b','Provider B','pending','operator_created')");
$claim=new HealthcareOrganizationClaimService($p);
foreach (['owner'=>'prov_a','owner_b'=>'prov_b'] as $id=>$group) {$r=$claim->submitExisting(actor($id),$group,'prov04e-claim-'.$id);$claim->decide(actor('reviewer'),$r['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED');}
$p->exec("INSERT INTO subscription_plans(plan_code,plan_label,billing_period,duration_days,product_family,source) VALUES ('prov04e_plan','Provider QA','annual',30,'PROVIDER_ORGANIZATION','disposable_qa')");
foreach (['provider_profile_manage','provider_locations_manage','provider_offerings_manage','provider_service_areas_manage','provider_matching_participation'] as $cap)
    $p->prepare("INSERT INTO provider_subscription_plan_capabilities(plan_code,billing_period,capability) VALUES ('prov04e_plan','annual',?)")->execute([$cap]);
$p->exec("INSERT INTO clinical_study_types(study_type_key,display_name_es,category_key,aliases_json) VALUES ('prov04e_bh','Biometría hemática','LABORATORIO','[\"BH\"]')");
$study=(int)$p->lastInsertId();
$api=new ProviderManagementApi($p);
function callApi(ProviderManagementApi $api,?AuthenticatedAccessContext $actor,string $method,array $path,array $body=[]): array {return $api->handle($method,$path,[],$body,$actor)[1];}
function denied(callable $fn,int $status,string $error,string $name): void {try {$fn();}catch(ProviderManagementHttpException $e){check($e->status===$status&&$e->error===$error,$name);return;}throw new RuntimeException('FAIL '.$name.' allowed');}
$a=actor('owner');$org=['organizations','prov_a'];
check(callApi($api,$a,'GET',$org)['member_role']==='owner','QA_OWNER_CONTEXT');
denied(fn()=>callApi($api,null,'GET',$org),401,'UNAUTHENTICATED','QA_NO_SESSION');
denied(fn()=>callApi($api,$a,'POST',[...$org,'locations'],['branch_name'=>'X','submission_key'=>'prov04e-noent']),403,'ENTITLEMENT_REQUIRED','QA_NO_ENTITLEMENT');
$team=new HealthcareOrganizationTeamService($p);
$inv=callApi($api,$a,'POST',[...$org,'invitations'],['invitee_account_id'=>'admin','role'=>'administrator','submission_key'=>'prov04e-inv-admin']);
check($inv['status']==='PENDING'&&count(callApi($api,$a,'GET',[...$org,'invitations'])['invitations'])===1,'QA_OWNER_TEAM_NO_ENTITLEMENT');
check(callApi($api,actor('admin'),'POST',['invitations',$inv['invitation_uuid'],'accept'])['status']==='ACCEPTED','QA_INVITE_ACCEPT');
denied(fn()=>callApi($api,actor('admin'),'POST',[...$org,'locations'],['branch_name'=>'No entitlement','submission_key'=>'prov04e-admin-noent']),403,'ENTITLEMENT_REQUIRED','QA_ADMIN_NO_ENTITLEMENT');
$inv2=callApi($api,$a,'POST',[...$org,'invitations'],['invitee_account_id'=>'collab','role'=>'collaborator','submission_key'=>'prov04e-inv-collab']);
callApi($api,actor('collab'),'POST',['invitations',$inv2['invitation_uuid'],'accept']);
check(count(callApi($api,$a,'GET',[...$org,'team'])['members'])===3,'QA_TEAM_LIST');
denied(fn()=>callApi($api,actor('admin'),'GET',[...$org,'team']),403,'FORBIDDEN','QA_ADMIN_TEAM_DENY');
$pending=callApi($api,$a,'POST',[...$org,'invitations'],['invitee_account_id'=>'invitee','role'=>'collaborator','submission_key'=>'prov04e-inv-revoke']);
check(callApi($api,$a,'POST',[...$org,'invitations',$pending['invitation_uuid'],'revoke'])['status']==='REVOKED','QA_OWNER_INVITATION_REVOKE');
(new ProviderOrganizationSubscriptionService($p))->activateInternal(actor('operator'),'prov_a','prov04e_plan','annual');
check(callApi($api,$a,'GET',[...$org,'subscription'])['subscription']['status']==='active','QA_OWNER_SUBSCRIPTION_READ');
denied(fn()=>callApi($api,$a,'POST',[...$org,'subscription'],['plan_code'=>'prov04e_plan']),404,'NOT_FOUND','QA_SUBSCRIPTION_WRITE_ABSENT');
denied(fn()=>callApi($api,$a,'POST',[...$org,'provider-verification'],['verification_state'=>'VERIFIED']),404,'NOT_FOUND','QA_PROVIDER_VERIFY_ROUTE_ABSENT');
check(isset(callApi($api,actor('admin'),'GET',[...$org,'subscription'])['capabilities'])
    && !isset(callApi($api,actor('admin'),'GET',[...$org,'subscription'])['subscription']),'QA_ADMIN_MINIMAL_ENTITLEMENT_READ');
check(!isset(callApi($api,actor('collab'),'GET',[...$org,'subscription'])['subscription']),'QA_COLLAB_ACTIONS_ONLY');
$location=callApi($api,$a,'POST',[...$org,'locations'],['branch_name'=>'Central','postal_code'=>'01000','submission_key'=>'prov04e-loc-0001']);
$uuid=$location['location_uuid'];
check($location['verification_state']==='UNVERIFIED'&&$location['operational_state']==='ACTIVE','QA_LOCATION_UNVERIFIED');
$repeat=callApi($api,$a,'POST',[...$org,'locations'],['branch_name'=>'Central','postal_code'=>'01000','submission_key'=>'prov04e-loc-0001']);
check($repeat['location_uuid']===$uuid,'QA_LOCATION_RETRY');
$locPath=[...$org,'locations',$uuid];
$dir=new HealthcareOrganizationDirectoryService($p);
$other=$dir->createLocation('prov_a',['branch_name'=>'Other']);
$dir->setLocationVerification('prov_a',$other['location_uuid'],'VERIFIED','reviewer');
$dir->setLocationVerification('prov_a',$uuid,'VERIFIED','reviewer');
check(callApi($api,$a,'PATCH',$locPath,['phone'=>'5512345678'])['verification_state']==='VERIFIED','QA_NONMATERIAL_EDIT');
check(callApi($api,$a,'PATCH',$locPath,['postal_code'=>'01001'])['verification_state']==='UNVERIFIED','QA_MATERIAL_EDIT');
check($dir->readOrganization('prov_a')['locations'][1]['verification_state']==='VERIFIED','QA_OTHER_LOCATION_UNCHANGED');
check(callApi($api,$a,'PATCH',[...$locPath,'state'],['operational_state'=>'INACTIVE'])['verification_state']==='UNVERIFIED','QA_LOCATION_INACTIVE');
callApi($api,$a,'PATCH',[...$locPath,'state'],['operational_state'=>'ACTIVE']);
$off=callApi($api,$a,'POST',[...$locPath,'offerings'],['study_type_id'=>$study,'service_mode'=>'HOME_SERVICE','submission_key'=>'prov04e-offer-01']);
check($off['verification_state']==='UNVERIFIED','QA_OFFERING_UNVERIFIED');
try {callApi($api,$a,'POST',[...$locPath,'offerings'],['study_type_id'=>$study,'submission_key'=>'prov04e-offer-02']);throw new RuntimeException('duplicate offering allowed');}
catch (InvalidArgumentException $e) {check(str_contains($e->getMessage(),'already_exists'),'QA_DUPLICATE_OFFERING_UNIQUE');}
denied(fn()=>callApi($api,$a,'POST',[...$locPath,'verify'],['verification_state'=>'VERIFIED']),404,'NOT_FOUND','QA_LOCATION_VERIFY_ROUTE_ABSENT');
$offerPath=[...$locPath,'offerings',(string)$study];
denied(fn()=>callApi($api,$a,'PATCH',$offerPath,['study_type_id'=>999]),422,'UNSUPPORTED_FIELD','QA_OFFERING_IMMUTABLE');
$dir->setOfferingVerification('prov_a',$uuid,$study,'VERIFIED','reviewer');
check(callApi($api,$a,'PATCH',$offerPath,['requires_appointment'=>true])['verification_state']==='VERIFIED','QA_OFFERING_OPERATIONAL_PRESERVES_VERIFICATION');
denied(fn()=>callApi($api,$a,'POST',[...$offerPath,'verify'],['verification_state'=>'VERIFIED']),404,'NOT_FOUND','QA_OFFERING_VERIFY_ROUTE_ABSENT');
$area=callApi($api,$a,'POST',[...$offerPath,'service-areas'],['scope_type'=>'POSTAL_CODE','postal_code'=>'01000','submission_key'=>'prov04e-area-01']);
check($area['verification_state']==='UNVERIFIED','QA_AREA_UNVERIFIED');
try {callApi($api,$a,'POST',[...$offerPath,'service-areas'],['scope_type'=>'POSTAL_CODE','postal_code'=>'01000','submission_key'=>'prov04e-area-02']);throw new RuntimeException('duplicate area allowed');}
catch (InvalidArgumentException $e) {check(str_contains($e->getMessage(),'already_exists'),'QA_DUPLICATE_AREA_UNIQUE');}
check(callApi($api,$a,'POST',[...$offerPath,'service-areas'],['scope_type'=>'POSTAL_CODE','postal_code'=>'01000','submission_key'=>'prov04e-area-01'])['service_area_id']===$area['service_area_id'],'QA_AREA_RETRY');
$areaPath=[...$offerPath,'service-areas',(string)$area['service_area_id']];
denied(fn()=>callApi($api,$a,'PATCH',$areaPath,['postal_code'=>'01001']),404,'NOT_FOUND','QA_AREA_REGION_IMMUTABLE');
denied(fn()=>callApi($api,$a,'POST',[...$areaPath,'verify'],['verification_state'=>'VERIFIED']),404,'NOT_FOUND','QA_AREA_VERIFY_ROUTE_ABSENT');
check(callApi($api,$a,'PATCH',[...$areaPath,'state'],['operational_state'=>'INACTIVE'])['verification_state']==='UNVERIFIED','QA_AREA_STATE');
denied(fn()=>callApi($api,$a,'GET',['organizations','prov_b','locations']),404,'NOT_FOUND','QA_CROSS_ORG');
denied(fn()=>callApi($api,actor('collab'),'POST',[...$org,'locations'],['branch_name'=>'Denied','submission_key'=>'prov04e-collab']),403,'FORBIDDEN','QA_COLLAB_DENY');
$adminLoc=callApi($api,actor('admin'),'POST',[...$org,'locations'],['branch_name'=>'Admin','submission_key'=>'prov04e-admin']);
check($adminLoc['verification_state']==='UNVERIFIED','QA_ADMIN_MANAGE');
$adminOffer=callApi($api,actor('admin'),'POST',[...$org,'locations',$adminLoc['location_uuid'],'offerings'],
    ['study_type_id'=>$study,'service_mode'=>'HOME_SERVICE','submission_key'=>'prov04e-admin-offer']);
check($adminOffer['verification_state']==='UNVERIFIED','QA_ADMIN_OFFERING');
$adminArea=callApi($api,actor('admin'),'POST',[...$org,'locations',$adminLoc['location_uuid'],'offerings',(string)$study,'service-areas'],
    ['scope_type'=>'POSTAL_CODE','postal_code'=>'01000','submission_key'=>'prov04e-admin-area']);
check($adminArea['verification_state']==='UNVERIFIED','QA_ADMIN_AREA');
denied(fn()=>callApi($api,actor('admin'),'POST',[...$org,'invitations'],['invitee_account_id'=>'invitee','role'=>'collaborator','submission_key'=>'prov04e-admin-deny']),403,'FORBIDDEN','QA_ADMIN_INVITE_DENY');
$adminMember=array_values(array_filter(callApi($api,$a,'GET',[...$org,'team'])['members'],
    static fn(array $member): bool=>$member['role_code']==='administrator'))[0];
check(callApi($api,$a,'POST',[...$org,'members',$adminMember['membership_id'],'suspend'])['status']==='suspended','QA_MEMBER_SUSPEND');
check(count(callApi($api,$a,'GET',[...$org,'study-types'])['items'])>=1,'QA_CATALOG');
check($p->query('SELECT COUNT(*) FROM healthcare_organization_location_edit_events')->fetchColumn()>=2,'QA_LOCATION_AUDIT');
check($p->query('SELECT COUNT(*) FROM healthcare_organization_locations')->fetchColumn()===3,'QA_RETRY_NO_DUPLICATE');
if ($fixture=getenv('PROV04E_HTTP_FIXTURE')) {
    $p->prepare("INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version)
        VALUES('owner','disposable-qa-only',NOW(),1)")->execute();
    $pepper=(string)getenv('PROV04E_HTTP_PEPPER');
    if (strlen($pepper)<32) throw new RuntimeException('disposable_http_pepper_required');
    $token=SessionToken::generate();$digest=(new SessionTokenCodec($pepper))->digest($token);
    $now=new DateTimeImmutable('now');$principal=new SessionPrincipal('owner',1,'active',$now->format('Y-m-d H:i:s'));
    $record=new SessionRecord(SessionId::generate(),$digest,$principal,$now,$now,$now->modify('+3600 seconds'),$now->modify('+43200 seconds'));
    file_put_contents($fixture,json_encode(['token'=>(string)$token,'csrf'=>(new CsrfTokenService($pepper,900,new \Identity\Contracts\SystemClock(),'https://127.0.0.1:8140'))->issueAuthenticated($digest),
        'session_key'=>'mxmed:gate4d:preview:session:'.(string)$digest,'session_json'=>json_encode($record->toArray(),JSON_THROW_ON_ERROR)],JSON_THROW_ON_ERROR));
}
echo "PROV04E_DISPOSABLE_GATE=PASS\n";
