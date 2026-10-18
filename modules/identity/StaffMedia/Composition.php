<?php
declare(strict_types=1);
namespace Identity\StaffMedia;
use Identity\Http\IdentityHttpComposition;
final class Composition
{
    public static function fromIdentity(IdentityHttpComposition $identity):Service
    {
        if(getenv('MXMED_STAFF_PRIVATE_MEDIA_ENABLED')!=='1')throw new \RuntimeException('staff_media_disabled');
        \Identity\StaffAdmission\Composition::fromIdentity($identity);
        // Neither an env flag nor a synthetic adapter can ratify custody, limits or reader authority.
        throw new \RuntimeException('staff_media_production_policy_unavailable');
    }
    public static function sourceHash():string
    {
        $root=dirname(__DIR__,3);$files=glob(__DIR__.'/*.php');$files[]=$root.'/modules/identity/db/migrations/2026_10_18_06_staff_private_media.sql';sort($files);
        $hash=[];foreach($files as $f)$hash[substr($f,strlen($root)+1)]=hash_file('sha256',$f);return hash('sha256',json_encode($hash,JSON_THROW_ON_ERROR));
    }
}
