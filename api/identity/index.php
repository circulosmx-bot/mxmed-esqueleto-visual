<?php
declare(strict_types=1);

require_once __DIR__ . '/../../modules/identity/http/IdentityHttpComposition.php';

use Identity\Contracts\OneTimeTokenPurpose;
use Identity\Contracts\ReasonCode;
use Identity\Contracts\SessionCookieDescriptor;
use Identity\Contracts\SessionValidationDecision;
use Identity\Http\IdentityHttpComposition;
use Identity\Http\IdentityHttpCompositionSelector;
use Identity\Http\ProductiveIdentityHttpConfiguration;
use Identity\Services\OneTimeTokenCodec;

IdentityHttpComposition::registerAutoloader();

function identityHttpJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'none\';');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function identityHttpSetCookie(?SessionCookieDescriptor $descriptor): void
{
    if ($descriptor === null) return;
    $maxAge = $descriptor->maxAge() !== null && $descriptor->maxAge() < 0 ? 0 : $descriptor->maxAge();
    $parts = [
        $descriptor->name() . '=' . rawurlencode($descriptor->value()),
        'Path=' . $descriptor->path(),
        'Secure',
        'HttpOnly',
        'SameSite=' . $descriptor->sameSite(),
    ];
    if ($maxAge !== null) $parts[] = 'Max-Age=' . $maxAge;
    header('Set-Cookie: ' . implode('; ', $parts), false);
}

function identityHttpOperation(): string
{
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $parts = explode('/', trim($path, '/'));
    $index = array_search('index.php', $parts, true);
    return $index === false ? '' : (string)($parts[$index + 1] ?? '');
}

function identityHttpBody(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (stripos($contentType, 'application/json') === false) identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 415);
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($decoded)) identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 400);
    return $decoded;
}

function identityHttpSameOrigin(IdentityHttpComposition $composition, bool $write): void
{
    $allowed = $composition->allowedOrigin();
    $origin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($origin !== '' && $origin !== $allowed) identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 403);
    if ($write && $origin === '' && ($referer === '' || !str_starts_with($referer, $allowed . '/'))) identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 403);
}

function identityHttpSessionFailure(SessionValidationDecision|array $decision): never
{
    $reason = $decision instanceof SessionValidationDecision ? $decision->reasonCode() : (string)($decision['reason_code'] ?? ReasonCode::SESSION_INVALID);
    if ($reason === ReasonCode::SESSION_STORE_UNAVAILABLE || $reason === ReasonCode::STORAGE_UNAVAILABLE) {
        identityHttpJson(['authenticated'=>false,'state'=>'TEMPORARILY_UNAVAILABLE'], 503);
    }
    $state = match ($reason) {
        ReasonCode::SESSION_IDLE_EXPIRED, ReasonCode::SESSION_ABSOLUTE_EXPIRED => 'SESSION_EXPIRED',
        ReasonCode::SESSION_SUPERSEDED => 'SESSION_REPLACED',
        ReasonCode::ACCOUNT_BLOCKED => 'ACCOUNT_LOCKED',
        ReasonCode::ACCOUNT_DISABLED => 'ACCOUNT_DISABLED',
        default => 'SESSION_REVOKED',
    };
    identityHttpSetCookie(SessionCookieDescriptor::deletion());
    identityHttpJson(['authenticated'=>false,'state'=>$state], 401);
}

/** @return array{0:array,1:?SessionValidationDecision} */
function identityHttpWriteGuard(IdentityHttpComposition $composition, string $operation, array $body): array
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 405);
    identityHttpSameOrigin($composition, true);
    $csrf = trim((string)($body['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    $preAuth = in_array($operation, ['registration-request','email-verification','login','recovery-request','password-reset'], true);
    if ($preAuth) {
        if (!$composition->csrf()->validPreAuth($csrf)) identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 403);
        return [$body, null];
    }
    $rawCookie = (string)($_COOKIE['__Host-mxmed_session'] ?? '');
    $session = $composition->sessions()->validate($rawCookie !== '' ? $rawCookie : null);
    if (!$session->allowed() || $session->record() === null) identityHttpSessionFailure($session);
    if (!$composition->csrf()->validAuthenticated($csrf, $session->record()->tokenDigest())) identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 403);
    return [$body, $session];
}

