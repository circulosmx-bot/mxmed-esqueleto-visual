<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$ready = false;
$connection = null;
try {
    $endpoint = (string)(getenv('SESSION_HOST') ?: '');
    $servicePort = (string)(getenv('SESSION_PORT') ?: '');
    $username = (string)(getenv('SESSION_STORE_USERNAME') ?: '');
    $credential = (string)(getenv('SESSION_STORE_PASSWORD') ?: '');
    $tls = strtolower((string)(getenv('SESSION_TLS_REQUIRED') ?: ''));
    $environment = strtolower((string)(getenv('APP_ENV') ?: ''));
    $prefix = (string)(getenv('SESSION_PREFIX') ?: '');
    if (
        !class_exists(Redis::class)
        || preg_match('/^[A-Za-z0-9.-]{1,253}$/D', $endpoint) !== 1
        || filter_var($endpoint, FILTER_VALIDATE_IP) !== false
        || $servicePort !== '6379'
        || preg_match('/^[A-Za-z0-9_.-]{1,128}$/D', $username) !== 1
        || $credential === ''
        || $tls !== 'true'
        || !in_array($environment, ['staging','production'], true)
        || $prefix !== ($environment === 'production' ? 'mxmed:prd:session:' : 'mxmed:stg:session:')
        || (string)getenv('SESSION_IDLE_TTL') !== '3600'
        || (string)getenv('SESSION_ABSOLUTE_LIFETIME') !== '43200'
        || (string)getenv('SESSION_TOUCH_INTERVAL') !== '300'
        || (string)getenv('SESSION_MAX_ACTIVE') !== '5'
        || strtolower((string)getenv('SESSION_LOCK_ENABLED')) !== 'true'
        || (string)getenv('SESSION_LOCK_TIMEOUT_SECONDS') !== '10'
        || (string)getenv('SESSION_LOCK_WAIT_MICROSECONDS') !== '100000'
    ) throw new RuntimeException('readiness_unavailable');

    $connection = new Redis();
    $context = ['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$endpoint,'SNI_enabled'=>true]];
    if (!$connection->connect('tls://' . $endpoint, 6379, 1.0, null, 0, 1.0, $context)) throw new RuntimeException('readiness_unavailable');
    if (!$connection->auth([$username, $credential])) throw new RuntimeException('readiness_unavailable');
    $pong = $connection->ping();
    $ready = $pong === true || $pong === '+PONG';
} catch (Throwable) {
    $ready = false;
} finally {
    if ($connection instanceof Redis) { try { $connection->close(); } catch (Throwable) {} }
}

if ($ready) http_response_code(200);
else http_response_code(503);
echo json_encode($ready ? ['status'=>'ready'] : ['status'=>'unavailable','code'=>'dependency_unavailable'], JSON_THROW_ON_ERROR);
