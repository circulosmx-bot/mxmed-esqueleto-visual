<?php
declare(strict_types=1);
namespace Media\Http;
require_once __DIR__.'/../services/MediaReviewAuthority.php';
use Media\Services\MediaReviewAuthority;
use Platform\Contracts\{AuthorizationContext,TrustedAuthorizationContext,AuthorizationPlane,RiskLevel,ActorReference,SessionReference,CapabilitySet};

/** Canonical identity first; PHP session fixture is a strictly isolated local fallback. */
final class MediaReviewHttpContext
{
    public static function fromRequest(array $cookies, array $server): ?TrustedAuthorizationContext
    {
        // Presence of a canonical token always prevents fallback, including invalid tokens.
        if (array_key_exists('__Host-mxmed_session', $cookies)) {
            try {
                $identity = \Identity\Http\IdentityHttpComposition::fromProcessEnvironment();
                $authority = new \Identity\Services\InternalOperatorAuthority(
                    new \Identity\Http\CanonicalHttpSessionResolver($identity->sessions()),
                    new \Identity\Repositories\InternalOperatorGrantRepository($identity->pdo())
                );
                return $authority->resolve($cookies, 'read', 'media_review_submission', RiskLevel::R0);
            } catch (\Throwable) {
                error_log('media_review_canonical_authority_unavailable');
                return null;
            }
        }
        // No productive request reads a PHP fixture session or its opt-in flag.
        $environment = [];
        foreach (['APP_ENV','MXMED_ENV','MXMED_ENVIRONMENT','ENVIRONMENT'] as $name) {
            $environment[$name] = (string)getenv($name);
            $value = strtolower($environment[$name]);
            if ($value !== '' && !in_array($value, ['local','development','test'], true)) return null;
        }
        if (!in_array(strtolower($environment['MXMED_ENVIRONMENT']), ['local','development'],true)) return null;
        $environment['MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED'] = (string)getenv('MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED');
        if ($environment['MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED'] !== '1'
            || !in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'],true)) return null;
        session_start(['read_and_close'=>true,'use_strict_mode'=>true,'cache_limiter'=>'']);
        return self::resolve($_SESSION, session_id(), $server, $environment);
    }

    public static function resolve(array $session, string $sessionId, array $server, array $environment): ?TrustedAuthorizationContext
    {
        if (($environment['MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED'] ?? '') !== '1'
            || !in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)
            || !in_array(strtolower($environment['MXMED_ENVIRONMENT'] ?? ''), ['local','development'], true)) return null;
        foreach (['APP_ENV','MXMED_ENV','MXMED_ENVIRONMENT','ENVIRONMENT'] as $name) {
            $value = strtolower($environment[$name] ?? '');
            if ($value !== '' && !in_array($value, ['local','development','test'], true)) return null;
        }
        // This record is created only by a local CLI test, never an HTTP login or headers.
        $fixture = $session['media_review_dev_operator'] ?? null;
        if (!is_array($fixture) || ($fixture['account_id'] ?? '') !== 'mr2_local_operator'
            || $sessionId === '' || !is_int($fixture['expires_at'] ?? null)) return null;
        $status = $fixture['session_status'] ?? 'invalid';
        if (!in_array($status, ['active','invalid','expired','revoked','superseded'], true)) $status = 'invalid';
        if ($fixture['expires_at'] <= time()) $status = 'expired';
        $actor = new ActorReference('operator','mr2_local_operator');
        $subject = new AuthorizationContext(
            realActor: $actor, effectiveActor: $actor,
            sessionReference: new SessionReference($sessionId),
            accountId: 'mr2_local_operator', credentialVersion: 1,
            capabilities: new CapabilitySet(($fixture['review_read_granted'] ?? false) === true ? [MediaReviewAuthority::CAPABILITY] : []),
            action: 'read', resource: 'media_review_submission',
            authorizationPlane: AuthorizationPlane::INTERNAL_OPERATOR, riskLevel: RiskLevel::R0
        );
        return TrustedAuthorizationContext::fromBackend($subject, 'media_review_local_fixture', $status,
            ($fixture['account_active'] ?? false) === true, false);
    }
}
