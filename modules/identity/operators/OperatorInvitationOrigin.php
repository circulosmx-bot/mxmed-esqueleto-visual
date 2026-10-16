<?php
declare(strict_types=1);
namespace Identity\Operators;
final class OperatorInvitationOrigin
{
    public static function assertPublic(string $origin):string
    {
        $p=parse_url($origin);$host=strtolower($p['host']??'');$canonical=rtrim($host,'.');
        if(!$p||($p['scheme']??'')!=='https'||$host===''||!str_contains($host,'.')||isset($p['user'],$p['pass'])||isset($p['user'])||isset($p['query'])||isset($p['fragment'])||isset($p['port'])||!in_array($p['path']??'',['','/'],true)
            ||$host!==$canonical||filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)===false||preg_match('/^[0-9.]+$/D',$host)
            ||filter_var($host,FILTER_VALIDATE_IP)||preg_match('/(?:^localhost$|\.(?:localhost|local|localdomain|internal|lan|home|invalid|test)$)/D',$host))throw new \RuntimeException('operator_public_origin_required');
        return rtrim($origin,'/');
    }
}
