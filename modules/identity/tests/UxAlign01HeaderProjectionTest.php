<?php
declare(strict_types=1);

/** Native header projection acceptance on private SQLite memory and an in-memory session store.
 * No environment composition, network, MySQL, Valkey or real cookie is read.
 * This is a resolver/component contract, not a full normal-local HTTP login test.
 */
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__.'/../http/PrivateApplicationAccessResolver.php';

use Identity\Adapters\{ExistingCapabilityAuthorityAdapter,InMemorySessionStoreAdapter,PdoSessionAccountStateAdapter};
use Identity\Contracts\{AuthenticationPrincipalCandidate,SystemClock};
use Identity\Http\{IdentityHttpComposition,PrivateApplicationAccessResolver};
use Identity\Repositories\{AccountCredentialRepository,AccountMembershipRepository,IdentityAccountRepository};
use Identity\Services\{FailClosedAuthorizationService,SessionService,SessionTokenCodec};
use Subscriptions\Services\ExistingCapabilityAuthorityService;

IdentityHttpComposition::registerAutoloader();
$checks=[];
function headerCheck(string $name,bool $condition):void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL '.$name);
    $checks[$name]='PASS';
}

$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE auth_accounts(account_id TEXT PRIMARY KEY,email_address TEXT,email_normalized TEXT,status TEXT,email_verified_at TEXT)');
$pdo->exec('CREATE TABLE auth_account_credentials(account_id TEXT PRIMARY KEY,password_hash TEXT,password_changed_at TEXT,credential_version INTEGER)');
$pdo->exec('CREATE TABLE profiles_doctors(doctor_id TEXT PRIMARY KEY,display_name TEXT,professional_designation TEXT)');
$pdo->exec('CREATE TABLE auth_account_memberships(membership_id TEXT PRIMARY KEY,account_id TEXT,profile_doctor_id TEXT,entity_group_id TEXT,role_code TEXT,scope_code TEXT,status TEXT,assignment_source TEXT)');
$pdo->exec("INSERT INTO auth_accounts VALUES('uxalign_person','synthetic@example.invalid','synthetic@example.invalid','active','2026-10-16 00:00:00')");
$pdo->exec("INSERT INTO auth_account_credentials VALUES('uxalign_person','unused-synthetic-hash','2026-10-16 00:00:00',1)");
$pdo->exec("INSERT INTO auth_account_memberships VALUES('uxalign_member','uxalign_person','uxalign_profile',NULL,'collaborator','profile','active','explicit_synthetic_test')");
$store=new InMemorySessionStoreAdapter();
$sessions=new SessionService($store,new SessionTokenCodec(bin2hex(random_bytes(32))),new SystemClock(),accounts:new PdoSessionAccountStateAdapter(new IdentityAccountRepository($pdo),new AccountCredentialRepository($pdo)),touchActivity:false);
$created=$sessions->create(new AuthenticationPrincipalCandidate('uxalign_person',1,'active',gmdate('Y-m-d H:i:s')));
headerCheck('canonical_synthetic_session_created',$created->allowed());
$token=$created->token()->value();
$memberships=new AccountMembershipRepository($pdo);
$identity=(new ReflectionClass(IdentityHttpComposition::class))->newInstanceWithoutConstructor();
foreach (['pdo'=>$pdo,'sessions'=>$sessions,'memberships'=>$memberships,'authorization'=>new FailClosedAuthorizationService($memberships,new ExistingCapabilityAuthorityAdapter(new ExistingCapabilityAuthorityService()))] as $field=>$value)
    (new ReflectionProperty($identity,$field))->setValue($identity,$value);
