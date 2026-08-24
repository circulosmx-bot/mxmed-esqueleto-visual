<?php
declare(strict_types=1);

require_once __DIR__ . '/../../modules/identity/http/IdentityHttpComposition.php';

use Identity\Contracts\ReasonCode;
use Identity\Contracts\OneTimeTokenPurpose;
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

function identityHttpSetCookie(?\Identity\Contracts\SessionCookieDescriptor $descriptor): void
{
    if ($descriptor === null) return;
    $maxAge = $descriptor->maxAge() !== null && $descriptor->maxAge() < 0 ? 0 : $descriptor->maxAge();
    $parts = [$descriptor->name() . '=' . rawurlencode($descriptor->value()), 'Path=/', 'Secure', 'HttpOnly', 'SameSite=Lax'];
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
    if (stripos($contentType, 'application/json') === false) identityHttpJson(['ok' => false, 'error' => 'INVALID_REQUEST'], 415);
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($decoded)) identityHttpJson(['ok' => false, 'error' => 'INVALID_REQUEST'], 400);
    return $decoded;
}

function identityHttpSameOrigin(IdentityHttpComposition $composition, bool $write): void
{
    $allowed = $composition->allowedOrigin();
    $origin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($origin !== '' && $origin !== $allowed) identityHttpJson(['ok' => false, 'error' => 'INVALID_REQUEST'], 403);
    if ($write && $origin === '' && ($referer === '' || !str_starts_with($referer, $allowed . '/'))) identityHttpJson(['ok' => false, 'error' => 'INVALID_REQUEST'], 403);
}

