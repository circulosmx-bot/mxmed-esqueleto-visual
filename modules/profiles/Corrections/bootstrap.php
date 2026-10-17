<?php
declare(strict_types=1);
require_once __DIR__.'/../../identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
spl_autoload_register(static function(string $class):void {
    $prefix='Profiles\\Corrections\\';
    if(str_starts_with($class,$prefix)) {
        $file=__DIR__.'/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
        if(is_file($file))require_once $file;
    }
});
