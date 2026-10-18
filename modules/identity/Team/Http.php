<?php
declare(strict_types=1);
namespace Identity\Team;
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver};

final class Http
{
    public static function headers(bool $json=false): void
    {
        header('Content-Type: '.($json?'application/json':'text/html').'; charset=UTF-8');header('Cache-Control: private, no-store');header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
    }
    public static function method(): bool
    {
        if(!in_array($_SERVER['REQUEST_METHOD']??'', ['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');return false;}return true;
    }
    public static function service(): ReadService
    {
        if(!IdentityHttpComposition::canonicalRuntimeRequested())throw new ReadFailure(503,'canonical_identity_unavailable');
        $identity=IdentityHttpComposition::fromProcessEnvironment();
        $origin=$_SERVER['HTTP_ORIGIN']??null;
        if(($origin!==null&&$origin!==$identity->allowedOrigin())||in_array($_SERVER['HTTP_SEC_FETCH_SITE']??'', ['cross-site','same-site'],true))throw new ReadFailure(403,'same_origin_required');
        $identity->pdo()->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
        return new ReadService($identity->pdo(),new CanonicalHttpSessionResolver($identity->sessions()));
    }
    public static function read(): void
    {
        self::headers(true);if(!self::method())return;
        try{
            // PHP collapses repeated query keys; reject ambiguity rather than trusting its last value.
            $names=[];foreach(explode('&',$_SERVER['QUERY_STRING']??'') as $pair){if($pair==='')continue;$name=urldecode(explode('=',$pair,2)[0]);if(isset($names[$name]))throw new ReadFailure(400,'invalid_query');$names[$name]=true;}
            $result=self::service()->read($_COOKIE,$_GET);
            if(($_SERVER['REQUEST_METHOD']??'')!=='HEAD')echo json_encode($result,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        }catch(\Throwable $e){$status=$e instanceof ReadFailure?$e->status:503;http_response_code($status);if(($_SERVER['REQUEST_METHOD']??'')!=='HEAD')echo json_encode(['error'=>match($status){401=>'authentication_required',403=>'access_denied',400=>'invalid_request',404=>'member_not_available',default=>'service_unavailable'}]);}
    }
}