function identityHttpWriteGuard(IdentityHttpComposition $composition, array $body): array
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') identityHttpJson(['ok' => false, 'error' => 'INVALID_REQUEST'], 405);
    identityHttpSameOrigin($composition, true);
    $csrf = trim((string)($body['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    if (!$composition->csrf()->valid($csrf)) identityHttpJson(['ok' => false, 'error' => 'INVALID_REQUEST'], 403);
    return $body;
}

try {
    $operation = identityHttpOperation();
    $selector = IdentityHttpCompositionSelector::fromProcessEnvironment();
    $composition = $selector->select(
        static fn(): IdentityHttpComposition => IdentityHttpComposition::preview(),
        static fn(string $environment): IdentityHttpComposition => IdentityHttpComposition::productive(
            ProductiveIdentityHttpConfiguration::fromProcessEnvironment($environment)
        )
    );
    if (!$composition instanceof IdentityHttpComposition) throw new \RuntimeException('identity_composition_unavailable');
    $clientIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $clockDimensions = ['ip' => $clientIp === '' ? null : $clientIp, 'device' => $userAgent === '' ? null : hash('sha256', $userAgent)];

    if ($operation === 'current-session') {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') identityHttpJson(['ok' => false, 'error' => 'INVALID_REQUEST'], 405);
        identityHttpSameOrigin($composition, false);
        $rawCookie = (string)($_COOKIE['__Host-mxmed_session'] ?? '');
        $decision = $composition->sessions()->validate($rawCookie !== '' ? $rawCookie : null);
        if (!$decision->allowed() || $decision->context() === null) identityHttpJson(['authenticated' => false]);
        $profile = null;
        foreach ($composition->memberships()->activeForAccount($decision->context()->accountId()) as $membership) {
            $profileId = trim((string)($membership['profile_doctor_id'] ?? ''));
            if ($profileId !== '') { $profile = ['type' => 'profile_doctor', 'id' => $profileId]; break; }
        }
        identityHttpJson(['authenticated' => true, 'account_status' => 'active', 'profile' => $profile]);
    }

    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        identityHttpJson(['ok' => false, 'error' => 'INVALID_REQUEST'], 405);
    }
    $body = identityHttpWriteGuard($composition, identityHttpBody());
    switch ($operation) {
        case 'registration-request':
            $email = trim((string)($body['email'] ?? ''));
            if (!is_string($body['password'] ?? null) || $body['password'] === '' || (($body['password_confirmation'] ?? $body['password']) !== $body['password'])) {
                identityHttpJson(['ok' => false, 'error' => 'INVALID_REQUEST'], 422);
            }
            $decision = $composition->registration()->register([
                'email' => $email,
                'password' => $body['password'] ?? null,
                'terms_version' => trim((string)($body['terms_version'] ?? 'v2')),
                'privacy_notice_version' => trim((string)($body['privacy_notice_version'] ?? 'v2')),
                'terms_accepted' => ($body['terms_accepted'] ?? false) === true,
                'privacy_notice_accepted' => ($body['privacy_notice_accepted'] ?? false) === true,
            ], $clockDimensions);
            if ($decision->reasonCode() === ReasonCode::STORAGE_UNAVAILABLE || $decision->reasonCode() === ReasonCode::NOTIFICATION_UNAVAILABLE) identityHttpJson(['ok' => false, 'error' => 'TEMPORARILY_UNAVAILABLE'], 503);
            identityHttpJson(['ok' => true, 'status' => 'pending_verification']);

        case 'email-verification':
            $decision = $composition->verification()->verify(trim((string)($body['token'] ?? '')), $clockDimensions);
            if (!$decision->verified()) identityHttpJson(['ok' => false, 'error' => 'VERIFICATION_UNAVAILABLE'], 400);
            identityHttpJson(['ok' => true, 'status' => 'verified']);

        case 'login':
            $decision = $composition->authentication()->authenticate((string)($body['email'] ?? ''), (string)($body['password'] ?? ''), $clockDimensions);
            if (!$decision->isAllowed() || $decision->candidate() === null) identityHttpJson(['ok' => false, 'error' => 'INVALID_CREDENTIALS'], 401);
            $session = $composition->sessions()->create($decision->candidate(), ['device_label' => 'MXMED Identity', 'user_agent' => $userAgent, 'ip' => $clientIp]);
            if (!$session->allowed() || $session->cookie() === null) identityHttpJson(['ok' => false, 'error' => 'TEMPORARILY_UNAVAILABLE'], 503);
            identityHttpSetCookie($session->cookie());
            identityHttpJson(['ok' => true, 'authenticated' => true]);

        case 'logout':
            $decision = $composition->sessions()->logout((string)($_COOKIE['__Host-mxmed_session'] ?? '') ?: null);
            identityHttpSetCookie($decision->cookie() ?? \Identity\Contracts\SessionCookieDescriptor::deletion());
            identityHttpJson(['ok' => true]);

        case 'recovery-request':
            $decision = $composition->recovery()->request((string)($body['email'] ?? ''), $clockDimensions);
            if ($decision->reasonCode() === ReasonCode::STORAGE_UNAVAILABLE || $decision->reasonCode() === ReasonCode::NOTIFICATION_UNAVAILABLE) identityHttpJson(['ok' => false, 'error' => 'TEMPORARILY_UNAVAILABLE'], 503);
            identityHttpJson(['ok' => true, 'status' => 'instructions_requested']);

        case 'password-reset':
            $rawToken = trim((string)($body['token'] ?? ''));
            if (($body['password_confirmation'] ?? $body['password'] ?? null) !== ($body['password'] ?? null)) {
                identityHttpJson(['ok' => false, 'error' => 'INVALID_REQUEST'], 422);
            }
            $tokenRow = null;
            try { $tokenRow = $composition->tokens()->findByHashAndPurpose(OneTimeTokenCodec::hash($rawToken), OneTimeTokenPurpose::PASSWORD_RECOVERY); } catch (\Throwable) {}
            $decision = $composition->recovery()->reset($rawToken, (string)($body['password'] ?? ''), $clockDimensions);
            if (!$decision->reset()) identityHttpJson(['ok' => false, 'error' => 'PASSWORD_RESET_UNAVAILABLE'], 400);
            if (is_array($tokenRow)) $composition->sessions()->revokeAll((string)$tokenRow['account_id']);
            identityHttpSetCookie(\Identity\Contracts\SessionCookieDescriptor::deletion());
            identityHttpJson(['ok' => true, 'status' => 'password_reset']);

        default:
            identityHttpJson(['ok' => false, 'error' => 'INVALID_REQUEST'], 404);
    }
} catch (\Throwable) {
    identityHttpJson(['ok' => false, 'error' => 'TEMPORARILY_UNAVAILABLE'], 503);
}
