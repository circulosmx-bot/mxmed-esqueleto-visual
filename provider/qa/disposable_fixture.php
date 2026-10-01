<?php
declare(strict_types=1);
// Runs only against the disposable PROV04F database created by the visual QA shell.
$database = (string)getenv('PROV04F_QA_DB');
if (PHP_SAPI !== 'cli' || !preg_match('/^mxmed_gate4d_preview_prov04f_[0-9a-f]{12}$/D', $database)) exit(1);
require_once __DIR__.'/../../modules/identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../modules/subscriptions/services/ProviderOrganizationSubscriptionService.php';
require_once __DIR__.'/../../modules/agenda/services/HealthcareOrganizationDirectoryService.php';
require_once __DIR__.'/../../modules/agenda/services/HealthcareProviderCoverageService.php';

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\SessionId;
use Identity\Contracts\SessionPrincipal;
use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionTokenDigest;
use Identity\Contracts\SessionToken;
use Identity\Services\SessionTokenCodec;
use Subscriptions\Services\ProviderOrganizationSubscriptionService;
use Agenda\Services\HealthcareOrganizationDirectoryService;
use Agenda\Services\HealthcareProviderCoverageService;

$pdo = new PDO('mysql:host=localhost;dbname='.$database.';charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
function fixtureActor(string $id): AuthenticatedAccessContext {
    $now = new DateTimeImmutable('now');
    $principal = new SessionPrincipal($id,1,'active',$now->format('Y-m-d H:i:s'));
    $session = new SessionRecord(SessionId::generate(),new SessionTokenDigest(hash('sha256',$id.random_bytes(8))),
        $principal,$now,$now,$now->modify('+3600 seconds'),$now->modify('+43200 seconds'));
    return new AuthenticatedAccessContext($principal,$session);
}
$pdo->exec("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at)
    VALUES('operator','operator@example.invalid','operator@example.invalid','active',NOW())");
$pdo->exec("INSERT INTO internal_staff(account_id,governance_class,status) VALUES('operator','ADVISOR','ACTIVE')");
$pdo->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status)
    VALUES(UUID(),'operator','provider_subscription_activate','ACTIVE')");
$pdo->exec("INSERT INTO subscription_plans(plan_code,plan_label,billing_period,duration_days,product_family,source)
    VALUES('prov04f_visual','Proveedor · Revisión', 'annual',30,'PROVIDER_ORGANIZATION','disposable_qa')");
foreach (['provider_profile_manage','provider_locations_manage','provider_offerings_manage','provider_service_areas_manage'] as $capability) {
    $pdo->prepare("INSERT INTO provider_subscription_plan_capabilities(plan_code,billing_period,capability)
        VALUES('prov04f_visual','annual',?)")->execute([$capability]);
}
(new ProviderOrganizationSubscriptionService($pdo))->activateInternal(fixtureActor('operator'),'prov_a','prov04f_visual','annual');
$pdo->exec("INSERT INTO healthcare_organization_provider_status(group_id,operational_state,verification_state)
    VALUES('prov_a','ACTIVE','UNVERIFIED'),('prov_b','INACTIVE','UNVERIFIED')");
$directory = new HealthcareOrganizationDirectoryService($pdo);
$coverage = new HealthcareProviderCoverageService($pdo);
$branches = [
    ['branch_name'=>'Sucursal Centro','street'=>'Av. Reforma','exterior_number'=>'120','postal_code'=>'01000',
        'colonia'=>'Florida','municipality'=>'Álvaro Obregón','state_name'=>'Ciudad de México','phone'=>'55 5555 0101'],
    ['branch_name'=>'Sucursal Norte','street'=>'Calle Norte','exterior_number'=>'48','postal_code'=>'01000',
        'colonia'=>'Florida','municipality'=>'Álvaro Obregón','state_name'=>'Ciudad de México','phone'=>'55 5555 0202'],
    ['branch_name'=>'Sucursal Sur','street'=>'Av. del Sur','exterior_number'=>'91','postal_code'=>'01000',
        'colonia'=>'Florida','municipality'=>'Álvaro Obregón','state_name'=>'Ciudad de México'],
];
$locations=[];
foreach ($branches as $branch) $locations[]=$directory->createLocation('prov_a',$branch);
$directory->setLocationVerification('prov_a',$locations[0]['location_uuid'],'VERIFIED','reviewer');
$study=(int)$pdo->query("SELECT study_type_id FROM clinical_study_types WHERE study_type_key='prov04f_bh'")->fetchColumn();
$image=(int)$pdo->query("SELECT study_type_id FROM clinical_study_types WHERE study_type_key='prov04f_rx'")->fetchColumn();
$directory->createOffering('prov_a',$locations[0]['location_uuid'],$study,[
    'service_mode'=>'HOME_SERVICE','requires_appointment'=>true,'preparation_instructions'=>'Presentarse en ayuno de 8 horas.',
]);
$directory->createOffering('prov_a',$locations[0]['location_uuid'],$image,['service_mode'=>'ON_SITE']);
$directory->setOfferingVerification('prov_a',$locations[0]['location_uuid'],$study,'VERIFIED','reviewer');
$coverage->createServiceArea('prov_a',$locations[0]['location_uuid'],$study,'POSTAL_CODE','01000');
if ((string)getenv('PROV04F_HTTP_EXTRA_FIXTURE') !== '') {
    $pepper=(string)getenv('PROV04F_HTTP_PEPPER');
    if (strlen($pepper)<32) throw new RuntimeException('disposable_http_pepper_required');
    $sessions=[];
    foreach (['admin','collab','eligible'] as $account) {
        $pdo->prepare("INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version)
            VALUES(?,'disposable-qa-only',NOW(),1)")->execute([$account]);
        $token=SessionToken::generate();
        $digest=(new SessionTokenCodec($pepper))->digest($token);
        $now=new DateTimeImmutable('now');
        $principal=new SessionPrincipal($account,1,'active',$now->format('Y-m-d H:i:s'));
        $record=new SessionRecord(SessionId::generate(),$digest,$principal,$now,$now,$now->modify('+3600 seconds'),$now->modify('+43200 seconds'));
        $sessions[$account]=['token'=>(string)$token,'session_key'=>'mxmed:gate4d:preview:session:'.(string)$digest,
            'session_json'=>json_encode($record->toArray(),JSON_THROW_ON_ERROR)];
    }
    file_put_contents((string)getenv('PROV04F_HTTP_EXTRA_FIXTURE'),json_encode($sessions,JSON_THROW_ON_ERROR));
}
echo "PROV04F_VISUAL_FIXTURE=READY\n";
