<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'||!preg_match('/^mxmed_gate4d_preview_prov04f_[0-9a-f]{12}$/D',(string)getenv('PROV04F_QA_DB'))) exit(1);
require_once __DIR__.'/../../identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../services/HealthcareOrganizationClaimService.php';
require_once __DIR__.'/../http/ProviderManagementApi.php';
use Agenda\Services\HealthcareOrganizationClaimService;
use Agenda\Services\HealthcareOrganizationTeamException;
use Agenda\Http\ProviderManagementApi;
use Agenda\Http\ProviderManagementHttpException;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\SessionId;
use Identity\Contracts\SessionPrincipal;
use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionToken;
use Identity\Contracts\SessionTokenDigest;
use Identity\Services\SessionTokenCodec;
use Identity\Http\CsrfTokenService;
$p=new PDO('mysql:host=localhost;dbname='.getenv('PROV04F_QA_DB').';charset=utf8mb4','root','',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
function actor(string $id): AuthenticatedAccessContext {
    $now=new DateTimeImmutable('now');$principal=new SessionPrincipal($id,1,'active',$now->format('Y-m-d H:i:s'));
    $session=new SessionRecord(SessionId::generate(),new SessionTokenDigest(hash('sha256',$id.random_bytes(8))),$principal,$now,$now,$now->modify('+3600 seconds'),$now->modify('+43200 seconds'));
    return new AuthenticatedAccessContext($principal,$session);
}
function check(bool $ok,string $name): void {if (!$ok) throw new RuntimeException('FAIL '.$name);echo $name."=PASS\n";}
function api(ProviderManagementApi $api,?AuthenticatedAccessContext $actor,string $method,array $path,array $query=[],array $body=[]): array {
    return $api->handle($method,$path,$query,$body,$actor)[1];
}
function deny(callable $fn,int $status,string $code,string $name): void {
    try {$fn();} catch (ProviderManagementHttpException $e) {check($e->status===$status&&$e->error===$code,$name);return;}
    throw new RuntimeException('FAIL '.$name.' allowed');
}
foreach (['owner','owner_c','reviewer','admin','collab','eligible','eligible2'] as $id)
    $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at)
        VALUES(?,?,?,'active',NOW())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