function identityHttpAuditSession(IdentityHttpComposition $composition, string $event, string $accountId, \Identity\Contracts\SessionRecord $target, ?\Identity\Contracts\SessionRecord $requestSession, string $route, string $result, string $reason): void
{
    $producer=$composition->sessionAudit();$requests=$composition->auditRequests();$actors=$composition->auditActors();
    if($producer===null||$requests===null||$actors===null)return;
    try {
        $request=$requests->newHttp([],[],[],'AUTH_SESSION_LIFECYCLE',$requestSession===null?null:(string)$requestSession->sessionId(),'SESSION','POST',$route,null,null);
        $actor=$actors->fromBackend([
            'authenticated_identity_id'=>$accountId,'real_actor_type'=>'ACCOUNT','real_actor_id'=>$accountId,
            'effective_entity_type'=>'SESSION','effective_entity_id'=>(string)$target->sessionId(),
            'actor_role'=>'CUSTOMER','actor_scope'=>'SELF','target_type'=>'SESSION','target_id'=>(string)$target->sessionId(),
            'authorization_provenance'=>'server_session_authority','trust_source'=>'backend_trusted',
        ]);
        match($event){
            'created'=>$producer->created($request,$actor,$target->sessionId(),$reason),
            'rotated'=>$producer->rotated($request,$actor,$target->sessionId(),$reason),
            'revoked'=>$producer->revoked($request,$actor,$target->sessionId(),$result,$reason),
            'logout'=>$producer->logout($request,$actor,$target->sessionId(),$result,$reason),
            default=>throw new \InvalidArgumentException('unknown_session_audit_event'),
        };
    } catch(\Throwable) { error_log('MXMED_SESSION_AUDIT_CONTEXT_FAILED'); }
}

function identityHttpAuditLogoutAll(IdentityHttpComposition $composition, string $accountId, string $result, string $reason): void
{
    $producer=$composition->sessionAudit();$requests=$composition->auditRequests();$actors=$composition->auditActors();
    if($producer===null||$requests===null||$actors===null)return;
    try {
        $request=$requests->newHttp([],[],[],'AUTH_SESSION_LIFECYCLE',null,'SESSION','POST','/api/identity/index.php/password-reset',null,null);
        $actor=$actors->fromBackend(['effective_entity_type'=>'ACCOUNT','effective_entity_id'=>$accountId,'actor_role'=>'SYSTEM','actor_scope'=>'SECURITY','target_type'=>'ACCOUNT','target_id'=>$accountId,'authorization_provenance'=>'credential_security_policy','trust_source'=>'system']);
        $producer->logoutAll($request,$actor,\Identity\Audit\TrustedIdentityId::fromAuthoritativeOutcome($accountId),$result,$reason);
    } catch(\Throwable) { error_log('MXMED_SESSION_AUDIT_CONTEXT_FAILED'); }
}

