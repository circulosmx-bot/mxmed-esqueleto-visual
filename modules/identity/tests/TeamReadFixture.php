<?php
declare(strict_types=1);
// CLI-only: new MySQL instance/datadir and synthetic identities. Never an application entry point.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Http\IdentityHttpComposition;
use Identity\Services\{InternalCapabilityCatalog,CredentialAuthenticationService,RateLimitService,SessionService,SessionStoreFactory,SessionTokenCodec};
use Identity\Repositories\{IdentityAccountRepository,AccountCredentialRepository};
use Identity\Contracts\{PasswordHash,RateLimitKeyHasher,SystemClock,Clock,SessionPolicy};
use Identity\Adapters\{PreviewValkeyClient,PdoSessionAccountStateAdapter};

$config=json_decode(file_get_contents((string)getenv('ASE_INT01_CONFIGURATION')),true,512,JSON_THROW_ON_ERROR);$env=$config['env'];$owned=$config['owned'];
if(!preg_match('~^/tmp/mxmed-ase-int01-[A-Za-z0-9_-]+$~D',$owned)||$config['socket']!==$owned.'/mysql.sock'||!preg_match('/^mxmed_gate4d_preview_aseint01_[a-f0-9]{10}$/D',$env['MXMED_DB_NAME'])||in_array((int)$env['MXMED_DB_PORT'],[3306,3309],true))throw new RuntimeException('owned_instance_required');
$admin=new PDO('mysql:unix_socket='.$config['socket'].';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(realpath((string)$admin->query('SELECT @@datadir')->fetchColumn())!==realpath($owned.'/mysql'))throw new RuntimeException('owned_datadir_required');
foreach($env as $key=>$value)putenv($key.'='.$value);
$database=$env['MXMED_DB_NAME'];$action=$argv[1]??'';
if($action==='setup'){
    $admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$admin->exec("USE `$database`");
    foreach(['2026_07_19_01_create_auth_accounts.sql','2026_07_20_04_create_auth_account_credentials.sql','2026_07_20_06_create_auth_rate_limit_buckets.sql','2026_09_08_07_create_internal_operator_grants.sql','2026_09_10_01_internal_governance.sql'] as $file)$admin->exec(file_get_contents(__DIR__.'/../db/migrations/'.$file));
    $password='Synthetic-only-'.bin2hex(random_bytes(16));file_put_contents($owned.'/fixture-password',$password);$hash=PasswordHash::hash($password);
    $account=function(string $id,?string $class='ADVISOR',string $status='ACTIVE')use($admin,$hash):void{
        $q=$admin->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',CURRENT_TIMESTAMP)");$q->execute([$id,$id.'@example.test',$id.'@example.test']);(new AccountCredentialRepository($admin))->create($id,$hash,date('Y-m-d H:i:s'));
        if($class!==null){$q=$admin->prepare('INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,?,?)');$q->execute([$id,$class,$status]);}
    };
    $grant=function(string $id,string $key)use($admin):void{$q=$admin->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),?,?,'ACTIVE')");$q->execute([$id,$key]);};
    $account('qa_director','DIRECTOR');
    foreach(['qa_master','qa_no_management_master','qa_other_master','qa_expired','qa_revoked_session'] as $id){$account($id,'MASTER_ADMIN');$grant($id,InternalCapabilityCatalog::MANAGE_ADVISORS);}
    $account('qa_suspended_actor','MASTER_ADMIN','SUSPENDED');$grant('qa_suspended_actor',InternalCapabilityCatalog::MANAGE_ADVISORS);
    foreach(['qa_advisor','qa_media_only','qa_disabled','qa_credential_changed'] as $id){$account($id);$grant($id,'media_review_read');}
    $account('qa_customer',null);
    for($i=0;$i<52;$i++){$id='qa_advisor_'.sprintf('%02d',$i);$account($id,'ADVISOR',$i===3?'SUSPENDED':'ACTIVE');if($i%3===0)$grant($id,'media_review_read');}
    foreach(['media_review_approve','professional_source_provision','profile_create','unregistered_read'] as $key)$grant('qa_advisor_00',$key);
    foreach(InternalCapabilityCatalog::all() as $key=>$definition)if($definition['kind']==='operational')$grant('qa_advisor_02',$key);
    $grant('qa_other_master','public_profile_correction_apply');
    foreach(['media_review_read','media_review_approve','physician_accreditation_review'] as $key){$q=$admin->prepare("INSERT INTO internal_capability_delegations(delegation_id,master_account_id,capability,authorized_by_account_id,status) VALUES(UUID(),'qa_master',?,'qa_director','ACTIVE')");$q->execute([$key]);}
    $admin->exec("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='qa_no_management_master'");
    $user=$admin->quote($env['MXMED_DB_USER']);$pass=$admin->quote($env['MXMED_DB_PASS']);$admin->exec("CREATE USER $user@'127.0.0.1' IDENTIFIED BY $pass");
    foreach(['auth_accounts','auth_account_credentials','internal_staff','internal_operator_grants','internal_capability_delegations'] as $table)$admin->exec("GRANT SELECT ON `$database`.`$table` TO $user@'127.0.0.1'");
}else $admin->exec("USE `$database`");

