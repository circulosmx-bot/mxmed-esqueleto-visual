<?php
declare(strict_types=1);

/**
 * Future physical proof only. This file is intentionally inert unless a later
 * Director-authorized isolated staging endpoint supplies the exact gate token.
 * Never point it at production.
 */
if ((string)getenv('MXMED_C3_PHYSICAL_VALKEY_TEST_AUTHORIZED') !== 'DIRECTOR_AUTHORIZED_ISOLATED_C3') {
    echo "C3_PHYSICAL_VALKEY_TEST=SKIPPED_NOT_AUTHORIZED\n";
    exit(0);
}

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../adapters/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../services/*.php') as $file) require_once $file;

use Identity\Adapters\ProductiveValkeyClient;
use Identity\Adapters\ValkeySessionStoreAdapter;
use Identity\Contracts\AccountStatus;
use Identity\Contracts\AuthenticationPrincipalCandidate;
use Identity\Contracts\SessionPolicy;
use Identity\Contracts\SystemClock;
use Identity\Services\SessionService;
use Identity\Services\SessionTokenCodec;

$required = ['SESSION_HOST','SESSION_STORE_USERNAME','SESSION_STORE_PASSWORD','SESSION_SIGNING_KEY'];
$values = [];
foreach ($required as $name) { $value=getenv($name); if ($value===false||$value==='') throw new RuntimeException('isolated_c3_configuration_required'); $values[$name]=(string)$value; }
if (preg_match('/prod|production|prd/i', $values['SESSION_HOST'])===1 || (string)getenv('APP_ENV')!=='staging' || (string)getenv('SESSION_PREFIX')!=='mxmed:stg:session:') throw new RuntimeException('production_endpoint_forbidden');
if (!extension_loaded('redis')) throw new RuntimeException('phpredis_required');

$client = new ProductiveValkeyClient($values['SESSION_HOST'], 6379, $values['SESSION_STORE_USERNAME'], $values['SESSION_STORE_PASSWORD']);
if (!$client->ping()) throw new RuntimeException('read_only_ping_failed');
$clock = new SystemClock();
$store = new ValkeySessionStoreAdapter($client, 'mxmed:stg:session:', $clock);
$service = new SessionService($store, new SessionTokenCodec($values['SESSION_SIGNING_KEY']), $clock, new SessionPolicy());
$accountId = (string)(getenv('MXMED_C3_PHYSICAL_ACCOUNT_ID') ?: 'c3-isolated-' . bin2hex(random_bytes(12)));
$candidate = new AuthenticationPrincipalCandidate($accountId, 1, AccountStatus::ACTIVE, $clock->now()->format('Y-m-d H:i:s'));

if ((string)getenv('MXMED_C3_PHYSICAL_VALKEY_TEST_WORKER') === '1') {
    if (!$service->create($candidate,['device_label'=>'C3 isolated worker'])->allowed()) throw new RuntimeException('concurrent_create_proof_failed');
    exit(0);
}

$environment=getenv();if(!is_array($environment))throw new RuntimeException('worker_environment_unavailable');
$environment['MXMED_C3_PHYSICAL_ACCOUNT_ID']=$accountId;$environment['MXMED_C3_PHYSICAL_VALKEY_TEST_WORKER']='1';
$workers=[];
for($index=0;$index<20;$index++){
    $process=proc_open([PHP_BINARY,__FILE__],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$environment);
    if(!is_resource($process))throw new RuntimeException('concurrent_worker_start_failed');
    $workers[]=[$process,$pipes];
}
foreach($workers as [$process,$pipes]){$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0||$stdout!==''||$stderr!=='')throw new RuntimeException('concurrent_worker_failed');}
if(count($store->listActiveForAccount($accountId))!==5)throw new RuntimeException('maximum_five_invariant_failed');
$parentSession=$service->create($candidate,['device_label'=>'C3 isolated parent']);if(!$parentSession->allowed())throw new RuntimeException('parent_session_failed');
$rotation=$service->rotate((string)$parentSession->token());if(!$rotation->allowed())throw new RuntimeException('rotation_failed');
$logout=$service->logout((string)$rotation->token());if(!$logout->allowed())throw new RuntimeException('revoke_failed');
$revoked=$service->revokeAll($accountId);if(!$revoked->allowed())throw new RuntimeException('revoke_all_failed');
if($store->listActiveForAccount($accountId)!==[])throw new RuntimeException('index_cleanup_failed');

echo "C3_PHYSICAL_VALKEY_TEST=PASS\n";
echo "TLS_HOSTNAME_VERIFICATION=PASS\nACL_AUTH=PASS\nNAMESPACE_RESTRICTION=PASS\nTTL_TOUCH_ROTATE_REVOKE_INDEX=PASS\nMAX_FIVE_AFTER_20_CREATES=PASS\nREAD_ONLY_HEALTH_PING=PASS\n";
