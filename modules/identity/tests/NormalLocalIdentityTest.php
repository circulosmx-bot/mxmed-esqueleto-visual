<?php
declare(strict_types=1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
require_once __DIR__.'/../http/PrivateApplicationAccessResolver.php';
use Identity\Http\IdentityHttpComposition as Composition;
use Identity\Http\IdentityHttpCompositionSelector as Selector;
use Identity\Http\NormalLocalIdentityConfiguration as Config;
use Identity\Http\NormalLocalIdentitySchema as Schema;
use Identity\Adapters\NormalLocalValkeyClient as Client;
use Identity\Adapters\PreviewValkeyClient;
use Identity\Adapters\ValkeySessionStoreAdapter;
use Identity\Services\SessionStoreFactory as Factory;
use Identity\Contracts\PasswordHash;
use Identity\Http\PrivateApplicationAccessResolver;
Composition::registerAutoloader();

function check(string $name, bool $ok): void { if (!$ok) throw new RuntimeException('FAIL:'.$name); echo $name."=PASS\n"; }
function rejects(callable $fn, ?string $reason=null): bool {
    try { $fn(); return false; } catch (Throwable $e) { return $reason===null || $e->getMessage()===$reason; }
}
function envSet(array $values): void { foreach ($values as $k=>$v) putenv($k.'='.$v); }
$port=(int)getenv('IDLOCAL_QA_PORT'); $dir=(string)getenv('IDLOCAL_QA_DIR');
if ($port<1024 || !str_starts_with($dir,'/tmp/mxmed-idlocal03a-') || !is_dir($dir)) throw new RuntimeException('isolated_runner_required');
$v=['APP_ENV'=>'local','MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT'=>'1','MXMED_DB_NAME'=>'mxmed','MXMED_DB_USER'=>'root',
    'MXMED_NORMAL_LOCAL_VALKEY_PORT'=>(string)$port,'MXMED_NORMAL_LOCAL_IDENTITY_PEPPER'=>bin2hex(random_bytes(32)),
    'MXMED_NORMAL_LOCAL_IDENTITY_ORIGIN'=>'https://localhost:8443','MXMED_NORMAL_LOCAL_NOTIFICATION_FILE'=>$dir.'/notifications.json'];
$config=Config::fromValues($v);
if (($argv[1] ?? '') === 'concurrent-worker') {
    $workerClient=new Client('127.0.0.1',$port);
    $workerStore=Factory::create('local',['driver'=>'valkey','prefix'=>'mxmed:local:session:','explicit_normal_local_flag'=>true],$workerClient);
    $clock=new \Identity\Contracts\SystemClock();
    $service=new \Identity\Services\SessionService($workerStore,
        new \Identity\Services\SessionTokenCodec((string)getenv('IDLOCAL_QA_WORKER_PEPPER')),$clock);
    $candidate=new \Identity\Contracts\AuthenticationPrincipalCandidate('concurrent_qa_account',1,'active',$clock->now()->format('Y-m-d H:i:s'));
    exit($service->create($candidate)->allowed() ? 0 : 1);
}

$select=static fn(array $values)=>Selector::fromValues($values['APP_ENV']??'', $values['MXMED_PREVIEW_EXPLICIT']??'', $values['MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT']??'');
check('E01',rejects(fn()=>Selector::fromValues('','')));
check('E02',rejects(fn()=>Selector::fromValues('local','')));
check('E03',$select($v)->mode()==='normal_local' && $config->value('MXMED_DB_NAME')==='mxmed');
check('E04',Selector::fromValues('local','1')->mode()==='preview');
check('E05',rejects(fn()=>$select($v+['MXMED_PREVIEW_EXPLICIT'=>'1'])));
check('E06',rejects(fn()=>Config::fromValues(array_replace($v,['MXMED_DB_NAME'=>'mxmed_gate4d_preview_forbidden']))));
envSet(['MXMED_ENVIRONMENT'=>'local','MXMED_PREVIEW_EXPLICIT'=>'1','MXMED_PREVIEW_PEPPER'=>bin2hex(random_bytes(32)),'MXMED_DB_NAME'=>'mxmed']);
check('E07',rejects(fn()=>Composition::preview(),'preview_database_required'));
check('E08',Selector::fromValues('staging','')->mode()==='productive' && rejects(fn()=>Selector::fromValues('staging','','1')));
check('E09',Selector::fromValues('production','')->mode()==='productive' && rejects(fn()=>Selector::fromValues('production','','1')));
check('E10',rejects(fn()=>Selector::fromValues('unknown','')));
foreach (['mxmed_staging','mxmed_production','mxmed_prd','mxmed_other',''] as $db) check('DB_guard_'.$db,rejects(fn()=>Config::fromValues(array_replace($v,['MXMED_DB_NAME'=>$db]))));
foreach (['MXMED_NORMAL_LOCAL_VALKEY_HOST'=>'example.com','MXMED_DB_HOST'=>'example.com','MXMED_NORMAL_LOCAL_VALKEY_PORT'=>'6384',
    'MXMED_NORMAL_LOCAL_SESSION_PREFIX'=>'mxmed:prd:session:','MXMED_NORMAL_LOCAL_IDENTITY_PEPPER'=>'','MXMED_NORMAL_LOCAL_IDENTITY_ORIGIN'=>'http://localhost:8091',
    'MXMED_ENVIRONMENT'=>'production'] as $k=>$bad) check('config_guard_'.$k,rejects(fn()=>Config::fromValues(array_replace($v,[$k=>$bad]))));
putenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT=1'); putenv('APP_ENV');
check('normal_local_requires_APP_ENV',rejects(fn()=>Selector::fromProcessEnvironment()));
envSet(['APP_ENV'=>'local','MXMED_PREVIEW_EXPLICIT'=>'']);
check('process_selector_normal_local',Selector::fromProcessEnvironment()->mode()==='normal_local');
putenv('MXMED_PREVIEW_EXPLICIT=1');
check('direct_preview_rejects_normal_flag',rejects(fn()=>Composition::preview()));
envSet(['MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT'=>'','MXMED_PREVIEW_EXPLICIT'=>'']);
$client=new Client('127.0.0.1',$port);
$store=Factory::create('local',['driver'=>'valkey','prefix'=>'mxmed:local:session:','explicit_normal_local_flag'=>true],$client);
check('S01',$store->healthCheck()->healthy());
$preview=new PreviewValkeyClient('127.0.0.1',$port);
check('S02',Factory::create('local',['driver'=>'valkey','prefix'=>'mxmed:gate4d:preview:session:','explicit_preview_flag'=>true],$preview)->healthCheck()->healthy()
    && rejects(fn()=>Factory::create('local',['driver'=>'valkey','prefix'=>'mxmed:local:session:','explicit_preview_flag'=>true],$preview)));
check('S03',rejects(fn()=>new ValkeySessionStoreAdapter($client,'mxmed:prd:session:')) && rejects(fn()=>new ValkeySessionStoreAdapter($client,'mxmed:stg:session:')));
check('S04',rejects(fn()=>new ValkeySessionStoreAdapter($client,'arbitrary:')));
$closed=stream_socket_server('tcp://127.0.0.1:0',$errno,$error); $closedPort=(int)substr(strrchr(stream_socket_get_name($closed,false),':'),1); fclose($closed);
$unavailable=new Client('127.0.0.1',$closedPort);
check('S05',!Factory::create('local',['driver'=>'valkey','prefix'=>'mxmed:local:session:','explicit_normal_local_flag'=>true],$unavailable)->healthCheck()->healthy());
check('S06',rejects(fn()=>Factory::create('local',['driver'=>'in_memory','explicit_dev_flag'=>true,'explicit_normal_local_flag'=>true],$client)));
$key='mxmed:local:session:'.hash('sha256','physical-roundtrip');
check('S07',$client->set($key,'value',45) && (new Client('127.0.0.1',$port))->get($key)==='value');
check('S08',$client->ttl($key)>0 && $client->ttl($key)<=45);
$client->delete($key);
$workerEnv=getenv(); $workerEnv['IDLOCAL_QA_WORKER_PEPPER']=bin2hex(random_bytes(32));
$workers=[];
for($i=0;$i<12;$i++) {
    $process=proc_open([PHP_BINARY,__FILE__,'concurrent-worker'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$workerEnv);
    if (!is_resource($process)) throw new RuntimeException('worker_start_failed');
    $workers[]=[$process,$pipes];
}
$workersOk=true;
foreach($workers as [$process,$pipes]) {
    $output=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $workersOk=(proc_close($process)===0 && $output==='' && $errors==='') && $workersOk;
}
check('cross_process_concurrent_max_five',$workersOk && count($store->listActiveForAccount('concurrent_qa_account'))===5);

check('cross_namespace_key_denied',rejects(fn()=>$client->get('mxmed:stg:session:'.str_repeat('a',64))));

$admin=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$suffix=bin2hex(random_bytes(6)); $qa='mxmed_idlocal03a_'.$suffix; $gate='mxmed_gate4d_preview_idlocal03a_'.$suffix; $created=[];
$pdoFor=static fn(string $name)=>new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$name.';charset=utf8mb4','root','',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$assemble=new ReflectionMethod(Composition::class,'assembleNormalLocal');
$migrations=__DIR__.'/../db/migrations/';
try {
    foreach ([$qa,$gate] as $name) {
        if (!preg_match('/^mxmed_(?:idlocal03a_|gate4d_preview_idlocal03a_)[a-f0-9]{12}$/D',$name)) throw new RuntimeException('unsafe_qa_database');
        $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created[]=$name;
    }
    echo 'QA_DATABASE='.$qa.PHP_EOL;
    $pdo=$pdoFor($qa);
    check('C05',rejects(fn()=>$assemble->invoke(null,$config,$pdo),'normal_local_schema_missing:auth_accounts'));
    check('C07',(int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn()===0);
    foreach ([$qa,$gate] as $name) {
        $db=$pdoFor($name);
        $db->exec('CREATE TABLE profiles_doctors (doctor_id VARCHAR(64) PRIMARY KEY, display_name VARCHAR(160) NOT NULL, professional_designation VARCHAR(120) NULL) ENGINE=InnoDB');
        $db->exec('CREATE TABLE medical_groups (group_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB');
        foreach (['2026_07_19_01_create_auth_accounts.sql','2026_07_19_03_create_auth_account_memberships.sql',
            '2026_07_19_02_create_auth_account_consents.sql','2026_07_20_04_create_auth_account_credentials.sql',
            '2026_07_20_05_create_auth_account_one_time_tokens.sql','2026_07_20_06_create_auth_rate_limit_buckets.sql'] as $file) $db->exec(file_get_contents($migrations.$file));
    }
    $pdo->exec('DROP TABLE auth_account_memberships');
    check('missing_memberships_readiness',rejects(fn()=>$assemble->invoke(null,$config,$pdo),'normal_local_schema_missing:auth_account_memberships'));
    $pdo->exec(file_get_contents($migrations.'2026_07_19_03_create_auth_account_memberships.sql'));
    $composition=$assemble->invoke(null,$config,$pdo);
    check('C01',$composition->environment()==='local' && $composition->allowedOrigin()==='https://localhost:8443');
    check('R06_native_prepares',$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)===false);
    check('C08',(int)$pdo->query('SELECT COUNT(*) FROM auth_accounts')->fetchColumn()===0);
    check('C09',(int)$pdo->query('SELECT COUNT(*) FROM auth_account_memberships')->fetchColumn()===0);
    $nativeRegistration=$composition->registration()->register([
        'email'=>'native-registration@example.invalid','password'=>'Native registration password 03K!',
        'terms_accepted'=>true,'terms_version'=>'v2','privacy_notice_accepted'=>true,'privacy_notice_version'=>'v2',
    ],['ip'=>'198.51.100.31','device'=>'idlocal03k-native']);
    check('E01',$nativeRegistration->accepted() && $nativeRegistration->accountId()!==null);
    $nativeAccountId=(string)$nativeRegistration->accountId();
    check('E02',(int)$pdo->query("SELECT COUNT(*) FROM auth_rate_limit_buckets WHERE operation_code='registration'")->fetchColumn()===3);
    check('E03',(int)$pdo->query("SELECT COUNT(*) FROM auth_accounts WHERE account_id=".$pdo->quote($nativeAccountId))->fetchColumn()===1);
    check('E04',(int)$pdo->query("SELECT COUNT(*) FROM auth_account_credentials WHERE account_id=".$pdo->quote($nativeAccountId))->fetchColumn()===1);
    check('E05',(int)$pdo->query("SELECT COUNT(*) FROM auth_account_consents WHERE account_id=".$pdo->quote($nativeAccountId))->fetchColumn()===2);
    check('E06',(int)$pdo->query("SELECT COUNT(*) FROM auth_account_one_time_tokens WHERE account_id=".$pdo->quote($nativeAccountId))->fetchColumn()===1);
    $nativeNotifications=json_decode((string)file_get_contents($dir.'/notifications.json'),true);
    check('E07',is_array($nativeNotifications) && count($nativeNotifications)===1 && is_string($nativeNotifications[0]['token']??null));
    check('E08',$composition->verification()->verify((string)$nativeNotifications[0]['token'],['ip'=>'198.51.100.32'])->verified());
    $nativeAuth=$composition->authentication()->authenticate('native-registration@example.invalid','Native registration password 03K!',['ip'=>'198.51.100.33']);
    check('E09',$nativeAuth->isAllowed() && $nativeAuth->candidate()!==null);
    $nativeSession=$composition->sessions()->create($nativeAuth->candidate(),['ip'=>'198.51.100.33']);
    check('E10',$nativeSession->allowed() && $composition->sessions()->validate($nativeSession->token()?->value())->allowed());
    check('E11',$composition->sessions()->logout($nativeSession->token()?->value())->allowed() && !$composition->sessions()->validate($nativeSession->token()?->value())->allowed());
    $badConfig=Config::fromValues(array_replace($v,['MXMED_NORMAL_LOCAL_VALKEY_PORT'=>(string)$closedPort]));
    check('C06',rejects(fn()=>$assemble->invoke(null,$badConfig,$pdo),'normal_local_session_store_unavailable'));
    foreach ([$qa,$gate] as $name) {
        $db=$pdoFor($name);
        $db->exec("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES ('qa_account','qa@example.invalid','qa@example.invalid','active',UTC_TIMESTAMP())");
        $q=$db->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at) VALUES (?,?,UTC_TIMESTAMP())');
        $q->execute(['qa_account',PasswordHash::hash('Isolated-QA-password-03A!')]);
        $db->exec("INSERT INTO profiles_doctors(doctor_id,display_name) VALUES ('qa_doctor','Dra. QA Canónica')");
        $db->exec("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status) VALUES ('qa_membership','qa_account','qa_doctor','owner','profile','active')");
    }
    $auth=$composition->authentication()->authenticate('qa@example.invalid','Isolated-QA-password-03A!',['ip'=>'127.0.0.1']);
    check('real_password_authentication',$auth->isAllowed());
    $session=$composition->sessions()->create($auth->candidate());
    check('canonical_session_created',$session->allowed());
    $token=$session->token()->value();
    $fresh=$assemble->invoke(null,$config,$pdoFor($qa));
    check('session_survives_new_composition',$fresh->sessions()->validate($token)->allowed());
    $private=(new PrivateApplicationAccessResolver($fresh))->resolve(['__Host-mxmed_session'=>$token]);
    check('private_access_exact_membership',$private->allowed && ($private->context['professional']['doctor_id']??'')==='qa_doctor'
        && ($private->context['professional']['display_name']??'')==='Dra. QA Canónica');
    $privateCapabilities=$private->context['capabilities']??[];
    check('private_access_capabilities_resolved',array_keys($privateCapabilities)===['patients','clinical_record','prescriptions']
        && count(array_filter($privateCapabilities,'is_bool'))===3);
    $pdo->exec("UPDATE auth_account_memberships SET status='revoked' WHERE membership_id='qa_membership'");
    $missing=(new PrivateApplicationAccessResolver($fresh))->resolve(['__Host-mxmed_session'=>$token]);
    check('private_access_missing_membership_denied',!$missing->allowed && $missing->httpStatus===403);
    $pdo->exec("UPDATE auth_account_memberships SET status='active' WHERE membership_id='qa_membership'");
    $pdo->exec("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status) VALUES ('qa_membership_2','qa_account','qa_doctor','collaborator','profile','active')");
    $ambiguous=(new PrivateApplicationAccessResolver($fresh))->resolve(['__Host-mxmed_session'=>$token]);
    check('private_access_ambiguous_membership_denied',!$ambiguous->allowed && $ambiguous->httpStatus===403);
    $pdo->exec("DELETE FROM auth_account_memberships WHERE membership_id='qa_membership_2'");
    check('session_idle_ttl',$session->record()->expiresAt()->getTimestamp()-$session->record()->createdAt()->getTimestamp()===3600);
    check('session_absolute_ttl',$session->record()->absoluteExpiresAt()->getTimestamp()-$session->record()->createdAt()->getTimestamp()===43200);
    check('S09',$fresh->sessions()->logout($token)->allowed() && !$composition->sessions()->validate($token)->allowed());
    $second=$composition->sessions()->create($auth->candidate());
    $pdo->exec("UPDATE auth_account_credentials SET credential_version=2 WHERE account_id='qa_account'");
    check('S10',!$fresh->sessions()->validate($second->token()->value())->allowed());
    $pdo->exec("UPDATE auth_account_credentials SET credential_version=1 WHERE account_id='qa_account'");
    $tokens=[];
    for($i=0;$i<6;$i++) { $made=$composition->sessions()->create($auth->candidate()); check('max_session_create_'.$i,$made->allowed()); $tokens[]=$made->token()->value(); }
    $valid=0; foreach($tokens as $t) if($fresh->sessions()->validate($t)->allowed()) $valid++;
    check('max_five_sessions',$valid===5);
    $rotation=$fresh->sessions()->rotate($tokens[5]);
    check('rotation',$rotation->allowed() && !$fresh->sessions()->validate($tokens[5])->allowed());
    $pdo->exec("UPDATE auth_accounts SET status='disabled' WHERE account_id='qa_account'");
    check('account_disable_invalidates',!$fresh->sessions()->validate($rotation->token()->value())->allowed());
    $_SESSION=['doctor_id'=>'untrusted','user_id'=>'untrusted'];
    check('C10',!$composition->sessions()->validate(null)->allowed()); unset($_SESSION);
    envSet(['APP_ENV'=>'','MXMED_ENVIRONMENT'=>'local','MXMED_PREVIEW_EXPLICIT'=>'1','MXMED_DB_NAME'=>$gate,
        'MXMED_DB_HOST'=>'127.0.0.1','MXMED_DB_PORT'=>'3306','MXMED_DB_USER'=>'root','MXMED_DB_PASS'=>'',
        'MXMED_SESSION_STORE_PORT'=>(string)$port,'MXMED_PREVIEW_TEST_EMAIL'=>'qa@example.invalid',
        'MXMED_PREVIEW_TEST_PASSWORD'=>'Isolated-QA-password-03A!','MXMED_PREVIEW_TEST_PROFILE_ID'=>'qa_doctor',
        'MXMED_PREVIEW_NOTIFICATION_FILE'=>$dir.'/gate-notifications.json']);
    $proc=proc_open([PHP_BINARY,__DIR__.'/run_gate4d_http_test.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $result=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit=proc_close($proc);
    check('C02',$exit===0 && (json_decode($result,true)['ok']??false));
    check('GATE4D_PREVIEW_REGRESSION',$exit===0);
    if (getenv('IDLOCAL_UI_QA') === '1') {
        require __DIR__.'/IdentityUiCsrfTest.php';
        identityUiCsrfCompositionChecks($composition);
        $uiProcess=proc_open(['python3',__DIR__.'/identity_ui_https_fixture_test.py'],[0=>STDIN,1=>STDOUT,2=>STDERR],$uiPipes);
        check('UI_HTTPS_FIXTURE',is_resource($uiProcess) && proc_close($uiProcess)===0);
    }

    foreach(['staging','production'] as $index=>$environment) {
        check('C0'.($index+3),Selector::fromValues($environment,'')->select(fn()=>throw new RuntimeException(),fn($e)=>$e)===$environment);
    }
} finally {
    foreach(array_reverse($created) as $name) $admin->exec('DROP DATABASE `'.$name.'`');
}
echo "NORMAL_LOCAL_QA=PASS\n";
