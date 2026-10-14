<?php
declare(strict_types=1);
require_once __DIR__.'/../../modules/identity/http/IdentityHttpComposition.php';
require_once __DIR__.'/../../modules/identity/http/PrivateApplicationAccessResolver.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/../../modules/subscriptions/repositories/CurrentSubscriptionRepository.php';
require_once __DIR__.'/../../modules/subscriptions/services/CurrentSubscriptionReadModelService.php';
require_once __DIR__.'/../../modules/profiles/repositories/PublicProfileRepository.php';
use Subscriptions\Services\NormalLocalReviewScenario as Scenario;
function reviewReply(array $body, int $status=200): never {
    http_response_code($status); header('Content-Type: application/json'); header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff'); echo json_encode($body,JSON_THROW_ON_ERROR); exit;
}
// Canonical Identity session and exact doctor-1 membership are both required.
function reviewActor(\Identity\Http\IdentityHttpComposition $identity): array {
    if (!Scenario::environmentAllowed()) reviewReply(['error'=>'local_review_forbidden'],403);
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $sameOriginGet = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && $origin === ''
        && ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'same-origin'
        && str_starts_with($_SERVER['HTTP_REFERER'] ?? '', 'https://localhost:8443/');
    if ($origin !== 'https://localhost:8443' && !$sameOriginGet) reviewReply(['error'=>'origin_forbidden'],403);
    $access=(new \Identity\Http\PrivateApplicationAccessResolver($identity))->resolve($_COOKIE);
    if (!$access->allowed) reviewReply(['error'=>$access->httpStatus===401?'authentication_required':'forbidden'],$access->httpStatus);
    if (($access->context['professional']['doctor_id'] ?? '') !== '1') reviewReply(['error'=>'doctor_scope_mismatch'],403);
    return ['account_id'=>(string)$access->context['account']['id'],'session'=>$access->session];
}
try {
    $identity=\Identity\Http\IdentityHttpComposition::fromProcessEnvironment();
    $actor=reviewActor($identity);
    $pdo=$identity->pdo();
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $repository=new \Subscriptions\Repositories\CurrentSubscriptionRepository($pdo);
    if (!$repository->isNormalLocalDatabase()) reviewReply(['error'=>'database_forbidden'],403);
    $service=new \Subscriptions\Services\CurrentSubscriptionReadModelService($repository);
    $csrf=$identity->csrf();
    $method=$_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method,['GET','POST','PUT','DELETE'],true)) reviewReply(['error'=>'method_not_allowed'],405);
    if ($_GET !== []) reviewReply(['error'=>'fixed_doctor_target'],400);
    $model=$service->resolveForEntity('doctor','1');
    if ($method !== 'GET') {
        if (!$csrf->validAuthenticated((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''),$actor['session']->tokenDigest())) reviewReply(['error'=>'csrf_required'],403);
        $body=json_decode(file_get_contents('php://input'),true);
        if (!is_array($body) || array_diff(array_keys($body),['plan','canonical_fingerprint']) !== []) reviewReply(['error'=>'invalid_input_or_target'],400);
        $plan=$method==='DELETE' ? 'real' : ($body['plan'] ?? '');
        if (!is_string($plan) || !in_array($plan,['real','standard','optimum','professional'],true)) reviewReply(['error'=>'unsupported_plan'],422);
        if ($plan !== 'real' && (($model['local_review']['conflict'] ?? false)
            || ($body['canonical_fingerprint'] ?? '') !== $model['local_review']['canonical_fingerprint'])) reviewReply(['error'=>'canonical_state_changed'],409);
        Scenario::select($plan,$model['local_review']['canonical_fingerprint'],$actor['account_id']);
        $model=$service->resolveForEntity('doctor','1');
    }
    $profile=$pdo->query("SELECT display_name FROM profiles_doctors WHERE doctor_id='1'")->fetchColumn();
    $ownership=(new \Profiles\Repositories\PublicProfileRepository($pdo))->resolveOwnershipSource('1');
    reviewReply(['doctor_id'=>'1','label'=>$profile,'current'=>$model,'administration'=>$ownership,'csrf_token'=>$csrf->issueAuthenticated($actor['session']->tokenDigest())]);
} catch (Throwable $e) { reviewReply(['error'=>'local_review_unavailable'],503); }
