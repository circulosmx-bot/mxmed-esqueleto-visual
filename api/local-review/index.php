<?php
declare(strict_types=1);
require_once __DIR__.'/../../modules/identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../modules/subscriptions/repositories/CurrentSubscriptionRepository.php';
require_once __DIR__.'/../../modules/subscriptions/services/CurrentSubscriptionReadModelService.php';
require_once __DIR__.'/../../modules/profiles/repositories/PublicProfileRepository.php';
use Subscriptions\Services\NormalLocalReviewScenario as Scenario;
function reviewReply(array $body, int $status=200): never {
    http_response_code($status); header('Content-Type: application/json'); header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff'); echo json_encode($body,JSON_THROW_ON_ERROR); exit;
}
// Future authorization hook: require canonical session + local-review permission here,
// and return its account id. This bootstrap boundary never fabricates an Identity actor.
function reviewActor(): ?string {
    if (!Scenario::environmentAllowed()) reviewReply(['error'=>'local_review_forbidden'],403);
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $sameOriginGet = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && $origin === ''
        && ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'same-origin'
        && str_starts_with($_SERVER['HTTP_REFERER'] ?? '', 'https://localhost:8443/');
    if ($origin !== 'https://localhost:8443' && !$sameOriginGet) reviewReply(['error'=>'origin_forbidden'],403);
    return null;
}
try {
    $actor=reviewActor();
    $config=\Identity\Http\NormalLocalIdentityConfiguration::fromProcessEnvironment();
    $pdo=new PDO($config->dsn(),$config->value('MXMED_DB_USER'),$config->value('MXMED_DB_PASS'),
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $repository=new \Subscriptions\Repositories\CurrentSubscriptionRepository($pdo);
    if (!$repository->isNormalLocalDatabase()) reviewReply(['error'=>'database_forbidden'],403);
    $service=new \Subscriptions\Services\CurrentSubscriptionReadModelService($repository);
    $csrf=new \Identity\Http\CsrfTokenService(hash_hmac('sha256','local-review-scenario',$config->value('MXMED_NORMAL_LOCAL_IDENTITY_PEPPER')),
        900,new \Identity\Contracts\SystemClock(),'https://localhost:8443');
    $method=$_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method,['GET','POST','PUT','DELETE'],true)) reviewReply(['error'=>'method_not_allowed'],405);
    if ($_GET !== []) reviewReply(['error'=>'fixed_doctor_target'],400);
    $model=$service->resolveForEntity('doctor','1');
    if ($method !== 'GET') {
        if (!$csrf->validPreAuth((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) reviewReply(['error'=>'csrf_required'],403);
        $body=json_decode(file_get_contents('php://input'),true);
        if (!is_array($body) || array_diff(array_keys($body),['plan','canonical_fingerprint']) !== []) reviewReply(['error'=>'invalid_input_or_target'],400);
        $plan=$method==='DELETE' ? 'real' : ($body['plan'] ?? '');
        if (!is_string($plan) || !in_array($plan,['real','standard','optimum','professional'],true)) reviewReply(['error'=>'unsupported_plan'],422);
        if ($plan !== 'real' && (($model['local_review']['conflict'] ?? false)
            || ($body['canonical_fingerprint'] ?? '') !== $model['local_review']['canonical_fingerprint'])) reviewReply(['error'=>'canonical_state_changed'],409);
        Scenario::select($plan,$model['local_review']['canonical_fingerprint'],$actor);
        $model=$service->resolveForEntity('doctor','1');
    }
    $profile=$pdo->query("SELECT display_name FROM profiles_doctors WHERE doctor_id='1'")->fetchColumn();
    $ownership=(new \Profiles\Repositories\PublicProfileRepository($pdo))->resolveOwnershipSource('1');
    reviewReply(['doctor_id'=>'1','label'=>$profile,'current'=>$model,'administration'=>$ownership,'csrf_token'=>$csrf->issuePreAuth()]);
} catch (Throwable $e) { reviewReply(['error'=>'local_review_unavailable'],503); }
