<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
$check=static function(bool $condition,string $label):void{if(!$condition)throw new RuntimeException($label);echo $label."=PASS\n";};
$runtime=$read('scripts/qa05-admin-env.py');
$gate=$read('modules/agenda/http/Qa05AgendaAuthorityGate.php');
$router=$read('api/agenda/index.php');
$session=$read('assets/js/core/private-session.js');
$fixture=$read('scripts/qa05-fixture.php');
$proxy=$read('scripts/qa05-https-proxy.py');

$check(str_contains($runtime,"DATABASE = 'mxmed_gate4d_preview_adminqa_qa05'")&&str_contains($runtime,"DB_USER = 'mxmed_qa05'")&&str_contains($runtime,"ORIGIN = 'https://127.0.0.1:8444'"),'QA05_CONTRACT_01_ISOLATED_TARGETS');
$check(str_contains($runtime,"DATABASE.lower()=='mxmed'")&&str_contains($runtime,"destroy requires --confirm")&&!str_contains($runtime,'FLUSHALL'),'QA05_CONTRACT_02_DESTRUCTIVE_GUARDS');
$check(str_contains($runtime,"'--bind','127.0.0.1'")&&str_contains($proxy,"('127.0.0.1',8444)")&&str_contains($proxy,"['127.0.0.1:8444']"),'QA05_CONTRACT_03_LOOPBACK_ONLY');
$check(str_contains($proxy,"QA_SESSION_COOKIE = '__Host-mxmed_qa05_session'")&&str_contains($proxy,"CANONICAL_SESSION_COOKIE = '__Host-mxmed_session'")&&str_contains($proxy,'backend_cookie_header'),'QA05_CONTRACT_03B_COOKIE_NAME_ISOLATION');
$check(str_contains($runtime,"MXMED_PRIVATE_MEDIA_ROOT")&&str_contains($runtime,"MXMED_PUBLIC_MEDIA_ROOT")&&str_contains($runtime,"MXMED_EMAIL_PROVIDER':'disabled'"),'QA05_CONTRACT_04_SIDE_EFFECT_ISOLATION');
$check(str_contains($gate,"getenv('MXMED_QA05_ADMIN_ENV') === '1'")&&str_contains($gate,"getenv('APP_ENV') !== 'local'")&&str_contains($gate,"getenv('MXMED_PREVIEW_EXPLICIT') !== '1'")&&str_contains($gate,"strtolower(\$database) === 'mxmed'"),'QA05_CONTRACT_05_SERVER_ONLY_ENABLEMENT');
$check(str_contains($gate,'PrivateApplicationAccessResolver')&&str_contains($gate,'AccountMembership')&&str_contains($gate,'AgendaAuthorityCompositionRoot')&&str_contains($gate,'PdoAuditTrailAdapter'),'QA05_CONTRACT_06_CANONICAL_AUTHORITY');
$check(str_contains($gate,'validAuthenticated')&&str_contains($gate,'HTTP_X_CSRF_TOKEN')&&str_contains($session,"url.pathname.startsWith('/api/agenda/')")&&str_contains($session,"headers.set('X-CSRF-Token', manager.csrfToken)"),'QA05_CONTRACT_07_CSRF');
$check(str_contains($router,'Qa05AgendaAuthorityGate::enabled()')&&str_contains($router,': resolveEffectiveAgendaActor'),'QA05_CONTRACT_08_NORMAL_LOCAL_FALLBACK');
$check(str_contains($fixture,"'owner','administrator','collaborator'")&&str_contains($fixture,"'qa05_synthetic_fixture'")&&!str_contains($fixture,'patients_'),'QA05_CONTRACT_09_SYNTHETIC_ONLY');
$check(str_contains($runtime,"'start','stop','status','reset','destroy'")&&str_contains($runtime,"'--appendonly','yes'"),'QA05_CONTRACT_10_PERSISTENT_LIFECYCLE');
echo "QA05_ADMIN_ENVIRONMENT_CONTRACT=PASS\n";
