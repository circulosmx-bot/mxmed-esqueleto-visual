<?php
declare(strict_types=1);
/** Temporary local adapter. Never loaded by normal production composition. */
function link03Administration(array $services):\Identity\PhysicianAdministration\Reader
{
    $owned=realpath((string)getenv('ASE_INT02A_OWNED'));if(!$owned)throw new RuntimeException('admin_owned_required');
    $read=function(string $path):array{if(is_link($path)||!is_file($path)||fileowner($path)!==posix_geteuid()||(fileperms($path)&0777)!==0600)throw new RuntimeException('admin_configuration_unavailable');return json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);};
    $config=$read($owned.'/administration-source.json');$secret=$read($config['secret_file']);
    if(($secret['stage']??'')!=='ready'||$secret['host']!=='127.0.0.1'||$secret['database']!=='mxmed'||$config['origin']!==$secret['origin'])throw new RuntimeException('admin_configuration_unavailable');
    $connect=static function(bool $operators)use($secret):PDO{
        $key=$operators?'operator':'summary';
        $pdo=new PDO('mysql:host='.$secret['host'].';port='.(int)$secret['port'].';dbname='.$secret['database'].';charset=utf8mb4',$secret[$key.'_user'],$secret[$key.'_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>2]);
        if($pdo->query('SELECT @@server_uuid')->fetchColumn()!==$secret['server_uuid'])throw new RuntimeException('admin_source_unavailable');return $pdo;
    };
    $scope=static function(string $actor,string $origin)use($read,$owned):array{$v=$read($owned.'/administration-source.json');if(($v['expires']??0)<=time()||$v['origin']!==$origin)throw new RuntimeException('admin_scope_denied');return $v['staff'][$actor]??[];};
    return new \Identity\PhysicianAdministration\Reader($services['authority'],new \Identity\PhysicianAdministration\SqlSource($connect,$secret['prefix'],false,(int)($secret['projection_version']??1)),$config['origin'],$scope);
}
