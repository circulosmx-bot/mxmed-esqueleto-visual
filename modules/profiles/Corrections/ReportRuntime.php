<?php
declare(strict_types=1);
namespace Profiles\Corrections;

/** Explicit dependency injection only; no env selector, default keys or automatic live activation. */
final class ReportRuntime
{
    private static ?ReportHttp $http=null;
    public static function install(ReportHttp $http):void
    {if(self::$http!==null)throw new \RuntimeException('correction_runtime_already_installed');self::$http=$http;}
    public static function current():?ReportHttp{\Identity\Operational\OperationalRuntime::boot();return self::$http;}
    public static function form(string $profile,string $route):?array
    {try{return self::current()?->form($profile,$route,$_SERVER,$_COOKIE);}catch(\Throwable){return null;}}
}
