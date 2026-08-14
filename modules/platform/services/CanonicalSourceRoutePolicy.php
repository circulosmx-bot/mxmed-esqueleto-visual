<?php
declare(strict_types=1);
namespace Platform\Services;
final class CanonicalSourceRoutePolicy
{
    public function __construct(private array $httpTemplates,private array $jobNames,private array $cliNames,private array $internalNames)
    {
        foreach($httpTemplates as $v)if(!is_string($v)||!str_starts_with($v,'/')||strpbrk($v,'?#')!==false||str_contains($v,'://'))throw new \InvalidArgumentException('invalid_http_route_allowlist');
        foreach(array_merge($jobNames,$cliNames,$internalNames) as $v)if(!is_string($v)||preg_match('/^[a-z][a-z0-9-]*$/D',$v)!==1)throw new \InvalidArgumentException('invalid_non_http_allowlist');
    }
    public function http(string $method,string $template):string
    {
        if($method!==strtoupper($method)||preg_match('/^[A-Z]+$/D',$method)!==1)throw new \InvalidArgumentException('invalid_http_method');
        if(!in_array($template,$this->httpTemplates,true))throw new \InvalidArgumentException('untrusted_or_noncanonical_route_template');
        return $method.' '.$template;
    }
    public function nonHttp(string $namespace,string $name):string
    {
        $map=['JOB'=>$this->jobNames,'CLI'=>$this->cliNames,'INTERNAL'=>$this->internalNames];
        if(!isset($map[$namespace])||!in_array($name,$map[$namespace],true))throw new \InvalidArgumentException('untrusted_non_http_route');
        return $namespace.':'.$name;
    }
}