try {
    $operation = identityHttpOperation();
    $appEnvironment = (string)(getenv('APP_ENV') ?: '');
    $selector = $appEnvironment === ''
        ? IdentityHttpCompositionSelector::fromProcessEnvironment()
        : IdentityHttpCompositionSelector::fromValues($appEnvironment, (string)(getenv('MXMED_PREVIEW_EXPLICIT') ?: ''));
    $composition = $selector->select(
        static fn(): IdentityHttpComposition => IdentityHttpComposition::preview(),
        static fn(string $environment): IdentityHttpComposition => IdentityHttpComposition::productive(ProductiveIdentityHttpConfiguration::fromProcessEnvironment($environment))
    );
    if (!$composition instanceof IdentityHttpComposition) throw new \RuntimeException('identity_composition_unavailable');
    $clientIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $clockDimensions = ['ip'=>$clientIp === '' ? null : $clientIp,'device'=>$userAgent === '' ? null : hash('sha256', $userAgent)];
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $rawCookie = (string)($_COOKIE['__Host-mxmed_session'] ?? '');

    if ($operation === 'csrf-token') {
        if ($method !== 'GET') identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 405);
        identityHttpSameOrigin($composition, false);
        identityHttpJson(['ok'=>true,'csrf_token'=>$composition->csrf()->issuePreAuth(),'purpose'=>'PRE_AUTH_CSRF']);
    }

    if ($operation === 'current-session') {
        if ($method !== 'GET') identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 405);
        identityHttpSameOrigin($composition, false);
        $decision = $composition->sessions()->validate($rawCookie !== '' ? $rawCookie : null);
        if (!$decision->allowed() || $decision->context() === null || $decision->record() === null) identityHttpSessionFailure($decision);
        $profile = null;
        foreach ($composition->memberships()->activeForAccount($decision->context()->accountId()) as $membership) {
            $profileId = trim((string)($membership['profile_doctor_id'] ?? ''));
            if ($profileId !== '') { $profile = ['type'=>'profile_doctor','id'=>$profileId]; break; }
        }
        identityHttpJson([
            'authenticated'=>true,
            'state'=>'AUTHENTICATED',
            'account_status'=>'active',
            'profile'=>$profile,
            'idle_expires_at'=>$decision->record()->expiresAt()->format(DATE_ATOM),
            'absolute_expires_at'=>$decision->record()->absoluteExpiresAt()->format(DATE_ATOM),
            'csrf_token'=>$composition->csrf()->issueAuthenticated($decision->record()->tokenDigest()),
        ]);
    }

    if ($operation === 'sessions') {
        if ($method !== 'GET') identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 405);
        identityHttpSameOrigin($composition, false);
        $result = $composition->sessions()->listOwnSessions($rawCookie !== '' ? $rawCookie : null);
        if (!$result['allowed']) identityHttpSessionFailure($result);
        identityHttpJson(['ok'=>true,'sessions'=>$result['sessions']]);
    }

    if ($method !== 'POST') identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 405);
    [$body, $authenticatedSession] = identityHttpWriteGuard($composition, $operation, identityHttpBody());

    switch ($operation) {
        case 'registration-request':
            $email = trim((string)($body['email'] ?? ''));
            if (!is_string($body['password'] ?? null) || $body['password'] === '' || (($body['password_confirmation'] ?? $body['password']) !== $body['password'])) identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 422);
            $decision = $composition->registration()->register(['email'=>$email,'password'=>$body['password'] ?? null,'terms_version'=>trim((string)($body['terms_version'] ?? 'v2')),'privacy_notice_version'=>trim((string)($body['privacy_notice_version'] ?? 'v2')),'terms_accepted'=>($body['terms_accepted'] ?? false) === true,'privacy_notice_accepted'=>($body['privacy_notice_accepted'] ?? false) === true], $clockDimensions);
            if (in_array($decision->reasonCode(), [ReasonCode::STORAGE_UNAVAILABLE,ReasonCode::NOTIFICATION_UNAVAILABLE], true)) identityHttpJson(['ok'=>false,'error'=>'TEMPORARILY_UNAVAILABLE'], 503);
            identityHttpJson(['ok'=>true,'status'=>'pending_verification']);

        case 'email-verification':
            $decision = $composition->verification()->verify(trim((string)($body['token'] ?? '')), $clockDimensions);
            if (!$decision->verified()) identityHttpJson(['ok'=>false,'error'=>'VERIFICATION_UNAVAILABLE'], 400);
            identityHttpJson(['ok'=>true,'status'=>'verified']);

        case 'login':
            $decision = $composition->authentication()->authenticate((string)($body['email'] ?? ''), (string)($body['password'] ?? ''), $clockDimensions);
            if (!$decision->isAllowed() || $decision->candidate() === null) identityHttpJson(['ok'=>false,'error'=>'INVALID_CREDENTIALS'], 401);
            $session = $composition->sessions()->create($decision->candidate(), ['device_label'=>'MXMED Identity','user_agent'=>$userAgent,'ip'=>$clientIp]);
            if (!$session->allowed() || $session->cookie() === null || $session->record() === null) identityHttpJson(['ok'=>false,'error'=>'TEMPORARILY_UNAVAILABLE'], 503);
            $superseded = $composition->sessions()->lastSupersededSession();
            $limitAction = $composition->sessions()->consumeSessionLimitAction();
            identityHttpAuditSession($composition,'created',$session->record()->principal()->accountId(),$session->record(),$session->record(),'/api/identity/index.php/login','SUCCESS','USER_REQUEST');
            if($superseded!==null)identityHttpAuditSession($composition,'revoked',$session->record()->principal()->accountId(),$superseded,$session->record(),'/api/identity/index.php/login','SUCCESS','SECURITY_RESPONSE');
            identityHttpSetCookie($session->cookie());
            $response = ['ok'=>true,'authenticated'=>true,'csrf_token'=>$composition->csrf()->issueAuthenticated($session->record()->tokenDigest()),'idle_expires_at'=>$session->record()->expiresAt()->format(DATE_ATOM),'absolute_expires_at'=>$session->record()->absoluteExpiresAt()->format(DATE_ATOM)];
            if ($limitAction !== null) $response['session_limit_action'] = $limitAction;
            identityHttpJson($response);

        case 'session-rotate':
            $decision = $composition->sessions()->rotate($rawCookie);
            if (!$decision->allowed() || $decision->cookie() === null || $decision->record() === null) identityHttpSessionFailure(['reason_code'=>$decision->reasonCode()]);
            identityHttpAuditSession($composition,'rotated',$decision->record()->principal()->accountId(),$decision->record(),$authenticatedSession?->record(),'/api/identity/index.php/session-rotate','SUCCESS','SYSTEM_POLICY');
            identityHttpSetCookie($decision->cookie());
            identityHttpJson(['ok'=>true,'state'=>'AUTHENTICATED','csrf_token'=>$composition->csrf()->issueAuthenticated($decision->record()->tokenDigest()),'idle_expires_at'=>$decision->record()->expiresAt()->format(DATE_ATOM),'absolute_expires_at'=>$decision->record()->absoluteExpiresAt()->format(DATE_ATOM)]);

        case 'session-revoke':
            $result = $composition->sessions()->revokeOwnSession($rawCookie, trim((string)($body['session_id'] ?? '')));
            if (!$result['allowed']) identityHttpSessionFailure($result);
            if($result['revoked_session'] instanceof \Identity\Contracts\SessionRecord)identityHttpAuditSession($composition,'revoked',$result['revoked_session']->principal()->accountId(),$result['revoked_session'],$result['current'],'/api/identity/index.php/session-revoke','SUCCESS','USER_REQUEST');
            if ($result['current_revoked']) identityHttpSetCookie(SessionCookieDescriptor::deletion());
            identityHttpJson(['ok'=>true,'state'=>$result['current_revoked']?'LOGOUT_SUCCESS':'SESSION_REVOKED']);

        case 'logout':
            $decision = $composition->sessions()->logout($rawCookie !== '' ? $rawCookie : null);
            identityHttpSetCookie($decision->cookie() ?? SessionCookieDescriptor::deletion());
            if($authenticatedSession?->record()!==null)identityHttpAuditSession($composition,'logout',$authenticatedSession->record()->principal()->accountId(),$authenticatedSession->record(),$authenticatedSession->record(),'/api/identity/index.php/logout',$decision->allowed()?'SUCCESS':'FAILURE',$decision->allowed()?'USER_REQUEST':'INTERNAL_ERROR');
            if (!$decision->allowed()) identityHttpJson(['ok'=>false,'state'=>'LOGOUT_TEMPORARY_FAILURE'], 503);
            identityHttpJson(['ok'=>true,'state'=>'LOGOUT_SUCCESS']);

        case 'recovery-request':
            $decision = $composition->recovery()->request((string)($body['email'] ?? ''), $clockDimensions);
            if (in_array($decision->reasonCode(), [ReasonCode::STORAGE_UNAVAILABLE,ReasonCode::NOTIFICATION_UNAVAILABLE], true)) identityHttpJson(['ok'=>false,'error'=>'TEMPORARILY_UNAVAILABLE'], 503);
            identityHttpJson(['ok'=>true,'status'=>'instructions_requested']);

        case 'password-reset':
            $rawToken = trim((string)($body['token'] ?? ''));
            if (($body['password_confirmation'] ?? $body['password'] ?? null) !== ($body['password'] ?? null)) identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 422);
            $tokenRow = null;
            try { $tokenRow = $composition->tokens()->findByHashAndPurpose(OneTimeTokenCodec::hash($rawToken), OneTimeTokenPurpose::PASSWORD_RECOVERY); } catch (\Throwable) {}
            $decision = $composition->recovery()->reset($rawToken, (string)($body['password'] ?? ''), $clockDimensions);
            if (!$decision->reset()) identityHttpJson(['ok'=>false,'error'=>'PASSWORD_RESET_UNAVAILABLE'], 400);
            if (is_array($tokenRow)) {
                $revoked = $composition->sessions()->revokeAll((string)$tokenRow['account_id']);
                identityHttpAuditLogoutAll($composition,(string)$tokenRow['account_id'],$revoked->allowed()?'SUCCESS':'FAILURE',$revoked->allowed()?'SECURITY_RESPONSE':'INTERNAL_ERROR');
                if (!$revoked->allowed()) { identityHttpSetCookie(SessionCookieDescriptor::deletion()); identityHttpJson(['ok'=>false,'error'=>'TEMPORARILY_UNAVAILABLE'], 503); }
            }
            identityHttpSetCookie(SessionCookieDescriptor::deletion());
            identityHttpJson(['ok'=>true,'status'=>'password_reset']);

        default:
            identityHttpJson(['ok'=>false,'error'=>'INVALID_REQUEST'], 404);
    }
} catch (\Throwable) {
    identityHttpJson(['ok' => false, 'error' => 'TEMPORARILY_UNAVAILABLE'], 503);
}
