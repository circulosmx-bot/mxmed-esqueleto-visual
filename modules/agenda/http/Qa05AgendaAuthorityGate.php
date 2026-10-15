<?php
declare(strict_types=1);

namespace Agenda\Http;

foreach (glob(dirname(__DIR__).'/contracts/*.php') as $qa05AgendaContract) require_once $qa05AgendaContract;

use Agenda\Composition\AgendaAuthorityCompositionRoot;
use Agenda\Security\AgendaAuthorizationTarget;
use Agenda\Security\PrivateAgendaRoutePolicy;
use Identity\Contracts\AccountMembership;
use Identity\Contracts\CanonicalProfileReference;
use Identity\Http\IdentityHttpComposition;
use Identity\Http\PrivateApplicationAccessResolver;
use Platform\Adapters\PdoAuditTrailAdapter;
use Platform\Repositories\PdoAuditEventRepository;
use Platform\Services\AuthorizationBoundary;

/** Server-only QA composition. It cannot be selected by request data. */
final class Qa05AgendaAuthorityGate
{
    public static function enabled(): bool
    {
        return getenv('MXMED_QA05_ADMIN_ENV') === '1';
    }

    /** @return array{ok:bool,status:int,response:?array,context:array} */
    public static function authorize(array $segments, string $method, array $query, array $body, array $cookies, array $server): array
    {
        try {
            self::assertIsolatedEnvironment();
            IdentityHttpComposition::registerAutoloader();
            $identity = IdentityHttpComposition::fromProcessEnvironment();
            $access = (new PrivateApplicationAccessResolver($identity))->resolve($cookies);
            if (!$access->allowed || $access->access === null || $access->session === null) {
                return self::deny($access->httpStatus === 401 ? 401 : 503);
            }

            $resource = strtolower(trim((string)($segments[0] ?? '')));
            $verb = strtoupper(trim($method));
            $rule = PrivateAgendaRoutePolicy::find($resource, $verb);
            if ($rule === null) return self::deny(403);
            if (!self::sameOriginAndCsrf($identity, $access->session->tokenDigest(), $verb, $server)) {
                return self::deny(403);
            }

            $professional = (array)($access->context['professional'] ?? []);
            $canonicalDoctor = trim((string)($professional['doctor_id'] ?? ''));
            $requestedDoctor = trim((string)($query['doctor_id'] ?? $body['doctor_id'] ?? $canonicalDoctor));
            if ($canonicalDoctor === '' || $requestedDoctor === '') return self::deny(403);

            $rows = $identity->memberships()->activeForAccount($access->access->accountId());
            $rows = array_values(array_filter($rows, static fn(array $row): bool =>
                (string)($row['profile_doctor_id'] ?? '') === $canonicalDoctor
                && (string)($row['scope_code'] ?? '') === 'profile'
            ));
            if (count($rows) !== 1) return self::deny(403);
            $row = $rows[0];
            $membership = new AccountMembership(
                (string)$row['membership_id'],
                (string)$row['account_id'],
                CanonicalProfileReference::profile($canonicalDoctor),
                (string)$row['role_code'],
                (string)$row['scope_code'],
                (string)$row['status'],
                'backend_resolver'
            );
            $requestId = 'qa05-request-' . bin2hex(random_bytes(12));
            $correlationId = 'qa05-correlation-' . bin2hex(random_bytes(12));
            $target = new AgendaAuthorizationTarget(
                $resource,
                $verb,
                $requestedDoctor,
                'profile',
                $rule->action(),
                $correlationId,
                $requestId
            );
            $audit = new PdoAuditTrailAdapter(new PdoAuditEventRepository($identity->pdo()));
            $resolution = (new AgendaAuthorityCompositionRoot(new AuthorizationBoundary(), $audit))->resolve(
                $access->access,
                $membership,
                CanonicalProfileReference::profile($canonicalDoctor),
                $target
            );
            if (!$resolution->allowed()) return self::deny($resolution->httpStatus());

            return ['ok'=>true,'status'=>200,'response'=>null,'context'=>[
                'mode'=>'qa05_canonical_identity','strict'=>true,'compat'=>false,
                'user_id'=>$access->access->accountId(),'doctor_id'=>$canonicalDoctor,
                'actor_role'=>(string)$row['role_code'],'actor_role_source'=>'canonical_identity_membership',
                'auth_source'=>'canonical_identity','auth_mode'=>'qa05_canonical','is_authoritative'=>true,
                'warnings'=>[],
            ]];
        } catch (\Throwable $error) {
            error_log('QA05_AGENDA_AUTHORITY_FAILED '.get_class($error));
            return self::deny(503);
        }
    }

    private static function assertIsolatedEnvironment(): void
    {
        $database = (string)getenv('MXMED_DB_NAME');
        $expected = (string)getenv('MXMED_QA05_DATABASE');
        $origin = rtrim((string)getenv('MXMED_PREVIEW_ORIGIN'), '/');
        if (getenv('APP_ENV') !== 'local'
            || getenv('MXMED_ENVIRONMENT') !== 'local'
            || getenv('MXMED_PREVIEW_EXPLICIT') !== '1'
            || !in_array((string)getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT'), ['', '0'], true)
            || $database === '' || $database !== $expected
            || !str_starts_with($database, 'mxmed_gate4d_preview_adminqa_')
            || strtolower($database) === 'mxmed'
            || getenv('MXMED_DB_HOST') !== '127.0.0.1'
            || getenv('MXMED_DB_USER') !== 'mxmed_qa05'
            || $origin !== 'https://127.0.0.1:8444') {
            throw new \RuntimeException('qa05_environment_isolation_required');
        }
    }

    private static function sameOriginAndCsrf(IdentityHttpComposition $identity, object $digest, string $method, array $server): bool
    {
        if (in_array($method, ['GET', 'HEAD'], true)) return true;
        $origin = rtrim((string)($server['HTTP_ORIGIN'] ?? ''), '/');
        $referer = (string)($server['HTTP_REFERER'] ?? '');
        $allowed = rtrim($identity->allowedOrigin(), '/');
        if ($origin !== $allowed && ($origin !== '' || !str_starts_with($referer, $allowed . '/'))) {
            return false;
        }
        $csrf = trim((string)($server['HTTP_X_CSRF_TOKEN'] ?? ''));
        return $identity->csrf()->validAuthenticated($csrf, $digest);
    }

    /** @return array{ok:false,status:int,response:array,context:array} */
    private static function deny(int $status): array
    {
        $status = in_array($status, [401,403,503], true) ? $status : 403;
        return ['ok'=>false,'status'=>$status,'context'=>[],'response'=>[
            'ok'=>false,
            'error'=>$status === 401 ? 'unauthorized' : ($status === 503 ? 'authorization_unavailable' : 'forbidden'),
            'message'=>$status === 401 ? 'Debes iniciar sesión para continuar.' : ($status === 503 ? 'No fue posible validar tus permisos.' : 'Tu rol no permite realizar esta acción.'),
            'data'=>null,
            'meta'=>(object)['auth_mode'=>'qa05_canonical_identity'],
        ]];
    }
}