$commands=[
 'deny_reader'=>"REVOKE SELECT ON `$database`.internal_staff FROM 'ase_reader'@'127.0.0.1'",
 'restore_reader'=>"GRANT SELECT ON `$database`.internal_staff TO 'ase_reader'@'127.0.0.1'",
 'revoke_manager'=>"UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='qa_master' AND capability='internal_advisors_manage'",
 'restore_manager'=>"UPDATE internal_operator_grants SET status='ACTIVE',revoked_at=NULL WHERE account_id='qa_master' AND capability='internal_advisors_manage'",
 'revoke_grant'=>"UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='qa_advisor_00' AND capability='media_review_read'",
 'restore_grant'=>"UPDATE internal_operator_grants SET status='ACTIVE',revoked_at=NULL WHERE account_id='qa_advisor_00' AND capability='media_review_read'",
 'revoke_delegation'=>"UPDATE internal_capability_delegations SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE master_account_id='qa_master' AND capability='media_review_read'",
 'restore_delegation'=>"UPDATE internal_capability_delegations SET status='ACTIVE',revoked_at=NULL WHERE master_account_id='qa_master' AND capability='media_review_read'",
];
if(isset($commands[$action])){$admin->exec($commands[$action]);echo '{"fixture_only":true}';exit;}
if($action==='counts'){
    $result=[];foreach(['auth_accounts','auth_account_credentials','internal_staff','internal_operator_grants','internal_capability_delegations'] as $table){$rows=$admin->query("SELECT * FROM `$table` ORDER BY 1")->fetchAll(PDO::FETCH_ASSOC);$result[$table]=['count'=>count($rows),'digest'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))];}echo json_encode($result,JSON_THROW_ON_ERROR);exit;
}
if($action==='readonly_proof'){
    $reader=IdentityHttpComposition::fromProcessEnvironment()->pdo();
    try{$reader->exec("UPDATE internal_staff SET status='ACTIVE' WHERE account_id='never_a_fixture'");}
    catch(PDOException $e){echo json_encode(['runtime_sql_write_denied'=>str_starts_with((string)$e->getCode(),'42')]);exit;}
    throw new RuntimeException('runtime_principal_has_unexpected_write_access');
}
if(!in_array($action,['setup','review_token'],true))throw new RuntimeException('unknown_fixture_action');
$identity=IdentityHttpComposition::fromProcessEnvironment();$clock=new SystemClock();
$auth=new CredentialAuthenticationService(new IdentityAccountRepository($admin),new AccountCredentialRepository($admin),new RateLimitService($admin,new RateLimitKeyHasher($env['MXMED_PREVIEW_PEPPER']),$clock),$clock);
$tokens=[];$actors=['qa_director','qa_master','qa_no_management_master','qa_other_master','qa_suspended_actor','qa_advisor','qa_media_only','qa_disabled','qa_credential_changed','qa_customer','qa_expired','qa_revoked_session'];
if($action==='review_token')$actors=['qa_director'];
foreach($actors as $id){
    $decision=$auth->authenticate($id.'@example.test',trim(file_get_contents($owned.'/fixture-password')),[]);if(!$decision->isAllowed())throw new RuntimeException('synthetic_canonical_authentication_failed');
    $sessions=$identity->sessions();
    if($id==='qa_expired'){$past=new class implements Clock{public function now():DateTimeImmutable{return new DateTimeImmutable('-3700 seconds');}};$store=SessionStoreFactory::create('local',['driver'=>'valkey','prefix'=>'mxmed:gate4d:preview:session:','explicit_preview_flag'=>true],new PreviewValkeyClient('127.0.0.1',(int)$env['MXMED_SESSION_STORE_PORT']),$past);$sessions=new SessionService($store,new SessionTokenCodec($env['MXMED_PREVIEW_PEPPER']),$past,new SessionPolicy(),new PdoSessionAccountStateAdapter($identity->accounts(),$identity->credentials()));}
    $created=$sessions->create($decision->candidate());if(!$created->allowed())throw new RuntimeException('synthetic_session_failed');$tokens[$id]=$created->token()->value();
    if($id==='qa_revoked_session')$sessions->logout($tokens[$id]);
}
if($action==='setup'){$admin->exec("UPDATE auth_accounts SET status='disabled' WHERE account_id='qa_disabled'");$admin->exec("UPDATE auth_account_credentials SET credential_version=2 WHERE account_id='qa_credential_changed'");}
echo json_encode(['tokens'=>$tokens],JSON_THROW_ON_ERROR); // Captured only in the private owned directory, never evidence/Git.
