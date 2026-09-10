<?php
declare(strict_types=1);
// CLI-only synthetic canonical identity fixture in a disposable database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../../../api/_lib/db.php';
require __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Http\IdentityHttpComposition;
use Identity\Contracts\{PasswordHash,Clock,SessionPolicy};
use Identity\Services\{SessionService,SessionStoreFactory,SessionTokenCodec};
use Identity\Adapters\{PreviewValkeyClient,PdoSessionAccountStateAdapter};
$db=(string)getenv('MR3_TEST_DB');
if(!preg_match('/^mxmed_gate4d_preview_mr3_[a-z0-9_]+$/D',$db))throw new RuntimeException('isolated_database_required');
$c=mxmed_load_db_config()['mysql'];
foreach(['host','port','user','pass'] as $key){$value=getenv('MR3_TEST_DB_'.strtoupper($key));if($value!==false)$c[$key]=$value;}

$admin=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';charset=utf8mb4',$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$action=$argv[1]??'';
if($action==='cleanup'){$admin->exec("DROP DATABASE IF EXISTS `$db`");exit;}
if($action==='setup'){
 $admin->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
 $admin->exec("USE `$db`");
 foreach(['2026_07_19_01_create_auth_accounts.sql','2026_07_20_04_create_auth_account_credentials.sql','2026_07_20_06_create_auth_rate_limit_buckets.sql','2026_09_08_07_create_internal_operator_grants.sql'] as $migration)$admin->exec(file_get_contents(__DIR__.'/../db/migrations/'.$migration));
 if((int)$admin->query('SELECT COUNT(*) FROM internal_operator_grants')->fetchColumn()!==0)throw new RuntimeException('unexpected_default_grants');
 $admin->exec(file_get_contents(__DIR__.'/../db/migrations/2026_09_08_07_rollback_internal_operator_grants.sql'));
 $admin->exec(file_get_contents(__DIR__.'/../db/migrations/2026_09_08_07_create_internal_operator_grants.sql'));
}
$env=['APP_ENV'=>'local','MXMED_ENVIRONMENT'=>'local','MXMED_PREVIEW_EXPLICIT'=>'1',
 'MXMED_PREVIEW_PEPPER'=>'mr3-synthetic-session-pepper-'.hash('sha256',$db),
 'MXMED_DB_HOST'=>$c['host'],'MXMED_DB_PORT'=>(string)$c['port'],'MXMED_DB_NAME'=>$db,'MXMED_DB_USER'=>$c['user'],'MXMED_DB_PASS'=>$c['pass'],
 'MXMED_SESSION_STORE_PORT'=>(string)(getenv('MR3_VALKEY_PORT')?:6387)];
foreach($env as $key=>$value)putenv($key.'='.$value);
$composition=IdentityHttpComposition::fromProcessEnvironment();$p=$composition->pdo();
if($action==='revoke'){$p->exec("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='mr3_good' AND status='ACTIVE'");exit;}
if($action==='regrant'){$p->exec("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_good','media_review_read','ACTIVE')");exit;}
if($action!=='setup')throw new RuntimeException('unknown_fixture_action');
$tokens=[];$password='Synthetic-MR3-only-'.bin2hex(random_bytes(16));$hash=PasswordHash::hash($password);
foreach(['good','customer','wrong','revoked_grant','inactive','blocked','expired','revoked_session','superseded','credential'] as $kind){
 $account='mr3_'.$kind;$email=$account.'@example.invalid';
 $s=$p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',CURRENT_TIMESTAMP)");$s->execute([$account,$email,$email]);
 $composition->credentials()->create($account,$hash,date('Y-m-d H:i:s'));
 if($kind!=='customer'){$s=$p->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),?,?,'ACTIVE')");$s->execute([$account,$kind==='wrong'?'unrelated_read':'media_review_read']);}
 $auth=$composition->authentication()->authenticate($email,$password,[]);
 if(!$auth->isAllowed())throw new RuntimeException('canonical_authentication_failed');
 $sessions=$composition->sessions();
 if($kind==='expired'){
  $clock=new class implements Clock{public function now():DateTimeImmutable{return new DateTimeImmutable('-3700 seconds');}};
  $store=SessionStoreFactory::create('local',['driver'=>'valkey','prefix'=>'mxmed:gate4d:preview:session:','explicit_preview_flag'=>true],new PreviewValkeyClient('127.0.0.1',(int)$env['MXMED_SESSION_STORE_PORT']),$clock);
  $sessions=new SessionService($store,new SessionTokenCodec($env['MXMED_PREVIEW_PEPPER']),$clock,new SessionPolicy(),new PdoSessionAccountStateAdapter($composition->accounts(),$composition->credentials()));
 }
 $created=$sessions->create($auth->candidate());if(!$created->allowed())throw new RuntimeException('canonical_session_creation_failed');
 $tokens[$kind]=$created->token()->value();
 if($kind==='revoked_grant')$p->exec("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='$account'");
 if($kind==='inactive'||$kind==='blocked')$p->exec("UPDATE auth_accounts SET status='".($kind==='blocked'?'blocked':'disabled')."' WHERE account_id='$account'");
 if($kind==='credential')$p->exec("UPDATE auth_account_credentials SET credential_version=2 WHERE account_id='$account'");
 if($kind==='revoked_session')$sessions->logout($tokens[$kind]);
 if($kind==='superseded')$sessions->rotate($tokens[$kind]);
}
foreach(["INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_good','media_review_read','ACTIVE')", "INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'missing_account','media_review_read','ACTIVE')", "INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'mr3_good','*','ACTIVE')"] as $sql){
 try{$p->exec($sql);}catch(PDOException){continue;}throw new RuntimeException('grant_constraint_failed');
}
$p->exec(file_get_contents(__DIR__.'/../db/migrations/2026_09_10_01_internal_governance.sql'));
// Output is consumed privately by the Node test process, never logged.
echo json_encode(['env'=>$env,'tokens'=>$tokens],JSON_THROW_ON_ERROR);