$resolver=new PrivateApplicationAccessResolver($identity);
$cookies=['__Host-mxmed_session'=>$token];
$contexts=[];
$cases=[
    'endocrinology'=>['Dra. Persona Sintética','Endocrinóloga'],
    'dentistry'=>['Perfil Sintético Dental','Cirujano dentista'],
    'neutral'=>['Perfil Sintético Neutral','Profesional de salud'],
    'blank'=>['Perfil Sintético Sin Denominación','   '],
    'null'=>['Otro Perfil Sintético',null],
];
foreach ($cases as $name=>[$display,$designation]) {
    $pdo->prepare('INSERT OR REPLACE INTO profiles_doctors VALUES(?,?,?)')->execute(['uxalign_profile',$display,$designation]);
    $before=(int)$pdo->query('SELECT total_changes()')->fetchColumn();
    $result=$resolver->resolve($cookies);
    headerCheck($name.'_allowed',$result->allowed&&$result->httpStatus===200);
    $context=$result->context;
    headerCheck($name.'_stored_profile_name',$context['professional']['display_name']===$display);
    headerCheck($name.'_stored_designation',array_key_exists('professional_designation',$context['professional'])&&$context['professional']['professional_designation']===trim((string)$designation));
    headerCheck($name.'_collaborator_unchanged',$context['professional']['role']==='collaborator'&&$context['professional']['scope']==='profile');
    headerCheck($name.'_missing_capabilities_fail_closed',$context['capabilities']===['patients'=>false,'clinical_record'=>false,'prescriptions'=>false]);
    headerCheck($name.'_resolver_read_only',(int)$pdo->query('SELECT total_changes()')->fetchColumn()===$before);
    $contexts[$name]=$context;
}
// Another stored profile cannot be selected by display name, gender, specialty or browser state.
$pdo->prepare('INSERT INTO profiles_doctors VALUES(?,?,?)')->execute(['uxalign_other','Otro perfil independiente','Pediatra']);
$pdo->exec("UPDATE auth_account_memberships SET profile_doctor_id='uxalign_other' WHERE membership_id='uxalign_member'");
$specific=$resolver->resolve($cookies);
headerCheck('exact_membership_profile_specific',$specific->allowed&&$specific->context['professional']['doctor_id']==='uxalign_other'&&$specific->context['professional']['professional_designation']==='Pediatra');
$contexts['other_profile']=$specific->context;
headerCheck('anonymous_denied',$resolver->resolve([])->httpStatus===401);
$pdo->exec("UPDATE auth_account_memberships SET status='revoked' WHERE membership_id='uxalign_member'");
headerCheck('revoked_membership_denied',$resolver->resolve($cookies)->httpStatus===403);
$pdo->exec("UPDATE auth_account_memberships SET status='active',assignment_source='owner_operator_invitation' WHERE membership_id='uxalign_member'");
headerCheck('nonmedical_operator_denied_physician_shell',$resolver->resolve($cookies)->httpStatus===403);
headerCheck('operator_denied_clinical_capability',!$identity->authorization()->authorize($sessions->validate($token)->context(),'profile_doctor','uxalign_other','prescriptions',['plan_code'=>'professional','is_active'=>true])->allowed());
// Exercise the existing bounded operator DTO without constructing a writer or service.
$operatorState=['account_id'=>'uxalign_person','alias'=>'Mary','owner'=>false,'profile'=>['id'=>'uxalign_other','name'=>'Otro perfil independiente'],
    'capabilities'=>['agenda.appointments.read'=>true,'agenda.appointments.create'=>false,'agenda.appointments.reschedule'=>false,'agenda.appointments.cancel'=>false,'agenda.availability.read'=>false],
    'grant_revision'=>1,'inbox'=>[],'offices'=>[],'booking_subjects'=>[],'presentation_ready'=>false];
$operator=(new ReflectionClass(Identity\Operators\OperatorHttp::class))->newInstanceWithoutConstructor();
$operator->identity=$identity;
(new ReflectionProperty($operator,'markerKey'))->setValue($operator,bin2hex(random_bytes(32)));
$_COOKIE=$cookies;
$operatorContext=$operator->privateContext($operatorState);
$_COOKIE=[];
headerCheck('operator_dto_separates_account_from_profile',$operatorContext['workspace']==='agenda_operators'&&$operatorContext['account']['display_name']==='Mary'&&$operatorContext['professional']['display_name']==='Otro perfil independiente');
headerCheck('operator_dto_not_physician',$operatorContext['professional']['role']==='operator'&&$operatorContext['capabilities']===[]&&!isset($operatorContext['professional']['professional_designation']));
$contexts['operator']=$operatorContext;
$pdo->exec("UPDATE auth_account_memberships SET status='active',assignment_source='explicit_synthetic_test' WHERE membership_id='uxalign_member'");
$pdo->exec("INSERT INTO auth_account_memberships VALUES('uxalign_ambiguous','uxalign_person','uxalign_profile',NULL,'collaborator','profile','active','explicit_synthetic_test')");
headerCheck('ambiguous_memberships_denied',$resolver->resolve($cookies)->httpStatus===403);
headerCheck('no_owner_created',(int)$pdo->query("SELECT COUNT(*) FROM auth_account_memberships WHERE role_code='owner'")->fetchColumn()===0);
$pdo->exec("UPDATE auth_accounts SET status='disabled' WHERE account_id='uxalign_person'");
headerCheck('disabled_account_denied',$resolver->resolve($cookies)->httpStatus===401);
headerCheck('memory_database_only',$pdo->query('PRAGMA database_list')->fetch()['file']==='');

if (($argv[1]??'')==='--json') echo json_encode(['result'=>'PASS','checks'=>$checks,'contexts'=>$contexts],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
else {foreach ($checks as $name=>$result)echo 'HEADER_'.$name.'='.$result.PHP_EOL;echo 'UX_ALIGN01_NATIVE_HEADER=PASS'.PHP_EOL;}
