<?php
declare(strict_types=1);
namespace Identity\Accreditation;

/** No process/env auto-activation. An explicit composition must be supplied by its owner.
 * The only consumer installing one today is the new disposable synthetic QA router. */
final class PublicEnrollmentRuntime
{
    private static ?PublicEnrollmentHttp $http=null;
    public static function install(PublicEnrollmentHttp $http):void
    {if(self::$http!==null)throw new \RuntimeException('enrollment_composition_already_installed');self::$http=$http;}
    public static function current():?PublicEnrollmentHttp{\Identity\Operational\OperationalRuntime::boot();return self::$http;}
    public static function identityReturn():?string{return self::current()?->returnPath($_GET,$_COOKIE);}
    public static function notifications(\Identity\Contracts\IdentityNotificationPort $inner):\Identity\Contracts\IdentityNotificationPort
    {return self::current()===null?$inner:new EnrollmentNotificationAdapter($inner,self::current());}
}
