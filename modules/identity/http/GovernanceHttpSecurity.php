<?php
declare(strict_types=1);
namespace Identity\Http;
use Identity\Services\SessionService;
use Identity\Contracts\AuthenticatedAccessContext;

final class GovernanceHttpDenied extends \RuntimeException
{
    public function __construct(public readonly int $status){parent::__construct('GOVERNANCE_DENIED');}
}

/** Shared with the real route and disposable transport tests. No actor from request data. */
final class GovernanceHttpSecurity
{
    public static function access(SessionService $sessions,CsrfTokenService $csrf,string $allowed,array $server,array $cookies,array $body,string $raw,string $key):AuthenticatedAccessContext
    {
        if(($server['REQUEST_METHOD']??'')!=='POST')throw new GovernanceHttpDenied(405);
        if(!GovernanceTransport::secure($server,$raw,$key))throw new GovernanceHttpDenied(503);
        $origin=(string)($server['HTTP_ORIGIN']??'');$referer=(string)($server['HTTP_REFERER']??'');
        if(($origin!==''&&$origin!==$allowed)||($origin===''&&!str_starts_with($referer,$allowed.'/')))throw new GovernanceHttpDenied(403);
        $session=(new CanonicalHttpSessionResolver($sessions))->resolve($cookies);
        if($session===null)throw new GovernanceHttpDenied(401);
        $token=(string)($body['csrf_token']??($server['HTTP_X_CSRF_TOKEN']??''));
        if(!$csrf->validAuthenticated($token,$session->session()->tokenDigest()))throw new GovernanceHttpDenied(403);
        return $session;
    }
}
