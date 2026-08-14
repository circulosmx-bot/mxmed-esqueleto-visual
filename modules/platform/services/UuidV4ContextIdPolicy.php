<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\ContextIdPolicy;
final class UuidV4ContextIdPolicy implements ContextIdPolicy
{
    public const PATTERN='/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';
    public function assertRequestId(string $v):void{$this->assert($v,'request');}
    public function assertCorrelationId(string $v):void{$this->assert($v,'correlation');}
    private function assert(string $v,string $kind):void{if(strlen($v)!==36||preg_match(self::PATTERN,$v)!==1)throw new \InvalidArgumentException('invalid_'.$kind.'_uuid_v4');}
}