$p->exec("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at)
    VALUES('unverified','unverified@example.invalid','unverified@example.invalid','pending_verification',NULL)");
$p->exec("INSERT INTO internal_staff(account_id,governance_class,status) VALUES ('reviewer','ADVISOR','ACTIVE')");
$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES (UUID(),'reviewer','provider_claim_review','ACTIVE')");
foreach (['prov_a'=>'Provider A','prov_b'=>'Provider B','prov_c'=>'Provider C'] as $id=>$name)
    $p->prepare("INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source)
        VALUES(?,'LABORATORY',?,?,'pending','operator_created')")->execute([$id,mb_strtolower($name),$name]);
$claim=new HealthcareOrganizationClaimService($p);
foreach (['prov_a'=>'owner','prov_b'=>'owner','prov_c'=>'owner_c'] as $group=>$account) {
    $r=$claim->submitExisting(actor($account),$group,'prov04f-claim-'.$group);
    $claim->decide(actor('reviewer'),$r['claim_uuid'],'APPROVED','EVIDENCE_ACCEPTED');
}
$p->exec("INSERT INTO clinical_study_types(study_type_key,display_name_es,category_key,aliases_json,is_active) VALUES
    ('prov04f_bh','Biometría hemática','LABORATORIO','[\"BH\"]',1),
    ('prov04f_rx','Radiografía de tórax','IMAGEN','[\"RX tórax\"]',1),
    ('prov04f_old','Estudio retirado','LABORATORIO','[\"retirado\"]',0)");
$api=new ProviderManagementApi($p);$owner=actor('owner');$org=['organizations','prov_a'];
$my=api($api,$owner,'GET',['me','organizations'])['organizations'];
check(count($my)===2&&array_column($my,'group_id')===['prov_a','prov_b'],'QA_MULTI_ORGANIZATION_LIST');
check(!in_array('prov_c',array_column($my,'group_id'),true),'QA_UNRELATED_ORGANIZATION_EXCLUDED');
check(api($api,$owner,'GET',['study-types'],['search'=>'Biometría'])['items'][0]['study_type_key']==='prov04f_bh','QA_CATALOG_NAME_SEARCH');
check(api($api,$owner,'GET',['study-types'],['search'=>'BH'])['items'][0]['study_type_key']==='prov04f_bh','QA_CATALOG_ALIAS_SEARCH');
check(count(api($api,$owner,'GET',['study-types'],['category'=>'IMAGEN'])['items'])===1,'QA_CATALOG_CATEGORY');
check(count(api($api,$owner,'GET',['study-types'],['search'=>'retirado'])['items'])===0,'QA_CATALOG_INACTIVE');
check(count(api($api,$owner,'GET',[...$org,'study-types'])['items'])===2,'QA_GROUP_CATALOG_NO_ENTITLEMENT');
deny(fn()=>api($api,null,'GET',['study-types']),401,'UNAUTHENTICATED','QA_NO_SESSION');
deny(fn()=>api($api,actor('eligible'),'GET',['study-types']),403,'FORBIDDEN','QA_CATALOG_REQUIRES_PROVIDER_MEMBERSHIP');
deny(fn()=>api($api,$owner,'GET',['accounts']),404,'NOT_FOUND','QA_NO_ACCOUNT_DIRECTORY');
deny(fn()=>api($api,$owner,'POST',['study-types'],[],['study_type_key'=>'bad']),404,'NOT_FOUND','QA_CATALOG_WRITE_ABSENT');
$found=api($api,$owner,'POST',[...$org,'invitee-resolution'],[],['email'=>'Eligible@Example.Invalid']);
check($found===['state'=>'FOUND_ELIGIBLE'],'QA_INVITEE_FOUND_NO_RAW_ID');
check(api($api,$owner,'POST',[...$org,'invitee-resolution'],[],['email'=>'owner@example.invalid'])['state']==='SELF_INVITE','QA_INVITEE_SELF');
$adminInvite=api($api,$owner,'POST',[...$org,'invitations'],[],['invitee_account_id'=>'admin','role'=>'administrator','submission_key'=>'prov04f-admin-inv']);
api($api,actor('admin'),'POST',['invitations',$adminInvite['invitation_uuid'],'accept']);
$collabInvite=api($api,$owner,'POST',[...$org,'invitations'],[],['invitee_account_id'=>'collab','role'=>'collaborator','submission_key'=>'prov04f-collab-inv']);
api($api,actor('collab'),'POST',['invitations',$collabInvite['invitation_uuid'],'accept']);
check(api($api,$owner,'POST',[...$org,'invitee-resolution'],[],['email'=>'admin@example.invalid'])['state']==='ALREADY_MEMBER','QA_INVITEE_ALREADY_MEMBER');
$inv=api($api,$owner,'POST',[...$org,'invitations'],[],['invitee_email'=>'eligible@example.invalid','role'=>'collaborator','submission_key'=>'prov04f-email-01']);
check($inv['status']==='PENDING'&&!isset($inv['invitee_account_id']),'QA_EMAIL_INVITATION_CREATED_NO_ID');
check(count(api($api,actor('eligible'),'GET',['me','invitations'])['invitations'])===1
    && api($api,$owner,'GET',['me','invitations'])['invitations']===[],'QA_INVITEE_OWN_PENDING_DISCOVERY');
$replay=api($api,$owner,'POST',[...$org,'invitations'],[],['invitee_email'=>'eligible@example.invalid','role'=>'collaborator','submission_key'=>'prov04f-email-01']);
check($replay['invitation_uuid']===$inv['invitation_uuid'],'QA_EMAIL_INVITATION_IDEMPOTENT');
check(api($api,$owner,'POST',[...$org,'invitee-resolution'],[],['email'=>'eligible@example.invalid'])['state']==='PENDING_INVITATION','QA_INVITEE_PENDING');
$unknown=api($api,$owner,'POST',[...$org,'invitee-resolution'],[],['email'=>'unknown@example.invalid']);
$unverified=api($api,$owner,'POST',[...$org,'invitee-resolution'],[],['email'=>'unverified@example.invalid']);
check($unknown===['state'=>'NOT_FOUND_OR_NOT_INVITABLE'],'QA_INVITEE_UNKNOWN');
check($unverified===$unknown,'QA_INVITEE_UNVERIFIED_NEUTRAL');
deny(fn()=>api($api,$owner,'POST',[...$org,'invitations'],[],['invitee_email'=>'owner@example.invalid','role'=>'collaborator','submission_key'=>'prov04f-self']),409,'SELF_INVITE','QA_SELF_INVITE_DENY');
deny(fn()=>api($api,$owner,'POST',[...$org,'invitations'],[],['invitee_email'=>'admin@example.invalid','role'=>'collaborator','submission_key'=>'prov04f-member']),409,'ALREADY_MEMBER','QA_MEMBER_CONFLICT');
deny(fn()=>api($api,$owner,'POST',[...$org,'invitations'],[],['invitee_email'=>'unknown@example.invalid','role'=>'collaborator','submission_key'=>'prov04f-unknown']),422,'NOT_FOUND_OR_NOT_INVITABLE','QA_UNKNOWN_ACCOUNT_DENY');
deny(fn()=>api($api,$owner,'POST',[...$org,'invitations'],[],['invitee_email'=>'unverified@example.invalid','role'=>'collaborator','submission_key'=>'prov04f-unverified']),422,'NOT_FOUND_OR_NOT_INVITABLE','QA_UNVERIFIED_ACCOUNT_DENY');
deny(fn()=>api($api,actor('admin'),'POST',[...$org,'invitee-resolution'],[],['email'=>'eligible2@example.invalid']),403,'FORBIDDEN','QA_ADMIN_LOOKUP_DENY');
deny(fn()=>api($api,actor('collab'),'POST',[...$org,'invitee-resolution'],[],['email'=>'eligible2@example.invalid']),403,'FORBIDDEN','QA_COLLAB_LOOKUP_DENY');
deny(fn()=>api($api,$owner,'POST',['organizations','prov_c','invitee-resolution'],[],['email'=>'eligible2@example.invalid']),404,'NOT_FOUND','QA_CROSS_ORG_LOOKUP_DENY');
check(count(api($api,actor('collab'),'GET',['me','organizations'])['organizations'])===1,'QA_COLLAB_ORGANIZATION_LIST');
check(count(api($api,actor('admin'),'GET',['study-types'],['search'=>'BH'])['items'])===1,'QA_ADMIN_CATALOG');
check(count(api($api,actor('collab'),'GET',['study-types'],['search'=>'BH'])['items'])===1,'QA_COLLAB_CATALOG');
check(!isset(api($api,$owner,'GET',[...$org,'invitations'])['invitations'][0]['invitee_account_id'])
    && !isset(api($api,$owner,'GET',[...$org,'team'])['members'][0]['account_id']),'QA_NO_RAW_IDS_IN_TEAM_UI');
for ($i=0;$i<20;$i++) api($api,$owner,'POST',['organizations','prov_b','invitee-resolution'],[],['email'=>'unknown@example.invalid']);
try {api($api,$owner,'POST',['organizations','prov_b','invitee-resolution'],[],['email'=>'unknown@example.invalid']);throw new RuntimeException('rate limit bypassed');}
catch (HealthcareOrganizationTeamException $e) {check($e->reason==='invitee_resolution_rate_limited','QA_EXACT_LOOKUP_RATE_LIMIT');}
check((int)$p->query('SELECT COUNT(*) FROM healthcare_organization_invitee_resolution_attempts')->fetchColumn()>=20,'QA_LOOKUP_AUDIT_COUNT');
$p->prepare("INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version)
    VALUES('owner','disposable-qa-only',NOW(),1)")->execute();
$pepper=(string)getenv('PROV04F_HTTP_PEPPER');if (strlen($pepper)<32) throw new RuntimeException('disposable_http_pepper_required');
$token=SessionToken::generate();$digest=(new SessionTokenCodec($pepper))->digest($token);
$now=new DateTimeImmutable('now');$principal=new SessionPrincipal('owner',1,'active',$now->format('Y-m-d H:i:s'));
$record=new SessionRecord(SessionId::generate(),$digest,$principal,$now,$now,$now->modify('+3600 seconds'),$now->modify('+43200 seconds'));
file_put_contents((string)getenv('PROV04F_HTTP_FIXTURE'),json_encode(['token'=>(string)$token,
    'csrf'=>(new CsrfTokenService($pepper,900,new \Identity\Contracts\SystemClock(),'https://127.0.0.1:8140'))->issueAuthenticated($digest),
    'session_key'=>'mxmed:gate4d:preview:session:'.(string)$digest,
    'session_json'=>json_encode($record->toArray(),JSON_THROW_ON_ERROR)],JSON_THROW_ON_ERROR));
echo "PROV04F_PREP_DISPOSABLE_GATE=PASS\n";
